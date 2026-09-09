// Local date strings keep recurring sessions at the same wall-clock time, including across DST.
window.SM.courseEditor = (format, sessions) => ({
    workshopFormat: format,
    courseSessions: sessions || [],
    generateCount: 8,
    generateMinutes: 60,
    generateStart: '',
    scheduleError: '',
    initCourseSchedule() {
        this.generateStart = this.generateStart || this.manualStartsAt || '';
        this.$watch('manualStartsAt', (value, previous) => {
            if (!this.generateStart || this.generateStart === previous) this.generateStart = value;
        });
    },
    courseTeachingHours() {
        return this.workshopFormat === 'course'
            ? this.courseSessions.reduce((sum, session) => sum + Math.max(0, (new Date(session.ends_at) - new Date(session.starts_at)) / 3600000 || 0), 0)
            : (new Date(this.manualEndsAt) - new Date(this.manualStartsAt)) / 3600000;
    },
    sessionChanged() { this.$dispatch('workshop-pricing-changed'); },
    addSession() {
        this.courseSessions.push({ id: crypto.randomUUID(), label: '', starts_at: '', ends_at: '' });
    },
    async generateSessions() {
        const start = new Date(this.generateStart || this.manualStartsAt);
        const count = Number(this.generateCount), minutes = Number(this.generateMinutes);
        if (!Number.isFinite(start.getTime()) || !Number.isInteger(count) || count < 1 || count > 104 || minutes < 1) {
            this.scheduleError = 'Choose a first session, 1–104 weeks and a positive duration.';
            return;
        }
        if (this.courseSessions.length) {
            const result = await window.SM.confirm('Replace session schedule?', 'Sessions with recorded attendance cannot be removed.', 'Replace sessions');
            if (!result?.isConfirmed) return;
        }
        this.scheduleError = '';
        const local = date => SM.toLocalISOString(date).slice(0, 16);
        this.courseSessions = Array.from({ length: count }, (_, i) => {
            const from = new Date(start); from.setDate(from.getDate() + i * 7);
            const to = new Date(from); to.setMinutes(to.getMinutes() + minutes);
            return { id: crypto.randomUUID(), label: '', starts_at: local(from), ends_at: local(to) };
        });
        this.manualStartsAt = this.courseSessions[0].starts_at;
        this.manualEndsAt = this.courseSessions[count - 1].ends_at;
        this.sessionChanged();
    },
});
