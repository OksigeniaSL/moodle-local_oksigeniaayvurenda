// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Ayvurenda: «Enseñar a la clase» (Jujol, 13 November 2026), the class's island over the whole whiteboard. Opened from
 * «My class» by its button (data-bc-abrir), it asks the browser for full screen where it can; − and + make everything
 * bigger or smaller (the boards with no zoom), Esc or «Salir» close it, and the herald's sentence plays on a touch
 * (never by itself). The island mounts with a key of its own, so what grew since the class last saw it grows in front
 * of the class, and goes when the board closes. Without this script the button is a link to the board as a page of its
 * own. Old browsers (Firefox 98): no optional chaining here.
 *
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';
    var ESCALAS = [0.8, 0.9, 1, 1.12, 1.25, 1.4];
    var guardada = function() {
        try {
            return Number(localStorage.getItem('ayv-bc-escala')) || 1;
        } catch (e) {
            return 1;
        }
    };
    var poner = function(pizarra, e) {
        pizarra.style.setProperty('--e', String(e));
        try {
            localStorage.setItem('ayv-bc-escala', String(e));
        } catch (err) { /* private window: the size is only for now */ }
    };
    var preparar = function(pizarra, alCerrar) {
        var voz = null;
        poner(pizarra, guardada());
        pizarra.addEventListener('click', function(ev) {
            var b = ev.target.closest('[data-bc]');
            if (!b) {
                return;
            }
            var que = b.getAttribute('data-bc');
            if (que === 'mas' || que === 'menos') {
                var ahora = Number(pizarra.style.getPropertyValue('--e')) || 1;
                var i = ESCALAS.indexOf(ahora);
                i = i < 0 ? 2 : i + (que === 'mas' ? 1 : -1);
                poner(pizarra, ESCALAS[Math.max(0, Math.min(ESCALAS.length - 1, i))]);
            } else if (que === 'completa') {
                if (document.fullscreenElement) {
                    document.exitFullscreen();
                } else if (document.fullscreenEnabled && pizarra.requestFullscreen) {
                    pizarra.requestFullscreen();
                }
            } else if (que === 'salir') {
                alCerrar();
            } else if (que === 'oir') {
                if (voz && !voz.paused) {
                    voz.pause();
                    b.setAttribute('aria-pressed', 'false');
                    return;
                }
                voz = voz || new Audio(b.getAttribute('data-voz'));
                voz.currentTime = 0;
                voz.onended = function() {
                    b.setAttribute('aria-pressed', 'false');
                };
                voz.play();
                b.setAttribute('aria-pressed', 'true');
            }
        });
        var completa = pizarra.querySelector('[data-bc="completa"]');
        if (completa && !document.fullscreenEnabled) {
            completa.hidden = true;
        }
        return function() {
            if (voz) {
                voz.pause();
            }
        };
    };

    // The class's island on the board, in 3D where it can: the island's own script mounts it (island.js).
    var isla = function(pizarra) {
        var figure = pizarra.querySelector('.ayv-island[data-island]');
        return figure && window.AYVIslandMount ? window.AYVIslandMount(figure, 'pizarra') : null;
    };

    // The board as a page of its own: «Salir» goes back to «Mi clase».
    var pagina = document.querySelector('.ayv-bc-pizarra-pagina');
    if (pagina) {
        preparar(pagina, function() {
            location.href = pagina.getAttribute('data-volver');
        });
        isla(pagina);
    }

    // From «Mi clase»: the board over the page, from its template, and back to the button that opened it.
    document.addEventListener('click', function(ev) {
        var abrir = ev.target.closest('[data-bc-abrir]');
        if (!abrir) {
            return;
        }
        var molde = document.getElementById(abrir.getAttribute('data-bc-abrir'));
        if (!molde) {
            return;
        }
        ev.preventDefault();
        var pizarra = molde.content.firstElementChild.cloneNode(true);
        document.body.appendChild(pizarra);
        document.documentElement.classList.add('ayv-bc-abierta');
        var parar;
        var island = null;
        var cerrar = function() {
            parar();
            if (island) {
                island.destroy();
            }
            if (document.fullscreenElement) {
                document.exitFullscreen();
            }
            document.removeEventListener('keydown', alTeclear);
            pizarra.remove();
            document.documentElement.classList.remove('ayv-bc-abierta');
            abrir.focus();
        };
        var alTeclear = function(e) {
            if (e.key === 'Escape') {
                cerrar();
            }
        };
        parar = preparar(pizarra, cerrar);
        island = isla(pizarra);
        document.addEventListener('keydown', alTeclear);
        if (document.fullscreenEnabled && pizarra.requestFullscreen) {
            pizarra.requestFullscreen().catch(function() { /* the board stays over the page */ });
        }
        var salir = pizarra.querySelector('[data-bc="salir"]');
        if (salir) {
            salir.focus();
        }
    });
})();
