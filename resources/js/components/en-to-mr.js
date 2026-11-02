/**
 * English → Marathi (Devanagari) for name fields.
 * Prefers Google Input Tools when online; falls back to local phonetic.
 * Auto-fills Marathi until the user edits that field manually.
 */

const VOWELS = [
    ['aa', 'आ'], ['ai', 'ऐ'], ['au', 'औ'], ['ee', 'ई'], ['ii', 'ई'],
    ['oo', 'ऊ'], ['uu', 'ऊ'], ['a', 'अ'], ['i', 'इ'], ['u', 'उ'],
    ['e', 'ए'], ['o', 'ओ'],
];

const CONSONANTS = [
    ['ksh', 'क्ष्'], ['dny', 'ज्ञ्'], ['chh', 'छ्'], ['shh', 'ष्'],
    ['th', 'थ्'], ['dh', 'ध्'], ['ph', 'फ्'], ['bh', 'भ्'], ['kh', 'ख्'],
    ['gh', 'घ्'], ['ch', 'च्'], ['jh', 'झ्'], ['sh', 'श्'], ['ng', 'ङ्'],
    ['ny', 'ञ्'], ['tr', 'त्र्'], ['gy', 'ज्ञ्'], ['jn', 'ज्ञ्'],
    ['k', 'क्'], ['g', 'ग्'], ['c', 'च्'], ['j', 'ज्'], ['t', 'ट्'], ['d', 'ड्'],
    ['n', 'न्'], ['p', 'प्'], ['b', 'ब्'], ['m', 'म्'], ['y', 'य्'], ['r', 'र्'],
    ['l', 'ल्'], ['v', 'व्'], ['w', 'व्'], ['s', 'स्'], ['h', 'ह्'], ['f', 'फ्'],
    ['z', 'झ्'], ['q', 'क्'], ['x', 'क्स'],
];

const MATRAS = {
    आ: 'ा', इ: 'ि', ई: 'ी', उ: 'ु', ऊ: 'ू', ए: 'े', ऐ: 'ै', ओ: 'ो', औ: 'ौ', अ: '',
};

function matchLongest(list, text, i) {
    for (const [lat, dev] of list) {
        if (text.startsWith(lat, i)) {
            return { lat, dev, len: lat.length };
        }
    }
    return null;
}

/** Offline phonetic Latin → Devanagari (approx). */
export function enToMr(input) {
    if (!input) return '';
    const raw = String(input);
    const text = raw.toLowerCase();
    let out = '';
    let i = 0;

    while (i < text.length) {
        const ch = text[i];
        if (ch === ' ' || ch === '-' || ch === '.' || ch === '/') {
            out += ch;
            i += 1;
            continue;
        }
        if (ch === "'") {
            i += 1;
            continue;
        }
        if (!/[a-z]/.test(ch)) {
            out += raw[i];
            i += 1;
            continue;
        }

        const con = matchLongest(CONSONANTS, text, i);
        if (con) {
            i += con.len;
            const vow = matchLongest(VOWELS, text, i);
            if (vow) {
                out += con.dev.replace(/्$/, '') + (MATRAS[vow.dev] ?? '');
                i += vow.len;
            } else {
                out += con.dev.replace(/्$/, '');
            }
            continue;
        }

        const vow = matchLongest(VOWELS, text, i);
        if (vow) {
            out += vow.dev;
            i += vow.len;
            continue;
        }

        out += ch;
        i += 1;
    }

    return out;
}

/** Online: Google Marathi transliteration (best quality). */
export async function enToMrOnline(text) {
    const q = String(text || '').trim();
    if (!q) return '';
    try {
        const url = 'https://inputtools.google.com/request?text='
            + encodeURIComponent(q)
            + '&itc=mr-t-i0-und&num=1&cp=0&cs=1&ie=utf-8&oe=utf-8&app=demopage';
        const res = await fetch(url);
        if (!res.ok) return enToMr(q);
        const data = await res.json();
        if (data?.[0] === 'SUCCESS' && data[1]?.[0]?.[1]?.[0]) {
            return String(data[1][0][1][0]);
        }
    } catch {
        // offline / blocked
    }

    return enToMr(q);
}

function findPair(form, enName, mrName) {
    const en = form.querySelector(`[name="${enName}"]`);
    const mr = form.querySelector(`[name="${mrName}"]`);
    if (!en || !mr || en.dataset.mrBound === '1') return null;
    return { en, mr };
}

function bindPair(en, mr) {
    en.dataset.mrBound = '1';
    let lastAuto = '';
    let manual = (mr.value || '').trim() !== '';
    let timer = null;
    let seq = 0;

    const applyLocal = () => {
        if (manual) return;
        const next = enToMr(en.value);
        lastAuto = next;
        if (mr.value !== next) {
            mr.value = next;
            mr.dispatchEvent(new Event('input', { bubbles: true }));
        }
    };

    const apply = () => {
        if (manual) return;
        applyLocal(); // instant feedback
        const my = ++seq;
        const typed = en.value;
        clearTimeout(timer);
        timer = setTimeout(async () => {
            if (manual || my !== seq) return;
            const next = await enToMrOnline(typed);
            if (manual || my !== seq) return;
            lastAuto = next;
            if (mr.value !== next) {
                mr.value = next;
                mr.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }, 320);
    };

    en.addEventListener('input', apply);
    en.addEventListener('change', apply);

    mr.addEventListener('input', () => {
        if (mr.value !== lastAuto) {
            manual = true;
        }
    });

    mr.addEventListener('blur', () => {
        if ((mr.value || '').trim() === '') {
            manual = false;
            apply();
        }
    });

    if (!manual && (en.value || '').trim()) {
        apply();
    }
}

/** Bind all English/Marathi name pairs under a root (document or drawer). */
export function bindNameMrPairs(root = document) {
    const scope = root?.querySelectorAll ? root : document;
    scope.querySelectorAll('form').forEach((form) => {
        [
            findPair(form, 'name', 'name_mr'),
            findPair(form, 'label', 'label_mr'),
        ].filter(Boolean).forEach(({ en, mr }) => bindPair(en, mr));
    });
}

export function registerEnToMr() {
    const run = (root) => bindNameMrPairs(root || document);
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => run(document));
    } else {
        run(document);
    }
    window.KrushiNameMr = { enToMr, enToMrOnline, bind: run };
}
