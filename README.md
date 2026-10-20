# School library for Moodle (Oksigenia Ayvurenda)

[![License: GPLv3](https://img.shields.io/badge/license-GPLv3+-blue.svg)](LICENSE)
![Moodle](https://img.shields.io/badge/Moodle-4.5%E2%80%935.3-orange)
![Status](https://img.shields.io/badge/status-pre--alpha-red)
[![Sponsor](https://img.shields.io/badge/sponsor-Oksigenia-00d4ff)](https://oksigenia.com/en/open-source#sponsor)

**Ayvurenda** is a school library for Moodle™, run by the school's librarians, where the readers are the users of the
site.

[Leer en español](README.es.md)

## Status

**Pre-alpha.** This is the base of the plugin, with its checks: the library is built on it in small versions, each one
tested before it is released. Not for production sites.

## Install

1. Copy the plugin to `local/oksigeniaayvurenda` in your Moodle (4.5 to 5.3).
2. Visit *Site administration > Notifications* to finish the install.

## Development

Code, comments and commit messages in English; texts in the language packs (`lang/en`, `lang/es`). The tests run with
`moodle-plugin-ci` in GitHub Actions on Moodle 4.5 and 5.3 (PostgreSQL), on 5.3 with MariaDB 11.4 too, and on `main`
to see what is coming. See [CONTRIBUTING.md](CONTRIBUTING.md).

## License

GNU GPL v3 or later. See [LICENSE](LICENSE).

© 2026 Oksigenia SL — dev@oksigenia.cc

The word Moodle and associated Moodle logos are trademarks or registered trademarks of Moodle Pty Ltd or its
related affiliates. Oksigenia is not affiliated with Moodle HQ and is not a Moodle Certified Partner.
