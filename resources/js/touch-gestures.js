/**
 * Touch Gestures Handler
 * Provides touch gesture support for mobile interactions
 */

class TouchGestureHandler {
    constructor(element, options = {}) {
        this.element = element;
        this.options = {
            threshold: 50,           // Minimum distance for swipe
            restraint: 100,          // Maximum distance perpendicular to swipe direction
            allowedTime: 500,        // Maximum time allowed to travel that distance
            enableSwipe: true,
            enablePinch: true,
            enableTap: true,
            enableLongPress: true,
            longPressDelay: 500,
            ...options
        };
        
        this.reset();
        this.init();
    }

    init() {
        if (!this.element) return;
        
        this.setupEventListeners();
    }

    reset() {
        this.startX = 0;
        this.startY = 0;
        this.endX = 0;
        this.endY = 0;
        this.startTime = 0;
        this.endTime = 0;
        this.distX = 0;
        this.distY = 0;
        this.elapsedTime = 0;
        this.direction = null;
        
        // Pinch/zoom variables
        this.initialDistance = 0;
        this.currentDistance = 0;
        this.initialScale = 1;
        this.currentScale = 1;
        
        // Long press
        this.longPressTimer = null;
        this.isLongPress = false;
        
        // Multi-touch
        this.touches = [];
    }

    setupEventListeners() {
        // Touch events
        this.element.addEventListener('touchstart', this.handleTouchStart.bind(this), { passive: false });
        this.element.addEventListener('touchmove', this.handleTouchMove.bind(this), { passive: false });
        this.element.addEventListener('touchend', this.handleTouchEnd.bind(this), { passive: false });
        this.element.addEventListener('touchcancel', this.handleTouchCancel.bind(this), { passive: false });
        
        // Mouse events for desktop testing
        this.element.addEventListener('mousedown', this.handleMouseDown.bind(this));
        this.element.addEventListener('mousemove', this.handleMouseMove.bind(this));
        this.element.addEventListener('mouseup', this.handleMouseUp.bind(this));
        this.element.addEventListener('mouseleave', this.handleMouseLeave.bind(this));
    }

    handleTouchStart(e) {
        this.reset();
        
        const touch = e.touches[0];
        this.startX = touch.clientX;
        this.startY = touch.clientY;
        this.startTime = Date.now();
        
        // Store all touches for multi-touch gestures
        this.touches = Array.from(e.touches);
        
        // Handle pinch/zoom for two fingers
        if (e.touches.length === 2) {
            this.initialDistance = this.getDistance(e.touches[0], e.touches[1]);
            this.initialScale = this.currentScale;
        }
        
        // Start long press timer
        if (this.options.enableLongPress) {
            this.startLongPressTimer();
        }
        
        this.triggerEvent('gestureStart', {
            startX: this.startX,
            startY: this.startY,
            touches: this.touches.length
        });
    }

    handleTouchMove(e) {
        if (this.touches.length === 0) return;
        
        // Cancel long press on move
        this.cancelLongPress();
        
        const touch = e.touches[0];
        this.endX = touch.clientX;
        this.endY = touch.clientY;
        
        this.distX = this.endX - this.startX;
        this.distY = this.endY - this.startY;
        
        // Handle pinch/zoom
        if (e.touches.length === 2 && this.options.enablePinch) {
            e.preventDefault(); // Prevent default zoom
            this.handlePinchMove(e);
        }
        
        // Handle swipe preview
        if (this.options.enableSwipe && Math.abs(this.distX) > 10 || Math.abs(this.distY) > 10) {
            this.direction = this.getDirection();
            this.triggerEvent('swipeMove', {
                direction: this.direction,
                distX: this.distX,
                distY: this.distY,
                deltaX: this.distX,
                deltaY: this.distY
            });
        }
    }

    handleTouchEnd(e) {
        this.cancelLongPress();
        
        if (this.touches.length === 0) return;
        
        this.endTime = Date.now();
        this.elapsedTime = this.endTime - this.startTime;
        
        // Handle different gesture types
        if (e.changedTouches.length === 1) {
            this.handleSingleTouch();
        }
        
        this.triggerEvent('gestureEnd', {
            elapsedTime: this.elapsedTime,
            distX: this.distX,
            distY: this.distY
        });
        
        this.reset();
    }

    handleTouchCancel(e) {
        this.cancelLongPress();
        this.reset();
    }

    handleSingleTouch() {
        // Determine gesture type
        if (this.isSwipe()) {
            this.handleSwipe();
        } else if (this.isTap()) {
            this.handleTap();
        }
    }

    handleSwipe() {
        if (!this.options.enableSwipe) return;
        
        this.direction = this.getDirection();
        
        const swipeData = {
            direction: this.direction,
            distance: this.getDistance({ clientX: this.startX, clientY: this.startY }, { clientX: this.endX, clientY: this.endY }),
            velocity: this.getVelocity(),
            distX: this.distX,
            distY: this.distY,
            elapsedTime: this.elapsedTime
        };
        
        this.triggerEvent('swipe', swipeData);
        this.triggerEvent(`swipe${this.direction}`, swipeData);
    }

    handleTap() {
        if (!this.options.enableTap) return;
        
        const tapData = {
            x: this.endX,
            y: this.endY,
            elapsedTime: this.elapsedTime
        };
        
        this.triggerEvent('tap', tapData);
    }

    handlePinchMove(e) {
        this.currentDistance = this.getDistance(e.touches[0], e.touches[1]);
        const scale = this.currentDistance / this.initialDistance;
        this.currentScale = this.initialScale * scale;
        
        const pinchData = {
            scale: this.currentScale,
            delta: scale,
            distance: this.currentDistance,
            center: this.getCenter(e.touches[0], e.touches[1])
        };
        
        this.triggerEvent('pinch', pinchData);
        
        if (scale > 1.1) {
            this.triggerEvent('pinchOut', pinchData);
        } else if (scale < 0.9) {
            this.triggerEvent('pinchIn', pinchData);
        }
    }

    startLongPressTimer() {
        this.longPressTimer = setTimeout(() => {
            this.isLongPress = true;
            this.triggerEvent('longPress', {
                x: this.startX,
                y: this.startY
            });
        }, this.options.longPressDelay);
    }

    cancelLongPress() {
        if (this.longPressTimer) {
            clearTimeout(this.longPressTimer);
            this.longPressTimer = null;
        }
        this.isLongPress = false;
    }

    // Mouse event handlers for desktop testing
    handleMouseDown(e) {
        this.handleTouchStart({
            touches: [{ clientX: e.clientX, clientY: e.clientY }]
        });
    }

    handleMouseMove(e) {
        if (this.startTime === 0) return;
        
        this.handleTouchMove({
            touches: [{ clientX: e.clientX, clientY: e.clientY }]
        });
    }

    handleMouseUp(e) {
        this.handleTouchEnd({
            changedTouches: [{ clientX: e.clientX, clientY: e.clientY }]
        });
    }

    handleMouseLeave(e) {
        this.handleTouchCancel(e);
    }

    // Utility methods
    isSwipe() {
        return this.elapsedTime <= this.options.allowedTime &&
               this.getSwipeDistance() >= this.options.threshold &&
               this.getRestraint() <= this.options.restraint;
    }

    isTap() {
        return this.elapsedTime <= this.options.allowedTime &&
               this.getSwipeDistance() < this.options.threshold;
    }

    getDirection() {
        if (Math.abs(this.distX) >= Math.abs(this.distY)) {
            return this.distX > 0 ? 'Right' : 'Left';
        } else {
            return this.distY > 0 ? 'Down' : 'Up';
        }
    }

    getSwipeDistance() {
        return Math.sqrt(this.distX * this.distX + this.distY * this.distY);
    }

    getRestraint() {
        if (Math.abs(this.distX) >= Math.abs(this.distY)) {
            return Math.abs(this.distY);
        } else {
            return Math.abs(this.distX);
        }
    }

    getVelocity() {
        return this.getSwipeDistance() / this.elapsedTime;
    }

    getDistance(touch1, touch2) {
        const dx = touch2.clientX - touch1.clientX;
        const dy = touch2.clientY - touch1.clientY;
        return Math.sqrt(dx * dx + dy * dy);
    }

    getCenter(touch1, touch2) {
        return {
            x: (touch1.clientX + touch2.clientX) / 2,
            y: (touch1.clientY + touch2.clientY) / 2
        };
    }

    triggerEvent(eventName, data) {
        const event = new CustomEvent(eventName, {
            detail: data,
            bubbles: true
        });
        this.element.dispatchEvent(event);
    }

    // Public API
    destroy() {
        this.cancelLongPress();
        // Remove event listeners
        this.element.removeEventListener('touchstart', this.handleTouchStart);
        this.element.removeEventListener('touchmove', this.handleTouchMove);
        this.element.removeEventListener('touchend', this.handleTouchEnd);
        this.element.removeEventListener('touchcancel', this.handleTouchCancel);
        this.element.removeEventListener('mousedown', this.handleMouseDown);
        this.element.removeEventListener('mousemove', this.handleMouseMove);
        this.element.removeEventListener('mouseup', this.handleMouseUp);
        this.element.removeEventListener('mouseleave', this.handleMouseLeave);
    }

    enable() {
        this.options.enabled = true;
    }

    disable() {
        this.options.enabled = false;
        this.cancelLongPress();
        this.reset();
    }

    updateOptions(newOptions) {
        this.options = { ...this.options, ...newOptions };
    }
}

// Helper function to easily add gestures to elements
function addGestures(selector, options = {}) {
    const elements = typeof selector === 'string' ? 
        document.querySelectorAll(selector) : 
        [selector];
    
    const handlers = [];
    
    elements.forEach(element => {
        if (element) {
            const handler = new TouchGestureHandler(element, options);
            handlers.push(handler);
        }
    });
    
    return handlers.length === 1 ? handlers[0] : handlers;
}

// Export for module usage
export { TouchGestureHandler, addGestures };