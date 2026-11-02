<?php
declare(strict_types=1);

namespace Xperion;

use Xperion\Google\ServiceAccountAuth;
use Xperion\Google\SheetsClient;
use Xperion\Http\CurlHttpClient;
use Xperion\Http\HttpClient;
use Xperion\Log\LocalLog;
use Xperion\Task\ConnectionCheckTask;
use Xperion\Task\HourlyTask;
use Xperion\Task\MonthlyTask;
use Xperion\Task\Runner;
use Xperion\Task\SetupSheetsTask;

/** Egyszerű szolgáltatás-tároló: a konfigurációból építi fel a részeket. */
final class App
{
    private ?HttpClient $http = null;
    private ?ServiceAccountAuth $auth = null;
    private ?SheetsClient $sheets = null;
    private ?Runner $runner = null;

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

    public function log(): LocalLog
    {
        return new LocalLog($this->storageDir . '/naplo.jsonl');
    }

    public function runner(): Runner
    {
        if ($this->runner === null) {
            $email = '';
            try {
                $email = $this->config->serviceAccountJson() !== '' ? $this->googleAuth()->clientEmail() : '';
            } catch (\Throwable) {
                // a kapcsolat-ellenőrzés jelzi a hibát
            }
            $this->runner = new Runner(
                [new ConnectionCheckTask($email), new SetupSheetsTask(), new MonthlyTask(), new HourlyTask()],
                fn (): SheetsClient => $this->sheets(),
                $this->log(),
                $this->storageDir . '/futas.lock',
            );
        }
        return $this->runner;
    }
}
