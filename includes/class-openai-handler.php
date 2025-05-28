<?php
/**
 * OpenAI API 處理器 - 完整修正版
 * 支援模型選擇
 * 負責與 ChatGPT API 通訊
 */

class AICG_OpenAI_Handler {
    
    private static $instance = null;
    private $api_key;
    private $api_endpoint = 'https://api.openai.com/v1/chat/completions';
    private $model;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 重置實例（強制重新載入）
     */
    public static function reset_instance() {
        self::$instance = null;
    }
    
    private function __construct() {
        $this->api_key = trim(get_option('aicg_openai_api_key', ''));
        $this->model = get_option('aicg_openai_model', 'gpt-3.5-turbo');
    }
    
    /**
     * 測試 API 連接
     * @return array
     */
    public function test_connection() {
        if (empty($this->api_key)) {
            return array(
                'success' => false,
                'message' => '請先設定 OpenAI API Key'
            );
        }
        
        // 使用簡單的測試請求
        $response = wp_remote_post($this->api_endpoint, array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'model' => 'gpt-3.5-turbo',
                'messages' => array(
                    array(
                        'role' => 'user',
                        'content' => 'Hello, this is a test message.'
                    )
                ),
                'max_tokens' => 10
            ))
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => '連接失敗: ' . $response->get_error_message()
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code === 200) {
            return array(
                'success' => true,
                'message' => 'OpenAI API 連接成功'
            );
        } elseif ($response_code === 401) {
            return array(
                'success' => false,
                'message' => 'API Key 無效或已過期'
            );
        } else {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $error_message = isset($body['error']['message']) ? $body['error']['message'] : '未知錯誤';
            return array(
                'success' => false,
                'message' => '錯誤: ' . $error_message
            );
        }
    }
    
    /**
     * 生成文字（通用方法）
     * @param string $prompt
     * @param array $options
     * @return array
     */
    public function generate_text($prompt, $options = array()) {
        if (empty($this->api_key)) {
            return array(
                'success' => false,
                'message' => 'OpenAI API Key 未設定'
            );
        }
        
        $default_options = array(
            'model' => $this->model,
            'max_tokens' => 2000,
            'temperature' => 0.7,
            'top_p' => 1,
            'frequency_penalty' => 0,
            'presence_penalty' => 0
        );
        
        $options = wp_parse_args($options, $default_options);
        
        $messages = array(
            array(
                'role' => 'system',
                'content' => '你是一個專業的內容創作者，擅長撰寫SEO優化的中文文章。'
            ),
            array(
                'role' => 'user',
                'content' => $prompt
            )
        );
        
        $request_body = array(
            'model' => $options['model'],
            'messages' => $messages,
            'max_tokens' => $options['max_tokens'],
            'temperature' => $options['temperature'],
            'top_p' => $options['top_p'],
            'frequency_penalty' => $options['frequency_penalty'],
            'presence_penalty' => $options['presence_penalty']
        );
        
        $response = wp_remote_post($this->api_endpoint, array(
            'timeout' => 120,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($request_body)
        ));
        
        if (is_wp_error($response)) {
            error_log('OpenAI API 錯誤: ' . $response->get_error_message());
            return array(
                'success' => false,
                'message' => '請求失敗: ' . $response->get_error_message()
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($response_code !== 200) {
            $error_message = isset($data['error']['message']) ? $data['error']['message'] : '請求失敗';
            error_log('OpenAI API 錯誤回應: ' . $error_message);
            return array(
                'success' => false,
                'message' => $error_message
            );
        }
        
        if (isset($data['choices'][0]['message']['content'])) {
            return array(
                'success' => true,
                'text' => trim($data['choices'][0]['message']['content']),
                'usage' => isset($data['usage']) ? $data['usage'] : null
            );
        }
        
        return array(
            'success' => false,
            'message' => '無法解析回應'
        );
    }
    
    /**
     * 生成文章內容（整合關鍵字）
     * @param array $params 參數
     * @return array
     */
    public function generate_content($params) {
        if (empty($this->api_key)) {
            return array(
                'success' => false,
                'error' => 'OpenAI API Key 未設定'
            );
        }
        
        // 建構提示詞
        $prompt = $this->build_prompt($params);
        
        // 根據模型調整 max_tokens
        $max_tokens = $this->get_max_tokens_for_model();
        
        // 準備請求資料
        $request_data = array(
            'model' => $this->model,
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => '你是一位專業的內容創作者，擅長撰寫SEO友好的中文文章。請確保文章自然流暢，關鍵字融入得當，避免過度優化。'
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'temperature' => 0.8,
            'max_tokens' => $max_tokens
        );
        
        // 發送請求
        $response = wp_remote_post($this->api_endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($request_data),
            'timeout' => 120 // 增加超時時間，特別是對於長文章
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message()
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            return array(
                'success' => false,
                'error' => $data['error']['message']
            );
        }
        
        if (isset($data['choices'][0]['message']['content'])) {
            return array(
                'success' => true,
                'content' => $data['choices'][0]['message']['content'],
                'usage' => $data['usage'] ?? array() // 返回使用統計
            );
        }
        
        return array(
            'success' => false,
            'error' => '無法生成內容'
        );
    }
    
    /**
     * 根據模型獲取最大 tokens
     * @return int
     */
    private function get_max_tokens_for_model() {
        $max_word_count = (int) get_option('aicg_max_word_count', 2000);
        
        // 中文大約 1.5-2 個字符對應 1 個 token
        $estimated_tokens = $max_word_count * 2;
        
        // 根據模型限制調整
        switch ($this->model) {
            case 'gpt-4':
            case 'gpt-4-0613':
                return min($estimated_tokens, 8000);
                
            case 'gpt-4-turbo-preview':
            case 'gpt-4-1106-preview':
            case 'gpt-4-0125-preview':
            case 'gpt-4-turbo':
                return min($estimated_tokens, 40000); // GPT-4 Turbo 支援更多
                
            case 'gpt-3.5-turbo':
            case 'gpt-3.5-turbo-0125':
                return min($estimated_tokens, 4000);
                
            case 'gpt-3.5-turbo-16k':
                return min($estimated_tokens, 16000);
                
            default:
                return min($estimated_tokens, 4000);
        }
    }
    
    /**
     * 建構提示詞 - 修正版
     * @param array $params
     * @return string
     */
    private function build_prompt($params) {
        // 檢查參數
        $keywords = isset($params['keywords']) ? $params['keywords'] : array();
        $min_words = isset($params['min_words']) ? $params['min_words'] : 1000;
        $max_words = isset($params['max_words']) ? $params['max_words'] : 2000;
        $title = isset($params['title']) ? $params['title'] : '';
        
        // 分類關鍵字
        $taiwan_keywords = array();
        $casino_keywords = array();
        
        foreach ($keywords as $keyword) {
            if (preg_match('/娛樂城|博弈|賭場|百家樂|老虎機|撲克|21點|輪盤|骰寶|運彩/u', $keyword)) {
                $casino_keywords[] = $keyword;
            } else {
                $taiwan_keywords[] = $keyword;
            }
        }
        
        $prompt = "請撰寫一篇 {$min_words} 到 {$max_words} 字的中文文章。\n\n";
        
        if (!empty($title)) {
            $prompt .= "文章標題：{$title}\n\n";
        }
        
        $prompt .= "要求：\n";
        
        $requirement_number = 1;
        
        if (!empty($taiwan_keywords)) {
            $taiwan_kw_string = implode('、', $taiwan_keywords);
            $prompt .= "{$requirement_number}. 文章必須自然地融入以下關鍵字：{$taiwan_kw_string}\n";
            $requirement_number++;
        }
        
        if (!empty($casino_keywords)) {
            $casino_kw_string = implode('、', $casino_keywords);
            $prompt .= "{$requirement_number}. 文章必須自然地融入以下娛樂城關鍵字：{$casino_kw_string}\n";
            $requirement_number++;
        }
        
        $prompt .= "{$requirement_number}. 關鍵字必須均勻分布在文章中，不要過度集中\n";
        $requirement_number++;
        
        $prompt .= "{$requirement_number}. 文章要有明確的引言、主體段落和結論\n";
        $requirement_number++;
        
        $prompt .= "{$requirement_number}. 內容要有價值、有深度，不要只是關鍵字堆砌\n";
        $requirement_number++;
        
        $prompt .= "{$requirement_number}. 使用適當的段落和子標題（使用 HTML 標籤如 <h2>、<h3>、<p>），提高可讀性\n";
        $requirement_number++;
        
        $prompt .= "{$requirement_number}. 語調要專業但親切，適合台灣讀者\n";
        $requirement_number++;
        
        if (!empty($casino_keywords)) {
            $prompt .= "{$requirement_number}. 請在文章中自然地提到娛樂城相關內容，但不要過於推銷\n";
            $requirement_number++;
        }
        
        $prompt .= "{$requirement_number}. 確保所有關鍵字都有被使用到，並在文章中均勻分布\n\n";
        
        // 根據字數要求調整提示
        if ($max_words >= 5000) {
            $prompt .= "這是一篇長文，請確保內容充實、資訊豐富，包含多個小節和詳細說明\n";
            $prompt .= "可以加入列表、比較表格、案例分析等豐富內容\n\n";
        }
        
        $prompt .= "請開始撰寫文章：";
        
        return $prompt;
    }
    
    /**
     * 生成文章標題
     * @param array $keywords
     * @return array
     */
    public function generate_title($keywords) {
        if (empty($this->api_key)) {
            return array(
                'success' => false,
                'error' => 'OpenAI API Key 未設定'
            );
        }
        
        // 檢查關鍵字
        if (empty($keywords)) {
            return array(
                'success' => false,
                'error' => '沒有關鍵字可供生成標題'
            );
        }
        
        $keyword_string = implode('、', array_slice($keywords, 0, 3));
        
        $request_data = array(
            'model' => $this->model,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => '請為包含關鍵字「' . $keyword_string . '」的文章生成一個吸引人的標題。標題要簡潔有力，不超過30個字。只回覆標題即可。'
                )
            ),
            'temperature' => 0.9,
            'max_tokens' => 100
        );
        
        $response = wp_remote_post($this->api_endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($request_data),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message()
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            return array(
                'success' => false,
                'error' => $data['error']['message']
            );
        }
        
        if (isset($data['choices'][0]['message']['content'])) {
            $title = trim($data['choices'][0]['message']['content']);
            // 移除可能的引號
            $title = trim($title, '"\' ');
            
            return array(
                'success' => true,
                'title' => $title
            );
        }
        
        return array(
            'success' => false,
            'error' => '無法生成標題'
        );
    }
    
    /**
     * 分析文章類別
     * @param string $content
     * @return array
     */
    public function analyze_category($content) {
        if (empty($this->api_key)) {
            return array(
                'success' => false,
                'error' => 'OpenAI API Key 未設定'
            );
        }
        
        // 取得所有分類
        $categories = get_categories(array(
            'hide_empty' => false
        ));
        
        if (empty($categories)) {
            return array(
                'success' => true,
                'categories' => array(1) // 預設分類
            );
        }
        
        $category_names = array();
        foreach ($categories as $cat) {
            $category_names[] = $cat->name;
        }
        
        $category_string = implode('、', $category_names);
        
        // 只取內容的前1000字來分析（節省 tokens）
        $content_excerpt = mb_substr($content, 0, 1000);
        
        $request_data = array(
            'model' => $this->model,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => '請分析以下文章內容，並從這些分類中選擇最適合的1-3個分類：' . $category_string . "\n\n文章內容：\n" . $content_excerpt . "\n\n只回覆分類名稱，用逗號分隔。"
                )
            ),
            'temperature' => 0.3,
            'max_tokens' => 100
        );
        
        $response = wp_remote_post($this->api_endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($request_data),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message()
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['choices'][0]['message']['content'])) {
            $suggested_categories = explode(',', $data['choices'][0]['message']['content']);
            $suggested_categories = array_map('trim', $suggested_categories);
            
            // 匹配分類ID
            $category_ids = array();
            foreach ($suggested_categories as $suggested) {
                foreach ($categories as $cat) {
                    if (strcasecmp($cat->name, $suggested) === 0) {
                        $category_ids[] = $cat->term_id;
                        break;
                    }
                }
            }
            
            // 如果沒有匹配到，使用預設分類
            if (empty($category_ids)) {
                $category_ids = array(1);
            }
            
            return array(
                'success' => true,
                'categories' => $category_ids
            );
        }
        
        return array(
            'success' => false,
            'error' => '無法分析分類'
        );
    }
    
    /**
     * 生成文章摘要
     * @param string $content
     * @return array
     */
    public function generate_excerpt($content) {
        if (empty($this->api_key)) {
            // 使用 WordPress 內建功能
            $excerpt = wp_trim_words(strip_tags($content), 55);
            return array(
                'success' => true,
                'excerpt' => $excerpt
            );
        }
        
        // 只取前1000字來生成摘要
        $content_excerpt = mb_substr(strip_tags($content), 0, 1000);
        
        $request_data = array(
            'model' => $this->model,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => '請為以下文章生成一個100字左右的摘要：' . "\n\n" . $content_excerpt . "\n\n只回覆摘要內容。"
                )
            ),
            'temperature' => 0.5,
            'max_tokens' => 200
        );
        
        $response = wp_remote_post($this->api_endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($request_data),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            // 失敗時使用備用方案
            $excerpt = wp_trim_words(strip_tags($content), 55);
            return array(
                'success' => true,
                'excerpt' => $excerpt
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['choices'][0]['message']['content'])) {
            return array(
                'success' => true,
                'excerpt' => trim($data['choices'][0]['message']['content'])
            );
        }
        
        // 備用方案
        $excerpt = wp_trim_words(strip_tags($content), 55);
        return array(
            'success' => true,
            'excerpt' => $excerpt
        );
    }
    
    /**
     * 生成摘要（簡化版）
     * @param string $content
     * @param int $max_length
     * @return string
     */
    public function generate_summary($content, $max_length = 150) {
        $prompt = sprintf(
            "請為以下文章生成一個%d字以內的中文摘要：\n\n%s",
            $max_length,
            strip_tags($content)
        );
        
        $result = $this->generate_text($prompt, array(
            'max_tokens' => 200,
            'temperature' => 0.5
        ));
        
        if ($result['success']) {
            return $result['text'];
        }
        
        // 如果生成失敗，使用簡單的截斷方法
        $text = strip_tags($content);
        if (mb_strlen($text) > $max_length) {
            return mb_substr($text, 0, $max_length) . '...';
        }
        
        return $text;
    }
    
    /**
     * 改寫文字
     * @param string $text
     * @param string $style
     * @return array
     */
    public function rewrite_text($text, $style = 'professional') {
        $style_prompts = array(
            'professional' => '請以專業的語氣改寫',
            'casual' => '請以輕鬆隨意的語氣改寫',
            'friendly' => '請以友善親切的語氣改寫',
            'authoritative' => '請以權威專業的語氣改寫'
        );
        
        $prompt = sprintf(
            "%s以下文字，保持原意不變：\n\n%s",
            isset($style_prompts[$style]) ? $style_prompts[$style] : $style_prompts['professional'],
            $text
        );
        
        return $this->generate_text($prompt, array(
            'temperature' => 0.7
        ));
    }
    
    /**
     * 取得可用的模型列表
     * @return array
     */
    public function get_available_models() {
        return array(
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
            'gpt-3.5-turbo-16k' => 'GPT-3.5 Turbo 16K',
            'gpt-4' => 'GPT-4',
            'gpt-4-turbo' => 'GPT-4 Turbo',
            'gpt-4-turbo-preview' => 'GPT-4 Turbo Preview',
            'gpt-4-0125-preview' => 'GPT-4 Turbo (Jan 2025)'
        );
    }
}