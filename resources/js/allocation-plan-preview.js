import { updateWorkshopLine } from './workshop-line';

export function calculatePlanPreview(plan, hours, seats, travelUnits, supplied = {}) {
    if (!(hours > 0 && seats > 0 && travelUnits >= 0)) return null;
    if (![hours, seats, travelUnits, plan.travel_cents, ...plan.rules.map(rule => rule.rate_cents)].every(value => Number.isFinite(value) && value >= 0)) return null;
    const rules = plan.rules.filter(rule => !(rule.suppliable && supplied[rule.category_id]));
    const costs = { workshop: 0, hour: 0, participant: 0, travel: 0 };
    for (const rule of rules) {
        const units = { workshop: 1, hour: hours, participant: seats, travel: travelUnits }[rule.basis] || 0;
        costs[rule.basis] = (costs[rule.basis] || 0) + Math.round(rule.rate_cents * units);
    }
    const workshop = { kind: 'workshop', workshop_hours: hours, workshop_seats: seats, auto_pricing: true, gst_applicable: true, supplied_categories: supplied };
    const travel = { kind: 'travel', travel_units: travelUnits, auto_pricing: true, gst_applicable: true, supplied_categories: supplied };
    updateWorkshopLine(workshop, plan);
    updateWorkshopLine(travel, plan);
    const gross = item => { const amounts = window.SM.lineAmounts(item); return amounts.net + amounts.tax; };
    const ticket = Number(window.SM.suggestTicketPrice({ ...plan, rules }, hours, seats));
    const workshopGross = gross(workshop), travelGross = travelUnits > 0 ? gross(travel) : 0;
    return { costs, totalCost: Object.values(costs).reduce((a, b) => a + b, 0) / 100,
        workshopGross, travelGross, invoiceGross: workshopGross + travelGross,
        unitNet: Number(workshop.unit_price), unitGross: Number(workshop.details_json?.inclusive_unit_price ?? (Number(workshop.unit_price) * 1.1).toFixed(2)),
        participantGross: workshopGross / seats, ticket,
        travelUnitGross: travelUnits > 0 ? travelGross / travelUnits : 0 };
}

window.SM.allocationPlanPreview = () => ({
    extra: 0, preview: null, supplies: [], travelBase: '0.00',
    money(value) { return new Intl.NumberFormat('en-AU', { style: 'currency', currency: 'AUD' }).format(value || 0); },
    refresh(form) {
        const data = new FormData(form), rules = {}, supplied = {};
        for (const [name, value] of data.entries()) {
            const match = name.match(/^rules\[(\d+)\]\[(\w+)\]$/);
            if (match) (rules[match[1]] ||= {})[match[2]] = value;
            const selected = name.match(/^preview_supplied\[(\d+)\]$/);
            if (selected) supplied[selected[1]] = value === '1';
        }
        const parsed = Object.values(rules).map(rule => ({ ...rule, rate_cents: Math.round(Number(rule.rate) * 100), suppliable: rule.suppliable === '1' }));
        const ids = new Set();
        this.supplies = parsed.filter(rule => rule.suppliable && !ids.has(rule.category_id) && ids.add(rule.category_id)).map(rule => ({ id: rule.category_id, name: form.querySelector(`[name="rules[${Object.keys(rules).find(key => rules[key].category_id === rule.category_id)}][category_id]"]`)?.selectedOptions[0]?.textContent.trim() || 'Cost centre' }));
        this.travelBase = (parsed.filter(rule => rule.basis === 'travel').reduce((sum, rule) => sum + rule.rate_cents, 0) * 1.1 / 100).toFixed(2);
        const plan = { rules: parsed, travel_cents: Math.round(Number(data.get('travel_price')) * 100), rounding_step: Number(data.get('rounding_step')), travel_rounding_step: Number(data.get('travel_rounding_step')) };
        const seats = data.get('preview_seats') === '' ? Number(data.get('pricing_participants')) : Number(data.get('preview_seats'));
        this.preview = calculatePlanPreview(plan, Number(data.get('preview_hours')), seats, Number(data.get('preview_travel_units')) * 4, supplied);
    },
});
