<?php
/**
 * SeoTools.php
 *
 * This class consolidates all SEO test handlers into one tool.
 * For each SEO test there are two methods:
 *   - processXXX(): Extracts the data (and updates the database)
 *   - showXXX(): Returns the HTML output for that test.
 *
 * You can expand each handler by implementing the detailed logic.
 */

class SeoTools {
    // Global properties used by all handlers.
    protected $html;         // Normalized HTML source (with meta tag names in lowercase)
    protected $con;          // Database connection
    protected $domainStr;    // Normalized domain string (used for DB lookups)
    protected $lang;         // Language strings array
    protected $urlParse;     // Parsed URL array (from parse_url())
    protected $sepUnique;    // Unique separator string for output sections
    protected $seoBoxLogin;  // HTML snippet for a login box (if user isn’t logged in)

    /**
     * Constructor.
     *
     * @param string $html       The normalized HTML source.
     * @param mixed  $con        The database connection.
     * @param string $domainStr  The normalized domain string.
     * @param array  $lang       The language strings array.
     * @param array  $urlParse   The parsed URL (via parse_url()).
     * @param string $sepUnique  A unique separator string.
     * @param string $seoBoxLogin The login box HTML snippet.
     */
    public function __construct($html, $con, $domainStr, $lang, $urlParse, $sepUnique, $seoBoxLogin) {
        $this->html        = $html;
        $this->con         = $con;
        $this->domainStr   = $domainStr;
        $this->lang        = $lang;
        $this->urlParse    = $urlParse;
        $this->sepUnique   = $sepUnique;
        $this->seoBoxLogin = $seoBoxLogin;
    }

    /*===================================================================
     * META HANDLER
     *===================================================================
     */

    /**
     * processMeta()
     *
     * Extracts the page title, meta description, and keywords from the HTML.
     * It updates the database with the serialized results.
     *
     * @return array Associative array with keys 'title', 'description', and 'keywords'.
     */
    public function processMeta() {
        
        $title = $description = $keywords = '';
        $doc = new DOMDocument();

        // Load HTML with proper encoding (suppress warnings)
        @$doc->loadHTML(mb_convert_encoding($this->html, 'HTML-ENTITIES', 'UTF-8'));

        // Get the page title.
        $nodes = $doc->getElementsByTagName('title');
        if ($nodes->length > 0) {
            $title = $nodes->item(0)->nodeValue;
        }

        // Loop through meta tags to extract description and keywords.
        $metas = $doc->getElementsByTagName('meta');
        for ($i = 0; $i < $metas->length; $i++) {
            $meta = $metas->item($i);
            if ($meta->getAttribute('name') === 'description') {
                $description = $meta->getAttribute('content');
            }
            if ($meta->getAttribute('name') === 'keywords') {
                $keywords = $meta->getAttribute('content');
            }
        }
        $meta = array();
        $meta['title']=trim($title);
        $meta['description'] = trim($description);
        $meta['keywords'] = trim($keywords);
        // Encrypt the meta data using serBase()
        $metaEncrypted = jsonEncode($meta);
      
        // Serialize and update meta data in the database. 
        updateToDbPrepared($this->con, 'domains_data', array('meta_data' => $metaEncrypted), array('domain' => $this->domainStr));
      
        return $metaEncrypted;
    }

    /**
     * showMeta()
     *
     * Builds and returns the HTML output for the meta data analysis.
     *
     * @param array $metaData The associative array returned by processMeta().
     * @return string The HTML output.
     */
    public function showMeta($metaData) {
        $metaData = jsonDecode($metaData);
        
        // Calculate character lengths.
        $lenTitle = mb_strlen($metaData['title'], 'utf8');
        $lenDes   = mb_strlen($metaData['description'], 'utf8');

        // Provide default messages if fields are empty.
        $site_title       = ($metaData['title'] == '' ? $this->lang['AN11'] : $metaData['title']);
        $site_description = ($metaData['description'] == '' ? $this->lang['AN12'] : $metaData['description']);
        $site_keywords    = ($metaData['keywords'] == '' ? $this->lang['AN15'] : $metaData['keywords']);

        // Determine CSS classes based on length.
        $classTitle = ($lenTitle < 10) ? 'improveBox' : (($lenTitle < 70) ? 'passedBox' : 'errorBox');
        $classDes   = ($lenDes < 70)   ? 'improveBox' : (($lenDes < 300)   ? 'passedBox' : 'errorBox');
        $classKey   = 'lowImpactBox';

        // Check user permissions.
        if (!isset($_SESSION['twebUsername']) && !isAllowedStats($this->con, 'seoBox1')) {
            die(
                $this->seoBoxLogin . $this->sepUnique .
                $this->seoBoxLogin . $this->sepUnique .
                $this->seoBoxLogin . $this->sepUnique .
                $this->seoBoxLogin
            );
        }

        // Predefined messages.
        $titleMsg  = $this->lang['AN173'];
        $desMsg    = $this->lang['AN174'];
        $keyMsg    = $this->lang['AN175'];
        $googleMsg = $this->lang['AN177'];

        // Build HTML output.
        $output = '<div class="' . $classTitle . '">
                        <div class="msgBox bottom10">
                            ' . $site_title . '<br />
                            <b>' . $this->lang['AN13'] . ':</b> ' . $lenTitle . ' ' . $this->lang['AN14'] . '
                        </div>
                        <div class="seoBox1 suggestionBox">' . $titleMsg . '</div>
                   </div>' . $this->sepUnique;
        $output .= '<div class="' . $classDes . '">
                        <div class="msgBox padRight10 bottom10">
                            ' . $site_description . '<br />
                            <b>' . $this->lang['AN13'] . ':</b> ' . $lenDes . ' ' . $this->lang['AN14'] . '
                        </div>
                        <div class="seoBox2 suggestionBox">' . $desMsg . '</div>
                    </div>' . $this->sepUnique;
        $output .= '<div class="' . $classKey . '">
                        <div class="msgBox padRight10">
                            ' . $site_keywords . '<br /><br />
                        </div>
                        <div class="seoBox3 suggestionBox">' . $keyMsg . '</div>
                    </div>' . $this->sepUnique;
                    $output .= '<div class="' . $classKey . '">
                    <div class="msgBox">
                        <div class="googlePreview">
                        
                            <!-- First Row: Mobile & Tablet Views -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="google-preview-box mobile-preview">
                                        <h6>Mobile View</h6>
                                        <p class="google-title"><a href="#">' . htmlspecialchars($site_title) . '</a></p>
                                        <p class="google-url"><span class="bold">' . htmlspecialchars($this->urlParse['host']) . '</span>/</p>
                                        <p class="google-desc">' . htmlspecialchars($site_description) . '</p>
                                    </div>
                                </div>
                
                                <div class="col-md-6">
                                    <div class="google-preview-box tablet-preview">
                                        <h6>Tablet View</h6>
                                        <p class="google-title"><a href="#">' . htmlspecialchars($site_title) . '</a></p>
                                        <p class="google-url"><span class="bold">' . htmlspecialchars($this->urlParse['host']) . '</span>/</p>
                                        <p class="google-desc">' . htmlspecialchars($site_description) . '</p>
                                    </div>
                                </div>
                            </div>
                
                            <!-- Second Row: Desktop View -->
                            <div class="row mt-3">
                                <div class="col-12">
                                    <div class="google-preview-box desktop-preview">
                                        <h6>Desktop View</h6>
                                        <p class="google-title"><a href="#">' . htmlspecialchars($site_title) . '</a></p>
                                        <p class="google-url"><span class="bold">' . htmlspecialchars($this->urlParse['host']) . '</span>/</p>
                                        <p class="google-desc">' . htmlspecialchars($site_description) . '</p>
                                    </div>
                                </div>
                            </div>
    
                        </div>
                    </div>
                     
                </div>';
                    
        return $output;
    }

    /*-------------------------------------------------------------------
     * HEADING HANDLER
     *-------------------------------------------------------------------
     */

    /**
     * processHeading()
     *
     * Extracts all heading tags (H1–H6) from the HTML and updates the database.
     *
     * @return array Array of headings grouped by tag.
     */
    public function processHeading() {
        
        $doc = new DOMDocument();
        @$doc->loadHTML(mb_convert_encoding($this->html, 'HTML-ENTITIES', 'UTF-8'));
        $tags = array('h1', 'h2', 'h3', 'h4', 'h5', 'h6');
        $headings = array();
        foreach ($tags as $tag) {
            $elements = $doc->getElementsByTagName($tag);
            foreach ($elements as $element) {
                $content = trim(strip_tags($element->textContent));
                if ($content != "") {
                    $headings[$tag][] = trim($content," \t\n\r\0\x0B\xc2\xa0");
                }
            }
        }
      
        $updateStr = jsonEncode(array($headings));
        //$updateStr = serBase(array($headings));
        updateToDbPrepared($this->con, 'domains_data', array('headings' => $updateStr), array('domain' => $this->domainStr));
        return  $updateStr;
    }

    /**
     * showHeading()
     *
     * Builds HTML output for the headings analysis.
     *
     * @param array $headings The array returned by processHeading().
     * @return string The HTML output.
     */
    public function showHeading($headings) {
        // Decode JSON safely
        $headingsArr = jsonDecode($headings);
    
        // Validate data structure
        if (!is_array($headingsArr) || !isset($headingsArr[0])) {
            return '<div class="errorBox"><div class="msgBox">Invalid heading data.</div></div>';
        }
    
        $elementList = $headingsArr[0];
    
        // Define heading tags and initialize counts
        $tags = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
        $counts = [];
        foreach ($tags as $tag) {
            $counts[$tag] = isset($elementList[$tag]) ? count($elementList[$tag]) : 0;
        }
    
        // Determine CSS class based on heading structure
        $boxClass = ($counts['h1'] > 2) ? 'improveBox' :
                    (($counts['h1'] > 0 && $counts['h2'] > 0) ? 'passedBox' : 'errorBox');
    
        // Suggestion text from language array
        $headMsg = isset($this->lang['AN176']) ? $this->lang['AN176'] : "Please review your heading structure.";
    
        // Helper function for suggestions
        if (!function_exists('getHeadingSuggestion')) {
            function getHeadingSuggestion($tag, $count) {
                $tagUpper = strtoupper($tag);
                if ($count === 0) {
                    return ($tag === 'h1') 
                        ? "No {$tagUpper} found. At least one H1 tag is recommended for SEO."
                        : "No {$tagUpper} found. Consider adding for better structure.";
                }
                return ($tag === 'h1' && $count > 2)
                    ? "More than 2 H1 tags found. Best practice is to have only one."
                    : "Looks good for {$tagUpper}.";
            }
        }
    
        // Build the heading count table
        $output = '<div class="contentBox" id="seoBox4">
            <div class="msgBox">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th width="50px">Tag</th>
                            <th width="100px">Count</th>
                            <th>Headings</th>
                            <th width="200px">Suggestion</th>
                        </tr>
                    </thead>
                    <tbody>';
    
        // Generate heading details
        foreach ($tags as $tag) {
            $count = $counts[$tag];
    
            // Build list of headings or show "None found"
            if (!empty($elementList[$tag])) {
                $headingsList = '<ul class="list-unstyled mb-0">';
                foreach ($elementList[$tag] as $text) {
                    $headingsList .= '<li>&lt;' . strtoupper($tag) . '&gt; <b>' . htmlspecialchars($text) . '</b> &lt;/' . strtoupper($tag) . '&gt;</li>';
                }
                $headingsList .= '</ul>';
            } else {
                $headingsList = '<em class="text-muted">None found.</em>';
            }
    
            // Generate suggestion text
            $suggestion = getHeadingSuggestion($tag, $count);
    
            // Add row to the table
            $output .= '
                <tr>
                    <td><strong>' . strtoupper($tag) . '</strong></td>
                    <td>' . $count . '</td>
                    <td>' . $headingsList . '</td>
                    <td class="text-muted small">' . $suggestion . '</td>
                </tr>';
        }
    
        // Complete table and close divs
        $output .= '
                    </tbody>
                </table>
            </div>
            <div class="seoBox4 suggestionBox">' . $headMsg . '</div>
        </div>
        <div class="questionBox" data-original-title="More Information" data-toggle="tooltip" data-placement="top">
            <i class="fa fa-question-circle grayColor"></i>
        </div>';
    
        return $output;
    }
    
    
    

    /*===================================================================
     * IMAGE ALT TAG HANDLER
     *===================================================================
     */

   /**
 * processImage()
 *
 * Processes image tags to evaluate alt attributes in detail:
 * - Counts total images.
 * - Gathers images missing alt attributes.
 * - Gathers images with empty, very short (<5 characters), very long (>100 characters),
 *   or redundant alt text.
 * - Provides suggestions based on the analysis.
 *
 * @return string JSON encoded array of results.
 */
/**
 * processImage()
 *
 * Processes image tags to evaluate alt attributes in detail:
 * - Counts total images.
 * - Aggregates images missing alt attributes, with empty, very short (<5 characters), very long (>100 characters),
 *   or redundant alt text.
 * - Provides suggestions based on the analysis.
 *
 * For duplicate images (by src) in a category, their count is aggregated.
 *
 * @return string JSON encoded array of results.
 */
public function processImage() {
    $doc = new DOMDocument();
    // Load the HTML while handling encoding issues.
    @$doc->loadHTML(mb_convert_encoding($this->html, 'HTML-ENTITIES', 'UTF-8'));
    $xpath = new DOMXPath($doc);
    $imgTags = $xpath->query("//img");
    
    // Initialize results arrays as associative arrays keyed by image src.
    $results = [
        'total_images' => $imgTags->length,
        'images_missing_alt'       => [],
        'images_with_empty_alt'    => [],
        'images_with_short_alt'    => [],
        'images_with_long_alt'     => [],
        'images_with_redundant_alt'=> [],
        'suggestions' => []
    ];
    
    // Helper function to aggregate duplicate images by src.
    $aggregate = function(&$array, $data) {
        $src = $data['src'];
        if(isset($array[$src])) {
            $array[$src]['count']++;
        } else {
            $data['count'] = 1;
            $array[$src] = $data;
        }
    };
    
    foreach ($imgTags as $img) {
        $src = trim($img->getAttribute('src')) ?: 'N/A';
        $alt = $img->getAttribute('alt');
        $title = trim($img->getAttribute('title')) ?: 'N/A';
        $width = trim($img->getAttribute('width')) ?: 'N/A';
        $height = trim($img->getAttribute('height')) ?: 'N/A';
        $class = trim($img->getAttribute('class')) ?: 'N/A';
        $parentTag = $img->parentNode->nodeName;
        $parentTxt = trim($img->parentNode->textContent);
        // Optionally, if you need node position, implement this method or use a default.
        $position = method_exists($this, 'getNodePosition') ? $this->getNodePosition($img) : 'N/A';
        
        // Data to be aggregated
        $data = compact('src', 'title', 'width', 'height', 'class', 'parentTag', 'parentTxt', 'position');
        
        // Check for missing alt attribute.
        if (!$img->hasAttribute('alt')) {
            $aggregate($results['images_missing_alt'], $data);
        }
        // Check for empty alt attribute.
        elseif (trim($alt) === '') {
            $aggregate($results['images_with_empty_alt'], $data);
        } else {
            $altLength = mb_strlen($alt);
            $normalizedAlt = strtolower($alt);
            $redundantAlt = in_array($normalizedAlt, ['image','photo','picture','logo']);
            if ($altLength < 5) {
                $data['alt'] = $alt;
                $data['length'] = $altLength;
                $aggregate($results['images_with_short_alt'], $data);
            }
            if ($altLength > 100) {
                $data['alt'] = $alt;
                $data['length'] = $altLength;
                $aggregate($results['images_with_long_alt'], $data);
            }
            if ($redundantAlt) {
                $data['alt'] = $alt;
                $aggregate($results['images_with_redundant_alt'], $data);
            }
        }
    }
    
    // Build suggestions based on counts for aggregated arrays.
    $totalMissing = array_sum(array_map(function($i){ return $i['count']; }, $results['images_missing_alt']));
    $totalEmpty   = array_sum(array_map(function($i){ return $i['count']; }, $results['images_with_empty_alt']));
    $totalShort   = array_sum(array_map(function($i){ return $i['count']; }, $results['images_with_short_alt']));
    $totalLong    = array_sum(array_map(function($i){ return $i['count']; }, $results['images_with_long_alt']));
    $totalRedund  = array_sum(array_map(function($i){ return $i['count']; }, $results['images_with_redundant_alt']));
    
    if ($totalMissing > 0) {
        $results['suggestions'][] = "There are {$totalMissing} image instances missing alt attributes.";
    }
    if ($totalEmpty > 0) {
        $results['suggestions'][] = "There are {$totalEmpty} image instances with empty alt attributes.";
    }
    if ($totalShort > 0) {
        $results['suggestions'][] = "There are {$totalShort} image instances with very short alt text (<5 chars).";
    }
    if ($totalLong > 0) {
        $results['suggestions'][] = "There are {$totalLong} image instances with very long alt text (>100 chars).";
    }
    if ($totalRedund > 0) {
        $results['suggestions'][] = "There are {$totalRedund} image instances with redundant alt text (e.g., 'image','logo').";
    }
    if ($totalMissing === 0 && $totalEmpty === 0 && $totalShort === 0 && $totalLong === 0 && $totalRedund === 0) {
        $results['suggestions'][] = "Great job! All images have appropriate alt attributes.";
    }
    
    $updateStr = jsonEncode($results);
    updateToDbPrepared($this->con, 'domains_data', ['image_alt' => $updateStr], ['domain' => $this->domainStr]);
    return $updateStr;
}

/**
 * showImage()
 *
 * Returns HTML output for the detailed image alt tag analysis.
 * Displays overall counts, individual tables for each issue category (aggregated by image src),
 * and suggestions.
 *
 * @param string $imageData JSON string as returned by processImage().
 * @return string HTML output.
 */
public function showImage($imageData) {
    $data = jsonDecode($imageData);
    
    // Calculate total issue count based on instance counts.
    $issuesCount = array_sum(array_map(function($i){ return $i['count']; }, $data['images_missing_alt'])) +
                   array_sum(array_map(function($i){ return $i['count']; }, $data['images_with_empty_alt'])) +
                   array_sum(array_map(function($i){ return $i['count']; }, $data['images_with_short_alt'])) +
                   array_sum(array_map(function($i){ return $i['count']; }, $data['images_with_long_alt'])) +
                   array_sum(array_map(function($i){ return $i['count']; }, $data['images_with_redundant_alt']));
    
    // Choose a CSS class based on issue severity.
    $altClass = ($issuesCount == 0) ? 'passedBox' : (($issuesCount < 3) ? 'improveBox' : 'errorBox');
    
    $output = '<div class="' . $altClass . '">
                    <div class="msgBox">
                        ' . str_replace('[image-count]', $data['total_images'], $this->lang['AN21']) . '<br />
                        <div class="altImgGroup">';
    // Display appropriate icon based on issues.
    if ($issuesCount == 0) {
        $output .= '<img src="' . themeLink('img/true.png', true) . '" alt="' . $this->lang['AN24'] . '" title="' . $this->lang['AN25'] . '" /> ' . $this->lang['AN27'] . '<br />';
    } else {
        $output .= '<img src="' . themeLink('img/false.png', true) . '" alt="' . $this->lang['AN23'] . '" title="' . $this->lang['AN22'] . '" /> ';
        $output .= "Issues found: " . $issuesCount;
    }
    $output .= '</div>
                        <br />';
    
    // Helper function to generate tables for a given category.
    $buildTable = function($title, $items) {
        if (count($items) > 0) {
            $table = '<h4>' . $title . ' (' . array_sum(array_map(function($i){ return $i['count']; }, $items)) . ')</h4>';
            $table .= '<table class="table table-striped table-responsive"><thead><tr><th>Image Source</th><th>Count</th></tr></thead><tbody>';
            foreach ($items as $item) {
                $table .= '<tr><td>' . htmlspecialchars($item['src']) . '</td><td>' . $item['count'] . '</td></tr>';
            }
            $table .= '</tbody></table>';
            return $table;
        }
        return '';
    };
    
    // Build tables for each category.
    $output .= $buildTable('Images Missing Alt Attribute', $data['images_missing_alt']);
    $output .= $buildTable('Images With Empty Alt Attribute', $data['images_with_empty_alt']);
    $output .= $buildTable('Images With Short Alt Text', $data['images_with_short_alt']);
    $output .= $buildTable('Images With Long Alt Text', $data['images_with_long_alt']);
    $output .= $buildTable('Images With Redundant Alt Text', $data['images_with_redundant_alt']);
    
    // Display suggestions.
    $output .= '<br /><ul>';
    foreach ($data['suggestions'] as $suggestion) {
        $output .= '<li>' . $suggestion . '</li>';
    }
    $output .= '</ul>
                    </div>
                     
               </div>';
    
    return $output;
}



/**
 * getNodePosition()
 *
 * Returns the position of the given node among its siblings.
 *
 * @param DOMNode $node
 * @return int Position index (starting from 1).
 */
private function getNodePosition(DOMNode $node) {
    $position = 1;
    while ($node->previousSibling) {
        $node = $node->previousSibling;
        $position++;
    }
    return $position;
}

    /*===================================================================
     * KEYWORD CLOUD HANDLER
     *===================================================================
     */

      /**
     * Returns an array of common stop words.
     */
    private function getStopWords(): array {
        return [
            "a","about","above","after","again","against","all","am","an","and","any","are","aren't","as","at","be","because","been","before","being","below","between","both","but","by","can","can't","cannot","could","couldn't","did","didn't","do","does","doesn't","doing","don't","down","during","each","few","for","from","further","had","hadn't","has","hasn't","have","haven't","having","he","he'd","he'll","he's","her","here","here's","hers","herself","him","himself","his","how","how's","i","i'd","i'll","i'm","i've","if","in","into","is","isn't","it","it's","its","itself","let's","me","more","most","mustn't","my","myself","no","nor","not","of","off","on","once","only","or","other","ought","our","ours","ourselves","out","over","own","same","shan't","she","she'd","she'll","she's","should","shouldn't","so","some","such","than","that","that's","the","their","theirs","them","themselves","then","there","there's","these","they","they'd","they'll","they're","they've","this","those","through","to","too","under","until","up","very","was","wasn't","we","we'd","we'll","we're","we've","were","weren't","what","what's","when","when's","where","where's","which","while","who","who's","whom","why","why's","with","won't","would","wouldn't","you","you'd","you'll","you're","you've","your","yours","yourself","yourselves"
            // Add additional words as needed.
        ];
    }

    /**
     * Builds frequency data for an array of tokens.
     */
    private function buildFrequencyData(array $tokens, int $minCount = 2): array {
        $counts = array_count_values($tokens);
        $counts = array_filter($counts, function($cnt) use ($minCount) {
            return $cnt >= $minCount;
        });
        arsort($counts);
        $total = array_sum($counts);
        $result = [];
        foreach ($counts as $phrase => $count) {
            $density = $total > 0 ? ($count / $total) * 100 : 0;
            $result[] = [
                'phrase'  => $phrase,
                'count'   => $count,
                'density' => round($density, 2)
            ];
        }
        return $result;
    }

    /**
     * Detect overused phrases from frequency data.
     * This example flags phrases with count > 5 or density > 5%.
     */
    private function detectOveruse(array $data): array {
        $overused = [];
        foreach ($data as $d) {
            if ($d['count'] > 5 || $d['density'] > 5) {
                $overused[] = $d['phrase'];
            }
        }
        return $overused;
    }

    /**
     * generateKeywordCloud()
     *
     * Extracts the textual content from the HTML, cleans it by removing scripts,
     * styles, comments, and HTML tags, then converts it to lowercase. It splits the
     * text into words and filters out stop words and short words. It then generates
     * unigrams, bigrams, and trigrams arrays, builds frequency data for each, and
     * returns all this data along with suggestions based on possible overuse.
     *
     * @param DOMDocument $dom The DOMDocument instance loaded with the page HTML.
     * @return array Associative array containing 'unigrams', 'bigrams', 'trigrams', and 'suggestions'.
     */
    private function generateKeywordCloud(DOMDocument $dom): array {
        // Get the full HTML and remove scripts, styles, and comments.
        $html = $dom->saveHTML();
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);
        $html = preg_replace('/<!--(.*?)-->/', '', $html);
        $html = html_entity_decode($html, ENT_QUOTES|ENT_HTML5, 'UTF-8');

        // Strip HTML tags and remove HTML entities.
        $textContent = strip_tags($html);
        $textContent = preg_replace('/&[A-Za-z0-9#]+;/', ' ', $textContent);
        $textContent = strtolower($textContent);
        // Replace non-letters with spaces.
        $textContent = preg_replace('/[^a-z\s]/', ' ', $textContent);
        $textContent = preg_replace('/\s+/', ' ', $textContent);
        $textContent = trim($textContent);

        // Split text into words.
        $words = explode(' ', $textContent);
        $words = array_filter($words);

        // Filter out stop words and words shorter than 3 characters.
        $stopWords = $this->getStopWords();
        $filtered = array_filter($words, function($w) use ($stopWords) {
            return !in_array($w, $stopWords) && strlen($w) > 2;
        });
        $filtered = array_values($filtered);

        // Unigrams.
        $unigrams = $filtered;

        // Bigrams.
        $bigrams = [];
        for ($i = 0; $i < count($filtered) - 1; $i++) {
            $bigrams[] = $filtered[$i] . ' ' . $filtered[$i + 1];
        }

        // Trigrams.
        $trigrams = [];
        for ($i = 0; $i < count($filtered) - 2; $i++) {
            $trigrams[] = $filtered[$i] . ' ' . $filtered[$i + 1] . ' ' . $filtered[$i + 2];
        }

        // Build frequency data.
        $uniCloud = $this->buildFrequencyData($unigrams);
        $biCloud  = $this->buildFrequencyData($bigrams);
        $triCloud = $this->buildFrequencyData($trigrams);

        // Detect possible overused phrases.
        $overusedSingles  = $this->detectOveruse($uniCloud);
        $overusedBigrams  = $this->detectOveruse($biCloud);
        $overusedTrigrams = $this->detectOveruse($triCloud);
        $allOverused = array_unique(array_merge($overusedSingles, $overusedBigrams, $overusedTrigrams));
        $suggestions = [];
        if (!empty($allOverused)) {
            $suggestions[] = "Possible overuse of phrases: " . implode(', ', $allOverused);
        }

        return [
            'unigrams'    => $uniCloud,
            'bigrams'     => $biCloud,
            'trigrams'    => $triCloud,
            'suggestions' => $suggestions,
        ];
    }

    /**
     * processKeyCloud()
     *
     * Uses generateKeywordCloud() to extract unigrams, bigrams, and trigrams from the HTML.
     * For the AJAX output, it builds an HTML list from the top 15 unigrams (you can adjust
     * which n-gram set to display as needed) and updates the database with the complete data.
     *
     * @return array Returns an array with keys 'keyCloudData', 'keyDataHtml', and 'outCount'.
     */
    public function processKeyCloud() {
        // Load the HTML into DOMDocument.
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($this->html, 'HTML-ENTITIES', 'UTF-8'));

        // Generate keyword cloud data (including unigrams, bigrams, trigrams and suggestions).
        $cloudData = $this->generateKeywordCloud($dom);

        // For display purposes, we use the unigrams.
        $unigrams = $cloudData['unigrams'];
        $keyDataHtml = '';
        $outArr = [];
        $keyCount = 0;
        $maxKeywords = 15;
        foreach ($unigrams as $data) {
            if ($keyCount >= $maxKeywords) {
                break;
            }
            $keyword = $data['phrase'];
            $outArr[] = [$keyword, $data['count'], $data['density']];
            $keyDataHtml .= '<li><span class="keyword">' . htmlspecialchars($keyword) . '</span><span class="number">' . $data['count'] . '</span></li>';
            $keyCount++;
        }
        $outCount = count($outArr);

        // Optionally, you might want to merge in suggestions or save all n-gram data.
        $updateStr = jsonEncode([
            'keyCloudData' => $outArr,
            'keyDataHtml'  => $keyDataHtml,
            'outCount'     => $outCount,
            'fullCloud'    => $cloudData  // Contains unigrams, bigrams, trigrams, and suggestions.
        ]);

        
        updateToDbPrepared($this->con, 'domains_data', ['keywords_cloud' => $updateStr], ['domain' => $this->domainStr]);
 
        return [
            'keyCloudData' => $outArr,
            'keyDataHtml'  => $keyDataHtml,
            'outCount'     => $outCount,
            'fullCloud'    => $cloudData,
        ];
    }

    /**
     * showKeyCloud()
     *
     * Returns HTML output for the keyword cloud.
     * This method preserves your CSS classes and IDs and uses AJAX-friendly markup.
     *
     * @param array $data The data returned by processKeyCloud().
     * @return string HTML output.
     */
    public function showKeyCloud($data) {
        $outCount = $data['outCount'];
        $keyDataHtml = $data['keyDataHtml'];
        $keycloudClass = 'lowImpactBox';
        $keyMsg = $this->lang['AN179'];
        if (!isset($_SESSION['twebUsername']) && !isAllowedStats($this->con, 'seoBox7')) {
            die($this->seoBoxLogin);
        }
        $output = '<div class="' . $keycloudClass . '">
                        <div class="msgBox padRight10 bottom5">';
        if ($outCount != 0) {
            $output .= '<ul class="keywordsTags">' . $keyDataHtml . '</ul>';
        } else {
            $output .= ' ' . $this->lang['AN29'];
        }
        $output .= '</div>
                        <div class="seoBox7 suggestionBox">' . $keyMsg . '</div>
                   </div>';
        return $output;
    }
    /*===================================================================
     * KEYWORD CONSISTENCY HANDLER
     *===================================================================
     */

    // This function expects the keyword cloud data (from processKeyCloud),
    // the meta data (decoded), and the headings (decoded from processHeading).
    public function processKeyConsistency($keyCloudData, $metaData, $headings) {
        $result = array();
        foreach ($keyCloudData as $item) {
            // Here, $item is expected to be an array: [ keyword, count, density ]
            $keyword = $item[0];
            $inTitle = (stripos($metaData['title'], $keyword) !== false);
            $inDesc  = (stripos($metaData['description'], $keyword) !== false);
            $inHeading = false;
            foreach ($headings as $tag => $texts) {
                foreach ($texts as $text) {
                    if (stripos($text, $keyword) !== false) {
                        $inHeading = true;
                        break 2;
                    }
                }
            }
            $result[] = array(
                'keyword'     => $keyword,
                'count'       => $item[1],
                'title'       => $inTitle,
                'description' => $inDesc,
                'heading'     => $inHeading
            );
        }
        $resultJson = jsonEncode($result);
        updateToDbPrepared($this->con, 'domains_data', array('key_consistency' => jsonEncode($resultJson)), array('domain' => $this->domainStr));
        return $resultJson;
    }

    public function showKeyConsistency($consistencyData) { 
        $consistencyData = jsonDecode($consistencyData);  
        $rows = "";
        $hideCount = 1;
        foreach ($consistencyData as $item) {
            $hideClass = ($hideCount > 4) ? 'hideTr hideTr3' : '';
            $rows .= '<tr class="' . $hideClass . '">
                        <td>' . htmlspecialchars($item['keyword']) . '</td>
                        <td>' . $item['count'] . '</td>
                        <td>' . ($item['title'] ? $this->true : $this->false) . '</td>
                        <td>' . ($item['description'] ? $this->true : $this->false) . '</td>
                        <td>' . ($item['heading'] ? $this->true : $this->false) . '</td>
                      </tr>';
            $hideCount++;
        }
        $output = '<div class="passedBox">
                        <div class="msgBox">
                            <table class="table table-striped table-responsive">
                                <thead>
                                    <tr>
                                        <th>' . $this->lang['AN31'] . '</th>
                                        <th>' . $this->lang['AN32'] . '</th>
                                        <th>' . $this->lang['AN33'] . '</th>
                                        <th>' . $this->lang['AN34'] . '</th>
                                        <th>&lt;H&gt;</th>
                                    </tr>
                                </thead>
                                <tbody>' . $rows . '</tbody>
                            </table>
                        </div>
                        <div class="seoBox8 suggestionBox">' . $this->lang['AN180'] . '</div>
                   </div>';
        return $output;
    }


   
/**
 * showKeyConsistencyNgramsTabs()
 *
 * Displays three tables (Trigrams, Bigrams, Unigrams) in separate tabs.
 * By default, we'll open the "Trigrams" tab first, but you can easily reorder.
 *
 * @param array $fullCloud  The 'fullCloud' array from processKeyCloud()
 *                          e.g. ['unigrams'=>[...], 'bigrams'=>[...], 'trigrams'=>[...], 'suggestions'=>[...]].
 * @param array $metaData   Decoded array from processMeta() (keys: 'title', 'description', etc.).
 * @param array $headings   Decoded array from processHeading()[0] (the array of h1–h6).
 * @return string           HTML output with Bootstrap-based tabs for each n‑gram type.
 */
public function showKeyConsistencyNgramsTabs($fullCloud, $metaData, $headings)
{
    // Ensure we have icons defined
    $this->true  = '<i class="fa fa-check text-success"></i>';
    $this->false = '<i class="fa fa-times text-danger"></i>';

    // Extract arrays for unigrams, bigrams, trigrams
    $unigrams = $fullCloud['unigrams'] ?? [];
    $bigrams  = $fullCloud['bigrams']  ?? [];
    $trigrams = $fullCloud['trigrams'] ?? [];

    // Build each table separately using your existing helper:
    $trigramTable = $this->buildConsistencyTable('Trigrams', $trigrams, $metaData, $headings);
    $bigramTable  = $this->buildConsistencyTable('Bigrams',  $bigrams,  $metaData, $headings);
    $unigramTable = $this->buildConsistencyTable('Unigrams', $unigrams, $metaData, $headings);

    // Now build the tab layout. 
    // Using Bootstrap 4 or 5 markup. Adjust class names as needed if using a different version.
    // We'll make "Trigrams" the default active tab. You can reorder if desired.
    $output = <<<HTML
<div class="keyword-consistency container-fluid">
    <h3>Keyword Consistency</h3>
    <ul class="nav nav-tabs" id="consistencyTabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="trigrams-tab" data-toggle="tab" href="#trigrams-pane" role="tab" aria-controls="trigrams-pane" aria-selected="true">
                Trigrams
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="bigrams-tab" data-toggle="tab" href="#bigrams-pane" role="tab" aria-controls="bigrams-pane" aria-selected="false">
                Bigrams
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="unigrams-tab" data-toggle="tab" href="#unigrams-pane" role="tab" aria-controls="unigrams-pane" aria-selected="false">
                Unigrams
            </a>
        </li>
    </ul>

    <div class="tab-content" id="consistencyTabsContent">
        <!-- Trigrams Tab Pane -->
        <div class="tab-pane fade show active" id="trigrams-pane" role="tabpanel" aria-labelledby="trigrams-tab">
            {$trigramTable}
        </div>

        <!-- Bigrams Tab Pane -->
        <div class="tab-pane fade" id="bigrams-pane" role="tabpanel" aria-labelledby="bigrams-tab">
            {$bigramTable}
        </div>

        <!-- Unigrams Tab Pane -->
        <div class="tab-pane fade" id="unigrams-pane" role="tabpanel" aria-labelledby="unigrams-tab">
            {$unigramTable}
        </div>
    </div>
</div>
HTML;

    return $output;
}


/**
 * buildConsistencyTable()
 *
 * Helper function that builds one table (e.g., "Unigrams") with columns:
 *   - Phrase
 *   - Count
 *   - Title (check or X)
 *   - Desc  (check or X)
 *   - <H>   (check or X)
 *
 * @param string $label    Section label, e.g. "Unigrams", "Bigrams", or "Trigrams".
 * @param array  $ngrams   An array like [ ['phrase'=>'...', 'count'=>X, 'density'=>Y], ... ].
 * @param array  $metaData Decoded array with 'title' and 'description'.
 * @param array  $headings Decoded array of headings.
 *
 * @return string HTML table for the given n‑gram set.
 */
private function buildConsistencyTable($label, $ngrams, $metaData, $headings) {
    $rows = '';
    $hideCount = 1;
    foreach ($ngrams as $item) {
        $phrase = $item['phrase'];
        $count  = $item['count'];
        $inTitle = (stripos($metaData['title'] ?? '', $phrase) !== false);
        $inDesc  = (stripos($metaData['description'] ?? '', $phrase) !== false);
        $inHeading = false;
        foreach ($headings as $tag => $texts) {
            foreach ($texts as $text) {
                if (stripos($text, $phrase) !== false) {
                    $inHeading = true;
                    break 2;
                }
            }
        }
        $hideClass = ($hideCount > 5) ? 'hideTr hideTr3' : '';
        $rows .= '<tr class="' . $hideClass . '">
                    <td>' . htmlspecialchars($phrase) . '</td>
                    <td>' . $count . '</td>
                    <td>' . ($inTitle ? $this->true : $this->false) . '</td>
                    <td>' . ($inDesc ? $this->true : $this->false) . '</td>
                    <td>' . ($inHeading ? $this->true : $this->false) . '</td>
                </tr>';
        $hideCount++;
    }
    $output = '<div class="passedBox">
        <div class="msgBox">
            <h4>' . $label . '</h4>
            <table class="table table-striped table-responsive">
                <thead>
                    <tr>
                        <th>' . $this->lang['AN31'] . '</th>
                        <th>' . $this->lang['AN32'] . '</th>
                        <th>' . $this->lang['AN33'] . '</th>
                        <th>' . $this->lang['AN34'] . '</th>
                        <th>&lt;H&gt;</th>
                    </tr>
                </thead>
                <tbody>' . $rows . '</tbody>
            </table>
            ' . (($hideCount > 6) ? '
            <div class="showLinks showLinks3">
                <a class="showMore showMore3">' . $this->lang['AN18'] . ' <br /><i class="fa fa-angle-double-down"></i></a>
                <a class="showLess showLess3">' . $this->lang['AN19'] . '</a>
            </div>' : '') . '
        </div>
        <div class="seoBox8 suggestionBox">' . $this->lang['AN180'] . '</div>
    </div>';
    return $output;
}
    /*===================================================================
     * TEXT RATIO HANDLER
     *===================================================================
     */

    public function processTextRatio() {
        // Assuming calTextRatio() is a defined function that returns an array.
        $textRatio = calTextRatio($this->html);  
        updateToDbPrepared($this->con, 'domains_data', array('ratio_data' => jsonEncode($textRatio)), array('domain' => $this->domainStr)); 
        return jsonEncode($textRatio);
    }

    public function showTextRatio($textRatio) {
        
        $textRatio = jsonDecode($textRatio); 
        $textClass = (round($textRatio[2]) < 2) ? 'errorBox' : ((round($textRatio[2]) < 10) ? 'improveBox' : 'passedBox');
        $output = '<div class="' . $textClass . '">
                        <div class="msgBox">
                            ' . $this->lang['AN36'] . ': <b>' . round($textRatio[2], 2) . '%</b><br /><br />
                            <table class="table table-responsive">
                                <tbody>
                                    <tr><td>' . $this->lang['AN37'] . '</td><td>' . $textRatio[1] . ' ' . $this->lang['AN39'] . '</td></tr>
                                    <tr><td>' . $this->lang['AN38'] . '</td><td>' . $textRatio[0] . ' ' . $this->lang['AN39'] . '</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="seoBox9 suggestionBox">' . $this->lang['AN181'] . '</div>
                   </div>';
        return $output;
    }

    /*===================================================================
     * GZIP COMPRESSION HANDLER
     *===================================================================
     */

    public function processGzip() {
        $outData = compressionTest($this->urlParse['host']);
        $header = 'Data!';
        $body = (trim($outData[5]) == "") ? 'Data!' : 'Data!'; // Simplified
        $outData = jsonEncode(array($outData[0], $outData[1], $outData[2], $outData[3], $header, $body)); 
        
        updateToDbPrepared($this->con, 'domains_data', array('gzip' => $outData), array('domain' => $this->domainStr));
        return $outData;
    }

    public function showGzip($outData) {
        $outData = jsonDecode($outData);
       
       
        // Determine percentage and class based on compression.
        if ($outData[2]) {
            $percentage = round((((int)$outData[1] - (int)$outData[0]) / (int)$outData[1] * 100), 1);
            $gzipClass = 'passedBox';
            $gzipHead  = $this->lang['AN42'];
            $gzipBody  = '<img src="' . themeLink('img/true.png', true) . '" /> ' . str_replace(
                array('[total-size]', '[compressed-size]', '[percentage]'),
                array(size_as_kb($outData[1]), size_as_kb($outData[0]), $percentage),
                $this->lang['AN41']
            );
        } else {
            $percentage = round((((int)$outData[1] - (int)$outData[3]) / (int)$outData[1] * 100), 1);
            $gzipClass = 'errorBox';
            $gzipHead  = $this->lang['AN43'];
            $gzipBody  = '<img src="' . themeLink('img/false.png', true) . '" /> ' . str_replace(
                array('[total-size]', '[compressed-size]', '[percentage]'),
                array(size_as_kb($outData[1]), size_as_kb($outData[3]), $percentage),
                $this->lang['AN44']
            );
        }
        $output = '<div class="' . $gzipClass . '">
                        <div class="msgBox">
                            ' . $gzipHead . '<br />
                            <div class="altImgGroup">' . $gzipBody . '</div>
                            <br />
                        </div>
                        <div class="seoBox10 suggestionBox">' . $this->lang['AN182'] . '</div>
                   </div>';
        return $output;
    }

    /*===================================================================
     * WWW RESOLVE HANDLER
     *===================================================================
     */

    public function processWWWResolve() {
        $url_with_www = "http://www." . $this->urlParse['host'];
        $url_no_www = "http://" . $this->urlParse['host'];
        $data1 = getHttpCode($url_with_www, false);
        $data2 = getHttpCode($url_no_www, false);
        $updateStr =  jsonEncode(array($data1, $data2)); 
        updateToDbPrepared($this->con, 'domains_data', array('resolve' => $updateStr), array('domain' => $this->domainStr));
       // $result = array('data1' => $data1, 'data2' => $data2);
        return $updateStr;
    }

    public function showWWWResolve($resolveData) {
        $resolveData = jsonDecode($resolveData);
       
        if ($resolveData['data1'] == '301' || $resolveData['data2'] == '301') {
            $resolveClass = 'passedBox';
            $resolveMsg = $this->lang['AN46'];
        } else {
            $resolveClass = 'improveBox';
            $resolveMsg = $this->lang['AN47'];
        }
        $output = '<div class="' . $resolveClass . '">
                        <div class="msgBox">' . $resolveMsg . '<br /><br /></div>
                        <div class="seoBox11 suggestionBox">' . $this->lang['AN183'] . '</div>
                   </div>';
        return $output;
    }

    /*===================================================================
     * IP CANONICALIZATION HANDLER
     *===================================================================
     */

    public function processIPCanonicalization() {
        $hostIP = gethostbyname($this->urlParse['host']);
        $ch = curl_init($hostIP);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        $response = curl_exec($ch);
        preg_match_all('/^Location:(.*)$/mi', $response, $matches);
        curl_close($ch);

        $tType = false;
        $redirectURLhost = '';
        if (!empty($matches[1])) {
            $redirectURL = 'http://' . clean_url(trim($matches[1][0]));
            $redirectURLparse = parse_url($redirectURL);
            $redirectURLhost = isset($redirectURLparse['host']) ? str_replace('www.', '', $redirectURLparse['host']) : '';
            $tType = true;
        } 
        $updateStr = jsonEncode(array($hostIP, $tType, $this->urlParse['host'], $redirectURLhost));
        updateToDbPrepared($this->con, 'domains_data', array('ip_can' => $updateStr), array('domain' => $this->domainStr));
        return array('hostIP' => $hostIP, 'redirectURLhost' => $redirectURLhost);
    }

    public function showIPCanonicalization($ipData) {
       
        if ($this->urlParse['host'] == $ipData['redirectURLhost']) {
            $ipClass = 'passedBox';
            $ipMsg = str_replace(array('[ip]', '[host]'), array($ipData['hostIP'], $this->urlParse['host']), $this->lang['AN50']);
        } else {
            $ipClass = 'improveBox';
            $ipMsg = str_replace(array('[ip]', '[host]'), array($ipData['hostIP'], $this->urlParse['host']), $this->lang['AN49']);
        }
        $output = '<div class="' . $ipClass . '">
                        <div class="msgBox">' . $ipMsg . '<br /><br /></div>
                        <div class="seoBox12 suggestionBox">' . $this->lang['AN184'] . '</div>
                   </div>';
        return $output;
    }

    /*===================================================================
     * IN-PAGE LINKS HANDLER
     *===================================================================
     */

     
    

    /**
     * showInPageLinks()
     *
     * Simply calls processAndRenderInPageLinks() to output the HTML.
     *
     * @param array $linksData The raw links data (as returned by processInPageLinks()).
     * @return string The HTML output.
     */
    public function showInPageLinks($linksData) {  
        return $this->processAndRenderInPageLinks($linksData);
    }


    /*===================================================================
     * BROKEN LINKS HANDLER
     *===================================================================
     */

    public function processBrokenLinks() {

       
        // Assuming processInPageLinks() has already run and returned $linksData.
        $linksData = $this->processInPageLinks();
        $brokenLinks = array();
        $brokenLinks[]="Goes here";
        // foreach ($linksData as $link) {
        //     $url = $link['href'];
        //     // Normalize URL if needed.
        //     if (substr($url, 0, 1) == "/") {
        //         $url = $this->urlParse['scheme'] . "://" . $this->urlParse['host'] . $url;
        //     }
        //     $httpCode = getHttpCode($url);
        //     if ($httpCode == 404) {
        //         $brokenLinks[] = $url;
        //     }
        // }
      
       
       // updateToDbPrepared($this->con, 'domains_data', array('broken_links' => jsonEncode($brokenLinks)), array('domain' => $this->domainStr));
        
        return $brokenLinks;
    }

    public function showBrokenLinks($brokenLinks) {
        $brokenClass = (count($brokenLinks) == 0) ? 'passedBox' : 'errorBox';
        $brokenMsg = (count($brokenLinks) == 0) ? $this->lang['AN68'] : $this->lang['AN69'];
        $rows = "";
        foreach ($brokenLinks as $link) {
            $rows .= '<tr><td>' . $link . '</td></tr>';
        }
        $output = '<div class="' . $brokenClass . '">
                        <div class="msgBox">
                            ' . $brokenMsg . '<br /><br />
                            ' . (count($brokenLinks) > 0 ? '<table class="table table-responsive"><tbody>' . $rows . '</tbody></table>' : '') . '
                        </div>
                        <div class="seoBox14 suggestionBox">' . $this->lang['AN186'] . '</div>
                   </div>';
        return $output;
    }

    /*===================================================================
     * ROBOTS.TXT HANDLER
     *===================================================================
     */

    public function processRobots() {
        $robotLink = $this->urlParse['scheme'] . "://" . $this->urlParse['host'] . '/robots.txt';
        $httpCode = getHttpCode($robotLink); 
        updateToDbPrepared($this->con, 'domains_data', array('robots' => jsonEncode($httpCode)), array('domain' => $this->domainStr));
        return array('robotLink' => $robotLink, 'httpCode' => $httpCode);
    }

    public function showRobots($robotsData) {
        if ($robotsData['httpCode'] == '404') {
            $robotClass = 'errorBox';
            $robotMsg = $this->lang['AN74'] . '<br><a href="' . $robotsData['robotLink'] . '" title="' . $this->lang['AN75'] . '" rel="nofollow" target="_blank">' . $robotsData['robotLink'] . '</a>';
        } else {
            $robotClass = 'passedBox';
            $robotMsg = $this->lang['AN73'] . '<br><a href="' . $robotsData['robotLink'] . '" title="' . $this->lang['AN75'] . '" rel="nofollow" target="_blank">' . $robotsData['robotLink'] . '</a>';
        }
        $output = '<div class="' . $robotClass . '">
                        <div class="msgBox">' . $robotMsg . '<br /><br /></div>
                        <div class="seoBox16 suggestionBox">' . $this->lang['AN187'] . '</div>
                   </div>';
        return $output;
    }

    /*===================================================================
     * SITEMAP HANDLER
     *===================================================================
     */

    public function processSitemap() {
        $sitemapInfo = jsonEncode(getSitemapInfo($this->urlParse['scheme'] . "://" . $this->urlParse['host'])); 
        updateToDbPrepared($this->con, 'domains_data', array('sitemap' => ($sitemapInfo)), array('domain' => $this->domainStr));
        return $sitemapInfo;
    }

    public function showSitemap($sitemapInfo) {
        $sitemapInfo = jsonDecode($sitemapInfo);
        
        if ($sitemapInfo['httpCode'] == '404') {
            $sitemapClass = 'errorBox';
            $sitemapMsg = $this->lang['AN71'] . '<br><a href="' . $sitemapInfo['sitemapLink'] . '" title="' . $this->lang['AN72'] . '" rel="nofollow" target="_blank">' . $sitemapInfo['sitemapLink'] . '</a>';
        } else {
            $sitemapClass = 'passedBox';
            $sitemapMsg = $this->lang['AN70'] . '<br><a href="' . $sitemapInfo['sitemapLink'] . '" title="' . $this->lang['AN72'] . '" rel="nofollow" target="_blank">' . $sitemapInfo['sitemapLink'] . '</a>';
        }
        $output = '<div class="' . $sitemapClass . '">
                        <div class="msgBox">' . $sitemapMsg . '<br /><br /></div>
                        <div class="seoBox15 suggestionBox">' . $this->lang['AN188'] . '</div>
                   </div>';
        return $output;
    }

    /*===================================================================
     * EMBEDDED OBJECT HANDLER
     *===================================================================
     */

    public function processEmbedded() {
       
        $embeddedCheck = false;
        $doc = new DOMDocument();
        @$doc->loadHTML(mb_convert_encoding($this->html, 'HTML-ENTITIES', 'UTF-8'));
        $objects = $doc->getElementsByTagName('object');
        foreach ($objects as $obj) {
            $embeddedCheck = true;
            break;
        }
        $embeds = $doc->getElementsByTagName('embed');
        foreach ($embeds as $embed) {
            $embeddedCheck = true;
            break;
        }
       
        
        updateToDbPrepared($this->con, 'domains_data', array('embedded' => $embeddedCheck), array('domain' => $this->domainStr));
        return $embeddedCheck;
    }

    public function showEmbedded($embeddedCheck) {
        $embeddedClass = $embeddedCheck ? 'errorBox' : 'passedBox';
        $embeddedMsg = $embeddedCheck ? $this->lang['AN78'] : $this->lang['AN77'];
        $output = '<div class="' . $embeddedClass . '">
                        <div class="msgBox">' . $embeddedMsg . '<br /><br /></div>
                        <div class="seoBox19 suggestionBox">' . $this->lang['AN191'] . '</div>
                   </div>';
        return $output;
    }

    /*===================================================================
     * IFRAME HANDLER
     *===================================================================
     */

    public function processIframe() {
        $iframeCheck = false;
        $doc = new DOMDocument();
        @$doc->loadHTML(mb_convert_encoding($this->html, 'HTML-ENTITIES', 'UTF-8'));
        $iframes = $doc->getElementsByTagName('iframe');
        foreach ($iframes as $iframe) {
            $iframeCheck = true;
            break;
        }
        
        updateToDbPrepared($this->con, 'domains_data', array('iframe' => $iframeCheck), array('domain' => $this->domainStr));
        return $iframeCheck;
    }

    public function showIframe($iframeCheck) {
        $iframeClass = $iframeCheck ? 'errorBox' : 'passedBox';
        $iframeMsg = $iframeCheck ? $this->lang['AN80'] : $this->lang['AN79'];
        $output = '<div class="' . $iframeClass . '">
                        <div class="msgBox">' . $iframeMsg . '<br /><br /></div>
                        <div class="seoBox20 suggestionBox">' . $this->lang['AN192'] . '</div>
                   </div>';
        return $output;
    }

    /*===================================================================
     * WHOIS HANDLER
     *===================================================================
     */

    public function processWhois() {
        $whois = new whois();
        $site = $whois->cleanUrl($this->urlParse['host']);
        $whois_data = jsonEncode($whois->whoislookup($site)); 
        updateToDbPrepared($this->con, 'domains_data', array('whois' => $whois_data), array('domain' => $this->domainStr));
        return $whois_data;
    }

    public function showWhois($whois_data) {
        $whois_data = jsonDecode($whois_data);
        
        
        // Build HTML output from WHOIS raw data.
        $lines = preg_split("/\r\n|\n|\r/", $whois_data[0]);
        $rows = "";
        $count = 0;
        foreach ($lines as $line) {
            if (!empty($line)) {
                if ($count == 5) { $class = 'hideTr hideTr6'; } else { $class = ''; }
                $rows .= '<tr class="' . $class . '"><td>' . $line . '</td></tr>';
                $count++;
            }
        }
        $output = '<div class="lowImpactBox">
                        <div class="msgBox">
                            ' . $this->lang['AN85'] . '<br /><br />
                            <div class="altImgGroup">
                                <p><i class="fa fa-paw solveMsgGreen"></i> ' . $this->lang['AN86'] . ': ' . $whois_data[1] . '</p>
                                <p><i class="fa fa-paw solveMsgGreen"></i> ' . $this->lang['AN87'] . ': ' . $whois_data[2] . '</p>
                                <p><i class="fa fa-paw solveMsgGreen"></i> ' . $this->lang['AN88'] . ': ' . $whois_data[3] . '</p>
                                <p><i class="fa fa-paw solveMsgGreen"></i> ' . $this->lang['AN89'] . ': ' . $whois_data[4] . '</p>
                            </div>
                        </div>
                        <div class="seoBox21 suggestionBox">' . $this->lang['AN193'] . '</div>
                   </div>
                   <div class="lowImpactBox">
                        <div class="msgBox">
                            ' . $this->lang['AN84'] . '<br /><br />
                            <table class="table table-hover table-bordered table-striped">
                                <tbody>' . $rows . '</tbody>
                            </table>
                        </div>
                        <div class="seoBox22 suggestionBox">' . $this->lang['AN194'] . '</div>
                   </div>';
        return $output;
    }

    /*===================================================================
     * MOBILE FRIENDLINESS HANDLER
     *===================================================================
     */

    public function processMobileCheck() {
        $jsonData = getMobileFriendly($this->urlParse['scheme'] . "://" . $this->urlParse['host']);  
        $updateStr = jsonEncode($jsonData); 
        updateToDbPrepared($this->con, 'domains_data', array('mobile_fri' => $updateStr), array('domain' => $this->domainStr));
        return $updateStr;
    }

    public function showMobileCheck($jsonData) {
        $jsonData = jsonDecode($jsonData);
        
        $mobileClass = $jsonData['passed'] ? 'passedBox' : 'errorBox';
        $mobileMsg = $jsonData['passed'] 
                        ? $this->lang['AN116'] . '<br>' . str_replace('[score]', intval($jsonData['score']), $this->lang['AN117'])
                        : $this->lang['AN118'] . '<br>' . str_replace('[score]', intval($jsonData['score']), $this->lang['AN117']);
        $screenData = $jsonData['screenshot'];
        $mobileScreenData = ($screenData == '') ? '' : '<img src="data:image/jpeg;base64,' . $screenData . '" />';
        $output = '<div class="' . $mobileClass . '">
                        <div class="msgBox">' . $mobileMsg . '<br /><br /></div>
                        <div class="seoBox23 suggestionBox">' . $this->lang['AN195'] . '</div>
                   </div>
                   <div class="lowImpactBox">
                        <div class="msgBox"><div class="mobileView">' . $mobileScreenData . '</div><br /></div>
                        <div class="seoBox24 suggestionBox">' . $this->lang['AN196'] . '</div>
                   </div>';
        return $output;
    }

    /*===================================================================
     * MOBILE COMPATIBILITY HANDLER
     *===================================================================
     */

    public function processMobileCom() {
        $doc = new DOMDocument();
        @$doc->loadHTML(mb_convert_encoding($this->html, 'HTML-ENTITIES', 'UTF-8'));
        $mobileComCheck = false;
        // Check for iframes, objects, or embeds.
        $elements = array_merge(
            iterator_to_array($doc->getElementsByTagName('iframe')),
            iterator_to_array($doc->getElementsByTagName('object')),
            iterator_to_array($doc->getElementsByTagName('embed'))
        );
        foreach ($elements as $el) {
            if ($el) { $mobileComCheck = true; break; }
        }
        
        updateToDbPrepared($this->con, 'domains_data', array('mobile_com' => $mobileComCheck), array('domain' => $this->domainStr));
        return $mobileComCheck;
    }

    public function showMobileCom($mobileComCheck) {
        $mobileComClass = $mobileComCheck ? 'errorBox' : 'passedBox';
        $mobileComMsg = $mobileComCheck ? $this->lang['AN121'] : $this->lang['AN120'];
        $output = '<div class="' . $mobileComClass . '">
                        <div class="msgBox">' . $mobileComMsg . '<br /><br /></div>
                        <div class="seoBox25 suggestionBox">' . $this->lang['AN197'] . '</div>
                   </div>';
        return $output;
    }

   /*===================================================================
     * URL LENGTH & FAVICON HANDLER
     *===================================================================
     */

    /**
     * processUrlLength()
     *
     * Splits the host into parts and returns the first part (hostWord),
     * its length, and the full URL constructed from the scheme and host.
     *
     * @return array An associative array with keys 'hostWord', 'length', and 'fullUrl'.
     */
    public function processUrlLength() {
        // Get the host from the parsed URL.
        $host = $this->urlParse['host'];
        $hostParts = explode('.', $host);
        $length = strlen($hostParts[0]);
        // Build the full URL (e.g. "http://example.com")
        $fullUrl = $this->urlParse['scheme'] . "://" . $host;
        return array(
            'hostWord' => $hostParts[0],
            'length'   => $length,
            'fullUrl'  => $fullUrl
        );
    }

    /**
     * showUrlLength()
     *
     * Builds and returns the HTML output for the URL length and favicon analysis.
     *
     * @param array $data An array with keys 'hostWord', 'length', and 'fullUrl' (from processUrlLength()).
     * @return string The HTML output.
     */
    public function showUrlLength($data) {
        // Decide CSS class based on length of the first part of the hostname.
        $urlLengthClass = ($data['length'] < 15) ? 'passedBox' : 'errorBox';
        // Build the URL length message. This uses the full URL and the character count.
        $urlLengthMsg = $data['fullUrl'] . '<br>' . str_replace('[count]', $data['length'], $this->lang['AN122']);
        // Build the favicon message. Here we use the full URL as provided.
        $favIconMsg = '<img src="https://www.google.com/s2/favicons?domain=' . $data['fullUrl'] . '" alt="FavIcon" />  ' . $this->lang['AN123'];
        // Construct the HTML output.
        $output = '<div class="' . $urlLengthClass . '">
                        <div class="msgBox">
                             ' . $urlLengthMsg . '
                            <br /><br />
                        </div>
                        <div class="seoBox26 suggestionBox">
                             ' . $this->lang['AN198'] . '
                        </div> 
                   </div>' . $this->sepUnique .
                   '<div class="lowImpactBox">
                        <div class="msgBox">
                             ' . $favIconMsg . '
                            <br /><br />
                        </div>
                        <div class="seoBox27 suggestionBox">
                             ' . $this->lang['AN199'] . '
                        </div> 
                   </div>';
                   
        return $output;
    }

    /*===================================================================
     * CUSTOM 404 PAGE HANDLER
     *===================================================================
     */

    public function processErrorPage() {
        $url = $this->urlParse['scheme'] . "://" . $this->urlParse['host'] . '/404error-test-page-by-atoz-seo-tools';
        $pageSize = strlen(curlGET($url)); 
        updateToDbPrepared($this->con, 'domains_data', array('404_page' => jsonEncode($pageSize)), array('domain' => $this->domainStr));
        return $pageSize;
    }

    public function showErrorPage($pageSize) {
        $pageSize = jsonDecode($pageSize);
        
        if ($pageSize < 1500) {
            $errorPageClass = 'errorBox';
            $errorPageMsg = $this->lang['AN125'];
        } else {
            $errorPageClass = 'passedBox';
            $errorPageMsg = $this->lang['AN124'];
        }
        $output = '<div class="' . $errorPageClass . '">
                        <div class="msgBox">' . $errorPageMsg . '<br /><br /></div>
                        <div class="seoBox28 suggestionBox">' . $this->lang['AN200'] . '</div>
                   </div>';
        return $output;
    }

    /*===================================================================
     * PAGE LOAD HANDLER
     *===================================================================
     */

    public function processPageLoad() {
        $timeStart = microtime(true);
        $ch = curl_init();
        $url = $this->urlParse['scheme'] . "://" . $this->urlParse['host'];
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.3; WOW64; rv:36.0) Gecko/20100101 Firefox/36.0');
        curl_setopt($ch, CURLOPT_REFERER, $url);
        $htmlContent = curl_exec($ch);
        curl_close($ch);
        $timeEnd = microtime(true);
        $timeTaken = $timeEnd - $timeStart;
        $dataSize = strlen($htmlContent);

        // Try to detect language code.
        $langCode = null;
        if (preg_match('#<html[^>]+lang=[\'"]?(.*?)[\'"]?#is', $htmlContent, $matches)) {
            $langCode = trim(mb_substr($matches[1], 0, 5));
        } elseif (preg_match('#<meta[^>]+http-equiv=[\'"]?content-language[\'"]?[^>]+content=[\'"]?(.*?)[\'"]?#is', $htmlContent, $matches)) {
            $langCode = trim(mb_substr($matches[1], 0, 5));
        }
        
        $updateStr = jsonEncode(array('timeTaken' => $timeTaken, 'dataSize' => $dataSize, 'langCode' => $langCode));
        
        updateToDbPrepared($this->con, 'domains_data', array('load_time' => $updateStr), array('domain' => $this->domainStr));
        return $updateStr;
    }

    public function showPageLoad($pageLoadData) {
        $pageLoadData = jsonDecode($pageLoadData);
        $dataSize = size_as_kb($pageLoadData['dataSize']);
        $sizeClass = ($dataSize < 320) ? 'passedBox' : 'errorBox';
        $sizeMsg = str_replace('[size]', $dataSize, $this->lang['AN126']);
        $timeTaken = round($pageLoadData['timeTaken'], 2);
        $loadClass = ($timeTaken < 1) ? 'passedBox' : 'errorBox';
        $loadMsg = str_replace('[time]', $timeTaken, $this->lang['AN127']);
        $langClass = is_null($pageLoadData['langCode']) ? 'errorBox' : 'passedBox';
        $langCode = is_null($pageLoadData['langCode']) ? $this->lang['AN129'] : lang_code_to_lnag($pageLoadData['langCode']);
        $langMsg = str_replace('[language]', $langCode, $this->lang['AN130']);

        $output = '<div class="' . $sizeClass . '">
                        <div class="msgBox">' . $sizeMsg . '<br /><br /></div>
                        <div class="seoBox29 suggestionBox">' . $this->lang['AN201'] . '</div>
                   </div>' . $this->sepUnique;
        $output .= '<div class="' . $loadClass . '">
                        <div class="msgBox">' . $loadMsg . '<br /><br /></div>
                        <div class="seoBox30 suggestionBox">' . $this->lang['AN202'] . '</div>
                   </div>' . $this->sepUnique;
        $output .= '<div class="' . $langClass . '">
                        <div class="msgBox">' . $langMsg . '<br /><br /></div>
                        <div class="seoBox31 suggestionBox">' . $this->lang['AN203'] . '</div>
                   </div>';
        return $output;
    }

    /*===================================================================
     * DOMAIN & TYPO AVAILABILITY HANDLER
     *===================================================================
     */

    public function processAvailabilityChecker() {
        // Load domain availability servers.
        $path = LIB_DIR . 'domainAvailabilityservers.tdata';
        $serverList = array();
        if (file_exists($path)) {
            $contents = file_get_contents($path);
            $serverList = json_decode($contents, true);
        }
        $tldCodes = array('com', 'net', 'org', 'biz', 'us', 'info', 'eu');
        $domainWord = explode('.', $this->urlParse['host']);
        $hostTLD = trim(end($domainWord));
        $domainWord = $domainWord[0];
        $doArr = $tyArr = array();
        $tldCount = 0;
        foreach ($tldCodes as $tldCode) {
            if ($tldCount == 5)
                break;
            if ($tldCode != $hostTLD) {
                $topDomain = $domainWord . '.' . $tldCode;
                $domainAvailabilityChecker = new domainAvailability($serverList);
                $domainAvailabilityStats = $domainAvailabilityChecker->isAvailable($topDomain);
                $doArr[] = array($topDomain, $domainAvailabilityStats);
                $tldCount++;
            }
        }
        // Process typo domains.
        $typo = new typos();
        $domainTypoWords = $typo->get($domainWord);
        $typoCount = 0;
        foreach ($domainTypoWords as $word) {
            if ($typoCount == 5)
                break;
            $topDomain = $word . '.' . $hostTLD;
            $domainAvailabilityChecker = new domainAvailability($serverList);
            $domainAvailabilityStats = $domainAvailabilityChecker->isAvailable($topDomain);
            $tyArr[] = array($topDomain, $domainAvailabilityStats);
            $typoCount++;
        }
        $updateStr = jsonEncode(array('doArr' => $doArr, 'tyArr' => $tyArr));
        updateToDbPrepared($this->con, 'domains_data', array('domain_typo' => $updateStr), array('domain' => $this->domainStr));
        return $updateStr;
    }

    public function showAvailabilityChecker($availabilityData) {
        $availabilityData = jsonDecode($availabilityData);
        $domainMsg = '';
        foreach ($availabilityData['doArr'] as $item) {
            $domainMsg .= '<tr><td>' . $item[0] . '</td><td>' . $item[1] . '</td></tr>';
        }
        $typoMsg = '';
        foreach ($availabilityData['tyArr'] as $item) {
            $typoMsg .= '<tr><td>' . $item[0] . '</td><td>' . $item[1] . '</td></tr>';
        }
        $seoBox32 = '<div class="lowImpactBox">
                        <div class="msgBox">
                            <table class="table table-hover table-bordered table-striped">
                                <tbody>
                                    <tr><th>' . $this->lang['AN134'] . '</th><th>' . $this->lang['AN135'] . '</th></tr>' . $domainMsg . '
                                </tbody>
                            </table>
                            <br />
                        </div>
                        <div class="seoBox32 suggestionBox">' . $this->lang['AN204'] . '</div>
                     </div>';
        $seoBox33 = '<div class="lowImpactBox">
                        <div class="msgBox">
                            <table class="table table-hover table-bordered table-striped">
                                <tbody>
                                    <tr><th>' . $this->lang['AN134'] . '</th><th>' . $this->lang['AN135'] . '</th></tr>' . $typoMsg . '
                                </tbody>
                            </table>
                            <br />
                        </div>
                        <div class="seoBox33 suggestionBox">' . $this->lang['AN205'] . '</div>
                     </div>';
        return $seoBox32 . $this->sepUnique . $seoBox33;
    }

    /*===================================================================
     * EMAIL PRIVACY HANDLER
     *===================================================================
     */

    public function processEmailPrivacy() {
        preg_match_all("/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6})/", $this->html, $matches, PREG_SET_ORDER);
        $emailCount = count($matches); 
        updateToDbPrepared($this->con, 'domains_data', array('email_privacy' => $emailCount), array('domain' => $this->domainStr));
        return $emailCount;
    }

    public function showEmailPrivacy($emailCount) {
        $emailPrivacyClass = ($emailCount == 0) ? 'passedBox' : 'errorBox';
        $emailPrivacyMsg = ($emailCount == 0) ? $this->lang['AN136'] : $this->lang['AN137'];
        $output = '<div class="' . $emailPrivacyClass . '">
                        <div class="msgBox">' . $emailPrivacyMsg . '<br /><br /></div>
                        <div class="seoBox34 suggestionBox">' . $this->lang['AN206'] . '</div>
                   </div>';
        return $output;
    }

    /*===================================================================
     * SAFE BROWSING HANDLER
     *===================================================================
     */

    public function processSafeBrowsing() {
        $safeBrowsingStats = safeBrowsing($this->urlParse['host']); 
        updateToDbPrepared($this->con, 'domains_data', array('safe_bro' => $safeBrowsingStats), array('domain' => $this->domainStr));
        return $safeBrowsingStats;
    }

    public function showSafeBrowsing($stats) {
        if ($stats == 204) {
            $safeBrowsingClass = 'passedBox';
            $safeBrowsingMsg = $this->lang['AN138'];
        } elseif ($stats == 200) {
            $safeBrowsingClass = 'errorBox';
            $safeBrowsingMsg = $this->lang['AN139'];
        } else {
            $safeBrowsingClass = 'improveBox';
            $safeBrowsingMsg = $this->lang['AN140'];
        }
        $output = '<div class="' . $safeBrowsingClass . '">
                        <div class="msgBox">' . $safeBrowsingMsg . '<br /><br /></div>
                        <div class="seoBox35 suggestionBox">' . $this->lang['AN207'] . '</div>
                   </div>';
        return $output;
    }

    /*===================================================================
     * SERVER LOCATION HANDLER
     *===================================================================
     */

    public function processServerIP() {
        $getHostIP = gethostbyname($this->urlParse['host']);
        $data_list = host_info($this->urlParse['host']);
        $updateStr =  jsonEncode(array('ip' => $getHostIP,'country' => $data_list[1],'isp' => $data_list[2]));
      
        updateToDbPrepared($this->con, 'domains_data', array('server_loc' => $updateStr), array('domain' => $this->domainStr));
        return $updateStr;
    }

    public function showServerIP($data_list) {
        $data_list = jsonDecode($data_list); 
        $output = '<div class="lowImpactBox">
                        <div class="msgBox">
                            <table class="table table-hover table-bordered table-striped">
                                <tbody>
                                    <tr>
                                        <th>' . $this->lang['AN141'] . '</th>
                                        <th>' . $this->lang['AN142'] . '</th>
                                        <th>' . $this->lang['AN143'] . '</th>
                                    </tr>
                                    <tr>
                                        <td>' . gethostbyname($this->urlParse['host']) . '</td>
                                        <td>' . $data_list['country'] . '</td>
                                        <td>' . $data_list['isp'] . '</td>
                                    </tr>
                                </tbody>
                            </table>
                            <br />
                        </div>
                        <div class="seoBox36 suggestionBox">' . $this->lang['AN208'] . '</div>
                   </div>';
        return $output;
    }

    /*===================================================================
     * SPEED TIPS HANDLER
     *===================================================================
     */

    public function processSpeedTips() {
        // Use regex to count CSS, JS, nested tables, and inline CSS.
        $cssCount = $jsCount = 0;
        preg_match_all("#<link[^>]*>#is", $this->html, $matches);
        if (!empty($matches[0])) {
            foreach ($matches[0] as $tagVal) {
                if (preg_match("#(?=.*\bstylesheet\b)(?=.*\bhref=('[^']*'|\"[^\"]*\")).*#is", $tagVal))
                    $cssCount++;
            }
        }
        preg_match_all("#<script[^>]*>#is", $this->html, $matches);
        if (!empty($matches[0])) {
            foreach ($matches[0] as $tagVal) {
                if (preg_match("#src=('[^']*'|\"[^\"]*\")#is", $tagVal))
                    $jsCount++;
            }
        }
        $nestedTables = preg_match("#<(td|th)(?:[^>]*)>(.*?)<table(?:[^>]*)>(.*?)</table>(.*?)</(td|th)#is", $this->html);
        $inlineCss = preg_match("#<.+style=\"[^\"]+\".+>#is", $this->html);
        $updateStr = serBase(array($cssCount, $jsCount, $nestedTables, $inlineCss));
        updateToDbPrepared($this->con, 'domains_data', array('speed_tips' => $updateStr), array('domain' => $this->domainStr));
        return array(
            'cssCount' => $cssCount,
            'jsCount' => $jsCount,
            'nestedTables' => $nestedTables,
            'inlineCss' => $inlineCss
        );
    }

    public function showSpeedTips($data) {
        $speedTipsCheck = 0;
        $speedTipsBody = '';
        $speedTipsBody .= ($data['cssCount'] > 5) ? '<img src="' . themeLink('img/false.png', true) . '" /> ' . $this->lang['AN145'] : '<img src="' . themeLink('img/true.png', true) . '" /> ' . $this->lang['AN144'];
        $speedTipsBody .= '<br><br>';
        $speedTipsBody .= ($data['jsCount'] > 5) ? '<img src="' . themeLink('img/false.png', true) . '" /> ' . $this->lang['AN147'] : '<img src="' . themeLink('img/true.png', true) . '" /> ' . $this->lang['AN146'];
        $speedTipsBody .= '<br><br>';
        $speedTipsBody .= ($data['nestedTables'] == 1) ? '<img src="' . themeLink('img/false.png', true) . '" /> ' . $this->lang['AN149'] : '<img src="' . themeLink('img/true.png', true) . '" /> ' . $this->lang['AN148'];
        $speedTipsBody .= '<br><br>';
        $speedTipsBody .= ($data['inlineCss'] == 1) ? '<img src="' . themeLink('img/false.png', true) . '" /> ' . $this->lang['AN151'] : '<img src="' . themeLink('img/true.png', true) . '" /> ' . $this->lang['AN150'];
        if ($data['cssCount'] > 5 || $data['jsCount'] > 5 || $data['nestedTables'] == 1 || $data['inlineCss'] == 1) {
            $speedTipsClass = ($speedTipsCheck > 2) ? 'errorBox' : 'improveBox';
        } else {
            $speedTipsClass = 'passedBox';
        }
        $output = '<div class="' . $speedTipsClass . '">
                        <div class="msgBox">
                            ' . $this->lang['AN152'] . '<br />
                            <div class="altImgGroup">' . $speedTipsBody . '</div>
                            <br />
                        </div>
                        <div class="seoBox37 suggestionBox">' . $this->lang['AN209'] . '</div>
                   </div>';
        return $output;
    }

    /*===================================================================
     * ANALYTICS & DOCTYPE HANDLER
     *===================================================================
     */

    public function processDocType() {
        // Check for Google Analytics tracking code.
        $anCheck = false;
        if (preg_match("/\bua-\d{4,9}-\d{1,4}\b/i", $this->html) || check_str_contains($this->html, "gtag('")) {
            $analyticsClass = 'passedBox';
            $analyticsMsg = $this->lang['AN154'];
            $anCheck = true;
        } else {
            $analyticsClass = 'errorBox';
            $analyticsMsg = $this->lang['AN153'];
        }
        // Detect DOCTYPE.
        $patternCode = "<!DOCTYPE[^>]*>";
        preg_match("#{$patternCode}#is", $this->html, $matches);
        if (!isset($matches[0])) {
            $docTypeMsg = $this->lang['AN155'];
            $docTypeClass = 'improveBox';
            $docType = "";
        } else {
            $doctypes = array(
                'HTML 5' => '<!DOCTYPE html>',
                'HTML 4.01 Strict' => '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">',
                'HTML 4.01 Transitional' => '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">',
                'HTML 4.01 Frameset' => '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Frameset//EN" "http://www.w3.org/TR/html4/frameset.dtd">',
                'XHTML 1.0 Strict' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">',
                'XHTML 1.0 Transitional' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">',
                'XHTML 1.0 Frameset' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Frameset//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-frameset.dtd">',
                'XHTML 1.1' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">'
            );
            $found = strtolower(preg_replace('/\s+/', ' ', trim($matches[0])));
            $docType = array_search($found, array_map('strtolower', $doctypes));
            $docTypeMsg = $this->lang['AN156'] . ' ' . $docType;
            $docTypeClass = 'passedBox';
        }
        $updateStr = jsonEncode(array('analyticsMsg' => $analyticsMsg, 'docTypeMsg' => $docTypeMsg, 'analyticsClass' => $analyticsClass, 'docTypeClass' => $docTypeClass));
        
        updateToDbPrepared($this->con, 'domains_data', array('analytics' => $updateStr), array('domain' => $this->domainStr));
        return $updateStr;
    }

    public function showDocType($data) {
        $data = jsonDecode($data); 
        $seoBox38 = '<div class="' . $data['analyticsClass'] . '">
                        <div class="msgBox">' . $data['analyticsMsg'] . '<br /><br /></div>
                        <div class="seoBox38 suggestionBox">' . $this->lang['AN210'] . '</div>
                     </div>';
        $seoBox40 = '<div class="' . $data['docTypeClass'] . '">
                        <div class="msgBox">' . $data['docTypeMsg'] . '<br /><br /></div>
                        <div class="seoBox40 suggestionBox">' . $this->lang['AN212'] . '</div>
                     </div>';
        return $seoBox38 . $this->sepUnique . $seoBox40;
    }

    /*===================================================================
     * W3C VALIDITY HANDLER
     *===================================================================
     */

    public function processW3c() {
        $w3Data = curlGET('https://validator.w3.org/nu/?doc=' . urlencode($this->urlParse['scheme'] . "://" . $this->urlParse['host']));
        if (!empty($w3Data)) {
            if (check_str_contains($w3Data, 'document validates')) {
                $w3cMsg = $this->lang['AN157'];
                $w3DataCheck = '1';
            } else {
                $w3cMsg = $this->lang['AN158'];
                $w3DataCheck = '2';
            }
        } else {
            $w3cMsg = $this->lang['AN10'];
            $w3DataCheck = '3';
        }
        $updateStr = jsonEncode(array('w3cMsg' => $w3cMsg, 'w3DataCheck' => $w3DataCheck));
        
        updateToDbPrepared($this->con, 'domains_data', $updateStr, array('domain' => $this->domainStr));
        return array('w3cMsg' => $w3cMsg, 'w3DataCheck' => $w3DataCheck);
    }

    public function showW3c($w3cData) {
        echo " <pre>";
        print_r($w3cData);
        echo "</pre> ";
        echo "Hi";
        die();
       
        $w3cData = jsonDecode($w3cData); 
       
        $output = '<div class="lowImpactBox">
                        <div class="msgBox">' . $w3cData['w3cMsg'] . '<br /><br /></div>
                        <div class="seoBox39 suggestionBox">' . $this->lang['AN211'] . '</div>
                   </div>';
                   echo "$output<pre>";
                   print_r($w3cData);
                   echo "</pre>";
                   echo "Hi";
                   die();
        return $output;
    }

    /*===================================================================
     * ENCODING HANDLER
     *===================================================================
     */

    public function processEncoding() {
        $pattern = '<meta[^>]+charset=[\'"]?(.*?)[\'"]?[\/\s>]';
        preg_match("#{$pattern}#is", $this->html, $matches);
        $charset = isset($matches[1]) ? trim(mb_strtoupper($matches[1])) : null; 
        $updateStr = base64_encode($charset);
        updateToDbPrepared($this->con, 'domains_data', array('encoding' => $updateStr), array('domain' => $this->domainStr));
        return $charset;
    }

    public function showEncoding($charset) {
        $encodingClass = $charset ? 'passedBox' : 'errorBox';
        $encodingMsg = $charset ? $this->lang['AN159'] . ' ' . $charset : $this->lang['AN160'];
        $output = '<div class="' . $encodingClass . '">
                        <div class="msgBox">' . $encodingMsg . '<br /><br /></div>
                        <div class="seoBox41 suggestionBox">' . $this->lang['AN213'] . '</div>
                   </div>';
        return $output;
    }

    /*===================================================================
     * INDEXED PAGES HANDLER
     *===================================================================
     */

    public function processIndexedPages() {
        $indexed = trim(str_replace(',', '', googleIndex($this->urlParse['host']))); 
        updateToDbPrepared($this->con, 'domains_data', array('indexed' => jsonEncode($indexed)), array('domain' => $this->domainStr));
        return $indexed;
    }

    public function showIndexedPages($indexed) {
        $indexed = jsonDecode($indexed);
        
        if (intval($indexed) < 50) {
            $datVal = 25;
            $indexedClass = 'errorBox';
            $progress = 'danger';
        } elseif (intval($indexed) < 200) {
            $datVal = 75;
            $indexedClass = 'improveBox';
            $progress = 'warning';
        } else {
            $datVal = 100;
            $indexedClass = 'passedBox';
            $progress = 'success';
        }
        $indexedPagesMsg = '<div style="width:' . $datVal . '%" role="progressbar" class="progress-bar progress-bar-' . $progress . '">
                                ' . number_format($indexed) . ' ' . $this->lang['AN162'] . '
                             </div>';
        $output = '<div class="' . $indexedClass . '">
                        <div class="msgBox">
                            ' . $this->lang['AN161'] . '<br /><br />
                            <div class="progress">' . $indexedPagesMsg . '</div><br />
                        </div>
                        <div class="seoBox42 suggestionBox">' . $this->lang['AN214'] . '</div>
                   </div>';
        return $output;
    }

    /*===================================================================
     * BACKLINKS / ALEXA / SITE WORTH HANDLER
     *===================================================================
     */

    public function processBacklinks() {
        $alexa = alexaRank($this->urlParse['host']);
        $alexa[3] = backlinkCount(clean_url($this->urlParse['host']), $this->con); 
        $updateStr = jsonEncode(array((string)$alexa[0], (string)$alexa[1], (string)$alexa[2], (string)$alexa[3]));
        updateToDbPrepared($this->con, 'domains_data', array('alexa' => $updateStr), array('domain' => $this->domainStr));
        return $updateStr;
    }

    public function showBacklinks($alexa) {
       
        $alexa = jsonDecode($alexa);
       
        $alexa_back = intval($alexa[3]);
        if ($alexa_back < 50) {
            $datVal = 25;
            $backlinksClass = 'errorBox';
            $progress = 'danger';
        } elseif ($alexa_back < 100) {
            $datVal = 75;
            $backlinksClass = 'improveBox';
            $progress = 'warning';
        } else {
            $datVal = 100;
            $backlinksClass = 'passedBox';
            $progress = 'success';
        }
        $backlinksMsg = '<div style="width:' . $datVal . '%" role="progressbar" class="progress-bar progress-bar-' . $progress . '">
                            ' . number_format($alexa_back) . ' ' . $this->lang['AN163'] . '
                         </div>';
        $alexa_rank = ($alexa[0] == 'No Global Rank') ? '0' : $alexa[0];
        $alexaMsg = ($alexa[0] == 'No Global Rank') ? $this->lang['AN165'] : ordinalNum(str_replace(',', '', $alexa[0])) . ' ' . $this->lang['AN164'];
        $worthMsg = "$" . number_format(calPrice($alexa_rank)) . " USD";
        $seoBox43 = '<div class="' . $backlinksClass . '">
                        <div class="msgBox">
                            ' . $this->lang['AN166'] . '<br /><br />
                            <div class="progress">' . $backlinksMsg . '</div><br />
                        </div>
                        <div class="seoBox43 suggestionBox">' . $this->lang['AN215'] . '</div>
                     </div>';
        $seoBox45 = '<div class="lowImpactBox">
                        <div class="msgBox">' . $worthMsg . '<br /><br /></div>
                        <div class="seoBox45 suggestionBox">' . $this->lang['AN217'] . '</div>
                     </div>';
        $seoBox46 = '<div class="lowImpactBox">
                        <div class="msgBox">' . $alexaMsg . '<br /><br /></div>
                        <div class="seoBox46 suggestionBox">' . $this->lang['AN218'] . '</div>
                     </div>';
        return $seoBox43 . $this->sepUnique . $seoBox45 . $this->sepUnique . $seoBox46;
    }

    /*===================================================================
     * SOCIAL DATA HANDLER
     *===================================================================
     */

    public function processSocialData() {
        $socialData = getSocialData($this->html);
        $updateStr = serBase(array($socialData['fb'], $socialData['twit'], $socialData['insta'], 0));
        updateToDbPrepared($this->con, 'domains_data', array('social' => $updateStr), array('domain' => $this->domainStr));
        return $socialData;
    }

    public function showSocialData($socialData) {
        $facebook_like = ($socialData['fb'] === '-') ? $this->false : $this->true . ' ' . $socialData['fb'];
        $twit_count = ($socialData['twit'] === '-') ? $this->false : $this->true . ' ' . $socialData['twit'];
        $insta_count = ($socialData['insta'] === '-') ? $this->false : $this->true . ' ' . $socialData['insta'];
        $output = '<div class="lowImpactBox">
                        <div class="msgBox">
                            ' . $this->lang['AN167'] . '<br />
                            <div class="altImgGroup">
                                <br>
                                <div class="social-box"><i class="fa fa-facebook social-facebook"></i> Facebook: ' . $facebook_like . '</div><br>
                                <div class="social-box"><i class="fa fa-twitter social-linkedin"></i> Twitter: ' . $twit_count . '</div><br>
                                <div class="social-box"><i class="fa fa-instagram social-google"></i> Instagram: ' . $insta_count . '</div>
                            </div>
                            <br />
                        </div>
                        <div class="seoBox44 suggestionBox">' . $this->lang['AN216'] . '</div>
                   </div>';
        return $output;
    }

    /*===================================================================
     * VISITORS LOCALIZATION HANDLER
     *===================================================================
     */

    public function processVisitorsData() {
        // Retrieve visitor localization info from DB (simulate here)
        $data = mysqliPreparedQuery($this->con, "SELECT alexa FROM domains_data WHERE domain=?", 's', array($this->domainStr));
        if ($data !== false) {
            $alexa = jsonDecode($data['alexa']);
            $alexaDatas = array(
                array('', 'Popularity at', $alexa[1]),
                array('', 'Regional Rank', $alexa[2])
            );
            return $alexaDatas;
        }
        return array();
    }

    public function showVisitorsData($alexaDatas) {
        $rows = "";
        foreach ($alexaDatas as $item) {
            $rows .= '<tr><td>' . $item[1] . '</td><td>' . $item[2] . '</td></tr>';
        }
        $output = '<div class="lowImpactBox">
                        <div class="msgBox">
                            ' . $this->lang['AN171'] . '<br /><br />
                            <table class="table table-hover table-bordered table-striped">
                                <tbody>' . $rows . '</tbody>
                            </table><br />
                        </div>
                        <div class="seoBox47 suggestionBox">' . $this->lang['AN219'] . '</div>
                   </div>';
        return $output;
    }

    /*===================================================================
     * PAGE SPEED INSIGHT HANDLER
     *===================================================================
     */

    public function processPageSpeedInsight() {
        $desktopScore = pageSpeedInsightChecker($this->urlParse['host'], 'desktop');
        $mobileScore = pageSpeedInsightChecker($this->urlParse['host'], 'mobile');
        $updateStr =  jsonEncode(array('desktopScore' => $desktopScore, 'mobileScore' => $mobileScore));
        
        updateToDbPrepared($this->con, 'domains_data', array('page_speed_insight' => $updateStr), array('domain' => $this->domainStr));
        return $updateStr;
    }

    public function showPageSpeedInsight($data) {
        $data = jsonDecode($data);
        $speedStr = $this->lang['117'];
        $desktopStr = $this->lang['118'];
        $mobileStr = $this->lang['119'];
        // Desktop gauge script.
        $desktopMsg = <<<EOT
<script>
var desktopPageSpeed = new Gauge({
    renderTo  : 'desktopPageSpeed',
    width     : 250,
    height    : 250,
    glow      : true,
    units     : '$speedStr',
    title     : '$desktopStr',
    minValue  : 0,
    maxValue  : 100,
    majorTicks: ['0','20','40','60','80','100'],
    minorTicks: 5,
    strokeTicks: true,
    valueFormat: { int : 2, dec : 0, text : '%' },
    valueBox: { rectStart: '#888', rectEnd: '#666', background: '#CFCFCF' },
    valueText: { foreground: '#CFCFCF' },
    highlights: [
        { from: 0, to: 40, color: '#EFEFEF' },
        { from: 40, to: 60, color: 'LightSalmon' },
        { from: 60, to: 80, color: 'Khaki' },
        { from: 80, to: 100, color: 'PaleGreen' }
    ],
    animation: { delay: 10, duration: 300, fn: 'bounce' }
});
desktopPageSpeed.onready = function() { desktopPageSpeed.setValue({$data['desktopScore']}); };
desktopPageSpeed.draw();
</script>
EOT;
        // Mobile gauge script.
        $mobileMsg = <<<EOT
<script>
var mobilePageSpeed = new Gauge({
    renderTo  : 'mobilePageSpeed',
    width     : 250,
    height    : 250,
    glow      : true,
    units     : '$speedStr',
    title     : '$mobileStr',
    minValue  : 0,
    maxValue  : 100,
    majorTicks: ['0','20','40','60','80','100'],
    minorTicks: 5,
    strokeTicks: true,
    valueFormat: { int : 2, dec : 0, text : '%' },
    valueBox: { rectStart: '#888', rectEnd: '#666', background: '#CFCFCF' },
    valueText: { foreground: '#CFCFCF' },
    highlights: [
        { from: 0, to: 40, color: '#EFEFEF' },
        { from: 40, to: 60, color: 'LightSalmon' },
        { from: 60, to: 80, color: 'Khaki' },
        { from: 80, to: 100, color: 'PaleGreen' }
    ],
    animation: { delay: 10, duration: 300, fn: 'bounce' }
});
mobilePageSpeed.onready = function() { mobilePageSpeed.setValue({$data['mobileScore']}); };
mobilePageSpeed.draw();
</script>
EOT;
        $seoBox48 = '<div class="passedBox">
                        <div class="msgBox">
                            <div class="row">
                                <div class="col-sm-6 text-center">
                                    <canvas id="desktopPageSpeed"></canvas>' . $desktopMsg . '
                                </div>
                                <div class="col-sm-6">
                                    <h2>' . $data['desktopScore'] . ' / 100</h2>
                                    <h4>' . $this->lang['123'] . '</h4>
                                    <p><strong>' . ucfirst($this->urlParse['host']) . '</strong> ' . $this->lang['127'] . ' <strong>Sample Speed</strong>. ' . $this->lang['128'] . '</p>
                                </div>
                            </div>
                        </div>
                        <div class="seoBox48 suggestionBox">' . $this->lang['AN220'] . '</div>
                    </div>';
        $seoBox49 = '<div class="passedBox">
                        <div class="msgBox">
                            <div class="row">
                                <div class="col-sm-6 text-center">
                                    <canvas id="mobilePageSpeed"></canvas>' . $mobileMsg . '
                                </div>
                                <div class="col-sm-6">
                                    <h2>' . $data['mobileScore'] . ' / 100</h2>
                                    <h4>' . $this->lang['123'] . '</h4>
                                    <p><strong>' . ucfirst($this->urlParse['host']) . '</strong> ' . $this->lang['129'] . ' <strong>Sample Speed</strong>. ' . $this->lang['128'] . '</p>
                                </div>
                            </div>
                        </div>
                        <div class="seoBox49 suggestionBox">' . $this->lang['AN221'] . '</div>
                    </div>';
        return $seoBox48 . $this->sepUnique . $seoBox49;
    }

    /*===================================================================
     * CLEAN OUT HANDLER
     *===================================================================
     */

    public function cleanOut() {
        $passscore = raino_trim($_POST['passscore']);
        $improvescore = raino_trim($_POST['improvescore']);
        $errorscore = raino_trim($_POST['errorscore']);
        $score = array($passscore, $improvescore, $errorscore);
        $updateStr = serBase($score);
        updateToDbPrepared($this->con, 'domains_data', array('score' => $updateStr, 'completed' => 'yes'), array('domain' => $this->domainStr));

        $data = mysqliPreparedQuery($this->con, "SELECT * FROM domains_data WHERE domain=?", 's', array($this->domainStr));
        if ($data !== false) {
            $pageSpeedInsightData = jsonDecode($data['page_speed_insight']);
            $alexa = jsonDecode($data['alexa']);
            $finalScore = ($passscore == '') ? '0' : $passscore;
            $globalRank = ($alexa[0] == '') ? '0' : $alexa[0];
            $pageSpeed = ($pageSpeedInsightData[0] == '') ? '0' : $pageSpeedInsightData[0];

            if (!isset($_SESSION['twebUsername']))
                $username = trans('Guest', $this->lang['11'], true);
            else
                $username = $_SESSION['twebUsername'];

            if ($globalRank == 'No Global Rank')
                $globalRank = 0;

            $other = serBase(array($finalScore, $globalRank, $pageSpeed));
            addToRecentSites($this->con, $this->domainStr, $ip, $username, $other);
        }

        // Clear temporary cached file (assume $filename is available globally or passed in)
        delFile($filename);
    }
}
?>