<?php
/**
 * 分類自動偵測器
 */
class AICG_Category_Detector {
    
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
     * 偵測文章分類
     * @param string $title
     * @param string $content
     * @param array $keywords
     * @return int|false 分類ID或false
     */
    public function detect_category($title, $content, $keywords) {
        // 合併標題、內容和關鍵字進行分析
        $text = $title . ' ' . strip_tags($content) . ' ' . implode(' ', $keywords);
        
        // 獲取所有分類
        $categories = get_categories(array(
            'hide_empty' => false,
            'orderby' => 'count',
            'order' => 'DESC'
        ));
        
        if (empty($categories)) {
            return false;
        }
        
        // 分類關鍵字映射
        $category_keywords = $this->get_category_keywords();
        
        $best_match_category = null;
        $best_match_score = 0;
        
        foreach ($categories as $category) {
            $score = 0;
            
            // 檢查分類名稱是否出現在文本中
            if (stripos($text, $category->name) !== false) {
                $score += 10;
            }
            
            // 檢查分類別名
            if (!empty($category->slug) && stripos($text, $category->slug) !== false) {
                $score += 5;
            }
            
            // 檢查預定義的分類關鍵字
            $cat_key = strtolower($category->slug);
            if (isset($category_keywords[$cat_key])) {
                foreach ($category_keywords[$cat_key] as $keyword) {
                    if (stripos($text, $keyword) !== false) {
                        $score += 3;
                    }
                }
            }
            
            // 特殊處理娛樂城相關內容
            if ($this->is_casino_content($text)) {
                if (stripos($category->name, '娛樂') !== false || 
                    stripos($category->name, '博弈') !== false ||
                    stripos($category->name, '遊戲') !== false) {
                    $score += 20;
                }
            }
            
            // 特殊處理旅遊相關內容
            if ($this->is_travel_content($text)) {
                if (stripos($category->name, '旅遊') !== false || 
                    stripos($category->name, '旅行') !== false ||
                    stripos($category->name, '景點') !== false) {
                    $score += 20;
                }
            }
            
            // 特殊處理科技相關內容
            if ($this->is_tech_content($text)) {
                if (stripos($category->name, '科技') !== false || 
                    stripos($category->name, '技術') !== false ||
                    stripos($category->name, '數位') !== false) {
                    $score += 20;
                }
            }
            
            // 特殊處理財經相關內容
            if ($this->is_finance_content($text)) {
                if (stripos($category->name, '財經') !== false || 
                    stripos($category->name, '投資') !== false ||
                    stripos($category->name, '理財') !== false) {
                    $score += 20;
                }
            }
            
            if ($score > $best_match_score) {
                $best_match_score = $score;
                $best_match_category = $category;
            }
        }
        
        // 如果找到匹配的分類且分數夠高
        if ($best_match_category && $best_match_score >= 5) {
            error_log('AICG: 自動偵測分類為 "' . $best_match_category->name . '" (分數: ' . $best_match_score . ')');
            return $best_match_category->term_id;
        }
        
        return false;
    }
    
    /**
     * 獲取分類關鍵字映射
     * @return array
     */
    private function get_category_keywords() {
        return array(
            'travel' => array('旅遊', '景點', '住宿', '飯店', '民宿', '行程', '交通', '美食', '觀光'),
            'technology' => array('科技', '手機', '電腦', '軟體', 'AI', '網路', '數位', 'APP', '3C'),
            'finance' => array('股票', '投資', '理財', '基金', '外匯', '金融', '銀行', '保險', '貸款'),
            'entertainment' => array('娛樂', '電影', '音樂', '遊戲', '明星', '演唱會', '展覽', '藝文'),
            'casino' => array('娛樂城', '博弈', '賭場', '百家樂', '老虎機', '撲克', '21點', '輪盤', '運彩'),
            'lifestyle' => array('生活', '美妝', '時尚', '穿搭', '保養', '健康', '運動', '瑜珈'),
            'food' => array('美食', '餐廳', '料理', '食譜', '小吃', '夜市', '咖啡', '甜點'),
            'news' => array('新聞', '時事', '政治', '社會', '國際', '頭條', '快訊', '報導'),
            'education' => array('教育', '學習', '考試', '升學', '補習', '英文', '語言', '證照'),
            'shopping' => array('購物', '優惠', '折扣', '團購', '網購', '特價', '促銷', '開箱')
        );
    }
    
    /**
     * 判斷是否為娛樂城相關內容
     * @param string $text
     * @return bool
     */
    private function is_casino_content($text) {
        $casino_keywords = array(
            '娛樂城', '博弈', '賭場', '百家樂', '老虎機', '撲克', '21點', 
            '輪盤', '骰寶', '運彩', '體育投注', '真人荷官', '電子遊戲',
            'bet365', '威博', '金合發', 'i88', '泊樂', '財神', '3A', 'HOYA'
        );
        
        $count = 0;
        foreach ($casino_keywords as $keyword) {
            if (stripos($text, $keyword) !== false) {
                $count++;
            }
        }
        
        return $count >= 2;
    }
    
    /**
     * 判斷是否為旅遊相關內容
     * @param string $text
     * @return bool
     */
    private function is_travel_content($text) {
        $travel_keywords = array(
            '旅遊', '景點', '住宿', '飯店', '民宿', '行程', '機票',
            '觀光', '遊記', '自由行', '跟團', '背包客', '打卡', '秘境'
        );
        
        $count = 0;
        foreach ($travel_keywords as $keyword) {
            if (stripos($text, $keyword) !== false) {
                $count++;
            }
        }
        
        return $count >= 2;
    }
    
    /**
     * 判斷是否為科技相關內容
     * @param string $text
     * @return bool
     */
    private function is_tech_content($text) {
        $tech_keywords = array(
            '科技', '手機', '電腦', '軟體', 'AI', '網路', '數位', 'APP',
            'iPhone', 'Android', 'Windows', 'Mac', '雲端', '5G', '程式'
        );
        
        $count = 0;
        foreach ($tech_keywords as $keyword) {
            if (stripos($text, $keyword) !== false) {
                $count++;
            }
        }
        
        return $count >= 2;
    }
    
    /**
     * 判斷是否為財經相關內容
     * @param string $text
     * @return bool
     */
    private function is_finance_content($text) {
        $finance_keywords = array(
            '股票', '投資', '理財', '基金', '外匯', '金融', '銀行',
            '台股', '美股', 'ETF', '定存', '信用卡', '貸款', '匯率'
        );
        
        $count = 0;
        foreach ($finance_keywords as $keyword) {
            if (stripos($text, $keyword) !== false) {
                $count++;
            }
        }
        
        return $count >= 2;
    }
    
    /**
     * 創建分類（如果不存在）
     * @param string $category_name
     * @return int 分類ID
     */
    public function create_category_if_not_exists($category_name) {
        $category = get_term_by('name', $category_name, 'category');
        
        if (!$category) {
            $result = wp_insert_term($category_name, 'category');
            
            if (!is_wp_error($result)) {
                return $result['term_id'];
            }
        } else {
            return $category->term_id;
        }
        
        return 1; // 返回預設分類
    }
}