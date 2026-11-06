<?php
declare(strict_types=1);

namespace Xperion\Tests;

use Xperion\Mail\MailException;
use Xperion\Mail\Mailer;
use Xperion\Mail\Message;

/** Helyettesítő küldő: rögzíti a leveleket; beállítható, hogy hibát dobjon. */
final class FakeMailer implements Mailer
{
    /** @var list<Message> */
    public array $sent = [];
    public ?string $fail = null;
    public bool $ready = true;

    public function send(Message $m): void
    {
        if ($this->fail !== null) {
            throw new MailException($this->fail);
        }
        $this->sent[] = $m;
    }

    public function configured(): bool
    {
        return $this->ready;
    }
}
