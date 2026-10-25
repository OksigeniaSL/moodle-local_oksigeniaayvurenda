/**
 * Ayvurenda's bookmark (Gaudí, 24 October 2026): beside the browser's scrollbar, the fore-edge of a book, its pages
 * as fine lines; a thumb index, as in a dictionary, with a notch and the initial of each section of the page (its h2
 * headings); and a satin ribbon with a swallow-tailed end that comes down to where you are reading. The notch of the
 * section you are in takes the ribbon's colour, and its name shows for a moment while you scroll. With «reduce motion»,
 * nothing animates. After the scroll ruler of oksigenia.com and the bookmark of campus.oksigenia.com.
 *
 * In the plugin (25 October 2026, Enrique: «algo realmente diferente y wow»), on wide screens it is the scrollbar
 * itself, as the ruler of oksigenia.com: the browser's is hidden, a notch takes you to its section, and the whole edge
 * (34 px wide, more than the 24 px of WCAG 2.2 2.5.8) is pressed or dragged like a scrollbar. Screen readers skip it,
 * and the keyboard, the wheel and the finger scroll the page as always.
 *
 *     ayvBookmark({ root: document.querySelector('main'), minWidth: 1000 });
 *
 * GPL v3 or later, with the plugin.
 */
function ayvBookmark(options) {
    const root = options.root || document.body;
    const wide = window.matchMedia(`(min-width: ${options.minWidth || 1000}px)`);
    const PAD = 22;
    const NAME_MS = 1200;
    if (document.querySelector('.ayv-bm')) {
        return;
    }
    const bar = document.createElement('div');
    bar.className = 'ayv-bm';
    bar.setAttribute('aria-hidden', 'true');
    bar.innerHTML = '<div class="ayv-bm-tabs"></div><div class="ayv-bm-ribbon"></div><div class="ayv-bm-name"></div>';
    document.body.appendChild(bar);
    const tabs = bar.querySelector('.ayv-bm-tabs');
    const ribbon = bar.querySelector('.ayv-bm-ribbon');
    const name = bar.querySelector('.ayv-bm-name');
    const html = document.documentElement;
    let heads = [];
    let span = 1;
    let current = -1;
    let queued = false;
    let hide = 0;

    const still = window.matchMedia('(prefers-reduced-motion: reduce)');
    const to = (y, smooth) => window.scrollTo({top: y, behavior: smooth && !still.matches ? 'smooth' : 'auto'});
    const at = (clientY) => {
        const max = Math.max(1, html.scrollHeight - window.innerHeight);
        return Math.min(1, Math.max(0, (clientY - PAD) / span)) * max;
    };
    let dragging = false;
    bar.addEventListener('pointerdown', (event) => {
        const tab = event.target.closest('.ayv-bm-tab');
        if (tab && heads[tab.dataset.index]) {
            to(Math.max(0, heads[tab.dataset.index].ayvY - 16), true);
            return;
        }
        dragging = true;
        bar.setPointerCapture(event.pointerId);
        bar.classList.add('ayv-bm-drag');
        to(at(event.clientY), false);
        event.preventDefault();
    });
    bar.addEventListener('pointermove', (event) => {
        if (dragging) {
            to(at(event.clientY), false);
        }
    });
    const stop = () => {
        dragging = false;
        bar.classList.remove('ayv-bm-drag');
    };
    bar.addEventListener('pointerup', stop);
    bar.addEventListener('pointercancel', stop);

    const build = () => {
        const docH = html.scrollHeight;
        bar.hidden = !wide.matches || docH < window.innerHeight * 1.3;
        html.classList.toggle('ayv-has-bm', !bar.hidden);
        if (bar.hidden) {
            return;
        }
        span = Math.max(1, window.innerHeight - 2 * PAD);
        heads = Array.from(root.querySelectorAll('h2')).filter((el) => el.offsetParent !== null && el.textContent.trim());
        tabs.innerHTML = '';
        heads.forEach((el) => {
            const y = el.getBoundingClientRect().top + window.scrollY;
            const tab = document.createElement('span');
            tab.className = 'ayv-bm-tab';
            tab.textContent = el.textContent.trim().replace(/^[¿¡«"]+/, '').charAt(0).toUpperCase();
            tab.style.top = `${PAD + span * Math.min(1, y / docH)}px`;
            tab.dataset.name = el.textContent.trim();
            tab.dataset.index = String(tabs.children.length);
            tabs.appendChild(tab);
            el.ayvY = y;
        });
        current = -1;
        draw();
    };

    const draw = () => {
        queued = false;
        if (bar.hidden) {
            return;
        }
        const max = Math.max(1, html.scrollHeight - window.innerHeight);
        const progress = Math.min(1, Math.max(0, window.scrollY / max));
        ribbon.style.height = `${PAD + 14 + span * progress}px`;
        const line = window.scrollY + window.innerHeight * 0.35;
        let now = -1;
        heads.forEach((el, i) => {
            if (el.ayvY <= line) {
                now = i;
            }
        });
        if (now !== current) {
            current = now;
            Array.from(tabs.children).forEach((t, i) => t.classList.toggle('on', i === now));
            if (now >= 0) {
                const tab = tabs.children[now];
                name.textContent = tab.dataset.name;
                name.style.top = tab.style.top;
                name.classList.add('on');
                clearTimeout(hide);
                hide = setTimeout(() => name.classList.remove('on'), NAME_MS);
            }
        }
    };

    const later = () => {
        if (!queued) {
            queued = true;
            requestAnimationFrame(draw);
        }
    };
    window.addEventListener('scroll', later, {passive: true});
    window.addEventListener('resize', build);
    wide.addEventListener('change', build);
    if (window.ResizeObserver) {
        new ResizeObserver(build).observe(root);
    }
    build();
}
