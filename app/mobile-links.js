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
 * What the app runs when it starts, in the official app and in Aty alike: a notice's link opens the book's page or the library
 * inside the app, instead of the list of notifications. The only JavaScript of this plugin in the app.
 *
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const that = this;

class AyvurendaLinks extends that.CoreContentLinksHandlerBase {
    constructor() {
        super();
        this.name = 'local_oksigeniaayvurenda_links';
        this.pattern = /\/local\/oksigeniaayvurenda\/(record|index)\.php/;
    }

    // «My library» is the reader's own page: the staff's catalogue (index.php?library=…) stays out of it.
    isEnabled(siteId, url, params) {
        return url.includes('/record.php') || !params.library;
    }

    getActions(siteIds, url, params) {
        const record = url.includes('/record.php');
        const method = record ? 'mobile_record_view' : 'mobile_library_view';
        const args = record ? {library: params.library, id: parseInt(params.id, 10)} : {};
        return [{
            action: (siteId) => that.CoreNavigatorService.navigateToSitePath(
                'siteplugins/content/local_oksigeniaayvurenda/' + method + '/' + that.Md5.hashAsciiStr(JSON.stringify(args)),
                {
                    // What a notice opens is fresh, never the app's copy of up to twenty minutes ago (int-aty-pixel 2).
                    params: {title: that.TranslateService.instant('plugin.local_oksigeniaayvurenda.mobilelibrary'), args,
                        preSets: {getFromCache: false}},
                    siteId,
                }
            ),
        }];
    }
}

that.CoreContentLinksDelegate.registerHandler(new AyvurendaLinks());
