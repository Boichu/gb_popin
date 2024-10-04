<?php
/*
Plugin Name: GB Popin
Plugin URI: https://github.com/Boichu/gb_popin
Description: Gestion d'une popin de bienvenue légère avec une image en paysage et une autre en portrait, un lien de redirection, un temps d'affichage, un temps de non-affichage si le client ferme la popin et un temps de non-affichage si le client passe commande.
Version: 1.0.0
Author: Votre Nom
Author URI: https://votre-site.com
License: GPL2
GitHub Plugin URI: https://github.com/Boichu/gb_popin
GitHub Branch: main
*/

// Sécurité pour éviter l'exécution directe du fichier PHP
if (!defined('ABSPATH')) {
    exit;
}

// Activation du plugin : création des tables
function gb_popin_activation() {
    require_once plugin_dir_path(__FILE__) . 'includes/gb-popin-install.php';
    gb_popin_install();

    // Forcer WordPress à vérifier les mises à jour des plugins
    set_site_transient('update_plugins', null);
    wp_update_plugins();
}
register_activation_hook(__FILE__, 'gb_popin_activation');




// Désactivation du plugin : nettoyage optionnel
function gb_popin_deactivation() {
    // Code pour nettoyer si nécessaire
}
register_deactivation_hook(__FILE__, 'gb_popin_deactivation');

// Inclure les fichiers nécessaires
function gb_popin_init() {
    require_once plugin_dir_path(__FILE__) . 'includes/gb-popin-functions.php';
    // Vérifier si on est dans l'admin pour inclure les fichiers de gestion des clients
    if (is_admin()) {
        require_once plugin_dir_path(__FILE__) . 'includes/gb-popin-admin-functions.php';
    }
}
add_action('init', 'gb_popin_init');

function load_select2() {
    // Charger jQuery
    wp_enqueue_script('jquery');

    // Charger Select2
    wp_enqueue_script('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array('jquery'), '4.0.13', true);

    // Charger le CSS de Select2
    wp_enqueue_style('select2-css', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css', array(), '4.0.13', 'all');


    wp_enqueue_script('jquery-ui-autocomplete');
    wp_enqueue_script('my-autocomplete-script', plugin_dir_url(__FILE__)  . 'assets/js/autocomplete.js', array('jquery', 'jquery-ui-autocomplete'), null, true);
    // Passer l'URL AJAX à notre script
    wp_localize_script('my-autocomplete-script', 'myAutocomplete', array('ajaxurl' => admin_url('admin-ajax.php')));

    wp_enqueue_script('gb-popin-ajax', plugin_dir_url(__FILE__) . 'assets/js/gb_popin.js', array('jquery'), null, true);
    wp_localize_script('gb-popin-ajax', 'gbpopinAjax', array('ajaxurl' => admin_url('admin-ajax.php')));

}
add_action('admin_enqueue_scripts', 'load_select2');





function gb_popin_check_for_updates($transient) {
    if (empty($transient->checked)) {
        return $transient;
    }

    $plugin_slug = 'gb-popin';
    $github_api_url = 'https://api.github.com/repos/votre-utilisateur/gb-popin/releases/latest';

    $response = wp_remote_get($github_api_url);
    if (is_wp_error($response)) {
        return $transient;
    }

    $release = json_decode(wp_remote_retrieve_body($response));
    if (version_compare($release->tag_name, $transient->checked[$plugin_slug . '/' . $plugin_slug . '.php'], '>')) {
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
}