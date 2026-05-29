export default {
    darkMode: 'class',
    content: ['./resources/**/*.blade.php', './resources/**/*.js', './resources/**/*.vue'],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'ui-sans-serif', 'system-ui'],
                mono: ['ui-monospace', 'SFMono-Regular', 'Menlo', 'monospace'],
            },
            colors: {
                primary: '#6366F1',
                success: '#10B981',
                danger: '#EF4444',
                warning: '#F59E0B',
                darkbg: '#0F172A',
                carddark: '#1E293B',
                textdark: '#F1F5F9',
            },
        },
    },
    plugins: [],
};
