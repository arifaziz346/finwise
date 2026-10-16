import { ArcElement, BarElement, CategoryScale, Chart as ChartJS, Filler, Legend, LineElement, LinearScale, PointElement, Tooltip } from 'chart.js';
import { amount, compactMoney } from './currency';

// Register once for the whole app so every chart view shares the same setup.
let registered = false;

export const registerCharts = () => {
    if (registered) return;
    ChartJS.register(ArcElement, BarElement, CategoryScale, Filler, Legend, LineElement, LinearScale, PointElement, Tooltip);
    ChartJS.defaults.font.family = "Inter, ui-sans-serif, system-ui, sans-serif";
    ChartJS.defaults.color = '#64748B';
    registered = true;
};

export const palette = {
    income: '#10B981',
    incomeSoft: 'rgba(16, 185, 129, 0.16)',
    expense: '#EF4444',
    expenseSoft: 'rgba(239, 68, 68, 0.16)',
    net: '#6366F1',
    netSoft: 'rgba(99, 102, 241, 0.14)',
    running: '#0EA5E9',
    grid: 'rgba(148, 163, 184, 0.22)',
};

/** Stable, readable colours for category series (donut + bars). */
export const categoryColors = [
    '#6366F1', '#10B981', '#F59E0B', '#EF4444', '#0EA5E9',
    '#8B5CF6', '#14B8A6', '#F97316', '#EC4899', '#84CC16',
    '#06B6D4', '#A855F7',
];

export const colorAt = (index) => categoryColors[index % categoryColors.length];

/**
 * User-picked category colours are sometimes near-black or near-white, which
 * disappears against the chart background. Mix such a colour toward a legible
 * palette entry while keeping the hue recognisable.
 */
export const readableColor = (color, index = 0, { dark = false } = {}) => {
    if (!color || !/^#[0-9a-f]{3,8}$/i.test(color)) return colorAt(index);

    const hex = color.length === 4
        ? `#${color[1]}${color[1]}${color[2]}${color[2]}${color[3]}${color[3]}`
        : color.slice(0, 7);

    const r = parseInt(hex.slice(1, 3), 16);
    const g = parseInt(hex.slice(3, 5), 16);
    const b = parseInt(hex.slice(5, 7), 16);
    // Perceived brightness, 0-255.
    const luminance = 0.299 * r + 0.587 * g + 0.114 * b;

    if (!dark && (luminance > 205 || luminance < 40)) return colorAt(index);
    if (dark && luminance < 70) return colorAt(index);

    return hex;
};

const gridOptions = {
    color: palette.grid,
    drawTicks: false,
};

const currencyTicks = {
    ticks: {
        callback: (value) => compactMoney(value),
        maxTicksLimit: 6,
    },
    grid: gridOptions,
    border: { display: false },
};

/** Shared options for the income-vs-expense bars / lines. */
export const trendOptions = ({ granularity = 'monthly', showRunning = false, currency = 'PKR' } = {}) => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
        legend: {
            position: 'top',
            align: 'end',
            labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 8, padding: 14, font: { size: 11 } },
        },
        tooltip: {
            backgroundColor: 'rgba(15, 23, 42, 0.94)',
            padding: 12,
            cornerRadius: 10,
            displayColors: true,
            usePointStyle: true,
            callbacks: {
                label: (context) => ` ${context.dataset.label}: ${currency} ${amount(context.parsed.y)}`,
            },
        },
    },
    scales: {
        x: {
            grid: { display: false },
            border: { display: false },
            ticks: {
                maxRotation: 0,
                autoSkipPadding: 12,
                font: { size: 10 },
            },
        },
        y: { ...currencyTicks, beginAtZero: true, stacked: false },
        ...(showRunning ? { y1: { position: 'right', grid: { display: false }, border: { display: false }, ticks: { callback: (value) => compactMoney(value), maxTicksLimit: 4, font: { size: 10 } } } } : {}),
    },
    // Daily ranges can hold hundreds of bars — keep them hairline-thin instead of hiding them.
    barPercentage: granularity === 'daily' ? 0.9 : 0.8,
    categoryPercentage: granularity === 'daily' ? 0.95 : 0.75,
});

/** Shared options for the category doughnuts. */
export const donutOptions = ({ currency = 'PKR', total = 0 } = {}) => ({
    responsive: true,
    maintainAspectRatio: false,
    cutout: '62%',
    plugins: {
        legend: {
            position: 'bottom',
            labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 8, padding: 10, font: { size: 11 } },
        },
        tooltip: {
            backgroundColor: 'rgba(15, 23, 42, 0.94)',
            padding: 12,
            cornerRadius: 10,
            callbacks: {
                label: (context) => {
                    const value = Number(context.parsed || 0);
                    const share = total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';

                    return ` ${context.label}: ${currency} ${amount(value)} (${share}%)`;
                },
            },
        },
    },
});

/** Horizontal bars for "top categories" comparisons. */
export const rankingOptions = ({ currency = 'PKR' } = {}) => ({
    indexAxis: 'y',
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: 'rgba(15, 23, 42, 0.94)',
            padding: 10,
            cornerRadius: 10,
            callbacks: { label: (context) => ` ${currency} ${amount(context.parsed.x)}` },
        },
    },
    scales: {
        x: { ...currencyTicks, beginAtZero: true },
        y: { grid: { display: false }, border: { display: false }, ticks: { font: { size: 11 } } },
    },
});

/** Small cumulative savings area chart. */
export const runningOptions = ({ currency = 'PKR' } = {}) => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: 'rgba(15, 23, 42, 0.94)',
            padding: 12,
            cornerRadius: 10,
            callbacks: { label: (context) => ` Running net: ${currency} ${amount(context.parsed.y)}` },
        },
    },
    scales: {
        x: { grid: { display: false }, border: { display: false }, ticks: { maxRotation: 0, autoSkipPadding: 16, font: { size: 10 } } },
        y: { ...currencyTicks, beginAtZero: false },
    },
});

export { compactMoney, amount };
