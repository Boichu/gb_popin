/**
 * GB Popin — écran d'administration.
 *
 * Liste réordonnable, formulaires dépliants, sélection d'images et comptage
 * en direct des URL correspondant à chaque expression régulière.
 */
jQuery(function ($) {
    'use strict';

    var $list = $('#gb-popin-list');
    if (!$list.length) {
        return;
    }

    // Index utilisés dans les noms de champs : jamais réutilisés, même après
    // suppression, pour que deux popins ne partagent pas la même clé au POST.
    var nextIndex = 0;
    $list.find('.gb-popin-item').each(function () {
        nextIndex = Math.max(nextIndex, parseInt($(this).data('index'), 10) + 1);
    });

    /** Réécrit l'ordre après un glisser-déposer ou un ajout. */
    function refreshOrder() {
        $list.find('.gb-popin-item').each(function (position) {
            $(this).find('> .gb-popin-order').val(position);
        });
        $('.gb-popin-empty').toggle($list.find('.gb-popin-item').length === 0);
    }

    $list.sortable({
        handle: '.gb-popin-item__handle',
        axis: 'y',
        placeholder: 'gb-popin-item__placeholder',
        forcePlaceholderSize: true,
        update: refreshOrder
    });

    /* ---------------------------------------------------------------- Lignes */

    $('#gb-popin-add').on('click', function () {
        var html = gbPopinAdmin.template.split('__INDEX__').join(String(nextIndex));
        nextIndex++;
        $list.append(html);
        refreshOrder();
        $list.find('.gb-popin-item').last().find('.gb-popin-field-title').trigger('focus');
    });

    $list.on('click', '.gb-popin-item__toggle', function () {
        var $item = $(this).closest('.gb-popin-item');
        var open = !$item.hasClass('is-open');
        $item.toggleClass('is-open', open);
        $(this).attr('aria-expanded', open ? 'true' : 'false');
    });

    $list.on('click', '.gb-popin-item__delete', function () {
        if (window.confirm(gbPopinAdmin.confirmDelete)) {
            $(this).closest('.gb-popin-item').remove();
            refreshOrder();
        }
    });

    // Le titre de l'en-tête suit la saisie, pour rester lisible une fois replié.
    $list.on('input', '.gb-popin-field-title', function () {
        var value = $(this).val();
        $(this).closest('.gb-popin-item').find('.gb-popin-item__title')
            .text(value !== '' ? value : 'Popin sans titre');
    });

    /* ---------------------------------------------------------------- Images */

    $list.on('click', '.gb-popin-image__select', function (event) {
        event.preventDefault();

        var $wrap = $(this).closest('.gb-popin-image');
        var frame = wp.media({
            title: 'Choisir une image',
            button: { text: 'Utiliser cette image' },
            library: { type: 'image' },
            multiple: false
        });

        frame.on('select', function () {
            var attachment = frame.state().get('selection').first().toJSON();
            var preview = attachment.sizes && attachment.sizes.medium
                ? attachment.sizes.medium.url
                : attachment.url;

            $wrap.find('.gb-popin-image__id').val(attachment.id);
            $wrap.find('.gb-popin-image__preview').attr('src', preview).show();
            $wrap.find('.gb-popin-image__remove').show();
        });

        frame.open();
    });

    $list.on('click', '.gb-popin-image__remove', function () {
        var $wrap = $(this).closest('.gb-popin-image');
        $wrap.find('.gb-popin-image__id').val('');
        $wrap.find('.gb-popin-image__preview').attr('src', '').hide();
        $(this).hide();
    });

    /* ----------------------------------------------------------- Règles d'URL */

    $list.on('change', '.gb-popin-url-mode', function () {
        var $rules = $(this).closest('td').find('.gb-popin-rules');
        $rules.toggle($(this).val() !== 'all');

        // Un ciblage sans règle ne montrerait la popin nulle part : on amorce le champ.
        if ($(this).val() !== 'all' && $rules.find('.gb-popin-rule').length === 0) {
            $rules.find('.gb-popin-add-rule').trigger('click');
        }
    });

    $list.on('click', '.gb-popin-add-rule', function () {
        var $item = $(this).closest('.gb-popin-item');
        var html = gbPopinAdmin.ruleTemplate.split('__INDEX__').join(String($item.data('index')));
        var $rule = $(html);

        $(this).siblings('.gb-popin-rules__list').append($rule);
        $rule.find('.gb-popin-rule__input').trigger('focus');
    });

    $list.on('click', '.gb-popin-rule__remove', function () {
        $(this).closest('.gb-popin-rule').remove();
    });

    $list.on('click', '.gb-popin-rule__samples', function () {
        $(this).closest('.gb-popin-rule').find('.gb-popin-rule__list').toggle();
    });

    function countMatches($rule, refresh) {
        var pattern = $rule.find('.gb-popin-rule__input').val();
        var $count = $rule.find('.gb-popin-rule__count');
        var $samples = $rule.find('.gb-popin-rule__samples');
        var $listing = $rule.find('.gb-popin-rule__list');

        if (!pattern) {
            $count.removeClass('is-error is-empty').text('');
            $samples.hide();
            $listing.hide().empty();
            return;
        }

        $count.removeClass('is-error is-empty').text('…');

        $.post(gbPopinAdmin.ajaxUrl, {
            action: 'gb_popin_count_urls',
            nonce: gbPopinAdmin.nonce,
            pattern: pattern,
            refresh: refresh ? 1 : 0
        }).done(function (response) {
            if (!response || !response.success) {
                $count.addClass('is-error').text('erreur');
                return;
            }

            var data = response.data;

            if (data.error) {
                $count.addClass('is-error').text(data.error);
                $samples.hide();
                $listing.hide().empty();
                return;
            }

            $count.toggleClass('is-empty', data.count === 0)
                .text(data.count + ' page' + (data.count > 1 ? 's' : '') +
                      ' sur ' + data.total + (data.truncated ? ' (index tronqué)' : ''));

            $listing.empty();
            if (data.samples && data.samples.length) {
                $samples.show();
                data.samples.forEach(function (path) {
                    $('<li/>').text(path).appendTo($listing);
                });
                if (data.count > data.samples.length) {
                    $('<li/>').addClass('gb-popin-rule__more')
                        .text('… et ' + (data.count - data.samples.length) + ' autres')
                        .appendTo($listing);
                }
            } else {
                $samples.hide();
            }
        }).fail(function () {
            $count.addClass('is-error').text('erreur réseau');
        });
    }

    // Le compteur attend une pause dans la frappe : le minuteur est porté par la
    // règle elle-même, sinon chaque caractère déclencherait sa propre requête.
    $list.on('input', '.gb-popin-rule__input', function () {
        var $rule = $(this).closest('.gb-popin-rule');

        window.clearTimeout($rule.data('timer'));
        $rule.data('timer', window.setTimeout(function () {
            countMatches($rule, false);
        }, 450));
    });

    // Compte initial des règles déjà enregistrées.
    $list.find('.gb-popin-rule').each(function () {
        countMatches($(this), false);
    });

    $('#gb-popin-refresh-index').on('click', function () {
        var $rules = $list.find('.gb-popin-rule');
        if (!$rules.length) {
            return;
        }
        // Une seule requête reconstruit l'index, les autres relisent le cache.
        countMatches($rules.eq(0), true);
        $rules.slice(1).each(function () {
            var $rule = $(this);
            window.setTimeout(function () { countMatches($rule, false); }, 600);
        });
    });

    // Filet : l'ordre est aussi réécrit à l'envoi du formulaire.
    $('#gb-popin-form').on('submit', refreshOrder);
});
