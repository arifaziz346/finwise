import dayjs from 'dayjs';

export const date = (value) => value ? dayjs(value).format('MMM D, YYYY') : '-';

export const shortDate = (value) => value ? dayjs(value).format('MMM D') : '-';

export const monthLabel = (value) => value ? dayjs(value).format('MMM YYYY') : '-';

/** X-axis friendly label that stays short for long ranges. */
export const axisDate = (value) => value ? dayjs(value).format('D MMM') : '';

export const relativeDays = (value) => {
    if (!value) return '';
    const days = dayjs().startOf('day').diff(dayjs(value).startOf('day'), 'day');

    if (days === 0) return 'Today';
    if (days === 1) return 'Yesterday';
    if (days === -1) return 'Tomorrow';
    if (days > 1) return `${days} days ago`;
    if (days < -1) return `in ${Math.abs(days)} days`;

    return date(value);
};

export const dayOfWeek = (value) => (value ? dayjs(value).format('ddd') : '');
