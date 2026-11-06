<?php
declare(strict_types=1);

namespace Xperion\Import;

use Xperion\Db\Database;
use Xperion\Google\SheetsClient;
use Xperion\Sheets\SheetNames;
use Xperion\Task\TaskContext;

/**
 * Adatátvétel egy ügyfél Google Sheetjéből az adatbázisba.
 * - Csak az adott ügyfél (client_id) adatait írja.
 * - Többször futtatható: a meglévő rekordot frissíti, nem duplikál.
 * - Szárazon csak megszámolja, mit tenne.
 * - A posztszabályokat csak akkor tölti be, ha az ügyfélnek még nincs szabálya.
 */
final class SheetImporter
{
    public function __construct(private Database $db, private SheetsClient $sheets, private int $clientId)
    {
    }

    public function run(TaskContext $ctx): void
    {
        // A „2609” alakú lapnevek a PHP-tömbben számkulccsá válnak: vissza kell alakítani szöveggé.
        $titles = array_map('strval', array_keys($this->sheets->sheets()));
        $ctx->done('A tábla elérhető: ' . count($titles) . ' munkalap.');
        $monthly = array_values(array_filter($titles, [SheetNames::class, 'isMonthly']));
        sort($monthly);

        $want = [SheetNames::SOURCES, SheetNames::KEYWORDS, SheetNames::LINKEDIN_POSTS, SheetNames::LINKEDIN_RULES, SheetNames::POST_IDEAS, SheetNames::SETTINGS];
        $present = array_values(array_intersect($want, $titles));
        foreach (array_diff([SheetNames::SOURCES, SheetNames::KEYWORDS], $titles) as $missing) {
            $ctx->warn('Nincs „' . $missing . '” munkalap; kimarad.');
        }
        $names = array_merge($present, $monthly);
        if (!$names) {
            $ctx->warn('Nincs átvehető munkalap.');
            return;
        }
        $data = array_combine($names, $this->sheets->batchGetValues(array_map(static fn ($n) => SheetsClient::range($n, 'A:Z'), $names)));

        $apply = function (callable $fn) use ($ctx): void {
            if (!$ctx->dry) {
                $this->db->transaction($fn);
            }
        };

        if (isset($data[SheetNames::SOURCES])) {
            $rows = SheetParser::sources($data[SheetNames::SOURCES]);
            [$new, $upd] = $this->diffCount('sources', 'url_hash', array_map(static fn ($r) => Url::hash($r['url']), $rows));
            $active = count(array_filter($rows, static fn ($r) => $r['active']));
            $ctx->info(sprintf('Figyelendő oldalak: %d forrás (aktív: %d, új forrás: %d) – új: %d, frissül: %d.', count($rows), $active, count(array_filter($rows, static fn ($r) => $r['is_new'])), $new, $upd));
            $apply(function () use ($rows): void {
                foreach ($rows as $r) {
                    $this->upsert('sources', $r + ['url_hash' => Url::hash($r['url'])], ['name', 'type', 'url', 'topics', 'active', 'is_new', 'note']);
                }
            });
        }

        if (isset($data[SheetNames::KEYWORDS])) {
            $rows = SheetParser::keywords($data[SheetNames::KEYWORDS]);
            $ex = count(array_filter($rows, static fn ($r) => $r['is_exclude']));
            $ctx->info(sprintf('Kulcsszavak: %d pozitív, %d kizáró.', count($rows) - $ex, $ex));
            $apply(function () use ($rows): void {
                foreach ($rows as $r) {
                    $this->upsert('keywords', $r, ['topic', 'is_exclude']);
                }
            });
        }

        if ($monthly) {
            $this->importMonthly($ctx, $monthly, $data, $apply);
        } else {
            $ctx->warn('Nincs havi lap (ÉÉHH nevű munkalap).');
        }

        if (isset($data[SheetNames::LINKEDIN_POSTS])) {
            $rows = SheetParser::posts($data[SheetNames::LINKEDIN_POSTS]);
            [$new, $upd] = $this->diffCount('posts', 'code', array_column($rows, 'code'));
            $ctx->info(sprintf('LinkedIn posztok: %d – új: %d, frissül: %d.', count($rows), $new, $upd));
            $apply(function () use ($rows): void {
                foreach ($rows as $r) {
                    $this->upsert('posts', $r, array_diff(array_keys($r), ['code']), true);
                }
            });
        }

        if (isset($data[SheetNames::POST_IDEAS])) {
            $rows = SheetParser::ideas($data[SheetNames::POST_IDEAS]);
            $ctx->info(sprintf('Posztötletek: %d.', count($rows)));
            $apply(function () use ($rows): void {
                foreach ($rows as $r) {
                    $this->upsert('post_ideas', $r, array_diff(array_keys($r), ['code']), true);
                }
            });
        }

        if (isset($data[SheetNames::LINKEDIN_RULES])) {
            $rows = SheetParser::pairs($data[SheetNames::LINKEDIN_RULES], ['megnevezés']);
            $have = (int) $this->db->value('SELECT COUNT(*) FROM post_rules WHERE client_id = ?', [$this->clientId]);
            if ($have > 0) {
                $ctx->info(sprintf('LinkedIn szabályok: %d a lapon; az alkalmazásban már %d szabály van, ezért nem írtam felül.', count($rows), $have));
            } else {
                $ctx->info(sprintf('LinkedIn szabályok: %d szabály betöltése.', count($rows)));
                $apply(function () use ($rows): void {
                    foreach ($rows as $i => [$name, $value]) {
                        $this->db->insert('post_rules', ['client_id' => $this->clientId, 'sort' => $i + 1, 'name' => mb_substr($name, 0, 190), 'value' => $value, 'updated_at' => Database::now()]);
                    }
                });
            }
        }

        if (isset($data[SheetNames::SETTINGS])) {
            $rows = array_filter(SheetParser::pairs($data[SheetNames::SETTINGS], ['kulcs']), static fn ($p) => $p[1] !== '');
            $ctx->info(sprintf('Beállítások: %d kitöltött érték.', count($rows)));
            $apply(function () use ($rows): void {
                foreach ($rows as [$k, $v]) {
                    $this->db->run('INSERT INTO client_settings (client_id, name, value, updated_at) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = VALUES(updated_at)', [$this->clientId, mb_substr($k, 0, 100), $v, Database::now()]);
                }
            });
        }

        $ctx->dry
            ? $ctx->warn('Szárazfuttatás: az adatbázisba nem került semmi. Ha rendben van, futtassa élesben.')
            : $ctx->done('Az adatok átvétele kész.');
    }

    /**
     * @param list<string> $monthly
     * @param array<string,list<list<string>>> $data
     */
    private function importMonthly(TaskContext $ctx, array $monthly, array $data, callable $apply): void
    {
        $existing = [];
        foreach ($this->db->all('SELECT url_hash, month FROM articles WHERE client_id = ?', [$this->clientId]) as $r) {
            $existing[$r['url_hash']] = $r['month'];
        }
        $seen = [];
        $plan = [];
        $stats = ['total' => 0, 'new' => 0, 'upd' => 0, 'dup' => 0, 'uploaded' => 0, 'decided' => 0];
        foreach ($monthly as $m) {
            foreach (SheetParser::monthly($data[$m]) as $a) {
                $stats['total']++;
                $h = Url::hash($a['url']);
                if (isset($seen[$h])) {
                    $stats['dup']++;
                    if ($a['uploaded']) {
                        $plan[$seen[$h]]['uploaded'] = 1; // egy későbbi lapon feltöltöttként jelölt
                    }
                    continue;
                }
                $seen[$h] = count($plan);
                $plan[] = $a + ['month' => $m, 'url_hash' => $h];
            }
        }
        foreach ($plan as $a) {
            $stats[isset($existing[$a['url_hash']]) ? 'upd' : 'new']++;
            $stats['uploaded'] += $a['uploaded'];
            $stats['decided'] += $a['decision'] !== '' ? 1 : 0;
        }
        $ctx->info(sprintf('Havi lapok: %d lap (%s – %s), %d cikksor, ebből %d egyedi cikk (új: %d, frissül: %d, több lapon is szereplő: %d); feltöltve: %d, döntéssel: %d.',
            count($monthly), $monthly[0], $monthly[count($monthly) - 1], $stats['total'], count($plan), $stats['new'], $stats['upd'], $stats['dup'], $stats['uploaded'], $stats['decided']));
        $apply(function () use ($plan): void {
            foreach ($plan as $a) {
                $a['url'] = mb_substr($a['url'], 0, 1000);
                $a['title'] = mb_substr($a['title'], 0, 500);
                $this->upsert('articles', $a + ['imported' => 1], ['month', 'no', 'source_name', 'published_on', 'title', 'description', 'url', 'recommendation', 'reason', 'decision', 'uploaded', 'upload_status', 'url_hu', 'url_en', 'post_code', 'from_new_source'], true);
            }
        });
    }

    /**
     * Beszúrás vagy frissítés az ügyfél egyedi kulcsa szerint (client_id + természetes kulcs).
     *
     * @param array<string,mixed> $row
     * @param list<string> $update frissítendő oszlopok
     */
    private function upsert(string $table, array $row, array $update, bool $hasCreated = false): void
    {
        $row = ['client_id' => $this->clientId] + $row + ['updated_at' => Database::now()];
        if ($hasCreated) {
            $row['created_at'] = Database::now();
        }
        $cols = array_keys($row);
        $sql = sprintf('INSERT INTO `%s` (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s', $table,
            implode(', ', array_map(static fn ($c) => "`$c`", $cols)),
            implode(', ', array_fill(0, count($cols), '?')),
            implode(', ', array_map(static fn ($c) => "`$c` = VALUES(`$c`)", array_merge($update, ['updated_at']))));
        $this->db->run($sql, array_values($row));
    }

    /**
     * @param list<string> $keys
     * @return array{0:int,1:int} [új, frissül]
     */
    private function diffCount(string $table, string $col, array $keys): array
    {
        $have = array_flip(array_column($this->db->all("SELECT `$col` AS k FROM `$table` WHERE client_id = ?", [$this->clientId]), 'k'));
        $keys = array_unique($keys);
        $upd = count(array_filter($keys, static fn ($k) => isset($have[$k])));
        return [count($keys) - $upd, $upd];
    }
}
