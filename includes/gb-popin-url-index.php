<?php
/**
 * Index des URLs publiques du site, utilisé pour compter les pages
 * correspondant à une expression régulière de ciblage.
 *
 * L'index est mis en cache (transient) : le reconstruire interroge tous les
 * contenus publiés, ce qu'on ne veut pas faire à chaque frappe dans le champ.
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Au-delà, on arrête d'indexer : le comptage devient indicatif. */
define('GB_POPIN_URL_INDEX_MAX', 5000);

/**
 * Liste des chemins publics du site.
 *
 * @param bool $force Reconstruire au lieu de lire le cache.
 * @return array{paths: string[], truncated: bool, built_at: int}
 */
function gb_popin_get_url_index($force = false)
{
    if (!$force) {
        $cached = get_transient(GB_POPIN_URL_INDEX_TRANSIENT);
        if (is_array($cached) && isset($cached['paths'])) {
            return $cached;
        }
    }

    $index = gb_popin_build_url_index();
    set_transient(GB_POPIN_URL_INDEX_TRANSIENT, $index, HOUR_IN_SECONDS);

    return $index;
}

/**
 * Construit l'index : accueil + contenus publiés + termes des taxonomies publiques.
 *
 * @return array{paths: string[], truncated: bool, built_at: int}
 */
function gb_popin_build_url_index()
{
    $paths     = array('/' => true);
    $truncated = false;

    $post_types = get_post_types(array('public' => true), 'names');
    unset($post_types['attachment']);

    foreach ($post_types as $post_type) {
        if ($truncated) {
            break;
        }

        $ids = get_posts(array(
            'post_type'              => $post_type,
            'post_status'            => 'publish',
            'posts_per_page'         => -1,
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'suppress_filters'       => true,
        ));

        // get_permalink() interroge la base pour chaque contenu : on amorce le cache
        // par paquets, sinon on déclenche un millier de requêtes.
        foreach (array_chunk($ids, 200) as $chunk) {
            _prime_post_caches($chunk, false, false);

            foreach ($chunk as $id) {
                $permalink = get_permalink($id);
                if (!$permalink) {
                    continue;
                }

                $paths[gb_popin_normalize_path($permalink)] = true;

                if (count($paths) >= GB_POPIN_URL_INDEX_MAX) {
                    $truncated = true;
                    break 3;
                }
            }
        }
    }

    if (!$truncated) {
        $taxonomies = get_taxonomies(array('public' => true), 'names');

        foreach ($taxonomies as $taxonomy) {
            $terms = get_terms(array(
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
            ));

            if (is_wp_error($terms)) {
                continue;
            }

            foreach ($terms as $term) {
                $link = get_term_link($term);
                if (is_wp_error($link)) {
                    continue;
                }

                $paths[gb_popin_normalize_path($link)] = true;

                if (count($paths) >= GB_POPIN_URL_INDEX_MAX) {
                    $truncated = true;
                    break 2;
                }
            }
        }
    }

    $list = array_keys($paths);
    sort($list);

    return array(
        'paths'     => $list,
        'truncated' => $truncated,
        'built_at'  => time(),
    );
}

/**
 * Compte les chemins du site correspondant à une expression régulière.
 *
 * @param string $pattern
 * @return array{error: string, count: int, total: int, truncated: bool, samples: string[]}
 */
function gb_popin_count_matching_urls($pattern)
{
    $result = array(
        'error'     => '',
        'count'     => 0,
        'total'     => 0,
        'truncated' => false,
        'samples'   => array(),
    );

    $pattern = trim((string) $pattern);
    if ($pattern === '') {
        return $result;
    }

    $error = gb_popin_regex_error($pattern);
    if ($error !== '') {
        $result['error'] = $error;
        return $result;
    }

    $index               = gb_popin_get_url_index();
    $result['total']     = count($index['paths']);
    $result['truncated'] = !empty($index['truncated']);

    foreach ($index['paths'] as $path) {
        if (gb_popin_regex_match($pattern, $path) === true) {
            $result['count']++;
            if (count($result['samples']) < 12) {
                $result['samples'][] = $path;
            }
        }
    }

    return $result;
}

/**
 * L'index devient faux dès qu'un contenu est publié, dépublié ou renommé.
 */
function gb_popin_flush_url_index()
{
    delete_transient(GB_POPIN_URL_INDEX_TRANSIENT);
}
add_action('save_post', 'gb_popin_flush_url_index');
add_action('deleted_post', 'gb_popin_flush_url_index');
add_action('created_term', 'gb_popin_flush_url_index');
add_action('edited_term', 'gb_popin_flush_url_index');
add_action('delete_term', 'gb_popin_flush_url_index');
