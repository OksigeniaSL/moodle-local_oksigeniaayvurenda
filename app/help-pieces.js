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
    // Importing: what it takes, an example file handed over, what it would bring before anything enters, what follows.
    import: function(h) {
        'use strict';
        return h.point('.ayv-imp-drop').then(function() {
            return h.say(0);
        }).then(function() {
            return h.say(1);
        }).then(function() {
            return h.drop('.ayv-imp-drop input[type="file"]', h.codes.sheetname, h.codes.sheet);
        }).then(function() {
            return h.point('.ayv-imp-figures');
        }).then(function() {
            return h.say(2);
        }).then(function() {
            return h.point('.ayv-imp-shelf');
        }).then(function() {
            return h.say(3);
        }).then(function() {
            return h.point('.ayv-imp-chips');
        }).then(function() {
            return h.say(4);
        }).then(function() {
            return Promise.all([h.say(5), h.tap('.ayv-imp-columnshead button').then(function() {
                return h.point('.ayv-imp-map');
            })]);
        }).then(function() {
            return h.tap('#ayv-imp-map [data-ayv-close]');
        }).then(function() {
            return h.you(6, '.ayv-imp-gobutton');
        }).then(function() {
            return h.point('.ayv-imp-done .ayv-imp-figures');
        }).then(function() {
            return h.say(7);
        }).then(function() {
            return h.point('.ayv-imp-next');
        }).then(function() {
            return h.say(8);
        });
    },
    // Weeding: the reasons, one list, refining, marking one, its record to sign.
    weed: function(h) {
        'use strict';
        return h.point('.ayv-weed-reasons').then(function() {
            return h.say(0);
        }).then(function() {
            return h.say(1);
        }).then(function() {
            return h.go('.ayv-weed-reason-unused');
        }).then(function() {
            return h.point('.ayv-weed-row');
        }).then(function() {
            return h.say(2);
        }).then(function() {
            return Promise.all([h.say(3), h.tap('[data-ayv-open="ayv-weed-refine"]').then(function() {
                return h.point('#ayv-weed-refine form[method="get"]');
            })]);
        }).then(function() {
            return h.tap('#ayv-weed-refine .ayv-modal-close');
        }).then(function() {
            return h.you(4, '.ayv-weed-item');
        }).then(function() {
            return h.point('.ayv-weed-bar');
        }).then(function() {
            return h.say(5);
        }).then(function() {
            return Promise.all([h.say(6), h.tap('[data-ayv-needs-mark]').then(function() {
                return h.type('#ayv-weed-go input[name="reason"]', h.codes.weedreason);
            })]);
        }).then(function() {
            return h.go('#ayv-weed-go button[type="submit"]');
        }).then(function() {
            return h.point('.ayv-weedtable');
        }).then(function() {
            return h.say(7);
        });
    },
    // The loan rules: one read as a sentence, changing it, and a new one for an exception.
    rules: function(h) {
        'use strict';
        return h.point('.ayv-rule').then(function() {
            return h.say(0);
        }).then(function() {
            return Promise.all([h.say(1), h.tap('.ayv-rule-actions button').then(function() {
                return h.point('dialog[open] .ayv-rule-numbers');
            })]);
        }).then(function() {
            return h.tap('dialog[open] .ayv-modal-close');
        }).then(function() {
            return Promise.all([h.say(2), h.tap('[data-ayv-open="ayv-rule-new"]').then(function() {
                return h.choose('#ayv-rule-new select[name="readertype"]', 'teacher');
            }).then(function() {
                return h.type('#ayv-rule-new input[name="loandays"]', '30');
            }).then(function() {
                return h.type('#ayv-rule-new input[name="maxloans"]', '5');
            })]);
        }).then(function() {
            return h.you(3, '#ayv-rule-new button[value="save"]');
        }).then(function() {
            return h.point('.ayv-rule:nth-child(2)');
        }).then(function() {
            return h.say(4);
        }).then(function() {
            return h.say(5);
        });
    },
    // The late books: the spans, a box, who has what, and the two things to do with it.
    late: function(h) {
        'use strict';
        return h.point('.ayv-late-spans').then(function() {
            return h.say(0);
        }).then(function() {
            return h.point('.ayv-late-group summary');
        }).then(function() {
            return h.say(1);
        }).then(function() {
            return h.tap('.ayv-late-group summary');
        }).then(function() {
            return h.point('.ayv-late-row');
        }).then(function() {
            return h.say(2);
        }).then(function() {
            return h.point('.ayv-late-who');
        }).then(function() {
            return h.say(3);
        }).then(function() {
            return h.point('.ayv-late-actions');
        }).then(function() {
            return h.say(4);
        }).then(function() {
            return h.say(5);
        });
    },
    // The holds: what is set aside, who waits, and how a library chooses to take them.
    holds: function(h) {
        'use strict';
        var waiting = '.ayv-holdcard:not(.ayv-holdcard-ready)';
        return h.point('.ayv-holdcard-ready').then(function() {
            return h.say(0);
        }).then(function() {
            return h.point(waiting);
        }).then(function() {
            return h.say(1);
        }).then(function() {
            return h.say(2);
        }).then(function() {
            return h.point(waiting + ' button');
        }).then(function() {
            return h.say(3);
        }).then(function() {
            if (!h.has('[data-ayv-open="ayv-holds-sheet"]')) {
                return null;
            }
            return Promise.all([h.say(4), h.tap('[data-ayv-open="ayv-holds-sheet"]').then(function() {
                return h.point('#ayv-holds-sheet form[method="post"]');
            })]).then(function() {
                return h.tap('#ayv-holds-sheet .ayv-modal-close');
            });
        });
    },
    // The voices waiting: listening, the family's permission, the licence, who else hears it, the signature.
    voices: function(h) {
        'use strict';
        return h.point('.ayv-book').then(function() {
            return h.say(0);
        }).then(function() {
            return h.point('.ayv-book audio');
        }).then(function() {
            return h.say(1);
        }).then(function() {
            return h.point('.ayv-book form.ayv-book-actions');
        }).then(function() {
            return h.say(2);
        }).then(function() {
            return h.point('.ayv-book select[name="licence"]');
        }).then(function() {
            return h.say(3);
        }).then(function() {
            return h.point('.ayv-book form:not(.ayv-book-actions) > .ayv-field-hint');
        }).then(function() {
            return h.say(4);
        }).then(function() {
            return h.point('.ayv-book input[name="signature"]');
        }).then(function() {
            return h.say(5);
        }).then(function() {
            return h.point('.ayv-book button[value="approve"]');
        }).then(function() {
            return h.say(6);
        }).then(function() {
            if (!h.has('.ayv-settingcard')) {
                return null;
            }
            return h.point('.ayv-settingcard').then(function() {
                return h.say(7);
            });
        });
    },
    // The readers: the figures, finding one, the stages, and the way to where they come from.
    readers: function(h) {
        'use strict';
        return h.point('.ayv-readers-sum').then(function() {
            return h.say(0);
        }).then(function() {
            return h.type('#ayv-readers-q', 'Ana');
        }).then(function() {
            return h.say(1);
        }).then(function() {
            return h.point('.ayv-readers-stage');
        }).then(function() {
            return h.say(2);
        }).then(function() {
            return h.say(3);
        }).then(function() {
            return h.go('.ayv-readers-top .ayv-button');
        }).then(function() {
            return h.point('.ayv-src-cards');
        }).then(function() {
            return h.say(4);
        }).then(function() {
            return h.say(5);
        });
    },
    // A list: an example handed over, the figures and people it brings, the columns, and registering them.
    readerslist: function(h) {
        'use strict';
        return h.point('.ayv-imp-drop').then(function() {
            return h.say(0);
        }).then(function() {
            return h.drop('.ayv-imp-drop input[type="file"]', h.codes.listname, h.codes.list);
        }).then(function() {
            return h.point('.ayv-imp-figures');
        }).then(function() {
            return h.say(1);
        }).then(function() {
            return h.point('.ayv-people-grid');
        }).then(function() {
            return h.say(2);
        }).then(function() {
            return Promise.all([h.say(3), h.tap('.ayv-imp-columnshead button').then(function() {
                return h.point('.ayv-imp-map');
            })]);
        }).then(function() {
            return h.tap('#ayv-imp-map [data-ayv-close]');
        }).then(function() {
            return h.you(4, '.ayv-imp-gobutton');
        }).then(function() {
            return h.point('.ayv-imp-done .ayv-imp-figures');
        }).then(function() {
            return h.say(5);
        });
    },
    // The shelves: spines as tall as the books, the label at their foot, the gap of a book that is out, the lectern.
    stacks: function(h) {
        'use strict';
        var spine = '.ayv-spine-slot:not(.ayv-spine-gap) a.ayv-spine';
        return h.point(spine).then(function() {
            return h.say(0);
        }).then(function() {
            return h.point('.ayv-spine-label');
        }).then(function() {
            return h.say(1);
        }).then(function() {
            return h.point('.ayv-spine-gap a.ayv-spine');
        }).then(function() {
            return h.say(2);
        }).then(function() {
            return h.you(3, spine);
        }).then(function() {
            return h.point('.ayv-lectern-in');
        }).then(function() {
            return h.say(4);
        }).then(function() {
            return h.point('.ayv-lectern-close');
        }).then(function() {
            return h.say(5);
        }).then(function() {
            return h.point('.ayv-stacks-back');
        }).then(function() {
            return h.say(6);
        });
    },
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
    // Free works: the two sources, a title typed, and what bringing one does (nothing is fetched from outside).
    free: function(h) {
        'use strict';
        return h.point('.ayv-staff-row').then(function() {
            return h.say(0);
        }).then(function() {
            return h.type('input[name="q"]', 'Platero');
        }).then(function() {
            return h.say(1);
        }).then(function() {
            return h.say(2);
        }).then(function() {
            return h.say(3);
        }).then(function() {
            return h.say(4);
        });
    },
    // Who keeps a library: what it is for, a name looked for, and how it is given and taken away.
    keepers: function(h) {
        'use strict';
        return h.point('.ayv-keepers h2').then(function() {
            return h.say(0);
        }).then(function() {
            return h.type('input[name="q"]', 'Ana');
        }).then(function() {
            return h.say(1);
        }).then(function() {
            return h.say(2);
        }).then(function() {
            return h.say(3);
        });
    },
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
