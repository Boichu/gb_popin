<?php
/*  définition de la partie admin pour configurer la popup selon le brief
* C’est une pop-up de bienvenue qui présente la boutique et nos offres commerciales. Au niveau des caractéristiques d’affichage, voici ce que j’aimerais :
* - Affichage après 2 secondes de navigation, sur la page d’arrivée du visiteur (peu importe la page)
* - Quand elle s’affiche, ça assombrit un peu la page en arrière plan (comme tu as fait sur la pop-up du tunnel de commande)
* - Si le client passe commande, la pop-up ne lui sera pas remontrée pendant 5 jours
* - Si le client ferme la pop-up, elle ne lui sera pas remontrée pendant 2 jours ; pour la fermeture de la pop-up, on peut mettre une croix dans le coin supérieur droit (une petite croix blanche par exemple). Sur la version bureau, j’aimerais bien qu’elle se ferme également si on clique en dehors de l’image.
* - Elle sera montrée à tous les visiteurs, même ceux qui sont loggués. Si on peut exclure les administrateurs ce serait top mais sinon c’est pas grave :)
* 
* Pour le format de la pop-up, si celui des images ne t’arrange pas ou ne semble pas convenir on peut bien sûr les revoir. Je peux également faire des exports d’une meilleure définition. 
Pour la version tablette, je ne sais pas ce qui serait le mieux entre les deux formats. Tu me diras :)
* 
*/
/*
* Il faut que la popup soit configurée dans le backoffice.
* Les variables à définir sont :
* - L'image format portrait
* - l'image format paysage
* - Le lien de redirection
* - Le temps d'affichage
* - Le temps de non-affichage si le client ferme la popup
* - Le temps de non-affichage si le client passe commande
*/

add_action('admin_menu', 'gb_popin_admin_menu');
add_action('admin_init', 'gb_popin_register_settings');

function gb_popin_admin_menu()
{
    add_menu_page(
        'GB Popin', // Page title
        'GB Popin', // Menu title
        'manage_options', // Capability
        'gb-popin', // Menu slug
        'gb_popin_settings_page', // Callback function
        '', // Icon URL (optional)
        6 // Position (mettre une valeur non nulle)
    );
}

function gb_popin_register_settings()
{
    // Définir des valeurs par défaut pour les options
    $default_options = array(
        'gb_popin_active' => '',
        'gb_popin_portrait_image' => '',
        'gb_popin_landscape_image' => '',
        'gb_popin_redirect_link' => '/',
        'gb_popin_display_time' => '3',
        'gb_popin_close_delay' => '2',
        'gb_popin_order_delay' => '5'
    );

    // Initialiser les options avec des valeurs par défaut si elles ne sont pas définies
    foreach ($default_options as $option_name => $default_value) {
        if (get_option($option_name) === false) {
            add_option($option_name, $default_value);
        }
    }

    register_setting('gb_popin_options_group', 'gb_popin_active');
    register_setting('gb_popin_options_group', 'gb_popin_portrait_image');
    register_setting('gb_popin_options_group', 'gb_popin_landscape_image');
    register_setting('gb_popin_options_group', 'gb_popin_redirect_link');
    register_setting('gb_popin_options_group', 'gb_popin_display_time');
    register_setting('gb_popin_options_group', 'gb_popin_close_delay');
    register_setting('gb_popin_options_group', 'gb_popin_order_delay');
}

function gb_popin_settings_page()
{

?>
    <div class="wrap">
        <h1>GB Popin Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('gb_popin_options_group'); ?>
            <?php do_settings_sections('gb_popin_options_group'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Active</th>
                    <td><input type="checkbox" name="gb_popin_active" value="1" <?php checked(1, get_option('gb_popin_active'), true); ?> /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Portrait Image</th>
                    <td>
                        <input type="hidden" class="champ_hidden" id="gb_popin_portrait_image" name="gb_popin_portrait_image" value="<?php echo esc_attr(get_option('gb_popin_portrait_image')); ?>" />
                        <img class="upload-image" id="gb_popin_portrait_image_preview" src="<?php echo wp_get_attachment_url(get_option('gb_popin_portrait_image')); ?>" style="max-width: 150px; display: <?php echo get_option('gb_popin_portrait_image') ? 'block' : 'none'; ?>;" />
                        <button type="button" class="button upload-button" id="gb_popin_portrait_image_button" >Select Image</button>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Landscape Image</th>
                    <td>
                        <input type="hidden" class="champ_hidden" id="gb_popin_landscape_image" name="gb_popin_landscape_image" value="<?php echo esc_attr(get_option('gb_popin_landscape_image')); ?>" />
                        <img class="upload-image" id="gb_popin_landscape_image_preview" src="<?php echo wp_get_attachment_url(get_option('gb_popin_landscape_image')); ?>" style="max-width: 150px; display: <?php echo get_option('gb_popin_landscape_image') ? 'block' : 'none'; ?>;" />
                        <button type="button" class="button upload-button" id="gb_popin_landscape_image_button" >Select Image</button>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Lien</th>
                    <td><input type="text" name="gb_popin_redirect_link" value="<?php echo esc_attr(get_option('gb_popin_redirect_link')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Secondes avant l'affichage</th>
                    <td><input type="text" name="gb_popin_display_time" value="<?php echo esc_attr(get_option('gb_popin_display_time')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Nombre de jours avant réapparition</th>
                    <td><input type="text" name="gb_popin_close_delay" value="<?php echo esc_attr(get_option('gb_popin_close_delay')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Nombre de jours après une commande</th>
                    <td><input type="text" name="gb_popin_order_delay" value="<?php echo esc_attr(get_option('gb_popin_order_delay')); ?>" /></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
<?php

}

function gb_popin_enqueue_media_uploader() {
    wp_enqueue_media();
    wp_enqueue_script('gb-popin-media-uploader', plugin_dir_url(__FILE__) . '../assets/js/gb-popin-media-uploader.js', array('jquery'), null, true);
}
add_action('admin_enqueue_scripts', 'gb_popin_enqueue_media_uploader');



?>