<?php
require_once __DIR__ . '/../../vendor/autoload.php';

require_once   'ServerInfoHelper.php';
/**
 * SeoTools.php
 *
 * This class consolidates all SEO test handlers into one tool.
 * For each SEO test there are two methods:
 *   - processXXX(): Extracts the data (and updates the database)
 *   - showXXX(): Returns the HTML output for that test.
 *
 * All functions from the original version are retained.
 */
class SeoTools {
    // Global properties used by all handlers.
    protected string $html;         // Normalized HTML source (with meta tag names in lowercase)
    protected $con;                 // Database connection
    protected string $domainStr;    // Normalized domain string (used for DB lookups)
    protected array $lang;          // Language strings array
    protected ?array $urlParse;     // Parsed URL array (from parse_url())
    protected string $sepUnique;    // Unique separator string for output sections
    protected string $seoBoxLogin;  // HTML snippet for a login box (if user isn’t logged in)
    protected string $true;         // Icon for "true"
    protected string $false;        // Icon for "false"
    protected string $scheme;
    protected string $host;
    /**
     * Constructor.
     *
     * @param string|null $html       The normalized HTML source.
     * @param mixed       $con        The database connection.
     * @param string      $domainStr  The normalized domain string.
     * @param array       $lang       The language strings array.
     * @param array|null  $urlParse   The parsed URL (via parse_url()).
     * @param string|null $sepUnique  A unique separator string. Defaults to '!!!!8!!!!'.
     * @param string|null $seoBoxLogin HTML snippet for a login box.
     */
    public function __construct(
        ?string $html, 
        $con, 
        string $domainStr, 
        array $lang, 
        ?array $urlParse, 
        ?string $sepUnique = null, 
        ?string $seoBoxLogin = null
    ) {
        $this->html = $this->normalizeHtml($html);
        $this->con = $con;
        
        // If domainStr is empty but the parsed URL contains a host, use that.
        if (empty($domainStr) && isset($urlParse['host'])) {
            $domainStr = strtolower($urlParse['host']);
        }
        $this->domainStr = $domainStr;
        $this->lang = $lang;
    
        // If no parsed URL is provided, try creating one using the domain string.
        if (!$urlParse && !empty($domainStr)) {
            $defaultUrl = "http://" . $domainStr;
            $urlParse = parse_url($defaultUrl);
        }
        $this->urlParse = $urlParse;
    
        // Validate that both scheme and host are available.
        if (!isset($this->urlParse['scheme']) || !isset($this->urlParse['host'])) {
            throw new Exception("Invalid URL: Both scheme and host must be provided.");
        }
        $this->scheme = $this->urlParse['scheme'];
        $this->host = $this->urlParse['host'];
    
        $this->sepUnique = $sepUnique ?? '!!!!8!!!!';
        $this->seoBoxLogin = $seoBoxLogin ?? '<div class="lowImpactBox"><div class="msgBox">Please log in to view SEO details.</div></div>';
        $this->true = '<img src="' . themeLink('img/true.png', true) . '" alt="True" />';
        $this->false = '<img src="' . themeLink('img/false.png', true) . '" alt="False" />';
    }
    

    /**
     * normalizeHtml()
     *
     * Ensures that the provided HTML is a non-null string.
     *
     * @param string|null $html
     * @return string
     */
    private function normalizeHtml(?string $html): string {
        return $html ?? '';
    }

    /**
     * getDom()
     *
     * Returns a DOMDocument loaded with the normalized HTML.
     *
     * @return DOMDocument
     */
    private function getDom(): DOMDocument {
        $doc = new DOMDocument();
        // Suppress warnings for invalid HTML.
        @$doc->loadHTML(mb_convert_encoding($this->html, 'HTML-ENTITIES', 'UTF-8'));
        return $doc;
    }

    /**
     * getLinkPosition()
     *
     * Determines the position (header, nav, main, footer, aside, section or body)
     * of a node by traversing its ancestors.
     *
     * @param DOMNode $node
     * @return string
     */
    private function getLinkPosition(DOMNode $node): string {
        $positions = ['header', 'nav', 'main', 'footer', 'aside', 'section'];
        while ($node && $node->nodeName !== 'html') {
            $nodeName = strtolower($node->nodeName);
            if (in_array($nodeName, $positions)) {
                return $nodeName;
            }
            $node = $node->parentNode;
        }
        return 'body';
    }

    /*===================================================================
     * META HANDLER
     *=================================================================== 
     */
    public function processMeta() {
        $title = $description = $keywords = '';
        $doc = $this->getDom();
        $nodes = $doc->getElementsByTagName('title');
        if ($nodes->length > 0) {
            $title = $nodes->item(0)->nodeValue;
        }
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
        $meta = [
            'title'       => trim($title),
            'description' => trim($description),
            'keywords'    => trim($keywords)
        ];
        $metaEncrypted = jsonEncode($meta);
        updateToDbPrepared($this->con, 'domains_data', ['meta_data' => $metaEncrypted], ['domain' => $this->domainStr]);
        return $metaEncrypted;
    }

    public function showMeta($metaData): string {
        $metaData = jsonDecode($metaData);
        $lenTitle = mb_strlen($metaData['title'], 'utf8');
        $lenDes   = mb_strlen($metaData['description'], 'utf8');
    
        $site_title       = $metaData['title'] ?: $this->lang['AN11'];
        $site_description = $metaData['description'] ?: $this->lang['AN12'];
        $site_keywords    = $metaData['keywords'] ?: $this->lang['AN15'];
    
        // Determine box classes based on string lengths.
        $classTitle = ($lenTitle < 10) ? 'improveBox' : (($lenTitle < 70) ? 'passedBox' : 'errorBox');
        $classDes   = ($lenDes < 70) ? 'improveBox' : (($lenDes < 300) ? 'passedBox' : 'errorBox');
        $classKey   = 'lowImpactBox';
    
        // Check login/permissions.
        if (!isset($_SESSION['twebUsername']) && !isAllowedStats($this->con, 'seoBox1')) {
            die(str_repeat($this->seoBoxLogin . $this->sepUnique, 4));
        }
    
        $titleMsg  = $this->lang['AN173'];
        $desMsg    = $this->lang['AN174'];
        $keyMsg    = $this->lang['AN175'];
    
        // Meta Title Block (seoBox1)
        $output = '<div class="seoBox seoBox1 ' . $classTitle . '">
                        <div class="msgBox bottom10">
                            ' . $site_title . '<br />
                            <b>' . $this->lang['AN13'] . ':</b> ' . $lenTitle . ' ' . $this->lang['AN14'] . '
                        </div>
                        <div class="suggestionBox">' . $titleMsg . '</div>
                   </div>' . $this->sepUnique;
    
        // Meta Description Block (seoBox2)
        $output .= '<div class="seoBox seoBox2 ' . $classDes . '">
                        <div class="msgBox padRight10 bottom10">
                            ' . $site_description . '<br />
                            <b>' . $this->lang['AN13'] . ':</b> ' . $lenDes . ' ' . $this->lang['AN14'] . '
                        </div>
                        <div class="suggestionBox">' . $desMsg . '</div>
                    </div>' . $this->sepUnique;
    
        // Meta Keywords Block (seoBox3)
        $output .= '<div class="seoBox seoBox3 ' . $classKey . '">
                        <div class="msgBox padRight10">
                            ' . $site_keywords . '<br /><br />
                        </div>
                        <div class="suggestionBox">' . $keyMsg . '</div>
                    </div>' . $this->sepUnique;
    
        // Google Preview Box (seoBox5)
        $host = $this->urlParse['host'] ?? '';
        $googlePreview = '<div id="seoBox5" class="seoBox seoBox5 ' . $classKey . '">
                            <div class="msgBox">
                                <div class="googlePreview">
                                    <!-- First Row: Mobile & Tablet Views -->
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="google-preview-box mobile-preview">
                                                <h6>Mobile View</h6>
                                                <p class="google-title"><a href="#">' . $site_title . '</a></p>
                                                <p class="google-url"><span class="bold">' . $host . '</span>/</p>
                                                <p class="google-desc">' . $site_description . '</p>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="google-preview-box tablet-preview">
                                                <h6>Tablet View</h6>
                                                <p class="google-title"><a href="#">' . $site_title . '</a></p>
                                                <p class="google-url"><span class="bold">' . $host . '</span>/</p>
                                                <p class="google-desc">' . $site_description . '</p>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Second Row: Desktop View -->
                                    <div class="row mt-3">
                                        <div class="col-12">
                                            <div class="google-preview-box desktop-preview mt-5">
                                                <h6>Desktop View</h6>
                                                <p class="google-title"><a href="#">' . $site_title . '</a></p>
                                                <p class="google-url"><span class="bold">' . $host . '</span>/</p>
                                                <p class="google-desc">' . $site_description . '</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                         </div>';
        $output .= $googlePreview . $this->sepUnique;
    
        return $output;
    }
    
    

    /*-------------------------------------------------------------------
     * HEADING HANDLER
     *-------------------------------------------------------------------
     */
    public function processHeading() {
        $doc = $this->getDom();
        $tags = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
        $headings = [];
        foreach ($tags as $tag) {
            $elements = $doc->getElementsByTagName($tag);
            foreach ($elements as $element) {
                $content = trim(strip_tags($element->textContent));
                if ($content !== "") {
                    $headings[$tag][] = trim($content, " \t\n\r\0\x0B\xc2\xa0");
                }
            }
        }
        $updateStr = jsonEncode([$headings]);
        updateToDbPrepared($this->con, 'domains_data', ['headings' => $updateStr], ['domain' => $this->domainStr]);
        return $updateStr;
    }

    public function showHeading($headings): string {
        $headingsArr = jsonDecode($headings);
        if (!is_array($headingsArr) || !isset($headingsArr[0])) {
            return '<div class="alert alert-danger">Invalid heading data.</div>';
        }
        $elementList = $headingsArr[0];
        $tags = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
        $counts = [];
        foreach ($tags as $tag) {
            $counts[$tag] = isset($elementList[$tag]) ? count($elementList[$tag]) : 0;
        }
        // Map the computed class to Bootstrap badge classes:
        $boxClass = ($counts['h1'] > 2)
                    ? 'bg-warning text-dark'       // Warning style for too many H1 tags
                    : (($counts['h1'] > 0 && $counts['h2'] > 0)
                        ? 'bg-success text-white'    // Success style when structure is good
                        : 'bg-danger text-white');   // Danger style otherwise
    
        $headMsg = $this->lang['AN176'] ?? "Please review your heading structure.";
    
        if (!function_exists('getHeadingSuggestion')) {
            function getHeadingSuggestion($tag, $count) {
                $tagUpper = strtoupper($tag);
                if ($count === 0) {
                    return ($tag === 'h1')
                        ? "No {$tagUpper} found. At least one H1 tag is recommended for SEO."
                        : "No {$tagUpper} found. Consider adding one for better structure.";
                }
                return ($tag === 'h1' && $count > 2)
                    ? "More than 2 H1 tags found. Best practice is to have only one."
                    : "Looks good for {$tagUpper}.";
            }
        }
    
        $output = '<div class="card my-3" id="seoBox4">
            <div class="card-header">
                <h4>Heading Structure</h4>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 50px;">Tag</th>
                            <th style="width: 100px;">Count</th>
                            <th>Headings</th>
                            <th style="width: 200px;">Suggestion</th>
                        </tr>
                    </thead>
                    <tbody>';
        foreach ($tags as $tag) {
            $count = $counts[$tag];
            $headingsList = !empty($elementList[$tag])
                ? '<ul class="list-unstyled mb-0">' . implode('', array_map(function($text) use ($tag) {
                      return '<li>&lt;' . strtoupper($tag) . '&gt; <strong>' . htmlspecialchars($text) . '</strong> &lt;/' . strtoupper($tag) . '&gt;</li>';
                  }, $elementList[$tag])) . '</ul>'
                : '<em class="text-muted">None found.</em>';
            $suggestion = getHeadingSuggestion($tag, $count);
            $output .= '<tr>
                        <td><strong>' . strtoupper($tag) . '</strong></td>
                        <td>' . $count . '</td>
                        <td>' . $headingsList . '</td>
                        <td class="text-muted small">' . $suggestion . '</td>
                    </tr>';
        }
        $output .= '
                    </tbody>
                </table>
            </div>
            <div class="card-footer text-center">
                <span class="badge ' . $boxClass . ' p-2">' . $headMsg . '</span>
            </div>
        </div>
       ';
        return $output;
    }
    
    
    

    /*===================================================================
     * IMAGE ALT TAG HANDLER
     *=================================================================== 
     */
    public function processImage() {
        $doc = $this->getDom();
        $xpath = new DOMXPath($doc);
        $imgTags = $xpath->query("//img");
        $results = [
            'total_images' => $imgTags->length,
            'images_missing_alt'       => [],
            'images_with_empty_alt'    => [],
            'images_with_short_alt'    => [],
            'images_with_long_alt'     => [],
            'images_with_redundant_alt'=> [],
            'suggestions' => []
        ];
    
        $aggregate = function (&$array, $data) {
            $src = $data['src'];
            if (isset($array[$src])) {
                $array[$src]['count']++;
            } else {
                $data['count'] = 1;
                $array[$src] = $data;
            }
        };
    
        foreach ($imgTags as $img) {
            // Original extraction
            $src = trim($img->getAttribute('src')) ?: 'N/A';
    
            // Convert relative to absolute
            $src = $this->toAbsoluteUrl($src);
    
            $alt = $img->getAttribute('alt');
            $title = trim($img->getAttribute('title')) ?: 'N/A';
            $width = trim($img->getAttribute('width')) ?: 'N/A';
            $height = trim($img->getAttribute('height')) ?: 'N/A';
            $class = trim($img->getAttribute('class')) ?: 'N/A';
            $parentTag = $img->parentNode->nodeName;
            $parentTxt = trim($img->parentNode->textContent);
            $position = method_exists($this, 'getNodePosition') ? $this->getNodePosition($img) : 'N/A';
    
            // Build data array
            $data = compact('src', 'title', 'width', 'height', 'class', 'parentTag', 'parentTxt', 'position');
    
            // Check alt attribute
            if (!$img->hasAttribute('alt')) {
                $aggregate($results['images_missing_alt'], $data);
            } elseif (trim($alt) === '') {
                $aggregate($results['images_with_empty_alt'], $data);
            } else {
                $altLength = mb_strlen($alt);
                $normalizedAlt = strtolower($alt);
                $redundantAlt = in_array($normalizedAlt, ['image', 'photo', 'picture', 'logo']);
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
        $totalMissing = array_sum(array_map(fn($i) => $i['count'], $results['images_missing_alt']));
        $totalEmpty   = array_sum(array_map(fn($i) => $i['count'], $results['images_with_empty_alt']));
        $totalShort   = array_sum(array_map(fn($i) => $i['count'], $results['images_with_short_alt']));
        $totalLong    = array_sum(array_map(fn($i) => $i['count'], $results['images_with_long_alt']));
        $totalRedund  = array_sum(array_map(fn($i) => $i['count'], $results['images_with_redundant_alt']));
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

    public function showImage($imageData): string {
        $data = jsonDecode($imageData);
    
        // Validate the decoded data.
        if (!is_array($data) || !isset($data['total_images'])) {
            return '<div class="alert alert-warning">No image data available.</div>';
        }
        
        // Calculate total issues across all categories.
        $issuesCount = array_sum(array_map(fn($i) => $i['count'], $data['images_missing_alt'] ?? []))
                     + array_sum(array_map(fn($i) => $i['count'], $data['images_with_empty_alt'] ?? []))
                     + array_sum(array_map(fn($i) => $i['count'], $data['images_with_short_alt'] ?? []))
                     + array_sum(array_map(fn($i) => $i['count'], $data['images_with_long_alt'] ?? []))
                     + array_sum(array_map(fn($i) => $i['count'], $data['images_with_redundant_alt'] ?? []));
        
        // Determine border and header color based on issues.
        if ($issuesCount == 0) {
            $boxClass = 'border-success';
            $headerIcon = themeLink('img/true.png', true);
            $headerText = $this->lang['AN27'];
        } elseif ($issuesCount < 3) {
            $boxClass = 'border-warning';
            $headerIcon = themeLink('img/false.png', true);
            $headerText = "Issues found: {$issuesCount}";
        } else {
            $boxClass = 'border-danger';
            $headerIcon = themeLink('img/false.png', true);
            $headerText = "Issues found: {$issuesCount}";
        }
        
        // Build header summary.
        $headerContent = '
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <img src="' . $headerIcon . '" alt="' . ($issuesCount == 0 ? $this->lang['AN24'] : $this->lang['AN23']) . '" 
                     title="' . ($issuesCount == 0 ? $this->lang['AN25'] : $this->lang['AN22']) . '" 
                     class="me-2" /> 
                <strong>' . $headerText . '</strong>
            </div>
            <div>
                <span class="badge bg-secondary">Total Images: ' . $data['total_images'] . '</span>
            </div>
        </div>';
        
        /**
         * Helper function to build tables for each category.
         * Each row shows:
         *  - a thumbnail (50×50 by default)
         *  - additional image info
         *  - occurrence count
         * If width/height in HTML is < 50, display at that smaller size instead.
         */
        $buildTable = function($title, $items) {
            if (!empty($items)) {
                $total = array_sum(array_map(fn($i) => $i['count'], $items));
                $table  = '<h5 class="mt-3">' . $title . ' (' . $total . ')</h5>';
                $table .= '<div class="table-responsive"><table class="table table-sm table-striped">';
                $table .= '<thead class="table-light">
                            <tr>
                                <th style="width:60px;">Thumbnail</th>
                                <th>Image Info</th>
                                <th class="text-center" style="width:80px;">Count</th>
                            </tr>
                           </thead>
                           <tbody>';
    
                foreach ($items as $item) {
                    // Determine final displayed width/height
                    $rawWidth = $item['width'] ?? 'N/A';
                    $rawHeight = $item['height'] ?? 'N/A';
    
                    // Try to parse numeric width/height
                    $imgWidth = (ctype_digit($rawWidth)) ? (int)$rawWidth : 0;
                    $imgHeight = (ctype_digit($rawHeight)) ? (int)$rawHeight : 0;
    
                    // Default to 50px, or use the smaller dimension if < 50
                    $thumbWidth = ($imgWidth > 0 && $imgWidth < 50) ? $imgWidth : 50;
                    $thumbHeight = ($imgHeight > 0 && $imgHeight < 50) ? $imgHeight : 50;
    
                    // Build a small thumbnail image
                    $thumbnail = '<img src="' . htmlspecialchars($item['src']) . '" alt="Image" 
                                  style="width:' . $thumbWidth . 'px; height:' . $thumbHeight . 'px; object-fit:cover;">';
                    
                    // Build detailed information block
                    $info = 'Source: ' . htmlspecialchars($item['src']);
                    if (isset($item['title']) && $item['title'] !== 'N/A') {
                        $info .= '<br>Title: ' . htmlspecialchars($item['title']);
                    }
                    if ($rawWidth !== 'N/A' && $rawHeight !== 'N/A') {
                        $info .= '<br>Dimensions: ' . htmlspecialchars($rawWidth) . ' x ' . htmlspecialchars($rawHeight);
                    }
                    if (isset($item['alt'])) {
                        $info .= '<br>Alt: ' . htmlspecialchars($item['alt']);
                    }
                    if (isset($item['class']) && $item['class'] !== 'N/A') {
                        $info .= '<br>Class: ' . htmlspecialchars($item['class']);
                    }
                    if (isset($item['parentTag'])) {
                        $info .= '<br>Parent: ' . htmlspecialchars($item['parentTag']);
                    }
                    
                    $table .= '<tr>';
                    $table .= '<td>' . $thumbnail . '</td>';
                    $table .= '<td>' . $info . '</td>';
                    $table .= '<td class="text-center">' . $item['count'] . '</td>';
                    $table .= '</tr>';
                }
    
                $table .= '</tbody></table></div>';
                return $table;
            }
            return '<div class="alert alert-info py-2">No data found for ' . $title . '.</div>';
        };
        
        // Build the nav tabs.
        $tabs = '
        <ul class="nav nav-tabs" id="imageAltTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="missing-alt-tab" data-bs-toggle="tab" data-bs-target="#missing-alt" 
                        type="button" role="tab" aria-controls="missing-alt" aria-selected="true">
                    Missing Alt
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="empty-alt-tab" data-bs-toggle="tab" data-bs-target="#empty-alt" 
                        type="button" role="tab" aria-controls="empty-alt" aria-selected="false">
                    Empty Alt
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="short-alt-tab" data-bs-toggle="tab" data-bs-target="#short-alt" 
                        type="button" role="tab" aria-controls="short-alt" aria-selected="false">
                    Short Alt
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="long-alt-tab" data-bs-toggle="tab" data-bs-target="#long-alt" 
                        type="button" role="tab" aria-controls="long-alt" aria-selected="false">
                    Long Alt
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="redundant-alt-tab" data-bs-toggle="tab" data-bs-target="#redundant-alt" 
                        type="button" role="tab" aria-controls="redundant-alt" aria-selected="false">
                    Redundant Alt
                </button>
            </li>
        </ul>';
        
        // Build tab content.
        $tabContent = '<div class="tab-content" id="imageAltTabContent">';
    
        $tabContent .= '<div class="tab-pane fade show active" id="missing-alt" role="tabpanel" aria-labelledby="missing-alt-tab">';
        $tabContent .= $buildTable('Images Missing Alt Attribute', $data['images_missing_alt'] ?? []);
        $tabContent .= '</div>';
        
        $tabContent .= '<div class="tab-pane fade" id="empty-alt" role="tabpanel" aria-labelledby="empty-alt-tab">';
        $tabContent .= $buildTable('Images With Empty Alt Attribute', $data['images_with_empty_alt'] ?? []);
        $tabContent .= '</div>';
        
        $tabContent .= '<div class="tab-pane fade" id="short-alt" role="tabpanel" aria-labelledby="short-alt-tab">';
        $tabContent .= $buildTable('Images With Short Alt Text', $data['images_with_short_alt'] ?? []);
        $tabContent .= '</div>';
        
        $tabContent .= '<div class="tab-pane fade" id="long-alt" role="tabpanel" aria-labelledby="long-alt-tab">';
        $tabContent .= $buildTable('Images With Long Alt Text', $data['images_with_long_alt'] ?? []);
        $tabContent .= '</div>';
        
        $tabContent .= '<div class="tab-pane fade" id="redundant-alt" role="tabpanel" aria-labelledby="redundant-alt-tab">';
        $tabContent .= $buildTable('Images With Redundant Alt Text', $data['images_with_redundant_alt'] ?? []);
        $tabContent .= '</div>';
        
        $tabContent .= '</div>'; // End .tab-content
        
        // Build additional information section below the tabs.
        $additionalInfo = '<div class="mt-4">';
        
        // Display suggestions if available.
        if (!empty($data['suggestions'])) {
            $additionalInfo .= '<div class="mb-3">';
            $additionalInfo .= '<h5>Suggestions</h5>';
            $additionalInfo .= '<ul class="list-group">';
            foreach ($data['suggestions'] as $suggestion) {
                $additionalInfo .= '<li class="list-group-item">' . $suggestion . '</li>';
            }
            $additionalInfo .= '</ul>';
            $additionalInfo .= '</div>';
        }
        
        // Optionally include other additional information from DB.
        if (!empty($data['additional_info'])) {
            $additionalInfo .= '<div class="mb-3">';
            $additionalInfo .= '<h5>Additional Information</h5>';
            $additionalInfo .= '<p>' . nl2br(htmlspecialchars($data['additional_info'])) . '</p>';
            $additionalInfo .= '</div>';
        }
        
        $additionalInfo .= '</div>';
        
        // Wrap everything in a Bootstrap card.
        $output = '<div id="seoBoxImage" class="card ' . $boxClass . ' my-3 shadow-sm">';
        $output .= '<div class="card-header">' . $headerContent . '</div>';
        $output .= '<div class="card-body">' . $tabs . $tabContent . $additionalInfo . '</div>';
        $output .= '</div>';
        
        return $output;
    }
    
            
    
    
    /**
 * Convert a (possibly) relative URL to an absolute URL based on the current domain.
 *
 * @param string $url   The image path or URL found in the HTML.
 * @return string       The absolute URL.
 */
private function toAbsoluteUrl(string $url): string
{
    // If empty or already starts with http:// or https:// or data:image,
    // we consider it already "absolute" enough.
    if (empty($url) || preg_match('#^(?:https?:)?//#i', $url) || preg_match('#^data:image#i', $url)) {
        return $url;
    }

    // If your domain property does not include scheme, prepend https:// or http://
    // e.g., $this->domainStr might be "example.com" -> "https://example.com"
    $domain = $this->domainStr;
    if (!preg_match('#^https?://#i', $domain)) {
        $domain = 'https://' . $domain;
    }

    // Combine them, ensuring we don’t double-slash
    return rtrim($domain, '/') . '/' . ltrim($url, '/');
}


    /**
     * Returns the position of the given node among its siblings.
     * (This method remains for calculating node position numbers.)
     *
     * @param DOMNode $node
     * @return int
     */
    private function getNodePosition(DOMNode $node): int {
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
    private function getStopWords(): array {
        return [
            "a","about","above","after","again","against","all","am","an","and","any","are","aren't",
            "as","at","be","because","been","before","being","below","between","both","but","by","can",
            "can't","cannot","could","couldn't","did","didn't","do","does","doesn't","doing","don't","down",
            "during","each","few","for","from","further","had","hadn't","has","hasn't","have","haven't",
            "having","he","he'd","he'll","he's","her","here","here's","hers","herself","him","himself",
            "his","how","how's","i","i'd","i'll","i'm","i've","if","in","into","is","isn't","it","it's",
            "its","itself","let's","me","more","most","mustn't","my","myself","no","nor","not","of","off",
            "on","once","only","or","other","ought","our","ours","ourselves","out","over","own","same",
            "shan't","she","she'd","she'll","she's","should","shouldn't","so","some","such","than","that",
            "that's","the","their","theirs","them","themselves","then","there","there's","these","they",
            "they'd","they'll","they're","they've","this","those","through","to","too","under","until","up",
            "very","was","wasn't","we","we'd","we'll","we're","we've","were","weren't","what","what's",
            "when","when's","where","where's","which","while","who","who's","whom","why","why's","with",
            "won't","would","wouldn't","you","you'd","you'll","you're","you've","your","yours","yourself",
            "yourselves"
        ];
    }

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

    private function detectOveruse(array $data): array {
        $overused = [];
        foreach ($data as $d) {
            if ($d['count'] > 5 || $d['density'] > 5) {
                $overused[] = $d['phrase'];
            }
        }
        return $overused;
    }

    private function generateKeywordCloud(DOMDocument $dom): array {
        $html = $dom->saveHTML();
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);
        $html = preg_replace('/<!--(.*?)-->/', '', $html);
        $html = html_entity_decode($html, ENT_QUOTES|ENT_HTML5, 'UTF-8');
        $textContent = strip_tags($html);
        $textContent = preg_replace('/&[A-Za-z0-9#]+;/', ' ', $textContent);
        $textContent = strtolower($textContent);
        $textContent = preg_replace('/[^a-z\s]/', ' ', $textContent);
        $textContent = preg_replace('/\s+/', ' ', $textContent);
        $textContent = trim($textContent);
        $words = explode(' ', $textContent);
        $words = array_filter($words);
        $stopWords = $this->getStopWords();
        $filtered = array_filter($words, function($w) use ($stopWords) {
            return !in_array($w, $stopWords) && strlen($w) > 2;
        });
        $filtered = array_values($filtered);
        $unigrams = $filtered;
        $bigrams = [];
        for ($i = 0; $i < count($filtered) - 1; $i++) {
            $bigrams[] = $filtered[$i] . ' ' . $filtered[$i + 1];
        }
        $trigrams = [];
        for ($i = 0; $i < count($filtered) - 2; $i++) {
            $trigrams[] = $filtered[$i] . ' ' . $filtered[$i + 1] . ' ' . $filtered[$i + 2];
        }
        $uniCloud = $this->buildFrequencyData($unigrams);
        $biCloud  = $this->buildFrequencyData($bigrams);
        $triCloud = $this->buildFrequencyData($trigrams);
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

    public function processKeyCloud() {
        $dom = $this->getDom();
        $cloudData = $this->generateKeywordCloud($dom);
        $unigrams = $cloudData['unigrams'];
        $keyDataHtml = '';
        $outArr = [];
        $keyCount = 0;
        $maxKeywords = 15;
        foreach ($unigrams as $data) {
            if ($keyCount >= $maxKeywords) break;
            $keyword = $data['phrase'];
            $outArr[] = [$keyword, $data['count'], $data['density']];
            $keyDataHtml .= '<li><span class="keyword">' . htmlspecialchars($keyword) . '</span><span class="number">' . $data['count'] . '</span></li>';
            $keyCount++;
        }
        $outCount = count($outArr);
        $updateStr = jsonEncode([
            'keyCloudData' => $outArr,
            'keyDataHtml'  => $keyDataHtml,
            'outCount'     => $outCount,
            'fullCloud'    => $cloudData
        ]);
        updateToDbPrepared($this->con, 'domains_data', ['keywords_cloud' => $updateStr], ['domain' => $this->domainStr]);
        return [
            'keyCloudData' => $outArr,
            'keyDataHtml'  => $keyDataHtml,
            'outCount'     => $outCount,
            'fullCloud'    => $cloudData,
        ];
    }

    public function showKeyCloud($data) {
        $outCount = $data['outCount'];
        $keyDataHtml = $data['keyDataHtml'];
        $keycloudClass = 'lowImpactBox';
        $keyMsg = $this->lang['AN179'];
        if (!isset($_SESSION['twebUsername']) && !isAllowedStats($this->con, 'seoBox7')) {
            die($this->seoBoxLogin);
        }
        $output = '<div class="">
                        <div class="msgBox padRight10 bottom5">';
        $output .= ($outCount != 0) ? '<ul class="keywordsTags">' . $keyDataHtml . '</ul>' : ' ' . $this->lang['AN29'];
        $output .= '</div></div>';
        return $output;
    }

    public function processKeyConsistency($keyCloudData, $metaData, $headings) {
        $result = [];
        foreach ($keyCloudData as $item) {
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
            $result[] = [
                'keyword'     => $keyword,
                'count'       => $item[1],
                'title'       => $inTitle,
                'description' => $inDesc,
                'heading'     => $inHeading
            ];
        }
        $resultJson = jsonEncode($result);
        updateToDbPrepared($this->con, 'domains_data', ['key_consistency' => jsonEncode($resultJson)], ['domain' => $this->domainStr]);
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
                   </div>';
        return $output;
    }

   /**
 * Displays keyword consistency data in tabs for Trigrams, Bigrams, and Unigrams.
 * Also adds a suggestion area below the tabs.
 *
 * @param array $fullCloud The full keyword cloud data.
 * @param array $metaData  The meta data array.
 * @param array $headings  The headings array.
 * @return string The formatted HTML output.
 */
public function showKeyConsistencyNgramsTabs($fullCloud, $metaData, $headings) {
    // Set icons for true/false results.
    $this->true  = '<i class="fa fa-check text-success"></i>';
    $this->false = '<i class="fa fa-times text-danger"></i>';
    
    // Extract each n-gram type.
    $unigrams = $fullCloud['unigrams'] ?? [];
    $bigrams  = $fullCloud['bigrams'] ?? [];
    $trigrams = $fullCloud['trigrams'] ?? [];
    
    // Build individual tables.
    $trigramTable = $this->buildConsistencyTable('Trigrams', $trigrams, $metaData, $headings);
    $bigramTable  = $this->buildConsistencyTable('Bigrams', $bigrams, $metaData, $headings);
    $unigramTable = $this->buildConsistencyTable('Unigrams', $unigrams, $metaData, $headings);
    
    // Construct the tab layout (using simple Bootstrap buttons for tabs, not colourfully styled).
    $output = <<<HTML
<div class="keyword-consistency container-fluid"> 
    <ul class="nav nav-tabs" id="consistencyTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="trigrams-tab" data-bs-toggle="tab" data-bs-target="#trigrams-pane" type="button" role="tab" aria-controls="trigrams-pane" aria-selected="true">
                Trigrams
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="bigrams-tab" data-bs-toggle="tab" data-bs-target="#bigrams-pane" type="button" role="tab" aria-controls="bigrams-pane" aria-selected="false">
                Bigrams
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="unigrams-tab" data-bs-toggle="tab" data-bs-target="#unigrams-pane" type="button" role="tab" aria-controls="unigrams-pane" aria-selected="false">
                Unigrams
            </button>
        </li>
    </ul>
    <div class="tab-content" id="consistencyTabsContent">
        <div class="tab-pane fade show active" id="trigrams-pane" role="tabpanel" aria-labelledby="trigrams-tab">
            {$trigramTable}
        </div>
        <div class="tab-pane fade" id="bigrams-pane" role="tabpanel" aria-labelledby="bigrams-tab">
            {$bigramTable}
        </div>
        <div class="tab-pane fade" id="unigrams-pane" role="tabpanel" aria-labelledby="unigrams-tab">
            {$unigramTable}
        </div>
    </div>
    <div class="mt-3">
      <div class="alert alert-secondary text-center" role="alert">
        <strong>Suggestion:</strong> Review your keyword usage and consistency in your title, description, and headings.
      </div>
    </div>
</div>
HTML;
    return $output;
}

/**
 * Builds a Bootstrap-styled table for keyword consistency data.
 *
 * Rows beyond the first 5 are hidden by default (using Bootstrap's d-none).
 * "Show More" and "Show Less" buttons are added in a centered div.
 *
 * @param string $label    The label for the table (e.g., "Trigrams").
 * @param array  $ngrams   The n-gram data array.
 * @param array  $metaData The meta data for checking keyword presence.
 * @param array  $headings The headings data.
 * @return string The formatted table HTML.
 */
private function buildConsistencyTable($label, $ngrams, $metaData, $headings) {
    // Determine suffix for class names based on label.
    $suffix = '';
    if (strcasecmp($label, 'Trigrams') === 0) {
        $suffix = 'Trigrams';
    } elseif (strcasecmp($label, 'Bigrams') === 0) {
        $suffix = 'Bigrams';
    } elseif (strcasecmp($label, 'Unigrams') === 0) {
        $suffix = 'Unigrams';
    }

    $rows = '';
    $hideCount = 1;

    // Build table rows.
    foreach ($ngrams as $item) {
        $phrase  = $item['phrase'];
        $count   = $item['count'];
        $density = $item['density'] ?? 0;  // Stored in DB

        // Determine if the phrase exists in title, description, or any heading.
        $inTitle   = (stripos($metaData['title'] ?? '', $phrase) !== false);
        $inDesc    = (stripos($metaData['description'] ?? '', $phrase) !== false);
        $inHeading = false;
        foreach ($headings as $tag => $texts) {
            foreach ($texts as $text) {
                if (stripos($text, $phrase) !== false) {
                    $inHeading = true;
                    break 2;
                }
            }
        }

        // Hide rows after the first five.
        $hideClass = ($hideCount > 20) ? 'd-none hideTr hideTr' . $suffix : '';

        // Create the table row.
        $rows .= '<tr class="' . $hideClass . '">
                    <td class="text-start">' . htmlspecialchars($phrase) . '</td>
                    <td>' . $count . '</td>
                    <td>' . $density . '%</td>
                    <td>' . ($inTitle ? $this->true : $this->false) . '</td>
                    <td>' . ($inDesc ? $this->true : $this->false) . '</td>
                    <td>' . ($inHeading ? $this->true : $this->false) . '</td>
                  </tr>';
        $hideCount++;
    }

    // Define class names for the show/hide buttons.
    $showMoreClass = 'showMore' . $suffix;
    $showLessClass = 'showLess' . $suffix;

    // Build the complete table.
    $output = '<div class="passedBox">
        <div class="msgBox">
            <h4 class="mb-3">' . $label . '</h4>
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle text-center">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 35%;">Keywords</th>
                            <th style="width: 10%;">Freq</th>
                            <th style="width: 10%;">Density</th>
                            <th style="width: 10%;">Title</th>
                            <th style="width: 10%;">Desc</th>
                            <th style="width: 10%;">&lt;H&gt;</th>
                        </tr>
                    </thead>
                    <tbody>' . $rows . '</tbody>
                </table>
            </div>';

    // Add Show More / Show Less buttons if needed.
    if ($hideCount > 6) {
        $output .= '<div class="mt-2 text-center">
            <button type="button" class="' . $showMoreClass . ' btn btn-outline-secondary btn-sm">
                Show More <i class="fa fa-angle-double-down"></i>
            </button>
            <button type="button" class="' . $showLessClass . ' btn btn-outline-secondary btn-sm d-none">
                Show Less
            </button>
        </div>';
    }

    $output .= '</div></div>';
    return $output;
}

 
    

    /*===================================================================
     * TEXT RATIO HANDLER
     *=================================================================== 
     */
    public function processTextRatio() {
        $url = $this->scheme . "://" . $this->host;
        $textRatioData = $this->calculateTextHtmlRatioExtended($url);
        $textRatio = $textRatioData['text_html_ratio'];
        $textRatioJson = jsonEncode($textRatio);
        updateToDbPrepared($this->con, 'domains_data', ['ratio_data' => $textRatioJson], ['domain' => $this->domainStr]);
        return $textRatioJson;
    }

    public function showTextRatio($textRatio): string {
        $data = jsonDecode($textRatio);
        if (!is_array($data)) {
            return '<div class="alert alert-danger">' . htmlspecialchars($data) . '</div>';
        }
    
        // Extract main metrics from $data
        $ratio         = $data['ratio_percent']        ?? 0;
        $htmlSize      = $data['html_size_bytes']      ?? 0;
        $textSize      = $data['text_size_bytes']      ?? 0;
        $ratioCat      = $data['ratio_category']       ?? 'N/A';
        $wordCount     = $data['word_count']           ?? 0;
        $readTime      = $data['estimated_reading_time'] ?? 0;
        $loadTime      = $data['load_time_seconds']    ?? 0;
        $totalTags     = $data['total_html_tags']      ?? 0;
        $totalLinks    = $data['total_links']          ?? 0;
        $totalImages   = $data['total_images']         ?? 0;
        $totalScripts  = $data['total_scripts']        ?? 0;
        $totalStyles   = $data['total_styles']         ?? 0;
        $httpCode      = $data['http_response_code']   ?? 0;
    
        // Determine the bootstrap alert class based on ratio
        $textClass = ($ratio < 2) ? 'alert-danger' : (($ratio < 10) ? 'alert-warning' : 'alert-success');
    
        // Build the main table
        $table = '
        <div class="table-responsive">
          <table class="table table-bordered table-striped mb-0">
              <thead class="table-light">
                  <tr>
                      <th>Metric</th>
                      <th>Value</th>
                      <th>Description</th>
                  </tr>
              </thead>
              <tbody>
                  <tr>
                      <td>HTML Size (bytes)</td>
                      <td>' . formatBytes($htmlSize) . '</td>
                      <td>Total size of the HTML source.</td>
                  </tr>
                  <tr>
                      <td>Text Size (bytes)</td>
                      <td>' . formatBytes($textSize) . '</td>
                      <td>Total size of visible text.</td>
                  </tr>
                  <tr>
                      <td>Text Ratio (%)</td>
                      <td>' . $ratio . '%</td>
                      <td>Percentage of text compared to total HTML.</td>
                  </tr>
                  <tr>
                      <td>Ratio Category</td>
                      <td>' . $ratioCat . '</td>
                      <td>HTML-heavy, Balanced, or Text-heavy.</td>
                  </tr>
                  <tr>
                      <td>Word Count</td>
                      <td>' . $wordCount . '</td>
                      <td>Total number of words in visible text.</td>
                  </tr>
                  <tr>
                      <td>Estimated Reading Time</td>
                      <td>' . $readTime . ' min</td>
                      <td>Approximate time to read the page.</td>
                  </tr>
                  <tr>
                      <td>Load Time</td>
                      <td>' . $loadTime . ' sec</td>
                      <td>Time taken to fetch the HTML.</td>
                  </tr>
                  <tr>
                      <td>Total HTML Tags</td>
                      <td>' . $totalTags . '</td>
                      <td>Count of all HTML tags.</td>
                  </tr>
                  <tr>
                      <td>Total Links</td>
                      <td>' . $totalLinks . '</td>
                      <td>Number of hyperlink tags.</td>
                  </tr>
                  <tr>
                      <td>Total Images</td>
                      <td>' . $totalImages . '</td>
                      <td>Number of image tags.</td>
                  </tr>
                  <tr>
                      <td>Total Scripts</td>
                      <td>' . $totalScripts . '</td>
                      <td>Number of script tags.</td>
                  </tr>
                  <tr>
                      <td>Total Styles</td>
                      <td>' . $totalStyles . '</td>
                      <td>Number of style tags.</td>
                  </tr>
                  <tr>
                      <td>HTTP Response Code</td>
                      <td>' . $httpCode . '</td>
                      <td>Status code received when fetching the page.</td>
                  </tr>
              </tbody>
          </table>
        </div>';
    
        // Build suggestions based on the data
        $suggestions = [];
        
        // 1) Ratio-based suggestions
        if ($ratio < 2) {
            $suggestions[] = 'Your text-to-HTML ratio is extremely low. Consider adding more textual content or removing excessive markup.';
        } elseif ($ratio < 10) {
            $suggestions[] = 'Your text-to-HTML ratio is somewhat low. Aim for at least 15–20% by adding relevant text or optimizing code.';
        } else {
            $suggestions[] = 'Great job! Your text-to-HTML ratio seems healthy. Just keep an eye on future changes.';
        }
    
        // 2) If totalImages is large
        if ($totalImages > 50) {
            $suggestions[] = 'You have a high number of images (' . $totalImages . '). Consider compressing or lazy-loading images for better performance.';
        }
    
        // 3) If totalScripts is large
        if ($totalScripts > 20) {
            $suggestions[] = 'You have ' . $totalScripts . ' script tags. Combining or minifying scripts can improve load time.';
        }
    
        // 4) If word count is very low
        if ($wordCount < 200) {
            $suggestions[] = 'Your page has a low word count. Adding more relevant, high-quality content can help user engagement and SEO.';
        }
    
        // 5) If the HTTP code indicates an issue
        if ($httpCode >= 400) {
            $suggestions[] = 'Your page returned an HTTP error code (' . $httpCode . '). Ensure the page is accessible and functioning correctly.';
        }
    
        // Build the suggestions UI
        $suggestionHtml = '';
        if (!empty($suggestions)) {
            $suggestionHtml .= '<div class="alert alert-info mt-3"><strong>Suggestions:</strong><ul class="mb-0">';
            foreach ($suggestions as $sug) {
                $suggestionHtml .= '<li>' . htmlspecialchars($sug) . '</li>';
            }
            $suggestionHtml .= '</ul></div>';
        }
    
        // Build the final output inside a Bootstrap card.
        $output = '
        <div id="ajaxTextRatio" class="card ' . $textClass . ' mb-3">
            <div class="card-header">
                <h4>' . $this->lang['AN36'] . ': <strong>' . $ratio . '%</strong> (' . $ratioCat . ')</h4>
            </div>
            <div class="card-body">
                <p class="mb-3">
                    A low text ratio indicates that your page is heavy on HTML relative to visible text.
                    Below are some details and suggestions based on our analysis.
                </p>
                ' . $table . '
                ' . $suggestionHtml . '
            </div>
            <div class="card-footer">
                <small>Consider optimizing your page by reducing unnecessary markup or increasing quality textual content.</small>
            </div>
        </div>';
    
        return $output;
    }
    
    

    /**
     * Calculates extended text-to-HTML ratio metrics for the given URL.
     *
     * @param string $url The URL to analyze.
     * @return array An associative array under the key 'text_html_ratio' with all metrics.
     */
    private function calculateTextHtmlRatioExtended(string $url): array {
        $start = microtime(true);
        $html = robustFetchHtml($url);
        $loadTime = microtime(true) - $start;
        if (!$html) {
            return ['text_html_ratio' => "Error: couldn't fetch HTML."];
        }
        $htmlSize = strlen($html);
        $plainText = strip_tags($html);
        $textSize = strlen($plainText);
        $ratio = ($htmlSize > 0) ? ($textSize / $htmlSize) * 100 : 0;
        $wordCount = str_word_count($plainText);
        $readTime = round($wordCount / 200);
        $cat = 'Text-heavy';
        if ($ratio < 10) {
            $cat = 'HTML-heavy';
        } elseif ($ratio <= 50) {
            $cat = 'Balanced';
        }
        preg_match_all('/<([a-z][a-z0-9]*)\b[^>]*>/i', $html, $tagMatches);
        $tagCount = count($tagMatches[1]);
        preg_match_all('/<a\s+(?:[^>]*?\s+)?href=[\'"]([^\'"]+)[\'"]/i', $html, $linkMatches);
        $linkCount = count($linkMatches[1]);
        preg_match_all('/<img\b[^>]*>/i', $html, $imgMatches);
        $imageCount = count($imgMatches[0]);
        preg_match_all('/<script\b[^>]*>/i', $html, $scriptMatches);
        $scriptCount = count($scriptMatches[0]);
        preg_match_all('/<style\b[^>]*>/i', $html, $styleMatches);
        $styleCount = count($styleMatches[0]);
        $httpCode = $this->getHttpResponseCode($url);
        return [
            'text_html_ratio' => [
                'html_size_bytes'      => $htmlSize,
                'text_size_bytes'      => $textSize,
                'ratio_percent'        => round($ratio, 2),
                'ratio_category'       => $cat,
                'word_count'           => $wordCount,
                'estimated_reading_time' => $readTime,
                'load_time_seconds'    => round($loadTime, 2),
                'total_html_tags'      => $tagCount,
                'total_links'          => $linkCount,
                'total_images'         => $imageCount,
                'total_scripts'        => $scriptCount,
                'total_styles'         => $styleCount,
                'http_response_code'   => $httpCode,
            ]
        ];
    }

    /**
     * Get HTTP response code for a given URL.
     *
     * @param string $url The URL to check.
     * @return int The HTTP response code, or 0 on failure.
     */
    private function getHttpResponseCode(string $url): int {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $httpCode ? (int)$httpCode : 0;
    }

    /*===================================================================
     * GZIP COMPRESSION HANDLER
     *=================================================================== 
     */
    public function processGzip() {
        $outData = compressionTest($this->urlParse['host']);
        $header = 'Data!';
        $body = (trim($outData[5]) == "") ? 'Data!' : 'Data!';
        $outData = jsonEncode([$outData[0], $outData[1], $outData[2], $outData[3], $header, $body]);
        updateToDbPrepared($this->con, 'domains_data', ['gzip' => $outData], ['domain' => $this->domainStr]);
        return $outData;
    }

    public function showGzip($outData)
    {
    $outData = jsonDecode($outData);

    // Ensure that $outData is an array and has at least 4 items.
    if (!is_array($outData) || count($outData) < 4) {
        return '<div class="errorBox">Invalid Gzip data.</div>';
    }

    // Extract values from the array.
    $compressedSize = (int)$outData[0];
    $originalSize   = (int)$outData[1];
    $isCompressed   = $outData[2];
    $fallbackSize   = (int)$outData[3]; // used if compression didn't work

    // Avoid division by zero. If original size is 0, we set percentage to 0.
    if ($originalSize === 0) {
        $percentage = 0;
    } else {
        $percentage = round((($originalSize - $compressedSize) / $originalSize) * 100, 1);
    }

    // Build the output message based on whether compression was successful.
    if ($isCompressed) {
        $gzipClass = 'passedBox';
        $gzipHead  = $this->lang['AN42'];
        $gzipBody  = '<img src="' . themeLink('img/true.png', true) . '" alt="True" /> '
                   . str_replace(
                        ['[total-size]', '[compressed-size]', '[percentage]'],
                        [size_as_kb($originalSize), size_as_kb($compressedSize), $percentage],
                        $this->lang['AN41']
                     );
    } else {
        $gzipClass = 'errorBox';
        $gzipHead  = $this->lang['AN43'];
        $gzipBody  = '<img src="' . themeLink('img/false.png', true) . '" alt="False" /> '
                   . str_replace(
                        ['[total-size]', '[compressed-size]', '[percentage]'],
                        [size_as_kb($originalSize), size_as_kb($fallbackSize), $percentage],
                        $this->lang['AN44']
                     );
    }

    $output = '<div class="' . $gzipClass . '">
                   <div class="msgBox">' . $gzipHead . '<br />
                       <div class="altImgGroup">' . $gzipBody . '</div><br />
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
        $updateStr = jsonEncode([$data1, $data2]);
        updateToDbPrepared($this->con, 'domains_data', ['resolve' => $updateStr], ['domain' => $this->domainStr]);
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
        $updateStr = jsonEncode([$hostIP, $tType, $this->urlParse['host'], $redirectURLhost]);
        updateToDbPrepared($this->con, 'domains_data', ['ip_can' => $updateStr], ['domain' => $this->domainStr]);
        return $updateStr;
    }

    public function showIPCanonicalization($ipData) {
        $ipData = jsonDecode($ipData);
        if ($this->urlParse['host'] == $ipData['redirectURLhost']) {
            $ipClass = 'passedBox';
            $ipMsg = str_replace(['[ip]', '[host]'], [$ipData['hostIP'], $this->urlParse['host']], $this->lang['AN50']);
        } else {
            $ipClass = 'improveBox';
            $ipMsg = str_replace(['[ip]', '[host]'], [$ipData['hostIP'], $this->urlParse['host']], $this->lang['AN49']);
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
    public function processInPageLinks() {
        // Use the pre-loaded DOMDocument if available.
        if (isset($this->dom) && $this->dom instanceof DOMDocument) {
            $doc = $this->dom;
        } else {
            $doc = new DOMDocument();
            @$doc->loadHTML(mb_convert_encoding($this->html, 'HTML-ENTITIES', 'UTF-8'));
            $this->dom = $doc;
        }
        
        $internalLinks = [];
        $externalLinks = [];
        $uniqueLinkSet = [];
        $externalDomainSet = [];
        
        // Initialize counters.
        $totalTargetBlank = 0;
        $totalHttps = 0;
        $totalHttp = 0;
        $totalTracking = 0;
        $totalTextLength = 0;
        $totalImageLinks = 0;
        $totalNoFollow = 0;
        $totalDoFollow = 0;
        // New counter for empty anchor text links.
        $totalEmptyLinks = 0;
        
        // We still count links by position for any potential use, but won't include detailed array.
        $linksByPosition = [
            'header'  => 0,
            'nav'     => 0,
            'main'    => 0,
            'footer'  => 0,
            'aside'   => 0,
            'section' => 0,
            'body'    => 0,
        ];
        
        // Base URL & host.
        $baseUrl = $this->scheme . "://" . $this->host;
        $myHost = strtolower($this->urlParse['host']);
         
        $anchors = $doc->getElementsByTagName('a');
        foreach ($anchors as $a) {
            $rawHref = trim($a->getAttribute('href'));
            if ($rawHref === "" || $rawHref === "#") {
                continue;
            }
            
            // Normalize the URL.
            $href = $rawHref;
            $this->scheme = $this->scheme;
            $this->host = $this->host;
            $baseUrl = $this->scheme . "://" . $this->host;
            
            $rel = strtolower($a->getAttribute('rel'));
            $target = strtolower($a->getAttribute('target'));
            $anchorText = trim(strip_tags($a->textContent));
            // Count empty anchor text if no text is present.
            if ($anchorText === "") {
                $totalEmptyLinks++;
            }
            
            // Determine follow type.
            if (strpos($rel, 'nofollow') !== false) {
                $followType = 'nofollow';
                $totalNoFollow++;
            } else {
                $followType = 'dofollow';
                $totalDoFollow++;
            }
            
            if ($target === '_blank') {
                $totalTargetBlank++;
            }
            
            // Parse URL parts.
            $parsed = parse_url($href);
            if ($parsed === false) {
                continue;
            }
            if (isset($parsed['scheme'])) {
                $scheme = strtolower($parsed['scheme']);
                if ($scheme === 'https') {
                    $totalHttps++;
                } elseif ($scheme === 'http') {
                    $totalHttp++;
                }
            }
            if (isset($parsed['query']) && stripos($parsed['query'], 'utm_') !== false) {
                $totalTracking++;
            }
            
            // Check if the anchor encloses an image.
            $hasImage = false;
            foreach ($a->childNodes as $child) {
                if ($child->nodeType === XML_ELEMENT_NODE && strtolower($child->nodeName) === 'img') {
                    $hasImage = true;
                    break;
                }
            }
            if ($hasImage) {
                $totalImageLinks++;
                $linkType = 'image';
            } else {
                $linkType = 'text';
                $totalTextLength += mb_strlen($anchorText);
            }
            
            // Determine position using our private method.
            $position = $this->getLinkPosition($a->parentNode);
            if (isset($linksByPosition[$position])) {
                $linksByPosition[$position]++;
            } else {
                $linksByPosition['body']++;
            }
            
            // Determine internal vs. external.
            $isInternal = false;
            if (!empty($parsed['host'])) {
                $linkHost = strtolower($parsed['host']);
                if ($linkHost === $myHost || $linkHost === "www.$myHost") {
                    $isInternal = true;
                }
            } else {
                $isInternal = true;
            }
            if ($isInternal) {
                $internalLinks[] = $href;
            } else {
                $externalLinks[] = [
                    'href'      => $href,
                    'follow_type' => $followType,
                    'target'    => $target,
                    'innertext' => $anchorText,
                    'rel'       => $rel
                ];
                if (isset($parsed['host'])) {
                    $externalDomainSet[strtolower($parsed['host'])] = true;
                }
            }
            
            // Build unique link set.
            $uniqueLinkSet[$href] = true;
        }
        
        // Totals and derived metrics.
        $totalLinks = count($internalLinks) + count($externalLinks);
        $totalInternalLinks = count($internalLinks);
        $totalExternalLinks = count($externalLinks);
        $uniqueLinksCount = count($uniqueLinkSet);
        $uniqueExternalDomainsCount = count($externalDomainSet);
        $totalNonTracking = $totalLinks - $totalTracking;
        
        $percentageNoFollow = ($totalLinks > 0) ? round(($totalNoFollow / $totalLinks) * 100, 2) : 0;
        $percentageDoFollow = ($totalLinks > 0) ? round(($totalDoFollow / $totalLinks) * 100, 2) : 0;
        
        $textLinkCount = count($internalLinks);
        $averageAnchorTextLength = ($textLinkCount > 0) ? round($totalTextLength / $textLinkCount, 2) : 0;
        
        $linkDiversityScore = ($totalLinks > 0) ? round($uniqueLinksCount / $totalLinks, 2) : 0;
        
        $externalDomains = array_keys($externalDomainSet);
        
        // Include the empty links count in our update array.
        $updateStr = json_encode( [
            'total_links'                   => $totalLinks,
            'total_internal_links'          => $totalInternalLinks,
            'total_external_links'          => $totalExternalLinks,
            'unique_links_count'            => $uniqueLinksCount,
            'total_nofollow_links'          => $totalNoFollow,
            'total_dofollow_links'          => $totalDoFollow,
            'percentage_nofollow_links'     => $percentageNoFollow,
            'percentage_dofollow_links'     => $percentageDoFollow,
            'total_target_blank_links'      => $totalTargetBlank,
            // 'links_by_position' removed.
            'total_image_links'             => $totalImageLinks,
            'total_text_links'              => $textLinkCount,
            'total_empty_links'             => $totalEmptyLinks,   // NEW: Count of empty anchor text links.
            'external_domains'              => $externalDomains,
            'unique_external_domains_count' => $uniqueExternalDomainsCount,
            'total_https_links'             => $totalHttps,
            'total_http_links'              => $totalHttp,
            'total_tracking_links'          => $totalTracking,
            'total_non_tracking_links'      => $totalNonTracking,
            'average_anchor_text_length'    => $averageAnchorTextLength,
            'link_diversity_score'          => $linkDiversityScore,
            // Optionally, you may include external links detailed list if required:
            'external_links'                => $externalLinks
        ]);

        updateToDbPrepared($this->con, 'domains_data', ['links_analyser' => $updateStr], ['domain' => $this->domainStr]);
        
        return $updateStr;
    }
    
    public function showInPageLinks($linksData) {
        // 1) Decode if $linksData is a JSON string.
        if (is_string($linksData)) {
            $linksData = json_decode($linksData, true);
        }
        if (!is_array($linksData)) {
            return '<div class="alert alert-danger">Invalid link data provided.</div>';
        }
        
        // 2) Extract the main fields.
        $totalLinks                 = $linksData['total_links'] ?? 0;
        $totalInternalLinks         = $linksData['total_internal_links'] ?? 0;
        $totalExternalLinks         = $linksData['total_external_links'] ?? 0;
        $uniqueLinksCount           = $linksData['unique_links_count'] ?? 0;
        $totalNofollowLinks         = $linksData['total_nofollow_links'] ?? 0;
        $totalDofollowLinks         = $linksData['total_dofollow_links'] ?? 0;
        $percentageNofollowLinks    = $linksData['percentage_nofollow_links'] ?? 0;
        $percentageDofollowLinks    = $linksData['percentage_dofollow_links'] ?? 0;
        $totalTargetBlankLinks      = $linksData['total_target_blank_links'] ?? 0;
        $totalImageLinks            = $linksData['total_image_links'] ?? 0;
        $totalTextLinks             = $linksData['total_text_links'] ?? 0;
        $totalEmptyLinks            = $linksData['total_empty_links'] ?? 0;
        $externalDomains            = $linksData['external_domains'] ?? [];
        $uniqueExternalDomainsCount = $linksData['unique_external_domains_count'] ?? 0;
        $totalHttpsLinks            = $linksData['total_https_links'] ?? 0;
        $totalHttpLinks             = $linksData['total_http_links'] ?? 0;
        $totalTrackingLinks         = $linksData['total_tracking_links'] ?? 0;
        $totalNonTrackingLinks      = $linksData['total_non_tracking_links'] ?? 0;
        $averageAnchorTextLength    = $linksData['average_anchor_text_length'] ?? 0;
        $linkDiversityScore         = $linksData['link_diversity_score'] ?? 0;
        
        // 3) Process external links: group duplicates and count them.
        $externalLinks = $linksData['external_links'] ?? [];
        $uniqueExternalLinks = [];
        foreach ($externalLinks as $ext) {
            $href = $ext['href'];
            if (!isset($uniqueExternalLinks[$href])) {
                $uniqueExternalLinks[$href] = $ext;
                $uniqueExternalLinks[$href]['count'] = 1;
            } else {
                $uniqueExternalLinks[$href]['count']++;
            }
        }
        
        // 4) Build the output using Bootstrap components.
        $html = '<div class="container my-4">';
        
        // Nav Tabs (kept simple to match your theme)
        $html .= '
        <ul class="nav nav-tabs" id="linkReportTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="summary-tab" data-bs-toggle="tab" data-bs-target="#summaryTab" type="button" role="tab" aria-controls="summaryTab" aria-selected="true">
              Summary
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="externalLinks-tab" data-bs-toggle="tab" data-bs-target="#externalLinksTab" type="button" role="tab" aria-controls="externalLinksTab" aria-selected="false">
              External Links
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="externalDomains-tab" data-bs-toggle="tab" data-bs-target="#externalDomainsTab" type="button" role="tab" aria-controls="externalDomainsTab" aria-selected="false">
              External Domains
            </button>
          </li>
        </ul>';
        
        // Tab content container
        $html .= '<div class="tab-content" id="linkReportTabsContent">';
        
        // Tab 1: Summary
        $html .= '
        <div class="tab-pane fade show active" id="summaryTab" role="tabpanel" aria-labelledby="summary-tab">
          <div class="card my-3">
            <div class="card-header">
              <h5>Link Summary</h5>
            </div>
            <div class="card-body">
              <div class="row">
                <div class="col-md-6">
                  <table class="table table-sm mb-0">
                    <tbody>
                      <tr><th>Total Links</th><td>' . $totalLinks . '</td></tr>
                      <tr><th>Internal Links</th><td>' . $totalInternalLinks . '</td></tr>
                      <tr><th>External Links</th><td>' . $totalExternalLinks . '</td></tr>
                      <tr><th>Unique Links</th><td>' . $uniqueLinksCount . '</td></tr>
                      <tr><th>Empty Links</th><td>' . $totalEmptyLinks . '</td></tr>
                      <tr><th>Link Diversity Score</th><td>' . $linkDiversityScore . '</td></tr>
                    </tbody>
                  </table>
                </div>
                <div class="col-md-6">
                  <table class="table table-sm mb-0">
                    <tbody>
                      <tr><th>Dofollow Links</th><td>' . $totalDofollowLinks . ' (' . $percentageDofollowLinks . '%)</td></tr>
                      <tr><th>Nofollow Links</th><td>' . $totalNofollowLinks . ' (' . $percentageNofollowLinks . '%)</td></tr>
                      <tr><th>Target Blank Links</th><td>' . $totalTargetBlankLinks . '</td></tr>
                      <tr><th>HTTPS Links</th><td>' . $totalHttpsLinks . '</td></tr>
                      <tr><th>HTTP Links</th><td>' . $totalHttpLinks . '</td></tr>
                    </tbody>
                  </table>
                </div>
              </div>
              <div class="row mt-3">
                <div class="col-md-6">
                  <table class="table table-sm mb-0">
                    <tbody>
                      <tr><th>Total Image Links</th><td>' . $totalImageLinks . '</td></tr>
                      <tr><th>Total Text Links</th><td>' . $totalTextLinks . '</td></tr>
                      <tr><th>Avg. Anchor Text Length</th><td>' . $averageAnchorTextLength . '</td></tr>
                    </tbody>
                  </table>
                </div>
                <div class="col-md-6">
                  <table class="table table-sm mb-0">
                    <tbody>
                      <tr><th>Total Tracking Links</th><td>' . $totalTrackingLinks . '</td></tr>
                      <tr><th>Non-Tracking Links</th><td>' . $totalNonTrackingLinks . '</td></tr>
                      <tr><th>Unique External Domains</th><td>' . $uniqueExternalDomainsCount . '</td></tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div><!-- card-body -->
          </div><!-- card -->
        </div><!-- tab-pane -->';
        
        // Tab 2: External Links
        $html .= '
        <div class="tab-pane fade" id="externalLinksTab" role="tabpanel" aria-labelledby="externalLinks-tab">
          <div class="card my-3">
            <div class="card-header">
              <h5>Unique External Links</h5>
            </div>
            <div class="card-body">';
        if (!empty($uniqueExternalLinks)) {
            $html .= '
              <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                  <thead class="table-dark">
                    <tr>
                      <th>Link</th>
                      <th>Follow Type</th>
                      <th>Anchor Text</th>
                      <th>Count</th>
                    </tr>
                  </thead>
                  <tbody>';
            foreach ($uniqueExternalLinks as $ext) {
                $displayText = !empty($ext['innertext']) ? $ext['innertext'] : $ext['href'];
                $html .= '
                    <tr>
                      <td>' . htmlspecialchars($ext['href']) . '</td>
                      <td>' . htmlspecialchars($ext['follow_type']) . '</td>
                      <td>' . htmlspecialchars($displayText) . '</td>
                      <td>' . $ext['count'] . '</td>
                    </tr>';
            }
            $html .= '
                  </tbody>
                </table>
              </div><!-- table-responsive -->';
        } else {
            $html .= '<div class="alert alert-info">No external links found.</div>';
        }
            $html .= '
            </div><!-- card-body -->
          </div><!-- card -->
        </div><!-- tab-pane -->';
        
        // Tab 3: External Domains
        $html .= '
        <div class="tab-pane fade" id="externalDomainsTab" role="tabpanel" aria-labelledby="externalDomains-tab">
          <div class="card my-3">
            <div class="card-header">
              <h5>External Domains</h5>
            </div>
            <div class="card-body">';
        if (!empty($externalDomains)) {
            $html .= '<ul class="list-group">';
            foreach ($externalDomains as $domain) {
                $html .= '<li class="list-group-item">' . htmlspecialchars($domain) . '</li>';
            }
            $html .= '</ul>';
        } else {
            $html .= '<div class="alert alert-info">No external domains found.</div>';
        }
            $html .= '
            </div><!-- card-body -->
          </div><!-- card -->
        </div><!-- tab-pane -->';
        
        // Close tab content and container.
        $html .= '</div><!-- tab-content -->';
        
        // Suggestion section below the tabs
        $html .= '
        <div class="mt-3">
          <div class="alert alert-secondary text-center" role="alert">
            <strong>Suggestion:</strong> Review your internal and external link structure to ensure optimal SEO performance.
          </div>
        </div>';
        
        $html .= '</div><!-- container -->';
        
        return $html;
    }
    
    


    
    
    
    /*===================================================================
     * BROKEN LINKS HANDLER
     *=================================================================== 
     */
    public function processBrokenLinks() {
        $linksData = $this->processInPageLinks();
        $brokenLinks = [];
        $brokenLinks[] = "Goes here";
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
                        <div class="msgBox">' . $brokenMsg . '<br /><br />' .
                        (count($brokenLinks) > 0 ? '<table class="table table-responsive"><tbody>' . $rows . '</tbody></table>' : '') .
                        '</div>
                        <div class="seoBox14 suggestionBox">' . $this->lang['AN186'] . '</div>
                   </div>';
        return $output;
    }

    /*===================================================================
     * ROBOTS.TXT HANDLER
     *=================================================================== 
     */
    public function processRobots() {
        $robotLink = $this->scheme . "://" . $this->host . '/robots.txt';
        $httpCode = getHttpCode($robotLink);
        updateToDbPrepared($this->con, 'domains_data', ['robots' => jsonEncode($httpCode)], ['domain' => $this->domainStr]);
        return ['robotLink' => $robotLink, 'httpCode' => $httpCode];
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
        $sitemapInfo = jsonEncode(getSitemapInfo($this->scheme . "://" . $this->host));
        updateToDbPrepared($this->con, 'domains_data', ['sitemap' => $sitemapInfo], ['domain' => $this->domainStr]);
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
        $doc = $this->getDom();
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
        updateToDbPrepared($this->con, 'domains_data', ['embedded' => $embeddedCheck], ['domain' => $this->domainStr]);
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
        $doc = $this->getDom();
        $iframes = $doc->getElementsByTagName('iframe');
        foreach ($iframes as $iframe) {
            $iframeCheck = true;
            break;
        }
        updateToDbPrepared($this->con, 'domains_data', ['iframe' => $iframeCheck], ['domain' => $this->domainStr]);
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
/**
 * Retrieves RDAP (WHOIS) data for a given domain.
 *
 * Tries to fetch RDAP data from rdap.org. If that fails, falls back to using the PHP-WHOIS library.
 *
 * @param string $domain The domain name.
 * @return array An associative array of WHOIS data or an error message.
 */
private function fetchDomainRdap(string $domain): array {
    $rdapUrl = "https://rdap.org/domain/" . $domain;
    try {
        $response = @file_get_contents($rdapUrl);
        if ($response === false) {
            return $this->fallbackWhois($domain);
        }
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => "RDAP response JSON error: " . json_last_error_msg()];
        }
        return $data;
    } catch (\Exception $e) {
        return $this->fallbackWhois($domain);
    }
}

/**
 * Fallback method to retrieve WHOIS data using the io-developer/php-whois library.
 *
 * @param string $domain The domain name.
 * @return array An array containing WHOIS data or an error message.
 */
private function fallbackWhois(string $domain): array {
    try {
        $domain = preg_replace('/^www\./i', '', $domain);
        $info = \Iodev\Whois\Factory::get()->createWhois()->loadDomainInfo($domain);
        if ($info) {
            $rawText = $info->getResponse()->getText();
            return ['raw_data' => $rawText];
        }
        return ['error' => "No WHOIS data available using php-whois fallback."];
    } catch (\Exception $e) {
        return ['error' => "Exception in php-whois fallback: " . $e->getMessage()];
    }
}



/**
 * Retrieves DNS records for the given domain.
 *
 * This method queries multiple DNS record types (A, AAAA, MX, NS, TXT, and CAA if available)
 * and returns them as an array.
 *
 * @param string $domain The domain to check.
 * @return array An array of DNS records.
 */
private function checkDNSRecords(string $domain): array {
    $recordTypes = [DNS_A, DNS_AAAA, DNS_MX, DNS_NS, DNS_TXT];
    if (defined('DNS_CAA')) {
        $recordTypes[] = DNS_CAA;
    }
    $records = [];
    foreach ($recordTypes as $type) {
        $result = @dns_get_record($domain, $type);
        if ($result !== false && !empty($result)) {
            $records = array_merge($records, $result);
        }
    }
    return $records;
}



public function showWhois($whois_data)
{
    // Step 1: Decode the JSON from the DB
    $decoded = jsonDecode($whois_data);

    // If decoding fails or returns null, just display as-is:
    if ($decoded === null) {
        $safeOutput = nl2br(htmlspecialchars($whois_data));
        return '<div class="whois-output" style="background:#f8f9fa; padding:15px; border:1px solid #ddd;">'
             . $safeOutput
             . '</div>';
    }

    // Step 2: Check what type we got:
    if (is_string($decoded)) {
        // If it's just a string, show it with line breaks
        $formatted = nl2br(htmlspecialchars($decoded));
    }
    elseif (is_array($decoded)) {

        // CASE A: If the fallback WHOIS gave us an array with "raw_data"
        if (isset($decoded['raw_data']) && is_string($decoded['raw_data'])) {
            // Show the raw_data with newlines:
            $raw = nl2br(htmlspecialchars($decoded['raw_data']));
            $formatted = "<strong>WHOIS Raw Data:</strong><br>{$raw}";
        }
        // CASE B: If it’s an RDAP array or something else, fallback to your recursive formatting
        else {
            $formatted = self::formatWhoisData($decoded);
        }
    }
    else {
        // If it’s neither string nor array, just do a safe print
        $formatted = nl2br(htmlspecialchars(print_r($decoded, true)));
    }

    // Step 3: Wrap in a nice container
    return '<div class="whois-output" style="background:#f8f9fa; padding:15px; border:1px solid #ddd;">'
         . $formatted
         . '</div>';
}

 /*===================================================================
     * Site Cards Handler
     *=================================================================== 
     */
    public function processSiteCards(): string {
        // Get the DOM from your stored HTML
        $doc = $this->getDom();
        $metaTags = $doc->getElementsByTagName('meta');
        
        // Initialize an array for different card types.
        $cards = [
            'facebook'  => [],
            'x'         => [], // formerly twitter
            'linkedin'  => [],
            'discord'   => [],
            'pinterest' => [],
            'whatsapp'  => [],
            'google'    => []
        ];
        
        // Iterate through all meta tags
        foreach ($metaTags as $tag) {
            // Process tags that use "property" (typically Open Graph)
            if ($tag->hasAttribute('property')) {
                $property = $tag->getAttribute('property');
                $content  = $tag->getAttribute('content');
                if (strpos($property, 'og:') === 0) {
                    // These tags are used for Facebook, LinkedIn, Pinterest, WhatsApp, and Discord
                    $cards['facebook'][$property]  = $content;
                    $cards['linkedin'][$property]  = $content;
                    $cards['pinterest'][$property] = $content;
                    $cards['whatsapp'][$property]  = $content;
                    $cards['discord'][$property]   = $content;
                }
            }
            // Process tags that use "name"
            if ($tag->hasAttribute('name')) {
                $name = $tag->getAttribute('name');
                $content  = $tag->getAttribute('content');
                if (strpos($name, 'twitter:') === 0) {
                    // Assign twitter meta tags to the 'x' key
                    $cards['x'][$name] = $content;
                }
                if (strpos($name, 'google:') === 0) {
                    $cards['google'][$name] = $content;
                }
            }
        }
        
        // Ensure that keys expected in the preview arrays exist
        if (!isset($cards['x']['twitter:url'])) {
            $cards['x']['twitter:url'] = '';
        }
        if (!isset($cards['google']['google:url'])) {
            $cards['google']['google:url'] = '';
        }
        
        // JSON encode the results.
        $jsonData = jsonEncode($cards);
        
        // Update the sitecards field in your domains_data table.
        updateToDbPrepared($this->con, 'domains_data', ['sitecards' => $jsonData], ['domain' => $this->domainStr]);
        
        return $jsonData;
    }
    

    
    public function showCards($cardsData): string
    {
        $data = jsonDecode($cardsData);
      
        if (!is_array($data)) {
            return '<div class="alert alert-warning">No card data available.</div>';
        }
    
        // Define platforms with labels, required tags, preview keys, and data.
        $cardTypes = [
            'facebook' => [
                'label'    => 'FACEBOOK',
                'required' => ['og:title', 'og:description', 'og:image', 'og:url', 'og:type'],
                'preview'  => ['og:title', 'og:description', 'og:image', 'og:url'],
                'data'     => $data['facebook'] ?? []
            ],
            'x' => [
                'label'    => 'X (FORMERLY TWITTER)',
                'required' => ['twitter:card', 'twitter:title', 'twitter:description', 'twitter:image'],
                'preview'  => ['twitter:title', 'twitter:description', 'twitter:image', 'twitter:url'],
                'data'     => $data['x'] ?? []
            ],
            'linkedin' => [
                'label'    => 'LINKEDIN',
                'required' => ['og:title', 'og:description', 'og:image', 'og:url'],
                'preview'  => ['og:title', 'og:description', 'og:image', 'og:url'],
                'data'     => $data['linkedin'] ?? []
            ],
            'discord' => [
                'label'    => 'DISCORD',
                'required' => ['og:title', 'og:description', 'og:image', 'og:url'],
                'preview'  => ['og:title', 'og:description', 'og:image', 'og:url'],
                'data'     => $data['discord'] ?? []
            ],
            'pinterest' => [
                'label'    => 'PINTEREST',
                'required' => ['og:title', 'og:description', 'og:image', 'og:url'],
                'preview'  => ['og:title', 'og:description', 'og:image', 'og:url'],
                'data'     => $data['pinterest'] ?? []
            ],
            'whatsapp' => [
                'label'    => 'WHATSAPP',
                'required' => ['og:title', 'og:description', 'og:image', 'og:url'],
                'preview'  => ['og:title', 'og:description', 'og:image', 'og:url'],
                'data'     => $data['whatsapp'] ?? []
            ],
            'google' => [
                'label'    => 'GOOGLE',
                'required' => ['google:title', 'google:description', 'google:image'],
                'preview'  => ['google:title', 'google:description', 'google:image', 'google:url'],
                'data'     => $data['google'] ?? []
            ],
        ];
    
        // Build the tab navigation using Bootstrap nav-tabs.
        $tabsNav = '<ul class="nav nav-tabs" id="cardsTab" role="tablist">';
        $tabsContent = '';
        $i = 0;
    
        foreach ($cardTypes as $key => $card) {
            $activeClass = ($i === 0) ? 'active' : '';
            $selected    = ($i === 0) ? 'true' : 'false';
            $tabsNav .= '
                <li class="nav-item" role="presentation">
                    <button class="nav-link ' . $activeClass . '" id="' . $key . '-tab"
                            data-bs-toggle="tab" data-bs-target="#' . $key . '" type="button"
                            role="tab" aria-controls="' . $key . '" aria-selected="' . $selected . '">
                        ' . $card['label'] . '
                    </button>
                </li>';
    
            // Extract preview data from the card.
            list($titleKey, $descKey, $imgKey, $urlKey) = $card['preview'];
            $cData = $card['data'];
            $title = $cData[$titleKey] ?? '';
            $desc  = $cData[$descKey] ?? '';
            $image = $cData[$imgKey] ?? '';
            $url   = $cData[$urlKey] ?? '';
    
            // Parse domain from URL if available.
            $domain = '';
            if (!empty($url)) {
                $parsed = parse_url($url);
                $domain = $parsed['host'] ?? '';
            }
    
            // Build preview HTML via platform-specific method.
            $previewHtml = $this->buildPlatformPreview($key, $title, $desc, $image, $domain);
    
            // Build meta tags table.
            $missing = [];
            $tableHtml = '<div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead class="table-light">
                        <tr><th style="width: 40%;">Meta Tag</th><th style="width: 60%;">Value</th></tr>
                    </thead>
                    <tbody>';
            foreach ($card['required'] as $tag) {
                $value = $cData[$tag] ?? '';
                if (empty($value)) {
                    $tableHtml .= '
                        <tr>
                            <td>' . htmlspecialchars($tag) . '</td>
                            <td><span class="text-danger">Missing</span></td>
                        </tr>';
                    $missing[] = $tag;
                } else {
                    $tableHtml .= '
                        <tr>
                            <td>' . htmlspecialchars($tag) . '</td>
                            <td>' . htmlspecialchars($value) . '</td>
                        </tr>';
                }
            }
            $tableHtml .= '</tbody></table></div>';
    
            // Build suggestions alert.
            $suggestions = '';
            if (!empty($missing)) {
                $suggestions = '
                    <div class="alert alert-warning p-2 small mt-3">
                        <strong>Suggestion:</strong> Missing meta tags: ' . implode(', ', $missing) . '.
                    </div>';
            } else {
                $suggestions = '
                    <div class="alert alert-success p-2 small mt-3">
                        All required meta tags are present.
                    </div>';
            }
    
            // Combine preview and table in a row layout.
            $fullContent = '
                <div class="row">
                    <div class="col-md-5 mb-3">' . $previewHtml . '</div>
                    <div class="col-md-7 mb-3">' . $tableHtml . $suggestions . '</div>
                </div>';
    
            // Build tab pane.
            $tabsContent .= '
                <div class="tab-pane fade ' . ($i === 0 ? 'show active' : '') . '" id="' . $key . '"
                     role="tabpanel" aria-labelledby="' . $key . '-tab">
                    ' . $fullContent . '
                </div>';
            $i++;
        }
    
        $tabsNav .= '</ul>';
    
        // Wrap everything in a Bootstrap card.
        $output = '<div class="card my-3 shadow-sm">
                      <div class="card-header"><strong>Social Cards Overview</strong></div>
                      <div class="card-body">'
                          . $tabsNav .
                          '<div class="tab-content mt-3" id="cardsTabContent">' . $tabsContent . '</div>
                      </div>
                  </div>';
    
        return $output;
    }
    
    /**
     * buildPlatformPreview()
     *
     * Calls the platform-specific preview builder based on $platform.
     *
     * @param string $platform  (facebook, x, linkedin, discord, pinterest, whatsapp, google)
     * @param string $title
     * @param string $desc
     * @param string $image
     * @param string $domain
     * @return string HTML preview
     */
    private function buildPlatformPreview(string $platform, string $title, string $desc, string $image, string $domain): string
    {
        $hasPreview = (!empty($title) || !empty($desc) || !empty($image));
        switch ($platform) {
            case 'facebook':
                return $this->buildFacebookStyle($title, $desc, $image, $domain, $hasPreview);
            case 'x':
                return $this->buildXStyle($title, $desc, $image, $domain, $hasPreview);
            case 'linkedin':
                return $this->buildLinkedInStyle($title, $desc, $image, $domain, $hasPreview);
            case 'discord':
                return $this->buildDiscordStyle($title, $desc, $image, $domain, $hasPreview);
            case 'pinterest':
                return $this->buildPinterestStyle($title, $desc, $image, $domain, $hasPreview);
            case 'whatsapp':
                return $this->buildWhatsAppStyle($title, $desc, $image, $domain, $hasPreview);
            case 'google':
                return $this->buildGoogleStyle($title, $desc, $image, $domain, $hasPreview);
            default:
                return $this->buildGenericStyle($title, $desc, $image, $domain, $hasPreview);
        }
    }
    
    /* --- Platform-specific preview builders --- */
    
    private function buildFacebookStyle(string $title, string $desc, string $image, string $domain, bool $hasPreview): string
    {
        if (!$hasPreview) {
            return '<div class="alert alert-info p-2">No preview available for Facebook.</div>';
        }
        return '
        <div class="fb-preview-card">
          <div class="fpc-domain">' . htmlspecialchars($domain ?: 'facebook.com') . '</div>
          <div class="fpc-image">' . ($image ? '<img src="' . htmlspecialchars($image) . '" alt="">' : '') . '</div>
          <div class="fpc-content">
            <div class="fpc-title">' . htmlspecialchars($title) . '</div>
            <div class="fpc-desc">' . htmlspecialchars($desc) . '</div>
          </div>
        </div>';
    }
    
    private function buildXStyle(string $title, string $desc, string $image, string $domain, bool $hasPreview): string
    {
        if (!$hasPreview) {
            return '<div class="alert alert-info p-2">No preview available for X.</div>';
        }
        return '
        <div class="x-preview-card">
          <div class="x-image">' . ($image ? '<img src="' . htmlspecialchars($image) . '" alt="">' : '') . '</div>
          <div class="x-content">
            <div class="x-domain">' . htmlspecialchars($domain ?: 'x.com') . '</div>
            <div class="x-title">' . htmlspecialchars($title) . '</div>
            <div class="x-desc">' . htmlspecialchars($desc) . '</div>
          </div>
        </div>';
    }
    
    private function buildLinkedInStyle(string $title, string $desc, string $image, string $domain, bool $hasPreview): string
    {
        if (!$hasPreview) {
            return '<div class="alert alert-info p-2">No preview available for LinkedIn.</div>';
        }
        return '
        <div class="li-preview-card">
          <div class="li-image">' . ($image ? '<img src="' . htmlspecialchars($image) . '" alt="">' : '') . '</div>
          <div class="li-domain">' . strtoupper(htmlspecialchars($domain ?: 'linkedin.com')) . '</div>
          <div class="li-title">' . htmlspecialchars($title) . '</div>
          <div class="li-desc">' . htmlspecialchars($desc) . '</div>
        </div>';
    }
    
    private function buildDiscordStyle(string $title, string $desc, string $image, string $domain, bool $hasPreview): string
    {
        if (!$hasPreview) {
            return '<div class="alert alert-info p-2">No preview available for Discord.</div>';
        }
        return '
        <div class="dc-preview-card">
          <div class="dc-heading">' . htmlspecialchars($domain ?: 'discord.com') . '</div>
          <div class="dc-title">' . htmlspecialchars($title) . '</div>
          <div class="dc-desc">' . htmlspecialchars($desc) . '</div>
          ' . ($image ? '<div class="dc-image"><img src="' . htmlspecialchars($image) . '" alt=""></div>' : '') . '
        </div>';
    }
    
    private function buildPinterestStyle(string $title, string $desc, string $image, string $domain, bool $hasPreview): string
    {
        if (!$hasPreview) {
            return '<div class="alert alert-info p-2">No preview available for Pinterest.</div>';
        }
        return '
        <div class="pin-preview-card">
          <div class="pin-image">' . ($image ? '<img src="' . htmlspecialchars($image) . '" alt="">' : '') . '</div>
          <div class="pin-content">
            <div class="pin-title">' . htmlspecialchars($title) . '</div>
            <div class="pin-desc">' . htmlspecialchars($desc) . '</div>
            <div class="pin-domain">' . htmlspecialchars($domain ?: 'pinterest.com') . '</div>
          </div>
        </div>';
    }
    
    private function buildWhatsAppStyle(string $title, string $desc, string $image, string $domain, bool $hasPreview): string
    {
        if (!$hasPreview) {
            return '<div class="alert alert-info p-2">No preview available for WhatsApp.</div>';
        }
        return '
        <div class="wa-preview-card">
          ' . ($image ? '<div class="wa-image"><img src="' . htmlspecialchars($image) . '" alt=""></div>' : '') . '
          <div class="wa-content">
            <div class="wa-title">' . htmlspecialchars($title) . '</div>
            <div class="wa-desc">' . htmlspecialchars($desc) . '</div>
            <div class="wa-domain">' . htmlspecialchars($domain ?: 'whatsapp.com') . '</div>
          </div>
        </div>';
    }
    
    private function buildGoogleStyle(string $title, string $desc, string $image, string $domain, bool $hasPreview): string
    {
        if (!$hasPreview) {
            return '<div class="alert alert-info p-2">No preview available for Google.</div>';
        }
        return '
        <div class="g-preview-card">
          <div class="g-title">' . htmlspecialchars($title) . '</div>
          <div class="g-desc">' . htmlspecialchars($desc) . '</div>
          ' . ($image ? '<div class="g-image"><img src="' . htmlspecialchars($image) . '" alt=""></div>' : '') . '
          <div class="g-domain">' . htmlspecialchars($domain ?: 'google.com') . '</div>
        </div>';
    }
    
    private function buildGenericStyle(string $title, string $desc, string $image, string $domain, bool $hasPreview): string
    {
        if (!$hasPreview) {
            return '<div class="alert alert-info p-2">No preview available.</div>';
        }
        return '
        <div class="generic-preview-card">
          <div class="gp-domain">' . htmlspecialchars($domain ?: 'example.com') . '</div>
          ' . ($image ? '<div class="gp-image"><img src="' . htmlspecialchars($image) . '" alt=""></div>' : '') . '
          <div class="gp-title">' . htmlspecialchars($title) . '</div>
          <div class="gp-desc">' . htmlspecialchars($desc) . '</div>
        </div>';
    }
    

    
    
    

    /*===================================================================
     * MOBILE FRIENDLINESS HANDLER
     *=================================================================== 
     */
    public function processMobileCheck() {
        $jsonData = getMobileFriendly($this->scheme . "://" . $this->host);
        $updateStr = jsonEncode($jsonData);
        updateToDbPrepared($this->con, 'domains_data', ['mobile_fri' => $updateStr], ['domain' => $this->domainStr]);
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
        $doc = $this->getDom();
        $mobileComCheck = false;
        $elements = array_merge(
            iterator_to_array($doc->getElementsByTagName('iframe')),
            iterator_to_array($doc->getElementsByTagName('object')),
            iterator_to_array($doc->getElementsByTagName('embed'))
        );
        foreach ($elements as $el) {
            if ($el) { $mobileComCheck = true; break; }
        }
        updateToDbPrepared($this->con, 'domains_data', ['mobile_com' => $mobileComCheck], ['domain' => $this->domainStr]);
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
    public function processUrlLength() {
        $host = $this->urlParse['host'];
        $hostParts = explode('.', $host);
        $length = strlen($hostParts[0]);
        $fullUrl = $this->scheme . "://" . $this->host;
        return [
            'hostWord' => $hostParts[0],
            'length'   => $length,
            'fullUrl'  => $fullUrl
        ];
    }

    public function showUrlLength($data) {
        $urlLengthClass = ($data['length'] < 15) ? 'passedBox' : 'errorBox';
        $urlLengthMsg = $data['fullUrl'] . '<br>' . str_replace('[count]', $data['length'], $this->lang['AN122']);
        $favIconMsg = '<img src="https://www.google.com/s2/favicons?domain=' . $data['fullUrl'] . '" alt="FavIcon" />  ' . $this->lang['AN123'];
        $output = '<div class="' . $urlLengthClass . '">
                        <div class="msgBox">' . $urlLengthMsg . '<br /><br /></div>
                        <div class="seoBox26 suggestionBox">' . $this->lang['AN198'] . '</div>
                   </div>' . $this->sepUnique .
                   '<div class="lowImpactBox">
                        <div class="msgBox">' . $favIconMsg . '<br /><br /></div>
                        <div class="seoBox27 suggestionBox">' . $this->lang['AN199'] . '</div>
                   </div>';
        return $output;
    }

    /*===================================================================
     * CUSTOM 404 PAGE HANDLER
     *=================================================================== 
     */
    public function processErrorPage() {
        $url = $this->scheme . "://" . $this->host . '/404error-test-page-by-atoz-seo-tools';
        $pageSize = strlen(curlGET($url));
        updateToDbPrepared($this->con, 'domains_data', ['404_page' => jsonEncode($pageSize)], ['domain' => $this->domainStr]);
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
        $url = $this->scheme . "://" . $this->host;
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
        $langCode = null;
        if (preg_match('#<html[^>]+lang=[\'"]?(.*?)[\'"]?#is', $htmlContent, $matches)) {
            $langCode = trim(mb_substr($matches[1], 0, 5));
        } elseif (preg_match('#<meta[^>]+http-equiv=[\'"]?content-language[\'"]?[^>]+content=[\'"]?(.*?)[\'"]?#is', $htmlContent, $matches)) {
            $langCode = trim(mb_substr($matches[1], 0, 5));
        }
        $updateStr = jsonEncode(['timeTaken' => $timeTaken, 'dataSize' => $dataSize, 'langCode' => $langCode]);
        updateToDbPrepared($this->con, 'domains_data', ['load_time' => $updateStr], ['domain' => $this->domainStr]);
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
    // public function processAvailabilityChecker() {
    //     $path = LIB_DIR . 'domainAvailabilityservers.tdata';
    //     $serverList = [];
    //     if (file_exists($path)) {
    //         $contents = file_get_contents($path);
    //         $serverList = json_decode($contents, true);
    //     }
    //     $tldCodes = ['com','net','org','biz','us','info','eu'];
    //     $domainWord = explode('.', $this->urlParse['host']);
    //     $hostTLD = trim(end($domainWord));
    //     $domainWord = $domainWord[0];
    //     $doArr = $tyArr = [];
    //     $tldCount = 0;
    //     foreach ($tldCodes as $tldCode) {
    //         if ($tldCount == 5)
    //             break;
    //         if ($tldCode != $hostTLD) {
    //             $topDomain = $domainWord . '.' . $tldCode;
    //             $domainAvailabilityChecker = new domainAvailability($serverList);
    //             $domainAvailabilityStats = $domainAvailabilityChecker->isAvailable($topDomain);
    //             $doArr[] = [$topDomain, $domainAvailabilityStats];
    //             $tldCount++;
    //         }
    //     }
    //     $typo = new typos();
    //     $domainTypoWords = $typo->get($domainWord);
    //     $typoCount = 0;
    //     foreach ($domainTypoWords as $word) {
    //         if ($typoCount == 5)
    //             break;
    //         $topDomain = $word . '.' . $hostTLD;
    //         $domainAvailabilityChecker = new domainAvailability($serverList);
    //         $domainAvailabilityStats = $domainAvailabilityChecker->isAvailable($topDomain);
    //         $tyArr[] = [$topDomain, $domainAvailabilityStats];
    //         $typoCount++;
    //     }
    //     $updateStr = jsonEncode(['doArr' => $doArr, 'tyArr' => $tyArr]);
    //     updateToDbPrepared($this->con, 'domains_data', ['domain_typo' => $updateStr], ['domain' => $this->domainStr]);
    //     return $updateStr;
    // }

    // public function showAvailabilityChecker($availabilityData) {
    //     $availabilityData = jsonDecode($availabilityData);
    //     $domainMsg = '';
    //     foreach ($availabilityData['doArr'] as $item) {
    //         $domainMsg .= '<tr><td>' . $item[0] . '</td><td>' . $item[1] . '</td></tr>';
    //     }
    //     $typoMsg = '';
    //     foreach ($availabilityData['tyArr'] as $item) {
    //         $typoMsg .= '<tr><td>' . $item[0] . '</td><td>' . $item[1] . '</td></tr>';
    //     }
    //     $seoBox32 = '<div class="lowImpactBox">
    //                     <div class="msgBox">
    //                         <table class="table table-hover table-bordered table-striped">
    //                             <tbody>
    //                                 <tr><th>' . $this->lang['AN134'] . '</th><th>' . $this->lang['AN135'] . '</th></tr>' . $domainMsg . '
    //                             </tbody>
    //                         </table>
    //                         <br />
    //                     </div>
    //                     <div class="seoBox32 suggestionBox">' . $this->lang['AN204'] . '</div>
    //                  </div>';
    //     $seoBox33 = '<div class="lowImpactBox">
    //                     <div class="msgBox">
    //                         <table class="table table-hover table-bordered table-striped">
    //                             <tbody>
    //                                 <tr><th>' . $this->lang['AN134'] . '</th><th>' . $this->lang['AN135'] . '</th></tr>' . $typoMsg . '
    //                             </tbody>
    //                         </table>
    //                         <br />
    //                     </div>
    //                     <div class="seoBox33 suggestionBox">' . $this->lang['AN205'] . '</div>
    //                  </div>';
    //     return $seoBox32 . $this->sepUnique . $seoBox33;
    // }

    /*===================================================================
     * EMAIL PRIVACY HANDLER
     *=================================================================== 
     */
    public function processEmailPrivacy() {
        preg_match_all("/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6})/", $this->html, $matches, PREG_SET_ORDER);
        $emailCount = count($matches);
        updateToDbPrepared($this->con, 'domains_data', ['email_privacy' => $emailCount], ['domain' => $this->domainStr]);
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
        updateToDbPrepared($this->con, 'domains_data', ['safe_bro' => $safeBrowsingStats], ['domain' => $this->domainStr]);
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
    /**
 * Processes and saves the server information into the DB field "server_loc".
 *
 * This method gathers:
 *   1. DNS records (A, MX, NS, TXT, etc.)
 *   2. Server IP, plus location, ISP, and server signature (from HTTP headers)
 *   3. SSL certificate details
 *   4. Technology used (from HTTP headers such as Server or X-Powered-By)
 *   5. Whois information
 *
 * It then encodes all this info as JSON and stores it.
 *
 * @return string JSON encoded server information.
 */
public function processServerInfo(): string {
    // Validate that URL components exist
    $scheme = $this->scheme ?? 'http';
    $host = $this->urlParse['host'] ?? '';
    if ($host === '') {
        throw new Exception("Cannot process server info because the host is missing in the URL.");
    }
    $fullUrl = $scheme . "://" . $host;
    $serverIP = gethostbyname($host);
    $dnsRecords = $this->checkDNSRecords($host);
    // checkIP() now returns both IPv4/IPv6 and also geo data (and an "ip_history" key if desired)
    $ipInfo = $this->checkIP($host);
    $headers = @get_headers($fullUrl, 1) ?: [];
    $serverSignature = isset($headers['Server']) ? $headers['Server'] : 'N/A';
    $sslInfo = $this->checkSSL($host);
    $technologyUsed = $this->detectFromHtml($this->html, $headers, $host);
    $whoisInfo = $this->fetchDomainRdap($host);
    $serverInfo = [
        'dns_records'      => $dnsRecords,
        'server_ip'        => $serverIP,
        'ip_info'          => $ipInfo,
        'server_signature' => $serverSignature,
        'ssl_info'         => $sslInfo,
        'technology_used'  => $technologyUsed,
        'whois_info'       => $whoisInfo
    ];
    $updateStr = json_encode($serverInfo);
    updateToDbPrepared($this->con, 'domains_data', ['server_loc' => $updateStr], ['domain' => $this->domainStr]);
    return $updateStr;
}

private function getIpInfo(string $ip): array
{
    // You can use any external IP info API (ip-api, ipinfo, etc.) or your own logic.
    // This is just a fallback dummy.
    // If you do call an external API, be sure to handle timeouts or errors gracefully.
    return [
        'country' => 'Unknown Country',
        'region'  => 'Unknown Region',
        'city'    => 'Unknown City',
        'isp'     => 'Unknown ISP'
    ];
}
/**
 * Retrieves IP information (IPv4, IPv6, and geolocation data) for a given domain.
 *
 * This method first resolves the domain to its IPv4 address and then
 * attempts to get IPv6 addresses. If an IPv4 is found, it uses an external
 * API (ip-api.com) to get geolocation details.
 *
 * @param string $domain The domain name.
 * @return array An associative array with keys 'IPv4', 'IPv6' (if available),
 *               and 'geo' containing geolocation data or error information.
 */
private function checkIP(string $domain): array {
    $ipInfo = [];
    
    // Resolve IPv4 using gethostbyname()
    $ipv4 = gethostbyname($domain);
    if ($ipv4 !== $domain && filter_var($ipv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $ipInfo['IPv4'] = $ipv4;
    }
    
    // Get IPv6 records using dns_get_record()
    $AAAA = @dns_get_record($domain, DNS_AAAA);
    $ipv6Arr = [];
    if (!empty($AAAA)) {
        foreach ($AAAA as $record) {
            if (!empty($record['ipv6'])) {
                $ipv6Arr[] = $record['ipv6'];
            }
        }
        if (!empty($ipv6Arr)) {
            $ipInfo['IPv6'] = $ipv6Arr;
        }
    }
    
    // If we have an IPv4, attempt to get geolocation data using ip-api.com
    if (!empty($ipInfo['IPv4'])) {
        $ip = $ipInfo['IPv4'];
        $apiUrl = "http://ip-api.com/json/{$ip}?fields=status,message,country,regionName,city,zip,isp,org,as,query";
        $response = @file_get_contents($apiUrl);
        if ($response !== false) {
            $data = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($data['status']) && $data['status'] === 'success') {
                $ipInfo['geo'] = [
                    'ip'      => $data['query']      ?? $ip,
                    'country' => $data['country']    ?? '',
                    'region'  => $data['regionName'] ?? '',
                    'city'    => $data['city']       ?? '',
                    'zip'     => $data['zip']        ?? '',
                    'isp'     => $data['isp']        ?? '',
                    'org'     => $data['org']        ?? '',
                    'as'      => $data['as']         ?? ''
                ];
            } else {
                $ipInfo['geo'] = [
                    'error' => isset($data['message']) ? $data['message'] : 'Unknown error'
                ];
            }
        } else {
            $ipInfo['geo'] = [
                'error' => 'Unable to fetch IP geolocation data.'
            ];
        }
        
        // Optionally, add IP history information if available.
        // For now, we'll add a placeholder.
        $ipInfo['ip_history'] = "Not available";
    }
    
    return $ipInfo;
}



/**
 * Checks if the given domain has SSL enabled and retrieves its certificate details.
 *
 * @param string $domain The domain name to check.
 * @return array An associative array with keys:
 *               - 'has_ssl': (bool) whether SSL is available.
 *               - 'ssl_info': (mixed) either the parsed certificate info or an error message.
 */
private function checkSSL(string $domain): array {
    $hasSSL = false;
    // Use cURL to try connecting via HTTPS.
    $url = "https://{$domain}";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    // Enable SSL verification.
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    // Execute the request.
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Consider a response code between 200 and 399 as a successful SSL connection.
    if ($httpCode >= 200 && $httpCode < 400) {
        $hasSSL = true;
    }
    
    // Retrieve SSL certificate info if SSL is active.
    $certInfo = $hasSSL ? $this->getSSLInfo($domain) : "SSL not available or certificate could not be fetched.";
    
    return [
        'has_ssl'   => $hasSSL,
        'ssl_info'  => $certInfo
    ];
}


/**
 * Detects technologies used on the site from its HTML and HTTP headers.
 *
 * @param string $html    The full HTML content.
 * @param array  $headers The HTTP response headers.
 * @param string $domain  The domain name (in case you need to check for domain-specific patterns).
 * @return array An array of detected technology names.
 */
private function detectFromHtml(string $html, array $headers, string $domain): array {
    // A comprehensive list of technologies to check for
    $techPatterns = [
        // Web Servers & Hosting
        'Apache'           => '/apache/i',
        'Nginx'            => '/nginx/i',
        'Microsoft IIS'    => '/iis/i',
        'LiteSpeed'        => '/litespeed/i',
        'Caddy'            => '/caddy/i',
        
        // Programming Languages & Runtimes
        'PHP'              => '/php/i',
        'Node.js'          => '/node\.js/i',
        'Python'           => '/python/i',
        'Ruby'             => '/ruby/i',
        'Java'             => '/java/i',
        '.NET'             => '/\.net|c#|asp\.net/i',
        'Go'               => '/go(lang)?/i',
        
        // Content Management Systems (CMS)
        'WordPress'        => '/wp-content|wp-admin|wordpress/i',
        'Drupal'           => '/drupal/i',
        'Joomla'           => '/joomla/i',
        'Magento'          => '/magento/i',
        'Shopify'          => '/shopify/i',
        'Wix'              => '/wix\.com/i',
        'Squarespace'      => '/squarespace/i',
        
        // Web Frameworks & Libraries
        'Laravel'          => '/laravel/i',
        'Symfony'          => '/symfony/i',
        'CodeIgniter'      => '/codeigniter/i',
        'Express'          => '/express/i',
        'Django'           => '/django/i',
        'Flask'            => '/flask/i',
        'Ruby on Rails'    => '/rails/i',
        'Spring'           => '/spring/i',
        'React'            => '/react/i',
        'Angular'          => '/angular/i',
        'Vue.js'           => '/vue\.js/i',
        
        // Databases (often found in error messages or frameworks)
        'MySQL'            => '/mysql/i',
        'PostgreSQL'       => '/postgresql/i',
        'SQLite'           => '/sqlite/i',
        'MongoDB'          => '/mongodb/i',
        'Redis'            => '/redis/i',
        
        // Caching & Optimization
        'Memcached'        => '/memcached/i',
        'Varnish'          => '/varnish/i',
        'Cloudflare'       => '/cloudflare/i',
        
        // E-commerce Platforms
        'WooCommerce'      => '/woocommerce/i',
        'PrestaShop'       => '/prestashop/i',
        
        // Analytics & Marketing
        'Google Analytics' => '/google-analytics|ga\(/i',
        'Adobe Analytics'  => '/adobe analytics/i',
        'Hotjar'           => '/hotjar/i',
        'Mixpanel'         => '/mixpanel/i',
    ];

    $technologies = [];

    // Check the HTML content for technology patterns.
    foreach ($techPatterns as $tech => $pattern) {
        if (preg_match($pattern, $html)) {
            $technologies[] = $tech;
        }
    }

    // Also check the HTTP headers (e.g., Server, X-Powered-By).
    foreach ($headers as $key => $value) {
        // In case the header contains an array of values
        if (is_array($value)) {
            $value = implode(' ', $value);
        }
        if (stripos($key, 'server') !== false || stripos($key, 'x-powered-by') !== false) {
            foreach ($techPatterns as $tech => $pattern) {
                if (preg_match($pattern, $value)) {
                    $technologies[] = $tech;
                }
            }
        }
    }

    // Remove duplicate entries.
    return array_unique($technologies);
}

private function whoislookup(string $domain): string {
    // Try RDAP first
    $rdapUrl = "https://rdap.org/domain/{$domain}";
    try {
        $response = @file_get_contents($rdapUrl);
        $data = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE && !empty($data)) {
            return print_r($data, true);
        }
    } catch (\Exception $e) {
        // Continue to fallback
    }
    // Fallback to php-whois
    require_once __DIR__ . '/vendor/autoload.php';
    try {
        $whois = \Iodev\Whois\Factory::create()->loadDomainInfo($domain);
        if ($whois) {
            return print_r($whois->getRawData(), true);
        }
    } catch (\Exception $e) {
        return "Error fetching WHOIS via php-whois: " . $e->getMessage();
    }
    return "No WHOIS data available.";
}
/**
 * Helper function to retrieve SSL certificate details for a host.
 *
 * @param string $host The domain name.
 * @return mixed Parsed SSL certificate info as an associative array, or an error string.
 */
private function getSSLInfo(string $host)
{
    // Attempt a socket connection on port 443
    $contextOptions = [
        'ssl' => [
            'capture_peer_cert' => true,
            'verify_peer'       => false,
            'verify_peer_name'  => false,
        ]
    ];
    $context = stream_context_create($contextOptions);

    $client = @stream_socket_client("ssl://{$host}:443", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);
    if (!$client) {
        return "Unable to connect to SSL port: {$errstr}";
    }

    $params = stream_context_get_params($client);
    if (empty($params['options']['ssl']['peer_certificate'])) {
        return "No SSL certificate found.";
    }

    $cert = $params['options']['ssl']['peer_certificate'];
    $sslInfo = openssl_x509_parse($cert);

    // Optionally, parse the data further for clarity
    // e.g. extract subject, issuer, validFrom, validTo, etc.

    return $sslInfo;
}

public static function formatWhoisData($data): string
{
    if (is_array($data)) {
        $html = '<ul>';
        foreach ($data as $key => $value) {
            $html .= '<li><strong>' . htmlspecialchars((string)$key) . ':</strong> '
                   . self::formatWhoisData($value)
                   . '</li>';
        }
        $html .= '</ul>';
        return $html;
    } else {
        // For a string or numeric, show as text
        // Use nl2br so newlines appear
        return nl2br(htmlspecialchars((string)$data));
    }
}


 


/**
 * showServerInfo()
 *
 * Displays the server information stored in the "server_loc" JSON in a tabbed layout.
 * (Tabs: Name Servers, DNS Info, Server Info, SSL Info, Technology, and Whois.)
 *
 * @param string $jsonData JSON-encoded server info from DB
 * @return string HTML output
 */
public function showServerInfo(string $jsonData): string
{
    // Decode the JSON from the database
    $data = json_decode($jsonData, true);
    if (!is_array($data)) {
        return '<div class="alert alert-danger">No server information available.</div>';
    }

    // Helper functions for date formatting & domain age
    $formatDateFriendly = function ($rawDate) {
        $ts = strtotime($rawDate);
        return ($ts !== false)
            ? date('M d, Y H:i:s', $ts)
            : $rawDate; // fallback if strtotime fails
    };

    $computeDomainAge = function ($rawDate) {
        $ts = strtotime($rawDate);
        if ($ts === false) {
            return ['years' => 'N/A', 'days' => 'N/A'];
        }
        $daysDiff = floor((time() - $ts) / 86400);
        $years    = round($daysDiff / 365, 1);
        return [
            'years' => $years,
            'days'  => $daysDiff
        ];
    };

    /*--------------------------------------------------------
     * 1) DNS Info (Accordion)
     *-------------------------------------------------------*/
    $dnsRecords = $data['dns_records'] ?? [];
    $dnsHtml = '<div class="alert alert-info">No DNS records found.</div>';
    if (!empty($dnsRecords)) {
        // Group DNS records by type
        $dnsByType = [];
        foreach ($dnsRecords as $rec) {
            $type = isset($rec['type']) ? strtoupper($rec['type']) : 'UNKNOWN';
            $dnsByType[$type][] = $rec;
        }

        // Build the accordion
        $dnsHtml = '<div class="accordion" id="dnsAccordion">';
        $i = 0;
        foreach ($dnsByType as $type => $records) {
            $i++;
            $collapseId    = "collapseDns{$i}";
            $expanded      = 'false';
            $collapseClass = 'accordion-collapse collapse';
            $buttonClass   = 'accordion-button collapsed';

            $dnsHtml .= '
              <div class="accordion-item">
                <h2 class="accordion-header" id="headingDns' . $i . '">
                  <button class="' . $buttonClass . '" type="button" data-bs-toggle="collapse"
                          data-bs-target="#' . $collapseId . '" aria-expanded="' . $expanded . '"
                          aria-controls="' . $collapseId . '">
                    ' . htmlspecialchars($type) . ' Records (' . count($records) . ')
                  </button>
                </h2>
                <div id="' . $collapseId . '" class="' . $collapseClass . '"
                     aria-labelledby="headingDns' . $i . '" data-bs-parent="#dnsAccordion">
                  <div class="accordion-body">
                    <table class="table table-sm table-bordered">
                      <thead class="table-light">
                        <tr><th>Field</th><th>Value</th></tr>
                      </thead>
                      <tbody>';
            foreach ($records as $rec) {
                foreach ($rec as $fieldKey => $fieldValue) {
                    if (is_array($fieldValue)) {
                        $fieldValue = implode(', ', $fieldValue);
                    }
                    $dnsHtml .= '
                        <tr>
                          <td>' . htmlspecialchars($fieldKey) . '</td>
                          <td>' . htmlspecialchars((string)$fieldValue) . '</td>
                        </tr>';
                }
                // Spacer row
                $dnsHtml .= '<tr><td colspan="2" class="bg-light"></td></tr>';
            }
            $dnsHtml .= '
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>';
        }
        $dnsHtml .= '</div>'; // end accordion
    }

    /*--------------------------------------------------------
     * 2) WHOIS + Domain Info (Name Servers Tab is first)
     *-------------------------------------------------------*/
    $whoisInfo = $data['whois_info'] ?? [];
    $rawWhois  = '';
    if (is_array($whoisInfo) && isset($whoisInfo['raw_data'])) {
        $rawWhois = trim($whoisInfo['raw_data']);
    } elseif (is_string($whoisInfo)) {
        $rawWhois = trim($whoisInfo);
    }

    // Defaults for WHOIS
    $domainName       = $this->urlParse['host'] ?? 'N/A';
    $registrar        = 'N/A';
    $ianaID           = 'N/A';
    $registrarUrl     = 'N/A';
    $whoisServer      = 'N/A';
    $abuseContact     = 'N/A';
    $domainStatus     = 'N/A';
    $createdDate      = 'N/A';
    $expiryDate       = 'N/A';
    $updatedDate      = 'N/A';
    $hostedIP         = $data['server_ip'] ?? 'N/A';
    $nsRecordsParsed  = [];
    $domainAgeYears   = 'N/A';
    $domainAgeDays    = 'N/A';

    if ($rawWhois !== '') {
        $lines = explode("\n", $rawWhois);
        foreach ($lines as $line) {
            $trimLine = trim($line);
            if (preg_match('/^domain name:\s*(.+)$/i', $trimLine, $m)) {
                $domainName = $m[1];
            }
            if (preg_match('/^registrar:\s*(.+)$/i', $trimLine, $m)) {
                $registrar = $m[1];
            }
            if (preg_match('/^registrar iana id:\s*(.+)$/i', $trimLine, $m)) {
                $ianaID = $m[1];
            }
            if (preg_match('/^registrar url:\s*(.+)$/i', $trimLine, $m)) {
                $registrarUrl = $m[1];
            }
            if (preg_match('/^registrar whois server:\s*(.+)$/i', $trimLine, $m)) {
                $whoisServer = $m[1];
            }
            if (preg_match('/^registrar abuse contact email:\s*(.+)$/i', $trimLine, $m)) {
                $abuseContact = $m[1];
            }
            if (preg_match('/^registrar abuse contact phone:\s*(.+)$/i', $trimLine, $m)) {
                $abuseContact .= ' / ' . $m[1];
            }
            if (preg_match('/^domain status:\s*(.+)$/i', $trimLine, $m)) {
                $domainStatus = ($domainStatus === 'N/A') ? $m[1] : $domainStatus . ', ' . $m[1];
            }
            if (preg_match('/(creation date|created on|registered on|registration time|creation time):\s*(.+)/i', $trimLine, $m)) {
                $createdDate = trim($m[2]);
            }
            if (preg_match('/(registry expiry date|expiry date|expiration date):\s*(.+)/i', $trimLine, $m)) {
                $expiryDate = trim($m[2]);
            }
            if (preg_match('/(updated date|last updated on):\s*(.+)/i', $trimLine, $m)) {
                $updatedDate = trim($m[2]);
            }
            if (preg_match('/^name server:\s*(.+)$/i', $trimLine, $m)) {
                $nsRecordsParsed[] = $m[1];
            }
        }
        $age = $computeDomainAge($createdDate);
        $domainAgeYears = $age['years'];
        $domainAgeDays  = $age['days'];
    }

    // Fallback: if WHOIS didn't supply NS records, use DNS-based NS records
    $nsRecordsDNS = array_filter($dnsRecords, function($r) {
        return (isset($r['type']) && strtoupper($r['type']) === 'NS');
    });
    $finalNsList = [];
    if (!empty($nsRecordsDNS)) {
        foreach ($nsRecordsDNS as $r) {
            $finalNsList[] = $r['target'] ?? $r['ip'] ?? 'N/A';
        }
    } elseif (!empty($nsRecordsParsed)) {
        $finalNsList = $nsRecordsParsed;
    }

    $createdDateFriendly = ($createdDate !== 'N/A') ? $formatDateFriendly($createdDate) : 'N/A';
    $expiryDateFriendly  = ($expiryDate !== 'N/A') ? $formatDateFriendly($expiryDate) : 'N/A';
    $updatedDateFriendly = ($updatedDate !== 'N/A') ? $formatDateFriendly($updatedDate) : 'N/A';

    $nsHtml = '<table class="table table-bordered table-sm mb-3">
        <tbody>
          <tr><th>Domain Name</th><td>' . htmlspecialchars($domainName) . '</td></tr>
          <tr><th>Registrar</th><td>' . htmlspecialchars($registrar) . '</td></tr>
          <tr><th>IANA ID</th><td>' . htmlspecialchars($ianaID) . '</td></tr>
          <tr><th>Registrar URL</th><td>' . htmlspecialchars($registrarUrl) . '</td></tr>
          <tr><th>WHOIS Server</th><td>' . htmlspecialchars($whoisServer) . '</td></tr>
          <tr><th>Abuse Contact</th><td>' . htmlspecialchars($abuseContact) . '</td></tr>
          <tr><th>Domain Status</th><td>' . htmlspecialchars($domainStatus) . '</td></tr>
          <tr><th>Creation Date</th><td>' . htmlspecialchars($createdDateFriendly) . '</td></tr>
          <tr><th>Expiry Date</th><td>' . htmlspecialchars($expiryDateFriendly) . '</td></tr>
          <tr><th>Updated Date</th><td>' . htmlspecialchars($updatedDateFriendly) . '</td></tr>
          <tr><th>Age</th><td>' . htmlspecialchars($domainAgeYears . ' years (' . $domainAgeDays . ' days)') . '</td></tr>
          <tr><th>Hosted IP Address</th><td>' . htmlspecialchars($hostedIP) . '</td></tr>';
    if (!empty($finalNsList)) {
        $nsHtml .= '<tr><th>Name Servers</th><td><ul>';
        foreach ($finalNsList as $oneNs) {
            $nsHtml .= '<li>' . htmlspecialchars($oneNs) . '</li>';
        }
        $nsHtml .= '</ul></td></tr>';
    } else {
        $nsHtml .= '<tr><th>Name Servers</th><td><div class="alert alert-info mb-0">No Name Server records found.</div></td></tr>';
    }
    $nsHtml .= '</tbody></table>';

    /*--------------------------------------------------------
     * 3) IP-API (Server Info Tab) – Now using stored data
     *-------------------------------------------------------*/
    $serverIP = $data['server_ip'] ?? null;
    // Instead of making a new external call, retrieve stored IP info:
    $geo = $data['ip_info']['geo'] ?? [];
    $city     = $geo['city']    ?? 'N/A';
    $region   = $geo['region']  ?? 'N/A';
    $country  = $geo['country'] ?? 'N/A';
    $asn      = $geo['as']      ?? 'N/A';
    $isp      = $geo['isp']     ?? 'N/A';
    $org      = $geo['org']     ?? 'N/A';
    $ipHistory = $data['ip_info']['ip_history'] ?? 'Not available';

    $serverInfoHtml = '
      <table class="table table-bordered table-sm">
        <thead><tr><th>Parameter</th><th>Value</th></tr></thead>
        <tbody>
          <tr><td>Server IP</td><td>' . htmlspecialchars($serverIP ?? 'N/A') . '</td></tr>
          <tr><td>IP Location</td><td>' . htmlspecialchars($city . ', ' . $region . ', ' . $country) . '</td></tr>
          <tr><td>ASN</td><td>' . htmlspecialchars($asn) . '</td></tr>
          <tr><td>Hosting Company</td><td>' . htmlspecialchars($org) . '</td></tr>
          <tr><td>ISP</td><td>' . htmlspecialchars($isp) . '</td></tr>
          <tr><td>IP History</td><td>' . htmlspecialchars($ipHistory) . '</td></tr>
        </tbody>
      </table>';

    /*--------------------------------------------------------
     * 4) SSL Info
     *-------------------------------------------------------*/
    $sslHtml = '<div class="alert alert-info">No SSL certificate details found.</div>';
    $sslInfo = $data['ssl_info'] ?? [];
    if (is_array($sslInfo) && !empty($sslInfo['ssl_info']) && is_array($sslInfo['ssl_info'])) {
        $sslCert = $sslInfo['ssl_info'];
        $subject         = $sslCert['subject']          ?? [];
        $issuer          = $sslCert['issuer']           ?? [];
        $validFromUnix   = $sslCert['validFrom_time_t'] ?? null;
        $validToUnix     = $sslCert['validTo_time_t']   ?? null;
        $validFrom       = $validFromUnix ? date('M d, Y H:i:s', $validFromUnix) : 'N/A';
        $validTo         = $validToUnix   ? date('M d, Y H:i:s', $validToUnix)   : 'N/A';
        $san             = $sslCert['extensions']['subjectAltName']      ?? 'N/A';
        $keyUsage        = $sslCert['extensions']['keyUsage']            ?? 'N/A';
        $extendedKeyUsage= $sslCert['extensions']['extendedKeyUsage']    ?? 'N/A';
        $certPolicies    = $sslCert['extensions']['certificatePolicies'] ?? 'N/A';
        $sslHtml = '<div style="font-family:Arial, sans-serif; border:1px solid #ccc; padding:15px; border-radius:5px; background:#f9f9f9;">';
        $sslHtml .= '<h3 style="margin-top:0;">SSL Certificate Details for ' . htmlspecialchars($this->urlParse['host']) . '</h3>';
        $sslHtml .= '<h4>Subject</h4><ul>';
        if (isset($subject['CN'])) {
            $sslHtml .= '<li><strong>Common Name (CN):</strong> ' . htmlspecialchars($subject['CN']) . '</li>';
        }
        $sslHtml .= '</ul>';
        $sslHtml .= '<h4>Issuer</h4><ul>';
        if (isset($issuer['C'])) {
            $sslHtml .= '<li><strong>Country (C):</strong> ' . htmlspecialchars($issuer['C']) . '</li>';
        }
        if (isset($issuer['O'])) {
            $sslHtml .= '<li><strong>Organization (O):</strong> ' . htmlspecialchars($issuer['O']) . '</li>';
        }
        if (isset($issuer['OU'])) {
            $sslHtml .= '<li><strong>Organizational Unit (OU):</strong> ' . htmlspecialchars($issuer['OU']) . '</li>';
        }
        if (isset($issuer['CN'])) {
            $sslHtml .= '<li><strong>Common Name (CN):</strong> ' . htmlspecialchars($issuer['CN']) . '</li>';
        }
        $sslHtml .= '</ul>';
        $sslHtml .= '<h4>Validity Period</h4><ul>';
        $sslHtml .= '<li><strong>Valid From:</strong> ' . $validFrom . ' <em>(Unix Time: ' . ($validFromUnix ?? 'N/A') . ')</em></li>';
        $sslHtml .= '<li><strong>Valid To:</strong> ' . $validTo . ' <em>(Unix Time: ' . ($validToUnix ?? 'N/A') . ')</em></li>';
        $sslHtml .= '</ul>';
        $sslHtml .= '<h4>Subject Alternative Names (SAN)</h4>';
        $sslHtml .= '<p>' . htmlspecialchars($san) . '</p>';
        $sslHtml .= '<h4>Key Usage</h4><ul>';
        $sslHtml .= '<li><strong>Standard:</strong> ' . htmlspecialchars($keyUsage) . '</li>';
        $sslHtml .= '<li><strong>Extended:</strong> ' . htmlspecialchars($extendedKeyUsage) . '</li>';
        $sslHtml .= '</ul>';
        $sslHtml .= '<h4>Certificate Policies</h4>';
        $sslHtml .= '<p>' . htmlspecialchars($certPolicies) . '</p>';
        $sslHtml .= '<p style="font-size:0.9em; color:#555;">Other technical details (such as serial number, version, and extensions) are available for advanced troubleshooting and security audits.</p>';
        $sslHtml .= '</div>';
    }

    /*--------------------------------------------------------
     * 5) Technology
     *-------------------------------------------------------*/
    $techUsed = $data['technology_used'] ?? [];
    $techHtml = '<div class="alert alert-info">No technology information found.</div>';
    if (!empty($techUsed)) {
        $techHtml = '<ul class="list-group">';
        foreach ($techUsed as $tech) {
            $techHtml .= '<li class="list-group-item">' . htmlspecialchars($tech) . '</li>';
        }
        $techHtml .= '</ul>';
    }

    /*--------------------------------------------------------
     * 6) WHOIS Tab
     *-------------------------------------------------------*/
    $whoisHtml = '<div class="alert alert-info">No WHOIS data available.</div>';
    if ($rawWhois !== '') {
        $whoisFormatted = preg_replace(
            '/^(.*?:)/m',
            '<strong>$1</strong>',
            nl2br(htmlspecialchars($rawWhois))
        );
        $whoisHtml = '<div>' . $whoisFormatted . '</div>';
    }

    /*--------------------------------------------------------
     * Final Tabs Layout
     *-------------------------------------------------------*/
    $html = '
<div class="container my-3">
  <ul class="nav nav-tabs" id="serverInfoTab" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="ns-tab" data-bs-toggle="tab" data-bs-target="#ns"
              type="button" role="tab" aria-controls="ns" aria-selected="true">
        Name Servers
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="dns-tab" data-bs-toggle="tab" data-bs-target="#dns"
              type="button" role="tab" aria-controls="dns" aria-selected="false">
        DNS Info
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="server-tab" data-bs-toggle="tab" data-bs-target="#server"
              type="button" role="tab" aria-controls="server" aria-selected="false">
        Server Info
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="ssl-tab" data-bs-toggle="tab" data-bs-target="#ssl"
              type="button" role="tab" aria-controls="ssl" aria-selected="false">
        SSL Info
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="tech-tab" data-bs-toggle="tab" data-bs-target="#tech"
              type="button" role="tab" aria-controls="tech" aria-selected="false">
        Technology
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="whois-tab" data-bs-toggle="tab" data-bs-target="#whois"
              type="button" role="tab" aria-controls="whois" aria-selected="false">
        Whois
      </button>
    </li>
  </ul>
  <div class="tab-content p-3" id="serverInfoTabContent">
    <div class="tab-pane fade show active" id="ns" role="tabpanel" aria-labelledby="ns-tab">
      ' . $nsHtml . '
    </div>
    <div class="tab-pane fade" id="dns" role="tabpanel" aria-labelledby="dns-tab">
      ' . $dnsHtml . '
    </div>
    <div class="tab-pane fade" id="server" role="tabpanel" aria-labelledby="server-tab">
      ' . $serverInfoHtml . '
    </div>
    <div class="tab-pane fade" id="ssl" role="tabpanel" aria-labelledby="ssl-tab">
      ' . $sslHtml . '
    </div>
    <div class="tab-pane fade" id="tech" role="tabpanel" aria-labelledby="tech-tab">
      ' . $techHtml . '
    </div>
    <div class="tab-pane fade" id="whois" role="tabpanel" aria-labelledby="whois-tab">
      ' . $whoisHtml . '
    </div>
  </div>
</div>';
    return $html;
}


    /*===================================================================
     * Processes and stores Schema data from the HTML.
     *=================================================================== 
     */
/**
 * Processes and stores schema data from the HTML.
 *
 * This method extracts schema information from:
 *   - JSON‑LD (from <script type="application/ld+json">)
 *   - Microdata (elements with itemscope/itemtype)
 *   - RDFa (elements with attributes like typeof, property, vocab)
 *
 * It then encodes all the extracted data as JSON and updates the DB.
 * It also checks for errors (adding suggestions) for each type.
 *
 * @return string JSON-encoded schema data.
 */
public function processSchema(): string
{
    $doc = $this->getDom();
    $xpath = new DOMXPath($doc);

    $schemas = [
        'json_ld'   => [],
        'microdata' => [],
        'rdfa'      => [],
        'suggestions' => [
            'json_ld'   => [],
            'microdata' => [],
            'rdfa'      => []
        ]
    ];

    // --- JSON-LD Extraction ---
    $jsonLdScripts = $xpath->query('//script[@type="application/ld+json"]');
    foreach ($jsonLdScripts as $js) {
        $json = trim($js->nodeValue);
        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $schemas['suggestions']['json_ld'][] = "Invalid JSON in an ld+json script.";
            continue;
        }
        if (isset($decoded['@graph']) && is_array($decoded['@graph'])) {
            foreach ($decoded['@graph'] as $node) {
                $types = $node['@type'] ?? 'undefined';
                if (is_array($types)) {
                    foreach ($types as $type) {
                        $type = is_string($type) ? $type : 'undefined';
                        $schemas['json_ld'][$type][] = $node;
                    }
                } else {
                    $type = is_string($types) ? $types : 'undefined';
                    $schemas['json_ld'][$type][] = $node;
                }
            }
        } else {
            $types = $decoded['@type'] ?? 'undefined';
            if (is_array($types)) {
                foreach ($types as $type) {
                    $type = is_string($type) ? $type : 'undefined';
                    $schemas['json_ld'][$type][] = $decoded;
                }
            } else {
                $type = is_string($types) ? $types : 'undefined';
                $schemas['json_ld'][$type][] = $decoded;
            }
        }
    }
    if (empty($schemas['json_ld']['Organization'])) {
        $schemas['suggestions']['json_ld'][] = "Organization schema is missing. Consider adding it to enhance your brand's structured data.";
    }

    // --- Microdata Extraction ---
    $microItems = $xpath->query('//*[@itemscope]');
    foreach ($microItems as $item) {
        $typeUrl = $item->getAttribute('itemtype');
        $type = $typeUrl ? basename(parse_url($typeUrl, PHP_URL_PATH)) : 'undefined';
        $schemas['microdata'][$type][] = $item->nodeName;
    }
    if (empty($schemas['microdata'])) {
        $schemas['suggestions']['microdata'][] = "No microdata found. Adding microdata can improve structured data richness.";
    }

    // --- RDFa Extraction ---
    $rdfaItems = $xpath->query('//*[@typeof]');
    foreach ($rdfaItems as $item) {
        $type = $item->getAttribute('typeof') ?: 'undefined';
        $rdfaData = [
            'node'     => $item->nodeName,
            'typeof'   => $type,
            'vocab'    => $item->getAttribute('vocab') ?: '',
            'property' => $item->getAttribute('property') ?: '',
            'content'  => $item->getAttribute('content') ?: $item->textContent
        ];
        $schemas['rdfa'][$type][] = $rdfaData;
    }
    if (empty($schemas['rdfa'])) {
        $schemas['suggestions']['rdfa'][] = "No RDFa data found. Consider adding RDFa to provide additional context.";
    }

    if (empty($schemas['json_ld']) && empty($schemas['microdata']) && empty($schemas['rdfa'])) {
        $schemas = ['schema_data' => 'No schema data found.'];
    }

    $schemaJson = json_encode($schemas);
    updateToDbPrepared($this->con, 'domains_data', ['schema_data' => $schemaJson], ['domain' => $this->domainStr]);
    return $schemaJson;
}

/**
 * Recursively renders an array as a nested table in a "Google-like" style.
 *
 * @param mixed $data The data to render.
 * @param string $prefix A unique prefix for collapse IDs.
 * @param int $level Current nesting level.
 * @return string HTML output.
 */
private function renderGoogleStyle($data, string $prefix = 'g', int $level = 0): string
{
    if (!is_array($data)) {
        return '<span>' . htmlspecialchars((string)$data) . '</span>';
    }
    $html = '<table class="table table-bordered table-sm mb-0">';
    foreach ($data as $key => $value) {
        $html .= '<tr>';
        $html .= '<th style="width:180px;">' . htmlspecialchars((string)$key) . '</th>';
        $html .= '<td>';
        if (is_array($value)) {
            $collapseId = $prefix . '-' . preg_replace('/\s+/', '-', $key) . '-' . $level . '-' . uniqid();
            $html .= '<button class="btn btn-link p-0" type="button" data-bs-toggle="collapse" data-bs-target="#' . $collapseId . '" aria-expanded="false" aria-controls="' . $collapseId . '">View details</button>';
            $html .= '<div class="collapse mt-1" id="' . $collapseId . '"><div class="card card-body p-2" style="background:#f9f9f9;">' . $this->renderGoogleStyle($value, $collapseId, $level + 1) . '</div></div>';
        } else {
            $html .= htmlspecialchars((string)$value);
        }
        $html .= '</td>';
        $html .= '</tr>';
    }
    $html .= '</table>';
    return $html;
}

/**
 * Custom rendering for Organization data using a card layout.
 *
 * @param array $data The Organization node data.
 * @return string HTML output.
 */
private function renderOrganization(array $data): string
{
    $html  = '<div class="card mb-3">';
    $html .= '<div class="card-header bg-primary text-white"><h5>Organization</h5></div>';
    $html .= '<div class="card-body">';
    $html .= '<table class="table table-bordered table-sm">';
    foreach ($data as $key => $value) {
        $html .= '<tr>';
        $html .= '<th style="width:150px;">' . htmlspecialchars(ucfirst($key)) . '</th>';
        $html .= '<td>';
        if (is_array($value)) {
            $html .= $this->renderGoogleStyle($value, 'org-' . $key);
        } else {
            $html .= htmlspecialchars((string)$value);
        }
        $html .= '</td>';
        $html .= '</tr>';
    }
    $html .= '</table>';
    $html .= '</div></div>';
    return $html;
}

/**
 * Displays the stored schema data in a tabbed layout in a user-friendly, Google-like format.
 * - JSON‑LD: Shown with sub-tabs by type (with "Organization" appearing first if present).
 *   Each sub-tab shows its data rendered via renderGoogleStyle(), and Organization data uses a custom layout.
 *   Suggestions for JSON‑LD are shown at the bottom of the JSON‑LD tab.
 * - Microdata and RDFa are rendered similarly with suggestions appended at the bottom.
 *
 * @param string $jsonData JSON-encoded schema data from the database.
 * @return string HTML output.
 */
public function showSchema(string $jsonData): string
{
    $data = json_decode($jsonData, true);
    if (!is_array($data)) {
        return '<div class="alert alert-danger">No schema data available.</div>';
    }

    // -------------------------------------------------------------------------
    // 1. Build JSON-LD Section
    // -------------------------------------------------------------------------
    $jsonLdContent = '';
    if (!empty($data['json_ld']) && is_array($data['json_ld'])) {
        // Order types so that "Organization" is first if present.
        $types = array_keys($data['json_ld']);
        $ordered = [];
        if (isset($data['json_ld']['Organization'])) {
            $ordered[] = 'Organization';
        }
        $otherTypes = array_diff($types, ['Organization']);
        sort($otherTypes);
        $ordered = array_merge($ordered, $otherTypes);

        // Build sub-tabs for JSON‑LD (remove "fade" class to fix refresh issues)
        $subTabNav = '<ul class="nav nav-pills mb-3" id="jsonLdSubTab" role="tablist">';
        $subTabContent = '<div class="tab-content" id="jsonLdSubTabContent">';
        $i = 0;
        foreach ($ordered as $type) {
            $count = count($data['json_ld'][$type]);
            $activeClass = ($i === 0) ? 'active' : '';
            $paneId = 'jsonld-' . preg_replace('/\s+/', '-', strtolower($type));
            $subTabNav .= '
            <li class="nav-item" role="presentation">
                <button class="nav-link ' . $activeClass . '" id="' . $paneId . '-tab" data-bs-toggle="tab" data-bs-target="#' . $paneId . '" type="button" role="tab" aria-controls="' . $paneId . '" aria-selected="' . ($i === 0 ? 'true' : 'false') . '">
                    ' . htmlspecialchars($type) . ' (' . $count . ')
                </button>
            </li>';
            $subTabContent .= '<div class="tab-pane show ' . $activeClass . '" id="' . $paneId . '" role="tabpanel" aria-labelledby="' . $paneId . '-tab">';
            foreach ($data['json_ld'][$type] as $index => $node) {
                if ($type === 'Organization') {
                    $subTabContent .= $this->renderOrganization($node);
                } else {
                    $subTabContent .= '<div class="card mb-3">';
                    $subTabContent .= '<div class="card-header">' . htmlspecialchars($type) . ' - Node ' . ($index + 1) . '</div>';
                    $subTabContent .= '<div class="card-body" style="background:#f9f9f9;">' . $this->renderGoogleStyle($node, $paneId . '-' . $index) . '</div>';
                    $subTabContent .= '</div>';
                }
            }
            $subTabContent .= '</div>';
            $i++;
        }
        $subTabNav .= '</ul>';
        $subTabContent .= '</div>';

        $jsonLdContent .= '<div class="container mt-3"><h4>JSON‑LD Data</h4>';
        $jsonLdContent .= $subTabNav . $subTabContent;
        $jsonLdContent .= '</div>';
        if (!empty($data['suggestions']['json_ld'])) {
            $jsonLdContent .= '<div class="container mt-2"><div class="alert alert-warning">';
            $jsonLdContent .= '<strong>Suggestions:</strong> ' . implode(" ", $data['suggestions']['json_ld']);
            $jsonLdContent .= '</div></div>';
        }
    } else {
        $jsonLdContent = '<div class="alert alert-info">No JSON‑LD schema data found.</div>';
    }

    // -------------------------------------------------------------------------
    // 2. Microdata Section
    // -------------------------------------------------------------------------
    $microContent = '';
    if (!empty($data['microdata']) && is_array($data['microdata'])) {
        $microContent .= '<div class="container mt-3"><h4>Microdata</h4>';
        $microContent .= $this->renderGoogleStyle($data['microdata'], 'micro');
        $microContent .= '</div>';
        if (!empty($data['suggestions']['microdata'])) {
            $microContent .= '<div class="container mt-2"><div class="alert alert-warning">';
            $microContent .= '<strong>Suggestions:</strong> ' . implode(" ", $data['suggestions']['microdata']);
            $microContent .= '</div></div>';
        }
    } else {
        $microContent = '<div class="alert alert-info">No Microdata found.</div>';
    }

    // -------------------------------------------------------------------------
    // 3. RDFa Section
    // -------------------------------------------------------------------------
    $rdfaContent = '';
    if (!empty($data['rdfa']) && is_array($data['rdfa'])) {
        $rdfaContent .= '<div class="container mt-3"><h4>RDFa</h4>';
        $rdfaContent .= $this->renderGoogleStyle($data['rdfa'], 'rdfa');
        $rdfaContent .= '</div>';
        if (!empty($data['suggestions']['rdfa'])) {
            $rdfaContent .= '<div class="container mt-2"><div class="alert alert-warning">';
            $rdfaContent .= '<strong>Suggestions:</strong> ' . implode(" ", $data['suggestions']['rdfa']);
            $rdfaContent .= '</div></div>';
        }
    } else {
        $rdfaContent = '<div class="alert alert-info">No RDFa data found.</div>';
    }

    // -------------------------------------------------------------------------
    // Build top-level tabs for JSON‑LD, Microdata, RDFa
    // -------------------------------------------------------------------------
    $tabs = '
    <ul class="nav nav-tabs" id="schemaMainTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="jsonld-tab" data-bs-toggle="tab" data-bs-target="#jsonld" type="button" role="tab" aria-controls="jsonld" aria-selected="true">JSON‑LD</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="microdata-tab" data-bs-toggle="tab" data-bs-target="#microdata" type="button" role="tab" aria-controls="microdata" aria-selected="false">Microdata</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="rdfa-tab" data-bs-toggle="tab" data-bs-target="#rdfa" type="button" role="tab" aria-controls="rdfa" aria-selected="false">RDFa</button>
        </li>
    </ul>';

    $content = '
    <div class="tab-content" id="schemaMainTabContent">
        <div class="tab-pane show active" id="jsonld" role="tabpanel" aria-labelledby="jsonld-tab">' . $jsonLdContent . '</div>
        <div class="tab-pane fade" id="microdata" role="tabpanel" aria-labelledby="microdata-tab">' . $microContent . '</div>
        <div class="tab-pane fade" id="rdfa" role="tabpanel" aria-labelledby="rdfa-tab">' . $rdfaContent . '</div>
    </div>';

    // -------------------------------------------------------------------------
    // 4. High-Level Suggestions (Global)
    // -------------------------------------------------------------------------
    $globalSuggestions = [];

    // Example conditions:
    if (empty($data['json_ld'])) {
        $globalSuggestions[] = 'We found no JSON‑LD markup. Google recommends JSON‑LD for modern structured data.';
    }
    if (empty($data['microdata'])) {
        $globalSuggestions[] = 'No microdata found. While JSON‑LD is preferred, microdata can be useful on older systems.';
    }
    if (empty($data['rdfa'])) {
        $globalSuggestions[] = 'No RDFa found. RDFa can add inline semantics but is less common nowadays.';
    }

    // If "Organization" is missing from JSON‑LD:
    if (empty($data['json_ld']['Organization'])) {
        $globalSuggestions[] = 'Consider adding "Organization" markup (e.g., your company name, logo, contact info) for better brand presence in search.';
    }

    // If you want more advanced checks (like checking if "logo" or "contactPoint" is missing), you can do so here:
    // e.g. if (!empty($data['json_ld']['Organization'])) { ... }

    // Build the final suggestions block
    $suggestionHtml = '';
    if (!empty($globalSuggestions)) {
        $suggestionHtml .= '<div class="container mt-3">';
        $suggestionHtml .= '<div class="alert alert-info">';
        $suggestionHtml .= '<strong>Additional Suggestions:</strong>';
        $suggestionHtml .= '<ul class="mb-0">';
        foreach ($globalSuggestions as $sug) {
            $suggestionHtml .= '<li>' . htmlspecialchars($sug) . '</li>';
        }
        $suggestionHtml .= '</ul></div></div>';
    }

    return '<div class="container my-3">'
            . $tabs
            . $content
            . $suggestionHtml
            . '</div>';
}

 /*===================================================================
     * Social URL Handelers
     *=================================================================== 
     */

     /**
 * Processes social page URLs from the HTML.
 * Scans all anchor tags and extracts URLs that match common social network patterns.
 * Stores the results as a JSON-encoded associative array in the "social_urls" field.
 *
 * @return string JSON data of social URLs.
 */
public function processSocialUrls(): string {
    $doc = $this->getDom();
    $xpath = new DOMXPath($doc);
    
    // Define social networks and regex patterns to match in the href attribute.
    // The TripAdvisor pattern now handles various TLDs.
    $socialPatterns = [
        'facebook'    => '/facebook\.com/i',
        'x'           => '/(twitter\.com|x\.com)/i', // X (formerly Twitter)
        'instagram'   => '/instagram\.com/i',
        'linkedin'    => '/linkedin\.com/i',
        'youtube'     => '/youtube\.com/i',
        'pinterest'   => '/pinterest\.com/i',
        'discord'     => '/discord\.com/i',
        'whatsapp'    => '/(wa\.me|whatsapp\.com)/i',
        'tripadvisor' => '/tripadvisor\.[a-z]{2,6}/i',  // handles multiple TLDs
        'tiktok'      => '/tiktok\.com/i'
    ];
    
    $socialUrls = [];
    
    // Loop through each social network pattern.
    foreach ($socialPatterns as $network => $pattern) {
        // Use XPath to fetch all <a> tags whose href (lowercased) contains the network keyword.
        $nodes = $xpath->query("//a[contains(translate(@href, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), '$network')]");
        // Fallback: if no nodes found, iterate all <a> tags.
        if ($nodes->length === 0) {
            $nodes = $xpath->query("//a");
        }
        
        $urls = [];
        foreach ($nodes as $node) {
            $href = trim($node->getAttribute('href'));
            if (empty($href)) {
                continue;
            }
            // Check if the URL matches our pattern.
            if (preg_match($pattern, $href)) {
                // Normalize relative URLs.
                if (!preg_match('#^(?:https?:)?//#i', $href)) {
                    $href = $this->toAbsoluteUrl($href);
                }
                $urls[] = $href;
            }
        }
        if (!empty($urls)) {
            // Save unique URLs for this social network.
            $socialUrls[$network] = array_values(array_unique($urls));
        }
    }
   
    $jsonData = jsonEncode($socialUrls);
    updateToDbPrepared($this->con, 'domains_data', ['social_urls' => $jsonData], ['domain' => $this->domainStr]);
    return $jsonData;
}

/**
 * Displays the processed social page URLs in a nicely formatted card.
 * Uses Font Awesome icons for each social network.
 *
 * @param string $socialData JSON-encoded social URLs from DB.
 * @return string HTML output.
 */
public function showSocialUrls($socialData): string {
    $data = jsonDecode($socialData, true);
    if (!is_array($data) || empty($data)) {
        return '<div class="alert alert-info">No social URLs found.</div>';
    }
    
    // Define Font Awesome icons for each social network.
    $icons = [
        'facebook'    => 'fa-facebook',
        'x'           => 'fa-twitter', // Replace with custom X icon if available.
        'instagram'   => 'fa-instagram',
        'linkedin'    => 'fa-linkedin',
        'youtube'     => 'fa-youtube',
        'pinterest'   => 'fa-pinterest',
        'discord'     => 'fa-discord',
        'whatsapp'    => 'fa-whatsapp',
        'tripadvisor' => 'fa-tripadvisor',
        'tiktok'      => 'fa-tiktok'
    ];
    
    $output = '<div class="card my-3 shadow-sm">
                    <div class="card-header"><strong>Social Page URLs</strong></div>
                    <div class="card-body">';
    
    // Loop through each network found.
    foreach ($data as $network => $urls) {
        $icon = $icons[$network] ?? 'fa-globe';
        $output .= '<div class="social-url-group mb-3">';
        $output .= '<h5><i class="fa ' . $icon . '"></i> ' . ucfirst($network) . '</h5>';
        $output .= '<ul class="list-unstyled">';
        foreach ($urls as $url) {
            $output .= '<li><a href="' . htmlspecialchars($url) . '" target="_blank" rel="nofollow">' . htmlspecialchars($url) . '</a></li>';
        }
        $output .= '</ul></div>';
    }
    
    $output .= '</div>
                <div class="card-footer"><small>Social page URLs extracted from the site.</small></div>
                </div>';
    
    return $output;
}



    /*===================================================================
     * SPEED TIPS HANDLER
     *=================================================================== 
     */
    public function processSpeedTips() {
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
        $updateStr = serBase([$cssCount, $jsCount, $nestedTables, $inlineCss]);
        updateToDbPrepared($this->con, 'domains_data', ['speed_tips' => $updateStr], ['domain' => $this->domainStr]);
        return [
            'cssCount' => $cssCount,
            'jsCount' => $jsCount,
            'nestedTables' => $nestedTables,
            'inlineCss' => $inlineCss
        ];
    }

    public function showSpeedTips($data) {
        $speedTipsBody = '';
        $speedTipsBody .= ($data['cssCount'] > 5) ? '<img src="' . themeLink('img/false.png', true) . '" /> ' . $this->lang['AN145'] : '<img src="' . themeLink('img/true.png', true) . '" /> ' . $this->lang['AN144'];
        $speedTipsBody .= '<br><br>';
        $speedTipsBody .= ($data['jsCount'] > 5) ? '<img src="' . themeLink('img/false.png', true) . '" /> ' . $this->lang['AN147'] : '<img src="' . themeLink('img/true.png', true) . '" /> ' . $this->lang['AN146'];
        $speedTipsBody .= '<br><br>';
        $speedTipsBody .= ($data['nestedTables'] == 1) ? '<img src="' . themeLink('img/false.png', true) . '" /> ' . $this->lang['AN149'] : '<img src="' . themeLink('img/true.png', true) . '" /> ' . $this->lang['AN148'];
        $speedTipsBody .= '<br><br>';
        $speedTipsBody .= ($data['inlineCss'] == 1) ? '<img src="' . themeLink('img/false.png', true) . '" /> ' . $this->lang['AN151'] : '<img src="' . themeLink('img/true.png', true) . '" /> ' . $this->lang['AN150'];
        $speedTipsClass = ($data['cssCount'] > 5 || $data['jsCount'] > 5 || $data['nestedTables'] == 1 || $data['inlineCss'] == 1) ? 'improveBox' : 'passedBox';
        $output = '<div class="' . $speedTipsClass . '">
                        <div class="msgBox">
                            ' . $this->lang['AN152'] . '<br />
                            <div class="altImgGroup">' . $speedTipsBody . '</div><br />
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
        if (preg_match("/\bua-\d{4,9}-\d{1,4}\b/i", $this->html) || check_str_contains($this->html, "gtag('")) {
            $analyticsClass = 'passedBox';
            $analyticsMsg = $this->lang['AN154'];
        } else {
            $analyticsClass = 'errorBox';
            $analyticsMsg = $this->lang['AN153'];
        }
        $patternCode = "<!DOCTYPE[^>]*>";
        preg_match("#{$patternCode}#is", $this->html, $matches);
        if (!isset($matches[0])) {
            $docTypeMsg = $this->lang['AN155'];
            $docTypeClass = 'improveBox';
            $docType = "";
        } else {
            $doctypes = [
                'HTML 5' => '<!DOCTYPE html>',
                'HTML 4.01 Strict' => '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">',
                'HTML 4.01 Transitional' => '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">',
                'HTML 4.01 Frameset' => '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Frameset//EN" "http://www.w3.org/TR/html4/frameset.dtd">',
                'XHTML 1.0 Strict' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">',
                'XHTML 1.0 Transitional' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">',
                'XHTML 1.0 Frameset' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Frameset//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-frameset.dtd">',
                'XHTML 1.1' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">'
            ];
            $found = strtolower(preg_replace('/\s+/', ' ', trim($matches[0])));
            $docType = array_search($found, array_map('strtolower', $doctypes));
            $docTypeMsg = $this->lang['AN156'] . ' ' . $docType;
            $docTypeClass = 'passedBox';
        }
        $updateStr = jsonEncode(['analyticsMsg' => $analyticsMsg, 'docTypeMsg' => $docTypeMsg, 'analyticsClass' => $analyticsClass, 'docTypeClass' => $docTypeClass]);
        updateToDbPrepared($this->con, 'domains_data', ['analytics' => $updateStr], ['domain' => $this->domainStr]);
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
        $w3Data = curlGET('https://validator.w3.org/nu/?doc=' . urlencode($this->scheme . "://" . $this->host));
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
        $updateStr = jsonEncode(['w3cMsg' => $w3cMsg, 'w3DataCheck' => $w3DataCheck]);
        updateToDbPrepared($this->con, 'domains_data', $updateStr, ['domain' => $this->domainStr]);
        return ['w3cMsg' => $w3cMsg, 'w3DataCheck' => $w3DataCheck];
    }

    public function showW3c($w3cData) {
        if (!is_string($w3cData)) {
            $data = $w3cData;
        } else {
            $data = jsonDecode($w3cData);
        }
        $output = '<div class="lowImpactBox">
                        <div class="msgBox">' . $data['w3cMsg'] . '<br /><br /></div>
                        <div class="seoBox39 suggestionBox">' . $this->lang['AN211'] . '</div>
                   </div>';
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
        updateToDbPrepared($this->con, 'domains_data', ['encoding' => $updateStr], ['domain' => $this->domainStr]);
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
        updateToDbPrepared($this->con, 'domains_data', ['indexed' => jsonEncode($indexed)], ['domain' => $this->domainStr]);
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
        $updateStr = jsonEncode([(string)$alexa[0], (string)$alexa[1], (string)$alexa[2], (string)$alexa[3]]);
        updateToDbPrepared($this->con, 'domains_data', ['alexa' => $updateStr], ['domain' => $this->domainStr]);
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
        $updateStr = serBase([$socialData['fb'], $socialData['twit'], $socialData['insta'], 0]);
        updateToDbPrepared($this->con, 'domains_data', ['social' => $updateStr], ['domain' => $this->domainStr]);
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
        $data = mysqliPreparedQuery($this->con, "SELECT alexa FROM domains_data WHERE domain=?", 's', [$this->domainStr]);
        if ($data !== false) {
            $alexa = jsonDecode($data['alexa']);
            $alexaDatas = [
                ['', 'Popularity at', $alexa[1]],
                ['', 'Regional Rank', $alexa[2]]
            ];
            return $alexaDatas;
        }
        return [];
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
        $updateStr = jsonEncode(['desktopScore' => $desktopScore, 'mobileScore' => $mobileScore]);
        updateToDbPrepared($this->con, 'domains_data', ['page_speed_insight' => $updateStr], ['domain' => $this->domainStr]);
        return $updateStr;
    }

    public function showPageSpeedInsight($data) {
        $data = jsonDecode($data);
        $speedStr = $this->lang['117'];
        $desktopStr = $this->lang['118'];
        $mobileStr = $this->lang['119'];
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
        $score = [$passscore, $improvescore, $errorscore];
        $updateStr = serBase($score);
        updateToDbPrepared($this->con, 'domains_data', ['score' => $updateStr, 'completed' => 'yes'], ['domain' => $this->domainStr]);
        $data = mysqliPreparedQuery($this->con, "SELECT * FROM domains_data WHERE domain=?", 's', [$this->domainStr]);
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
            $other = serBase([$finalScore, $globalRank, $pageSpeed]);
            addToRecentSites($this->con, $this->domainStr, $ip, $username, $other);
        }
        delFile($filename);
    }
}
?>
