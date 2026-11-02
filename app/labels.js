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
 * The form of the labels: what shows with each choice, and the first free label chosen on the sheet.
 */
(function() {
    'use strict';
    var form = document.querySelector('[data-ayv-lab]');
    if (!form) {
        return;
    }
    var part = function(selector) {
        return form.querySelector(selector);
    };
    var which = function() {
        var chosen = part('input[name="which"]:checked');
        return chosen ? chosen.value : 'today';
    };
    var show = function() {
        part('.ayv-lab-since').hidden = which() !== 'since';
        part('.ayv-lab-codes').hidden = which() !== 'codes';
        part('.ayv-lab-go').hidden = which() === 'today';
    };
    form.addEventListener('change', function(event) {
        show();
        var name = event.target.name;
        if (name === 'sheet') {
            part('input[name="start"]').value = '1';
        }
        if (name === 'kind' || name === 'sheet' || (name === 'which' && event.target.value === 'today')) {
            form.submit();
        }
    });
    form.querySelectorAll('[data-start]').forEach(function(cell) {
        cell.addEventListener('click', function() {
            part('input[name="start"]').value = cell.getAttribute('data-start');
            form.submit();
        });
    });
})();
