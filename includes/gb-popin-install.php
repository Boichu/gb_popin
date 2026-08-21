<?php
/**
 * Appelé à l'activation du plugin.
 *
 * Rien à installer : les popins vivent dans l'option `gb_popin_popins`, créée
 * à la première visite de l'écran de réglages (ou par la migration de l'ancienne
 * popin unique). Voir includes/gb-popin-data.php.
 */

if (!defined('ABSPATH')) {
    exit;
}

function gb_popin_install()
{
    // Reprend l'ancienne configuration si le site tournait en popin unique.
    gb_popin_get_all();
}
