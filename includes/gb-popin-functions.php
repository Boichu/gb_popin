<?php

function gb_display_popin()
{
    // Récupérer les options depuis le backoffice
    $portrait_image = get_option('gb_popin_portrait_image');
    $landscape_image = get_option('gb_popin_landscape_image');
    $redirect_link = get_option('gb_popin_redirect_link');
    $display_time = get_option('gb_popin_display_time');
    $close_delay = get_option('gb_popin_close_delay');
    $order_delay = get_option('gb_popin_order_delay');

    // Vérifier les critères pour afficher la pop-up
    if (should_display_popin($close_delay, $order_delay)) 
    {
        ?>
        <div id="gb-popin-overlay"></div>
        <div id="gb-popin" style="display:none;">
            <a href="<?php echo esc_url($redirect_link); ?>" target="_blank">
                <?php echo wp_get_attachment_image($portrait_image, 'full', false, array('class' => 'portrait', 'loading' => 'lazy')); ?>
                <?php echo wp_get_attachment_image($landscape_image, 'full', false, array('class' => 'landscape', 'loading' => 'lazy')); ?>
            </a>
            <button class="close-button" onclick="document.getElementById('gb-popin-overlay').click()"></button>
        </div>
        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function () {
                setTimeout(function () {
                    document.getElementById('gb-popin-overlay').style.display = 'block';
                    document.getElementById('gb-popin').style.display = 'block';
                }, <?php echo intval($display_time) * 1000; ?>);

                document.getElementById('gb-popin-overlay').addEventListener('click', function () {
                    document.getElementById('gb-popin-overlay').style.display = 'none';
                    document.getElementById('gb-popin').style.display = 'none';
                    document.cookie = "gb_popin_closed=" + Math.floor(Date.now() / 1000) + "; path=/; max-age=" + (<?php echo intval($close_delay) * 24 * 60 * 60; ?>);
                });
            });
        </script>
        <?php
    }
}

function should_display_popin($close_delay, $order_delay)
{
    // Vérifier si la pop-up est active
    $active = get_option('gb_popin_active');
    if (!$active) {
        return false; // Ne pas afficher la pop-up si elle n'est pas active
    }
    // Vérifier si le cookie de fermeture existe et est encore valide
    if (isset($_COOKIE['gb_popin_closed'])) {
        $close_time = intval($_COOKIE['gb_popin_closed']);
        $current_time = time();
        $close_delay_seconds = intval($close_delay) * 24 * 60 * 60; // Convertir les jours en secondes

        if (($current_time - $close_time) < $close_delay_seconds) {
            return false; // Ne pas afficher la pop-up si le délai de fermeture n'est pas écoulé
        }
    }

    // Vérifier si le cookie de commande existe et est encore valide
    // Vérifier la dernière commande de l'utilisateur
    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
        $last_order = function_exists('wc_get_customer_last_order') ? wc_get_customer_last_order($user_id) : null;

        if ($last_order) {
            $order_time = strtotime($last_order->get_date_created());
            $current_time = time();
            $order_delay_seconds = intval($order_delay) * 24 * 60 * 60; // Convertir les jours en secondes

            if (($current_time - $order_time) < $order_delay_seconds) {
                return false; // Ne pas afficher la pop-up si le délai de commande n'est pas écoulé
            }
        }
    }

    return true; // Afficher la pop-up si aucun des délais n'est en cours
}

// Ajouter l'action pour afficher la pop-up dans le front-end
add_action('wp_footer', 'gb_display_popin');

function gb_enqueue_popin_scripts() {
    wp_enqueue_style('gb_popin_style', plugins_url('../assets/css/gb_popin.css', __FILE__));
}
add_action('wp_enqueue_scripts', 'gb_enqueue_popin_scripts');