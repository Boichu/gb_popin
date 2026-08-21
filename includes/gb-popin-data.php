<?php
/**
 * Couche données de GB Popin : modèle, migration, règles d'éligibilité.
 *
 * Depuis la 1.1.0, toutes les popins vivent dans une seule option `gb_popin_popins`
 * (tableau ordonné : le premier éligible gagne). Les anciennes options plates
 * `gb_popin_*` sont conservées telles quelles et servent à la migration.
 */

if (!defined('ABSPATH')) {
    exit;
}

define('GB_POPIN_OPTION', 'gb_popin_popins');
define('GB_POPIN_URL_INDEX_TRANSIENT', 'gb_popin_url_index');

/**
 * Valeurs par défaut d'une popin.
 */
function gb_popin_defaults()
{
    return array(
        'id'              => 0,
        'title'           => '',
        'active'          => 0,
        'portrait_image'  => 0,
        'landscape_image' => 0,
        'redirect_link'   => '',
        'display_time'    => 3,   // secondes
        'close_delay'     => 2,   // jours
        'order_delay'     => 5,   // jours
        'date_start'      => '',  // Y-m-d
        'date_end'        => '',  // Y-m-d
        'audience'        => 'all', // all | logged_in | logged_out
        'exclude_admins'  => 0,
        'url_mode'        => 'all', // all | include | exclude
        'url_rules'       => array(),
        'legacy_cookie'   => 0,   // honore aussi l'ancien cookie gb_popin_closed
    );
}

/**
 * Toutes les popins enregistrées, dans l'ordre d'affichage.
 *
 * @return array[]
 */
function gb_popin_get_all()
{
    $popins = get_option(GB_POPIN_OPTION, null);

    if (!is_array($popins)) {
        $popins = gb_popin_migrate_legacy();
    }

    $out = array();
    foreach ($popins as $popin) {
        if (is_array($popin)) {
            $out[] = wp_parse_args($popin, gb_popin_defaults());
        }
    }

    return $out;
}

/**
 * Reprend l'ancienne popin unique (options plates) sous forme de première popin.
 * Les options d'origine ne sont pas supprimées : rien n'est perdu si on revient en arrière.
 *
 * @return array[]
 */
function gb_popin_migrate_legacy()
{
    $portrait  = (int) get_option('gb_popin_portrait_image');
    $landscape = (int) get_option('gb_popin_landscape_image');

    // Rien à migrer : on part d'une liste vide.
    if (!$portrait && !$landscape) {
        return array();
    }

    $popin = wp_parse_args(array(
        'id'              => 1,
        'title'           => 'Popin de bienvenue',
        'active'          => get_option('gb_popin_active') ? 1 : 0,
        'portrait_image'  => $portrait,
        'landscape_image' => $landscape,
        'redirect_link'   => (string) get_option('gb_popin_redirect_link'),
        'display_time'    => (int) get_option('gb_popin_display_time'),
        'close_delay'     => (int) get_option('gb_popin_close_delay'),
        'order_delay'     => (int) get_option('gb_popin_order_delay'),
        'legacy_cookie'   => 1,
    ), gb_popin_defaults());

    $popins = array($popin);
    update_option(GB_POPIN_OPTION, $popins);

    return $popins;
}

/**
 * Nettoie et réordonne les popins reçues du formulaire d'administration.
 *
 * @param mixed $input
 * @return array[]
 */
function gb_popin_sanitize($input)
{
    if (!is_array($input)) {
        return array();
    }

    $defaults = gb_popin_defaults();
    $clean    = array();
    $used_ids = array();

    foreach ($input as $raw) {
        if (!is_array($raw)) {
            continue;
        }

        $popin = $defaults;

        $popin['title']           = sanitize_text_field($raw['title'] ?? '');
        $popin['active']          = empty($raw['active']) ? 0 : 1;
        $popin['portrait_image']  = absint($raw['portrait_image'] ?? 0);
        $popin['landscape_image'] = absint($raw['landscape_image'] ?? 0);
        $popin['display_time']    = max(0, (int) ($raw['display_time'] ?? 3));
        $popin['close_delay']     = max(0, (int) ($raw['close_delay'] ?? 2));
        $popin['order_delay']     = max(0, (int) ($raw['order_delay'] ?? 5));
        $popin['exclude_admins']  = empty($raw['exclude_admins']) ? 0 : 1;
        $popin['legacy_cookie']   = empty($raw['legacy_cookie']) ? 0 : 1;

        // Lien : une URL absolue ou un chemin relatif ("/newsletter/").
        $link = trim((string) ($raw['redirect_link'] ?? ''));
        if ($link !== '' && strpos($link, '/') === 0) {
            $popin['redirect_link'] = esc_url_raw($link, array('http', 'https'));
            if ($popin['redirect_link'] === '') {
                $popin['redirect_link'] = '/' . ltrim(sanitize_text_field($link), '/');
            }
        } else {
            $popin['redirect_link'] = esc_url_raw($link, array('http', 'https'));
        }

        foreach (array('date_start', 'date_end') as $field) {
            $date = trim((string) ($raw[$field] ?? ''));
            $popin[$field] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : '';
        }

        $audience = (string) ($raw['audience'] ?? 'all');
        $popin['audience'] = in_array($audience, array('all', 'logged_in', 'logged_out'), true) ? $audience : 'all';

        $url_mode = (string) ($raw['url_mode'] ?? 'all');
        $popin['url_mode'] = in_array($url_mode, array('all', 'include', 'exclude'), true) ? $url_mode : 'all';

        // Règles d'URL : une expression régulière par entrée, dédoublonnées.
        $rules = $raw['url_rules'] ?? array();
        if (is_string($rules)) {
            $rules = preg_split('/\R/', $rules);
        }
        $popin['url_rules'] = array();
        if (is_array($rules)) {
            foreach ($rules as $rule) {
                $rule = trim((string) $rule);
                if ($rule !== '' && !in_array($rule, $popin['url_rules'], true)) {
                    $popin['url_rules'][] = $rule;
                }
            }
        }

        // Identifiant stable : conservé s'il existe et n'est pas déjà pris.
        $id = absint($raw['id'] ?? 0);
        if ($id < 1 || in_array($id, $used_ids, true)) {
            $id = empty($used_ids) ? 1 : max($used_ids) + 1;
        }
        $used_ids[]   = $id;
        $popin['id']  = $id;

        // Sert uniquement au tri, n'est pas stocké.
        $clean[] = array('order' => (int) ($raw['order'] ?? count($clean)), 'popin' => $popin);
    }

    usort($clean, function ($a, $b) {
        return $a['order'] <=> $b['order'];
    });

    return wp_list_pluck($clean, 'popin');
}

/* -------------------------------------------------------------------------
 * Règles d'URL
 * ---------------------------------------------------------------------- */

/**
 * Chemin de la page courante, tel que comparé aux expressions régulières
 * (sans domaine, sans paramètres, sans sous-dossier d'installation).
 *
 * @return string
 */
function gb_popin_current_path()
{
    $uri  = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
    $path = wp_parse_url($uri, PHP_URL_PATH);

    return gb_popin_normalize_path(is_string($path) ? $path : '/');
}

/**
 * Ramène une URL ou un chemin à la forme comparée aux règles : "/chemin/".
 *
 * @param string $url
 * @return string
 */
function gb_popin_normalize_path($url)
{
    $path = wp_parse_url($url, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        $path = '/';
    }

    // Retirer le sous-dossier d'installation si WordPress n'est pas à la racine.
    $home = wp_parse_url(home_url('/'), PHP_URL_PATH);
    if (is_string($home) && $home !== '' && $home !== '/' && strpos($path, $home) === 0) {
        $path = substr($path, strlen($home) - 1);
    }

    return '/' . ltrim($path, '/');
}

/**
 * Applique une expression régulière saisie en administration.
 *
 * L'utilisateur saisit le motif nu ("^/produit/"), sans délimiteurs : on les ajoute
 * ici et on échappe les # non échappés pour ne pas casser le motif.
 *
 * @param string $pattern
 * @param string $subject
 * @return bool|null true/false, ou null si l'expression est invalide.
 */
function gb_popin_regex_match($pattern, $subject)
{
    $pattern = (string) $pattern;
    if ($pattern === '') {
        return null;
    }

    $escaped = preg_replace('/(?<!\\\\)#/', '\\#', $pattern);
    $result  = @preg_match('#' . $escaped . '#i', $subject);

    if ($result === false) {
        return null;
    }

    return (bool) $result;
}

/**
 * Message d'erreur si l'expression régulière est invalide, chaîne vide sinon.
 *
 * @param string $pattern
 * @return string
 */
function gb_popin_regex_error($pattern)
{
    $pattern = (string) $pattern;
    if ($pattern === '') {
        return '';
    }

    // preg_last_error_msg() ne dit que « Internal error » sur un motif qui ne compile
    // pas : le message utile (« missing closing parenthesis at offset 9 ») passe par
    // une alerte PHP, qu'on intercepte ici pour la montrer telle quelle.
    $message = '';
    set_error_handler(function ($errno, $errstr) use (&$message) {
        $message = $errstr;
        return true;
    });

    $escaped = preg_replace('/(?<!\\\\)#/', '\\#', $pattern);
    $result  = preg_match('#' . $escaped . '#i', '/test/');

    restore_error_handler();

    if ($result !== false) {
        return '';
    }

    $message = trim(str_replace(array('preg_match():', 'Compilation failed:'), '', $message));

    return $message !== '' ? $message : 'expression régulière invalide';
}

/**
 * La popin doit-elle s'afficher sur ce chemin ?
 *
 * @param array  $popin
 * @param string $path
 * @return bool
 */
function gb_popin_path_matches($popin, $path)
{
    if ($popin['url_mode'] === 'all' || empty($popin['url_rules'])) {
        return $popin['url_mode'] !== 'include';
    }

    $matched = false;
    foreach ($popin['url_rules'] as $rule) {
        if (gb_popin_regex_match($rule, $path) === true) {
            $matched = true;
            break;
        }
    }

    return $popin['url_mode'] === 'exclude' ? !$matched : $matched;
}

/* -------------------------------------------------------------------------
 * Éligibilité
 * ---------------------------------------------------------------------- */

/**
 * Les popins affichables sur la page courante, dans l'ordre de priorité.
 *
 * Le tri des cookies de fermeture est fait côté navigateur : le HTML anonyme
 * est mis en cache par gb-cache, il ne doit donc dépendre d'aucun cookie.
 *
 * @return array[]
 */
function gb_popin_get_eligible()
{
    $path      = gb_popin_current_path();
    $today     = current_time('Y-m-d');
    $logged_in = is_user_logged_in();
    $eligible  = array();

    foreach (gb_popin_get_all() as $popin) {
        if (!$popin['active']) {
            continue;
        }

        // Une popin sans image n'a rien à montrer.
        if (!$popin['portrait_image'] && !$popin['landscape_image']) {
            continue;
        }

        if ($popin['date_start'] !== '' && $today < $popin['date_start']) {
            continue;
        }

        if ($popin['date_end'] !== '' && $today > $popin['date_end']) {
            continue;
        }

        if ($popin['audience'] === 'logged_in' && !$logged_in) {
            continue;
        }

        if ($popin['audience'] === 'logged_out' && $logged_in) {
            continue;
        }

        if ($popin['exclude_admins'] && current_user_can('manage_options')) {
            continue;
        }

        if (!gb_popin_path_matches($popin, $path)) {
            continue;
        }

        if (gb_popin_ordered_recently($popin, $logged_in)) {
            continue;
        }

        $eligible[] = $popin;
    }

    return $eligible;
}

/**
 * Le client a-t-il commandé trop récemment pour revoir cette popin ?
 *
 * @param array $popin
 * @param bool  $logged_in
 * @return bool
 */
function gb_popin_ordered_recently($popin, $logged_in)
{
    if (!$logged_in || $popin['order_delay'] < 1 || !function_exists('wc_get_customer_last_order')) {
        return false;
    }

    $last_order = wc_get_customer_last_order(get_current_user_id());
    if (!$last_order) {
        return false;
    }

    $order_date = $last_order->get_date_created();
    if (!$order_date) {
        return false;
    }

    $elapsed = time() - $order_date->getTimestamp();

    return $elapsed < ($popin['order_delay'] * DAY_IN_SECONDS);
}
