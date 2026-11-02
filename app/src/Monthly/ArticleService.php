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
        }, $this->db->all('SELECT id, no, source_name, published_on, title, description, url, recommendation, reason, decision, uploaded, upload_status, url_hu, url_en, post_code, from_new_source, wp_state, upload_error, upload_attempts
            FROM articles WHERE client_id = ? AND month = ? ORDER BY from_new_source, COALESCE(no, 9999), published_on, id', [$cid, $month]));
    }

    /** @return array<string,mixed>|null */
    public function report(Access $a, string $month): ?array
    {
        $r = $a->clientId() === null ? null : $this->db->one('SELECT summary_rows, details, stats, model, created_at FROM month_reports WHERE client_id = ? AND month = ?', [$a->clientId(), $month]);
        if ($r === null) {
            return null;
        }
        $r['summary_rows'] = json_decode((string) $r['summary_rows'], true) ?: [];
        $r['stats'] = json_decode((string) $r['stats'], true) ?: [];
        return $r;
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
