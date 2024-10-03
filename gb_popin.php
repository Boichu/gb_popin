<?php
/**
 * Plugin Name: GB popin
 * Description: Gestion d'une popin de bienvenue
 * Version: 1.0
 * Author: Gaétan Boishue
 * 
 * 
 * 
 * C’est une pop-up de bienvenue qui présente la boutique et nos offres commerciales. Au niveau des caractéristiques d’affichage, voici ce que j’aimerais :
 * - Affichage après 2 secondes de navigation, sur la page d’arrivée du visiteur (peu importe la page)
 * - Quand elle s’affiche, ça assombrit un peu la page en arrière plan (comme tu as fait sur la pop-up du tunnel de commande)
 * - Si le client passe commande, la pop-up ne lui sera pas remontrée pendant 5 jours
 * - Si le client ferme la pop-up, elle ne lui sera pas remontrée pendant 2 jours ; pour la fermeture de la pop-up, on peut mettre une croix dans le coin supérieur droit (une petite croix blanche par exemple). Sur la version bureau, j’aimerais bien qu’elle se ferme également si on clique en dehors de l’image.
 * - Elle sera montrée à tous les visiteurs, même ceux qui sont loggués. Si on peut exclure les administrateurs ce serait top mais sinon c’est pas grave :)
 * 
 * Pour le format de la pop-up, si celui des images ne t’arrange pas ou ne semble pas convenir on peut bien sûr les revoir. Je peux également faire des exports d’une meilleure définition. Pour la version tablette, je ne sais pas ce qui serait le mieux entre les deux formats. Tu me diras :)
 * 
 * 
 */

// Sécurité pour éviter l'exécution directe du fichier PHP
if (!defined('ABSPATH')) {
    exit;
}

// Activation du plugin : création des tables
function gb_popin_activation() {
    require_once plugin_dir_path(__FILE__) . 'includes/gb-popin-install.php';
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



