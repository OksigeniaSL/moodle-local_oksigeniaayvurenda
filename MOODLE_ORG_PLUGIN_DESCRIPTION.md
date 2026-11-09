<p>A whole library inside Moodle: the catalogue, the circulation desk, the readers and their cards, holds, late notices and reports, for any library, a school's, a university's, a town's, a company's or a bookmobile. Readers find it in the main menu, the staff at their desk. Try it without installing anything: the <a href="https://campus.oksigenia.com/local/oksigeniacampus/demo.php?as=bibliotecaria&amp;to=/local/oksigeniaayvurenda/desk.php%3Flibrary%3Ddemo-1%26lang%3Den">desk</a> and the <a href="https://campus.oksigenia.com/local/oksigeniacampus/demo.php?as=lector&amp;to=/local/oksigeniaayvurenda/index.php%3Flang%3Den">reader's library</a> of a demo library with sample data, erased every night.</p>
<h3>The desk</h3>
<ul>
<li><strong>One field for everything</strong>: scan a card or a book with a barcode reader, the camera or a phone, or type a name or a title. It lends, returns and renews without modes, with sounds, undo and a note in one tap; a book without a label is found by its title or by the ISBN on its back cover.</li>
<li><strong>Holds</strong> with a queue and a notice when the book is set aside, <strong>late notices</strong> to the reader (and, in a school, to the form tutor), closed days and a return deadline, and, only if the library wants them, days without loans or fines that are just written down.</li>
<li><strong>Keeps working offline</strong>: what is scanned is kept in the browser and sent when the connection is back.</li>
<li><strong>Self-loan posts</strong> that open without an account, and a <strong>SIP2 bridge</strong> for self-service machines, return boxes and RFID.</li>
</ul>
<h3>The catalogue</h3>
<ul>
<li>A search that forgives accents and typos, corners by subject (UDC or Dewey) or by genre, new arrivals, and a record page that says where the book is on the shelf; the <strong>shelves</strong> drawn as spines in call-number order, with the gap of a book that is out.</li>
<li>A <strong>public catalogue without an account</strong>, closed until the library opens it, which can also be embedded in any website, with OPDS, OAI-PMH (Dublin Core and MARCXML) and SRU.</li>
<li>The <strong>digital collection</strong>: EPUB, PDF, audio and video read in the browser, and free works from Project Gutenberg and Wikisource.</li>
<li>Citations in RIS, BibTeX and CSL-JSON, and COinS for Zotero and Mendeley.</li>
</ul>
<h3>Bringing a library in</h3>
<ul>
<li>From any system that exports <strong>MARC21 or UNIMARC</strong> (copies in 852, Koha's 952, 995, 949 or 999; old character sets read right), a <strong>spreadsheet</strong> (LibraryThing, Goodreads, Calibre and Libib as they come), publishers' ONIX, or an Abies Web export with its readers and loans. You see the books it brings before importing, and importing again adds only what is new.</li>
<li>New books by <strong>ISBN or by title</strong> from some twenty-five national libraries and shared catalogues: the Library of Congress, the national libraries of Spain, France, Germany and Portugal, and catalogues of Europe and the Americas.</li>
<li>Readers from <strong>cohorts</strong>, courses with groups, a list, <strong>OneRoster</strong> (its files or its REST API) or <strong>LTI 1.3</strong>, matched with their Moodle accounts.</li>
</ul>
<h3>Readers who come back</h3>
<ul>
<li>A <strong>reader card that grows</strong> with each book (a flower for the youngest, a sky from twelve, or a plain card), with an album at the end of the year. No rankings and no comparisons.</li>
<li><strong>Class challenges</strong>, cooperative and anonymous, library challenges with dates, reviews in a sentence, a drawing or a voice, and an idea a day from the library for each reader.</li>
<li>In the Moodle app: their books, holds, renewals and their card.</li>
</ul>
<h3>Everything a library does</h3>
<p>Several libraries in one site (a central one and one per classroom, department or branch, with loans of batches between them), the textbook bank with its sets by course, course reading lists and reserves, purchases with the budget and the order sheet, interlibrary loans, accessible formats, stocktake with a phone, weeding with its record, labels and spine labels for any sheet, printed reader cards, reports and the year in review, who did what, and nightly copies in open formats, optionally encrypted and sent to the library's own WebDAV, S3 or Google Drive.</p>
<h3>Each library in its own words</h3>
<p>Six looks, each with its words and what is switched on: public or foundation library, university or academy, company, mobile library, secondary school and primary school. A site can also keep each centre apart, with its own staff, for a service shared by many libraries.</p>
<h3>Help for the staff</h3>
<p>Guided tours on a practice library of one's own, and «How do I…?» with nearly eighty tasks; from Moodle 4.5, the site's AI can answer in its own words, using only the guide.</p>
<h3>Configuration</h3>
<p>Under Site administration → Plugins → Local plugins → Library (Oksigenia Ayvurenda), and the rest from the library itself, by its staff. Two roles come with it, Librarian and Library manager.</p>
<h3>Privacy</h3>
<p>Covered by the Privacy API (export and deletion), and a reader can be erased on request. Loans older than the years a centre keeps stop saying who took them. Everything stays in your Moodle; external services are used only when the library asks for them (catalogue lookups and covers, copies to its own storage).</p>
<h3>Languages</h3>
<p>English, Spanish, French and Portuguese (Brazil and Portugal).</p>
<h3>Support</h3>
<p>Free and open source. The same library, hosted with nothing to install and with its illustrated building to visit, at <a href="https://ayvurenda.com/en/">ayvurenda.com</a>.</p>
<h3>Requirements</h3>
<ul>
<li>Moodle 4.5 to 5.3, PHP 8.1 or later, MariaDB, MySQL or PostgreSQL.</li>
<li>Tested with PHPUnit and Behat in CI on Moodle 4.5, 5.0, 5.1, 5.2 and 5.3.</li>
<li>Any theme and any modern browser.</li>
</ul>
<h3>License</h3>
<p>GPL v3 or later. Bundled ZXing (Apache 2.0), epub.js (BSD 2-clause), JSZip (MIT), three.js (MIT), Lucide icons (ISC), and the Andika and Literata fonts (SIL OFL 1.1).</p>
