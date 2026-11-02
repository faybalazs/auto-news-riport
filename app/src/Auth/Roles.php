<?php
declare(strict_types=1);

namespace Xperion\Auth;

/** Szerepkörök és jogosultságok (a prototípus jogosultságmátrixa szerint). */
final class Roles
{
    public const OPERATOR = 'uzemelteto';
    public const CLIENT_ADMIN = 'ugyfeladmin';
    public const CONTENT = 'tartalom';
    public const READER = 'olvaso';

    /** Ügyfélhez köthető szerepkörök (az üzemeltető globális). */
    public const CLIENT_ROLES = [self::CLIENT_ADMIN, self::CONTENT, self::READER];

    public const LABELS = [
        self::OPERATOR => 'Üzemeltető',
        self::CLIENT_ADMIN => 'Ügyfél-adminisztrátor',
        self::CONTENT => 'Tartalomkezelő',
        self::READER => 'Olvasó',
    ];

    public const DESCRIPTIONS = [
        self::OPERATOR => 'Minden ügyfél: futások, havi e-mail, ügyfelek és felhasználók kezelése',
        self::CLIENT_ADMIN => 'Dönt a cikkekről, jóváhagyja a posztokat, meghívja a saját munkatársait',
        self::CONTENT => 'Posztötletek és -szabályok, posztok közzététele a LinkedInen',
        self::READER => 'Mindent lát az ügyfélnél, de nem módosít',
    ];

    public const PERMISSIONS = [
        'decide' => 'Döntés a havi cikkekről (Mehet / LinkedIn poszt / Nem kell)',
        'approve' => 'Poszt jóváhagyása, módosítás kérése',
        'ideas' => 'Posztötletek kezelése, küldés generálásra',
        'rules' => 'Posztszabályok szerkesztése',
        'publish' => 'Poszt megjelentnek jelölése',
        'deletePost' => 'Poszt törlése',
        'users' => 'Felhasználók meghívása (saját ügyfél)',
        'sources' => 'Figyelt források és kulcsszavak szerkesztése',
        'log' => 'Műveleti napló (saját ügyfél)',
        'ops' => 'Futások, havi e-mail, ügyfelek, napló',
    ];

    public const GRANTS = [
        self::OPERATOR => ['decide', 'approve', 'ideas', 'rules', 'publish', 'deletePost', 'users', 'sources', 'log', 'ops'],
        self::CLIENT_ADMIN => ['decide', 'approve', 'ideas', 'rules', 'deletePost', 'users', 'sources', 'log'],
        self::CONTENT => ['ideas', 'rules', 'publish'],
        self::READER => [],
    ];

    public static function grants(?string $role, string $permission): bool
    {
        return $role !== null && in_array($permission, self::GRANTS[$role] ?? [], true);
    }

    public static function isClientRole(string $role): bool
    {
        return in_array($role, self::CLIENT_ROLES, true);
    }
}
