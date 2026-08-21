<?php
/**
 * Administration de GB Popin : liste de popins réordonnable, chaque ligne
 * dépliant son formulaire. Le tout est enregistré dans l'option `gb_popin_popins`.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', 'gb_popin_admin_menu');
add_action('admin_init', 'gb_popin_register_settings');

function gb_popin_admin_menu()
{
    add_menu_page(
        'GB Popin',
        'GB Popin',
        'manage_options',
        'gb-popin',
        'gb_popin_settings_page',
        'dashicons-format-image',
        6
    );
}

function gb_popin_register_settings()
{
    register_setting('gb_popin_options_group', GB_POPIN_OPTION, array(
        'type'              => 'array',
        'sanitize_callback' => 'gb_popin_sanitize',
        'default'           => array(),
    ));
}

/**
 * Le HTML du front est mis en cache : sans purge, la popin change en base
 * mais pas à l'écran.
 */
function gb_popin_purge_cache()
{
    if (class_exists('GB_Cache') && method_exists('GB_Cache', 'purge_all')) {
        GB_Cache::purge_all();
    }
}
add_action('update_option_' . GB_POPIN_OPTION, 'gb_popin_purge_cache');
add_action('add_option_' . GB_POPIN_OPTION, 'gb_popin_purge_cache');

/**
 * Scripts et styles, uniquement sur la page du plugin.
 *
 * @param string $hook
 */
function gb_popin_admin_assets($hook)
{
    if ($hook !== 'toplevel_page_gb-popin') {
        return;
    }

    wp_enqueue_media();

    wp_enqueue_style(
        'gb-popin-admin',
        plugins_url('assets/css/gb-popin-admin.css', GB_POPIN_FILE),
        array(),
        GB_POPIN_VERSION
    );

    wp_enqueue_script(
        'gb-popin-admin',
        plugins_url('assets/js/gb-popin-admin.js', GB_POPIN_FILE),
        array('jquery', 'jquery-ui-sortable'),
        GB_POPIN_VERSION,
        true
    );

    wp_localize_script('gb-popin-admin', 'gbPopinAdmin', array(
        'ajaxUrl'      => admin_url('admin-ajax.php'),
        'nonce'        => wp_create_nonce('gb_popin_count_urls'),
        'template'     => gb_popin_render_row(gb_popin_defaults(), '__INDEX__', true),
        'ruleTemplate' => gb_popin_render_rule('', '__INDEX__'),
        'confirmDelete' => __('Supprimer cette popin ?', 'gb-popin'),
    ));
}
add_action('admin_enqueue_scripts', 'gb_popin_admin_assets');

/* -------------------------------------------------------------------------
 * Comptage des URLs correspondant à une expression régulière
 * ---------------------------------------------------------------------- */

function gb_popin_ajax_count_urls()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Accès refusé'), 403);
    }

    check_ajax_referer('gb_popin_count_urls', 'nonce');

    $pattern = isset($_POST['pattern']) ? wp_unslash($_POST['pattern']) : '';

    if (!empty($_POST['refresh'])) {
        gb_popin_get_url_index(true);
    }

    wp_send_json_success(gb_popin_count_matching_urls($pattern));
}
add_action('wp_ajax_gb_popin_count_urls', 'gb_popin_ajax_count_urls');

/* -------------------------------------------------------------------------
 * Page de réglages
 * ---------------------------------------------------------------------- */

function gb_popin_settings_page()
{
    $popins = gb_popin_get_all();
    $index  = gb_popin_get_url_index();
?>
    <div class="wrap gb-popin-admin">
        <h1>GB Popin</h1>
        <p class="description">
            Les popins sont testées de haut en bas : <strong>la première éligible s'affiche</strong>,
            les suivantes sont ignorées pour cette page. Si le visiteur la ferme, la suivante prend
            le relais lors des visites ultérieures. Glissez les lignes pour changer l'ordre.
        </p>

        <form method="post" action="options.php" id="gb-popin-form">
            <?php settings_fields('gb_popin_options_group'); ?>

            <div id="gb-popin-list">
                <?php foreach ($popins as $i => $popin) : ?>
                    <?php echo gb_popin_render_row($popin, $i, false); ?>
                <?php endforeach; ?>
            </div>

            <p class="gb-popin-empty" <?php echo empty($popins) ? '' : 'style="display:none"'; ?>>
                Aucune popin pour le moment.
            </p>

            <p>
                <button type="button" class="button button-secondary" id="gb-popin-add">
                    + Ajouter une popup
                </button>
            </p>

            <?php gb_popin_render_regex_help($index); ?>
            <?php submit_button(); ?>
        </form>
    </div>
<?php
}

/**
 * Une ligne de la liste : en-tête replié + formulaire complet.
 *
 * @param array      $popin
 * @param int|string $index    Index dans le tableau du formulaire.
 * @param bool       $template Rendu du gabarit JavaScript (ligne neuve).
 * @return string
 */
function gb_popin_render_row($popin, $index, $template = false)
{
    $popin = wp_parse_args($popin, gb_popin_defaults());
    $name  = GB_POPIN_OPTION . '[' . $index . ']';
    $title = $popin['title'] !== '' ? $popin['title'] : 'Popin sans titre';

    ob_start();
?>
    <div class="gb-popin-item<?php echo $template ? ' is-open' : ''; ?>" data-index="<?php echo esc_attr($index); ?>">
        <input type="hidden" name="<?php echo esc_attr($name); ?>[id]" value="<?php echo esc_attr($popin['id']); ?>" />
        <input type="hidden" name="<?php echo esc_attr($name); ?>[order]" class="gb-popin-order" value="<?php echo esc_attr($index); ?>" />
        <input type="hidden" name="<?php echo esc_attr($name); ?>[legacy_cookie]" value="<?php echo esc_attr($popin['legacy_cookie']); ?>" />

        <div class="gb-popin-item__header">
            <span class="gb-popin-item__handle dashicons dashicons-menu" title="Glisser pour réordonner"></span>

            <label class="gb-popin-item__active">
                <input type="checkbox" name="<?php echo esc_attr($name); ?>[active]" value="1" <?php checked(1, $popin['active']); ?> />
                Active
            </label>

            <span class="gb-popin-item__title"><?php echo esc_html($title); ?></span>
            <span class="gb-popin-item__summary"><?php echo esc_html(gb_popin_row_summary($popin)); ?></span>

            <button type="button" class="button-link gb-popin-item__toggle" aria-expanded="<?php echo $template ? 'true' : 'false'; ?>">
                <span class="dashicons dashicons-arrow-down-alt2"></span>
            </button>
            <button type="button" class="button-link gb-popin-item__delete" title="Supprimer">
                <span class="dashicons dashicons-trash"></span>
            </button>
        </div>

        <div class="gb-popin-item__body">
            <table class="form-table">
                <tr>
                    <th scope="row"><label>Nom</label></th>
                    <td>
                        <input type="text" class="regular-text gb-popin-field-title" name="<?php echo esc_attr($name); ?>[title]" value="<?php echo esc_attr($popin['title']); ?>" placeholder="Newsletter, soldes d'été…" />
                        <p class="description">Sert uniquement à s'y retrouver ici.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label>Image portrait</label></th>
                    <td><?php echo gb_popin_render_image_field($name, 'portrait_image', $popin['portrait_image']); ?></td>
                </tr>

                <tr>
                    <th scope="row"><label>Image paysage</label></th>
                    <td><?php echo gb_popin_render_image_field($name, 'landscape_image', $popin['landscape_image']); ?></td>
                </tr>

                <tr>
                    <th scope="row"><label>Lien</label></th>
                    <td>
                        <input type="text" class="regular-text" name="<?php echo esc_attr($name); ?>[redirect_link]" value="<?php echo esc_attr($popin['redirect_link']); ?>" placeholder="/newsletter/" />
                        <p class="description">Laissé vide, un clic sur l'image ferme simplement la popin.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label>Délais</label></th>
                    <td class="gb-popin-delays">
                        <label>
                            <input type="number" min="0" name="<?php echo esc_attr($name); ?>[display_time]" value="<?php echo esc_attr($popin['display_time']); ?>" />
                            secondes avant l'affichage
                        </label>
                        <label>
                            <input type="number" min="0" name="<?php echo esc_attr($name); ?>[close_delay]" value="<?php echo esc_attr($popin['close_delay']); ?>" />
                            jours avant réapparition après fermeture
                        </label>
                        <label>
                            <input type="number" min="0" name="<?php echo esc_attr($name); ?>[order_delay]" value="<?php echo esc_attr($popin['order_delay']); ?>" />
                            jours de silence après une commande
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label>Période</label></th>
                    <td>
                        <label>Du <input type="date" name="<?php echo esc_attr($name); ?>[date_start]" value="<?php echo esc_attr($popin['date_start']); ?>" /></label>
                        <label>au <input type="date" name="<?php echo esc_attr($name); ?>[date_end]" value="<?php echo esc_attr($popin['date_end']); ?>" /></label>
                        <p class="description">Dates incluses. Vides = pas de limite.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label>Visiteurs</label></th>
                    <td>
                        <select name="<?php echo esc_attr($name); ?>[audience]">
                            <option value="all" <?php selected('all', $popin['audience']); ?>>Tout le monde</option>
                            <option value="logged_out" <?php selected('logged_out', $popin['audience']); ?>>Visiteurs non connectés</option>
                            <option value="logged_in" <?php selected('logged_in', $popin['audience']); ?>>Clients connectés</option>
                        </select>
                        <label class="gb-popin-inline">
                            <input type="checkbox" name="<?php echo esc_attr($name); ?>[exclude_admins]" value="1" <?php checked(1, $popin['exclude_admins']); ?> />
                            Ne pas afficher aux administrateurs
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label>Pages ciblées</label></th>
                    <td>
                        <select name="<?php echo esc_attr($name); ?>[url_mode]" class="gb-popin-url-mode">
                            <option value="all" <?php selected('all', $popin['url_mode']); ?>>Tout le site</option>
                            <option value="include" <?php selected('include', $popin['url_mode']); ?>>Uniquement les URL qui correspondent</option>
                            <option value="exclude" <?php selected('exclude', $popin['url_mode']); ?>>Partout SAUF les URL qui correspondent</option>
                        </select>

                        <div class="gb-popin-rules" <?php echo $popin['url_mode'] === 'all' ? 'style="display:none"' : ''; ?>>
                            <div class="gb-popin-rules__list" data-name="<?php echo esc_attr($name); ?>">
                                <?php foreach ($popin['url_rules'] as $rule) : ?>
                                    <?php echo gb_popin_render_rule($rule, $index); ?>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="button button-small gb-popin-add-rule">+ Ajouter une règle</button>
                            <p class="description">
                                Une expression régulière par ligne, comparée au chemin de la page
                                (<code>/boutique/mon-produit/</code>), sans le domaine ni les paramètres.
                                Voir la mini-doc en bas de page.
                            </p>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    </div>
<?php
    return ob_get_clean();
}

/**
 * Résumé affiché sur la ligne repliée.
 *
 * @param array $popin
 * @return string
 */
function gb_popin_row_summary($popin)
{
    $parts = array();

    if ($popin['url_mode'] === 'all') {
        $parts[] = 'tout le site';
    } else {
        $count   = count($popin['url_rules']);
        $label   = $popin['url_mode'] === 'exclude' ? 'exclusion' : 'ciblage';
        $parts[] = $label . ' : ' . $count . ' règle' . ($count > 1 ? 's' : '');
    }

    if ($popin['date_start'] !== '' || $popin['date_end'] !== '') {
        $parts[] = 'du ' . ($popin['date_start'] !== '' ? $popin['date_start'] : '…') .
                   ' au ' . ($popin['date_end'] !== '' ? $popin['date_end'] : '…');
    }

    if ($popin['audience'] === 'logged_in') {
        $parts[] = 'clients connectés';
    } elseif ($popin['audience'] === 'logged_out') {
        $parts[] = 'non connectés';
    }

    return implode(' · ', $parts);
}

/**
 * Champ image avec aperçu et sélecteur de médiathèque.
 *
 * @param string $name
 * @param string $field
 * @param int    $attachment_id
 * @return string
 */
function gb_popin_render_image_field($name, $field, $attachment_id)
{
    $attachment_id = (int) $attachment_id;
    $url           = $attachment_id ? wp_get_attachment_image_url($attachment_id, 'medium') : '';

    ob_start();
?>
    <div class="gb-popin-image">
        <input type="hidden" class="gb-popin-image__id" name="<?php echo esc_attr($name . '[' . $field . ']'); ?>" value="<?php echo esc_attr($attachment_id); ?>" />
        <img class="gb-popin-image__preview" src="<?php echo esc_url($url); ?>" alt="" <?php echo $url ? '' : 'style="display:none"'; ?> />
        <button type="button" class="button gb-popin-image__select">Choisir une image</button>
        <button type="button" class="button-link gb-popin-image__remove" <?php echo $url ? '' : 'style="display:none"'; ?>>Retirer</button>
    </div>
<?php
    return ob_get_clean();
}

/**
 * Une règle d'URL : le champ, son compteur de correspondances et sa suppression.
 *
 * @param string     $rule
 * @param int|string $index Index de la popin dans le formulaire.
 * @return string
 */
function gb_popin_render_rule($rule, $index)
{
    $name = GB_POPIN_OPTION . '[' . $index . '][url_rules][]';

    ob_start();
?>
    <div class="gb-popin-rule">
        <input type="text" class="regular-text code gb-popin-rule__input" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($rule); ?>" placeholder="^/boutique/" spellcheck="false" />
        <span class="gb-popin-rule__count" aria-live="polite"></span>
        <button type="button" class="button-link gb-popin-rule__samples" style="display:none">voir</button>
        <button type="button" class="button-link gb-popin-rule__remove" title="Retirer">&times;</button>
        <ul class="gb-popin-rule__list" style="display:none"></ul>
    </div>
<?php
    return ob_get_clean();
}

/**
 * Mini-documentation des expressions régulières.
 *
 * @param array $index
 */
function gb_popin_render_regex_help($index)
{
    $examples = array(
        array('^/$',                         "l'accueil, et rien d'autre"),
        array('^/boutique/',                 'toutes les pages sous /boutique/'),
        array('^/produit/[^/]+/$',           'une fiche produit, mais pas ce qui est en dessous'),
        array('/carte-mentale',              'toute URL contenant « carte-mentale », où que ce soit'),
        array('\.pdf$',                      'les URL qui finissent par .pdf'),
        array('^/(cgv|mentions-legales)/',   'plusieurs pages précises, séparées par une barre verticale'),
        array('^/(?!compte).*',              'tout sauf ce qui commence par /compte'),
    );
?>
    <div class="gb-popin-help">
        <h2>Mini-doc des expressions régulières</h2>
        <p>
            La règle est comparée au <strong>chemin</strong> de la page : ce qui suit le domaine,
            sans les paramètres. Pour <code><?php echo esc_html(home_url('/boutique/mon-produit/?couleur=bleu')); ?></code>,
            la règle voit <code>/boutique/mon-produit/</code>. La casse est ignorée.
        </p>
        <p>
            Attention : une règle <em>non ancrée</em> correspond dès qu'elle apparaît quelque part
            dans le chemin. <code>/boutique</code> correspond donc aussi à <code>/vieille-boutique/</code> ;
            écrivez <code>^/boutique</code> pour l'ancrer au début.
        </p>

        <table class="widefat striped gb-popin-help__table">
            <thead>
                <tr><th style="width:32%">Symbole</th><th>Ce qu'il fait</th></tr>
            </thead>
            <tbody>
                <tr><td><code>^</code></td><td>début du chemin</td></tr>
                <tr><td><code>$</code></td><td>fin du chemin</td></tr>
                <tr><td><code>.</code></td><td>n'importe quel caractère</td></tr>
                <tr><td><code>.*</code></td><td>n'importe quoi, y compris rien</td></tr>
                <tr><td><code>[^/]+</code></td><td>un segment d'URL, sans descendre plus bas</td></tr>
                <tr><td><code>a|b</code></td><td>l'un ou l'autre</td></tr>
                <tr><td><code>(…)</code></td><td>regroupe, souvent utilisé avec <code>|</code></td></tr>
                <tr><td><code>?</code></td><td>l'élément qui précède est facultatif</td></tr>
                <tr><td><code>\.</code></td><td>un vrai point (la barre oblique inverse neutralise le sens spécial)</td></tr>
            </tbody>
        </table>

        <h3>Exemples prêts à l'emploi</h3>
        <table class="widefat striped gb-popin-help__table">
            <thead>
                <tr><th style="width:32%">Règle</th><th>Correspond à</th></tr>
            </thead>
            <tbody>
                <?php foreach ($examples as $example) : ?>
                    <tr>
                        <td><code><?php echo esc_html($example[0]); ?></code></td>
                        <td><?php echo esc_html($example[1]); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p class="description">
            Le compteur affiché à côté de chaque règle s'appuie sur
            <strong><?php echo (int) count($index['paths']); ?> URL</strong> connues du site
            <?php if (!empty($index['truncated'])) : ?>
                (index tronqué à <?php echo (int) GB_POPIN_URL_INDEX_MAX; ?> URL, le compte est indicatif)
            <?php endif; ?>
            — contenus publiés et pages de catégories. Il ne connaît pas les URL générées
            à la volée (pagination, filtres), mais la règle s'y appliquera quand même.
            <button type="button" class="button button-small" id="gb-popin-refresh-index">Recalculer l'index</button>
        </p>
    </div>
<?php
}
