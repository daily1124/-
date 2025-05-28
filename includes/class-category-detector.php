<?php
/**
 * 分類自動偵測器 - 增強版
 * 支援特定分類並自動加入"所有文章"分類
 */
class AICG_Category_Detector {
    
    private static $instance = null;
    
    // 定義網站的所有分類
    private $site_categories = array(
        '娛樂城教學',
        '虛擬貨幣',
        '體育',
        '科技',
        '健康',
        '新聞',
        '明星',
        '汽車',
        '理財',
        '生活',
        '社會',
        '美食',
        '追劇'
    );
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // 確保所有分類都存在
        add_action('init', array($this, 'ensure_categories_exist'), 5);
    }
    
    /**
     * 確保所有分類都存在
     */
    public function ensure_categories_exist() {
        // 確保"所有文章"分類存在
        $all_articles_cat = get_term_by('name', '所有文章', 'category');
        if (!$all_articles_cat) {
            wp_insert_term('所有文章', 'category', array(
                'description' => '所有文章的集合',
                'slug' => 'all-articles'
            ));
        }
        
        // 確保其他分類都存在
        foreach ($this->site_categories as $category_name) {
            $cat = get_term_by('name', $category_name, 'category');
            if (!$cat) {
                wp_insert_term($category_name, 'category');
            }
        }
    }
    
    /**
     * 偵測文章分類（增強版）
     * @param string $title
     * @param string $content
     * @param array $keywords
     * @return array 分類ID陣列（包含"所有文章"分類）
     */
    public function detect_category($title, $content, $keywords) {
        // 合併標題、內容和關鍵字進行分析
        $text = $title . ' ' . strip_tags($content) . ' ' . implode(' ', $keywords);
        
        // 獲取"所有文章"分類ID
        $all_articles_cat = get_term_by('name', '所有文章', 'category');
        $all_articles_id = $all_articles_cat ? $all_articles_cat->term_id : 1;
        
        // 開始分類偵測
        $detected_categories = array($all_articles_id); // 預設包含"所有文章"
        
        // 娛樂城教學
        if ($this->is_casino_tutorial($text, $keywords)) {
            $cat = get_term_by('name', '娛樂城教學', 'category');
            if ($cat) $detected_categories[] = $cat->term_id;
        }
        
        // 虛擬貨幣
        if ($this->is_cryptocurrency($text, $keywords)) {
            $cat = get_term_by('name', '虛擬貨幣', 'category');
            if ($cat) $detected_categories[] = $cat->term_id;
        }
        
        // 體育
        if ($this->is_sports($text, $keywords)) {
            $cat = get_term_by('name', '體育', 'category');
            if ($cat) $detected_categories[] = $cat->term_id;
        }
        
        // 科技
        if ($this->is_technology($text, $keywords)) {
            $cat = get_term_by('name', '科技', 'category');
            if ($cat) $detected_categories[] = $cat->term_id;
        }
        
        // 健康
        if ($this->is_health($text, $keywords)) {
            $cat = get_term_by('name', '健康', 'category');
            if ($cat) $detected_categories[] = $cat->term_id;
        }
        
        // 新聞
        if ($this->is_news($text, $keywords)) {
            $cat = get_term_by('name', '新聞', 'category');
            if ($cat) $detected_categories[] = $cat->term_id;
        }
        
        // 明星
        if ($this->is_celebrity($text, $keywords)) {
            $cat = get_term_by('name', '明星', 'category');
            if ($cat) $detected_categories[] = $cat->term_id;
        }
        
        // 汽車
        if ($this->is_automotive($text, $keywords)) {
            $cat = get_term_by('name', '汽車', 'category');
            if ($cat) $detected_categories[] = $cat->term_id;
        }
        
        // 理財
        if ($this->is_finance($text, $keywords)) {
            $cat = get_term_by('name', '理財', 'category');
            if ($cat) $detected_categories[] = $cat->term_id;
        }
        
        // 生活
        if ($this->is_lifestyle($text, $keywords)) {
            $cat = get_term_by('name', '生活', 'category');
            if ($cat) $detected_categories[] = $cat->term_id;
        }
        
        // 社會
        if ($this->is_society($text, $keywords)) {
            $cat = get_term_by('name', '社會', 'category');
            if ($cat) $detected_categories[] = $cat->term_id;
        }
        
        // 美食
        if ($this->is_food($text, $keywords)) {
            $cat = get_term_by('name', '美食', 'category');
            if ($cat) $detected_categories[] = $cat->term_id;
        }
        
        // 追劇
        if ($this->is_drama($text, $keywords)) {
            $cat = get_term_by('name', '追劇', 'category');
            if ($cat) $detected_categories[] = $cat->term_id;
        }
        
        // 如果只有"所有文章"分類，嘗試使用預設分類
        if (count($detected_categories) == 1) {
            $default_category = get_option('aicg_default_category', 1);
            if ($default_category && $default_category != $all_articles_id) {
                $detected_categories[] = $default_category;
            }
        }
        
        // 去重並返回
        return array_unique($detected_categories);
    }
    
    /**
     * 判斷是否為娛樂城教學內容
     */
    private function is_casino_tutorial($text, $keywords) {
        $casino_keywords = array(
            '娛樂城', '博弈', '賭場', '百家樂', '老虎機', '撲克', '21點', 
            '輪盤', '骰寶', '運彩', '體育投注', '真人荷官', '電子遊戲',
            'bet365', '威博', '金合發', 'i88', '泊樂', '財神', '3A', 'HOYA',
            '教學', '攻略', '技巧', '玩法', '規則', '賠率', '機率'
        );
        
        return $this->match_keywords($text, $keywords, $casino_keywords, 2);
    }
    
    /**
     * 判斷是否為虛擬貨幣內容
     */
    private function is_cryptocurrency($text, $keywords) {
        $crypto_keywords = array(
            '虛擬貨幣', '加密貨幣', '比特幣', 'Bitcoin', 'BTC', '以太幣', 
            'Ethereum', 'ETH', '區塊鏈', 'blockchain', '挖礦', 'mining',
            'DeFi', 'NFT', '錢包', 'wallet', '交易所', 'Binance', '幣安',
            'USDT', '泰達幣', '狗狗幣', 'Web3', '去中心化'
        );
        
        return $this->match_keywords($text, $keywords, $crypto_keywords, 2);
    }
    
    /**
     * 判斷是否為體育內容
     */
    private function is_sports($text, $keywords) {
        $sports_keywords = array(
            '體育', '運動', '足球', '籃球', '棒球', '網球', '高爾夫',
            'NBA', 'MLB', 'NFL', '世界盃', '奧運', '中職', 'SBL',
            '球員', '球隊', '比賽', '賽事', '冠軍', '教練', '戰績',
            '健身', '跑步', '游泳', '瑜珈', '重訓'
        );
        
        return $this->match_keywords($text, $keywords, $sports_keywords, 2);
    }
    
    /**
     * 判斷是否為科技內容
     */
    private function is_technology($text, $keywords) {
        $tech_keywords = array(
            '科技', '手機', '電腦', '軟體', 'AI', '人工智慧', '網路', 
            'iPhone', 'Android', 'Windows', 'Mac', '雲端', '5G', '6G',
            'APP', '應用程式', '程式設計', '科學', '創新', '數位',
            'Google', 'Apple', 'Microsoft', 'Meta', '晶片', '半導體'
        );
        
        return $this->match_keywords($text, $keywords, $tech_keywords, 2);
    }
    
    /**
     * 判斷是否為健康內容
     */
    private function is_health($text, $keywords) {
        $health_keywords = array(
            '健康', '醫療', '醫生', '醫院', '疾病', '症狀', '治療',
            '保健', '養生', '營養', '維他命', '運動', '減肥', '瘦身',
            '心理', '睡眠', '壓力', '疫苗', '防疫', '中醫', '西醫',
            '藥物', '診所', '健檢', '癌症', '三高'
        );
        
        return $this->match_keywords($text, $keywords, $health_keywords, 2);
    }
    
    /**
     * 判斷是否為新聞內容
     */
    private function is_news($text, $keywords) {
        $news_keywords = array(
            '新聞', '時事', '政治', '選舉', '政府', '總統', '立委',
            '快訊', '頭條', '報導', '記者', '媒體', '事件', '案件',
            '國際', '兩岸', '外交', '政策', '法案', '民調', '輿論'
        );
        
        return $this->match_keywords($text, $keywords, $news_keywords, 2);
    }
    
    /**
     * 判斷是否為明星內容
     */
    private function is_celebrity($text, $keywords) {
        $celebrity_keywords = array(
            '明星', '藝人', '歌手', '演員', '偶像', '網紅', 'YouTuber',
            '娛樂', '八卦', '緋聞', '演唱會', '電影', '戲劇', '綜藝',
            '粉絲', '追星', 'KOL', '直播主', '名人', '影視', '音樂'
        );
        
        return $this->match_keywords($text, $keywords, $celebrity_keywords, 2);
    }
    
    /**
     * 判斷是否為汽車內容
     */
    private function is_automotive($text, $keywords) {
        $auto_keywords = array(
            '汽車', '車子', '轎車', '休旅車', 'SUV', '跑車', '機車',
            'Toyota', 'Honda', 'BMW', 'Benz', 'Tesla', '特斯拉',
            '引擎', '馬力', '油耗', '電動車', '充電', '駕駛', '開車',
            '車款', '新車', '二手車', '保養', '維修', '改裝'
        );
        
        return $this->match_keywords($text, $keywords, $auto_keywords, 2);
    }
    
    /**
     * 判斷是否為理財內容
     */
    private function is_finance($text, $keywords) {
        $finance_keywords = array(
            '理財', '投資', '股票', '基金', '外匯', '期貨', '選擇權',
            '股市', '台股', '美股', 'ETF', '債券', '定存', '儲蓄',
            '銀行', '信用卡', '貸款', '房貸', '保險', '退休金',
            '財務', '資產', '報酬', '獲利', '配息'
        );
        
        return $this->match_keywords($text, $keywords, $finance_keywords, 2);
    }
    
    /**
     * 判斷是否為生活內容
     */
    private function is_lifestyle($text, $keywords) {
        $lifestyle_keywords = array(
            '生活', '日常', '居家', '家居', '裝潢', '設計', '收納',
            '購物', '逛街', '時尚', '穿搭', '美妝', '保養', '護膚',
            '旅遊', '旅行', '出國', '景點', '住宿', '飯店', '民宿',
            '興趣', '休閒', '娛樂', '手作', 'DIY'
        );
        
        return $this->match_keywords($text, $keywords, $lifestyle_keywords, 2);
    }
    
    /**
     * 判斷是否為社會內容
     */
    private function is_society($text, $keywords) {
        $society_keywords = array(
            '社會', '民生', '教育', '學校', '大學', '學生', '老師',
            '工作', '職場', '就業', '薪資', '勞工', '企業', '公司',
            '房價', '房地產', '租屋', '物價', '通膨', '經濟',
            '交通', '捷運', '高鐵', '公車', '環保', '能源'
        );
        
        return $this->match_keywords($text, $keywords, $society_keywords, 2);
    }
    
    /**
     * 判斷是否為美食內容
     */
    private function is_food($text, $keywords) {
        $food_keywords = array(
            '美食', '餐廳', '小吃', '料理', '食譜', '烹飪', '做菜',
            '咖啡', '甜點', '蛋糕', '麵包', '飲料', '手搖', '珍奶',
            '夜市', '小吃', '火鍋', '燒烤', '日本料理', '義大利麵',
            '早餐', '午餐', '晚餐', '下午茶', '美味', '好吃'
        );
        
        return $this->match_keywords($text, $keywords, $food_keywords, 2);
    }
    
    /**
     * 判斷是否為追劇內容
     */
    private function is_drama($text, $keywords) {
        $drama_keywords = array(
            '追劇', '戲劇', '電視劇', '韓劇', '日劇', '陸劇', '美劇',
            'Netflix', '愛奇藝', 'Disney+', '影集', '連續劇', '劇情',
            '男主角', '女主角', '演員', '劇組', '導演', '編劇',
            '首播', '大結局', '收視率', '劇評', '推薦'
        );
        
        return $this->match_keywords($text, $keywords, $drama_keywords, 2);
    }
    
    /**
     * 關鍵字匹配輔助函數
     * @param string $text 要搜尋的文本
     * @param array $keywords 使用的關鍵字
     * @param array $category_keywords 分類關鍵字
     * @param int $min_matches 最少匹配數
     * @return bool
     */
    private function match_keywords($text, $keywords, $category_keywords, $min_matches = 2) {
        $matches = 0;
        
        // 檢查文本中的關鍵字
        foreach ($category_keywords as $keyword) {
            if (mb_stripos($text, $keyword) !== false) {
                $matches++;
                if ($matches >= $min_matches) {
                    return true;
                }
            }
        }
        
        // 特別檢查使用的關鍵字
        foreach ($keywords as $keyword) {
            foreach ($category_keywords as $cat_keyword) {
                if (mb_stripos($keyword, $cat_keyword) !== false || 
                    mb_stripos($cat_keyword, $keyword) !== false) {
                    $matches++;
                    if ($matches >= $min_matches) {
                        return true;
                    }
                }
            }
        }
        
        return false;
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
    
    /**
     * 取得所有網站分類
     * @return array
     */
    public function get_site_categories() {
        return $this->site_categories;
    }
}