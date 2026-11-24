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
 * The reader's booklet (13 Nov 2026): what the buttons do. Without this file the page still reads, prints from
 * the browser's menu and plays the voice with the browser's own player. «Imprimirlo o guardarlo» opens the print
 * dialog (where «Guardar como PDF» lives, also on phones); «Mandárselo a alguien» uses the phone's own way of sharing
 * (WhatsApp, mail…), or copies the address where there is none; the voice plays and pauses with one big button.
 *
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function () {
    'use strict';
    var raiz = document.documentElement;
    raiz.classList.remove('lb-sin-js');
    raiz.classList.add('lb-con-js');

    Array.prototype.forEach.call(document.querySelectorAll('[data-lb="imprimir"]'), function (boton) {
        boton.addEventListener('click', function () {
            window.print();
        });
    });

    // «Imprimirlo» from the reader's record opens the page straight on the print dialog, once its pictures are in.
    if (location.hash === '#imprimir') {
        window.addEventListener('load', function () {
            window.print();
        });
    }

    Array.prototype.forEach.call(document.querySelectorAll('[data-lb="pasar"]'), function (boton) {
        var dicho = document.querySelector('.lb-pasado');
        boton.addEventListener('click', function () {
            var datos = {title: boton.getAttribute('data-titulo'), text: boton.getAttribute('data-texto'), url: location.href};
            if (navigator.share) {
                navigator.share(datos).catch(function () {
                    // Closed without sharing: nothing to say.
                });
            } else if (navigator.clipboard && dicho) {
                navigator.clipboard.writeText(location.href).then(function () {
                    dicho.textContent = boton.getAttribute('data-copiado');
                });
            }
        });
    });

    // Each voice: its own <audio>, played by the round button next to it; one at a time.
    var sonando = null;
    Array.prototype.forEach.call(document.querySelectorAll('.lb-voz'), function (voz) {
        var audio = voz.querySelector('audio');
        var play = voz.querySelector('.lb-voz-play');
        if (!audio || !play) {
            return;
        }
        var pintar = function () {
            play.setAttribute('aria-pressed', String(!audio.paused));
            play.setAttribute('aria-label', audio.paused ? play.getAttribute('data-escuchar') : play.getAttribute('data-pausar'));
        };
        play.addEventListener('click', function () {
            if (audio.paused) {
                if (sonando && sonando !== audio) {
                    sonando.pause();
                }
                sonando = audio;
                audio.play();
            } else {
                audio.pause();
            }
        });
        ['play', 'pause', 'ended'].forEach(function (ev) {
            audio.addEventListener(ev, pintar);
        });
        pintar();
    });
})();
