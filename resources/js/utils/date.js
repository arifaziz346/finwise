import dayjs from 'dayjs';
export const date = (value) => value ? dayjs(value).format('MMM D, YYYY') : '-';
