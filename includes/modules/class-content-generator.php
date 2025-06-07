<?php
/**
 * 檔案：includes/modules/class-content-generator.php
 * 功能：內容生成模組
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
 * 內容生成類別
 * 
 * 負責整合OpenAI API，生成高品質SEO優化內容
 */
class ContentGenerator {
    
    /**
     * 資料庫實例
     */
    private Database $db;
    
    /**
     * 日誌實例
     */
    private Logger $logger;
    
    /**
     * SEO優化器實例
     */
    private ?SEOOptimizer $seo_optimizer = null;
    
    /**
     * 成本控制器實例
     */
    private ?CostController $cost_controller = null;
    
    /**
     * OpenAI API端點
     */
    private const OPENAI_API_URL = 'https://api.openai.com/v1/chat/completions';
    
    /**
     * 模型定價（每1000 tokens，美元）
     */
    private const MODEL_PRICING = [
        'gpt-4' => ['input' => 0.03, 'output' => 0.06],
        'gpt-4-turbo-preview' => ['input' => 0.01, 'output' => 0.03],
        'gpt-3.5-turbo' => ['input' => 0.001, 'output' => 0.002]
    ];
    
    /**
     * 預定義分類
     */
    private const CATEGORIES = [
        '所有文章', '娛樂城教學', '虛擬貨幣', '體育', '科技', 
        '健康', '新聞', '明星', '汽車', '理財', 
        '生活', '社會', '美食', '追劇'
    ];
    
    /**
     * 建構函數
     */
    public function __construct() {
        $this->db = new Database();
        $this->logger = new Logger();
    }
    
    /**
     * 1. 主要生成方法
     */
    
    /**
     * 1.1 生成文章
     */
    public function generate_article(array $params): array {
        $this->logger->info('開始生成文章', $params);
        
        $defaults = [
            'keyword' => '',
            'length' => 8000,
            'model' => 'gpt-4-turbo-preview',
            'images' => 3,
            'type' => 'general',
            'schedule_id' => null
        ];
        
        $params = wp_parse_args($params, $defaults);
        
        try {
            // 檢查API金鑰
            $api_key = get_option('aisc_openai_api_key');
            if (empty($api_key)) {
                throw new \Exception('請先設定OpenAI API金鑰');
            }
            
            // 檢查成本限制
            if (!$this->check_cost_limit($params)) {
                throw new \Exception('已達到每日成本限制');
            }
            
            // 生成文章結構
            $structure = $this->generate_article_structure($params['keyword'], $params['type']);
            
            // 分段生成內容
            $content = $this->generate_content_segments($structure, $params);
            
            // 組合並優化內容
            $article = $this->assemble_article($content, $params);
            
            // 自動分類
            $categories = $this->determine_categories($article['content'], $params['keyword']);
            
            // 創建文章
            $post_id = $this->create_post($article, $categories, $params);
            
            // 記錄歷史
            $this->record_generation_history($post_id, $params, $article);
            
            // 返回結果
            return [
                'success' => true,
                'post_id' => $post_id,
                'url' => get_permalink($post_id),
                'cost' => $article['total_cost'],
                'message' => '文章生成成功'
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('文章生成失敗', [
                'error' => $e->getMessage(),
                'params' => $params
            ]);
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 1.2 生成文章結構
     */
    private function generate_article_structure(string $keyword, string $type): array {
        $prompt = $this->build_structure_prompt($keyword, $type);
        
        $response = $this->call_openai_api([
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'system', 'content' => '你是一位專業的SEO內容策劃專家。'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.7,
            'max_tokens' => 1000
        ]);
        
        return $this->parse_structure_response($response);
    }
    
    /**
     * 1.3 構建結構提示詞
     */
    private function build_structure_prompt(string $keyword, string $type): string {
        $prompt = "請為關鍵字「{$keyword}」設計一個SEO友好的文章結構。\n\n";
        $prompt .= "要求：\n";
        $prompt .= "1. 標題要吸引人且包含關鍵字\n";
        $prompt .= "2. 設計3-5個H2標題，每個H2下可有2-3個H3標題\n";
        $prompt .= "3. 標題層級不超過H3\n";
        $prompt .= "4. 結構要符合搜尋意圖和精選摘要優化\n";
        $prompt .= "5. 包含一個FAQ部分（10個問題）\n\n";
        
        if ($type === 'casino') {
            $prompt .= "文章類型：娛樂城相關內容，需要專業且具有說服力\n";
        }
        
        $prompt .= "請以JSON格式返回，包含：title, description, outline, faq";
        
        return $prompt;
    }
    
    /**
     * 1.4 分段生成內容
     */
    private function generate_content_segments(array $structure, array $params): array {
        $segments = [];
        $total_tokens = 0;
        $total_cost = 0;
        
        // 計算每段目標字數
        $segment_length = 1500; // 每次請求生成1500字
        $num_segments = ceil($params['length'] / $segment_length);
        
        // 為每個大綱點生成內容
        foreach ($structure['outline'] as $index => $section) {
            $segment_prompt = $this->build_content_prompt($section, $params['keyword'], $segment_length);
            
            $response = $this->call_openai_api([
                'model' => $params['model'],
                'messages' => [
                    ['role' => 'system', 'content' => $this->get_content_system_prompt()],
                    ['role' => 'user', 'content' => $segment_prompt]
                ],
                'temperature' => 0.8,
                'max_tokens' => 2000
            ]);
            
            $segments[] = [
                'section' => $section,
                'content' => $response['content'],
                'tokens' => $response['usage']['total_tokens']
            ];
            
            $total_tokens += $response['usage']['total_tokens'];
            $total_cost += $this->calculate_cost(
                $response['usage'], 
                $params['model']
            );
            
            // 檢查是否已達到目標長度
            $current_length = array_sum(array_map(function($s) {
                return mb_strlen($s['content']);
            }, $segments));
            
            if ($current_length >= $params['length']) {
                break;
            }
        }
        
        // 生成FAQ內容
        $faq_content = $this->generate_faq_content($structure['faq'], $params['keyword'], $params['model']);
        $segments[] = $faq_content;
        $total_tokens += $faq_content['tokens'];
        $total_cost += $faq_content['cost'];
        
        return [
            'segments' => $segments,
            'total_tokens' => $total_tokens,
            'total_cost' => $total_cost
        ];
    }
    
    /**
     * 1.5 獲取內容系統提示詞
     */
    private function get_content_system_prompt(): string {
        return "你是一位專業的SEO內容撰寫專家，專門創作高品質、深度的文章。
        
你的寫作原則：
1. 使用繁體中文，語氣專業但易讀
2. 每個段落至少500字，確保內容深度
3. 自然融入關鍵字，密度保持在1-2%
4. 使用數據、例子和引用增加可信度
5. 段落間使用過渡句保持連貫性
6. 符合E-E-A-T原則（經驗、專業、權威、可信）
7. 為精選摘要優化，使用簡潔定義和列表
8. 避免過度SEO優化的痕跡";
    }
    
    /**
     * 1.6 構建內容提示詞
     */
    private function build_content_prompt(array $section, string $keyword, int $length): string {
        $prompt = "請為以下段落撰寫約{$length}字的深度內容：\n\n";
        $prompt .= "標題：{$section['title']}\n";
        $prompt .= "關鍵字：{$keyword}\n\n";
        
        if (!empty($section['subheadings'])) {
            $prompt .= "子標題：\n";
            foreach ($section['subheadings'] as $sub) {
                $prompt .= "- {$sub}\n";
            }
            $prompt .= "\n";
        }
        
        $prompt .= "要求：\n";
        $prompt .= "1. 深入探討主題，提供實用資訊\n";
        $prompt .= "2. 包含具體例子和數據支持\n";
        $prompt .= "3. 保持段落結構清晰\n";
        $prompt .= "4. 自然融入關鍵字\n";
        
        return $prompt;
    }
    
    /**
     * 2. API呼叫方法
     */
    
    /**
     * 2.1 呼叫OpenAI API
     */
    private function call_openai_api(array $params): array {
        $api_key = get_option('aisc_openai_api_key');
        
        $response = wp_remote_post(self::OPENAI_API_URL, [
            'timeout' => 60,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($params)
        ]);
        
        if (is_wp_error($response)) {
            throw new \Exception('API請求失敗: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            throw new \Exception('API錯誤: ' . $data['error']['message']);
        }
        
        return [
            'content' => $data['choices'][0]['message']['content'] ?? '',
            'usage' => $data['usage'] ?? []
        ];
    }
    
    /**
     * 2.2 計算API成本
     */
    private function calculate_cost(array $usage, string $model): float {
        if (!isset(self::MODEL_PRICING[$model])) {
            return 0;
        }
        
        $pricing = self::MODEL_PRICING[$model];
        $input_cost = ($usage['prompt_tokens'] ?? 0) / 1000 * $pricing['input'];
        $output_cost = ($usage['completion_tokens'] ?? 0) / 1000 * $pricing['output'];
        
        // 轉換為台幣（假設匯率為30）
        $usd_cost = $input_cost + $output_cost;
        $twd_cost = $usd_cost * 30;
        
        return round($twd_cost, 2);
    }
    
    /**
     * 3. 內容組裝和優化
     */
    
    /**
     * 3.1 組裝文章
     */
    private function assemble_article(array $content_data, array $params): array {
        $segments = $content_data['segments'];
        $full_content = '';
        
        // 組合所有段落
        foreach ($segments as $segment) {
            if (isset($segment['section']['title'])) {
                $full_content .= "<h2>{$segment['section']['title']}</h2>\n\n";
            }
            
            $full_content .= $segment['content'] . "\n\n";
        }
        
        // 取得SEO優化器
        if (!$this->seo_optimizer) {
            $this->seo_optimizer = new SEOOptimizer();
        }
        
        // SEO優化
        $optimized = $this->seo_optimizer->optimize_content($full_content, $params['keyword']);
        
        // 插入圖片
        if ($params['images'] > 0) {
            $optimized['content'] = $this->insert_image_placeholders($optimized['content'], $params['images']);
        }
        
        return [
            'title' => $optimized['title'],
            'content' => $optimized['content'],
            'excerpt' => $optimized['excerpt'],
            'meta_description' => $optimized['meta_description'],
            'slug' => $optimized['slug'],
            'total_tokens' => $content_data['total_tokens'],
            'total_cost' => $content_data['total_cost']
        ];
    }
    
    /**
     * 3.2 插入圖片占位符
     */
    private function insert_image_placeholders(string $content, int $count): string {
        // 計算段落數
        $paragraphs = explode('</p>', $content);
        $total_paragraphs = count($paragraphs) - 1;
        
        if ($total_paragraphs <= 0) {
            return $content;
        }
        
        // 計算圖片位置
        $interval = max(3, floor($total_paragraphs / ($count + 1)));
        $positions = [];
        
        for ($i = 1; $i <= $count; $i++) {
            $positions[] = min($i * $interval, $total_paragraphs - 1);
        }
        
        // 插入圖片
        foreach (array_reverse($positions) as $pos) {
            $image_html = $this->generate_image_placeholder();
            $paragraphs[$pos] .= '</p>' . $image_html;
        }
        
        return implode('', $paragraphs);
    }
    
    /**
     * 3.3 生成圖片占位符
     */
    private function generate_image_placeholder(): string {
        // 預留圖片生成介面
        $placeholder_url = AISC_PLUGIN_URL . 'assets/images/placeholder.jpg';
        
        return sprintf(
            '<figure class="wp-block-image size-large">
                <img src="%s" alt="待生成圖片" class="aisc-image-placeholder" />
                <figcaption>圖片說明</figcaption>
            </figure>',
            $placeholder_url
        );
    }
    
    /**
     * 4. 分類判斷
     */
    
    /**
     * 4.1 判斷文章分類
     */
    private function determine_categories(string $content, string $keyword): array {
        $categories = ['所有文章']; // 預設分類
        
        // 分類關鍵字映射
        $category_keywords = [
            '娛樂城教學' => ['娛樂城', '博弈', '賭場', '老虎機', '百家樂', '撲克', '21點'],
            '虛擬貨幣' => ['比特幣', '以太坊', '加密貨幣', '區塊鏈', 'NFT', 'DeFi', '挖礦'],
            '體育' => ['運動', '球賽', '籃球', '足球', '棒球', '網球', '奧運'],
            '科技' => ['科技', 'AI', '人工智慧', '5G', '物聯網', '軟體', '硬體'],
            '健康' => ['健康', '醫療', '養生', '運動', '減肥', '營養', '疾病'],
            '新聞' => ['新聞', '時事', '政治', '國際', '突發', '快訊', '報導'],
            '明星' => ['明星', '藝人', '偶像', '演員', '歌手', '網紅', '名人'],
            '汽車' => ['汽車', '機車', '電動車', 'Tesla', '車款', '駕駛', '交通'],
            '理財' => ['理財', '投資', '股票', '基金', '保險', '退休', '財務'],
            '生活' => ['生活', '居家', '旅遊', '購物', '時尚', '美妝', '育兒'],
            '社會' => ['社會', '民生', '教育', '文化', '環保', '公益', '法律'],
            '美食' => ['美食', '餐廳', '料理', '食譜', '小吃', '飲料', '烹飪'],
            '追劇' => ['追劇', '影集', '電影', '戲劇', 'Netflix', '綜藝', '動漫']
        ];
        
        // 計算內容相關性
        $content_lower = mb_strtolower($content . ' ' . $keyword);
        $category_scores = [];
        
        foreach ($category_keywords as $category => $keywords) {
            $score = 0;
            foreach ($keywords as $kw) {
                $count = substr_count($content_lower, mb_strtolower($kw));
                $score += $count * (mb_strlen($kw) > 3 ? 2 : 1); // 長關鍵字權重更高
            }
            
            if ($score > 0) {
                $category_scores[$category] = $score;
            }
        }
        
        // 排序並選擇前3個相關分類
        arsort($category_scores);
        $top_categories = array_slice(array_keys($category_scores), 0, 3);
        
        foreach ($top_categories as $cat) {
            if ($category_scores[$cat] > 5) { // 最低門檻
                $categories[] = $cat;
            }
        }
        
        return array_unique($categories);
    }
    
    /**
     * 5. 文章創建
     */
    
    /**
     * 5.1 創建WordPress文章
     */
    private function create_post(array $article, array $categories, array $params): int {
        // 準備文章資料
        $post_data = [
            'post_title' => $article['title'],
            'post_content' => $article['content'],
            'post_excerpt' => $article['excerpt'],
            'post_status' => 'publish',
            'post_author' => get_current_user_id() ?: 1,
            'post_type' => 'post',
            'post_name' => $article['slug'],
            'meta_input' => [
                '_aisc_generated' => true,
                '_aisc_keyword' => $params['keyword'],
                '_aisc_model' => $params['model'],
                '_aisc_cost' => $article['total_cost'],
                '_yoast_wpseo_metadesc' => $article['meta_description']
            ]
        ];
        
        // 創建文章
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            throw new \Exception('文章創建失敗: ' . $post_id->get_error_message());
        }
        
        // 設定分類
        $this->assign_categories($post_id, $categories);
        
        // 設定標籤
        $tags = $this->extract_tags($article['content'], $params['keyword']);
        wp_set_post_tags($post_id, $tags);
        
        return $post_id;
    }
    
    /**
     * 5.2 分配分類
     */
    private function assign_categories(int $post_id, array $category_names): void {
        $category_ids = [];
        
        foreach ($category_names as $cat_name) {
            $category = get_category_by_slug(sanitize_title($cat_name));
            
            if (!$category) {
                // 如果分類不存在，創建它
                $cat_id = wp_create_category($cat_name);
                if ($cat_id) {
                    $category_ids[] = $cat_id;
                }
            } else {
                $category_ids[] = $category->term_id;
            }
        }
        
        if (!empty($category_ids)) {
            wp_set_post_categories($post_id, $category_ids);
        }
    }
    
    /**
     * 5.3 提取標籤
     */
    private function extract_tags(string $content, string $keyword): array {
        $tags = [$keyword]; // 主關鍵字作為標籤
        
        // 提取常見名詞作為標籤
        // 這裡可以整合更複雜的NLP處理
        
        return array_slice(array_unique($tags), 0, 10);
    }
    
    /**
     * 6. 記錄和追蹤
     */
    
    /**
     * 6.1 記錄生成歷史
     */
    private function record_generation_history(int $post_id, array $params, array $article): void {
        $history_data = [
            'post_id' => $post_id,
            'keyword' => $params['keyword'],
            'model' => $params['model'],
            'word_count' => str_word_count(strip_tags($article['content'])),
            'image_count' => $params['images'],
            'generation_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
            'api_cost' => $article['total_cost'],
            'categories' => wp_json_encode(wp_get_post_categories($post_id, ['fields' => 'names'])),
            'status' => 'published',
            'meta_data' => wp_json_encode([
                'tokens' => $article['total_tokens'],
                'title_length' => mb_strlen($article['title']),
                'has_faq' => strpos($article['content'], 'FAQ') !== false
            ])
        ];
        
        // 如果有關鍵字ID，記錄它
        if (isset($params['keyword_id'])) {
            $history_data['keyword_id'] = $params['keyword_id'];
            
            // 標記關鍵字已使用
            $keyword_manager = new KeywordManager();
            $keyword_manager->mark_keyword_used($params['keyword_id']);
        }
        
        $this->db->insert('content_history', $history_data);
        
        // 記錄成本
        if ($this->cost_controller) {
            $this->cost_controller->record_cost([
                'service' => 'openai',
                'model' => $params['model'],
                'tokens_used' => $article['total_tokens'],
                'cost_twd' => $article['total_cost'],
                'post_ids' => [$post_id]
            ]);
        }
    }
    
    /**
     * 7. 排程生成
     */
    
    /**
     * 7.1 生成排程內容
     */
    public function generate_scheduled_content(array $schedule): array {
        $results = [
            'success' => 0,
            'failed' => 0,
            'posts' => []
        ];
        
        try {
            // 解析排程設定
            $settings = json_decode($schedule['content_settings'], true) ?: [];
            
            // 獲取關鍵字
            $keyword_manager = new KeywordManager();
            $keywords = $keyword_manager->get_best_keywords(
                $schedule['type'], 
                $schedule['keyword_count']
            );
            
            if (empty($keywords)) {
                throw new \Exception('沒有可用的關鍵字');
            }
            
            // 為每個關鍵字生成文章
            foreach ($keywords as $keyword) {
                $params = array_merge($settings, [
                    'keyword' => $keyword->keyword,
                    'keyword_id' => $keyword->id,
                    'type' => $schedule['type'],
                    'schedule_id' => $schedule['id']
                ]);
                
                $result = $this->generate_article($params);
                
                if ($result['success']) {
                    $results['success']++;
                    $results['posts'][] = $result['post_id'];
                } else {
                    $results['failed']++;
                    $this->logger->warning('排程文章生成失敗', [
                        'schedule_id' => $schedule['id'],
                        'keyword' => $keyword->keyword,
                        'error' => $result['message']
                    ]);
                }
            }
            
            // 更新排程統計
            $this->update_schedule_stats($schedule['id'], $results);
            
        } catch (\Exception $e) {
            $this->logger->error('排程執行失敗', [
                'schedule_id' => $schedule['id'],
                'error' => $e->getMessage()
            ]);
        }
        
        return $results;
    }
    
    /**
     * 7.2 更新排程統計
     */
    private function update_schedule_stats(int $schedule_id, array $results): void {
        $schedule = $this->db->get_row('schedules', ['id' => $schedule_id]);
        
        if ($schedule) {
            $this->db->update('schedules', [
                'last_run' => current_time('mysql'),
                'run_count' => $schedule->run_count + 1,
                'success_count' => $schedule->success_count + $results['success'],
                'failure_count' => $schedule->failure_count + $results['failed']
            ], ['id' => $schedule_id]);
        }
    }
    
    /**
     * 8. 輔助方法
     */
    
    /**
     * 8.1 檢查成本限制
     */
    private function check_cost_limit(array $params): bool {
        $daily_budget = floatval(get_option('aisc_daily_budget', 1000));
        
        if ($daily_budget <= 0) {
            return true; // 無限制
        }
        
        // 獲取今日花費
        $today_cost = $this->db->sum('costs', 'cost_twd', [
            'date' => date('Y-m-d')
        ]);
        
        // 估算此次花費
        $estimated_cost = $this->estimate_generation_cost($params);
        
        if ($today_cost + $estimated_cost > $daily_budget) {
            $this->logger->warning('成本超支警告', [
                'today_cost' => $today_cost,
                'estimated_cost' => $estimated_cost,
                'daily_budget' => $daily_budget
            ]);
            
            return false;
        }
        
        return true;
    }
    
    /**
     * 8.2 估算生成成本
     */
    private function estimate_generation_cost(array $params): float {
        // 估算token數量（中文約1.5字=1token）
        $estimated_tokens = $params['length'] / 1.5;
        
        // 加上提示詞和回應的額外token
        $total_tokens = $estimated_tokens * 1.3;
        
        if (!isset(self::MODEL_PRICING[$params['model']])) {
            return 50; // 預設估值
        }
        
        $pricing = self::MODEL_PRICING[$params['model']];
        $cost_usd = ($total_tokens / 1000) * $pricing['output'];
        
        return round($cost_usd * 30, 2); // 轉換為台幣
    }
    
    /**
     * 8.3 解析結構回應
     */
    private function parse_structure_response(array $response): array {
        $content = $response['content'];
        
        // 嘗試解析JSON
        if (preg_match('/\{.*\}/s', $content, $matches)) {
            $json = json_decode($matches[0], true);
            if ($json) {
                return $json;
            }
        }
        
        // 如果解析失敗，使用預設結構
        return [
            'title' => '文章標題',
            'description' => '文章描述',
            'outline' => [
                ['title' => '引言', 'subheadings' => []],
                ['title' => '主要內容', 'subheadings' => ['子標題1', '子標題2']],
                ['title' => '結論', 'subheadings' => []]
            ],
            'faq' => array_map(function($i) {
                return "常見問題 $i";
            }, range(1, 10))
        ];
    }
    
    /**
     * 8.4 生成FAQ內容
     */
    private function generate_faq_content(array $questions, string $keyword, string $model): array {
        $faq_prompt = "請為以下FAQ問題提供簡潔但完整的答案（每個答案100-150字）：\n\n";
        $faq_prompt .= "關鍵字：{$keyword}\n\n";
        
        foreach ($questions as $i => $q) {
            $faq_prompt .= ($i + 1) . ". {$q}\n";
        }
        
        $response = $this->call_openai_api([
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => '你是一位專業的FAQ內容撰寫專家。'],
                ['role' => 'user', 'content' => $faq_prompt]
            ],
            'temperature' => 0.7,
            'max_tokens' => 2000
        ]);
        
        // 格式化FAQ內容
        $faq_html = $this->format_faq_content($questions, $response['content']);
        
        return [
            'section' => ['title' => '常見問題 FAQ'],
            'content' => $faq_html,
            'tokens' => $response['usage']['total_tokens'] ?? 0,
            'cost' => $this->calculate_cost($response['usage'] ?? [], $model)
        ];
    }
    
    /**
     * 8.5 格式化FAQ內容
     */
    private function format_faq_content(array $questions, string $answers): string {
        $html = '<div class="aisc-faq-section" itemscope itemtype="https://schema.org/FAQPage">';
        
        // 解析答案
        $answer_lines = explode("\n", $answers);
        $parsed_answers = [];
        $current_answer = '';
        $current_num = 0;
        
        foreach ($answer_lines as $line) {
            if (preg_match('/^(\d+)\.\s*(.*)/', $line, $matches)) {
                if ($current_num > 0) {
                    $parsed_answers[$current_num] = trim($current_answer);
                }
                $current_num = intval($matches[1]);
                $current_answer = $matches[2];
            } else {
                $current_answer .= ' ' . $line;
            }
        }
        
        if ($current_num > 0) {
            $parsed_answers[$current_num] = trim($current_answer);
        }
        
        // 生成FAQ HTML
        foreach ($questions as $i => $question) {
            $num = $i + 1;
            $answer = $parsed_answers[$num] ?? '答案生成中...';
            
            $html .= sprintf('
                <div class="faq-item" itemscope itemtype="https://schema.org/Question">
                    <h3 class="faq-question" itemprop="name">%s</h3>
                    <div class="faq-answer" itemscope itemtype="https://schema.org/Answer" itemprop="acceptedAnswer">
                        <div itemprop="text">%s</div>
                    </div>
                </div>',
                esc_html($question),
                wpautop($answer)
            );
        }
        
        $html .= '</div>';
        
        return $html;
    }
}