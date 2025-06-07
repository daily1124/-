<?php
/**
 * 檔案：includes/modules/class-diagnostics.php
 * 功能：診斷工具模組
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
 * 診斷工具類別
 * 
 * 負責系統健康檢查、功能測試、問題診斷和修復
 */
class Diagnostics {
    
    /**
     * 資料庫實例
     */
    private Database $db;
    
    /**
     * 日誌實例
     */
    private Logger $logger;
    
    /**
     * 測試結果
     */
    private array $test_results = [];
    
    /**
     * 系統需求
     */
    private const REQUIREMENTS = [
        'php_version' => '7.4.0',
        'wp_version' => '5.8.0',
        'memory_limit' => '128M',
        'max_execution_time' => 300,
        'curl_enabled' => true,
        'json_enabled' => true
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
        // 定期健康檢查
        add_action('aisc_health_check', [$this, 'run_health_check']);
        
        if (!wp_next_scheduled('aisc_health_check')) {
            wp_schedule_event(time(), 'daily', 'aisc_health_check');
        }
        
        // AJAX測試端點
        add_action('wp_ajax_aisc_run_diagnostic_test', [$this, 'ajax_run_test']);
        
        // 資料庫維護
        add_action('aisc_db_maintenance', [$this, 'run_database_maintenance']);
        
        if (!wp_next_scheduled('aisc_db_maintenance')) {
            wp_schedule_event(strtotime('sunday 03:00:00'), 'weekly', 'aisc_db_maintenance');
        }
    }
    
    /**
     * 2. 系統檢查方法
     */
    
    /**
     * 2.1 執行系統檢查
     */
    public function run_system_checks(): array {
        $checks = [];
        
        // PHP版本檢查
        $checks[] = $this->check_php_version();
        
        // WordPress版本檢查
        $checks[] = $this->check_wp_version();
        
        // 記憶體限制檢查
        $checks[] = $this->check_memory_limit();
        
        // 執行時間檢查
        $checks[] = $this->check_execution_time();
        
        // PHP擴展檢查
        $checks[] = $this->check_php_extensions();
        
        // 目錄權限檢查
        $checks[] = $this->check_directory_permissions();
        
        // 資料庫檢查
        $checks[] = $this->check_database_tables();
        
        // API連接檢查
        $checks[] = $this->check_api_connectivity();
        
        // 快取系統檢查
        $checks[] = $this->check_cache_system();
        
        // 排程系統檢查
        $checks[] = $this->check_cron_system();
        
        return $checks;
    }
    
    /**
     * 2.2 檢查PHP版本
     */
    private function check_php_version(): array {
        $current_version = PHP_VERSION;
        $required_version = self::REQUIREMENTS['php_version'];
        
        if (version_compare($current_version, $required_version, '>=')) {
            return [
                'name' => 'PHP版本',
                'status' => 'pass',
                'message' => sprintf('當前版本 %s 符合要求', $current_version)
            ];
        } else {
            return [
                'name' => 'PHP版本',
                'status' => 'error',
                'message' => sprintf('需要 PHP %s 或更高版本，當前版本為 %s', $required_version, $current_version)
            ];
        }
    }
    
    /**
     * 2.3 檢查WordPress版本
     */
    private function check_wp_version(): array {
        global $wp_version;
        $required_version = self::REQUIREMENTS['wp_version'];
        
        if (version_compare($wp_version, $required_version, '>=')) {
            return [
                'name' => 'WordPress版本',
                'status' => 'pass',
                'message' => sprintf('當前版本 %s 符合要求', $wp_version)
            ];
        } else {
            return [
                'name' => 'WordPress版本',
                'status' => 'warning',
                'message' => sprintf('建議使用 WordPress %s 或更高版本，當前版本為 %s', $required_version, $wp_version)
            ];
        }
    }
    
    /**
     * 2.4 檢查記憶體限制
     */
    private function check_memory_limit(): array {
        $memory_limit = ini_get('memory_limit');
        $required_limit = self::REQUIREMENTS['memory_limit'];
        
        $memory_bytes = $this->convert_to_bytes($memory_limit);
        $required_bytes = $this->convert_to_bytes($required_limit);
        
        if ($memory_bytes >= $required_bytes) {
            return [
                'name' => '記憶體限制',
                'status' => 'pass',
                'message' => sprintf('當前限制 %s 符合要求', $memory_limit)
            ];
        } else {
            return [
                'name' => '記憶體限制',
                'status' => 'warning',
                'message' => sprintf('建議至少 %s 記憶體，當前為 %s', $required_limit, $memory_limit)
            ];
        }
    }
    
    /**
     * 2.5 檢查執行時間
     */
    private function check_execution_time(): array {
        $max_execution_time = ini_get('max_execution_time');
        $required_time = self::REQUIREMENTS['max_execution_time'];
        
        if ($max_execution_time == 0 || $max_execution_time >= $required_time) {
            return [
                'name' => '最大執行時間',
                'status' => 'pass',
                'message' => sprintf('當前設定 %s 秒符合要求', $max_execution_time ?: '無限制')
            ];
        } else {
            return [
                'name' => '最大執行時間',
                'status' => 'warning',
                'message' => sprintf('建議至少 %d 秒，當前為 %d 秒', $required_time, $max_execution_time)
            ];
        }
    }
    
    /**
     * 2.6 檢查PHP擴展
     */
    private function check_php_extensions(): array {
        $required_extensions = ['curl', 'json', 'mbstring', 'openssl'];
        $missing = [];
        
        foreach ($required_extensions as $ext) {
            if (!extension_loaded($ext)) {
                $missing[] = $ext;
            }
        }
        
        if (empty($missing)) {
            return [
                'name' => 'PHP擴展',
                'status' => 'pass',
                'message' => '所有必要的PHP擴展都已安裝'
            ];
        } else {
            return [
                'name' => 'PHP擴展',
                'status' => 'error',
                'message' => '缺少以下擴展：' . implode(', ', $missing)
            ];
        }
    }
    
    /**
     * 2.7 檢查目錄權限
     */
    private function check_directory_permissions(): array {
        $directories = [
            AISC_PLUGIN_DIR . 'logs',
            AISC_PLUGIN_DIR . 'cache',
            AISC_PLUGIN_DIR . 'temp',
            AISC_PLUGIN_DIR . 'exports'
        ];
        
        $issues = [];
        
        foreach ($directories as $dir) {
            if (!is_writable($dir)) {
                $issues[] = basename($dir);
            }
        }
        
        if (empty($issues)) {
            return [
                'name' => '目錄權限',
                'status' => 'pass',
                'message' => '所有必要目錄都可寫入'
            ];
        } else {
            return [
                'name' => '目錄權限',
                'status' => 'error',
                'message' => '以下目錄無法寫入：' . implode(', ', $issues)
            ];
        }
    }
    
    /**
     * 2.8 檢查資料庫表
     */
    private function check_database_tables(): array {
        $required_tables = [
            'keywords',
            'content_history',
            'schedules',
            'costs',
            'logs',
            'performance',
            'cache'
        ];
        
        $missing = [];
        
        foreach ($required_tables as $table) {
            if (!$this->db->table_exists($table)) {
                $missing[] = $this->db->get_table_name($table);
            }
        }
        
        if (empty($missing)) {
            return [
                'name' => '資料庫表',
                'status' => 'pass',
                'message' => '所有必要的資料表都存在'
            ];
        } else {
            return [
                'name' => '資料庫表',
                'status' => 'error',
                'message' => '缺少以下資料表：' . implode(', ', $missing)
            ];
        }
    }
    
    /**
     * 2.9 檢查API連接
     */
    private function check_api_connectivity(): array {
        $api_key = get_option('aisc_openai_api_key');
        
        if (empty($api_key)) {
            return [
                'name' => 'API連接',
                'status' => 'warning',
                'message' => '尚未設定OpenAI API Key'
            ];
        }
        
        // 測試API連接
        $response = wp_remote_get('https://api.openai.com/v1/models', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key
            ],
            'timeout' => 10
        ]);
        
        if (is_wp_error($response)) {
            return [
                'name' => 'API連接',
                'status' => 'error',
                'message' => '無法連接到OpenAI API：' . $response->get_error_message()
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code === 200) {
            return [
                'name' => 'API連接',
                'status' => 'pass',
                'message' => 'OpenAI API連接正常'
            ];
        } elseif ($status_code === 401) {
            return [
                'name' => 'API連接',
                'status' => 'error',
                'message' => 'API Key無效或已過期'
            ];
        } else {
            return [
                'name' => 'API連接',
                'status' => 'warning',
                'message' => sprintf('API回應狀態碼：%d', $status_code)
            ];
        }
    }
    
    /**
     * 2.10 檢查快取系統
     */
    private function check_cache_system(): array {
        // 測試寫入快取
        $test_key = 'aisc_cache_test_' . time();
        $test_value = 'test_value';
        
        set_transient($test_key, $test_value, 60);
        $retrieved = get_transient($test_key);
        delete_transient($test_key);
        
        if ($retrieved === $test_value) {
            return [
                'name' => '快取系統',
                'status' => 'pass',
                'message' => '快取系統運作正常'
            ];
        } else {
            return [
                'name' => '快取系統',
                'status' => 'warning',
                'message' => '快取系統可能有問題'
            ];
        }
    }
    
    /**
     * 2.11 檢查排程系統
     */
    private function check_cron_system(): array {
        $next_cron = wp_next_scheduled('aisc_keyword_update');
        
        if ($next_cron) {
            $time_diff = $next_cron - time();
            $hours = round($time_diff / 3600, 1);
            
            return [
                'name' => '排程系統',
                'status' => 'pass',
                'message' => sprintf('下次排程將在 %.1f 小時後執行', $hours)
            ];
        } else {
            return [
                'name' => '排程系統',
                'status' => 'warning',
                'message' => '沒有設定排程任務'
            ];
        }
    }
    
    /**
     * 3. 功能測試方法
     */
    
    /**
     * 3.1 執行功能測試
     */
    public function run_test(string $test_type): array {
        $this->logger->info('開始執行診斷測試', ['type' => $test_type]);
        
        switch ($test_type) {
            case 'keyword':
                return $this->test_keyword_fetching();
                
            case 'content':
                return $this->test_content_generation();
                
            case 'seo':
                return $this->test_seo_optimization();
                
            case 'schedule':
                return $this->test_scheduler();
                
            case 'api':
                return $this->test_api_endpoints();
                
            case 'all':
                return $this->run_all_tests();
                
            default:
                return [
                    'success' => false,
                    'message' => '未知的測試類型'
                ];
        }
    }
    
    /**
     * 3.2 測試關鍵字抓取
     */
    private function test_keyword_fetching(): array {
        try {
            $keyword_manager = new KeywordManager();
            
            // 測試Google Trends連接
            $trends_url = 'https://trends.google.com.tw/trends/trendingsearches/daily/rss';
            $response = wp_remote_get($trends_url, [
                'timeout' => 10,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ]
            ]);
            
            if (is_wp_error($response)) {
                throw new \Exception('無法連接到Google Trends: ' . $response->get_error_message());
            }
            
            $body = wp_remote_retrieve_body($response);
            if (empty($body)) {
                throw new \Exception('Google Trends回應為空');
            }
            
            // 嘗試解析XML
            $xml = simplexml_load_string($body);
            if (!$xml) {
                throw new \Exception('無法解析Google Trends資料');
            }
            
            $keyword_count = count($xml->channel->item);
            
            return [
                'success' => true,
                'message' => sprintf('成功連接到Google Trends，發現 %d 個熱門關鍵字', $keyword_count),
                'details' => [
                    'source' => 'Google Trends',
                    'status' => 'connected',
                    'keyword_count' => $keyword_count
                ]
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '關鍵字抓取測試失敗',
                'error' => $e->getMessage(),
                'details' => [
                    'source' => 'Google Trends',
                    'status' => 'failed'
                ]
            ];
        }
    }
    
    /**
     * 3.3 測試內容生成
     */
    private function test_content_generation(): array {
        try {
            $api_key = get_option('aisc_openai_api_key');
            
            if (empty($api_key)) {
                throw new \Exception('未設定OpenAI API Key');
            }
            
            // 測試簡單的內容生成
            $test_prompt = '請用繁體中文回答：今天天氣如何？（測試回應）';
            
            $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode([
                    'model' => 'gpt-3.5-turbo',
                    'messages' => [
                        ['role' => 'user', 'content' => $test_prompt]
                    ],
                    'max_tokens' => 50,
                    'temperature' => 0.7
                ]),
                'timeout' => 30
            ]);
            
            if (is_wp_error($response)) {
                throw new \Exception('API請求失敗: ' . $response->get_error_message());
            }
            
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            if (isset($body['error'])) {
                throw new \Exception('API錯誤: ' . $body['error']['message']);
            }
            
            if (!isset($body['choices'][0]['message']['content'])) {
                throw new \Exception('API回應格式錯誤');
            }
            
            $tokens_used = $body['usage']['total_tokens'] ?? 0;
            
            return [
                'success' => true,
                'message' => '內容生成測試成功',
                'details' => [
                    'model' => 'gpt-3.5-turbo',
                    'tokens_used' => $tokens_used,
                    'response_preview' => mb_substr($body['choices'][0]['message']['content'], 0, 50) . '...'
                ]
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '內容生成測試失敗',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 3.4 測試SEO優化
     */
    private function test_seo_optimization(): array {
        try {
            $seo_optimizer = new SEOOptimizer();
            
            // 測試內容
            $test_content = '這是一篇測試文章。測試關鍵字出現在這裡。這段內容用來測試SEO優化功能是否正常運作。';
            $test_keyword = '測試關鍵字';
            
            // 執行優化
            $optimized = $seo_optimizer->optimize_content($test_content, $test_keyword);
            
            // 驗證結果
            $checks = [
                'title' => !empty($optimized['title']),
                'content' => strlen($optimized['content']) > strlen($test_content),
                'excerpt' => !empty($optimized['excerpt']),
                'meta_description' => !empty($optimized['meta_description']),
                'slug' => !empty($optimized['slug'])
            ];
            
            $passed = array_filter($checks);
            $pass_rate = (count($passed) / count($checks)) * 100;
            
            return [
                'success' => $pass_rate >= 80,
                'message' => sprintf('SEO優化測試完成，通過率：%.0f%%', $pass_rate),
                'details' => [
                    'checks' => $checks,
                    'optimizations' => array_keys($optimized)
                ]
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'SEO優化測試失敗',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 3.5 測試排程系統
     */
    private function test_scheduler(): array {
        try {
            $scheduler = new Scheduler();
            
            // 檢查排程掛鉤
            $hooks = [
                'aisc_keyword_update' => wp_next_scheduled('aisc_keyword_update'),
                'aisc_content_generation' => wp_next_scheduled('aisc_content_generation'),
                'aisc_check_schedules' => wp_next_scheduled('aisc_check_schedules')
            ];
            
            $active_hooks = array_filter($hooks);
            
            // 測試建立臨時排程
            $test_schedule = [
                'name' => '診斷測試排程',
                'type' => 'general',
                'frequency' => 'once',
                'keyword_count' => 1,
                'status' => 'inactive'
            ];
            
            $schedule_id = $scheduler->create_schedule($test_schedule);
            
            if ($schedule_id) {
                // 刪除測試排程
                $scheduler->delete_schedule($schedule_id);
                
                return [
                    'success' => true,
                    'message' => '排程系統測試成功',
                    'details' => [
                        'active_hooks' => count($active_hooks),
                        'hooks' => array_keys($active_hooks),
                        'test_schedule' => 'created and deleted successfully'
                    ]
                ];
            } else {
                throw new \Exception('無法建立測試排程');
            }
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '排程系統測試失敗',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 3.6 測試API端點
     */
    private function test_api_endpoints(): array {
        try {
            $endpoints = [
                'OpenAI' => 'https://api.openai.com/v1/models',
                'Google Trends' => 'https://trends.google.com.tw/trends/trendingsearches/daily/rss',
                'WordPress API' => home_url('/wp-json/wp/v2/posts')
            ];
            
            $results = [];
            
            foreach ($endpoints as $name => $url) {
                $response = wp_remote_get($url, [
                    'timeout' => 10,
                    'headers' => $name === 'OpenAI' ? [
                        'Authorization' => 'Bearer ' . get_option('aisc_openai_api_key')
                    ] : []
                ]);
                
                if (is_wp_error($response)) {
                    $results[$name] = [
                        'status' => 'error',
                        'message' => $response->get_error_message()
                    ];
                } else {
                    $status_code = wp_remote_retrieve_response_code($response);
                    $results[$name] = [
                        'status' => $status_code >= 200 && $status_code < 300 ? 'success' : 'error',
                        'code' => $status_code
                    ];
                }
            }
            
            $success_count = count(array_filter($results, fn($r) => $r['status'] === 'success'));
            
            return [
                'success' => $success_count === count($endpoints),
                'message' => sprintf('API端點測試：%d/%d 成功', $success_count, count($endpoints)),
                'details' => $results
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'API端點測試失敗',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 3.7 執行所有測試
     */
    private function run_all_tests(): array {
        $tests = ['keyword', 'content', 'seo', 'schedule', 'api'];
        $results = [];
        $success_count = 0;
        
        foreach ($tests as $test) {
            $result = $this->run_test($test);
            $results[$test] = $result;
            
            if ($result['success']) {
                $success_count++;
            }
        }
        
        return [
            'success' => $success_count === count($tests),
            'message' => sprintf('完成所有測試：%d/%d 成功', $success_count, count($tests)),
            'details' => $results
        ];
    }
    
    /**
     * 4. 日誌管理方法
     */
    
    /**
     * 4.1 取得最近日誌
     */
    public function get_recent_logs(int $limit = 100, string $level = 'all', ?string $date = null): string {
        $where_conditions = ['1=1'];
        $params = [];
        
        if ($level !== 'all') {
            $where_conditions[] = 'level = %s';
            $params[] = $level;
        }
        
        if ($date) {
            $where_conditions[] = 'DATE(created_at) = %s';
            $params[] = $date;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $logs = $this->db->get_results(
            "SELECT * FROM {$this->db->get_table_name('logs')} 
            WHERE {$where_clause}
            ORDER BY created_at DESC 
            LIMIT %d",
            array_merge($params, [$limit])
        );
        
        $output = '';
        foreach ($logs as $log) {
            $output .= sprintf(
                "[%s] [%s] [%s] %s\n",
                $log->created_at,
                strtoupper($log->level),
                $log->category,
                $log->message
            );
            
            if (!empty($log->context)) {
                $context = json_decode($log->context, true);
                if ($context) {
                    $output .= "  Context: " . json_encode($context, JSON_UNESCAPED_UNICODE) . "\n";
                }
            }
        }
        
        return $output ?: '沒有找到符合條件的日誌記錄。';
    }
    
    /**
     * 4.2 清除日誌
     */
    public function clear_logs(string $level = 'all', int $days_to_keep = 0): int {
        $where_conditions = [];
        $params = [];
        
        if ($level !== 'all') {
            $where_conditions[] = 'level = %s';
            $params[] = $level;
        }
        
        if ($days_to_keep > 0) {
            $where_conditions[] = 'created_at < DATE_SUB(NOW(), INTERVAL %d DAY)';
            $params[] = $days_to_keep;
        }
        
        $where_clause = empty($where_conditions) ? '1=1' : implode(' AND ', $where_conditions);
        
        $deleted = $this->db->delete('logs', $where_clause, $params);
        
        $this->logger->info('日誌已清除', [
            'deleted_count' => $deleted,
            'level' => $level,
            'days_to_keep' => $days_to_keep
        ]);
        
        return $deleted;
    }
    
    /**
     * 4.3 匯出日誌
     */
    public function export_logs(string $format = 'txt', string $period = '7days'): string {
        $days = $this->get_period_days($period);
        $start_date = date('Y-m-d', strtotime("-{$days} days"));
        
        $logs = $this->db->get_results(
            "SELECT * FROM {$this->db->get_table_name('logs')} 
            WHERE created_at >= %s
            ORDER BY created_at DESC",
            $start_date
        );
        
        if ($format === 'json') {
            return json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            $output = "AI SEO Content Generator - 系統日誌匯出\n";
            $output .= "匯出時間：" . current_time('Y-m-d H:i:s') . "\n";
            $output .= "期間：過去 {$days} 天\n";
            $output .= str_repeat("=", 80) . "\n\n";
            
            foreach ($logs as $log) {
                $output .= sprintf(
                    "[%s] [%-8s] [%-15s] %s\n",
                    $log->created_at,
                    strtoupper($log->level),
                    $log->category,
                    $log->message
                );
                
                if (!empty($log->context)) {
                    $output .= "    Context: " . $log->context . "\n";
                }
                
                $output .= "\n";
            }
            
            return $output;
        }
    }
    
    /**
     * 5. 資料庫維護方法
     */
    
    /**
     * 5.1 優化資料表
     */
    public function optimize_tables(): array {
        $tables = $this->db->get_all_tables();
        $results = [];
        
        foreach ($tables as $table) {
            $result = $this->db->get_wpdb()->query("OPTIMIZE TABLE `{$table}`");
            $results[$table] = $result !== false;
        }
        
        $this->logger->info('資料表優化完成', $results);
        
        return [
            'success' => !in_array(false, $results),
            'message' => sprintf('優化了 %d 個資料表', count(array_filter($results))),
            'details' => $results
        ];
    }
    
    /**
     * 5.2 修復資料表
     */
    public function repair_tables(): array {
        $tables = $this->db->get_all_tables();
        $results = [];
        
        foreach ($tables as $table) {
            $result = $this->db->get_wpdb()->query("REPAIR TABLE `{$table}`");
            $results[$table] = $result !== false;
        }
        
        $this->logger->info('資料表修復完成', $results);
        
        return [
            'success' => !in_array(false, $results),
            'message' => sprintf('修復了 %d 個資料表', count(array_filter($results))),
            'details' => $results
        ];
    }
    
    /**
     * 5.3 備份資料
     */
    public function backup_data(): array {
        try {
            $backup_dir = AISC_PLUGIN_DIR . 'exports/backups/';
            if (!file_exists($backup_dir)) {
                wp_mkdir_p($backup_dir);
            }
            
            $backup_file = $backup_dir . 'aisc_backup_' . date('Y-m-d_His') . '.json';
            $backup_data = [];
            
            // 備份所有資料表
            $tables = ['keywords', 'content_history', 'schedules', 'costs', 'performance'];
            
            foreach ($tables as $table) {
                $data = $this->db->get_results(
                    "SELECT * FROM {$this->db->get_table_name($table)}"
                );
                $backup_data[$table] = $data;
            }
            
            // 備份設定
            $backup_data['settings'] = $this->get_all_plugin_options();
            
            // 寫入檔案
            $json = json_encode($backup_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            file_put_contents($backup_file, $json);
            
            // 壓縮檔案
            if (class_exists('ZipArchive')) {
                $zip = new \ZipArchive();
                $zip_file = str_replace('.json', '.zip', $backup_file);
                
                if ($zip->open($zip_file, \ZipArchive::CREATE) === true) {
                    $zip->addFile($backup_file, basename($backup_file));
                    $zip->close();
                    unlink($backup_file);
                    $backup_file = $zip_file;
                }
            }
            
            $this->logger->info('資料備份完成', ['file' => basename($backup_file)]);
            
            return [
                'success' => true,
                'message' => '資料備份成功',
                'file' => $backup_file,
                'size' => filesize($backup_file)
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('資料備份失敗', ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'message' => '資料備份失敗',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 5.4 執行資料庫維護
     */
    public function run_database_maintenance(): void {
        $this->logger->info('開始執行資料庫維護');
        
        // 清理過期日誌
        $log_retention = intval(get_option('aisc_log_retention', 30));
        $this->clear_logs('all', $log_retention);
        
        // 清理過期快取
        $this->db->delete('cache', 'expires_at < NOW() AND expires_at IS NOT NULL');
        
        // 優化資料表
        $this->optimize_tables();
        
        // 更新統計資訊
        $this->update_database_stats();
        
        $this->logger->info('資料庫維護完成');
    }
    
    /**
     * 5.5 重置外掛
     */
    public function reset_plugin(bool $keep_settings = false): array {
        try {
            $this->logger->warning('開始重置外掛', ['keep_settings' => $keep_settings]);
            
            // 停止所有排程
            $this->clear_all_schedules();
            
            // 清空資料表
            $tables = ['keywords', 'content_history', 'schedules', 'costs', 'logs', 'performance', 'cache'];
            
            foreach ($tables as $table) {
                $this->db->truncate_table($table);
            }
            
            // 刪除設定
            if (!$keep_settings) {
                $this->delete_all_plugin_options();
            }
            
            // 重新初始化
            $this->db->create_tables();
            
            $this->logger->info('外掛重置完成');
            
            return [
                'success' => true,
                'message' => '外掛已成功重置',
                'kept_settings' => $keep_settings
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('外掛重置失敗', ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'message' => '外掛重置失敗',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 6. 健康檢查和報告
     */
    
    /**
     * 6.1 執行健康檢查
     */
    public function run_health_check(): void {
        $this->logger->info('開始執行健康檢查');
        
        $checks = $this->run_system_checks();
        $issues = array_filter($checks, fn($check) => $check['status'] !== 'pass');
        
        if (!empty($issues)) {
            $this->logger->warning('健康檢查發現問題', ['issues' => $issues]);
            
            // 發送通知給管理員
            if (count($issues) > 3) {
                $this->send_health_alert($issues);
            }
        } else {
            $this->logger->info('健康檢查通過，系統運作正常');
        }
        
        // 儲存檢查結果
        update_option('aisc_last_health_check', [
            'time' => current_time('mysql'),
            'results' => $checks,
            'issues_count' => count($issues)
        ]);
    }
    
    /**
     * 6.2 發送健康警報
     */
    private function send_health_alert(array $issues): void {
        $message = "AI SEO內容生成器健康檢查發現以下問題：\n\n";
        
        foreach ($issues as $issue) {
            $message .= sprintf(
                "- %s: %s\n",
                $issue['name'],
                $issue['message']
            );
        }
        
        $message .= "\n請盡快檢查並修復這些問題。\n";
        $message .= "診斷工具：" . admin_url('admin.php?page=aisc-diagnostics');
        
        wp_mail(
            get_option('admin_email'),
            sprintf('[%s] AI SEO生成器健康檢查警報', get_bloginfo('name')),
            $message
        );
    }
    
    /**
     * 6.3 生成診斷報告
     */
    public function generate_diagnostic_report(): array {
        $report = [
            'generated_at' => current_time('mysql'),
            'system_info' => $this->get_system_info(),
            'system_checks' => $this->run_system_checks(),
            'database_stats' => $this->get_database_stats(),
            'performance_metrics' => $this->get_performance_metrics(),
            'recent_errors' => $this->get_recent_errors(),
            'recommendations' => $this->get_recommendations()
        ];
        
        return $report;
    }
    
    /**
     * 7. 輔助方法
     */
    
    /**
     * 7.1 轉換為位元組
     */
    private function convert_to_bytes(string $value): int {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $value = (int)$value;
        
        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }
    
    /**
     * 7.2 取得系統資訊
     */
    private function get_system_info(): array {
        global $wp_version;
        
        return [
            'php_version' => PHP_VERSION,
            'wp_version' => $wp_version,
            'plugin_version' => AISC_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'php_sapi' => php_sapi_name(),
            'max_execution_time' => ini_get('max_execution_time'),
            'memory_limit' => ini_get('memory_limit'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'timezone' => date_default_timezone_get(),
            'debug_mode' => WP_DEBUG ? 'Enabled' : 'Disabled'
        ];
    }
    
    /**
     * 7.3 取得資料庫統計
     */
    private function get_database_stats(): array {
        $stats = [];
        $tables = ['keywords', 'content_history', 'schedules', 'costs', 'logs', 'performance'];
        
        foreach ($tables as $table) {
            $count = $this->db->count($table);
            $size = $this->db->get_table_size($table);
            
            $stats[$table] = [
                'row_count' => $count,
                'size' => $size,
                'size_formatted' => size_format($size)
            ];
        }
        
        return $stats;
    }
    
    /**
     * 7.4 取得效能指標
     */
    private function get_performance_metrics(): array {
        // 最近7天的平均生成時間
        $avg_generation_time = $this->db->get_var(
            "SELECT AVG(TIMESTAMPDIFF(SECOND, started_at, completed_at))
            FROM {$this->db->get_table_name('content_history')}
            WHERE completed_at IS NOT NULL
            AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        
        // API錯誤率
        $total_api_calls = $this->db->count('content_history', [
            'created_at' => ['>=', date('Y-m-d', strtotime('-7 days'))]
        ]);
        
        $failed_api_calls = $this->db->count('content_history', [
            'status' => 'failed',
            'created_at' => ['>=', date('Y-m-d', strtotime('-7 days'))]
        ]);
        
        $error_rate = $total_api_calls > 0 ? ($failed_api_calls / $total_api_calls) * 100 : 0;
        
        return [
            'avg_generation_time' => round($avg_generation_time ?: 0, 2),
            'api_error_rate' => round($error_rate, 2),
            'total_api_calls_7d' => $total_api_calls,
            'failed_api_calls_7d' => $failed_api_calls
        ];
    }
    
    /**
     * 7.5 取得最近錯誤
     */
    private function get_recent_errors(int $limit = 10): array {
        return $this->db->get_results(
            "SELECT * FROM {$this->db->get_table_name('logs')}
            WHERE level IN ('error', 'critical')
            ORDER BY created_at DESC
            LIMIT %d",
            $limit
        );
    }
    
    /**
     * 7.6 取得建議
     */
    private function get_recommendations(): array {
        $recommendations = [];
        
        // 檢查系統問題
        $checks = $this->run_system_checks();
        foreach ($checks as $check) {
            if ($check['status'] === 'error') {
                $recommendations[] = [
                    'priority' => 'high',
                    'category' => 'system',
                    'message' => $check['message'],
                    'action' => $this->get_fix_action($check['name'])
                ];
            } elseif ($check['status'] === 'warning') {
                $recommendations[] = [
                    'priority' => 'medium',
                    'category' => 'system',
                    'message' => $check['message'],
                    'action' => $this->get_fix_action($check['name'])
                ];
            }
        }
        
        // 效能建議
        $metrics = $this->get_performance_metrics();
        if ($metrics['api_error_rate'] > 10) {
            $recommendations[] = [
                'priority' => 'high',
                'category' => 'performance',
                'message' => 'API錯誤率過高，可能影響內容生成',
                'action' => '檢查API Key設定和網路連接'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * 7.7 取得修復動作
     */
    private function get_fix_action(string $check_name): string {
        $actions = [
            'PHP版本' => '聯繫主機商升級PHP版本',
            'WordPress版本' => '更新WordPress到最新版本',
            '記憶體限制' => '修改php.ini或.htaccess增加記憶體限制',
            'PHP擴展' => '聯繫主機商安裝缺少的PHP擴展',
            '目錄權限' => '設定目錄權限為755或775',
            '資料庫表' => '重新啟用外掛以建立缺少的資料表',
            'API連接' => '檢查API Key是否正確並確保網路連接正常'
        ];
        
        return $actions[$check_name] ?? '請查看文檔或聯繫技術支援';
    }
    
    /**
     * 7.8 取得期間天數
     */
    private function get_period_days(string $period): int {
        $periods = [
            'today' => 0,
            '7days' => 7,
            '30days' => 30,
            '90days' => 90,
            'all' => 9999
        ];
        
        return $periods[$period] ?? 7;
    }
    
    /**
     * 7.9 取得所有外掛選項
     */
    private function get_all_plugin_options(): array {
        global $wpdb;
        
        $options = $wpdb->get_results(
            "SELECT option_name, option_value 
            FROM {$wpdb->options} 
            WHERE option_name LIKE 'aisc_%'"
        );
        
        $result = [];
        foreach ($options as $option) {
            $result[$option->option_name] = maybe_unserialize($option->option_value);
        }
        
        return $result;
    }
    
    /**
     * 7.10 刪除所有外掛選項
     */
    private function delete_all_plugin_options(): void {
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE 'aisc_%'"
        );
    }
    
    /**
     * 7.11 清除所有排程
     */
    private function clear_all_schedules(): void {
        $schedules = [
            'aisc_keyword_update',
            'aisc_content_generation',
            'aisc_check_schedules',
            'aisc_collect_performance_data',
            'aisc_cleanup_performance_data',
            'aisc_daily_cost_report',
            'aisc_health_check',
            'aisc_db_maintenance'
        ];
        
        foreach ($schedules as $hook) {
            wp_clear_scheduled_hook($hook);
        }
    }
    
    /**
     * 7.12 更新資料庫統計
     */
    private function update_database_stats(): void {
        update_option('aisc_db_stats', [
            'updated_at' => current_time('mysql'),
            'stats' => $this->get_database_stats()
        ]);
    }
    
    /**
     * 8. AJAX處理
     */
    
    /**
     * 8.1 AJAX執行測試
     */
    public function ajax_run_test(): void {
        check_ajax_referer('aisc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('權限不足');
        }
        
        $test_type = sanitize_text_field($_POST['test'] ?? 'all');
        $result = $this->run_test($test_type);
        
        wp_send_json($result);
    }
}
