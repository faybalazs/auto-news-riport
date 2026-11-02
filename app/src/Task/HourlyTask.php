<?php
declare(strict_types=1);

namespace Xperion\Task;

use Xperion\Sheets\SheetNames;
use Xperion\Sheets\Settings;

/**
 * Óránkénti futás (product.md 6.2). Az 1. lépésben csak beolvassa a
 * Beállításokat; a teendők (e-mail, feltöltés, posztok) később jönnek.
 */
final class HourlyTask implements Task
{
    public function id(): string
    {
        return 'orankenti';
    }

    public function label(): string
    {
        return 'Óránkénti futás';
    }

    public function readOnly(): bool
    {
        return false;
    }

    public function run(TaskContext $ctx): void
    {
        $sheets = $ctx->sheets();
        if (!isset($sheets->sheets()[SheetNames::SETTINGS])) {
            $ctx->warn('Nincs „Beállítások” lap. Futtasd a „Munkalapok előkészítése” feladatot.');
            return;
        }
        $settings = new Settings($sheets);
        $state = $settings->get('honap_email_allapot');
        $ctx->info('Havi e-mail állapota: ' . ($state === '' ? '(üres)' : $state) . '.');
        $ctx->info('Aktuális hónap: ' . ($settings->get('aktualis_honap') ?: '(üres)') . '.');
        $ctx->warn('Az e-mail-küldés (4. lépés), a feltöltés és a posztgenerálás (2. ütem) még nincs kész; most nem történt írás.');
    }
}
