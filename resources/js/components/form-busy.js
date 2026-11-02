/**
 * Spinner on submit buttons for full-page POSTs (login, logout, save, filters…).
 * Matches TMS/ERP FormBusy: do not disable the clicked button mid-submit
 * (browsers may cancel navigation). AJAX drawer forms opt out via data-ajax-form.
 */
(function () {
    function waitLabel(btn, form) {
        const custom = (btn && btn.getAttribute('data-busy-label')) || (form && form.getAttribute('data-busy-label'));
        if (custom) return custom;
        const text = ((btn && (btn.innerText || btn.textContent)) || '').replace(/\s+/g, ' ').trim().toLowerCase();
        if (/log ?in|sign in/.test(text)) return 'Signing in…';
        if (/send|email/.test(text)) return 'Sending…';
        if (/verify|confirm/.test(text)) return 'Verifying…';
        if (/delete|remove/.test(text)) return 'Deleting…';
        if (/save|update|reset/.test(text)) return 'Saving…';
        if (/post|pay|receive/.test(text)) return 'Posting…';
        if (/logout|sign out/.test(text)) return 'Signing out…';
        if (/filter|search|apply/.test(text)) return 'Loading…';
        return 'Please wait…';
    }

    function isSubmitControl(el) {
        if (!el || el.nodeType !== 1) return false;
        if (el.matches('input[type="submit"], button[type="submit"]')) return true;
        if (el.matches('button') && !el.hasAttribute('type')) return true;
        return false;
    }

    function submitButtons(form) {
        const list = [];
        form.querySelectorAll('button, input[type="submit"]').forEach((b) => {
            if (isSubmitControl(b) && !list.includes(b)) list.push(b);
        });
        if (form.id) {
            document.querySelectorAll(`[form="${form.id}"]`).forEach((b) => {
                if (isSubmitControl(b) && !list.includes(b)) list.push(b);
            });
        }
        return list;
    }

    function spinnerEl() {
        const spin = document.createElement('span');
        spin.className = 'ui-spinner ui-spinner--btn';
        spin.setAttribute('aria-hidden', 'true');
        return spin;
    }

    function markBusy(btn, label) {
        if (!btn || btn.getAttribute('data-busy-on') === '1') return;
        btn.setAttribute('data-busy-on', '1');
        if (btn.dataset.origHtml == null) btn.dataset.origHtml = btn.innerHTML;
        btn.setAttribute('aria-busy', 'true');
        btn.classList.add('is-busy');
        if (btn.tagName === 'INPUT') {
            if (btn.dataset.origValue == null) btn.dataset.origValue = btn.value;
            btn.value = label;
            return;
        }
        btn.replaceChildren();
        if (btn.classList.contains('side-logout') || btn.classList.contains('btn-icon')) {
            if (btn.classList.contains('side-logout')) {
                const ico = document.createElement('span');
                ico.className = 'side-ico';
                ico.appendChild(spinnerEl());
                const lab = document.createElement('span');
                lab.className = 'side-label';
                lab.textContent = label;
                btn.appendChild(ico);
                btn.appendChild(lab);
                return;
            }
            btn.appendChild(spinnerEl());
            return;
        }
        btn.appendChild(spinnerEl());
        const text = document.createElement('span');
        text.textContent = label;
        btn.appendChild(text);
    }

    function restore(btn) {
        if (!btn || btn.getAttribute('data-busy-on') !== '1') return;
        btn.removeAttribute('data-busy-on');
        btn.removeAttribute('aria-busy');
        btn.classList.remove('is-busy');
        btn.disabled = false;
        if (btn.tagName === 'INPUT' && btn.dataset.origValue != null) {
            btn.value = btn.dataset.origValue;
        }
        if (btn.dataset.origHtml != null) btn.innerHTML = btn.dataset.origHtml;
    }

    function markFormBusy(form, btn) {
        if (form.getAttribute('data-busy') === '1') return false;
        form.setAttribute('data-busy', '1');
        const label = waitLabel(btn, form);
        const buttons = submitButtons(form);
        if (buttons.length) {
            buttons.forEach((other) => markBusy(other, label));
            return true;
        }
        if (btn) markBusy(btn, label);
        return true;
    }

    function clearAllBusy() {
        document.querySelectorAll('form[data-busy], form.is-busy').forEach((form) => {
            form.removeAttribute('data-busy');
            form.classList.remove('is-busy');
            submitButtons(form).forEach(restore);
        });
        document.querySelectorAll('[data-busy-on="1"]').forEach(restore);
        const overlay = document.getElementById('krushi-busy');
        if (overlay) overlay.hidden = true;
    }

    function ensureOverlay() {
        let el = document.getElementById('krushi-busy');
        if (el) return el;
        el = document.createElement('div');
        el.id = 'krushi-busy';
        el.className = 'krushi-busy';
        el.hidden = true;
        el.setAttribute('role', 'status');
        el.innerHTML = '<span class="ui-spinner" aria-hidden="true"></span><span class="krushi-busy__lab">Please wait…</span>';
        document.body.appendChild(el);
        return el;
    }

    window.FormBusy = {
        set(form, btn, label) {
            if (form) form.setAttribute('data-busy', '1');
            const text = label || waitLabel(btn, form);
            if (btn) markBusy(btn, text);
            else if (form) submitButtons(form).forEach((b) => markBusy(b, text));
        },
        clear(form) {
            if (form) {
                form.removeAttribute('data-busy');
                form.classList.remove('is-busy');
                submitButtons(form).forEach(restore);
            } else {
                clearAllBusy();
            }
        },
        mark(btn, label) {
            markBusy(btn, label || 'Please wait…');
        },
        restore,
        showOverlay(label) {
            const el = ensureOverlay();
            const lab = el.querySelector('.krushi-busy__lab');
            if (lab && label) lab.textContent = label;
            el.hidden = false;
        },
        hideOverlay() {
            const el = document.getElementById('krushi-busy');
            if (el) el.hidden = true;
        },
    };

    document.addEventListener('submit', (e) => {
        if (e.defaultPrevented) return;
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (form.hasAttribute('data-ajax-form') || form.hasAttribute('data-no-busy') || form.hasAttribute('data-filter')) return;

        if (form.getAttribute('data-busy') === '1') {
            e.preventDefault();
            return;
        }

        let btn = e.submitter;
        if (!isSubmitControl(btn)) {
            btn = form.querySelector('button[type="submit"], input[type="submit"], button:not([type])');
        }
        const label = waitLabel(btn, form);
        markFormBusy(form, btn);

        // Full-page overlay on invoice / stock / voucher desks so the page clearly shows loading.
        if (form.classList.contains('txn-desk') || form.hasAttribute('data-page-busy')) {
            window.FormBusy.showOverlay(label);
        }

        window.setTimeout(() => {
            if (form.getAttribute('data-busy') !== '1') return;
            clearAllBusy();
            const box = form.querySelector('[data-login-retry]');
            if (box) {
                box.hidden = false;
                box.textContent = 'Request did not finish. Check your connection, then try again.';
            }
        }, 20000);
    });

    // Click handlers for non-form busy buttons (e.g. drawer open) can call FormBusy.mark
    window.addEventListener('pageshow', clearAllBusy);
    document.addEventListener('DOMContentLoaded', clearAllBusy);
})();
