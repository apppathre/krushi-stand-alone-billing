import Alpine from 'alpinejs';

/**
 * Invoice print picker with Marathi font-aware dialog.
 * Prefer Browser print when Devanagari PDF font is missing.
 */
Alpine.data('invoicePrintMenu', (cfg = {}) => ({
    open: false,
    dialog: false,
    mrFontOk: !!cfg.mrFontOk,
    defaultLang: cfg.defaultLang || 'en',
    defaultFormat: cfg.defaultFormat || 'standard',
    defaultSize: cfg.defaultSize || 'a4',
    pdfBase: cfg.pdfBase || '',
    browserBase: cfg.browserBase || '',
    dcBase: cfg.dcBase || '',
    pending: null,

    quickPrint() {
        this.choosePdf(this.defaultFormat, this.defaultSize, this.defaultLang);
    },

    choosePdf(format, size, lang) {
        this.open = false;
        this.pending = { format, size, lang, mode: 'pdf' };
        if (lang === 'mr' && !this.mrFontOk) {
            this.dialog = true;
            return;
        }
        this.openUrl(this.pdfUrl(format, size, lang));
    },

    openBrowser(format, size, lang) {
        this.open = false;
        this.pending = { format, size, lang, mode: 'browser' };
        this.openUrl(this.browserUrl(format, size, lang, true));
    },

    confirmBrowser() {
        const p = this.pending || {};
        this.dialog = false;
        this.openUrl(this.browserUrl(p.format || 'standard', p.size || 'a4', 'mr', true));
    },

    confirmEnglishPdf() {
        const p = this.pending || {};
        this.dialog = false;
        this.openUrl(this.pdfUrl(p.format || 'standard', p.size || 'a4', 'en'));
    },

    confirmMrPdf() {
        const p = this.pending || {};
        this.dialog = false;
        this.openUrl(this.pdfUrl(p.format || 'standard', p.size || 'a4', 'mr'));
    },

    pdfUrl(format, size, lang) {
        const u = new URL(this.pdfBase, window.location.origin);
        u.searchParams.set('format', format);
        u.searchParams.set('size', size);
        u.searchParams.set('lang', lang);
        return u.toString();
    },

    browserUrl(format, size, lang, autoprint = false) {
        const u = new URL(this.browserBase, window.location.origin);
        u.searchParams.set('format', format);
        u.searchParams.set('size', size);
        u.searchParams.set('lang', lang);
        if (autoprint) u.searchParams.set('autoprint', '1');
        return u.toString();
    },

    openUrl(url) {
        window.open(url, '_blank', 'noopener');
    },
}));
