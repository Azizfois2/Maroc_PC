<?php
$homepageGamerReviews = [];
$homepageReviewsLoadedFromDb = false;
$homepageCategoryCounts = [];

if (!function_exists('homepageReviewInitials')) {
    function homepageReviewInitials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $initials = '';
        foreach ($parts as $part) {
            if ($part !== '') {
                $initials .= strtoupper(substr($part, 0, 1));
            }
            if (strlen($initials) >= 2) {
                break;
            }
        }
        return $initials !== '' ? $initials : 'MP';
    }
}

if (!function_exists('homepageReviewText')) {
    function homepageReviewText(array $review, string $field): string
    {
        $base = trim((string) ($review[$field] ?? ''));
        $locale = function_exists('i18n_current_locale') ? i18n_current_locale() : 'en';
        $defaultLocale = defined('I18N_DEFAULT_LOCALE') ? I18N_DEFAULT_LOCALE : 'en';
        if ($locale !== $defaultLocale) {
            $localized = trim((string) ($review[$field . '_' . $locale] ?? ''));
            if ($localized !== '') {
                return $localized;
            }
        }

        return $base;
    }
}

try {
    $homepagePdo = db();
    if (function_exists('adminEnsureHomepageGamerReviewsTable')) {
        adminEnsureHomepageGamerReviewsTable($homepagePdo);
    }
    $homepageGamerReviews = adminFetchAll($homepagePdo, "
        SELECT *
        FROM homepage_gamer_reviews
        WHERE is_active = 1
        ORDER BY sort_order ASC, created_at DESC
        LIMIT 6
    ");
    $homepageReviewsLoadedFromDb = true;

    try {
        $countRows = $homepagePdo->query('SELECT category, COUNT(*) AS total FROM products GROUP BY category')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($countRows as $row) {
            $homepageCategoryCounts[(string) $row['category']] = (int) $row['total'];
        }
    } catch (Throwable $countError) {
        $homepageCategoryCounts = [];
    }
} catch (Throwable $e) {
    $homepageGamerReviews = [];
}

if (empty($homepageGamerReviews) && !$homepageReviewsLoadedFromDb) {
    $homepageGamerReviews = [
        [
            'reviewer_name' => 'Abdelkrim El Cherifi',
            'reviewer_name_ar' => 'عبد الكريم الشريفي',
            'reviewer_role' => i18n_t('home.verified_customer'),
            'quote' => 'The AI PC Builder is a game changer. It perfectly balanced my Ryzen 7 and RTX 4070 build without bottlenecking. Delivered to Marrakech in 2 days!',
            'quote_ar' => 'مُجَمِّع الحواسب الذكي غيّر قواعد اللعبة. لقد وازن جهازي بامتياز (Ryzen 7 مع RTX 4070). التوصيل إلى مراكش في يومين!',
            'quote_fr' => 'Le configurateur PC IA change la donne. Il a parfaitement équilibré ma configuration Ryzen 7 et RTX 4070 sans goulot d\'étranglement. Livré à Marrakech en 2 jours !',
            'quote_es' => '¡El configurador de PC con IA cambia las reglas del juego! Equilibró perfectamente mi Ryzen 7 y RTX 4070 sin cuellos de botella. ¡Entregado en Marrakech en 2 días!',
            'avatar_initials' => 'AE',
            'rating' => 5,
        ],
        [
            'reviewer_name' => 'Sami L.',
            'reviewer_name_ar' => 'سامي ل.',
            'reviewer_role' => i18n_t('home.content_creator'),
            'quote' => "Amazing setup! Prices are great, the packaging is perfect. Customer service replied instantly when I asked about the power supply. Top!",
            'quote_ar' => "سيت آب ناضي بزاف! الأثمنة هي هاديك والتغليف مقاد. خدمة العملاء جاوبوني فالبلاصة ملي سولتهم على مزود الطاقة. طوب!",
            'quote_fr' => "Setup magnifique ! Les prix sont parfaits et l\'emballage est impeccable. Le service client m\'a répondu immédiatement quand j\'ai demandé pour l\'alimentation. Top !",
            'quote_es' => '¡Setup increíble! Precios justos y embalaje perfecto. El servicio al cliente respondió de inmediato cuando pregunté por la fuente de alimentación. ¡Excelente!',
            'avatar_initials' => 'SL',
            'rating' => 5,
        ],
        [
            'reviewer_name' => 'Yassine B.',
            'reviewer_name_ar' => 'ياسين ب',
            'reviewer_role' => i18n_t('home.artist_3d'),
            'quote' => "Very clean assembly and impeccable cable management. I ordered a workstation for 3D rendering and the temperatures are excellent.",
            'quote_ar' => 'تركيب نظيف جدا وإدارة كابلات مثالية. طلبت محطة عمل للتصيير ثلاثي الأبعاد ودرجات الحرارة ممتازة.',
            'quote_fr' => 'Montage très propre et gestion des câbles impeccable. J\'ai commandé une station de travail pour le rendu 3D et les températures sont excellentes.',
            'quote_es' => 'Montaje muy limpio y gestión de cables impecable. Compré una estación de trabajo para renderizado 3D y las temperaturas son excelentes.',
            'avatar_initials' => 'YB',
            'rating' => 4.5,
        ],
        [
            'reviewer_name' => 'Lina R.',
            'reviewer_name_ar' => 'لينا ر',
            'reviewer_role' => i18n_t('home.tech_enthusiast'),
            'quote' => 'Finally, a Moroccan hardware store that updates stock in real-time. Got my hands on the new OLED monitor before it sold out everywhere else.',
            'quote_ar' => 'أخيراً، متجر أجهزة مغربي يُحدّث المخزون في الوقت الفعلي. حصلت على شاشة OLED الجديدة قبل نفادها في كل مكان.',
            'quote_fr' => 'Enfin, un magasin de matériel marocain qui met à jour le stock en temps réel. J\'ai mis la main sur le nouvel écran OLED avant qu\'il ne soit épuisé partout ailleurs.',
            'quote_es' => 'Por fin, una tienda de hardware marroquí que actualiza el stock en tiempo real. Conseguí el nuevo monitor OLED antes de que se agotara en todas partes.',
            'avatar_initials' => 'LR',
            'rating' => 5,
        ],
        [
            'reviewer_name' => 'Ali Z.',
            'reviewer_name_ar' => 'علي ز',
            'reviewer_role' => i18n_t('home.software_engineer'),
            'quote' => 'Fast delivery and all components are original with warranty. The full setup arrived in Tangier in less than 48 hours. Thank you Maroc PC!',
            'quote_ar' => 'توصيل سريع وجميع المكونات أصلية بالضمان. الست آب كامل وصلني لطنجة في أقل من 48 ساعة. شكراً ماروك بي سي!',
            'quote_fr' => 'Livraison rapide et tous les composants sont d\'origine avec garantie. Le setup complet est arrivé à Tanger en moins de 48h. Merci Maroc PC !',
            'quote_es' => 'Entrega rápida y todos los componentes originales con garantía. ¡El setup completo llegó a Tánger en menos de 48h! ¡Gracias Maroc PC!',
            'avatar_initials' => 'AZ',
            'rating' => 5,
        ],
        [
            'reviewer_name' => 'Sofia T.',
            'reviewer_name_ar' => 'صوفيا ط',
            'reviewer_role' => i18n_t('home.esports_pro'),
            'quote' => 'Best gaming store in Morocco, hands down. The after-sales service is incredible, they helped me troubleshoot a RAM issue via WhatsApp instantly.',
            'quote_ar' => 'أفضل متجر ألعاب في المغرب بدون منازع. خدمة ما بعد البيع مذهلة، ساعدوني في حل مشكلة في الذاكرة عبر الواتساب فوراً.',
            'quote_fr' => 'Meilleur magasin gaming au Maroc, sans aucun doute. Le service après-vente est incroyable, ils m\'ont aidé à résoudre un problème de RAM via WhatsApp instantanément.',
            'quote_es' => 'La mejor tienda de gaming de Marruecos, sin duda. El servicio postventa es increíble, me ayudaron a solucionar un problema de RAM por WhatsApp al instante.',
            'avatar_initials' => 'ST',
            'rating' => 5,
        ],
    ];
}

$homepageGamerAverage = count($homepageGamerReviews)
    ? array_sum(array_map(static fn($review) => (float) ($review['rating'] ?? 5), $homepageGamerReviews)) / count($homepageGamerReviews)
    : 4.8;

$homepageCategoryCount = static function (array $keys, string $fallback) use ($homepageCategoryCounts): string {
    $total = 0;
    foreach ($keys as $key) {
        $total += (int) ($homepageCategoryCounts[$key] ?? 0);
    }
    return $total > 0 ? (string) $total : $fallback;
};
?>

<main>
    <div class="scroll-progress" id="scrollProgressBar" aria-hidden="true"></div>

    <!-- HERO -->
    <section class="hero hero-immersive animate-on-scroll" style="position: relative; overflow: hidden;">
        <video id="heroVideo" src="gpu.mp4" autoplay loop muted playsinline
            style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; z-index: 0;"></video>
        <div class="hero-particles" aria-hidden="true"></div>
        <div class="hero-bg" style="position: relative; z-index: 1;">
            <div class="hero-layout">
                <div class="hero-copy" data-scroll="reveal">
                    <span class="hero-kicker"><?php i18n_e('home.hero_kicker', [], 'Maroc PC // Performance Architecture'); ?></span>
                    <h1><span class="effet-nadi"><?php i18n_e('home.hero_title'); ?></span></h1>
                    <p id="Lktba"><?php i18n_e('home.hero_copy'); ?> <br>
                        <?php i18n_e('home.hero_copy_2'); ?></p>
                    <nav class="hero-buttons hero-command-bar" aria-label="<?php echo htmlspecialchars(i18n_t('home.hero_actions_label', [], 'Homepage hero actions'), ENT_QUOTES, 'UTF-8'); ?>">
                        <a href="<?= htmlspecialchars(i18n_url('products.php'), ENT_QUOTES, 'UTF-8') ?>"
                            class="btn btn-primary"><?php i18n_e('home.shop_now'); ?></a>
                        <a href="#categories" class="btn btn-secondary"><?php i18n_e('home.browse_categories'); ?></a>
                        <a href="<?= htmlspecialchars(i18n_url('builder.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-secondary hero-ghost-link"><?php i18n_e('home.ai_builder_cta', [], 'AI Builder'); ?></a>
                    </nav>
                </div>

                <aside class="hero-mission-panel" data-scroll="reveal" data-scroll-delay="180">
                    <div class="hero-panel-shell">
                        <span class="hero-panel-label"><?php i18n_e('home.hero_panel_label', [], 'Interactive Ecosystem'); ?></span>
                        <h2><?php i18n_e('home.hero_panel_title', [], 'Scroll to reveal each performance layer'); ?></h2>
                        <p><?php i18n_e('home.hero_panel_desc', [], 'Move from flagship hardware to guided navigation, curated builds, and compatible ecosystems without losing context.'); ?></p>
                        <ul class="hero-mission-list" data-scroll="stagger" data-stagger-child=":scope > li" data-stagger-delay="110">
                            <li>
                                <span class="hero-mission-index">01</span>
                                <div>
                                    <strong><?php i18n_e('home.hero_mission_1_title', [], 'Reveal sequence'); ?></strong>
                                    <span><?php i18n_e('home.hero_mission_1_desc', [], 'Story-driven component journey with active chapter states.'); ?></span>
                                </div>
                            </li>
                            <li>
                                <span class="hero-mission-index">02</span>
                                <div>
                                    <strong><?php i18n_e('home.hero_mission_2_title', [], 'Ecosystem map'); ?></strong>
                                    <span><?php i18n_e('home.hero_mission_2_desc', [], 'Tap a component and expose the compatibility path instantly.'); ?></span>
                                </div>
                            </li>
                            <li>
                                <span class="hero-mission-index">03</span>
                                <div>
                                    <strong><?php i18n_e('home.hero_mission_3_title', [], 'Advanced navigation'); ?></strong>
                                    <span><?php i18n_e('home.hero_mission_3_desc', [], 'Jump by mission, category, or product intent from one control rail.'); ?></span>
                                </div>
                            </li>
                        </ul>
                    </div>
                </aside>
            </div>

            <!-- HERO STATS — fixed semantic structure with icons -->
            <div class="hero-stats hero-stats-immersive" data-scroll="stagger" data-stagger-child=":scope > .hero-stat-card" data-stagger-delay="120">
                <div class="hero-stat-card">
                    <i class="fas fa-shield-alt hero-stat-icon"></i>
                    <strong class="hero-stat-title"><?php i18n_e('home.authenticity_title'); ?></strong>
                    <p class="hero-stat-desc"><?php i18n_e('home.authenticity_desc'); ?></p>
                </div>
                <div class="hero-stat-card">
                    <i class="fas fa-truck-fast hero-stat-icon"></i>
                    <strong class="hero-stat-title"><?php i18n_e('home.shipping_title'); ?></strong>
                    <p class="hero-stat-desc"><?php i18n_e('home.shipping_desc'); ?></p>
                </div>
                <div class="hero-stat-card">
                    <i class="fas fa-tags hero-stat-icon"></i>
                    <strong class="hero-stat-title"><?php i18n_e('home.price_match_title'); ?></strong>
                    <p class="hero-stat-desc"><?php i18n_e('home.price_match_desc'); ?></p>
                </div>
            </div>

            <div class="hero-scroll-cue" aria-hidden="true">
                <span class="hero-scroll-line"></span>
                <span class="hero-scroll-text"><?php i18n_e('home.hero_scroll_cue', [], 'Scroll to enter the build story'); ?></span>
            </div>
        </div>
    </section>

    <!-- TICKER -->
    <div class="ticker-wrapper" aria-hidden="true">
        <div class="ticker-fade ticker-fade-left"></div>
        <div class="ticker-fade ticker-fade-right"></div>
        <div class="ticker-track">
            <div class="ticker-item"><?php i18n_e('home.ticker'); ?></div>
            <div class="ticker-item" aria-hidden="true"><?php i18n_e('home.ticker'); ?></div>
        </div>
    </div>

    <!-- SLEEK LAPTOP FINDER PROMO & OUTCOME PORTFOLIO -->
    <section class="laptop-promo animate-on-scroll">
        <div class="container">
            <header class="laptop-promo-header">
                <span class="featured-tag laptop-promo-tag"><?php i18n_e('home.new_launch'); ?></span>
                <h2><?php i18n_e('home.laptop_title'); ?></h2>
                <p><?php i18n_e('home.laptop_intro'); ?></p>
            </header>

            <figure class="laptop-promo-visual" aria-label="Curated laptop finder preview">
                <img id="laptopImage" src="laptop.webp" alt="Premium laptop on an engineering workbench" loading="lazy">
                <span class="laptop-promo-badge"><?php i18n_e('home.curated_hardware_profiles'); ?></span>
            </figure>

            <div class="laptop-promo-grid">
                <!-- 1. The Road Warrior -->
                <article class="laptop-promo-card laptop-promo-card-cyan">
                    <div class="laptop-promo-card-top">
                        <span class="laptop-promo-chip"><?php i18n_e('home.portable_ultra'); ?></span>
                        <i class="fas fa-plane"></i>
                    </div>
                    <h3><?php i18n_e('home.road_warrior'); ?></h3>
                    <p><?php i18n_e('home.road_warrior_desc'); ?></p>
                    <a href="<?= htmlspecialchars(i18n_url('laptop-finder.php?use=business&portability=ultralight'), ENT_QUOTES, 'UTF-8') ?>"
                        class="btn btn-outline"><?php i18n_e('home.explore_curation'); ?></a>
                </article>

                <!-- 2. The Desktop Replacement -->
                <article class="laptop-promo-card laptop-promo-card-orange">
                    <div class="laptop-promo-card-top">
                        <span class="laptop-promo-chip"><?php i18n_e('home.extreme_performance'); ?></span>
                        <i class="fas fa-fire"></i>
                    </div>
                    <h3><?php i18n_e('home.desktop_replacement'); ?></h3>
                    <p><?php i18n_e('home.desktop_replacement_desc'); ?></p>
                    <a href="<?= htmlspecialchars(i18n_url('laptop-finder.php?use=gaming&portability=desktop_replacement'), ENT_QUOTES, 'UTF-8') ?>"
                        class="btn btn-outline"><?php i18n_e('home.explore_curation'); ?></a>
                </article>

                <!-- 3. The Value Student -->
                <article class="laptop-promo-card laptop-promo-card-green">
                    <div class="laptop-promo-card-top">
                        <span class="laptop-promo-chip"><?php i18n_e('home.high_value'); ?></span>
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <h3><?php i18n_e('home.value_student'); ?></h3>
                    <p><?php i18n_e('home.value_student_desc'); ?></p>
                    <a href="<?= htmlspecialchars(i18n_url('laptop-finder.php?use=student&portability=any'), ENT_QUOTES, 'UTF-8') ?>"
                        class="btn btn-outline"><?php i18n_e('home.explore_curation'); ?></a>
                </article>
            </div>

            <div class="laptop-promo-actions">
                <a href="<?= htmlspecialchars(i18n_url('laptop-finder.php'), ENT_QUOTES, 'UTF-8') ?>"
                    class="btn btn-primary"><i class="fas fa-laptop"></i> <?php i18n_e('home.open_finder'); ?></a>
            </div>
        </div>
    </section>

    <!-- FEATURED PRODUCT -->
    <section class="featured animate-on-scroll" id="featured">
        <div class="featured-inner">
            <div>
                <div class="featured-tag">⚡ <?php i18n_e('home.deal_month'); ?></div>
                <h2>Intel Core i9<br>14900K</h2>
                <p><?php i18n_e('home.featured_desc'); ?></p>

                <div class="featured-specs">
                    <div class="spec-item">
                        <div class="spec-key">Cores / Threads</div>
                        <div class="spec-val">24C / 32T</div>
                    </div>
                    <div class="spec-item">
                        <div class="spec-key">Max Boost</div>
                        <div class="spec-val">6.2 GHz</div>
                    </div>
                    <div class="spec-item">
                        <div class="spec-key">TDP</div>
                        <div class="spec-val">125W (253W PL2)</div>
                    </div>
                    <div class="spec-item">
                        <div class="spec-key">Cache</div>
                        <div class="spec-val">36MB L3</div>
                    </div>
                    <div class="spec-item">
                        <div class="spec-key">Socket</div>
                        <div class="spec-val">LGA1700</div>
                    </div>
                    <div class="spec-item">
                        <div class="spec-key">Memory Support</div>
                        <div class="spec-val">DDR5-5600</div>
                    </div>
                </div>

                <div class="featured-ctas">
                    <div id="featuredHeroCta">
                        <script>
                            document.addEventListener('DOMContentLoaded', () => {
                                const heroProduct = products.find(p => p.id === 4);
                                const ctaContainer = document.getElementById('featuredHeroCta');
                                const formatMAD = (value) => window.formatMAD ? window.formatMAD(value) : (Number(value).toLocaleString('en-US', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                }) + ' DH');

                                if (heroProduct && heroProduct.inStock) {
                                    ctaContainer.innerHTML = `<button type="button" class="btn btn-primary featured-add-to-cart">${window.__marocPcI18n?.addToCart || 'Add to Cart'} - ${formatMAD(heroProduct.price)}</button>`;
                                    ctaContainer.querySelector('.featured-add-to-cart').addEventListener('click', () => {
                                        if (window.Cart && typeof Cart.add === 'function') {
                                            Cart.add(heroProduct);
                                        }
                                    });
                                } else {
                                    ctaContainer.innerHTML = `<button type="button" class="btn-secondary notify-restock-btn" style="padding: 12px 24px; border-radius: 8px; font-weight: bold;" data-id="4" data-name="Intel Core i9-14900K"><i class="fas fa-bell"></i> ${window.__marocPcI18n?.notifyAvailable || 'Notify Me When Available'}</button>`;
                                }
                            });
                        </script>
                    </div>
                </div>
            </div>

            <div class="featured-visual">
                <div class="featured-glow"></div>
                <div class="featured-img-box">
                    <!-- Dark Mode CPU SVG -->
                    <svg class="cpu-svg cpu-svg-dark" viewBox="0 0 200 200" fill="none"
                        xmlns="http://www.w3.org/2000/svg" style="width:80%">
                        <!-- Transparent background for seamless integration -->
                        <rect width="100%" height="100%" fill="transparent" />

                        <!-- Main CPU body layers - matching light mode positions -->
                        <rect class="cpu-body" x="30" y="30" width="140" height="140" rx="9" stroke-width="1"
                            fill="none" />
                        <rect class="cpu-layer-1" x="45" y="45" width="110" height="110" rx="5" stroke-width="0.7"
                            fill="none" />
                        <rect class="cpu-layer-2" x="58" y="58" width="84" height="84" rx="3" stroke-width="0.8"
                            fill="none" />

                        <!-- Text branding - matching light mode positions -->
                        <text class="cpu-text-primary" x="100" y="97" text-anchor="middle"
                            font-family="system-ui, -apple-system, sans-serif" font-size="8.5" font-weight="700"
                            letter-spacing="0.5">i9-14900K</text>
                        <text class="cpu-text-secondary" x="100" y="113" text-anchor="middle"
                            font-family="system-ui, -apple-system, sans-serif" font-size="5.5" font-weight="600"
                            letter-spacing="1.3">INTEL CORE</text>

                        <!-- Gold pins - matching light mode positions -->
                        <g class="cpu-pins" stroke="#b8860b" stroke-width="0.5" fill="#b8860b">
                            <!-- Top pins (17 pins) -->
                            <rect x="44" y="25" width="2" height="8" rx="0.6" />
                            <rect x="51" y="25" width="2" height="8" rx="0.6" />
                            <rect x="58" y="25" width="2" height="8" rx="0.6" />
                            <rect x="65" y="25" width="2" height="8" rx="0.6" />
                            <rect x="72" y="25" width="2" height="8" rx="0.6" />
                            <rect x="79" y="25" width="2" height="8" rx="0.6" />
                            <rect x="86" y="25" width="2" height="8" rx="0.6" />
                            <rect x="93" y="25" width="2" height="8" rx="0.6" />
                            <rect x="100" y="25" width="2" height="8" rx="0.6" />
                            <rect x="107" y="25" width="2" height="8" rx="0.6" />
                            <rect x="114" y="25" width="2" height="8" rx="0.6" />
                            <rect x="121" y="25" width="2" height="8" rx="0.6" />
                            <rect x="128" y="25" width="2" height="8" rx="0.6" />
                            <rect x="135" y="25" width="2" height="8" rx="0.6" />
                            <rect x="142" y="25" width="2" height="8" rx="0.6" />
                            <rect x="149" y="25" width="2" height="8" rx="0.6" />
                            <rect x="156" y="25" width="2" height="8" rx="0.6" />

                            <!-- Bottom pins (17 pins) -->
                            <rect x="44" y="167" width="2" height="8" rx="0.6" />
                            <rect x="51" y="167" width="2" height="8" rx="0.6" />
                            <rect x="58" y="167" width="2" height="8" rx="0.6" />
                            <rect x="65" y="167" width="2" height="8" rx="0.6" />
                            <rect x="72" y="167" width="2" height="8" rx="0.6" />
                            <rect x="79" y="167" width="2" height="8" rx="0.6" />
                            <rect x="86" y="167" width="2" height="8" rx="0.6" />
                            <rect x="93" y="167" width="2" height="8" rx="0.6" />
                            <rect x="100" y="167" width="2" height="8" rx="0.6" />
                            <rect x="107" y="167" width="2" height="8" rx="0.6" />
                            <rect x="114" y="167" width="2" height="8" rx="0.6" />
                            <rect x="121" y="167" width="2" height="8" rx="0.6" />
                            <rect x="128" y="167" width="2" height="8" rx="0.6" />
                            <rect x="135" y="167" width="2" height="8" rx="0.6" />
                            <rect x="142" y="167" width="2" height="8" rx="0.6" />
                            <rect x="149" y="167" width="2" height="8" rx="0.6" />
                            <rect x="156" y="167" width="2" height="8" rx="0.6" />

                            <!-- Left pins (17 pins) -->
                            <rect x="25" y="44" width="8" height="2" rx="0.6" />
                            <rect x="25" y="51" width="8" height="2" rx="0.6" />
                            <rect x="25" y="58" width="8" height="2" rx="0.6" />
                            <rect x="25" y="65" width="8" height="2" rx="0.6" />
                            <rect x="25" y="72" width="8" height="2" rx="0.6" />
                            <rect x="25" y="79" width="8" height="2" rx="0.6" />
                            <rect x="25" y="86" width="8" height="2" rx="0.6" />
                            <rect x="25" y="93" width="8" height="2" rx="0.6" />
                            <rect x="25" y="100" width="8" height="2" rx="0.6" />
                            <rect x="25" y="107" width="8" height="2" rx="0.6" />
                            <rect x="25" y="114" width="8" height="2" rx="0.6" />
                            <rect x="25" y="121" width="8" height="2" rx="0.6" />
                            <rect x="25" y="128" width="8" height="2" rx="0.6" />
                            <rect x="25" y="135" width="8" height="2" rx="0.6" />
                            <rect x="25" y="142" width="8" height="2" rx="0.6" />
                            <rect x="25" y="149" width="8" height="2" rx="0.6" />
                            <rect x="25" y="156" width="8" height="2" rx="0.6" />

                            <!-- Right pins (17 pins) -->
                            <rect x="167" y="44" width="8" height="2" rx="0.6" />
                            <rect x="167" y="51" width="8" height="2" rx="0.6" />
                            <rect x="167" y="58" width="8" height="2" rx="0.6" />
                            <rect x="167" y="65" width="8" height="2" rx="0.6" />
                            <rect x="167" y="72" width="8" height="2" rx="0.6" />
                            <rect x="167" y="79" width="8" height="2" rx="0.6" />
                            <rect x="167" y="86" width="8" height="2" rx="0.6" />
                            <rect x="167" y="93" width="8" height="2" rx="0.6" />
                            <rect x="167" y="100" width="8" height="2" rx="0.6" />
                            <rect x="167" y="107" width="8" height="2" rx="0.6" />
                            <rect x="167" y="114" width="8" height="2" rx="0.6" />
                            <rect x="167" y="121" width="8" height="2" rx="0.6" />
                            <rect x="167" y="128" width="8" height="2" rx="0.6" />
                            <rect x="167" y="135" width="8" height="2" rx="0.6" />
                            <rect x="167" y="142" width="8" height="2" rx="0.6" />
                            <rect x="167" y="149" width="8" height="2" rx="0.6" />
                            <rect x="167" y="156" width="8" height="2" rx="0.6" />
                        </g>
                    </svg>

                    <!-- Light Mode CPU SVG - Realistic with heat glow and shadows -->
                    <svg class="cpu-svg cpu-svg-light" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg"
                        style="width: 80%;">
                        <defs>
                            <!-- Grid pattern -->
                            <pattern id="grid-light" width="13" height="13" patternUnits="userSpaceOnUse">
                                <path d="M 13 0 L 0 0 0 13" fill="none" stroke="#E5E9F0" stroke-width="0.3" />
                            </pattern>

                            <!-- Filters -->
                            <filter id="heat-glow-light" x="-50%" y="-50%" width="200%" height="200%">
                                <feGaussianBlur stdDeviation="5" result="blur" />
                                <feComponentTransfer in="blur" result="boost">
                                    <feFuncA type="linear" slope="1.2" />
                                </feComponentTransfer>
                                <feMerge>
                                    <feMergeNode in="boost" />
                                    <feMergeNode in="SourceGraphic" />
                                </feMerge>
                            </filter>
                            <filter id="pin-shadow-light" x="-50%" y="-50%" width="200%" height="200%">
                                <feDropShadow dx="0.3" dy="0.6" stdDeviation="0.6" flood-color="#000000"
                                    flood-opacity="0.25" />
                            </filter>
                            <filter id="body-shadow-light" x="-20%" y="-20%" width="140%" height="140%">
                                <feDropShadow dx="0" dy="2" stdDeviation="3" flood-color="#000000"
                                    flood-opacity="0.15" />
                            </filter>

                            <!-- Gradients -->
                            <radialGradient id="ambient-glow-light" cx="50%" cy="50%" r="50%">
                                <stop offset="40%" stop-color="#FF6B35" stop-opacity="0.15" />
                                <stop offset="80%" stop-color="#FFAD60" stop-opacity="0.03" />
                                <stop offset="100%" stop-color="#FFFFFF" stop-opacity="0" />
                            </radialGradient>
                            <linearGradient id="substrate-grad-light" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" stop-color="#7B889B" />
                                <stop offset="50%" stop-color="#94A1B5" />
                                <stop offset="100%" stop-color="#5C677D" />
                            </linearGradient>
                            <linearGradient id="ihs-base-light" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" stop-color="#D9DDE3" />
                                <stop offset="30%" stop-color="#F1F3F5" />
                                <stop offset="70%" stop-color="#CFD5DC" />
                                <stop offset="100%" stop-color="#A3ABB7" />
                            </linearGradient>
                            <linearGradient id="ihs-cap-light" x1="100%" y1="100%" x2="0%" y2="0%">
                                <stop offset="0%" stop-color="#C2C9D2" />
                                <stop offset="50%" stop-color="#E2E6EA" />
                                <stop offset="100%" stop-color="#F8FAFC" />
                            </linearGradient>
                            <linearGradient id="gold-pin-v-light" x1="0%" y1="0%" x2="100%" y2="0%">
                                <stop offset="0%" stop-color="#D4AF37" />
                                <stop offset="40%" stop-color="#FFF3A8" />
                                <stop offset="70%" stop-color="#AA7C11" />
                                <stop offset="100%" stop-color="#805B00" />
                            </linearGradient>
                            <linearGradient id="gold-pin-h-light" x1="0%" y1="0%" x2="0%" y2="100%">
                                <stop offset="0%" stop-color="#D4AF37" />
                                <stop offset="40%" stop-color="#FFF3A8" />
                                <stop offset="70%" stop-color="#AA7C11" />
                                <stop offset="100%" stop-color="#805B00" />
                            </linearGradient>
                        </defs>

                        <!-- Transparent background for seamless integration -->
                        <!-- Grid overlay removed for cleaner look -->
                        <rect width="100%" height="100%" fill="transparent" />

                        <!-- Ambient glow -->
                        <circle cx="100" cy="100" r="85" fill="url(#ambient-glow-light)" />

                        <g filter="url(#heat-glow-light)">
                            <!-- Gold pins with shadows -->
                            <g filter="url(#pin-shadow-light)">
                                <!-- Top pins (17 pins) -->
                                <rect x="44" y="25" width="2" height="8" rx="0.6" fill="url(#gold-pin-v-light)" />
                                <rect x="51" y="25" width="2" height="8" rx="0.6" fill="url(#gold-pin-v-light)" />
                                <rect x="58" y="25" width="2" height="8" rx="0.6" fill="url(#gold-pin-v-light)" />
                                <rect x="65" y="25" width="2" height="8" rx="0.6" fill="url(#gold-pin-v-light)" />
                                <rect x="72" y="25" width="2" height="8" rx="0.6" fill="url(#gold-pin-v-light)" />
                                <rect x="79" y="25" width="2" height="8" rx="0.6" fill="url(#gold-pin-v-light)" />
                                <rect x="86" y="25" width="2" height="8" rx="0.6" fill="url(#gold-pin-v-light)" />
                                <rect x="93" y="25" width="2" height="8" rx="0.6" fill="url(#gold-pin-v-light)" />
                                <rect x="100" y="25" width="2" height="8" rx="0.6" fill="url(#gold-pin-v-light)" />
                                <rect x="107" y="25" width="2" height="8" rx="0.6" fill="url(#gold-pin-v-light)" />
                                <rect x="114" y="25" width="2" height="8" rx="0.6" fill="url(#gold-pin-v-light)" />
                                <rect x="121" y="25" width="2" height="8" rx="0.6" fill="url(#gold-pin-v-light)" />
                                <rect x="128" y="25" width="2" height="8" rx="0.6" fill="url(#gold-pin-v-light)" />
                                <rect x="135" y="25" width="2" height="8" rx="0.6" fill="url(#gold-pin-v-light)" />
                                <rect x="142" y="25" width="2" height="8" rx="0.6" fill="url(#gold-pin-v-light)" />
                                <rect x="149" y="25" width="2" height="8" rx="0.6" fill="url(#gold-pin-v-light)" />
                                <rect x="156" y="25" width="2" height="8" rx="0.6" fill="url(#gold-pin-v-light)" />

                                <!-- Bottom pins (17 pins) -->
                                <rect x="44" y="167" width="2" height="8" rx="0.6" fill="url(#gold-pin-v-light)" />
                                <rect x="51" y="167" width="2" height="8" rx="0.6" fill="url(#gold-pin-v-light)" />
                                <rect x="58" y="167" width="2" height="8" rx="0.6" fill="url(#gold-pin-v-light)" />
                                <rect x="65" y="167" width="2" height="8" rx="0.6" fill="url(#gold-pin-v-light)" />
                                <rect x="72" y="167" width="2" height="8" rx="0.6" fill="url(#gold-pin-v-light)" />
                                <rect x="79" y="167" width="2" height="8" rx="0.6" fill="url(#gold-pin-v-light)" />
                                <rect x="86" y="167" width="2" height="8" rx="0.6" fill="url(#gold-pin-v-light)" />
                                <rect x="93" y="167" width="2" height="8" rx="0.6" fill="url(#gold-pin-v-light)" />
                                <rect x="100" y="167" width="2" height="8" rx="0.6" fill="url(#gold-pin-v-light)" />
                                <rect x="107" y="167" width="2" height="8" rx="0.6" fill="url(#gold-pin-v-light)" />
                                <rect x="114" y="167" width="2" height="8" rx="0.6" fill="url(#gold-pin-v-light)" />
                                <rect x="121" y="167" width="2" height="8" rx="0.6" fill="url(#gold-pin-v-light)" />
                                <rect x="128" y="167" width="2" height="8" rx="0.6" fill="url(#gold-pin-v-light)" />
                                <rect x="135" y="167" width="2" height="8" rx="0.6" fill="url(#gold-pin-v-light)" />
                                <rect x="142" y="167" width="2" height="8" rx="0.6" fill="url(#gold-pin-v-light)" />
                                <rect x="149" y="167" width="2" height="8" rx="0.6" fill="url(#gold-pin-v-light)" />
                                <rect x="156" y="167" width="2" height="8" rx="0.6" fill="url(#gold-pin-v-light)" />

                                <!-- Left pins (17 pins) -->
                                <rect x="25" y="44" width="8" height="2" rx="0.6" fill="url(#gold-pin-h-light)" />
                                <rect x="25" y="51" width="8" height="2" rx="0.6" fill="url(#gold-pin-h-light)" />
                                <rect x="25" y="58" width="8" height="2" rx="0.6" fill="url(#gold-pin-h-light)" />
                                <rect x="25" y="65" width="8" height="2" rx="0.6" fill="url(#gold-pin-h-light)" />
                                <rect x="25" y="72" width="8" height="2" rx="0.6" fill="url(#gold-pin-h-light)" />
                                <rect x="25" y="79" width="8" height="2" rx="0.6" fill="url(#gold-pin-h-light)" />
                                <rect x="25" y="86" width="8" height="2" rx="0.6" fill="url(#gold-pin-h-light)" />
                                <rect x="25" y="93" width="8" height="2" rx="0.6" fill="url(#gold-pin-h-light)" />
                                <rect x="25" y="100" width="8" height="2" rx="0.6" fill="url(#gold-pin-h-light)" />
                                <rect x="25" y="107" width="8" height="2" rx="0.6" fill="url(#gold-pin-h-light)" />
                                <rect x="25" y="114" width="8" height="2" rx="0.6" fill="url(#gold-pin-h-light)" />
                                <rect x="25" y="121" width="8" height="2" rx="0.6" fill="url(#gold-pin-h-light)" />
                                <rect x="25" y="128" width="8" height="2" rx="0.6" fill="url(#gold-pin-h-light)" />
                                <rect x="25" y="135" width="8" height="2" rx="0.6" fill="url(#gold-pin-h-light)" />
                                <rect x="25" y="142" width="8" height="2" rx="0.6" fill="url(#gold-pin-h-light)" />
                                <rect x="25" y="149" width="8" height="2" rx="0.6" fill="url(#gold-pin-h-light)" />
                                <rect x="25" y="156" width="8" height="2" rx="0.6" fill="url(#gold-pin-h-light)" />

                                <!-- Right pins (17 pins) -->
                                <rect x="167" y="44" width="8" height="2" rx="0.6" fill="url(#gold-pin-h-light)" />
                                <rect x="167" y="51" width="8" height="2" rx="0.6" fill="url(#gold-pin-h-light)" />
                                <rect x="167" y="58" width="8" height="2" rx="0.6" fill="url(#gold-pin-h-light)" />
                                <rect x="167" y="65" width="8" height="2" rx="0.6" fill="url(#gold-pin-h-light)" />
                                <rect x="167" y="72" width="8" height="2" rx="0.6" fill="url(#gold-pin-h-light)" />
                                <rect x="167" y="79" width="8" height="2" rx="0.6" fill="url(#gold-pin-h-light)" />
                                <rect x="167" y="86" width="8" height="2" rx="0.6" fill="url(#gold-pin-h-light)" />
                                <rect x="167" y="93" width="8" height="2" rx="0.6" fill="url(#gold-pin-h-light)" />
                                <rect x="167" y="100" width="8" height="2" rx="0.6" fill="url(#gold-pin-h-light)" />
                                <rect x="167" y="107" width="8" height="2" rx="0.6" fill="url(#gold-pin-h-light)" />
                                <rect x="167" y="114" width="8" height="2" rx="0.6" fill="url(#gold-pin-h-light)" />
                                <rect x="167" y="121" width="8" height="2" rx="0.6" fill="url(#gold-pin-h-light)" />
                                <rect x="167" y="128" width="8" height="2" rx="0.6" fill="url(#gold-pin-h-light)" />
                                <rect x="167" y="135" width="8" height="2" rx="0.6" fill="url(#gold-pin-h-light)" />
                                <rect x="167" y="142" width="8" height="2" rx="0.6" fill="url(#gold-pin-h-light)" />
                                <rect x="167" y="149" width="8" height="2" rx="0.6" fill="url(#gold-pin-h-light)" />
                                <rect x="167" y="156" width="8" height="2" rx="0.6" fill="url(#gold-pin-h-light)" />
                            </g>

                            <!-- Substrate (PCB green layer) -->
                            <rect x="30" y="30" width="140" height="140" rx="9" fill="url(#substrate-grad-light)"
                                stroke="#4A5262" stroke-width="1" filter="url(#body-shadow-light)" />
                            <rect x="32" y="32" width="136" height="136" rx="8" fill="none" stroke="#A3ABB7"
                                stroke-width="0.5" opacity="0.6" />

                            <!-- IHS Base (silver metal) -->
                            <rect x="45" y="45" width="110" height="110" rx="5" fill="url(#ihs-base-light)"
                                stroke="#6C757D" stroke-width="0.7" />
                            <rect x="46" y="46" width="108" height="108" rx="4.5" fill="none" stroke="#FFFFFF"
                                stroke-width="0.5" opacity="0.7" />
                            <rect x="45" y="45" width="110" height="110" rx="5" fill="none" stroke="#333333"
                                stroke-width="0.3" opacity="0.4" />

                            <!-- IHS Cap (top polished surface) -->
                            <rect x="58" y="58" width="84" height="84" rx="3" fill="url(#ihs-cap-light)"
                                stroke="#8B95A5" stroke-width="0.8" />
                            <rect x="59" y="59" width="82" height="82" rx="2" fill="none" stroke="#495057"
                                stroke-width="0.3" opacity="0.3" />

                            <!-- Text branding -->
                            <text x="100" y="97" text-anchor="middle" font-family="system-ui, -apple-system, sans-serif"
                                font-size="8.5" font-weight="700" fill="#D64527" letter-spacing="0.5">i9-14900K</text>
                            <text x="100" y="113" text-anchor="middle"
                                font-family="system-ui, -apple-system, sans-serif" font-size="5.5" font-weight="600"
                                fill="#5A6578" letter-spacing="1.3">INTEL CORE</text>

                            <!-- Corner highlight -->
                            <polygon points="35,38 40,33 35,33" fill="#E2E8F0" opacity="0.6" />
                        </g>
                    </svg>
                </div>
            </div>
        </div>
    </section>

    <!-- PAYMENT & TRUST SECTION -->
    <section class="payment-trust-section animate-on-scroll" aria-label="Payment methods and trust indicators">
        <div class="payment-trust-container">
            <!-- Payment Methods -->
            <div class="payment-methods-wrapper">
                <h2 class="section-eyebrow"><?php i18n_e('home.payment_options'); ?></h2>
                <div class="payment-grid">
                    <div class="payment-card" title="Visa">
                        <i class="fab fa-cc-visa"></i>
                        <span>Visa</span>
                    </div>
                    <div class="payment-card" title="Mastercard">
                        <i class="fab fa-cc-mastercard"></i>
                        <span>Mastercard</span>
                    </div>
                    <div class="payment-card" title="PayPal">
                        <i class="fab fa-cc-paypal"></i>
                        <span>PayPal</span>
                    </div>
                    <div class="payment-card" title="Bitcoin">
                        <i class="fab fa-bitcoin"></i>
                        <span>Bitcoin</span>
                    </div>
                    <div class="payment-card" title="Apple Pay">
                        <i class="fab fa-apple-pay"></i>
                        <span>Apple Pay</span>
                    </div>
                    <div class="payment-card" title="Google Pay">
                        <i class="fab fa-google-pay"></i>
                        <span>Google Pay</span>
                    </div>
                </div>
                <p class="payment-security-note">
                    <i class="fas fa-lock"></i>
                    <?php i18n_e('home.payment_security_note'); ?>
                </p>
            </div>

            <!-- Trust Indicators -->
            <div class="trust-indicators-wrapper">
                <h2 class="section-eyebrow"><?php i18n_e('home.trust_title_short'); ?></h2>
                <div class="trust-grid">
                    <div class="trust-card trust-card-cyan">
                        <div class="trust-icon-wrapper">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div class="trust-content">
                            <h3><?php i18n_e('home.secure_100'); ?></h3>
                            <p><?php i18n_e('home.secure_100_desc'); ?></p>
                        </div>
                    </div>

                    <div class="trust-card trust-card-orange">
                        <div class="trust-icon-wrapper">
                            <i class="fas fa-award"></i>
                        </div>
                        <div class="trust-content">
                            <h3><?php i18n_e('home.certified_seller'); ?></h3>
                            <p><?php i18n_e('home.certified_seller_desc'); ?></p>
                        </div>
                    </div>

                    <div class="trust-card trust-card-cyan">
                        <div class="trust-icon-wrapper">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="trust-content">
                            <h3><?php i18n_e('home.customers_10000'); ?></h3>
                            <p><?php i18n_e('home.customers_10000_desc'); ?></p>
                        </div>
                    </div>

                    <div class="trust-card trust-card-orange">
                        <div class="trust-icon-wrapper">
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="trust-content">
                            <h3><?php i18n_e('home.rating_48'); ?></h3>
                            <p><?php i18n_e('home.rating_48_desc'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- PRODUCT REVEAL SEQUENCE -->
    <?php include 'components/home-reveal-sequence.php'; ?>

    <!-- INTERACTIVE ECOSYSTEM -->
    <?php include 'components/home-ecosystem.php'; ?>

    <!-- CATEGORIES -->
    <section class="categories categories-immersive animate-on-scroll" id="categories">
        <header class="categories-header-immersive" data-scroll="reveal">
            <span class="category-eyebrow"><?php i18n_e('home.category_eyebrow', [], 'Advanced Navigation'); ?></span>
            <h2 class="text-center"><?php i18n_e('home.shop_by_category'); ?></h2>
            <p class="text-center"><?php i18n_e('home.shop_by_category_desc'); ?></p>
        </header>

        <nav class="category-quick-nav" aria-label="<?php echo htmlspecialchars(i18n_t('home.category_quick_nav_label', [], 'Advanced category navigation'), ENT_QUOTES, 'UTF-8'); ?>" data-scroll="stagger" data-stagger-child=":scope > a" data-stagger-delay="90">
            <a href="<?= htmlspecialchars(i18n_url('builder.php'), ENT_QUOTES, 'UTF-8') ?>"><?php i18n_e('home.category_quick_nav_build', [], 'Build a PC'); ?></a>
            <a href="<?= htmlspecialchars(i18n_url('products.php?category=graphics'), ENT_QUOTES, 'UTF-8') ?>"><?php i18n_e('home.category_quick_nav_gaming', [], 'Gaming Setup'); ?></a>
            <a href="<?= htmlspecialchars(i18n_url('laptop-finder.php'), ENT_QUOTES, 'UTF-8') ?>"><?php i18n_e('home.category_quick_nav_mobile', [], 'Mobile Workflows'); ?></a>
            <a href="#featured"><?php i18n_e('home.category_quick_nav_flagship', [], 'Flagship Deal'); ?></a>
            <a href="#products"><?php i18n_e('home.category_quick_nav_featured', [], 'Featured Drops'); ?></a>
        </nav>

        <nav class="category-grid category-scroll-track" data-scroll="stagger" data-stagger-child=":scope > .category-card" data-stagger-delay="70">
            <a href="products.php?category=processors" class="category-card">
                <i class="fas fa-microchip category-icon"></i>
                <h3><?php i18n_e('nav.processors'); ?></h3>
                <p><?php i18n_e('home.cat_processors_desc'); ?></p>
                <small><?= htmlspecialchars($homepageCategoryCount(['cpu', 'processor', 'processors'], '150+'), ENT_QUOTES, 'UTF-8') ?>
                    <?php i18n_e('home.products_count'); ?></small>
            </a>
            <a href="products.php?category=graphics" class="category-card">
                <i class="fas fa-gamepad category-icon"></i>
                <h3><?php i18n_e('nav.graphics_cards'); ?></h3>
                <p><?php i18n_e('home.cat_graphics_desc'); ?></p>
                <small><?= htmlspecialchars($homepageCategoryCount(['gpu', 'graphics', 'graphics_card'], '80+'), ENT_QUOTES, 'UTF-8') ?>
                    <?php i18n_e('home.products_count'); ?></small>
            </a>
            <a href="products.php?category=memory" class="category-card">
                <i class="fas fa-memory category-icon"></i>
                <h3><?php i18n_e('nav.memory_ram'); ?></h3>
                <p><?php i18n_e('home.cat_memory_desc'); ?></p>
                <small><?= htmlspecialchars($homepageCategoryCount(['ram', 'memory'], '200+'), ENT_QUOTES, 'UTF-8') ?>
                    <?php i18n_e('home.products_count'); ?></small>
            </a>
            <a href="products.php?category=storage" class="category-card">
                <i class="fas fa-hdd category-icon"></i>
                <h3><?php i18n_e('nav.storage'); ?></h3>
                <p><?php i18n_e('home.cat_storage_desc'); ?></p>
                <small><?= htmlspecialchars($homepageCategoryCount(['storage'], '120+'), ENT_QUOTES, 'UTF-8') ?>
                    <?php i18n_e('home.products_count'); ?></small>
            </a>
            <a href="products.php?category=motherboard" class="category-card">
                <i class="fas fa-diagram-project category-icon"></i>
                <h3><?php i18n_e('nav.motherboards'); ?></h3>
                <p><?php i18n_e('home.cat_motherboards_desc'); ?></p>
                <small><?= htmlspecialchars($homepageCategoryCount(['motherboard', 'motherboards'], '6'), ENT_QUOTES, 'UTF-8') ?>
                    <?php i18n_e('home.products_count'); ?></small>
            </a>
            <a href="products.php?category=cooling" class="category-card">
                <i class="fas fa-fan category-icon"></i>
                <h3><?php i18n_e('home.cat_cooling'); ?></h3>
                <p><?php i18n_e('home.cat_cooling_desc'); ?></p>
                <small><?= htmlspecialchars($homepageCategoryCount(['cooling'], '75+'), ENT_QUOTES, 'UTF-8') ?>
                    <?php i18n_e('home.products_count'); ?></small>
            </a>
            <a href="products.php?category=power" class="category-card">
                <i class="fas fa-bolt category-icon"></i>
                <h3><?php i18n_e('nav.power_supplies'); ?></h3>
                <p><?php i18n_e('home.cat_power_desc'); ?></p>
                <small><?= htmlspecialchars($homepageCategoryCount(['psu', 'power'], '60+'), ENT_QUOTES, 'UTF-8') ?>
                    <?php i18n_e('home.products_count'); ?></small>
            </a>
            <a href="products.php?category=cases" class="category-card">
                <i class="fas fa-server category-icon"></i>
                <h3><?php i18n_e('nav.pc_cases'); ?></h3>
                <p><?php i18n_e('home.cat_cases_desc'); ?></p>
                <small><?= htmlspecialchars($homepageCategoryCount(['case', 'cases'], '100+'), ENT_QUOTES, 'UTF-8') ?>
                    <?php i18n_e('home.products_count'); ?></small>
            </a>
            <a href="products.php?category=monitor" class="category-card">
                <i class="fas fa-display category-icon"></i>
                <h3><?php i18n_e('home.cat_monitors'); ?></h3>
                <p><?php i18n_e('home.cat_monitors_desc'); ?></p>
                <small><?= htmlspecialchars($homepageCategoryCount(['monitor', 'monitors'], '30+'), ENT_QUOTES, 'UTF-8') ?>
                    <?php i18n_e('home.products_count'); ?></small>
            </a>
            <a href="products.php?category=accessories" class="category-card">
                <i class="fas fa-toolbox category-icon"></i>
                <h3><?php i18n_e('nav.accessories'); ?></h3>
                <p><?php i18n_e('home.cat_accessories_desc'); ?></p>
                <small><?= htmlspecialchars($homepageCategoryCount(['accessories', 'accessory'], '10'), ENT_QUOTES, 'UTF-8') ?>
                    <?php i18n_e('home.products_count'); ?></small>
            </a>
            <a href="products.php?category=keyboard" class="category-card">
                <i class="fas fa-keyboard category-icon"></i>
                <h3><?php i18n_e('nav.keyboards'); ?></h3>
                <p><?php i18n_e('home.cat_keyboards_desc'); ?></p>
                <small><?= htmlspecialchars($homepageCategoryCount(['keyboard', 'keyboards'], '12'), ENT_QUOTES, 'UTF-8') ?>
                    <?php i18n_e('home.products_count'); ?></small>
            </a>
            <a href="products.php?category=mouse" class="category-card">
                <i class="fas fa-computer-mouse category-icon"></i>
                <h3><?php i18n_e('nav.mice'); ?></h3>
                <p><?php i18n_e('home.cat_mice_desc'); ?></p>
                <small><?= htmlspecialchars($homepageCategoryCount(['mouse', 'mice'], '12'), ENT_QUOTES, 'UTF-8') ?>
                    <?php i18n_e('home.products_count'); ?></small>
            </a>
            <a href="products.php?category=vr" class="category-card">
                <i class="fas fa-vr-cardboard category-icon"></i>
                <h3><?php i18n_e('nav.vr_headsets'); ?></h3>
                <p><?php i18n_e('home.cat_vr_headsets_desc'); ?></p>
                <small><?= htmlspecialchars($homepageCategoryCount(['vr'], '2'), ENT_QUOTES, 'UTF-8') ?>
                    <?php i18n_e('home.products_count'); ?></small>
            </a>
            <a href="products.php?category=router" class="category-card">
                <i class="fas fa-wifi category-icon"></i>
                <h3><?php i18n_e('nav.routers'); ?></h3>
                <p><?php i18n_e('home.cat_routers_desc'); ?></p>
                <small><?= htmlspecialchars($homepageCategoryCount(['router', 'routers'], '2'), ENT_QUOTES, 'UTF-8') ?>
                    <?php i18n_e('home.products_count'); ?></small>
            </a>
        </nav>
    </section>

    <!-- FEATURED PRODUCTS -->
    <section class="featured-products animate-on-scroll" id="products">
        <header>
            <h2 class="text-center"><?php i18n_e('home.featured_products'); ?></h2>
            <p class="text-center"><?php i18n_e('home.featured_products_desc'); ?></p>
        </header>

        <!-- Skeleton loaders shown while JS loads products -->
        <ul class="products-grid" id="skeletonGrid" aria-hidden="true">
            <li class="skeleton-card">
                <div class="skeleton-img"></div>
                <div class="skeleton-body">
                    <div class="skeleton-line short"></div>
                    <div class="skeleton-line"></div>
                    <div class="skeleton-line medium"></div>
                </div>
            </li>
            <li class="skeleton-card">
                <div class="skeleton-img"></div>
                <div class="skeleton-body">
                    <div class="skeleton-line short"></div>
                    <div class="skeleton-line"></div>
                    <div class="skeleton-line medium"></div>
                </div>
            </li>
            <li class="skeleton-card">
                <div class="skeleton-img"></div>
                <div class="skeleton-body">
                    <div class="skeleton-line short"></div>
                    <div class="skeleton-line"></div>
                    <div class="skeleton-line medium"></div>
                </div>
            </li>
            <li class="skeleton-card">
                <div class="skeleton-img"></div>
                <div class="skeleton-body">
                    <div class="skeleton-line short"></div>
                    <div class="skeleton-line"></div>
                    <div class="skeleton-line medium"></div>
                </div>
            </li>
        </ul>

        <ul class="products-grid hidden" id="featuredProducts">
            <!-- Products loaded via JavaScript -->
        </ul>
        <footer>
            <a href="<?= htmlspecialchars(i18n_url('products.php'), ENT_QUOTES, 'UTF-8') ?>"
                class="btn btn-outline"><?php i18n_e('home.view_all_products'); ?></a>
        </footer>
    </section>

    <?php if (!empty($homepageGamerReviews)): ?>
        <!-- TESTIMONIALS -->
        <!-- TESTIMONIALS SECTION -->
        <section class="testimonials-premium animate-on-scroll">
            <div class="testimonials-container">
                <header class="testimonials-header">
                    <span class="section-eyebrow">
                        <i class="fas fa-quote-left"></i>
                        <?php i18n_e('home.customer_stories'); ?>
                    </span>
                    <h2><?php i18n_e('home.testimonials_title'); ?></h2>
                    <p><?php i18n_e('home.testimonials_desc'); ?></p>
                </header>

                <div class="testimonials-grid">
                    <?php foreach ($homepageGamerReviews as $review): ?>
                        <?php
                        $rating = max(1, min(5, (float) ($review['rating'] ?? 5)));
                        $fullStars = (int) floor($rating);
                        $hasHalfStar = ($rating - $fullStars) >= 0.5 && $fullStars < 5;
                        $emptyStars = 5 - $fullStars - ($hasHalfStar ? 1 : 0);
                        $reviewerName = homepageReviewText($review, 'reviewer_name');
                        $reviewerRole = homepageReviewText($review, 'reviewer_role');
                        $reviewQuote = homepageReviewText($review, 'quote');
                        $avatar = trim((string) ($review['avatar_initials'] ?? '')) ?: homepageReviewInitials($reviewerName);
                        ?>
                        <article class="testimonial-premium-card">
                            <div class="testimonial-quote-icon">
                                <i class="fas fa-quote-right"></i>
                            </div>
                            <div class="testimonial-stars"
                                aria-label="<?= htmlspecialchars(number_format($rating, 1), ENT_QUOTES, 'UTF-8') ?> out of 5">
                                <?php for ($i = 0; $i < $fullStars; $i++): ?><i class="fas fa-star"></i><?php endfor; ?>
                                <?php if ($hasHalfStar): ?><i class="fas fa-star-half-alt"></i><?php endif; ?>
                                <?php for ($i = 0; $i < $emptyStars; $i++): ?><i class="far fa-star"></i><?php endfor; ?>
                            </div>
                            <p class="testimonial-premium-text">"<?= htmlspecialchars($reviewQuote, ENT_QUOTES, 'UTF-8') ?>"</p>
                            <div class="testimonial-premium-author">
                                <div class="author-avatar"><?= htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="author-info">
                                    <h4><?= htmlspecialchars($reviewerName, ENT_QUOTES, 'UTF-8') ?></h4>
                                    <span><?= htmlspecialchars($reviewerRole, ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                    <?php if (false): ?>
                        <!-- Review 3 -->
                        <article class="testimonial-premium-card">
                            <div class="testimonial-quote-icon">
                                <i class="fas fa-quote-right"></i>
                            </div>
                            <div class="testimonial-stars">
                                <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i
                                    class="fas fa-star"></i><i class="fas fa-star-half-alt"></i>
                            </div>
                            <p class="testimonial-premium-text">"Montage très propre et câble management impeccable. J'ai
                                commandé une workstation pour le rendu 3D et les températures sont excellentes."</p>
                            <div class="testimonial-premium-author">
                                <div class="author-avatar">YB</div>
                                <div class="author-info">
                                    <h4>Yassine B.</h4>
                                    <span><?php i18n_e('home.artist_3d'); ?></span>
                                </div>
                            </div>
                        </article>

                        <!-- Review 4 -->
                        <article class="testimonial-premium-card">
                            <div class="testimonial-quote-icon">
                                <i class="fas fa-quote-right"></i>
                            </div>
                            <div class="testimonial-stars">
                                <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i
                                    class="fas fa-star"></i><i class="fas fa-star"></i>
                            </div>
                            <p class="testimonial-premium-text">"Finally, a Moroccan hardware store that updates stock in
                                real-time. Got my hands on the new OLED monitor before it sold out everywhere else."</p>
                            <div class="testimonial-premium-author">
                                <div class="author-avatar">LR</div>
                                <div class="author-info">
                                    <h4>Lina R.</h4>
                                    <span><?php i18n_e('home.tech_enthusiast'); ?></span>
                                </div>
                            </div>
                        </article>

                        <!-- Review 5 -->
                        <article class="testimonial-premium-card">
                            <div class="testimonial-quote-icon">
                                <i class="fas fa-quote-right"></i>
                            </div>
                            <div class="testimonial-stars">
                                <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i
                                    class="fas fa-star"></i><i class="fas fa-star"></i>
                            </div>
                            <p class="testimonial-premium-text">"Tawsil srie3 w les composants kolhom originaux b garantie. Le
                                setup kamel wsalni l Tanger f a9al mn 48h. Chokran Maroc PC!"</p>
                            <div class="testimonial-premium-author">
                                <div class="author-avatar">AZ</div>
                                <div class="author-info">
                                    <h4>Ali Z.</h4>
                                    <span><?php i18n_e('home.software_engineer'); ?></span>
                                </div>
                            </div>
                        </article>

                        <!-- Review 6 -->
                        <article class="testimonial-premium-card">
                            <div class="testimonial-quote-icon">
                                <i class="fas fa-quote-right"></i>
                            </div>
                            <div class="testimonial-stars">
                                <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i
                                    class="fas fa-star"></i><i class="fas fa-star"></i>
                            </div>
                            <p class="testimonial-premium-text">"Best gaming store in Morocco, hands down. Le service
                                après-vente est incroyable, they helped me troubleshoot a RAM issue via WhatsApp instantly."</p>
                            <div class="testimonial-premium-author">
                                <div class="author-avatar">ST</div>
                                <div class="author-info">
                                    <h4>Sofia T.</h4>
                                    <span><?php i18n_e('home.esports_pro'); ?></span>
                                </div>
                            </div>
                        </article>
                    <?php endif; ?>
                </div>

                <!-- Trust Stats -->
                <div class="testimonials-stats">
                    <div class="testimonial-stat">
                        <div class="stat-value">
                            <?= htmlspecialchars(number_format($homepageGamerAverage, 1), ENT_QUOTES, 'UTF-8') ?><span
                                class="stat-max">/5</span></div>
                        <div class="stat-label"><?php i18n_e('home.average_rating'); ?></div>
                    </div>
                    <div class="testimonial-stat">
                        <div class="stat-value">10,000<span class="stat-plus">+</span></div>
                        <div class="stat-label"><?php i18n_e('home.happy_customers'); ?></div>
                    </div>
                    <div class="testimonial-stat">
                        <div class="stat-value">98<span class="stat-percent">%</span></div>
                        <div class="stat-label"><?php i18n_e('home.satisfaction_rate'); ?></div>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- STORE LOCATIONS MAP -->
    <section class="locations-section animate-on-scroll" style="padding: 6rem 2rem; position: relative;">
        <header>
            <h2 class="text-center"
                style="margin-bottom: 0.5rem; font-family: 'Orbitron', sans-serif; font-weight: 900; letter-spacing: 1px;">
                <?php i18n_e('home.network_title'); ?></h2>
            <p class="text-center"
                style="color: var(--muted); margin-bottom: 4rem; font-family: 'Space Mono', monospace;">
                <?php i18n_e('home.network_desc'); ?></p>
        </header>

        <div class="map-container">
            <div class="map-inner">
                <!-- Inline Morocco Provinces SVG Map -->
                <svg class="map-svg" version="1.2" viewBox="0 0 800 795" stroke-linecap="round" stroke-linejoin="round"
                    id="svg153" xmlns="http://www.w3.org/2000/svg" xmlns:svg="http://www.w3.org/2000/svg"
                    xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:cc="http://creativecommons.org/ns#"
                    xmlns:dc="http://purl.org/dc/elements/1.1/">
                    <style>
                        .map-provinces-group {
                            fill: rgba(0, 245, 212, 0.03);
                            stroke: rgba(0, 245, 212, 0.25);
                            stroke-width: 0.8px;
                            vector-effect: non-scaling-stroke;
                            transition: fill 0.3s ease, stroke 0.3s ease;
                        }

                        .map-province {
                            fill: rgba(0, 245, 212, 0.03);
                            stroke: rgba(0, 245, 212, 0.25);
                            stroke-width: 0.8px;
                            vector-effect: non-scaling-stroke;
                            transition: fill 0.3s ease, stroke 0.3s ease, filter 0.3s ease;
                            cursor: pointer;
                        }

                        .map-province:hover {
                            fill: rgba(0, 245, 212, 0.15);
                            stroke: rgba(0, 245, 212, 0.8);
                            filter: drop-shadow(0 0 4px rgba(0, 245, 212, 0.5));
                        }

                        .map-lake {
                            fill: rgba(0, 245, 212, 0.2) !important;
                            stroke: rgba(0, 245, 212, 0.4) !important;
                            stroke-width: 0.5px !important;
                            vector-effect: non-scaling-stroke;
                        }
                    </style>
                    <metadata id="metadata159">
                        <rdf:RDF>
                            <cc:Work rdf:about="">
                                <dc:format>image/svg+xml</dc:format>
                                <dc:type rdf:resource="http://purl.org/dc/dcmitype/StillImage" />
                                <dc:title />
                            </cc:Work>
                        </rdf:RDF>
                    </metadata>
                    <defs id="defs157">
                        <pattern y="0" x="0" height="6" width="6" patternUnits="userSpaceOnUse" id="EMFhbasepattern" />
                        <pattern y="0" x="0" height="6" width="6" patternUnits="userSpaceOnUse"
                            id="EMFhbasepattern-7" />
                        <pattern y="0" x="0" height="6" width="6" patternUnits="userSpaceOnUse"
                            id="EMFhbasepattern-8" />
                    </defs>

                    <g id="PROVINCE-2015" class="map-provinces-group">
                        <path
                            d="M 208.1779 700.2966 206.3286 697.6547 201.6523 695.5292 197.4011 692.9786 193.5751 689.5777 188.8988 687.0271 184.2225 684.9016 179.9714 682.3509 175.2951 680.2254 171.0439 677.6747 166.7928 675.1241 162.1165 672.9986 157.0151 671.2982 151.4886 670.8731 146.3872 670.4481 141.711 672.1486 136.1845 672.1486 131.5082 670.4482 127.2571 667.0474 123.0059 664.0716 118.3296 661.9461 114.5036 665.7722 112.3781 670.4485 111.9531 675.5498 111.9531 680.6512 110.6778 685.7526 108.1272 690.0037 106.0017 695.1051 105.1515 700.6316 102.1758 704.8828 100.0503 709.5591 100.4755 715.0855 101.7509 720.1869 100.9007 722.7376 95.3742 723.5879 90.6979 723.1628 86.4468 721.8875 81.7705 720.1871 81.3454 720.1871 76.6691 718.4867 71.5678 718.9119 67.3167 721.4626 64.341 725.7138 61.3652 729.965 56.6889 727.8395 54.5633 723.1633 52.0126 718.9122 48.1865 715.0862 42.2348 709.1347 42.2348 704.8835 43.5101 701.4826 46.0607 699.7821 47.7611 695.531 49.0364 689.1543 51.587 687.0287 51.587 682.7775 52.8623 680.6519 56.6883 680.2268 59.239 678.5263 59.664 674.7002 57.1133 671.7245 60.9393 665.7728 66.4657 658.5458 71.1419 654.2946 71.5669 650.4686 73.6924 646.6425 77.5184 643.2416 82.1946 638.5653 85.1704 633.889 83.4699 630.9132 81.3443 631.3383 76.243 636.0147 71.5668 642.3914 69.8663 640.691 71.1416 637.2901 75.8178 630.4882 82.6196 625.3867 86.4456 624.9616 91.547 624.5364 92.8223 619.8601 96.6482 616.034 102.5998 611.7828 107.276 610.5074 108.5513 607.5316 111.5271 604.5558 117.4786 598.6041 121.7298 597.7538 125.5558 593.9277 128.9567 591.6604 129.3818 591.377 131.5074 593.9277 134.9083 593.9276 137.459 594.7778 139.1595 599.4541 141.7102 603.7052 145.1112 603.7051 148.512 601.5795 151.0627 599.879 154.0385 601.5795 158.2897 601.5794 162.1157 599.8789 166.3668 599.4538 176.9947 602.0043 190.1733 607.5307 194.8496 606.2553 199.5258 603.7046 203.3519 602.4292 212.2793 603.7045 222.0569 604.5546 229.2839 605.8299 239.9118 609.6558 245.4383 610.506 251.815 609.2306 260.3173 607.5301 264.9935 608.8054 270.9908 610.2164 272.2205 610.5058 268.82 666.1958 264.1438 666.1958 259.0424 667.4712 250.1151 671.7224 244.5886 673.4229 238.637 674.6983 230.1348 678.9495 221.2074 684.051 215.2559 688.7273 211.0048 696.8045 208.1779 700.2966 Z"
                            id="path2" class="map-province" />
                        <path
                            d="M 208.1779 700.2966 209.3044 701.9059 210.5798 707.0072 211.4301 712.1086 211.4301 712.5337 212.706 776.7258 212.706 777.1509 12.9028 764.8249 11.6275 769.9263 9.9271 774.6025 8.2267 779.2788 5.6761 783.9551 5.6761 784.3802 6.1013 788.2062 5.2511 792.4573 5.8281 793.9409 1 791.1821 2.2753 788.6314 2.2752 784.8054 2.2752 780.5542 4.8257 769.9264 7.3762 759.7236 12.9025 746.545 11.2021 744.4195 13.3276 741.0186 12.0522 738.893 15.028 736.7674 15.028 736.3423 17.1534 729.9656 19.7041 725.7144 21.4044 721.0381 25.6555 718.4874 25.6555 713.386 30.7568 712.1106 35.008 714.6613 39.2591 711.6854 42.2348 709.1347 48.1865 715.0862 52.0126 718.9122 54.5633 723.1633 56.6889 727.8395 61.3652 729.965 64.341 725.7138 67.3167 721.4626 71.5678 718.9119 76.6691 718.4867 81.3454 720.1871 81.7705 720.1871 86.4468 721.8875 90.6979 723.1628 95.3742 723.5879 100.9007 722.7376 101.7509 720.1869 100.4755 715.0855 100.0503 709.5591 102.1758 704.8828 105.1515 700.6316 106.0017 695.1051 108.1272 690.0037 110.6778 685.7526 111.9531 680.6512 111.9531 675.5498 112.3781 670.4485 114.5036 665.7722 118.3296 661.9461 123.0059 664.0716 127.2571 667.0474 131.5082 670.4482 136.1845 672.1486 141.711 672.1486 146.3872 670.4481 151.4886 670.8731 157.0151 671.2982 162.1165 672.9986 166.7928 675.1241 171.0439 677.6747 175.2951 680.2254 179.9714 682.3509 184.2225 684.9016 188.8988 687.0271 193.5751 689.5777 197.4011 692.9786 201.6523 695.5292 206.3286 697.6547 208.1779 700.2966 Z"
                            id="path4" class="map-province" />
                        <path
                            d="M 219.9299 446.4913 226.3067 454.0637 230.1327 454.9139 233.9588 454.4887 241.6108 452.3631 239.4853 455.3389 238.21 458.7398 238.21 461.7156 240.3356 465.5417 246.2873 467.6672 255.6398 470.2178 261.1664 477.4447 263.0865 480.6448 263.7171 481.6959 256.0651 489.348 253.5145 493.5992 250.1136 502.9518 252.2392 507.2029 252.6644 516.5555 254.3649 519.5313 256.0654 523.7824 255.2485 526.3498 253.5147 525.4829 210.5778 497.4257 203.7759 496.5755 196.2064 489.4267 199.0996 486.3728 201.6502 482.1216 204.2008 476.1699 206.7515 471.4936 209.3021 464.2666 209.7272 460.4406 213.5532 458.315 217.8044 454.0637 219.5048 450.6628 219.5047 445.9865 219.9299 446.4913 Z"
                            id="path6" class="map-province" />
                        <path
                            d="M 196.2064 489.4267 203.7759 496.5755 210.5778 497.4257 253.5147 525.4829 255.2485 526.3498 257.7659 527.6084 261.1668 526.7582 266.6933 524.2074 271.7947 524.2074 273.07 525.0576 275.6208 528.8836 275.6059 529.2062 272.2204 602.4286 270.9908 610.2164 264.9935 608.8054 260.3173 607.5301 251.815 609.2306 245.4383 610.506 239.9118 609.6558 229.2839 605.8299 222.0569 604.5546 212.2793 603.7045 203.3519 602.4292 199.5258 603.7046 194.8496 606.2553 190.1733 607.5307 176.9947 602.0043 166.3668 599.4538 162.1157 599.8789 158.2897 601.5794 154.0385 601.5795 151.0627 599.879 148.512 601.5795 145.1112 603.7051 141.7102 603.7052 139.1595 599.4541 137.459 594.7778 134.9083 593.9276 131.5074 593.9277 129.3818 591.377 128.9567 591.6604 128.9566 588.8263 130.2319 584.15 131.5072 582.0244 133.6327 576.0728 134.0578 571.3965 131.9322 566.2952 133.6326 559.9184 136.1832 553.5417 139.584 548.0151 142.5598 540.363 144.6853 537.3872 144.6853 533.1361 147.2359 530.5853 151.0619 525.4839 154.0377 521.2327 154.4627 515.2811 154.4627 511.4551 157.0133 506.7788 162.5398 506.7787 165.5156 505.5033 168.9165 502.5275 170.6169 498.7014 175.2932 499.1265 178.6941 497.426 186.3462 493.5999 196.1238 489.3486 196.2064 489.4267 Z"
                            id="path8" class="map-province" />
                        <path
                            d="M 366.0998 438.8398 367.4452 439.6086 372.5466 443.8598 377.2229 444.71 381.049 443.8597 384.0248 445.5602 388.7011 453.2122 393.8025 456.6131 400.6044 458.3136 405.2807 458.3136 408.6816 456.188 412.5077 457.8884 418.4593 465.1154 421.8603 472.7674 427.3868 479.1442 432.9133 482.1199 438.4166 484.0854 438.865 484.2455 437.1647 536.9599 278.5966 528.4585 275.6059 529.2062 275.6208 528.8836 273.07 525.0576 271.7947 524.2074 266.6933 524.2074 261.1668 526.7582 257.7659 527.6084 255.2485 526.3498 256.0654 523.7824 254.3649 519.5313 252.6644 516.5555 252.2392 507.2029 250.1136 502.9518 253.5145 493.5992 256.0651 489.348 263.7171 481.6959 263.0865 480.6448 264.9925 480.4205 269.6687 474.4689 273.4947 464.6912 279.0211 450.6623 283.2722 443.8604 290.4991 439.6092 295.1754 435.358 299.4265 434.0826 302.6788 432.863 306.6535 434.5076 310.9047 434.5076 313.4554 434.5076 317.2814 435.7829 321.5326 438.3336 325.3587 435.3577 329.6098 435.3577 338.1121 435.3577 346.1894 434.5074 350.8656 435.7827 356.3922 435.7827 361.4936 436.2077 366.0998 438.8398 Z"
                            id="path10" class="map-province" />
                        <path
                            d="M 292.6702 408.6442 293.4747 409.851 293.8999 415.3775 296.8757 417.9282 299.8516 426.0054 302.8275 432.8072 302.6788 432.863 299.4265 434.0826 295.1754 435.358 290.4991 439.6092 283.2722 443.8604 279.0211 450.6623 273.4947 464.6912 269.6687 474.4689 264.9925 480.4205 263.0865 480.6448 261.1664 477.4447 255.6398 470.2178 246.2873 467.6672 240.3356 465.5417 238.21 461.7156 238.21 458.7398 239.4853 455.3389 241.6108 452.3631 233.9588 454.4887 230.1327 454.9139 226.3067 454.0637 219.9299 446.4913 219.9298 441.7354 222.0553 436.2088 222.9056 433.6581 228.0069 432.3827 230.5576 428.9818 233.9585 423.4552 237.3594 420.9045 246.712 419.629 258.6152 418.3536 272.2189 414.5274 284.5473 410.7013 291.7742 407.3003 292.6702 408.6442 Z"
                            id="path12" class="map-province" />
                        <path
                            d="M 343.6585 399.7721 345.764 400.0729 350.4403 402.6236 354.6915 407.2998 357.2422 411.9761 361.0684 420.9035 364.0442 431.5314 366.0998 438.8398 361.4936 436.2077 356.3922 435.7827 350.8656 435.7827 346.1894 434.5074 338.1121 435.3577 329.6098 435.3577 325.3587 435.3577 321.5326 438.3336 317.2814 435.7829 313.4554 434.5076 310.9047 434.5076 306.6535 434.5076 302.6788 432.863 302.8275 432.8072 299.8516 426.0054 296.8757 417.9282 293.8999 415.3775 293.4747 409.851 292.6702 408.6442 301.1268 405.5997 308.3538 403.049 310.4793 398.3727 315.5807 393.6964 319.4067 388.5949 324.5081 386.8944 327.4839 390.2953 329.1844 393.2711 332.5853 393.2711 335.1361 395.8218 336.8366 398.7976 343.6585 399.7721 Z"
                            id="path14" class="map-province" />
                        <path
                            d="M 441.4154 410.2752 432.4879 400.4976 429.9372 396.2464 429.0869 391.5701 426.5362 389.4445 424.1839 389.4446 424.1839 385.0992 421.6034 385.4218 419.9906 383.1638 419.9906 379.9382 421.926 377.6803 420.6357 376.0675 417.4101 375.745 417.0875 373.1645 415.7973 369.9389 416.7649 367.681 419.668 367.3584 420.9582 365.7456 420.9582 363.1651 422.8936 363.1651 422.8936 359.6169 419.9905 354.1334 420.313 351.8755 421.2807 349.9401 423.8612 349.295 428.6996 348.0047 431.6026 348.0047 434.1831 347.3596 435.7959 345.7468 436.7636 348.3272 437.4087 349.94 440.3118 352.198 441.9246 350.5851 444.8276 351.2303 446.4404 350.5851 443.8599 346.3918 441.2794 343.4888 442.5697 340.2632 442.8922 336.0699 442.8922 332.1992 441.6019 330.2638 443.2147 328.9736 446.1178 327.3608 442.5696 327.3608 441.9245 325.4254 443.8598 324.4577 442.8921 319.2968 445.1501 320.2644 448.3757 320.2644 449.6659 321.5547 450.9562 323.49 453.2141 323.49 455.1495 322.5223 456.7623 323.8126 458.6976 323.8125 460.3104 320.2644 462.2458 319.2967 465.1488 319.2967 468.3744 319.2967 469.3421 320.2643 470.6324 323.1674 472.8903 323.1674 473.2129 321.232 474.8257 320.2643 475.4708 318.0064 475.4708 313.4905 476.4384 312.5229 479.3415 312.5228 480.9543 313.8131 483.5348 313.8131 485.7927 314.4582 487.7281 314.4582 487.7281 312.2003 490.6311 308.9746 494.5018 308.6521 498.3726 307.6844 500.6305 309.9423 502.8884 311.2325 505.4689 310.91 509.0171 313.4904 510.3073 312.8453 508.6945 308.3295 509.6622 306.7167 511.9201 304.1362 514.178 298.3301 517.7262 300.2654 520.9518 303.1685 523.5323 302.2008 525.4677 300.9105 528.6933 301.2331 529.0158 298.0075 529.9835 295.7496 533.5317 296.7172 535.7896 296.0721 538.3701 294.1367 539.9829 298.6526 541.5957 298.33 543.5311 301.2331 545.789 303.1684 542.2408 306.0715 542.5634 311.2325 545.4665 312.8453 544.1762 315.7483 543.5311 318.3288 545.789 318.9739 548.3695 317.6837 551.5951 318.0062 551.2726 319.2965 547.0793 323.8123 546.1116 326.3928 547.7244 328.9733 545.7891 331.2312 545.4665 334.1343 547.4019 335.7471 548.047 338.005 547.0793 344.7788 544.4988 346.069 539.6604 348.3269 534.4995 347.6818 527.4031 347.6818 516.7586 345.1014 507.4044 351.2301 502.2434 352.5203 496.4373 352.5203 479.0191 364.1326 462.5686 377.035 449.6662 386.7119 444.8278 388.6473 442.5699 391.5503 442.2473 401.5497 441.4154 410.2752 Z"
                            id="path16" class="map-province" />
                        <path
                            d="M 419.9905 354.1334 422.8936 359.6169 422.8936 363.1651 420.9582 363.1651 420.9582 365.7456 419.668 367.3584 416.7649 367.681 415.7973 369.9389 417.0875 373.1645 417.4101 375.745 420.6357 376.0675 421.926 377.6803 419.9906 379.9382 419.9906 383.1638 421.6034 385.4218 424.1839 385.0992 424.1839 389.4446 422.7101 389.4446 418.8841 388.1692 409.9566 382.2176 407.831 386.0437 398.0533 388.5944 391.6766 389.8698 383.5994 394.9713 373.3966 397.522 360.218 398.7975 357.2422 397.5221 357.2421 392.8458 355.5416 390.2952 351.7156 394.1212 347.0393 395.3966 344.4886 397.0971 343.6585 399.7721 336.8366 398.7976 335.1361 395.8218 332.5853 393.2711 329.1844 393.2711 327.4839 390.2953 324.5081 386.8944 326.4481 383.1644 329.3512 380.9064 337.7377 378.0033 346.4468 372.5198 355.1559 368.649 359.9943 362.1977 362.8973 365.1008 366.7681 363.1654 369.3485 363.1654 369.026 366.0684 371.6065 367.3586 375.4772 368.3263 375.1546 364.7781 381.6058 361.5525 387.0894 363.8104 386.4442 361.2299 389.3473 361.2299 389.3473 358.3268 387.4119 356.0689 394.1856 353.1658 398.0564 357.6817 399.9918 359.2945 402.5722 355.7463 402.2497 353.8109 405.7978 353.1658 406.7655 355.7463 408.7009 356.7139 411.2814 355.4237 413.8618 353.4883 416.1198 353.4883 419.9905 354.1334 Z"
                            id="path18" class="map-province" />
                        <path
                            d="M 426.5362 389.4445 429.0869 391.5701 429.9372 396.2464 432.4879 400.4976 441.4154 410.2752 439.7151 463.8399 439.2901 482.1199 438.4166 484.0854 432.9133 482.1199 427.3868 479.1442 421.8603 472.7674 418.4593 465.1154 412.5077 457.8884 408.6816 456.188 405.2807 458.3136 400.6044 458.3136 393.8025 456.6131 388.7011 453.2122 384.0248 445.5602 381.049 443.8597 377.2229 444.71 372.5466 443.8598 367.4452 439.6086 366.0998 438.8398 364.0442 431.5314 361.0684 420.9035 357.2422 411.9761 354.6915 407.2998 350.4403 402.6236 345.764 400.0729 343.6585 399.7721 344.4886 397.0971 347.0393 395.3966 351.7156 394.1212 355.5416 390.2952 357.2421 392.8458 357.2422 397.5221 360.218 398.7975 373.3966 397.522 383.5994 394.9713 391.6766 389.8698 398.0533 388.5944 407.831 386.0437 409.9566 382.2176 418.8841 388.1692 422.7101 389.4446 424.1839 389.4446 426.5362 389.4445 Z"
                            id="path20" class="map-province" />
                        <path
                            d="M 419.9905 354.1334 416.1198 353.4883 413.8618 353.4883 411.2814 355.4237 408.7009 356.7139 406.7655 355.7463 405.7978 353.1658 402.2497 353.8109 402.5722 355.7463 399.9918 359.2945 398.0564 357.6817 394.1856 353.1658 387.4119 356.0689 389.3473 358.3268 389.3473 361.2299 386.4442 361.2299 387.0894 363.8104 381.6058 361.5525 375.1546 364.7781 375.4772 368.3263 371.6065 367.3586 369.026 366.0684 369.3485 363.1654 366.7681 363.1654 362.8973 365.1008 359.9943 362.1977 361.9296 358.6496 365.1552 356.0691 368.3808 354.7788 370.9613 349.2953 374.5094 345.7471 377.735 340.5861 378.3801 337.0379 379.6703 340.2635 380.638 342.844 383.2185 339.9409 384.1862 339.9409 385.799 344.7793 386.1216 346.7147 388.7021 348.0049 391.2826 346.0695 394.1856 345.747 395.7984 347.6823 398.0563 347.6823 401.2819 345.1018 403.2173 348.0048 404.8301 347.3597 406.1204 348.0048 408.3783 348.0048 410.6362 343.4889 414.5069 344.7792 417.41 347.3596 418.0551 349.295 419.9905 354.1334 Z"
                            id="path22" class="map-province" />
                        <path
                            d="M 428.0544 328.3285 430.3123 329.2962 434.1831 331.8767 436.1184 333.4895 439.6666 333.4894 441.6019 330.2638 442.8922 332.1992 442.8922 336.0699 442.5697 340.2632 441.2794 343.4888 443.8599 346.3918 446.4404 350.5851 444.8276 351.2303 441.9246 350.5851 440.3118 352.198 437.4087 349.94 436.7636 348.3272 435.7959 345.7468 434.1831 347.3596 431.6026 348.0047 428.6996 348.0047 423.8612 349.295 421.2807 349.9401 420.313 351.8755 419.9905 354.1334 418.0551 349.295 417.41 347.3596 414.5069 344.7792 410.6362 343.4889 408.3783 348.0048 406.1204 348.0048 404.8301 347.3597 403.2173 348.0048 401.2819 345.1018 398.0563 347.6823 395.7984 347.6823 394.1856 345.747 391.2826 346.0695 388.7021 348.0049 386.1216 346.7147 385.799 344.7793 384.1862 339.9409 383.2185 339.9409 380.638 342.844 379.6703 340.2635 378.3801 337.0379 381.9282 331.8769 385.7989 329.619 388.3794 327.0385 390.9599 321.8775 395.1532 322.2 399.669 322.5226 400.9592 320.5872 403.8623 320.5872 404.1849 323.4902 405.4751 323.1677 405.4751 325.7481 406.7654 324.7804 408.7007 326.3932 410.6361 326.3932 411.9263 327.3609 416.1196 327.3609 418.3776 327.6834 417.0873 329.9414 417.0873 330.9091 419.9904 331.2316 421.6032 335.1023 422.8934 334.7798 423.216 331.8767 425.7965 331.5541 425.7965 328.9736 428.0544 328.3285 Z"
                            id="path24" class="map-province" />
                        <path
                            d="M 414.668 301.0722 412.2488 301.0722 409.9909 301.8786 407.5716 301.2335 405.7976 302.0399 402.2494 302.0399 399.1851 301.2335 399.9914 298.1692 401.7655 297.6854 403.3783 296.0725 405.1524 295.2661 405.1524 298.4917 407.4103 298.1692 410.3134 297.0402 412.8939 296.8789 414.6679 293.6533 416.7646 291.7179 415.6357 298.653 416.2808 299.1368 416.4421 300.1045 414.668 301.0722 Z"
                            id="path26" class="map-province" />
                        <path
                            d="M 428.0544 328.3285 425.7965 328.9736 425.7965 331.5541 423.216 331.8767 422.8934 334.7798 421.6032 335.1023 419.9904 331.2316 417.0873 330.9091 417.0873 329.9414 418.3776 327.6834 416.1196 327.3609 411.9263 327.3609 410.6361 326.3932 408.7007 326.3932 406.7654 324.7804 405.4751 325.7481 405.4751 323.1677 404.1849 323.4902 403.8623 320.5872 400.9592 320.5872 399.669 322.5226 395.1532 322.2 390.9599 321.8775 392.7339 318.3293 394.8306 314.6198 396.9272 310.104 399.1851 301.2335 402.2494 302.0399 405.7976 302.0399 407.5716 301.2335 409.9909 301.8786 412.2488 301.0722 414.668 301.0722 415.9582 303.6527 418.3774 303.9752 420.1515 304.7816 422.732 303.8139 423.861 304.6203 425.7964 307.0395 428.6994 307.2008 429.5058 310.5877 431.4412 311.8779 433.5378 312.2005 433.3766 314.4584 430.9574 316.0712 431.28 319.2968 433.3766 323.9739 433.3766 326.2318 431.9251 326.7157 428.2157 326.2319 428.0544 328.3285 Z"
                            id="path28" class="map-province" />
                        <path
                            d="M 418.8612 275.106 419.6676 278.4929 419.345 280.1057 417.5709 279.6219 415.9581 282.0411 414.5066 283.8152 414.1841 286.3957 416.6033 287.6859 416.9259 289.2987 416.7646 291.7179 414.6679 293.6533 412.8939 296.8789 410.3134 297.0402 407.4103 298.1692 405.1524 298.4917 405.1524 295.2661 403.3783 296.0725 401.7655 297.6854 399.9914 298.1692 399.9914 295.2662 398.2173 293.3308 394.5079 288.0086 390.9597 284.9443 388.3792 282.8476 389.0243 279.9446 390.7983 276.0738 391.6047 272.6869 392.0885 267.8485 394.0239 268.8162 394.1852 271.0741 394.8303 274.7836 398.056 275.2674 399.5075 274.461 401.7654 270.429 404.991 268.8161 406.6038 272.203 408.0553 273.1707 413.055 269.2999 414.8291 268.9774 416.7645 271.8804 418.8612 275.106 Z"
                            id="path30" class="map-province" />
                        <path
                            d="M 490.6311 308.9746 487.7281 312.2003 487.7281 314.4582 485.7927 314.4582 483.5348 313.8131 480.9543 313.8131 479.3415 312.5228 476.4384 312.5229 475.4708 313.4905 475.4708 318.0064 474.8257 320.2643 473.2129 321.232 472.8903 323.1674 470.6324 323.1674 469.3421 320.2643 468.3744 319.2967 465.1488 319.2967 462.2458 319.2967 460.3104 320.2644 458.6976 323.8125 456.7623 323.8126 455.1495 322.5223 453.2141 323.49 450.9562 323.49 449.6659 321.5547 448.3757 320.2644 445.1501 320.2644 442.8921 319.2968 443.8598 324.4577 441.9245 325.4254 442.5696 327.3608 446.1178 327.3608 443.2147 328.9736 441.6019 330.2638 439.6666 333.4894 436.1184 333.4895 434.1831 331.8767 430.3123 329.2962 428.0544 328.3285 428.2157 326.2319 431.9251 326.7157 433.3766 326.2318 433.3766 323.9739 431.28 319.2968 430.9574 316.0712 433.3766 314.4584 433.5378 312.2005 431.4412 311.8779 429.5058 310.5877 428.6994 307.2008 425.7964 307.0395 423.861 304.6203 422.732 303.8139 420.1515 304.7816 418.3774 303.9752 415.9582 303.6527 414.668 301.0722 416.4421 300.1045 416.2808 299.1368 415.6357 298.653 416.7646 291.7179 416.9259 289.2987 416.6033 287.6859 414.1841 286.3957 414.5066 283.8152 415.9581 282.0411 417.5709 279.6219 419.345 280.1057 419.6676 278.4929 418.8612 275.106 421.7642 273.1706 423.6996 273.3319 426.9252 273.8157 429.8282 274.2996 430.6346 271.2352 431.7636 268.816 435.1505 266.8806 438.2148 265.1065 439.8276 266.8806 440.634 269.1385 440.634 272.3641 441.4404 274.1382 444.1822 272.5254 445.9563 271.0739 448.8593 271.0739 453.5365 271.5577 456.4395 271.8802 458.5362 273.1705 460.7941 273.0092 466.1163 269.7836 469.8258 267.6869 472.0837 269.2997 475.1481 270.9125 477.2447 270.4286 478.0511 267.8481 479.6639 262.5259 481.9218 261.2356 485.7925 258.3326 487.8892 256.7198 489.8246 256.7197 489.1794 258.8164 486.9215 260.9131 488.6956 263.0097 490.3084 263.3323 489.502 264.9451 489.1795 267.8481 489.1795 271.7188 490.631 273.3316 493.8566 274.6219 491.4374 276.0734 490.3085 279.9442 495.1469 280.5893 495.1469 283.331 497.0823 286.7179 499.824 290.5886 497.5661 291.2338 495.9533 292.0402 494.5018 294.4594 491.4375 294.4594 489.6634 296.2335 490.4698 299.2978 491.5988 299.2978 493.6954 299.6204 495.9533 303.3298 495.147 305.5877 490.6311 308.9746 Z"
                            id="path32" class="map-province" />
                        <path
                            d="M 547.0793 344.7788 548.047 338.005 547.4019 335.7471 545.4665 334.1343 545.7891 331.2312 547.7244 328.9733 546.1116 326.3928 547.0793 323.8123 551.2726 319.2965 551.5951 318.0062 548.3695 317.6837 545.789 318.9739 543.5311 318.3288 544.1762 315.7483 545.4665 312.8453 542.5634 311.2325 542.2408 306.0715 545.789 303.1684 543.5311 301.2331 541.5957 298.33 539.9829 298.6526 538.3701 294.1367 535.7896 296.0721 533.5317 296.7172 529.9835 295.7496 528.8545 293.8142 527.403 292.2014 525.6289 290.266 523.371 288.6532 524.1774 287.2017 524.9838 284.7825 524.0161 281.8794 524.8225 280.5892 528.2094 278.3313 531.5963 276.0733 533.5316 273.6541 535.3057 271.7187 537.5637 271.5575 539.6603 272.6864 539.6603 276.3959 540.7893 276.5572 543.2085 274.7831 548.2082 274.2992 551.4338 274.1379 553.6917 272.6864 556.5948 271.3962 559.8204 270.4285 562.5622 268.3318 565.3039 264.7836 568.207 262.5257 569.9811 261.558 571.9164 261.3967 574.0131 258.8162 576.9161 258.4937 578.3676 259.4614 580.303 262.2031 582.8835 263.9772 586.593 266.0739 589.3347 264.9449 592.2378 264.6223 593.528 265.1062 597.0762 265.59 600.9469 265.59 601.9146 266.2352 602.3985 267.848 601.5921 269.4608 601.5921 271.5574 599.818 271.88 598.3665 272.6864 599.3341 274.9443 598.2052 277.8474 598.5277 278.815 599.6567 278.1699 601.4308 277.3635 604.0113 280.2666 602.3985 282.2019 602.8823 284.1373 605.3015 284.7824 607.882 285.105 608.6884 286.8791 608.8497 289.137 606.9143 290.1047 604.4951 290.9111 603.6887 294.7818 604.1725 295.7495 606.2692 296.3946 608.2046 298.0074 610.6238 297.5236 612.3979 297.5236 613.8494 298.8138 616.5911 301.5556 616.5911 303.491 614.9783 307.8455 615.1396 311.3937 615.946 315.2644 614.3332 318.0062 609.1722 321.8769 606.4305 323.651 606.2692 325.4251 604.6564 327.8443 602.0759 328.812 599.9793 331.715 594.9796 336.0696 593.2055 339.2952 590.7863 344.94 588.6896 350.101 587.2381 351.7138 585.1415 351.7138 584.4963 349.4559 584.4963 348.1656 583.6899 347.5205 580.3031 348.4882 580.3031 347.1979 580.7869 345.1013 580.1418 345.1013 578.6903 345.1013 577.0775 347.3592 575.3034 347.8431 569.0134 349.6172 563.2073 349.6172 559.0141 349.4559 557.24 348.1656 554.6595 346.5528 552.079 346.5528 547.0793 344.7788 Z"
                            id="path34" class="map-province" />
                        <path
                            d="M 559.8204 270.4285 556.5948 271.3962 553.6917 272.6864 551.4338 274.1379 548.2082 274.2992 543.2085 274.7831 540.7893 276.5572 539.6603 276.3959 539.6603 272.6864 537.5637 271.5575 535.3057 271.7187 533.5316 273.6541 531.5963 276.0733 528.2094 278.3313 524.8225 280.5892 524.0161 281.8794 524.9838 284.7825 524.1774 287.2017 523.371 288.6532 525.6289 290.266 527.403 292.2014 528.8545 293.8142 529.9835 295.7496 529.0158 298.0075 528.6933 301.2331 525.4677 300.9105 523.5323 302.2008 520.9518 303.1685 517.7262 300.2654 514.178 298.3301 511.9201 304.1362 509.6622 306.7167 508.6945 308.3295 510.3073 312.8453 509.0171 313.4904 505.4689 310.91 502.8884 311.2325 500.6305 309.9423 498.3726 307.6844 494.5018 308.6521 490.6311 308.9746 495.147 305.5877 495.9533 303.3298 493.6954 299.6204 491.5988 299.2978 490.4698 299.2978 489.6634 296.2335 491.4375 294.4594 494.5018 294.4594 495.9533 292.0402 497.5661 291.2338 499.824 290.5886 497.0823 286.7179 495.1469 283.331 495.1469 280.5893 490.3085 279.9442 491.4374 276.0734 493.8566 274.6219 490.631 273.3316 489.1795 271.7188 489.1795 267.8481 489.502 264.9451 490.3084 263.3323 488.6956 263.0097 486.9215 260.9131 489.1794 258.8164 489.8246 256.7197 492.2438 256.2359 494.9855 254.1392 497.0822 253.1716 501.2755 252.3651 503.2108 251.3974 504.6624 249.6234 508.2105 249.6233 509.9846 248.9782 513.5328 246.8816 513.5328 243.4947 515.952 243.6559 516.5971 244.3011 518.3712 246.3977 520.4679 246.2364 522.7258 245.1075 523.5322 247.3654 526.274 247.3654 532.7252 247.3654 535.3057 246.0751 536.5959 239.9464 536.7572 237.6885 539.3377 238.0111 542.5633 238.8175 545.1438 237.6885 547.4017 237.2047 549.8209 236.8821 550.4661 237.8498 550.4661 241.2367 551.7563 243.4946 554.0143 246.5589 556.4335 248.8169 557.4012 250.7522 558.2076 265.4288 558.5301 267.5254 559.8204 270.4285 Z"
                            id="path36" class="map-province" />
                        <path
                            d="M 702.8764 227.3666 699.4895 227.8505 696.909 227.8505 695.7801 226.2377 696.7477 224.7861 699.6508 223.4959 703.0377 221.5605 701.9087 220.7541 699.8121 219.9477 696.2639 213.819 696.2639 208.658 696.2639 206.2388 698.5219 203.1745 699.4896 202.2068 697.8768 202.3681 693.8447 203.6583 690.6191 204.9486 687.0709 203.9809 684.0066 201.8842 681.91 203.0132 679.1682 204.9485 677.3941 205.9162 677.7167 202.3681 675.7813 201.4004 672.0718 200.7552 671.4267 197.0458 671.4267 193.6589 670.2978 190.9171 668.6849 189.143 665.1368 187.8527 664.3304 186.4012 664.9755 185.2722 665.7819 183.4982 664.4917 182.6918 661.7499 179.95 661.9112 176.8856 662.7176 174.6277 666.1045 172.0472 667.8786 166.8862 674.0073 159.6286 675.4588 156.8868 677.7168 153.1773 681.7488 151.4033 685.4583 153.1774 689.329 153.4999 694.9738 152.5323 700.9412 150.4356 703.3605 150.4356 706.9086 150.2744 707.3925 148.0164 712.3922 147.5326 713.1986 146.2423 709.6505 141.8877 704.812 135.1139 707.8764 131.4045 712.3923 126.7273 713.1987 123.0179 715.6179 120.7599 717.392 120.7599 718.6823 121.4051 719.6499 122.534 737.8747 122.0503 740.1327 120.1149 743.3583 118.8247 746.5839 119.6311 751.5836 122.2116 755.9382 122.2116 759.0026 121.5665 761.2605 120.4375 764.3249 119.1473 765.7764 122.3729 768.1956 124.7922 769.1632 131.2434 767.2278 137.8559 767.3891 141.0816 769.0019 144.6298 771.5824 148.0167 774.6467 151.4036 776.0982 153.8228 776.4208 157.5323 775.4531 159.6289 774.8079 162.0481 776.7433 166.0802 778.8399 168.822 782.872 168.9833 784.9686 170.1122 789.3232 174.3056 796.097 178.1763 799 181.2407 795.7744 185.7566 791.7423 190.7562 790.9359 194.3044 792.0648 198.9816 794.3227 202.5298 794.8066 204.3039 793.0325 205.5941 790.2907 206.723 786.9038 206.723 784.3233 205.4328 779.3236 204.7876 761.9053 204.9488 754.9702 202.5296 752.3897 202.8521 749.8092 205.4326 748.1964 208.1744 743.5192 207.6905 742.229 209.3033 741.2613 210.271 738.8421 208.1743 736.2616 204.9487 731.907 205.11 729.004 207.0453 725.9396 207.5292 721.2625 207.5292 718.5207 208.013 715.7789 209.9484 713.8435 211.3999 715.6176 212.2063 717.3917 213.0127 716.424 215.1093 715.4563 217.0447 715.7789 218.8188 716.2627 222.367 716.2627 223.4959 714.1661 224.9475 710.6179 226.5603 709.1664 226.5603 702.8764 227.3666 Z"
                            id="path38" class="map-province" />
                        <path
                            d="M 702.8764 227.3666 703.0377 231.2374 696.5864 231.7212 691.4255 231.7212 689.3288 234.1404 687.5547 236.3983 683.2001 239.3014 680.9422 240.2691 677.7166 239.3014 675.9425 239.3014 675.2974 241.7206 676.1038 243.9785 676.7489 245.9139 672.0717 246.2364 671.4266 247.6879 672.233 253.8166 670.9428 257.3648 670.9427 258.9776 672.8781 258.4938 674.3296 258.0099 674.8135 259.784 676.1037 260.5904 677.394 259.4614 678.6842 260.4291 678.8455 261.7194 680.4583 263.816 682.2324 263.816 683.5226 263.9773 683.5226 265.7514 682.0711 268.3319 672.0717 281.073 670.4589 283.4922 670.4589 286.234 669.3299 288.0081 667.2332 288.6532 654.8146 291.395 650.7826 292.3626 649.1698 292.6852 634.9771 302.6846 615.946 315.2644 615.1396 311.3937 614.9783 307.8455 616.5911 303.491 616.5911 301.5556 613.8494 298.8138 612.3979 297.5236 610.6238 297.5236 608.2046 298.0074 606.2692 296.3946 612.3979 291.5562 619.1716 285.4275 625.7841 279.2989 626.4293 275.1056 627.5582 271.7187 629.3323 268.9769 630.6226 267.848 631.2677 259.6227 634.8159 256.8809 636.9125 255.4294 638.5253 250.9135 638.0415 248.9781 635.1385 249.1394 631.9128 250.2684 628.2034 249.3007 625.139 249.3007 620.7845 249.3007 616.9137 250.7522 611.4302 252.5263 609.1723 253.3327 606.5918 252.6876 606.4305 249.6232 607.0756 247.3653 608.6884 243.9784 609.1723 242.6882 607.7207 242.5269 606.753 240.9141 603.0436 241.2366 600.4631 240.7528 597.8826 238.6562 595.786 236.8821 595.4634 234.9467 595.1408 233.8177 594.1732 233.0113 594.8183 231.5598 599.1729 224.6247 602.5598 222.2055 606.9143 219.4637 608.8497 221.0765 611.4302 219.9476 611.5915 218.3348 612.0753 216.2381 613.8494 214.7866 612.7204 213.4964 611.2689 211.561 613.3656 211.8835 615.6235 211.561 615.3009 210.1095 616.5912 208.6579 618.204 207.2064 618.3653 204.7872 620.7845 203.6582 622.7199 202.5293 623.365 204.3033 625.3004 207.6902 627.397 210.7546 629.6549 210.5933 631.2677 208.9805 632.0742 207.529 635.6223 208.6579 639.0092 208.8192 640.622 207.529 644.3315 203.8195 645.9443 202.6906 648.8474 202.5293 651.2666 201.8842 653.5245 201.0778 654.4922 202.6906 654.976 205.7549 657.3953 206.2388 660.6209 205.7549 661.1047 203.6583 662.7175 202.8519 664.9755 202.2067 667.0721 203.1744 669.8139 202.8519 671.4267 201.7229 672.0718 200.7552 675.7813 201.4004 677.7167 202.3681 677.3941 205.9162 679.1682 204.9485 681.91 203.0132 684.0066 201.8842 687.0709 203.9809 690.6191 204.9486 693.8447 203.6583 697.8768 202.3681 699.4896 202.2068 698.5219 203.1745 696.2639 206.2388 696.2639 208.658 696.2639 213.819 699.8121 219.9477 701.9087 220.7541 703.0377 221.5605 699.6508 223.4959 696.7477 224.7861 695.7801 226.2377 696.909 227.8505 699.4895 227.8505 702.8764 227.3666 Z"
                            id="path40" class="map-province" />
                        <path
                            d="M 764.3249 119.1473 761.2605 120.4375 759.0026 121.5665 755.9382 122.2116 751.5836 122.2116 746.5839 119.6311 743.3583 118.8247 740.1327 120.1149 737.8747 122.0503 719.6499 122.534 719.65 116.2441 721.1015 113.1797 722.3918 111.5669 723.5207 110.438 721.2628 108.8251 720.6177 106.7285 722.8756 104.9544 726.2625 103.3416 730.9397 101.7288 731.7461 98.987 730.4559 96.8903 729.1656 93.6647 726.5852 89.794 728.3593 88.3424 730.2946 87.0522 732.23 86.0845 737.391 85.4394 737.7136 83.504 735.9395 80.7622 737.7136 80.7622 739.1651 78.9881 741.1005 78.9882 741.9069 77.8592 746.2615 77.5366 747.068 75.2787 748.5195 74.311 749.3259 72.5369 751.9064 71.2467 754.0031 71.5693 756.7449 71.5693 758.8415 69.9565 760.4543 72.537 761.422 73.9885 763.5187 74.1498 764.0025 75.6013 763.1961 77.698 761.0994 80.1172 761.0994 81.8913 762.3897 83.6654 764.0025 85.7621 764.0024 89.3103 764.9701 93.0197 766.5829 98.9872 766.099 103.9869 765.4539 107.5351 765.2926 112.6961 765.9377 115.9217 764.3249 119.1473 Z"
                            id="path42" class="map-province" />
                        <path
                            d="M 758.8415 69.9565 756.7449 71.5693 754.0031 71.5693 751.9064 71.2467 749.3259 72.5369 748.5195 74.311 747.068 75.2787 746.2615 77.5366 741.9069 77.8592 741.1005 78.9882 739.1651 78.9881 737.7136 80.7622 735.9395 80.7622 734.9718 77.0528 734.6493 75.9238 735.617 73.9884 736.1008 73.3433 735.2944 70.2789 737.2298 70.1177 739.0039 69.15 738.5201 66.7308 738.3588 62.5374 737.3911 60.7633 737.875 59.6344 738.6814 57.699 738.6814 56.57 739.6491 56.57 740.778 57.2152 741.1006 56.0862 742.0683 56.0862 744.6488 55.6024 744.6488 53.667 745.6165 52.8606 746.5842 51.7316 747.8745 51.5703 750.9388 51.5703 751.4226 53.3444 751.7452 54.6347 754.0031 55.925 754.6483 57.3765 756.9062 58.9893 759.648 59.7957 762.3898 60.7634 761.2608 63.1827 759.1641 65.9244 757.8738 68.1824 758.8415 69.9565 Z"
                            id="path44" class="map-province" />
                        <path
                            d="M 735.2944 70.2789 736.1008 73.3433 735.617 73.9884 734.6493 75.9238 734.9718 77.0528 735.9395 80.7622 737.7136 83.504 737.391 85.4394 732.23 86.0845 730.2946 87.0522 728.3593 88.3424 726.5852 89.794 729.1656 93.6647 730.4559 96.8903 731.7461 98.987 730.9397 101.7288 726.2625 103.3416 722.8756 104.9544 720.6177 106.7285 721.2628 108.8251 723.5207 110.438 722.3918 111.5669 721.1015 113.1797 719.65 116.2441 719.6499 122.534 718.6823 121.4051 717.392 120.7599 715.6179 120.7599 713.1987 123.0179 712.3923 126.7273 707.8764 131.4045 704.812 135.1139 702.0703 132.856 699.1672 130.9206 694.6513 130.598 694.6513 127.5337 696.9093 125.7596 700.1349 122.6953 703.3606 118.8245 705.1347 117.373 704.4895 115.5989 700.4575 114.9538 699.6511 113.0184 698.3609 109.3089 696.1029 105.1156 696.1029 102.5351 697.0706 101.0836 700.4575 101.0836 702.5542 98.9869 703.3606 96.8903 702.8768 95.6 700.7801 93.1808 700.6189 88.3423 698.6835 86.7295 695.9417 83.9877 696.4256 81.5685 699.6512 78.5042 701.4253 76.4075 703.0381 73.9883 702.8768 69.9563 706.7476 70.1176 706.5863 69.3111 706.5863 67.6983 709.3281 66.5694 711.9086 65.763 714.1666 63.3438 716.9084 63.1825 721.9081 64.1502 723.8435 62.8599 725.7788 62.2148 728.3593 63.1825 729.4883 64.4728 729.327 66.2469 728.8432 69.7951 731.585 69.7951 734.3267 69.6338 735.2944 70.2789 Z"
                            id="path46" class="map-province" />
                        <path
                            d="M 746.5842 51.7316 745.6165 52.8606 744.6488 53.667 744.6488 55.6024 742.0683 56.0862 741.1006 56.0862 740.778 57.2152 739.6491 56.57 738.6814 56.57 738.6814 57.699 737.875 59.6344 737.3911 60.7633 738.3588 62.5374 738.5201 66.7308 739.0039 69.15 737.2298 70.1177 735.2944 70.2789 734.3267 69.6338 731.585 69.7951 728.8432 69.7951 729.327 66.2469 729.4883 64.4728 728.3593 63.1825 725.7788 62.2148 723.8435 62.8599 721.9081 64.1502 716.9084 63.1825 714.1666 63.3438 715.1343 59.9568 717.0697 58.344 719.3276 56.5699 720.9404 53.5056 723.1984 52.5379 725.9402 51.7315 729.4884 51.0864 729.4884 47.8608 731.2625 43.99 733.6817 41.5708 734.3831 41.2903 734.4881 41.2483 738.1976 42.5385 740.2943 43.1837 739.9717 45.2803 740.9394 47.2157 742.8747 48.9898 746.5842 51.7316 Z"
                            id="path48" class="map-province" />
                        <path
                            d="M 734.3831 41.2903 733.6817 41.5708 731.2625 43.99 729.4884 47.8608 729.4884 51.0864 725.9402 51.7315 723.1984 52.5379 720.9404 53.5056 719.3276 56.5699 717.0697 58.344 715.1343 59.9568 714.1666 63.3438 711.9086 65.763 709.3281 66.5694 706.5863 67.6983 706.7476 65.763 706.7476 64.634 704.4897 65.1178 702.8769 65.1178 702.393 63.1824 700.1351 62.6986 698.361 62.6986 698.361 61.247 694.329 60.9245 693.5068 60.9245 693.4171 58.9519 693.4171 57.2398 693.4171 53.5179 693.566 52.3269 694.3848 52.1036 695.1292 51.4337 694.9059 50.3171 693.7893 49.0517 693.7149 48.084 694.6826 47.6374 695.6503 46.893 694.9803 45.6275 694.5337 44.511 694.9803 42.5756 696.3947 40.4169 696.3202 38.6304 696.2458 36.9928 696.2557 36.9828 696.9157 36.3228 697.809 35.1318 698.4789 33.792 699.1489 33.8664 699.9677 34.313 700.7865 33.9408 701.6798 32.5265 702.7963 30.5912 703.4663 29.028 703.8385 27.3159 704.5828 25.5294 705.1783 25.1572 706.146 25.3061 706.3693 25.7527 706.0716 26.7948 705.9227 27.7625 705.6994 29.4746 706.2205 30.219 706.5182 30.5912 706.816 31.7077 707.337 32.6754 707.337 33.2709 707.337 33.8664 707.5603 34.2386 707.9325 34.7597 709.8679 36.6951 709.8679 37.1417 709.1979 36.4717 707.9325 35.2063 707.6348 35.5041 707.7836 36.4717 708.3791 37.0672 708.9002 37.5883 708.4536 38.1838 707.8581 38.6304 707.9325 39.2259 708.4536 39.9703 708.9002 40.938 709.6446 41.3102 710.6867 42.129 711.6544 42.5012 712.9942 43.2456 713.7386 43.32 714.7807 43.32 715.674 43.2456 715.674 42.5757 715.0785 42.129 714.6319 41.5335 714.1108 41.4591 713.7386 40.7147 713.2176 40.417 712.5476 39.4493 711.0589 38.0349 711.5799 38.0349 712.3243 38.6305 713.1431 39.3748 714.1108 40.3425 715.0041 41.1613 716.0462 41.9802 716.9394 42.2779 718.2049 43.1712 719.9169 43.6922 722.4478 43.99 724.1599 43.99 726.0208 43.5434 727.8073 42.4268 729.0728 41.3103 730.1149 40.0448 731.0082 40.0448 732.4225 40.4915 734.3831 41.2903 Z"
                            id="path50" class="map-province" />
                        <path
                            d="M 594.7724 14.3288 595.0949 11.9096 595.0949 9.4904 595.0949 7.555 596.5465 7.2324 597.5141 7.2324 597.5141 4.3294 597.6754 2.8778 599.2882 2.7166 600.0038 1.0434 601.198 1.6803 601.8686 1.8939 602.7268 1.1551 603.1111 1.1843 602.6878 2.107 600.9488 2.6887 600.2608 4.0762 600.1642 5.9551 600.4539 8.3085 600.7055 10.5136 601.2985 12.7295 602.1913 13.845 599.127 13.845 597.5141 14.3288 596.2239 13.845 594.7724 14.3288 Z"
                            id="path52" class="map-province" />
                        <path
                            d="M 612.6746 26.425 609.449 28.5216 608.1587 30.457 605.9008 34.1665 605.9008 36.9083 604.4493 38.1985 600.9011 38.0373 598.4818 38.3598 597.0303 38.8437 596.2239 36.747 594.4498 36.4244 595.2562 34.9729 595.74 33.5214 595.0949 32.715 594.4498 31.2634 594.9336 29.8119 593.6434 29.6506 591.5467 28.5216 589.2888 29.1668 585.7406 30.2957 583.3214 30.9409 580.7408 29.4893 579.4506 27.7152 578.8055 25.4573 580.0957 23.8445 581.2247 21.5865 584.1278 21.4252 584.6116 18.0383 584.829 18.0383 585.9019 18.0383 587.3534 18.1996 588.9662 16.9094 591.3854 15.4578 592.9983 15.9417 594.1272 17.0706 594.7724 16.1029 594.7724 14.3288 596.2239 13.845 597.5141 14.3288 599.127 13.845 602.1913 13.845 602.9977 15.1353 604.6105 16.9094 607.5136 20.2963 609.6103 22.3929 612.6746 26.425 Z"
                            id="path54" class="map-province" />
                        <path
                            d="M 594.7724 14.3288 594.7724 16.1029 594.1272 17.0706 592.9983 15.9417 591.3854 15.4578 588.9662 16.9094 587.3534 18.1996 585.9019 18.0383 584.829 18.0383 584.1278 16.4255 584.1278 13.3612 583.1601 12.716 581.8698 12.3935 581.8698 11.5871 581.8698 9.813 583.3213 8.5227 583.3777 5.7819 584.825 5.109 585.9223 5.129 587.1565 5.2724 588.3952 5.0943 589.8084 4.9895 591.5332 4.2819 592.4895 3.0691 593.8052 2.1123 594.9239 1.0419 596.4722 1.119 598.3126 1 600.0038 1.0434 599.2882 2.7166 597.6754 2.8778 597.5141 4.3294 597.5141 7.2324 596.5465 7.2324 595.0949 7.555 595.0949 9.4904 595.0949 11.9096 594.7724 14.3288 Z"
                            id="path56" class="map-province" />
                        <path
                            d="M 584.829 18.0383 584.6116 18.0383 584.1278 21.4252 581.2247 21.5865 580.0957 23.8445 578.8055 25.4573 577.3539 26.5863 576.225 26.7475 573.3219 27.3927 573.3219 28.1991 574.6121 29.328 575.4186 30.457 574.9347 33.6827 574.4509 34.4891 572.9993 33.3601 570.9027 32.0698 569.1286 32.5537 566.5961 33.1657 566.9881 31.7588 567.7733 28.7041 569.3484 25.1165 570.1145 24.4289 570.5129 22.9976 571.7506 18.2508 572.8458 13.7166 574.2488 8.7917 575.0037 7.1942 577.3147 6.9499 578.6353 6.9694 580.1304 7.3278 580.6424 8.1417 581.311 7.9413 581.9791 7.6048 583.3777 5.7819 583.3213 8.5227 581.8698 9.813 581.8698 11.5871 581.8698 12.3935 583.1601 12.716 584.1278 13.3612 584.1278 16.4255 584.829 18.0383 Z"
                            id="path58" class="map-province" />
                        <path
                            d="M 675.9156 69.1992 675.2977 69.1498 673.3623 69.3111 672.3946 71.0852 672.2334 77.2139 669.0077 78.6654 667.5562 79.4718 667.5562 83.1813 667.3949 86.8907 664.4918 87.052 665.2982 89.7938 666.911 91.5679 668.6851 93.342 668.3625 103.1801 666.1046 110.2765 663.8467 112.857 662.7177 114.9537 663.2015 117.3729 659.1695 120.1147 657.1882 119.4844 656.3818 119.3231 655.5754 116.9039 654.6077 114.646 652.6723 112.0655 651.7047 111.2591 652.6723 110.6139 653.64 108.6786 651.3821 107.8722 649.4467 109.1624 648.8016 110.4527 647.0275 109.1624 645.0921 109.485 643.8019 110.9365 642.3503 109.9688 641.5439 109.485 639.4473 110.4526 636.7055 109.6462 635.0927 108.1947 636.2217 106.2593 636.7055 102.5499 635.5765 101.4209 634.2863 99.4855 634.4476 98.1953 637.0281 96.905 638.9635 96.0986 643.1568 96.0986 643.4793 94.002 646.8662 92.7117 646.8662 91.5827 645.4147 90.4538 643.6406 89.3248 640.7376 88.5184 639.7699 87.8733 641.544 85.7766 639.9312 83.1961 639.9312 79.4866 641.8665 78.5189 642.8342 77.8738 643.3181 76.0997 645.7373 76.0997 646.705 74.9708 646.705 72.229 648.1565 71.4226 650.8983 71.7451 652.0273 71.1 653.9627 72.0677 655.898 72.7128 657.5108 71.2613 656.0593 69.971 654.9303 69.0034 654.7691 67.7131 655.5755 65.6164 654.2852 63.6811 655.0916 62.7134 656.5432 60.2942 658.156 58.6813 660.2527 58.6813 662.9944 58.8426 665.7362 57.2298 667.8329 56.1009 668.7261 55.4756 668.7683 55.9396 671.6069 56.0686 674.9615 56.8428 676.7679 57.1008 678.4452 57.6169 678.4452 58.7781 678.1872 60.8426 677.8001 63.5521 677.284 65.3584 676.7679 67.0358 675.9156 69.1992 Z"
                            id="path60" class="map-province" />
                        <path
                            d="M 696.2557 36.9828 696.2458 36.9928 696.3202 38.6304 696.3947 40.4169 694.9803 42.5756 694.5337 44.511 694.9803 45.6275 695.6503 46.893 694.6826 47.6374 693.7149 48.084 693.7893 49.0517 694.9059 50.3171 695.1292 51.4337 694.3848 52.1036 693.566 52.3269 693.4171 53.5179 693.4171 57.2398 693.4171 58.9519 693.5068 60.9245 692.2323 60.9245 691.9217 61.02 690.1356 61.5696 689.8131 63.6662 690.7808 64.9565 689.1679 66.5693 685.9423 68.0208 683.2005 70.1175 683.0392 72.2141 681.5877 72.3754 679.491 72.0529 679.3298 69.4724 675.9156 69.1992 676.7679 67.0358 677.284 65.3584 677.8001 63.5521 678.1872 60.8426 678.4452 58.7781 678.4452 57.6169 676.7679 57.1008 674.9615 56.8428 671.6069 56.0686 668.7683 55.9396 668.7261 55.4756 668.6393 54.5203 668.6393 51.9398 668.5103 48.9722 668.3813 45.6176 668.3813 43.5531 668.3813 41.3597 668.851 39.8567 669.0264 39.2953 669.9296 37.618 671.7359 35.4246 673.4133 34.3924 674.9616 34.7794 676.3809 35.2955 677.284 35.9407 679.2194 37.1019 680.2516 37.489 680.6387 38.0051 682.5741 38.6502 683.8643 38.3922 684.8965 38.7793 685.9287 38.9083 688.6383 39.5534 690.8317 38.6502 693.1541 37.489 694.8315 36.9729 696.1217 36.9729 696.2557 36.9828 Z"
                            id="path62" class="map-province" />
                        <path
                            d="M 668.851 39.8567 668.3813 41.3597 668.3813 43.5531 668.3813 45.6176 668.5103 48.9722 668.6393 51.9398 668.6393 54.5203 668.7261 55.4756 667.8329 56.1009 665.7362 57.2298 662.9944 58.8426 660.2527 58.6813 658.156 58.6813 656.5432 60.2942 655.0916 62.7134 654.2852 63.6811 653.4788 63.8423 651.3822 64.3262 649.9306 64.1649 649.6081 62.0682 648.8017 60.4554 647.834 61.1005 646.705 63.3585 644.4471 66.5841 640.5763 68.3582 635.4153 67.2292 632.0284 66.2615 628.1576 66.2615 626.061 65.2938 624.932 62.5521 624.1256 59.1651 626.8674 56.2621 629.6092 52.8752 631.222 52.3913 632.8348 53.8429 635.4153 53.0365 638.1571 49.1657 638.2885 48.9905 639.6086 47.2303 638.8022 44.166 638.4797 41.4242 638.641 40.6178 641.2215 40.9403 643.4794 40.9403 646.2212 40.7791 648.8017 39.8114 652.3499 38.8437 657.5109 36.9083 660.414 35.2955 662.5106 34.973 664.2847 36.4245 666.0588 38.0373 667.6716 39.005 668.851 39.8567 Z"
                            id="path64" class="map-province" />
                        <path
                            d="M 594.635 49.3473 597.0303 49.4882 599.9334 49.4882 602.5139 52.3913 604.6105 54.8105 606.7072 57.391 607.8362 59.649 608.0857 60.4475 608.6426 62.2295 607.8362 67.0679 607.9974 68.0356 608.4813 71.2613 608.6426 74.1643 605.7395 75.9384 602.1913 77.3899 600.2559 77.7125 597.8367 76.261 596.2239 74.3256 596.2239 72.3902 595.9013 71.2612 590.2565 66.4228 587.0308 64.6487 586.3857 65.2938 585.0955 66.2615 583.1601 65.939 581.8698 64.3261 581.386 60.2941 581.386 57.2298 581.386 54.0041 583.1601 54.0041 584.7729 53.359 585.0955 51.4236 587.0308 51.1011 587.5147 48.8431 589.4501 50.2947 592.0306 50.9398 593.4821 50.7785 594.635 49.3473 Z"
                            id="path66" class="map-province" />
                        <path
                            d="M 638.641 40.6178 638.4797 41.4242 638.8022 44.166 639.6086 47.2303 638.2885 48.9905 638.1571 49.1657 635.4153 53.0365 632.8348 53.8429 631.222 52.3913 629.6092 52.8752 626.8674 56.2621 624.1256 59.1651 620.9 60.4554 617.8356 61.5844 615.2551 61.5844 612.5133 62.2295 609.7715 62.2295 608.6426 62.2295 608.0857 60.4475 607.8362 59.649 606.7072 57.391 604.6105 54.8105 602.5139 52.3913 599.9334 49.4882 597.0303 49.4882 594.635 49.3473 594.2885 49.327 593.1595 46.2626 592.6757 44.166 592.1918 38.3598 593.4821 37.3921 594.4498 36.4244 596.2239 36.747 597.0303 38.8437 598.4818 38.3598 600.9011 38.0373 604.4493 38.1985 605.9008 36.9083 605.9008 34.1665 608.1587 30.457 609.449 28.5216 612.6746 26.425 618.9646 33.0375 621.2225 34.9729 625.7384 35.9406 632.5123 38.5211 636.383 40.1339 638.641 40.6178 Z"
                            id="path68" class="map-province" />
                        <path
                            d="M 594.4498 36.4244 593.4821 37.3921 592.1918 38.3598 592.6757 44.166 593.1595 46.2626 594.2885 49.327 594.635 49.3473 593.4821 50.7785 592.0306 50.9398 589.4501 50.2947 587.5147 48.8431 587.0308 51.1011 585.0955 51.4236 584.7729 53.359 583.1601 54.0041 581.386 54.0041 577.9991 53.1977 575.2573 53.0364 572.8381 51.9075 570.9027 51.1011 566.5481 50.9398 561.2258 50.9398 560.4194 50.1334 563.1612 43.037 565.9029 35.2955 566.5961 33.1657 569.1286 32.5537 570.9027 32.0698 572.9993 33.3601 574.4509 34.4891 574.9347 33.6827 575.4186 30.457 574.6121 29.328 573.3219 28.1991 573.3219 27.3927 576.225 26.7475 577.3539 26.5863 578.8055 25.4573 579.4506 27.7152 580.7408 29.4893 583.3214 30.9409 585.7406 30.2957 589.2888 29.1668 591.5467 28.5216 593.6434 29.6506 594.9336 29.8119 594.4498 31.2634 595.0949 32.715 595.74 33.5214 595.2562 34.9729 594.4498 36.4244 Z"
                            id="path70" class="map-province" />
                        <path
                            d="M 654.2852 63.6811 655.5755 65.6164 654.7691 67.7131 654.9303 69.0034 656.0593 69.971 657.5108 71.2613 655.898 72.7128 653.9627 72.0677 652.0273 71.1 650.8983 71.7451 648.1565 71.4226 646.705 72.229 646.705 74.9708 645.7373 76.0997 643.3181 76.0997 642.8342 77.8738 641.8665 78.5189 639.9312 79.4866 639.9312 83.1961 641.544 85.7766 639.7699 87.8733 640.7376 88.5184 643.6406 89.3248 645.4147 90.4538 646.8662 91.5827 646.8662 92.7117 643.4793 94.002 643.1568 96.0986 638.9635 96.0986 637.0281 96.905 634.4476 98.1953 634.2863 99.4855 635.5765 101.4209 632.996 101.9047 631.0607 102.066 629.2866 101.9047 627.835 100.7758 627.0286 100.1306 627.1899 98.034 627.1899 96.4212 626.7061 95.4535 625.2545 95.1309 623.9643 92.3891 623.6417 91.9053 622.3515 92.873 621.5451 93.0342 620.5774 91.2602 619.1258 91.0989 618.8033 90.1312 617.6743 88.5184 614.4487 88.5184 612.0295 89.1635 610.7392 89.0022 608.8038 87.5507 606.7072 87.8732 604.1267 89.4861 601.3849 89.8086 596.2239 90.4537 593.6434 88.1958 591.5467 85.7766 591.0629 84.0025 594.6111 82.7122 597.998 82.2284 599.9334 81.2607 600.2559 77.7125 602.1913 77.3899 605.7395 75.9384 608.6426 74.1643 608.4813 71.2613 607.9974 68.0356 607.8362 67.0679 608.6426 62.2295 609.7715 62.2295 612.5133 62.2295 615.2551 61.5844 617.8356 61.5844 620.9 60.4554 624.1256 59.1651 624.932 62.5521 626.061 65.2938 628.1576 66.2615 632.0284 66.2615 635.4153 67.2292 640.5763 68.3582 644.4471 66.5841 646.705 63.3585 647.834 61.1005 648.8017 60.4554 649.6081 62.0682 649.9306 64.1649 651.3822 64.3262 653.4788 63.8423 654.2852 63.6811 Z"
                            id="path72" class="map-province" />
                        <path
                            d="M 581.386 54.0041 581.386 57.2298 577.6765 58.52 575.2573 59.3264 576.5475 61.5844 576.5475 63.0359 576.5475 64.9713 575.7411 66.4228 576.7088 68.3582 577.6765 70.1323 575.7411 70.7774 573.4832 69.971 571.5478 69.1646 569.2899 70.4549 567.3545 72.3902 565.0966 71.4225 562.5161 72.7128 560.0968 74.4869 558.0002 75.6159 557.3551 76.5836 559.4517 79.4866 558.1615 83.6799 556.3874 87.5507 559.4517 89.6474 559.613 90.7763 557.5164 95.776 555.581 95.6148 554.6133 94.8084 553.4843 95.9373 552.5166 96.4212 551.2264 95.2922 550.42 96.5825 548.9685 97.8727 545.5815 97.5502 543.001 97.2276 541.5495 95.6148 537.5175 95.2922 540.098 90.1312 544.13 83.3574 547.5169 77.5513 551.3876 70.9387 554.9358 62.0682 557.5163 55.617 560.4194 50.1334 561.2258 50.9398 566.5481 50.9398 570.9027 51.1011 572.8381 51.9075 575.2573 53.0364 577.9991 53.1977 581.386 54.0041 Z"
                            id="path74" class="map-province" />
                        <path
                            d="M 600.2559 77.7125 599.9334 81.2607 597.998 82.2284 594.6111 82.7122 591.0629 84.0025 591.5467 85.7766 593.6434 88.1958 596.2239 90.4537 595.9013 93.6794 594.7724 97.0663 591.5467 99.0017 587.5147 98.8404 583.9665 98.8404 584.1278 97.5501 584.2891 94.9696 582.515 93.6794 580.0958 93.0342 580.0958 87.2281 580.5796 85.6153 581.8698 85.1315 582.8375 86.583 584.7729 86.583 584.7729 83.3574 583.1601 83.0348 582.3537 81.7445 581.2247 81.422 579.9345 80.7769 578.8055 78.6802 576.7088 78.6802 574.6122 79.8092 575.9024 82.2284 576.7088 84.0025 575.5799 85.2927 573.8058 85.2927 572.6768 84.4863 570.5801 84.4863 568.6448 83.5187 565.903 81.2607 564.1289 81.0994 563.3225 79.3253 559.4517 79.4866 557.3551 76.5836 558.0002 75.6159 560.0968 74.4869 562.5161 72.7128 565.0966 71.4225 567.3545 72.3902 569.2899 70.4549 571.5478 69.1646 573.4832 69.971 575.7411 70.7774 577.6765 70.1323 576.7088 68.3582 575.7411 66.4228 576.5475 64.9713 576.5475 63.0359 576.5475 61.5844 575.2573 59.3264 577.6765 58.52 581.386 57.2298 581.386 60.2941 581.8698 64.3261 583.1601 65.939 585.0955 66.2615 586.3857 65.2938 587.0308 64.6487 590.2565 66.4228 595.9013 71.2612 596.2239 72.3902 596.2239 74.3256 597.8367 76.261 600.2559 77.7125 Z"
                            id="path76" class="map-province" />
                        <path
                            d="M 657.0728 122.3726 659.1695 120.1147 663.2015 117.3729 662.7177 114.9537 663.8467 112.857 666.1046 110.2765 668.3625 103.1801 668.6851 93.342 666.911 91.5679 665.2982 89.7938 664.4918 87.052 667.3949 86.8907 667.5562 83.1813 667.5562 79.4718 669.0077 78.6654 672.2334 77.2139 672.3946 71.0852 673.3623 69.3111 675.2977 69.1498 675.9156 69.1992 679.3298 69.4724 679.491 72.0529 681.5877 72.3754 683.0392 72.2141 683.2005 70.1175 685.9423 68.0208 689.1679 66.5693 690.7808 64.9565 689.8131 63.6662 690.1356 61.5696 691.9217 61.02 692.2323 60.9245 693.5068 60.9245 694.329 60.9245 698.361 61.247 698.361 62.6986 700.1351 62.6986 702.393 63.1824 702.8769 65.1178 704.4897 65.1178 706.7476 64.634 706.7476 65.763 706.5863 67.6983 706.5863 69.3111 706.7476 70.1176 702.8768 69.9563 703.0381 73.9883 701.4253 76.4075 699.6512 78.5042 696.4256 81.5685 695.9417 83.9877 698.6835 86.7295 700.6189 88.3423 700.7801 93.1808 702.8768 95.6 703.3606 96.8903 702.5542 98.9869 700.4575 101.0836 697.0706 101.0836 696.1029 102.5351 696.1029 105.1156 698.3609 109.3089 699.6511 113.0184 700.4575 114.9538 695.1352 114.3086 690.2968 114.1473 689.1678 112.2119 687.8776 110.2766 684.4907 109.3089 682.7166 109.1476 680.9425 110.9217 676.5879 115.115 672.5558 120.1147 669.6528 123.6629 669.0076 125.7595 673.0397 126.2434 672.5558 127.8562 670.7817 129.9528 668.6851 131.7269 667.3948 132.0495 667.0722 129.7916 666.4271 128.0175 664.9756 129.469 663.3628 130.9205 661.75 130.7592 660.9436 128.9851 657.7179 128.0174 656.589 127.3723 657.0728 122.3726 Z"
                            id="path78" class="map-province" />
                        <path
                            d="M 624.4481 103.3563 625.8997 104.1627 625.4158 105.4529 624.7707 106.5819 623.3192 107.3883 621.0612 107.8721 619.771 110.1301 617.9969 112.0654 617.0292 110.7752 615.4164 109.3237 616.0615 106.5819 616.0615 105.7755 614.1261 105.7755 613.481 105.1303 612.8359 103.6788 613.481 102.066 614.61 100.7758 616.5453 100.2919 618.3194 100.2919 619.1258 101.0983 620.2548 102.066 622.0289 102.5498 624.4481 103.3563 Z"
                            id="path80" class="map-province" />
                        <path
                            d="M 627.0286 100.1306 625.4158 101.7434 624.4481 103.3563 622.0289 102.5498 620.2548 102.066 619.1258 101.0983 618.3194 100.2919 616.5453 100.2919 614.61 100.7758 613.481 102.066 612.8359 103.6788 613.481 105.1303 614.1261 105.7755 616.0615 105.7755 616.0615 106.5819 615.4164 109.3237 612.5133 109.3237 610.2554 109.6462 608.32 106.7432 607.5136 104.9691 606.7072 103.8401 605.7395 102.2273 605.7395 101.2596 605.7395 98.8404 604.4492 97.7114 603.6428 98.3565 602.9977 100.4532 600.5785 100.4532 600.0946 98.5178 600.0946 95.776 599.7721 94.1632 598.9657 92.5504 596.2239 90.4537 601.3849 89.8086 604.1267 89.4861 606.7072 87.8732 608.8038 87.5507 610.7392 89.0022 612.0295 89.1635 614.4487 88.5184 617.6743 88.5184 618.8033 90.1312 619.1258 91.0989 620.5774 91.2602 621.5451 93.0342 622.3515 92.873 623.6417 91.9053 623.9643 92.3891 625.2545 95.1309 626.7061 95.4535 627.1899 96.4212 627.1899 98.034 627.0286 100.1306 Z"
                            id="path82" class="map-province" />
                        <path
                            d="M 580.0958 93.0342 577.8378 93.3568 576.3863 95.2922 575.096 97.7114 574.1283 98.5178 570.7414 99.4855 567.9997 99.8081 565.903 97.8727 563.8063 96.7437 561.871 96.0986 557.5164 95.776 559.613 90.7763 559.4517 89.6474 556.3874 87.5507 558.1615 83.6799 559.4517 79.4866 563.3225 79.3253 564.1289 81.0994 565.903 81.2607 568.6448 83.5187 570.5801 84.4863 572.6768 84.4863 573.8058 85.2927 575.5799 85.2927 576.7088 84.0025 575.9024 82.2284 574.6122 79.8092 576.7088 78.6802 578.8055 78.6802 579.9345 80.7769 581.2247 81.422 582.3537 81.7445 583.1601 83.0348 584.7729 83.3574 584.7729 86.583 582.8375 86.583 581.8698 85.1315 580.5796 85.6153 580.0958 87.2281 580.0958 93.0342 Z"
                            id="path84" class="map-province" />
                        <path
                            d="M 606.7072 103.8401 605.0944 105.9368 604.288 106.5819 602.03 106.4206 601.0623 105.7755 600.901 107.3883 600.5785 108.5173 597.8367 108.5173 597.5141 109.3237 597.5141 111.5816 598.1593 112.5493 596.5465 114.4847 596.5465 116.42 595.74 117.7103 594.4498 117.8716 592.9983 116.7426 591.3855 117.7103 590.2565 116.9039 590.2565 114.6459 589.2888 114.1621 587.676 112.8718 585.9019 113.517 585.2568 115.1298 583.1601 115.4523 580.7409 113.3557 580.0958 111.4203 581.8699 108.8398 583.1601 107.3883 583.1601 106.5819 580.7409 104.9691 579.2894 103.6788 577.354 100.937 575.096 97.7114 576.3863 95.2922 577.8378 93.3568 580.0958 93.0342 582.515 93.6794 584.2891 94.9696 584.1278 97.5501 583.9665 98.8404 587.5147 98.8404 591.5467 99.0017 594.7724 97.0663 595.9013 93.6794 596.2239 90.4537 598.9657 92.5504 599.7721 94.1632 600.0946 95.776 600.0946 98.5178 600.5785 100.4532 602.9977 100.4532 603.6428 98.3565 604.4492 97.7114 605.7395 98.8404 605.7395 101.2596 605.7395 102.2273 606.7072 103.8401 Z"
                            id="path86" class="map-province" />
                        <path
                            d="M 545.5815 97.5502 545.9041 100.6145 544.4526 102.8724 545.5816 104.6465 546.2267 107.5496 545.7428 109.1624 547.1944 111.0978 549.291 111.2591 548.9685 113.517 548.1621 114.0008 546.8718 115.2911 545.7429 115.6137 544.13 114.9685 543.6462 113.0332 540.5818 110.7752 537.8401 110.7752 536.8724 110.2914 537.6788 107.227 536.8724 106.9045 537.1949 104.6465 535.4208 104.6466 533.9693 104.1627 532.8403 102.7112 533.6467 100.7758 535.7434 97.3889 537.5175 95.2922 541.5495 95.6148 543.001 97.2276 545.5815 97.5502 Z"
                            id="path88" class="map-province" />
                        <path
                            d="M 536.8724 106.9045 534.4531 108.1947 533.3242 109.1624 531.2275 108.5173 530.5824 106.2594 530.5824 105.1304 532.8403 102.7112 533.9693 104.1627 535.4208 104.6466 537.1949 104.6465 536.8724 106.9045 Z"
                            id="path90" class="map-province" />
                        <path
                            d="M 700.4575 114.9538 704.4895 115.5989 705.1347 117.373 703.3606 118.8245 700.1349 122.6953 696.9093 125.7596 694.6513 127.5337 694.6513 130.598 699.1672 130.9206 702.0703 132.856 704.812 135.1139 709.6505 141.8877 713.1986 146.2423 712.3922 147.5326 707.3925 148.0164 706.9086 150.2744 703.3605 150.4356 700.9412 150.4356 694.9738 152.5323 689.329 153.4999 685.4583 153.1774 681.7488 151.4033 677.7168 153.1773 675.4588 156.8868 674.0073 159.6286 667.8786 166.8862 666.1045 172.0472 662.7176 174.6277 661.9112 176.8856 661.7499 179.95 659.9758 179.7887 659.8145 177.8533 658.5243 177.0469 657.234 178.1759 655.4599 180.7564 652.3956 180.7564 650.1377 179.6274 646.1056 172.6923 643.3639 168.0151 639.3318 164.467 636.7513 164.467 633.5257 165.2734 628.8486 164.3057 627.397 164.1444 626.4294 163.338 627.0745 161.08 627.0745 158.0157 625.9455 155.2739 623.5263 153.6611 622.3514 151.7406 620.8999 148.0312 619.9322 145.7732 619.4484 143.1927 618.4807 139.9671 617.1904 138.0317 614.9325 136.2576 614.2874 135.4512 615.7389 134.4835 617.6743 131.903 619.7709 130.4515 621.3211 129.741 621.8676 130.4515 623.6417 131.0966 626.706 129 628.3189 129 629.9317 129 630.7381 127.2259 630.7381 125.2905 631.2219 124.4841 633.6411 125.6131 634.7701 125.1292 635.4152 123.839 637.8345 123.1939 637.8344 126.7421 640.0924 128.1936 643.1567 128.8387 644.6083 128.1936 646.8662 129.3226 648.1564 128.8387 648.8016 127.3872 648.8016 125.6131 649.608 124.968 651.3821 125.4518 653.1562 124.4841 657.0728 122.3726 656.589 127.3723 657.7179 128.0174 660.9436 128.9851 661.75 130.7592 663.3628 130.9205 664.9756 129.469 666.4271 128.0175 667.0722 129.7916 667.3948 132.0495 668.6851 131.7269 670.7817 129.9528 672.5558 127.8562 673.0397 126.2434 669.0076 125.7595 669.6528 123.6629 672.5558 120.1147 676.5879 115.115 680.9425 110.9217 682.7166 109.1476 684.4907 109.3089 687.8776 110.2766 689.1678 112.2119 690.2968 114.1473 695.1352 114.3086 700.4575 114.9538 Z"
                            id="path92" class="map-province" />
                        <path
                            d="M 635.5765 101.4209 636.7055 102.5499 636.2217 106.2593 635.0927 108.1947 636.7055 109.6462 639.4473 110.4526 641.5439 109.485 642.3503 109.9688 643.8019 110.9365 645.0921 109.485 647.0275 109.1624 648.8016 110.4527 649.4467 109.1624 651.3821 107.8722 653.64 108.6786 652.6723 110.6139 651.7047 111.2591 652.6723 112.0655 654.6077 114.646 655.5754 116.9039 656.3818 119.3231 657.1882 119.4844 659.1695 120.1147 657.0728 122.3726 653.1562 124.4841 651.3821 125.4518 649.608 124.968 648.8016 125.6131 648.8016 127.3872 648.1564 128.8387 646.8662 129.3226 644.6083 128.1936 643.1567 128.8387 640.0924 128.1936 637.8344 126.7421 637.8345 123.1939 635.4152 123.839 634.7701 125.1292 633.6411 125.6131 631.2219 124.4841 630.7381 125.2905 630.7381 127.2259 629.9317 129 628.3189 129 626.706 129 623.6417 131.0966 621.8676 130.4515 621.3211 129.741 620.2548 128.3549 619.771 126.2582 619.771 123.839 617.3517 122.3874 615.2551 122.5487 612.9971 122.2262 611.223 122.5487 609.9328 122.2262 608.6425 120.2908 608.4813 118.8393 610.2554 116.9039 612.1907 114.6459 611.7069 113.0331 611.5526 111.4905 611.5456 111.4203 611.4689 111.3715 609.7715 110.2913 610.2554 109.6462 612.5133 109.3237 615.4164 109.3237 617.0292 110.7752 617.9969 112.0654 619.771 110.1301 621.0612 107.8721 623.3192 107.3883 624.7707 106.5819 625.4158 105.4529 625.8997 104.1627 624.4481 103.3563 625.4158 101.7434 627.0286 100.1306 627.835 100.7758 629.2866 101.9047 631.0607 102.066 632.996 101.9047 635.5765 101.4209 Z"
                            id="path94" class="map-province" />
                        <path
                            d="M 610.2554 109.6462 609.7715 110.2913 611.4689 111.3715 611.5456 111.4203 611.5526 111.4905 611.7069 113.0331 612.1907 114.6459 610.2554 116.9039 608.4813 118.8393 607.9974 117.2264 607.8361 115.2911 607.3523 112.8718 605.2556 112.2267 605.4169 114.6459 605.4169 116.0975 603.8041 117.7103 603.8041 119.4844 605.5782 120.6133 606.5459 122.8713 606.5459 124.4841 607.3523 126.2582 610.2554 128.3548 610.9005 128.6774 610.0941 130.1289 607.191 129.4838 602.3526 127.7097 597.8367 125.7743 595.2562 125.6131 595.0949 127.3872 593.6434 130.7741 590.5791 131.4192 589.6114 131.903 588.4824 134.8061 586.3857 136.7415 585.0955 136.7415 580.2571 134.6448 577.354 133.6771 578.1604 131.7418 580.5796 129.9677 580.7409 127.5484 580.0958 123.5164 581.8699 120.2908 582.9988 118.1941 583.1601 115.4523 585.2568 115.1298 585.9019 113.517 587.676 112.8718 589.2888 114.1621 590.2565 114.6459 590.2565 116.9039 591.3855 117.7103 592.9983 116.7426 594.4498 117.8716 595.74 117.7103 596.5465 116.42 596.5465 114.4847 598.1593 112.5493 597.5141 111.5816 597.5141 109.3237 597.8367 108.5173 600.5785 108.5173 600.901 107.3883 601.0623 105.7755 602.03 106.4206 604.288 106.5819 605.0944 105.9368 606.7072 103.8401 607.5136 104.9691 608.32 106.7432 610.2554 109.6462 Z"
                            id="path96" class="map-province" />
                        <path
                            d="M 623.5263 153.6611 625.9455 155.2739 627.0745 158.0157 627.0745 161.08 626.4294 163.338 627.397 164.1444 628.8486 164.3057 633.5257 165.2734 636.7513 164.467 639.3318 164.467 643.3639 168.0151 646.1056 172.6923 650.1377 179.6274 652.3956 180.7564 655.4599 180.7564 657.234 178.1759 658.5243 177.0469 659.8145 177.8533 659.9758 179.7887 661.7499 179.95 664.4917 182.6918 665.7819 183.4982 664.9755 185.2722 664.3304 186.4012 665.1368 187.8527 668.6849 189.143 670.2978 190.9171 671.4267 193.6589 671.4267 197.0458 672.0718 200.7552 671.4267 201.7229 669.8139 202.8519 667.0721 203.1744 664.9755 202.2067 662.7175 202.8519 661.1047 203.6583 660.6209 205.7549 657.3953 206.2388 654.976 205.7549 654.4922 202.6906 653.5245 201.0778 651.2666 201.8842 648.8474 202.5293 645.9443 202.6906 644.3315 203.8195 640.622 207.529 639.0092 208.8192 635.6223 208.6579 632.0742 207.529 631.2677 208.9805 629.6549 210.5933 627.397 210.7546 625.3004 207.6902 623.365 204.3033 622.7199 202.5293 620.7845 203.6582 618.3653 204.7872 615.7848 204.7872 615.7848 203.1744 614.6558 202.6905 612.0753 202.6905 611.2689 204.3033 610.6238 205.7549 609.6561 206.7226 606.4305 206.5613 603.85 206.8838 602.8823 208.3354 603.3662 209.9482 603.0436 212.0448 600.7857 211.3997 599.3341 211.2384 599.6567 213.6576 598.8503 216.0768 598.0439 218.3348 597.3988 218.4961 596.1085 217.2058 594.4957 217.2058 592.5603 217.8509 591.7539 219.7863 590.7863 218.9799 589.1734 216.0768 588.0445 214.6253 586.4317 212.3674 585.3027 212.6899 583.3673 214.6253 580.7868 216.2381 579.8192 217.2058 578.8515 215.2704 577.5612 213.3351 575.9484 212.5287 576.1097 210.2707 578.0451 206.2387 579.3353 206.2387 583.3673 205.7549 584.1737 204.3033 585.7865 201.0777 588.0445 199.9488 591.9152 200.2713 594.3344 200.7552 595.786 200.5939 594.0119 197.8521 594.0119 196.2393 594.8183 194.4652 593.8506 193.9814 591.1088 193.3362 590.625 192.046 592.2378 188.1752 595.4634 187.6914 597.3988 187.2076 597.3988 183.6594 598.5277 183.6594 600.4631 183.6594 601.5921 179.6274 602.3985 177.3694 602.3985 173.66 602.0759 167.2087 604.979 163.9831 605.0375 163.933 606.1079 163.0154 608.3659 160.9188 610.4625 161.5639 611.9141 161.4026 612.8817 158.177 615.1397 154.4675 616.9138 153.8224 619.1717 153.4998 623.5263 153.6611 Z"
                            id="path98" class="map-province" />
                        <path
                            d="M 608.4813 118.8393 608.6425 120.2908 609.9328 122.2262 611.223 122.5487 612.9971 122.2262 615.2551 122.5487 617.3517 122.3874 619.771 123.839 619.771 126.2582 620.2548 128.3549 621.3211 129.741 619.7709 130.4515 617.6743 131.903 615.7389 134.4835 614.2874 135.4512 614.9325 136.2576 617.1904 138.0317 618.4807 139.9671 619.4484 143.1927 619.9322 145.7732 620.8999 148.0312 622.3514 151.7406 623.5263 153.6611 619.1717 153.4998 616.9138 153.8224 615.1397 154.4675 612.8817 158.177 611.9141 161.4026 610.4625 161.5639 608.3659 160.9188 606.1079 163.0154 605.0375 163.933 602.8364 161.4175 601.3849 158.9983 601.0623 155.6114 601.5462 152.7083 602.6751 150.9342 601.8687 150.4504 599.127 151.2568 596.869 151.2568 594.1272 148.0312 592.837 144.6442 592.6757 141.5799 592.3531 140.1284 590.4178 138.5156 586.3857 136.7415 588.4824 134.8061 589.6114 131.903 590.5791 131.4192 593.6434 130.7741 595.0949 127.3872 595.2562 125.6131 597.8367 125.7743 602.3526 127.7097 607.191 129.4838 610.0941 130.1289 610.9005 128.6774 610.2554 128.3548 607.3523 126.2582 606.5459 124.4841 606.5459 122.8713 605.5782 120.6133 603.8041 119.4844 603.8041 117.7103 605.4169 116.0975 605.4169 114.6459 605.2556 112.2267 607.3523 112.8718 607.8361 115.2911 607.9974 117.2264 608.4813 118.8393 Z"
                            id="path100" class="map-province" />
                        <path
                            d="M 577.354 133.6771 580.2571 134.6448 585.0955 136.7415 586.3857 136.7415 590.4178 138.5156 592.3531 140.1284 592.6757 141.5799 592.837 144.6442 594.1272 148.0312 596.869 151.2568 599.127 151.2568 601.8687 150.4504 602.6751 150.9342 601.5462 152.7083 601.0623 155.6114 601.3849 158.9983 602.8364 161.4175 605.0375 163.933 604.979 163.9831 602.0759 167.2087 602.3985 173.66 602.3985 177.3694 601.5921 179.6274 600.4631 183.6594 598.5277 183.6594 597.3988 183.6594 595.2562 185.4484 592.9983 186.416 591.5467 186.416 590.9016 184.9645 589.7727 182.7066 586.7083 182.384 586.547 178.8358 585.2568 178.9971 584.4504 180.1261 582.8376 179.6422 581.8699 179.481 580.5796 180.2874 579.7732 177.223 578.6443 175.6102 573.322 170.6105 570.9028 171.0944 568.8061 171.0944 568.3223 166.4172 568.3223 163.998 566.8707 163.0303 565.2579 164.3206 563.8064 165.127 562.0323 163.3529 562.3549 161.5788 563.1613 160.2885 564.6128 159.3208 565.903 158.5144 560.9033 155.9339 556.71 151.9019 553.3231 149.644 552.0329 148.3537 551.0802 146.9248 551.8716 146.4184 553.807 144.483 551.7103 142.7089 550.5813 141.0961 553.0005 136.2577 554.6133 132.8707 556.2262 132.8707 559.4518 133.3546 562.6774 133.8384 561.5484 137.3866 560.5808 139.8058 563 139.9671 564.7741 140.1284 566.5482 141.7412 567.9997 143.354 570.7415 142.225 573.8058 141.4186 577.0314 141.4186 579.2894 141.4186 579.7732 140.1284 579.1281 137.8704 577.354 133.6771 Z"
                            id="path102" class="map-province" />
                        <path
                            d="M 575.096 97.7114 577.354 100.937 579.2894 103.6788 580.7409 104.9691 583.1601 106.5819 583.1601 107.3883 581.8699 108.8398 580.0958 111.4203 580.7409 113.3557 583.1601 115.4523 582.9988 118.1941 581.8699 120.2908 580.0958 123.5164 580.7409 127.5484 580.5796 129.9677 578.1604 131.7418 577.354 133.6771 579.1281 137.8704 579.7732 140.1284 579.2894 141.4186 577.0314 141.4186 573.8058 141.4186 570.7415 142.225 567.9997 143.354 566.5482 141.7412 564.7741 140.1284 563 139.9671 560.5808 139.8058 561.5484 137.3866 562.6774 133.8384 559.4518 133.3546 556.2262 132.8707 554.6133 132.8707 553.0005 136.2577 550.5813 141.0961 551.7103 142.7089 553.807 144.483 551.8716 146.4184 551.0802 146.9248 547.8396 148.9989 545.7429 148.9989 543.1624 147.2248 540.7432 145.9345 533.4855 145.2894 533.5138 144.9083 533.8081 140.9348 534.937 138.1931 535.5822 136.5802 534.7758 134.161 533.9693 132.8708 535.2596 130.6128 536.3886 128.8387 536.6305 127.8711 537.3562 124.968 538.1626 119.807 538.1626 114.646 537.8401 110.7752 540.5818 110.7752 543.6462 113.0332 544.13 114.9685 545.7429 115.6137 546.8718 115.2911 548.1621 114.0008 548.9685 113.517 549.291 111.2591 547.1944 111.0978 545.7428 109.1624 546.2267 107.5496 545.5816 104.6465 544.4526 102.8724 545.9041 100.6145 545.5815 97.5502 548.9685 97.8727 550.42 96.5825 551.2264 95.2922 552.5166 96.4212 553.4843 95.9373 554.6133 94.8084 555.581 95.6148 557.5164 95.776 561.871 96.0986 563.8063 96.7437 565.903 97.8727 567.9997 99.8081 570.7414 99.4855 574.1283 98.5178 575.096 97.7114 Z"
                            id="path104" class="map-province" />
                        <path
                            d="M 618.3653 204.7872 618.204 207.2064 616.5912 208.6579 615.3009 210.1095 615.6235 211.561 613.3656 211.8835 611.2689 211.561 612.7204 213.4964 613.8494 214.7866 612.0753 216.2381 611.5915 218.3348 611.4302 219.9476 608.8497 221.0765 606.9143 219.4637 602.5598 222.2055 599.1729 224.6247 594.8183 231.5598 594.1732 233.0113 595.1408 233.8177 595.4634 234.9467 595.786 236.8821 597.8826 238.6562 600.4631 240.7528 603.0436 241.2366 606.753 240.9141 607.7207 242.5269 609.1723 242.6882 608.6884 243.9784 607.0756 247.3653 606.4305 249.6232 606.5918 252.6876 609.1723 253.3327 611.4302 252.5263 616.9137 250.7522 620.7845 249.3007 625.139 249.3007 628.2034 249.3007 631.9128 250.2684 635.1385 249.1394 638.0415 248.9781 638.5253 250.9135 636.9125 255.4294 634.8159 256.8809 631.2677 259.6227 630.6226 267.848 629.3323 268.9769 627.5582 271.7187 626.4293 275.1056 625.7841 279.2989 619.1716 285.4275 612.3979 291.5562 606.2692 296.3946 604.1725 295.7495 603.6887 294.7818 604.4951 290.9111 606.9143 290.1047 608.8497 289.137 608.6884 286.8791 607.882 285.105 605.3015 284.7824 602.8823 284.1373 602.3985 282.2019 604.0113 280.2666 601.4308 277.3635 599.6567 278.1699 598.5277 278.815 598.2052 277.8474 599.3341 274.9443 598.3665 272.6864 599.818 271.88 601.5921 271.5574 601.5921 269.4608 602.3985 267.848 601.9146 266.2352 600.9469 265.59 597.0762 265.59 593.528 265.1062 592.2378 264.6223 589.3347 264.9449 586.593 266.0739 582.8835 263.9772 580.303 262.2031 578.3676 259.4614 576.9161 258.4937 574.0131 258.8162 571.9164 261.3967 569.9811 261.558 568.207 262.5257 565.3039 264.7836 562.5622 268.3318 559.8204 270.4285 558.5301 267.5254 558.2076 265.4288 557.4012 250.7522 556.4335 248.8169 554.0143 246.5589 551.7563 243.4946 550.4661 241.2367 550.4661 237.8498 549.8209 236.8821 552.2402 235.4306 554.1755 233.6565 556.2722 231.8824 559.8203 230.9147 563.5298 229.7857 566.5941 228.9793 571.11 223.9796 573.5292 221.3991 575.142 221.2378 577.7225 221.2378 579.8192 217.2058 580.7868 216.2381 583.3673 214.6253 585.3027 212.6899 586.4317 212.3674 588.0445 214.6253 589.1734 216.0768 590.7863 218.9799 591.7539 219.7863 592.5603 217.8509 594.4957 217.2058 596.1085 217.2058 597.3988 218.4961 598.0439 218.3348 598.8503 216.0768 599.6567 213.6576 599.3341 211.2384 600.7857 211.3997 603.0436 212.0448 603.3662 209.9482 602.8823 208.3354 603.85 206.8838 606.4305 206.5613 609.6561 206.7226 610.6238 205.7549 611.2689 204.3033 612.0753 202.6905 614.6558 202.6905 615.7848 203.1744 615.7848 204.7872 618.3653 204.7872 Z"
                            id="path106" class="map-province" />
                        <path
                            d="M 551.0802 146.9248 552.0329 148.3537 553.3231 149.644 556.71 151.9019 560.9033 155.9339 565.903 158.5144 564.6128 159.3208 563.1613 160.2885 562.3549 161.5788 562.0323 163.3529 563.8064 165.127 565.2579 164.3206 566.8707 163.0303 568.3223 163.998 568.3223 166.4172 568.8061 171.0944 566.8707 173.3523 561.871 174.9651 557.3552 175.6102 554.1295 175.7715 548.0009 174.1587 544.614 173.8362 542.1948 173.6749 541.4107 176.1837 541.3883 176.2554 537.6789 176.2554 532.5179 175.6103 531.0664 175.7716 530.26 176.9005 529.2923 177.5457 524.1313 178.0295 524.1313 176.7393 523.3249 175.6103 522.0346 173.8362 525.2602 158.8371 527.3569 158.6758 528.002 157.7081 529.2922 153.6761 529.2922 147.3861 530.5825 145.7733 533.5138 144.9083 533.4855 145.2894 540.7432 145.9345 543.1624 147.2248 545.7429 148.9989 547.8396 148.9989 551.0802 146.9248 Z"
                            id="path108" class="map-province" />
                        <path
                            d="M 541.4107 176.1837 542.5173 177.5456 547.1945 182.2228 550.4201 184.642 550.9039 186.4161 550.9039 190.4481 549.2911 190.7707 546.2268 192.3835 543.8076 194.1576 544.4527 196.4155 545.5817 198.0283 547.0332 199.9637 544.1302 199.9637 542.0335 200.2862 540.4207 198.996 539.6143 198.1896 537.6789 199.1573 535.2597 199.6411 535.9048 201.7378 532.5179 201.5765 528.8085 201.0927 526.0667 201.8991 524.1313 203.3506 521.5508 202.8668 520.2606 202.3829 519.2929 193.9963 521.7121 191.7384 522.8411 188.9966 523.97 185.2871 524.4539 180.61 524.1313 178.0295 529.2923 177.5457 530.26 176.9005 531.0664 175.7716 532.5179 175.6103 537.6789 176.2554 541.3883 176.2554 541.4107 176.1837 Z"
                            id="path110" class="map-province" />
                        <path
                            d="M 597.3988 183.6594 597.3988 187.2076 595.4634 187.6914 592.2378 188.1752 590.625 192.046 591.1088 193.3362 593.8506 193.9814 594.8183 194.4652 594.0119 196.2393 594.0119 197.8521 595.786 200.5939 594.3344 200.7552 591.9152 200.2713 588.0445 199.9488 585.7865 201.0777 584.1737 204.3033 583.3673 205.7549 579.3353 206.2387 578.0451 206.2387 575.2574 206.2536 572.3543 205.6085 571.7092 203.6731 571.8705 202.3829 574.451 201.7377 575.5799 201.5765 576.0638 199.3185 575.2574 197.0606 574.1284 193.1898 573.1607 190.9319 571.2253 190.7706 569.6125 192.706 567.1933 193.3511 565.258 193.9963 564.6128 196.738 562.6775 197.3832 561.5485 196.2542 559.9357 196.4155 556.7101 198.3509 552.5168 201.2539 551.3878 201.7378 550.2588 200.4475 548.3235 198.6734 547.0332 199.9637 545.5817 198.0283 544.4527 196.4155 543.8076 194.1576 546.2268 192.3835 549.2911 190.7707 550.9039 190.4481 550.9039 186.4161 550.4201 184.642 547.1945 182.2228 542.5173 177.5456 541.4107 176.1837 542.1948 173.6749 544.614 173.8362 548.0009 174.1587 554.1295 175.7715 557.3552 175.6102 561.871 174.9651 566.8707 173.3523 568.8061 171.0944 570.9028 171.0944 573.322 170.6105 578.6443 175.6102 579.7732 177.223 580.5796 180.2874 581.8699 179.481 582.8376 179.6422 584.4504 180.1261 585.2568 178.9971 586.547 178.8358 586.7083 182.384 589.7727 182.7066 590.9016 184.9645 591.5467 186.416 592.9983 186.416 595.2562 185.4484 597.3988 183.6594 Z"
                            id="path112" class="map-province" />
                        <path
                            d="M 530.5825 145.7733 529.2922 147.3861 529.2922 153.6761 528.002 157.7081 527.3569 158.6758 525.2602 158.8371 522.0346 173.8362 523.3249 175.6103 524.1313 176.7393 524.1313 178.0295 524.4539 180.61 523.97 185.2871 522.8411 188.9966 521.7121 191.7384 519.2929 193.9963 515.4221 193.1899 509.2935 191.2546 508.8096 188.3515 507.3581 186.4162 505.9066 186.5774 501.7133 187.8677 497.8425 187.7064 496.2297 186.0936 494.6169 185.1259 492.3589 179.6424 490.101 176.5781 485.9077 175.6104 483.1659 174.804 479.4564 167.0625 478.0049 164.8046 476.2308 159.6436 476.7146 155.9341 479.7789 155.9341 481.2305 156.5792 483.0046 157.2244 484.4561 155.7728 485.9076 155.6115 487.0366 154.8051 488.0043 155.289 488.8107 157.0631 491.0686 157.063 491.7137 155.4502 491.7137 153.9987 491.875 153.3536 493.004 152.2246 493.1653 151.0956 492.1976 150.4505 492.8427 148.5151 494.9393 149.1603 497.8424 149.8054 499.2939 148.3538 499.6165 150.2892 500.9067 150.2892 501.2293 151.902 502.5196 152.3859 502.0357 154.16 503.0034 154.16 504.2937 153.031 504.9388 151.2569 508.1644 150.773 510.9062 149.9666 510.5836 146.9023 512.1964 145.1282 514.1318 146.0959 515.2608 147.3861 517.8413 147.2248 520.0992 147.0635 522.1958 145.2894 524.7763 144.483 527.5181 144.1605 528.4858 143.1928 529.4535 143.8379 530.5825 145.7733 Z"
                            id="path114" class="map-province" />
                        <path
                            d="M 514.1318 146.0959 512.1964 145.1282 510.5836 146.9023 510.9062 149.9666 508.1644 150.773 504.9388 151.2569 504.2937 153.031 503.0034 154.16 502.0357 154.16 502.5196 152.3859 501.2293 151.902 500.9067 150.2892 499.6165 150.2892 499.2939 148.3538 497.8424 149.8054 494.9393 149.1603 492.8427 148.5151 492.1976 150.4505 493.1653 151.0956 493.004 152.2246 491.875 153.3536 491.7137 153.9987 491.7137 155.4502 491.0686 157.063 488.8107 157.0631 488.0043 155.289 487.0366 154.8051 485.9076 155.6115 484.4561 155.7728 483.0046 157.2244 481.2305 156.5792 479.7789 155.9341 476.7146 155.9341 476.2307 152.5472 474.9405 152.0634 474.4566 150.4506 473.0051 148.6765 473.6502 147.8701 475.1018 147.3862 475.5856 145.1283 478.1661 145.1283 478.8112 142.7091 479.9402 143.3542 481.3917 143.6767 482.1981 141.7414 482.1981 139.6447 481.553 138.1932 481.3917 135.774 480.1014 133.3548 479.9401 132.0645 482.1981 130.9355 484.7786 130.1291 488.488 131.2581 491.3911 132.2257 493.1652 133.1934 493.1652 134.8062 491.875 135.9352 492.1975 138.0319 493.4878 139.9672 496.2296 141.7413 499.1326 142.0639 499.2939 140.7736 499.4552 138.9995 500.7454 137.8706 501.2293 135.2901 503.8098 133.9998 506.7128 133.8385 507.358 134.9675 508.4869 136.0965 510.9061 136.0964 512.0351 136.7416 511.2287 138.5157 510.7449 139.8059 511.8738 141.0962 513.3254 141.58 515.422 143.3541 514.1318 146.0959 Z"
                            id="path116" class="map-province" />
                        <path
                            d="M 578.0451 206.2387 576.1097 210.2707 575.9484 212.5287 577.5612 213.3351 578.8515 215.2704 579.8192 217.2058 577.7225 221.2378 575.142 221.2378 573.5292 221.3991 571.11 223.9796 566.5941 228.9793 563.5298 229.7857 559.8203 230.9147 556.2722 231.8824 554.1755 233.6565 552.2402 235.4306 549.8209 236.8821 547.4017 237.2047 545.1438 237.6885 542.5633 238.8175 539.3377 238.0111 536.7572 237.6885 536.5959 239.9464 535.3057 246.0751 532.7252 247.3654 526.274 247.3654 523.5322 247.3654 522.7258 245.1075 520.4679 246.2364 518.3712 246.3977 516.5971 244.3011 515.952 243.6559 516.2287 241.2516 517.8415 237.8647 518.0027 235.1229 516.0674 234.8004 514.4545 234.3166 512.3579 231.8973 510.7451 230.4458 508.6484 229.4781 509.4548 226.8976 511.3902 224.4784 513.003 223.1882 515.2609 225.9299 517.6801 225.9299 522.196 225.2848 523.4863 223.672 524.4539 221.0915 523.1637 219.3174 521.8734 218.1885 522.3573 214.9628 522.196 212.8662 520.5832 210.9308 519.1316 209.318 519.6155 205.6086 520.2606 202.3829 521.5508 202.8668 524.1313 203.3506 526.0667 201.8991 528.8085 201.0927 532.5179 201.5765 535.9048 201.7378 535.2597 199.6411 537.6789 199.1573 539.6143 198.1896 540.4207 198.996 542.0335 200.2862 544.1302 199.9637 547.0332 199.9637 548.3235 198.6734 550.2588 200.4475 551.3878 201.7378 552.5168 201.2539 556.7101 198.3509 559.9357 196.4155 561.5485 196.2542 562.6775 197.3832 564.6128 196.738 565.258 193.9963 567.1933 193.3511 569.6125 192.706 571.2253 190.7706 573.1607 190.9319 574.1284 193.1898 575.2574 197.0606 576.0638 199.3185 575.5799 201.5765 574.451 201.7377 571.8705 202.3829 571.7092 203.6731 572.3543 205.6085 575.2574 206.2536 578.0451 206.2387 Z"
                            id="path118" class="map-province" />
                        <path
                            d="M 519.2929 193.9963 520.2606 202.3829 519.6155 205.6086 519.1316 209.318 520.5832 210.9308 522.196 212.8662 522.3573 214.9628 521.8734 218.1885 523.1637 219.3174 524.4539 221.0915 523.4863 223.672 522.196 225.2848 517.6801 225.9299 515.2609 225.9299 513.003 223.1882 511.3902 224.4784 509.4548 226.8976 508.6484 229.4781 503.9713 229.3169 503.0036 230.7684 501.2295 231.091 496.7137 231.2523 496.5524 227.2202 498.0039 225.2849 498.8103 223.6721 498.8103 222.7044 497.6813 222.0593 497.8426 221.0916 498.649 220.4464 498.9716 218.0272 497.6813 213.6726 495.7459 211.576 494.4557 209.9632 493.488 207.3827 492.0364 204.8022 490.5849 202.2217 491.7139 199.4799 494.2943 195.6092 496.2297 194.3189 496.5523 193.0287 497.1974 189.9644 497.8425 187.7064 501.7133 187.8677 505.9066 186.5774 507.3581 186.4162 508.8096 188.3515 509.2935 191.2546 515.4221 193.1899 519.2929 193.9963 Z"
                            id="path120" class="map-province" />
                        <path
                            d="M 476.7146 155.9341 476.2308 159.6436 478.0049 164.8046 476.8759 167.8689 474.618 168.1915 472.5213 167.3851 470.1021 167.3851 468.0055 165.4497 468.328 164.1595 466.3926 164.1595 465.1024 163.0305 462.038 163.8369 458.9737 165.7723 458.6512 168.1915 454.6191 167.7077 452.6838 168.998 452.0386 170.2882 448.9743 170.4495 447.3615 169.9657 447.0389 172.0623 444.6197 172.0623 443.3295 170.4495 440.9103 171.9011 439.1362 172.0624 437.8459 169.4819 437.0322 167.9358 436.2331 166.4175 435.7492 165.2886 448.6517 153.3537 449.9419 149.483 451.2321 146.9025 454.2965 142.2253 456.5544 142.2253 458.6511 143.6768 460.1026 143.5155 465.2636 138.3545 466.7151 136.7417 470.5858 136.2578 473.9728 135.4514 477.5209 133.6773 479.9401 132.0645 480.1014 133.3548 481.3917 135.774 481.553 138.1932 482.1981 139.6447 482.1981 141.7414 481.3917 143.6767 479.9402 143.3542 478.8112 142.7091 478.1661 145.1283 475.5856 145.1283 475.1018 147.3862 473.6502 147.8701 473.0051 148.6765 474.4566 150.4506 474.9405 152.0634 476.2307 152.5472 476.7146 155.9341 Z"
                            id="path122" class="map-province" />
                        <path
                            d="M 476.8759 167.8689 476.3921 172.7074 474.9406 174.804 474.4567 176.0943 475.4244 177.2232 476.0696 179.965 474.7793 182.5455 468.8119 186.7388 465.9089 187.7065 463.6509 188.6742 462.1994 189.4806 461.393 192.545 457.361 193.5127 456.2823 193.7284 455.7482 193.8352 453.6515 193.8352 451.8774 191.416 451.2323 189.8032 451.2323 185.9325 450.7485 183.9971 448.9744 181.7392 443.6521 182.7069 438.0073 183.352 436.2332 182.3844 433.8139 180.1264 432.685 179.32 432.2011 177.2234 433.0075 175.6106 434.9429 174.3203 433.9752 172.8688 432.3624 170.4496 431.556 169.3206 432.8462 168.0304 435.7492 165.2886 436.2331 166.4175 437.0322 167.9358 437.8459 169.4819 439.1362 172.0624 440.9103 171.9011 443.3295 170.4495 444.6197 172.0623 447.0389 172.0623 447.3615 169.9657 448.9743 170.4495 452.0386 170.2882 452.6838 168.998 454.6191 167.7077 458.6512 168.1915 458.9737 165.7723 462.038 163.8369 465.1024 163.0305 466.3926 164.1595 468.328 164.1595 468.0055 165.4497 470.1021 167.3851 472.5213 167.3851 474.618 168.1915 476.8759 167.8689 Z"
                            id="path124" class="map-province" />
                        <path
                            d="M 497.8425 187.7064 497.1974 189.9644 496.5523 193.0287 496.2297 194.3189 494.2943 195.6092 491.7139 199.4799 490.5849 202.2217 492.0364 204.8022 493.488 207.3827 494.4557 209.9632 495.7459 211.576 497.6813 213.6726 498.9716 218.0272 498.649 220.4464 497.8426 221.0916 497.6813 222.0593 498.8103 222.7044 498.8103 223.6721 498.0039 225.2849 496.5524 227.2202 496.7137 231.2523 494.7783 231.4135 493.8106 229.8007 489.7786 229.6395 487.3594 228.0267 484.6176 228.0267 482.5209 228.0267 482.5209 226.4139 481.0694 225.7688 479.4566 224.9624 478.0051 225.93 476.8761 226.0913 476.3923 224.6398 477.5212 222.8657 477.5212 221.0916 476.8761 219.9627 475.7471 217.3822 476.2309 208.673 467.683 208.673 467.683 205.1249 466.8766 203.8346 466.3928 202.3831 464.1348 203.0282 462.3607 202.5444 460.5867 202.0605 459.9415 201.5767 459.7802 198.1898 457.8449 197.706 456.5546 195.7706 456.2823 193.7284 457.361 193.5127 461.393 192.545 462.1994 189.4806 463.6509 188.6742 465.9089 187.7065 468.8119 186.7388 474.7793 182.5455 476.0696 179.965 475.4244 177.2232 474.4567 176.0943 474.9406 174.804 476.3921 172.7074 476.8759 167.8689 478.0049 164.8046 479.4564 167.0625 483.1659 174.804 485.9077 175.6104 490.101 176.5781 492.3589 179.6424 494.6169 185.1259 496.2297 186.0936 497.8425 187.7064 Z"
                            id="path126" class="map-province" />
                        <path
                            d="M 453.6515 193.8352 450.4259 195.6093 447.6842 196.0932 445.7488 196.0932 445.265 198.0286 443.007 198.3512 440.9104 197.3835 440.9104 201.0929 438.975 203.0283 439.6202 204.6411 439.6202 206.0926 439.1363 207.2216 439.6202 209.3183 439.2976 211.0924 436.3946 212.8665 434.7818 214.9631 436.072 215.9308 434.9431 218.0275 432.3626 221.8982 431.5562 221.7369 429.7821 220.1241 431.3949 218.35 431.5562 215.2857 430.5885 214.8019 428.4918 215.6083 426.0726 215.9308 424.2985 213.0278 422.6857 212.7052 421.2342 214.6406 419.6214 216.0922 418.0086 215.9309 416.0732 216.7373 414.6217 215.9309 413.1701 214.1568 411.0735 212.8666 414.6216 209.1571 415.9119 205.7702 418.1698 201.4156 420.2664 200.4479 419.9439 197.7061 420.1051 196.0933 421.2341 193.3515 420.1051 191.9 419.1374 190.1259 421.3953 186.2552 422.6856 183.9972 420.7502 180.9329 420.5889 179.1588 422.363 178.0298 424.7822 177.2234 427.3627 173.8365 428.9755 171.0947 431.556 169.3206 432.3624 170.4496 433.9752 172.8688 434.9429 174.3203 433.0075 175.6106 432.2011 177.2234 432.685 179.32 433.8139 180.1264 436.2332 182.3844 438.0073 183.352 443.6521 182.7069 448.9744 181.7392 450.7485 183.9971 451.2323 185.9325 451.2323 189.8032 451.8774 191.416 453.6515 193.8352 Z"
                            id="path128" class="map-province" />
                        <path
                            d="M 508.6484 229.4781 510.7451 230.4458 512.3579 231.8973 514.4545 234.3166 516.0674 234.8004 518.0027 235.1229 517.8415 237.8647 516.2287 241.2516 515.952 243.6559 513.5328 243.4947 513.5328 246.8816 509.9846 248.9782 508.2105 249.6233 504.6624 249.6234 503.2108 251.3974 501.2755 252.3651 497.0822 253.1716 494.9855 254.1392 492.2438 256.2359 489.8246 256.7197 487.8892 256.7198 485.7925 258.3326 481.9218 261.2356 479.6639 262.5259 478.0511 267.8481 477.2447 270.4286 475.1481 270.9125 472.0837 269.2997 469.8258 267.6869 466.1163 269.7836 460.7941 273.0092 458.5362 273.1705 456.4395 271.8802 453.5365 271.5577 448.8593 271.0739 445.9563 271.0739 447.5232 269.1534 448.1683 268.0244 447.3619 266.8955 449.2972 266.5729 452.6841 266.4116 455.2646 266.4116 456.3936 263.6698 457.5225 261.5732 458.3289 259.6378 457.6838 257.7024 456.5548 255.7671 457.2 253.3478 458.3289 252.5414 457.8451 250.9286 457.0387 249.6384 456.5548 248.0256 458.8127 247.8643 460.103 248.6707 460.5868 247.0579 459.9417 245.1225 460.2642 243.9936 461.5545 246.574 463.006 245.2838 465.2259 244.8676 465.5865 244.7999 468.0057 244.6387 469.7798 242.8646 471.3926 241.7356 471.2313 239.9615 470.4249 237.8649 471.7152 236.5746 474.1344 235.9295 478.0051 236.252 480.4243 238.0261 481.8758 238.8325 481.8758 236.0907 482.3597 234.1554 484.6176 233.9941 488.3271 233.8328 489.456 233.5102 489.7786 229.6395 493.8106 229.8007 494.7783 231.4135 496.7137 231.2523 501.2295 231.091 503.0036 230.7684 503.9713 229.3169 508.6484 229.4781 Z"
                            id="path130" class="map-province" />
                        <path
                            d="M 458.8127 225.4463 460.1029 226.8978 460.2642 228.188 456.8773 228.9945 457.0386 230.9298 459.6191 231.4137 460.2642 233.0265 460.7481 234.3167 458.974 234.3167 458.4901 236.4134 460.5868 237.2198 462.3609 238.8326 463.4899 240.2841 464.7801 241.413 465.2259 244.8676 463.006 245.2838 461.5545 246.574 460.2642 243.9936 459.9417 245.1225 460.5868 247.0579 460.103 248.6707 458.8127 247.8643 456.5548 248.0256 457.0387 249.6384 457.8451 250.9286 458.3289 252.5414 457.2 253.3478 456.5548 255.7671 457.6838 257.7024 458.3289 259.6378 457.5225 261.5732 456.3936 263.6698 455.2646 266.4116 452.6841 266.4116 449.2972 266.5729 447.3619 266.8955 448.1683 268.0244 447.5232 269.1534 445.9563 271.0739 444.1822 272.5254 441.4404 274.1382 440.634 272.3641 440.634 269.1385 439.8276 266.8806 438.2148 265.1065 435.1505 266.8806 431.7636 268.816 430.6346 271.2352 429.8282 274.2996 426.9252 273.8157 423.6996 273.3319 421.7642 273.1706 421.8795 267.5407 423.4924 268.3471 424.7826 268.3471 424.2988 266.8956 423.0085 264.9602 421.3957 263.0248 420.2667 261.2508 418.9765 261.5733 417.2024 261.2508 415.1057 259.7993 413.9767 257.38 416.396 256.4124 416.7185 254.477 416.7185 252.5416 418.0087 252.219 418.4926 250.2837 418.4926 247.5419 417.2023 246.4129 417.2023 244.4776 419.4602 244.3163 422.5246 244.3163 423.331 242.8647 423.8148 241.8971 425.5889 240.123 423.0084 239.3166 422.202 238.6714 422.0407 237.3812 423.9761 236.2522 423.976 234.3169 424.9437 231.7364 426.8791 228.8333 428.008 227.0592 430.1047 227.8656 431.8788 228.0269 432.5239 226.0915 432.3626 221.8982 434.1367 222.382 435.1044 223.0272 436.2333 223.8336 438.4913 224.6399 439.9428 223.511 440.9105 224.3174 442.6846 223.8335 445.2651 223.6722 448.0068 223.8335 448.9745 224.9625 450.7486 225.4463 453.0065 225.7689 454.6194 225.1237 456.5547 225.6076 458.8127 225.4463 Z"
                            id="path132" class="map-province" />
                        <path
                            d="M 467.683 208.673 465.4251 209.802 463.1672 210.1246 462.3608 210.931 461.5544 214.8017 460.4254 217.8661 458.9739 219.3176 458.3288 220.9304 458.8127 225.4463 456.5547 225.6076 454.6194 225.1237 453.0065 225.7689 450.7486 225.4463 448.9745 224.9625 448.0068 223.8335 445.2651 223.6722 442.6846 223.8335 440.9105 224.3174 439.9428 223.511 438.4913 224.6399 436.2333 223.8336 435.1044 223.0272 434.1367 222.382 432.3626 221.8982 434.9431 218.0275 436.072 215.9308 434.7818 214.9631 436.3946 212.8665 439.2976 211.0924 439.6202 209.3183 439.1363 207.2216 439.6202 206.0926 439.6202 204.6411 438.975 203.0283 440.9104 201.0929 440.9104 197.3835 443.007 198.3512 445.265 198.0286 445.7488 196.0932 447.6842 196.0932 450.4259 195.6093 453.6515 193.8352 455.7482 193.8352 456.2823 193.7284 456.5546 195.7706 457.8449 197.706 459.7802 198.1898 459.9415 201.5767 460.5867 202.0605 462.3607 202.5444 464.1348 203.0282 466.3928 202.3831 466.8766 203.8346 467.683 205.1249 467.683 208.673 Z"
                            id="path134" class="map-province" />
                        <path
                            d="M 432.3626 221.8982 432.5239 226.0915 431.8788 228.0269 430.1047 227.8656 428.008 227.0592 426.8791 228.8333 424.9437 231.7364 423.976 234.3169 423.9761 236.2522 422.0407 237.3812 422.202 238.6714 423.0084 239.3166 425.5889 240.123 423.8148 241.8971 423.331 242.8647 422.5246 244.3163 419.4602 244.3163 417.2023 244.4776 417.2023 246.4129 418.4926 247.5419 418.4926 250.2837 418.0087 252.219 416.7185 252.5416 416.7185 254.477 416.396 256.4124 413.9767 257.38 415.1057 259.7993 417.2024 261.2508 418.9765 261.5733 420.2667 261.2508 421.3957 263.0248 423.0085 264.9602 424.2988 266.8956 424.7826 268.3471 423.4924 268.3471 421.8795 267.5407 421.7642 273.1706 418.8612 275.106 416.7645 271.8804 414.8291 268.9774 413.055 269.2999 408.0553 273.1707 406.6038 272.203 404.991 268.8161 401.7654 270.429 399.5075 274.461 398.056 275.2674 394.8303 274.7836 394.1852 271.0741 394.0239 268.8162 392.0885 267.8485 392.0426 263.5088 391.8813 258.8317 391.8813 255.6061 392.5264 252.5417 393.9779 248.8323 394.623 245.6067 393.1715 243.8326 391.2361 242.5423 392.0425 240.2844 396.2358 235.1234 399.4614 231.7365 400.429 230.285 400.429 223.8337 404.6223 219.963 408.9768 215.1245 411.0735 212.8666 413.1701 214.1568 414.6217 215.9309 416.0732 216.7373 418.0086 215.9309 419.6214 216.0922 421.2342 214.6406 422.6857 212.7052 424.2985 213.0278 426.0726 215.9308 428.4918 215.6083 430.5885 214.8019 431.5562 215.2857 431.3949 218.35 429.7821 220.1241 431.5562 221.7369 432.3626 221.8982 Z"
                            id="path136" class="map-province" />
                        <path
                            d="M 489.7786 229.6395 489.456 233.5102 488.3271 233.8328 484.6176 233.9941 482.3597 234.1554 481.8758 236.0907 481.8758 238.8325 480.4243 238.0261 478.0051 236.252 474.1344 235.9295 471.7152 236.5746 470.4249 237.8649 471.2313 239.9615 471.3926 241.7356 469.7798 242.8646 468.0057 244.6387 465.5865 244.7999 465.2259 244.8676 464.7801 241.413 463.4899 240.2841 462.3609 238.8326 460.5868 237.2198 458.4901 236.4134 458.974 234.3167 460.7481 234.3167 460.2642 233.0265 459.6191 231.4137 457.0386 230.9298 456.8773 228.9945 460.2642 228.188 460.1029 226.8978 458.8127 225.4463 458.3288 220.9304 458.9739 219.3176 460.4254 217.8661 461.5544 214.8017 462.3608 210.931 463.1672 210.1246 465.4251 209.802 467.683 208.673 476.2309 208.673 475.7471 217.3822 476.8761 219.9627 477.5212 221.0916 477.5212 222.8657 476.3923 224.6398 476.8761 226.0913 478.0051 225.93 479.4566 224.9624 481.0694 225.7688 482.5209 226.4139 482.5209 228.0267 484.6176 228.0267 487.3594 228.0267 489.7786 229.6395 Z"
                            id="path138" class="map-province" />
                        <path
                            d="M 536.8724 106.9045 537.6788 107.227 536.8724 110.2914 537.8401 110.7752 538.1626 114.646 538.1626 119.807 537.3562 124.968 536.6305 127.8711 533.4855 127.8711 530.5824 127.2259 530.5824 125.7744 529.6147 125.4518 528.0019 125.7744 525.744 126.4195 524.615 125.4519 523.1635 118.678 523.0022 114.9686 520.7443 114.646 518.9702 113.5171 519.9378 113.0332 523.1635 111.5817 526.0665 108.8399 530.5824 105.1304 530.5824 106.2594 531.2275 108.5173 533.3242 109.1624 534.4531 108.1947 536.8724 106.9045 Z"
                            id="path140" class="map-province" />
                        <path
                            d="M 509.2393 131.3371 508.3276 129.8177 508.0238 128.9061 507.7199 127.3867 506.3524 127.3867 505.2888 127.6906 504.5291 125.8673 506.0485 124.044 506.5043 122.9804 506.2005 122.0688 505.9318 121.1732 509.3912 118.8781 510.3028 119.6378 511.9742 121.613 512.8858 122.3727 512.1261 124.9556 510.6067 126.627 510.4548 128.2983 510.3028 130.7294 509.2393 131.3371 Z"
                            id="path142" class="map-province" />
                        <path
                            d="M 509.2393 131.3371 507.5679 131.641 506.8082 131.7929 506.7128 133.8385 503.8098 133.9998 501.2293 135.2901 499.971 134.0721 498.4516 132.7046 499.5151 131.9449 500.1229 130.5774 500.1229 128.6239 501.0345 128.7542 501.4903 128.1464 503.1617 126.627 504.5291 125.8673 505.2888 127.6906 506.3524 127.3867 507.7199 127.3867 508.0238 128.9061 508.3276 129.8177 509.2393 131.3371 Z"
                            id="path144" class="map-province" />
                        <path
                            d="M 536.6305 127.8711 536.3886 128.8387 535.2596 130.6128 533.9693 132.8708 534.7758 134.161 535.5822 136.5802 534.937 138.1931 533.8081 140.9348 533.5138 144.9083 530.5825 145.7733 529.4535 143.8379 528.4858 143.1928 527.5181 144.1605 524.7763 144.483 522.1958 145.2894 520.0992 147.0635 517.8413 147.2248 515.2608 147.3861 514.1318 146.0959 515.422 143.3541 513.3254 141.58 511.8738 141.0962 510.7449 139.8059 511.2287 138.5157 512.0351 136.7416 510.9061 136.0964 508.4869 136.0965 507.358 134.9675 506.7128 133.8385 506.8082 131.7929 507.5679 131.641 509.2393 131.3371 510.3028 130.7294 510.4548 128.2983 510.6067 126.627 512.1261 124.9556 512.8858 122.3727 511.9742 121.613 510.3028 119.6378 509.3912 118.8781 512.278 115.6873 514.1013 114.1679 515.3168 114.3199 518.9702 113.5171 520.7443 114.646 523.0022 114.9686 523.1635 118.678 524.615 125.4519 525.744 126.4195 528.0019 125.7744 529.6147 125.4518 530.5824 125.7744 530.5824 127.2259 533.4855 127.8711 536.6305 127.8711 Z"
                            id="path146" class="map-province" />
                        <path
                            d="M 504.5291 125.8673 503.1617 126.627 501.4903 128.1464 501.0345 128.7542 500.1229 128.6239 499.971 128.6022 498.6035 128.9061 497.6919 130.1216 496.0205 130.1216 494.6531 129.5139 494.0453 128.4503 493.1337 126.627 492.6778 125.7154 494.3492 125.1076 495.8686 125.1076 499.2112 125.1076 500.5787 124.4999 503.7694 122.5246 505.9318 121.1732 506.2005 122.0688 506.5043 122.9804 506.0485 124.044 504.5291 125.8673 Z"
                            id="path148" class="map-province" />
                        <path
                            d="M 500.1229 128.6239 500.1229 130.5774 499.5151 131.9449 498.4516 132.7046 499.971 134.0721 501.2293 135.2901 500.7454 137.8706 499.4552 138.9995 499.2939 140.7736 499.1326 142.0639 496.2296 141.7413 493.4878 139.9672 492.1975 138.0319 491.875 135.9352 493.1652 134.8062 493.1652 133.1934 491.3911 132.2257 488.488 131.2581 484.7786 130.1291 492.6778 125.7154 493.1337 126.627 494.0453 128.4503 494.6531 129.5139 496.0205 130.1216 497.6919 130.1216 498.6035 128.9061 499.971 128.6022 500.1229 128.6239 Z"
                            id="path150" class="map-province" />
                        <g class="map-lake" transform="matrix(0.0518931,0,0,0.05117344,401.84804,706.97479)" id="g2599"
                            class="map-lakes-group">
                            <path class="map-lake"
                                d="m 2933.519,1557.8521 c 22.2486,18.0722 -41.8048,10.2505 0,-4e-4 z m -630.7524,9.3 c 9.2615,1.8174 -6.9907,38.3065 -14.483,20.5164 5.5242,-3.9953 4.9323,-19.4126 14.483,-20.5164 z m 555.9429,25.02 c 6.1743,36.8302 -43.499,63.621 -35.9224,33.1414 -5.2848,-7.8791 32.5548,-14.9911 35.9224,-33.1414 z"
                                id="path3379" inkscape:connector-curvature="0" />
                        </g>
                    </g>
                </svg>


                <!-- Location Nodes -->
                <!-- Tangier -->
                <div class="map-node" style="top: 1%; left: 74.5%;">
                    <div class="map-tooltip">
                        <h4><?php i18n_e('home.tangier_terminal', [], 'Tangier Terminal'); ?></h4>
                        <p><?php i18n_e('home.tangier_address', [], '32 Rue de la Liberté, Iberia'); ?></p>
                        <span class="status online"><?php i18n_e('home.online'); ?></span>
                    </div>
                </div>
                <!-- Rabat -->
                <div class="map-node" style="top: 11%; left: 68.5%;">
                    <div class="map-tooltip">
                        <h4><?php i18n_e('home.rabat_showroom', [], 'Rabat Showroom'); ?></h4>
                        <p><?php i18n_e('home.rabat_address', [], 'Avenue Fal Ould Oumeir, Agdal'); ?></p>
                        <span class="status online"><?php i18n_e('home.online'); ?></span>
                    </div>
                </div>
                <!-- Casablanca -->
                <div class="map-node" style="top: 14.5%; left: 64%;">
                    <div class="map-tooltip">
                        <h4><?php i18n_e('home.casablanca_hq', [], 'Casablanca HQ'); ?></h4>
                        <p><?php i18n_e('home.casablanca_address', [], '123 Boulevard Zerktouni, Maarif'); ?></p>
                        <span class="status online"><?php i18n_e('home.online'); ?></span>
                    </div>
                </div>
                <!-- Fes -->
                <div class="map-node" style="top: 10%; left: 75.5%;">
                    <div class="map-tooltip">
                        <h4><?php i18n_e('home.fes_terminal', [], 'Fes Terminal'); ?></h4>
                        <p><?php i18n_e('home.fes_address', [], '12 Rue Atlas, Ville Nouvelle'); ?></p>
                        <span class="status online"><?php i18n_e('home.online'); ?></span>
                    </div>
                </div>
                <!-- Marrakech -->
                <div class="map-node" style="top: 27%; left: 61%;">
                    <div class="map-tooltip">
                        <h4><?php i18n_e('home.marrakech_terminal', [], 'Marrakech Terminal'); ?></h4>
                        <p><?php i18n_e('home.marrakech_address', [], '45 Avenue Mohammed V, Guéliz'); ?></p>
                        <span class="status online"><?php i18n_e('home.online'); ?></span>
                    </div>
                </div>
                <!-- Agadir -->
                <div class="map-node" style="top: 37%; left: 53.5%;">
                    <div class="map-tooltip">
                        <h4><?php i18n_e('home.agadir_terminal', [], 'Agadir Terminal'); ?></h4>
                        <p><?php i18n_e('home.agadir_address', [], 'Av. Hassan II, Talborjt'); ?></p>
                        <span class="status online"><?php i18n_e('home.online'); ?></span>
                    </div>
                </div>
                <!-- Oujda -->
                <div class="map-node" style="top: 8%; left: 92%;">
                    <div class="map-tooltip">
                        <h4><?php i18n_e('home.oujda_hub', [], 'Oujda East Hub'); ?></h4>
                        <p><?php i18n_e('home.oujda_address', [], '8 Boulevard Derfoufi, Oujda'); ?></p>
                        <span class="status online"><?php i18n_e('home.online'); ?></span>
                    </div>
                </div>
                <!-- Laayoune -->
                <div class="map-node" style="top: 57.5%; left: 28.5%;">
                    <div class="map-tooltip">
                        <h4><?php i18n_e('home.laayoune_terminal', [], 'Laayoune South Terminal'); ?></h4>
                        <p><?php i18n_e('home.laayoune_address', [], 'Avenue de la Marche Verte, Laayoune'); ?></p>
                        <span class="status online"><?php i18n_e('home.online'); ?></span>
                    </div>
                </div>
                <!-- Dakhla -->
                <div class="map-node" style="top: 80.5%; left: 9.5%;">
                    <div class="map-tooltip">
                        <h4><?php i18n_e('home.dakhla_terminal', [], 'Dakhla Sahara Point'); ?></h4>
                        <p><?php i18n_e('home.dakhla_address', [], 'Boulevard Mohammed V, Dakhla'); ?></p>
                        <span class="status online"><?php i18n_e('home.online'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FLASH DEALS SECTION -->
    <section class="flash-deals-section animate-on-scroll" id="deals" aria-label="Flash sale deals">
        <div class="flash-deals-container">
            <!-- Left Content -->
            <div class="flash-deals-content">
                <div class="flash-deals-header">
                    <span class="flash-badge">
                        <i class="fas fa-bolt"></i>
                        <?php i18n_e('home.limited_offer'); ?>
                    </span>
                    <h2 class="flash-title">
                        <?php i18n_e('home.flash_title_before'); ?> <span class="highlight-gradient">40%
                            <?php i18n_e('home.off'); ?></span><br>
                        <?php i18n_e('home.flash_title_after'); ?>
                    </h2>
                    <p class="flash-description">
                        <?php i18n_e('home.flash_desc'); ?>
                        <strong><?php i18n_e('home.offer_ends'); ?></strong>
                    </p>
                </div>

                <!-- Countdown Timer -->
                <div class="flash-countdown" aria-label="Countdown timer">
                    <div class="countdown-unit">
                        <div class="countdown-value" id="days">02</div>
                        <div class="countdown-label"><?php i18n_e('home.days'); ?></div>
                    </div>
                    <div class="countdown-separator">:</div>
                    <div class="countdown-unit">
                        <div class="countdown-value" id="hours">18</div>
                        <div class="countdown-label"><?php i18n_e('home.hours'); ?></div>
                    </div>
                    <div class="countdown-separator">:</div>
                    <div class="countdown-unit">
                        <div class="countdown-value" id="minutes">43</div>
                        <div class="countdown-label"><?php i18n_e('home.minutes'); ?></div>
                    </div>
                    <div class="countdown-separator">:</div>
                    <div class="countdown-unit">
                        <div class="countdown-value" id="seconds">42</div>
                        <div class="countdown-label"><?php i18n_e('home.seconds'); ?></div>
                    </div>
                </div>

                <div class="flash-deals-actions">
                    <a href="products.php?sale=true" class="btn btn-primary flash-cta">
                        <i class="fas fa-tags"></i>
                        <?php i18n_e('home.shop_flash_deals'); ?>
                    </a>
                    <div class="flash-stats">
                        <div class="flash-stat">
                            <i class="fas fa-fire"></i>
                            <span><strong>127</strong> <?php i18n_e('home.items_on_sale'); ?></span>
                        </div>
                        <div class="flash-stat">
                            <i class="fas fa-users"></i>
                            <span><strong>2,341</strong> <?php i18n_e('home.shoppers_viewing'); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Visual -->
            <div class="flash-deals-visual">
                <div class="discount-badge-large">
                    <div class="badge-content">
                        <span class="badge-percent">40</span>
                        <span class="badge-symbol">%</span>
                        <span class="badge-text"><?php i18n_e('home.off'); ?></span>
                    </div>
                </div>
                <div class="visual-glow"></div>
            </div>
        </div>
    </section>

    <!-- WHY CHOOSE -->
    <section class="why-choose animate-on-scroll">
        <header>
            <h2 class="text-center"><?php i18n_e('home.why_choose'); ?></h2>
            <p class="text-center"><?php i18n_e('home.why_choose_intro'); ?></p>
        </header>

        <ul class="features-grid">
            <li class="feature-card">
                <div class="feature-icon"><i class="fas fa-shipping-fast"></i></div>
                <h3><?php i18n_e('home.free_shipping'); ?></h3>
                <p><?php i18n_e('home.free_shipping_desc'); ?></p>
            </li>
            <li class="feature-card">
                <div class="feature-icon"><i class="fas fa-shield-alt"></i></div>
                <h3><?php i18n_e('home.secure_payments'); ?></h3>
                <p><?php i18n_e('home.secure_payments_desc'); ?></p>
            </li>
            <li class="feature-card">
                <div class="feature-icon"><i class="fas fa-undo"></i></div>
                <h3><?php i18n_e('home.easy_returns'); ?></h3>
                <p><?php i18n_e('home.easy_returns_desc'); ?></p>
            </li>
            <li class="feature-card">
                <div class="feature-icon"><i class="fas fa-headset"></i></div>
                <h3><?php i18n_e('home.support_247'); ?></h3>
                <p><?php i18n_e('home.support_247_desc'); ?></p>
            </li>
        </ul>
    </section>

    <!-- FAQ SECTION -->
    <section class="faq-section animate-on-scroll" id="faq">
        <div class="container">
            <header class="faq-header">
                <span class="featured-tag faq-tag"><?php i18n_e('home.help_center'); ?></span>
                <h2><?php i18n_e('home.faq_title'); ?></h2>
                <p><?php i18n_e('home.faq_desc'); ?></p>
            </header>

            <div class="faq-accordion">
                <div class="faq-item">
                    <button class="faq-question" type="button" id="faq-question-shipping" aria-expanded="false"
                        aria-controls="faq-answer-shipping">
                        <span><i class="fas fa-truck-fast"></i> <?php i18n_e('home.faq_shipping_q'); ?></span>
                        <i class="fas fa-chevron-down" aria-hidden="true"></i>
                    </button>
                    <div class="faq-answer" id="faq-answer-shipping" role="region"
                        aria-labelledby="faq-question-shipping">
                        <p><?php i18n_e('home.faq_shipping_a'); ?></p>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-question" type="button" id="faq-question-genuine" aria-expanded="false"
                        aria-controls="faq-answer-genuine">
                        <span><i class="fas fa-shield-alt"></i> <?php i18n_e('home.faq_genuine_q'); ?></span>
                        <i class="fas fa-chevron-down" aria-hidden="true"></i>
                    </button>
                    <div class="faq-answer" id="faq-answer-genuine" role="region"
                        aria-labelledby="faq-question-genuine">
                        <p><?php i18n_e('home.faq_genuine_a'); ?></p>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-question" type="button" id="faq-question-returns" aria-expanded="false"
                        aria-controls="faq-answer-returns">
                        <span><i class="fas fa-rotate-left"></i> <?php i18n_e('home.faq_returns_q'); ?></span>
                        <i class="fas fa-chevron-down" aria-hidden="true"></i>
                    </button>
                    <div class="faq-answer" id="faq-answer-returns" role="region"
                        aria-labelledby="faq-question-returns">
                        <p><?php i18n_e('home.faq_returns_a'); ?></p>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-question" type="button" id="faq-question-building" aria-expanded="false"
                        aria-controls="faq-answer-building">
                        <span><i class="fas fa-screwdriver-wrench"></i> <?php i18n_e('home.faq_building_q'); ?></span>
                        <i class="fas fa-chevron-down" aria-hidden="true"></i>
                    </button>
                    <div class="faq-answer" id="faq-answer-building" role="region"
                        aria-labelledby="faq-question-building">
                        <p><?php i18n_e('home.faq_building_a'); ?></p>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-question" type="button" id="faq-question-payment" aria-expanded="false"
                        aria-controls="faq-answer-payment">
                        <span><i class="fas fa-credit-card"></i> <?php i18n_e('home.faq_payment_q'); ?></span>
                        <i class="fas fa-chevron-down" aria-hidden="true"></i>
                    </button>
                    <div class="faq-answer" id="faq-answer-payment" role="region"
                        aria-labelledby="faq-question-payment">
                        <p><?php i18n_e('home.faq_payment_a'); ?></p>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-question" type="button" id="faq-question-support" aria-expanded="false"
                        aria-controls="faq-answer-support">
                        <span><i class="fas fa-headset"></i> <?php i18n_e('home.faq_support_q'); ?></span>
                        <i class="fas fa-chevron-down" aria-hidden="true"></i>
                    </button>
                    <div class="faq-answer" id="faq-answer-support" role="region"
                        aria-labelledby="faq-question-support">
                        <p><?php i18n_e('home.faq_support_a'); ?></p>
                    </div>
                </div>
            </div>

            <div class="faq-actions">
                <p><?php i18n_e('home.still_questions'); ?></p>
                <a href="#contact" class="btn btn-outline"><i class="fas fa-envelope"></i>
                    <?php i18n_e('home.contact_support'); ?></a>
            </div>
        </div>
    </section>

    <script>
        // FAQ Accordion functionality
        document.querySelectorAll('.faq-question').forEach(button => {
            button.addEventListener('click', () => {
                const faqItem = button.closest('.faq-item');
                const answer = faqItem ? faqItem.querySelector('.faq-answer') : null;
                const isOpen = button.getAttribute('aria-expanded') === 'true';

                // Close all other FAQs
                document.querySelectorAll('.faq-item').forEach(item => {
                    const itemQuestion = item.querySelector('.faq-question');
                    const itemAnswer = item.querySelector('.faq-answer');
                    item.classList.remove('is-open');
                    if (itemQuestion) itemQuestion.setAttribute('aria-expanded', 'false');
                    if (itemAnswer) itemAnswer.style.maxHeight = '0';
                });

                // Toggle current FAQ
                if (!isOpen && faqItem && answer) {
                    faqItem.classList.add('is-open');
                    button.setAttribute('aria-expanded', 'true');
                    answer.style.maxHeight = answer.scrollHeight + 'px';
                }
            });
        });
    </script>

    <!-- FEEDBACK SECTION -->
    <section class="feedback-section animate-on-scroll" id="feedback">
        <div class="container">
            <div class="feedback-header">
                <span class="feedback-tag"><i class="fas fa-comment-dots"></i>
                    <?php i18n_e('home.feedback_tag'); ?></span>
                <h2><?php i18n_e('home.feedback_title'); ?></h2>
                <p><?php i18n_e('home.feedback_desc'); ?></p>
            </div>

            <div class="feedback-container">
                <form class="feedback-form" id="feedbackForm">
                    <div class="feedback-grid">
                        <div class="form-group">
                            <label for="feedbackName">
                                <i class="fas fa-user"></i> <?php i18n_e('home.name'); ?>
                            </label>
                            <input type="text" id="feedbackName" name="name"
                                placeholder="<?php i18n_e('home.your_name'); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="feedbackEmail">
                                <i class="fas fa-envelope"></i> <?php i18n_e('home.email'); ?>
                            </label>
                            <input type="email" id="feedbackEmail" name="email" placeholder="your@email.com" required>
                        </div>

                        <div class="form-group full-width">
                            <label for="feedbackType">
                                <i class="fas fa-tag"></i> <?php i18n_e('home.feedback_type'); ?>
                            </label>
                            <select id="feedbackType" name="type" required>
                                <option value=""><?php i18n_e('home.select_type'); ?></option>
                                <option value="suggestion">💡 <?php i18n_e('home.suggestion'); ?></option>
                                <option value="bug">🐛 <?php i18n_e('home.bug_report'); ?></option>
                                <option value="compliment">⭐ <?php i18n_e('home.compliment'); ?></option>
                                <option value="complaint">⚠️ <?php i18n_e('home.complaint'); ?></option>
                                <option value="question">❓ <?php i18n_e('home.question'); ?></option>
                                <option value="other">📝 <?php i18n_e('home.other'); ?></option>
                            </select>
                        </div>

                        <div class="form-group full-width">
                            <label for="feedbackRating">
                                <i class="fas fa-star"></i> <?php i18n_e('home.overall_experience'); ?>
                            </label>
                            <div class="rating-input" id="ratingInput">
                                <input type="radio" name="rating" value="5" id="star5" required>
                                <label for="star5" title="<?php i18n_e('home.excellent'); ?>"><i
                                        class="fas fa-star"></i></label>
                                <input type="radio" name="rating" value="4" id="star4">
                                <label for="star4" title="<?php i18n_e('home.good'); ?>"><i
                                        class="fas fa-star"></i></label>
                                <input type="radio" name="rating" value="3" id="star3">
                                <label for="star3" title="<?php i18n_e('home.average'); ?>"><i
                                        class="fas fa-star"></i></label>
                                <input type="radio" name="rating" value="2" id="star2">
                                <label for="star2" title="<?php i18n_e('home.poor'); ?>"><i
                                        class="fas fa-star"></i></label>
                                <input type="radio" name="rating" value="1" id="star1">
                                <label for="star1" title="<?php i18n_e('home.very_poor'); ?>"><i
                                        class="fas fa-star"></i></label>
                            </div>
                        </div>

                        <div class="form-group full-width">
                            <label for="feedbackMessage">
                                <i class="fas fa-message"></i> <?php i18n_e('home.your_feedback'); ?>
                            </label>
                            <textarea id="feedbackMessage" name="message" rows="5"
                                placeholder="<?php i18n_e('home.feedback_placeholder'); ?>" required></textarea>
                        </div>
                    </div>

                    <div class="feedback-actions">
                        <button type="submit" class="btn btn-primary" id="submitFeedbackBtn">
                            <i class="fas fa-paper-plane"></i> <?php i18n_e('home.submit_feedback'); ?>
                        </button>
                        <button type="reset" class="btn btn-secondary">
                            <i class="fas fa-rotate-left"></i> <?php i18n_e('home.clear'); ?>
                        </button>
                    </div>

                    <div class="feedback-status" id="feedbackStatus"></div>
                </form>

                <div class="feedback-info">
                    <div class="info-card">
                        <i class="fas fa-lock"></i>
                        <h3><?php i18n_e('home.privacy_first'); ?></h3>
                        <p><?php i18n_e('home.privacy_first_desc'); ?></p>
                    </div>
                    <div class="info-card">
                        <i class="fas fa-clock"></i>
                        <h3><?php i18n_e('home.quick_response'); ?></h3>
                        <p><?php i18n_e('home.quick_response_desc'); ?></p>
                    </div>
                    <div class="info-card">
                        <i class="fas fa-heart"></i>
                        <h3><?php i18n_e('home.we_value_you'); ?></h3>
                        <p><?php i18n_e('home.we_value_you_desc'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- NEWSLETTER -->
    <section class="newsletter animate-on-scroll">
        <h2 class="text-center"><?php i18n_e('home.newsletter_title'); ?></h2>
        <p class="text-center"><?php i18n_e('home.newsletter_copy'); ?></p>
        <div class="newsletter-form" id="newsletterForm">
            <input type="email" placeholder="<?php i18n_e('home.email_placeholder'); ?>" required
                aria-label="<?php i18n_e('home.email_label'); ?>">
            <button type="button"><?php i18n_e('home.subscribe'); ?></button>
        </div>
    </section>

</main>

