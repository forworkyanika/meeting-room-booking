const dateFmt = new Intl.DateTimeFormat('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
const timeFmt = new Intl.DateTimeFormat('en-GB', { hour: '2-digit', minute: '2-digit' });

export const formatDate = (iso) => dateFmt.format(new Date(iso));
export const formatTime = (iso) => timeFmt.format(new Date(iso));
export const formatRange = (startIso, endIso) => `${formatDate(startIso)} · ${formatTime(startIso)}–${formatTime(endIso)}`;

/** Date -> "YYYY-MM-DDTHH:mm" in the browser's local time, the format <input type="datetime-local"> wants. */
export function toLocalInput(date) {
    const pad = (n) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

/** The next half hour from now, used as the default start of a new booking. */
export function nextHalfHour() {
    const d = new Date();
    d.setSeconds(0, 0);
    d.setMinutes(d.getMinutes() < 30 ? 30 : 60);
    return d;
}

export const STATUS = {
    pending: { label: 'Pending', badge: 'text-bg-warning', icon: 'bi-hourglass-split' },
    approved: { label: 'Approved', badge: 'text-bg-success', icon: 'bi-check-circle' },
    rejected: { label: 'Rejected', badge: 'text-bg-danger', icon: 'bi-x-circle' },
    cancelled: { label: 'Cancelled', badge: 'text-bg-secondary', icon: 'bi-slash-circle' },
};
