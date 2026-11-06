<?php
/**
 * Plugin Name: Xperion – belépés kiszűrt Authorization fejléc mellett
 * Description: A sajtófigyelő alkalmazásjelszavas belépését segíti: (1) ha a tárhely kiszűri az „Authorization” fejlécet, a sajtófigyelő ugyanazt az „X-Xperion-Auth” fejlécben is elküldi, és ez a fájl visszateszi a helyére; (2) a REST-kéréseknél akkor is elfogadtatja az alkalmazásjelszót, ha egy másik bővítmény túl korán kérdezi le a felhasználót. A jelszót továbbra is a WordPress ellenőrzi, új jogot nem ad.
 * Version: 1.1.0
 * Requires PHP: 7.4
 *
 * Telepítés: a WordPress gyökérmappájában a wp-content/mu-plugins mappába kell feltölteni (ha nincs ilyen mappa, létre kell hozni).
 * A „must-use” bővítmények magától futnak, bekapcsolni nem kell. Eltávolítás: a fájl törlésével.
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Mit látott ez a kérés (titok nélkül), a sajtófigyelő hibakereséséhez. */
$GLOBALS['xperion_auth_seen'] = [
    'authorization' => !empty($_SERVER['HTTP_AUTHORIZATION']) || !empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) || !empty($_SERVER['PHP_AUTH_USER']),
    'alt' => !empty($_SERVER['HTTP_X_XPERION_AUTH']),
    'failed' => '',
];

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

/** A REST-kérés felismerése a címből: a REST_REQUEST állandó még nem létezik, ha egy bővítmény korán kérdezi le a felhasználót. */
function xperion_auth_is_rest_uri(): bool
{
    $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    $prefix = '/' . trim(function_exists('rest_get_url_prefix') ? rest_get_url_prefix() : 'wp-json', '/') . '/';
    return strpos($uri, $prefix) !== false || isset($_GET['rest_route']);
}

// Csak ha valóban érkezett belépési adat, és a kérés REST-kérés.
add_filter('application_password_is_api_request', static function ($is) {
    if ($is) {
        return $is;
    }
    return !empty($_SERVER['PHP_AUTH_USER']) && xperion_auth_is_rest_uri();
});

// A sikertelen alkalmazásjelszavas belépés oka (pl. incorrect_password), hogy a sajtófigyelő pontosan megmondhassa.
add_action('application_password_failed_authentication', static function ($error) {
    if (is_object($error) && method_exists($error, 'get_error_code')) {
        $GLOBALS['xperion_auth_seen']['failed'] = (string) $error->get_error_code();
    }
});

// Jelzés a REST-indexben: a kiegészítő telepítve van, és mit látott ez a kérés (csak igen/nem és hibakód, titok nélkül).
add_filter('rest_index', static function ($response) {
    if (is_object($response) && method_exists($response, 'get_data')) {
        $data = $response->get_data();
        $data['xperion_auth_header'] = '1.1.0';
        $data['xperion_auth_seen'] = $GLOBALS['xperion_auth_seen'] + ['logged_in' => function_exists('get_current_user_id') && get_current_user_id() > 0];
        $response->set_data($data);
    }
    return $response;
});
