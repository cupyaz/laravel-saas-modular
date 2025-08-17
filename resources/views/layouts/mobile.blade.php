<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5, user-scalable=yes">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <!-- Mobile-specific meta tags -->
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'Laravel SaaS') }}">
    <meta name="theme-color" content="#2563eb">
    
    <!-- SEO and social -->
    <title>{{ $title ?? config('app.name', 'Laravel SaaS') }}</title>
    <meta name="description" content="{{ $description ?? 'Modern SaaS Platform' }}">
    
    <!-- Preload critical resources -->
    <link rel="preload" href="{{ asset('css/app.css') }}" as="style">
    <link rel="preload" href="{{ asset('js/app.js') }}" as="script">
    
    <!-- Styles -->
    @vite(['resources/css/app.css'])
    
    <!-- Additional mobile-specific styles -->
    <style>
        :root {
            --vh: 1vh;
        }
        
        .full-height {
            height: calc(var(--vh, 1vh) * 100);
        }
        
        /* iOS safe area support */
        .ios-safe-area {
            padding-top: env(safe-area-inset-top);
            padding-bottom: env(safe-area-inset-bottom);
            padding-left: env(safe-area-inset-left);
            padding-right: env(safe-area-inset-right);
        }
        
        /* Touch feedback */
        .touch-feedback {
            -webkit-tap-highlight-color: rgba(59, 130, 246, 0.1);
        }
    </style>
    
    @stack('head')
</head>
<body class="h-full bg-gray-50 font-sans antialiased ios-safe-area">
    <!-- Skip to main content (accessibility) -->
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 bg-primary-600 text-white px-4 py-2 rounded-md z-max">
        Skip to main content
    </a>

    <!-- Mobile Header -->
    <header class="bg-white border-b border-gray-200 sticky top-0 z-mobile-nav ios-safe-area-top">
        <div class="container-mobile">
            <div class="flex items-center justify-between h-16">
                <!-- Logo -->
                <div class="flex items-center">
                    <a href="{{ route('dashboard') }}" class="flex items-center space-x-2 touch-feedback">
                        <div class="w-8 h-8 bg-primary-600 rounded-lg flex items-center justify-center">
                            <span class="text-white font-bold text-sm">{{ substr(config('app.name'), 0, 1) }}</span>
                        </div>
                        <span class="font-semibold text-gray-900 text-lg md:text-xl">
                            {{ config('app.name') }}
                        </span>
                    </a>
                </div>

                <!-- Mobile Menu Button -->
                <button type="button" class="hamburger md:hidden touch-feedback p-2" aria-label="Toggle navigation menu">
                    <span class="hamburger-line"></span>
                    <span class="hamburger-line"></span>
                    <span class="hamburger-line"></span>
                </button>

                <!-- Desktop Navigation -->
                <nav class="hidden md:flex items-center space-x-6">
                    <a href="{{ route('dashboard') }}" class="text-gray-700 hover:text-primary-600 transition-colors">
                        Dashboard
                    </a>
                    <a href="{{ route('usage') }}" class="text-gray-700 hover:text-primary-600 transition-colors">
                        Usage
                    </a>
                    <a href="{{ route('plans') }}" class="text-gray-700 hover:text-primary-600 transition-colors">
                        Plans
                    </a>
                    <a href="{{ route('profile') }}" class="text-gray-700 hover:text-primary-600 transition-colors">
                        Profile
                    </a>
                </nav>
            </div>
        </div>
    </header>

    <!-- Mobile Menu Overlay -->
    <div class="mobile-menu fixed inset-0 z-modal bg-black bg-opacity-50 hidden md:hidden">
        <div class="mobile-menu-content bg-white w-80 h-full shadow-xl transform -translate-x-full transition-transform duration-300 ease-in-out">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-primary-600 rounded-lg flex items-center justify-center">
                        <span class="text-white font-bold">{{ substr(config('app.name'), 0, 1) }}</span>
                    </div>
                    <div>
                        <div class="font-semibold text-gray-900">{{ Auth::user()->name ?? 'Guest' }}</div>
                        <div class="text-sm text-gray-500">{{ Auth::user()->email ?? '' }}</div>
                    </div>
                </div>
            </div>
            
            <nav class="py-6">
                <a href="{{ route('dashboard') }}" class="menu-item flex items-center px-6 py-3 text-gray-700 hover:bg-gray-50 touch-feedback">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"/>
                    </svg>
                    Dashboard
                </a>
                <a href="{{ route('usage') }}" class="menu-item flex items-center px-6 py-3 text-gray-700 hover:bg-gray-50 touch-feedback">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    Usage
                </a>
                <a href="{{ route('plans') }}" class="menu-item flex items-center px-6 py-3 text-gray-700 hover:bg-gray-50 touch-feedback">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                    </svg>
                    Plans
                </a>
                <a href="{{ route('profile') }}" class="menu-item flex items-center px-6 py-3 text-gray-700 hover:bg-gray-50 touch-feedback">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    Profile
                </a>
                
                <div class="border-t border-gray-200 mt-6 pt-6">
                    <a href="{{ route('settings') }}" class="menu-item flex items-center px-6 py-3 text-gray-700 hover:bg-gray-50 touch-feedback">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        Settings
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="menu-item flex items-center w-full px-6 py-3 text-gray-700 hover:bg-gray-50 touch-feedback">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                            Sign Out
                        </button>
                    </form>
                </div>
            </nav>
        </div>
    </div>

    <!-- Main Content -->
    <main id="main-content" class="flex-1 pb-20 md:pb-4">
        @if(session('success'))
            <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 mx-4 mt-4 rounded-md animate-fade-in">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 mx-4 mt-4 rounded-md animate-fade-in">
                {{ session('error') }}
            </div>
        @endif

        @yield('content')
    </main>

    <!-- Mobile Bottom Navigation -->
    <nav class="mobile-nav">
        <div class="flex">
            <a href="{{ route('dashboard') }}" class="mobile-nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}" data-tab="dashboard">
                <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"/>
                </svg>
                <span>Dashboard</span>
            </a>
            <a href="{{ route('usage') }}" class="mobile-nav-item {{ request()->routeIs('usage') ? 'active' : '' }}" data-tab="usage">
                <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                <span>Usage</span>
            </a>
            <a href="{{ route('plans') }}" class="mobile-nav-item {{ request()->routeIs('plans') ? 'active' : '' }}" data-tab="plans">
                <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                </svg>
                <span>Plans</span>
            </a>
            <a href="{{ route('profile') }}" class="mobile-nav-item {{ request()->routeIs('profile') ? 'active' : '' }}" data-tab="profile">
                <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                <span>Profile</span>
            </a>
        </div>
    </nav>

    <!-- Scripts -->
    @vite(['resources/js/app.js', 'resources/js/mobile-navigation.js'])
    
    <!-- Mobile-specific scripts -->
    <script>
        // Set viewport height for mobile browsers
        function setViewportHeight() {
            const vh = window.innerHeight * 0.01;
            document.documentElement.style.setProperty('--vh', `${vh}px`);
        }
        
        setViewportHeight();
        window.addEventListener('resize', setViewportHeight);
        window.addEventListener('orientationchange', () => {
            setTimeout(setViewportHeight, 100);
        });
        
        // Add touch feedback to buttons
        document.addEventListener('DOMContentLoaded', function() {
            const touchElements = document.querySelectorAll('.touch-feedback');
            touchElements.forEach(element => {
                element.addEventListener('touchstart', function() {
                    this.style.opacity = '0.7';
                }, { passive: true });
                
                element.addEventListener('touchend', function() {
                    this.style.opacity = '1';
                }, { passive: true });
                
                element.addEventListener('touchcancel', function() {
                    this.style.opacity = '1';
                }, { passive: true });
            });
        });
    </script>
    
    @stack('scripts')
</body>
</html>