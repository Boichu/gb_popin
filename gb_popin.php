<?php
/**
 * Plugin Name: GB TODOS
 * Description: Gestion des clients et des todos.
 * Version: 1.0
 * Author: Gaétan Boishue
 */

// Sécurité pour éviter l'exécution directe du fichier PHP
if (!defined('ABSPATH')) {
    exit;
}

// Activation du plugin : création des tables
function gb_popin_activation() {
    require_once plugin_dir_path(__FILE__) . 'includes/gb-todos-install.php';
    gb_popin_install();
}
register_activation_hook(__FILE__, 'gb_popin_activation');

// Désactivation du plugin : nettoyage optionnel
function gb_popin_deactivation() {
    // Code pour nettoyer si nécessaire
}
register_deactivation_hook(__FILE__, 'gb_popin_deactivation');

// Inclure les fichiers nécessaires
function gb_popin_init() {
    require_once plugin_dir_path(__FILE__) . 'includes/gb-todos-functions.php';
    // Vérifier si on est dans l'admin pour inclure les fichiers de gestion des clients
    if (is_admin()) {
        require_once plugin_dir_path(__FILE__) . 'includes/gb-todos-clients-functions.php';
        require_once plugin_dir_path(__FILE__) . 'includes/gb-todos-todos-functions.php';
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

    wp_enqueue_script('gb-todos-ajax', plugin_dir_url(__FILE__) . 'assets/js/todos.js', array('jquery'), null, true);
    wp_localize_script('gb-todos-ajax', 'gbTodosAjax', array('ajaxurl' => admin_url('admin-ajax.php')));

}
add_action('admin_enqueue_scripts', 'load_select2');



