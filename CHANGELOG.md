# Changelog

## 0.3.0 (2026-10-24)

- Abies Web importer, catalogue first: records of every document type, authors with their role, the UDC tree,
  subjects, publishers, collections, audiences and places as authorities, copies with their own codes, register
  numbers and spine labels, copy and reader types, and loan rules. `cli/import_abies.php` takes the zip or the XML.
- A survey before importing: repeated codes, wrong control letters, copies of missing records, records without copies,
  languages that cannot be told, copies without a place. It reads no names of people.
- Running it again with a newer export brings the library up to date: each row keeps its Abies id and a fingerprint,
  unchanged rows are left alone and nothing is duplicated. A copy code already taken gets the Abies id after it.
- The core reads the export as a stream, without loading it whole, and works without the intl extension.

## 0.2.0 (2026-10-23)

- Data model: entities with several libraries (central, classroom, department, faculty), records shared within an
  entity, authorities (authors, subjects, publishers, classification with its hierarchy, locations), copies and
  material with their library, readers (linked to a Moodle account when there is one), their group in each school
  year, loans with the group of that moment, loan rules by reader type and item type, and the source of each row for
  re-imports.
- Core in plain PHP, without Moodle: control letters of codes, ISBN checks, word search without accents, school years
  (north and south) and loan rules (due dates, limits, renewals, days late).
- A made-up library to develop and show with: `cli/demo.php` creates an entity with its catalogue, copies, readers and
  rules, all invented.
- Privacy provider: readers, their groups and loans, and the staff who handled them; deleting a reader anonymises it
  and keeps the library's counts.

## 0.1.0 (2026-10-20)

- Base of the plugin: version metadata for Moodle 4.5 to 5.3, English and Spanish language packs, and a privacy
  provider that declares no personal data.
- Checks: `moodle-plugin-ci` in GitHub Actions (PostgreSQL and MariaDB), Gitleaks and the check of commit messages.
