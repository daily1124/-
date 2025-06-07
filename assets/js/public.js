/**
 * 檔案：assets/js/public.js
 * 功能：前端JavaScript
 * 
 * @package AI_SEO_Content_Generator
 */

(function($) {
    'use strict';

    // 全域設定
    const AISC_Public = {
        ajaxUrl: aisc_public.ajax_url,
        nonce: aisc_public.nonce,
        postId: null,
        startTime: Date.now()
    };

    /**
     * 1. 初始化
     */
    $(document).ready(function() {
        // 取得文章ID
        AISC_Public.postId = $('body').data('post-id') || $('article').first().attr('id')?.replace('post-', '');
        
        // 初始化各項功能
        initFAQAccordion();
        initTableOfContents();
        initReadingProgress();
        initSocialShare();
        initPerformanceTracking();
        initImageLazyLoad();
        initSmoothScroll();
        initRelatedPosts();
    });

    /**
     * 2. FAQ手風琴功能
     */
    function initFAQAccordion() {
        const $faqItems = $('.aisc-faq-item');
        if ($faqItems.length === 0) return;

        $faqItems.find('.aisc-faq-question').on('click', function() {
            const $item = $(this).parent();
            const $answer = $item.find('.aisc-faq-answer');
            const isActive = $item.hasClass('active');

            // 關閉其他項目
            $faqItems.not($item).removeClass('active');
            
            // 切換當前項目
            $item.toggleClass('active');

            // 動畫效果
            if (!isActive) {
                $answer.css('max-height', $answer[0].scrollHeight + 'px');
            } else {
                $answer.css('max-height', '0');
            }

            // 追蹤互動
            trackEvent('FAQ', isActive ? 'close' : 'open', $(this).text());
        });

        // 從URL hash開啟特定FAQ
        if (window.location.hash) {
            const $target = $(window.location.hash);
            if ($target.hasClass('aisc-faq-item')) {
                $target.find('.aisc-faq-question').trigger('click');
                $('html, body').animate({
                    scrollTop: $target.offset().top - 100
                }, 500);
            }
        }
    }

    /**
     * 3. 目錄功能
     */
    function initTableOfContents() {
        const $toc = $('.aisc-toc');
        if ($toc.length === 0) return;

        // 生成目錄
        const $headings = $('h2, h3').not('.aisc-faq-question');
        if ($headings.length < 3) {
            $toc.hide();
            return;
        }

        const $tocList = $('<ul></ul>');
        let currentH2 = null;

        $headings.each(function(index) {
            const $heading = $(this);
            const text = $heading.text();
            const id = $heading.attr('id') || 'heading-' + index;
            
            // 確保標題有ID
            $heading.attr('id', id);

            if ($heading.is('h2')) {
                currentH2 = $('<li><a href="#' + id + '">' + text + '</a></li>');
                $tocList.append(currentH2);
            } else if ($heading.is('h3') && currentH2) {
                let $subList = currentH2.find('ul');
                if ($subList.length === 0) {
                    $subList = $('<ul></ul>');
                    currentH2.append($subList);
                }
                $subList.append('<li><a href="#' + id + '">' + text + '</a></li>');
            }
        });

        $toc.append($tocList);

        // 點擊事件
        $toc.on('click', 'a', function(e) {
            e.preventDefault();
            const target = $(this).attr('href');
            smoothScrollTo(target);
            trackEvent('TOC', 'click', $(this).text());
        });

        // 滾動時高亮當前章節
        let scrollTimer;
        $(window).on('scroll', function() {
            clearTimeout(scrollTimer);
            scrollTimer = setTimeout(highlightCurrentSection, 100);
        });

        function highlightCurrentSection() {
            const scrollTop = $(window).scrollTop();
            let currentSection = null;

            $headings.each(function() {
                if ($(this).offset().top - 100 <= scrollTop) {
                    currentSection = $(this).attr('id');
                }
            });

            if (currentSection) {
                $toc.find('a').removeClass('active');
                $toc.find('a[href="#' + currentSection + '"]').addClass('active');
            }
        }
    }

    /**
     * 4. 閱讀進度條
     */
    function initReadingProgress() {
        const $article = $('article').first();
        if ($article.length === 0) return;

        // 創建進度條
        const $progressBar = $('<div class="aisc-reading-progress"><div class="aisc-reading-progress-bar"></div></div>');
        $('body').append($progressBar);

        const $bar = $progressBar.find('.aisc-reading-progress-bar');
        let isVisible = false;

        $(window).on('scroll', function() {
            const scrollTop = $(window).scrollTop();
            const articleTop = $article.offset().top;
            const articleHeight = $article.height();
            const windowHeight = $(window).height();

            // 顯示/隱藏進度條
            if (scrollTop > articleTop - windowHeight / 2) {
                if (!isVisible) {
                    $progressBar.css('opacity', '1');
                    isVisible = true;
                }
            } else {
                if (isVisible) {
                    $progressBar.css('opacity', '0');
                    isVisible = false;
                }
            }

            // 計算進度
            if (isVisible) {
                const progress = Math.min(100, Math.max(0, 
                    ((scrollTop - articleTop) / (articleHeight - windowHeight)) * 100
                ));
                $bar.css('width', progress + '%');

                // 達到特定進度時追蹤
                trackReadingMilestone(progress);
            }
        });
    }

    let trackedMilestones = [];
    function trackReadingMilestone(progress) {
        const milestones = [25, 50, 75, 100];
        
        milestones.forEach(milestone => {
            if (progress >= milestone && !trackedMilestones.includes(milestone)) {
                trackedMilestones.push(milestone);
                trackEvent('Reading', 'milestone', milestone + '%');
            }
        });
    }

    /**
     * 5. 社交分享功能
     */
    function initSocialShare() {
        const $shareButtons = $('.aisc-share-button');
        if ($shareButtons.length === 0) return;

        const pageUrl = encodeURIComponent(window.location.href);
        const pageTitle = encodeURIComponent(document.title);

        $shareButtons.each(function() {
            const $button = $(this);
            const network = $button.data('network');
            let shareUrl;

            switch (network) {
                case 'facebook':
                    shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${pageUrl}`;
                    break;
                case 'twitter':
                    shareUrl = `https://twitter.com/intent/tweet?url=${pageUrl}&text=${pageTitle}`;
                    break;
                case 'linkedin':
                    shareUrl = `https://www.linkedin.com/shareArticle?mini=true&url=${pageUrl}&title=${pageTitle}`;
                    break;
                case 'whatsapp':
                    shareUrl = `https://wa.me/?text=${pageTitle}%20${pageUrl}`;
                    break;
                case 'email':
                    shareUrl = `mailto:?subject=${pageTitle}&body=${pageUrl}`;
                    break;
            }

            if (shareUrl) {
                $button.attr('href', shareUrl);
                
                if (network !== 'email') {
                    $button.on('click', function(e) {
                        e.preventDefault();
                        window.open(shareUrl, 'share', 'width=600,height=400');
                        trackEvent('Share', network, pageTitle);
                    });
                }
            }
        });

        // 複製連結功能
        $('.aisc-copy-link').on('click', function(e) {
            e.preventDefault();
            copyToClipboard(window.location.href);
            showToast('連結已複製！');
            trackEvent('Share', 'copy_link', document.title);
        });
    }

    /**
     * 6. 效能追蹤
     */
    function initPerformanceTracking() {
        if (!AISC_Public.postId) return;

        // 頁面瀏覽追蹤
        trackPageView();

        // 滾動深度追蹤
        trackScrollDepth();

        // 停留時間追蹤
        trackTimeOnPage();

        // 點擊追蹤
        trackClicks();
    }

    function trackPageView() {
        $.post(AISC_Public.ajaxUrl, {
            action: 'aisc_track_pageview',
            nonce: AISC_Public.nonce,
            post_id: AISC_Public.postId,
            referrer: document.referrer
        });
    }

    let maxScrollDepth = 0;
    function trackScrollDepth() {
        $(window).on('scroll', debounce(function() {
            const scrollTop = $(window).scrollTop();
            const docHeight = $(document).height();
            const winHeight = $(window).height();
            const scrollPercent = (scrollTop / (docHeight - winHeight)) * 100;

            if (scrollPercent > maxScrollDepth) {
                maxScrollDepth = Math.round(scrollPercent);
            }
        }, 100));

        // 離開頁面時發送
        $(window).on('beforeunload', function() {
            if (maxScrollDepth > 0) {
                navigator.sendBeacon(AISC_Public.ajaxUrl, new URLSearchParams({
                    action: 'aisc_track_scroll_depth',
                    nonce: AISC_Public.nonce,
                    post_id: AISC_Public.postId,
                    depth: maxScrollDepth
                }));
            }
        });
    }

    function trackTimeOnPage() {
        let isActive = true;
        let activeTime = 0;
        let lastActiveTime = Date.now();

        // 監測用戶活動
        $(document).on('mousemove keypress scroll', debounce(function() {
            if (!isActive) {
                isActive = true;
                lastActiveTime = Date.now();
            }
        }, 1000));

        // 監測閒置
        setInterval(function() {
            if (isActive && Date.now() - lastActiveTime > 30000) {
                isActive = false;
                activeTime += Date.now() - lastActiveTime;
            }
        }, 5000);

        // 離開頁面時發送
        $(window).on('beforeunload', function() {
            if (isActive) {
                activeTime += Date.now() - lastActiveTime;
            }

            const totalTime = Date.now() - AISC_Public.startTime;
            const engagementRate = (activeTime / totalTime) * 100;

            navigator.sendBeacon(AISC_Public.ajaxUrl, new URLSearchParams({
                action: 'aisc_track_time_on_page',
                nonce: AISC_Public.nonce,
                post_id: AISC_Public.postId,
                total_time: Math.round(totalTime / 1000),
                active_time: Math.round(activeTime / 1000),
                engagement_rate: Math.round(engagementRate)
            }));
        });
    }

    function trackClicks() {
        // 外部連結
        $('a[href^="http"]:not([href*="' + window.location.hostname + '"])').on('click', function() {
            trackEvent('Outbound', 'click', $(this).attr('href'));
        });

        // 內部連結
        $('a[href*="' + window.location.hostname + '"]').not('.aisc-share-button').on('click', function() {
            trackEvent('Internal', 'click', $(this).attr('href'));
        });

        // CTA按鈕
        $('.cta-button, .wp-block-button__link').on('click', function() {
            trackEvent('CTA', 'click', $(this).text());
        });
    }

    /**
     * 7. 圖片延遲載入
     */
    function initImageLazyLoad() {
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver(function(entries, observer) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        const $img = $(entry.target);
                        const src = $img.data('src');
                        
                        if (src) {
                            $img.attr('src', src).removeAttr('data-src');
                            $img.on('load', function() {
                                $img.addClass('loaded');
                            });
                            observer.unobserve(entry.target);
                        }
                    }
                });
            }, {
                rootMargin: '50px 0px',
                threshold: 0.01
            });

            $('img[data-src]').each(function() {
                imageObserver.observe(this);
            });
        } else {
            // Fallback for older browsers
            $('img[data-src]').each(function() {
                $(this).attr('src', $(this).data('src')).removeAttr('data-src');
            });
        }
    }

    /**
     * 8. 平滑滾動
     */
    function initSmoothScroll() {
        $('a[href^="#"]').not('[href="#"]').on('click', function(e) {
            e.preventDefault();
            smoothScrollTo($(this).attr('href'));
        });
    }

    function smoothScrollTo(target) {
        const $target = $(target);
        if ($target.length) {
            $('html, body').animate({
                scrollTop: $target.offset().top - 80
            }, 800, 'swing');
        }
    }

    /**
     * 9. 相關文章功能
     */
    function initRelatedPosts() {
        const $relatedPosts = $('.aisc-related-posts');
        if ($relatedPosts.length === 0) return;

        // 動態載入更多相關文章
        const $loadMore = $('<button class="aisc-load-more">載入更多相關文章</button>');
        $relatedPosts.append($loadMore);

        let offset = $('.aisc-related-item').length;

        $loadMore.on('click', function() {
            const $button = $(this);
            $button.text('載入中...').prop('disabled', true);

            $.post(AISC_Public.ajaxUrl, {
                action: 'aisc_load_more_related',
                nonce: AISC_Public.nonce,
                post_id: AISC_Public.postId,
                offset: offset
            }, function(response) {
                if (response.success && response.data.posts.length > 0) {
                    const $grid = $('.aisc-related-grid');
                    
                    response.data.posts.forEach(function(post) {
                        const $item = createRelatedPostItem(post);
                        $grid.append($item);
                        $item.addClass('aisc-animate-fade-in');
                    });

                    offset += response.data.posts.length;

                    if (response.data.has_more) {
                        $button.text('載入更多相關文章').prop('disabled', false);
                    } else {
                        $button.remove();
                    }
                } else {
                    $button.remove();
                }
            });
        });
    }

    function createRelatedPostItem(post) {
        return $(`
            <div class="aisc-related-item">
                <div class="thumbnail">
                    ${post.thumbnail ? `<img src="${post.thumbnail}" alt="${post.title}">` : ''}
                </div>
                <div class="content">
                    <h4><a href="${post.url}">${post.title}</a></h4>
                    <p class="excerpt">${post.excerpt}</p>
                </div>
            </div>
        `);
    }

    /**
     * 10. 輔助函數
     */
    function trackEvent(category, action, label = null) {
        // Google Analytics tracking
        if (typeof gtag !== 'undefined') {
            gtag('event', action, {
                'event_category': category,
                'event_label': label
            });
        }

        // 內部追蹤
        $.post(AISC_Public.ajaxUrl, {
            action: 'aisc_track_event',
            nonce: AISC_Public.nonce,
            post_id: AISC_Public.postId,
            event_category: category,
            event_action: action,
            event_label: label
        });
    }

    function copyToClipboard(text) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text);
        } else {
            // Fallback
            const $temp = $('<input>');
            $('body').append($temp);
            $temp.val(text).select();
            document.execCommand('copy');
            $temp.remove();
        }
    }

    function showToast(message) {
        const $toast = $('<div class="aisc-toast">' + message + '</div>');
        $('body').append($toast);
        
        $toast.addClass('show');
        
        setTimeout(function() {
            $toast.removeClass('show');
            setTimeout(function() {
                $toast.remove();
            }, 300);
        }, 3000);
    }

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

    /**
     * 11. Schema結構化資料
     */
    function generateSchemaMarkup() {
        const $article = $('article').first();
        if ($article.length === 0) return;

        const schema = {
            "@context": "https://schema.org",
            "@type": "Article",
            "headline": document.title,
            "datePublished": $article.find('time[datetime]').attr('datetime'),
            "dateModified": $article.data('modified'),
            "author": {
                "@type": "Person",
                "name": $article.data('author')
            },
            "publisher": {
                "@type": "Organization",
                "name": $('meta[property="og:site_name"]').attr('content'),
                "logo": {
                    "@type": "ImageObject",
                    "url": $('link[rel="icon"]').attr('href')
                }
            },
            "description": $('meta[name="description"]').attr('content'),
            "image": $('meta[property="og:image"]').attr('content')
        };

        // 添加FAQ Schema
        const $faqSection = $('.aisc-faq-section');
        if ($faqSection.length > 0) {
            const faqSchema = {
                "@context": "https://schema.org",
                "@type": "FAQPage",
                "mainEntity": []
            };

            $('.aisc-faq-item').each(function() {
                const $item = $(this);
                faqSchema.mainEntity.push({
                    "@type": "Question",
                    "name": $item.find('.aisc-faq-question').text().trim(),
                    "acceptedAnswer": {
                        "@type": "Answer",
                        "text": $item.find('.aisc-faq-answer').text().trim()
                    }
                });
            });

            $('<script type="application/ld+json">' + JSON.stringify(faqSchema) + '</script>').appendTo('head');
        }

        $('<script type="application/ld+json">' + JSON.stringify(schema) + '</script>').appendTo('head');
    }

    // 生成Schema標記
    generateSchemaMarkup();

    /**
     * 12. 深色模式支援
     */
    function initDarkModeSupport() {
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)');
        
        function toggleDarkMode(e) {
            if (e.matches) {
                $('body').addClass('aisc-dark-mode');
            } else {
                $('body').removeClass('aisc-dark-mode');
            }
        }

        prefersDark.addListener(toggleDarkMode);
        toggleDarkMode(prefersDark);
    }

    initDarkModeSupport();

    /**
     * 13. 表單增強
     */
    function enhanceForms() {
        // 評論表單
        $('#commentform').on('submit', function() {
            trackEvent('Engagement', 'comment_submit', document.title);
        });

        // 搜尋表單
        $('.search-form').on('submit', function() {
            const query = $(this).find('input[type="search"]').val();
            trackEvent('Search', 'submit', query);
        });
    }

    enhanceForms();

    /**
     * 14. 效能優化
     */
    // 預先載入關鍵資源
    function preloadCriticalAssets() {
        const criticalImages = $('img').slice(0, 3);
        
        criticalImages.each(function() {
            const src = $(this).attr('src') || $(this).data('src');
            if (src) {
                const link = document.createElement('link');
                link.rel = 'preload';
                link.as = 'image';
                link.href = src;
                document.head.appendChild(link);
            }
        });
    }

    preloadCriticalAssets();

    /**
     * 15. 響應式表格
     */
    function makeTablesResponsive() {
        $('table').not('.aisc-comparison-table').each(function() {
            if (!$(this).parent().hasClass('table-responsive')) {
                $(this).wrap('<div class="table-responsive"></div>');
            }
        });
    }

    makeTablesResponsive();

})(jQuery);

// Toast樣式
const toastStyles = `
<style>
.aisc-toast {
    position: fixed;
    bottom: 20px;
    right: 20px;
    background: #333;
    color: #fff;
    padding: 15px 20px;
    border-radius: 4px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    opacity: 0;
    transform: translateY(20px);
    transition: all 0.3s ease;
    z-index: 10000;
}

.aisc-toast.show {
    opacity: 1;
    transform: translateY(0);
}

.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.table-responsive table {
    min-width: 600px;
}
</style>
`;

jQuery('head').append(toastStyles);
