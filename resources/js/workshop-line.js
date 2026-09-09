const pricingPlans = new WeakMap();
export function updateWorkshopLine(item, plan = null, inclusive = null) {
    const cached = pricingPlans.get(item);
    plan = plan ?? cached?.plan;
    inclusive = inclusive ?? cached?.inclusive ?? false;
    if (plan) pricingPlans.set(item, { plan, inclusive });
    if (item.kind === 'multi_workshop') {
        updateMultipleWorkshops(item, plan, inclusive);
        return;
    }
    if (item.kind === 'travel' && item.travel_hours !== '' && item.travel_hours != null) {
        item.travel_units = Number(item.travel_hours) * 4;
        item.quantity = Number(item.travel_hours);
        item.details_json = { ...(item.details_json || {}), travel: { billable_units: item.travel_units, quantity_basis: 'hours', supplied_categories: { ...(item.supplied_categories || {}) } } };
    } else if (item.kind === 'travel' && item.travel_units !== '' && item.travel_units != null) {
        item.quantity = Number(item.travel_units);
        item.details_json = { ...(item.details_json || {}), travel: { billable_units: Number(item.travel_units), supplied_categories: { ...(item.supplied_categories || {}) } } };
    }
    if (item.kind === 'travel') applyPlanPrice(item, plan, inclusive);
    if (item.kind !== 'workshop') return;
    const hours = Number(item.workshop_hours), seats = Number(item.workshop_seats);
    if (hours > 0 && seats > 0) {
        item.quantity = Math.round(hours * seats * 100) / 100;
        item.details_json = { ...(item.details_json || {}), workshop: { hours, seats, date: item.workshop_date || item.details_json?.workshop?.date || null, venue_supplied: !!item.venue_supplied, supplied_categories: { ...(item.supplied_categories || {}) } } };
        applyPlanPrice(item, plan, inclusive);
    }
}
function applyPlanPrice(item, plan, inclusive) {
    if (!plan || !item.auto_pricing || !(Number(item.quantity) > 0)) return;
    const taxed = item.gst_applicable !== false;
    let ex;
    if (item.kind === 'travel') {
        const travelRules = (plan.rules || []).filter(rule => rule.basis === 'travel');
        ex = travelRules.length ? travelRules.reduce((sum, rule) => sum + Number(rule.rate_cents), 0) : Number(plan.travel_cents) / (taxed ? 1.1 : 1);
        ex = Math.max(0, ex - (plan.rules || []).filter(rule => rule.basis === 'travel' && rule.suppliable && item.supplied_categories?.[rule.category_id]).reduce((total, rule) => total + Number(rule.rate_cents), 0));
    } else {
        const costs = (plan.rules || []).reduce((total, rule) => {
            if (rule.suppliable && (item.supplied_categories?.[rule.category_id] ?? (rule.venue_default && item.venue_supplied))) return total;
            const units = { workshop: 1, hour: Number(item.workshop_hours), participant: Number(item.workshop_seats),
                venue_hour: (item.supplied_categories?.[rule.category_id] ?? item.venue_supplied) ? 0 : Number(item.workshop_hours), travel: 0 }[rule.basis] ?? 0;
            return total + Math.round(Number(rule.rate_cents) * units);
        }, 0);
        ex = costs / Number(item.quantity);
    }
    if (!Number.isFinite(ex) || ex < 0) return;
    if (item.kind === 'travel' && item.travel_hours !== '' && item.travel_hours != null) {
        const unitStep = Number(plan.travel_rounding_step || 0);
        const unitGross = ex * (taxed ? 1.1 : 1);
        ex = (unitStep > 0 ? Math.ceil((unitGross - 0.000001) / unitStep) * unitStep / (taxed ? 1.1 : 1) : ex) * 4;
    }
    let grossCents = ex * (taxed ? 1.1 : 1);
    const step = Number((item.kind === 'workshop' ? plan.rounding_step : plan.travel_rounding_step) || 0);
    if (step > 0) grossCents = Math.ceil((grossCents - 0.000001) / step) * step;
    const gross = Number((grossCents / 100).toFixed(2));
    if (step > 0) item.details_json = { ...(item.details_json || {}), inclusive_unit_price: gross };
    else if (item.details_json) delete item.details_json.inclusive_unit_price;
    const value = (inclusive ? gross : step > 0 ? gross / (taxed ? 1.1 : 1) : ex / 100).toFixed(2);
    item[inclusive ? 'unit_price_inc_tax' : 'unit_price'] = value;
}
window.SM = window.SM || {};
window.SM.updateWorkshopLine = updateWorkshopLine;

window.SM.registerLinePlan = (item, plan, inclusive) => pricingPlans.set(item, { plan, inclusive });
window.SM.refreshLineQuantity = item => {
    const automatic = item.auto_pricing;
    item.auto_pricing = false;
    updateWorkshopLine(item);
    item.auto_pricing = automatic;
};
window.SM.refreshLinePrice = item => {
    item.auto_pricing = true;
    updateWorkshopLine(item);
};

window.SM.lineAmounts = item => {
    const qty = Number(item.quantity || 0), rate = item.gst_applicable !== false ? 0.1 : 0;
    const inclusive = item.details_json?.inclusive_unit_price;
    const gross = inclusive != null ? Math.round(qty * Number(inclusive) * 100) / 100 : null;
    const net = gross != null ? Math.round(gross / (1 + rate) * 100) / 100 : Math.round(qty * Number(item.unit_price || 0) * 100) / 100;
    return { net, tax: gross != null ? Math.round((gross - net) * 100) / 100 : Math.round(net * rate * 100) / 100 };
};
window.SM.suggestTicketPrice = (plan, hours, seats, venueSupplied = false) => {
    if (!(hours > 0 && seats > 0)) return null;
    const costs = (plan.rules || []).reduce((total, rule) => {
        if (rule.suppliable && rule.venue_default && venueSupplied) return total;
        const units = { workshop: 1, hour: hours, participant: seats, venue_hour: venueSupplied ? 0 : hours, travel: 0 }[rule.basis] ?? 0;
        return total + Math.round(rule.rate_cents * units);
    }, 0);
    const raw = costs * 1.1 / seats, step = Number(plan.rounding_step || 1);
    return (Math.ceil((raw - 0.000001) / step) * step / 100).toFixed(2);
};

window.SM.lineCostAllocations = (items, rules) => {
    const totals = {};
    for (const item of window.SM.expandWorkshopGroups(items)) {
        const hours = Number(item.workshop_hours), seats = Number(item.workshop_seats);
        if (item.kind !== 'travel' && !(item.kind === 'workshop' && hours > 0 && seats > 0)) continue;
        for (const rule of rules) {
            if ((item.kind === 'travel') !== (rule.basis === 'travel')) continue;
            const supplied = item.supplied_categories?.[rule.category_id] ?? (rule.venue_default && item.venue_supplied);
            if (rule.suppliable && supplied) continue;
            const units = { workshop: 1, hour: hours, participant: seats, travel: item.travel_hours !== '' && item.travel_hours != null ? Number(item.travel_hours) * 4 : Number(item.travel_units || 0),
                venue_hour: (item.supplied_categories?.[rule.category_id] ?? item.venue_supplied) ? 0 : hours }[rule.basis] ?? 0;
            totals[rule.category_id] = (totals[rule.category_id] || 0) + Math.round(units * rule.rate_cents);
        }
    }
    return totals;
};

window.SM.workshopPrice = (plan, registration, current, start, end, seats, force = false, teachingHours = null) => {
    if (registration !== 'tickets' || (!force && String(current ?? '').trim() !== '')) return current;
    const hours = teachingHours ?? (new Date(end) - new Date(start)) / 3600000;
    const count = Number(seats);
    if (!Number.isFinite(hours) || hours <= 0 || !Number.isInteger(count) || count <= 0) return current;
    return window.SM.suggestTicketPrice(plan, hours, Math.min(count, Number(plan.pricing_participants || 10))) ?? current;
};

window.SM.ticketCostBreakdown = (plan, start, end, maxTickets, capAtPricingAttendance = true, teachingHours = null) => {
    const hours = teachingHours ?? (new Date(end) - new Date(start)) / 3600000;
    const capacity = Number(maxTickets);
    const participants = Number.isInteger(capacity) && capacity > 0 ? (capAtPricingAttendance ? Math.min(capacity, Number(plan.pricing_participants || 10)) : capacity) : 0;
    if (!(hours > 0 && participants > 0)) return { categories: {}, total: 0, participants };
    const categories = window.SM.lineCostAllocations([{ kind: 'workshop', workshop_hours: hours, workshop_seats: participants, venue_supplied: false }], plan.rules || []);
    return { categories, total: Object.values(categories).reduce((total, amount) => total + amount, 0), participants };
};

window.SM.hydrateTravelLine = item => {
    if (item.kind !== 'travel') return item;
    if (item.travel_hours !== '' && item.travel_hours != null) return item;
    const saved = item.details_json?.travel;
    if (saved?.quantity_basis === 'hours') item.travel_hours = Number(item.quantity);
    else if (saved?.billable_units != null) {
        item.travel_hours = Number(saved.billable_units) / 4;
        item.quantity = item.travel_hours;
        item.unit_price = (Number(item.unit_price || 0) * 4).toFixed(2);
        if (item.unit_price_inc_tax != null) item.unit_price_inc_tax = (Number(item.unit_price_inc_tax) * 4).toFixed(2);
        if (item.details_json?.inclusive_unit_price != null) item.details_json.inclusive_unit_price *= 4;
        item.details_json.travel.quantity_basis = 'hours';
    } else item.travel_hours = Number(item.quantity || 0);
    return item;
};

window.SM.invoiceRoundingAllowance = (items, rules, prices) => window.SM.expandWorkshopGroups(items).reduce((sum, item) => {
    if (!['workshop', 'travel'].includes(item.kind)) return sum;
    if (item.kind === 'workshop' && !(Number(item.workshop_hours) > 0 && Number(item.workshop_seats) > 0)) return sum;
    const step = Number(item.kind === 'travel' ? prices.travel_rounding_step : prices.rounding_step);
    if (!(step > 0)) return sum;
    const priced = JSON.parse(JSON.stringify(item));
    priced.auto_pricing = true;
    updateWorkshopLine(priced, { ...prices, rules });
    const cost = Object.values(window.SM.lineCostAllocations([item], rules)).reduce((total, cents) => total + cents, 0);
    return sum + Math.max(0, Math.round(window.SM.lineAmounts(priced).net * 100) - cost);
}, 0);

window.SM.expandWorkshopGroups = items => items.flatMap(item => item.kind === 'multi_workshop'
    ? (item.workshops ?? item.details_json?.multi_workshop?.rows ?? []).map(row => ({ ...row, kind: 'workshop', gst_applicable: item.gst_applicable }))
    : [item]);
window.SM.addWorkshopRow = item => {
    item.workshops ??= JSON.parse(JSON.stringify(item.details_json?.multi_workshop?.rows || []));
    item.workshops.push({ description: '', workshop_date: '', workshop_hours: 1, workshop_seats: 10, venue_supplied: true, supplied_categories: {} });
};
function updateMultipleWorkshops(item, plan, inclusive) {
    item.workshops ??= JSON.parse(JSON.stringify(item.details_json?.multi_workshop?.rows || []));
    const quantity = Math.round(item.workshops.reduce((sum, row) => sum + Number(row.workshop_hours || 0) * Number(row.workshop_seats || 0), 0) * 100) / 100;
    if (quantity > 0 && Number(item.quantity) === 1 && item.details_json?.multi_workshop && !item.details_json.multi_workshop.quantity_basis) {
        if (item.details_json.inclusive_unit_price == null) {
            const amount = window.SM.lineAmounts(inclusive ? { ...item, unit_price: Number(item.unit_price_inc_tax || 0) / (item.gst_applicable === false ? 1 : 1.1) } : item);
            item.details_json.inclusive_unit_price = Math.round((amount.net + amount.tax) * 100) / 100;
        }
        for (const field of ['unit_price', 'unit_price_inc_tax']) {
            if (item[field] != null) item[field] = (Number(item[field]) / quantity).toFixed(2);
        }
        if (item.details_json.inclusive_unit_price != null) item.details_json.inclusive_unit_price /= quantity;
    }
    item.quantity = quantity;
    item.details_json = { ...(item.details_json || {}), multi_workshop: { rows: item.workshops, quantity_basis: 'seat_hours' } };
    item.notes = item.workshops.map(row => {
        const date = row.workshop_date ? row.workshop_date.split('-').reverse().join('/') + ' - ' : '';
        return '- ' + date + (row.description || '').trim() + ' - (' + Number(row.workshop_hours || 0) + ' hr / ' + Number(row.workshop_seats || 0) + ' seats)';
    }).join('\n');
    if (!plan || !item.auto_pricing || !(quantity > 0)) return;
    let gross = 0;
    for (const row of item.workshops) {
        const priced = { ...row, kind: 'workshop', auto_pricing: true, gst_applicable: item.gst_applicable };
        updateWorkshopLine(priced, plan, false);
        const amounts = window.SM.lineAmounts(priced);
        gross += Math.round((amounts.net + amounts.tax) * 100);
    }
    item.details_json.inclusive_unit_price = gross / 100 / quantity;
    item[inclusive ? 'unit_price_inc_tax' : 'unit_price'] = (gross / 100 / quantity / (inclusive || item.gst_applicable === false ? 1 : 1.1)).toFixed(2);
}
