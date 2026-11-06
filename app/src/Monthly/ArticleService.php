<?php
declare(strict_types=1);

namespace Xperion\Monthly;

use Xperion\Auth\Access;
use Xperion\Auth\AuditLog;
use Xperion\Db\Database;

/** Havi cikklisták olvasása és az ügyfél döntései, mindig az aktuális ügyfélre szűrve. */
final class ArticleService
{
    public const DECISIONS = ['' => 'Nincs döntés', 'mehet' => 'Mehet', 'linkedin' => 'LinkedIn poszt', 'nemkell' => 'Nem kell'];

    public function __construct(private Database $db, private AuditLog $audit)
    {
    }

    /** @return list<array{month:string, total:int, open:int}> a legfrissebb elöl */
    public function months(Access $a): array
    {
        $cid = $a->clientId();
        if ($cid === null) {
            return [];
        }
        return array_map(static fn ($r) => ['month' => (string) $r['month'], 'total' => (int) $r['total'], 'open' => (int) $r['open']], $this->db->all(
            "SELECT month, COUNT(*) AS total, SUM(decision = '' AND uploaded = 0 AND recommendation NOT LIKE 'Nem javasolt%') AS open FROM articles WHERE client_id = ? GROUP BY month ORDER BY month DESC", [$cid]));
    }

    /** @return list<array<string,mixed>> */
    public function list(Access $a, string $month): array
    {
        $cid = $a->clientId();
        if ($cid === null || !preg_match('/^\d{4}$/', $month)) {
            return [];
        }
        return array_map(static function ($r) {
            foreach (['id', 'no', 'uploaded', 'from_new_source', 'upload_attempts'] as $k) {
                $r[$k] = $r[$k] === null ? null : (int) $r[$k];
            }
            return $r;
        }, $this->db->all('SELECT id, no, source_name, published_on, title, description, url, recommendation, reason, decision, uploaded, upload_status, url_hu, url_en, post_code, from_new_source, wp_state, upload_error, upload_attempts, hu_title, hu_summary, en_title, en_summary
            FROM articles WHERE client_id = ? AND month = ? ORDER BY from_new_source, COALESCE(no, 9999), published_on, id', [$cid, $month]));
    }

    /** @return array<string,mixed>|null */
    public function report(Access $a, string $month): ?array
    {
        $r = $a->clientId() === null ? null : $this->db->one('SELECT summary_rows, details, sections, stats, model, created_at, updated_at FROM month_reports WHERE client_id = ? AND month = ?', [$a->clientId(), $month]);
        if ($r === null) {
            return null;
        }
        $r['summary_rows'] = json_decode((string) $r['summary_rows'], true) ?: [];
        $r['stats'] = json_decode((string) $r['stats'], true) ?: [];
        $r['sections'] = Summarizer::cleanSections(json_decode((string) ($r['sections'] ?? ''), true));
        $r['updated_at'] = (string) ($r['updated_at'] ?? '');
        return $r;
    }

    /**
     * Cikk törlése a listából, visszaállíthatóan (a sor a deleted_articles táblába kerül). Feltöltött cikk nem törölhető.
     */
    public function remove(Access $a, int $id): void
    {
        $a->require('decide');
        $cid = $a->clientId() ?? throw new \InvalidArgumentException('Nincs kiválasztott ügyfél.');
        $this->db->transaction(function () use ($a, $cid, $id): void {
            $row = $this->db->one('SELECT * FROM articles WHERE id = ? AND client_id = ?', [$id, $cid]) ?? throw new \InvalidArgumentException('Nincs ilyen cikk.');
            if ((int) $row['uploaded'] === 1) {
                throw new \InvalidArgumentException('Publikált cikk nem törölhető. Előbb vonja vissza a feltöltést (a sor ⋯ menüje → Feltöltés visszavonása).');
            }
            $this->db->run('DELETE FROM deleted_articles WHERE id = ?', [$id]);
            $this->db->insert('deleted_articles', ['id' => $id, 'client_id' => $cid, 'month' => (string) $row['month'], 'data' => json_encode($row, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'deleted_by' => $a->userId(), 'deleted_at' => Database::now()]);
            $this->db->run('DELETE FROM articles WHERE id = ? AND client_id = ?', [$id, $cid]);
            $this->audit->add($cid, $a->userId(), 'Cikk törölve', $row['month'] . '/' . ($row['no'] ?? $row['id']) . ' · ' . mb_substr((string) $row['title'], 0, 120));
        });
    }

    /** Törölt cikk visszaállítása ugyanazzal az azonosítóval. @return array<string,mixed> a cikk listabeli alakja */
    public function restore(Access $a, int $id): array
    {
        $a->require('decide');
        $cid = $a->clientId() ?? throw new \InvalidArgumentException('Nincs kiválasztott ügyfél.');
        $this->db->transaction(function () use ($a, $cid, $id): void {
            $d = $this->db->one('SELECT data FROM deleted_articles WHERE id = ? AND client_id = ?', [$id, $cid]) ?? throw new \InvalidArgumentException('Ez a cikk nem állítható vissza.');
            $row = json_decode((string) $d['data'], true, 512, JSON_THROW_ON_ERROR);
            try {
                $this->db->insert('articles', $row);
            } catch (\PDOException $e) {
                throw new \InvalidArgumentException('A cikk nem állítható vissza: közben újra bekerült a listába (ugyanazzal a címmel).');
            }
            $this->db->run('DELETE FROM deleted_articles WHERE id = ?', [$id]);
            $this->audit->add($cid, $a->userId(), 'Cikk visszaállítva', $row['month'] . '/' . ($row['no'] ?? $row['id']) . ' · ' . mb_substr((string) $row['title'], 0, 120));
        });
        $month = (string) $this->db->value('SELECT month FROM articles WHERE id = ?', [$id]);
        foreach ($this->list($a, $month) as $r) {
            if ($r['id'] === $id) {
                return $r;
            }
        }
        return [];
    }

    /**
     * Döntések rögzítése. Csak az aktuális ügyfél cikkeire, és csak még fel nem töltött cikkre.
     *
     * @param list<array{id:int, decision:string}> $changes
     * @return int a módosított cikkek száma
     */
    public function decide(Access $a, array $changes): int
    {
        $a->require('decide');
        $cid = $a->clientId();
        if ($cid === null) {
            throw new \InvalidArgumentException('Nincs kiválasztott ügyfél.');
        }
        $n = 0;
        $labels = [];
        $this->db->transaction(function () use ($changes, $cid, &$n, &$labels): void {
            foreach ($changes as $c) {
                $d = (string) ($c['decision'] ?? '');
                if (!array_key_exists($d, self::DECISIONS)) {
                    throw new \InvalidArgumentException('Ismeretlen döntés.');
                }
                $row = $this->db->one('SELECT id, no, month, uploaded FROM articles WHERE id = ? AND client_id = ?', [(int) ($c['id'] ?? 0), $cid]);
                if ($row === null) {
                    throw new \InvalidArgumentException('Nincs ilyen cikk.');
                }
                if ((int) $row['uploaded'] === 1) {
                    continue; // feltöltött cikk döntése már nem módosítható
                }
                $n += $this->db->run('UPDATE articles SET decision = ?, updated_at = ? WHERE id = ? AND client_id = ?', [$d, Database::now(), $row['id'], $cid])->rowCount();
                $labels[self::DECISIONS[$d]][] = $row['month'] . '/' . ($row['no'] ?? $row['id']);
            }
        });
        foreach ($labels as $label => $refs) {
            $this->audit->add($cid, $a->userId(), 'Döntés: ' . count($refs) . ' cikk → ' . $label, implode(', ', $refs));
        }
        return $n;
    }
}
