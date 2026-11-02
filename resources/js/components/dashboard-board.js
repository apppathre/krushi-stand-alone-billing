import Alpine from 'alpinejs';

/**
 * Progressive dashboard: shell paints first; pulse / figures / recent / calendar load over Ajax.
 */
Alpine.data('dashboardBoard', (urls = {}) => ({
    urls,
    pulse: null,
    figures: null,
    recentHtml: '',
    calendar: null,
    pulseError: false,
    figuresError: false,
    recentError: false,
    calendarError: false,
    pulseLoading: true,
    figuresLoading: true,
    recentLoading: true,
    calendarLoading: true,
    calMonth: null, // YYYY-MM-01

    init() {
        const n = new Date();
        this.calMonth = `${n.getFullYear()}-${String(n.getMonth() + 1).padStart(2, '0')}-01`;
        this.refresh();
    },

    async refresh() {
        this.pulseLoading = true;
        this.figuresLoading = true;
        this.recentLoading = true;
        this.calendarLoading = true;
        this.pulseError = false;
        this.figuresError = false;
        this.recentError = false;
        this.calendarError = false;

        await Promise.all([
            this.loadPulse(),
            this.loadFigures(),
            this.loadRecent(),
            this.loadCalendar(),
        ]);
    },

    async loadPulse() {
        try {
            const res = await fetch(this.urls.pulse, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) throw new Error('pulse');
            this.pulse = await res.json();
        } catch (_) {
            this.pulseError = true;
            this.pulse = null;
        } finally {
            this.pulseLoading = false;
        }
    },

    async loadFigures() {
        try {
            const res = await fetch(this.urls.figures, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) throw new Error('figures');
            this.figures = await res.json();
        } catch (_) {
            this.figuresError = true;
            this.figures = null;
        } finally {
            this.figuresLoading = false;
        }
    },

    async loadRecent() {
        try {
            const res = await fetch(this.urls.recent, {
                headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) throw new Error('recent');
            this.recentHtml = await res.text();
        } catch (_) {
            this.recentError = true;
            this.recentHtml = '';
        } finally {
            this.recentLoading = false;
        }
    },

    calLabel() {
        if (!this.calMonth) return '';
        const d = new Date(this.calMonth + 'T12:00:00');
        return d.toLocaleString(undefined, { month: 'long', year: 'numeric' });
    },

    shiftMonth(delta) {
        const d = new Date(this.calMonth + 'T12:00:00');
        d.setMonth(d.getMonth() + delta);
        this.calMonth = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-01`;
        this.loadCalendar();
    },

    calRange() {
        const start = new Date(this.calMonth + 'T12:00:00');
        const end = new Date(start.getFullYear(), start.getMonth() + 1, 0);
        const iso = (x) => `${x.getFullYear()}-${String(x.getMonth() + 1).padStart(2, '0')}-${String(x.getDate()).padStart(2, '0')}`;
        return { from: iso(start), to: iso(end) };
    },

    calendarDays() {
        if (!this.calMonth) return [];
        const start = new Date(this.calMonth + 'T12:00:00');
        const y = start.getFullYear();
        const m = start.getMonth();
        const firstDow = new Date(y, m, 1).getDay(); // 0 Sun
        const daysInMonth = new Date(y, m + 1, 0).getDate();
        const eventsByDay = {};
        (this.calendar?.events || []).forEach((ev) => {
            const day = Number(String(ev.date).slice(8, 10));
            if (!eventsByDay[day]) eventsByDay[day] = [];
            eventsByDay[day].push(ev);
        });
        const cells = [];
        for (let i = 0; i < firstDow; i++) cells.push({ empty: true });
        const today = new Date();
        const isThisMonth = today.getFullYear() === y && today.getMonth() === m;
        for (let d = 1; d <= daysInMonth; d++) {
            const list = eventsByDay[d] || [];
            cells.push({
                empty: false,
                day: d,
                today: isThisMonth && today.getDate() === d,
                events: list,
                hasRecv: list.some((e) => e.kind === 'receivable'),
                hasPay: list.some((e) => e.kind === 'payable'),
                hasLic: list.some((e) => e.kind === 'licence' || e.kind === 'licence_expired'),
            });
        }
        return cells;
    },

    dayEvents(day) {
        return (this.calendar?.events || []).filter((e) => Number(String(e.date).slice(8, 10)) === day);
    },

    async loadCalendar() {
        if (!this.urls.calendar) {
            this.calendarLoading = false;
            return;
        }
        this.calendarLoading = true;
        this.calendarError = false;
        try {
            const { from, to } = this.calRange();
            const url = `${this.urls.calendar}?from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}`;
            const res = await fetch(url, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) throw new Error('calendar');
            this.calendar = await res.json();
        } catch (_) {
            this.calendarError = true;
            this.calendar = null;
        } finally {
            this.calendarLoading = false;
        }
    },
}));
