# Oksigenia Ayvurenda: online library management

[![License: GPLv3](https://img.shields.io/badge/license-GPLv3+-blue.svg)](LICENSE)
![Moodle](https://img.shields.io/badge/Moodle-4.5%E2%80%935.3-orange)
![Status](https://img.shields.io/badge/status-beta-yellow)
[![Sponsor](https://img.shields.io/badge/sponsor-Oksigenia-00d4ff)](https://oksigenia.com/en/open-source#sponsor)

**Ayvurenda** is online library management, for any library: the desk, the catalogue, each reader's card and a library
you can walk into. This is its plugin for Moodle™, where the readers are the users of the site.

Developed by [Oksigenia](https://oksigenia.com).

[Leer en español](README.es.md)

## Status

**Beta.** In daily use in a library, and the base of the hosted service at [ayvurenda.com](https://ayvurenda.com). It
grows in small versions, each one tested before it is released; what each one brings is in the [CHANGELOG](CHANGELOG.md).

## Install

**What it needs.** Moodle 4.5 to 5.3, with the PHP and the database Moodle itself asks for (it is tested on PostgreSQL
and MariaDB, and runs on MySQL 8), and Moodle's cron running every minute: the library's work at night goes through it.

1. Copy the plugin to `local/oksigeniaayvurenda` (or `public/local/oksigeniaayvurenda` where Moodle has a `public` folder), or install
   the zip from *Site administration > Plugins > Install plugins*.
2. Visit *Site administration > Notifications* to finish the install.
3. Open `/local/oksigeniaayvurenda/libraries.php`: create the centre and its first library. From there the library walks
   you through bringing its catalogue (MARC 21 or UNIMARC from any program, a spreadsheet, or ISBN by ISBN) and its
   readers (Moodle cohorts or courses, a list, OneRoster, LTI 1.3 from another platform).

**Who does what.** Five capabilities, given in the system or, on a site shared by several centres, in each centre's
category: `view` (readers), `desk` (lending and giving back), `catalogue` (adding and correcting books), `manage` (the
centre's settings, copies and readers) and `plans` (the plans of a hosted service; nobody has it by default).

**Every night** (scheduled tasks): readers brought up to date from where they come (`sync_readers`), notices of late
books and reservations at eight in each centre's time zone (`send_notices`, every hour), the daily copy
(`make_copies`) and the loans of old school years that stop naming their reader (`forget_history`).

**The daily copy** is in open formats (MARC 21, Dublin Core, RIS, CSV and a folder ready for Koha), for the centre to
download, and may also go out of the server encrypted with the centre's own key (WebDAV, S3 or Google Drive). A centre can
be recovered from it, with its files, on any Moodle with this plugin.

**Settings** (*Site administration > Plugins > Local plugins > Library (Oksigenia Ayvurenda)*): the hosts of the site's own
network a connector or an off-server copy may reach (`internalhosts`, `offsitehosts`), a Z39.50 bridge for the
catalogues that only speak Z39.50, a Google client for copies in Drive, a site that is only a library, and one category
per centre for a site shared by several.

**Optional.** The block `block_oksigeniaayvurenda` shows each reader their library on their Moodle dashboard. The
illustrations, the characters' drawings and voices, the visit and the illustrated card come in a separate art package
of the hosted service: without it, the library keeps its drawn look and everything works. The Moodle app shows the
library (books out, reservations, search and the card) when the site has the mobile service on.

**From the command line** (`cli/`): a made-up library to try it (`demo.php`), imports (`import_catalogue.php`,
`import_abies.php`), a centre set up or removed (`add_entity.php`, `centre.php`, `remove_entity.php`), loans written
on paper (`paper_loans.php`) and the files of a recovered centre (`recover_files.php`). Each one says how with `--help`.

**Upgrading** is putting the new folder in place of the old one and visiting the notifications page; every version
upgrades from any earlier one.

## Development

Code, comments and commit messages in English; texts in the language packs (`lang/en`, `lang/es`, and `lang/pt_br`, `lang/pt` and `lang/fr`, machine-translated from the Spanish and reviewed). The tests run with
`moodle-plugin-ci` in GitHub Actions on Moodle 4.5 and 5.3 (PostgreSQL), on 5.3 with MariaDB 11.4 too, and on `main`
to see what is coming. See [CONTRIBUTING.md](CONTRIBUTING.md).

## Authorship and provenance

Ayvurenda is designed and developed by Oksigenia SL. Each sealed version carries, in [`provenance/`](provenance/), a
manifest of the code with a timestamp proof and a tag signed by Oksigenia, which anyone can check with
`provenance/verify.sh`.

## License

GNU GPL v3 or later. See [LICENSE](LICENSE).

© 2026 Oksigenia SL — dev@oksigenia.cc

The word Moodle and associated Moodle logos are trademarks or registered trademarks of Moodle Pty Ltd or its
related affiliates. Oksigenia is not affiliated with Moodle HQ and is not a Moodle Certified Partner.
