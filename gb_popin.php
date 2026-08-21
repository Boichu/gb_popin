<?php
/*
Plugin Name: GB Popin
Plugin URI: https://github.com/Boichu/gb_popin
Description: Gestion de plusieurs popins : images portrait et paysage, lien de redirection, délais d'affichage et de réapparition, période de diffusion, ciblage par visiteur et par URL (expressions régulières). La première popin éligible s'affiche.
Version: 1.1.0
Author: Gaétan Boishue
Author URI: https://www.pagespeedlab.com/
License: GPL2
GitHub Plugin URI: https://github.com/Boichu/gb_popin
GitHub Branch: main
*/

// Sécurité pour éviter l'exécution directe du fichier PHP
if (!defined('ABSPATH')) {
    exit;
}

define('GB_POPIN_FILE', __FILE__);
define('GB_POPIN_VERSION', '1.1.0');

require_once plugin_dir_path(__FILE__) . 'includes/gb-popin-data.php';
require_once plugin_dir_path(__FILE__) . 'includes/gb-popin-url-index.php';
require_once plugin_dir_path(__FILE__) . 'includes/gb-popin-functions.php';

// Activation du plugin
function gb_popin_activation() {
    require_once plugin_dir_path(__FILE__) . 'includes/gb-popin-install.php';
    gb_popin_install();

    // Forcer WordPress à vérifier les mises à jour des plugins
    set_site_transient('update_plugins', null);
    wp_update_plugins();
}
register_activation_hook(__FILE__, 'gb_popin_activation');

// Charger l'administration uniquement là où elle sert.
function gb_popin_init() {
    if (is_admin()) {
        require_once plugin_dir_path(__FILE__) . 'includes/gb-popin-admin-functions.php';
    }
}
add_action('init', 'gb_popin_init');




function gb_popin_check_for_updates($transient) {
    if (empty($transient->checked)) {
        return $transient;
    }

    $plugin_slug = 'gb-popin';
    $github_api_url = 'https://api.github.com/repos/Boichu/gb-popin/releases/latest';

    $response = wp_remote_get($github_api_url);
    if (is_wp_error($response)) {
        return $transient;
    }

    $release = json_decode(wp_remote_retrieve_body($response));
    if (is_object($release) && isset($release->tag_name) && isset($transient->checked[$plugin_slug . '/' . $plugin_slug . '.php']) && version_compare($release->tag_name, $transient->checked[$plugin_slug . '/' . $plugin_slug . '.php'], '>')) {
        $transient->response[$plugin_slug . '/' . $plugin_slug . '.php'] = (object) array(
            'new_version' => $release->tag_name,
            'package' => $release->zipball_url,
            'slug' => $plugin_slug,
        );
    }

    return $transient;
}
add_filter('pre_set_site_transient_update_plugins', 'gb_popin_check_for_updates');




function gb_popin_plugin_info($res, $action, $args) {
    if ($action !== 'plugin_information') {
        return $res;
    }

    $plugin_slug = 'gb-popin';
    if ($args->slug !== $plugin_slug) {
        return $res;
    }

    $github_api_url = 'https://github.com/Boichu/gb_popin';

    $response = wp_remote_get($github_api_url);
    if (is_wp_error($response)) {
        return $res;
    }

    $repo = json_decode(wp_remote_retrieve_body($response));
    $res = (object) array(
        'name' => $repo->name,
        'slug' => $plugin_slug,
        'version' => $repo->tag_name,
        'author' => '<a href="' . $repo->owner->html_url . '">' . $repo->owner->login . '</a>',
        'homepage' => $repo->html_url,
        'download_link' => $repo->zipball_url,
        'sections' => array(
            'description' => $repo->description,
        ),
    );

    return $res;
}
add_filter('plugins_api', 'gb_popin_plugin_info', 10, 3);




// Planifier un événement pour vérifier les mises à jour des plugins toutes les 12 heures
if (!wp_next_scheduled('gb_popin_check_for_updates')) {
    wp_schedule_event(time(), 'twicedaily', 'gb_popin_check_for_updates');
}

// Ajouter l'action pour vérifier les mises à jour des plugins
add_action('gb_popin_check_for_updates', 'gb_popin_force_update_check');

function gb_popin_force_update_check() {
    // Forcer WordPress à vérifier les mises à jour des plugins
    set_site_transient('update_plugins', null);
    wp_update_plugins();
}

// Nettoyer l'événement planifié lors de la désactivation du plugin
register_deactivation_hook(__FILE__, 'gb_popin_deactivate');

function gb_popin_deactivate() {
    wp_clear_scheduled_hook('gb_popin_check_for_updates');
    gb_popin_flush_url_index();
}
