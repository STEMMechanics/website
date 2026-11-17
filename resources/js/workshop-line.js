// Keep currency padding without discarding precision needed by saved line totals.
function formatUnitPrice(value) {
    const price = Number(Number(value ?? 0).toFixed(8));
    if (!Number.isFinite(price)) return '0.00';
    return Math.abs(price - Number(price.toFixed(2))) < 0.00000001
        ? price.toFixed(2)
        : price.toFixed(8).replace(/0+$/, '');
}
const pricingPlans = new WeakMap();
export function updateWorkshopLine(item, plan = null, inclusive = null) {
    window.SM.initializeWorkshopNotes(item);
    const cached = pricingPlans.get(item);
    plan = plan ?? cached?.plan;
    inclusive = inclusive ?? cached?.inclusive ?? false;
    if (plan) pricingPlans.set(item, { plan, inclusive });
    if (item.kind === 'multi_workshop') {
        updateMultipleWorkshops(item, plan, inclusive);
        return;
    }
    if (item.kind === 'multi_travel') {
        updateMultipleTravel(item, plan, inclusive);
        return;
    }
    if (item.kind === 'travel' && item.travel_hours === '') {
        item.travel_units = '';
        item.quantity = 0;
        item.details_json = { ...(item.details_json || {}), travel: { ...(item.details_json?.travel || {}), billable_units: 0, quantity_basis: 'hours', supplied_categories: { ...(item.supplied_categories || {}) } } };
    } else if (item.kind === 'travel' && item.travel_hours !== '' && item.travel_hours != null) {
        item.travel_units = Number(item.travel_hours) * 4;
        item.quantity = Number(item.travel_hours);
        item.details_json = { ...(item.details_json || {}), travel: { ...(item.details_json?.travel || {}), billable_units: item.travel_units, quantity_basis: 'hours', supplied_categories: { ...(item.supplied_categories || {}) } } };
    } else if (item.kind === 'travel' && item.travel_units !== '' && item.travel_units != null) {
        item.quantity = Number(item.travel_units);
        item.details_json = { ...(item.details_json || {}), travel: { ...(item.details_json?.travel || {}), billable_units: Number(item.travel_units), supplied_categories: { ...(item.supplied_categories || {}) } } };
    }
    if (item.kind === 'travel') applyPlanPrice(item, plan, inclusive);
    if (item.kind !== 'workshop') return;
    const hours = Number(item.workshop_hours), seats = Number(item.workshop_seats);
    if (hours > 0 && seats >= 0 && item.workshop_seats !== '' && item.workshop_seats != null) {
        item.quantity = Math.round(hours * seats * 100) / 100;
        item.details_json = { ...(item.details_json || {}), workshop: { ...(item.details_json?.workshop || {}), hours, seats, date: (item.workshop_date ?? item.details_json?.workshop?.date) || null, venue_supplied: !!item.venue_supplied, supplied_categories: { ...(item.supplied_categories || {}) } } };
        syncWorkshopNotes(item);
        applyPlanPrice(item, plan, inclusive);
    }
}
function applyPlanPrice(item, plan, inclusive) {
    if (!plan || !item.auto_pricing || !(Number(item.quantity) > 0)) return;
    const taxed = item.gst_applicable !== false;
    item.tax_rate = taxed ? window.SM.currentGstRate() : 0;
    const multiplier = 1 + item.tax_rate;
    let ex;
    if (item.kind === 'travel') {
        const travelRules = (plan.rules || []).filter(rule => rule.basis === 'travel');
        ex = travelRules.length ? travelRules.reduce((sum, rule) => sum + Number(rule.rate_cents), 0) : Number(plan.travel_cents) / multiplier;
        ex = Math.max(0, ex - (plan.rules || []).filter(rule => rule.basis === 'travel' && rule.suppliable && item.supplied_categories?.[rule.category_id]).reduce((total, rule) => total + Number(rule.rate_cents), 0));
    } else {
        const participants = Number(plan.pricing_participants || 10);
        const hours = Number(item.workshop_hours);
        const costs = (plan.rules || []).reduce((total, rule) => {
            if (rule.suppliable && (item.supplied_categories?.[rule.category_id] ?? (rule.venue_default && item.venue_supplied))) return total;
            const units = { workshop: 1, hour: hours, participant: participants,
                venue_hour: (item.supplied_categories?.[rule.category_id] ?? item.venue_supplied) ? 0 : hours, travel: 0 }[rule.basis] ?? 0;
            return total + Math.round(Number(rule.rate_cents) * units);
        }, 0);
        ex = costs / (hours * participants);
        item.details_json.pricing_basis = { participants };
    }
    if (!Number.isFinite(ex) || ex < 0) return;
    if (item.kind === 'travel' && item.travel_hours !== '' && item.travel_hours != null) {
        const unitStep = Number(plan.travel_rounding_step || 0);
        const unitGross = ex * multiplier;
        ex = (unitStep > 0 ? Math.ceil((unitGross - 0.000001) / unitStep) * unitStep / multiplier : ex) * 4;
    }
    let grossCents = ex * multiplier;
    const step = Number((item.kind === 'workshop' ? plan.rounding_step : plan.travel_rounding_step) || 0);
    if (step > 0) grossCents = Math.ceil((grossCents - 0.000001) / step) * step;
    const cleanPricing = inclusive && plan.clean_prices;
    const gross = cleanPricing ? grossCents / 100 : Number((grossCents / 100).toFixed(2));
    if (step > 0 || inclusive) item.details_json = { ...(item.details_json || {}), inclusive_unit_price: gross };
    else if (item.details_json) delete item.details_json.inclusive_unit_price;
    const value = cleanPricing ? String(gross) : (inclusive ? gross : step > 0 ? gross / multiplier : ex / 100).toFixed(2);
    item[inclusive ? 'unit_price_inc_tax' : 'unit_price'] = value;
    if (cleanPricing) {
        delete item.details_json.price_rounding;
        window.SM.roundWorkshopPrice(item, true);
        item.unit_price_inc_tax = Number(item.unit_price_inc_tax).toFixed(2);
    }
}
window.SM = window.SM || {};
const workshopSpacingAliases = new Set(['-', 'space', 'spacing', '- spacing', '- (space)']);
const committedWorkshopSpacingLabels = new Set(['- spacing', '- (space)', 'spacing']);
window.SM.isWorkshopSpacing = row => {
    const description = String(row?.description ?? '').trim().replace(/\s+/g, ' ').toLowerCase();
    if (row?.row_type === 'heading') return true;
    if (row?.row_type === 'spacing') return true;
    return committedWorkshopSpacingLabels.has(description);
};
window.SM.commitWorkshopSpacing = row => {
    const description = String(row?.description ?? '').trim().replace(/\s+/g, ' ').toLowerCase();
    const isSpacing = row?.row_type === 'heading' || workshopSpacingAliases.has(description);
    if (!isSpacing) {
        if (row?.row_type === 'spacing') delete row.row_type;
        return row;
    }
    row.row_type = 'spacing';
    row.description = '- (space)';
    // Keep the hidden workshop controls' Alpine state valid while the spacer is displayed.
    row.details_json = { workshop: { allocation_basis: 'manual', allocation_seats: null } };
    row.workshop_date = '';
    row.workshop_hours = '';
    row.workshop_seats = '';
    row.venue_supplied = true;
    row.supplied_categories = {};
    delete row.spacer_after;
    return row;
};
window.SM.normalizeWorkshopSpacing = window.SM.commitWorkshopSpacing;
window.SM.currentGstRate = () => Number(typeof document !== 'undefined' ? document.querySelector?.('meta[name="gst-rate"]')?.content ?? 0.10 : 0.10);
window.SM.lineTaxRate = item => item.gst_applicable === false ? 0 : Number(item.tax_rate ?? window.SM.currentGstRate());
window.SM.gstRateForDate = date => {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(date || '')) return window.SM.currentGstRate();
    const schedule = JSON.parse(typeof document !== 'undefined' ? document.querySelector?.('meta[name="gst-rates"]')?.content || '[]' : '[]');
    return schedule.reduce((rate, entry) => entry.effective_from <= date ? Number(entry.rate) : rate, 0.10);
};
window.SM.applyCurrentGst = item => {
    if (item.gst_applicable === false || window.SM.lineTaxRate(item) === 0) return;
    const netUnit = Number(item.unit_price_inc_tax ?? item.details_json?.inclusive_unit_price ?? 0) / (1 + window.SM.lineTaxRate(item));
    item.tax_rate = window.SM.currentGstRate();
    item.unit_price = netUnit;
    item.unit_price_ex_tax = netUnit;
    item.unit_price_inc_tax = window.SM.formatUnitPrice(netUnit * (1 + item.tax_rate));
    item.details_json ??= {};
    item.details_json.inclusive_unit_price = Number(item.unit_price_inc_tax);
    delete item.details_json.price_rounding;
    item.saved_pricing = null;
    item.auto_pricing = false;
    window.SM.roundWorkshopPrice(item);
};
window.SM.changeLineGst = item => { item.tax_rate = item.gst_applicable === false ? 0 : window.SM.currentGstRate(); };
window.SM.updateWorkshopLine = updateWorkshopLine;
// PHP encodes empty maps as []; numeric category keys must not create sparse arrays.
window.SM.suppliedCategoryMap = supplied => Object.fromEntries(Object.entries(supplied || {}).filter(([, value]) => value != null));

window.SM.registerLinePlan = (item, plan, inclusive) => pricingPlans.set(item, { plan, inclusive });
window.SM.initializePricingSupplies = (item, plan) => {
    if (!item || !plan) return;
    const rules = (plan.rules || []).filter(rule => rule.suppliable || rule.basis === 'venue_hour');
    const initialize = (target, travel = false) => {
        target.supplied_categories = window.SM.suppliedCategoryMap(target.supplied_categories);
        const venueSupplied = target.venue_supplied ?? target.details_json?.workshop?.venue_supplied ?? true;
        for (const rule of rules) {
            if (travel !== (rule.basis === 'travel')) continue;
            const id = String(rule.category_id);
            target.supplied_categories[id] ??= !travel && ((rule.venue_default || rule.basis === 'venue_hour') && !!venueSupplied);
        }
    };
    if (item.kind === 'multi_workshop') {
        item.workshops ??= JSON.parse(JSON.stringify(item.details_json?.multi_workshop?.rows || []));
        item.workshops.forEach(row => initialize(row));
    } else if (item.kind === 'multi_travel') {
        item.travel_rows ??= JSON.parse(JSON.stringify(item.details_json?.multi_travel?.rows || []));
        item.travel_rows.forEach(row => initialize(row, true));
    } else {
        initialize(item, item.kind === 'travel');
    }
};
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

const moneyRound = value => Math.sign(value) * Math.round((Math.abs(value) + Number.EPSILON) * 100) / 100;

// A clean rate has exact cent values before/after GST and for the billable quantity.
window.SM.cleanPriceQuote = (inclusive, quantity, taxRate = window.SM.currentGstRate()) => {
    inclusive = Number(inclusive);
    quantity = Number(quantity);
    taxRate = Number(taxRate);
    if (![inclusive, quantity, taxRate].every(Number.isFinite) || inclusive < 0 || quantity < 0 || taxRate < 0) return null;
    const gcd = (a, b) => { while (b) [a, b] = [b, a % b]; return Math.abs(a); };
    const lcm = (a, b) => a / gcd(a, b) * b;
    const denominator = 1000000;
    const numerator = denominator + Math.round(taxRate * denominator);
    const divisor = gcd(numerator, denominator);
    const baseEx = denominator / divisor;
    const baseTax = numerator / divisor - baseEx;
    const quantityCents = Math.round(quantity * 100);
    const quantityDenominator = 100 / gcd(quantityCents, 100);
    const multiplier = taxRate > 0 ? lcm(quantityDenominator / gcd(baseEx, quantityDenominator), quantityDenominator / gcd(baseTax, quantityDenominator)) : 1;
    const stepEx = baseEx * multiplier;
    const stepInc = numerator / divisor * multiplier;
    const index = inclusive * 100 / stepInc;
    const clean = Math.abs(index - Math.round(index)) < 0.00000001;
    const at = number => {
        const unitEx = number * stepEx / 100;
        const unitInc = number * stepInc / 100;
        const net = moneyRound(quantity * unitEx);
        const tax = moneyRound(net * taxRate);
        return { unitEx, unitInc, net, tax, gross: moneyRound(net + tax) };
    };
    const gross = moneyRound(quantity * inclusive);
    const net = moneyRound(gross / (1 + taxRate));
    const ceiling = Math.ceil(index - 0.00000001);
    return {
        clean, quantity, taxRate, requested: inclusive,
        current: { unitEx: inclusive / (1 + taxRate), unitInc: inclusive, net, tax: moneyRound(gross - net), gross },
        suggested: at(ceiling), previous: at(Math.max(0, ceiling - 1)), next: at(clean ? ceiling + 1 : ceiling),
    };
};
window.SM.priceRoundingForLine = item => {
    if (!item || !['workshop', 'multi_workshop', 'travel', 'multi_travel'].includes(item.kind)) return null;
    const inclusive = Number(item.unit_price_inc_tax ?? item.details_json?.inclusive_unit_price ?? 0);
    const taxRate = window.SM.lineTaxRate(item);
    if (taxRate > 0 && Math.abs(taxRate - window.SM.currentGstRate()) > 0.00000001) return null;
    const quote = window.SM.cleanPriceQuote(inclusive, item.quantity, taxRate);
    if (!quote) return null;
    const saved = item.details_json?.price_rounding;
    const requested = saved && Number.isFinite(Number(saved.requested)) && Number(saved.requested) >= 0 && Math.abs(Number(saved.applied) - inclusive) < 0.00000001 ? Number(saved.requested) : inclusive;
    const original = window.SM.cleanPriceQuote(requested, item.quantity, taxRate);
    return { ...quote, original: original.current, adjusted: Math.abs(requested - inclusive) > 0.00000001, amounts: window.SM.lineAmounts(item) };
};
window.SM.applyCleanPrice = (item, inclusive, requested = null, automatic = false) => {
    const taxRate = window.SM.lineTaxRate(item);
    const quote = window.SM.cleanPriceQuote(inclusive, item.quantity, taxRate);
    if (!quote) return;
    const selected = quote.suggested;
    const wasAutomatic = item.auto_pricing;
    item.details_json ??= {};
    item.details_json.price_rounding = { requested: requested ?? quote.requested, applied: selected.unitInc };
    item.details_json.inclusive_unit_price = selected.unitInc;
    item.unit_price_inc_tax = selected.unitInc.toFixed(2);
    item.unit_price = selected.unitEx.toFixed(2);
    item.unit_price_ex_tax = selected.unitEx;
    item.saved_pricing = null;
    item.auto_pricing = automatic ? wasAutomatic : false;
};
window.SM.roundWorkshopPrice = (item, automatic = false) => {
    const quote = window.SM.priceRoundingForLine(item);
    if (quote && !quote.clean) window.SM.applyCleanPrice(item, quote.suggested.unitInc, quote.original.unitInc, automatic);
};
window.SM.priceRoundingSummary = items => {
    const lines = items.map(item => ({ item, quote: window.SM.priceRoundingForLine(item) }));
    const adjusted = lines.filter(line => line.quote?.adjusted);
    const pending = lines.filter(line => line.quote && !line.quote.clean);
    return {
        adjusted: adjusted.length, pending: pending.length,
        increase: moneyRound(adjusted.reduce((sum, line) => sum + line.quote.amounts.gross - line.quote.original.gross, 0)),
        roundedTotal: moneyRound(lines.reduce((sum, line) => sum + (line.quote && !line.quote.clean ? line.quote.suggested.gross : window.SM.lineAmounts(line.item).gross), 0)),
        first: pending[0]?.item ?? adjusted[0]?.item ?? null,
    };
};
window.SM.lineAmounts = item => {
    const qty = Number(item.quantity || 0), rate = window.SM.lineTaxRate(item);
    const inclusive = item.unit_price_inc_tax ?? item.details_json?.inclusive_unit_price;
    const saved = item.saved_pricing;
    if (saved && qty === Number(saved.quantity) && Math.abs(Number(inclusive) - Number(saved.price)) < 0.0000001 && rate === Number(saved.rate)) return { net: saved.net, tax: saved.tax, gross: saved.gross ?? moneyRound(saved.net + saved.tax), saved: true };
    const gross = inclusive != null ? moneyRound(qty * Number(inclusive)) : null;
    const net = gross != null ? moneyRound(gross / (1 + rate)) : moneyRound(qty * Number(item.unit_price || 0));
    const tax = gross != null ? moneyRound(gross - net) : moneyRound(net * rate);
    return { net, tax, gross: gross ?? moneyRound(net + tax) };
};
window.SM.suggestTicketPrice = (plan, hours, seats, venueSupplied = false) => {
    if (!(hours > 0 && seats > 0)) return null;
    const costs = (plan.rules || []).reduce((total, rule) => {
        if (rule.suppliable && rule.venue_default && venueSupplied) return total;
        const units = { workshop: 1, hour: hours, participant: seats, venue_hour: venueSupplied ? 0 : hours, travel: 0 }[rule.basis] ?? 0;
        return total + Math.round(rule.rate_cents * units);
    }, 0);
    const raw = costs * (1 + window.SM.currentGstRate()) / seats, step = Number(plan.rounding_step || 1);
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
    const details = item.details_json?.travel || {};
    item.location_id ??= details.location_id ?? null;
    item.travel_hours_defaulted ??= !!details.hours_defaulted;
    item.travel_hours_user_changed ??= !!details.hours_user_changed;
    if (item.travel_hours !== '' && item.travel_hours != null) return item;
    const saved = details;
    if (saved?.quantity_basis === 'hours') item.travel_hours = Number(item.quantity);
    else if (saved?.billable_units != null) {
        item.travel_hours = Number(saved.billable_units) / 4;
        item.quantity = item.travel_hours;
        item.unit_price = (Number(item.unit_price || 0) * 4).toFixed(2);
        if (item.unit_price_inc_tax != null) item.unit_price_inc_tax = formatUnitPrice(Number(item.unit_price_inc_tax) * 4);
        if (item.details_json?.inclusive_unit_price != null) item.details_json.inclusive_unit_price *= 4;
        if (item.saved_pricing) { item.saved_pricing.price *= 4; item.saved_pricing.quantity /= 4; }
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
    delete priced.unit_price_inc_tax;
    priced.saved_pricing = null;
    // Historical lines keep their attendance-based rounding calculation until repriced.
    const participants = Number(item.details_json?.pricing_basis?.participants || item.workshop_seats);
    updateWorkshopLine(priced, { ...prices, rules, pricing_participants: participants });
    const reference = item.kind === 'workshop' ? { ...item, workshop_seats: participants } : item;
    const referenceCost = Object.values(window.SM.lineCostAllocations([reference], rules)).reduce((total, cents) => total + cents, 0);
    const cost = item.kind === 'workshop' ? referenceCost * Number(item.workshop_seats) / participants : referenceCost;
    const net = window.SM.lineAmounts(priced).gross / (1 + window.SM.lineTaxRate(item)) * 100;
    return sum + Math.max(0, Math.round(net - cost));
}, 0);

window.SM.expandWorkshopGroups = items => items.flatMap(item => {
    if (item.kind === 'multi_workshop') {
        return (item.workshops ?? item.details_json?.multi_workshop?.rows ?? []).filter(row => !window.SM.isWorkshopSpacing(row)).map(row => ({ ...row, kind: 'workshop', gst_applicable: item.gst_applicable,
            details_json: { ...(row.details_json || {}), ...(item.details_json?.pricing_basis ? { pricing_basis: item.details_json.pricing_basis } : {}) } }));
    }
    if (item.kind === 'multi_travel') {
        return (item.travel_rows ?? item.details_json?.multi_travel?.rows ?? []).filter(row => !window.SM.isWorkshopSpacing(row) && Number(row.travel_hours) > 0).map(row => ({ ...row, kind: 'travel', gst_applicable: item.gst_applicable,
            travel_hours: row.travel_hours ?? row.details_json?.travel?.hours ?? '',
            details_json: { ...(row.details_json || {}) } }));
    }
    return [item];
});
window.SM.addWorkshopRow = (item, defaults = {}) => {
    item.workshops ??= JSON.parse(JSON.stringify(item.details_json?.multi_workshop?.rows || []));
    const hours = Number(defaults.hours ?? 1);
    const seats = Number(defaults.seats ?? 10);
    item.workshops.push({
        description: '',
        workshop_date: '',
        workshop_hours: Number.isFinite(hours) && hours > 0 ? hours : 1,
        workshop_seats: Number.isInteger(seats) && seats >= 0 ? seats : 10,
        venue_supplied: true,
        supplied_categories: {},
    });
};
window.SM.hydrateMultiTravelLine = item => {
    if (item?.kind !== 'multi_travel') return item;
    item.travel_rows ??= JSON.parse(JSON.stringify(item.details_json?.multi_travel?.rows || []));
    item.travel_rows.forEach(row => {
        row.location_id ??= row.details_json?.travel?.location_id ?? null;
        row.travel_hours_defaulted ??= !!row.details_json?.travel?.hours_defaulted;
        row.travel_hours_user_changed ??= !!row.details_json?.travel?.hours_user_changed;
    });
    return item;
};
window.SM.addTravelRow = item => {
    window.SM.hydrateMultiTravelLine(item);
    item.travel_rows.push({ description: '', travel_date: '', travel_hours: '', location_id: null, travel_hours_defaulted: false, travel_hours_user_changed: false, supplied_categories: {} });
};
window.SM.copyWorkshopRowsToTravel = (item, workshopItem, workshopCatalog = [], locationCatalog = []) => {
    if (!item || !workshopItem) return false;
    const sourceRows = Array.isArray(workshopItem.workshops) && workshopItem.workshops.length
        ? workshopItem.workshops
        : workshopItem.details_json?.multi_workshop?.rows ?? [];
    if (!Array.isArray(sourceRows)) return false;
    const spacingAliases = new Set(['-', 'space', 'spacing', '- spacing', '- (space)']);
    const rows = sourceRows
        .filter(row => {
            if (!row || typeof row !== 'object') return false;
            const description = String(row.description ?? '').trim().replace(/\s+/g, ' ').toLowerCase();
            return window.SM.isWorkshopSpacing(row)
                || spacingAliases.has(description)
                || description !== ''
                || String(row.workshop_date ?? '').trim() !== ''
                || String(row.details_json?.workshop?.date ?? '').trim() !== ''
                || (row.workshop_hours ?? '') !== ''
                || (row.workshop_seats ?? '') !== '';
        })
        .map(row => {
            const description = String(row.description ?? '').trim().replace(/\s+/g, ' ').toLowerCase();
            if (window.SM.isWorkshopSpacing(row) || spacingAliases.has(description)) {
                return { row_type: 'spacing', description: '- (space)', travel_date: '', travel_hours: 0, supplied_categories: {} };
            }
            const linkedWorkshopId = row.details_json?.workshop?.linked_workshop_id;
            const linkedWorkshop = workshopCatalog.find(option => option.id === linkedWorkshopId);
            const rowLocationId = row.location_id ?? row.details_json?.workshop?.location_id ?? null;
            const rowDescription = String(row.description ?? '').trim().replace(/\s+/g, ' ').toLowerCase();
            const exactLocationMatches = locationCatalog.filter(option => String(option.name ?? option.label ?? '').trim().replace(/\s+/g, ' ').toLowerCase() === rowDescription);
            const location = linkedWorkshop ? null : locationCatalog.find(option => String(option.location_id ?? option.id) === String(rowLocationId))
                ?? (exactLocationMatches.length === 1 ? exactLocationMatches[0] : null);
            const locationId = linkedWorkshop ? linkedWorkshop.location_id : location?.location_id ?? rowLocationId;
            const defaultHours = linkedWorkshop ? linkedWorkshop.default_travel_hours : location?.default_travel_hours;
            const hasTravelDefault = defaultHours !== null && defaultHours !== undefined && defaultHours !== '' && Number.isFinite(Number(defaultHours));
            return {
                description: String(row.description ?? '').trim(),
                travel_date: row.workshop_date || row.details_json?.workshop?.date || '',
                travel_hours: hasTravelDefault ? Number(defaultHours) : 0,
                location_id: locationId,
                travel_hours_defaulted: hasTravelDefault,
                travel_hours_user_changed: false,
                details_json: {
                    ...(linkedWorkshopId ? { workshop: { linked_workshop_id: linkedWorkshopId } } : {}),
                    ...(locationId ? { travel: { location_id: locationId } } : {}),
                },
                supplied_categories: {},
            };
        });
    if (!rows.length) return false;
    item.travel_rows = rows;
    return true;
};
window.SM.moveWorkshopRow = (item, index, direction) => {
    const rows = item?.kind === 'multi_travel' ? item.travel_rows : item?.workshops;
    if (!Array.isArray(rows)) return;
    const destination = Number(index) + Number(direction);
    if (!Number.isInteger(Number(index)) || ![-1, 1].includes(Number(direction)) || destination < 0 || destination >= rows.length) return;
    [rows[index], rows[destination]] = [rows[destination], rows[index]];
    updateWorkshopLine(item);
};
function generatedWorkshopNotes(item) {
    const calculation = row => {
        const hours = Number(row.workshop_hours ?? row.hours ?? 0);
        const seats = Number(row.workshop_seats ?? row.seats ?? 0);
        return `${hours} ${hours === 1 ? 'hr' : 'hrs'} × ${seats} seats`;
    };
    if (item.kind === 'multi_workshop') {
        const lines = [];
        (item.workshops ?? item.details_json?.multi_workshop?.rows ?? []).forEach(row => {
            if (window.SM.isWorkshopSpacing(row)) {
                if (lines.length && lines.at(-1) !== '') lines.push('');
                return;
            }
            const description = String(row.description || '').trim();
            const hasData = description !== '' || row.workshop_date || row.workshop_hours !== '' && row.workshop_hours != null || row.workshop_seats !== '' && row.workshop_seats != null;
            if (!hasData) return;
            const date = row.workshop_date ? row.workshop_date.split('-').reverse().join('/') + ' - ' : '';
            lines.push('- ' + date + description + ' - (' + calculation(row) + ')');
            if (row.spacer_after) lines.push('');
        });
        return lines.join('\n').replace(/\n+$/, '');
    } else if (item.kind === 'workshop') {
        return calculation({ ...item.details_json?.workshop, ...item });
    }
    return '';
}
window.SM.defaultWorkshopDescription = item => {
    if (!['workshop', 'multi_workshop'].includes(item.kind)) return;
    if (item.kind === 'workshop') {
        if (item.legacy_workshop) return;
        item.details_json ??= {};
        item.details_json.workshop ??= {};
        if (!Object.hasOwn(item.details_json.workshop, 'name')) {
            const description = String(item.description ?? '').trim();
            item.details_json.workshop.name = ['', 'workshop delivery', 'charged per hour, per seat'].includes(description.toLowerCase()) ? '' : description;
            item.description = 'Charged per hour, per seat';
        }
        return;
    }
    if (item.kind === 'multi_workshop' && item.description != null && item.description.trim() === '') return;
    if (!item.description?.trim() || ['workshop delivery', 'multi workshop delivery'].includes(item.description.trim().toLowerCase())) item.description = 'Charged per hour, per seat';
};
window.SM.initializeWorkshopNotes = item => {
    if (!['workshop', 'multi_workshop'].includes(item.kind)) return;
    if (item.kind === 'workshop' && !(Number(item.workshop_hours ?? item.details_json?.workshop?.hours) > 0 && Number(item.workshop_seats ?? item.details_json?.workshop?.seats) > 0)) return;
    item.details_json ??= {};
    if (item.details_json.workshop_notes_mode != null) return;
    const generated = generatedWorkshopNotes(item);
    const legacy = generated.replace(/(\d+(?:\.\d+)?) hrs? × (\d+) seats/g, '$1 hr / $2 seats');
    const expanded = generated.replace(/(\d+(?:\.\d+)?) (hrs?) × (\d+) seats/g, (_, hours, unit, seats) => `${hours} ${unit} × ${seats} seats = ${Math.round(Number(hours) * Number(seats) * 100) / 100} seat-hours`);
    const notes = (item.notes ?? '').trim();
    item.details_json.workshop_notes_mode = !notes || notes === generated || notes === legacy || notes === expanded ? 'auto' : 'manual';
    item.details_json.generated_workshop_notes = item.notes ?? '';
};
window.SM.markWorkshopNotesEdited = item => {
    item.details_json ??= {};
    if (item.details_json.workshop_notes_mode !== 'template') item.details_json.workshop_notes_mode = 'manual';
};
window.SM.refreshWorkshopNotes = item => {
    if (item.details_json?.workshop_notes_mode === 'template') {
        item.notes = window.SM.defaultWorkshopText;
        return;
    }
    item.notes = generatedWorkshopNotes(item);
    item.details_json ??= {};
    item.details_json.workshop_notes_mode = 'auto';
    item.details_json.generated_workshop_notes = item.notes;
};
function syncWorkshopNotes(item) {
    if (item.details_json.workshop_notes_mode !== 'auto') return;
    if ((item.notes ?? '') !== item.details_json.generated_workshop_notes) {
        window.SM.markWorkshopNotesEdited(item);
        return;
    }
    window.SM.refreshWorkshopNotes(item);
}

window.SM.defaultWorkshopText = '- {date} - {workshop} - ({duration} x {seats})';
window.SM.initializeWorkshopText = item => {
    if (!['workshop', 'multi_workshop'].includes(item.kind) || (item.kind === 'workshop' && item.legacy_workshop)) return;
    window.SM.defaultWorkshopDescription(item);
    if (item.details_json?.workshop_notes_mode === 'template') return;
    const notes = String(item.notes ?? '').trim();
    const generated = generatedWorkshopNotes(item);
    const generatedLines = generated.split('\n');
    if (item.kind === 'workshop') generatedLines.push(generatedWorkshopNotes({ kind: 'multi_workshop', workshops: [{ ...item, description: item.details_json?.workshop?.name || '', workshop_date: item.workshop_date ?? item.details_json?.workshop?.date ?? '' }] }));
    const variants = line => [line, line.replace(/(\d+(?:\.\d+)?) hrs? × (\d+) seats/g, '$1 hr / $2 seats'), line.replace(/(\d+(?:\.\d+)?) (hrs?) × (\d+) seats/g, (_, hours, unit, seats) => `${hours} ${unit} × ${seats} seats = ${Math.round(Number(hours) * Number(seats) * 100) / 100} seat-hours`)];
    const automatic = new Set(generatedLines.flatMap(variants));
    const retained = notes.split(/\r\n|\r|\n/).filter(line => !automatic.has(line.trim())).join('\n').trim();
    const intentionallyEmpty = item.details_json?.workshop_notes_mode === 'manual' && !notes;
    item.notes = intentionallyEmpty || /\{(?:date|workshop|duration|seats)\}/.test(notes)
        ? notes
        : [retained, window.SM.defaultWorkshopText].filter(Boolean).join('\n');
    item.details_json ??= {};
    item.details_json.workshop_notes_mode = 'template';
    delete item.details_json.generated_workshop_notes;
};
window.SM.workshopNotesPreview = (item, catalog = []) => {
    const rows = item.kind === 'multi_workshop' ? (item.workshops ?? item.details_json?.multi_workshop?.rows ?? []) : [item];
    const rowsWithData = item.kind === 'multi_workshop'
        ? rows.filter(row => window.SM.isWorkshopSpacing(row)
            || String(row.description ?? '').trim() !== ''
            || String(row.workshop_date ?? '').trim() !== ''
            || (row.workshop_hours ?? '') !== ''
            || (row.workshop_seats ?? '') !== ''
            || !!row.details_json?.workshop?.linked_workshop_id)
        : rows;
    const entries = rowsWithData.map(row => {
        if (window.SM.isWorkshopSpacing(row)) return { spacing: true, date: '', workshop: '', duration: '', seats: '' };
        const settings = row.details_json?.workshop ?? {};
        const linked = catalog.find(option => option.id === settings.linked_workshop_id);
        const date = row.workshop_date ?? settings.date ?? linked?.date ?? '';
        const hours = row.workshop_hours ?? settings.hours;
        const seats = row.workshop_seats ?? settings.seats;
        return {
            date: date ? date.split('T')[0].split('-').reverse().join('/') : '',
            workshop: (item.kind === 'workshop' ? settings.name : row.description) || linked?.title || '',
            duration: hours !== '' && hours != null ? `${Number(hours)} ${Number(hours) === 1 ? 'hr' : 'hrs'}` : '',
            seats: seats !== '' && seats != null ? `${Number(seats)} seats` : '',
        };
    });
    return String(item.notes ?? '').split(/\r\n|\r|\n/).flatMap(line => /\{(?:date|workshop|duration|seats)\}/.test(line)
        ? entries.map(entry => {
            if (entry.spacing) return '';
            let text = line;
            for (const optional of ['date', 'workshop']) if (!entry[optional]) text = text.replace(new RegExp(`\\{${optional}\\}\\s*-\\s*`), '');
            return text.replace(/\{(date|workshop|duration|seats)\}/g, (_, token) => entry[token]);
        })
        : [line]).join('\n').trim();
};
window.SM.multiTravelNotesPreview = item => {
    const rows = item?.travel_rows ?? item?.details_json?.multi_travel?.rows ?? [];
    const lines = [];
    const formatNumber = value => {
        const number = Number(value);
        if (!Number.isFinite(number)) return '';
        return String(number).replace(/\.0+$/, '').replace(/(\.\d*?)0+$/, '$1');
    };
    rows.forEach(row => {
        if (window.SM.isWorkshopSpacing(row)) {
            if (lines.length && lines.at(-1) !== '') lines.push('');
            return;
        }
        const description = String(row?.description ?? '').trim();
        const rawDate = String(row?.travel_date ?? row?.workshop_date ?? row?.details_json?.travel?.date ?? '').split('T')[0];
        const date = /^\d{4}-\d{2}-\d{2}$/.test(rawDate) ? rawDate.split('-').reverse().join('/') : '';
        const hasHours = row?.travel_hours !== '' && row?.travel_hours != null && Number.isFinite(Number(row.travel_hours));
        const hours = hasHours ? formatNumber(row.travel_hours) : '';
        if (description === '' && date === '' && hours === '') return;
        const label = [date, description, hours !== '' ? hours + ' ' + (Number(row.travel_hours) === 1 ? 'hr' : 'hrs') : '']
            .filter(part => part !== '')
            .join(' - ');
        if (label !== '') lines.push('- ' + label);
    });
    return lines.join('\n').replace(/\n+$/, '');
};
function updateMultipleTravel(item, plan, inclusive) {
    item.travel_rows ??= JSON.parse(JSON.stringify(item.details_json?.multi_travel?.rows || []));
    if (plan) window.SM.initializePricingSupplies(item, plan);
    const travelRows = item.travel_rows.filter(row => !window.SM.isWorkshopSpacing(row) && Number(row.travel_hours) > 0);
    const quantity = Math.round(travelRows.reduce((sum, row) => sum + Number(row.travel_hours || 0), 0) * 100) / 100;
    item.quantity = quantity;
    item.details_json = { ...(item.details_json || {}), multi_travel: { rows: item.travel_rows, quantity_basis: 'hours' } };
    if (!plan || !item.auto_pricing) return;
    if (!(quantity > 0)) {
        const sampleRow = item.travel_rows.find(row => !window.SM.isWorkshopSpacing(row)) ?? { supplied_categories: {} };
        const priced = { ...sampleRow, kind: 'travel', travel_hours: 1, auto_pricing: true, gst_applicable: item.gst_applicable };
        updateWorkshopLine(priced, plan, inclusive);
        item.tax_rate = window.SM.lineTaxRate(priced);
        const unitGross = Number(priced.unit_price_inc_tax ?? priced.details_json?.inclusive_unit_price
            ?? Number(priced.unit_price_ex_tax ?? priced.unit_price ?? 0) * (1 + item.tax_rate));
        item.details_json = { ...(item.details_json || {}), inclusive_unit_price: unitGross };
        item[inclusive ? 'unit_price_inc_tax' : 'unit_price'] = inclusive
            ? formatUnitPrice(unitGross)
            : (unitGross / (1 + item.tax_rate)).toFixed(2);
        return;
    }

    let gross = 0;
    for (const row of travelRows) {
        const priced = { ...row, kind: 'travel', travel_hours: Number(row.travel_hours || 0), auto_pricing: true, gst_applicable: item.gst_applicable };
        updateWorkshopLine(priced, plan, false);
        const amounts = window.SM.lineAmounts(priced);
        gross += Math.round((amounts.net + amounts.tax) * 100);
    }
    item.tax_rate = item.gst_applicable === false ? 0 : window.SM.currentGstRate();
    item.details_json.inclusive_unit_price = gross / 100 / quantity;
    item[inclusive ? 'unit_price_inc_tax' : 'unit_price'] = inclusive
        ? formatUnitPrice(gross / 100 / quantity)
        : (gross / 100 / quantity / (1 + window.SM.lineTaxRate(item))).toFixed(2);
    if (inclusive && plan.clean_prices) {
        item.unit_price_inc_tax = String(gross / 100 / quantity);
        delete item.details_json.price_rounding;
        window.SM.roundWorkshopPrice(item, true);
        item.unit_price_inc_tax = Number(item.unit_price_inc_tax).toFixed(2);
    }
}
function updateMultipleWorkshops(item, plan, inclusive) {
    item.workshops ??= JSON.parse(JSON.stringify(item.details_json?.multi_workshop?.rows || []));
    if (plan) window.SM.initializePricingSupplies(item, plan);
    item.workshops.forEach(row => {
        if (row?.row_type === 'heading') window.SM.commitWorkshopSpacing(row);
    });
    const workshopRows = item.workshops.filter(row => !window.SM.isWorkshopSpacing(row));
    const pricedWorkshopRows = workshopRows.filter(row => Number(row.workshop_hours) > 0 && row.workshop_seats !== '' && row.workshop_seats != null);
    const quantity = Math.round(workshopRows.reduce((sum, row) => sum + Number(row.workshop_hours || 0) * Number(row.workshop_seats || 0), 0) * 100) / 100;
    if (quantity > 0 && Number(item.quantity) === 1 && item.details_json?.multi_workshop && !item.details_json.multi_workshop.quantity_basis) {
        if (item.details_json.inclusive_unit_price == null) {
            const amount = window.SM.lineAmounts(inclusive ? { ...item, unit_price: Number(item.unit_price_inc_tax || 0) / (1 + window.SM.lineTaxRate(item)) } : item);
            item.details_json.inclusive_unit_price = Math.round((amount.net + amount.tax) * 100) / 100;
        }
        for (const field of ['unit_price', 'unit_price_inc_tax']) {
            if (item[field] != null) item[field] = field === 'unit_price_inc_tax' ? formatUnitPrice(Number(item[field]) / quantity) : (Number(item[field]) / quantity).toFixed(2);
        }
        if (item.details_json.inclusive_unit_price != null) item.details_json.inclusive_unit_price /= quantity;
        if (item.saved_pricing) { item.saved_pricing.price /= quantity; item.saved_pricing.quantity = quantity; }
    }
    item.quantity = quantity;
    item.details_json = { ...(item.details_json || {}), multi_workshop: { rows: item.workshops, quantity_basis: 'seat_hours' } };
    syncWorkshopNotes(item);
    if (!plan || !item.auto_pricing || !(quantity > 0)) return;
    let gross = 0;
    for (const row of pricedWorkshopRows) {
        const priced = { ...row, kind: 'workshop', auto_pricing: true, gst_applicable: item.gst_applicable };
        updateWorkshopLine(priced, plan, false);
        const amounts = window.SM.lineAmounts(priced);
        gross += Math.round((amounts.net + amounts.tax) * 100);
    }
    item.tax_rate = item.gst_applicable === false ? 0 : window.SM.currentGstRate();
    item.details_json.inclusive_unit_price = gross / 100 / quantity;
    item.details_json.pricing_basis = { participants: Number(plan.pricing_participants || 10) };
    item[inclusive ? 'unit_price_inc_tax' : 'unit_price'] = inclusive ? formatUnitPrice(gross / 100 / quantity) : (gross / 100 / quantity / (1 + window.SM.lineTaxRate(item))).toFixed(2);
    if (inclusive && plan.clean_prices) {
        // Keep the exact combined rate until the clean-price calculation rounds up.
        item.unit_price_inc_tax = String(gross / 100 / quantity);
        delete item.details_json.price_rounding;
        window.SM.roundWorkshopPrice(item, true);
        item.unit_price_inc_tax = Number(item.unit_price_inc_tax).toFixed(2);
    }
}

// Historical document totals may use document-level instead of line-level rounding.
window.SM.documentAmounts = (items, original = null) => {
    const lines = items.map(item => window.SM.lineAmounts(item));
    if (original && original.count === lines.length && lines.every(line => line.saved)) return original;
    const net = lines.reduce((sum, line) => sum + line.net, 0);
    const tax = lines.reduce((sum, line) => sum + line.tax, 0);
    return { net, tax, gross: net + tax };
};

window.SM.formatUnitPrice = formatUnitPrice;


window.SM.workshopFundingEditor = (item, catalog, billingLocked = false, allowSpacing = false, travelRow = false, locations = [], allowLocations = false) => ({
    item, catalog, locations: Array.isArray(locations) ? locations : Object.values(locations || {}), billingLocked, allowSpacing, travelRow, allowLocations, query: '', open: false, menuOpen: false, selected: 0, menuTop: 0, menuBottom: 0, menuAbove: false, menuListMaxHeight: 192, menuLeft: 0, menuWidth: 320,
    init() {
        this.item.details_json ??= {};
        this.item.details_json.workshop ??= {};
        this.item.details_json.workshop.allocation_basis ??= 'manual';
        this.item.details_json.workshop.allocation_seats ??= this.item.workshop_seats ?? this.item.details_json.workshop.seats ?? null;
    },
    get linkedWorkshop() { return this.catalog.find(option => option.id === this.item.details_json.workshop.linked_workshop_id); },
    get linkedLocation() {
        const locationId = this.item.location_id ?? this.item.details_json?.travel?.location_id ?? this.item.details_json?.workshop?.location_id;
        return this.locations.find(option => String(option.location_id ?? option.id) === String(locationId));
    },
    get linkedOption() { return this.linkedWorkshop || this.linkedLocation; },
    get usesWorkshopName() { return this.item.kind === 'workshop' && Object.hasOwn(this.item.details_json.workshop, 'name'); },
    get workshopName() { return this.usesWorkshopName ? this.item.details_json.workshop.name : this.item.description; },
    set workshopName(value) {
        if (this.usesWorkshopName) this.item.details_json.workshop.name = value;
        else this.item.description = value;
    },
    get descriptionValue() { return this.billingLocked && this.open ? this.query : this.workshopName; },
    set descriptionValue(value) {
        this.query = value;
        if (!this.billingLocked) {
            const previousDescription = this.item.description;
            if (this.allowLocations && !this.travelRow) {
                this.item.description = value;
                if (this.usesWorkshopName) this.item.details_json.workshop.name = value;
            } else {
                this.workshopName = value;
            }
            if (this.travelRow && this.item.location_id && value !== previousDescription) {
                this.item.location_id = null;
                this.item.details_json.travel ??= {};
                this.item.details_json.travel.location_id = null;
                this.item.details_json.workshop.linked_workshop_id = null;
            } else if (this.allowLocations && this.item.location_id && value !== previousDescription) {
                this.item.location_id = null;
                this.item.details_json.workshop.location_id = null;
                this.item.details_json.workshop.linked_workshop_id = null;
            }
        }
    },
    get seatValue() {
        const basis = this.item.details_json.workshop.allocation_basis;
        if (this.linkedWorkshop && basis === 'attendance') return Number(this.linkedWorkshop.attendance || 0);
        if (!this.billingLocked) return this.item.workshop_seats;
        if (this.linkedWorkshop && basis === 'capacity') return Number(this.linkedWorkshop.capacity || 0);
        if (this.linkedWorkshop && basis === 'tickets') return Number(this.linkedWorkshop.tickets || 0);
        return this.item.details_json.workshop.allocation_seats;
    },
    set seatValue(value) {
        if (!this.billingLocked) this.item.workshop_seats = value;
        this.item.details_json.workshop.allocation_seats = value;
        this.item.details_json.workshop.allocation_basis = 'manual';
    },
    syncLiveSeatValue() {
        if (this.billingLocked || !this.linkedWorkshop || this.item.details_json.workshop.allocation_basis !== 'attendance') return;
        const value = Number(this.linkedWorkshop.attendance || 0);
        if (Number(this.item.workshop_seats || 0) === value) return;
        this.item.workshop_seats = value;
        this.$nextTick(() => this.$dispatch('workshop-line-changed'));
    },
    get basisLabel() { return { manual: 'Manual seats', capacity: 'Workshop capacity', tickets: 'Registered tickets', attendance: 'Attendance count' }[this.item.details_json.workshop.allocation_basis]; },
    get matches() {
        const query = this.query.trim().toLowerCase();
        const description = String(this.workshopName || '').toLowerCase();
        const date = this.travelRow ? this.item.travel_date : this.item.workshop_date || this.item.details_json.workshop.date;
        const score = option => (date && option.date === date ? 4 : 0) + (option.title && description.includes(option.title.toLowerCase()) ? 3 : 0);
        const spacing = { id: '__workshop_spacing__', title: '- (space)', label: '- (space)', spacing: true };
        const catalog = this.travelRow || this.allowLocations ? [...this.catalog, ...this.locations] : this.catalog;
        const options = catalog.filter(option => !query || [option.label, option.title].some(value => String(value || '').toLowerCase().includes(query)));
        if (this.allowSpacing && (!query || spacing.label.includes(query))) options.unshift(spacing);
        return options.sort((a, b) => (a.spacing ? -1 : b.spacing ? 1 : score(b) - score(a))).slice(0, 12);
    },
    position(element, width = 320) {
        const rect = element.getBoundingClientRect();
        this.menuWidth = Math.min(width, window.innerWidth - 16);
        const viewportTop = window.visualViewport?.offsetTop ?? 0;
        const viewportBottom = viewportTop + (window.visualViewport?.height ?? window.innerHeight);
        const gap = 4;
        const edge = 8;
        const footerHeight = this.linkedOption ? 40 : 0;
        const spaceAbove = Math.max(0, rect.top - viewportTop - gap - edge);
        const spaceBelow = Math.max(0, viewportBottom - rect.bottom - gap - edge);
        this.menuAbove = spaceBelow < 220 && spaceAbove > spaceBelow;
        const availableHeight = this.menuAbove ? spaceAbove : spaceBelow;
        this.menuListMaxHeight = Math.max(0, Math.min(192, availableHeight - 16 - footerHeight));
        this.menuTop = rect.bottom + gap;
        this.menuBottom = Math.max(0, (window.visualViewport?.height ?? window.innerHeight) - (rect.top - viewportTop) + gap);
        this.menuLeft = Math.max(8, Math.min(rect.left, window.innerWidth - this.menuWidth - 8));
    },
    editDescription(element) {
        this.query = element.value || '';
        this.selected = 0;
        this.position(element, Math.max(320, element.offsetWidth));
        this.open = !!this.query.trim() && this.matches.length > 0;
    },
    browse(element) {
        this.position(element);
        this.query = '';
        this.selected = 0;
        this.open = !this.open;
        if (this.open) this.$nextTick?.(() => this.$refs?.workshopDescription?.focus());
    },
    move(step) { this.selected = Math.max(0, Math.min(this.matches.length - 1, this.selected + step)); },
    setSeats(basis) {
        const option = this.linkedWorkshop;
        if (basis !== 'manual' && !option) return;
        const value = basis === 'capacity' ? Number(option.capacity || 0) : basis === 'tickets' ? Number(option.tickets || 0) : basis === 'attendance' ? Number(option.attendance || 0) : this.seatValue;
        this.seatValue = value;
        this.item.details_json.workshop.allocation_basis = basis;
        this.menuOpen = false;
    },
    applyTravelHoursDefault(value) {
        const row = this.item;
        const travel = row.details_json.travel ??= {};
        const defaulted = !!(row.travel_hours_defaulted ?? travel.hours_defaulted);
        const changed = !!(row.travel_hours_user_changed ?? travel.hours_user_changed);
        const hours = row.travel_hours;
        const hasValue = hours !== '' && hours != null && Number.isFinite(Number(hours)) && Number(hours) !== 0;
        if (changed || (!defaulted && hasValue)) return;

        const numericDefault = value !== '' && value != null && Number.isFinite(Number(value)) ? Number(value) : null;
        if (numericDefault !== null) {
            row.travel_hours = numericDefault;
            row.travel_hours_defaulted = true;
        } else {
            if (defaulted) {
                row.travel_hours = '';
                row.travel_units = '';
                row.quantity = 0;
                travel.billable_units = 0;
                travel.quantity_basis = 'hours';
            }
            row.travel_hours_defaulted = false;
        }
        row.travel_hours_user_changed = false;
        travel.hours_defaulted = row.travel_hours_defaulted;
        travel.hours_user_changed = false;
        travel.location_id = row.location_id || null;
    },
    choose(option) {
        if (option?.spacing) {
            this.item.description = option.label;
            window.SM.commitWorkshopSpacing(this.item);
            this.query = '';
            this.open = false;
            this.$nextTick?.(() => this.$refs?.workshopDescription?.focus());
            return;
        }
        if (option?.type === 'location' && (this.travelRow || this.allowLocations)) {
            if (this.billingLocked) {
                this.open = false;
                return;
            }
            this.item.details_json.workshop.linked_workshop_id = null;
            this.item.location_id = option.location_id || option.id || null;
            if (this.allowLocations && !this.travelRow) {
                this.item.description = option.name || option.label;
                if (this.usesWorkshopName) this.item.details_json.workshop.name = option.name || option.label;
            } else {
                this.workshopName = option.name || option.label;
            }
            if (this.travelRow) {
                this.applyTravelHoursDefault(option.default_travel_hours);
            } else {
                this.item.details_json.workshop.location_id = this.item.location_id;
            }
            this.open = false;
            this.$nextTick?.(() => this.$refs?.workshopDescription?.focus());
            return;
        }
        this.item.details_json.workshop.linked_workshop_id = option?.id || null;
        if (option && !this.billingLocked) {
            this.workshopName = option.title || option.label;
            if (this.travelRow || this.allowLocations) this.item.location_id = option.location_id || null;
            if (this.allowLocations) this.item.details_json.workshop.location_id = this.item.location_id;
            if (this.travelRow) {
                this.item.travel_date = option.date || '';
                this.item.location_id = option.location_id || null;
                this.applyTravelHoursDefault(option.default_travel_hours);
            } else {
                if (option.hours > 0) this.item.workshop_hours = option.hours;
                this.item.workshop_date = option.date || '';
            }
        }
        if (option && !this.travelRow && this.item.details_json.workshop.allocation_basis !== 'manual') this.setSeats(this.item.details_json.workshop.allocation_basis);
        else if (!option && !this.travelRow) this.setSeats('manual');
        if (!option && this.travelRow) {
            this.item.location_id = null;
            this.item.details_json.travel ??= {};
            this.item.details_json.travel.location_id = null;
        }
        if (!option && this.allowLocations) {
            this.item.location_id = null;
            this.item.details_json.workshop.location_id = null;
        }
        this.open = false;
        this.$nextTick?.(() => this.$refs?.workshopDescription?.focus());
    },
});
