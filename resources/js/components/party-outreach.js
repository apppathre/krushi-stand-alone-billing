import Alpine from 'alpinejs';

/**
 * Outstanding follow-up: Call (tel:) + WhatsApp (wa.me) with master template + optional statement link.
 */
Alpine.data('partyOutreach', (composeUrl, defaults = {}) => ({
    composeUrl,
    withLink: defaults.with_link !== false,
    includeBills: defaults.include_bills !== false,
    allowPayment: defaults.allow_payment !== false,
    busy: false,
    last: null,

    async open(channel) {
        if (this.busy) return;
        this.busy = true;
        try {
            const res = await fetch(this.composeUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({
                    with_link: this.withLink,
                    include_bills: this.includeBills,
                    allow_payment: this.allowPayment,
                }),
            });
            const data = await res.json();
            if (!res.ok || !data.ok) throw new Error(data.message || 'Failed');
            this.last = data;

            if (channel === 'call') {
                if (!data.tel_url) {
                    Alpine.store('ui').toast('No phone on this party', 'error');
                    return;
                }
                window.location.href = data.tel_url;
                return;
            }

            if (channel === 'whatsapp') {
                if (!data.wa_url) {
                    Alpine.store('ui').toast('No phone on this party', 'error');
                    return;
                }
                window.open(data.wa_url, '_blank', 'noopener');
                return;
            }

            if (channel === 'copy') {
                const text = data.url || data.message;
                await navigator.clipboard.writeText(text);
                Alpine.store('ui').toast(data.url ? 'Link copied' : 'Message copied');
            }
        } catch (e) {
            Alpine.store('ui').toast(e.message || 'Could not prepare message', 'error');
        } finally {
            this.busy = false;
        }
    },
}));
