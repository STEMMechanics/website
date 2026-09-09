window.SM = window.SM || {};
window.SM.workshopHoldCountdown = expiresAt => ({
    remainingSeconds: 0,
    timer: null,
    init() {
        this.update();
        if (this.remainingSeconds > 0) this.timer = setInterval(() => this.update(), 1000);
    },
    update() {
        this.remainingSeconds = Math.max(0, Math.floor((Date.parse(expiresAt) - Date.now()) / 1000) || 0);
        if (this.remainingSeconds === 0) clearInterval(this.timer);
    },
    get timeRemaining() {
        return `${String(Math.floor(this.remainingSeconds / 60)).padStart(2, '0')}:${String(this.remainingSeconds % 60).padStart(2, '0')}`;
    },
    destroy() { clearInterval(this.timer); },
});
