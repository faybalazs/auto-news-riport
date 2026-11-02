<?php
declare(strict_types=1);

namespace Xperion\Posts;

use Xperion\Auth\Access;
use Xperion\Auth\AuditLog;
use Xperion\Db\Database;

/** Posztötletek (product.md 4.2): Attila és Emi viszi fel, innen mennek posztgenerálásra. */
final class IdeaService
{
    public const STATUS = ['uj' => 'Új', 'elkeszult' => 'Elkészült', 'jovahagyva' => 'Jóváhagyva', 'utemezve' => 'Ütemezve', 'elvetve' => 'Elvetve'];
    public const TYPES = ['szakmai', 'rendezvény', 'díj', 'partnerség', 'esettanulmány', 'saját adat', 'csapat'];

    public function __construct(private Database $db, private AuditLog $audit)
    {
    }

    /** @return list<array<string,mixed>> a legfrissebb elöl */
    public function all(Access $a): array
    {
        $cid = $a->clientId();
        if ($cid === null) {
            return [];
        }
        return array_map([self::class, 'shape'], $this->db->all('SELECT i.*, (SELECT COUNT(*) FROM post_jobs j WHERE j.client_id = i.client_id AND j.kind = \'otlet\' AND j.ref = i.code) AS pending
            FROM post_ideas i WHERE i.client_id = ? ORDER BY i.created_at DESC, i.id DESC', [$cid]));
    }

    /** @return array<string,mixed>|null */
    public function get(Access $a, string $code): ?array
    {
        $cid = $a->clientId();
        $r = $cid === null ? null : $this->db->one('SELECT i.*, (SELECT COUNT(*) FROM post_jobs j WHERE j.client_id = i.client_id AND j.kind = \'otlet\' AND j.ref = i.code) AS pending
            FROM post_ideas i WHERE i.client_id = ? AND i.code = ?', [$cid, $code]);
        return $r === null ? null : self::shape($r);
    }

    /**
     * Új ötlet (code = '') vagy meglévő módosítása.
     *
     * @param array<string,mixed> $in submitter, topic, message, link, type, wanted_week, status, note
     * @return array<string,mixed>
     */
    public function save(Access $a, string $code, array $in): array
    {
        $a->require('ideas');
        $cid = $a->clientId() ?? throw new \InvalidArgumentException('Nincs kiválasztott ügyfél.');
        $f = [
            'submitter' => mb_substr(trim((string) ($in['submitter'] ?? '')), 0, 150),
            'topic' => mb_substr(trim((string) ($in['topic'] ?? '')), 0, 255),
            'message' => trim((string) ($in['message'] ?? '')),
            'link' => mb_substr(trim((string) ($in['link'] ?? '')), 0, 1000),
            'type' => mb_substr(trim((string) ($in['type'] ?? '')), 0, 40),
            'wanted_week' => mb_substr(trim((string) ($in['wanted_week'] ?? '')), 0, 60),
            'note' => trim((string) ($in['note'] ?? '')),
        ];
        if ($f['topic'] === '' || $f['message'] === '') {
            throw new \InvalidArgumentException('A téma és a fő üzenet kötelező.');
        }
        if ($f['link'] !== '' && !preg_match('~^https?://~i', $f['link'])) {
            throw new \InvalidArgumentException('A link https://-sel kezdődjön.');
        }
        $status = (string) ($in['status'] ?? '');
        $now = Database::now();
        if ($code === '') {
            if ($f['submitter'] === '') {
                $f['submitter'] = (string) $this->db->value('SELECT name FROM users WHERE id = ?', [$a->userId()]);
            }
            $code = $this->nextCode($cid);
            $this->db->insert('post_ideas', $f + ['client_id' => $cid, 'code' => $code, 'status' => 'uj', 'created_by' => $a->userId(), 'created_at' => $now, 'updated_at' => $now]);
            $this->audit->add($cid, $a->userId(), 'Posztötlet beküldve', $code . ' · ' . $f['topic']);
        } else {
            $old = $this->db->one('SELECT id FROM post_ideas WHERE client_id = ? AND code = ?', [$cid, $code]) ?? throw new \InvalidArgumentException('Nincs ilyen ötlet.');
            $set = $f + ['updated_at' => $now];
            if (array_key_exists($status, self::STATUS)) {
                $set['status'] = $status;
            }
            $this->db->run('UPDATE post_ideas SET ' . implode(', ', array_map(static fn ($k) => "$k = ?", array_keys($set))) . ' WHERE id = ?', [...array_values($set), $old['id']]);
            $this->audit->add($cid, $a->userId(), 'Posztötlet módosítva', $code . ' · ' . $f['topic']);
        }
        return $this->get($a, $code) ?? [];
    }

    public function delete(Access $a, string $code): void
    {
        $a->require('ideas');
        $cid = $a->clientId() ?? throw new \InvalidArgumentException('Nincs kiválasztott ügyfél.');
        $row = $this->db->one('SELECT id, topic FROM post_ideas WHERE client_id = ? AND code = ?', [$cid, $code]) ?? throw new \InvalidArgumentException('Nincs ilyen ötlet.');
        $this->db->transaction(function () use ($row, $cid, $code): void {
            $this->db->run('UPDATE posts SET idea_id = NULL WHERE client_id = ? AND idea_id = ?', [$cid, $row['id']]);
            $this->db->run("DELETE FROM post_jobs WHERE client_id = ? AND kind = 'otlet' AND ref = ?", [$cid, $code]);
            $this->db->run('DELETE FROM post_ideas WHERE id = ?', [$row['id']]);
        });
        $this->audit->add($cid, $a->userId(), 'Posztötlet törölve', $code . ' · ' . $row['topic']);
    }

    private function nextCode(int $cid): string
    {
        $prefix = 'OT-' . (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Budapest')))->format('ym') . '-';
        $n = 0;
        foreach ($this->db->all('SELECT code FROM post_ideas WHERE client_id = ? AND code LIKE ?', [$cid, $prefix . '%']) as $r) {
            $n = max($n, (int) substr((string) $r['code'], strlen($prefix)));
        }
        return $prefix . str_pad((string) ($n + 1), 2, '0', STR_PAD_LEFT);
    }

    /** @param array<string,mixed> $r @return array<string,mixed> */
    private static function shape(array $r): array
    {
        return [
            'code' => (string) $r['code'], 'submitter' => (string) $r['submitter'], 'topic' => (string) $r['topic'], 'message' => (string) ($r['message'] ?? ''),
            'link' => (string) $r['link'], 'type' => (string) $r['type'], 'wanted_week' => (string) $r['wanted_week'], 'status' => (string) $r['status'],
            'status_label' => self::STATUS[$r['status']] ?? (string) $r['status'], 'post_code' => (string) $r['post_code'], 'note' => (string) ($r['note'] ?? ''),
            'pending' => (int) ($r['pending'] ?? 0) > 0, 'created_at' => (string) $r['created_at'],
        ];
    }
}
