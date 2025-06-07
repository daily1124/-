<?php
/**
 * 檔案：includes/modules/class-performance-tracker.php
 * 功能：效能追蹤模組
 * 
 * @package AI_SEO_Content_Generator
 * @subpackage Modules
 */

namespace AISC\Modules;

use AISC\Core\Database;
use AISC\Core\Logger;

// 防止直接訪問
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 效能追蹤類別
 * 
 * 負責追蹤和分析AI生成文章的表現數據
 */
class PerformanceTracker {
    
    /**
     * 資料庫實例
     */
    private Database $db;
    
    /**
     * 日誌實例
     */
    private Logger $logger;
    
    /**
     * Google Analytics Measurement ID
     */
    private ?string $ga_measurement_id = null;
    
    /**
     * 追蹤腳本版本
     */
    private const TRACKING_SCRIPT_VERSION = '1.0.0';
    
    /**
     * 建構函數
     */
    public function __construct() {
        $this->db = new Database();
        $this->logger = new Logger();
        
        // 獲取GA設定
        $this->ga_measurement_id = get_option('aisc_ga_measurement_id');
        
        // 初始化掛鉤
        $this->init_hooks();
    }
    
    /**
     * 1. 初始化和設定
     */
    
    /**
     * 1.1 初始化掛鉤
     */
    private function init_hooks(): void {
        // 前端追蹤腳本
        add_action('wp_head', [$this, 'inject_tracking_script']);
        add_action('wp_footer', [$this, 'inject_performance_collector']);
        
        // AJAX處理
        add_action('wp_ajax_aisc_track_performance', [$this, 'ajax_track_performance']);
        add_action('wp_ajax_nopriv_aisc_track_performance', [$this, 'ajax_track_performance']);
        
        // 定期資料收集
        add_action('aisc_collect_performance_data', [$this, 'collect_performance_data']);
        
        // 確保排程存在
        if (!wp_next_scheduled('aisc_collect_performance_data')) {
            wp_schedule_event(time(), 'hourly', 'aisc_collect_performance_data');
        }
        
        // 管理介面
        add_action('add_meta_boxes', [$this, 'add_performance_meta_box']);
        
        // 資料清理
        add_action('aisc_cleanup_performance_data', [$this, 'cleanup_old_data']);
        
        if (!wp_next_scheduled('aisc_cleanup_performance_data')) {
            wp_schedule_event(strtotime('tomorrow 03:00:00'), 'daily', 'aisc_cleanup_performance_data');
        }
    }
    
    /**
     * 2. 前端追蹤
     */
    
    /**
     * 2.1 注入追蹤腳本
     */
    public function inject_tracking_script(): void {
        if (!is_singular('post')) {
            return;
        }
        
        $post_id = get_the_ID();
        
        // 檢查是否為AI生成的文章
        if (!get_post_meta($post_id, '_aisc_generated', true)) {
            return;
        }
        
        ?>
        <script>
        (function() {
            // 性能追蹤物件
            window.AISCPerformance = {
                postId: <?php echo $post_id; ?>,
                startTime: Date.now(),
                events: [],
                metrics: {
                    scrollDepth: 0,
                    timeOnPage: 0,
                    engagementTime: 0,
                    clicks: 0,
                    shares: 0
                }
            };
            
            // 追蹤頁面載入時間
            if (window.performance && window.performance.timing) {
                window.addEventListener('load', function() {
                    var timing = window.performance.timing;
                    window.AISCPerformance.loadTime = timing.loadEventEnd - timing.navigationStart;
                });
            }
        })();
        </script>
        <?php
    }
    
    /**
     * 2.2 注入效能收集器
     */
    public function inject_performance_collector(): void {
        if (!is_singular('post') || !get_post_meta(get_the_ID(), '_aisc_generated', true)) {
            return;
        }
        
        ?>
        <script>
        (function() {
            var perf = window.AISCPerformance;
            if (!perf) return;
            
            // 滾動深度追蹤
            var scrollHandler = function() {
                var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                var docHeight = document.documentElement.scrollHeight - document.documentElement.clientHeight;
                var scrollPercent = Math.round((scrollTop / docHeight) * 100);
                
                if (scrollPercent > perf.metrics.scrollDepth) {
                    perf.metrics.scrollDepth = scrollPercent;
                    
                    // 記錄關鍵滾動點
                    if ([25, 50, 75, 100].includes(scrollPercent)) {
                        perf.events.push({
                            type: 'scroll',
                            depth: scrollPercent,
                            time: Date.now() - perf.startTime
                        });
                    }
                }
            };
            
            // 參與度時間追蹤
            var isEngaged = true;
            var lastEngagementTime = Date.now();
            
            var updateEngagementTime = function() {
                if (isEngaged) {
                    perf.metrics.engagementTime += Date.now() - lastEngagementTime;
                }
                lastEngagementTime = Date.now();
            };
            
            // 可見性變化
            document.addEventListener('visibilitychange', function() {
                isEngaged = !document.hidden;
                updateEngagementTime();
            });
            
            // 滑鼠和鍵盤活動
            var activityHandler = function() {
                if (!isEngaged) {
                    isEngaged = true;
                    updateEngagementTime();
                }
                
                // 重置閒置計時器
                clearTimeout(window.AISCIdleTimer);
                window.AISCIdleTimer = setTimeout(function() {
                    isEngaged = false;
                    updateEngagementTime();
                }, 30000); // 30秒無活動視為閒置
            };
            
            // 點擊追蹤
            var clickHandler = function(e) {
                perf.metrics.clicks++;
                
                var target = e.target;
                var data = {
                    type: 'click',
                    target: target.tagName,
                    time: Date.now() - perf.startTime
                };
                
                // 追蹤特定元素
                if (target.href) {
                    data.href = target.href;
                    data.isExternal = target.hostname !== window.location.hostname;
                }
                
                if (target.classList.contains('aisc-faq-item')) {
                    data.faqClick = true;
                }
                
                perf.events.push(data);
            };
            
            // 社交分享追蹤
            var shareHandler = function(platform) {
                perf.metrics.shares++;
                perf.events.push({
                    type: 'share',
                    platform: platform,
                    time: Date.now() - perf.startTime
                });
            };
            
            // 綁定事件
            window.addEventListener('scroll', scrollHandler, { passive: true });
            document.addEventListener('mousemove', activityHandler);
            document.addEventListener('keypress', activityHandler);
            document.addEventListener('click', clickHandler);
            
            // 監聽社交分享按鈕
            document.querySelectorAll('.share-button').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    shareHandler(this.dataset.platform || 'unknown');
                });
            });
            
            // 定期發送數據
            var sendData = function() {
                updateEngagementTime();
                perf.metrics.timeOnPage = Date.now() - perf.startTime;
                
                // 準備數據
                var data = {
                    action: 'aisc_track_performance',
                    nonce: '<?php echo wp_create_nonce('aisc_performance_tracking'); ?>',
                    post_id: perf.postId,
                    metrics: perf.metrics,
                    events: perf.events.slice(-50), // 只發送最近50個事件
                    load_time: perf.loadTime || 0
                };
                
                // 發送到伺服器
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams(data)
                }).catch(function(error) {
                    console.error('AISC Performance tracking error:', error);
                });
                
                // 清除已發送的事件
                perf.events = [];
            };
            
            // 每30秒發送一次數據
            setInterval(sendData, 30000);
            
            // 頁面卸載時發送
            window.addEventListener('beforeunload', sendData);
            
            <?php if ($this->ga_measurement_id): ?>
            // Google Analytics 整合
            if (typeof gtag !== 'undefined') {
                // 發送自訂事件
                var sendGAEvent = function(eventName, parameters) {
                    gtag('event', eventName, Object.assign({
                        custom_dimension_1: 'ai_generated',
                        post_id: perf.postId
                    }, parameters));
                };
                
                // 追蹤參與度
                setTimeout(function() {
                    sendGAEvent('engaged_reader', {
                        engagement_time: perf.metrics.engagementTime
                    });
                }, 15000);
            }
            <?php endif; ?>
        })();
        </script>
        <?php
    }
    
    /**
     * 3. AJAX處理
     */
    
    /**
     * 3.1 處理效能追蹤AJAX請求
     */
    public function ajax_track_performance(): void {
        check_ajax_referer('aisc_performance_tracking', 'nonce');
        
        $post_id = intval($_POST['post_id'] ?? 0);
        
        if (!$post_id || !get_post($post_id)) {
            wp_die('Invalid post ID');
        }
        
        // 收集數據
        $metrics = $_POST['metrics'] ?? [];
        $events = $_POST['events'] ?? [];
        $load_time = floatval($_POST['load_time'] ?? 0);
        
        // 處理和儲存數據
        $this->process_tracking_data($post_id, $metrics, $events, $load_time);
        
        wp_send_json_success(['message' => 'Data tracked successfully']);
    }
    
    /**
     * 3.2 處理追蹤數據
     */
    private function process_tracking_data(int $post_id, array $metrics, array $events, float $load_time): void {
        // 取得或建立今日記錄
        $today = current_time('Y-m-d');
        $record = $this->get_or_create_daily_record($post_id, $today);
        
        // 更新指標
        $updates = [
            'pageviews' => $record->pageviews + 1,
            'avg_time_on_page' => $this->calculate_average(
                $record->avg_time_on_page,
                $record->pageviews,
                ($metrics['timeOnPage'] ?? 0) / 1000
            ),
            'scroll_depth' => max($record->scroll_depth, $metrics['scrollDepth'] ?? 0),
            'engagement_score' => $this->calculate_engagement_score($metrics, $events)
        ];
        
        // 計算跳出率（少於10秒或滾動少於25%視為跳出）
        if (($metrics['timeOnPage'] ?? 0) < 10000 || ($metrics['scrollDepth'] ?? 0) < 25) {
            $bounce_count = intval($record->pageviews * $record->bounce_rate / 100) + 1;
            $updates['bounce_rate'] = ($bounce_count / $updates['pageviews']) * 100;
        } else {
            $bounce_count = intval($record->pageviews * $record->bounce_rate / 100);
            $updates['bounce_rate'] = ($bounce_count / $updates['pageviews']) * 100;
        }
        
        // 更新社交分享
        $share_events = array_filter($events, fn($e) => ($e['type'] ?? '') === 'share');
        $updates['social_shares'] = $record->social_shares + count($share_events);
        
        // 更新資料庫
        $this->db->update('performance', $updates, ['id' => $record->id]);
        
        // 記錄詳細事件（用於進階分析）
        $this->log_detailed_events($post_id, $events);
        
        // 更新即時統計快取
        $this->update_realtime_stats($post_id, $metrics);
    }
    
    /**
     * 4. 數據收集和分析
     */
    
    /**
     * 4.1 取得或建立每日記錄
     */
    private function get_or_create_daily_record(int $post_id, string $date): object {
        $record = $this->db->get_row('performance', [
            'post_id' => $post_id,
            'date' => $date
        ]);
        
        if (!$record) {
            // 建立新記錄
            $id = $this->db->insert('performance', [
                'post_id' => $post_id,
                'date' => $date,
                'pageviews' => 0,
                'unique_visitors' => 0,
                'avg_time_on_page' => 0,
                'bounce_rate' => 0,
                'scroll_depth' => 0,
                'social_shares' => 0,
                'engagement_score' => 0
            ]);
            
            return $this->db->get_row('performance', ['id' => $id]);
        }
        
        return $record;
    }
    
    /**
     * 4.2 計算平均值
     */
    private function calculate_average(float $current_avg, int $count, float $new_value): float {
        if ($count == 0) {
            return $new_value;
        }
        
        return (($current_avg * $count) + $new_value) / ($count + 1);
    }
    
    /**
     * 4.3 計算參與度分數
     */
    private function calculate_engagement_score(array $metrics, array $events): float {
        $score = 0;
        
        // 基於時間的分數（最高30分）
        $time_minutes = ($metrics['timeOnPage'] ?? 0) / 60000;
        $score += min(30, $time_minutes * 5);
        
        // 基於滾動深度的分數（最高30分）
        $scroll = $metrics['scrollDepth'] ?? 0;
        $score += ($scroll / 100) * 30;
        
        // 基於點擊的分數（最高20分）
        $clicks = $metrics['clicks'] ?? 0;
        $score += min(20, $clicks * 2);
        
        // 基於分享的分數（最高20分）
        $shares = $metrics['shares'] ?? 0;
        $score += min(20, $shares * 10);
        
        return min(100, round($score, 2));
    }
    
    /**
     * 4.4 記錄詳細事件
     */
    private function log_detailed_events(int $post_id, array $events): void {
        // 將事件儲存到快取或專門的事件表
        $cache_key = 'aisc_events_' . $post_id . '_' . date('Ymd');
        $existing_events = get_transient($cache_key) ?: [];
        
        $merged_events = array_merge($existing_events, $events);
        
        // 限制儲存的事件數量
        if (count($merged_events) > 1000) {
            $merged_events = array_slice($merged_events, -1000);
        }
        
        set_transient($cache_key, $merged_events, DAY_IN_SECONDS);
    }
    
    /**
     * 4.5 更新即時統計
     */
    private function update_realtime_stats(int $post_id, array $metrics): void {
        $cache_key = 'aisc_realtime_' . $post_id;
        $stats = get_transient($cache_key) ?: [
            'current_visitors' => 0,
            'last_update' => time()
        ];
        
        // 清理過期的訪客
        if (time() - $stats['last_update'] > 300) { // 5分鐘
            $stats['current_visitors'] = 0;
        }
        
        $stats['current_visitors']++;
        $stats['last_update'] = time();
        $stats['last_metrics'] = $metrics;
        
        set_transient($cache_key, $stats, 300); // 5分鐘過期
    }
    
    /**
     * 5. 定期數據收集
     */
    
    /**
     * 5.1 收集效能數據（從GA或其他來源）
     */
    public function collect_performance_data(): void {
        $this->logger->info('開始收集效能數據');
        
        // 如果有設定GA，嘗試從GA API獲取數據
        if ($this->ga_measurement_id && get_option('aisc_ga_property_id')) {
            $this->collect_from_google_analytics();
        }
        
        // 收集WordPress內部數據
        $this->collect_wordpress_metrics();
        
        // 計算並更新排名
        $this->update_performance_rankings();
        
        $this->logger->info('效能數據收集完成');
    }
    
    /**
     * 5.2 從Google Analytics收集數據
     */
    private function collect_from_google_analytics(): void {
        // 需要Google Analytics Data API整合
        // 這裡提供基本框架
        
        try {
            // 獲取認證
            $credentials = get_option('aisc_ga_credentials');
            if (!$credentials) {
                return;
            }
            
            // 這裡應該實現GA API呼叫
            // 由於需要額外的套件和設定，這裡只提供結構
            
        } catch (\Exception $e) {
            $this->logger->error('GA數據收集失敗', ['error' => $e->getMessage()]);
        }
    }
    
    /**
     * 5.3 收集WordPress指標
     */
    private function collect_wordpress_metrics(): void {
        // 收集評論數據
        $this->collect_comment_metrics();
        
        // 收集社交分享數據（如果有整合）
        $this->collect_social_metrics();
        
        // 更新搜尋流量（從referrer）
        $this->analyze_traffic_sources();
    }
    
    /**
     * 5.4 收集評論指標
     */
    private function collect_comment_metrics(): void {
        global $wpdb;
        
        // 獲取所有AI生成文章的評論統計
        $query = "
            SELECT p.ID as post_id, COUNT(c.comment_ID) as comment_count
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->comments} c ON p.ID = c.comment_post_ID
            WHERE p.post_type = 'post' 
            AND p.post_status = 'publish'
            AND EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm 
                WHERE pm.post_id = p.ID 
                AND pm.meta_key = '_aisc_generated'
            )
            AND c.comment_approved = '1'
            AND DATE(c.comment_date) = CURDATE()
            GROUP BY p.ID
        ";
        
        $results = $wpdb->get_results($query);
        
        foreach ($results as $row) {
            // 更新評論數據
            $this->update_metric($row->post_id, 'comments', $row->comment_count);
        }
    }
    
    /**
     * 6. 報告和分析
     */
    
    /**
     * 6.1 獲取文章效能報告
     */
    public function get_post_performance(int $post_id, string $period = 'all'): array {
        $where = ['post_id' => $post_id];
        
        // 設定日期範圍
        switch ($period) {
            case 'today':
                $where['date'] = current_time('Y-m-d');
                break;
            case 'week':
                // 使用自訂查詢
                return $this->get_post_performance_range($post_id, '-7 days');
            case 'month':
                return $this->get_post_performance_range($post_id, '-30 days');
            case 'all':
                // 不限制日期
                break;
        }
        
        $records = $this->db->get_results('performance', $where, [
            'orderby' => 'date',
            'order' => 'DESC'
        ]);
        
        return $this->aggregate_performance_data($records);
    }
    
    /**
     * 6.2 獲取日期範圍效能
     */
    private function get_post_performance_range(int $post_id, string $range): array {
        global $wpdb;
        $table_name = $this->db->get_table_name('performance');
        
        $query = $wpdb->prepare(
            "SELECT * FROM $table_name 
            WHERE post_id = %d 
            AND date >= %s 
            ORDER BY date DESC",
            $post_id,
            date('Y-m-d', strtotime($range))
        );
        
        $records = $wpdb->get_results($query);
        
        return $this->aggregate_performance_data($records);
    }
    
    /**
     * 6.3 聚合效能數據
     */
    private function aggregate_performance_data(array $records): array {
        if (empty($records)) {
            return [
                'total_pageviews' => 0,
                'unique_visitors' => 0,
                'avg_time_on_page' => 0,
                'bounce_rate' => 0,
                'avg_scroll_depth' => 0,
                'total_shares' => 0,
                'engagement_score' => 0,
                'daily_data' => []
            ];
        }
        
        $aggregated = [
            'total_pageviews' => 0,
            'unique_visitors' => 0,
            'total_time' => 0,
            'total_bounces' => 0,
            'total_scroll' => 0,
            'total_shares' => 0,
            'total_engagement' => 0,
            'daily_data' => []
        ];
        
        foreach ($records as $record) {
            $aggregated['total_pageviews'] += $record->pageviews;
            $aggregated['unique_visitors'] += $record->unique_visitors;
            $aggregated['total_time'] += $record->avg_time_on_page * $record->pageviews;
            $aggregated['total_bounces'] += ($record->bounce_rate / 100) * $record->pageviews;
            $aggregated['total_scroll'] += $record->scroll_depth * $record->pageviews;
            $aggregated['total_shares'] += $record->social_shares;
            $aggregated['total_engagement'] += $record->engagement_score * $record->pageviews;
            
            $aggregated['daily_data'][] = [
                'date' => $record->date,
                'pageviews' => $record->pageviews,
                'engagement' => $record->engagement_score
            ];
        }
        
        $total_views = $aggregated['total_pageviews'] ?: 1;
        
        return [
            'total_pageviews' => $aggregated['total_pageviews'],
            'unique_visitors' => $aggregated['unique_visitors'],
            'avg_time_on_page' => round($aggregated['total_time'] / $total_views, 2),
            'bounce_rate' => round(($aggregated['total_bounces'] / $total_views) * 100, 2),
            'avg_scroll_depth' => round($aggregated['total_scroll'] / $total_views, 2),
            'total_shares' => $aggregated['total_shares'],
            'engagement_score' => round($aggregated['total_engagement'] / $total_views, 2),
            'daily_data' => $aggregated['daily_data']
        ];
    }
    
    /**
     * 6.4 獲取最佳表現文章
     */
    public function get_top_performing_posts(int $limit = 10, string $metric = 'pageviews'): array {
        global $wpdb;
        $table_name = $this->db->get_table_name('performance');
        
        $valid_metrics = [
            'pageviews' => 'SUM(pageviews)',
            'engagement' => 'AVG(engagement_score)',
            'time' => 'AVG(avg_time_on_page)',
            'shares' => 'SUM(social_shares)'
        ];
        
        $order_by = $valid_metrics[$metric] ?? 'SUM(pageviews)';
        
        $query = $wpdb->prepare(
            "SELECT 
                post_id,
                SUM(pageviews) as total_views,
                AVG(engagement_score) as avg_engagement,
                AVG(avg_time_on_page) as avg_time,
                SUM(social_shares) as total_shares,
                MAX(date) as last_tracked
            FROM $table_name
            WHERE date >= %s
            GROUP BY post_id
            ORDER BY $order_by DESC
            LIMIT %d",
            date('Y-m-d', strtotime('-30 days')),
            $limit
        );
        
        $results = $wpdb->get_results($query);
        
        $posts = [];
        foreach ($results as $row) {
            $post = get_post($row->post_id);
            if ($post) {
                $posts[] = [
                    'post_id' => $row->post_id,
                    'title' => $post->post_title,
                    'url' => get_permalink($row->post_id),
                    'pageviews' => intval($row->total_views),
                    'engagement' => round(floatval($row->avg_engagement), 2),
                    'avg_time' => round(floatval($row->avg_time), 2),
                    'shares' => intval($row->total_shares),
                    'keyword' => get_post_meta($row->post_id, '_aisc_keyword', true),
                    'published' => $post->post_date,
                    'last_tracked' => $row->last_tracked
                ];
            }
        }
        
        return $posts;
    }
    
    /**
     * 6.5 比較效能
     */
    public function compare_performance(array $post_ids, string $period = 'month'): array {
        $comparison = [];
        
        foreach ($post_ids as $post_id) {
            $performance = $this->get_post_performance($post_id, $period);
            $post = get_post($post_id);
            
            if ($post) {
                $comparison[] = [
                    'post_id' => $post_id,
                    'title' => $post->post_title,
                    'performance' => $performance,
                    'cost' => floatval(get_post_meta($post_id, '_aisc_cost', true)),
                    'roi' => $this->calculate_roi($post_id, $performance)
                ];
            }
        }
        
        // 排序by ROI
        usort($comparison, function($a, $b) {
            return $b['roi'] <=> $a['roi'];
        });
        
        return $comparison;
    }
    
    /**
     * 7. ROI計算
     */
    
    /**
     * 7.1 計算投資報酬率
     */
    private function calculate_roi(int $post_id, array $performance): float {
        // 獲取成本
        $cost = floatval(get_post_meta($post_id, '_aisc_cost', true));
        
        if ($cost <= 0) {
            return 0;
        }
        
        // 計算價值（基於流量和參與度）
        $value = $this->calculate_content_value($performance);
        
        // ROI = (價值 - 成本) / 成本 * 100
        return round((($value - $cost) / $cost) * 100, 2);
    }
    
    /**
     * 7.2 計算內容價值
     */
    private function calculate_content_value(array $performance): float {
        // 基礎價值設定（可調整）
        $values = [
            'pageview' => 0.5,      // 每個瀏覽價值 NT$0.5
            'engaged_minute' => 2,   // 每分鐘參與價值 NT$2
            'share' => 10,          // 每次分享價值 NT$10
            'engagement_point' => 0.3 // 每個參與分數價值 NT$0.3
        ];
        
        $value = 0;
        
        // 瀏覽價值
        $value += $performance['total_pageviews'] * $values['pageview'];
        
        // 參與時間價值
        $total_minutes = ($performance['total_pageviews'] * $performance['avg_time_on_page']) / 60;
        $value += $total_minutes * $values['engaged_minute'];
        
        // 社交分享價值
        $value += $performance['total_shares'] * $values['share'];
        
        // 參與度價值
        $value += $performance['engagement_score'] * $values['engagement_point'];
        
        return round($value, 2);
    }
    
    /**
     * 8. 趨勢分析
     */
    
    /**
     * 8.1 分析效能趨勢
     */
    public function analyze_trends(int $post_id, int $days = 30): array {
        global $wpdb;
        $table_name = $this->db->get_table_name('performance');
        
        $query = $wpdb->prepare(
            "SELECT * FROM $table_name 
            WHERE post_id = %d 
            AND date >= %s 
            ORDER BY date ASC",
            $post_id,
            date('Y-m-d', strtotime("-$days days"))
        );
        
        $data = $wpdb->get_results($query);
        
        if (count($data) < 7) {
            return ['status' => 'insufficient_data'];
        }
        
        // 計算趨勢
        $trend = $this->calculate_trend($data);
        
        // 預測未來表現
        $forecast = $this->forecast_performance($data);
        
        // 識別模式
        $patterns = $this->identify_patterns($data);
        
        return [
            'status' => 'success',
            'trend' => $trend,
            'forecast' => $forecast,
            'patterns' => $patterns,
            'recommendations' => $this->generate_recommendations($trend, $patterns)
        ];
    }
    
    /**
     * 8.2 計算趨勢
     */
    private function calculate_trend(array $data): array {
        $pageviews = array_column($data, 'pageviews');
        $engagement = array_column($data, 'engagement_score');
        
        // 簡單線性回歸
        $pageview_trend = $this->linear_regression($pageviews);
        $engagement_trend = $this->linear_regression($engagement);
        
        return [
            'pageviews' => [
                'direction' => $pageview_trend['slope'] > 0 ? 'up' : 'down',
                'strength' => abs($pageview_trend['slope']),
                'r_squared' => $pageview_trend['r_squared']
            ],
            'engagement' => [
                'direction' => $engagement_trend['slope'] > 0 ? 'up' : 'down',
                'strength' => abs($engagement_trend['slope']),
                'r_squared' => $engagement_trend['r_squared']
            ]
        ];
    }
    
    /**
     * 8.3 線性回歸
     */
    private function linear_regression(array $y): array {
        $n = count($y);
        if ($n < 2) {
            return ['slope' => 0, 'intercept' => 0, 'r_squared' => 0];
        }
        
        $x = range(0, $n - 1);
        
        $x_mean = array_sum($x) / $n;
        $y_mean = array_sum($y) / $n;
        
        $num = 0;
        $den = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $num += ($x[$i] - $x_mean) * ($y[$i] - $y_mean);
            $den += ($x[$i] - $x_mean) ** 2;
        }
        
        $slope = $den != 0 ? $num / $den : 0;
        $intercept = $y_mean - $slope * $x_mean;
        
        // 計算R²
        $ss_tot = 0;
        $ss_res = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $y_pred = $slope * $x[$i] + $intercept;
            $ss_tot += ($y[$i] - $y_mean) ** 2;
            $ss_res += ($y[$i] - $y_pred) ** 2;
        }
        
        $r_squared = $ss_tot != 0 ? 1 - ($ss_res / $ss_tot) : 0;
        
        return [
            'slope' => $slope,
            'intercept' => $intercept,
            'r_squared' => $r_squared
        ];
    }
    
    /**
     * 9. 管理介面整合
     */
    
    /**
     * 9.1 添加效能meta box
     */
    public function add_performance_meta_box(): void {
        add_meta_box(
            'aisc_performance_metrics',
            __('AI內容效能指標', 'ai-seo-content-generator'),
            [$this, 'render_performance_meta_box'],
            'post',
            'side',
            'default'
        );
    }
    
    /**
     * 9.2 渲染效能meta box
     */
    public function render_performance_meta_box(\WP_Post $post): void {
        // 檢查是否為AI生成
        if (!get_post_meta($post->ID, '_aisc_generated', true)) {
            echo '<p>此文章非AI生成</p>';
            return;
        }
        
        // 獲取效能數據
        $performance = $this->get_post_performance($post->ID, 'all');
        $realtime = get_transient('aisc_realtime_' . $post->ID);
        
        ?>
        <div class="aisc-performance-widget">
            <?php if ($realtime && time() - $realtime['last_update'] < 300): ?>
            <div class="realtime-visitors">
                <span class="dashicons dashicons-visibility"></span>
                <strong><?php echo $realtime['current_visitors']; ?></strong> 目前在線
            </div>
            <?php endif; ?>
            
            <table class="aisc-metrics-table">
                <tr>
                    <td>總瀏覽數</td>
                    <td><strong><?php echo number_format($performance['total_pageviews']); ?></strong></td>
                </tr>
                <tr>
                    <td>平均停留</td>
                    <td><strong><?php echo gmdate('i:s', $performance['avg_time_on_page']); ?></strong></td>
                </tr>
                <tr>
                    <td>跳出率</td>
                    <td><strong><?php echo $performance['bounce_rate']; ?>%</strong></td>
                </tr>
                <tr>
                    <td>參與度</td>
                    <td>
                        <div class="engagement-bar">
                            <div class="engagement-fill" style="width: <?php echo $performance['engagement_score']; ?>%"></div>
                        </div>
                        <small><?php echo $performance['engagement_score']; ?>/100</small>
                    </td>
                </tr>
                <tr>
                    <td>社交分享</td>
                    <td><strong><?php echo $performance['total_shares']; ?></strong></td>
                </tr>
            </table>
            
            <p class="aisc-view-details">
                <a href="<?php echo admin_url('admin.php?page=aisc-analytics&post_id=' . $post->ID); ?>" class="button">
                    查看詳細分析
                </a>
            </p>
        </div>
        
        <style>
        .aisc-performance-widget { padding: 10px 0; }
        .realtime-visitors { 
            background: #f0f8ff; 
            padding: 8px; 
            margin-bottom: 10px; 
            border-radius: 3px;
            text-align: center;
        }
        .aisc-metrics-table { width: 100%; }
        .aisc-metrics-table td { padding: 5px 0; }
        .engagement-bar {
            background: #e0e0e0;
            height: 10px;
            border-radius: 5px;
            overflow: hidden;
            margin: 5px 0;
        }
        .engagement-fill {
            background: #4caf50;
            height: 100%;
            transition: width 0.3s;
        }
        .aisc-view-details { margin-top: 10px; text-align: center; }
        </style>
        <?php
    }
    
    /**
     * 10. 資料清理
     */
    
    /**
     * 10.1 清理舊數據
     */
    public function cleanup_old_data(): void {
        $retention_days = intval(get_option('aisc_performance_retention', 90));
        
        global $wpdb;
        $table_name = $this->db->get_table_name('performance');
        
        $query = $wpdb->prepare(
            "DELETE FROM $table_name WHERE date < %s",
            date('Y-m-d', strtotime("-$retention_days days"))
        );
        
        $deleted = $wpdb->query($query);
        
        if ($deleted > 0) {
            $this->logger->info('清理舊效能數據', ['deleted' => $deleted]);
        }
        
        // 清理過期的事件快取
        $this->cleanup_event_cache();
    }
    
    /**
     * 10.2 清理事件快取
     */
    private function cleanup_event_cache(): void {
        global $wpdb;
        
        $query = "DELETE FROM {$wpdb->options} 
                 WHERE option_name LIKE '_transient_aisc_events_%' 
                 AND option_value < %s";
        
        $wpdb->query($wpdb->prepare($query, time()));
    }
    
    /**
     * 11. 輔助方法
     */
    
    /**
     * 11.1 更新指標
     */
    private function update_metric(int $post_id, string $metric, $value): void {
        $record = $this->get_or_create_daily_record($post_id, current_time('Y-m-d'));
        
        $this->db->update('performance', [
            $metric => $value
        ], ['id' => $record->id]);
    }
    
    /**
     * 11.2 預測效能
     */
    private function forecast_performance(array $data): array {
        $pageviews = array_column($data, 'pageviews');
        $trend = $this->linear_regression($pageviews);
        
        $forecast = [];
        $last_x = count($pageviews);
        
        for ($i = 1; $i <= 7; $i++) {
            $forecast[] = max(0, round($trend['slope'] * ($last_x + $i) + $trend['intercept']));
        }
        
        return $forecast;
    }
    
    /**
     * 11.3 識別模式
     */
    private function identify_patterns(array $data): array {
        $patterns = [];
        
        // 週末vs平日
        $weekend_avg = 0;
        $weekday_avg = 0;
        $weekend_count = 0;
        $weekday_count = 0;
        
        foreach ($data as $record) {
            $day_of_week = date('w', strtotime($record->date));
            
            if ($day_of_week == 0 || $day_of_week == 6) {
                $weekend_avg += $record->pageviews;
                $weekend_count++;
            } else {
                $weekday_avg += $record->pageviews;
                $weekday_count++;
            }
        }
        
        if ($weekend_count > 0 && $weekday_count > 0) {
            $weekend_avg /= $weekend_count;
            $weekday_avg /= $weekday_count;
            
            if ($weekend_avg > $weekday_avg * 1.2) {
                $patterns[] = 'weekend_peak';
            } elseif ($weekday_avg > $weekend_avg * 1.2) {
                $patterns[] = 'weekday_peak';
            }
        }
        
        // 衰減模式
        if (count($data) > 14) {
            $first_week = array_slice($data, 0, 7);
            $last_week = array_slice($data, -7);
            
            $first_avg = array_sum(array_column($first_week, 'pageviews')) / 7;
            $last_avg = array_sum(array_column($last_week, 'pageviews')) / 7;
            
            if ($last_avg < $first_avg * 0.5) {
                $patterns[] = 'rapid_decay';
            } elseif ($last_avg > $first_avg * 1.5) {
                $patterns[] = 'growing_popularity';
            }
        }
        
        return $patterns;
    }
    
    /**
     * 11.4 生成建議
     */
    private function generate_recommendations(array $trend, array $patterns): array {
        $recommendations = [];
        
        // 基於趨勢的建議
        if ($trend['pageviews']['direction'] === 'down' && $trend['pageviews']['r_squared'] > 0.7) {
            $recommendations[] = [
                'type' => 'content_refresh',
                'priority' => 'high',
                'message' => '文章流量持續下降，建議更新內容或增加新資訊'
            ];
        }
        
        if ($trend['engagement']['direction'] === 'down') {
            $recommendations[] = [
                'type' => 'engagement_improvement',
                'priority' => 'medium',
                'message' => '參與度下降，考慮改善內容結構或增加互動元素'
            ];
        }
        
        // 基於模式的建議
        if (in_array('weekend_peak', $patterns)) {
            $recommendations[] = [
                'type' => 'timing_optimization',
                'priority' => 'low',
                'message' => '週末流量較高，建議在週五發布相關內容'
            ];
        }
        
        if (in_array('rapid_decay', $patterns)) {
            $recommendations[] = [
                'type' => 'evergreen_content',
                'priority' => 'high',
                'message' => '內容衰減快速，建議創作更多常青內容'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * 11.5 分析流量來源
     */
    private function analyze_traffic_sources(): void {
        // 這裡可以整合更詳細的流量來源分析
        // 例如從訪客日誌或整合的分析工具獲取數據
    }
    
    /**
     * 11.6 收集社交指標
     */
    private function collect_social_metrics(): void {
        // 可以整合社交媒體API來獲取分享數據
        // 或使用第三方服務如SharedCount
    }
    
    /**
     * 12. 更新效能排名
     */
    private function update_performance_rankings(): void {
        // 計算各種排名指標
        // 可用於快速識別最佳和最差表現的內容
    }
}