import { updateWorkshopLine } from './workshop-line';

export function calculatePlanPreview(plan, hours, seats, travelUnits, supplied = {}) {
    const pricingParticipants = Number(plan.pricing_participants ?? 10);
    if (!(hours > 0 && seats > 0 && travelUnits >= 0 && Number.isInteger(pricingParticipants) && pricingParticipants > 0)) return null;
    if (![hours, seats, travelUnits, plan.travel_cents, ...plan.rules.map(rule => rule.rate_cents)].every(value => Number.isFinite(value) && value >= 0)) return null;
    const rules = plan.rules.filter(rule => !(rule.suppliable && supplied[rule.category_id]));
    const costs = { workshop: 0, hour: 0, participant: 0, travel: 0 };
    for (const rule of rules) {
        const units = { workshop: 1, hour: hours, participant: seats, travel: travelUnits }[rule.basis] || 0;
        costs[rule.basis] = (costs[rule.basis] || 0) + Math.round(rule.rate_cents * units);
    }
    const rates = settings => {
        const workshop = { kind: 'workshop', workshop_hours: hours, workshop_seats: seats, auto_pricing: true, gst_applicable: true, supplied_categories: supplied };
        const travel = { kind: 'travel', travel_hours: (travelUnits > 0 ? travelUnits : 1) / 4, auto_pricing: true, gst_applicable: true, supplied_categories: supplied };
        updateWorkshopLine(workshop, { ...settings, clean_prices: true }, true);
        updateWorkshopLine(travel, { ...settings, clean_prices: true }, true);
        const quote = item => {
            const rate = window.SM.priceRoundingForLine(item).original.unitInc;
            return window.SM.cleanPriceQuote(rate, item.quantity, window.SM.currentGstRate());
        };
        const travelRate = quote(travel);
        return { workshop: quote(workshop), travel: travelUnits > 0 ? travelRate : null, travelRate };
    };
    const base = rates({ ...plan, rounding_step: 0, travel_rounding_step: 0 });
    const planned = rates(plan);
    const ticket = Number(window.SM.suggestTicketPrice({ ...plan, rules }, hours, seats));
    const workshopRounding = planned.workshop, travelRounding = planned.travel;
    const unitGross = workshopRounding.current.unitInc;
    const workshopGross = workshopRounding.current.gross, travelGross = travelRounding?.current.gross ?? 0;
    const cleanWorkshopGross = workshopRounding.suggested.gross;
    const cleanTravelGross = travelRounding?.suggested.gross ?? 0;
    const cleanInvoiceGross = Math.round((cleanWorkshopGross + cleanTravelGross) * 100) / 100;
    const totalCost = Object.values(costs).reduce((a, b) => a + b, 0) / 100;
    const baseInvoiceGross = Math.round((base.workshop.current.gross + (base.travel?.current.gross ?? 0)) * 100) / 100;
    return { costs, totalCost, costPerParticipant: totalCost / seats, pricingParticipants,
        baseWorkshopRounding: base.workshop, baseTravelRounding: base.travel, baseInvoiceGross,
        workshopIncrement: Number(plan.rounding_step || 0), travelIncrement: Number(plan.travel_rounding_step || 0),
        planRoundingIncrease: Math.round((workshopGross + travelGross - baseInvoiceGross) * 100) / 100,
        totalRoundingIncrease: Math.round((cleanInvoiceGross - baseInvoiceGross) * 100) / 100,
        workshopGross, travelGross, invoiceGross: workshopGross + travelGross,
        unitNet: unitGross / (1 + window.SM.currentGstRate()), unitGross,
        quantity: hours * seats, workshopRounding, travelRounding, travelRate: planned.travelRate,
        roundingNeeded: !workshopRounding.clean || !!(travelRounding && !travelRounding.clean),
        cleanWorkshopGross, cleanTravelGross, cleanInvoiceGross,
        roundingIncrease: Math.round((cleanInvoiceGross - workshopGross - travelGross) * 100) / 100,
        participantGross: workshopGross / seats, ticket,
        travelUnitGross: travelUnits > 0 ? travelGross / travelUnits : 0 };
}

window.SM.allocationPlanPreview = (initialRules = []) => ({
    costRules: initialRules.map((rule, id) => ({ ...rule, id, rate: (Number(rule.rate_cents) / 100).toFixed(2),
        basis: rule.basis === 'venue_hour' ? 'hour' : rule.basis,
        suppliable: !!rule.suppliable || rule.basis === 'venue_hour', venue_default: !!rule.venue_default || rule.basis === 'venue_hour' })),
    nextCostRuleId: initialRules.length, preview: null, supplies: [],
    money(value) { return new Intl.NumberFormat('en-AU', { style: 'currency', currency: 'AUD' }).format(value || 0); },
    rate(value) { return '$' + window.SM.formatUnitPrice(value || 0); },
    approximateMoney(value) { return (Math.abs(Number(value) * 100 - Math.round(Number(value) * 100)) > 0.000001 ? '≈ ' : '') + this.money(value); },
    addCostRule(form) {
        if (this.costRules.length >= 100) return;
        const id = this.nextCostRuleId++;
        this.costRules.push({ id, category_id: '', basis: 'workshop', rate: '0.00', suppliable: false, venue_default: false });
        this.$nextTick(() => { this.refresh(form); form.querySelector(`[data-cost-rule="${id}"] select`)?.focus(); });
    },
    removeCostRule(id, form) {
        if (this.costRules.length <= 1) return;
        this.costRules = this.costRules.filter(rule => rule.id !== id);
        this.$nextTick(() => this.refresh(form));
    },
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
        const travelCents = Math.round(parsed.filter(rule => rule.basis === 'travel').reduce((sum, rule) => sum + rule.rate_cents, 0) * (1 + window.SM.currentGstRate()));
        const plan = { rules: parsed, pricing_participants: Number(data.get('pricing_participants')), travel_cents: travelCents, rounding_step: 0, travel_rounding_step: 0 };
        const seats = data.get('preview_seats') === '' ? Number(data.get('pricing_participants')) : Number(data.get('preview_seats'));
        this.preview = calculatePlanPreview(plan, Number(data.get('preview_hours')), seats, Number(data.get('preview_travel_units')) * 4, supplied);
    },
});
