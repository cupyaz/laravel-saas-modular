import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
        './resources/js/**/*.js',
        './modules/**/resources/views/**/*.blade.php',
        './modules/**/resources/js/**/*.vue',
        './public/**/*.html',
        './public/**/*.php',
    ],

    theme: {
        // Mobile-first breakpoints
        screens: {
            'xs': '475px',   // Extra small phones
            'sm': '640px',   // Small tablets
            'md': '768px',   // Tablets
            'lg': '1024px',  // Laptops
            'xl': '1280px',  // Desktops
            '2xl': '1536px', // Large screens
            // Mobile-specific breakpoints
            'mobile': {'max': '767px'},
            'tablet': {'min': '768px', 'max': '1023px'},
            'desktop': {'min': '1024px'},
            // Touch device detection
            'touch': {'raw': '(hover: none) and (pointer: coarse)'},
            'no-touch': {'raw': '(hover: hover) and (pointer: fine)'},
        },
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primary: {
                    50: '#eff6ff',
                    100: '#dbeafe',
                    200: '#bfdbfe',
                    300: '#93c5fd',
                    400: '#60a5fa',
                    500: '#3b82f6',
                    600: '#2563eb',
                    700: '#1d4ed8',
                    800: '#1e40af',
                    900: '#1e3a8a',
                    950: '#172554',
                },
                // Mobile-specific colors
                touch: {
                    light: '#f8fafc',
                    DEFAULT: '#f1f5f9',
                    dark: '#e2e8f0',
                },
            },
            // Mobile-optimized spacing
            spacing: {
                'safe-top': 'env(safe-area-inset-top)',
                'safe-bottom': 'env(safe-area-inset-bottom)',
                'safe-left': 'env(safe-area-inset-left)',
                'safe-right': 'env(safe-area-inset-right)',
                // Touch-optimized sizes
                'touch-sm': '36px',
                'touch': '44px',
                'touch-lg': '52px',
            },
            // Mobile-first typography
            fontSize: {
                'xs': ['0.75rem', { lineHeight: '1rem' }],
                'sm': ['0.875rem', { lineHeight: '1.25rem' }],
                'base': ['1rem', { lineHeight: '1.5rem' }],
                'lg': ['1.125rem', { lineHeight: '1.75rem' }],
                'xl': ['1.25rem', { lineHeight: '1.75rem' }],
                '2xl': ['1.5rem', { lineHeight: '2rem' }],
                '3xl': ['1.875rem', { lineHeight: '2.25rem' }],
                '4xl': ['2.25rem', { lineHeight: '2.5rem' }],
                // Mobile-optimized sizes
                'mobile-sm': ['0.875rem', { lineHeight: '1.5rem' }],
                'mobile-base': ['1rem', { lineHeight: '1.75rem' }],
                'mobile-lg': ['1.125rem', { lineHeight: '2rem' }],
            },
            // Animation optimized for mobile
            animation: {
                'fade-in': 'fadeIn 0.2s ease-in-out',
                'slide-up': 'slideUp 0.3s ease-out',
                'slide-down': 'slideDown 0.3s ease-out',
                'scale-in': 'scaleIn 0.2s ease-out',
                'bounce-gentle': 'bounceGentle 0.6s ease-in-out',
            },
            keyframes: {
                fadeIn: {
                    '0%': { opacity: '0' },
                    '100%': { opacity: '1' },
                },
                slideUp: {
                    '0%': { transform: 'translateY(100%)', opacity: '0' },
                    '100%': { transform: 'translateY(0)', opacity: '1' },
                },
                slideDown: {
                    '0%': { transform: 'translateY(-100%)', opacity: '0' },
                    '100%': { transform: 'translateY(0)', opacity: '1' },
                },
                scaleIn: {
                    '0%': { transform: 'scale(0.9)', opacity: '0' },
                    '100%': { transform: 'scale(1)', opacity: '1' },
                },
                bounceGentle: {
                    '0%, 100%': { transform: 'translateY(0)' },
                    '50%': { transform: 'translateY(-5px)' },
                },
            },
            // Mobile-optimized shadows
            boxShadow: {
                'mobile': '0 2px 8px -2px rgba(0, 0, 0, 0.1)',
                'mobile-lg': '0 4px 16px -4px rgba(0, 0, 0, 0.1)',
                'touch': '0 0 0 2px rgba(59, 130, 246, 0.1)',
            },
            // Z-index scale
            zIndex: {
                'modal': '1000',
                'dropdown': '1010',
                'tooltip': '1020',
                'mobile-nav': '1030',
                'notification': '1040',
                'max': '9999',
            },
        },
    },

    plugins: [
        // Add forms plugin for better form styling
        // require('@tailwindcss/forms'),
        // Add aspect ratio plugin
        // require('@tailwindcss/aspect-ratio'),
        
        // Custom plugin for mobile utilities
        function({ addUtilities, theme }) {
            const mobileUtilities = {
                '.touch-manipulation': {
                    'touch-action': 'manipulation',
                },
                '.touch-pan-x': {
                    'touch-action': 'pan-x',
                },
                '.touch-pan-y': {
                    'touch-action': 'pan-y',
                },
                '.touch-none': {
                    'touch-action': 'none',
                },
                '.scroll-smooth': {
                    'scroll-behavior': 'smooth',
                },
                '.overscroll-none': {
                    'overscroll-behavior': 'none',
                },
                '.overscroll-y-none': {
                    'overscroll-behavior-y': 'none',
                },
                '.ios-safe-area-top': {
                    'padding-top': 'env(safe-area-inset-top)',
                },
                '.ios-safe-area-bottom': {
                    'padding-bottom': 'env(safe-area-inset-bottom)',
                },
                '.ios-safe-area': {
                    'padding-top': 'env(safe-area-inset-top)',
                    'padding-bottom': 'env(safe-area-inset-bottom)',
                    'padding-left': 'env(safe-area-inset-left)',
                    'padding-right': 'env(safe-area-inset-right)',
                },
            };
            
            addUtilities(mobileUtilities);
        },
    ],
};