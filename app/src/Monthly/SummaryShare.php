<?php
declare(strict_types=1);

namespace Xperion\Monthly;

use Xperion\Auth\Access;
use Xperion\Auth\AuditLog;
use Xperion\Mail\Mailer;
use Xperion\Mail\Message;
use Xperion\Mail\TextToHtml;
use Xperion\Web\View;

/**
 * A havi vezetői összefoglaló megosztása: nyomtatható (PDF-be menthető) nézet adatai és küldés e-mailben
 * tetszőleges címzettnek. Ügyfél-adminisztrátor és üzemeltető (a „decide” jog, mint az újragenerálásnál).
 */
final class SummaryShare
{
    public function __construct(private ArticleService $articles, private Mailer $mailer, private AuditLog $audit, private string $baseUrl)
    {
    }

    /** @return array{client:string, month:string, label:string, report:array<string,mixed>} */
    public function data(Access $a, string $month): array
    {
        $client = $a->client() ?? throw new \InvalidArgumentException('Nincs kiválasztott ügyfél.');
        if (!preg_match('/^\d{4}$/', $month)) {
            throw new \InvalidArgumentException('Érvénytelen hónap.');
        }
        $report = $this->articles->report($a, $month) ?? throw new \InvalidArgumentException('Ehhez a hónaphoz nincs vezetői összefoglaló.');
        return ['client' => (string) $client['name'], 'month' => $month, 'label' => self::label($month), 'report' => $report];
    }

    /** Az összefoglaló sima szövege (a levél törzse; a „- ” sorokból a HTML-változatban lista lesz). */
    public static function text(array $d): string
    {
        $r = $d['report'];
        $out = ["Vezetői összefoglaló – {$d['client']}, {$d['label']}", ''];
        if (!empty($r['sections'])) {
            if ((string) $r['details'] !== '') {
                $out[] = (string) $r['details'];
                $out[] = '';
            }
            foreach ($r['sections'] as $s) {
                $out[] = $s['title'];
                foreach ($s['points'] as $pt) {
                    $out[] = '- ' . $pt;
                }
                $out[] = '';
            }
        } else {
            foreach ($r['summary_rows'] ?? [] as $row) {
                $out[] = '- ' . $row['label'] . ': ' . $row['value'];
            }
            if ((string) $r['details'] !== '') {
                $out[] = '';
                $out[] = (string) $r['details'];
            }
            $out[] = '';
        }
        return rtrim(implode("\n", $out)) . "\n";
    }

    /**
     * Küldés e-mailben. A címzettet és a kísérőszöveget a felhasználó adja meg; a levél egyszer megy ki.
     *
     * @return list<string> a címzettek
     */
    public function send(Access $a, string $month, string $to, string $cc, string $note): array
    {
        $a->require('decide');
        if (!$this->mailer->configured()) {
            throw new \InvalidArgumentException('Az e-mail-küldés nincs beállítva (config.php: smtp). Szóljon az üzemeltetőnek.');
        }
        $toList = Message::parseList($to);
        $ccList = Message::parseList($cc);
        if (!$toList) {
            throw new \InvalidArgumentException('Adjon meg legalább egy címzettet (több cím vesszővel elválasztva).');
        }
        foreach ([...$toList, ...$ccList] as $addr) {
            if (!Message::validAddress($addr)) {
                throw new \InvalidArgumentException('Érvénytelen e-mail-cím: ' . $addr);
            }
        }
        $d = $this->data($a, $month);
        $note = trim(mb_substr($note, 0, 4000));
        $link = $this->baseUrl !== '' ? $this->baseUrl . 'index.php?p=cikkek&honap=' . $month : '';
        $body = ($note !== '' ? $note . "\n\n" : '') . self::text($d) . ($link !== '' ? "\nA hónap cikkei: {$link}\n" : '');
        $subject = "Vezetői összefoglaló – {$d['client']}, {$d['label']}";
        $this->mailer->send(new Message($toList, $subject, $body, TextToHtml::convert($body), $ccList));
        $cid = (int) ($a->clientId() ?? 0);
        $this->audit->add($cid, $a->userId(), 'Vezetői összefoglaló elküldve', $month . ' → ' . implode(', ', [...$toList, ...$ccList]));
        return [...$toList, ...$ccList];
    }

    private static function label(string $month): string
    {
        return View::monthHu(new \DateTimeImmutable(sprintf('20%s-%s-01', substr($month, 0, 2), substr($month, 2, 2))));
    }
}
