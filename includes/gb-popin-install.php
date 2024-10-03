user_ip varchar(55) DEFAULT '' NOT NULL,

<?php

function gb_popin_install()
{
    /*  définition de la partie admin pour configurer la popup selon le brief
     * C’est une pop-up de bienvenue qui présente la boutique et nos offres commerciales. Au niveau des caractéristiques d’affichage, voici ce que j’aimerais :
        * - Affichage après 2 secondes de navigation, sur la page d’arrivée du visiteur (peu importe la page)
        * - Quand elle s’affiche, ça assombrit un peu la page en arrière plan (comme tu as fait sur la pop-up du tunnel de commande)
        * - Si le client passe commande, la pop-up ne lui sera pas remontrée pendant 5 jours
        * - Si le client ferme la pop-up, elle ne lui sera pas remontrée pendant 2 jours ; pour la fermeture de la pop-up, on peut mettre une croix dans le coin supérieur droit (une petite croix blanche par exemple). Sur la version bureau, j’aimerais bien qu’elle se ferme également si on clique en dehors de l’image.
        * - Elle sera montrée à tous les visiteurs, même ceux qui sont loggués. Si on peut exclure les administrateurs ce serait top mais sinon c’est pas grave :)
        * 
        * Pour le format de la pop-up, si celui des images ne t’arrange pas ou ne semble pas convenir on peut bien sûr les revoir. Je peux également faire des exports d’une meilleure définition. Pour la version tablette, je ne sais pas ce qui serait le mieux entre les deux formats. Tu me diras :)
        * 
        */
    global $wpdb;
    $table_name = $wpdb->prefix . 'gb_popin';
    $charset_collate = $wpdb->get_charset_collate();
    //il faut que la table existe
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

}