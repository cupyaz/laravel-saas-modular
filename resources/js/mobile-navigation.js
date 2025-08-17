/**
 * Mobile Navigation System
 * Handles mobile-first navigation with touch optimization
 */

class MobileNavigation {
    constructor() {
        this.init();
        this.setupEventListeners();
        this.setupTouchGestures();
    }

    init() {
        this.isMenuOpen = false;
        this.activeSection = null;
        this.touchStartX = 0;
        this.touchStartY = 0;
        this.touchEndX = 0;
        this.touchEndY = 0;
        
        // DOM elements
        this.hamburger = document.querySelector('.hamburger');
        this.mobileMenu = document.querySelector('.mobile-menu');
        this.mobileNavItems = document.querySelectorAll('.mobile-nav-item');
        this.body = document.body;
        
        this.initializeActiveStates();
    }

    setupEventListeners() {
        // Hamburger menu toggle
        if (this.hamburger) {
            this.hamburger.addEventListener('click', (e) => {
                e.preventDefault();
                this.toggleMenu();
            });
        }

        // Mobile nav item clicks
        this.mobileNavItems.forEach(item => {
            item.addEventListener('click', (e) => {
                this.handleNavItemClick(e);
            });
        });

        // Close menu on outside click
        document.addEventListener('click', (e) => {
            if (this.isMenuOpen && !e.target.closest('.mobile-menu') && !e.target.closest('.hamburger')) {
                this.closeMenu();
            }
        });

        // Handle window resize
        window.addEventListener('resize', () => {
            this.handleResize();
        });

        // Handle orientation change
        window.addEventListener('orientationchange', () => {
            setTimeout(() => {
                this.handleResize();
            }, 100);
        });

        // Back button handling for mobile
        window.addEventListener('popstate', () => {
            this.closeMenu();
            this.updateActiveStates();
        });
    }

    setupTouchGestures() {
        // Swipe to close menu
        if (this.mobileMenu) {
            this.mobileMenu.addEventListener('touchstart', (e) => {
                this.touchStartX = e.changedTouches[0].screenX;
                this.touchStartY = e.changedTouches[0].screenY;
            }, { passive: true });

            this.mobileMenu.addEventListener('touchend', (e) => {
                this.touchEndX = e.changedTouches[0].screenX;
                this.touchEndY = e.changedTouches[0].screenY;
                this.handleSwipeGesture();
            }, { passive: true });
        }

        // Edge swipe to open menu
        document.addEventListener('touchstart', (e) => {
            const touch = e.touches[0];
            if (touch.clientX < 20 && !this.isMenuOpen) {
                this.touchStartX = touch.screenX;
                this.touchStartY = touch.screenY;
                this.isEdgeSwipe = true;
            }
        }, { passive: true });

        document.addEventListener('touchend', (e) => {
            if (this.isEdgeSwipe) {
                this.touchEndX = e.changedTouches[0].screenX;
                this.touchEndY = e.changedTouches[0].screenY;
                this.handleEdgeSwipe();
                this.isEdgeSwipe = false;
            }
        }, { passive: true });
    }

    toggleMenu() {
        if (this.isMenuOpen) {
            this.closeMenu();
        } else {
            this.openMenu();
        }
    }

    openMenu() {
        this.isMenuOpen = true;
        this.hamburger?.classList.add('active');
        this.mobileMenu?.classList.add('active');
        this.body.classList.add('menu-open', 'overflow-hidden');
        
        // Animate menu items
        this.animateMenuItems(true);
        
        // Trigger custom event
        this.dispatchEvent('mobileMenuOpened');
    }

    closeMenu() {
        this.isMenuOpen = false;
        this.hamburger?.classList.remove('active');
        this.mobileMenu?.classList.remove('active');
        this.body.classList.remove('menu-open', 'overflow-hidden');
        
        // Animate menu items
        this.animateMenuItems(false);
        
        // Trigger custom event
        this.dispatchEvent('mobileMenuClosed');
    }

    handleNavItemClick(e) {
        const item = e.currentTarget;
        const href = item.getAttribute('href') || item.dataset.href;
        
        if (href && href.startsWith('#')) {
            // Handle anchor links
            e.preventDefault();
            this.scrollToSection(href);
        } else if (href) {
            // Handle page navigation
            this.closeMenu();
            // Allow default navigation
        }
        
        this.setActiveNavItem(item);
    }

    setActiveNavItem(activeItem) {
        this.mobileNavItems.forEach(item => {
            item.classList.remove('active');
        });
        activeItem.classList.add('active');
        this.activeSection = activeItem.dataset.section;
    }

    scrollToSection(sectionId) {
        const section = document.querySelector(sectionId);
        if (section) {
            const offset = this.getScrollOffset();
            const targetPosition = section.offsetTop - offset;
            
            window.scrollTo({
                top: targetPosition,
                behavior: 'smooth'
            });
            
            this.closeMenu();
        }
    }

    getScrollOffset() {
        // Account for fixed headers or mobile navigation
        const header = document.querySelector('.header-fixed');
        return header ? header.offsetHeight : 0;
    }

    handleSwipeGesture() {
        const deltaX = this.touchEndX - this.touchStartX;
        const deltaY = Math.abs(this.touchEndY - this.touchStartY);
        
        // Horizontal swipe with minimal vertical movement
        if (Math.abs(deltaX) > 50 && deltaY < 100) {
            if (deltaX < 0 && this.isMenuOpen) {
                // Swipe left to close
                this.closeMenu();
            }
        }
    }

    handleEdgeSwipe() {
        const deltaX = this.touchEndX - this.touchStartX;
        const deltaY = Math.abs(this.touchEndY - this.touchStartY);
        
        // Right swipe from left edge
        if (deltaX > 50 && deltaY < 100 && !this.isMenuOpen) {
            this.openMenu();
        }
    }

    animateMenuItems(show) {
        const items = this.mobileMenu?.querySelectorAll('.menu-item');
        if (!items) return;

        items.forEach((item, index) => {
            if (show) {
                setTimeout(() => {
                    item.classList.add('animate-slide-down');
                }, index * 50);
            } else {
                item.classList.remove('animate-slide-down');
            }
        });
    }

    handleResize() {
        const isMobile = window.innerWidth < 768;
        
        if (!isMobile && this.isMenuOpen) {
            this.closeMenu();
        }
        
        // Update viewport height for mobile browsers
        this.updateViewportHeight();
    }

    updateViewportHeight() {
        // Fix for mobile browser address bar height issues
        const vh = window.innerHeight * 0.01;
        document.documentElement.style.setProperty('--vh', `${vh}px`);
    }

    initializeActiveStates() {
        // Set initial active state based on current URL
        const currentPath = window.location.pathname;
        this.mobileNavItems.forEach(item => {
            const href = item.getAttribute('href') || item.dataset.href;
            if (href === currentPath) {
                item.classList.add('active');
                this.activeSection = item.dataset.section;
            }
        });
    }

    updateActiveStates() {
        // Update active states when URL changes
        this.initializeActiveStates();
    }

    dispatchEvent(eventName, data = {}) {
        const event = new CustomEvent(eventName, {
            detail: data,
            bubbles: true
        });
        document.dispatchEvent(event);
    }

    // Public API methods
    isOpen() {
        return this.isMenuOpen;
    }

    open() {
        this.openMenu();
    }

    close() {
        this.closeMenu();
    }

    toggle() {
        this.toggleMenu();
    }

    setActive(sectionId) {
        const item = document.querySelector(`[data-section="${sectionId}"]`);
        if (item) {
            this.setActiveNavItem(item);
        }
    }
}

// Bottom Tab Navigation for Mobile
class MobileTabNavigation {
    constructor() {
        this.init();
        this.setupEventListeners();
    }

    init() {
        this.tabs = document.querySelectorAll('.mobile-nav-item');
        this.activeTab = null;
        this.initializeActiveTab();
    }

    setupEventListeners() {
        this.tabs.forEach(tab => {
            tab.addEventListener('click', (e) => {
                this.handleTabClick(e);
            });
        });

        // Handle badge updates
        document.addEventListener('badgeUpdate', (e) => {
            this.updateBadge(e.detail.tab, e.detail.count);
        });
    }

    handleTabClick(e) {
        const tab = e.currentTarget;
        const href = tab.getAttribute('href') || tab.dataset.href;
        
        // Prevent default if it's a hash link
        if (href?.startsWith('#')) {
            e.preventDefault();
        }
        
        this.setActiveTab(tab);
        
        // Add touch feedback
        this.addTouchFeedback(tab);
        
        // Trigger navigation event
        this.dispatchEvent('tabNavigation', {
            tab: tab.dataset.tab,
            href: href
        });
    }

    setActiveTab(activeTab) {
        this.tabs.forEach(tab => {
            tab.classList.remove('active');
        });
        activeTab.classList.add('active');
        this.activeTab = activeTab;
    }

    addTouchFeedback(tab) {
        tab.classList.add('scale-95');
        setTimeout(() => {
            tab.classList.remove('scale-95');
        }, 150);
    }

    updateBadge(tabId, count) {
        const tab = document.querySelector(`[data-tab="${tabId}"]`);
        if (!tab) return;

        let badge = tab.querySelector('.badge');
        
        if (count > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'badge absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center';
                tab.appendChild(badge);
            }
            badge.textContent = count > 99 ? '99+' : count;
        } else if (badge) {
            badge.remove();
        }
    }

    initializeActiveTab() {
        // Set initial active tab based on current page
        const currentPath = window.location.pathname;
        this.tabs.forEach(tab => {
            const href = tab.getAttribute('href') || tab.dataset.href;
            if (href === currentPath) {
                this.setActiveTab(tab);
            }
        });
    }

    dispatchEvent(eventName, data = {}) {
        const event = new CustomEvent(eventName, {
            detail: data,
            bubbles: true
        });
        document.dispatchEvent(event);
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    // Initialize mobile navigation
    window.mobileNav = new MobileNavigation();
    window.mobileTabNav = new MobileTabNavigation();
    
    // Set initial viewport height
    const vh = window.innerHeight * 0.01;
    document.documentElement.style.setProperty('--vh', `${vh}px`);
});

// Export for module usage
export { MobileNavigation, MobileTabNavigation };