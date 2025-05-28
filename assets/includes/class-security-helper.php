<?php
/**
 * 安全助手類別
 */
class AICG_Security_Helper {
    
    /**
     * 清理輸入資料
     * @param mixed $input
     * @return mixed
     */
    public static function sanitize_input($input) {
        if (is_array($input)) {
            return array_map(array(__CLASS__, 'sanitize_input'), $input);
        }
        
        if (is_string($input)) {
            return sanitize_text_field($input);
        }
        
        return $input;
    }
    
    /**
     * 驗證 nonce
     * @param string $nonce
     * @param string $action
     * @return bool
     */
    public static function verify_nonce($nonce, $action = 'aicg_ajax_nonce') {
        return wp_verify_nonce($nonce, $action);
    }
    
    /**
     * 檢查用戶權限
     * @param string $capability
     * @return bool
     */
    public static function check_permission($capability = 'manage_options') {
        return current_user_can($capability);
    }
    
    /**
     * 清理 HTML 內容
     * @param string $content
     * @param array $allowed_tags
     * @return string
     */
    public static function sanitize_html($content, $allowed_tags = null) {
        if ($allowed_tags === null) {
            $allowed_tags = array(
                'a' => array(
                    'href' => array(),
                    'title' => array(),
                    'target' => array(),
                    'rel' => array()
                ),
                'br' => array(),
                'em' => array(),
                'strong' => array(),
                'b' => array(),
                'i' => array(),
                'u' => array(),
                'p' => array(
                    'class' => array(),
                    'id' => array()
                ),
                'h1' => array('class' => array(), 'id' => array()),
                'h2' => array('class' => array(), 'id' => array()),
                'h3' => array('class' => array(), 'id' => array()),
                'h4' => array('class' => array(), 'id' => array()),
                'h5' => array('class' => array(), 'id' => array()),
                'h6' => array('class' => array(), 'id' => array()),
                'ul' => array('class' => array()),
                'ol' => array('class' => array()),
                'li' => array('class' => array()),
                'blockquote' => array('class' => array(), 'cite' => array()),
                'img' => array(
                    'src' => array(),
                    'alt' => array(),
                    'title' => array(),
                    'width' => array(),
                    'height' => array(),
                    'class' => array()
                ),
                'div' => array('class' => array(), 'id' => array()),
                'span' => array('class' => array(), 'id' => array()),
                'pre' => array('class' => array()),
                'code' => array('class' => array()),
                'table' => array('class' => array()),
                'thead' => array(),
                'tbody' => array(),
                'tr' => array(),
                'th' => array('colspan' => array(), 'rowspan' => array()),
                'td' => array('colspan' => array(), 'rowspan' => array())
            );
        }
        
        return wp_kses($content, $allowed_tags);
    }
    
    /**
     * 驗證 URL
     * @param string $url
     * @return string|false
     */
    public static function validate_url($url) {
        $url = esc_url_raw($url);
        
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }
        
        return false;
    }
    
    /**
     * 加密敏感資料
     * @param string $data
     * @return string
     */
    public static function encrypt_data($data) {
        if (empty($data)) {
            return '';
        }
        
        $key = wp_salt('auth');
        $encrypted = base64_encode($data ^ $key);
        
        return $encrypted;
    }
    
    /**
     * 解密敏感資料
     * @param string $encrypted_data
     * @return string
     */
    public static function decrypt_data($encrypted_data) {
        if (empty($encrypted_data)) {
            return '';
        }
        
        $key = wp_salt('auth');
        $decrypted = base64_decode($encrypted_data) ^ $key;
        
        return $decrypted;
    }
    
    /**
     * 檢查是否為安全的檔案類型
     * @param string $filename
     * @return bool
     */
    public static function is_safe_file_type($filename) {
        $allowed_types = array(
            'jpg', 'jpeg', 'png', 'gif', 'webp',
            'pdf', 'doc', 'docx', 'xls', 'xlsx',
            'txt', 'csv', 'json', 'xml'
        );
        
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        return in_array($extension, $allowed_types);
    }
    
    /**
     * 防止 XSS 攻擊
     * @param string $string
     * @return string
     */
    public static function prevent_xss($string) {
        // 移除所有 JavaScript 事件處理器
        $string = preg_replace('/on\w+\s*=\s*["\'][^"\']*["\']/i', '', $string);
        
        // 移除 JavaScript 協議
        $string = preg_replace('/javascript\s*:/i', '', $string);
        
        // 移除 script 標籤
        $string = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $string);
        
        return esc_html($string);
    }
    
    /**
     * 生成安全的隨機字串
     * @param int $length
     * @return string
     */
    public static function generate_random_string($length = 32) {
        return wp_generate_password($length, false);
    }
    
    /**
     * 驗證 API Key 格式
     * @param string $api_key
     * @param string $type
     * @return bool
     */
    public static function validate_api_key($api_key, $type = 'openai') {
        if (empty($api_key)) {
            return false;
        }
        
        switch ($type) {
            case 'openai':
                // OpenAI API Key 格式: sk-...
                return preg_match('/^sk-[a-zA-Z0-9]{48}$/', $api_key);
                
            case 'volcengine':
                // 火山引擎 Access Key ID 格式
                return preg_match('/^[A-Z0-9]{20,}$/', $api_key);
                
            default:
                // 基本驗證：至少包含字母和數字
                return preg_match('/^[a-zA-Z0-9\-_]+$/', $api_key) && strlen($api_key) >= 10;
        }
    }
    
    /**
     * 記錄安全事件
     * @param string $event_type
     * @param array $data
     */
    public static function log_security_event($event_type, $data = array()) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'event_type' => $event_type,
            'user_id' => get_current_user_id(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'data' => $data
        );
        
        error_log('AICG Security Event: ' . json_encode($log_entry));
    }
}