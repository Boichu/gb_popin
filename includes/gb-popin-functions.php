<?php
/**
 * Affichage des popins côté visiteur.
 *
 * Le serveur ne choisit pas la popin : il publie la liste de celles qui sont
 * éligibles pour cette page, dans l'ordre de priorité, et le navigateur retient
 * la première que le visiteur n'a pas déjà fermée. Ce partage est imposé par le
 * cache de page (gb-cache) : le HTML anonyme est commun à tous les visiteurs,
 * il ne peut donc pas dépendre de leurs cookies.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Charge la feuille de style et le script du front, avec la configuration.
 */
function gb_popin_enqueue_front()
{
    if (is_admin()) {
        return;
    }

    $eligible = gb_popin_get_eligible();
    if (empty($eligible)) {
        return;
    }

    $config = array();
    foreach ($eligible as $popin) {
        $portrait  = gb_popin_image_html($popin['portrait_image'], 'portrait');
        $landscape = gb_popin_image_html($popin['landscape_image'], 'landscape');

        if ($portrait === '' && $landscape === '') {
            continue;
        }

        $config[] = array(
            'id'         => (int) $popin['id'],
            'title'      => $popin['title'],
            // Les images partent en HTML : tant que le script ne les insère pas
            // dans la page, le navigateur ne les télécharge pas.
            'portrait'   => $portrait,
            'landscape'  => $landscape,
            'link'       => $popin['redirect_link'],
            'delay'      => (int) $popin['display_time'],
            'closeDelay' => (int) $popin['close_delay'],
            'legacy'     => (int) $popin['legacy_cookie'],
            // Une seule image : elle doit servir dans les deux orientations.
            'single'     => ($portrait === '' || $landscape === '') ? 1 : 0,
            // Filet de sécurité si une page en cache survit à la date de fin.
            'start'      => gb_popin_date_to_timestamp($popin['date_start'], false),
            'end'        => gb_popin_date_to_timestamp($popin['date_end'], true),
        );
    }

    if (empty($config)) {
        return;
    }

    wp_enqueue_style(
        'gb_popin_style',
        plugins_url('assets/css/gb_popin.css', GB_POPIN_FILE),
        array(),
        GB_POPIN_VERSION
    );

    wp_enqueue_script(
        'gb-popin-front',
        plugins_url('assets/js/gb-popin-front.js', GB_POPIN_FILE),
        array(),
        GB_POPIN_VERSION,
        true
    );

    wp_add_inline_script(
        'gb-popin-front',
        'window.gbPopinData = ' . wp_json_encode(array('popins' => $config)) . ';',
        'before'
    );
}
add_action('wp_enqueue_scripts', 'gb_popin_enqueue_front');

/**
 * Balise <img> d'une image de popin, chaîne vide si l'image n'existe plus.
 *
 * @param int    $attachment_id
 * @param string $class
 * @return string
 */
function gb_popin_image_html($attachment_id, $class)
{
    $attachment_id = (int) $attachment_id;
    if ($attachment_id < 1) {
        return '';
    }

    $html = wp_get_attachment_image($attachment_id, 'full', false, array(
        'class'    => 'gb-popin__image gb-popin__image--' . $class,
        'decoding' => 'async',
    ));

    return $html ? $html : '';
}

/**
 * Convertit une date de réglage (Y-m-d, fuseau du site) en timestamp UTC.
 *
 * @param string $date
 * @param bool   $end_of_day true pour viser la fin de la journée.
 * @return int|null
 */
function gb_popin_date_to_timestamp($date, $end_of_day)
{
    if (!is_string($date) || $date === '') {
        return null;
    }

    $time      = $end_of_day ? ' 23:59:59' : ' 00:00:00';
    $timestamp = get_gmt_from_date($date . $time, 'U');

    return $timestamp ? (int) $timestamp : null;
}
