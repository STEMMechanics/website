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
    if (hours > 0 && seats >= 0 && item.workshop_seats !== '' && item.workshop_seats != null) {
        item.quantity = Math.round(hours * seats * 100) / 100;
        item.details_json = { ...(item.details_json || {}), workshop: { ...(item.details_json?.workshop || {}), hours, seats, date: item.workshop_date || item.details_json?.workshop?.date || null, venue_supplied: !!item.venue_supplied, supplied_categories: { ...(item.supplied_categories || {}) } } };
        syncWorkshopNotes(item);
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
    if (step > 0 || inclusive) item.details_json = { ...(item.details_json || {}), inclusive_unit_price: gross };
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

const moneyRound = value => Math.sign(value) * Math.round((Math.abs(value) + Number.EPSILON) * 100) / 100;
window.SM.lineAmounts = item => {
    const qty = Number(item.quantity || 0), rate = item.gst_applicable !== false ? Number(item.tax_rate || 0.1) : 0;
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
function generatedWorkshopNotes(item) {
    const calculation = row => {
        const hours = Number(row.workshop_hours ?? row.hours ?? 0);
        const seats = Number(row.workshop_seats ?? row.seats ?? 0);
        return `${hours} ${hours === 1 ? 'hr' : 'hrs'} × ${seats} seats`;
    };
    if (item.kind === 'multi_workshop') {
        return (item.workshops ?? item.details_json?.multi_workshop?.rows ?? []).map(row => {
            const date = row.workshop_date ? row.workshop_date.split('-').reverse().join('/') + ' - ' : '';
            return '- ' + date + (row.description || '').trim() + ' - (' + calculation(row) + ')';
        }).join('\n');
    } else if (item.kind === 'workshop') {
        return calculation({ ...item.details_json?.workshop, ...item });
    }
    return '';
}
window.SM.defaultWorkshopDescription = item => {
    if (!['workshop', 'multi_workshop'].includes(item.kind)) return;
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
    item.details_json.workshop_notes_mode = 'manual';
};
window.SM.refreshWorkshopNotes = item => {
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
function updateMultipleWorkshops(item, plan, inclusive) {
    item.workshops ??= JSON.parse(JSON.stringify(item.details_json?.multi_workshop?.rows || []));
    const quantity = Math.round(item.workshops.reduce((sum, row) => sum + Number(row.workshop_hours || 0) * Number(row.workshop_seats || 0), 0) * 100) / 100;
    if (quantity > 0 && Number(item.quantity) === 1 && item.details_json?.multi_workshop && !item.details_json.multi_workshop.quantity_basis) {
        if (item.details_json.inclusive_unit_price == null) {
            const amount = window.SM.lineAmounts(inclusive ? { ...item, unit_price: Number(item.unit_price_inc_tax || 0) / (item.gst_applicable === false ? 1 : 1.1) } : item);
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
    for (const row of item.workshops) {
        const priced = { ...row, kind: 'workshop', auto_pricing: true, gst_applicable: item.gst_applicable };
        updateWorkshopLine(priced, plan, false);
        const amounts = window.SM.lineAmounts(priced);
        gross += Math.round((amounts.net + amounts.tax) * 100);
    }
    item.details_json.inclusive_unit_price = gross / 100 / quantity;
    item[inclusive ? 'unit_price_inc_tax' : 'unit_price'] = inclusive ? formatUnitPrice(gross / 100 / quantity) : (gross / 100 / quantity / (item.gst_applicable === false ? 1 : 1.1)).toFixed(2);
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


window.SM.workshopFundingEditor = (item, catalog, billingLocked = false) => ({
    item, catalog, billingLocked, query: '', open: false, menuOpen: false, selected: 0, menuTop: 0, menuLeft: 0, menuWidth: 320,
    init() {
        this.item.details_json ??= {};
        this.item.details_json.workshop ??= {};
        this.item.details_json.workshop.allocation_basis ??= 'manual';
        this.item.details_json.workshop.allocation_seats ??= this.item.workshop_seats ?? this.item.details_json.workshop.seats ?? null;
    },
    get linkedWorkshop() { return this.catalog.find(option => option.id === this.item.details_json.workshop.linked_workshop_id); },
    get seatValue() {
        if (!this.billingLocked) return this.item.workshop_seats;
        const basis = this.item.details_json.workshop.allocation_basis;
        if (this.linkedWorkshop && basis === 'capacity') return Number(this.linkedWorkshop.capacity || 0);
        if (this.linkedWorkshop && basis === 'tickets') return Number(this.linkedWorkshop.tickets || 0);
        if (this.linkedWorkshop && basis === 'attendance') return Number(this.linkedWorkshop.attendance || 0);
        return this.item.details_json.workshop.allocation_seats;
    },
    set seatValue(value) {
        if (!this.billingLocked) this.item.workshop_seats = value;
        this.item.details_json.workshop.allocation_seats = value;
        this.item.details_json.workshop.allocation_basis = 'manual';
    },
    get basisLabel() { return { manual: 'Manual seats', capacity: 'Workshop capacity', tickets: 'Registered tickets', attendance: 'Attendance count' }[this.item.details_json.workshop.allocation_basis]; },
    get matches() {
        const query = this.query.trim().toLowerCase();
        const description = String(this.item.description || '').toLowerCase();
        const date = this.item.workshop_date || this.item.details_json.workshop.date;
        const score = option => (date && option.date === date ? 4 : 0) + (option.title && description.includes(option.title.toLowerCase()) ? 3 : 0);
        return this.catalog.filter(option => !query || option.label.toLowerCase().includes(query)).sort((a, b) => score(b) - score(a)).slice(0, 12);
    },
    position(element, width = 320) {
        const rect = element.getBoundingClientRect();
        this.menuWidth = Math.min(width, window.innerWidth - 16);
        this.menuTop = Math.max(8, Math.min(rect.bottom + 4, window.innerHeight - 320));
        this.menuLeft = Math.max(8, Math.min(rect.left, window.innerWidth - this.menuWidth - 8));
    },
    editDescription(element) {
        if (this.item.details_json.workshop.linked_workshop_id) return;
        this.query = this.item.description || '';
        this.selected = 0;
        this.position(element, Math.max(320, element.offsetWidth));
        this.open = !!this.query.trim();
    },
    browse(element) { this.position(element); this.query = ''; this.selected = 0; this.open = !this.open; },
    move(step) { this.selected = Math.max(0, Math.min(this.matches.length - 1, this.selected + step)); },
    setSeats(basis) {
        const option = this.linkedWorkshop;
        if (basis !== 'manual' && !option) return;
        const value = basis === 'capacity' ? Number(option.capacity || 0) : basis === 'tickets' ? Number(option.tickets || 0) : basis === 'attendance' ? Number(option.attendance || 0) : this.seatValue;
        this.seatValue = value;
        this.item.details_json.workshop.allocation_basis = basis;
        this.menuOpen = false;
    },
    choose(option) {
        this.item.details_json.workshop.linked_workshop_id = option?.id || null;
        if (option && !this.billingLocked) {
            this.item.description = option.title || option.label;
            if (option.hours > 0) this.item.workshop_hours = option.hours;
            this.item.workshop_date = option.date || '';
        }
        if (option && this.item.details_json.workshop.allocation_basis !== 'manual') this.setSeats(this.item.details_json.workshop.allocation_basis);
        else if (!option) this.setSeats('manual');
        this.open = false;
    },
});
