import { defineStore } from "pinia";
import { clamp } from "../helpers/utils";

export interface ProgressStore {
    spinner: number;
    status: number;
    opacity: number;
    queue: number;
    timeoutID: number | null;
}

export const useProgressStore = defineStore({
    id: "progress",
    state: (): ProgressStore => ({
        spinner: 0,
        status: 0,
        opacity: 0,
        queue: 0,
        timeoutID: null,
    }),

    actions: {
        start() {
            if (this.queue == 0) {
                this.set(0);

                const work = () => {
                    window.setTimeout(() => {
                        if (this.status != null) {
                            this._trickle();
                            work();
                        }
                    }, 200);
                };

                work();

                if (this.opacity == 0) {
                    if (this.timeoutID != null) {
                        window.clearTimeout(this.timeoutID);
                    }

                    this.timeoutID = window.setTimeout(() => {
                        this._show();
                        this.timeoutID = null;
                    }, 200);
                }

                if (this.spinner == 0) {
                    this.spinner = 1;
                }
            }

            ++this.queue;
        },

        set(number: number) {
            this.status = clamp(number, 0.08, 1);
        },

        finish() {
            if (this.queue > 0) {
                --this.queue;
            }
        },

        _trickle() {
            const n = this.status;

            if (this.queue == 0 && this.timeoutID == null) {
                this.timeoutID = window.setTimeout(() => {
                    this.set(1);
                    this.timeoutID = null;

                    this.timeoutID = window.setTimeout(() => {
                        this._hide();
                        this.timeoutID = null;
                    }, 500);
                }, 200);
            }

            if (n == 0) {
                this.start();
            } else if (n < 1) {
                let amount = 0;

                if (n >= 0 && n < 0.2) {
                    amount = 0.1;
                } else if (n >= 0.2 && n < 0.5) {
                    amount = 0.04;
                } else if (n >= 0.5 && n < 0.8) {
                    amount = 0.02;
                } else if (n >= 0.8 && n < 0.99) {
                    amount = 0.005;
                } else {
                    amount = 0;
                }

                this.set(clamp(n + amount, 0, 0.994));
            }
        },

        _show() {
            this.opacity = 1;
        },

        _hide() {
            this.opacity = 0;

            if (this.spinner == 1) {
                this.spinner = 0;
            }
        },
    },
});
