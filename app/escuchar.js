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
 * «Leyó para los pequeños»: the family's player (13 Nov 2026). Without this file the page keeps the browser's own
 * player. The big button plays and pauses, the line moves along with the voice, and «Mandárselo a alguien» uses the
 * phone's own way of sharing (WhatsApp, mail…), or copies the address where there is none.
 *
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function () {
    'use strict';
    var raiz = document.documentElement;
    raiz.classList.remove('lp-sin-js');
    raiz.classList.add('lp-con-js');
    var audio = document.querySelector('.lp-audio-sin-js');
    var play = document.querySelector('.lp-play');
    var barra = document.querySelector('.lp-barra');
    var ya = document.querySelector('.lp-ya');
    var total = document.querySelector('.lp-total');
    if (!audio || !play) {
        return;
    }
    var mmss = function (s) {
        s = Math.max(0, Math.floor(s || 0));
        return Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2);
    };
    // A recording made in a browser (webm) does not say how long it is until it is played through: asked for its end
    // once, the browser works it out, and it goes back to the start (Ayvurenda).
    var midiendo = false;
    var medir = function () {
        if (audio.duration === Infinity && !midiendo) {
            midiendo = true;
            audio.currentTime = 1e101;
        }
    };
    var pintar = function () {
        if (audio.duration && isFinite(audio.duration)) {
            if (midiendo) {
                midiendo = false;
                audio.currentTime = 0;
            }
            barra.max = String(audio.duration);
            total.textContent = mmss(audio.duration);
        }
        // The dot where the voice is, as the clock says it, not rounded to whole seconds.
        barra.value = String(midiendo ? 0 : audio.currentTime);
        ya.textContent = mmss(midiendo ? 0 : audio.currentTime);
        play.setAttribute('aria-pressed', String(!audio.paused));
        play.setAttribute('aria-label', audio.paused ? play.dataset.escuchar : play.dataset.pausar);
    };
    play.addEventListener('click', function () {
        if (audio.paused) {
            audio.play();
        } else {
            audio.pause();
        }
    });
    barra.addEventListener('input', function () {
        audio.currentTime = Number(barra.value);
    });
    ['timeupdate', 'loadedmetadata', 'durationchange', 'play', 'pause', 'ended'].forEach(function (ev) {
        audio.addEventListener(ev, pintar);
    });
    audio.addEventListener('loadedmetadata', medir);
    if (audio.readyState >= 1) {
        medir();
    }
    pintar();
    // A reading in parts goes on by itself to the next part, as its own page does.
    var partes = (audio.dataset.parts || '').split(' ').filter(Boolean);
    var parte = 0;
    audio.addEventListener('ended', function () {
        if (parte + 1 < partes.length) {
            parte++;
            audio.src = partes[parte];
            audio.play();
        }
    });

    var pasar = document.querySelector('.lp-pasar');
    var dicho = document.querySelector('.lp-pasado');
    if (pasar) {
        pasar.addEventListener('click', function () {
            var datos = {title: pasar.dataset.titulo, text: pasar.dataset.texto, url: location.href};
            if (navigator.share) {
                navigator.share(datos).catch(function () {});
            } else if (navigator.clipboard) {
                navigator.clipboard.writeText(location.href).then(function () {
                    dicho.textContent = pasar.dataset.copiado;
                });
            }
        });
    }
})();
