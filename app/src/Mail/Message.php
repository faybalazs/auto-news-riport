<?php
declare(strict_types=1);

namespace Xperion\Mail;

/** Egy kimenő levél: címzettek, tárgy, sima szöveg és opcionális HTML. */
final class Message
{
    /**
     * @param list<string> $to
     * @param list<string> $cc
     */
    public function __construct(
        public readonly array $to,
        public readonly string $subject,
        public readonly string $text,
        public readonly string $html = '',
        public readonly array $cc = [],
        public readonly string $replyTo = '',
    ) {
        if (!$to) {
            throw new MailException('Nincs címzett.');
        }
        foreach ([...$to, ...$cc, ...($replyTo !== '' ? [$replyTo] : [])] as $a) {
            if (!self::validAddress($a)) {
                throw new MailException('Érvénytelen e-mail-cím: ' . $a);
            }
        }
    }

    public static function validAddress(string $a): bool
    {
        return filter_var($a, FILTER_VALIDATE_EMAIL) !== false && !preg_match('/[\r\n<>,;]/', $a);
    }

    /**
     * Vesszővel, pontosvesszővel vagy szóközzel elválasztott címlista.
     *
     * @return list<string>
     */
    public static function parseList(string $s): array
    {
        return array_values(array_unique(array_filter(array_map('trim', preg_split('/[,;\s]+/', $s) ?: []), static fn ($x) => $x !== '')));
    }
}
