<?php
/**
 * 火山引擎 API 簡化實現
 * 基於官方文檔的標準實現
 */

class AICG_Volcengine_Simple {
    
    private $access_key_id;
    private $secret_access_key;
    private $service = 'cv';
    private $region = 'cn-north-1';
    private $host = 'visual.volcengineapi.com';
    private $version = '2022-08-31';
    
    public function __construct($access_key_id = '', $secret_access_key = '') {
        $this->access_key_id = $access_key_id ?: get_option('aicg_volcengine_access_key_id', '');
        $this->secret_access_key = $secret_access_key ?: get_option('aicg_volcengine_secret_access_key', '');
    }
    
    /**
     * 發送 API 請求
     */
    public function request($action, $params = []) {
        // 1. 準備請求
        $method = 'POST';
        $path = '/';
        $query = [
            'Action' => $action,
            'Version' => $this->version,
        ];
        
        // 2. 準備時間戳
        $timestamp = time();
        $datetime = gmdate('Ymd\THis\Z', $timestamp);
        
        // 3. 準備請求體
        $body = json_encode($params);
        
        // 4. 準備請求頭
        $headers = [
            'Content-Type' => 'application/json',
            'Host' => $this->host,
            'X-Date' => $datetime,
        ];
        
        // 5. 計算簽名
        $auth_header = $this->sign($method, $path, $query, $headers, $body);
        $headers['Authorization'] = $auth_header;
        
        // 6. 構建完整 URL
        $url = 'https://' . $this->host . $path . '?' . http_build_query($query);
        
        // 7. 發送請求
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $this->build_curl_headers($headers),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);
        
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        
        if ($error) {
            return [
                'success' => false,
                'message' => 'CURL 錯誤: ' . $error
            ];
        }
        
        $data = json_decode($response, true);
        
        if ($http_code !== 200) {
            $error_msg = isset($data['ResponseMetadata']['Error']['Message']) 
                ? $data['ResponseMetadata']['Error']['Message'] 
                : '請求失敗';
            
            return [
                'success' => false,
                'message' => $error_msg,
                'http_code' => $http_code,
                'response' => $response
            ];
        }
        
        return [
            'success' => true,
            'data' => $data
        ];
    }
    
    /**
     * 計算簽名
     */
    private function sign($method, $path, $query, $headers, $body) {
        $datetime = $headers['X-Date'];
        $date = substr($datetime, 0, 8);
        
        // 1. 規範請求
        ksort($query);
        $canonical_query = http_build_query($query);
        
        $canonical_headers = '';
        $signed_headers_arr = [];
        ksort($headers);
        foreach ($headers as $key => $value) {
            $lower_key = strtolower($key);
            $canonical_headers .= $lower_key . ':' . trim($value) . "\n";
            $signed_headers_arr[] = $lower_key;
        }
        $signed_headers = implode(';', $signed_headers_arr);
        
        $hashed_payload = hash('sha256', $body);
        
        $canonical_request = implode("\n", [
            $method,
            $path,
            $canonical_query,
            $canonical_headers,
            $signed_headers,
            $hashed_payload
        ]);
        
        // 2. 待簽名字符串
        $credential_scope = $date . '/' . $this->region . '/' . $this->service . '/request';
        $string_to_sign = implode("\n", [
            'HMAC-SHA256',
            $datetime,
            $credential_scope,
            hash('sha256', $canonical_request)
        ]);
        
        // 3. 計算簽名
        $k_secret = 'VOLC' . $this->secret_access_key;
        $k_date = hash_hmac('sha256', $date, $k_secret, true);
        $k_region = hash_hmac('sha256', $this->region, $k_date, true);
        $k_service = hash_hmac('sha256', $this->service, $k_region, true);
        $k_signing = hash_hmac('sha256', 'request', $k_service, true);
        $signature = hash_hmac('sha256', $string_to_sign, $k_signing);
        
        // 4. 構建授權頭
        return sprintf(
            'HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s',
            $this->access_key_id,
            $credential_scope,
            $signed_headers,
            $signature
        );
    }
    
    /**
     * 構建 CURL 請求頭
     */
    private function build_curl_headers($headers) {
        $curl_headers = [];
        foreach ($headers as $key => $value) {
            $curl_headers[] = $key . ': ' . $value;
        }
        return $curl_headers;
    }
    
    /**
     * 測試連接
     */
    public function test_connection() {
        if (empty($this->access_key_id) || empty($this->secret_access_key)) {
            return [
                'success' => false,
                'message' => '請設定 Access Key ID 和 Secret Access Key'
            ];
        }
        
        // 使用簡單的 API 測試
        $result = $this->request('GetImageTemplate', [
            'TemplateId' => 'test'
        ]);
        
        if ($result['success']) {
            return [
                'success' => true,
                'message' => '火山引擎連接成功'
            ];
        } else {
            // 如果是模板不存在，但認證成功，也算成功
            if (strpos($result['message'], 'not exist') !== false ||
                strpos($result['message'], 'not found') !== false) {
                return [
                    'success' => true,
                    'message' => '火山引擎連接成功（認證通過）'
                ];
            }
            
            return [
                'success' => false,
                'message' => '連接失敗: ' . $result['message']
            ];
        }
    }
    
    /**
     * 文字生成圖片
     */
    public function text_to_image($prompt, $options = []) {
        $default_options = [
            'Width' => 1024,
            'Height' => 768,
            'Steps' => 20,
            'Scale' => 7.5,
            'Seed' => -1,
        ];
        
        $params = array_merge($default_options, $options);
        $params['Prompt'] = $prompt;
        $params['ReqKey'] = 'img_' . time() . '_' . rand(1000, 9999);
        
        $result = $this->request('Text2Image', $params);
        
        if (!$result['success']) {
            return $result;
        }
        
        // 檢查返回結果
        $data = $result['data'];
        
        if (isset($data['Data']['Images']) && !empty($data['Data']['Images'])) {
            return [
                'success' => true,
                'image_url' => $data['Data']['Images'][0]
            ];
        } elseif (isset($data['Data']['TaskId'])) {
            // 異步任務，需要輪詢
            return $this->wait_for_task($data['Data']['TaskId']);
        }
        
        return [
            'success' => false,
            'message' => '未知的返回格式'
        ];
    }
    
    /**
     * 等待異步任務完成
     */
    private function wait_for_task($task_id, $max_attempts = 30) {
        for ($i = 0; $i < $max_attempts; $i++) {
            if ($i > 0) {
                sleep(3);
            }
            
            $result = $this->request('GetAsyncTaskResult', [
                'TaskId' => $task_id
            ]);
            
            if (!$result['success']) {
                continue;
            }
            
            $data = $result['data'];
            if (isset($data['Data']['Status'])) {
                $status = $data['Data']['Status'];
                
                if ($status === 'Success' && isset($data['Data']['Images'])) {
                    return [
                        'success' => true,
                        'image_url' => $data['Data']['Images'][0]
                    ];
                } elseif ($status === 'Failed') {
                    return [
                        'success' => false,
                        'message' => '圖片生成失敗'
                    ];
                }
            }
        }
        
        return [
            'success' => false,
            'message' => '任務超時'
        ];
    }
}

// 測試用例
if (isset($_GET['test_simple']) && current_user_can('manage_options')) {
    $volcengine = new AICG_Volcengine_Simple();
    $result = $volcengine->test_connection();
    
    echo '<pre>';
    echo '測試結果：' . "\n";
    print_r($result);
    echo '</pre>';
}