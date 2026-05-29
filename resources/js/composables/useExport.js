export const useExport = () => ({ download: (url) => window.open(`/api/v1${url}`, '_blank') });
