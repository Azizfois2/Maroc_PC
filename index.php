<?php
require_once 'includes/store-head.php';
require_once 'includes/store-header.php';
require_once 'includes/store-sidebar.php';
require_once 'includes/store-footer.php';
require_once 'includes/store-end.php';

storeHead(
    i18n_t('home.title'),
    [
        'assets/css/flash-sales.css?v=countdown-fallback-1',
        'assets/css/builder.css',
        'assets/css/products.css?v=mobile-dock-1',
        'assets/css/cinematic-enhancements.css?v=tilt-soft-1',
    ],
    ['assets/js/wishlist.js'],
    i18n_t('home.description'),
    'https://marocpc.com/gpu.webp'
);
?>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const bindThemedMedia = (elementId, darkSrc, lightSrc, useLoad = false) => {
                const element = document.getElementById(elementId);
                if (!element) return;

                const updateSource = () => {
                    const theme = document.documentElement.getAttribute('data-theme') || 'dark';
                    const nextSrc = theme === 'light' ? lightSrc : darkSrc;
                    if (element.getAttribute('src') === nextSrc) return;
                    element.setAttribute('src', nextSrc);
                    if (useLoad && typeof element.load === 'function') {
                        element.load();
                        element.play?.().catch(() => {});
                    }
                };

                updateSource();
                new MutationObserver(updateSource).observe(document.documentElement, {
                    attributes: true,
                    attributeFilter: ['data-theme'],
                });
            };

            bindThemedMedia('heroVideo', 'gpu.mp4', 'gpu-light.mp4', true);
            bindThemedMedia('laptopImage', 'laptop.webp', 'laptop-light.webp');
        });
    </script>

    <!-- Structured Data for SEO -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Store",
      "name": "Maroc PC",
      "description": "Premium computer hardware store in Morocco",
      "url": "https://marocpc.com",
      "logo": "https://marocpc.com/logo.png",
      "image": "https://marocpc.com/gpu.webp",
      "telephone": "+212618821949",
      "address": {
        "@type": "PostalAddress",
        "streetAddress": "123 Boulevard Zerktouni",
        "addressLocality": "Maarif",
        "addressCountry": "MA"
      }
    }
    </script>
</head>
<?php storeHeader('home', true); ?>

<?php require __DIR__ . '/index-content.php'; ?>

<?php storeFooter(); ?>

<?php storeSidebar('home'); ?>

<?php require __DIR__ . '/index-post.php'; ?>

<?php
storeEnd([
    'assets/js/store-sidebar.js?v=shared-sidebar-1',
    'assets/js/data.js',
    'assets/js/products.js?v=products-i18n-1',
    'assets/js/performance-optimizer.js',
    'assets/js/advanced-search.js',
    'assets/js/immersive-scroll.js',
    'assets/js/cinematic-enhancements.js?v=tilt-soft-1',
]);
