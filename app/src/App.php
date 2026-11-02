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

    public function mailer(): \Xperion\Mail\Mailer
    {
        return new \Xperion\Mail\SmtpMailer((array) $this->config->get('smtp', []));
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
        return $this->runner ??= new Runner(
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
            }), new HourlyTask()],
            fn (): SheetsClient => $this->sheets(),
            $this->log(),
            $this->storageDir . '/futas.lock',
        );
    }
}
