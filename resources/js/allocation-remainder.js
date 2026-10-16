window.SM = window.SM || {};

window.SM.allocationRemainder = (value = null, defaults = {}) => {
    const entries = Object.entries(value || {}).filter(([, amount]) => Number(amount) > 0);
    return {
        remainderMode: value === null ? 'default' : entries.length === 0 ? 'cash'
            : entries.length === 1 && Number(entries[0][1]) === 10000 ? entries[0][0] : 'split',
        remainderPercent: Object.fromEntries(entries.map(([id, amount]) => [id, Number(amount) / 100])),
        remainderDefault: defaults || {},
        remainderRules() {
            if (this.remainderMode === 'default') return null;
            if (this.remainderMode === 'cash') return {};
            if (this.remainderMode !== 'split') return { [this.remainderMode]: 10000 };
            const rules = {};
            for (const [id, value] of Object.entries(this.remainderPercent)) {
                if (String(value ?? '').trim() === '') continue;
                const percent = Number(value);
                const basisPoints = Math.round(percent * 100);
                if (!Number.isFinite(percent) || percent < 0 || percent > 100 || Math.abs(percent * 100 - basisPoints) > 0.000001) return null;
                if (basisPoints > 0) rules[id] = basisPoints;
            }
            return Object.values(rules).reduce((sum, amount) => sum + amount, 0) > 10000 ? null : rules;
        },
        effectiveRemainder() {
            return this.remainderMode === 'default' ? this.remainderDefault : this.remainderRules();
        },
        remainderJson() {
            const rules = this.remainderRules();
            return JSON.stringify(rules === null && this.remainderMode !== 'default' ? { invalid: null } : rules);
        },
        remainderCashPercent() {
            const rules = this.effectiveRemainder();
            return rules === null ? null : (10000 - Object.values(rules).reduce((sum, amount) => sum + Number(amount), 0)) / 100;
        },
    };
};
