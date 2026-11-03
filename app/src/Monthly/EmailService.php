<?php
declare(strict_types=1);

namespace Xperion\Monthly;

use Xperion\Auth\Access;
use Xperion\Auth\AuditLog;
use Xperion\Claude\ClaudeClient;
use Xperion\Db\Database;
use Xperion\Mail\MailException;
use Xperion\Mail\Mailer;
use Xperion\Mail\Message;
use Xperion\Mail\TextToHtml;
use Xperion\Web\View;

/**
 * Havi e-mail az ügyfélnek (product.md 6.1/8–9, 6.4): Claude írja a hónap adataiból, csak az üzemeltető
 * kap értesítést; az ügyfélnek csak a „Jóváhagyom, mehet” után megy ki, azonnal, egyszer. Piszkozat nincs.
 */
final class EmailService
{
    public const STATUS = ['jovahagyasra_var' => 'Jóváhagyásra vár', 'kuldes' => 'Küldés folyamatban', 'elkuldve' => 'Elküldve', 'hiba' => 'Hiba'];

    public const DEFAULT_STYLE = "A csapat nevében, többes számban; tárgyilagos, lényegre törő, udvarias.\n"
        . "Rövid bevezető, utána a Havi cikkek link, hogy döntsenek (Mehet / LinkedIn poszt / Nem kell).\n"
        . "Újdonságok pontokban, „Címke: leírás” formában (pl. új forrás, kiemelt téma, erősen javasolt cikkek).\n"
        . "A hónap számai egy rövid felsorolásban. Ne ismételje, ami a listából kiderül, és ne írjon a felület működéséről.\n"
        . 'Aláírás: Üdvözlettel: az Auris Dynamics csapata';

    public function __construct(
        private Database $db,
        private ClaudeClient $claude,
        private Mailer $mailer,
        private AuditLog $audit,
        private string $baseUrl,
    ) {
    }

    public function mailerReady(): bool
    {
        return $this->mailer->configured();
    }

    /** Ha nincs beállítva, miért nem (a felületre). */
    public function mailerProblem(): string
    {
        if ($this->mailer->configured()) {
            return '';
        }
        return $this->mailer instanceof \Xperion\Mail\SmtpMailer ? implode('; ', $this->mailer->problemList()) : 'nincs beállítva';
    }

    /** @return array{to:string, cc:string, style:string} */
    public function settings(int $clientId): array
    {
        $s = ['ugyfel_email' => '', 'ugyfel_cc' => '', 'email_stilus' => ''];
        foreach ($this->db->all("SELECT name, value FROM client_settings WHERE client_id = ? AND name IN ('ugyfel_email','ugyfel_cc','email_stilus')", [$clientId]) as $r) {
            $s[(string) $r['name']] = trim((string) $r['value']);
        }
        return ['to' => $s['ugyfel_email'], 'cc' => $s['ugyfel_cc'], 'style' => $s['email_stilus']];
    }

    /** @return array{to:string, cc:string, style:string} */
    public function saveSettings(Access $a, string $to, string $cc, string $style): array
    {
        $a->require('ops');
        $cid = $this->clientOf($a);
        foreach ([...Message::parseList($to), ...Message::parseList($cc)] as $addr) {
            if (!Message::validAddress($addr)) {
                throw new \InvalidArgumentException('Érvénytelen e-mail-cím: ' . $addr);
            }
        }
        $now = Database::now();
        $toList = Message::parseList($to);
        foreach (['ugyfel_email' => implode(', ', $toList), 'ugyfel_cc' => implode(', ', array_diff(Message::parseList($cc), $toList)), 'email_stilus' => mb_substr(trim($style), 0, 3000)] as $k => $v) {
            $this->db->run('INSERT INTO client_settings (client_id, name, value, updated_at) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = VALUES(updated_at)', [$cid, $k, $v, $now]);
        }
        $saved = $this->settings($cid);
        $this->audit->add($cid, $a->userId(), 'Havi e-mail beállításai', 'Címzett: ' . ($saved['to'] !== '' ? $saved['to'] : '–') . ($saved['cc'] !== '' ? '; másolat: ' . $saved['cc'] : ''));
        return $saved;
    }

    /** @return list<string> hónapok, amelyekhez levél írható (van lista vagy összefoglaló), a legfrissebb elöl */
    public function months(Access $a): array
    {
        $cid = $a->clientId();
        if ($cid === null) {
            return [];
        }
        $m = array_map(static fn ($r) => (string) $r['month'], $this->db->all('SELECT month FROM month_reports WHERE client_id = ? UNION SELECT DISTINCT month FROM articles WHERE client_id = ?', [$cid, $cid]));
        rsort($m, SORT_STRING);
        return $m;
    }

    /** @return array<string,mixed>|null */
    public function get(Access $a, string $month): ?array
    {
        $cid = $a->clientId();
        return $cid === null ? null : $this->row($cid, $month);
    }

    /** Kézi generálás a Vezérlőpultról (üzemeltető). Szárazon csak megmutatja. @return array<string,mixed> */
    public function generate(Access $a, string $month, bool $dry): array
    {
        $a->require('ops');
        // Kézi generálásnál az üzemeltető épp a felületen van: külön értesítés nem kell.
        return $this->generateForClient($this->clientOf($a), $month, $dry, $a->userId(), false);
    }

    /**
     * A levél megírása (a havi futás élesben is ezt hívja). Élesben menti „Jóváhagyásra vár” állapotban,
     * és értesíti az üzemeltető(ke)t. Elküldött levelet nem ír felül.
     *
     * @return array<string,mixed> a levél adatai, plusz saved, notified, notice
     */
    public function generateForClient(int $cid, string $month, bool $dry, ?int $userId = null, bool $notify = true): array
    {
        self::checkMonth($month);
        $existing = $this->row($cid, $month);
        if ($existing !== null && in_array($existing['status'], ['elkuldve', 'kuldes'], true)) {
            throw new \InvalidArgumentException('Ennek a hónapnak a levele már ' . ($existing['status'] === 'elkuldve' ? 'elment' : 'küldés alatt van') . '; nem írom újra.');
        }
        if (!$this->claude->hasKey()) {
            throw new \Xperion\Claude\ClaudeException('Nincs beállítva a Claude API-kulcs (config.php: claude_api_key).');
        }
        $client = $this->db->one('SELECT id, name FROM clients WHERE id = ?', [$cid]) ?? throw new \InvalidArgumentException('Nincs ilyen ügyfél.');
        $report = $this->db->one('SELECT summary_rows, details, sections FROM month_reports WHERE client_id = ? AND month = ?', [$cid, $month]);
        $arts = $this->db->all('SELECT source_name, title, recommendation, from_new_source, decision, uploaded FROM articles WHERE client_id = ? AND month = ? ORDER BY COALESCE(no, 9999), id', [$cid, $month]);
        if ($report === null && !$arts) {
            throw new \InvalidArgumentException('Ehhez a hónaphoz még nincs havi lista. Előbb futtassa a havi futást.');
        }
        $settings = $this->settings($cid);
        $model = (string) ($this->db->value("SELECT value FROM client_settings WHERE client_id = ? AND name = 'modell_poszt'", [$cid]) ?: MonthlyService::DEFAULT_SUMMARY_MODEL);
        $label = self::monthLabel($month);
        $link = $this->baseUrl !== '' ? $this->baseUrl . 'index.php?p=cikkek&honap=' . $month : '';
        $recipientName = '';
        if (($first = Message::parseList($settings['to'])[0] ?? '') !== '') {
            $recipientName = (string) $this->db->value('SELECT name FROM users WHERE email = ?', [$first]);
        }
        $by = array_count_values(array_column($arts, 'recommendation'));
        $src = array_count_values(array_column($arts, 'source_name'));
        arsort($src);
        $facts = [
            'ügyfél' => $client['name'],
            'hónap' => $label,
            'havi_cikkek_link' => $link !== '' ? $link : '(nincs link; hagyd ki)',
            'megszólított' => $recipientName !== '' ? $recipientName . ' (magyar névsorrend: a keresztnév az utolsó szó)' : '(ismeretlen; általános megszólítás)',
            'vezetői_összefoglaló' => $report ? array_filter(['sorok' => json_decode((string) $report['summary_rows'], true) ?: [], 'bevezető' => (string) $report['details'], 'csoportok' => Summarizer::cleanSections(json_decode((string) ($report['sections'] ?? ''), true))]) : null,
            'számok' => ['cikkek' => count($arts), 'javaslatok' => $by, 'források' => array_slice($src, 0, 8, true)],
            'új_forrásból' => array_values(array_map(static fn ($r) => $r['source_name'] . ': ' . $r['title'], array_filter($arts, static fn ($r) => (int) $r['from_new_source'] === 1))),
            'erősen_javasolt' => array_slice(array_values(array_map(static fn ($r) => $r['source_name'] . ': ' . $r['title'], array_filter($arts, static fn ($r) => $r['recommendation'] === 'Erősen javasolt'))), 0, 8),
        ];
        $system = "Te a(z) {$client['name']} havi sajtófigyelő levelét írod az ügyfélnek, magyarul, sima szövegként (nem HTML, nem Markdown: csillag és kettőskereszt nincs).\n"
            . "Stílus és szerkezet:\n" . (($settings['style'] !== '' ? $settings['style'] : self::DEFAULT_STYLE)) . "\n"
            . "Formai szabályok: a felsorolás sorai „- ” kezdetűek; a linket változatlanul, külön sorba írd; csak a kapott adatokból dolgozz, ne találj ki számot vagy eseményt.\n"
            . 'subject: rövid tárgy, benne a hónap (pl. „Sajtófigyelés – 2026. október”). body: a teljes levél a megszólítástól az aláírásig.';
        $schema = ['type' => 'object', 'properties' => ['subject' => ['type' => 'string'], 'body' => ['type' => 'string']], 'required' => ['subject', 'body'], 'additionalProperties' => false];
        $res = $this->claude->json($model, $system, json_encode($facts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), $schema, 'medium', 4000);
        $subject = mb_substr(trim(str_replace(["\r", "\n"], ' ', (string) ($res['subject'] ?? ''))), 0, 255);
        $body = trim(str_replace("\r\n", "\n", (string) ($res['body'] ?? '')));
        if ($subject === '' || $body === '') {
            throw new \Xperion\Claude\ClaudeException('A Claude üres levelet adott vissza. Próbálja újra.');
        }
        $out = ['month' => $month, 'subject' => $subject, 'body' => $body, 'status' => 'jovahagyasra_var', 'model' => $model, 'saved' => false, 'notified' => 0, 'notice' => ''];
        if ($dry) {
            $out['notice'] = 'Szárazfuttatás: a levél nincs mentve, értesítés nem ment.';
            return $out;
        }
        $now = Database::now();
        $this->db->run('INSERT INTO month_emails (client_id, month, subject, body, status, model, generated_at, edited_by, edited_at, error) VALUES (?, ?, ?, ?, \'jovahagyasra_var\', ?, ?, NULL, NULL, \'\')
            ON DUPLICATE KEY UPDATE subject = VALUES(subject), body = VALUES(body), status = \'jovahagyasra_var\', model = VALUES(model), generated_at = VALUES(generated_at), edited_by = NULL, edited_at = NULL, error = \'\'', [$cid, $month, $subject, $body, $model, $now]);
        $this->audit->add($cid, $userId, 'Havi e-mail elkészült', $month . ' · ' . $subject);
        $out['saved'] = true;
        if ($notify) {
            [$out['notified'], $out['notice']] = $this->notifyOperators($cid, (string) $client['name'], $month, $subject, $body);
        }
        return $out + ['generated_at' => $now];
    }

    /** @return array<string,mixed> */
    public function update(Access $a, string $month, string $subject, string $body): array
    {
        $a->require('ops');
        $cid = $this->clientOf($a);
        $row = $this->row($cid, $month) ?? throw new \InvalidArgumentException('Ehhez a hónaphoz még nincs levél.');
        if (in_array($row['status'], ['elkuldve', 'kuldes'], true)) {
            throw new \InvalidArgumentException('Az elküldött levél nem módosítható.');
        }
        $subject = mb_substr(trim(str_replace(["\r", "\n"], ' ', $subject)), 0, 255);
        $body = trim(str_replace("\r\n", "\n", $body));
        if ($subject === '' || $body === '') {
            throw new \InvalidArgumentException('A tárgy és a szöveg nem lehet üres.');
        }
        $this->db->run("UPDATE month_emails SET subject = ?, body = ?, edited_by = ?, edited_at = ?, status = IF(status = 'hiba', 'jovahagyasra_var', status) WHERE client_id = ? AND month = ? AND status IN ('jovahagyasra_var','hiba')",
            [$subject, $body, $a->userId(), Database::now(), $cid, $month]);
        $this->audit->add($cid, $a->userId(), 'Havi e-mail szerkesztve', $month . ' · ' . $subject);
        return $this->row($cid, $month) ?? [];
    }

    /** Teszt e-mail csak a bejelentkezett felhasználónak. @return string a címzett */
    public function sendTest(Access $a, string $month): string
    {
        $a->require('ops');
        $cid = $this->clientOf($a);
        $row = $this->row($cid, $month) ?? throw new \InvalidArgumentException('Ehhez a hónaphoz még nincs levél.');
        $me = (string) $this->db->value('SELECT email FROM users WHERE id = ?', [$a->userId()]);
        $s = $this->settings($cid);
        $note = "[Teszt: ez a levél csak Önnek ment ki. Élesben a címzett: " . ($s['to'] !== '' ? $s['to'] : 'nincs megadva') . ($s['cc'] !== '' ? ', másolat: ' . $s['cc'] : '') . ".]\n\n";
        $this->mailer->send(new Message([$me], '[Teszt] ' . $row['subject'], $note . $row['body'], TextToHtml::convert($note . $row['body'])));
        $this->audit->add($cid, $a->userId(), 'Havi e-mail: teszt', $month . ' → ' . $me);
        return $me;
    }

    /**
     * Jóváhagyás és azonnali küldés az ügyfélnek (címzett + másolat). Egy levél egyszer megy ki.
     *
     * @return array<string,mixed> a levél új állapota
     */
    public function approveAndSend(Access $a, string $month): array
    {
        $a->require('ops');
        $cid = $this->clientOf($a);
        $row = $this->row($cid, $month) ?? throw new \InvalidArgumentException('Ehhez a hónaphoz még nincs levél.');
        if ($row['status'] === 'elkuldve') {
            throw new \InvalidArgumentException('Ez a levél már elment: ' . View::dateHu((string) $row['sent_at']) . '.');
        }
        $s = $this->settings($cid);
        $to = Message::parseList($s['to']);
        $cc = array_values(array_diff(Message::parseList($s['cc']), $to));
        if (!$to) {
            throw new \InvalidArgumentException('Nincs megadva az ügyfél e-mail-címe. Ügyfelek → Havi e-mail beállításai.');
        }
        if (!$this->mailer->configured()) {
            throw new MailException('Az e-mail-küldés nincs beállítva: ' . $this->mailerProblem() . '. A levél nem ment el.');
        }
        $message = new Message($to, $row['subject'], $row['body'], TextToHtml::convert($row['body']), $cc);
        // Zárolás állapotváltással: csak egy kérés küldheti el (a 15 percnél régebbi, félbemaradt küldés újrapróbálható).
        $now = Database::now();
        $locked = $this->db->run("UPDATE month_emails SET status = 'kuldes', sent_at = ?, sent_by = ?, error = '' WHERE client_id = ? AND month = ?
            AND (status IN ('jovahagyasra_var','hiba') OR (status = 'kuldes' AND sent_at < ?))", [$now, $a->userId(), $cid, $month, gmdate('Y-m-d H:i:s', time() - 900)])->rowCount();
        if ($locked !== 1) {
            throw new \InvalidArgumentException('A levél küldése már folyamatban van.');
        }
        $sentTo = implode(', ', $to) . ($cc ? ' (másolat: ' . implode(', ', $cc) . ')' : '');
        try {
            $this->mailer->send($message);
        } catch (\Throwable $e) {
            $this->db->run("UPDATE month_emails SET status = 'hiba', sent_at = NULL, error = ? WHERE client_id = ? AND month = ?", [mb_substr($e->getMessage(), 0, 1000), $cid, $month]);
            $this->audit->add($cid, $a->userId(), 'Havi e-mail küldése sikertelen', $month . ' · ' . $e->getMessage());
            throw new MailException('A levél nem ment el: ' . $e->getMessage());
        }
        $this->db->run("UPDATE month_emails SET status = 'elkuldve', sent_at = ?, sent_to = ? WHERE client_id = ? AND month = ?", [Database::now(), mb_substr($sentTo, 0, 1000), $cid, $month]);
        $this->audit->add($cid, $a->userId(), 'Havi e-mail elküldve', $month . ' → ' . $sentTo);
        return $this->row($cid, $month) ?? [];
    }

    /** Teendőhöz: jóváhagyásra váró vagy hibás levelek száma az ügyfélnél. @return list<array{month:string, status:string}> */
    public function waiting(int $clientId): array
    {
        return array_map(static fn ($r) => ['month' => (string) $r['month'], 'status' => (string) $r['status']], $this->db->all("SELECT month, status FROM month_emails WHERE client_id = ? AND status IN ('jovahagyasra_var','hiba') ORDER BY month DESC", [$clientId]));
    }

    /** @return array{0:int, 1:string} értesített címek száma, megjegyzés */
    private function notifyOperators(int $cid, string $clientName, string $month, string $subject, string $body): array
    {
        if (!$this->mailer->configured()) {
            return [0, 'Értesítés nem ment: az e-mail-küldés nincs beállítva (' . $this->mailerProblem() . ').'];
        }
        $ops = array_map(static fn ($r) => (string) $r['email'], $this->db->all("SELECT email FROM users WHERE is_operator = 1 AND status = 'aktiv'"));
        if (!$ops) {
            return [0, 'Nincs aktív üzemeltető, akit értesíteni lehetne.'];
        }
        $link = $this->baseUrl !== '' ? $this->baseUrl . 'index.php?p=vezerlo' : 'a Vezérlőpulton';
        $text = "Elkészült a(z) {$clientName} " . self::monthLabel($month) . " havi levele. Az ügyfél még nem kapta meg.\n\n"
            . "Jóváhagyás vagy szerkesztés: {$link}\n(Vezérlőpult → Havi e-mail az ügyfélnek → Jóváhagyom, mehet)\n\n"
            . "Előnézet\nTárgy: {$subject}\n\n{$body}";
        try {
            $this->mailer->send(new Message($ops, 'Jóváhagyásra vár: ' . $subject, $text, TextToHtml::convert($text)));
            return [count($ops), 'Értesítés elküldve: ' . implode(', ', $ops) . '.'];
        } catch (\Throwable $e) {
            $this->audit->add($cid, null, 'Havi e-mail: értesítés sikertelen', $e->getMessage());
            return [0, 'A levél elkészült, de az értesítés nem ment el: ' . $e->getMessage()];
        }
    }

    /** @return array<string,mixed>|null */
    private function row(int $cid, string $month): ?array
    {
        $r = $this->db->one('SELECT e.month, e.subject, e.body, e.status, e.model, e.generated_at, e.edited_at, e.sent_at, e.sent_to, e.error, u.name AS edited_by_name
            FROM month_emails e LEFT JOIN users u ON u.id = e.edited_by WHERE e.client_id = ? AND e.month = ?', [$cid, $month]);
        if ($r === null) {
            return null;
        }
        $r['status_label'] = self::STATUS[$r['status']] ?? $r['status'];
        foreach (['generated_at', 'edited_at', 'sent_at'] as $k) {
            $r[$k . '_hu'] = $r[$k] ? View::dateHu((string) $r[$k]) : '';
        }
        return $r;
    }

    private function clientOf(Access $a): int
    {
        return $a->clientId() ?? throw new \InvalidArgumentException('Nincs kiválasztott ügyfél.');
    }

    private static function checkMonth(string $month): void
    {
        if (!preg_match('/^\d{2}(0[1-9]|1[0-2])$/', $month)) {
            throw new \InvalidArgumentException('Érvénytelen hónap.');
        }
    }

    public static function monthLabel(string $month): string
    {
        return View::monthHu(new \DateTimeImmutable(sprintf('20%s-%s-01', substr($month, 0, 2), substr($month, 2, 2))));
    }
}
