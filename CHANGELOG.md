# Changelog

## 0.7.1 (2026-10-25)

- The school year, on its own screen: how many groups and readers this year has, how many groups have a tutor, who
  left with books (in a group last year and in none this year, or gone from the centre), the books out, and last
  year in numbers (loans, and readers who borrowed of those in each group). Moving the cohorts is enough to start a
  year; the groups can also be brought at once. The day the year starts is set there, and a change applies from the
  next year on, which begins where this one ends.
- The desk shows «Start the school year» while the beginning of a year asks for something (tutors missing, readers
  who left with books), and the books still out when the year is ending, to those who manage the library.

## 0.7.0 (2026-10-25)

- Notices of late books, as Moodle messages, so they go by whatever the site and each person use. The reader hears
  on the first day late and then once a week, kindly and with the titles, which are theirs. The tutor of each group
  gets, once a week, who in the group has books late and for how long, without the titles: the tutor sees the
  lateness, not what the child reads. Each person can turn them off in their notification preferences.
- The tutor of each group in each school year, chosen once a year, with last year's tutor of a group with the same
  name proposed.
- The list of late books by group for the staff, with names, titles and days late, and the list of all books out;
  the desk's «late» and «out» cards lead to it.
- The daily copy: every night, everything an entity holds in CSV files in a zip, with a note of what each file is,
  kept in the site for the library staff to download with one tap. The last seven are kept. It does not depend on
  Moodle's own backups, which only the site administrator sees.
- The made-up library can get made-up loans and tutors (`cli/demo.php --loans=N --library=SLUG`).

## 0.6.1 (2026-10-25)

- The Abies importer brings, on request (`--with-readers`), the readers with their card codes, kind and group of the
  school year of the export, and their loans, out and returned, each in its school year. The printed card wins: a
  code taken by a reader brought from a cohort goes to the Abies reader, and the other gets a new one.
- Matching readers without an account with the accounts of the site, in three piles: those that match by themselves
  (the same words in the same order and, when both sides have one, the same group), accepted with one tap; those in
  doubt (several accounts, the surnames in another order, one surname less, a letter changed, another group), one by
  one; and those not on the site, who stay as readers without an account or are marked as former readers, keeping
  their history. Linking merges the reader the cohort brought: the Abies card and loans stay, the account gives the
  name and the cohort this year's group.

## 0.6.0 (2026-10-25)

- Readers from the Moodle cohorts the entity chooses: each chosen cohort says what kind of reader its members are and
  the stage, guessed from its name for the librarian to confirm; when its name is the group, the library keeps the
  group of every reader for each school year, which Moodle does not. A nightly task, or a button, brings them: new
  readers get a card code that follows the entity's (with the control letter when its codes carry one), those who moved
  group get the new one for this year, and those who left every cohort are marked so and keep their card and history.
- «Readers» for the staff: the groups of this school year, and each reader with the code of the card and the loans out.
- The made-up library can add made-up Moodle accounts in cohorts (`cli/demo.php --accounts=N --entity=ID`).

## 0.5.0 (2026-10-25)

- The desk: one field, always ready, with no modes. A reader's card opens the reader (name, group, loans, what is
  late); a free copy goes to the open reader; a copy on loan comes back. A name typed instead of a code finds the
  reader who came without the card.
- Reads that arrive while the last one travels wait their turn. Codes with a control letter are checked in the
  browser before going to the server. Three short sounds made by the page (done, look, no), always with colour and
  words; the scanner's own beep can stay off.
- Every line can be undone. Only red cards ask for a tap, and only when the librarian may override them (the reader
  has as many books as the rule allows, or one late); a copy that is not lent, or is lost, never goes.
- On return, the one-tap note (loved it, did not finish it, wants more like it) and «Renew» instead. Shortcuts need
  Alt or Ctrl (Alt+1/2/3, Alt+A, Ctrl+Z), so a scanner never fires them. Escape closes the reader.
- Due dates move to Monday when they fall on a weekend. Each loan keeps the reader's group and the school year.
- When no reader is open: what comes back today, what is late and what is out.
- A librarian role, created on install, with the capabilities to look up, lend and catalogue.

## 0.4.0 (2026-10-24)

- The catalogue, on screens of its own with the library's look (Literata and Andika, served from the plugin): search
  by words in any order, without accents or case, with the title counting most; suggestions when nothing is found,
  by sound and by letters ("bolcan" finds "volcán"); results while typing; corners by the top classes of the
  classification; what came in last; a record with its authors, topics, details and copies, those of this library
  first; a switch to see only the books free to take home.
- Whether a book is free, out, only to read in the library or kept in another library of the entity, and where it is.
- «Library» in the top bar of the site. Capabilities to look up, lend, catalogue and set up.
- Checked in a headless browser in four sizes (Chromebook with zoom, 800 px frame, 4:3 board, phone): no console
  errors, no failed requests, no axe-core violations, no horizontal scroll at 320 px.

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
