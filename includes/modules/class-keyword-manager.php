<?php
/**
 * 檔案：includes/modules/class-keyword-manager.php
 * 功能：關鍵字管理模組
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
 * 關鍵字管理類別
 * 
 * 負責抓取Google Trends關鍵字、分析競爭度、管理關鍵字資料
 */
class KeywordManager {
    
    /**
     * 資料庫實例
     */
    private Database $db;
    
    /**
     * 日誌實例
     */
    private Logger $logger;
    
    /**
     * Google Trends URL
     */
    private const TRENDS_URL = 'https://trends.google.com.tw/trends/trendingsearches/daily/rss';
    
    /**
     * Google搜尋建議URL
     */
    private const SUGGEST_URL = 'https://suggestqueries.google.com/complete/search';
    
    /**
     * 建構函數
     */
    public function __construct() {
        $this->db = new Database();
        $this->logger = new Logger();
    }
    
    /**
     * 1. 關鍵字抓取主要方法
     */
    
    /**
     * 1.1 更新關鍵字
     */
    public function update_keywords(string $type = 'all'): array {
        $this->logger->info('開始更新關鍵字', ['type' => $type]);
        
        $results = [
            'success' => true,
            'general' => ['count' => 0, 'keywords' => []],
            'casino' => ['count' => 0, 'keywords' => []],
            'errors' => []
        ];
        
        try {
            // 更新一般關鍵字
            if ($type === 'all' || $type === 'general') {
                $general_keywords = $this->fetch_trending_keywords();
                $results['general'] = $this->process_keywords($general_keywords, 'general');
            }
            
            // 更新娛樂城關鍵字
            if ($type === 'all' || $type === 'casino') {
                $casino_keywords = $this->fetch_casino_keywords();
                $results['casino'] = $this->process_keywords($casino_keywords, 'casino');
            }
            
            // 更新關鍵字優先級
            $this->update_priority_scores();
            
            // 記錄成功
            $this->logger->info('關鍵字更新完成', $results);
            
        } catch (\Exception $e) {
            $results['success'] = false;
            $results['errors'][] = $e->getMessage();
            $this->logger->error('關鍵字更新失敗', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        
        return $results;
    }
    
    /**
     * 1.2 抓取Google Trends熱門關鍵字
     */
    private function fetch_trending_keywords(): array {
        $keywords = [];
        
        try {
            // 方法1: RSS Feed
            $keywords = array_merge($keywords, $this->fetch_from_rss());
            
            // 方法2: 網頁爬蟲
            $keywords = array_merge($keywords, $this->fetch_from_web_scraping());
            
            // 方法3: 相關搜尋建議
            $keywords = array_merge($keywords, $this->fetch_from_suggestions());
            
            // 去重並限制數量
            $keywords = array_unique($keywords);
            $keywords = array_slice($keywords, 0, 30);
            
        } catch (\Exception $e) {
            $this->logger->error('抓取熱門關鍵字失敗', ['error' => $e->getMessage()]);
        }
        
        return $keywords;
    }
    
    /**
     * 1.3 從RSS抓取
     */
    private function fetch_from_rss(): array {
        $keywords = [];
        
        $response = wp_remote_get(self::TRENDS_URL, [
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]
        ]);
        
        if (is_wp_error($response)) {
            throw new \Exception('RSS請求失敗: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        
        // 解析RSS
        $xml = simplexml_load_string($body);
        if ($xml && isset($xml->channel->item)) {
            foreach ($xml->channel->item as $item) {
                $keywords[] = (string) $item->title;
            }
        }
        
        return $keywords;
    }
    
    /**
     * 1.4 網頁爬蟲方式
     */
    private function fetch_from_web_scraping(): array {
        $keywords = [];
        
        $urls = [
            'https://trends.google.com.tw/trends/trendingsearches/daily?geo=TW',
            'https://trends.google.com.tw/trends/trendingsearches/realtime?geo=TW&category=all'
        ];
        
        foreach ($urls as $url) {
            try {
                $response = wp_remote_get($url, [
                    'timeout' => 30,
                    'headers' => [
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                        'Accept-Language' => 'zh-TW,zh;q=0.9,en;q=0.8',
                        'Accept-Encoding' => 'gzip, deflate, br',
                        'DNT' => '1',
                        'Connection' => 'keep-alive',
                        'Upgrade-Insecure-Requests' => '1'
                    ],
                    'cookies' => [
                        'NID' => $this->get_google_nid_cookie()
                    ]
                ]);
                
                if (!is_wp_error($response)) {
                    $body = wp_remote_retrieve_body($response);
                    
                    // 解析HTML尋找關鍵字
                    $extracted = $this->extract_keywords_from_html($body);
                    $keywords = array_merge($keywords, $extracted);
                }
                
            } catch (\Exception $e) {
                $this->logger->warning('網頁爬蟲失敗', [
                    'url' => $url,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $keywords;
    }
    
    /**
     * 1.5 從HTML提取關鍵字
     */
    private function extract_keywords_from_html(string $html): array {
        $keywords = [];
        
        // 使用正則表達式提取可能的關鍵字
        $patterns = [
            '/<div[^>]*class="[^"]*trending[^"]*"[^>]*>([^<]+)<\/div>/i',
            '/<span[^>]*class="[^"]*query[^"]*"[^>]*>([^<]+)<\/span>/i',
            '/<a[^>]*href="[^"]*\/search\?q=([^"&]+)[^"]*"[^>]*>/i',
            '/data-query="([^"]+)"/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                foreach ($matches[1] as $match) {
                    $keyword = urldecode(html_entity_decode(strip_tags($match)));
                    if (strlen($keyword) > 2 && strlen($keyword) < 100) {
                        $keywords[] = $keyword;
                    }
                }
            }
        }
        
        return array_unique($keywords);
    }
    
    /**
     * 1.6 從Google搜尋建議獲取
     */
    private function fetch_from_suggestions(): array {
        $keywords = [];
        $seed_terms = ['台灣', '2024', '最新', '熱門', '推薦'];
        
        foreach ($seed_terms as $term) {
            $url = add_query_arg([
                'q' => $term,
                'client' => 'firefox',
                'hl' => 'zh-TW',
                'gl' => 'TW'
            ], self::SUGGEST_URL);
            
            $response = wp_remote_get($url, [
                'timeout' => 10,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ]
            ]);
            
            if (!is_wp_error($response)) {
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                
                if (isset($data[1]) && is_array($data[1])) {
                    $keywords = array_merge($keywords, $data[1]);
                }
            }
        }
        
        return array_unique($keywords);
    }
    
    /**
     * 1.7 抓取娛樂城相關關鍵字
     */
    private function fetch_casino_keywords(): array {
        $base_keywords = [
            '線上娛樂城', '博弈遊戲', '老虎機', '百家樂', '德州撲克',
            '運彩', '體育博彩', '真人娛樂', '電子遊戲', '捕魚機',
            '輪盤', '21點', '骰寶', '賓果', '彩票',
            '娛樂城推薦', '娛樂城優惠', '娛樂城體驗金', '娛樂城註冊', '娛樂城出金'
        ];
        
        $modifiers = [
            '2024', '2025', '最新', '推薦', '排名',
            '優惠', '攻略', '技巧', '心得', '評價',
            'PTT', 'Dcard', '論壇', '討論', '分享'
        ];
        
        $keywords = [];
        
        // 組合關鍵字
        foreach ($base_keywords as $base) {
            $keywords[] = $base;
            
            // 加入修飾詞組合
            foreach ($modifiers as $modifier) {
                if (rand(0, 1)) { // 50%機率加入
                    $keywords[] = $base . ' ' . $modifier;
                }
            }
        }
        
        // 從搜尋建議擴展
        foreach (array_slice($base_keywords, 0, 5) as $base) {
            $suggestions = $this->get_search_suggestions($base);
            $keywords = array_merge($keywords, $suggestions);
        }
        
        // 去重並隨機選擇30個
        $keywords = array_unique($keywords);
        shuffle($keywords);
        
        return array_slice($keywords, 0, 30);
    }
    
    /**
     * 2. 關鍵字處理和分析
     */
    
    /**
     * 2.1 處理關鍵字
     */
    private function process_keywords(array $keywords, string $type): array {
        $result = [
            'count' => 0,
            'keywords' => []
        ];
        
        foreach ($keywords as $keyword) {
            $keyword = trim($keyword);
            
            if (empty($keyword) || strlen($keyword) < 2) {
                continue;
            }
            
            // 檢查是否已存在
            $existing = $this->db->get_row('keywords', [
                'keyword' => $keyword,
                'type' => $type
            ]);
            
            if ($existing) {
                // 更新現有關鍵字
                $this->update_keyword_data($existing->id, $keyword);
            } else {
                // 新增關鍵字
                $keyword_data = $this->analyze_keyword($keyword);
                $keyword_data['keyword'] = $keyword;
                $keyword_data['type'] = $type;
                $keyword_data['status'] = 'active';
                
                $id = $this->db->insert('keywords', $keyword_data);
                
                if ($id) {
                    $result['count']++;
                    $result['keywords'][] = $keyword;
                }
            }
        }
        
        return $result;
    }
    
    /**
     * 2.2 分析關鍵字
     */
    private function analyze_keyword(string $keyword): array {
        $data = [
            'search_volume' => $this->estimate_search_volume($keyword),
            'competition_level' => $this->analyze_competition($keyword),
            'cpc_value' => $this->estimate_cpc($keyword),
            'trend_score' => $this->calculate_trend_score($keyword),
            'priority_score' => 0 // 稍後計算
        ];
        
        return $data;
    }
    
    /**
     * 2.3 估算搜尋量
     */
    private function estimate_search_volume(string $keyword): int {
        // 基於關鍵字長度和類型估算
        $base_volume = 1000;
        
        // 短關鍵字通常搜尋量較高
        $length_factor = max(1, 5 - strlen(mb_convert_encoding($keyword, 'UTF-8')));
        
        // 包含數字（如年份）的關鍵字可能更熱門
        if (preg_match('/\d{4}/', $keyword)) {
            $base_volume *= 1.5;
        }
        
        // 包含熱門詞彙
        $hot_words = ['推薦', '最新', '排名', '攻略', '2024', '2025'];
        foreach ($hot_words as $word) {
            if (strpos($keyword, $word) !== false) {
                $base_volume *= 1.2;
            }
        }
        
        return intval($base_volume * $length_factor * (rand(50, 150) / 100));
    }
    
    /**
     * 2.4 分析競爭度
     */
    private function analyze_competition(string $keyword): float {
        // 使用Google搜尋結果數量估算競爭度
        $search_results = $this->get_search_results_count($keyword);
        
        // 將結果數量轉換為0-100的競爭度分數
        if ($search_results < 100000) {
            return 10;
        } elseif ($search_results < 500000) {
            return 20;
        } elseif ($search_results < 1000000) {
            return 30;
        } elseif ($search_results < 5000000) {
            return 50;
        } elseif ($search_results < 10000000) {
            return 70;
        } else {
            return 90;
        }
    }
    
    /**
     * 2.5 獲取搜尋結果數量
     */
    private function get_search_results_count(string $keyword): int {
        // 使用快取
        $cache_key = 'search_results_' . md5($keyword);
        $cached = $this->db->get_cache($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $url = 'https://www.google.com.tw/search?' . http_build_query([
            'q' => $keyword,
            'hl' => 'zh-TW',
            'gl' => 'TW'
        ]);
        
        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]
        ]);
        
        if (is_wp_error($response)) {
            return 1000000; // 預設值
        }
        
        $body = wp_remote_retrieve_body($response);
        
        // 尋找結果數量
        if (preg_match('/約有\s*([\d,]+)\s*項結果/u', $body, $matches) ||
            preg_match('/About\s*([\d,]+)\s*results/i', $body, $matches)) {
            $count = intval(str_replace(',', '', $matches[1]));
            $this->db->set_cache($cache_key, $count, 86400); // 快取24小時
            return $count;
        }
        
        return 1000000; // 預設值
    }
    
    /**
     * 2.6 估算CPC價值
     */
    private function estimate_cpc(string $keyword): float {
        // 基於關鍵字類型和商業價值估算
        $base_cpc = 5.0; // 基礎CPC (台幣)
        
        // 商業關鍵字
        $commercial_terms = ['購買', '價格', '費用', '推薦', '比較', '優惠', '折扣'];
        foreach ($commercial_terms as $term) {
            if (strpos($keyword, $term) !== false) {
                $base_cpc *= 1.5;
            }
        }
        
        // 娛樂城相關
        $casino_terms = ['娛樂城', '博弈', '賭場', '老虎機', '百家樂'];
        foreach ($casino_terms as $term) {
            if (strpos($keyword, $term) !== false) {
                $base_cpc *= 3.0; // 娛樂城關鍵字CPC較高
            }
        }
        
        // 加入隨機變化
        $base_cpc *= (rand(70, 130) / 100);
        
        return round($base_cpc, 2);
    }
    
    /**
     * 2.7 計算趨勢分數
     */
    private function calculate_trend_score(string $keyword): float {
        // 基於時效性和熱度計算
        $score = 50.0;
        
        // 包含當前年份
        if (strpos($keyword, date('Y')) !== false) {
            $score += 20;
        }
        
        // 包含季節性詞彙
        $seasonal_terms = $this->get_seasonal_terms();
        foreach ($seasonal_terms as $term) {
            if (strpos($keyword, $term) !== false) {
                $score += 15;
            }
        }
        
        // 包含趨勢詞彙
        $trending_terms = ['AI', '元宇宙', 'ChatGPT', 'NFT', '虛擬貨幣'];
        foreach ($trending_terms as $term) {
            if (stripos($keyword, $term) !== false) {
                $score += 10;
            }
        }
        
        return min(100, $score);
    }
    
    /**
     * 2.8 獲取季節性詞彙
     */
    private function get_seasonal_terms(): array {
        $month = intval(date('n'));
        
        $terms = [];
        
        // 春季 (3-5月)
        if ($month >= 3 && $month <= 5) {
            $terms = ['春天', '春季', '母親節', '畢業'];
        }
        // 夏季 (6-8月)
        elseif ($month >= 6 && $month <= 8) {
            $terms = ['夏天', '夏季', '暑假', '旅遊', '海邊'];
        }
        // 秋季 (9-11月)
        elseif ($month >= 9 && $month <= 11) {
            $terms = ['秋天', '秋季', '中秋', '萬聖節', '雙11'];
        }
        // 冬季 (12-2月)
        else {
            $terms = ['冬天', '冬季', '聖誕', '新年', '春節'];
        }
        
        return $terms;
    }
    
    /**
     * 3. 優先級計算
     */
    
    /**
     * 3.1 更新所有關鍵字優先級分數
     */
    public function update_priority_scores(): void {
        $keywords = $this->db->get_results('keywords', ['status' => 'active']);
        
        foreach ($keywords as $keyword) {
            $score = $this->calculate_priority_score($keyword);
            
            $this->db->update('keywords', 
                ['priority_score' => $score],
                ['id' => $keyword->id]
            );
        }
    }
    
    /**
     * 3.2 計算優先級分數
     */
    private function calculate_priority_score(object $keyword): float {
        // 優先級分數 = (搜尋量 × 0.4) + ((100 - 競爭度) × 0.3) + (趨勢分數 × 0.2) + (CPC價值 × 0.1)
        
        $search_volume_score = min(100, $keyword->search_volume / 100);
        $competition_score = 100 - $keyword->competition_level;
        $trend_score = $keyword->trend_score;
        $cpc_score = min(100, $keyword->cpc_value * 2);
        
        $priority = ($search_volume_score * 0.4) +
                   ($competition_score * 0.3) +
                   ($trend_score * 0.2) +
                   ($cpc_score * 0.1);
        
        // 使用次數懲罰
        if ($keyword->use_count > 0) {
            $penalty = min(30, $keyword->use_count * 5);
            $priority -= $penalty;
        }
        
        // 最近使用時間懲罰
        if ($keyword->last_used) {
            $days_since_used = (time() - strtotime($keyword->last_used)) / 86400;
            if ($days_since_used < 7) {
                $priority -= (7 - $days_since_used) * 5;
            }
        }
        
        return max(0, min(100, $priority));
    }
    
    /**
     * 4. 關鍵字選擇和使用
     */
    
    /**
     * 4.1 取得最佳關鍵字
     */
    public function get_best_keywords(string $type = 'all', int $count = 5): array {
        $where = ['status' => 'active'];
        
        if ($type !== 'all') {
            $where['type'] = $type;
        }
        
        return $this->db->get_results('keywords', $where, [
            'orderby' => 'priority_score',
            'order' => 'DESC',
            'limit' => $count
        ]);
    }
    
    /**
     * 4.2 標記關鍵字已使用
     */
    public function mark_keyword_used(int $keyword_id): void {
        $keyword = $this->db->get_row('keywords', ['id' => $keyword_id]);
        
        if ($keyword) {
            $this->db->update('keywords', [
                'last_used' => current_time('mysql'),
                'use_count' => $keyword->use_count + 1
            ], ['id' => $keyword_id]);
            
            // 重新計算優先級
            $updated = $this->db->get_row('keywords', ['id' => $keyword_id]);
            $new_score = $this->calculate_priority_score($updated);
            
            $this->db->update('keywords', 
                ['priority_score' => $new_score],
                ['id' => $keyword_id]
            );
        }
    }
    
    /**
     * 5. 關鍵字管理
     */
    
    /**
     * 5.1 新增自訂關鍵字
     */
    public function add_custom_keyword(string $keyword, string $type = 'general'): int|false {
        $keyword = trim($keyword);
        
        if (empty($keyword)) {
            return false;
        }
        
        // 檢查是否已存在
        $existing = $this->db->get_row('keywords', [
            'keyword' => $keyword,
            'type' => $type
        ]);
        
        if ($existing) {
            return $existing->id;
        }
        
        // 分析並新增
        $data = $this->analyze_keyword($keyword);
        $data['keyword'] = $keyword;
        $data['type'] = $type;
        $data['status'] = 'active';
        
        return $this->db->insert('keywords', $data);
    }
    
    /**
     * 5.2 更新關鍵字狀態
     */
    public function update_keyword_status(int $keyword_id, string $status): bool {
        return $this->db->update('keywords', 
            ['status' => $status],
            ['id' => $keyword_id]
        ) !== false;
    }
    
    /**
     * 5.3 刪除關鍵字
     */
    public function delete_keyword(int $keyword_id): bool {
        return $this->db->delete('keywords', ['id' => $keyword_id]) !== false;
    }
    
    /**
     * 6. 統計和報告
     */
    
    /**
     * 6.1 取得關鍵字統計
     */
    public function get_statistics(): array {
        $stats = [
            'total' => $this->db->count('keywords'),
            'active' => $this->db->count('keywords', ['status' => 'active']),
            'general' => $this->db->count('keywords', ['type' => 'general']),
            'casino' => $this->db->count('keywords', ['type' => 'casino']),
            'used_today' => 0,
            'avg_competition' => $this->db->avg('keywords', 'competition_level'),
            'avg_priority' => $this->db->avg('keywords', 'priority_score'),
            'top_keywords' => $this->get_best_keywords('all', 10)
        ];
        
        // 今日使用數
        $today = date('Y-m-d');
        $stats['used_today'] = $this->db->count('content_history', [
            'created_at' => ['LIKE', $today . '%']
        ]);
        
        return $stats;
    }
    
    /**
     * 6.2 匯出關鍵字
     */
    public function export_keywords(string $format = 'csv'): string {
        $keywords = $this->db->get_results('keywords', [], [
            'orderby' => 'priority_score',
            'order' => 'DESC'
        ]);
        
        $export_dir = AISC_PLUGIN_DIR . 'exports/';
        if (!file_exists($export_dir)) {
            wp_mkdir_p($export_dir);
        }
        
        $filename = 'keywords_' . date('Y-m-d_H-i-s') . '.' . $format;
        $filepath = $export_dir . $filename;
        
        if ($format === 'csv') {
            $this->export_to_csv($keywords, $filepath);
        } else {
            $this->export_to_json($keywords, $filepath);
        }
        
        return $filepath;
    }
    
    /**
     * 7. 輔助方法
     */
    
    /**
     * 7.1 獲取Google NID Cookie
     */
    private function get_google_nid_cookie(): string {
        // 生成隨機NID cookie模擬真實瀏覽器
        return base64_encode(random_bytes(32));
    }
    
    /**
     * 7.2 取得搜尋建議
     */
    private function get_search_suggestions(string $query): array {
        $url = add_query_arg([
            'q' => $query,
            'client' => 'chrome',
            'hl' => 'zh-TW'
        ], self::SUGGEST_URL);
        
        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]
        ]);
        
        if (is_wp_error($response)) {
            return [];
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        return isset($data[1]) ? $data[1] : [];
    }
    
    /**
     * 7.3 更新關鍵字資料
     */
    private function update_keyword_data(int $keyword_id, string $keyword): void {
        // 重新分析關鍵字
        $data = $this->analyze_keyword($keyword);
        $data['updated_at'] = current_time('mysql');
        
        $this->db->update('keywords', $data, ['id' => $keyword_id]);
    }
    
    /**
     * 7.4 匯出為CSV
     */
    private function export_to_csv(array $keywords, string $filepath): void {
        $handle = fopen($filepath, 'w');
        
        // 寫入標題
        fputcsv($handle, [
            'ID', '關鍵字', '類型', '搜尋量', '競爭度', 
            'CPC', '趨勢分數', '優先級', '使用次數', '狀態'
        ]);
        
        // 寫入資料
        foreach ($keywords as $keyword) {
            fputcsv($handle, [
                $keyword->id,
                $keyword->keyword,
                $keyword->type,
                $keyword->search_volume,
                $keyword->competition_level,
                $keyword->cpc_value,
                $keyword->trend_score,
                $keyword->priority_score,
                $keyword->use_count,
                $keyword->status
            ]);
        }
        
        fclose($handle);
    }
    
    /**
     * 7.5 匯出為JSON
     */
    private function export_to_json(array $keywords, string $filepath): void {
        file_put_contents($filepath, wp_json_encode($keywords, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}