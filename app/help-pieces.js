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
    // The loan rules: a card, a number changed with its «+» and kept, and a new card for an exception.
    rules: function(h) {
        'use strict';
        return h.point('.ayv-nr-ficha').then(function() {
            return h.say(0);
        }).then(function() {
            return Promise.all([h.say(1), h.tap('.ayv-nr-ficha .ayv-cf-mas[name="loandays"]').then(function() {
                return h.point('.ayv-nr-ficha .ayv-cf-guardado');
            })]);
        }).then(function() {
            return h.tap('.ayv-nr-nueva > details > summary');
        }).then(function() {
            return Promise.all([h.say(2), h.tap('label[for="ayv-nr-quien-' + h.codes.ruletype + '"]')]);
        }).then(function() {
            return h.you(3, '.ayv-nr-nueva .ayv-button');
        }).then(function() {
            return h.point('.ayv-nr-ficha .ayv-cf-guardado');
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
            if (!h.has('#ayv-nr-reservas')) {
                return null;
            }
            return Promise.all([h.say(4), h.point('#ayv-nr-reservas .ayv-cf-luz[name="online"]')]);
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
            if (!h.has('#ayv-nr-guardan')) {
                return null;
            }
            return h.point('#ayv-nr-guardan').then(function() {
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
            return h.point(h.codes.adults ? '.ayv-person' : '.ayv-readers-stage');
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
        return h.point('.ayv-et-tipos').then(function() {
            return h.say(0);
        }).then(function() {
            return h.point('#ayv-et-dias .ayv-cf-chapas');
        }).then(function() {
            return h.say(1);
        }).then(function() {
            return h.go('#ayv-et-dias .ayv-cf-chapa:last-child');
        }).then(function() {
            return h.type('#ayv-et-codigos', c.faro + '\n' + c.ladrona);
        }).then(function() {
            return h.say(2);
        }).then(function() {
            return h.go('.ayv-et-lista button[type="submit"]');
        }).then(function() {
            return h.point('.ayv-et-papel');
        }).then(function() {
            return h.say(3);
        }).then(function() {
            return h.point('.ayv-et-primera');
        }).then(function() {
            return h.say(4);
        }).then(function() {
            return h.point('.ayv-et-muestra-hoja .ayv-cf-fila .ayv-button');
        }).then(function() {
            return h.say(5);
        });
    },
    // The six reports: their tabs, a period, a row, the most lent, the CSV, the year in numbers.
    reports: function(h) {
        'use strict';
        var tab = function(n) {
            return '.ayv-in-fichas a:nth-child(' + n + ')';
        };
        return h.point('.ayv-in-fichas').then(function() {
            return h.say(0);
        }).then(function() {
            return h.point('.ayv-in-periodos');
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
    cardprint: function(h) {
        'use strict';
        // The boxes of the page, in their order: whose, the design, where, the back.
        var box = function(n, inside) {
            return function(doc) {
                var boxes = doc.querySelectorAll('.ayv-cards-step');
                return boxes.length > n ? boxes[n].querySelector(inside) : null;
            };
        };
        return h.point(box(0, 'h2')).then(function() {
            return h.say(0);
        }).then(function() {
            return h.point('.ayv-cards-designs');
        }).then(function() {
            return h.say(1);
        }).then(function() {
            return h.point('.ayv-cards-logo');
        }).then(function() {
            return h.say(2);
        }).then(function() {
            return h.point(box(2, '.ayv-mine-tiles'));
        }).then(function() {
            return h.say(3);
        }).then(function() {
            return h.point(box(3, 'h2'));
        }).then(function() {
            return h.say(4);
        }).then(function() {
            return h.point('.ayv-cards-go button[type="submit"]');
        }).then(function() {
            return h.say(5);
        });
    },
    record: function(h) {
        'use strict';
        return h.point('[data-ayv-open="ayv-fix-record"]').then(function() {
            return h.say(0);
        }).then(function() {
            return h.tap('[data-ayv-open="ayv-fix-record"]');
        }).then(function() {
            return h.point('#ayv-fix-record input[name="title"]');
        }).then(function() {
            return h.say(1);
        }).then(function() {
            return h.point('#ayv-fix-record textarea[name="subjects"]');
        }).then(function() {
            return h.say(2);
        }).then(function() {
            return h.you(3, '#ayv-fix-record .ayv-fix-form button[type="submit"]');
        }).then(function() {
            return h.point('[data-ayv-open^="ayv-fix-copy-"]');
        }).then(function() {
            return h.say(4);
        }).then(function() {
            return h.tap('[data-ayv-open^="ayv-fix-copy-"]');
        }).then(function() {
            return h.point('dialog[open] button[type="submit"][class*="quiet"]');
        }).then(function() {
            return h.say(5);
        });
    },
    bank: function(h) {
        'use strict';
        return h.point('.ayv-bankset').then(function() {
            return h.say(0);
        }).then(function() {
            return h.point('.ayv-bankset .ayv-holds-row');
        }).then(function() {
            return h.say(1);
        }).then(function() {
            return h.point('.ayv-bankset .ayv-window-search');
        }).then(function() {
            return h.say(2);
        }).then(function() {
            return h.point('form.ayv-book');
        }).then(function() {
            return h.say(3);
        }).then(function() {
            return h.go('a[href*="roll.php"]');
        }).then(function() {
            return h.point('.ayv-roll-class');
        }).then(function() {
            return h.say(4);
        }).then(function() {
            return h.go('.ayv-roll-class');
        }).then(function() {
            return h.point('.ayv-roll-pupil');
        }).then(function() {
            return h.say(5);
        });
    },
    bankround: function(h) {
        'use strict';
        return h.point('.ayv-camp-number').then(function() {
            return h.say(0);
        }).then(function() {
            return h.tap('[data-ayv-open="ayv-camp-sheet"]');
        }).then(function() {
            return h.point('#ayv-camp-sheet .ayv-date');
        }).then(function(box) {
            // Day, month and year, whatever their order in this language: the day has 31 options, the month 12 and the year 13.
            var when = new Date(Date.now() + 20 * 86400000);
            box.querySelectorAll('select').forEach(function(list) {
                var size = list.options.length;
                list.value = String(size > 31 ? when.getDate() : (size === 13 ? when.getMonth() + 1 : when.getFullYear()));
                list.dispatchEvent(new Event('change', {bubbles: true}));
            });
            return h.say(1);
        }).then(function() {
            return h.go('#ayv-camp-sheet button[type="submit"]');
        }).then(function() {
            return h.point('.ayv-camp-cards');
        }).then(function() {
            return h.say(2);
        }).then(function() {
            return h.point('.ayv-camp-class');
        }).then(function() {
            return h.say(3);
        }).then(function() {
            return h.go('.ayv-camp-class');
        }).then(function() {
            return h.point('.ayv-roll-lost button');
        }).then(function() {
            return h.say(4);
        }).then(function() {
            return h.go('.ayv-roll-lost button');
        }).then(function() {
            return h.go('a[href*="bankround.php"]');
        }).then(function() {
            return h.point('.ayv-camp-lostrow button');
        }).then(function() {
            return h.say(5);
        }).then(function() {
            return h.go('.ayv-tabs .ayv-tab:nth-child(2)');
        }).then(function() {
            return h.point('.ayv-camp-hero');
        }).then(function() {
            return h.say(6);
        }).then(function() {
            return h.point('.ayv-camp-links a:last-child');
        }).then(function() {
            return h.say(7);
        });
    },
    // What is lent and is no book: its name, how many, the rest folded, and «now you»: add them.
    material: function(h) {
        'use strict';
        // The last dashed tile, «Something else»: it opens with its name written; the + of the counter, twice.
        var tile = '.ayv-mt-nueva:last-child';
        var plus = tile + ' input[name="count"]:checked + .ayv-mt-unidades .ayv-mt-signo:last-child';
        return h.tap(tile + ' > details > summary').then(function() {
            return h.type(tile + ' input[name="name"]', h.codes.material);
        }).then(function() {
            return h.say(0);
        }).then(function() {
            return h.tap(plus);
        }).then(function() {
            return h.tap(plus);
        }).then(function() {
            return h.say(1);
        }).then(function() {
            return h.tap(tile + ' .ayv-mt-mas summary');
        }).then(function() {
            return h.say(2);
        }).then(function() {
            return h.you(3, tile + ' .ayv-cf-fila .ayv-button');
        }).then(function() {
            return h.point('.ayv-notice');
        }).then(function() {
            return h.say(4);
        });
    },
    // The same person twice: what each record says, which stays, «now you»: join them, and by hand.
    twins: function(h) {
        'use strict';
        return h.point('#ayv-twins-likely .ayv-twinset').then(function() {
            return h.say(0);
        }).then(function() {
            return h.point('.ayv-twinset-person:last-child .ayv-field-hint');
        }).then(function() {
            return h.say(1);
        }).then(function() {
            return h.point('.ayv-twinset-person');
        }).then(function() {
            return h.say(2);
        }).then(function() {
            return h.you(3, '#ayv-twins-likely .ayv-twinset button[type="submit"]');
        }).then(function() {
            return h.point('.ayv-notice');
        }).then(function() {
            return h.say(4);
        }).then(function() {
            return h.point('[data-ayv-open="ayv-twins-bycode"]');
        }).then(function() {
            return h.say(5);
        });
    },
    // Who looks after each group: how many have one, what they get, a group's face opens the tray of faces, last year's,
    // and «now you»: touch a group's face.
    tutors: function(h) {
        'use strict';
        return h.point('.ayv-tu-cuenta').then(function() {
            return h.say(0);
        }).then(function() {
            return h.point('.ayv-tu-etapa');
        }).then(function() {
            return h.say(1);
        }).then(function() {
            return h.point('.ayv-tu-grupo');
        }).then(function() {
            return h.say(2);
        }).then(function() {
            return h.say(3);
        }).then(function() {
            return h.you(4, '.ayv-tu-grupo');
        });
    },
    // What a late book leaves: off of itself; days, a fine, the days kept, how it reads, and who has the last word. Each
    // chip saves itself: the next step waits for what only the page after it has (its counters).
    sanctions: function(h) {
        'use strict';
        var late = '#ayv-nr-tarde';
        return h.point(late).then(function() {
            return h.say(0);
        }).then(function() {
            return Promise.all([h.say(1), h.tap(late + ' button[name="mode"][value="days"]').then(function() {
                return h.point(late + ' .ayv-cf-mas[name="perday"]');
            })]);
        }).then(function() {
            return Promise.all([h.say(2), h.tap(late + ' button[name="mode"][value="fine"]').then(function() {
                return h.point(late + ' .ayv-cf-mas[name="cents"]');
            })]);
        }).then(function() {
            return h.you(3, late + ' button[name="mode"][value="days"]');
        }).then(function() {
            return h.point(late + ' .ayv-cf-mas[name="perday"]');
        }).then(function() {
            return h.point(late + ' .ayv-nr-frase');
        }).then(function() {
            return h.say(4);
        }).then(function() {
            return h.say(5);
        });
    },
    // Other formats: what comes, how to give it, saving it, the answered ones, and marking a reader.
    formats: function(h) {
        'use strict';
        return h.point('.ayv-formatcard').then(function() {
            return h.say(0);
        }).then(function() {
            return h.tap('.ayv-formatcard [data-ayv-open^="ayv-format-"]');
        }).then(function() {
            return h.say(1);
        }).then(function() {
            return h.you(2, 'dialog[open] button[name="do"][value="served"]');
        }).then(function() {
            return h.point('.ayv-tabs');
        }).then(function() {
            return h.say(3);
        }).then(function() {
            return h.say(4);
        });
    },
    // Lending between libraries: what it is, asking for a book, what happens when it comes, and lending one out.
    ill: function(h) {
        'use strict';
        return h.point('.ayv-tabs').then(function() {
            return h.say(0);
        }).then(function() {
            return h.tap('[data-ayv-open="ayv-ill-ask"]');
        }).then(function() {
            return h.type('dialog[open] input[name="title"]', h.codes.buy);
        }).then(function() {
            return h.type('dialog[open] input[name="partner"]', h.codes.partner);
        }).then(function() {
            return h.say(1);
        }).then(function() {
            return h.you(2, 'dialog[open] button[type="submit"]:not(.ayv-modal-close)');
        }).then(function() {
            return h.point('.ayv-ill-rows .ayv-self-row');
        }).then(function() {
            return h.say(3);
        }).then(function() {
            return h.go('.ayv-tabs a:nth-child(2)');
        }).then(function() {
            return h.say(4);
        });
    },
    // Buying: what readers ask for, the budget, saying yes, the list by vendor, the order and what happens when it comes.
    purchases: function(h) {
        'use strict';
        return h.point('.ayv-pur-cards .ayv-dig-card').then(function() {
            return h.say(0);
        }).then(function() {
            return h.point('#ayv-nr-gasto');
        }).then(function() {
            return h.say(1);
        }).then(function() {
            return h.tap('.ayv-pur-cards [data-ayv-open]');
        }).then(function() {
            return h.type('dialog[open] input[name="price"]', '12,50');
        }).then(function() {
            return h.type('dialog[open] input[name="vendor"]', h.codes.vendor);
        }).then(function() {
            return h.say(2);
        }).then(function() {
            return h.you(3, 'dialog[open] button[type="submit"]:not(.ayv-modal-close)');
        }).then(function() {
            return h.go('.ayv-tabs a:nth-child(2)');
        }).then(function() {
            return h.point('.ayv-pur-group');
        }).then(function() {
            return h.say(4);
        }).then(function() {
            return h.you(5, '.ayv-pur-grouphead button[type="submit"]');
        }).then(function() {
            return h.point('.ayv-order-table');
        }).then(function() {
            return h.say(6);
        }).then(function() {
            return h.say(7);
        });
    },
    // A post: what it is, making one, its address, who may use it, and going over what was done at it.
    selfloan: function(h) {
        'use strict';
        return h.point('.ayv-cf-titulo').then(function() {
            return h.say(0);
        }).then(function() {
            return h.tap('#ayv-nr-puesto-nuevo > summary');
        }).then(function() {
            return h.type('#ayv-nr-puesto-nuevo input[name="name"]', h.codes.post);
        }).then(function() {
            return h.say(1);
        }).then(function() {
            return h.you(2, '#ayv-nr-puesto-nuevo button[type="submit"]');
        }).then(function() {
            return h.point('.ayv-self-created');
        }).then(function() {
            return h.say(3);
        }).then(function() {
            return h.point('#ayv-nr-quien');
        }).then(function() {
            return h.say(4);
        }).then(function() {
            return h.point('.ayv-self-recent');
        }).then(function() {
            return h.say(5);
        });
    },
    keepers: function(h) {
        'use strict';
        // Whoever practises is the reader with an account in the practice library: they give it to themselves.
        var c = h.codes;
        return h.point('.ayv-keepers h2').then(function() {
            return h.say(0);
        }).then(function() {
            return h.type('.ayv-window-search input[name="q"]', c.myname);
        }).then(function() {
            return h.say(1);
        }).then(function() {
            return h.go('.ayv-window-search button[type="submit"]');
        }).then(function() {
            return h.you(2, 'button[name="do"][value="add"]');
        }).then(function() {
            return h.point('button[name="do"][value="remove"]');
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
