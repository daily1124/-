<?php
/**
 * 檔案：includes/modules/class-seo-optimizer.php
 * 功能：SEO優化模組
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
 * SEO優化類別
 * 
 * 負責優化內容以獲得更好的搜尋引擎排名和精選摘要
 */
class SEOOptimizer {
    
    /**
     * 資料庫實例
     */
    private Database $db;
    
    /**
     * 日誌實例
     */
    private Logger $logger;
    
    /**
     * 高權重網站列表
     */
    private const HIGH_AUTHORITY_SITES = [
        'wikipedia.org' => '維基百科',
        'gov.tw' => '政府網站',
        'edu.tw' => '教育機構',
        'bbc.com' => 'BBC新聞',
        'cnn.com' => 'CNN',
        'forbes.com' => '富比士',
        'harvard.edu' => '哈佛大學',
        'nature.com' => '自然期刊',
        'who.int' => '世界衛生組織',
        'youtube.com' => 'YouTube',
        'twitter.com' => 'Twitter',
        'linkedin.com' => 'LinkedIn'
    ];
    
    /**
     * 停用詞列表
     */
    private const STOP_WORDS = [
        '的', '是', '在', '和', '了', '有', '我', '你', '他', '她',
        '這', '那', '就', '也', '都', '而', '及', '與', '或', '但'
    ];
    
    /**
     * 建構函數
     */
    public function __construct() {
        $this->db = new Database();
        $this->logger = new Logger();
    }
    
    /**
     * 1. 主要優化方法
     */
    
    /**
     * 1.1 優化內容
     */
    public function optimize_content(string $content, string $keyword): array {
        $this->logger->info('開始SEO優化', ['keyword' => $keyword]);
        
        // 提取或生成標題
        $title = $this->extract_or_generate_title($content, $keyword);
        
        // 優化標題
        $optimized_title = $this->optimize_title($title, $keyword);
        
        // 優化內容結構
        $structured_content = $this->optimize_content_structure($content, $keyword);
        
        // 優化關鍵字密度
        $density_optimized = $this->optimize_keyword_density($structured_content, $keyword);
        
        // 添加內部連結
        $linked_content = $this->add_internal_links($density_optimized, $keyword);
        
        // 添加外部權威連結
        $authority_linked = $this->add_authority_links($linked_content);
        
        // 優化圖片
        $image_optimized = $this->optimize_images($authority_linked, $keyword);
        
        // 生成精選摘要優化內容
        $snippet_optimized = $this->optimize_for_featured_snippet($image_optimized, $keyword);
        
        // 生成meta資料
        $meta_data = $this->generate_meta_data($snippet_optimized, $keyword, $optimized_title);
        
        // 計算SEO分數
        $seo_score = $this->calculate_seo_score($snippet_optimized, $keyword, $meta_data);
        
        return [
            'title' => $optimized_title,
            'content' => $snippet_optimized,
            'excerpt' => $meta_data['excerpt'],
            'meta_description' => $meta_data['description'],
            'slug' => $meta_data['slug'],
            'seo_score' => $seo_score,
            'optimization_report' => $this->generate_optimization_report($seo_score)
        ];
    }
    
    /**
     * 1.2 優化標題
     */
    private function optimize_title(string $title, string $keyword): string {
        // 確保標題包含關鍵字
        if (mb_strpos($title, $keyword) === false) {
            // 在標題前面加入關鍵字
            $title = $keyword . '：' . $title;
        }
        
        // 優化標題長度（理想長度50-60字符）
        if (mb_strlen($title) > 60) {
            $title = mb_substr($title, 0, 57) . '...';
        } elseif (mb_strlen($title) < 30) {
            // 標題太短，添加修飾詞
            $modifiers = ['完整指南', '最新資訊', '詳細解析', '專業分析'];
            $title .= ' - ' . $modifiers[array_rand($modifiers)];
        }
        
        // 添加年份（如果沒有）
        if (!preg_match('/20\d{2}/', $title)) {
            $title .= ' (' . date('Y') . '最新)';
        }
        
        // 使用強力詞彙
        $power_words = [
            '最佳' => '最佳',
            '指南' => '完整指南',
            '技巧' => '專業技巧',
            '方法' => '有效方法',
            '推薦' => '強力推薦'
        ];
        
        foreach ($power_words as $weak => $strong) {
            $title = str_replace($weak, $strong, $title);
        }
        
        return trim($title);
    }
    
    /**
     * 2. 內容結構優化
     */
    
    /**
     * 2.1 優化內容結構
     */
    private function optimize_content_structure(string $content, string $keyword): string {
        // 確保有適當的標題層級
        $content = $this->ensure_heading_hierarchy($content);
        
        // 添加目錄
        if (mb_strlen($content) > 2000) {
            $content = $this->add_table_of_contents($content);
        }
        
        // 優化段落長度
        $content = $this->optimize_paragraph_length($content);
        
        // 添加過渡句
        $content = $this->add_transition_sentences($content);
        
        // 確保每個H2下有足夠內容
        $content = $this->ensure_section_depth($content);
        
        return $content;
    }
    
    /**
     * 2.2 確保標題層級
     */
    private function ensure_heading_hierarchy(string $content): string {
        // 檢查並修正標題層級
        $dom = new \DOMDocument('1.0', 'UTF-8');
        @$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        $xpath = new \DOMXPath($dom);
        
        // 確保沒有H1（文章標題已經是H1）
        $h1_nodes = $xpath->query('//h1');
        foreach ($h1_nodes as $h1) {
            $h2 = $dom->createElement('h2');
            $h2->nodeValue = $h1->nodeValue;
            $h1->parentNode->replaceChild($h2, $h1);
        }
        
        // 限制到H3
        for ($i = 6; $i >= 4; $i--) {
            $nodes = $xpath->query("//h{$i}");
            foreach ($nodes as $node) {
                $h3 = $dom->createElement('h3');
                $h3->nodeValue = $node->nodeValue;
                $node->parentNode->replaceChild($h3, $node);
            }
        }
        
        return $dom->saveHTML();
    }
    
    /**
     * 2.3 添加目錄
     */
    private function add_table_of_contents(string $content): string {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        @$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        $xpath = new \DOMXPath($dom);
        $headings = $xpath->query('//h2|//h3');
        
        if ($headings->length < 3) {
            return $content; // 標題太少，不需要目錄
        }
        
        $toc = '<div class="aisc-toc">';
        $toc .= '<h2>目錄</h2>';
        $toc .= '<ul>';
        
        $counter = 1;
        foreach ($headings as $heading) {
            $id = 'section-' . $counter;
            $heading->setAttribute('id', $id);
            
            $level = $heading->tagName;
            $class = $level === 'h2' ? 'toc-h2' : 'toc-h3';
            
            $toc .= sprintf(
                '<li class="%s"><a href="#%s">%s</a></li>',
                $class,
                $id,
                $heading->nodeValue
            );
            
            $counter++;
        }
        
        $toc .= '</ul>';
        $toc .= '</div>';
        
        // 在第一個H2之前插入目錄
        $first_h2 = $xpath->query('//h2')->item(0);
        if ($first_h2) {
            $toc_dom = $dom->createDocumentFragment();
            $toc_dom->appendXML($toc);
            $first_h2->parentNode->insertBefore($toc_dom, $first_h2);
        }
        
        return $dom->saveHTML();
    }
    
    /**
     * 3. 關鍵字優化
     */
    
    /**
     * 3.1 優化關鍵字密度
     */
    private function optimize_keyword_density(string $content, string $keyword): string {
        $content_text = strip_tags($content);
        $word_count = mb_strlen($content_text);
        $keyword_count = substr_count(mb_strtolower($content_text), mb_strtolower($keyword));
        
        $current_density = ($keyword_count / $word_count) * 100;
        $target_density = 1.5; // 目標密度1.5%
        
        if ($current_density < 1.0) {
            // 密度太低，需要增加關鍵字
            $content = $this->increase_keyword_density($content, $keyword, $target_density);
        } elseif ($current_density > 2.5) {
            // 密度太高，需要減少關鍵字
            $content = $this->decrease_keyword_density($content, $keyword, $target_density);
        }
        
        // 確保關鍵字分布均勻
        $content = $this->ensure_keyword_distribution($content, $keyword);
        
        return $content;
    }
    
    /**
     * 3.2 增加關鍵字密度
     */
    private function increase_keyword_density(string $content, string $keyword, float $target_density): string {
        // 在適當位置添加關鍵字
        $synonyms = $this->get_keyword_synonyms($keyword);
        
        // 在段落開頭添加關鍵字
        $content = preg_replace(
            '/(<p>)([^<]{50,})/i',
            '$1' . $keyword . '是重要的議題。$2',
            $content,
            2
        );
        
        // 使用同義詞變化
        foreach ($synonyms as $synonym) {
            $content = preg_replace(
                '/(' . preg_quote($keyword, '/') . ')/i',
                '$1（' . $synonym . '）',
                $content,
                1
            );
        }
        
        return $content;
    }
    
    /**
     * 3.3 獲取關鍵字同義詞
     */
    private function get_keyword_synonyms(string $keyword): array {
        // 簡單的同義詞映射
        $synonym_map = [
            '娛樂城' => ['線上賭場', '博弈平台', '遊戲平台'],
            '投資' => ['理財', '資產配置', '財務規劃'],
            '健康' => ['養生', '保健', '健身'],
            '科技' => ['技術', '創新', '數位']
        ];
        
        foreach ($synonym_map as $key => $synonyms) {
            if (mb_strpos($keyword, $key) !== false) {
                return $synonyms;
            }
        }
        
        return [];
    }
    
    /**
     * 4. 連結優化
     */
    
    /**
     * 4.1 添加內部連結
     */
    private function add_internal_links(string $content, string $keyword): string {
        if (!get_option('aisc_auto_internal_links', true)) {
            return $content;
        }
        
        // 獲取相關文章
        $related_posts = $this->get_related_posts($keyword, 5);
        
        if (empty($related_posts)) {
            return $content;
        }
        
        $dom = new \DOMDocument('1.0', 'UTF-8');
        @$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        $xpath = new \DOMXPath($dom);
        $paragraphs = $xpath->query('//p[string-length(.) > 100]');
        
        $links_added = 0;
        foreach ($paragraphs as $p) {
            if ($links_added >= 3) break; // 最多添加3個內部連結
            
            foreach ($related_posts as $post) {
                if ($links_added >= 3) break;
                
                $anchor_text = $post->post_title;
                $short_anchor = mb_substr($anchor_text, 0, 30);
                
                // 檢查段落是否包含相關文字
                if (mb_strpos($p->nodeValue, $short_anchor) !== false ||
                    mb_strpos($p->nodeValue, $keyword) !== false) {
                    
                    $link = $dom->createElement('a');
                    $link->setAttribute('href', get_permalink($post->ID));
                    $link->setAttribute('title', $anchor_text);
                    $link->nodeValue = $short_anchor;
                    
                    // 在段落末尾添加連結
                    $text = $dom->createTextNode('。相關閱讀：');
                    $p->appendChild($text);
                    $p->appendChild($link);
                    
                    $links_added++;
                }
            }
        }
        
        return $dom->saveHTML();
    }
    
    /**
     * 4.2 添加權威外部連結
     */
    private function add_authority_links(string $content): string {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        @$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        $xpath = new \DOMXPath($dom);
        $text_nodes = $xpath->query('//text()[not(parent::a)]');
        
        $links_added = 0;
        foreach ($text_nodes as $node) {
            if ($links_added >= 2) break; // 最多添加2個外部權威連結
            
            foreach (self::HIGH_AUTHORITY_SITES as $domain => $name) {
                if ($links_added >= 2) break;
                
                // 檢查是否提到網站名稱
                if (mb_strpos($node->nodeValue, $name) !== false) {
                    $link = $dom->createElement('a');
                    $link->setAttribute('href', 'https://' . $domain);
                    $link->setAttribute('target', '_blank');
                    $link->setAttribute('rel', 'noopener noreferrer');
                    $link->nodeValue = $name;
                    
                    // 替換文字為連結
                    $new_text = str_replace($name, '', $node->nodeValue);
                    $node->nodeValue = substr($new_text, 0, strpos($new_text, $name));
                    $node->parentNode->insertBefore($link, $node->nextSibling);
                    
                    $links_added++;
                }
            }
        }
        
        return $dom->saveHTML();
    }
    
    /**
     * 5. 精選摘要優化
     */
    
    /**
     * 5.1 優化精選摘要
     */
    private function optimize_for_featured_snippet(string $content, string $keyword): string {
        // 在開頭添加定義段落
        $definition = $this->create_definition_paragraph($keyword);
        
        // 添加列表摘要
        $list_summary = $this->create_list_summary($keyword);
        
        // 添加表格（如果適用）
        $table = $this->create_comparison_table($keyword);
        
        // 組合優化內容
        $snippet_content = $definition . "\n\n" . $content;
        
        if (!empty($list_summary)) {
            // 在第一個H2後插入列表
            $snippet_content = preg_replace(
                '/(<\/h2>)/',
                '$1' . "\n" . $list_summary,
                $snippet_content,
                1
            );
        }
        
        if (!empty($table)) {
            // 在適當位置插入表格
            $snippet_content = preg_replace(
                '/(<h2[^>]*>.*比較.*<\/h2>)/i',
                '$1' . "\n" . $table,
                $snippet_content,
                1
            );
        }
        
        return $snippet_content;
    }
    
    /**
     * 5.2 創建定義段落
     */
    private function create_definition_paragraph(string $keyword): string {
        $definition = '<div class="aisc-definition-box">';
        $definition .= '<p><strong>' . $keyword . '</strong>是';
        
        // 根據關鍵字類型生成定義
        if (mb_strpos($keyword, '娛樂城') !== false) {
            $definition .= '指提供線上博弈遊戲服務的平台，玩家可以透過網路參與各種賭博遊戲，包括老虎機、百家樂、撲克等。';
        } elseif (mb_strpos($keyword, '投資') !== false) {
            $definition .= '將資金投入特定資產或項目，期望在未來獲得收益的財務行為。';
        } else {
            $definition .= '本文將詳細介紹的主題。';
        }
        
        $definition .= '</p>';
        $definition .= '</div>';
        
        return $definition;
    }
    
    /**
     * 5.3 創建列表摘要
     */
    private function create_list_summary(string $keyword): string {
        $list = '<div class="aisc-list-summary">';
        $list .= '<h3>' . $keyword . '的重點整理：</h3>';
        $list .= '<ul>';
        
        // 生成5-7個要點
        $points = [
            '深入了解基本概念與原理',
            '掌握實際應用技巧',
            '避免常見錯誤與陷阱',
            '學習專業評估方法',
            '獲得最新市場資訊',
            '建立正確觀念與態度',
            '制定合適的個人策略'
        ];
        
        shuffle($points);
        for ($i = 0; $i < 5; $i++) {
            $list .= '<li>' . str_replace('', $keyword . '的', $points[$i]) . '</li>';
        }
        
        $list .= '</ul>';
        $list .= '</div>';
        
        return $list;
    }
    
    /**
     * 6. 圖片優化
     */
    
    /**
     * 6.1 優化圖片
     */
    private function optimize_images(string $content, string $keyword): string {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        @$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        $xpath = new \DOMXPath($dom);
        $images = $xpath->query('//img');
        
        $img_count = 1;
        foreach ($images as $img) {
            // 優化alt屬性
            $alt = $keyword . ' - 圖片' . $img_count;
            $img->setAttribute('alt', $alt);
            
            // 添加title屬性
            $title = $keyword . '相關圖片說明' . $img_count;
            $img->setAttribute('title', $title);
            
            // 添加loading="lazy"
            $img->setAttribute('loading', 'lazy');
            
            // 確保有適當的class
            $existing_class = $img->getAttribute('class');
            if (!strpos($existing_class, 'aisc-optimized')) {
                $img->setAttribute('class', trim($existing_class . ' aisc-optimized'));
            }
            
            $img_count++;
        }
        
        return $dom->saveHTML();
    }
    
    /**
     * 7. Meta資料生成
     */
    
    /**
     * 7.1 生成Meta資料
     */
    private function generate_meta_data(string $content, string $keyword, string $title): array {
        return [
            'description' => $this->generate_meta_description($content, $keyword),
            'excerpt' => $this->generate_excerpt($content, $keyword),
            'slug' => $this->generate_slug($title),
            'focus_keyword' => $keyword,
            'keywords' => $this->extract_keywords($content, $keyword)
        ];
    }
    
    /**
     * 7.2 生成Meta描述
     */
    private function generate_meta_description(string $content, string $keyword): string {
        // 提取第一段作為基礎
        $text = strip_tags($content);
        $first_paragraph = substr($text, 0, 300);
        
        // 確保包含關鍵字
        if (mb_strpos($first_paragraph, $keyword) === false) {
            $first_paragraph = $keyword . ' - ' . $first_paragraph;
        }
        
        // 限制長度（150-160字符）
        $description = mb_substr($first_paragraph, 0, 155);
        
        // 添加行動呼籲
        if (mb_strlen($description) < 140) {
            $cta = ['立即了解', '深入探索', '完整指南', '專業解析'];
            $description .= '。' . $cta[array_rand($cta)] . '！';
        }
        
        return trim($description);
    }
    
    /**
     * 7.3 生成URL slug
     */
    private function generate_slug(string $title): string {
        // 移除特殊字符
        $slug = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $title);
        
        // 轉換為小寫
        $slug = mb_strtolower($slug);
        
        // 移除停用詞
        foreach (self::STOP_WORDS as $stop_word) {
            $slug = str_replace(' ' . $stop_word . ' ', ' ', ' ' . $slug . ' ');
        }
        
        // 替換空格為連字符
        $slug = preg_replace('/\s+/', '-', trim($slug));
        
        // 移除重複連字符
        $slug = preg_replace('/-+/', '-', $slug);
        
        // 限制長度
        if (mb_strlen($slug) > 50) {
            $slug = mb_substr($slug, 0, 50);
            $slug = rtrim($slug, '-');
        }
        
        return $slug;
    }
    
    /**
     * 8. SEO評分
     */
    
    /**
     * 8.1 計算SEO分數
     */
    public function calculate_seo_score(string $content, string $keyword, array $meta_data): float {
        $score = 0;
        $factors = [];
        
        // 標題優化（20分）
        $title_score = $this->score_title_optimization($meta_data['focus_keyword'] ?? $keyword);
        $score += $title_score * 20;
        $factors['title'] = $title_score;
        
        // 關鍵字密度（15分）
        $density_score = $this->score_keyword_density($content, $keyword);
        $score += $density_score * 15;
        $factors['density'] = $density_score;
        
        // 內容長度（15分）
        $length_score = $this->score_content_length($content);
        $score += $length_score * 15;
        $factors['length'] = $length_score;
        
        // 標題結構（10分）
        $structure_score = $this->score_heading_structure($content);
        $score += $structure_score * 10;
        $factors['structure'] = $structure_score;
        
        // 內部連結（10分）
        $internal_links_score = $this->score_internal_links($content);
        $score += $internal_links_score * 10;
        $factors['internal_links'] = $internal_links_score;
        
        // 外部連結（5分）
        $external_links_score = $this->score_external_links($content);
        $score += $external_links_score * 5;
        $factors['external_links'] = $external_links_score;
        
        // 圖片優化（10分）
        $images_score = $this->score_image_optimization($content);
        $score += $images_score * 10;
        $factors['images'] = $images_score;
        
        // Meta描述（10分）
        $meta_score = $this->score_meta_description($meta_data['description']);
        $score += $meta_score * 10;
        $factors['meta'] = $meta_score;
        
        // 可讀性（5分）
        $readability_score = $this->calculate_readability_score($content);
        $score += ($readability_score / 100) * 5;
        $factors['readability'] = $readability_score / 100;
        
        $this->logger->info('SEO評分完成', [
            'keyword' => $keyword,
            'total_score' => $score,
            'factors' => $factors
        ]);
        
        return round($score, 2);
    }
    
    /**
     * 8.2 生成優化報告
     */
    private function generate_optimization_report(float $score): array {
        $report = [
            'score' => $score,
            'grade' => $this->get_seo_grade($score),
            'status' => $score >= 80 ? 'excellent' : ($score >= 60 ? 'good' : 'needs_improvement'),
            'recommendations' => []
        ];
        
        if ($score < 80) {
            $report['recommendations'] = $this->get_improvement_recommendations($score);
        }
        
        return $report;
    }
    
    /**
     * 9. 輔助方法
     */
    
    /**
     * 9.1 提取或生成標題
     */
    private function extract_or_generate_title(string $content, string $keyword): string {
        // 嘗試從內容中提取H1或H2
        if (preg_match('/<h[12][^>]*>([^<]+)<\/h[12]>/i', $content, $matches)) {
            return strip_tags($matches[1]);
        }
        
        // 生成預設標題
        return $keyword . ' - 完整指南與專業分析';
    }
    
    /**
     * 9.2 獲取相關文章
     */
    private function get_related_posts(string $keyword, int $limit = 5): array {
        $args = [
            'posts_per_page' => $limit,
            'post_status' => 'publish',
            's' => $keyword,
            'orderby' => 'relevance',
            'order' => 'DESC',
            'meta_query' => [
                [
                    'key' => '_aisc_generated',
                    'compare' => 'EXISTS'
                ]
            ]
        ];
        
        return get_posts($args);
    }
    
    /**
     * 9.3 優化段落長度
     */
    private function optimize_paragraph_length(string $content): string {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        @$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        $xpath = new \DOMXPath($dom);
        $paragraphs = $xpath->query('//p');
        
        foreach ($paragraphs as $p) {
            $text = $p->nodeValue;
            $sentence_count = preg_match_all('/[。！？.!?]+/u', $text);
            
            // 如果段落太長（超過5個句子），分割它
            if ($sentence_count > 5) {
                $sentences = preg_split('/([。！？.!?]+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
                $new_paragraphs = [];
                $current = '';
                $count = 0;
                
                for ($i = 0; $i < count($sentences); $i += 2) {
                    if (isset($sentences[$i + 1])) {
                        $current .= $sentences[$i] . $sentences[$i + 1];
                        $count++;
                        
                        if ($count >= 3) {
                            $new_paragraphs[] = $current;
                            $current = '';
                            $count = 0;
                        }
                    }
                }
                
                if (!empty($current)) {
                    $new_paragraphs[] = $current;
                }
                
                // 替換原段落
                foreach (array_reverse($new_paragraphs) as $new_text) {
                    $new_p = $dom->createElement('p');
                    $new_p->nodeValue = trim($new_text);
                    $p->parentNode->insertBefore($new_p, $p->nextSibling);
                }
                
                $p->parentNode->removeChild($p);
            }
        }
        
        return $dom->saveHTML();
    }
    
    /**
     * 9.4 計算可讀性分數
     */
    public function calculate_readability_score(string $content): float {
        $text = strip_tags($content);
        
        // 計算平均句子長度
        $sentences = preg_split('/[。！？.!?]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $total_words = 0;
        
        foreach ($sentences as $sentence) {
            $total_words += mb_strlen($sentence);
        }
        
        $avg_sentence_length = count($sentences) > 0 ? $total_words / count($sentences) : 0;
        
        // 理想句子長度是15-20字
        $sentence_score = 100;
        if ($avg_sentence_length < 15) {
            $sentence_score = ($avg_sentence_length / 15) * 100;
        } elseif ($avg_sentence_length > 25) {
            $sentence_score = max(0, 100 - (($avg_sentence_length - 25) * 5));
        }
        
        // 計算段落平均長度
        $paragraphs = explode("\n\n", $text);
        $avg_paragraph_length = count($paragraphs) > 0 ? $total_words / count($paragraphs) : 0;
        
        // 理想段落長度是100-200字
        $paragraph_score = 100;
        if ($avg_paragraph_length < 100) {
            $paragraph_score = ($avg_paragraph_length / 100) * 100;
        } elseif ($avg_paragraph_length > 300) {
            $paragraph_score = max(0, 100 - (($avg_paragraph_length - 300) / 10));
        }
        
        // 綜合分數
        return ($sentence_score * 0.6 + $paragraph_score * 0.4);
    }
    
    /**
     * 9.5 評分輔助方法
     */
    private function score_title_optimization(string $keyword): float {
        // 這裡應該檢查實際的標題
        // 簡化版本：假設標題已優化
        return 0.9;
    }
    
    private function score_keyword_density(string $content, string $keyword): float {
        $text = strip_tags($content);
        $word_count = mb_strlen($text);
        $keyword_count = substr_count(mb_strtolower($text), mb_strtolower($keyword));
        
        $density = ($keyword_count / $word_count) * 100;
        
        if ($density >= 1.0 && $density <= 2.0) {
            return 1.0;
        } elseif ($density < 1.0) {
            return $density;
        } else {
            return max(0, 1 - ($density - 2) * 0.5);
        }
    }
    
    private function score_content_length(string $content): float {
        $word_count = mb_strlen(strip_tags($content));
        
        if ($word_count >= 2000) {
            return 1.0;
        } elseif ($word_count >= 1000) {
            return 0.8;
        } elseif ($word_count >= 500) {
            return 0.6;
        } else {
            return $word_count / 500 * 0.6;
        }
    }
    
    private function score_heading_structure(string $content): float {
        $h2_count = substr_count($content, '<h2');
        $h3_count = substr_count($content, '<h3');
        
        if ($h2_count >= 3 && $h2_count <= 10 && $h3_count >= $h2_count) {
            return 1.0;
        } elseif ($h2_count >= 2) {
            return 0.8;
        } elseif ($h2_count >= 1) {
            return 0.6;
        } else {
            return 0.3;
        }
    }
    
    private function score_internal_links(string $content): float {
        $internal_links = preg_match_all('/<a[^>]+href=["\']https?:\/\/' . preg_quote($_SERVER['HTTP_HOST'], '/') . '[^"\']*["\'][^>]*>/i', $content);
        
        if ($internal_links >= 3) {
            return 1.0;
        } else {
            return $internal_links / 3;
        }
    }
    
    private function score_external_links(string $content): float {
        $external_links = preg_match_all('/<a[^>]+href=["\']https?:\/\/(?!' . preg_quote($_SERVER['HTTP_HOST'], '/') . ')[^"\']+["\'][^>]*>/i', $content);
        
        if ($external_links >= 2) {
            return 1.0;
        } else {
            return $external_links / 2;
        }
    }
    
    private function score_image_optimization(string $content): float {
        $images = preg_match_all('/<img[^>]+>/i', $content);
        $optimized = preg_match_all('/<img[^>]+alt=["\'][^"\']+["\'][^>]*>/i', $content);
        
        if ($images == 0) {
            return 0.5; // 沒有圖片
        }
        
        return $optimized / $images;
    }
    
    private function score_meta_description(string $description): float {
        $length = mb_strlen($description);
        
        if ($length >= 150 && $length <= 160) {
            return 1.0;
        } elseif ($length >= 120 && $length <= 180) {
            return 0.8;
        } elseif ($length >= 80) {
            return 0.6;
        } else {
            return $length / 80 * 0.6;
        }
    }
    
    private function get_seo_grade(float $score): string {
        if ($score >= 90) return 'A+';
        if ($score >= 80) return 'A';
        if ($score >= 70) return 'B';
        if ($score >= 60) return 'C';
        if ($score >= 50) return 'D';
        return 'F';
    }
    
    private function get_improvement_recommendations(float $score): array {
        $recommendations = [];
        
        if ($score < 80) {
            $recommendations[] = '增加內容深度，確保文章至少2000字';
            $recommendations[] = '優化關鍵字密度，保持在1-2%之間';
            $recommendations[] = '添加更多內部連結到相關文章';
            $recommendations[] = '確保所有圖片都有描述性的alt標籤';
            $recommendations[] = '改善文章結構，使用清晰的標題層級';
        }
        
        return $recommendations;
    }
    
    /**
     * 10. 其他優化功能
     */
    
    /**
     * 10.1 添加過渡句
     */
    private function add_transition_sentences(string $content): string {
        $transitions = [
            '此外，',
            '另一方面，',
            '值得注意的是，',
            '重要的是，',
            '總的來說，',
            '具體而言，',
            '實際上，',
            '換句話說，'
        ];
        
        // 在某些段落開頭添加過渡詞
        $dom = new \DOMDocument('1.0', 'UTF-8');
        @$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        $xpath = new \DOMXPath($dom);
        $paragraphs = $xpath->query('//p[position() > 1]');
        
        $i = 0;
        foreach ($paragraphs as $p) {
            if ($i % 3 == 0 && !preg_match('/^[此另值重總具實換]/u', $p->nodeValue)) {
                $transition = $transitions[array_rand($transitions)];
                $p->nodeValue = $transition . $p->nodeValue;
            }
            $i++;
        }
        
        return $dom->saveHTML();
    }
    
    /**
     * 10.2 確保章節深度
     */
    private function ensure_section_depth(string $content): string {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        @$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        $xpath = new \DOMXPath($dom);
        $h2_sections = $xpath->query('//h2');
        
        foreach ($h2_sections as $h2) {
            $next = $h2->nextSibling;
            $content_length = 0;
            
            // 計算此H2下的內容長度
            while ($next && $next->nodeName !== 'h2') {
                if ($next->nodeType === XML_ELEMENT_NODE) {
                    $content_length += mb_strlen($next->nodeValue);
                }
                $next = $next->nextSibling;
            }
            
            // 如果內容太少，添加提示
            if ($content_length < 300) {
                $notice = $dom->createElement('p');
                $notice->setAttribute('class', 'aisc-content-notice');
                $notice->nodeValue = '（此部分內容需要進一步擴充以提供更完整的資訊）';
                
                if ($h2->nextSibling) {
                    $h2->parentNode->insertBefore($notice, $h2->nextSibling);
                } else {
                    $h2->parentNode->appendChild($notice);
                }
            }
        }
        
        return $dom->saveHTML();
    }
    
    /**
     * 10.3 創建比較表格
     */
    private function create_comparison_table(string $keyword): string {
        // 只為特定類型的關鍵字創建表格
        if (!preg_match('/(比較|推薦|排名|選擇)/u', $keyword)) {
            return '';
        }
        
        $table = '<div class="aisc-comparison-table">';
        $table .= '<table>';
        $table .= '<thead>';
        $table .= '<tr>';
        $table .= '<th>項目</th>';
        $table .= '<th>優點</th>';
        $table .= '<th>缺點</th>';
        $table .= '<th>適合對象</th>';
        $table .= '</tr>';
        $table .= '</thead>';
        $table .= '<tbody>';
        
        // 生成3-5行比較資料
        for ($i = 1; $i <= 3; $i++) {
            $table .= '<tr>';
            $table .= '<td>選項 ' . $i . '</td>';
            $table .= '<td>優點說明</td>';
            $table .= '<td>缺點說明</td>';
            $table .= '<td>目標使用者</td>';
            $table .= '</tr>';
        }
        
        $table .= '</tbody>';
        $table .= '</table>';
        $table .= '</div>';
        
        return $table;
    }
    
    /**
     * 10.4 提取關鍵字列表
     */
    private function extract_keywords(string $content, string $main_keyword): array {
        $keywords = [$main_keyword];
        
        // 提取常見的相關詞彙
        $text = strip_tags($content);
        $words = preg_split('/\s+/u', $text);
        
        $word_freq = array_count_values($words);
        arsort($word_freq);
        
        foreach ($word_freq as $word => $count) {
            if (mb_strlen($word) > 2 && 
                $count > 3 && 
                !in_array($word, self::STOP_WORDS) &&
                count($keywords) < 10) {
                $keywords[] = $word;
            }
        }
        
        return array_unique($keywords);
    }
    
    /**
     * 10.5 生成摘要
     */
    private function generate_excerpt(string $content, string $keyword): string {
        $text = strip_tags($content);
        
        // 找到包含關鍵字的段落
        $paragraphs = explode("\n", $text);
        $excerpt = '';
        
        foreach ($paragraphs as $para) {
            if (mb_strpos($para, $keyword) !== false && mb_strlen($para) > 100) {
                $excerpt = $para;
                break;
            }
        }
        
        if (empty($excerpt)) {
            $excerpt = mb_substr($text, 0, 300);
        }
        
        // 限制長度
        if (mb_strlen($excerpt) > 200) {
            $excerpt = mb_substr($excerpt, 0, 197) . '...';
        }
        
        return trim($excerpt);
    }
    
    /**
     * 10.6 減少關鍵字密度
     */
    private function decrease_keyword_density(string $content, string $keyword, float $target_density): string {
        // 使用同義詞替換部分關鍵字
        $synonyms = $this->get_keyword_synonyms($keyword);
        
        if (!empty($synonyms)) {
            $count = 0;
            foreach ($synonyms as $synonym) {
                $content = preg_replace(
                    '/(' . preg_quote($keyword, '/') . ')/i',
                    $synonym,
                    $content,
                    2,
                    $count
                );
                
                if ($count >= 2) break;
            }
        }
        
        return $content;
    }
    
    /**
     * 10.7 確保關鍵字分布
     */
    private function ensure_keyword_distribution(string $content, string $keyword): string {
        // 將內容分成幾個部分
        $sections = explode('</h2>', $content);
        $section_count = count($sections);
        
        if ($section_count <= 1) {
            return $content;
        }
        
        // 確保每個主要部分都有關鍵字
        foreach ($sections as $i => &$section) {
            if ($i == $section_count - 1) continue; // 跳過最後一個空部分
            
            if (mb_strpos(mb_strtolower($section), mb_strtolower($keyword)) === false) {
                // 在段落開頭添加關鍵字
                $section = preg_replace(
                    '/(<p>)([^<]{20,})/i',
                    '$1關於' . $keyword . '，$2',
                    $section,
                    1
                );
            }
        }
        
        return implode('</h2>', $sections);
    }
}