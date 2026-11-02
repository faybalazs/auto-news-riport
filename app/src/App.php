<?php
declare(strict_types=1);

namespace Xperion;

use Xperion\Google\ServiceAccountAuth;
use Xperion\Google\SheetsClient;
use Xperion\Http\CurlHttpClient;
use Xperion\Http\HttpClient;
use Xperion\Log\LocalLog;
use Xperion\Task\HourlyTask;
use Xperion\Task\MonthlyTask;
use Xperion\Task\Runner;
use Xperion\Auth\AuditLog;
use Xperion\Auth\Authenticator;
use Xperion\Auth\ClientAdmin;
use Xperion\Auth\TokenService;
use Xperion\Auth\UserAdmin;
use Xperion\Db\Database;
use Xperion\Db\Migrator;

/** Egyszerű szolgáltatás-tároló: a konfigurációból építi fel a részeket. */
final class App
{
    private ?HttpClient $http = null;
    private ?ServiceAccountAuth $auth = null;
    private ?SheetsClient $sheets = null;
    private ?Runner $runner = null;
    private ?Database $db = null;

    public function __construct(public readonly Config $config, public readonly string $storageDir)
    {
    }

    public function http(): HttpClient
    {
        return $this->http ??= new CurlHttpClient();
    }

    public function googleAuth(): ServiceAccountAuth
    {
        if ($this->auth === null) {
            $json = $this->config->serviceAccountJson();
            if ($json === '') {
                throw new \RuntimeException('Nincs beállítva a Google-szolgáltatásfiók (config.php: google_service_account_file vagy google_service_account_json).');
            }
            $this->auth = new ServiceAccountAuth($json, $this->http(), $this->storageDir . '/google-token.json');
        }
        return $this->auth;
    }

    public function sheets(): SheetsClient
    {
        if ($this->sheets === null) {
            $id = $this->config->string('spreadsheet_id');
            if ($id === '') {
                throw new \RuntimeException('Nincs beállítva a táblázat azonosítója (config.php: spreadsheet_id).');
            }
            $auth = $this->googleAuth();
            $this->sheets = new SheetsClient($id, $this->http(), static fn (): string => $auth->accessToken());
        }
        return $this->sheets;
    }

    /** Sheets-kliens egy adott táblához (ügyfelenként más-más). */
    public function sheetsFor(string $spreadsheetId): SheetsClient
    {
        $auth = $this->googleAuth();
        return new SheetsClient($spreadsheetId, $this->http(), static fn (): string => $auth->accessToken());
    }

    public function dbConfigured(): bool
    {
        return $this->config->has('db.name') && $this->config->has('db.user');
    }

    public function db(): Database
    {
        if ($this->db === null) {
            if (!$this->dbConfigured()) {
                throw new \RuntimeException('Nincs beállítva az adatbázis (config.php: db).');
            }
            $this->db = Database::connect((array) $this->config->get('db', []));
        }
        return $this->db;
    }

    public function migrator(): Migrator
    {
        return new Migrator($this->db(), dirname(__DIR__) . '/migrations');
    }

    public function audit(): AuditLog
    {
        return new AuditLog($this->db());
    }

    public function users(): UserAdmin
    {
        return new UserAdmin($this->db(), new TokenService($this->db()), $this->audit());
    }

    public function clients(): ClientAdmin
    {
        return new ClientAdmin($this->db(), $this->audit());
    }

    public function importer(): \Xperion\Import\ImportService
    {
        return new \Xperion\Import\ImportService($this->db(), fn (string $id): SheetsClient => $this->sheetsFor($id), $this->log(), $this->audit(), $this->storageDir . '/futas.lock');
    }

    /** A szolgáltatásfiók e-mail-címe (ezzel kell megosztani a táblákat), vagy üres. */
    public function serviceAccountEmail(): string
    {
        try {
            return $this->config->serviceAccountJson() !== '' ? $this->googleAuth()->clientEmail() : '';
        } catch (\Throwable) {
            return '';
        }
    }

    public function collector(): \Xperion\Collect\CollectService
    {
        $http = new CurlHttpClient(1, 20, null, 'Mozilla/5.0 (compatible; XperionSajtofigyelo/0.4; +https://xperion.hu)');
        return new \Xperion\Collect\CollectService($this->db(), new \Xperion\Collect\Collector($http), $this->log(), $this->storageDir . '/futas.lock');
    }

    public function claude(): \Xperion\Claude\ClaudeClient
    {
        return new \Xperion\Claude\ClaudeClient(new CurlHttpClient(2, 180), $this->config->string('claude_api_key'));
    }

    public function monthly(): \Xperion\Monthly\MonthlyService
    {
        return new \Xperion\Monthly\MonthlyService($this->db(), $this->collector(), $this->claude(), $this->log(), $this->audit(), $this->storageDir . '/futas.lock',
            function (int $clientId, string $month, \Xperion\Task\TaskContext $ctx): void {
                $r = $this->emails()->generateForClient($clientId, $month, false);
                $ctx->done('Havi e-mail elkészült, jóváhagyásra vár: ' . $r['subject']);
                if ($r['notice'] !== '') {
                    $ctx->info($r['notice']);
                }
            });
    }

    /** Az alkalmazás címe a levelek linkjeihez: config app_url, vagy webes kérésnél a kérés címe. */
    public function baseUrl(): string
    {
        $url = $this->config->string('app_url');
        if ($url === '' && PHP_SAPI !== 'cli') {
            $url = \Xperion\Web\Http::baseUrl();
        }
        return $url === '' ? '' : rtrim($url, '/') . '/';
    }

    /** Az ügyfél WordPress-kliense a config.php „wordpress” részéből, az ügyfél weboldala szerint. @param array<string,mixed> $client */
    public function wpFor(array $client): \Xperion\Press\WpBridgeClient
    {
        $site = $this->config->wordpressSite((string) ($client['website'] ?? ''));
        if ($site === null) {
            throw new \InvalidArgumentException('Nincs WordPress-beállítás ehhez az ügyfélhez (' . (($client['website'] ?? '') !== '' ? $client['website'] : 'nincs megadva weboldal') . '). A config.php „wordpress” részébe kell, az ügyfél weboldala szerint (pl. \'xperion.hu\' => [\'user\' => …, \'app_password\' => …]).');
        }
        // A WordPress-hívás nem ismételhető vakon: újrapróbálás nélküli kliens.
        return new \Xperion\Press\WpBridgeClient(new CurlHttpClient(0, 90), $site['base_url'], $site['user'], $site['app_password']);
    }

    public function uploads(): \Xperion\Press\UploadService
    {
        return new \Xperion\Press\UploadService($this->db(), $this->claude(), fn (array $c) => $this->wpFor($c),
            new \Xperion\Press\SourceText(new CurlHttpClient(1, 20, null, 'Mozilla/5.0 (compatible; XperionSajtofigyelo/0.8; +https://xperion.hu)')),
            $this->mailer(), $this->audit(), $this->log(), $this->storageDir . '/futas.lock');
    }

    public function rules(): \Xperion\Posts\RuleService
    {
        return new \Xperion\Posts\RuleService($this->db(), $this->audit());
    }

    public function ideas(): \Xperion\Posts\IdeaService
    {
        return new \Xperion\Posts\IdeaService($this->db(), $this->audit());
    }

    public function posts(): \Xperion\Posts\PostService
    {
        return new \Xperion\Posts\PostService($this->db(), $this->claude(), $this->rules(), $this->mailer(), $this->audit(), $this->baseUrl());
    }

    public function mailer(): \Xperion\Mail\Mailer
    {
        return new \Xperion\Mail\SmtpMailer($this->config->smtp());
    }

    public function emails(): \Xperion\Monthly\EmailService
    {
        return new \Xperion\Monthly\EmailService($this->db(), $this->claude(), $this->mailer(), $this->audit(), $this->baseUrl());
    }

    public function articles(): \Xperion\Monthly\ArticleService
    {
        return new \Xperion\Monthly\ArticleService($this->db(), $this->audit());
    }

    public function overview(): \Xperion\Monthly\OverviewService
    {
        return new \Xperion\Monthly\OverviewService($this->db(), $this->articles());
    }

    /**
     * A felületről feltöltött Google-kulcs ellenőrzése és mentése (csak üzemeltető).
     *
     * @return string a szolgáltatásfiók e-mail-címe
     */
    public function saveServiceAccountKey(\Xperion\Auth\Access $a, string $json): string
    {
        $a->require('ops');
        $data = json_decode(trim($json), true);
        if (!is_array($data) || ($data['type'] ?? '') !== 'service_account' || empty($data['client_email']) || empty($data['private_key'])) {
            throw new \InvalidArgumentException('Ez nem Google-szolgáltatásfiók kulcsfájl. A Google Cloud Console-ban a szolgáltatásfióknál a Kulcsok fülön letöltött JSON-fájlt válassza.');
        }
        if (openssl_pkey_get_private((string) $data['private_key']) === false) {
            throw new \InvalidArgumentException('A kulcsfájl privát kulcsa nem olvasható. Töltse le újra a JSON-kulcsot.');
        }
        $file = $this->storageDir . '/google-service-account.json';
        if (@file_put_contents($file, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
            throw new \RuntimeException('A kulcs nem menthető a storage mappába (írási jog?).');
        }
        @chmod($file, 0600);
        @unlink($this->storageDir . '/google-token.json');
        $this->auth = null;
        $this->sheets = null;
        $this->audit()->add(null, $a->userId(), 'Google-kulcs feltöltve', (string) $data['client_email']);
        return (string) $data['client_email'];
    }

    public function authenticator(): Authenticator
    {
        return new Authenticator($this->db());
    }

    public function tokens(): TokenService
    {
        return new TokenService($this->db());
    }

    public function log(): LocalLog
    {
        return new LocalLog($this->storageDir . '/naplo.jsonl');
    }

    public function runner(): Runner
    {
        if ($this->runner !== null) {
            return $this->runner;
        }
        $this->runner = new Runner(
            [new MonthlyTask(function (\Xperion\Task\TaskContext $ctx, string $month): void {
                $clients = $this->db()->all("SELECT id, name, status FROM clients WHERE status <> 'szunetel' ORDER BY name");
                foreach ($clients as $c) {
                    $ctx->info('— ' . $c['name'] . ' —');
                    if ($c['status'] !== 'aktiv') {
                        $ctx->info('„Beállítás alatt” állapotú ügyfél: kimarad (az Ügyfelek oldalon állítsa Aktívra).');
                        continue;
                    }
                    try {
                        $this->monthly()->run((int) $c['id'], $month, $ctx);
                    } catch (\Throwable $e) {
                        $ctx->error($c['name'] . ': ' . $e->getMessage()); // egy ügyfél hibája nem állítja meg a többit
                    }
                }
            }), new HourlyTask(function (\Xperion\Task\TaskContext $ctx): void {
                $this->uploads()->runAll($ctx);
                $this->posts()->runPending($ctx);
                if (!$ctx->dry) {
                    // megőrzés: műveleti napló 2 év, futásnapló 90 nap
                    $n = $this->audit()->prune() + $this->log()->prune();
                    if ($n > 0) {
                        $ctx->info("Napló: {$n} régi bejegyzés törölve (megőrzés).");
                    }
                }
            })],
            fn (): SheetsClient => $this->sheets(),
            $this->log(),
            $this->storageDir . '/futas.lock',
        );
        $this->runner->onFailure = fn (\Xperion\Task\RunResult $r, string $trigger) => $this->notifyFailure($r, $trigger);
        return $this->runner;
    }

    /**
     * Hibás időzített futás → e-mail az üzemeltetőknek (product.md 8.: minden hiba a Naplóba és e-mailben).
     * Ugyanarról a feladatról óránként legfeljebb egy levél; kézi futásnál nincs levél (a hiba a felületen látszik).
     */
    public function notifyFailure(\Xperion\Task\RunResult $r, string $trigger): void
    {
        if ($trigger === 'admin' || !$this->dbConfigured()) {
            return;
        }
        $mailer = $this->mailer();
        if (!$mailer->configured()) {
            return;
        }
        $state = $this->storageDir . '/hiba-ertesites.json';
        $sent = is_file($state) ? (json_decode((string) file_get_contents($state), true) ?: []) : [];
        $key = $r->task . ($r->dry ? ':dry' : '');
        if (($sent[$key] ?? 0) > time() - 3600) {
            return;
        }
        $ops = array_column($this->db()->all("SELECT email FROM users WHERE is_operator = 1 AND status = 'aktiv'"), 'email');
        if (!$ops) {
            return;
        }
        $errors = array_values(array_map(static fn ($l) => '- ' . $l['text'], array_filter($r->lines, static fn ($l) => $l['level'] === 'error')));
        $text = "Hibával zárult egy időzített futás: {$r->label}" . ($r->dry ? ' (szárazon)' : '') . ".\n\n" . implode("\n", array_slice($errors, 0, 20))
            . "\n\nRészletek: " . ($this->baseUrl() !== '' ? $this->baseUrl() . 'index.php?p=naplo' : 'Napló oldal') . "\n(Ugyanerről a feladatról legfeljebb óránként egy értesítés megy.)";
        $mailer->send(new \Xperion\Mail\Message(array_map('strval', $ops), 'Hibás futás: ' . $r->label, $text));
        $sent[$key] = time();
        @file_put_contents($state, json_encode($sent), LOCK_EX);
    }

    /**
     * A futás állapota a felületnek: mi fut most (mióta, ki indította, melyik lépésnél), és a legutóbbi eredmények.
     *
     * @return array{running:?array<string,mixed>, results:list<array<string,mixed>>}
     */
    public function runState(): array
    {
        $names = [];
        $name = function (?int $id) use (&$names): string {
            if ($id === null) {
                return 'időzítés (cron)';
            }
            return $names[$id] ??= (string) ($this->db()->value('SELECT name FROM users WHERE id = ?', [$id]) ?: 'ismeretlen');
        };
        $run = \Xperion\Lock::current($this->storageDir . '/futas.lock');
        if ($run !== null) {
            $run['by'] = ($run['trigger'] ?? '') === 'cron' ? 'időzítés (cron)' : $name(isset($run['user']) ? (int) $run['user'] : null);
            $run['elapsed_hu'] = \Xperion\Lock::duration((int) ($run['elapsed'] ?? 0));
            $run['started_hu'] = isset($run['started_at']) ? (new \DateTimeImmutable('@' . (int) $run['started_at']))->setTimezone(new \DateTimeZone('Europe/Budapest'))->format('H:i') : '';
        }
        $results = array_map(function ($r) use ($name) {
            $r['by'] = $name(isset($r['user']) ? (int) $r['user'] : null);
            $r['finished_hu'] = isset($r['finished_at']) ? (new \DateTimeImmutable('@' . (int) $r['finished_at']))->setTimezone(new \DateTimeZone('Europe/Budapest'))->format('Y. m. d. H:i') : '';
            $r['duration_hu'] = \Xperion\Lock::duration((int) round(((int) ($r['duration_ms'] ?? 0)) / 1000));
            return $r;
        }, array_slice($this->log()->results(), 0, 8));
        return ['running' => $run, 'results' => $results];
    }

    public function sources(): \Xperion\Collect\SourceService
    {
        return new \Xperion\Collect\SourceService($this->db(), $this->audit(), new \Xperion\Collect\Collector(new CurlHttpClient(1, 20, null, 'Mozilla/5.0 (compatible; XperionSajtofigyelo/1.0; +https://xperion.hu)')));
    }
}
