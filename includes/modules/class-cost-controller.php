<?php
/**
 * 檔案：includes/modules/class-cost-controller.php
 * 功能：成本控制模組
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
 * 成本控制類別
 * 
 * 負責追蹤API使用成本、預算管理和成本優化建議
 */
class CostController {
    
    /**
     * 資料庫實例
     */
    private Database $db;
    
    /**
     * 日誌實例
     */
    private Logger $logger;
    
    /**
     * 匯率（USD to TWD）
     */
    private const EXCHANGE_RATE = 30;
    
    /**
     * 模型定價（每1000 tokens，美元）
     */
    private const MODEL_PRICING = [
        'gpt-4' => ['input' => 0.03, 'output' => 0.06],
        'gpt-4-turbo-preview' => ['input' => 0.01, 'output' => 0.03],
        'gpt-3.5-turbo' => ['input' => 0.001, 'output' => 0.002]
    ];
    
    /**
     * DALL-E 定價（美元）
     */
    private const IMAGE_PRICING = [
        'dall-e-3' => [
            'standard' => ['1024x1024' => 0.04, '1024x1792' => 0.08, '1792x1024' => 0.08],
            'hd' => ['1024x1024' => 0.08, '1024x1792' => 0.12, '1792x1024' => 0.12]
        ],
        'dall-e-2' => [
            'standard' => ['256x256' => 0.016, '512x512' => 0.018, '1024x1024' => 0.02]
        ]
    ];
    
    /**
     * 建構函數
     */
    public function __construct() {
        $this->db = new Database();
        $this->logger = new Logger();
        
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
        // 成本追蹤掛鉤
        add_action('aisc_content_generated', [$this, 'track_content_cost'], 10, 3);
        add_action('aisc_image_generated', [$this, 'track_image_cost'], 10, 3);
        
        // 預算檢查掛鉤
        add_filter('aisc_can_generate_content', [$this, 'check_budget_limit']);
        
        // 定期報告
        add_action('aisc_daily_cost_report', [$this, 'send_daily_cost_report']);
        
        if (!wp_next_scheduled('aisc_daily_cost_report')) {
            wp_schedule_event(strtotime('tomorrow 09:00:00'), 'daily', 'aisc_daily_cost_report');
        }
    }
    
    /**
     * 2. 成本追蹤方法
     */
    
    /**
     * 2.1 追蹤內容生成成本
     */
    public function track_content_cost(int $post_id, array $usage, string $model): void {
        $cost_usd = $this->calculate_text_cost($usage, $model);
        $cost_twd = $cost_usd * self::EXCHANGE_RATE;
        
        // 記錄到資料庫
        $this->db->insert('costs', [
            'post_id' => $post_id,
            'type' => 'content',
            'model' => $model,
            'usage_details' => json_encode($usage),
            'cost_usd' => $cost_usd,
            'cost_twd' => $cost_twd,
            'created_at' => current_time('mysql')
        ]);
        
        $this->logger->info('內容生成成本已記錄', [
            'post_id' => $post_id,
            'model' => $model,
            'tokens' => $usage['total_tokens'] ?? 0,
            'cost_twd' => $cost_twd
        ]);
        
        // 檢查預算警告
        $this->check_budget_warning();
    }
    
    /**
     * 2.2 追蹤圖片生成成本
     */
    public function track_image_cost(int $post_id, array $params, string $model): void {
        $cost_usd = $this->calculate_image_cost($params, $model);
        $cost_twd = $cost_usd * self::EXCHANGE_RATE;
        
        // 記錄到資料庫
        $this->db->insert('costs', [
            'post_id' => $post_id,
            'type' => 'image',
            'model' => $model,
            'usage_details' => json_encode($params),
            'cost_usd' => $cost_usd,
            'cost_twd' => $cost_twd,
            'created_at' => current_time('mysql')
        ]);
        
        $this->logger->info('圖片生成成本已記錄', [
            'post_id' => $post_id,
            'model' => $model,
            'params' => $params,
            'cost_twd' => $cost_twd
        ]);
    }
    
    /**
     * 3. 成本計算方法
     */
    
    /**
     * 3.1 計算文字生成成本
     */
    public function calculate_text_cost(array $usage, string $model): float {
        if (!isset(self::MODEL_PRICING[$model])) {
            $this->logger->warning('未知的模型定價', ['model' => $model]);
            return 0;
        }
        
        $pricing = self::MODEL_PRICING[$model];
        $input_cost = ($usage['prompt_tokens'] ?? 0) / 1000 * $pricing['input'];
        $output_cost = ($usage['completion_tokens'] ?? 0) / 1000 * $pricing['output'];
        
        return round($input_cost + $output_cost, 4);
    }
    
    /**
     * 3.2 計算圖片生成成本
     */
    public function calculate_image_cost(array $params, string $model): float {
        $quality = $params['quality'] ?? 'standard';
        $size = $params['size'] ?? '1024x1024';
        $count = $params['count'] ?? 1;
        
        if (!isset(self::IMAGE_PRICING[$model][$quality][$size])) {
            $this->logger->warning('未知的圖片定價參數', [
                'model' => $model,
                'quality' => $quality,
                'size' => $size
            ]);
            return 0;
        }
        
        $unit_cost = self::IMAGE_PRICING[$model][$quality][$size];
        return round($unit_cost * $count, 4);
    }
    
    /**
     * 3.3 估算內容生成成本
     */
    public function estimate_content_cost(int $word_count, string $model): float {
        // 估算token數（中文約1.5字=1token，英文約4字=1token）
        $estimated_tokens = $word_count * 0.7; // 平均估算
        
        // 加上系統prompt的token（約500）
        $total_tokens = $estimated_tokens + 500;
        
        // 假設輸入和輸出token比例為1:4
        $usage = [
            'prompt_tokens' => $total_tokens * 0.2,
            'completion_tokens' => $total_tokens * 0.8
        ];
        
        $cost_usd = $this->calculate_text_cost($usage, $model);
        return round($cost_usd * self::EXCHANGE_RATE, 2);
    }
    
    /**
     * 4. 預算管理方法
     */
    
    /**
     * 4.1 取得今日成本
     */
    public function get_today_cost(): float {
        $today = current_time('Y-m-d');
        
        $result = $this->db->get_var(
            "SELECT SUM(cost_twd) FROM {$this->db->get_table_name('costs')} 
            WHERE DATE(created_at) = %s",
            $today
        );
        
        return floatval($result ?: 0);
    }
    
    /**
     * 4.2 取得本月成本
     */
    public function get_month_cost(): float {
        $year_month = current_time('Y-m');
        
        $result = $this->db->get_var(
            "SELECT SUM(cost_twd) FROM {$this->db->get_table_name('costs')} 
            WHERE DATE_FORMAT(created_at, '%%Y-%%m') = %s",
            $year_month
        );
        
        return floatval($result ?: 0);
    }
    
    /**
     * 4.3 檢查預算限制
     */
    public function check_budget_limit(): bool {
        $daily_budget = floatval(get_option('aisc_daily_budget', 0));
        $monthly_budget = floatval(get_option('aisc_monthly_budget', 0));
        
        // 檢查每日預算
        if ($daily_budget > 0) {
            $today_cost = $this->get_today_cost();
            if ($today_cost >= $daily_budget) {
                $this->logger->warning('已達到每日預算限制', [
                    'budget' => $daily_budget,
                    'used' => $today_cost
                ]);
                return false;
            }
        }
        
        // 檢查每月預算
        if ($monthly_budget > 0) {
            $month_cost = $this->get_month_cost();
            if ($month_cost >= $monthly_budget) {
                $this->logger->warning('已達到每月預算限制', [
                    'budget' => $monthly_budget,
                    'used' => $month_cost
                ]);
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * 4.4 檢查預算警告
     */
    public function check_budget_warning(): void {
        if (!get_option('aisc_budget_warning', 1)) {
            return;
        }
        
        $daily_budget = floatval(get_option('aisc_daily_budget', 0));
        $monthly_budget = floatval(get_option('aisc_monthly_budget', 0));
        
        // 檢查每日預算警告（80%）
        if ($daily_budget > 0) {
            $today_cost = $this->get_today_cost();
            $usage_percentage = ($today_cost / $daily_budget) * 100;
            
            if ($usage_percentage >= 80 && $usage_percentage < 100) {
                set_transient('aisc_daily_budget_warning', true, HOUR_IN_SECONDS);
            }
        }
        
        // 檢查每月預算警告（80%）
        if ($monthly_budget > 0) {
            $month_cost = $this->get_month_cost();
            $usage_percentage = ($month_cost / $monthly_budget) * 100;
            
            if ($usage_percentage >= 80 && $usage_percentage < 100) {
                set_transient('aisc_monthly_budget_warning', true, DAY_IN_SECONDS);
            }
        }
    }
    
    /**
     * 4.5 是否顯示預算警告
     */
    public function is_budget_warning(): bool {
        return get_transient('aisc_daily_budget_warning') || get_transient('aisc_monthly_budget_warning');
    }
    
    /**
     * 4.6 取得預算使用百分比
     */
    public function get_budget_usage_percentage(): float {
        $daily_budget = floatval(get_option('aisc_daily_budget', 0));
        
        if ($daily_budget > 0) {
            $today_cost = $this->get_today_cost();
            return min(100, round(($today_cost / $daily_budget) * 100, 1));
        }
        
        return 0;
    }
    
    /**
     * 5. 成本報告和分析
     */
    
    /**
     * 5.1 取得成本摘要
     */
    public function get_cost_summary(): array {
        return [
            'today' => $this->get_today_cost(),
            'yesterday' => $this->get_yesterday_cost(),
            'week' => $this->get_week_cost(),
            'month' => $this->get_month_cost(),
            'details' => $this->get_recent_cost_details(20)
        ];
    }
    
    /**
     * 5.2 取得昨日成本
     */
    private function get_yesterday_cost(): float {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        $result = $this->db->get_var(
            "SELECT SUM(cost_twd) FROM {$this->db->get_table_name('costs')} 
            WHERE DATE(created_at) = %s",
            $yesterday
        );
        
        return floatval($result ?: 0);
    }
    
    /**
     * 5.3 取得本週成本
     */
    private function get_week_cost(): float {
        $week_start = date('Y-m-d', strtotime('monday this week'));
        
        $result = $this->db->get_var(
            "SELECT SUM(cost_twd) FROM {$this->db->get_table_name('costs')} 
            WHERE DATE(created_at) >= %s",
            $week_start
        );
        
        return floatval($result ?: 0);
    }
    
    /**
     * 5.4 取得最近成本明細
     */
    public function get_recent_cost_details(int $limit = 20): array {
        $results = $this->db->get_results(
            "SELECT c.*, p.post_title 
            FROM {$this->db->get_table_name('costs')} c
            LEFT JOIN {$this->db->get_wpdb()->posts} p ON c.post_id = p.ID
            ORDER BY c.created_at DESC
            LIMIT %d",
            $limit
        );
        
        $details = [];
        foreach ($results as $row) {
            $usage = json_decode($row->usage_details, true);
            
            $details[] = [
                'date' => date('Y-m-d H:i', strtotime($row->created_at)),
                'post_id' => $row->post_id,
                'title' => $row->post_title ?: '未知',
                'type' => $row->type,
                'model' => $row->model,
                'tokens' => $usage['total_tokens'] ?? 0,
                'images' => $usage['count'] ?? 0,
                'cost' => $row->cost_twd
            ];
        }
        
        return $details;
    }
    
    /**
     * 5.5 發送每日成本報告
     */
    public function send_daily_cost_report(): void {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $yesterday_cost = $this->get_yesterday_cost();
        
        // 取得昨日生成統計
        $stats = $this->db->get_row(
            "SELECT 
                COUNT(DISTINCT post_id) as post_count,
                SUM(CASE WHEN type = 'content' THEN 1 ELSE 0 END) as content_count,
                SUM(CASE WHEN type = 'image' THEN 1 ELSE 0 END) as image_count
            FROM {$this->db->get_table_name('costs')}
            WHERE DATE(created_at) = %s",
            $yesterday
        );
        
        // 準備報告內容
        $report = sprintf(
            "AI SEO內容生成器 - 每日成本報告\n\n" .
            "日期：%s\n" .
            "總成本：NT$ %s\n" .
            "生成文章：%d 篇\n" .
            "內容生成：%d 次\n" .
            "圖片生成：%d 張\n\n" .
            "查看詳細報告：%s",
            $yesterday,
            number_format($yesterday_cost, 2),
            $stats->post_count,
            $stats->content_count,
            $stats->image_count,
            admin_url('admin.php?page=aisc-costs')
        );
        
        // 發送給管理員
        $admin_email = get_option('admin_email');
        wp_mail(
            $admin_email,
            sprintf('[%s] AI SEO生成器 - 每日成本報告', get_bloginfo('name')),
            $report
        );
        
        $this->logger->info('每日成本報告已發送', [
            'date' => $yesterday,
            'cost' => $yesterday_cost
        ]);
    }
    
    /**
     * 6. 成本優化建議
     */
    
    /**
     * 6.1 取得優化建議
     */
    public function get_optimization_tips(): array {
        $tips = [];
        $cost_data = $this->analyze_cost_patterns();
        
        // 模型使用建議
        if ($cost_data['gpt4_percentage'] > 50) {
            $tips[] = [
                'title' => '考慮使用GPT-4 Turbo',
                'description' => '您大量使用GPT-4，切換到GPT-4 Turbo可以節省約66%的成本，同時保持相似的品質。',
                'saving' => 66
            ];
        }
        
        // 文章長度建議
        if ($cost_data['avg_word_count'] > 10000) {
            $tips[] = [
                'title' => '優化文章長度',
                'description' => '您的平均文章長度超過10000字，考慮將長文章分成多篇系列文章，可以提升讀者體驗並降低單次成本。',
                'saving' => 30
            ];
        }
        
        // 圖片使用建議
        if ($cost_data['image_cost_percentage'] > 30) {
            $tips[] = [
                'title' => '優化圖片生成策略',
                'description' => '圖片成本佔比較高，考慮減少每篇文章的圖片數量或使用較小的圖片尺寸。',
                'saving' => 20
            ];
        }
        
        // 排程時間建議
        if ($cost_data['peak_hour_percentage'] > 60) {
            $tips[] = [
                'title' => '調整生成時間',
                'description' => '大部分內容在尖峰時段生成，考慮將排程設定在離峰時段，可能獲得更好的API回應速度。',
                'saving' => 5
            ];
        }
        
        // 重複內容建議
        if ($cost_data['similar_content_count'] > 5) {
            $tips[] = [
                'title' => '避免相似主題',
                'description' => '偵測到多篇相似主題的文章，建議使用更多樣化的關鍵字，避免內容重複。',
                'saving' => 15
            ];
        }
        
        return $tips;
    }
    
    /**
     * 6.2 分析成本模式
     */
    private function analyze_cost_patterns(): array {
        // 分析最近30天的成本模式
        $start_date = date('Y-m-d', strtotime('-30 days'));
        
        // GPT-4使用比例
        $gpt4_costs = $this->db->get_var(
            "SELECT SUM(cost_twd) FROM {$this->db->get_table_name('costs')}
            WHERE model = 'gpt-4' AND created_at >= %s",
            $start_date
        );
        
        $total_costs = $this->db->get_var(
            "SELECT SUM(cost_twd) FROM {$this->db->get_table_name('costs')}
            WHERE created_at >= %s",
            $start_date
        );
        
        // 平均文章字數
        $avg_tokens = $this->db->get_var(
            "SELECT AVG(JSON_EXTRACT(usage_details, '$.total_tokens'))
            FROM {$this->db->get_table_name('costs')}
            WHERE type = 'content' AND created_at >= %s",
            $start_date
        );
        
        // 圖片成本比例
        $image_costs = $this->db->get_var(
            "SELECT SUM(cost_twd) FROM {$this->db->get_table_name('costs')}
            WHERE type = 'image' AND created_at >= %s",
            $start_date
        );
        
        // 尖峰時段比例（9-18點）
        $peak_costs = $this->db->get_var(
            "SELECT SUM(cost_twd) FROM {$this->db->get_table_name('costs')}
            WHERE HOUR(created_at) BETWEEN 9 AND 18 AND created_at >= %s",
            $start_date
        );
        
        // 相似內容檢測
        $similar_count = $this->detect_similar_content_count();
        
        return [
            'gpt4_percentage' => $total_costs > 0 ? ($gpt4_costs / $total_costs) * 100 : 0,
            'avg_word_count' => $avg_tokens ? $avg_tokens * 1.5 : 0, // 估算字數
            'image_cost_percentage' => $total_costs > 0 ? ($image_costs / $total_costs) * 100 : 0,
            'peak_hour_percentage' => $total_costs > 0 ? ($peak_costs / $total_costs) * 100 : 0,
            'similar_content_count' => $similar_count
        ];
    }
    
    /**
     * 6.3 檢測相似內容數量
     */
    private function detect_similar_content_count(): int {
        // 簡單的相似度檢測（基於關鍵字）
        $keywords = $this->db->get_results(
            "SELECT meta_value, COUNT(*) as count
            FROM {$this->db->get_wpdb()->postmeta}
            WHERE meta_key = '_aisc_keyword'
            GROUP BY meta_value
            HAVING count > 1"
        );
        
        $similar_count = 0;
        foreach ($keywords as $keyword) {
            if ($keyword->count > 2) {
                $similar_count += $keyword->count - 1;
            }
        }
        
        return $similar_count;
    }
    
    /**
     * 7. 成本統計圖表資料
     */
    
    /**
     * 7.1 取得成本趨勢圖表資料
     */
    public function get_cost_chart_data(string $period = '7days'): array {
        $days = $this->get_period_days($period);
        $start_date = date('Y-m-d', strtotime("-{$days} days"));
        
        $results = $this->db->get_results(
            "SELECT DATE(created_at) as date, 
                    SUM(cost_twd) as daily_cost,
                    COUNT(DISTINCT post_id) as post_count
            FROM {$this->db->get_table_name('costs')}
            WHERE created_at >= %s
            GROUP BY DATE(created_at)
            ORDER BY date ASC",
            $start_date
        );
        
        $labels = [];
        $cost_data = [];
        $post_data = [];
        
        // 填充所有日期
        for ($i = $days; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $labels[] = date('m/d', strtotime($date));
            
            $found = false;
            foreach ($results as $row) {
                if ($row->date === $date) {
                    $cost_data[] = round($row->daily_cost, 2);
                    $post_data[] = $row->post_count;
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $cost_data[] = 0;
                $post_data[] = 0;
            }
        }
        
        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => '每日成本 (NT$)',
                    'data' => $cost_data,
                    'borderColor' => '#4CAF50',
                    'backgroundColor' => 'rgba(76, 175, 80, 0.1)',
                    'yAxisID' => 'y-cost'
                ],
                [
                    'label' => '文章數量',
                    'data' => $post_data,
                    'borderColor' => '#2196F3',
                    'backgroundColor' => 'rgba(33, 150, 243, 0.1)',
                    'yAxisID' => 'y-posts'
                ]
            ]
        ];
    }
    
    /**
     * 7.2 取得模型使用分布
     */
    public function get_model_distribution_data(): array {
        $results = $this->db->get_results(
            "SELECT model, COUNT(*) as count, SUM(cost_twd) as total_cost
            FROM {$this->db->get_table_name('costs')}
            WHERE type = 'content'
            GROUP BY model"
        );
        
        $labels = [];
        $data = [];
        $colors = [
            'gpt-4' => '#FF6384',
            'gpt-4-turbo-preview' => '#36A2EB',
            'gpt-3.5-turbo' => '#FFCE56'
        ];
        
        foreach ($results as $row) {
            $labels[] = $row->model;
            $data[] = round($row->total_cost, 2);
        }
        
        return [
            'labels' => $labels,
            'datasets' => [{
                'data' => $data,
                'backgroundColor' => array_values($colors)
            }]
        ];
    }
    
    /**
     * 7.3 取得時段分析資料
     */
    public function get_hourly_distribution_data(): array {
        $results = $this->db->get_results(
            "SELECT HOUR(created_at) as hour, 
                    COUNT(*) as count,
                    AVG(cost_twd) as avg_cost
            FROM {$this->db->get_table_name('costs')}
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY HOUR(created_at)
            ORDER BY hour"
        );
        
        $labels = [];
        $count_data = [];
        $cost_data = [];
        
        // 填充24小時
        for ($hour = 0; $hour < 24; $hour++) {
            $labels[] = sprintf('%02d:00', $hour);
            
            $found = false;
            foreach ($results as $row) {
                if (intval($row->hour) === $hour) {
                    $count_data[] = $row->count;
                    $cost_data[] = round($row->avg_cost, 2);
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $count_data[] = 0;
                $cost_data[] = 0;
            }
        }
        
        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => '生成次數',
                    'data' => $count_data,
                    'type' => 'bar',
                    'backgroundColor' => 'rgba(76, 175, 80, 0.5)'
                ],
                [
                    'label' => '平均成本',
                    'data' => $cost_data,
                    'type' => 'line',
                    'borderColor' => '#FF9800',
                    'backgroundColor' => 'transparent'
                ]
            ]
        ];
    }
    
    /**
     * 8. 輔助方法
     */
    
    /**
     * 8.1 取得期間天數
     */
    private function get_period_days(string $period): int {
        $periods = [
            'today' => 0,
            '7days' => 7,
            '30days' => 30,
            '90days' => 90
        ];
        
        return $periods[$period] ?? 7;
    }
    
    /**
     * 8.2 格式化成本顯示
     */
    public function format_cost(float $cost, string $currency = 'TWD'): string {
        if ($currency === 'TWD') {
            return 'NT$ ' . number_format($cost, 2);
        } else {
            return '$ ' . number_format($cost, 4) . ' USD';
        }
    }
    
    /**
     * 8.3 匯出成本報告
     */
    public function export_cost_report(string $format = 'csv', string $period = '30days'): string {
        $days = $this->get_period_days($period);
        $start_date = date('Y-m-d', strtotime("-{$days} days"));
        
        $results = $this->db->get_results(
            "SELECT c.*, p.post_title
            FROM {$this->db->get_table_name('costs')} c
            LEFT JOIN {$this->db->get_wpdb()->posts} p ON c.post_id = p.ID
            WHERE c.created_at >= %s
            ORDER BY c.created_at DESC",
            $start_date
        );
        
        if ($format === 'csv') {
            return $this->export_as_csv($results);
        } else {
            return $this->export_as_json($results);
        }
    }
    
    /**
     * 8.4 匯出為CSV
     */
    private function export_as_csv(array $data): string {
        $csv = "日期時間,文章標題,類型,模型,Token/數量,成本(TWD),成本(USD)\n";
        
        foreach ($data as $row) {
            $usage = json_decode($row->usage_details, true);
            $quantity = $row->type === 'content' ? 
                ($usage['total_tokens'] ?? 0) . ' tokens' : 
                ($usage['count'] ?? 1) . ' 張';
            
            $csv .= sprintf(
                '"%s","%s","%s","%s","%s","%.2f","%.4f"' . "\n",
                $row->created_at,
                $row->post_title ?: '未知',
                $row->type === 'content' ? '內容' : '圖片',
                $row->model,
                $quantity,
                $row->cost_twd,
                $row->cost_usd
            );
        }
        
        return $csv;
    }
    
    /**
     * 8.5 匯出為JSON
     */
    private function export_as_json(array $data): string {
        $export_data = [];
        
        foreach ($data as $row) {
            $usage = json_decode($row->usage_details, true);
            
            $export_data[] = [
                'datetime' => $row->created_at,
                'post_title' => $row->post_title ?: '未知',
                'type' => $row->type,
                'model' => $row->model,
                'usage' => $usage,
                'cost_twd' => floatval($row->cost_twd),
                'cost_usd' => floatval($row->cost_usd)
            ];
        }
        
        return json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
