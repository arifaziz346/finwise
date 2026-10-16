export const money = (value = 0, currency = 'PKR') => `${currency} ${Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

/** Short form for chart axes and tight KPI cards: PKR 281.4K, PKR 1.2M. */
export const compactMoney = (value = 0, currency = '') => {
    const amount = Number(value || 0);
    const sign = amount < 0 ? '-' : '';
    const abs = Math.abs(amount);
    const prefix = currency ? `${currency} ` : '';

    if (abs >= 1_000_000_000) return `${sign}${prefix}${(abs / 1_000_000_000).toFixed(1)}B`;
    if (abs >= 1_000_000) return `${sign}${prefix}${(abs / 1_000_000).toFixed(abs >= 10_000_000 ? 0 : 1)}M`;
    if (abs >= 1_000) return `${sign}${prefix}${(abs / 1_000).toFixed(abs >= 100_000 ? 0 : 1)}K`;

    return `${sign}${prefix}${abs.toFixed(0)}`;
};

/** Number only, no currency prefix — used inside chart tooltips. */
export const amount = (value = 0) => Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export const percent = (value, digits = 1) => (value === null || value === undefined ? '—' : `${Number(value).toFixed(digits)}%`);

export const signedPercent = (value) => (value === null || value === undefined ? null : `${value > 0 ? '+' : ''}${Number(value).toFixed(0)}%`);
