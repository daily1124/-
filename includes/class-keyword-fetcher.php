<?php
/**
 * 關鍵字抓取器類別 - 完整修正版 v4.0
 * 負責從外部來源抓取熱門關鍵字
 * 
 * @version 4.0
 * @author AICG Team
 */

// 防止直接訪問
if (!defined('ABSPATH')) {
    exit;
}

class AICG_Keyword_Fetcher {
    
    private static $instance = null;
    private $max_keywords_per_source = 30;
    private $request_timeout = 30;
    private $user_agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    
    /**
     * 取得單例實例
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 私有建構函數
     */
    private function __construct() {
        // 初始化設定
        $this->init_settings();
    }
    
    /**
     * 初始化設定
     */
    private function init_settings() {
        // 設定錯誤處理
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_reporting(E_ALL);
        }
    }
    
    /**
     * 抓取關鍵字主函數
     * @param string $type 關鍵字類型 (taiwan 或 casino)
     * @return bool 成功或失敗
     */
    public function fetch_keywords($type = 'taiwan') {
        global $wpdb;
        
        // 驗證類型
        if (!in_array($type, array('taiwan', 'casino'))) {
            error_log('AICG: 無效的關鍵字類型: ' . $type);
            return false;
        }
        
        $keywords_table = $wpdb->prefix . 'aicg_keywords';
        
        // 根據類型抓取關鍵字
        $keywords = ($type === 'taiwan') ? 
            $this->fetch_taiwan_keywords() : 
            $this->fetch_casino_keywords();
        
        if (empty($keywords)) {
            error_log('AICG: 無法抓取到任何 ' . $type . ' 類型的關鍵字');
            return false;
        }
        
        // 開始事務處理
        $wpdb->query('START TRANSACTION');
        
        try {
            // 清除舊的關鍵字
            $deleted = $wpdb->delete($keywords_table, array('type' => $type), array('%s'));
            error_log('AICG: 已刪除 ' . $deleted . ' 個舊關鍵字');
            
            // 批量插入新關鍵字
            $inserted = 0;
            $values = array();
            $placeholders = array();
            
            foreach ($keywords as $keyword) {
                // 驗證關鍵字
                if (empty($keyword['keyword']) || mb_strlen($keyword['keyword']) > 100) {
                    continue;
                }
                
                $values[] = $keyword['keyword'];
                $values[] = $type;
                $values[] = intval($keyword['volume']);
                $values[] = current_time('mysql');
                $placeholders[] = "(%s, %s, %d, %s)";
            }
            
            if (!empty($placeholders)) {
                $query = "INSERT INTO $keywords_table (keyword, type, traffic_volume, last_updated) VALUES ";
                $query .= implode(', ', $placeholders);
                
                $result = $wpdb->query($wpdb->prepare($query, $values));
                
                if ($result !== false) {
                    $inserted = $result;
                }
            }
            
            // 提交事務
            $wpdb->query('COMMIT');
            
            error_log('AICG: 成功插入 ' . $inserted . ' 個 ' . $type . ' 關鍵字');
            
            // 清理快取
            wp_cache_delete('aicg_keywords_' . $type, 'aicg');
            
            return $inserted > 0;
            
        } catch (Exception $e) {
            // 回滾事務
            $wpdb->query('ROLLBACK');
            error_log('AICG: 插入關鍵字時發生錯誤: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 抓取台灣熱門關鍵字（完全重寫版）
     * @return array
     */
    private function fetch_taiwan_keywords() {
        $collected_keywords = array();
        
        // 方法1: Google Trends RSS Feed（更可靠）
        $this->add_keywords_to_collection(
            $collected_keywords, 
            $this->fetch_google_trends_rss(),
            'google_trends_rss'
        );
        
        // 方法2: DuckDuckGo 搜尋建議（不需要 API Key）
        $this->add_keywords_to_collection(
            $collected_keywords,
            $this->fetch_duckduckgo_suggestions(),
            'duckduckgo'
        );
        
        // 方法3: Bing 搜尋建議
        $this->add_keywords_to_collection(
            $collected_keywords,
            $this->fetch_bing_suggestions(),
            'bing'
        );
        
        // 方法4: 從新聞網站抓取熱門關鍵字
        $this->add_keywords_to_collection(
            $collected_keywords,
            $this->fetch_news_keywords(),
            'news'
        );
        
        // 方法5: Wikipedia 熱門頁面
        $this->add_keywords_to_collection(
            $collected_keywords,
            $this->fetch_wikipedia_trending(),
            'wikipedia'
        );
        
        // 方法6: 自定義來源
        $custom_source = get_option('aicg_keyword_source_url');
        if ($custom_source) {
            $this->add_keywords_to_collection(
                $collected_keywords,
                $this->fetch_from_custom_source($custom_source),
                'custom'
            );
        }
        
        // 如果沒有抓到足夠的關鍵字，使用備用方法
        if (count($collected_keywords) < 20) {
            $this->add_keywords_to_collection(
                $collected_keywords,
                $this->fetch_backup_keywords(),
                'backup'
            );
        }
        
        // 轉換並排序
        $keywords = array_values($collected_keywords);
        usort($keywords, function($a, $b) {
            return $b['volume'] - $a['volume'];
        });
        
        // 確保至少有50個關鍵字
        if (count($keywords) < 50) {
            // 使用變體生成更多關鍵字
            $keywords = $this->expand_keywords($keywords);
        }
        
        // 只保留前50個
        return array_slice($keywords, 0, 50);
    }
    
    /**
     * 從 Google Trends RSS 抓取
     * @return array
     */
    private function fetch_google_trends_rss() {
        $keywords = array();
        
        // Google Trends RSS feed for Taiwan
        $rss_url = 'https://trends.google.com.tw/trends/trendingsearches/daily/rss?geo=TW';
        
        $response = $this->make_http_request($rss_url, array(
            'headers' => array(
                'Accept' => 'application/rss+xml,application/xml;q=0.9,*/*;q=0.8'
            )
        ));
        
        if ($response === false) {
            error_log('AICG: 無法訪問 Google Trends RSS');
            return $keywords;
        }
        
        // 解析 RSS
        $xml = @simplexml_load_string($response);
        if ($xml === false) {
            error_log('AICG: 無法解析 Google Trends RSS');
            return $keywords;
        }
        
        // 提取項目
        if (isset($xml->channel->item)) {
            $rank = 0;
            foreach ($xml->channel->item as $item) {
                $rank++;
                $title = (string) $item->title;
                
                // 從描述中提取流量數據
                $traffic = 100000 - ($rank * 2000); // 根據排名估算流量
                
                if (isset($item->{'ht:approx_traffic'})) {
                    $traffic_str = (string) $item->{'ht:approx_traffic'};
                    $traffic = $this->parse_traffic_volume($traffic_str);
                }
                
                if ($this->is_valid_keyword($title, 2, 20)) {
                    $keywords[] = array(
                        'keyword' => $this->sanitize_keyword($title),
                        'volume' => $traffic
                    );
                }
            }
        }
        
        return $keywords;
    }
    
    /**
     * 從 DuckDuckGo 抓取搜尋建議
     * @return array
     */
    private function fetch_duckduckgo_suggestions() {
        $keywords = array();
        
        // 熱門搜尋種子詞
        $seed_terms = array(
            '台灣', '2024', '2025', '最新', '推薦', '教學', '比較', 
            '評價', '免費', '下載', '線上', '購買', '優惠', '活動',
            '新聞', '排名', '攻略', '心得', '分享', '介紹'
        );
        
        foreach ($seed_terms as $seed) {
            // DuckDuckGo 搜尋建議 API
            $url = 'https://duckduckgo.com/ac/?q=' . urlencode($seed) . '&kl=tw-tzh';
            
            $response = $this->make_http_request($url, array(
                'headers' => array(
                    'Accept' => 'application/json'
                )
            ));
            
            if ($response === false) {
                continue;
            }
            
            $data = json_decode($response, true);
            
            if (is_array($data)) {
                foreach ($data as $item) {
                    if (isset($item['phrase']) && $this->is_valid_keyword($item['phrase'], 2, 15)) {
                        $keywords[] = array(
                            'keyword' => $this->sanitize_keyword($item['phrase']),
                            'volume' => rand(80000, 150000) // 估算流量
                        );
                    }
                }
            }
            
            // 避免過多請求
            usleep(200000); // 200ms
        }
        
        return array_unique($keywords, SORT_REGULAR);
    }
    
    /**
     * 從 Bing 抓取搜尋建議
     * @return array
     */
    private function fetch_bing_suggestions() {
        $keywords = array();
        
        $seed_terms = array('台灣', '台北', '高雄', '台中', '新竹', '桃園');
        
        foreach ($seed_terms as $seed) {
            $url = 'https://api.bing.com/osjson.aspx?query=' . urlencode($seed) . '&market=zh-TW';
            
            $response = $this->make_http_request($url, array(
                'headers' => array(
                    'Accept' => 'application/json'
                )
            ));
            
            if ($response === false) {
                continue;
            }
            
            $data = json_decode($response, true);
            
            if (isset($data[1]) && is_array($data[1])) {
                foreach ($data[1] as $suggestion) {
                    if ($this->is_valid_keyword($suggestion, 2, 15)) {
                        $keywords[] = array(
                            'keyword' => $this->sanitize_keyword($suggestion),
                            'volume' => rand(70000, 130000)
                        );
                    }
                }
            }
            
            usleep(200000); // 200ms
        }
        
        return $keywords;
    }
    
    /**
     * 從新聞網站抓取熱門關鍵字
     * @return array
     */
    private function fetch_news_keywords() {
        $keywords = array();
        $keyword_frequency = array();
        
        // 抓取聯合新聞網熱門新聞
        $url = 'https://udn.com/news/breaknews';
        $response = $this->make_http_request($url);
        
        if ($response !== false) {
            // 提取新聞標題中的關鍵字
            preg_match_all('/<h3[^>]*>.*?<a[^>]*>(.*?)<\/a>.*?<\/h3>/is', $response, $matches);
            
            if (!empty($matches[1])) {
                foreach ($matches[1] as $title) {
                    $title = strip_tags($title);
                    
                    // 提取關鍵字（2-8個字的詞彙）
                    $words = $this->extract_keywords_from_text($title);
                    foreach ($words as $word) {
                        if (isset($keyword_frequency[$word])) {
                            $keyword_frequency[$word]++;
                        } else {
                            $keyword_frequency[$word] = 1;
                        }
                    }
                }
            }
        }
        
        // 轉換為關鍵字陣列（出現2次以上的詞彙）
        foreach ($keyword_frequency as $word => $freq) {
            if ($freq >= 2) {
                $keywords[] = array(
                    'keyword' => $word,
                    'volume' => $freq * rand(20000, 40000)
                );
            }
        }
        
        return array_slice($keywords, 0, 30);
    }
    
    /**
     * 從 Wikipedia 抓取熱門頁面
     * @return array
     */
    private function fetch_wikipedia_trending() {
        $keywords = array();
        
        // Wikipedia API - 最多瀏覽的頁面
        $date = date('Y/m/d', strtotime('-1 day'));
        $url = 'https://zh.wikipedia.org/api/rest_v1/feed/featured/' . $date;
        
        $response = $this->make_http_request($url, array(
            'headers' => array(
                'Accept' => 'application/json'
            )
        ));
        
        if ($response === false) {
            return $keywords;
        }
        
        $data = json_decode($response, true);
        
        if (isset($data['mostread']['articles'])) {
            foreach ($data['mostread']['articles'] as $article) {
                if (isset($article['displaytitle'])) {
                    $title = $article['displaytitle'];
                    $views = isset($article['views']) ? $article['views'] : rand(50000, 100000);
                    
                    if ($this->is_valid_keyword($title, 2, 15)) {
                        $keywords[] = array(
                            'keyword' => $this->sanitize_keyword($title),
                            'volume' => $views
                        );
                    }
                }
            }
        }
        
        return $keywords;
    }
    
    /**
     * 備用關鍵字抓取方法
     * @return array
     */
    private function fetch_backup_keywords() {
        $keywords = array();
        
        // 使用 Google 搜尋的 "其他人也搜尋了" 功能
        $search_terms = array('台灣旅遊', '台北美食', '網路購物', '線上學習', '投資理財');
        
        foreach ($search_terms as $term) {
            $url = 'https://www.google.com/search?q=' . urlencode($term) . '&hl=zh-TW';
            
            $response = $this->make_http_request($url, array(
                'headers' => array(
                    'Accept-Language' => 'zh-TW,zh;q=0.9'
                )
            ));
            
            if ($response === false) {
                continue;
            }
            
            // 提取 "相關搜尋" 關鍵字
            if (preg_match_all('/class="[^"]*BNeawe[^"]*"[^>]*>([^<]+)<\/div>/i', $response, $matches)) {
                foreach ($matches[1] as $related) {
                    $related = html_entity_decode($related, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    
                    if ($this->is_valid_keyword($related, 2, 20)) {
                        $keywords[] = array(
                            'keyword' => $this->sanitize_keyword($related),
                            'volume' => rand(30000, 80000)
                        );
                    }
                }
            }
            
            usleep(500000); // 500ms 避免被封鎖
        }
        
        return $keywords;
    }
    
    /**
     * 擴展關鍵字（生成變體）
     * @param array $keywords
     * @return array
     */
    private function expand_keywords($keywords) {
        $expanded = $keywords;
        
        // 常見的前綴和後綴
        $prefixes = array('最新', '2024', '2025', '台灣', '推薦');
        $suffixes = array('推薦', '比較', '評價', '教學', '心得', '排名');
        
        foreach ($keywords as $kw) {
            $base_keyword = $kw['keyword'];
            
            // 只對較短的關鍵字進行擴展
            if (mb_strlen($base_keyword) <= 8) {
                // 添加前綴
                foreach ($prefixes as $prefix) {
                    if (strpos($base_keyword, $prefix) === false) {
                        $new_keyword = $prefix . $base_keyword;
                        if ($this->is_valid_keyword($new_keyword, 3, 15)) {
                            $expanded[] = array(
                                'keyword' => $new_keyword,
                                'volume' => intval($kw['volume'] * 0.7)
                            );
                        }
                    }
                }
                
                // 添加後綴
                foreach ($suffixes as $suffix) {
                    if (strpos($base_keyword, $suffix) === false) {
                        $new_keyword = $base_keyword . $suffix;
                        if ($this->is_valid_keyword($new_keyword, 3, 15)) {
                            $expanded[] = array(
                                'keyword' => $new_keyword,
                                'volume' => intval($kw['volume'] * 0.6)
                            );
                        }
                    }
                }
            }
            
            if (count($expanded) >= 50) {
                break;
            }
        }
        
        // 去重並排序
        $unique_keywords = array();
        $seen = array();
        
        foreach ($expanded as $kw) {
            if (!isset($seen[$kw['keyword']])) {
                $unique_keywords[] = $kw;
                $seen[$kw['keyword']] = true;
            }
        }
        
        usort($unique_keywords, function($a, $b) {
            return $b['volume'] - $a['volume'];
        });
        
        return $unique_keywords;
    }
    
    /**
     * 從文本中提取關鍵字
     * @param string $text
     * @return array
     */
    private function extract_keywords_from_text($text) {
        $keywords = array();
        
        // 移除標點符號
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        
        // 簡單的中文分詞（基於詞長）
        // 提取2-8個字的連續中文
        if (preg_match_all('/[\x{4e00}-\x{9fa5}]{2,8}/u', $text, $matches)) {
            foreach ($matches[0] as $word) {
                $word = trim($word);
                
                // 過濾停用詞
                $stop_words = array(
                    '的', '是', '在', '有', '和', '了', '不', '為', '這', '個', 
                    '我', '你', '他', '她', '它', '我們', '你們', '他們', '她們',
                    '但是', '因為', '所以', '如果', '雖然', '可是', '或者', '並且'
                );
                
                if (!in_array($word, $stop_words) && mb_strlen($word) >= 2) {
                    $keywords[] = $word;
                }
            }
        }
        
        return $keywords;
    }
    
    /**
     * 抓取娛樂城相關關鍵字（無預設版本）
     * @return array
     */
    private function fetch_casino_keywords() {
        $collected_keywords = array();
        
        // 方法1: 從搜尋引擎獲取真實的娛樂城關鍵字
        $this->add_keywords_to_collection(
            $collected_keywords,
            $this->fetch_casino_search_suggestions(),
            'search_suggestions'
        );
        
        // 方法2: 從相關論壇抓取
        $this->add_keywords_to_collection(
            $collected_keywords,
            $this->fetch_casino_forum_keywords(),
            'forums'
        );
        
        // 方法3: 從娛樂城評論網站抓取
        $this->add_keywords_to_collection(
            $collected_keywords,
            $this->fetch_casino_review_keywords(),
            'reviews'
        );
        
        // 方法4: 自定義來源
        $custom_source = get_option('aicg_casino_keyword_source_url');
        if ($custom_source) {
            $this->add_keywords_to_collection(
                $collected_keywords,
                $this->fetch_from_custom_source($custom_source),
                'custom'
            );
        }
        
        // 如果沒有抓到足夠的關鍵字，使用搜尋建議擴展
        if (count($collected_keywords) < 30) {
            $this->add_keywords_to_collection(
                $collected_keywords,
                $this->expand_casino_keywords(),
                'expanded'
            );
        }
        
        // 排序
        $keywords = array_values($collected_keywords);
        usort($keywords, function($a, $b) {
            return $b['volume'] - $a['volume'];
        });
        
        // 返回前50個
        return array_slice($keywords, 0, 50);
    }
    
    /**
     * 從搜尋引擎獲取娛樂城相關建議
     * @return array
     */
    private function fetch_casino_search_suggestions() {
        $keywords = array();
        
        // 基礎種子詞（用於獲取更多相關詞）
        $seed_terms = array(
            '娛樂城', '線上娛樂城', '博弈', '線上博弈', 
            '老虎機', '百家樂', '運彩', '體育投注'
        );
        
        foreach ($seed_terms as $seed) {
            // DuckDuckGo 建議
            $url = 'https://duckduckgo.com/ac/?q=' . urlencode($seed) . '&kl=tw-tzh';
            $response = $this->make_http_request($url, array(
                'headers' => array('Accept' => 'application/json')
            ));
            
            if ($response !== false) {
                $data = json_decode($response, true);
                if (is_array($data)) {
                    foreach ($data as $item) {
                        if (isset($item['phrase'])) {
                            $keywords[] = array(
                                'keyword' => $this->sanitize_keyword($item['phrase']),
                                'volume' => rand(50000, 120000)
                            );
                        }
                    }
                }
            }
            
            // Bing 建議
            $bing_url = 'https://api.bing.com/osjson.aspx?query=' . urlencode($seed . ' ') . '&market=zh-TW';
            $bing_response = $this->make_http_request($bing_url, array(
                'headers' => array('Accept' => 'application/json')
            ));
            
            if ($bing_response !== false) {
                $bing_data = json_decode($bing_response, true);
                if (isset($bing_data[1]) && is_array($bing_data[1])) {
                    foreach ($bing_data[1] as $suggestion) {
                        $keywords[] = array(
                            'keyword' => $this->sanitize_keyword($suggestion),
                            'volume' => rand(40000, 100000)
                        );
                    }
                }
            }
            
            usleep(300000); // 300ms
        }
        
        return $keywords;
    }
    
    /**
     * 從論壇抓取娛樂城關鍵字
     * @return array
     */
    private function fetch_casino_forum_keywords() {
        $keywords = array();
        $keyword_patterns = array();
        
        // Mobile01 博弈討論區
        $url = 'https://www.mobile01.com/topiclist.php?f=640';
        $response = $this->make_http_request($url);
        
        if ($response !== false) {
            // 提取標題中的關鍵字
            preg_match_all('/<a[^>]*class="topic_gen"[^>]*>(.*?)<\/a>/is', $response, $matches);
            
            if (!empty($matches[1])) {
                foreach ($matches[1] as $title) {
                    $title = strip_tags($title);
                    
                    // 提取娛樂城相關關鍵字
                    if (preg_match('/娛樂城|博弈|老虎機|百家樂|運彩|賭場/u', $title)) {
                        $words = $this->extract_keywords_from_text($title);
                        foreach ($words as $word) {
                            if (preg_match('/娛樂|博弈|賭|機|百家|運彩/u', $word)) {
                                $keyword_patterns[$word] = isset($keyword_patterns[$word]) ? 
                                    $keyword_patterns[$word] + 1 : 1;
                            }
                        }
                    }
                }
            }
        }
        
        // 轉換為關鍵字陣列
        foreach ($keyword_patterns as $keyword => $frequency) {
            if ($frequency >= 1) {
                $keywords[] = array(
                    'keyword' => $keyword,
                    'volume' => $frequency * rand(15000, 35000)
                );
            }
        }
        
        return array_slice($keywords, 0, 30);
    }
    
    /**
     * 從評論網站抓取關鍵字
     * @return array
     */
    private function fetch_casino_review_keywords() {
        $keywords = array();
        
        // 搜尋 "娛樂城評價" 相關結果
        $search_url = 'https://www.google.com/search?q=' . urlencode('娛樂城評價 推薦') . '&hl=zh-TW';
        $response = $this->make_http_request($search_url, array(
            'headers' => array(
                'Accept-Language' => 'zh-TW,zh;q=0.9'
            )
        ));
        
        if ($response !== false) {
            // 提取搜尋結果中的關鍵字
            preg_match_all('/<h3[^>]*>(.*?)<\/h3>/is', $response, $matches);
            
            if (!empty($matches[1])) {
                foreach ($matches[1] as $result) {
                    $result = strip_tags($result);
                    
                    // 提取品牌名稱和相關詞
                    if (preg_match_all('/([^-–\s]+娛樂城|[^-–\s]+博弈|[A-Za-z0-9]+)/u', $result, $brand_matches)) {
                        foreach ($brand_matches[0] as $brand) {
                            if ($this->is_valid_keyword($brand, 2, 12)) {
                                $keywords[] = array(
                                    'keyword' => $this->sanitize_keyword($brand),
                                    'volume' => rand(20000, 60000)
                                );
                            }
                        }
                    }
                }
            }
        }
        
        return array_unique($keywords, SORT_REGULAR);
    }
    
    /**
     * 擴展娛樂城關鍵字
     * @return array
     */
    private function expand_casino_keywords() {
        $keywords = array();
        
        // 遊戲類型組合
        $game_types = array('老虎機', '百家樂', '21點', '輪盤', '骰寶', '德州撲克');
        $modifiers = array('線上', '免費', '真錢', '手機', 'APP');
        
        foreach ($game_types as $game) {
            foreach ($modifiers as $modifier) {
                $keyword = $modifier . $game;
                $keywords[] = array(
                    'keyword' => $keyword,
                    'volume' => rand(15000, 40000)
                );
            }
        }
        
        // 娛樂城相關詞組合
        $casino_terms = array('娛樂城', '博弈網');
        $attributes = array('推薦', '排名', '優惠', '註冊', '出金', '評價', 'PTT');
        
        foreach ($casino_terms as $term) {
            foreach ($attributes as $attr) {
                $keyword = $term . $attr;
                $keywords[] = array(
                    'keyword' => $keyword,
                    'volume' => rand(20000, 50000)
                );
            }
        }
        
        return $keywords;
    }
    
    /**
     * 將關鍵字加入收集陣列
     */
    private function add_keywords_to_collection(&$collection, $keywords, $source) {
        if (!is_array($keywords)) {
            return;
        }
        
        foreach ($keywords as $kw) {
            if (isset($kw['keyword']) && !empty($kw['keyword'])) {
                $key = md5($kw['keyword']); // 使用 MD5 作為唯一鍵
                
                if (!isset($collection[$key]) || $collection[$key]['volume'] < $kw['volume']) {
                    $collection[$key] = array(
                        'keyword' => $kw['keyword'],
                        'volume' => $kw['volume'],
                        'source' => $source
                    );
                }
            }
        }
    }
    
    /**
     * 從自定義來源抓取關鍵字
     * @param string $url
     * @return array
     */
    private function fetch_from_custom_source($url) {
        $keywords = array();
        
        // 驗證 URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            error_log('AICG: 無效的自定義來源 URL: ' . $url);
            return $keywords;
        }
        
        $response = $this->make_http_request($url, array(
            'timeout' => 60,
            'headers' => array(
                'Accept' => 'application/json'
            )
        ));
        
        if ($response === false) {
            return $keywords;
        }
        
        $data = json_decode($response, true);
        
        if (is_array($data)) {
            foreach ($data as $item) {
                if (isset($item['keyword']) && $this->is_valid_keyword($item['keyword'], 2, 50)) {
                    $keywords[] = array(
                        'keyword' => $this->sanitize_keyword($item['keyword']),
                        'volume' => isset($item['volume']) ? intval($item['volume']) : rand(5000, 20000)
                    );
                }
            }
        }
        
        return $keywords;
    }
    
    /**
     * 統一的 HTTP 請求函數
     * @param string $url
     * @param array $args
     * @return string|false
     */
    private function make_http_request($url, $args = array()) {
        $default_args = array(
            'timeout' => $this->request_timeout,
            'redirection' => 5,
            'httpversion' => '1.1',
            'headers' => array(
                'User-Agent' => $this->user_agent,
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'zh-TW,zh;q=0.9,en;q=0.8',
                'Accept-Encoding' => 'gzip, deflate',
                'Connection' => 'keep-alive',
                'Cache-Control' => 'max-age=0'
            ),
            'sslverify' => false
        );
        
        // 合併參數
        $args = wp_parse_args($args, $default_args);
        
        // 發送請求
        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            error_log('AICG HTTP 請求錯誤 (' . $url . '): ' . $response->get_error_message());
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code !== 200) {
            error_log('AICG HTTP 請求失敗 (' . $url . '): HTTP ' . $response_code);
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        
        if (empty($body)) {
            error_log('AICG HTTP 請求返回空內容 (' . $url . ')');
            return false;
        }
        
        return $body;
    }
    
    /**
     * 驗證關鍵字是否有效
     * @param string $keyword
     * @param int $min_length
     * @param int $max_length
     * @return bool
     */
    private function is_valid_keyword($keyword, $min_length = 2, $max_length = 50) {
        if (empty($keyword)) {
            return false;
        }
        
        $length = mb_strlen($keyword);
        
        if ($length < $min_length || $length > $max_length) {
            return false;
        }
        
        // 過濾無效字符
        if (preg_match('/[\x00-\x1F\x7F]/', $keyword)) {
            return false;
        }
        
        // 過濾純數字或純符號
        if (preg_match('/^[\d\s\p{P}]+$/u', $keyword)) {
            return false;
        }
        
        // 過濾包含過多特殊字符的關鍵字
        $special_char_count = preg_match_all('/[^\p{L}\p{N}\s]/u', $keyword);
        if ($special_char_count > $length * 0.3) {
            return false;
        }
        
        return true;
    }
    
    /**
     * 清理和標準化關鍵字
     * @param string $keyword
     * @return string
     */
    private function sanitize_keyword($keyword) {
        // 移除多餘空白
        $keyword = preg_replace('/\s+/u', ' ', $keyword);
        
        // 移除首尾空白
        $keyword = trim($keyword);
        
        // 移除控制字符
        $keyword = preg_replace('/[\x00-\x1F\x7F]/u', '', $keyword);
        
        // HTML 實體解碼
        $keyword = html_entity_decode($keyword, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // 移除 HTML 標籤
        $keyword = strip_tags($keyword);
        
        // WordPress 清理
        $keyword = sanitize_text_field($keyword);
        
        return $keyword;
    }
    
    /**
     * 解析流量數字
     * @param string $traffic
     * @return int
     */
    private function parse_traffic_volume($traffic) {
        if (empty($traffic)) {
            return rand(10000, 50000);
        }
        
        // 移除非數字字符（保留 K, M）
        $traffic = preg_replace('/[^\d.KM]/i', '', $traffic);
        
        if (stripos($traffic, 'M') !== false) {
            $number = floatval(str_ireplace('M', '', $traffic));
            return intval($number * 1000000);
        } elseif (stripos($traffic, 'K') !== false) {
            $number = floatval(str_ireplace('K', '', $traffic));
            return intval($number * 1000);
        } else {
            $number = intval($traffic);
            return $number > 0 ? $number : rand(10000, 50000);
        }
    }
    
    /**
     * 顯示關鍵字列表
     * @param string $type
     */
    public function display_keywords($type) {
        global $wpdb;
        
        // 驗證類型
        if (!in_array($type, array('taiwan', 'casino'))) {
            echo '<div class="notice notice-error"><p>無效的關鍵字類型</p></div>';
            return;
        }
        
        $keywords_table = $wpdb->prefix . 'aicg_keywords';
        
        // 從快取獲取
        $cache_key = 'aicg_keywords_' . $type;
        $keywords = wp_cache_get($cache_key, 'aicg');
        
        if ($keywords === false) {
            $keywords = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $keywords_table WHERE type = %s ORDER BY traffic_volume DESC LIMIT 100",
                $type
            ));
            
            // 設置快取（5分鐘）
            wp_cache_set($cache_key, $keywords, 'aicg', 300);
        }
        
        if ($keywords) {
            $type_name = ($type === 'taiwan') ? '台灣熱門' : '娛樂城';
            
            // 生成唯一的 nonce
            $fetch_nonce = wp_create_nonce('aicg_fetch_keywords');
            $export_nonce = wp_create_nonce('aicg_export_keywords');
            ?>
            <div class="aicg-keywords-summary">
                <h3><?php echo esc_html($type_name); ?>關鍵字</h3>
                <p>共有 <strong><?php echo count($keywords); ?></strong> 個關鍵字，
                最後更新時間：<strong><?php echo isset($keywords[0]) ? human_time_diff(strtotime($keywords[0]->last_updated)) . ' 前' : '-'; ?></strong></p>
                
                <div class="aicg-keywords-actions">
                    <button type="button" class="button button-primary aicg-refresh-keywords" data-type="<?php echo esc_attr($type); ?>" data-nonce="<?php echo esc_attr($fetch_nonce); ?>">
                        <span class="dashicons dashicons-update"></span> 立即更新
                    </button>
                    <button type="button" class="button aicg-export-keywords" data-type="<?php echo esc_attr($type); ?>" data-nonce="<?php echo esc_attr($export_nonce); ?>">
                        <span class="dashicons dashicons-download"></span> 匯出 CSV
                    </button>
                </div>
            </div>
            
            <table class="widefat striped aicg-keywords-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">排名</th>
                        <th>關鍵字</th>
                        <th style="width: 150px;">預估流量</th>
                        <th style="width: 150px;">最後更新</th>
                        <th style="width: 100px;">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $rank = 1;
                    foreach ($keywords as $keyword): 
                    ?>
                    <tr>
                        <td style="text-align: center;">
                            <span class="aicg-rank-badge"><?php echo $rank++; ?></span>
                        </td>
                        <td>
                            <strong><?php echo esc_html($keyword->keyword); ?></strong>
                        </td>
                        <td style="text-align: right;">
                            <span class="aicg-traffic-volume"><?php echo number_format($keyword->traffic_volume); ?></span>
                        </td>
                        <td>
                            <span class="aicg-time-ago"><?php echo human_time_diff(strtotime($keyword->last_updated)) . ' 前'; ?></span>
                        </td>
                        <td style="text-align: center;">
                            <button type="button" class="button button-small aicg-use-keyword" 
                                    data-keyword="<?php echo esc_attr($keyword->keyword); ?>">
                                使用
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php
        } else {
            echo '<div class="notice notice-warning"><p>尚無關鍵字資料，請先抓取關鍵字。</p></div>';
        }
    }
    
    /**
     * 取得隨機關鍵字
     * @param string $type
     * @param int $count
     * @return array
     */
    public function get_random_keywords($type, $count) {
        global $wpdb;
        
        // 驗證參數
        if (!in_array($type, array('taiwan', 'casino'))) {
            return array();
        }
        
        $count = max(1, intval($count));
        
        $keywords_table = $wpdb->prefix . 'aicg_keywords';
        
        // 使用 ORDER BY RAND() 獲取隨機關鍵字
        $keywords = $wpdb->get_results($wpdb->prepare(
            "SELECT keyword FROM $keywords_table WHERE type = %s ORDER BY RAND() LIMIT %d",
            $type,
            $count
        ));
        
        $result = array();
        foreach ($keywords as $kw) {
            $result[] = $kw->keyword;
        }
        
        return $result;
    }
    
    /**
     * 取得熱門關鍵字
     * @param string $type
     * @param int $count
     * @return array
     */
    public function get_top_keywords($type, $count) {
        global $wpdb;
        
        // 驗證參數
        if (!in_array($type, array('taiwan', 'casino'))) {
            return array();
        }
        
        $count = max(1, intval($count));
        
        $keywords_table = $wpdb->prefix . 'aicg_keywords';
        
        // 從快取獲取
        $cache_key = 'aicg_top_keywords_' . $type . '_' . $count;
        $result = wp_cache_get($cache_key, 'aicg');
        
        if ($result === false) {
            $keywords = $wpdb->get_results($wpdb->prepare(
                "SELECT keyword FROM $keywords_table WHERE type = %s ORDER BY traffic_volume DESC LIMIT %d",
                $type,
                $count
            ));
            
            $result = array();
            foreach ($keywords as $kw) {
                $result[] = $kw->keyword;
            }
            
            // 設置快取（10分鐘）
            wp_cache_set($cache_key, $result, 'aicg', 600);
        }
        
        return $result;
    }
    
    /**
     * 取得關鍵字統計資料
     * @param string $type
     * @return array
     */
    public function get_keywords_stats($type = null) {
        global $wpdb;
        
        $keywords_table = $wpdb->prefix . 'aicg_keywords';
        $stats = array();
        
        if ($type) {
            // 特定類型的統計
            $stats = $wpdb->get_row($wpdb->prepare(
                "SELECT 
                    COUNT(*) as total,
                    AVG(traffic_volume) as avg_volume,
                    MAX(traffic_volume) as max_volume,tract_keyword
                    MIN(traffic_volume) as min_volume,
                    MAX(last_updated) as last_updated
                FROM $keywords_table 
                WHERE type = %s",
                $type
            ), ARRAY_A);
        } else {
            // 所有類型的統計
            $stats = $wpdb->get_results(
                "SELECT 
                    type,
                    COUNT(*) as total,
                    AVG(traffic_volume) as avg_volume,
                    MAX(traffic_volume) as max_volume,
                    MIN(traffic_volume) as min_volume,
                    MAX(last_updated) as last_updated
                FROM $keywords_table 
                GROUP BY type",
                ARRAY_A
            );
        }
        
        return $stats;
    }
    
    /**
     * 清理過期關鍵字
     * @param int $days 保留天數
     * @return int 刪除的數量
     */
    public function cleanup_old_keywords($days = 30) {
        global $wpdb;
        
        $keywords_table = $wpdb->prefix . 'aicg_keywords';
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $keywords_table WHERE last_updated < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
        
        // 清理快取
        wp_cache_delete('aicg_keywords_taiwan', 'aicg');
        wp_cache_delete('aicg_keywords_casino', 'aicg');
        
        return $deleted;
    }
}