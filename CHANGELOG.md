# Changelog

## 0.224.1 (2026-11-09)

- What the design review of the staff's notes asked: only the thing in a sentence is a link (the book's title), never
  the whole sentence, here and in the desk's moment; links underlined finer. A book's round number of loans says it is
  this week's («“The lighthouse” reached 10 loans this week»), so that it does not clash with the year's figures. No
  note on top of each centre on the page of libraries, whose cards already tell their week. On the readers' page, the
  note goes under the search, which is what the page is for. «What was catalogued this month has already been lent…».
  The reports' note is «These days», and each sentence in it says its own time.

## 0.224.0 (2026-11-09)

- A little life on the staff's screens, each in its own subject and never the same strip on all, summed and without
  names, under the desk's three pennants (`local\life`, template `life`): the reports open with this week (loans and
  readers), the year ahead of the last at the same point when it is, and up to two moments to celebrate; the late books
  say how much of what came back this year came back on time (from seven in ten) and how many came back this week; the
  holds, how many books on hold reached whoever waited; the readers, how many took a book this year and how many their
  very first this week; what was catalogued, how many times this month's copies already went out and how soon the first
  did (or, with nothing this month, the copy added this year that went out most); weeding, the oldest books that still
  went out this year; the year, its busiest day and a clear most lent book; purchases, how many times what arrived because
  someone asked has gone out. On the page of libraries, each card says its loans this week and each centre its first
  moment. Nothing in a practice library nor in a textbook bank, nothing when there is nothing good to tell, and nothing
  on paper.
- A moment with more than four classes that met their challenge says how many, not four names «and 7 more».
- The window to add a purchase has a second-level heading.

## 0.223.0 (2026-11-09)

- The moments to celebrate (Enrique, 8 November: high roles see life too, not only figures): what happened this week
  that is worth a cheer, added up and without names. The library online turning a month, a hundred days or a year (from
  the day the centre opens to its readers, now written down, or its first loan here); a round number of books lent this
  year and the book that made it («1,000 books lent so far this school year. Number 1,000 was «The lighthouse»»); a book
  that has gone out a round number of times in all the library's years; the busiest day of the year at the desk; readers
  reaching the goal of a challenge of the library, or how one that just ended went; readers taking their very first
  book; and, for the staff only, the classes that met their challenge. In the words of each kind of library («this
  year» where there are no courses, no classes where there are none).
- On the home's board, a strip with paper pennants where the herald cheers the first and its book; whoever runs or keeps
  the library sees the next two below it. Under the desk's cards, the first, quietly. In the dashboard's block (from
  its 0.2.4), up to three for the staff and the first of the whole library for a reader. Readers hear only those of the
  whole library, and those that count people only from five.
- `\local_oksigeniaayvurenda\api::moments()`: the readers' moments of each centre for other plugins (the screens of a
  centre, a newsletter), with the book's cover; never a practice library nor a centre still closed.

## 0.222.0 (2026-11-08)

- The library's block on the dashboard tells at the desk (Enrique, 8 November: «Doesn't it tell anything?»): what is
  late, set aside and asked for, and also what comes back this week and what is out now, each leading to its list, with
  what the desk did today («Today: 3 loans · 2 returns»). «All up to date» only when nothing is out.
- Whoever keeps a desk sees the idea of the day too: in a narrow column it was hidden and left its box empty.
- High roles see life too, not only management (Enrique, 8 November; now a common rule): whoever runs the library
  without a card there gets the memory's idea of the day (a curious thing of the catalogue, the book of the week or what
  came last), and whoever runs or keeps it, «This week at the library»: books lent, how many readers came (from five),
  new titles of the month and the corner most read, added up and without names. It needs the block 0.2.3.

## 0.221.0 (2026-11-08)

- The story reader, as Gaudí drew it (`tale.php`): a story to read at home opens on its cover («Read it aloud, slowly.
  If they like, let them turn the page»), then the e-book one page at a time, and «The end» with «Good night», choose
  another story, start again and its credits («… chose it to read at home, after reading it all the way through»).
  Big letters for reading aloud, in four sizes, smaller on a short screen; night as a lamp, dim and amber, on when the
  system is dark and remembered; two big buttons at the thumb, the arrows, Page Up and Down, the space bar and a finger
  to turn, Escape to go back; the screen does not go off while reading; the open book when the phone lies down or on a
  big screen. A recording the work brings is offered quietly on the cover; never a made voice. For anyone, without an
  account, only for what a library marked to read at home at its open door. Anything but an e-book opens in the
  library's reader, as before.
- Where one is (Gaudí): a picture book laid out page by page says «Page 2 of 4» with its dots; text that flows says the
  chapter and the screen inside it («One · 2 of 4») over a thin line of how far through the whole book (its places,
  worked out once and kept in the browser for that book). The spine's shadow between two pages side by side; on a
  short screen, the chapter's title smaller so the text starts on its screen; the end, lying down, beside its cover.

## 0.220.0 (2026-11-08)

- Stories to read at home, as Gaudí drew them (Enrique: «Lovely»): a living room drawn on the page, which turns to
  night in dark mode with the moon and the lamp lit; the memory reading beside her words for the age, as each skin
  has her (whole where the little ones are, a portrait at a secondary school or a university, the library itself at a
  company); the four ages, each with its little book; each story with its cover, minutes, pages or chapters, why it is
  lovely for that age, «For bedtime» and «Read»; «Each one was read first, all the way through, by someone of the
  library»; three tips for reading together for each age; and the library's foot, with its next stops on a bookmobile.
  The address takes the age in years or as a band (`age=4`, `age=3-5`, also `edad`).
- Whoever looks at a story writes, beside its ages, about how many minutes it takes, its pages or chapters, a line for
  each age, whether it is for bedtime and its place in the list (without it, the shortest first).
- A page can bring a stylesheet of its own (`styles` in `shell::output`).

## 0.219.0 (2026-11-08)

- The library in the organisation's Telegram channel, through Oksigenia Marandu (Marandu's request, with Enrique's OK):
  whoever catalogues recommends a book from its record («Recommend it in the channel»), once per book, with its cover,
  author, age and a short summary; and each morning of the centre, the books lent in the year go to the channel when
  they pass a round number (1,000, 2,000, 5,000, 10,000…), each milestone once: small numbers look bad. Never anything
  of a reader. Marandu sends each post to whoever reviews the channel first, unless the site says otherwise, and draws
  its card. Only where the site has Marandu with its channel ready, and not on a site shared by centres.

## 0.218.0 (2026-11-08)

- «Your work» (Oksigenia Tembiapo, version 2 of its contract): each alert with a deadline says it (`due`), so the block
  can put the whole centre's alerts in one list, the oldest first in each level: the oldest book missed, the end of
  today for what is due today, the oldest interlibrary loan past its date. And the plugin has its icon, `pix/icon.svg`:
  the small emblem of the brand, as it is (Gaudí's delivery of 24 October, `emblema-pequeno.svg`).

## 0.217.0 (2026-11-08)

- Stories to read at home (the families' library, first part; Enrique, 8 November): at a library's open door, without an
  account, the stories it offers to read together, by age (0 to 2, 3 to 5, 6 to 8 and 9 to 12), with a word of the
  memory for each age. A grown-up reads them with the child: there is no «Listen» (Enrique: «that it is mum or dad who
  reads them stories, not all AI voices»). The address takes the child's age (`public.php?library=…&tales=1&age=4`), so
  that admissions (Oksigenia Eguahe) can send a family there; the door offers it when the library has some. Whoever
  catalogues marks a digital form for the public «To read at home» and its ages.
- Every tale is looked at before it is offered (Enrique, 8 November): only what a person of the library marks shows, and
  changing a digital form now leaves its trace in «Who did what», with the ages to read it at home.
- For admissions and other plugins of the site, `api::libraries()` (each library with whether its door is open and
  whether it offers tales) and `api::tales_url($slug, $age)`, which never gives the address of a closed door.
- Since 0.216.0, a centre's own character no longer leaves the visit at night: the hummingbird of the art sleeps on its book, and a
  character without a sleeping drawing stays awake (the tests found it on a clock where it was night).

## 0.216.0 (2026-11-08)

- Portuguese (audit, MD-04; Enrique, 8 November): the whole library in Brazilian Portuguese (`pt_br`) and in European
  Portuguese (`pt`), translated by machine from the Spanish with a glossary for each, every string checked to keep its
  placeholders, tags and line breaks. Where a voice of the help has no Portuguese recording, the step goes without voice.
- The library's card in «Your work» (Oksigenia Tembiapo, `block_oksigeniatembiapo`; Nuryana, with Enrique's OK): for
  whoever lends, keeps a library or manages, `local_oksigeniaayvurenda_oksigenia_work()` gives one card per centre, with
  the desk as its door, a line of numbers (out on loan, late, set aside) and what has to be solved, the urgent first:
  books more than a month late, a self-checkout point that stopped, copies that have not left the server; late, due
  today, interlibrary loans past their date, requests and class requests, reviews and voices to look at. Each alert
  leads to where it is solved. Kept five minutes for each person; nothing for anyone else.
- The terms a centre accepts on signing up (audit, DIA-12): its terms of service, privacy policy and data processing
  agreement, now on ayvurenda.com, in the language of whoever runs it (`--dpa` in `cli/add_entity.php`); «Centre
  settings» links the three beside the date.
- The caches of the curious things and of SIP2 machines have their names on the caches page.

## 0.215.0 (2026-11-08)

- A cover that does not load is never a blank (Enrique, 8 November): the card shows its drawn cover with the title, as a
  book without a picture; the small covers of the board go away.
- The reader's home board (Gaudí and Enrique): the herald's card spreads what it says over its height, and on a wide
  screen brings six new arrivals in two rows, bigger; three on smaller screens.

## 0.214.0 (2026-11-08)

- 0.213.0 did not reach the sites: on PHP 8.1 (Moodle 4.5) the corners of a search with a genre came in another order.
  They are sorted as text now, the same everywhere. It is all in here.
- The library's agenda (audit, MD-05 and MER-07; Enrique, 8 November): the storytelling, the book club, the author who
  comes. «Agenda» lists what is coming, each activity with its day on a calendar leaf, when (an hour, until an hour, or
  over days), where, for whom, a line and its places; whoever manages the centre adds, changes and takes away in its
  window. This week's, on the reader's home board; the next ones, at the open door; «Agenda» in the reader's menu when
  something is coming. For the centre's screens, `api::activities()`, beside `api::new_books()`.
- Each centre's week (Enrique: «the English start on Sunday, we Latins on Monday, and don't mention the Sabbath»): in
  «Academic year», the day its week starts (by default, what Moodle says for the site or its language) and the days its
  libraries close every week (Saturday and Sunday unless it says others, like Friday and Saturday). Nothing falls due on
  those days, and the notices of late books wait; «this week» in «What was catalogued» and the clue of the week start on
  the centre's first day.

## 0.213.0 (2026-11-08)

- 0.212.0 did not reach the sites: the validator of the CI does not take a form inside a span, on the genre cards of
  «Corners». It is all in here.
- The library's challenges with their own dates (audit, MD-03 and MER-05; Enrique, 8 November): the summer, the book
  week, for a public library as for a school between June and September.
  - «Library challenges» (in «More»), for whoever manages the centre: a card each, with its name, dates, for whom,
    how many books, and how it goes (who joined, who made it, the books of all of them); made and changed in its
    window, taken away with two taps.
  - On the reader's card, each challenge they can play: «Join in», and then a card of stamps, one for each book they
    take home in its days. Only the desk counts, and never the textbook bank; nothing they declare. No ranking.
  - Erasing a reader, joining two readers, the copies, recovering a copy and forgetting old years know the new tables.
- On the card, «What comes to the library» signs up for the centre of the page: it signed up for every centre at
  once, and could not then be turned off in one.

## 0.212.0 (2026-11-08)

- Genres of the stories (audit, MER-01; Enrique, 8 November): a library splits its stories into corners of its own,
  Mystery, Fantasy, Humour…, as many school libraries sort their shelves. A genre takes the books with its topics (any
  form of them: «Mysteries» and «mystery» are one) or whose shelf mark starts as the library says (N MIS), only from the
  corners of stories (the 8 and the letters, unless it says otherwise); the corner they come from keeps the rest. The
  classification, the shelves and the labels do not change.
  - On «Corners»: the genres your catalogue has, proposed from the topics of its stories with how many books each would
    take, to add one by one or all at once; each genre in a card, changed in its window; where they take books from.
  - In the catalogue: the genres as corners on the home page (with the initial where the others have a drawing, until
    Gaudí draws them), their own page, and in the search to narrow it.
- The reader's home board ends its two columns together: the last card of the shorter one grows, with what it says in
  the middle, instead of leaving a hole beside the other; the new arrivals' covers, half as big again.

## 0.211.0 (2026-11-08)

- The album's collector cards of a reader of the sky are the sky's, Gaudí's night cards with a moon (art package
  0.1.54), and the flower's where the package has none; corner 4, for a library whose corner 4 holds languages, as the
  Dewey 400. The art package 0.1.54 also brings the home picture of each skin, which the home page already looked for.

## 0.210.0 (2026-11-08)

- Other catalogues can gather and search the library's (audit, IOB-M2; Enrique, 8 November):
  - The OAI-PMH repository has two sets: «catalogue», every book with a copy in the library when its window shows the
    catalogue to anyone (in Dublin Core and in MARC 21 with its copies here, without their codes), and «digital», what
    the centre publishes as its own. Lists go on by date and number, so a change in between does not move them.
  - An SRU 1.2 server (`sru.php/<library>`): search by ISBN, title, author or any word, joined with «and», in MARCXML or
    Dublin Core, a page at a time, with the protocol's diagnostics for what it does not do.
  - The window's settings give both addresses.
- On a site that is only a library: the buttons of the site's outside accounts (Google, Microsoft…) on its own entry, with
  what they say when they cannot let someone in (an icon that does not load goes away); and «Security of your account», where the second factor is set up, from
  the card and from «More».

## 0.209.0 (2026-11-08)

- 0.208.0 did not reach the sites: on Moodle 4.5 a new test could not stand in for the site's AI. It is all in here.
- The plugin's own scripts (app/) go through ESLint in the CI, as the scripts of a Moodle plugin do; the three things it
  found are fixed (an unused variable at the desk, an escape too many when adding a book, the bookmark's function said
  to be used by the page).

## 0.208.0 (2026-11-08)

- For whoever installs and looks after it (audit, otr-diablo and otr-codigo):
  - A page whose address lost its library goes to the only library whoever looks has, or to the home that lets them
    choose; never to Moodle's error page.
  - The README says what it needs, who does what, what runs every night, the daily copy, the settings, what is optional
    and the command line, in English and in Spanish.
  - Every version in this changelog carries its date.
  - The CI tests Moodle 5.2 on every push and the whole supported range, 5.0 and 5.1 too, once a week; the SIP2 bridge
    has its own job; and each task of the night is run as the cron runs it.

## 0.207.0 (2026-11-08)
- «How do I…?» with the site's AI (audit, ia-bibliotecarios): only the staff ask it, never a reader; it gets the tasks
  the question finds, whole, and the rest by their title; the question goes between marks, as a question; it is asked in
  the library's context, so a shared site counts each centre's; what the AI thought aloud is dropped, and its Markdown
  is shown as clean text.
- The app (audit, int-aty-pixel and int-app-oficial):
  - A reservation in another library of the centre (a classroom's) opens there, is the reader's in the main library
    too («Your reservation is at…», with «I don't want it any more»), and is never made twice.
  - What a notice opens, and a book or a search opened from the library, comes fresh, not the app's copy of twenty
    minutes before.
  - A grown-up of a family who is neither reader nor staff goes by the plan of their children's centres, and a child's
    card in Aty shows nothing of a centre whose plan has no app; the official app's «Library» follows the plan too.
  - The date on a chip of Aty's home is the centre's day, in any time zone.
  - The staff without a reader's card find the way to their desk and their library instead of «not a reader yet».
  - A centre's own portrait of the memory reaches the app.
  - Enter searches; the results say how many there are, bring more with «See more» and show their covers.

## 0.206.0 (2026-11-08)
- Privacy of the youngest (audit, priv-menores):
  - Loans given back more school years ago than the centre keeps (two, unless it says otherwise in «Centre settings»)
    stop saying who took them: each reader's year goes to a stand-in of its own, in the same group and stage, so the
    figures of that year stay; the notes at the desk, and the reservations, sanctions, formats asked and reading places
    of then, go. What the reader won stays with them (the flower of each year in the album, the corners' cards), never
    the books. Every night, a little.
  - What readers see of each other («What your class reads», the book of the week, «Also taken») needs five readers
    behind it, and a pair of books three; below ten, the book of the week says «several», never the number.
  - Reviews are signed with the first name and the initial in a school, with no name in a public or mobile library,
    with the name where readers are grown-ups, until the centre chooses.
  - Whether the family decides who hears a voice goes by age (up to fourteen, by the age band or the stage), whatever
    kind of reader; the texts say so.
  - The privacy description says that asking for accessible formats may reveal a disability, and who sees it.

## 0.205.0 (2026-11-08)
- Every page of the library tells the browser to run only the library's own scripts: its files and what it writes with
  the page's nonce (`Content-Security-Policy`, with no plugins and no other base), and never to guess a type
  (`X-Content-Type-Options: nosniff`, also on every file it serves). The print buttons no longer carry a script in the
  page. The reader of e-books keeps its own rules.

## 0.204.0 (2026-11-08)
- What is reached without an account, tightened (audit, seg-anon and seg-cripto):
  - The address of a self-loan post carries its key after a «#», which never leaves the browser: the page sends it by
    POST and the post keeps it in a cookie of its own, so it is in no log of the server nor of a proxy. Addresses of
    before still open the post. The SIP bridge reads both.
  - A book read at a post that is neither lent nor given back counts against the post's limit, like an unknown code; a
    machine with nobody's card read says whether a copy is in, not what it is nor when it is due.
  - The public record and its cover show only the copies of the libraries that open their window.
  - The frame of the newest books for a website opens no Moodle session per visit.
  - Reading places are not kept for the guest account; a digital item that is not for whoever asks, or of another
    centre, is the library's page that says so, never Moodle's error.
  - Covers, library pictures and portraits are cacheable by proxies only when an open window shows them.
  - A family's link stops opening two days after they recorded with it, or as soon as the voice is published.
  - LTI registration asks over https (plain http only to a host the administrator allowed) and no longer shows the
    platform's raw error; with no deployment written, the first signed launch writes its own, and only that one comes in
    after.
  - «It is read here; the library does not offer it for download», which is what is true.
  - A RIS file is named by its citation key, not by the title as typed.
- On a phone, the way out is in «More» and no longer a tab too: with few doors (someone without an account) it pushed
  «More» off the screen.

## 0.203.0 (2026-11-08)
- The curious things of each catalogue, worked out once a day: with nothing of the reader's own to tell, the memory
  tells its longest title, a title of one word or a title that is a question, and takes the reader to it (never to
  Infantil); the blackboard of the visit and the pulse of the door say the year of the oldest book still in service
  (not in a company).
- While the first layer of the visit comes, a line under it: «Opening the doors…», or «Lighting the lamps…» by night.
- On the shelves: at the end of the last one, a sign for the flower («The library ends here. For now.») and for the
  sky's in a school, with the way to ask for a book where the library takes requests; on the lectern, the thickest book
  of the stretch, when its pages are counted.
- In the app, nothing on loan in the words of whoever looks.

## 0.202.0 (2026-11-08)
- The last lines of humour: the practice library says what it is on top of each of its pages (break away: it goes the
  next night); the herald marks the hundredth loan of the day at a desk; on the first of April one spine of the shelves
  stands upside down and the memory owns up to it on the lectern; a gap opened a third time, the hummingbird says it is
  still keeping the spot.

## 0.201.0 (2026-11-08)
- The voices of the lines of humour that carry no name nor search, for the youngest, who may not read yet (art 0.1.53):
  the memory by night in the visit, the third tap on the memory and on the herald (each herald with its voice), the
  memory's glasses on a search with nothing found for Infantil, «Looking for me?» of the memory and of the herald, and
  the empty album of Infantil.

## 0.200.0 (2026-11-08)
- From the audit's ideas, with Gaudí:
  - «Show it big» on the card: the codes on the whole screen, black on white, the screen kept awake, closed by itself in
    half a minute;
  - at the desk, a child who brings back their last book sees the hummingbird cross their card once and leave a feather
    by their name (nothing with «reduce motion»);
  - «The neighbour» on the lectern of the shelves: which of three labels goes on its right; the wrong ones say which
    way they go (no game with twin labels);
  - the clue of the week, an A5 by corner from the posters page: the words on top, the memory thinking and the label as
    the hero, as the real label of a spine, of a book hidden on the real shelf, the same all week, to find it and take
    it to the desk;
  - the desk's stamps: the date of the loan just made falls on the card and sets sharp, «Returned» is stamped green on
    the line, and the reader's card lands on the desk;
  - the way into the library one visits opens as a door, on its hinges, with the hall's light;
  - the hall's clock keeps the library's own time (Gaudí painted the face without its hands, art 0.1.52);
  - the hummingbird crossing a child's card flies along its top edge and out, never stopping over a button.
- 0.199.0 did not get installed (a test of the late notice depended on the day); it goes inside this one.

## 0.199.0 (2026-11-08)
- The lines of humour, second part:
  - a book late with nothing to pay: a day in two, another way to say it, by age and library (the herald to the young;
    the shelf has noticed, to the secondary school; someone is waiting, at a university; a visit fixes it, at a public
    library);
  - a year from a reader's first loan, the herald of the card remembers it, with the book;
  - a very old book says so on its record (fifty years for the young and the sky, a century for grown-ups);
  - on the first of April (and the 28th of December in Spanish) the two characters swap jobs: the memory brings the
    news, and the herald searches;
  - while a free work is brought, the herald carries it, and hummingbirds are small; while the cards are made, cutting
    them out is still yours.
- The herald's round (with Gaudí): each day it is in one room of the visit only, the same for everyone; in the hall, a
  feather of it (Gaudí's, art 0.1.51) on the board's row of that room, for the young; the sky looks for it.

## 0.198.0 (2026-11-08)
- The lines of humour, first part (from the audit, with Gaudí): written for each age, a bag of them the same all day for
  each reader (`characters::bag`, `characters::band`):
  - a search with nothing found, by age (the glasses for Infantil, under the rug for Primaria, dry for secondary,
    short for grown-ups), and three Easter eggs: the name of a character (who answers), «nothing», and «book» with
    too many;
  - a page not found, with the memory's line by age; a shared book no longer in the catalogue, its own;
  - the herald's word as a book comes home, by the age of whoever brings it and the nights it slept out; «Undone» now
    and then says that nothing happened here;
  - the visit by the library's night: the memory speaks low, the hummingbird sleeps on its book (Gaudí's new pose, art
    0.1.51) and says so with its eyes shut, and the board counts the books sleeping out; with nothing out, the herald
    watches the door; the third tap in a row on a character says something else;
  - a new reader with nothing taken yet is welcomed by the memory; the empty album of Infantil, as a plate before
    dinner; the staff's reservations with nothing waiting, in the university's words.

## 0.197.0 (2026-11-08)
- Play and the visit (audit batch 10, with Gaudí):
  - the hidden book by age: a shorter shelf for the youngest; for a reader past the flower, the memory gives the title
    and the author, the label is folded under «See its label», the spines carry no title (as seen from the aisle), and
    a book touched comes out a little with its title and goes back;
  - in the hall, the memory tells a reader today's idea, with the way to it; in a room, the herald tells how many books
    went home from that corner this week;
  - the hidden-book game comes out of the room's shelf: the room leans towards it and the game opens from there.
- «Not yet» on a reservation: a reader puts it off for a week, two or a month and keeps their place in the queue; nobody
  waits behind it meanwhile, one already set aside goes to the next or to the shelf, and «I can now» brings it back
  (MER-03).
- A shared link to a book no longer in the public catalogue shows the memory and the way back to the door.

## 0.196.1 (2026-11-07)
- The personal area's block, after Gaudí's review: whoever lends at several desks gets one section with a row for each
  library (its name, what waits, in red with books late, and «Desk»); the buttons «Go to the library» and «My card» come
  once, at the end, and each library's name links to it; a class challenge met says «Met!» by its count. A university or
  a company looks like one whatever the reader's card.

## 0.196.0 (2026-11-07)
- The memory's idea of the day, to taste and with a door to the new (Enrique, 7 November 2026): after a book loved, first
  what three or more other readers who loved it loved too (each loved by two of them at least, so that no one's reading
  can be guessed); one day in three (one in two when the flower's «Explore» has stood still for a month), a book of a
  corner the reader has never been to that shares an author or a topic with one they loved: «Something different today».
- What the library shows on the personal area, for the block 0.2.0 (`\local_oksigeniaayvurenda\local\dashboard`): with
  nothing at home, the memory's idea with its book on a plate, or the book of the week, or the covers of what just came;
  with books at home, each with its cover and when it goes back in words («Back on Thursday», «It was due back on
  Monday»), what is set aside and «Renew»; to a pupil, the class challenge; to a grown reader, their own goal; to a
  teacher, the readings of their courses against their students; to whoever lends, what waits at the desk, as numbers.
  In the words of each centre's skin; the round portraits come in the art package 0.1.50.

## 0.195.0 (2026-11-07)
- Play and the visit (audit batch 10, second part):
  - once a class meets its challenge, the tutor (in «My class») and the staff (on the group's page) can start another
    one in the same term, counted from that day; the garden keeps both, and «No other challenge» goes back to the one
    met; after its two weeks of celebration, the challenge says the day it was met;
  - the reader's own goal counts the books finished (brought back, not half read), as the flower's «Stay» does, up to
    100 a year; its words say «finish», and in the secondary and university skins it is met in their own words;
  - the card in the app shows the moon of the stage too, and the printed card of a public or mobile library draws the
    seed or the new moon for the readers who have them;
  - the news of each centre are asked for in that centre: someone with a card in two centres chooses in each, and a
    sign-up for all from before keeps both until leaving one;
  - the visit on a phone opens with the room's map in sight, its arrows sit at the top corners instead of on the door,
    and the list under the scene ends with the way out.

## 0.194.0 (2026-11-07)
- Play and the visit (audit batch 10, first part):
  - the hidden book never has a twin with the same label on its shelf (the next turn's book is taken), and if two
    labels still match the memory says so; the gap of a book that is out is not counted as a miss; the magnifier shows
    only the label (a tried spine, its title), and the spines of the game have no title tooltip;
  - after «before» or «after», the memory says in her voice which line of the label to look at, and that line lights up
    on the clue (new voices in the art package 0.1.49); the book found shows its cover;
  - «Shelve the new books» starts every day with other books, and each door shows its number, as the shelf's sign;
  - the class challenge of corners and the flower's «Explore» ask for the corners the library shows (between three and
    six, and between two and seven), not a fixed number a classroom may not have;
  - the terms of the class challenges: thirds of the ten months of classes from the start of the year (the third one
    was the summer), or the three end days each centre sets in «Academic year»; the herald celebrates a challenge met
    for two weeks, not every day until the next term;
  - a young reader sees the class's island on their card, as the tutor does in «My class»;
  - the app's card shows a young reader's flower.
- A class named twice in the catalogue no longer warns when the corners are listed.

## 0.193.0 (2026-11-07)
- The reader's home as one board, from Gaudí's mock-up (dis-pase-04): on the left, the herald once and whole, with three
  covers of what just arrived and the book of the week on a plate; on the right, what the memory recommends with its cover,
  and the reader's flower with «My card» and the class's challenge inside; one «Listen» per card, by its title. The visit,
  as a card with «Enter» and the other two ways in (Ayvu rande and the window) beside the hall.
- The header on a phone, from Gaudí's mock-up (dis-pase-21): a bar with the emblem, the name and a magnifier; the search
  opens over the bar, with the keyboard, a cross and Esc, and starts open on the results; a reader has four tabs, the fourth
  «More», a sheet from below with the rest and the way out, closed with a tap outside it.

## 0.192.0 (2026-11-07)
- 0.191.0 did not get installed (the CI's HTML validator refused `role="switch"` on a button): it comes inside this one,
  with the switches as `aria-pressed`.
- The characters do not move any more: their puppets turned pieces cut out of a drawing made whole, which the common
  rule on animating characters forbids; each one stands in its pose, drawn whole, everywhere (`characters::MOVING`), until
  they come back by one of the rule's ways.
- Design, dark mode and accessibility (audit batch 9, second part):
  - focus and anchors stop below the fixed header, and the header is not fixed in short windows (WCAG 2.4.11);
  - the book reader takes the whole width when narrow or zoomed to 200 %; the label map's cells are 24 px at least;
  - the visit fits a classroom board's height (1280 x 600), sliding sideways when needed, and the hidden-book game fits
    low screens; at night by the library's own hour, not the screen's mode (`light=night` or `day` to look at the other);
  - what the mouse lights up in the visit, only where there is a mouse; windows open in the page on browsers without
    modal dialogs; the guided help sends its forms on an old Safari;
  - less motion keeps fades and colour changes, and the lectern's sheet no longer rises on a phone; a tapped spine does
    not stay up; the camera vibrates 8 ms;
  - the guided helps have their own volume, a quarter to start and remembered for the device, as the visit;
  - the record: back to the results it came from, reviews after the copies and where the book is, a young reader draws
    or speaks first and writes if they want, and the hold box is «Your loan» when the reader has the book;
  - the card's code right after the card on a phone; a young reader of a school reads nothing under 14 px;
  - «My class» opened by a pupil goes to their class on the home page;
  - «Readers» puts the search first with the strong button; the staff notes keep themselves on leaving the field;
    «Late» has a field that finds a name or a title and opens its group; the loans report says its days and each group
    opens its readers; «One reader borrowed», not «1 readers»;
  - on a phone, the desk hides «Read with the phone» and the camera leads; the start of the year in the centre settings
    is a day and a month from lists;
  - Gaudí's review of 0.191.0: the shelf places without the repeated drawing, the corners' hint once, characters
    «spoken of as» she or he, and the flower's row a point bigger.
  - a «How do I…?» task to put the library on everyone's dashboard (UXL-17).

## 0.191.0 (2026-11-07)
- Design, dark mode and accessibility (audit batch 9, first part):
  - in dark mode, late books, «Erase this reader…» and the featured library's «Enter» read at 4.5:1 or more, and the
    shelf embedded in another website keeps its own paper and ink whatever the mode;
  - the characters, a card each: how it speaks on top, its name, its sex as two buttons, «Save» in the card, and one place
    to drop a picture (its portrait) or a zip (the whole character), with what each holds folded under it;
  - the corners in their real order, with their drawing, the name above its field and arrows to move them (no more
    numbers to invent), and a page that says where corners come from when there are none;
  - «My class» without a class: the island still empty, Mainumby reading and two ways on; the challenge rules only where
    there is a class;
  - the search on a phone folds its filters under «Refine · n», unless one is on, so that the books come first;
  - the tutors' «Save» as a bar the width of the page; the course memoir's groups in the school's order, under their
    stage; a stocktake with thousands in its counts and fifty at a time with «See 50 more»;
  - the card: the flower's marks say their level in words, stickers not won lose «not yet» and step back in dark;
  - Ayvu rande without voices: the memory reading and «Find a book to give a voice to», without the coral microphone;
  - the public door for the staff: the pulse as a line of text, and «By corners» over the second grid;
  - the review's other ways (a drawing, a voice) as chips in one row, «Send» on the right; «Accessible formats» on the
    reader's record as a switch that saves on a tap; the visit's game leads with a magnifier and «Sound» sits with its
    volume; «Who did what» names who did it only when it changes; the phone page's button the size of its words on a
    computer; the shelf places as three books on a plank; a guided help's stage the size of its card until it starts;
  - book counts with thousands on the home page, the door and the shelves.

## 0.190.0 (2026-11-07)
- Helps (audit batch 8):
  - «How do I…?» answers by how much of the question a task covers, not by how many words it has: «lend a book» is the
    desk, not the textbook bank; the words every task has only break a tie; the very word counts more than its
    beginning, above all in the title; a question that holds a whole title asks for that task; one word that only the
    steps share is no answer; and words that say nothing («what do I») find nothing, while an acronym in capitals (QR)
    is still a question;
  - new tasks for what the libraries asked and found nothing: correct a record or a copy, open the library when it is
    ready, read codes with the camera or a phone, the centre's settings, the readers' voices, link readers with their
    accounts, readers from a list, the guides and helps, a lost book, the password, and who has a book; more words for
    who keeps a library, late notices, repeated readers, a book the desk does not know and new books;
  - readers asking get readers' tasks (reserve, keep a book longer, my card, a review, a voice, another format), and
    the staff do not get them; the printed guides are offered only to the staff;
  - every guided help with its task links to it (the bank's campaign, the voices, readers from a list), «Print cards»
    goes to its own page, and «If a book comes back late» has its guided help beside it in «Rules»;
  - a guided help is offered only where its skin can play it, and help.php says so in its own page instead of ending in
    «not found» (or in an error page, for a help that does not exist);
  - inside a guided help's frame, no page shows its own help, its row of links or «How do I…?»: every link and form
    of the frame keeps the help on;
  - «Skip to the end» taps the button «Now you» waits for, and that button takes the keyboard focus (the frame is no
    longer out of the tab order);
  - a help that stops (a tap that leads nowhere for 20 s, something not found) says so in words, and «Start» prepares
    the practice library again and starts;
  - the help of who keeps a library searches whoever practises, gives them the library with «Now you» and shows
    «Remove»; the labels help types its codes one per line; the practice's self-loan post has another name than the
    one the help creates; the card's last step speaks of «Print cards» as it is now (with its voices);
  - two new guided helps, with their voices in Spanish and English: printing cards (whose, the design, the logo, where,
    the back, prepare), which lets the practice library into «Print cards» only inside the help and with nothing kept
    or made, and correcting a record and its copies, with its «How does it work?» on the record for who catalogues;
  - «Apártalo» on the desk's button, as in its line; the Abies Web card among the readers' sources only in Spanish or
    where something already came from it.
- Ages (the rest of batch 7): the book reader's arrows say «Previous» and «Next» under their icon (on a phone, the icon
  alone, with its name for screen readers), and a young reader of a school reads the pages a little bigger.

## 0.189.0 (2026-11-07)
- Ages and skins (audit batch 7):
  - the card and the characters guess the age as other countries name their groups (Grade 2, Year 7, Klasse 3, CE2,
    6ème, Transición, 4º Básico…), by the first number of the group, and «Grado en» is a university degree;
  - what is not recognised is the lively sky in a school, the sober one where readers are grown-ups or of every age;
  - a reader's age band, said by the staff in «Change their data» when the group does not tell it (a new field, with
    its upgrade step and its privacy line), above the stage's choice;
  - a young reader of a public library or a bookmobile reads the school's words (the ones its voices say), and a reader
    past the flower in a school reads the secondary school's words where there are some;
  - a university speaks with the plain words of grown-ups where it has none of its own, not the school's; the help's
    title, intro and end speak in the skin too; «How do I…?» without «course», stages or families where there are none;
  - neutral words where school words leaked into other skins (a reader, the staff, the year);
  - the reader's small targets at 44 px (the way out to Moodle, the credits, the subnav, the search filters, volume);
  - the card's look choice comes first until the reader has chosen it;
  - a reader of the flower is not told how many books each corner has;
  - the herald speaks of what grew, not of what is missing (with its voices in the art package 0.1.47).

## 0.188.0 (2026-11-07)
- The guided help «Sets and classes» of a textbook bank (a set, its titles, adding one, a new set, by class, a class), with
  its voices in the art package 0.1.46, opened from «Sets», «By class» and the bank's desk.
- A new year: a set none of whose groups has pupils opens its change with this year's groups to tick, and the handout links
  to the sets.

## 0.187.0 (2026-11-07)
- Linking readers with their accounts: «None of these» is remembered. The reader is not offered those accounts again and
  goes with those the site does not have, still a reader with their card. Nothing is checked by default: what is left
  unmarked stays in doubt for another day.
- «Marcarlos como antiguos lectores», which marks everyone the site does not have at once, asks with a second touch.

## 0.186.0 (2026-11-07)
- The last day of class of 0.185.0 becomes a date to have everything back, for any library and any calendar (the long
  holidays of each country, a stocktake, a move): until then nothing falls due or is renewed past it; six weeks before,
  «Academic year» and the desk say what is out and let everyone be told; once it has passed, lending goes on as always.
  The notices and «How do I…?» no longer speak of the end of the course nor of June.
- Linking readers with their accounts, and pairing with a platform: with given names and surnames apart, a candidate has to
  be able to be the same person (the given names one inside the other, the surnames of one all among the other's, a letter
  changed in a long one). A shared first name and one surname in another place, or another first name, is no candidate.
- A book with an ISBN and no cover (made by hand, or imported) asks for its cover once, in the background, when it is lent,
  comes back or its record is opened, and not again for six months: one question per book, as Open Library asks, never a
  sweep of the catalogue.

## 0.185.0 (2026-11-07)
- The end of the course for a reading library (where there are classes), in «Academic year»:
  - the centre says its last day of class: nothing lent falls due after it, and no more time is given past it;
  - the end of the year shows six weeks before that day, not in the summer;
  - «Tell everyone who has books»: each reader gets their books and the date, and each tutor their class, as the library's
    notice (a new message, «Library books to bring back at the end of the year»), not the bank's;
  - «How do I…?» answers how to get the books back in June.

## 0.184.0 (2026-11-07)
- The bank of textbooks, from the audit:
  - the round is of its year: in September, last June's date, reminders and counts are gone;
  - collecting begins in June when no date is set, and textbooks are lent until the round's date when it is set;
  - a pupil who no longer comes is not waited for, and is listed only for the books still with them («Ya no viene»);
  - a book given up for lost says whose it is when the pupil has an account;
  - each set counts what its own pupils have, and the free copies are shared between the sets that ask for a title;
  - a textbook is «Roto» or «Le faltan páginas», not «No funciona»;
  - the contract fits a phone; a served class is quieter without faded text; each set's search has its name;
  - «No lo trae: perdido» only when books come back, and it and «Quitar el lote» ask with a second touch;
  - groups without a set only from the stages the bank serves;
  - whoever keeps a bank runs its classes, its round and its sets, with their links at its desk;
  - a pupil opening the bank sees their textbooks, their state and until when, not a library to visit, and the bank is
    not in a reader's choice of libraries;
  - a textbook only a bank has is not given a voice nor reviewed, and a reader sees a library's many copies in one line;
  - the block and the app say «and your N textbooks» in one line instead of listing them all year (block 0.1.7);
  - who left with books says their group of this year when they had none the year before.

## 0.183.0 (2026-11-07)
- With 0.182.0, which was never installed (a test that picked one of two top libraries at random failed in the CI).
- Corners for libraries that sort by letters (N, P, C…), on their own after the figures, coloured in turn, in the
  catalogue, the search, the shelves, the hidden-book game and the memory; «J 821» is in the corner of 8.
- A spreadsheet: «Fecha de alta» is when the copy came, no longer the year; a column with the kind of document (book,
  DVD, magazine…); two editions without an ISBN are two books; Excel's «Unicode text» (UTF-16) is read.
- One MARCXML record on its own, without a namespace, is recognised.
- Weeding counts a copy's loans wherever it was lent (a box in a classroom too).
- A call number's title letters keep a word said twice.
- An ONIX edition is written in the language of the book, or as the publisher says it.
- The search simplifies ß, æ, œ, ø, ł and their company («strasse» finds «Straße»); the stored search text is rewritten.
- The app's home picks the centre's reading library every time, never the bank of textbooks.
- What a child writes: «dragones» finds «dragón», «un libro de dragones» finds the dragons; with close words, the memory
  no longer says she is sure there is nothing.
- A spreadsheet ISBN typed wrong goes to the record's notes and the preview says it.
- Labels open on the last day copies came in when none came today.
- Two people adding books at once no longer take the same code (a lock per centre, also for new cards and lists).
- A corner shows all its pages, not only the first.
- A field longer than its column (an edition statement, a physical description) is cut instead of stopping an import.
- «Came this year» in the course's memoir and in «The year in figures» counts when copies came, not when a catalogue
  was imported; an import that does not say when leaves it unknown.
- A failure inside a plan's check no longer leaves its transaction open.
- A centre's own character is drawn in the public visit without an account.
- The stocktake keeps what was read when the session runs out or the server says no, and says why.
- Adding: one save at a time (a double Intro or click no longer makes two records), and a lookup or search that fails says
  so instead of «Looking up…» for ever.
- An EPUB that cannot be opened says so.
- The daily copy and OAI-PMH write the class number in 080 and 082, not its name.
- Bringing a MARC or spreadsheet catalogue again takes the corrections made in its program, on records not changed here
  (as with Abies); a record with the same first author is the same book even if its title was corrected.
- A MARC or MARCXML file is read a record at a time from the disk: a big catalogue no longer sits whole in memory.

## 0.182.0 (2026-11-07)
- Bringing a catalogue in:
  - the volumes of a work keep their number and name (245 $n and $p), and a record without 245 $a takes another title;
  - an 852 split in $h and $i keeps its whole call number;
  - UNIMARC's field 100 says its character set again;
  - German and Portuguese in Windows-1252 are no longer read as MARC-8;
  - a record with nothing that names it (no 001, 035 or ISBN) does not come in twice;
  - two programs that number their records alike no longer hang one's book on the other's record;
  - a copy whose code is another record's gets a new one, listed to be relabelled;
  - records with no title at all are counted and said.
- A record whose copies were all withdrawn or lost, with nothing to read online, is no longer in its readers' catalogue.
- An ISBN-13 needs the prefix of books (978, 979); an ISBN with its qualifier stuck to it («…(tapa flexible)») is read.
- Labels by codes find codes typed in small letters.

## 0.181.0 (2026-11-07)
- Closed days cost nothing: a late book pays, or keeps its reader without loans, only for the days the library was open.
- The class challenge «Everyone» counts the readers who are here, not one who stopped coming.
- A cohort renamed during the year takes its tutor with it; a reader erased leaves no group of this year behind.
- The pupils of a course see its readings before the night makes them readers (on a shared site, being in the course is
  what they have).
- The only editing teacher of a chosen course becomes the tutor of its groups where they have none, so «My class» reaches
  whoever brought them.

## 0.180.0 (2026-11-07)
- Whoever looks after one library (a classroom's, a department's) sees what it has out and who has it («Lent», in its
  header and from the desk's figures), and a page that is not for them says what they can do instead of sending them to
  ask for a permission.
- The year report and the corners of a classroom or department library count its own books and loans, not the whole
  centre's.
- Choosing who looks after a library shows each person's group.
- The memory offers again only a search that found something.
- «N readers without an account» shows only where there are accounts to link them with, and not over a search.
- «Centre settings» is the first entry of «More» › «Settings», and opens its window.
- Words that talked like a platform: «Other platforms», «Site groups (cohorts)», «Site courses», «Download the sheet».
- A name alone in a list the first time and twice the next keeps the reader of before, by their group.

## 0.179.0 (2026-11-07)
- Readers who came through a platform (LTI) have their origin noted, and the night of the cohorts no longer takes them
  away (they left and came back every day); an upgrade step notes it for those already there.
- Cifra is read to its last page, however many people its pages bring.
- Joining two readers moves their loans between libraries too; a test checks every table that belongs to a reader.
- «Tutors»: saving does not take away a tutor who is no longer a reader (they show as such until another is chosen),
  and the groups listed are those of readers who are here.
- The reader's card at the desk: «Renew», «Return» and «Lost» of one size, together at the end of each row.
- A list of readers takes «Curso» (and course, grade, form, homeroom…) as the group, and a group named for teachers,
  staff or families says the kind of its readers.

## 0.178.0 (2026-11-07)
- The desk: «Renew» on each loan of the reader's card, without the book; with a reader open, the field says whom it lends
  to and the day's figures stay under the card; «Their record» opens in the same tab and «Close» says «Done, next»;
  «Due today» opens today's list.
- A name typed at the desk shows the people first; the books that also have those words are folded under them.
- A code read twice that is not of this library says so, instead of «read it again» for ever; a second copy of a book the
  reader already has stops (the librarian may go ahead).
- An error page of the site is no longer taken for a lost network: that scan shows as failed and the desk goes on.
- A centre that has just started sees, at its desk, where to begin: books, readers, labels and cards.
- The self-loan station keeps a visit for a minute and a half, asks fifteen seconds before it ends, and any touch gives
  more time.
- «My shelf» and asking for another format, opened by their address, answer as pages of the library.
- With everything of 0.177.0, which never reached a site (a template of «School year» did not pass the CI).

## 0.177.0 (2026-11-07)
- Closed days: each centre names the days it is closed (holidays, a local festival, a move) on «School year»; nothing
  lent or set aside falls due on them, it moves to the next open day, as weekends already did.
- The family of a young reader hears of their late books too, where the site keeps its families (Oksigenia Family):
  through their account, or by e-mail.
- A «set aside for you» that could not be sent is tried again the next morning instead of being taken as told.
- At the desk: a return undone, or a returned loan renewed, after another reader took the copy, is refused (it made two
  open loans); so is undoing a loan of a book that came back, or a renewal with a date that was not its own.
- The limit of loans counts what is out under the same rules: not the textbooks of a bank, nor the books of a classroom
  with rules of its own.
- A reader who left the centre, or whose card is on hold, is stopped at the desk; the librarian may go ahead.
- A copy of another library of the centre lent at this desk is that library's loan.
- «Lend anyway» on a red line lends to the reader of that line, not to whoever is open now; scans kept without a network
  go to the reader each was kept with.
- The self-loan station and SIP machines leave to the desk a copy whose loan was noted by its title; a book read again at
  the same station soon after being taken, with no card, is still «yours» and is not returned.

## 0.176.0 (2026-11-07)
- The nightly notices go at eight in the morning of each centre's own zone, not of the server's: the task runs every hour
  and tells each centre once a day (late books and what waits for the staff on weekdays; what expires every day, Saturday
  too).
- A reservation that was set aside and not collected tells its reader it is no longer kept, and how to ask again.
- The textbooks of the bank stay out of the late notices: they have the bank's own reminders, which no longer reach
  anybody twice if the task is tried again, and the page says while they are on their way.
- Every notice comes from the library by its name, so a site with several libraries tells them apart in the inbox.
- To a young reader, the late notice keeps what it may cost in the plain text too; at a public library or a bookmobile
  its subject speaks in the same voice; at a university or a company the herald does not speak.
- The copy of the files that did not reach its place goes again the next night, and the old one is kept until the new
  one exists; two nights without a copy out are told, and then every week, and «Make a copy now» counts no night.
- A recovered copy brings back the names of readers with an account who had left.
- The weekly news counts from the last letter, however long ago.
- The images of the notices have a name; a family's voice link is not sent from a practice library.
- The card of a reader opened again shows its album (0.175.0 broke it; it never reached a site).

## 0.175.0 (2026-11-07)
- The late notice counts its week by reader, not by book: two books late a few days apart are one message with every late
  book, instead of a message for each on different days; a reader who stopped coming but still has books hears too, and
  their tutor's summary lists them.
- A centre open only to its staff tells its readers nothing, whoever sends it (the textbook bank, purchases, other formats,
  other libraries, voices); its staff still hear.
- What is set aside, or what a late book left, while a centre is getting ready is told when it opens, instead of being taken
  as told and never told.
- The nightly copies: a sweep that fails no longer leaves every centre without its copy, the weekly copy of the files runs
  apart from sending the copy out, and the task log says why something failed.
- Whoever is on the staff of two centres hears each day of what waits in each.
- A reader without an account can be given their way in on the web («Darle entrada» in their record, or a whole class
  from its page): an account with a first password they change the first time; by e-mail if the library has one, or on a
  sheet to print, shown once. It works in any plan, with or without the app.
- A copy read at two desks at once (or a desk and a self-loan station) is lent once.
- Each skin has its own home picture; a library without classes offers no group nor tutor to change, no class
  challenges nor stage cards in its settings, no «What your class reads», and no «What the courses ask» where there are
  no courses; its reports start with the most read.
- In a public library or a bookmobile the characters stand whole only for the young; the album's collector cards only
  for a school's readers; Mainumby whole beside an empty album only where the characters stand whole.
- «Lost» on each late book of «Late books».
- The card opened without its library goes to the reader's library instead of failing.
- LTI: the library opens inside the platform's frame (Moodle's «Select content» and embedded activities), and the site's
  LTI private key is kept encrypted.

## 0.174.1 (2026-11-07)
- «Subjects» no longer falls over in a catalogue with subjects that are a number («1914»): they became integer keys and
  the grouping of the alike went round for ever. Subjects with figures, Arabic or Roman, are never taken for a typo of
  each other («1914» and «1915», «siglo XX» and «siglo XI»).

## 0.174.0 (2026-11-07)
- «Lost»: a book a reader has lost is given up as lost at the desk (next to «Return» in the reader's list, with a second
  touch) or from their record; the loan ends as lost, the copy is lost, what a late book costs at the centre counts as if it
  came back that day, and «Undo» opens the loan again. A lost copy that turns up and is passed at the desk goes back on the
  shelf, or straight to the reader who is there.
- «At home»: the reader sees on the library's home page and on their card the books they have out, with the day each one
  comes back, the late ones in red, and «Renew» where the library lets them.

## 0.173.0 (2026-11-07)
- Dates for a date field keep the zero of the day: «Reports» came with «From» and «To» empty from the 1st to the 9th of
  every month (and «From» always, since a year starts on a 1st), and so did other date fields and the dates of some CSV.
- The copy a centre takes with it has the readings of the courses with their books, the course reserves, the fragments,
  who keeps each library and the LTI platforms; what it leaves out on purpose (voices and their consents, the keys of
  self-loan stations, a phone paired with a desk, where each person goes in a digital book) is written down, and a test
  fails if a new table is in neither list.
- The catalogue search finds a book by its ISBN, with or without hyphens, in 10 or 13 figures.
- The app no longer says «Done: it is reserved» when the hold was refused (the limit reached, a copy free, already asked
  for): it shows why.
- Two global functions of the page of copies have the plugin's prefix, and the plugin says it is beta.

## 0.172.0 (2026-11-07)
- «Repeated records», from «More»: the records that share an ISBN, and those with the same title and author when one lacks
  the ISBN, side by side with their copies, summary and origin. Joining them leaves one with every copy, loan, hold, review,
  reading and subject, and with what it lacked (the summary, the cover); a new import of the program they came from updates
  that one. «Not the same book» stops offering them.

## 0.171.0 (2026-11-07)
- The subjects of a centre are one vocabulary: a subject typed, imported or brought by a catalogue that the centre has with
  other capitals, accents or number is that one («misterios» is «Misterio»).
- Correcting a record, its subjects are chips: what the centre already uses comes up while typing, the most used first and
  with a letter wrong too.
- «Subjects», from «More»: those that look like one (written another way, or a typo apart) to join with a touch, choosing
  which stays; every subject with its books, to look for and rename (renamed as another, they become one).

## 0.170.0 (2026-11-07)
- «How does it work?»: the guided help of every page is a button that says what it does, first in its row, instead of a
  link that said «Help»; the same words in «How do I…?» and in the titles of the guided help.
- Pass a book to a class: besides Google Classroom and Microsoft Teams, a Moodle course of whoever looks, where it goes into
  the course's readings, required or not.
- What waits to be looked at (reviews, voices, what the classes asked for, other formats, books to buy, stopped self-loan
  posts, late loans from other libraries) shows as a number on its link and on «More», as phones do; never for a practice
  library.
- Labels: the sheets by brand and reference (Apli 01286 and 01276 and fourteen more of Apli, Avery and Avery Zweckform, and
  US Letter), the favourites of each library first, for codes and spine labels apart; the print moved for the library's
  printer, sheet by sheet, with a test sheet to hold against the light. Spine labels on small sheets shrink their text.
- Printing labels: only the sheets go to the paper. The row of the page's links and the notice of a long list pushed the
  first sheet down, and every label came out shifted.

## 0.169.0 (2026-11-07)
- The class while it is typed: a word in the first box of the call number («biology») brings the numbers that fit, with
  their names and how many books have them, first those the library gave before to books on that subject; a number brings
  those that start with it. In «Add books» and in the correction of a copy.
- «Catalogued lately»: the copies added by hand today, this week, this fortnight or this month, the latest first and day by
  day, with what may have gone in twice; from «More» and from «Add books».
- A book added a moment ago that is passed again in «Add books» asks whether it is another copy, and the next book waits
  for the answer; a book without an ISBN typed by hand asks when the catalogue has its title and author.
- «How do I…?» says how to lend by the ISBN, find the class of a book, see what was catalogued and wait some days before
  the late notice.

## 0.168.0 (2026-11-07)
- The desk reads the ISBN of the back cover as if it were the book's label: with a reader open it goes out, or comes back if
  they have it; without one it stays in the hand, or comes back when only one is out; when it is not clear, the desk offers
  what is out and what is on the shelf. An ISBN that is not in the catalogue says so.
- Late notices can wait: «Days before the first notice», in the rules of what a late book leaves, for the whole centre (1,
  the next day, as before; 7, a week of grace). It counts for the reader's notice and the tutor's summary; the desk says it
  from the first day, and the staff can still tell now.
- A practice library tells nobody of what the guided help leaves waiting in it.
- A site coming straight from 0.149.0 or before upgrades in one go: the origin of an import is renamed before the step
  that reads it.

## 0.167.0 (2026-11-04)
- Print cards: «The design» shows the flower, the sky and the sober one with who gets each, and each centre chooses one for
  everyone or one for each stage, whatever its kind, without changing the card each reader sees on screen.
- The logo of the centre goes at the top right of every printed card, on a white plate.
- The stages come in the order of the school, not of the alphabet.

## 0.166.1 (2026-11-04)
- Print cards: when they are ready the page says so and offers them, instead of leaving the button turning.

## 0.166.0 (2026-11-04)
- Books out: what is already late has its own filter, the first of the row, in red when there is any.
- Print cards speaks of readers, not of a centre, where the library is not a school.

## 0.165.0 (2026-11-04)
- Print cards: with a stage chosen, the list of classes has only its classes, and with everyone they go under their stages;
  what is ready can be removed; and sheets can be chosen by model (Avery, Apli), to correct with the test sheet.
- Books out: by default the closest date first, with filters for today, three days, this week and this fortnight; the cards can
  be sorted by date, class or name, and what has little time left says so, in colour.

## 0.164.0 (2026-11-04)
- Print cards: teachers and staff have their own choice, and «Everyone» leaves out students without a class this year, who are
  printed one by one. A long word on a card, such as an e-mail, makes the text smaller instead of splitting.

## 0.163.0 (2026-11-04)
- «Print cards»: of everyone, a stage, a class or one reader (from their record), with their photo or their bichito and the
  design of their stage: the flower, the sky, or the sober one for adults and for whoever chose it. For a print shop, an
  actual-size PDF with each face on its page, with bleed and trim box; on pre-cut sheets of ten, with a test sheet to fit any
  brand; on plastic, one page per face at the exact size of the card, for a card printer; or on plain paper with marks to cut
  by. Many cards are prepared apart, and what is made deletes itself after seven days.
- The back of the card says the library's address and a few lines of its own.

## 0.162.0 (2026-11-04)
- «For you» on the card is no longer a form: the goal of the year is a ring with numbers to tap, the news a switch, and how
  the card looks three tiles. Each saves itself, with no «Save» button.

## 0.161.0 (2026-11-04)
- A search of the catalogues when adding a book shows how it goes, catalogue by catalogue, and what is found can be chosen
  while the others answer; writing it by hand is there from the first moment.
- A title or an author written wrong is searched as it may have been meant, by the words of the catalogue itself or,
  if they do not have it, by Wikipedia's spelling suggestion; the page says that it did so.
- Readers without a photo have a bichito, always the same for each one; not in the sober skins.
- The list of books out shows the reader's face, and with few readers, a card for each one instead of groups to open.
- The row of the call number no longer has two bands across it.

## 0.160.0 (2026-11-04)
- The menu is on every page of the staff, also on the list of libraries.
- «More» fits without scrolling on a computer, and is in the order people look for things: lending, readers, catalogue,
  labels and stocktake, what to review, reports and settings.
- The library's copies are now «Backups», in the settings, and say that they make themselves.

## 0.159.0 (2026-11-04)
- At the desk and in «Readers», a name typed wrong finds who it may have meant: written by ear, a letter out, two letters
  swapped, a foreign surname. They are offered to choose: a guess never opens a reader or lends by itself.
- At the desk, so does a title typed wrong.
- A list of readers that does not fit says that there are more.

## 0.158.0 (2026-11-04)
- Readers without a group are no longer one long list: teachers, staff and students each have their own place, with a
  search. Those who stopped coming have theirs too.
- A reader's record has a way back to the list it was opened from, and says which group a teacher tutors.
- «Change their details»: name and e-mail for someone without an account, kind of reader, group, and the group they tutor.
  What an account or the platform brings stays with them, and says so.
- «They no longer come» takes someone off the lists and keeps their history; «They are back» undoes it. Erasing is only for
  a person who asks for it.
- «See their whole history»: every book a reader took out, with its dates, year by year.
- «How do I…?» with the site's AI answers as soon as you ask, with no second button, and brings the screen and the guided
  help of the tasks it used.

## 0.157.0 (2026-11-04)
- The desk starts wherever the person in front of it starts. A book found by its title with no reader open is a button:
  a touch puts it in the desk's hand, the field asks who it is for, and the reader who opens takes it, by card or by name.
  The same for a book scanned before its card, and for several books before one card.
- A book's page has «Lend this book» for the staff: the desk opens with that book in its hand.
- While a book waits for its reader, words typed are a name and never a title. «Leave it» or Escape puts it down, and the
  list it came from comes back.
- With a reader open, the list of books says who takes the one you touch.

## 0.156.0 (2026-11-04)
- A record or a copy can be corrected after it was catalogued, from its own page: title, authors, subjects, ISBN and
  the rest; a copy's code, spine label, location and notes. What was entered by mistake and has nothing behind it can
  be taken out; what was lent stays, and is withdrawn as always.
- The desk without a network in two tabs no longer passes each scan twice, which brought back what it had just lent; and
  a kept scan whose answer was lost is not passed again.

## 0.155.0 (2026-11-03)
- MySQL 8: the columns `source.system` and `bankset.groups`, words it reserves, are now `origin` and `groupnames`. Older
  copies still come back.
- PostgreSQL: the desk and the people who keep a library find names with accents by a word typed without them.
- Subjects and short notices no longer say which book or how much: a phone's locked screen and the push service do not
  need to know; the title stays in the message.
- The reader of EPUB books lets a book ask nothing of other sites, which would tell them who is reading it.

## 0.154.1 (2026-11-03)
- Erasing a reader is final everywhere: no list, program or cohort that still has them brings them back the next night
  (a fingerprint stays, as with Abies); the staff no longer finds their record, card or desk panel; and what their
  account left in the library (their place in a book, the phone's code, their choices) goes too.
- A centre that fails at night no longer stops the readers or the notices of the others, and a step that fails no longer
  stops the rest.
- A copy that cannot be written whole, on a full disk, is an error and not a short copy that looks fine.
- With centres apart, a centre that loses its category closes instead of opening to the whole site.

## 0.154.0 (2026-11-03)
- A copy comes back whole: a text ending in a backslash no longer swallows its row and the next, and the screen says when a
  copy has fewer rows than it declares. Older copies still read.
- A source that stops bringing many readers at once no longer leaves them as gone: a deleted cohort, an empty answer or
  a file for another school is held back and whoever manages the centre is told. A OneRoster zip without a manifest
  no longer counts as a whole export.
- Loading a list, or the night of a management program, keeps the account a reader was matched with by hand.
- A cell in quotes with several lines no longer makes rubbish records from a CSV.
- «I no longer want it» is gone from a book the reader has on loan, where it led to an error.

## 0.153.2 (2026-11-03)
- Loans written on paper find the readers with an account by the account's name (the library keeps no copy of it);
  before, only those without an account were found. The same name once with an account and once without (a reader from
  an old program not matched yet) is the one with the account; matching later brings the two together, loans and all.

## 0.153.1 (2026-11-03)
- At the desk with no reader open, a title also brings up the books on the shelf (with their author, how many and their
  call number), saying that lending one needs the reader first; before, it only looked among the loans out and, with
  none, answered «I do not know». When nothing has the words, it says no reader and no book has them.
- Only the books with every word in their title come up when there are any; a word only in the author or a subject
  counts when no title has them all. When more books have them than fit, it says how many more, to add a word.
- `cli/centre.php --match-sure`: links the readers without an account that are sure to theirs, as «Readers» does with
  one touch, and says how many are left doubtful and how many have no account here.

## 0.153.0 (2026-11-03)
- A book without a label, by its title, at the desk: with the reader open, a word of the title or the author brings up
  the books on the shelf (those with it in their title first, each with its author, how many are on the shelf and its
  call number) and a touch lends one; with no reader open, it brings up the loans out of it and who has them, and a
  touch brings one back. A reader's name still opens the reader.
- «Return» next to each book in the reader's list at the desk.
- A record with several copies lent by its title marks the loan as not knowing which copy went: the copy that comes
  back is taken as the one (as with the loans written on paper).
- «How do I…?»: lending or taking back a book without a label.

## 0.152.0 (2026-11-03)
- Loans written on paper while the desk could not be used (`cli/paper_loans.php`): a list saved as CSV, with who, what,
  what happened and when, is first read and explained line by line (its reader and its book, sure, close with a slip of
  a letter, or doubtful with the candidates), and with `--apply` done in date order as the desk would have done it on
  each day: loans, returns and renewals; a renewal of a loan that came before lends it from that day, and a return of
  one that is not here only says so. Running it again repeats nothing; `--due` gives every loan of the list one day.
- A copy nobody wrote down is the first free one of its record that can be lent. When another copy of that record comes
  back with the reader open, the desk takes it as the one that went; when someone else takes the one it took, the loan
  moves to another free copy; with no reader open, the desk says who has it.
- «New cards start at code», in the centre's settings (and `cli/centre.php --first-card-code`): like copies, for when
  an old program kept giving cards after the export that came; new cards from cohorts, lists, LTI, another library or
  a lost card start after its last one.
- An old export brought in as returned lists the loans it had out whose copy has not moved here since, to look on the
  shelf whether they really came back.

## 0.151.0 (2026-11-03)
- Importing a newer Abies export over a library already in use keeps what the library did since: a record, copy, reader
  or loan changed here is left as it is (the rest is brought up to date), one deleted here does not come back, and a
  reader erased at their request does not come back either (only a fingerprint of their Abies id stays).
- A book added here before the export came is not repeated: a copy made with the code of its Abies label is that copy,
  with its record, when both are the same book; and a record made here with the ISBN of one of the export is that
  record. A copy made here whose new code Abies had printed on another book gets a new code, and the import lists it to
  print its label: the printed label wins.
- Updating a record from Abies keeps what was added to it here, such as its cover.
- The upgrade takes what came from Abies, as it is now, as the starting point.
- «New copies start at code», in the centre's settings (and `cli/centre.php --first-copy-code`): when an old program
  kept printing labels after the copy that came, new copy codes start after its last label, in the desk, in imports
  and for the copies the Abies import relabels.

## 0.150.3 (2026-11-03)
- Matching readers who came without an account (from Abies, from a list) compares their group only when it is this
  year's: an export from an earlier year brings last year's groups, and the same name in this year's class is sure, not
  a doubt. A different group this year is still worth a look.

## 0.150.2 (2026-11-03)
- Readers from a cohort for a whole stage (not a class) take their class from the group of a chosen course, keeping the
  kind and stage of the cohort; before, the cohort hid the course's group and they had no class.
- `cli/centre.php --cohorts`: a cohort ending in * is every cohort whose id number or name starts so («ESO *»), each with
  its own stage guessed from its name; for a site with a cohort for each class.

## 0.150.1 (2026-11-03)
- In a site with one centre, «Library manager» (the librarian's work and the centre's settings: rules, readers,
  characters, matching readers) can be given on the site, the centre's place; with a category for each centre it is
  still given only in that category. Turning that setting on or off moves it.

## 0.150.0 (2026-11-03)
- A centre open only to its staff while it gets ready now shows nothing to anybody else: no «Library» in the site's
  menu, no «Readings» in the courses, no «Library» in the app's menu (its init says disabled), and no message from the
  library while every centre of the site is getting ready, except to their staff.
- Importing an Abies export: `--closed` makes the new centre open only to its staff from the start, and `--as-returned`
  takes an old export as history: its copies come in on the shelf, its loans still out come in returned on the day of
  the export, and copies already here keep what the desk has done with them.
- `cli/centre.php`: a centre got ready from the console, as its screens would: its names, open or only for its staff, its
  two characters (and the herald's own drawings) and the cohorts its readers come from; `--list` says which centres
  there are.

## 0.149.0 (2026-11-02)
- «How do I…?» links the printed guides on Ayvurenda's website (starting the library, the desk and the readers), in the
  reader's language.
- What making the guides showed, in a library without classes: late books are listed by reader, and each row now says
  the book and when it was due (it said the reader's name again); the readers' page offers «Print cards» for everyone; the
  sheet of cards is «Readers' cards», not «Cards of No group»; a reader's profile says «Reader», not «Student».

## 0.148.0 (2026-11-02)
- Readers from the OneRoster REST API of a management program (1.1 or 1.2), from «Where the readers come from»: OAuth 2
  with client credentials, or the OAuth 1.0a signature of 1.1; it is tried first without changing anything, the centre
  chooses its school and whether the families come, and after the first time by hand the night keeps them up to date,
  with the tutor of each homeroom. They are the same readers as the OneRoster zip brings.
- On Moodle 4.5, the request of a token to Cifra Educación joined its fields with «&amp;» and the program read it wrong;
  now with «&».
- Late books say «1 día» («1 day»), not «1 días».

## 0.147.0 (2026-11-02)
- ONIX for Books, the format of publishers and distributors (and of services such as DILVE): a message in 3.0 or 2.1,
  with reference names or the short tags of 3.0, comes in as records without copies, which are added at the desk; loading
  it again repeats nothing. The import screen says it.

## 0.146.0 (2026-11-02)
- COinS on each book's page: Zotero, Mendeley and the other reference managers take the book with one click from the
  browser.
- A course's reading list also in CSL-JSON, the format of the Citation Style Language that the citation styles read.

## 0.145.0 (2026-11-02)
- The OAI-PMH repository also gives its records in MARC 21 (MARCXML, prefix «marc21»), as the daily copy writes them, with
  where each digital form is read (856); valid against MARC21slim.xsd. A resumption token goes on in the format asked.

## 0.144.0 (2026-11-02)
- A catalogue from the exports of LibraryThing, Goodreads, Calibre and Libib: their columns are understood (the ISBN-13
  before the ISBN-10, the pages, the year, the tags, Calibre's description in HTML), their ISBNs come clean («="…"», «[…]»,
  several in a cell), their authors are split as each writes them («Mary GrandPré, Jim Kay», «A & B»), more authors go
  with the rest, and what Goodreads keeps on the «to-read» shelf does not come in.

## 0.143.0 (2026-11-02)
- UNIMARC, the format of France, Portugal, Italy and much of Europe: its records are read as well as MARC 21's, in an
  import from any program (with its holdings in 995, already read) and from the catalogues.
- Two more catalogues for adding books: the Bibliothèque nationale de France (by SRU, in UNIMARC) and Portugal's National
  Library (PORBASE, through the bridge, which gets a route for ISO 5426). Portugal and Brazil are now two regions.
- A character the bridge could not map comes as itself, not as «<U+00BA>».

## 0.142.1 (2026-11-02)
- The copies made by hand are kept apart (the last two) and never push out the last seven nightly ones; the list says
  which are made by hand.
- The lines for the library's computer fetch both copies already, the day's and the week's of the files, and the Linux
  one is ready to paste into «crontab -e»; the card says why the copy is encrypted, with nothing technical to type.
- «Copias» stops offering «Connect Google Drive» once Drive is one of its places (seen in the real test in dev, where a
  copy reached Drive and opened with the centre's key).

## 0.142.0 (2026-11-02)
- Erasing a reader at their request, for whoever manages the centre, from the reader's record: everything that says who
  they are goes (name, card, e-mail, notes, reviews with their drawings and voices, their voices, reservations, fines,
  asks, the invitations to their family and the key their origin knew them by); the loans stay as anonymous numbers. On a
  site shared by centres their account goes too, when it has nothing else there. Not while they have books out. Moodle's
  privacy requests do the same with each reader of an account.

## 0.141.0 (2026-11-02)
- The world's libraries for adding books: besides the Library of Congress, Open Library and Spain's, the national
  libraries and shared catalogues of Germany, Switzerland, Austria, Sweden, Finland, Norway, Denmark, Italy, Quebec,
  Wales, Victoria (Australia), Brazil, Colombia, Peru and Catalonia, each asked in its own way and tried with real
  questions. Those that only speak Z39.50 go through the bridge, which gets a route for catalogues that send UTF-8
  without saying so.
- Each centre chooses them in «Centre settings»; without a choice, the ones of its time zone go first (a centre in Berlin
  asks the German ones), or of the language of the site.
- A search by words stops asking after 20 seconds: a catalogue that does not answer must not hold the desk.

## 0.140.0 (2026-11-02)
- A centre signed up from the command line, for the hosted service: `cli/add_entity.php` creates the centre, its first
  library and its category, sets its plan, gives whoever runs it an account and the role «Library manager» in that
  category, mails them how to get in, and notes when the centre accepted the terms and the privacy policy. Run again,
  it changes nothing that is already there.
- The role «Library manager» (`ayvurendamanager`): the librarian's permissions and the centre's settings, only in the
  category of a centre.

## 0.139.0 (2026-11-02)
- A book with no ISBN, by its words: type its title or its author in «Add books» and what other catalogues have comes up,
  the ones with most of the words first; choose the one in your hands and it comes in as that catalogue described it,
  or write it by hand. Each catalogue is asked in its own way (CQL «all», «=», or Alma's index).
- Two more catalogues: REBIUN (Spain's university libraries, through the Z39.50 bridge, in MARC-8) and the libraries of
  Spain's research council (CSIC, by SRU). A catalogue in MARC-8 sends each accent apart from its letter: it comes in
  as one character.

## 0.138.0 (2026-11-02)
- Accessible formats. A reader can be marked as needing them, with no reason given; an item of the digital collection
  can be an accessible format, seen only by those readers and never public. Any reader asks for a book in another format
  (large print, audio, braille, a text that can be read aloud) from its page; the staff see the asks in «Otros formatos»,
  with a card on the desk, answer with an accessible item or some other way, and the reader is told.

## 0.137.1 (2026-11-02)
- Copies in Google Drive with no client of the library's own: a Moodle that already has Google's OAuth 2 service uses it.
  When that project has the Drive API off, «Copias» says so.

## 0.137.0 (2026-11-02)
- Links that open from home: in a centre's settings, the prefix of its proxy and its link resolver. A link marked as
  subscribed goes through the proxy, a pasted DOI becomes its permanent address, and a book's page asks the resolver for
  its full text. This is no proxy: whoever opens it goes through the institution's, with its own login.
- To pass a book to a class: on its page, for whoever teaches, in Google Classroom or Microsoft Teams.

## 0.136.0 (2026-11-02)
- Taking a centre away with `--accounts` also takes its staff, who have no reader's card, when they have nothing else.
- The copies out of the server can also go to a centre's own Google Drive: it connects its account from «Copies», with the
  narrowest permission Google has (only what this creates), and they go there encrypted with its own key. Taking the place
  away gives the permission back; what is already there stays. Needs the site's client at Google, in the plugin's settings.

## 0.135.0 (2026-11-02)
- Each centre has its own time zone, in its settings; none chosen, the site's. Its day ends at its own midnight: due dates,
  what is late, days without lending, reservations, notices, the school year, reports and the dates shown for them all go
  by it, for whoever looks. A book back at 18:30 on its last day in Bogotá is not late because it is tomorrow in Madrid.
  Moments, such as when a copy was made or who did what, stay in the zone of whoever reads them.

## 0.134.2 (2026-11-02)
- A new centre on a shared site starts without the app, as the free plan does; a plan saved before this has none either.

## 0.134.1 (2026-11-02)
- The app of the hosted service (Oksigenia Aty) as a feature of a plan: in the paid ones, not in the free one. A plugin in
  someone's own Moodle, and the official app of Moodle, do not ask.

## 0.134.0 (2026-11-02)
- SIP2, for self-loan machines, return boxes and RFID pads: a small bridge (`sip2-bridge/`, Python with nothing added, GPL)
  that runs in the library, speaks SIP2 to the machines on its network and HTTPS to the library, with the address of a
  self-loan post. A machine is one more post: the same rules, limits and record, and what it does can be undone.
- It stands in a public room, so it says what a post says: the first name and what was just taken; never someone's
  books, whether they are late or how much they owe.
- A copy that failed also reaches the phones of whoever manages the centre.
- The block's way to the desk for whoever keeps a library, lost when keepers had to be readers (block 0.1.6).

What an independent review found in this and in 0.133.0:

- A member of staff could write down an amount for a reader of another centre, by the address; that reader's message and
  the report then failed. Now such a reader is nobody on that page, and the amount is refused.
- «Paid» or «Forgive» on the line of one book closed everything the reader owed: now that book's, and the panel closes all.
- Renewing a late book from home or at a machine moved its date and left nothing: not where a late book costs something.
- Whoever only keeps a library saw how much someone owed and until when: now only where to send them.
- Paid amounts survive undoing a return; a reader who owes is not melted away when matching; a blank amount keeps what it
  was; the date of a stretch of days is the site's for everyone; privacy exports everything, with no cap.

## 0.133.0 (2026-11-02)
- If a book comes back late, a centre can now choose what it leaves, in the lending rules: only the warning (still the
  default), days without lending, or a fine, with days of grace, a ceiling, its currency, and which stages and adults it
  applies to. It is worked out as the book comes back.
- The desk says what the book left, stops before lending to whoever is waiting or owes (the librarian can always lend
  anyway), and lifts the days, takes the payment or forgives it from there. Payment is only written down: nothing is
  charged online.
- The reader's record lists it all and writes down other amounts, such as for a lost book. «Fines and suspensions», a new
  report, shows who waits and who owes, the sums of the year, what was closed (which can be undone) and a CSV.
- The reader is told by message, sees it on their card, in the app and in the block, and cannot reserve or renew from
  home meanwhile; the notice of a late book warns first. A self-loan post sends them to the desk.
- Never at a textbook bank, nor for a library that borrows. Goes in the daily copy, recovery and merging of readers, and
  the privacy provider exports and deletes it.
- A guided help with its voices; the desk's own help no longer promises there are no fines.
- What waits for the staff and a voice received also reach the staff's phones.

## 0.132.1 (2026-11-02)

- On a child's page in Aty, the row of a book of an older child opens nothing: the page would be the adult's library, not the child's.

## 0.132.0 (2026-11-02)

- Lending between libraries of different centres, whatever program the other one uses: what is asked of another library for a reader here, from the asking to going home, with a notice when it arrives; and copies lent to another library, which go out and come back at the desk. What a reader asks to buy can be asked of another library instead.

## 0.131.2 (2026-11-02)

- The entrance of a site that is only a library says where to get Oksigenia Aty, once the site's setup link points to it in Google Play.

## 0.131.1 (2026-11-02)

- In Oksigenia Aty, a family sees a child's library on the child's page: what they have out, what is set aside, and until when. To see, not to act. From the age the sky replaces the flower, there is a book and its date, not which one.

## 0.131.0 (2026-11-02)

- Purchases: what readers ask for and what the staff see missing (required readings with too few copies, books with a queue), what is decided, the order for each vendor as a sheet to send or print, what arrives, and what it costs against the year's budget. When a book enters the catalogue, whoever asked for it is told.

## 0.130.0 (2026-11-02)

- Self-loan: a tablet or computer in the library where readers lend to themselves with their card and give books back, opened by its own address, with no account left open on it. It only says the person's first name and what they have just taken. Whoever runs the library chooses who may use it and goes over what was done, undoing what is wrong. If its address gets out, it holds back what no queue would do and stops until someone looks, and everything it did in the last 24 hours can be undone at once.
- A lost card: another one from the reader's page, and the old code stops working everywhere.

## 0.129.1 (2026-11-02)

- The forms of a stocktake and of a box of books, with the label above the field.

## 0.129.0 (2026-11-02)

- What repeated across the staff's screens: an intro of a line or two, what they do and not how the system works; one main button (How do I…?, libraries, matching); a list with nothing in it says so in its place, with the next step where there is one; the tabs of the reports look like tabs. In Spanish, «enlazar» instead of «casar». The groups of the site are called that, with «cohorts» in brackets where it matters.

## 0.128.0 (2026-11-02)

- Guided helps for adding material, repeated readers and tutors, with their voices (art 0.1.38). «How do I…?» also answers about material, repeated readers and other platforms.

## 0.127.2 (2026-11-02)

- A centre's own character stands on the floor of the visit, at the size of a person, instead of perching where the hummingbird perches (`standing` in each scene; art 0.1.37).
- A twice-written entity keeps its characters in the app's templates.

## 0.127.1 (2026-11-02)

- What looks like an entity and is none no longer stops a page of the app from compiling.
- The fields of a stacked form keep their height (material, platforms). The label samples, also in dark mode. The sheet of labels fits a phone. Thousands in the count of a search.

## 0.127.0 (2026-11-02)

The findings of an independent security review:

- A practice library belongs to one person and nothing of the site comes through it: no accounts, cohorts or courses of other centres.
- Nothing from a catalogue can run in the app: numeric entities no longer get past the guard, and the last search is data, not part
  of an expression.
- One centre cannot leave the others without their nightly copy, nor hold the night with a server that does not answer.
- A reminder, a course fragment or a course reserve only reaches one's own centre.
- The daily copy, for whoever manages the centre (it holds everyone's addresses), and without where the copies go. It can be
  downloaded from a shared site again.
- The passwords and keys of the centres, encrypted in the settings. Copies have their own list of internal hosts (`offsitehosts`).
- Who keeps a library stops doing so when they stop being a reader. Removing a centre can take its lonely accounts with it.

## 0.126.0 (2026-11-02)

- The screens that were still forms, redone: labels (what, of which, and the first free one tapped on a map of the sheet), material (the
  few things first), repeated readers (a card for each person, twenty at a time), tutors (by stage), platforms (one touch first) and
  reports (the total and its file first, two columns on a wide screen).

## 0.125.0 (2026-11-02)

- What a library reads, in the words of its kind: without classes, no challenges, no tutors, no «My class», no class wishes; without
  courses, no reading lists, no school programs among the sources, and «year» instead of «academic year». Readers, not students.
- Each person has their own practice library, in their language and with the skin of the library they work in: in a shared site, two
  people at a time no longer change it under each other. The guides change their words where a skin needs it, with their own voices.
- A big library, measured with 110,000 copies and 10,000 readers: the nightly sync asks once instead of once per reader, a page of
  labels stops at 2,000 and printed cards go 200 at a time.

## 0.124.0 (2026-11-02)

- The copy of a centre's files, apart from the daily one: covers, the digital collection, the drawings of the reviews, the pictures of
  the libraries and its own characters, in folders anyone can open. Made on request; every week if the copies leave the server.
  Recorded voices never leave the server.
- A centre comes back whole: after its daily copy, its files (`recover.php`, or `cli/recover_files.php` if too big to upload).
- The daily copy also has the reviews, the challenges, what the classes asked for, what was read in the room and the sets of the
  textbook bank. The list of readers for Koha without a byte-order mark.
- Her thinking pose moves the herald too.

## 0.123.0 (2026-11-02)

- Who did what: each delicate action (joining readers, taking a copy, changing a rule, publishing a voice, a plan, a skin…) tells
  Moodle's logs who did it, with ids and codes only; loans and searches are not told.
- «Who did what», a page for whoever manages a library, who in a shared site has no way into the site's logs.

## 0.122.0 (2026-11-02)

- The memory works with the staff too: when cataloguing a book, she says if a class asked for it or what else there is by its author;
  and the year's report opens with the year as she tells it.
- What she has to tell a reader today, also in the block and in the app.

## 0.121.1 (2026-11-02)

- The plans of the hosted service with one choice in a centre's settings: Free, Small and Medium, each with its limits and what it
  brings; and the copy off the server goes with the plan (the daily copy to download, always).

## 0.121.0 (2026-11-02)

- A textbook bank's handing out and collecting («Handing out and collecting», from its desk and its sets). Collecting: how many books
  are still out, by class; a deadline; reminders to whoever has books, with their list, and to the tutors, with who has them; and
  what is lost, without charging anything, which can turn up again. Handing out: who is waiting, what each set is short of, the groups
  that do not match this year's, and each class with its list and its contracts, all of a class at once.
- Its guided help, on a practice bank.

## 0.120.2 (2026-11-01)

- The mobile library has its own hall, a bookmobile in a square, with its board saying «Today in the square», and its picture on the
  notices by e-mail.

## 0.120.1 (2026-11-01)

- The button of a notice by e-mail opens the book or the library in Aty for whoever has it on their phone, instead of leaving them in
  the browser, asked to sign in. Without Aty, the same page as before.

## 0.120.0 (2026-11-01)

- A book's page in the app, redone like the notice by e-mail: its cover or a drawn one, the library's name, how it stands at a glance,
  the date large, and the place and call number only when they are of use. In the official app and in Aty, in light and in dark.

## 0.119.2 (2026-11-01)

- In the app, a page of a library where the person has no card says so, instead of saying they read at no library on the site.
- Notices by e-mail hold up in the programs that darken a mail by themselves (the paper turned mustard in Proton on Android), and
  their button lands on an entry in the language of the notice.

## 0.119.1 (2026-11-01)

- Bringing in a catalogue is no longer news: the weekly message counts what an import brings only if it says it was acquired that
  week.
- By e-mail: the pictures cut closer to what they show; the adults of a public library, a university or a company read the library's
  signature, without a character, unless the centre asks the herald to sign; and on a phone the new books go two by two.

## 0.119.0 (2026-11-01)

- Notices by e-mail have a design: the library's picture and colour, what it is about, the book as a slip with its date, one large
  button and who signs, in the tone of each skin, on a phone and in dark mode; readable with images off. All of them: a book set
  aside, late books, new books, the tutor's summary, what awaits the staff, a copy that failed, a voice received and the app link.
- A library's sentences no longer need an article before its name: «Where: Monte Claro Library».
- A notice reaches each person in their own language and at their own hour, wherever the task runs.
- In the app, a notice opens the book or the library inside it, in the official app and in Aty.

## 0.118.0 (2026-11-01)

- Recover a copy. Whoever runs the site opens an encrypted copy with its key, sees what it holds and brings it back as a new
  centre, with its copies, readers, loans and voices, without touching anything already on the site.
- The copies off the server also go to any store that speaks S3 (AWS, Backblaze, Wasabi, MinIO), tested when added and with the
  same days, weeks and months to keep.
- Remove a centre entirely, by command line (`cli/remove_entity.php --entity=ID`, which first says what it would remove).
- The printed card, as drawn by the art direction: ten cards on an A4 sheet, with the reader's drawing, the code in both QR and
  Code 39, cutting guides, and a ruler to check the printer did not resize it.

## 0.117.1 (2026-11-01)

- What a reader waits for reaches their phone: late books, a book set aside, the new books, and to a tutor their group's late
  books also go through the mobile app's notifications, in the official app and in Aty.

## 0.117.0 (2026-11-01)

- Any old MARC file reads right. MARC-8 and ISO 5426, with their accents before the letter, and Windows-1252, told from how the
  file writes its accents, whatever its leader says; when a file is not in Unicode, the import screen lets you read it another way
  and see the books change.
- The copies of other programs: fields 949 (Sierra, OCLC, suppliers), 999 (Symphony) and 995 (UNIMARC, Koha, PMB), and the accession
  number of 952 and 995.
- Readers from Koha's list of patrons are recognised.
- To go to Koha: the daily copy has a koha folder, with the catalogue with its copies in 952, the readers in its list of patrons
  and the books out in a file of its offline circulation, and a note of how to load them.

## 0.116.0 (2026-11-01)

- A site that is only a library («This site is only a library», off by default): its own entry instead of the site's, with a
  picture of a library, sign-in and the libraries open to everyone; the front page, dashboard and course list lead to the
  library; the pages left of the platform wear its colours, and the library's header ends in signing out. Whoever runs the
  site still reaches everything.
- On a site with centres apart, a list no longer takes the account of someone of another centre, nor says whether there is
  one; and the staff of a centre can send a reader the app, making their account from the e-mail the library has.
- A window that is closed offers the way in, on such a site.

## 0.115.0 (2026-11-01)

- The readers, as one route. «Readers» says how many there are, finds one by name or card, and shows the groups by stage,
  with what needs a look at the top; a library without classes lists its readers a page at a time.
- «Where the readers come from» is a set of cards, one for each place (a list, OneRoster, Moodle cohorts and courses, a
  learning platform, a management program, Abies Web), each saying whether it is in use. Cohorts and courses are chosen in
  their own sheets, where one row is one line.
- A list and a OneRoster zip are dropped like a catalogue, and show what they bring before anyone arrives: figures, the first
  people and the columns they were taken for. The connector shows its state first; the connection goes in a sheet, and «every
  night» is a choice of its own.
- Guided helps for the readers and for loading a list. On the app's home, staff without a reader's card get their way in too.

## 0.114.0 (2026-11-01)

- Guided helps for the screens already remade: importing a catalogue, weeding, loan rules, late books, holds and voices, in
  English and Spanish. Each plays over the real screen in the practice library, with made-up data: importing takes in an
  example file of eight books and goes through to the end, weeding makes its record, a new rule is saved. Nobody is told
  anything and the library's own data is not touched. Each screen offers its help, and «How do I…?» leads to it too.
- The practice library sets out what each help needs only for that help, and takes it all away when it is made again:
  also the voices, tutors and import traces it had been leaving behind.
- A tick with a long sentence keeps the sentence beside it, instead of the tick alone on the line above.

## 0.113.0 (2026-11-01)

- The import screen, remade. A file is seen as the books it brings: the first six stand on their shelf, each with its
  drawn cover, its spine label, its author and its place, beside the figures of the whole file (records, copies, places,
  copies without a code). It is the same for MARC, a spreadsheet or Abies Web.
- A spreadsheet's columns are recognised and folded away: one line says so, and a window lets you correct them with what
  each one says, and then the shelf is drawn again with the new columns. Without a column for the title nothing can be
  imported, and the window opens by itself.
- A file is dropped on a zone or chosen, and that is already the next step. The button says what it will do («Import
  1,284 records»), and the page warns before importing if the file may not fit the plan.
- After importing, what entered in figures, Memoria's words (a portrait and a signature at a university) and what comes
  next: the catalogue, the labels, the readers and the desk.

## 0.112.1 (2026-11-01)

- Importing again repeats nothing, also for copies with no code: those the record already has count for them, and one
  more in the file is one more. Until now each pass added them again.
- The import screen and its help begin with any program; Abies Web comes last.

## 0.112.0 (2026-11-01)

- Two more skins. «Company», for a company's library or documentation centre: adult readers, plain, the system's font, a
  portrait and a signature, no games by default, each reader told about their own books, and the words of a university.
  «Mobile library», for a bookmobile or any library that travels: readers of every age, no classes, like a public library,
  whose hall and rooms it visits until the art brings its own. The public library's skin now says «or foundation».

## 0.111.0 (2026-11-01)

- The reader's card alone in the app, to show at the desk (`mobile_card_view`): their name, the library, the QR and the
  barcode, big and on white in light and in dark; on Aty's home, a tile («Your card») beside the library's.
- The look of a reader's library gives Aty its picture as `picture`, not as `logo`: it is a photo or a drawing, and it
  fills the top of the header.
- The library's menu entry no longer asks to be a tab: Aty cannot open a plugin's page from one.

## 0.110.1 (2026-11-01)

From Aty's test on a phone:

- The look of a reader's library in Aty only where the site is shared and keeps its centres apart: on a school's own
  site, a student with a card saw the app with the library's name and colour instead of the school's.
- A late book on Aty's home says so in words, not only by its colour.
- The library's icon in the app is `fas-book-open-reader`: Aty's «My courses» has `fas-book-open`, right beside it.

## 0.110.0 (2026-11-01)

- The home of Oksigenia Aty shows the library: a reader's books out and what is set aside for them, the late ones first,
  and the way in (`local_oksigeniaayvurenda_oksigeniaaty_home_cards()`); in Aty, the library is a tab where it fits.
  Textbooks lent for the year stay out.
- In the app's pages, an at sign in a text is as harmless as a brace: Angular also reads «@let» and «@if» there.
- A book's record opens at the top, with its title: the short shelf «Beside it on the shelf» took the page to the end and
  took the focus with it.
- In English, a book that can be borrowed is «available», not «free», which read like a price.

## 0.109.0 (2026-11-01)

- A fourth skin, «Public library», for a town's, a foundation's or an association's library: readers of every age,
  no classes nor tutors (late books are told to each reader, as at a university), the characters standing whole for
  the children, no voices nor sound until the centre turns them on, and the words of a secondary school where it has
  none of its own. Its visit starts in its own hall (`publica`) when the art package brings one, and until then in the
  school's, whose rooms are its children's room.

## 0.108.0 (2026-11-01)

- A notice for every page of the library, set by whoever runs the site («Notice in the library», `sitenotice`): one
  line at the top, for everyone, for an open demo that starts again each night or a stop for maintenance.

## 0.107.0 (2026-11-01)

From Enrique's look at the redone screens:

- «How do I…?» sits at the end of each page, on the right of the content, instead of floating over cards and buttons.
- Weeding: the bar of the marked ones shows only once something is marked, fixed at the bottom and centred; without
  scripts it stays at the end of the list.
- Late books: the groups (and the books in each) go from the most late to the least, also when a span filters; red past
  30 days and orange from 15, in the spans, the groups and each book; the button says whom it tells («Tell Elena Duna
  Montes»), and a group without a tutor offers to set one. At a university or academy the readers are adults: each one
  with their books and «Tell …» to them (`notices::remind()`, the same message as the night's), no classes, no tutors.

## 0.106.0 (2026-11-01)

- Reservations and voices redone from the design reviews (the same problem: the settings took the page): first what
  waits, as cards with the cover, the reader and the date (a copy set aside marked on its edge); when nothing waits, the
  herald (reservations) or the memory (voices) says so; the settings read in one line («Online reservations: no · a book
  set aside is kept 5 days · 3 at a time per reader…») and changed in a sheet, the stages as chips with «All the
  stages» explicit, and «teaching days» instead of «school days».

## 0.105.0 (2026-11-01)

- Late books redone from the design reviews: tabs «Late | Lent» instead of a chip; three cards by span (more than 30
  days, 15 to 30, under 15) that filter; the groups folded, in the order of the stages, each saying how many readers
  and the oldest delay (in red past 30 days); each name leads to the reader's record, with the cover beside the title,
  and the red only past two weeks; for each group, its list alone to print, and «Tell the tutor now»
  (`notices::tell_tutor()`, the same message as the week's). The page was 8,929 px long.

## 0.104.0 (2026-11-01)

The first screens redone from the design reviews of 31 October:

- Weeding: a card for each reason with its count (lost, torn or worn, by age, not lent for years), starting with the
  small ones; the copies of the chosen reason by place, with their cover, fifty to a page; «Mark those of this page»
  says so; a bar that stays at the bottom counts the marked ones and opens a sheet with the reason for the record; the
  records in their own tab; the filters in a «Refine» sheet. The page was 28,875 px long.
- Loan rules: each rule a card read as a sentence («They take up to 1 at a time, for 7 days, and can renew 6 times, 7
  days more each time»), «Not lent: read in the library» and «No limit» instead of zeros, changed in its own sheet, one
  at a time; a new rule from the top; a new one that repeats another says so instead of replacing it.
- Numbers with the thousands separator of the page's language (`screens::number()`), starting with weeding.

## 0.103.1 (2026-10-31)

- The sky's medallion on the card wears a thin ring of cream paper and a soft shadow, so that it stands apart from the
  card's green as the flower does on its plate (Gaudí's review).

## 0.103.0 (2026-10-31)

- The card's drawing by Gaudí, in cut paper, when the art package brings it (`pix/carne/flor/` and `pix/carne/cielo/`,
  from the art package 0.1.28): the base of the stage (seed, sprout, bud, flower; new, crescent, half and full moon)
  and each mark of the four paths in its box, lit up to its level and waiting beyond, as `carne.json` lays them out.
  Up to 140 px the sky takes the marks drawn thicker; the sober sky leaves out the waiting ones. On the card, the home,
  the reader's record and the class garden; without the package, the drawn SVG of before.
- A hint softens its words by their colour, not by opacity, so a «Listen» button inside it keeps its contrast.

## 0.102.0 (2026-10-31)

The herald at work, as she was designed: she tells what happens.

- On the home, the book of the week: the one most readers took from the library in the last seven days, from two
  readers on (a count, never who), with its cover.
- At the desk, a word of hers in a corner as each book comes home («Home again!», «Someone was waiting for this
  one!»): it never waits for anything, goes by itself in four seconds and is not read aloud twice. Not in a textbook
  bank, nor at a university or academy, where the characters are a portrait and a signature.
- The reminder of a late book to a young reader (the card with the flower) comes in her words, by their name: the
  books miss their shelf.

## 0.101.0 (2026-10-31)

The memory at work (`classes/local/memory.php`), as she was designed: she has read everything and remembers everything.

- On the home, to each reader, one thing a day: the book they left half read (still free on its shelf), one like a book
  they loved or wanted more of (by its author, else its topic, else its corner), what they were looking for last time
  (for a month, if they took nothing since), or one more of the corner they read most. With the book's cover and a way
  to it, in the tone of the centre's skin.
- On a record, «Also taken»: what else its readers took, said by the memory, only when at least three readers took
  the book and each one by two of them, so that nobody is singled out. Never the textbooks of a bank.
- The last search is kept as a preference of the person (declared in privacy, exported and deleted with them).

## 0.100.0 (2026-10-31)

- Copies out of the server, in «Copies», for whoever manages the centre: each night's copy, encrypted with the centre's
  public key in the open format of age (X25519 and ChaCha20-Poly1305; checked against the age program both ways), goes
  to the centre's WebDAV folders (Nextcloud, ownCloud, a NAS…), tried as they are added; each keeps the copies of the
  last days, one a week and one a month, as the centre chooses. The library's own computer can fetch the newest copy
  with a link of its own (`pull.php`), with lines ready for Linux, macOS and Windows. The server keeps only the public
  key: the secret one is shown once (or the centre brings its own). Two nights that fail, and whoever manages the
  centre gets a notice. «How do I…?» explains it.
- The page of copies: shorter, the state out of the server first, quiet buttons.

## 0.99.0 (2026-10-31)

The rest of the review of the whole project:

- Names with «&» or quotes come out once escaped, not twice, in the header, the tab, the cards, OAI-PMH and OPDS.
- Undoing an addition only takes back what was added today, and never a record that anything else hangs from (digital
  items, reviews, holds, course readings) nor a copy on reserve.
- Uploaded zips: a OneRoster file or a catalogue that says it is far too big is not read, and a catalogue is copied out
  of its zip by streams; an Abies export with a document type is refused.
- LTI: a deep link only goes back to a web address; two entries at once never make two readers of one account.
- Accessibility: the class challenge is saved with its button (choosing with the arrows no longer saves halfway);
  what a live search found is announced; the focus stays in the camera; the button that removes a bookmark says so;
  groups of buttons without arrow keys are groups, not toolbars.
- An administrator logged in as someone else sees it on every page of the library.
- The quotes and list separators of the classic card follow the language of the page; a title with «$&» in it shows as
  it is.
- Faster with many centres and big libraries: the site's menu asks the cheap question first and stops at the first
  centre; each centre's category is kept for the request; the corners' counts are kept a few minutes; the desk and the
  stocktake find a code by its index before trying without minding capitals; new indexes for loans, copies and groups.
- The dashboard block 0.1.4 looks at each centre's permissions and gives a library's desk to whoever keeps it.

## 0.98.0 (2026-10-31)

- `\local_oksigeniaayvurenda\api::new_books($since, $limit, $entityid)`: the books that have just come to the
  libraries, newest first, for other plugins of the site (the screens of a centre, a block) without reading the
  library's tables. Each with its title, subtitle, authors, its cover as a stored file (or the colours of its drawn
  cover), when it came, its library and the address of its record. Never a bank of textbooks, the practice library, a
  withdrawn or lost copy, a device or a centre still closed to its readers.
- Upgrading keeps a centre's bridge reachable: the host its connector already used on the server's own network goes
  into «Internal hosts for the connectors» (since 0.97.0, the only hosts there a connector may reach).
- The search, with more matches than it scores (2,000), takes first those whose title starts with what was typed, so
  the best is never left out; it counts all of them, and pages only what it scored.
- The lists opened in a spreadsheet (reports, the missing of a stocktake, the record of course fragments) never carry
  a formula: a cell that begins with «=», «+», «-» or «@» gets an apostrophe in front.
- Deleting a person's data in the library also takes away their choices there (look of the card, new books, goal).

## 0.97.0 (2026-10-31)

Fixes from a review of the whole project:

- The digital collection takes only what is read or played (EPUB, PDF, audio, video), never a page: a file whose
  content is markup or a script is refused, and anything that is not a work that reads or plays, and any HTML, SVG or
  XML file the plugin serves, always goes as a download. The catalogue capability now says it carries a risk of XSS.
- The app's screens: braces in a title, author or shelf mark become their full-width look-alikes, so the app (which
  compiles these templates) can never run what a catalogue brought.
- Cifra's connector forgets its key or secret when its address or way of connecting changes, and reaches a host of
  the server's own network only if the site's administrator allowed it (new setting «Internal hosts for the
  connectors»); Moodle's HTTP security stands for everything else.
- Linking a reader with an account that already had one brings everything along (holds, reviews, voices, where it
  came from), as joining repeated readers does: no reservation is left pointing at a reader that is gone.
- A library cannot hang from one of its own, and the desk never loops looking for loan rules.
- Whoever only keeps a library sees, on a reader's record, what that library lends them: not their history elsewhere
  nor the staff's notes, and they write nothing there.
- Deleting a reader's data also forgets their name in their family's voice invitations.
- Smaller: cancelling someone else's reservation shows a page of the library instead of an error; the memoir and the
  posters say when a library does not exist; «Cancel» in LTI.

## 0.96.0 (2026-10-31)

- The same header on every page (`classes/local/menu.php`), after a design review of every screen: for the staff,
  the desk, the catalogue, the readers, the reports and «More», a sheet with the rest in groups (lending, catalogue,
  to review, settings, help), each shown to whoever may open it; for a reader, the catalogue, the shelves and their
  card. The page you are in is underlined; the way back to Moodle is a quiet link at the end. What only one page needs
  (back to a course, its guided help) goes in a small row under the header.
- Groups and stages in a school's order everywhere (readers, reports, tutors, late loans, the memoir, the bank, the
  classroom shelf and the stages of reservations): infant, primary, secondary, upper secondary, vocational, then by
  year and letter (`core\group_order`).
- The floating «How do I…?» leaves room at the end of every page, and the bookmark shows a section's name only while
  dragging or on its notch, with no notches at all on long lists.

## 0.95.0 (2026-10-31)

- A course's readings, for a reference manager: «Download the list» in RIS (Zotero, Mendeley, EndNote) or BibTeX
  (LaTeX, JabRef), in their order, each with where it is on the shelf and, as its note, its topic, whether it is
  required and the teacher's word. Checked with the parsers those tools use (18,097 entries read without an error).
- A BibTeX writer in the core (`core\export\bibtex`), with LaTeX characters escaped and keys from the author, year and
  title.

## 0.94.1 (2026-10-31)

- The digital forms of a record, redesigned (Enrique: «La ficha puede estar técnicamente bien montada, pero el diseño es
  lo que siempre hay que mejorar»): the book on top with its cover; each form a card with its format, licence, who it
  is for (or the course it is only for, and until when) and «Open» and «Change»; adding one, a card that opens its
  sheet; changing or removing one, in its sheet; who it is for and the stages, as the house's pill buttons; the date in
  one row.

## 0.94.0 (2026-10-31)

- Course fragments: a digital form of a book (a scanned chapter, some pages, a link) can be given only to the students
  of a course whose readings have the book, until a date, as the law or the centre's licence allows teaching to use
  them: its enrolled students open it (and the course's readings offer it), no one else; after the date, only the
  library. «Record of fragments (CSV)», from «What the courses ask», lists the work, ISBN, part, course, students,
  dates and how many opened it, for whoever the rights are paid to. Also in «How do I…?».

## 0.93.1 (2026-10-31)

- The course memoir: «Save» and «Print» no longer sit against the box of text above them.

## 0.93.0 (2026-10-31)

- Course reserves: from «What the courses ask», the library puts copies of a required reading on reserve for one of
  its courses, as many as it chooses (free ones first), lent only in the room (back the same day) or for one to seven
  days, until a date; then each copy goes back to its own rules. The desk lends them so, says it is the course's
  reserve, and does not renew them (neither does the reader online). The course's readings say «On reserve». A course
  deleted takes its reserves with it. Also in «How do I…?».

## 0.92.2 (2026-10-31)

- After Aty's second test on a phone: a book the reader has out says so on its page («You have it, until…», with
  «Renew»), instead of offering to ask for it; «Renew» shows only while a renewal is left (and no one waits for the
  book), and says the new date when it goes through, in the app and in the dashboard block; letting go of a
  reservation asks in the page with «Keep waiting» and «Let it go», not «Cancel» and «OK».

## 0.92.1 (2026-10-31)

- In the app (tried by Aty on a phone): the search field is outlined and as tall as its button, so it shows in dark
  too; a book asked for can be let go from its page («I do not want it any more», with a question first; the new web
  service `local_oksigeniaayvurenda_cancel_hold`); and the books out and asked for open their page inside the app.

## 0.92.0 (2026-10-31)

- Readers renew their own loans online, where the library lets them: a new switch next to reserving online, for the
  same stages, off by default. «Renew» on each loan in the app (the new web service `local_oksigeniaayvurenda_renew_loan`,
  which says why when it cannot) and in the dashboard block from its 0.1.3 (`renew.php`). The same rules as at the desk:
  as many times as the loan rules say, not while another reader waits for the book (unless the library says it may),
  and never in a textbook bank.

## 0.91.0 (2026-10-31)

- The way into the app, from a reader's record: where Oksigenia Aty is installed, «Send them the app» e-mails the
  reader a link that opens the app already signed in, once and for 7 days (Aty's `api::send_link()`). A reader without
  an account gets one first from the e-mail the library has, if whoever asks may create accounts on the site; never
  when another account already has that e-mail (a sibling's or the family's). Also in «How do I…?».

## 0.90.0 (2026-10-31)

- What the library says to a reader comes in the tone of its centre's skin, at home, on the card and in its
  characters' lines: a secondary school's says it plainly and dry («¿Qué vas a leer ahora?», «Recién llegados. Échales
  un ojo.»), a university's or academy's like a newsletter («¿Qué buscas?», «Novedades de esta semana.», «Último
  préstamo», «Sin resultados para…»). A school's stays as it was.
- The library in the Moodle app (and in Oksigenia Aty) without leaving it: a search box for each library, the results
  with whether each book is free and where, and a book's page with its cover, its copies here, what the reader asked for
  and, when it is out and they may ask online, «Tell me when it comes back», answered by the new web service
  `local_oksigeniaayvurenda_ask_hold` (in the app's service). Tried with the official app.

## 0.89.0 (2026-10-31)

- Course readings: «Readings» in a course's menu. Its teacher makes the list from the library's own search, by topic
  if they like, each book required or recommended and with a word on it, moved within its topic or taken off; the
  students see it in the course, each book with its cover, whether it is free and where, and the way to read it online
  when the library has it for them. A course takes its books from the library of the centre whose category holds it.
- What the courses ask (from «Reports»): each required title with its courses, their students against the library's
  copies and who is waiting for it, the ones with no copy first. To know what to buy.
- A course deleted takes its list with it; the privacy provider knows who started each list.

## 0.88.0 (2026-10-31)

- The desk without a network: if it goes down, the desk goes on; each scan is kept in that browser, in order and with
  its moment, a band says how many wait, and they go through by themselves as soon as the network is back (or when the
  page opens again), each answered as it would have been then, with the dates of that moment.
- For Oksigenia Aty, the app of the libraries of a shared site: `local_oksigeniaayvurenda_oksigeniaaty_user_brand()`
  gives the look of a reader's library (their centre, the colour, picture and line of its main library); nothing for
  whoever is not a reader or reads at a centre open only to its staff.
- Not only for schools: «Academic year» instead of «School year», and the readme and the help's instructions say it
  is the library for schools, secondary schools, universities, academies and public or private libraries.

## 0.87.1 (2026-10-31)

- A class's page, compact (Enrique: «ocupa mucho espacio y tiene mucho sitio en blanco»): the challenge of the term,
  choosing it (as buttons in a row that save when pressed) and the class's garden side by side in one band; the
  readers as small cards in a grid, each with their face, name and card, and the count of books only when they have
  some.

## 0.87.0 (2026-10-31)

- A reader's record for the library staff (`reader.php`): their photo, from their Moodle account (or their initial),
  so the child is known even without the card; who they are this year (group, stage, tutor), their card with its
  barcode, their flower or sky, what they have now in any library of the centre with what is late, their reservations,
  what they took home this year and in the years before (not the textbooks), and the staff's own notes. It opens from
  the desk («Their record», beside the reader's name), from the list of a group (each name) and «Open at the desk»
  brings the desk with the reader already there. Whoever keeps a classroom library sees only its readers.
- At the desk, the reader's photo beside their name, and when two readers share a name, their photos to tell them apart.
- In the centre's settings, «The reader's photo at the desk and in their record», on unless the centre turns it off.
  Photos are only on the staff's pages.

## 0.86.0 (2026-10-31)

- «Just for you» on the card of every reader past the flower (from twelve, and adults), in any skin, as the skins'
  sheet asks for a university:
  - their own goal for the school year, how many books they mean to read, with its progress: only they see it, and it
    counts the books taken home that year (not the textbooks);
  - the library's news, if they sign up: once a week at most, a Moodle message with what came into the library since
    the last one, only when something did, in the tone of the centre's skin and opening the library's home. Nobody gets
    it without asking, and nothing goes while the centre is open only to its staff.
- In Cifra every user has their own id: a relative is known by it (the reader's comment says so).

## 0.85.0 (2026-10-31)

- The roster readers say who is family of whom, for the common family record (`local_oksigeniafamily`), which carries a
  copy of them made by hq's kit: from OneRoster, each parent, guardian or relative with their children (the
  `agentSourcedIds` of users.csv, read both ways); from Cifra, the relatives of each student's record, each adult once
  with their kinship, whether they are a legal guardian and their mobile first. Plain PHP with their tests; the library
  does not use them yet.

## 0.84.2 (2026-10-31)

- The textbook bank's sets say what has to be replaced of each title (copies broken or being mended, and lost), and
  count lent, free and short in the singular when there is one.
- «How do I…?» explains handing out the sets in September and collecting them in June, class by class.

## 0.84.1 (2026-10-31)

- What the library tells a reader comes in the tone of the centre's skin (`skins::words()`): a school's greets and
  speaks warmly, a secondary school's says it plainly and offers a renewal, a university's gives the facts (title and
  date). For now the overdue notice and the held book ready; a skin without its own words says the school's.

## 0.84.0 (2026-10-31)

- What is on at each centre (in its settings): the visit, its games, the characters standing whole, their voices in the
  games and the visit's sound on for the readers of the flower, each as its skin brings it (all at a school; no voices
  nor sound at first at a secondary school; and neither figures at a university), and the centre changes any. Only what
  differs from the skin is kept, so choosing another skin brings its own back.
- Every notice of the library carries the link to its exact page, which Moodle and its app open from it: an overdue book,
  the reader's library; to a tutor, their class; a held book ready, its page; a voice received, the book's page; and to
  the staff, the desk.

## 0.83.0 (2026-10-31)

- The textbook bank by class (`roll.php`, «By class» from the bank's desk and its sets): a card for each class with its
  pupils, who is still waiting for books and who still has them; in a class, each pupil with the books they hold (code
  and state) and the titles of their set still to give them, filtered (everyone, waiting, have books) and to print, for
  handing out in September and collecting in June class by class. Whoever left the class with books still shows.
- Textbooks are not reading: the titles only a bank has stay out of the reading catalogue's search and corners (the bank
  itself still finds them), and the bank's loans are not counted in the challenges, the flower, the cards, «What your
  class reads», the search ideas, the year in figures or the year's report.

## 0.82.1 (2026-10-31)

- The year's report, redone to be read and not only filed: the share of the year's readers who came as one big figure
  with its bar, the other figures as tiles with how they moved since the year before, stages as bars, groups as small
  cards, the covers of what went out most in their order, the corners as ranked bars with their pictures, and icons for
  what the readers did and the textbook bank; in light and dark, and on four A4 pages when printed. Who came counts only
  the readers of the year's groups (the staff borrow too).

## 0.82.0 (2026-10-31)

- The visit by skin: each scene of the art package says its skin (`"skin"`; the school's without it), and a centre
  visits only the scenes of its own, starting in its skin's hall (`vestibulo`, `instituto` or `universidad`); without
  it, there is no visit. Shelves without a corner lead to the whole shelves, and in a skin without rooms each corner of
  the board goes to its shelf.

## 0.81.0 (2026-10-31)

- The year's report (`memoir.php`, from «Reports»): for the centre's reading plan, the year's figures beside the year
  before (loans, readers who came of those of the year, read in the room, copies come, weeded and held), by stage and
  by group, what went out most and from which corners, the class challenges, reviews and voices of the year, and the
  textbook bank; with the team's words of what was done and what comes next, kept for the year, and ready to print.
  Always added up and without names.

## 0.80.0 (2026-10-31)

- The textbook bank, first part: a library of the kind «Textbook bank», with its sets (`bank.php`, from its desk): each
  set is a grade's titles for the groups it serves. At the bank's desk, the pupil's panel says their set and what is
  still to give; each copy goes until the school year ends, with no limit of copies, and its state is noted when it
  goes and when it comes back. The family's contract, one page to print (`contract.php`), lists each copy with its
  code and state. Each set says, per title, the copies lent, free and short for the pupils of its groups.

## 0.79.1 (2026-10-31)

- A volume control beside «Sound» in the visit, one for the whole device, kept for every room and game and for
  «Listen»: it starts at a quarter, since twenty devices may sound at once in a class and a loud voice frightens the
  little ones. Turning the sound on no longer jumps to full volume.

## 0.79.0 (2026-10-31)

- The skin of a centre's library, first part: each centre chooses in its settings whether its library is a school's
  (as until now), a secondary school's, or a university's or academy's. Every page carries the skin's class; at a
  university or academy the interface goes to the system's font (titles and records stay in Literata) and the characters
  are a portrait and a signature: they stand whole nowhere and have no voice of their own. Each reader's own step by age
  goes on under every skin. The skins' own art and words come next.

## 0.78.0 (2026-10-31)

- Guided helps for free works and for who keeps a library, from their pages and from «How do I…?», where who keeps a
  library is a task of its own; the task of the characters tells about the zip of a character of the centre's own.

## 0.77.0 (2026-10-31)

- A centre can give its own character whole, in a zip on «Characters»: its portrait, its five poses and its puppet
  (`retrato`, `saluda`, `senala`, `celebra`, `lee`, `piensa`, and `marioneta.webp` with its `.atlas.json`, `.rig.json`
  and `.anim.json`). It then stands in its own poses and moves as its own puppet in the visit and on every screen;
  taken away, Ayvurenda's comes back. Only those names are read, each picture checked and each file of data parsed.

## 0.76.0 (2026-10-31)

- Each library can have its own keepers: the centre's staff choose them among its readers with an account
  (`keepers.php`, from the library's card) — the teachers of a department, the class teacher, a pupil librarian. A keeper
  works at that library's desk only: its own books, and in a classroom library only the readers of its groups; never
  the other libraries' loans, the copies, the boxes, the catalogue nor the settings. «Desk» shows in the header for
  them, and the library's card says who keeps it. The privacy provider exports and deletes it.

## 0.75.0 (2026-10-31)

- A centre can be open only to its staff while it gets ready: its readers see no way in (pages, the block, the app)
  and hear «The library opens soon», get no notices, and its public door stays shut; its staff work as before. Only
  whoever sets the plans opens it, beside the plan in the centre's settings, where the staff do not see it.

## 0.74.1 (2026-10-31)

- Bringing a free work, and searching for one, shows that it is working: the button turns with «Bringing it into the
  catalogue…» or «Searching…», and a line says that a long work takes a while. It cannot be sent twice.
- The credits say what the name means, under the dedication: «The place of words · Ayvu renda (Guarani)».

## 0.74.0 (2026-10-31)

- Works of Wikisource come with their EPUB, from Wikisource's own export, and are read in the library like those of
  Project Gutenberg; when the export does not answer, the link to read them there, and brought again later, the EPUB
  takes the link's place.
- In the visit, a sound nobody chose (a reader of the flower hears it from the start) begins at a quarter of its
  volume, voices and touches too: twenty devices in a class sound at once. Turned on by hand, it is heard whole.

## 0.73.5 (2026-10-31)

- The addresses of the art package carry its version: a new one is seen as soon as it is installed, even behind a cache
  that keeps files for hours.
- «Sound» in the visit shows whether it is on: the speaker with its waves, or crossed out.
- The way back to Moodle in the header keeps its colour under the pointer, a little darker, instead of turning pale
  under its white words.

## 0.73.4 (2026-10-27)

- A herald of the other sex keeps Gaudí's hummingbird on the home and the card when the art package has no portrait
  of her, instead of the initial of her name.

## 0.73.3 (2026-10-27)

- The sound of a room in the visit is heard: at full volume, and lowered to a third while a character speaks. When the
  sound was on from before and the browser kept it quiet until a first touch, that touch on «Sound» starts it instead
  of turning it off.

## 0.73.2 (2026-10-27)

- The made-up library has books of engineering, vehicles and cooking in the corner of the applied sciences, for the
  things of its room in the visit; `cli/demo.php --refine` brings them to an earlier one.

## 0.73.1 (2026-10-27)

- The made-up library has the corners of the social sciences (economy, education, folk tales) and of the applied
  sciences, for the rooms of the visit to come; `cli/demo.php --refine` gives an earlier made-up library made-up books
  of its own in a corner it did not have, with their covers, as many as its smallest corner has.

## 0.73.0 (2026-10-27)

- The characters grow with the reader, as the card does: a reader whose card is not the flower, and an adult reader,
  sees the memory and Mainumby of the sky's step when the art package draws them (`pix/personajes/<name>-cielo-<pose>`
  and `pix/marionetas/<name>-cielo`); a reader of the flower, the staff and whoever comes without an account see the
  flower's. Without the sky's drawing, the flower's, with its puppet.

## 0.72.3 (2026-10-27)

- The words on the painted boards of the library one visits fit them: on a busy day the blackboard's lines, and a long
  name on the board of corners, get smaller letters instead of going out of the board (found in dev).

## 0.72.2 (2026-10-27)

- Made-up loans leave a fifth of the made-up library's copies out at most, however often they are made, as in a school
  library: its shelves had become all gaps and the game of the hidden book had nothing free to hide. `cli/demo.php
  --refine` brings the made-up loans of an earlier made-up library back to that, the oldest first.

## 0.72.1 (2026-10-27)

- The made-up library has books in more classes (computing, libraries, botany, painting, games and sports, geography,
  biographies), so that every thing of the rooms of the visit leads to books; `cli/demo.php --refine` brings a made-up
  library made before up to them. Never in a centre with records brought from a catalogue.

## 0.72.0 (2026-10-27)

- Every room the art package paints is a room of the library one visits: the board of the hall walks into the room of
  each corner that has one, through its door, without a new painting of the hall.
- Things of a room lead to where their class begins on the shelf (a telescope, to astronomy), named as the library
  names that class; a thing whose class the library has no book of is only painted. The room of a corner the library
  hides is none of its rooms.
- Some air between the buttons of the home and the way into the library.

## 0.71.1 (2026-10-27)

- The library one visits by night on a site without Moodle's colour modes too: the painting then follows the visitor's
  system (found on campus).

## 0.71.0 (2026-10-27)

- The characters speak in the games of the library one visits, from the art package's voices: the memory gives her
  clue, says which way to go, points at the glow and cheers the find; the herald welcomes, says when a door is not the
  one, points at the glowing door, cheers each book and thanks at the end. One clip at a time, the room's sound lower
  meanwhile, only with the visit's sound on; what changes each time (the label, the line to look at) stays written.
  The memory greets aloud when touched in the hall. A reader of the flower hears the visit unless they turn the sound
  off.

## 0.70.0 (2026-10-27)

- The herald's shelf, a game in the hall of the library one visits: the herald brings the library's new books, four at
  a time, and each goes to its corner, the corners being doors with their names and pictures. A wrong door says what
  numbers it keeps, what number the book has and what it is about, never the answer; after two, the right door glows.
  With all in their place, the herald cheers and the books are there to take home. The herald offers each room's game.

## 0.69.0 (2026-10-26)

- Find the hidden book, a game in each room of the library one visits: the memory hides a real book of the room's
  corner that is free to take home and gives its label as the clue (and its cover, to the young). It is found on the
  room's shelf, among the books really beside it, with a magnifier that shows the label of the spine under the pointer,
  the keyboard or the finger. A spine that is not it says which way to go and which line of the label to look at;
  after three, the hidden one glows a little; never lost. Found, it comes out and the memory cheers, with where it
  stands, to find it on the real shelf. A book of the day for each reader, better one they have not taken home;
  nothing is kept. The visit plays a soft touch when a place lights up, with the sound on.

## 0.68.1 (2026-10-26)

- The library one visits: the first time in a room in a session, every place one can touch glows a moment, since a
  painting does not say what is a door; never with «reduce motion».

## 0.68.0 (2026-10-26)

- The library one visits: rooms painted for the art package (a hall and a room for each corner, by day and by night,
  with their sound) that the library fills with its own. Doors lead to the rooms there are, or to the shelves of their
  corner; the board names the corners as the library names and orders them; the stand shows real covers of what came
  in last; the blackboard says what moved today, never who; the fountain leads to Ayvu rande; the characters stand in
  the room as puppets, and the memory asks what one is looking for. Every place is a real link, also in a row of words
  for the keyboard and the screen reader. Without WebGL; still with «reduce motion»; for anyone when the centre shows
  its catalogue. The way in is a card on the home and at the door. Without the art package's hall, there is no visit.

## 0.67.1 (2026-10-26)

- The puppets start on their gesture and come to rest on its still pose, never in the middle of a wingbeat.

## 0.67.0 (2026-10-26)

- The characters' puppets: where the art package brings them, a pose becomes the character doing its gesture, then
  breathing a while and holding still; a touch plays it again. Only while in sight, never with «reduce motion».

## 0.66.1 (2026-10-26)

- The characters stand bigger on a soft shadow of ground, and the bubble points to the face of whoever speaks.

## 0.66.0 (2026-10-26)

- The characters whole, in a pose, beside what they say and in the empty states (a search that finds nothing, a book
  that is not here, an empty album, a challenge met, the book a class asked for), from the art package and only for a
  centre without a portrait of its own.

## 0.65.3 (2026-10-26)

- Made-up covers on servers whose GD reads no WebP: a small JPEG is kept as it is, and the demo takes the JPEG first.

## 0.65.2 (2026-10-26)

- The class island loads the reading hut modelled for the art package; the drawn one stays without it.

## 0.65.1 (2026-10-26)

- The class island after its art review: flat facets, one crown, the sun of the drawing, cut-paper grass, a deeper sea,
  a shingled roof and warm halos.

## 0.65.0 (2026-10-26)

- The class island, alive in 3D in My class: what the class met since this browser last saw it grows in front of the
  tutor, and then everything stops. About 20,000 triangles, tested in a real Chrome 93. With «reduce motion», without
  WebGL2 or with the setting off, the drawn island stays. Three.js under MIT.

## 0.64.0 (2026-10-26)

- Posters for the shelves, two to an A4 sheet: each place's name on its colour and a QR code that opens that shelf
  online.

## 0.63.0 (2026-10-26)

- A shelf of the newest books for the school's website, in a frame, when the catalogue is shown to anyone; the door and
  each public record show their title and cover when a link to them is shared.

## 0.62.0 (2026-10-26)

- Where is it? What each line of a book's label says, and the place to look in, to find it on the real shelf.

## 0.61.0 (2026-10-26)

- The door: the public window as the library's entrance, with its picture, colour and motto, what moves today (never
  who), its places and corners, what came in last and, if the centre wants, the catalogue and the shelves for anyone,
  with whether each book is free and where it stands.

## 0.60.0 (2026-10-26)

- The album's collector cards: one for each corner the library shows, won with the first book taken home from it.

## 0.59.0 (2026-10-26)

- The shelves: each place or corner with its books as spines in the order of the room, as tall and thick as the record
  says and in the colour of their cover; a book that is out leaves its gap; taking a spine out turns the book on a
  lectern. Each record shows the books beside it on its shelf.

## 0.58.3 (2026-10-26)

- How do I…? always at hand, as a button down on the right that opens a small box.

## 0.58.2 (2026-10-26)

- Privacy: the key a reader's origin knew them by is declared and deleted with their data.

## 0.58.1 (2026-10-26)

- Fields fit a phone: the control goes below its label when they do not fit, and a long dropdown is cut short.

## 0.58.0 (2026-10-26)

- The daily copy carries the catalogue in Dublin Core and RIS too, and a record can be cited in a reference manager.

## 0.57.1 (2026-10-26)

- My class shows the class garden as an island from the art package; the album keeps what each past class met.

## 0.57.0 (2026-10-26)

- Readers from a OneRoster 1.1 or 1.2 export, with their homeroom as their group, its tutor when they have an account,
  and who left in a whole export. Adding by ISBN asks the Z39.50 bridge again when it says it is asked too much.

## 0.56.1 (2026-10-26)

- The OPDS catalogue checked with the reading code of Thorium, KOReader and Calibre; its search name is cut between
  words.

## 0.56.0 (2026-10-25)

- Adding by ISBN asks REBECA, the shared catalogue of Spain's public libraries, through a Z39.50 bridge whose address
  the site sets; first, on a site in Spanish.

## 0.55.0 (2026-10-25)

- Centres kept apart on a shared site: each centre's staff get their roles in its own hidden category, and every page,
  answer and file checks its permission in the centre it serves. Ids of another centre lead nowhere.

## 0.54.0 (2026-10-25)

- Plans for hosted libraries: limits of copies and readers per centre and what each plan brings. Past a limit nothing
  is removed; what would not fit is refused or undone whole.

## 0.53.1 (2026-10-25)

- Small fixes to the recorder and to the setting of How do I…?.

## 0.53.0 (2026-10-25)

- How do I…? answers in its own words when the site has Moodle's text generation set up, with the guide as its only
  source, after the site's policy is accepted, and saying the answer is automatic.

## 0.52.0 (2026-10-25)

- How do I…? at the foot of the staff's screens: the library's tasks step by step, found in one's own words, with their
  screen and guided help.

## 0.51.0 (2026-10-25)

- Read in the library: a book passed at the desk without a card counts with one touch, with no reader.

## 0.50.0 (2026-10-25)

- Reviews with a voice, under the rules of recorded voices.

## 0.49.0 (2026-10-25)

- The guided helps of the reviews, what the classes ask for and My class.

## 0.48.0 (2026-10-25)

- The staff hear what waits for them: cards on the desk and a Moodle notice a day at most.

## 0.47.0 (2026-10-25)

- Reviews with a drawing, for whoever would rather draw than write.

## 0.46.1 (2026-10-25)

- Listen keeps its own width.

## 0.46.0 (2026-10-25)

- The older readers recommend books to the young ones.

## 0.45.0 (2026-10-25)

- The class reward: a class that meets its challenge chooses the next book with its tutor.

## 0.44.0 (2026-10-25)

- Reviews: a reader tells what they thought of a book, and the library publishes it with a touch.

## 0.43.0 (2026-10-25)

- Listen, for the young: the home, the card, the herald and the class challenge in a voice.

## 0.42.0 (2026-10-25)

- My class for the tutor, and class challenges on or off by stage.

## 0.41.1 (2026-10-25)

- Each centre says it is one, with its settings beside its name.

## 0.41.0 (2026-10-25)

- The libraries as cards with their forms in windows, the house's dropdowns, dates in the library's language, and a
  voice for each step of the guided helps.

## 0.40.2 (2026-10-25)

- The file button speaks the library's language, not the browser's.

## 0.40.1 (2026-10-25)

- The help pointer finds what sits under a sticky header.

## 0.40.0 (2026-10-25)

- The guided helps of the card, the labels and the reports.

## 0.39.3 (2026-10-25)

- The catalogue first in the header, the house's file buttons and headers to choose from.

## 0.39.2 (2026-10-25)

- The memory drawn above the dedication of the credits.

## 0.39.1 (2026-10-25)

- Corners with short names in even rows, the global menu in the header, the main seat chosen by the administrator,
  and the credits.

## 0.39.0 (2026-10-25)

- A bookmark as the scrollbar on wide screens, the featured library, each library's own card and the characters' own
  portraits.

## 0.38.0 (2026-10-25)

- Free works from Wikisource; shelves with arrows instead of a scrollbar; the way back to the dashboard in the header.

## 0.37.0 (2026-10-25)

- The class challenge of each term, cooperative and anonymous, and the class garden.

## 0.36.2 (2026-10-25)

- The home page after its art review.

## 0.36.1 (2026-10-25)

- The characters' portraits and the drawn corners from the art package, when the site has it.

## 0.36.0 (2026-10-25)

- A new home page: the greeting with the search and its ideas, the herald, the reader's card, the corners, the shelves
  and what the class reads (never who).

## 0.35.0 (2026-10-24)

- A teacher brings the students of their course from their platform, with their group, kept up to date every night
  (LTI Names and Roles).

## 0.34.0 (2026-10-24)

- Repeated readers, joined into the one the librarian chooses.

## 0.33.1 (2026-10-24)

- Pairing with a platform compares each reader with people of their type.

## 0.33.0 (2026-10-24)

- A teacher puts a book of the library, or the library, in a course of their platform (LTI Deep Linking).

## 0.32.1 (2026-10-24)

- A platform registers in one go (LTI Dynamic Registration).

## 0.32.0 (2026-10-24)

- The library as an LTI 1.3 tool: a learning platform opens it without installing anything, and whoever comes in
  becomes a reader.

## 0.31.1 (2026-10-24)

- The nightly task brings readers from a management program only after a first time by hand.

## 0.31.0 (2026-10-24)

- Readers already there are paired with a management program's before it brings them, so none come twice.

## 0.30.2 (2026-10-28)

- In the app, each book out says until when again (and the late ones, that they are late), and each reservation whether
  it waits for the reader: the lists worked out for the app did not replace the summary's.

## 0.30.1 (2026-10-28)

- In the app, the pages of the library (each book of the list, the library and Ayvu rande) open inside the app with
  the session, not in the phone's browser.

## 0.30.0 (2026-10-28)

- The library in the Moodle app, and in the apps made from it: an entry «Library» of the main menu with, for each
  centre where someone is a reader, their books out and until when (the late ones marked), what is set aside for
  them, their card's code to show at the desk (in letters, as a barcode and as a QR), and the ways into the library
  and Ayvu rande, which open in the browser.

## 0.29.1 (2026-10-28)

- A page someone may not use no longer ends in an error. Someone not logged in, or in as a guest, goes to log in and
  comes back; someone logged in without the permission gets a page of the library that says which permission it
  needs and has the way back. Recording a voice says so too for someone who is not a reader.
- The counts in the chips that narrow a search have their space.

## 0.29.0 (2026-10-28)

- Adding by ISBN asks Spain's National Library too, by SRU and without a key: most books in Spanish, little known to
  the Library of Congress or Open Library, come with their authors and their UDC class. Without a choice of the
  entity, the order follows the language of the site: in Spanish, the National Library first.

## 0.28.0 (2026-10-28)

- The corners as each library wants them: whoever manages it gives them the names its readers know them by («Stories
  and poems» rather than «Language and literature»), puts them in the order of the room and hides the ones not
  wanted. A name left empty goes back to the classification's.
- Narrowing a search: the results say how many of them are in each corner and about each topic they share, as chips
  that narrow the search and, pressed again, widen it.

## 0.27.1 (2026-10-28)

- The names of the plugin's four capabilities are found again: they were kept under the wrong key, so a page refused
  for lack of a capability showed a developer's notice instead of Moodle's own message.

## 0.27.0 (2026-10-28)

- The repository: what the centre publishes as its own (its own licence or a Creative Commons one), meant for the
  public, in an open window, by OAI-PMH 2.0 with Dublin Core at `oai.php/<library>`, for repository harvesters. The
  public-domain works the centre brought and the voices stay out. The window's settings show its address.
- What a person has in the libraries, in a few lines (their books out and until when, their reservations, the ways
  in), for the block of the personal area.

## 0.26.0 (2026-10-28)

- Free works: whoever catalogues searches Project Gutenberg's own OPDS catalogue, looks at a book with its languages
  and what Project Gutenberg says of its rights, confirms that it is in the public domain in their country too,
  chooses who it is for and brings it: a record with its author, language and cover, and its EPUB in the public
  domain, ready to read and, if meant for the public, in the public window. One question per search and one book at a
  time, never in bulk; a book brought again keeps its record.

## 0.25.0 (2026-10-27)

- Ayvu rande, «the spring of words» in Guarani: a page with the voices given to the library's books, book by book,
  that each person may hear, linked from the home and from the record of a book with voices.

## 0.24.0 (2026-10-27)

- House style D, first part, by Gaudí: a layer after the plugin's own styles (`app/ayvurenda-d.css`). Flat shapes in
  layers with soft shadows and warm light: the header floats, boxes are cards, drawn covers carry a motif by colour,
  the record has its cover on a cream plate and its actions in one card, the reader is an open book with round page
  turns, and the recorder sits on a cream plate with a halo round the coral button. In dark mode the book's page is
  dark with light letters.
- «Read the book» with an open-book icon, and what it is in small beside; «Give it a voice» under its question.

## 0.23.2 (2026-10-27)

- Full screen in the reader: the book alone, or beside the recorder the book and the recorder, with nothing else on
  the screen. Where the browser cannot go full screen (an iPhone), the same part of the page covers the rest. Escape
  or the same button leaves it.

## 0.23.1 (2026-10-27)

- The reader is a sheet with a book's proportion: one page on a phone and beside the recorder, two facing pages with
  their fold on wide screens. The sheet sets the measure of the lines and the text fills it.
- The recorder: a big round coral button with its word, a meter that moves with the voice while recording, and what
  was read, the signature and the email underneath, quieter, once there is something to send.
- The public window: every book with its cover, drawn when it has none; «Read» as a button and «Download» as a link.
- Icons thinner (1.75) and at their sizes: 20 px in buttons, 24 px in the big actions.

## 0.23.0 (2026-10-27)

- Recording with the book in front: when the book has an EPUB or a PDF that the person may open, it is beside the
  recorder, which stays in sight while reading; if not, the page says to have the paper book at hand. A family's
  link opens the text meant for families too.
- One class: a tutor makes a family's link for a student of their groups, and the family can keep their voice to
  that student's class this school year. Voices of students can be kept to their class as well.
- A signature, if wanted («Ana's mum»), read and corrected by whoever approves the voice before anyone sees it; it
  shows in the centre, never in the public window.
- The centre chooses what families may pick with their link, and how long voices are kept, now with no term at all
  (until whoever recorded them deletes them).
- Whoever records with an account gets a message with a link that deletes the voice without logging in or asking
  anyone; a family can leave an email to get it, used once and not kept. The link opens a page with one button, so
  that mail programs that open links by themselves delete nothing.
- The reader looks like a page: the library's letters, page turns at its sides, a finger turns the page, and how far
  through the book. Icons for recording and turning pages, from Lucide (ISC).
- The header search no longer widens a phone's screen, nor does a long option in a card.

## 0.22.1 (2026-10-27)

- The made-up library can fill its public window: `cli/demo.php --window=N --library=SLUG` writes N short made-up
  EPUBs of the centre's own on titles of its catalogue, for everyone, and opens the window, to show it.
- The public window lays its works out in columns on wide screens.

## 0.22.0 (2026-10-27)

- The public window of each library: a page anyone can open without an account, closed until whoever manages the
  library opens it from the same page. It shows only the digital forms meant for the public whose licence lets them
  out, and the voices approved for the public if the centre wants; never who reads nor who recorded. It searches by
  title and author, reads in the browser keeping the place and the bookmarks in the visitor's own browser, and
  downloads what the licence allows. The centre writes a few lines for the top and chooses whether search engines may
  list it.
- The window as an OPDS 1.2 catalogue, with OpenSearch, for Calibre, KOReader, Thorium and other readers; the page
  announces it.
- Files and covers are served without an account only while they are in an open window; everything else asks to log
  in, as before.

## 0.21.1 (2026-10-27)

- Families without an account give a book a voice too. A teacher or the library makes a one-time link from the book's
  recorder page and sends it; it works for 14 days. Whoever opens it reads how their voice is handled, gives their
  permission before they can record, chooses who else may hear it (the centre's students, its families, the public),
  records and sends. The teacher who invited them hears it first and approves it, never for more people than the
  family chose. With the same link the family hears it and deletes it whenever they want; it records only once.
- The privacy provider finds whoever noted a family's permission or sent a link.

## 0.21.0 (2026-10-27)

- «Do you want to give this book a voice?». A reader records a reading of a book in the browser, listens to it, records
  it again if they want, and sends it; it waits for a tutor or the library to approve it. A voice is personal data, and
  it is handled as such:
  - before recording, the page says in one paragraph where the voice is kept and who hears it, with a link to «How
    voices are handled», to which the centre can add its own text;
  - whoever recorded it, their tutors this school year and the library staff hear it; nobody else until it is approved;
  - a minor's voice reaches more people (the class, the centre, the public) only with the family's permission, which
    the centre notes with its date; taking it back makes the reader's voices private again; an adult chooses;
  - whoever recorded it deletes it whenever they want, and the centre keeps voices only for the days it sets (two
    school years if it sets nothing);
  - voices never leave the site: not in the daily copy, nor sent to any service; they are in the privacy export and
    go with the person's data when it is deleted.
- Whatever a script hides stays hidden, whatever its class.

## 0.20.0 (2026-10-27)

- The digital collection. A record can have digital forms: an EPUB read in the browser (a page at a time, with the size
  of the letters and bookmarks), a PDF, an audiobook or a video that go on from where they were left, or a link to
  where the work is. Each one says its licence and who it is for (students, all or by stage; families; teachers and
  staff; the public). The licence wins: a work whose rights are someone else's is only read here, never downloaded nor
  shown to the public. The files stay in the site and are served only to whom they are for. Where each reader is, and
  their bookmarks, go into the privacy export.
- epub.js 0.3.93 (BSD-2-Clause) and JSZip 3.10.1 (MIT) for the EPUB reader.

## 0.19.2 (2026-10-27)

- The library staff can see any reader's card as the reader sees it, and switch between the flower, both skies, the
  classic look and none; without a card of their own, a link takes them to a practice reader's.
- The search box keeps what was searched and has a cross to clear it, which takes back to the library's page; clearing
  the box by hand does the same. Words that say nothing about a book («de», «del», «the») are never suggested.
- The footer leaves room on the left for the floating button of an accessibility plugin.

## 0.19.1 (2026-10-27)

- The classic look of the card says what each path means, concretely, without counts: the last visit («today», «on
  Tuesday»), the corners by name, what the reader said of their books, what they finished and the series they have
  half read.

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
