<?php
/**
 * 檔案：includes/core/class-database.php
 * 功能：資料庫管理核心類別
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
 * 資料庫管理類別
 * 
 * 負責建立和管理外掛所需的資料表
 */
class Database {
    
    /**
     * WordPress資料庫物件
     */
    private \wpdb $wpdb;
    
    /**
     * 資料表前綴
     */
    private string $prefix;
    
    /**
     * 建構函數
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->prefix = $wpdb->prefix . 'aisc_';
    }
    
    /**
     * 1. 建立所有資料表
     */
    public function create_tables(): void {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $this->create_keywords_table();
        $this->create_content_history_table();
        $this->create_schedules_table();
        $this->create_costs_table();
        $this->create_logs_table();
        $this->create_performance_table();
        $this->create_cache_table();
        
        update_option('aisc_db_version', AISC_DB_VERSION);
    }
    
    /**
     * 1.1 建立關鍵字資料表
     */
    private function create_keywords_table(): void {
        $table_name = $this->prefix . 'keywords';
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            keyword varchar(255) NOT NULL,
            type varchar(50) NOT NULL DEFAULT 'general',
            search_volume int(11) DEFAULT 0,
            competition_level float DEFAULT 0,
            cpc_value decimal(10,2) DEFAULT 0,
            trend_score float DEFAULT 0,
            priority_score float DEFAULT 0,
            last_used datetime DEFAULT NULL,
            use_count int(11) DEFAULT 0,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY keyword_type (keyword, type),
            KEY type (type),
            KEY priority_score (priority_score),
            KEY status (status)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * 1.2 建立內容歷史資料表
     */
    private function create_content_history_table(): void {
        $table_name = $this->prefix . 'content_history';
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            keyword_id bigint(20) DEFAULT NULL,
            keyword varchar(255) NOT NULL,
            model varchar(50) NOT NULL,
            word_count int(11) NOT NULL,
            image_count int(11) DEFAULT 0,
            generation_time float DEFAULT 0,
            api_cost decimal(10,4) DEFAULT 0,
            categories text,
            seo_score float DEFAULT 0,
            readability_score float DEFAULT 0,
            similarity_score float DEFAULT 0,
            status varchar(20) DEFAULT 'published',
            error_message text,
            meta_data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY keyword_id (keyword_id),
            KEY model (model),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * 1.3 建立排程資料表
     */
    private function create_schedules_table(): void {
        $table_name = $this->prefix . 'schedules';
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            type varchar(50) NOT NULL DEFAULT 'general',
            frequency varchar(50) NOT NULL,
            next_run datetime DEFAULT NULL,
            last_run datetime DEFAULT NULL,
            keyword_count int(11) DEFAULT 1,
            content_settings longtext,
            status varchar(20) DEFAULT 'active',
            run_count int(11) DEFAULT 0,
            success_count int(11) DEFAULT 0,
            failure_count int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY type (type),
            KEY status (status),
            KEY next_run (next_run)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * 1.4 建立成本資料表
     */
    private function create_costs_table(): void {
        $table_name = $this->prefix . 'costs';
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            date date NOT NULL,
            service varchar(50) NOT NULL,
            model varchar(100) DEFAULT NULL,
            tokens_used int(11) DEFAULT 0,
            api_calls int(11) DEFAULT 0,
            cost_usd decimal(10,6) DEFAULT 0,
            cost_twd decimal(10,2) DEFAULT 0,
            post_ids text,
            details longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY date (date),
            KEY service (service),
            KEY model (model)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * 1.5 建立日誌資料表
     */
    private function create_logs_table(): void {
        $table_name = $this->prefix . 'logs';
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            level varchar(20) NOT NULL,
            message text NOT NULL,
            context longtext,
            user_id bigint(20) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text,
            url text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY level (level),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * 1.6 建立效能追蹤資料表
     */
    private function create_performance_table(): void {
        $table_name = $this->prefix . 'performance';
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            date date NOT NULL,
            pageviews int(11) DEFAULT 0,
            unique_visitors int(11) DEFAULT 0,
            avg_time_on_page float DEFAULT 0,
            bounce_rate float DEFAULT 0,
            scroll_depth float DEFAULT 0,
            social_shares int(11) DEFAULT 0,
            organic_traffic int(11) DEFAULT 0,
            direct_traffic int(11) DEFAULT 0,
            referral_traffic int(11) DEFAULT 0,
            engagement_score float DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY post_date (post_id, date),
            KEY date (date),
            KEY pageviews (pageviews),
            KEY engagement_score (engagement_score)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * 1.7 建立快取資料表
     */
    private function create_cache_table(): void {
        $table_name = $this->prefix . 'cache';
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            cache_key varchar(255) NOT NULL,
            cache_value longtext NOT NULL,
            expiration datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (cache_key),
            KEY expiration (expiration)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * 2. 資料操作方法
     */
    
    /**
     * 2.1 插入資料
     */
    public function insert(string $table, array $data): int|false {
        $table_name = $this->prefix . $table;
        $result = $this->wpdb->insert($table_name, $data);
        
        if ($result === false) {
            $this->log_db_error('insert', $table);
            return false;
        }
        
        return $this->wpdb->insert_id;
    }
    
    /**
     * 2.2 更新資料
     */
    public function update(string $table, array $data, array $where): int|false {
        $table_name = $this->prefix . $table;
        $result = $this->wpdb->update($table_name, $data, $where);
        
        if ($result === false) {
            $this->log_db_error('update', $table);
            return false;
        }
        
        return $result;
    }
    
    /**
     * 2.3 刪除資料
     */
    public function delete(string $table, array $where): int|false {
        $table_name = $this->prefix . $table;
        $result = $this->wpdb->delete($table_name, $where);
        
        if ($result === false) {
            $this->log_db_error('delete', $table);
            return false;
        }
        
        return $result;
    }
    
    /**
     * 2.4 查詢單筆資料
     */
    public function get_row(string $table, array $where = [], string $output = OBJECT): mixed {
        $table_name = $this->prefix . $table;
        
        $query = "SELECT * FROM $table_name";
        if (!empty($where)) {
            $conditions = [];
            foreach ($where as $key => $value) {
                $conditions[] = $this->wpdb->prepare("$key = %s", $value);
            }
            $query .= " WHERE " . implode(' AND ', $conditions);
        }
        
        return $this->wpdb->get_row($query, $output);
    }
    
    /**
     * 2.5 查詢多筆資料
     */
    public function get_results(string $table, array $where = [], array $args = []): array {
        $table_name = $this->prefix . $table;
        
        $defaults = [
            'orderby' => 'id',
            'order' => 'DESC',
            'limit' => 0,
            'offset' => 0,
            'output' => OBJECT
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $query = "SELECT * FROM $table_name";
        
        // WHERE條件
        if (!empty($where)) {
            $conditions = [];
            foreach ($where as $key => $value) {
                if (is_array($value)) {
                    $placeholders = array_fill(0, count($value), '%s');
                    $conditions[] = $this->wpdb->prepare(
                        "$key IN (" . implode(',', $placeholders) . ")",
                        ...$value
                    );
                } else {
                    $conditions[] = $this->wpdb->prepare("$key = %s", $value);
                }
            }
            $query .= " WHERE " . implode(' AND ', $conditions);
        }
        
        // ORDER BY
        $query .= sprintf(" ORDER BY %s %s", 
            esc_sql($args['orderby']), 
            esc_sql($args['order'])
        );
        
        // LIMIT
        if ($args['limit'] > 0) {
            $query .= sprintf(" LIMIT %d", intval($args['limit']));
            if ($args['offset'] > 0) {
                $query .= sprintf(" OFFSET %d", intval($args['offset']));
            }
        }
        
        return $this->wpdb->get_results($query, $args['output']);
    }
    
    /**
     * 3. 統計方法
     */
    
    /**
     * 3.1 計算總數
     */
    public function count(string $table, array $where = []): int {
        $table_name = $this->prefix . $table;
        
        $query = "SELECT COUNT(*) FROM $table_name";
        
        if (!empty($where)) {
            $conditions = [];
            foreach ($where as $key => $value) {
                $conditions[] = $this->wpdb->prepare("$key = %s", $value);
            }
            $query .= " WHERE " . implode(' AND ', $conditions);
        }
        
        return intval($this->wpdb->get_var($query));
    }
    
    /**
     * 3.2 計算總和
     */
    public function sum(string $table, string $column, array $where = []): float {
        $table_name = $this->prefix . $table;
        
        $query = sprintf("SELECT SUM(%s) FROM %s", 
            esc_sql($column), 
            $table_name
        );
        
        if (!empty($where)) {
            $conditions = [];
            foreach ($where as $key => $value) {
                $conditions[] = $this->wpdb->prepare("$key = %s", $value);
            }
            $query .= " WHERE " . implode(' AND ', $conditions);
        }
        
        return floatval($this->wpdb->get_var($query) ?? 0);
    }
    
    /**
     * 3.3 計算平均值
     */
    public function avg(string $table, string $column, array $where = []): float {
        $table_name = $this->prefix . $table;
        
        $query = sprintf("SELECT AVG(%s) FROM %s", 
            esc_sql($column), 
            $table_name
        );
        
        if (!empty($where)) {
            $conditions = [];
            foreach ($where as $key => $value) {
                $conditions[] = $this->wpdb->prepare("$key = %s", $value);
            }
            $query .= " WHERE " . implode(' AND ', $conditions);
        }
        
        return floatval($this->wpdb->get_var($query) ?? 0);
    }
    
    /**
     * 4. 快取方法
     */
    
    /**
     * 4.1 設定快取
     */
    public function set_cache(string $key, mixed $value, int $expiration = 3600): bool {
        $this->clear_expired_cache();
        
        $data = [
            'cache_key' => $key,
            'cache_value' => maybe_serialize($value),
            'expiration' => date('Y-m-d H:i:s', time() + $expiration)
        ];
        
        $existing = $this->get_cache($key);
        if ($existing !== false) {
            return $this->update('cache', 
                ['cache_value' => $data['cache_value'], 'expiration' => $data['expiration']], 
                ['cache_key' => $key]
            ) !== false;
        }
        
        return $this->insert('cache', $data) !== false;
    }
    
    /**
     * 4.2 取得快取
     */
    public function get_cache(string $key): mixed {
        $table_name = $this->prefix . 'cache';
        
        $query = $this->wpdb->prepare(
            "SELECT cache_value FROM $table_name 
            WHERE cache_key = %s 
            AND expiration > %s",
            $key,
            current_time('mysql')
        );
        
        $value = $this->wpdb->get_var($query);
        
        if ($value === null) {
            return false;
        }
        
        return maybe_unserialize($value);
    }
    
    /**
     * 4.3 刪除快取
     */
    public function delete_cache(string $key): bool {
        return $this->delete('cache', ['cache_key' => $key]) !== false;
    }
    
    /**
     * 4.4 清除過期快取
     */
    public function clear_expired_cache(): int {
        $table_name = $this->prefix . 'cache';
        
        $query = $this->wpdb->prepare(
            "DELETE FROM $table_name WHERE expiration < %s",
            current_time('mysql')
        );
        
        return $this->wpdb->query($query);
    }
    
    /**
     * 5. 維護方法
     */
    
    /**
     * 5.1 優化資料表
     */
    public function optimize_tables(): array {
        $tables = [
            'keywords', 'content_history', 'schedules', 
            'costs', 'logs', 'performance', 'cache'
        ];
        
        $results = [];
        
        foreach ($tables as $table) {
            $table_name = $this->prefix . $table;
            $result = $this->wpdb->query("OPTIMIZE TABLE $table_name");
            $results[$table] = $result !== false;
        }
        
        return $results;
    }
    
    /**
     * 5.2 清理舊日誌
     */
    public function clean_old_logs(int $days = 30): int {
        $table_name = $this->prefix . 'logs';
        
        $query = $this->wpdb->prepare(
            "DELETE FROM $table_name WHERE created_at < %s",
            date('Y-m-d H:i:s', strtotime("-$days days"))
        );
        
        return $this->wpdb->query($query);
    }
    
    /**
     * 5.3 備份資料表
     */
    public function backup_table(string $table): string|false {
        $table_name = $this->prefix . $table;
        $backup_dir = AISC_PLUGIN_DIR . 'exports/';
        
        if (!file_exists($backup_dir)) {
            wp_mkdir_p($backup_dir);
        }
        
        $filename = sprintf('%s_backup_%s.sql', 
            $table, 
            date('Y-m-d_H-i-s')
        );
        
        $filepath = $backup_dir . $filename;
        
        // 取得資料表結構
        $create = $this->wpdb->get_row(
            "SHOW CREATE TABLE $table_name", 
            ARRAY_A
        );
        
        if (!$create) {
            return false;
        }
        
        $sql = $create['Create Table'] . ";\n\n";
        
        // 取得資料
        $rows = $this->wpdb->get_results(
            "SELECT * FROM $table_name", 
            ARRAY_A
        );
        
        if (!empty($rows)) {
            foreach ($rows as $row) {
                $values = array_map([$this->wpdb, 'prepare'], 
                    array_fill(0, count($row), '%s'), 
                    array_values($row)
                );
                
                $sql .= sprintf(
                    "INSERT INTO `%s` VALUES (%s);\n",
                    $table_name,
                    implode(',', $values)
                );
            }
        }
        
        if (file_put_contents($filepath, $sql) !== false) {
            return $filepath;
        }
        
        return false;
    }
    
    /**
     * 6. 資料表檢查和修復
     */
    
    /**
     * 6.1 檢查資料表是否存在
     */
    public function table_exists(string $table): bool {
        $table_name = $this->prefix . $table;
        
        $query = $this->wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_name
        );
        
        return $this->wpdb->get_var($query) === $table_name;
    }
    
    /**
     * 6.2 修復資料表
     */
    public function repair_table(string $table): bool {
        $table_name = $this->prefix . $table;
        
        $result = $this->wpdb->query("REPAIR TABLE $table_name");
        
        return $result !== false;
    }
    
    /**
     * 6.3 檢查資料表結構
     */
    public function check_table_structure(): array {
        $tables = [
            'keywords', 'content_history', 'schedules', 
            'costs', 'logs', 'performance', 'cache'
        ];
        
        $results = [];
        
        foreach ($tables as $table) {
            $exists = $this->table_exists($table);
            $results[$table] = [
                'exists' => $exists,
                'status' => $exists ? 'OK' : 'Missing'
            ];
            
            if ($exists) {
                $table_name = $this->prefix . $table;
                $check = $this->wpdb->get_row(
                    "CHECK TABLE $table_name",
                    ARRAY_A
                );
                
                if ($check && isset($check['Msg_text'])) {
                    $results[$table]['status'] = $check['Msg_text'];
                }
            }
        }
        
        return $results;
    }
    
    /**
     * 7. 錯誤處理
     */
    
    /**
     * 7.1 記錄資料庫錯誤
     */
    private function log_db_error(string $operation, string $table): void {
        if ($this->wpdb->last_error) {
            error_log(sprintf(
                '[AISC Database Error] Operation: %s, Table: %s, Error: %s',
                $operation,
                $table,
                $this->wpdb->last_error
            ));
        }
    }
    
    /**
     * 7.2 取得最後錯誤
     */
    public function get_last_error(): string {
        return $this->wpdb->last_error;
    }
    
    /**
     * 8. 資料表前綴
     */
    public function get_table_name(string $table): string {
        return $this->prefix . $table;
    }
    
    /**
     * 9. 原生查詢
     */
    public function query(string $sql): int|bool {
        return $this->wpdb->query($sql);
    }
    
    /**
     * 10. 預備語句
     */
    public function prepare(string $query, ...$args): string {
        return $this->wpdb->prepare($query, ...$args);
    }
}