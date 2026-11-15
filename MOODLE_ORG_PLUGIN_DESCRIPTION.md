<p>A whole library inside Moodle: the catalogue, the circulation desk, readers and their cards, holds, late notices and reports, for any library: a school's, a university's, a town's, a company's or a bookmobile. Readers find it in the main menu, the staff at their desk. Try it without installing anything: the <a href="https://campus.oksigenia.com/local/oksigeniacampus/demo.php?as=bibliotecaria&amp;to=/local/oksigeniaayvurenda/desk.php%3Flibrary%3Ddemo-1%26lang%3Den">desk</a> and the <a href="https://campus.oksigenia.com/local/oksigeniacampus/demo.php?as=lector&amp;to=/local/oksigeniaayvurenda/index.php%3Flang%3Den">reader's library</a> of a demo library with sample data, erased every night.</p>
<p><strong>The first day</strong></p>
<ul>
<li><strong>Library: start here</strong> appears in the navigation bar. Name the first library and you are at its desk, with <strong>To open your library</strong> in five steps (the catalogue, the readers, the loan rules, the team, opening), each ticked when it is really done.</li>
<li><strong>See a full sample library</strong> first: made-up books, readers and loans, kept apart and removed with one touch.</li>
<li><strong>What do you want to do?</strong> answers as you type, forgives typos and knows the library's settings. New staff get a welcome and <strong>Your first day here</strong>: lend a book, take one back, print a card.</li>
<li>Later, each function introduces itself on the desk when something real calls for it, and <strong>What your library can do</strong> shows them all, with what each did this year and its switch.</li>
</ul>
<p><strong>The desk</strong></p>
<ul>
<li><strong>One field for everything</strong>: scan a card or a book with a barcode reader, the camera or a phone, or type a name or a title. It lends, returns and renews without modes, with undo and a note in one tap; a book without a label is found by its title or its ISBN.</li>
<li><strong>Holds</strong> with a queue, <strong>late notices</strong> to the reader (and, in a school, to the form tutor), closed days, and only if the library wants them, days without loans or fines.</li>
<li><strong>Works offline</strong>: what is scanned waits in the browser until the connection is back.</li>
<li><strong>Self-loan posts</strong> without an account, and a <strong>SIP2 bridge</strong> for self-service machines, return boxes and RFID.</li>
</ul>
<p><strong>The catalogue</strong></p>
<ul>
<li>A search that forgives accents and typos, corners by subject (UDC or Dewey) or genre, new arrivals, a record that says where the book is, and the <strong>shelves</strong> drawn as spines in call-number order.</li>
<li>A <strong>public catalogue</strong> without an account, closed until the library opens it, embeddable in any website, with OPDS, OAI-PMH and SRU.</li>
<li>The <strong>digital collection</strong>: EPUB, PDF, audio and video in the browser, and free works from Project Gutenberg and Wikisource. Citations in RIS, BibTeX and CSL-JSON.</li>
</ul>
<p><strong>Bringing a library in</strong></p>
<ul>
<li>From any system that exports <strong>MARC21 or UNIMARC</strong> (Koha's 952 and other holdings fields; old character sets read right), a <strong>spreadsheet</strong> (LibraryThing, Goodreads, Calibre, Libib), ONIX or an Abies Web export. You see the books before importing, and importing again adds only what is new.</li>
<li>New books by <strong>ISBN or title</strong> from some twenty-five national libraries and shared catalogues.</li>
<li>Readers from <strong>cohorts</strong>, courses with groups, a list, <strong>OneRoster</strong> or <strong>LTI 1.3</strong>, matched with their Moodle accounts.</li>
</ul>
<p><strong>Readers who come back</strong></p>
<ul>
<li>A <strong>reader card that grows</strong> with each book (a flower for the youngest, a sky from twelve, or a plain card), with an album at the end of the year. No rankings.</li>
<li><strong>Class challenges</strong>, cooperative and anonymous, and <strong>library challenges with dates</strong>, such as a reading bingo card the desk fills as books go out.</li>
<li><strong>Blind dates with a book</strong>, the book of the week, the first reader who premieres a new copy, and reviews in a sentence, a drawing or a voice.</li>
<li>In the Moodle app: their books, holds, renewals and their card.</li>
</ul>
<p><strong>Everything a library does</strong></p>
<p>Several libraries in one site with loans of batches between them, the textbook bank, course reading lists, purchases with the budget, interlibrary loans, stocktake with a phone, weeding, labels and spine labels for any sheet, printed reader cards, reports and the year in review, and nightly copies in open formats, optionally encrypted and sent to the library's own WebDAV, S3 or Google Drive.</p>
<p><strong>Each library in its own words</strong></p>
<p>Six looks, each with its words and what is switched on: public library, university or academy, company, mobile library, secondary school and primary school. A site can also keep each centre apart, with its own staff, for a service shared by many libraries.</p>
<p><strong>Help for the staff</strong></p>
<p>Guided tours on a practice library of one's own, «How do I…?» with over eighty tasks, and printable guides at <a href="https://ayvurenda.com/en/guides/">ayvurenda.com/en/guides</a>. From Moodle 4.5, the site's AI can answer in its own words, using only the guide.</p>
<p><strong>Configuration and privacy</strong></p>
<p>Site administration → Plugins → Local plugins → Library (Oksigenia Ayvurenda); the rest from the library itself. Two roles come with it, Librarian and Library manager. Covered by the Privacy API; a reader can be erased on request, and old loans stop saying who took them. Everything stays in your Moodle; outside services only when the library asks for them (catalogue lookups and covers, copies to its own storage).</p>
<p><strong>Languages and requirements</strong></p>
<p>English, Spanish, French and Portuguese (Brazil and Portugal). Moodle 4.5 to 5.3, any theme. Tested with PHPUnit and Behat in CI. GPL v3 or later; bundled libraries in thirdpartylibs.xml.</p>
<p>Free and open source. The same library, hosted with nothing to install and with its illustrated building to visit, at <a href="https://ayvurenda.com/en/">ayvurenda.com</a>.</p>
