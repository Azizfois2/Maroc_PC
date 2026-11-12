/* ============================================
   PERFORMANCE OPTIMIZER
   Lazy loading, preloading, and optimization
   ============================================ */

(function() {
  'use strict';

  // ── PRELOAD CRITICAL RESOURCES ────────────────────────────────
  function preloadCriticalResources() {
    const criticalImages = [
      'logo.png',
      'laptop.webp'
    ];

    criticalImages.forEach(src => {
      const link = document.createElement('link');
      link.rel = 'preload';
      link.as = 'image';
      link.href = src;
      document.head.appendChild(link);
    });
  }

  // ── DEFER NON-CRITICAL CSS ────────────────────────────────
  function deferNonCriticalCSS() {
    const stylesheets = document.querySelectorAll('link[rel="stylesheet"]');
    stylesheets.forEach(link => {
      if (!link.href.includes('index.css') && !link.href.includes('cinematic')) {
        link.media = 'print';
        link.onload = function() {
          this.media = 'all';
        };
      }
    });
  }

  // ── LAZY LOAD IMAGES WITH BLUR-UP ────────────────────────────────
  function initBlurUpImages() {
    const images = document.querySelectorAll('img[data-src]');
    
    const imageObserver = new IntersectionObserver((entries, observer) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const img = entry.target;
          
          // Create a tiny placeholder if not exists
          if (!img.src || img.src.includes('placeholder')) {
            img.style.filter = 'blur(20px)';
            img.style.transform = 'scale(1.1)';
          }
          
          // Load full image
          const fullImg = new Image();
          fullImg.src = img.dataset.src;
          fullImg.onload = () => {
            img.src = img.dataset.src;
            img.style.filter = 'blur(0)';
            img.style.transform = 'scale(1)';
            img.style.transition = 'filter 0.5s, transform 0.5s';
            img.classList.add('loaded');
          };
          
          observer.unobserve(img);
        }
      });
    }, {
      rootMargin: '50px'
    });

    images.forEach(img => imageObserver.observe(img));
  }

  // ── PREFETCH NEXT PAGE ────────────────────────────────
  function prefetchNextPage() {
    const links = document.querySelectorAll('a[href]');
    const prefetchedUrls = new Set();

    const linkObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const link = entry.target;
          const href = link.href;
          
          if (href && 
              href.startsWith(window.location.origin) && 
              !prefetchedUrls.has(href) &&
              !href.includes('#')) {
            
            const prefetchLink = document.createElement('link');
            prefetchLink.rel = 'prefetch';
            prefetchLink.href = href;
            document.head.appendChild(prefetchLink);
            
            prefetchedUrls.add(href);
          }
        }
      });
    }, {
      rootMargin: '200px'
    });

    links.forEach(link => linkObserver.observe(link));
  }

  // ── OPTIMIZE ANIMATIONS ────────────────────────────────
  function optimizeAnimations() {
    // Reduce animations on low-end devices
    if (navigator.hardwareConcurrency && navigator.hardwareConcurrency < 4) {
      document.documentElement.style.setProperty('--animation-duration', '0.2s');
    }

    // Remove body animation pausing logic, as it breaks ticker and child animations
    // when switching tabs.
  }

  // ── DEBOUNCE SCROLL EVENTS ────────────────────────────────
  function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  }

  // ── THROTTLE RESIZE EVENTS ────────────────────────────────
  function throttle(func, limit) {
    let inThrottle;
    return function(...args) {
      if (!inThrottle) {
        func.apply(this, args);
        inThrottle = true;
        setTimeout(() => inThrottle = false, limit);
      }
    };
  }

  // ── OPTIMIZE SCROLL PERFORMANCE ────────────────────────────────
  function optimizeScrollPerformance() {
    let ticking = false;
    
    window.addEventListener('scroll', () => {
      if (!ticking) {
        window.requestAnimationFrame(() => {
          // Scroll-dependent operations here
          ticking = false;
        });
        ticking = true;
      }
    });
  }

  // ── INITIALIZE ────────────────────────────────
  function init() {
    preloadCriticalResources();
    // deferNonCriticalCSS(); // Commented out to avoid breaking styles
    initBlurUpImages();
    prefetchNextPage();
    optimizeAnimations();
    optimizeScrollPerformance();

    console.log('⚡ Performance optimizations loaded!');
  }

  // Run on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  // Export utilities
  window.performanceUtils = {
    debounce,
    throttle
  };

})();
