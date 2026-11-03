<?php
/**
 * Plugin Name: Xperion – belépés kiszűrt Authorization fejléc mellett
 * Description: Ha a tárhely kiszűri az „Authorization” fejlécet, a sajtófigyelő ugyanazt a belépést (felhasználó + alkalmazásjelszó) az „X-Xperion-Auth” fejlécben is elküldi; ez a fájl visszateszi a helyére. A jelszót továbbra is a WordPress ellenőrzi, új jogot nem ad.
 * Version: 1.0.0
 * Requires PHP: 7.4
 *
 * Telepítés: a WordPress gyökérmappájában a wp-content/mu-plugins mappába kell feltölteni (ha nincs ilyen mappa, létre kell hozni).
 * A „must-use” bővítmények magától futnak, bekapcsolni nem kell. Eltávolítás: a fájl törlése.
 */

if (!defined('ABSPATH')) {
    exit;
}

(static function () {
    $alt = isset($_SERVER['HTTP_X_XPERION_AUTH']) ? trim((string) $_SERVER['HTTP_X_XPERION_AUTH']) : '';
    if ($alt === '' || stripos($alt, 'Basic ') !== 0) {
        return;
    }
    // Ha az eredeti fejléc mégis megérkezett, azt hagyjuk érvényben.
    if (!empty($_SERVER['HTTP_AUTHORIZATION']) || !empty($_SERVER['PHP_AUTH_USER'])) {
        return;
    }
    $decoded = base64_decode(substr($alt, 6), true);
    if ($decoded === false || strpos($decoded, ':') === false) {
        return;
    }
    list($user, $pass) = explode(':', $decoded, 2);
    $_SERVER['HTTP_AUTHORIZATION'] = $alt;
    $_SERVER['PHP_AUTH_USER'] = $user;
    $_SERVER['PHP_AUTH_PW'] = $pass;
})();

// Jelzés a REST-indexben, hogy a sajtófigyelő lássa: a kiegészítő telepítve van.
add_filter('rest_index', static function ($response) {
    if (is_object($response) && method_exists($response, 'get_data')) {
        $data = $response->get_data();
        $data['xperion_auth_header'] = '1.0.0';
        $response->set_data($data);
    }
    return $response;
});
