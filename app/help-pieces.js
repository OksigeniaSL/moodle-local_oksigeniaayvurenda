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
 * Ayvurenda: the pieces of the guided help. Each one is the script of its steps over the real screen; the captions
 * are strings of the plugin, in the order of the steps (help<piece>01, 02…).
 *
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
window.AYVHelpPieces = {
    // The desk: lend, undo, give back without the card, the note of one tap.
    desk: function(h) {
        'use strict';
        var c = h.codes;
        var line = function(n) {
            return '#ayv-log li:nth-child(' + n + ')';
        };
        return h.point('#ayv-code').then(function() {
            return h.say(0);
        }).then(function() {
            return h.say(1);
        }).then(function() {
            return h.scan(c.ana);
        }).then(function() {
            return h.wait('#ayv-reader:not([hidden])');
        }).then(function() {
            return h.point('#ayv-reader');
        }).then(function() {
            return h.say(2);
        }).then(function() {
            return h.say(3);
        }).then(function() {
            return h.scan(c.faro);
        }).then(function() {
            return h.point(line(1) + ' .ayv-line-quiet');
        }).then(function() {
            return h.say(4);
        }).then(function() {
            return h.say(5);
        }).then(function() {
            return h.scan(c.ladrona);
        }).then(function() {
            return h.point(line(1));
        }).then(function() {
            return h.say(6);
        }).then(function() {
            return h.say(7);
        }).then(function() {
            return h.tap(line(1) + ' .ayv-note');
        }).then(function() {
            return h.you(8, line(1) + ' .ayv-note:nth-of-type(3)');
        }).then(function() {
            return h.say(9);
        });
    },
    // Adding books: the ISBN, the card the catalogues give, the next book saves this one.
    add: function(h) {
        'use strict';
        var c = h.codes;
        return h.point('#ayv-code').then(function() {
            return h.say(0);
        }).then(function() {
            return h.scan(c.isbn1);
        }).then(function() {
            return h.wait('#ayv-book:not([hidden])');
        }).then(function() {
            return h.point('#ayv-book-title');
        }).then(function() {
            return h.say(1);
        }).then(function() {
            return h.point('input[name="callnumber1"]');
        }).then(function() {
            return h.say(2);
        }).then(function() {
            return h.say(3);
        }).then(function() {
            return h.scan(c.isbn2);
        }).then(function() {
            return h.wait('#ayv-log li');
        }).then(function() {
            return h.point('#ayv-log li .ayv-line-button');
        }).then(function() {
            return h.say(4);
        }).then(function() {
            return h.you(5, '#ayv-book button[type="submit"]');
        });
    }
};
