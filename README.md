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

1. Copy the plugin to `local/oksigeniaayvurenda` in your Moodle (4.5 to 5.3).
2. Visit *Site administration > Notifications* to finish the install.

## Development

Code, comments and commit messages in English; texts in the language packs (`lang/en`, `lang/es`). The tests run with
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
