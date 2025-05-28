<?php
/**
 * 內容生成器 - 完整修正版（支援長文章和分類系統）
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
            
            // 驗證字數設定
            $min_words = max(500, min(50000, intval($min_words)));
            $max_words = max($min_words, min(50000, intval($max_words)));
            
            error_log('AICG: 生成文章 - 字數範圍: ' . $min_words . ' - ' . $max_words);
            
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
            
            // 生成文章內容（支援長文章）
            $content = $this->generate_content($title, $all_keywords, $min_words, $max_words, $keyword_density);
            if (!$content) {
                throw new Exception('無法生成文章內容');
            }
            
            // 使用新的分類偵測系統
            $category_detector = AICG_Category_Detector::get_instance();
            $category_ids = $category_detector->detect_category($title, $content, $all_keywords);
            
            error_log('AICG: 偵測到的分類ID: ' . implode(', ', $category_ids));
            
            // 生成摘要
            $excerpt = $this->generate_excerpt($content);
            
            // 準備文章資料
            $post_data = array(
                'post_title'    => $title,
                'post_content'  => $content,
                'post_excerpt'  => $excerpt,
                'post_status'   => $post_status,
                'post_author'   => $default_author,
                'post_category' => $category_ids, // 使用偵測到的分類陣列
                'meta_input'    => array(
                    '_aicg_keywords' => implode(', ', $all_keywords),
                    '_aicg_keyword_type' => $selected_type,
                    '_aicg_generated' => 1,
                    '_aicg_generation_time' => current_time('mysql'),
                    '_aicg_word_count' => str_word_count(strip_tags($content))
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
                'message' => sprintf('文章生成成功 (耗時 %s 秒，字數 %d)', $execution_time, str_word_count(strip_tags($content))),
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
     * 生成文章內容（支援長文章）
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
        
        // 對於超長文章，需要分段生成
        if ($max_words > 10000) {
            return $this->generate_long_content($openai, $title, $keywords, $min_words, $max_words, $keyword_density);
        }
        
        // 標準長度文章
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
     * 生成超長文章（分段生成）
     * @param AICG_OpenAI_Handler $openai
     * @param string $title
     * @param array $keywords
     * @param int $min_words
     * @param int $max_words
     * @param float $keyword_density
     * @return string|false
     */
    private function generate_long_content($openai, $title, $keywords, $min_words, $max_words, $keyword_density) {
        error_log('AICG: 開始生成長文章，目標字數: ' . $min_words . '-' . $max_words);
        
        // 計算需要生成的段落數
        $words_per_section = 5000; // 每個段落的目標字數
        $sections_needed = ceil($max_words / $words_per_section);
        
        // 先生成文章大綱
        $outline = $this->generate_article_outline($openai, $title, $keywords, $sections_needed);
        if (!$outline) {
            error_log('AICG: 無法生成文章大綱');
            return false;
        }
        
        $full_content = '';
        $current_word_count = 0;
        
        // 逐段生成內容
        foreach ($outline as $index => $section) {
            error_log('AICG: 生成第 ' . ($index + 1) . ' 段，標題: ' . $section);
            
            // 計算這一段需要的字數
            $remaining_words = $max_words - $current_word_count;
            $section_words = min($words_per_section, $remaining_words);
            
            if ($section_words < 500) {
                break; // 如果剩餘字數太少，停止生成
            }
            
            // 生成段落內容
            $section_content = $this->generate_section_content(
                $openai, 
                $title, 
                $section, 
                $keywords, 
                $section_words,
                $index == 0 // 是否為第一段
            );
            
            if ($section_content) {
                $full_content .= $section_content;
                $current_word_count = str_word_count(strip_tags($full_content));
                
                error_log('AICG: 第 ' . ($index + 1) . ' 段完成，當前總字數: ' . $current_word_count);
                
                // 檢查是否已達到最小字數要求
                if ($current_word_count >= $min_words) {
                    break;
                }
                
                // 避免API限制，稍微延遲
                if ($index < count($outline) - 1) {
                    sleep(2);
                }
            } else {
                error_log('AICG: 第 ' . ($index + 1) . ' 段生成失敗');
            }
        }
        
        // 添加結論
        if ($current_word_count < $max_words) {
            $conclusion = $this->generate_conclusion($openai, $title, $keywords, min(1000, $max_words - $current_word_count));
            if ($conclusion) {
                $full_content .= $conclusion;
            }
        }
        
        // 優化關鍵字密度
        $full_content = $this->optimize_keyword_density($full_content, $keywords, $keyword_density);
        
        $final_word_count = str_word_count(strip_tags($full_content));
        error_log('AICG: 長文章生成完成，最終字數: ' . $final_word_count);
        
        return $full_content;
    }
    
    /**
     * 生成文章大綱
     * @param AICG_OpenAI_Handler $openai
     * @param string $title
     * @param array $keywords
     * @param int $sections_needed
     * @return array|false
     */
    private function generate_article_outline($openai, $title, $keywords, $sections_needed) {
        $keywords_string = implode('、', $keywords);
        
        $prompt = "請為標題「{$title}」的文章生成 {$sections_needed} 個主要段落的標題。
要求：
1. 每個段落標題都要與主題相關
2. 要涵蓋關鍵字：{$keywords_string}
3. 段落之間要有邏輯順序
4. 只回覆段落標題，每行一個，不要編號或其他說明";
        
        $result = $openai->generate_text($prompt, array(
            'max_tokens' => 500,
            'temperature' => 0.7
        ));
        
        if ($result['success']) {
            $outline_text = trim($result['text']);
            $outline = array_filter(array_map('trim', explode("\n", $outline_text)));
            return array_slice($outline, 0, $sections_needed);
        }
        
        // 如果生成失敗，使用預設大綱
        return $this->get_default_outline($sections_needed);
    }
    
    /**
     * 獲取預設大綱
     * @param int $sections_needed
     * @return array
     */
    private function get_default_outline($sections_needed) {
        $default_sections = array(
            '引言與背景介紹',
            '核心概念詳解',
            '實務應用與案例',
            '優勢與特點分析',
            '常見問題與解決方案',
            '未來發展趨勢',
            '專家建議與心得',
            '總結與展望'
        );
        
        return array_slice($default_sections, 0, $sections_needed);
    }
    
    /**
     * 生成段落內容
     * @param AICG_OpenAI_Handler $openai
     * @param string $article_title
     * @param string $section_title
     * @param array $keywords
     * @param int $target_words
     * @param bool $is_first_section
     * @return string|false
     */
    private function generate_section_content($openai, $article_title, $section_title, $keywords, $target_words, $is_first_section = false) {
        $keywords_string = implode('、', $keywords);
        
        $prompt = "請為文章「{$article_title}」撰寫段落「{$section_title}」的內容。
要求：
1. 字數約 {$target_words} 字
2. 自然融入關鍵字：{$keywords_string}
3. 使用 <h2>{$section_title}</h2> 作為段落標題
4. 內容要有深度、有價值
5. 使用 <p> 標籤分段，每段不超過200字
6. 可以適當使用 <h3> 子標題和 <ul>、<ol> 列表";
        
        if ($is_first_section) {
            $prompt .= "\n7. 這是文章的第一段，請包含引人入勝的開頭";
        }
        
        $result = $openai->generate_text($prompt, array(
            'max_tokens' => min(4000, $target_words * 2), // 中文token比例調整
            'temperature' => 0.8
        ));
        
        if ($result['success']) {
            return $result['text'];
        }
        
        return false;
    }
    
    /**
     * 生成結論
     * @param AICG_OpenAI_Handler $openai
     * @param string $title
     * @param array $keywords
     * @param int $target_words
     * @return string|false
     */
    private function generate_conclusion($openai, $title, $keywords, $target_words) {
        $keywords_string = implode('、', $keywords);
        
        $prompt = "請為文章「{$title}」撰寫結論段落。
要求：
1. 字數約 {$target_words} 字
2. 總結文章重點
3. 再次提及關鍵字：{$keywords_string}
4. 使用 <h2>結論</h2> 作為標題
5. 給讀者行動建議或展望
6. 使用 <p> 標籤分段";
        
        $result = $openai->generate_text($prompt, array(
            'max_tokens' => min(2000, $target_words * 2),
            'temperature' => 0.7
        ));
        
        if ($result['success']) {
            return $result['text'];
        }
        
        return false;
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