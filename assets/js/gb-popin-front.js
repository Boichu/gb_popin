/**
 * GB Popin — affichage côté visiteur.
 *
 * Le serveur fournit les popins éligibles pour cette page, par ordre de priorité.
 * On retient la première que le visiteur n'a pas fermée récemment : c'est ici, et
 * pas côté serveur, parce que le HTML de la page est mis en cache et partagé.
 */
(function () {
    'use strict';

    var data = window.gbPopinData;
    if (!data || !data.popins || !data.popins.length) {
        return;
    }

    function getCookie(name) {
        var parts = ('; ' + document.cookie).split('; ' + name + '=');
        return parts.length === 2 ? parts.pop().split(';').shift() : null;
    }

    function wasClosed(popin) {
        if (getCookie('gb_popin_closed_' + popin.id)) {
            return true;
        }
        // Popin migrée depuis la version à popin unique : on respecte le cookie
        // déjà posé chez les visiteurs, pour ne pas la leur remontrer.
        return !!(popin.legacy && getCookie('gb_popin_closed'));
    }

    function isInSchedule(popin) {
        var now = Math.floor(Date.now() / 1000);
        if (popin.start && now < popin.start) {
            return false;
        }
        return !(popin.end && now > popin.end);
    }

    var chosen = null;
    for (var i = 0; i < data.popins.length; i++) {
        if (isInSchedule(data.popins[i]) && !wasClosed(data.popins[i])) {
            chosen = data.popins[i];
            break;
        }
    }

    if (!chosen) {
        return;
    }

    function close(popin, overlay, box) {
        overlay.remove();
        box.remove();
        document.removeEventListener('keydown', onKeydown);
        document.cookie = 'gb_popin_closed_' + popin.id + '=' + Math.floor(Date.now() / 1000) +
            '; path=/; max-age=' + (popin.closeDelay * 24 * 60 * 60) + '; SameSite=Lax';
    }

    var onKeydown = null;

    function render(popin) {
        var overlay = document.createElement('div');
        overlay.className = 'gb-popin-overlay';

        var box = document.createElement('div');
        box.className = popin.single ? 'gb-popin gb-popin--single' : 'gb-popin';
        box.setAttribute('role', 'dialog');
        box.setAttribute('aria-modal', 'true');
        if (popin.title) {
            box.setAttribute('aria-label', popin.title);
        }

        var images = (popin.portrait || '') + (popin.landscape || '');

        if (popin.link) {
            var link = document.createElement('a');
            link.className = 'gb-popin__link';
            link.href = popin.link;
            link.innerHTML = images;
            box.appendChild(link);
        } else {
            // Sans lien de redirection, cliquer sur l'image ferme la popin.
            var media = document.createElement('div');
            media.className = 'gb-popin__link';
            media.innerHTML = images;
            media.addEventListener('click', function () {
                close(popin, overlay, box);
            });
            box.appendChild(media);
        }

        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'gb-popin__close';
        button.setAttribute('aria-label', 'Fermer');
        box.appendChild(button);

        document.body.appendChild(overlay);
        document.body.appendChild(box);

        button.addEventListener('click', function () {
            close(popin, overlay, box);
        });

        overlay.addEventListener('click', function () {
            close(popin, overlay, box);
        });

        onKeydown = function (event) {
            if (event.key === 'Escape') {
                close(popin, overlay, box);
            }
        };
        document.addEventListener('keydown', onKeydown);

        button.focus();
    }

    function start() {
        window.setTimeout(function () {
            render(chosen);
        }, Math.max(0, chosen.delay) * 1000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
