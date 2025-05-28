<?php
/**
 * 火山引擎視覺生成 API 處理器 - 修正版
 */
class AICG_Jimeng_Handler {
    
    private static $instance = null;
    private $access_key_id;
    private $secret_access_key;
    private $api_endpoint = 'https://visual.volcengineapi.com';
    private $region = 'cn-north-1';
    private $service = 'cv';
    private $version = '2022-08-31';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 重新載入設定（用於測試）
     */
    public function reload_settings() {
        $this->access_key_id = get_option('aicg_volcengine_access_key_id', '');
        $this->secret_access_key = get_option('aicg_volcengine_secret_access_key', '');
    }
    
    private function __construct() {
        // 修正：統一使用火山引擎的認證選項名稱
        $this->access_key_id = get_option('aicg_volcengine_access_key_id', '');
        $this->secret_access_key = get_option('aicg_volcengine_secret_access_key', '');
        
        // 記錄調試信息
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('火山引擎 Access Key ID 長度: ' . strlen($this->access_key_id));
            error_log('火山引擎 Secret Access Key 長度: ' . strlen($this->secret_access_key));
        }
    }
    
    /**
     * 生成請求簽名
     */
    private function sign_request($method, $uri, $query_params, $headers, $body) {
        $datetime = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');
        
        // Canonical Request
        $canonical_uri = $uri;
        $canonical_query = $this->build_canonical_query($query_params);
        $canonical_headers = $this->build_canonical_headers($headers);
        $signed_headers = $this->get_signed_headers($headers);
        $hashed_payload = hash('sha256', $body);
        
        $canonical_request = implode("\n", [
            $method,
            $canonical_uri,
            $canonical_query,
            $canonical_headers,
            $signed_headers,
            $hashed_payload
        ]);
        
        // String to Sign
        $credential_scope = $date . '/' . $this->region . '/' . $this->service . '/request';
        $string_to_sign = implode("\n", [
            'HMAC-SHA256',
            $datetime,
            $credential_scope,
            hash('sha256', $canonical_request)
        ]);
        
        // Calculate Signature
        $k_date = hash_hmac('sha256', $date, 'VOLC' . $this->secret_access_key, true);
        $k_region = hash_hmac('sha256', $this->region, $k_date, true);
        $k_service = hash_hmac('sha256', $this->service, $k_region, true);
        $k_signing = hash_hmac('sha256', 'request', $k_service, true);
        $signature = hash_hmac('sha256', $string_to_sign, $k_signing);
        
        // Authorization Header
        $authorization = sprintf(
            'HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s',
            $this->access_key_id,
            $credential_scope,
            $signed_headers,
            $signature
        );
        
        return $authorization;
    }
    
    private function build_canonical_query($params) {
        if (empty($params)) return '';
        
        ksort($params);
        $parts = [];
        foreach ($params as $key => $value) {
            $parts[] = rawurlencode($key) . '=' . rawurlencode($value);
        }
        return implode('&', $parts);
    }
    
    private function build_canonical_headers($headers) {
        $canonical_headers = [];
        foreach ($headers as $key => $value) {
            $canonical_headers[strtolower($key)] = trim($value);
        }
        ksort($canonical_headers);
        
        $result = [];
        foreach ($canonical_headers as $key => $value) {
            $result[] = $key . ':' . $value;
        }
        return implode("\n", $result) . "\n";
    }
    
    private function get_signed_headers($headers) {
        $signed_headers = [];
        foreach ($headers as $key => $value) {
            $signed_headers[] = strtolower($key);
        }
        sort($signed_headers);
        return implode(';', $signed_headers);
    }
    
    /**
     * 發送請求到火山引擎
     */
    private function send_request($action, $params = []) {
        $datetime = gmdate('Ymd\THis\Z');
        
        $query_params = [
            'Action' => $action,
            'Version' => $this->version,
        ];
        
        $headers = [
            'Host' => 'visual.volcengineapi.com',
            'X-Date' => $datetime,
            'Content-Type' => 'application/json',
        ];
        
        $body = json_encode($params);
        
        // 生成簽名
        $authorization = $this->sign_request('POST', '/', $query_params, $headers, $body);
        $headers['Authorization'] = $authorization;
        
        // 構建完整URL
        $url = $this->api_endpoint . '?' . $this->build_canonical_query($query_params);
        
        // 發送請求
        $wp_headers = [];
        foreach ($headers as $key => $value) {
            $wp_headers[$key] = $value;
        }
        
        $response = wp_remote_post($url, [
            'timeout' => 120,
            'headers' => $wp_headers,
            'body' => $body,
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => '請求失敗: ' . $response->get_error_message()
            ];
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        error_log('火山引擎回應碼: ' . $response_code);
        error_log('火山引擎回應: ' . substr($response_body, 0, 500));
        
        if ($response_code !== 200) {
            $error_data = json_decode($response_body, true);
            $error_message = isset($error_data['ResponseMetadata']['Error']['Message']) 
                ? $error_data['ResponseMetadata']['Error']['Message'] 
                : '請求失敗';
            
            return [
                'success' => false,
                'message' => $error_message
            ];
        }
        
        $data = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'message' => '回應解析失敗'
            ];
        }
        
        return [
            'success' => true,
            'data' => $data
        ];
    }
    
    /**
     * 測試 API 連接
     * @return array
     */
    public function test_connection() {
        if (empty($this->access_key_id) || empty($this->secret_access_key)) {
            return array(
                'success' => false,
                'message' => '請先設定火山引擎 Access Key ID 和 Secret Access Key'
            );
        }
        
        // 使用簡單的 API 調用測試連接
        $result = $this->send_request('ListModels', []);
        
        if ($result['success']) {
            return array(
                'success' => true,
                'message' => '火山引擎連接成功'
            );
        } else {
            return array(
                'success' => false,
                'message' => '連接失敗: ' . $result['message']
            );
        }
    }
    
    /**
     * 生成圖片
     * @param string $prompt 圖片描述
     * @param array $options 選項
     * @return array
     */
    public function generate_image($prompt, $options = array()) {
        if (empty($this->access_key_id) || empty($this->secret_access_key)) {
            return array(
                'success' => false,
                'message' => '火山引擎認證資訊未設定'
            );
        }
        
        $default_options = array(
            'req_key' => 'text2img_' . time() . '_' . rand(1000, 9999),
            'model_version' => 'general_v2.0',
            'width' => 1024,
            'height' => 768,
            'scale' => 7.5,
            'seed' => -1,
            'ddim_steps' => 20,
            'use_sr' => false,
        );
        
        $options = wp_parse_args($options, $default_options);
        
        // 構建請求參數
        $params = [
            'req_key' => $options['req_key'],
            'text' => $prompt,
            'model_version' => $options['model_version'],
            'width' => $options['width'],
            'height' => $options['height'],
            'scale' => $options['scale'],
            'seed' => $options['seed'],
            'ddim_steps' => $options['ddim_steps'],
            'use_sr' => $options['use_sr'],
        ];
        
        // 如果有負面提示詞
        if (!empty($options['negative_prompt'])) {
            $params['negative_prompt'] = $options['negative_prompt'];
        }
        
        error_log('火山引擎圖片生成請求: ' . json_encode($params));
        
        // 發送請求
        $result = $this->send_request('TextToImage', $params);
        
        if (!$result['success']) {
            return array(
                'success' => false,
                'message' => $result['message']
            );
        }
        
        // 檢查返回數據
        if (isset($result['data']['Result']['Data']['task_id'])) {
            // 異步任務，需要輪詢結果
            $task_id = $result['data']['Result']['Data']['task_id'];
            return $this->wait_for_task($task_id);
        } elseif (isset($result['data']['Result']['Data']['image_urls']) && !empty($result['data']['Result']['Data']['image_urls'])) {
            // 直接返回結果
            return array(
                'success' => true,
                'image_url' => $result['data']['Result']['Data']['image_urls'][0]
            );
        } else {
            return array(
                'success' => false,
                'message' => '未收到圖片 URL'
            );
        }
    }
    
    /**
     * 等待異步任務完成
     * @param string $task_id
     * @return array
     */
    private function wait_for_task($task_id) {
        $max_attempts = 30;
        $attempt = 0;
        
        while ($attempt < $max_attempts) {
            sleep(3); // 等待3秒
            
            $result = $this->send_request('GetAsyncTaskResult', [
                'task_id' => $task_id
            ]);
            
            if (!$result['success']) {
                $attempt++;
                continue;
            }
            
            $data = $result['data'];
            
            if (isset($data['Result']['Data']['status'])) {
                $status = $data['Result']['Data']['status'];
                
                if ($status === 'SUCCESS' && isset($data['Result']['Data']['image_urls'])) {
                    return array(
                        'success' => true,
                        'image_url' => $data['Result']['Data']['image_urls'][0]
                    );
                } elseif ($status === 'FAILED') {
                    return array(
                        'success' => false,
                        'message' => '圖片生成失敗'
                    );
                }
            }
            
            $attempt++;
        }
        
        return array(
            'success' => false,
            'message' => '生成超時，請稍後重試'
        );
    }
    
    /**
     * 下載並保存圖片到媒體庫
     * @param string $image_url
     * @param string $filename
     * @return int|false
     */
    public function download_and_save_image($image_url, $filename = '') {
        if (empty($image_url)) {
            return false;
        }
        
        // 下載圖片
        $response = wp_remote_get($image_url, array(
            'timeout' => 60
        ));
        
        if (is_wp_error($response)) {
            error_log('下載圖片失敗: ' . $response->get_error_message());
            return false;
        }
        
        $image_data = wp_remote_retrieve_body($response);
        
        if (empty($image_data)) {
            error_log('圖片資料為空');
            return false;
        }
        
        // 生成檔名
        if (empty($filename)) {
            $filename = 'ai-generated-' . time() . '.png';
        }
        
        // 上傳到媒體庫
        $upload = wp_upload_bits($filename, null, $image_data);
        
        if ($upload['error']) {
            error_log('上傳圖片失敗: ' . $upload['error']);
            return false;
        }
        
        // 準備附件資料
        $file_path = $upload['file'];
        $file_name = basename($file_path);
        $file_type = wp_check_filetype($file_name, null);
        $attachment_title = sanitize_file_name(pathinfo($file_name, PATHINFO_FILENAME));
        $wp_upload_dir = wp_upload_dir();
        
        $post_info = array(
            'guid'           => $wp_upload_dir['url'] . '/' . $file_name,
            'post_mime_type' => $file_type['type'],
            'post_title'     => $attachment_title,
            'post_content'   => '',
            'post_status'    => 'inherit',
        );
        
        // 插入附件
        $attach_id = wp_insert_attachment($post_info, $file_path);
        
        // 生成附件中繼資料
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
        wp_update_attachment_metadata($attach_id, $attach_data);
        
        return $attach_id;
    }
    
    /**
     * 生成文章配圖
     * @param string $title 文章標題
     * @param string $content 文章內容
     * @param array $keywords 關鍵字
     * @return int|false 附件ID或false
     */
    public function generate_post_image($title, $content, $keywords = array()) {
        // 如果有關鍵字，隨機選擇一個作為圖片生成的主題
        $selected_keyword = '';
        if (!empty($keywords)) {
            $selected_keyword = $keywords[array_rand($keywords)];
            error_log('AICG: 使用關鍵字生成圖片: ' . $selected_keyword);
        }
        
        // 構建圖片描述提示詞
        $prompt = $this->build_image_prompt_from_keyword($selected_keyword, $title);
        
        // 生成圖片
        $result = $this->generate_image($prompt, array(
            'width' => 1200,
            'height' => 800,
            'ddim_steps' => 30,
            'scale' => 8.0,
            'negative_prompt' => '低质量, 模糊, 变形, 丑陋, 文字, 水印, logo, 商标, 版权, 簽名'
        ));
        
        if (!$result['success']) {
            error_log('生成圖片失敗: ' . $result['message']);
            return false;
        }
        
        // 下載並保存圖片
        $filename = sanitize_title($selected_keyword ?: $title) . '-' . time() . '.png';
        $attachment_id = $this->download_and_save_image($result['image_url'], $filename);
        
        return $attachment_id;
    }
    
    /**
     * 基於關鍵字構建圖片生成提示詞
     * @param string $keyword
     * @param string $title
     * @return string
     */
    private function build_image_prompt_from_keyword($keyword, $title) {
        if (empty($keyword)) {
            // 如果沒有關鍵字，使用標題
            return $this->build_image_prompt($title);
        }
        
        // 判斷關鍵字類型並生成相應的提示詞
        if (preg_match('/娛樂城|博弈|賭場|百家樂|老虎機|撲克|21點|輪盤|骰寶|運彩/u', $keyword)) {
            // 娛樂城相關圖片
            $prompts = array(
                "豪華賭場內部，{$keyword}遊戲場景，金碧輝煌的裝潢，專業攝影，高品質，8K解析度",
                "現代線上{$keyword}介面，科技感設計，炫彩霓虹燈效果，數位藝術風格，高解析度",
                "{$keyword}遊戲特寫，精緻的遊戲道具，豪華氛圍，電影級燈光，專業攝影",
                "未來科技風格的{$keyword}場景，全息投影效果，賽博朋克風格，高品質渲染"
            );
        } elseif (preg_match('/旅遊|景點|美食|住宿|交通/u', $keyword)) {
            // 旅遊相關圖片
            $prompts = array(
                "台灣{$keyword}風景照，自然風光，專業攝影，黃金時刻光線，8K解析度",
                "{$keyword}場景，充滿活力的氛圍，旅遊攝影風格，高品質",
                "美麗的{$keyword}，藍天白雲，風景如畫，專業風景攝影"
            );
        } elseif (preg_match('/股票|投資|理財|基金|外匯/u', $keyword)) {
            // 金融相關圖片
            $prompts = array(
                "{$keyword}概念圖，金融科技，數據視覺化，現代商業風格，專業設計",
                "股市圖表與{$keyword}，上升趨勢，商業攝影，高品質渲染",
                "{$keyword}金融場景，專業商務風格，現代辦公環境，高解析度"
            );
        } elseif (preg_match('/科技|手機|電腦|網路|AI|軟體/u', $keyword)) {
            // 科技相關圖片
            $prompts = array(
                "{$keyword}科技產品展示，未來感設計，專業產品攝影，高品質渲染",
                "創新{$keyword}概念圖，科技風格，藍色調，現代設計，8K解析度",
                "{$keyword}技術視覺化，數位藝術，賽博朋克風格，高品質"
            );
        } else {
            // 通用圖片提示詞
            $prompts = array(
                "{$keyword}主題插圖，現代設計風格，鮮豔色彩，專業品質，高解析度",
                "創意{$keyword}概念圖，藝術風格，吸引眼球的設計，8K品質",
                "{$keyword}視覺呈現，簡潔大方，專業設計，高品質渲染"
            );
        }
        
        // 隨機選擇一個提示詞模板
        $selected_prompt = $prompts[array_rand($prompts)];
        
        // 加入通用的品質描述
        $selected_prompt .= "，細節豐富，專業打光，無文字，無浮水印";
        
        return $selected_prompt;
    }
    
    /**
     * 構建圖片生成提示詞
     * @param string $title
     * @param array $keywords
     * @return string
     */
    private function build_image_prompt($title, $keywords = array()) {
        // 基礎提示詞
        $prompt = "高質量的部落格文章配圖，標題：" . $title;
        
        // 根據關鍵字類型調整風格
        if (strpos($title, '娛樂城') !== false || strpos($title, '博弈') !== false) {
            $prompt .= "，現代賭場主題，奢華遊戲氛圍，金色和紅色調，高品質，專業攝影風格";
        } else {
            $prompt .= "，現代設計，乾淨專業，鮮豔的色彩，高品質，藝術感";
        }
        
        // 加入關鍵字元素
        if (!empty($keywords)) {
            $keyword_string = implode('、', array_slice($keywords, 0, 3));
            $prompt .= "，包含元素：" . $keyword_string;
        }
        
        // 技術參數
        $prompt .= "，8K解析度，高度細節，銳利對焦，專業打光";
        
        return $prompt;
    }
    
    /**
     * 取得可用的模型列表
     * @return array
     */
    public function get_available_models() {
        return array(
            'general_v2.0' => '通用模型 v2.0',
            'general_v1.5' => '通用模型 v1.5',
            'anime_v1.0' => '動漫風格 v1.0',
            'realistic_v1.0' => '寫實風格 v1.0'
        );
    }
}