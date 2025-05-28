<?php
/**
 * 內容生成器 - 完整修正版
 */
class AICG_Content_Generator {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // 初始化
    }
    
    /**
     * 生成單篇文章
     * @return array
     */
    public function generate_single_post() {
        global $wpdb;
        
        // 記錄開始時間
        $start_time = microtime(true);
        
        try {
            // 獲取設定
            $min_words = get_option('aicg_min_word_count', 1000);
            $max_words = get_option('aicg_max_word_count', 2000);
            $keywords_per_post = get_option('aicg_keywords_per_post', 3);
            $keyword_density = get_option('aicg_keyword_density', 2);
            $default_category = get_option('aicg_default_category', 1);
            $default_author = get_option('aicg_default_author', 1);
            $post_status = get_option('aicg_post_status', 'publish');
            $include_images = get_option('aicg_include_images', 1);
            $image_source = get_option('aicg_image_source', 'volcengine');
            $keyword_preference = get_option('aicg_keyword_preference', 'mixed');
            
            // 獲取關鍵字
            $keyword_fetcher = AICG_Keyword_Fetcher::get_instance();
            
            // 根據偏好決定使用哪種類型的關鍵字
            $selected_type = '';
            $all_keywords = array();
            
            if ($keyword_preference === 'mixed') {
                // 混合使用：隨機選擇類型
                $keyword_types = ['taiwan', 'casino'];
                $selected_type = $keyword_types[array_rand($keyword_types)];
            } elseif ($keyword_preference === 'taiwan') {
                // 優先台灣關鍵字
                $selected_type = 'taiwan';
            } elseif ($keyword_preference === 'casino') {
                // 優先娛樂城關鍵字
                $selected_type = 'casino';
            }
            
            // 獲取關鍵字
            $all_keywords = $keyword_fetcher->get_random_keywords($selected_type, $keywords_per_post);
            
            if (empty($all_keywords)) {
                // 如果選定類型沒有關鍵字，嘗試另一種類型
                $other_type = ($selected_type === 'taiwan') ? 'casino' : 'taiwan';
                $all_keywords = $keyword_fetcher->get_random_keywords($other_type, $keywords_per_post);
                $selected_type = $other_type;
                
                if (empty($all_keywords)) {
                    throw new Exception('沒有可用的關鍵字，請先抓取關鍵字');
                }
            }
            
            error_log('AICG: 使用 ' . $selected_type . ' 類型關鍵字: ' . implode(', ', $all_keywords));
            
            // 生成文章標題
            $title = $this->generate_title($all_keywords);
            if (!$title) {
                throw new Exception('無法生成文章標題');
            }
            
            // 生成文章內容
            $content = $this->generate_content($title, $all_keywords, $min_words, $max_words, $keyword_density);
            if (!$content) {
                throw new Exception('無法生成文章內容');
            }
            
            // 自動偵測或使用預設分類
            $category_id = $this->determine_category($title, $content, $all_keywords, $default_category);
            
            // 生成摘要
            $excerpt = $this->generate_excerpt($content);
            
            // 準備文章資料
            $post_data = array(
                'post_title'    => $title,
                'post_content'  => $content,
                'post_excerpt'  => $excerpt,
                'post_status'   => $post_status,
                'post_author'   => $default_author,
                'post_category' => array($category_id),
                'meta_input'    => array(
                    '_aicg_keywords' => implode(', ', $all_keywords),
                    '_aicg_keyword_type' => $selected_type,
                    '_aicg_generated' => 1,
                    '_aicg_generation_time' => current_time('mysql')
                )
            );
            
            // 創建文章
            $post_id = wp_insert_post($post_data);
            
            if (is_wp_error($post_id)) {
                throw new Exception('創建文章失敗: ' . $post_id->get_error_message());
            }
            
            // 生成並設定特色圖片
            if ($include_images && $image_source !== 'none') {
                $this->generate_and_set_featured_image($post_id, $title, $content, $all_keywords, $image_source);
            }
            
            // 設定標籤
            wp_set_post_tags($post_id, $all_keywords);
            
            // 記錄生成日誌
            $log_table = $wpdb->prefix . 'aicg_generation_log';
            $wpdb->insert(
                $log_table,
                array(
                    'post_id' => $post_id,
                    'keywords_used' => implode(', ', $all_keywords),
                    'generation_time' => current_time('mysql'),
                    'status' => 'success'
                ),
                array('%d', '%s', '%s', '%s')
            );
            
            // 計算執行時間
            $execution_time = round(microtime(true) - $start_time, 2);
            
            return array(
                'success' => true,
                'post_id' => $post_id,
                'title' => $title,
                'message' => sprintf('文章生成成功 (耗時 %s 秒)', $execution_time),
                'execution_time' => $execution_time
            );
            
        } catch (Exception $e) {
            // 記錄錯誤日誌
            $log_table = $wpdb->prefix . 'aicg_generation_log';
            $wpdb->insert(
                $log_table,
                array(
                    'keywords_used' => isset($all_keywords) ? implode(', ', $all_keywords) : '',
                    'generation_time' => current_time('mysql'),
                    'status' => 'failed',
                    'error_message' => $e->getMessage()
                ),
                array('%s', '%s', '%s', '%s')
            );
            
            error_log('AICG 生成錯誤: ' . $e->getMessage());
            
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * 生成文章標題
     * @param array $keywords
     * @return string|false
     */
    private function generate_title($keywords) {
        // 重置實例以確保使用最新設定
        AICG_OpenAI_Handler::reset_instance();
        $openai = AICG_OpenAI_Handler::get_instance();
        
        // 使用 OpenAI handler 的 generate_title 方法
        $result = $openai->generate_title($keywords);
        
        if ($result['success']) {
            return $result['title'];
        } elseif (isset($result['error'])) {
            error_log('AICG: 生成標題失敗 - ' . $result['error']);
        }
        
        return false;
    }
    
    /**
     * 生成文章內容
     * @param string $title
     * @param array $keywords
     * @param int $min_words
     * @param int $max_words
     * @param float $keyword_density
     * @return string|false
     */
    private function generate_content($title, $keywords, $min_words, $max_words, $keyword_density) {
        // 重置實例以確保使用最新設定
        AICG_OpenAI_Handler::reset_instance();
        $openai = AICG_OpenAI_Handler::get_instance();
        
        // 使用標準的 generate_content 方法
        $result = $openai->generate_content(array(
            'title' => $title,
            'keywords' => $keywords,
            'min_words' => $min_words,
            'max_words' => $max_words
        ));
        
        if ($result['success']) {
            $content = $result['content'];
            
            // 後處理：確保關鍵字密度
            $content = $this->optimize_keyword_density($content, $keywords, $keyword_density);
            
            return $content;
        } elseif (isset($result['error'])) {
            error_log('AICG: 生成內容失敗 - ' . $result['error']);
        }
        
        return false;
    }
    
    /**
     * 決定文章分類
     * @param string $title
     * @param string $content
     * @param array $keywords
     * @param int $default_category
     * @return int
     */
    private function determine_category($title, $content, $keywords, $default_category) {
        // 先嘗試使用本地分類偵測器
        $category_detector = AICG_Category_Detector::get_instance();
        $detected_category = $category_detector->detect_category($title, $content, $keywords);
        
        if ($detected_category) {
            return $detected_category;
        }
        
        // 如果本地偵測失敗，嘗試使用 AI 分析
        $openai = AICG_OpenAI_Handler::get_instance();
        $ai_result = $openai->analyze_category($content);
        
        if ($ai_result['success'] && !empty($ai_result['categories'])) {
            return $ai_result['categories'][0];
        }
        
        // 都失敗時使用預設分類
        return $default_category;
    }
    
    /**
     * 生成文章摘要
     * @param string $content
     * @return string
     */
    private function generate_excerpt($content) {
        $openai = AICG_OpenAI_Handler::get_instance();
        $result = $openai->generate_excerpt($content);
        
        if ($result['success']) {
            return $result['excerpt'];
        }
        
        // 備用方案
        return wp_trim_words(strip_tags($content), 55);
    }
    
    /**
     * 優化關鍵字密度
     * @param string $content
     * @param array $keywords
     * @param float $target_density
     * @return string
     */
    private function optimize_keyword_density($content, $keywords, $target_density) {
        // 計算當前關鍵字密度
        $total_words = str_word_count(strip_tags($content), 0, 'UTF-8');
        
        foreach ($keywords as $keyword) {
            $count = substr_count($content, $keyword);
            $current_density = ($count / $total_words) * 100;
            
            // 如果密度太低，在適當位置增加關鍵字
            if ($current_density < $target_density * 0.8) {
                // 在段落結尾加入關鍵字相關句子
                $content = preg_replace(
                    '/(<\/p>)/',
                    sprintf(' 關於%s的更多資訊，值得深入了解。$1', $keyword),
                    $content,
                    1
                );
            }
        }
        
        return $content;
    }
    
    /**
     * 生成並設定特色圖片
     * @param int $post_id
     * @param string $title
     * @param string $content
     * @param array $keywords
     * @param string $image_source
     */
    private function generate_and_set_featured_image($post_id, $title, $content, $keywords, $image_source) {
        $attachment_id = false;
        
        if ($image_source === 'volcengine') {
            // 使用火山引擎 AI 生成圖片
            $jimeng = AICG_Jimeng_Handler::get_instance();
            $attachment_id = $jimeng->generate_post_image($title, $content, $keywords);
        } elseif ($image_source === 'unsplash') {
            // 使用 Unsplash 圖片
            $attachment_id = $this->fetch_unsplash_image($keywords);
        }
        
        if ($attachment_id) {
            set_post_thumbnail($post_id, $attachment_id);
            error_log('AICG: 成功設定特色圖片 ID: ' . $attachment_id);
        } else {
            error_log('AICG: 無法生成或獲取特色圖片');
        }
    }
    
    /**
     * 從 Unsplash 獲取圖片
     * @param array $keywords
     * @return int|false
     */
    private function fetch_unsplash_image($keywords) {
        $access_key = get_option('aicg_unsplash_access_key');
        
        if (empty($access_key)) {
            return false;
        }
        
        // 使用第一個關鍵字搜尋
        $query = urlencode($keywords[0]);
        $api_url = "https://api.unsplash.com/photos/random?query={$query}&client_id={$access_key}";
        
        $response = wp_remote_get($api_url);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($data['urls']['regular'])) {
            // 下載圖片
            $image_url = $data['urls']['regular'];
            $photographer = isset($data['user']['name']) ? $data['user']['name'] : 'Unsplash';
            
            $response = wp_remote_get($image_url);
            if (is_wp_error($response)) {
                return false;
            }
            
            $image_data = wp_remote_retrieve_body($response);
            $filename = 'unsplash-' . sanitize_title($keywords[0]) . '-' . time() . '.jpg';
            
            $upload = wp_upload_bits($filename, null, $image_data);
            
            if ($upload['error']) {
                return false;
            }
            
            // 創建附件
            $attachment = array(
                'post_mime_type' => 'image/jpeg',
                'post_title' => sprintf('Photo by %s on Unsplash', $photographer),
                'post_content' => '',
                'post_status' => 'inherit'
            );
            
            $attach_id = wp_insert_attachment($attachment, $upload['file']);
            
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attach_data = wp_generate_attachment_metadata($attach_id, $upload['file']);
            wp_update_attachment_metadata($attach_id, $attach_data);
            
            return $attach_id;
        }
        
        return false;
    }
}