# Changelog

## 0.19.0 (2026-10-27)

- The card by age. The flower stays for Infantil and Primaria; from Secundaria on, the sky by Gaudí: a constellation
  for each path (The Tide, The Compass, The Voice, The Anchor) and the moon as the stage, lively from twelve to
  fourteen and sober from fifteen and for adults. The centre sets it by stage in «Libraries» (automatic by what the
  stage is called); from twelve on, each reader may choose the sky, a classic look (a list, without a picture) or none
  at all. Teachers, staff and families see no game unless they turn it on, and it never appears at the desk or in the
  late notices. Nobody is ranked. The drawings of the sky are dual-licensed CC BY-SA 4.0 or GPL v3
  (`app/cielo/LICENSE.md`).
- Texts that readers of every age see, without exclamations: they stay only in the flower's, for the youngest. The
  signature lines of the weeding record name the role, not a person.

## 0.18.0 (2026-10-27)

- A classroom library can serve several groups (two classes that share a shelf, a cycle): they are ticked in
  «Libraries», the tutors of any of them use the shelf, and the shelf shows the children under each group. A library
  with a single group from before keeps it.
- Filling a box, words instead of a code bring the free copies whose book has them (title, author, subject), while
  typing and on Enter; a tap puts one in the box. A scanner goes on as before.
- Cifra is called by its name, Cifra Educación, in every text.

## 0.17.0 (2026-10-27)

- Boxes between the libraries of a centre: the central library fills a box by reading its copies (a theme, a class
  reading, a corner for the term) and sends it to a classroom or a department until a date. The copies sit on that
  shelf meanwhile and its teacher lends them to the children; the shelf shows the box and gives it back with a tap.
  What is on the shelf goes home at once, what a child has goes home when it is returned, and the central library sees
  what is still missing and which boxes are late.

## 0.16.0 (2026-10-27)

- The shelf of a classroom library, for its teacher, who is not a librarian and has no scanner: the children of the
  group by name, the books of the shelf by cover; a name and a cover lend a book until the end of the school year, a
  lent cover brings it back, and each step can be undone. «What each one read» lists the books of every child this
  year, from the same history as the central library. The tutors of the group use it, and so does the library staff;
  the classroom library's page shows them the button.

## 0.15.0 (2026-10-27)

- The libraries of a site on a page of their own, without the command line: a centre is created with its first
  library (with the start of its school year, how its catalogue classifies and whether its codes carry a control
  letter); then it adds libraries of any kind (central, classroom, department, faculty), each hanging from another if
  it wants, a classroom one with its group; renames them, and removes those without copies. Several centres can share
  a site. Managers reach it from the desk and, while there is no library, from the plugin's first page.
- The third stage of the flower is called «Casi flor» in Spanish.
- Settings fields go under their label on narrow screens instead of past the edge.

## 0.14.0 (2026-10-27)

- Weeding: the candidates of a library (lost, worn out or broken, published before a year, not lent for some years),
  by place, the strongest reason first; the library ticks those that leave and they are taken out together, with a
  record of weeding (library, date, reason, copies and why) to print on A4 and sign. One taken out by mistake goes back
  to the collection with a tap. What is lent or set aside for a reservation is never a candidate.
- The catalogue no longer counts copies lost or taken out of the collection: a book whose only copy was lost reads
  «No copies», not «On loan».

## 0.13.0 (2026-10-27)

- Stocktakes with the phone or the scanner, for the whole library or one place in it, by several people at once and
  in parts. Each copy read says whether it is in its place, belongs to another place, shows as lent (return it there
  with a tap), was lost and turned up, or belongs to another library. Where the shelves have no network, the browser
  keeps what is read and sends it by itself when the network comes back. At the end: what is missing, by place and in
  a list to download; what was out of its place; and, with one tap each, marking the missing as lost and putting back
  what turned up. Counted by the database, for libraries with tens of thousands of copies.
- The legend of how the flower grows shows the flower's own marks (drops, sparkles, speech bubbles, roots), by Gaudí,
  with what each path has for a screen reader.

## 0.12.1 (2026-10-27)

- The flower of the card, drawn by Gaudí, Oksigenia's art director: seed, sprout, bud and flower, each with a place
  for every path (drops for coming back, sparkles for exploring, speech bubbles for telling, roots for staying) whose
  three marks light up as the reader goes; what is missing shows dotted and nothing withers. Drawn in the page, in
  light and dark, also for the flowers of other years. The drawings are dual-licensed CC BY-SA 4.0 or GPL v3
  (`app/flor/LICENSE.md`); the name and the emblem stay marks of Oksigenia SL.

## 0.12.0 (2026-10-27)

- Reservations with a notice. A reader waits in a queue for a book that is out; when a copy comes back, the desk says
  to set it aside and who for, with the other sound, and the reader gets a Moodle message (by any channel the centre
  has). The copy waits a few school days, then goes to the next in the queue or back to the shelf. Lending it to
  someone else shows a red card with «Keep it set aside» first; lending it to the reader who waited fulfils the
  reservation. Every step can be undone at the desk, and the message waits two minutes so an undo takes it back.
- Readers reserve online from the book's page («Tell me when it comes back») only where the library switches it on,
  for everyone or by stage; staff reserve at the desk, for a reader by card or name, whatever the switch says.
- A page of reservations: copies set aside with their date, the queues, and the settings (online and stages, school
  days set aside, reservations at a time, renewing while others wait). The desk shows what is set aside today and,
  with a reader open, what they reserved.
- A copy set aside reads «Set aside» in the catalogue. Reservations go into the daily copy and the privacy export, and
  leave with the reader's data.
- At the desk, the safe answer of a red card comes first.
- Groups named by the children's age («2 años») no longer give a stage.

## 0.11.0 (2026-10-27)

- Readers from the centre's management program, as an optional connector: Cifra for now. Its students come with
  their main group and stay up to date every night, before the cohorts and courses; who leaves Cifra stops being an
  active reader, with the history kept. It reads only, either through a bridge that holds the centre's credentials and
  gives a key, or straight to Cifra's public API with the centre's identifier and secret. The key and the secret are
  kept in the plugin's configuration, never in the daily copies. «Try the connection» says what would come without
  changing anything.
- Readers are found again by the identifier their origin gives them, so two siblings with the family's email are two
  readers; an email that comes twice in a list no longer picks an account.
- The cohorts no longer retire readers who came from a list, a management program or Abies: their origin says when
  they leave.
- «Where readers come from» now points to the connector, to the CSV lists and to the Abies import.
- The counts of the import and connector notices read well with any number.

## 0.10.0 (2026-10-27)

- Importing a catalogue from the screen, for any centre and not only from Abies: an Abies Web export (the zip or its
  XML, with or without its readers and loans), any library program's MARC21 (ISO 2709 or MARCXML, with the copies in
  the standard field 852 or in Koha's 952), or a spreadsheet saved as CSV with its columns recognised by their headings
  and correctable. First it says what the file brings; then it imports. Records are found again by where they came
  from or by their ISBN, so a file on top of another does not repeat books; copies by their code, and a copy without
  one gets the next of the library. Also from the command line (`cli/import_catalogue.php`).

## 0.9.8 (2026-10-27)

- Until Gaudí's puppets arrive, each character shows the initial of its name in a circle, instead of a makeshift
  drawing.

## 0.9.7 (2026-10-27)

- Fixed: a copy made in the same second as another (the nightly task and the button at once) failed; the newer one
  takes its place. Seen on the Moodle 6.0dev test site.

## 0.9.6 (2026-10-27)

- Fixed: a site with the 0.1.0, which had no tables, could not upgrade; it gets them all now.

## 0.9.5 (2026-10-27)

- Readers from Moodle courses, for a centre whose classes are courses with groups instead of cohorts: the students of
  each course chosen are readers with their group in the course (or the course itself, if it has none), and its
  teachers are readers as teachers. They come with the cohorts, by the same nightly task; whoever is in a chosen
  cohort keeps the cohort's group.

## 0.9.4 (2026-10-27)

- Readers from a list, for a centre whose readers are not in Moodle cohorts or that has no Moodle: the CSV its
  management program exports, or a spreadsheet, pasted or uploaded. The separator, the encoding and the usual columns
  (name, surnames or a full name, group, stage, card, email, user, file number, kind) are found by themselves, and can
  be corrected before loading. Each reader is found again by the card, the file number, the email or the user, or by
  the name alone (two of the same name, by their group): the list can be loaded every year without repeating anybody.
  Whoever has an account on the site with the same file number, user or email is linked to it.

## 0.9.3 (2026-10-27)

- The six fixed reports, instead of a report builder, each with its CSV: loans by period and group (and how many
  readers of each group borrowed), late books, the most lent, the never lent or not for years (to weed; all of them in
  the CSV), the holdings by subject and audience with what was lent this year, and the school year for the library's
  yearly report. Counts and titles, never what a child read. From «Reports» at the desk.
- The herald comes as a male hummingbird, «el pregonero», and the memory as a grandmother: invented characters with
  a normal mix. The titles of the roles agree with the sex chosen.
- Fixed: the examples of three templates left an empty image address, which the CI's HTML check refuses.

## 0.9.2 (2026-10-27)

- The two characters of the library, with their screen: the memory, who is asked, and the herald, who tells what
  happens. By default the two of Ayvurenda (Memoria and Mainumby); each centre gives them a name and a sex, so the
  words agree, turns them off, and chooses whether the herald signs the notices to readers. They speak little: the
  memory when a search finds nothing, the herald with the new books and with the flower of the card. Portraits are
  placeholders until Gaudí's puppets.

## 0.9.1 (2026-10-27)

- Labels with the barcode and spine labels to print: of the copies added today, since a day, or a list of codes in
  its order; on the usual A4 sheets of labels (3 × 8, 3 × 7, 4 × 10, 5 × 13), starting at any label to use up a sheet
  already started. From «Labels» in the adding screen.
- Fixed: the phpdoc of the flower, which Moodle's checker could not read.

## 0.9.0 (2026-10-27)

- My card: each reader with an account sees their card, a flower that grows during the school year by four paths at
  once (coming back, exploring corners they did not know, telling what they thought, and staying with books:
  finishing them, reading them again), with the code as a barcode and a QR code to show at the desk, the books of the
  year and the flowers of other years. No path alone makes it flower; it never withers and shows no numbers or
  rankings. Everything comes from the desk. Only the reader sees it.
- Reader cards to print, by group, ten to an A4 sheet (85.6 × 54 mm), with the barcode (Code 39) and the QR code.
- Own Code 39 barcodes as SVG, in plain PHP.
- The flower's stages are placeholders until Gaudí's art.

## 0.8.3 (2026-10-26)

- Guided help for the desk and for adding books, from the «Help» link of each: the real screen, in a practice library
  with made-up readers and books, moved step by step with a pointer and captions; «Now you» rings a button and waits
  for the person's tap; a minute each. The practice library is made again before each help, asks a made-up catalogue
  instead of the world, and is hidden from the libraries, the copies and the notices.

## 0.8.2 (2026-10-26)

- Material that is not a book (laptops, tablets, calculators, cameras, games…): a record with its brand and model,
  and its units, each with its label and serial number. It goes through the same desk and rules as books; the desk
  names the unit by its serial number and notes its state when it goes and when it comes back (fine, marked,
  something missing, does not work), as it was, changed with a tap or Alt and a number.
- The loan rules of each library on their own screen: for each kind of reader and kind of copy, days, how many at
  once and renewals. The first material of a library whose rules do not cover it brings a rule of a week, one at a
  time, and says so.
- The daily copy carries the catalogue in MARC21 too, as ISO 2709 (catalogo.mrc) and MARCXML (catalogo.xml), with
  each copy in field 852 and the brand and model of material in local fields: any library program imports it. Own
  ISO 2709 writer and reader (UTF-8, and ISO 8859-1 when reading).
- Notes and summaries from MARC keep their own full stops.

## 0.8.1 (2026-10-26)

- The camera as a reader, at the desk and when adding books: the browser's own BarcodeDetector where it has one, and
  ZXing (Apache-2.0, in the plugin, loaded only then) where it has not, as in the Chromebooks and computers of many
  schools. It stays open between books; each code read goes the same way as a scanner's.
- A phone as the reader of a computer, for a desk without a scanner: «Read with the phone» shows the phone's address
  as a QR code; whatever the phone's camera reads there appears on the desk or the adding screen the same person has
  open. Codes wait at most five minutes.
- The emblem for dark pages, by Gaudí, instead of the round of paper.
- Fixed: the home page failed for those who may not add books, because Mustache took the name «add» for one of its
  own methods.

## 0.8.0 (2026-10-26)

- Adding books by their ISBN, one after another, without leaving the keyboard or the scanner: read the book's code
  and its card appears, with what the catalogues know of it, its cover and a proposed spine (the class, three
  capitals of the author and three letters of the title); read the labels already stuck on it and they become its
  codes, or new ones follow the library's; read the next book and this one is saved. Each saved book is a line that
  can be undone. A book the library already has offers one more copy; one no catalogue knows asks for its title.
  The library, place, kind and first line of the spine stay from one book to the next, as in a box of new books.
- The catalogues are asked from the server, in order: the Library of Congress by SRU, then Open Library; each answer
  is kept a day. A catalogue that answers with other editions only counts for the one asked for, also when it was
  read as an ISBN-10.
- Own MARCXML reader and writer and MARC21 descriptions (title without ISBD punctuation, ISBN without its qualifier,
  year, language, authors, publisher, UDC or Dewey, a few subjects, series, audience), in plain PHP, without
  dependencies; an SRU client and Open Library's answers too.
- Covers fetched by the server when a book is added, made small and kept in the site with their source; the browser
  only sees the site's own files. The catalogue's cards and records show them.

## 0.7.2 (2026-10-25)

- The library's screens follow Moodle 5.3's light and dark modes: the person's choice, or the site's default, and
  the device's when it is «auto». Where the site has no colour modes, they stay light, as Moodle does. Text, borders
  and surfaces turn around; the brand's fills keep their colours with light text on them, and the emblem sits on a
  round of paper.

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
