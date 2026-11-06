<?php
declare(strict_types=1);

namespace Xperion\Mail;

interface Mailer
{
    /** @throws MailException */
    public function send(Message $m): void;

    /** Be van-e állítva a küldés (a felületen jelezzük, ha nincs). */
    public function configured(): bool;
}
