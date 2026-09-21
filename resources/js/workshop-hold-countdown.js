window.SM = window.SM || {};
window.SM.workshopHoldCountdown = (expiresAt, bookingId = null) => ({
    expiresAt,
    bookingId,
    remainingSeconds: 0,
    refreshHandler: null,
    timer: null,
    init() {
        this.refreshHandler = event => {
            if (this.bookingId !== event.detail.booking_id) return;
            this.expiresAt = event.detail.expires_at;
            clearInterval(this.timer);
            this.update();
            if (this.remainingSeconds > 0) this.timer = setInterval(() => this.update(), 1000);
        };
        window.addEventListener('workshop-hold-updated', this.refreshHandler);
        this.update();
        if (this.remainingSeconds > 0) this.timer = setInterval(() => this.update(), 1000);
    },
    update() {
        this.remainingSeconds = Math.max(0, Math.floor((Date.parse(this.expiresAt) - Date.now()) / 1000) || 0);
        if (this.remainingSeconds === 0) clearInterval(this.timer);
    },
    get timeRemaining() {
        return `${String(Math.floor(this.remainingSeconds / 60)).padStart(2, '0')}:${String(this.remainingSeconds % 60).padStart(2, '0')}`;
    },
    destroy() { clearInterval(this.timer); window.removeEventListener('workshop-hold-updated', this.refreshHandler); },
});
