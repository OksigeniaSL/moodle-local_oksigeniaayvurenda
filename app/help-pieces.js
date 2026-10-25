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
    },
    // The card: how a reader sees it, what grows it, the code for the desk, the looks by age.
    card: function(h) {
        'use strict';
        return h.point('.ayv-mycard').then(function() {
            return h.say(0);
        }).then(function() {
            return h.point('.ayv-card-flower');
        }).then(function() {
            return h.say(1);
        }).then(function() {
            return h.point('.ayv-card-code');
        }).then(function() {
            return h.say(2);
        }).then(function() {
            return h.point('.ayv-paths');
        }).then(function() {
            return h.say(3);
        }).then(function() {
            return h.point('.ayv-notice .ayv-subjects');
        }).then(function() {
            return h.say(4);
        }).then(function() {
            return h.go(function(doc) {
                var chips = doc.querySelectorAll('.ayv-notice .ayv-chip');
                return chips.length > 1 ? chips[1] : chips[0];
            });
        }).then(function() {
            return h.point('.ayv-mycard');
        }).then(function() {
            return h.say(5);
        }).then(function() {
            return h.say(6);
        });
    },
    // Labels and spine labels: which, a couple of codes, the sheet, where to start, print.
    labels: function(h) {
        'use strict';
        var c = h.codes;
        return h.point('select[name="kind"]').then(function() {
            return h.say(0);
        }).then(function() {
            return h.choose('select[name="which"]', 'codes');
        }).then(function() {
            return h.say(1);
        }).then(function() {
            return h.type('textarea[name="codes"]', c.faro + ' ' + c.ladrona);
        }).then(function() {
            return h.say(2);
        }).then(function() {
            return h.go('.ayv-print-head form button[type="submit"]');
        }).then(function() {
            return h.point('.ayv-labelsheet');
        }).then(function() {
            return h.say(3);
        }).then(function() {
            return h.point('input[name="start"]');
        }).then(function() {
            return h.say(4);
        }).then(function() {
            return h.point('.ayv-print-head .ayv-button[onclick]');
        }).then(function() {
            return h.say(5);
        });
    },
    // The six reports: their tabs, a period, a row, the most lent, the CSV, the year in numbers.
    reports: function(h) {
        'use strict';
        var tab = function(n) {
            return '.ayv-tabs a:nth-child(' + n + ')';
        };
        return h.point('.ayv-tabs').then(function() {
            return h.say(0);
        }).then(function() {
            return h.point('label:has(input[name="from"])');
        }).then(function() {
            return h.say(1);
        }).then(function() {
            return h.go(tab(3));
        }).then(function() {
            return h.point('.ayv-report');
        }).then(function() {
            return h.say(2);
        }).then(function() {
            return h.point('.ayv-more .ayv-button');
        }).then(function() {
            return h.say(3);
        }).then(function() {
            return h.go(tab(6));
        }).then(function() {
            return h.point('.ayv-results-title');
        }).then(function() {
            return h.say(4);
        });
    },
    // The reviews waiting for the library: what and who, publish or not, and «now you»: publish Leo's.
    reviews: function(h) {
        'use strict';
        var first = '.ayv-review-card:first-child ';
        return h.point(first + '.ayv-review-text').then(function() {
            return h.say(0);
        }).then(function() {
            return h.point(first + '.ayv-review-who');
        }).then(function() {
            return h.say(1);
        }).then(function() {
            return h.point(first + 'button[value="publish"]');
        }).then(function() {
            return h.say(2);
        }).then(function() {
            return h.point(first + 'button[value="decline"]');
        }).then(function() {
            return h.say(3);
        }).then(function() {
            return h.you(4, first + 'button[value="publish"]');
        }).then(function() {
            return h.say(5);
        });
    },
    // What the classes ask for: the book, looking for it, and «now you»: here it is.
    wishes: function(h) {
        'use strict';
        var first = '.ayv-review-card:first-child ';
        return h.point(first + '.ayv-review-text').then(function() {
            return h.say(0);
        }).then(function() {
            return h.point(first + 'a');
        }).then(function() {
            return h.say(1);
        }).then(function() {
            return h.you(2, first + 'button[value="got"]');
        }).then(function() {
            return h.say(3);
        });
    },
    // My class: a challenge met, its reward (a book the class chooses), and the challenge of a group still playing.
    myclass: function(h) {
        'use strict';
        var second = '.ayv-myclass ~ .ayv-myclass ';
        return h.point('.ayv-myclass h2').then(function() {
            return h.say(0);
        }).then(function() {
            return h.point('.ayv-myclass .ayv-challenge');
        }).then(function() {
            return h.say(1);
        }).then(function() {
            return h.type('.ayv-reward-form input[name="title"]', h.codes.wish);
        }).then(function() {
            return h.say(2);
        }).then(function() {
            return h.say(3);
        }).then(function() {
            return h.go('.ayv-reward-form button[type="submit"]');
        }).then(function() {
            return h.point(second + 'fieldset');
        }).then(function() {
            return h.say(4);
        }).then(function() {
            return h.you(5, second + 'input[value="notes"]');
        }).then(function() {
            return h.say(6);
        });
    }
};
