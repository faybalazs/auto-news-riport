<?php
declare(strict_types=1);

namespace Xperion\Auth;

use Xperion\Db\Database;
use Xperion\Import\Url;

/** Ügyfelek kezelése (csak üzemeltető). */
final class ClientAdmin
{
    public const STATUS_LABELS = ['aktiv' => 'Aktív', 'beallitas' => 'Beállítás alatt', 'szunetel' => 'Szüneteltetve'];

    public function __construct(private Database $db, private AuditLog $audit)
    {
    }

    /** @return list<array<string,mixed>> */
    public function all(Access $a): array
    {
        $a->require('ops');
        return array_map(static function ($c) {
            $c['id'] = (int) $c['id'];
            $c['users'] = (int) $c['users'];
            foreach (['sources', 'keywords', 'articles', 'posts', 'rules'] as $k) {
                $c[$k] = (int) $c[$k];
            }
            return $c;
        }, $this->db->all('SELECT c.id, c.name, c.website, c.spreadsheet_id, c.status, c.created_at,
            (SELECT COUNT(*) FROM memberships m WHERE m.client_id = c.id) AS users,
            (SELECT COUNT(*) FROM sources s WHERE s.client_id = c.id) AS sources,
            (SELECT COUNT(*) FROM keywords k WHERE k.client_id = c.id) AS keywords,
            (SELECT COUNT(*) FROM articles a WHERE a.client_id = c.id) AS articles,
            (SELECT COUNT(*) FROM posts p WHERE p.client_id = c.id) AS posts,
            (SELECT COUNT(*) FROM post_rules r WHERE r.client_id = c.id) AS rules
            FROM clients c ORDER BY c.name'));
    }

    public function create(Access $a, string $name, string $website, string $sheet = ''): int
    {
        $a->require('ops');
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Adja meg az ügyfél nevét.');
        }
        if ($this->db->value('SELECT 1 FROM clients WHERE name = ?', [$name])) {
            throw new \InvalidArgumentException('Ilyen nevű ügyfél már van.');
        }
        $id = $this->db->insert('clients', ['name' => $name, 'website' => self::cleanSite($website), 'spreadsheet_id' => self::sheetId($sheet), 'status' => 'beallitas', 'created_at' => Database::now()]);
        $this->audit->add($id, $a->userId(), 'Ügyfél létrehozva', $name);
        return $id;
    }

    public function update(Access $a, int $id, string $name, string $website, string $status, string $sheet = ''): void
    {
        $a->require('ops');
        if (!isset(self::STATUS_LABELS[$status])) {
            throw new \InvalidArgumentException('Ismeretlen állapot.');
        }
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Adja meg az ügyfél nevét.');
        }
        if ($this->db->value('SELECT 1 FROM clients WHERE name = ? AND id <> ?', [$name, $id])) {
            throw new \InvalidArgumentException('Ilyen nevű ügyfél már van.');
        }
        if ($this->db->update('clients', ['name' => $name, 'website' => self::cleanSite($website), 'spreadsheet_id' => self::sheetId($sheet), 'status' => $status], ['id' => $id]) === 0
            && !$this->db->value('SELECT 1 FROM clients WHERE id = ?', [$id])) {
            throw new \InvalidArgumentException('Nincs ilyen ügyfél.');
        }
        $this->audit->add($id, $a->userId(), 'Ügyfél módosítva', $name . ' · ' . self::STATUS_LABELS[$status]);
    }

    /** Üres megadás: nincs Sheet; érvénytelen: hiba. */
    private static function sheetId(string $input): string
    {
        if (trim($input) === '') {
            return '';
        }
        $id = Url::spreadsheetId($input);
        if ($id === '') {
            throw new \InvalidArgumentException('A Google Sheet linkje vagy azonosítója nem értelmezhető. Másolja be a tábla teljes linkjét a böngésző címsorából.');
        }
        return $id;
    }

    private static function cleanSite(string $site): string
    {
        $site = trim($site);
        $site = preg_replace('#^https?://#i', '', $site) ?? '';
        return rtrim($site, '/');
    }

    public const SETTINGS = ['linkedin_nev', 'linkedin_kovetok', 'modell_ertekeles', 'modell_poszt', 'modell_linkedin', 'modell_feltoltes'];
    public const MODELS = ['claude-haiku-5-5', 'claude-sonnet-5-5', 'claude-opus-5-5'];

    /** @return array<string,string> az ügyfél felületről szerkeszthető beállításai */
    public function settings(int $clientId): array
    {
        $out = array_fill_keys(self::SETTINGS, '');
        foreach ($this->db->all('SELECT name, value FROM client_settings WHERE client_id = ? AND name IN (' . implode(',', array_fill(0, count(self::SETTINGS), '?')) . ')', [$clientId, ...self::SETTINGS]) as $r) {
            $out[(string) $r['name']] = trim((string) $r['value']);
        }
        return $out;
    }

    /** @param array<string,mixed> $in @return array<string,string> */
    public function saveSettings(Access $a, array $in): array
    {
        $a->require('ops');
        $cid = $a->clientId() ?? throw new \InvalidArgumentException('Nincs kiválasztott ügyfél.');
        $now = Database::now();
        foreach (self::SETTINGS as $k) {
            if (!array_key_exists($k, $in)) {
                continue;
            }
            $v = mb_substr(trim((string) $in[$k]), 0, 190);
            if (str_starts_with($k, 'modell_') && $v !== '' && !in_array($v, self::MODELS, true)) {
                throw new \InvalidArgumentException('Ismeretlen modell: ' . $v);
            }
            $this->db->run('INSERT INTO client_settings (client_id, name, value, updated_at) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = VALUES(updated_at)', [$cid, $k, $v, $now]);
        }
        $this->audit->add($cid, $a->userId(), 'Ügyfélbeállítások módosítva', implode(', ', array_intersect(self::SETTINGS, array_keys($in))));
        return $this->settings($cid);
    }

    /**
     * Az új ügyfél beállítási lépései valódi állapottal.
     *
     * @return list<array{title:string, sub:string, done:bool, href:string, cta:string}>
     */
    public function onboarding(int $cid, ?array $wordpressSite): array
    {
        $c = $this->db->one('SELECT name, website, status FROM clients WHERE id = ?', [$cid]);
        if ($c === null) {
            return [];
        }
        $n = fn (string $sql) => (int) $this->db->value($sql, [$cid]);
        $users = $n('SELECT COUNT(*) FROM memberships WHERE client_id = ?');
        $sources = $n('SELECT COUNT(*) FROM sources WHERE client_id = ? AND active = 1');
        $keywords = $n('SELECT COUNT(*) FROM keywords WHERE client_id = ?');
        $rules = $n('SELECT COUNT(*) FROM post_rules WHERE client_id = ?');
        $mailTo = (string) $this->db->value("SELECT value FROM client_settings WHERE client_id = ? AND name = 'ugyfel_email'", [$cid]);
        $reports = $n('SELECT COUNT(*) FROM month_reports WHERE client_id = ?');
        return [
            ['title' => 'Alapadatok', 'sub' => $c['website'] !== '' ? $c['name'] . ' · ' . $c['website'] : 'a weboldal hiányzik (a feltöltéshez és a képekhez kell)', 'done' => $c['website'] !== '', 'href' => 'index.php?p=ugyfelek', 'cta' => 'Szerkesztés a sor ⋯ menüjében'],
            ['title' => 'Felhasználók meghívása', 'sub' => $users ? $users . ' felhasználó' : 'ügyfél-adminisztrátor, tartalomkezelő', 'done' => $users > 0, 'href' => 'index.php?p=felhasznalok', 'cta' => 'Felhasználók'],
            ['title' => 'Források és kulcsszavak', 'sub' => $sources ? "{$sources} aktív forrás, {$keywords} kulcsszó" : 'adatátvétel a Google Sheetből, vagy felvétel kézzel', 'done' => $sources > 0, 'href' => 'index.php?p=forrasok', 'cta' => 'Források'],
            ['title' => 'Posztszabályok', 'sub' => $rules ? "{$rules} szabály" : 'a LinkedIn-posztokhoz kell', 'done' => $rules > 0, 'href' => 'index.php?p=szabalyok', 'cta' => 'Posztszabályok'],
            ['title' => 'Havi e-mail címzettje', 'sub' => $mailTo !== '' ? $mailTo : 'a havi levélhez kell', 'done' => $mailTo !== '', 'href' => 'index.php?p=ugyfelek#havi-email', 'cta' => 'Beállítás'],
            ['title' => 'Weboldal-kapcsolat (WordPress)', 'sub' => $wordpressSite ? $wordpressSite['base_url'] . ' · a Vezérlőpulton ellenőrizhető' : 'config.php „wordpress” rész az ügyfél weboldala szerint', 'done' => $wordpressSite !== null, 'href' => 'index.php?p=vezerlo', 'cta' => 'Vezérlőpult'],
            ['title' => 'Aktív állapot', 'sub' => $c['status'] === 'aktiv' ? 'a havi és az óránkénti futás fut' : 'amíg nem Aktív, a cron kihagyja', 'done' => $c['status'] === 'aktiv', 'href' => 'index.php?p=ugyfelek', 'cta' => 'Szerkesztés a sor ⋯ menüjében'],
            ['title' => 'Első havi futás', 'sub' => $reports ? $reports . ' havi lista készült' : 'Vezérlőpult → Havi futás szárazon, majd élesben', 'done' => $reports > 0, 'href' => 'index.php?p=vezerlo', 'cta' => 'Vezérlőpult'],
        ];
    }
}
