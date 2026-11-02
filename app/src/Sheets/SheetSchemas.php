<?php
declare(strict_types=1);

namespace Xperion\Sheets;

/** Az új munkalapok fejlécei és alapértékei (product.md 4.2). */
final class SheetSchemas
{
    public const LOG_HEADER = ['Időbélyeg', 'Feladat', 'Eredmény', 'Részletek'];

    public const SETTINGS_HEADER = ['Kulcs', 'Érték'];

    /** Alapértelmezett beállítások; meglévő értéket soha nem írunk felül. */
    public const SETTINGS_DEFAULTS = [
        'ugyfel_email' => '',
        'ugyfel_cc' => '',
        'uzemelteto_email' => '',
        'felulet_url' => '',
        'honap_email_allapot' => '',
        'aktualis_honap' => '',
        'modell_ertekeles' => 'claude-haiku-5-5',
        'modell_poszt' => 'claude-sonnet-5-5',
    ];

    public const POST_IDEAS_HEADER = ['ID', 'Beküldő', 'Téma', 'Fő üzenet', 'Forrás- vagy fotólink', 'Poszttípus', 'Kívánt hét', 'Állapot', 'Előnézet link', 'Poszt ID', 'Megjegyzés'];

    /** Legördülők a Posztötletek lapon: oszlopindex (0-tól) → értékek. */
    public const POST_IDEAS_DROPDOWNS = [
        5 => ['szakmai', 'rendezvény', 'díj', 'partner', 'csapat'],
        7 => ['Új', 'Elkészült', 'Jóváhagyva', 'Ütemezve', 'Elvetve'],
    ];

    /** @return list<string> */
    public static function header(string $sheet): array
    {
        return match ($sheet) {
            SheetNames::LOG => self::LOG_HEADER,
            SheetNames::SETTINGS => self::SETTINGS_HEADER,
            SheetNames::POST_IDEAS => self::POST_IDEAS_HEADER,
            default => throw new \InvalidArgumentException('Ismeretlen lap: ' . $sheet),
        };
    }

    /**
     * Legördülő lista beállítása egy oszlop 2. sorától a lap végéig.
     *
     * @param list<string> $values
     * @return array<string,mixed>
     */
    public static function dropdownRequest(int $sheetId, int $column, array $values): array
    {
        return ['setDataValidation' => [
            'range' => ['sheetId' => $sheetId, 'startRowIndex' => 1, 'startColumnIndex' => $column, 'endColumnIndex' => $column + 1],
            'rule' => [
                'condition' => ['type' => 'ONE_OF_LIST', 'values' => array_map(static fn ($v) => ['userEnteredValue' => $v], $values)],
                'showCustomUi' => true,
                'strict' => true,
            ],
        ]];
    }

    /** @return array<string,mixed> Félkövér fejlécsor. */
    public static function boldHeaderRequest(int $sheetId): array
    {
        return ['repeatCell' => [
            'range' => ['sheetId' => $sheetId, 'startRowIndex' => 0, 'endRowIndex' => 1],
            'cell' => ['userEnteredFormat' => ['textFormat' => ['bold' => true]]],
            'fields' => 'userEnteredFormat.textFormat.bold',
        ]];
    }
}
