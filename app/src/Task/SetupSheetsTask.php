<?php
declare(strict_types=1);

namespace Xperion\Task;

use Xperion\Google\SheetsClient;
use Xperion\Sheets\SheetNames;
use Xperion\Sheets\SheetSchemas;
use Xperion\Sheets\Settings;

/**
 * Létrehozza az új munkalapokat (Beállítások, Napló, Posztötletek) fejléccel,
 * és pótolja a hiányzó beállításkulcsokat. Meglévő adatot nem ír felül;
 * többször is futtatható.
 */
final class SetupSheetsTask implements Task
{
    public function id(): string
    {
        return 'elokeszites';
    }

    public function label(): string
    {
        return 'Munkalapok előkészítése';
    }

    public function readOnly(): bool
    {
        return false;
    }

    public function run(TaskContext $ctx): void
    {
        $sheets = $ctx->sheets();
        $existing = $sheets->sheets();

        foreach (SheetNames::CREATED as $title) {
            if (isset($existing[$title])) {
                $ctx->info('„' . $title . '” már létezik.');
                continue;
            }
            $ctx->write('„' . $title . '” munkalap létrehozása fejléccel.', function () use ($sheets, $title): void {
                $id = $sheets->addSheet($title, 1);
                $header = SheetSchemas::header($title);
                $rows = [$header];
                if ($title === SheetNames::SETTINGS) {
                    foreach (SheetSchemas::SETTINGS_DEFAULTS as $k => $v) {
                        $rows[] = [$k, $v];
                    }
                }
                $sheets->updateValues(SheetsClient::range($title, 'A1'), $rows);
                $requests = [SheetSchemas::boldHeaderRequest($id)];
                if ($title === SheetNames::POST_IDEAS) {
                    foreach (SheetSchemas::POST_IDEAS_DROPDOWNS as $col => $values) {
                        $requests[] = SheetSchemas::dropdownRequest($id, $col, $values);
                    }
                }
                $sheets->batchUpdate($requests);
            });
        }

        // Meglévő Beállítások lapon csak a hiányzó kulcsokat pótoljuk.
        if (isset($existing[SheetNames::SETTINGS])) {
            $current = (new Settings($sheets))->all();
            $missing = array_diff_key(SheetSchemas::SETTINGS_DEFAULTS, $current);
            if (!$missing) {
                $ctx->info('A Beállítások lapon minden kulcs megvan.');
            } else {
                $ctx->write('Hiányzó beállításkulcsok pótlása: ' . implode(', ', array_keys($missing)) . '.', function () use ($sheets, $missing): void {
                    $rows = [];
                    foreach ($missing as $k => $v) {
                        $rows[] = [$k, $v];
                    }
                    $sheets->appendValues(SheetsClient::range(SheetNames::SETTINGS, 'A:B'), $rows);
                });
            }
        }
    }
}
