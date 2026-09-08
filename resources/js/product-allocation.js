export function productTargets(rules, quantity, revenue) {
    const split = (amount, weights) => {
        const total = Object.values(weights).reduce((sum, value) => sum + Number(value), 0);
        let sum = 0, previous = 0;
        return Object.fromEntries(Object.entries(weights).map(([id, weight]) => {
            sum += Number(weight);
            const next = total > 0 ? Math.round(Math.max(0, amount) * sum / total) : 0;
            const value = next - previous;
            previous = next;
            return [id, value];
        }));
    };
    const fixed = Object.fromEntries(Object.entries(rules.fixed || {}).map(([id, value]) => [id, Math.round(Number(value) * Math.max(0, quantity))]));
    const cost = Object.values(fixed).reduce((sum, value) => sum + value, 0);
    const targets = cost > revenue ? split(Math.max(0, revenue), fixed) : fixed;
    const percent = { ...rules.percent };
    percent.surplus = 10000 - Object.values(percent).reduce((sum, value) => sum + Number(value), 0);
    for (const [id, value] of Object.entries(split(Math.max(0, revenue - cost), percent))) {
        if (id !== 'surplus') targets[id] = (targets[id] || 0) + value;
    }
    return targets;
}
window.SM = window.SM || {};
window.SM.productLineCostAllocations = (items, total, snapshots) => {
    const weights = items.map(item => Math.max(0, Math.round(window.SM.lineAmounts(item).net * 100)));
    const sum = weights.reduce((value, weight) => value + weight, 0);
    let cumulative = 0, previous = 0;
    const targets = {};
    items.forEach((item, index) => {
        cumulative += weights[index];
        const next = sum > 0 ? Math.round(Math.max(0, total) * cumulative / sum) : 0;
        const revenue = next - previous;
        previous = next;
        const snapshot = snapshots[item.id];
        if (item.kind !== 'product' || !snapshot || String(snapshot.product_id) !== String(item.source_id)) return;
        for (const [id, amount] of Object.entries(productTargets(snapshot.rules, Number(item.quantity), revenue))) {
            targets[id] = (targets[id] || 0) + amount;
        }
    });
    return targets;
};
