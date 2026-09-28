const parseWorkshopDate = (value) => {
    const raw = String(value || '').trim();
    if (!raw) return null;

    const parsed = new Date(raw.replace(' ', 'T'));
    return Number.isNaN(parsed.getTime()) ? null : parsed;
};

const pad = (value) => String(value).padStart(2, '0');

const formatDatePattern = (date, pattern) => {
    const values = {
        yyyy: String(date.getFullYear()),
        yy: String(date.getFullYear()).slice(-2),
        mmmm: new Intl.DateTimeFormat('en-AU', { month: 'long' }).format(date),
        mmm: new Intl.DateTimeFormat('en-AU', { month: 'short' }).format(date),
        mm: pad(date.getMonth() + 1),
        m: String(date.getMonth() + 1),
        dddd: new Intl.DateTimeFormat('en-AU', { weekday: 'long' }).format(date),
        ddd: new Intl.DateTimeFormat('en-AU', { weekday: 'short' }).format(date),
        dd: pad(date.getDate()),
        d: String(date.getDate()),
    };

    return String(pattern).replace(/yyyy|mmmm|dddd|mmm|ddd|yy|mm|dd|m|d/g, (token) => values[token]);
};

const htmlToPlainText = (content) => {
    const html = String(content || '')
        .replace(/<br\s*\/?\s*>/gi, '\n')
        .replace(/<\/p\s*>/gi, '\n\n')
        .replace(/<li\b[^>]*>/gi, '\n• ')
        .replace(/<\/(?:div|li|h[1-6]|blockquote|ul|ol)>/gi, '\n');

    if (typeof document === 'undefined') {
        return html.replace(/<[^>]*>/g, '');
    }

    const template = document.createElement('template');
    template.innerHTML = html;
    return template.content.textContent || '';
};

export const resolveWorkshopTaskCopy = (content, values = {}) => {
    let text = htmlToPlainText(content);
    const startDate = values.startDate instanceof Date ? values.startDate : parseWorkshopDate(values.startsAt);

    text = text.replace(/\{[^{}]+\}/g, (token) => {
        if (Object.prototype.hasOwnProperty.call(values, token)) {
            return String(values[token] ?? '');
        }

        const datePattern = token.match(/^\{date-([dmy\s/.,-]+)\}$/i);
        if (datePattern && startDate) {
            return formatDatePattern(startDate, datePattern[1]);
        }

        return token;
    });

    return text
        .replace(/[\t ]+\n/g, '\n')
        .replace(/\n[\t ]+/g, '\n')
        .replace(/\n{3,}/g, '\n\n')
        .trim();
};

const formatTime = (date) => new Intl.DateTimeFormat('en-AU', {
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
}).format(date).replace(/\s/g, '').toLowerCase();

const formatCost = (value) => {
    const raw = String(value || '').trim();
    if (!raw || /^\$?\s*0(?:\.0+)?$/i.test(raw) || raw.toLowerCase() === 'free') return 'Free';

    const numeric = raw.replace(/[,$\s]/g, '');
    if (/^\d+(?:\.\d+)?$/.test(numeric)) return `$${Number(numeric).toFixed(2)}`;

    return raw;
};

window.SM = window.SM || {};
window.SM.workshopTaskCopy = ({ publicUrl = '' } = {}) => ({
    workshopPublicUrl: String(publicUrl || ''),

    taskPreviewAvailable(task) {
        const content = htmlToPlainText(task?.notes || '').trim();
        const name = String(task?.name || '').toLowerCase();
        return content !== '' && (/social|facebook|instagram|\bpost\b/.test(name) || /\{(?:date-|start-time|end-time|time-range|location|ages|cost|workshop-url)/i.test(content));
    },

    workshopTaskPlaceholderValues() {
        // This revision is incremented on form input so the preview remains reactive
        // even for values that are held by ordinary inputs rather than x-model.
        this.workshopTaskPreviewRevision;

        const form = document.getElementById('workshop-form');
        const read = (name) => {
            const field = form?.elements?.namedItem(name);
            return field && typeof field.value === 'string' ? field.value.trim() : '';
        };

        const startsAt = String(this.manualStartsAt || read('starts_at') || '').trim();
        const endsAt = String(this.manualEndsAt || read('ends_at') || '').trim();
        const startDate = parseWorkshopDate(startsAt);
        const endDate = parseWorkshopDate(endsAt);
        const type = String(this.type || read('type') || '').toLowerCase();
        const format = String(this.workshopFormat || read('format') || '').toLowerCase();
        const locationId = String(this.selectedLocationId || read('location_id') || '').trim();
        const selectedLocation = (this.locations || []).find((location) => String(location.id) === locationId);
        let location = selectedLocation?.name || '';

        if (type === 'stemcraft') location = 'STEMCraft';
        else if (type === 'online' || (format === 'course' && !locationId)) location = 'Online';
        else if (!location) location = locationId ? 'Not specified' : 'Online';

        const ages = read('ages') || 'Not specified';
        const cost = formatCost(read('price'));
        const startTime = startDate ? formatTime(startDate) : 'Not specified';
        const endTime = endDate ? formatTime(endDate) : 'Not specified';
        let timeRange = 'Not specified';
        if (startDate && endDate) {
            const compactStart = startTime.replace(/(am|pm)$/, '');
            timeRange = `${startTime.slice(-2) === endTime.slice(-2) ? compactStart : startTime}-${endTime}`;
        }

        let longDate = 'Not specified';
        let shortDate = 'Not specified';
        let weekdayDate = 'Not specified';
        if (startDate) {
            const weekday = new Intl.DateTimeFormat('en-AU', { weekday: 'long' }).format(startDate);
            const abbreviatedWeekday = new Intl.DateTimeFormat('en-AU', { weekday: 'short' }).format(startDate);
            const month = new Intl.DateTimeFormat('en-AU', { month: 'long' }).format(startDate);
            longDate = `${weekday} ${startDate.getDate()} ${month}`;
            shortDate = `${pad(startDate.getDate())}/${pad(startDate.getMonth() + 1)}/${startDate.getFullYear()}`;
            weekdayDate = `${abbreviatedWeekday} ${shortDate}`;
        }

        return {
            startsAt,
            startDate,
            '{date-short}': shortDate,
            '{date-long}': longDate,
            '{date-ddd dd/mm/yyyy}': weekdayDate,
            '{start-time}': startTime,
            '{end-time}': endTime,
            '{time-range}': timeRange,
            '{location}': location,
            '{ages}': ages,
            '{cost}': cost,
            '{workshop-url}': this.workshopPublicUrl,
        };
    },

    resolvedWorkshopTaskCopy(task) {
        return resolveWorkshopTaskCopy(task?.notes || '', this.workshopTaskPlaceholderValues());
    },

    async copyWorkshopTaskCopy(task) {
        const text = this.resolvedWorkshopTaskCopy(task);
        if (!text) return;

        try {
            if (navigator.clipboard?.writeText) {
                await navigator.clipboard.writeText(text);
            } else {
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.setAttribute('readonly', '');
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                const copied = document.execCommand('copy');
                textarea.remove();
                if (!copied) throw new Error('Clipboard copy failed');
            }

            task.copy_status = 'Copied';
        } catch {
            task.copy_status = 'Copy failed';
        }

        window.setTimeout(() => {
            task.copy_status = '';
        }, 1800);
    },
});
