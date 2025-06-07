<?php
/**
 * 檔案：includes/core/class-logger.php
 * 功能：日誌記錄核心類別
 * 
 * @package AI_SEO_Content_Generator
 * @subpackage Core
 */

namespace AISC\Core;

// 防止直接訪問
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 日誌管理類別
 * 
 * 實施分級日誌系統，支援多種輸出方式
 */
class Logger {
    
    /**
     * 日誌級別常數
     */
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    const LEVEL_CRITICAL = 'critical';
    
    /**
     * 日誌級別權重
     */
    private array $levels = [
        self::LEVEL_DEBUG => 100,
        self::LEVEL_INFO => 200,
        self::LEVEL_WARNING => 300,
        self::LEVEL_ERROR => 400,
        self::LEVEL_CRITICAL => 500
    ];
    
    /**
     * 資料庫實例
     */
    private Database $db;
    
    /**
     * 當前最低記錄級別
     */
    private string $min_level;
    
    /**
     * 日誌檔案路徑
     */
    private string $log_dir;
    
    /**
     * 最大檔案大小（MB）
     */
    private int $max_file_size = 10;
    
    /**
     * 保留天數
     */
    private int $retention_days = 30;
    
    /**
     * 建構函數
     */
    public function __construct() {
        $this->db = new Database();
        $this->min_level = get_option('aisc_log_level', self::LEVEL_INFO);
        $this->log_dir = AISC_PLUGIN_DIR . 'logs/';
        $this->max_file_size = intval(get_option('aisc_log_max_size', 10));
        $this->retention_days = intval(get_option('aisc_log_retention', 30));
        
        // 確保日誌目錄存在
        $this->ensure_log_directory();
        
        // 註冊清理排程
        $this->setup_cleanup_schedule();
    }
    
    /**
     * 1. 主要日誌方法
     */
    
    /**
     * 1.1 記錄日誌
     */
    public function log(string $level, string $message, array $context = []): void {
        // 檢查日誌級別
        if (!$this->should_log($level)) {
            return;
        }
        
        // 準備日誌資料
        $log_data = $this->prepare_log_data($level, $message, $context);
        
        // 寫入資料庫
        $this->write_to_database($log_data);
        
        // 寫入檔案
        $this->write_to_file($log_data);
        
        // 特殊處理
        $this->handle_special_cases($level, $message, $context);
    }
    
    /**
     * 1.2 Debug級別日誌
     */
    public function debug(string $message, array $context = []): void {
        $this->log(self::LEVEL_DEBUG, $message, $context);
    }
    
    /**
     * 1.3 Info級別日誌
     */
    public function info(string $message, array $context = []): void {
        $this->log(self::LEVEL_INFO, $message, $context);
    }
    
    /**
     * 1.4 Warning級別日誌
     */
    public function warning(string $message, array $context = []): void {
        $this->log(self::LEVEL_WARNING, $message, $context);
    }
    
    /**
     * 1.5 Error級別日誌
     */
    public function error(string $message, array $context = []): void {
        $this->log(self::LEVEL_ERROR, $message, $context);
    }
    
    /**
     * 1.6 Critical級別日誌
     */
    public function critical(string $message, array $context = []): void {
        $this->log(self::LEVEL_CRITICAL, $message, $context);
    }
    
    /**
     * 2. 日誌處理方法
     */
    
    /**
     * 2.1 檢查是否應該記錄
     */
    private function should_log(string $level): bool {
        if (!isset($this->levels[$level])) {
            return false;
        }
        
        $min_weight = $this->levels[$this->min_level] ?? 0;
        $level_weight = $this->levels[$level];
        
        return $level_weight >= $min_weight;
    }
    
    /**
     * 2.2 準備日誌資料
     */
    private function prepare_log_data(string $level, string $message, array $context): array {
        $user_id = get_current_user_id();
        
        $data = [
            'level' => $level,
            'message' => $this->interpolate($message, $context),
            'context' => wp_json_encode($context),
            'user_id' => $user_id ?: null,
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'url' => $this->get_current_url(),
            'timestamp' => current_time('mysql'),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']
        ];
        
        // 添加堆疊追蹤（僅限錯誤和關鍵級別）
        if (in_array($level, [self::LEVEL_ERROR, self::LEVEL_CRITICAL])) {
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
            $data['backtrace'] = $this->format_backtrace($backtrace);
        }
        
        return $data;
    }
    
    /**
     * 2.3 字串插值
     */
    private function interpolate(string $message, array $context): string {
        $replace = [];
        
        foreach ($context as $key => $val) {
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = (string) $val;
            }
        }
        
        return strtr($message, $replace);
    }
    
    /**
     * 3. 寫入方法
     */
    
    /**
     * 3.1 寫入資料庫
     */
    private function write_to_database(array $data): void {
        try {
            $db_data = [
                'level' => $data['level'],
                'message' => substr($data['message'], 0, 65535), // TEXT欄位限制
                'context' => $data['context'],
                'user_id' => $data['user_id'],
                'ip_address' => $data['ip_address'],
                'user_agent' => substr($data['user_agent'], 0, 255),
                'url' => substr($data['url'], 0, 2048),
                'created_at' => $data['timestamp']
            ];
            
            $this->db->insert('logs', $db_data);
        } catch (\Exception $e) {
            // 如果資料庫寫入失敗，至少寫入檔案
            error_log('[AISC] Database logging failed: ' . $e->getMessage());
        }
    }
    
    /**
     * 3.2 寫入檔案
     */
    private function write_to_file(array $data): void {
        try {
            $filename = $this->get_log_filename($data['level']);
            $filepath = $this->log_dir . $filename;
            
            // 檢查檔案大小
            if (file_exists($filepath) && filesize($filepath) > $this->max_file_size * 1024 * 1024) {
                $this->rotate_log_file($filepath);
            }
            
            // 格式化日誌行
            $log_line = $this->format_log_line($data);
            
            // 寫入檔案
            file_put_contents($filepath, $log_line, FILE_APPEND | LOCK_EX);
            
        } catch (\Exception $e) {
            error_log('[AISC] File logging failed: ' . $e->getMessage());
        }
    }
    
    /**
     * 4. 檔案管理方法
     */
    
    /**
     * 4.1 取得日誌檔案名稱
     */
    private function get_log_filename(string $level): string {
        $date = date('Y-m-d');
        
        // 關鍵錯誤使用獨立檔案
        if ($level === self::LEVEL_CRITICAL) {
            return "critical-{$date}.log";
        }
        
        // 錯誤級別使用獨立檔案
        if ($level === self::LEVEL_ERROR) {
            return "error-{$date}.log";
        }
        
        // 其他級別使用通用檔案
        return "general-{$date}.log";
    }
    
    /**
     * 4.2 格式化日誌行
     */
    private function format_log_line(array $data): string {
        $format = "[%s] %s.%s: %s | Context: %s | Memory: %s | Time: %.4fs | User: %s | IP: %s | URL: %s\n";
        
        return sprintf(
            $format,
            $data['timestamp'],
            strtoupper($data['level']),
            uniqid(),
            $data['message'],
            $data['context'],
            $this->format_bytes($data['memory_usage']),
            $data['execution_time'],
            $data['user_id'] ?: 'guest',
            $data['ip_address'],
            $data['url']
        );
    }
    
    /**
     * 4.3 輪替日誌檔案
     */
    private function rotate_log_file(string $filepath): void {
        $path_info = pathinfo($filepath);
        $new_filename = sprintf(
            '%s/%s-%s.%s',
            $path_info['dirname'],
            $path_info['filename'],
            date('His'),
            $path_info['extension']
        );
        
        rename($filepath, $new_filename);
        
        // 壓縮舊檔案
        if (function_exists('gzopen')) {
            $gz = gzopen($new_filename . '.gz', 'w9');
            gzwrite($gz, file_get_contents($new_filename));
            gzclose($gz);
            unlink($new_filename);
        }
    }
    
    /**
     * 5. 日誌查詢方法
     */
    
    /**
     * 5.1 查詢日誌
     */
    public function get_logs(array $filters = [], int $limit = 100, int $offset = 0): array {
        $where = [];
        $args = [
            'limit' => $limit,
            'offset' => $offset,
            'orderby' => 'created_at',
            'order' => 'DESC'
        ];
        
        // 級別過濾
        if (!empty($filters['level'])) {
            $where['level'] = $filters['level'];
        }
        
        // 時間範圍過濾
        if (!empty($filters['date_from']) || !empty($filters['date_to'])) {
            // 需要自訂查詢
            return $this->get_logs_with_date_range($filters, $limit, $offset);
        }
        
        // 使用者過濾
        if (!empty($filters['user_id'])) {
            $where['user_id'] = $filters['user_id'];
        }
        
        $logs = $this->db->get_results('logs', $where, $args);
        
        // 解析context
        foreach ($logs as &$log) {
            $log->context = json_decode($log->context, true) ?: [];
        }
        
        return $logs;
    }
    
    /**
     * 5.2 帶日期範圍的查詢
     */
    private function get_logs_with_date_range(array $filters, int $limit, int $offset): array {
        global $wpdb;
        $table_name = $this->db->get_table_name('logs');
        
        $query = "SELECT * FROM $table_name WHERE 1=1";
        $query_args = [];
        
        if (!empty($filters['level'])) {
            $query .= " AND level = %s";
            $query_args[] = $filters['level'];
        }
        
        if (!empty($filters['date_from'])) {
            $query .= " AND created_at >= %s";
            $query_args[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $query .= " AND created_at <= %s";
            $query_args[] = $filters['date_to'];
        }
        
        if (!empty($filters['user_id'])) {
            $query .= " AND user_id = %d";
            $query_args[] = $filters['user_id'];
        }
        
        $query .= " ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $query_args[] = $limit;
        $query_args[] = $offset;
        
        $prepared_query = $wpdb->prepare($query, ...$query_args);
        $logs = $wpdb->get_results($prepared_query);
        
        foreach ($logs as &$log) {
            $log->context = json_decode($log->context, true) ?: [];
        }
        
        return $logs;
    }
    
    /**
     * 5.3 取得日誌統計
     */
    public function get_statistics(string $period = 'today'): array {
        $stats = [
            'total' => 0,
            'by_level' => [],
            'by_hour' => [],
            'top_messages' => [],
            'top_users' => []
        ];
        
        // 計算時間範圍
        $date_from = $this->get_period_start($period);
        $date_to = current_time('mysql');
        
        // 總數和級別統計
        foreach ($this->levels as $level => $weight) {
            $count = $this->db->count('logs', [
                'level' => $level
            ]);
            $stats['by_level'][$level] = $count;
            $stats['total'] += $count;
        }
        
        // 每小時統計
        $stats['by_hour'] = $this->get_hourly_stats($date_from, $date_to);
        
        // 熱門訊息
        $stats['top_messages'] = $this->get_top_messages($date_from, $date_to, 10);
        
        // 活躍使用者
        $stats['top_users'] = $this->get_top_users($date_from, $date_to, 10);
        
        return $stats;
    }
    
    /**
     * 6. 清理和維護方法
     */
    
    /**
     * 6.1 設定清理排程
     */
    private function setup_cleanup_schedule(): void {
        if (!wp_next_scheduled('aisc_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'aisc_cleanup_logs');
        }
        
        add_action('aisc_cleanup_logs', [$this, 'cleanup_old_logs']);
    }
    
    /**
     * 6.2 清理舊日誌
     */
    public function cleanup_old_logs(): void {
        // 清理資料庫日誌
        $db_count = $this->db->clean_old_logs($this->retention_days);
        
        // 清理檔案日誌
        $file_count = $this->cleanup_old_files();
        
        $this->info('日誌清理完成', [
            'db_deleted' => $db_count,
            'files_deleted' => $file_count
        ]);
    }
    
    /**
     * 6.3 清理舊檔案
     */
    private function cleanup_old_files(): int {
        $count = 0;
        $cutoff_date = strtotime("-{$this->retention_days} days");
        
        $files = glob($this->log_dir . '*.log*');
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff_date) {
                if (unlink($file)) {
                    $count++;
                }
            }
        }
        
        return $count;
    }
    
    /**
     * 7. 輔助方法
     */
    
    /**
     * 7.1 取得客戶端IP
     */
    private function get_client_ip(): string {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // 處理多個IP的情況
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * 7.2 取得當前URL
     */
    private function get_current_url(): string {
        if (defined('WP_CLI') && WP_CLI) {
            return 'WP-CLI';
        }
        
        if (defined('DOING_CRON') && DOING_CRON) {
            return 'CRON';
        }
        
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return 'AJAX: ' . ($_REQUEST['action'] ?? 'unknown');
        }
        
        $protocol = is_ssl() ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        
        return $protocol . $host . $uri;
    }
    
    /**
     * 7.3 格式化堆疊追蹤
     */
    private function format_backtrace(array $backtrace): array {
        $formatted = [];
        
        foreach ($backtrace as $i => $trace) {
            $formatted[] = sprintf(
                '#%d %s%s%s() at %s:%s',
                $i,
                $trace['class'] ?? '',
                $trace['type'] ?? '',
                $trace['function'] ?? 'unknown',
                $trace['file'] ?? 'unknown',
                $trace['line'] ?? '?'
            );
        }
        
        return $formatted;
    }
    
    /**
     * 7.4 格式化位元組
     */
    private function format_bytes(int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor((strlen($bytes) - 1) / 3);
        
        return sprintf("%.2f %s", $bytes / pow(1024, $factor), $units[$factor]);
    }
    
    /**
     * 7.5 確保日誌目錄存在
     */
    private function ensure_log_directory(): void {
        if (!file_exists($this->log_dir)) {
            wp_mkdir_p($this->log_dir);
            
            // 建立 .htaccess 保護
            $htaccess = $this->log_dir . '.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, 'deny from all');
            }
            
            // 建立 index.php
            $index = $this->log_dir . 'index.php';
            if (!file_exists($index)) {
                file_put_contents($index, '<?php // Silence is golden');
            }
        }
    }
    
    /**
     * 8. 特殊處理方法
     */
    
    /**
     * 8.1 處理特殊情況
     */
    private function handle_special_cases(string $level, string $message, array $context): void {
        // 關鍵錯誤通知
        if ($level === self::LEVEL_CRITICAL) {
            $this->send_critical_notification($message, $context);
        }
        
        // API錯誤追蹤
        if (isset($context['api_error']) && $context['api_error']) {
            $this->track_api_error($message, $context);
        }
        
        // 成本超支警告
        if (isset($context['cost_overrun']) && $context['cost_overrun']) {
            $this->handle_cost_overrun($message, $context);
        }
    }
    
    /**
     * 8.2 發送關鍵錯誤通知
     */
    private function send_critical_notification(string $message, array $context): void {
        $admin_email = get_option('admin_email');
        
        if (!$admin_email) {
            return;
        }
        
        $subject = '[AI SEO Content Generator] 關鍵錯誤警報';
        
        $body = "網站發生關鍵錯誤，需要立即處理。\n\n";
        $body .= "錯誤訊息：{$message}\n";
        $body .= "發生時間：" . current_time('mysql') . "\n";
        $body .= "詳細資訊：\n" . print_r($context, true);
        
        wp_mail($admin_email, $subject, $body);
    }
    
    /**
     * 8.3 追蹤API錯誤
     */
    private function track_api_error(string $message, array $context): void {
        $error_count = get_transient('aisc_api_error_count') ?: 0;
        $error_count++;
        
        set_transient('aisc_api_error_count', $error_count, HOUR_IN_SECONDS);
        
        // 如果錯誤過多，暫停API呼叫
        if ($error_count > 10) {
            update_option('aisc_api_paused', true);
            update_option('aisc_api_pause_reason', '過多API錯誤');
            
            $this->critical('API已暫停使用', [
                'error_count' => $error_count,
                'last_error' => $message
            ]);
        }
    }
    
    /**
     * 8.4 處理成本超支
     */
    private function handle_cost_overrun(string $message, array $context): void {
        // 暫停所有排程
        update_option('aisc_schedules_paused', true);
        update_option('aisc_pause_reason', '成本超支');
        
        // 記錄事件
        $this->critical('因成本超支暫停所有自動功能', $context);
        
        // 通知管理員
        $this->send_cost_alert($context);
    }
    
    /**
     * 8.5 發送成本警報
     */
    private function send_cost_alert(array $context): void {
        $admin_email = get_option('admin_email');
        
        if (!$admin_email) {
            return;
        }
        
        $subject = '[AI SEO Content Generator] 成本超支警報';
        
        $body = "您的AI內容生成成本已超出預算限制。\n\n";
        $body .= "當前花費：NT$ " . number_format($context['current_cost'] ?? 0, 2) . "\n";
        $body .= "預算限制：NT$ " . number_format($context['budget_limit'] ?? 0, 2) . "\n";
        $body .= "超支金額：NT$ " . number_format($context['overrun'] ?? 0, 2) . "\n\n";
        $body .= "所有自動功能已暫停，請檢查設定。";
        
        wp_mail($admin_email, $subject, $body);
    }
    
    /**
     * 9. 統計輔助方法
     */
    
    /**
     * 9.1 取得時段開始時間
     */
    private function get_period_start(string $period): string {
        switch ($period) {
            case 'today':
                return date('Y-m-d 00:00:00');
            case 'yesterday':
                return date('Y-m-d 00:00:00', strtotime('-1 day'));
            case 'week':
                return date('Y-m-d 00:00:00', strtotime('-7 days'));
            case 'month':
                return date('Y-m-d 00:00:00', strtotime('-30 days'));
            default:
                return date('Y-m-d 00:00:00');
        }
    }
    
    /**
     * 9.2 取得每小時統計
     */
    private function get_hourly_stats(string $date_from, string $date_to): array {
        global $wpdb;
        $table_name = $this->db->get_table_name('logs');
        
        $query = $wpdb->prepare(
            "SELECT HOUR(created_at) as hour, COUNT(*) as count
            FROM $table_name
            WHERE created_at BETWEEN %s AND %s
            GROUP BY HOUR(created_at)
            ORDER BY hour",
            $date_from,
            $date_to
        );
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        $stats = array_fill(0, 24, 0);
        foreach ($results as $row) {
            $stats[$row['hour']] = intval($row['count']);
        }
        
        return $stats;
    }
    
    /**
     * 9.3 取得熱門訊息
     */
    private function get_top_messages(string $date_from, string $date_to, int $limit): array {
        global $wpdb;
        $table_name = $this->db->get_table_name('logs');
        
        $query = $wpdb->prepare(
            "SELECT message, COUNT(*) as count
            FROM $table_name
            WHERE created_at BETWEEN %s AND %s
            GROUP BY message
            ORDER BY count DESC
            LIMIT %d",
            $date_from,
            $date_to,
            $limit
        );
        
        return $wpdb->get_results($query, ARRAY_A);
    }
    
    /**
     * 9.4 取得活躍使用者
     */
    private function get_top_users(string $date_from, string $date_to, int $limit): array {
        global $wpdb;
        $table_name = $this->db->get_table_name('logs');
        
        $query = $wpdb->prepare(
            "SELECT user_id, COUNT(*) as count
            FROM $table_name
            WHERE created_at BETWEEN %s AND %s
            AND user_id IS NOT NULL
            GROUP BY user_id
            ORDER BY count DESC
            LIMIT %d",
            $date_from,
            $date_to,
            $limit
        );
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        // 加入使用者名稱
        foreach ($results as &$row) {
            $user = get_user_by('id', $row['user_id']);
            $row['username'] = $user ? $user->display_name : 'Unknown';
        }
        
        return $results;
    }
    
    /**
     * 10. 匯出功能
     */
    
    /**
     * 10.1 匯出日誌
     */
    public function export_logs(array $filters = [], string $format = 'csv'): string {
        $logs = $this->get_logs($filters, 10000, 0); // 最多匯出10000筆
        
        switch ($format) {
            case 'csv':
                return $this->export_to_csv($logs);
            case 'json':
                return $this->export_to_json($logs);
            default:
                throw new \InvalidArgumentException('不支援的匯出格式');
        }
    }
    
    /**
     * 10.2 匯出為CSV
     */
    private function export_to_csv(array $logs): string {
        $export_dir = AISC_PLUGIN_DIR . 'exports/';
        if (!file_exists($export_dir)) {
            wp_mkdir_p($export_dir);
        }
        
        $filename = 'logs_' . date('Y-m-d_H-i-s') . '.csv';
        $filepath = $export_dir . $filename;
        
        $handle = fopen($filepath, 'w');
        
        // 寫入標題
        fputcsv($handle, [
            'ID', '級別', '訊息', '使用者', 'IP位址', 
            'URL', '建立時間'
        ]);
        
        // 寫入資料
        foreach ($logs as $log) {
            fputcsv($handle, [
                $log->id,
                $log->level,
                $log->message,
                $log->user_id ?: 'Guest',
                $log->ip_address,
                $log->url,
                $log->created_at
            ]);
        }
        
        fclose($handle);
        
        return $filepath;
    }
    
    /**
     * 10.3 匯出為JSON
     */
    private function export_to_json(array $logs): string {
        $export_dir = AISC_PLUGIN_DIR . 'exports/';
        if (!file_exists($export_dir)) {
            wp_mkdir_p($export_dir);
        }
        
        $filename = 'logs_' . date('Y-m-d_H-i-s') . '.json';
        $filepath = $export_dir . $filename;
        
        file_put_contents($filepath, wp_json_encode($logs, JSON_PRETTY_PRINT));
        
        return $filepath;
    }
}