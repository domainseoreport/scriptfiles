<?php
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
require_once __DIR__ . '/../../vendor/autoload.php';
require_once   'ServerInfoHelper.php';
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
    public function processMeta(): string {
        // Start the session if not already started.
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        // === 1. Extract raw meta data ===
        $title = $description = $keywords = '';
        $doc = $this->getDom();
        $nodes = $doc->getElementsByTagName('title');
        if ($nodes->length > 0) {
            $title = $nodes->item(0)->nodeValue;
        }
        $metas = $doc->getElementsByTagName('meta');
        for ($i = 0; $i < $metas->length; $i++) {
            $meta = $metas->item($i);
            // Use case‑insensitive comparison
            if (strtolower($meta->getAttribute('name')) === 'description') {
                $description = $meta->getAttribute('content');
            }
            if (strtolower($meta->getAttribute('name')) === 'keywords') {
                $keywords = $meta->getAttribute('content');
            }
        }
        // Use a helper to clean extra spaces/newlines
        $clean = function($str) {
            return trim(preg_replace('/\s+/', ' ', $str));
        };
        
        $rawMeta = [
            'title'       => $clean($title),
            'description' => $clean($description),
            'keywords'    => $clean($keywords)
        ];
        
        // === 2. Compute meta score and build meta report ===
        $score   = 0;
        $passed  = 0;
        $improve = 0;
        $errors  = 0;
        
        // Title scoring: if length between 10 and 70, award 2 points
        $lenTitle = mb_strlen($rawMeta['title'], 'utf8');
        if ($lenTitle < 10) {
            $errors++;
        } elseif ($lenTitle <= 70) {
            $passed++;
            $score += 2;
        } else {
            $errors++;
        }
        
        // Description scoring: if length between 70 and 300, award 2 points
        $lenDesc = mb_strlen($rawMeta['description'], 'utf8');
        if ($lenDesc < 70) {
            $errors++;
        } elseif ($lenDesc <= 300) {
            $passed++;
            $score += 2;
        } else {
            $errors++;
        }
        
        // Keywords scoring: if provided, award 2 points; if missing, mark as "to improve"
        if (!empty($rawMeta['keywords'])) {
            $passed++;
            $score += 2;
        } else {
            $improve++;
        }
        
        // Calculate percentage (max score is 6)
        $maxScore = 6;
        $percent = ($maxScore > 0) ? round(($score / $maxScore) * 100) : 0;
        
        // Build a comment summarizing the findings
        $comment = "";
        if ($lenTitle < 10) {
            $comment .= "Title is too short. ";
        } elseif ($lenTitle > 70) {
            $comment .= "Title is too long. ";
        } else {
            $comment .= "Title is within range. ";
        }
        if ($lenDesc < 70) {
            $comment .= "Description is too short. ";
        } elseif ($lenDesc > 300) {
            $comment .= "Description is too long. ";
        } else {
            $comment .= "Description is acceptable. ";
        }
        if (empty($rawMeta['keywords'])) {
            $comment .= "No keywords provided.";
        } else {
            $comment .= "Keywords are provided.";
        }
        
        // Prepare the meta report array
        $metaReport = [
            'score'   => $score,
            'passed'  => $passed,
            'improve' => $improve,
            'errors'  => $errors,
            'percent' => $percent,
            'details' => [
                'title_length'       => $lenTitle,
                'description_length' => $lenDesc,
                'keywords_present'   => !empty($rawMeta['keywords'])
            ],
            'comment' => $comment
        ];
        
        // === 3. Combine raw meta and report into one array and update the DB ===
        // Also store the meta report in a session variable for later consolidation.
        $_SESSION['report_data']['meta'] = $metaReport;
        
        $completeMetaData = [
            'raw'    => $rawMeta,
            'report' => $_SESSION['report_data']['meta']
        ];
        $completeMetaJson = jsonEncode($completeMetaData);
        
        // Update the DB with both the complete meta data and the overall score in one call.
        updateToDbPrepared($this->con, 'domains_data', [
            'meta_data' => $completeMetaJson,
            'score'     => $score
        ], ['domain' => $this->domainStr]);
        
        // Return the complete meta JSON.
        return $completeMetaJson;
    }
    
    
    
    

    public function showMeta(string $jsonData): string {
        // Decode the JSON stored in DB.
        $data = jsonDecode($jsonData);
        if (!is_array($data) || !isset($data['raw']) || !isset($data['report'])) {
            return '<div class="alert alert-warning">No meta report available.</div>';
        }
    
        // Extract raw data & computed report.
        $rawMeta    = $data['raw'];
        $metaReport = $data['report'];
    
        // For Title and Description, use the raw data; for Keywords, if empty then display empty.
        $siteTitle       = !empty($rawMeta['title']) ? $rawMeta['title'] : $this->lang['AN11'];
        $siteDescription = !empty($rawMeta['description']) ? $rawMeta['description'] : $this->lang['AN12'];
        $siteKeywords    = (isset($rawMeta['keywords']) && trim($rawMeta['keywords']) !== "") ? $rawMeta['keywords'] : ""; // Do NOT fallback to lang string.
        
        $host = $this->urlParse['host'] ?? '';
    
        // --- Recommended Ranges ---
        $titleMin = 50; 
        $titleMax = 60;   // recommended 50–60
        $descMin  = 120; 
        $descMax  = 160;  // recommended 120–160
        $keysMax  = 200;  // example limit for keywords
    
        // --- Actual Lengths ---
        // For title and description, use the report details if available.
        $actualTitleLen = $metaReport['details']['title_length'] ?? mb_strlen($siteTitle);
        $actualDescLen  = $metaReport['details']['description_length'] ?? mb_strlen($siteDescription);
        // For keywords, we now use the actual length of the raw keywords (which will be 0 if empty).
        $actualKeysLen  = mb_strlen($siteKeywords);
    
        // Check permissions.
        if (!isset($_SESSION['twebUsername']) && !isAllowedStats($this->con, 'seoBox1')) {
            die(str_repeat($this->seoBoxLogin . $this->sepUnique, 4));
        }
    
        // Build progress bars using the helper function.
        [$titleBarHTML, $classTitle] = $this->buildLengthBar(
            actualLen: $actualTitleLen,
            min: $titleMin,
            max: $titleMax
        );
        [$descBarHTML, $classDesc] = $this->buildLengthBar(
            actualLen: $actualDescLen,
            min: $descMin,
            max: $descMax
        );
        [$keysBarHTML, $classKeys] = $this->buildLengthBar(
            actualLen: $actualKeysLen,
            min: 0,
            max: $keysMax
        );
    
        // Build HTML output.
        // 1) Title Tag block.
        $output = '
    <div class="seoBox seoBox1 '.$classTitle.'">
      <div class="row">
        <div class="col-md-3">
          <strong>Title Tag</strong>
        </div>
        <div class="col-md-9">
          <div class="msgBox">
            '.htmlspecialchars($siteTitle).'<br />
          </div>
          '.$titleBarHTML.'
          <small class="text-muted">
              <strong>Length: </strong>'.$actualTitleLen.' chars 
              <span style="float:right;"><strong>(recommended '.$titleMin.'–'.$titleMax.')</strong></span>
          </small>
        </div>
      </div>
    </div>' . $this->sepUnique;
    
        // 2) Meta Description block.
        $output .= '
    <div class="seoBox seoBox2 '.$classDesc.'">
      <div class="row">
        <div class="col-md-3">
          <strong>Meta Description</strong>
        </div>
        <div class="col-md-9">
          <div class="msgBox padRight10">
            '.htmlspecialchars($siteDescription).'<br />
          </div>
          '.$descBarHTML.'
          <small class="text-muted">
              <strong>Length: </strong>'.$actualDescLen.' chars 
              <span style="float:right;"><strong>(recommended '.$descMin.'–'.$descMax.')</strong></span>
          </small>
        </div>
      </div>
    </div>' . $this->sepUnique;
    
        // 3) Meta Keywords block.
        // If keywords are empty, we display "Not provided" and a length of 0.
        $displayKeywords = !empty($siteKeywords) ? htmlspecialchars($siteKeywords) : '<em>Not provided</em>';
        $output .= '
    <div class="seoBox seoBox3 '.$classKeys.'">
      <div class="row">
        <div class="col-md-3">
          <strong>Meta Keywords</strong>
        </div>
        <div class="col-md-9">
          <div class="msgBox padRight10">
            '.$displayKeywords.'<br />
          </div>
          '.$keysBarHTML.'
          <small class="text-muted">
              <strong>Length: </strong>'.$actualKeysLen.' chars 
              <span style="float:right;"><strong>(recommended up to '.$keysMax.')</strong></span>
          </small>
        </div>
      </div>
    </div>' . $this->sepUnique;
    
        // 4) Google Preview block (unchanged to preserve your JS and styling).
        $output .= '
    <div id="seoBox5" class="seoBox seoBox5 lowImpactBox">
      <div class="msgBox">
        <div class="googlePreview">
          <!-- First Row: Mobile & Tablet Views -->
          <div class="row">
            <div class="col-md-6">
              <div class="google-preview-box mobile-preview">
                <h6>Mobile View</h6>
                <p class="google-title"><a href="#">'.htmlspecialchars($siteTitle).'</a></p>
                <p class="google-url"><span class="bold">'.htmlspecialchars($host).'</span>/</p>
                <p class="google-desc">'.htmlspecialchars($siteDescription).'</p>
              </div>
            </div>
            <div class="col-md-6">
              <div class="google-preview-box tablet-preview">
                <h6>Tablet View</h6>
                <p class="google-title"><a href="#">'.htmlspecialchars($siteTitle).'</a></p>
                <p class="google-url"><span class="bold">'.htmlspecialchars($host).'</span>/</p>
                <p class="google-desc">'.htmlspecialchars($siteDescription).'</p>
              </div>
            </div>
          </div>
          <!-- Second Row: Desktop View -->
          <div class="row mt-3">
            <div class="col-12">
              <div class="google-preview-box desktop-preview mt-5">
                <h6>Desktop View</h6>
                <p class="google-title"><a href="#">'.htmlspecialchars($siteTitle).'</a></p>
                <p class="google-url"><span class="bold">'.htmlspecialchars($host).'</span>/</p>
                <p class="google-desc">'.htmlspecialchars($siteDescription).'</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>' . $this->sepUnique;
    
        // 5) Summary card for meta analysis.
        $score   = $metaReport['score']   ?? 0;
        $percent = $metaReport['percent'] ?? 0;
        $comment = $metaReport['comment'] ?? '';
    
        $output .= '
    <div class="card my-3">
      <div class="card-header">
        <h4>Meta Analysis Summary</h4>
      </div>
      <div class="card-body">
        <p><strong>Score:</strong> '.$score.' (Percent: '.$percent.'%)</p>
        <p><strong>Comment:</strong> '.htmlspecialchars($comment).'</p>
      </div>
    </div>';
    
        return $output;
    }
        

/**
 * Helper function to build a length-based progress bar (always 100% width) with user-friendly text.
 * Returns an array: [ $progressBarHTML, $cssClassBox ].
 */
private function buildLengthBar(int $actualLen, int $min, int $max): array
{
    // Use a constant width (100%) and change only the color/text
    $widthPercent = 100;
    $barClass     = 'bg-success';
    $barText      = 'Within recommended range';
    $boxClass     = 'passedBox';

    if ($min > 0 && $actualLen < $min) {
        // Too short
        $barClass = 'bg-danger';
        $needed   = $min - $actualLen;
        $barText  = "Too short - Need {$needed} more chars";
        $boxClass = 'improveBox';
    } elseif ($max > 0 && $actualLen > $max) {
        // Too long
        $barClass = 'bg-danger';
        $exceed   = $actualLen - $max;
        $barText  = "Too long - Exceeded by {$exceed} chars";
        $boxClass = 'errorBox';
    }

    $progressBarHTML = '
<div class="progress mt-2" style="height: 22px;">
  <div class="progress-bar ' . $barClass . '"
       role="progressbar"
       style="width: ' . $widthPercent . '%;"
       aria-valuenow="' . $widthPercent . '"
       aria-valuemin="0"
       aria-valuemax="100">
    ' . $barText . '
  </div>
</div>';

    return [$progressBarHTML, $boxClass];
}

    
public function getSeparator(): string {
    return $this->sepUnique;
}


/**
 * Helper function to build the Google preview block.
 */
private function buildGooglePreview(string $title, string $description, string $host): string
{
    $classKey = 'lowImpactBox'; // your existing style

    return '<div id="seoBox5" class="seoBox seoBox5 ' . $classKey . '">
              <div class="msgBox">
                <div class="googlePreview">
                  <!-- First Row: Mobile & Tablet Views -->
                  <div class="row">
                    <div class="col-md-6">
                      <div class="google-preview-box mobile-preview">
                        <h6>Mobile View</h6>
                        <p class="google-title"><a href="#">' . htmlspecialchars($title) . '</a></p>
                        <p class="google-url"><span class="bold">' . htmlspecialchars($host) . '</span>/</p>
                        <p class="google-desc">' . htmlspecialchars($description) . '</p>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="google-preview-box tablet-preview">
                        <h6>Tablet View</h6>
                        <p class="google-title"><a href="#">' . htmlspecialchars($title) . '</a></p>
                        <p class="google-url"><span class="bold">' . htmlspecialchars($host) . '</span>/</p>
                        <p class="google-desc">' . htmlspecialchars($description) . '</p>
                      </div>
                    </div>
                  </div>
                  <!-- Second Row: Desktop View -->
                  <div class="row mt-3">
                    <div class="col-12">
                      <div class="google-preview-box desktop-preview mt-5">
                        <h6>Desktop View</h6>
                        <p class="google-title"><a href="#">' . htmlspecialchars($title) . '</a></p>
                        <p class="google-url"><span class="bold">' . htmlspecialchars($host) . '</span>/</p>
                        <p class="google-desc">' . htmlspecialchars($description) . '</p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>' . $this->sepUnique;
}

    
    
    

    /*-------------------------------------------------------------------
     * HEADING HANDLER
     *-------------------------------------------------------------------
     */
    public function processHeading(): string {
        try {
            // === 1. Extract raw heading data ===
            $doc = $this->getDom();
            if (!$doc) {
                throw new Exception("Failed to load DOM.");
            }
    
            $tags = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
            $headings = [];
            foreach ($tags as $tag) {
                $elements = $doc->getElementsByTagName($tag);
                foreach ($elements as $element) {
                    $content = trim(strip_tags($element->textContent));
                    if ($content !== "") {
                        // Use a trim that removes regular whitespace as well as non-breaking spaces.
                        $headings[$tag][] = trim($content, " \t\n\r\0\x0B\xc2\xa0");
                    }
                }
            }
    
            // === 2. Compute heading report ===
            $report = $this->computeHeadingReport($tags, $headings);
    
            // === 3. Combine raw headings and report, update the DB in one call ===
            $completeHeadingData = [
                'raw'    => $headings,
                'report' => $report
            ];
            $completeHeadingJson = json_encode($completeHeadingData);
    
            // Update the DB column "headings" with the complete JSON
            updateToDbPrepared($this->con, 'domains_data', ['headings' => $completeHeadingJson], ['domain' => $this->domainStr]);
            // Optionally update the overall score field (if combining with other tests)
            updateToDbPrepared($this->con, 'domains_data', ['score' => $report['score']], ['domain' => $this->domainStr]);
    
            // --- Store the complete heading data in session for later use ---
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            $_SESSION['report_data']['headingReport'] = $completeHeadingData;
    
            return $completeHeadingJson;
        } catch (Exception $e) {
            error_log("Error in processHeading: " . $e->getMessage());
            return json_encode(['error' => 'An error occurred while processing headings.']);
        }
    }
    
    
    private function computeHeadingReport(array $tags, array $headings): array {
        // Count headings for each tag
        $counts = [];
        foreach ($tags as $tag) {
            $counts[$tag] = isset($headings[$tag]) ? count($headings[$tag]) : 0;
        }
    
        // Initialize scoring variables and recommendations
        $score = 0;
        $maxScore = 7;
        $comments = [];
    
        // -- H1 Validation --
        if ($counts['h1'] === 1) {
            $score += 2;
            $h1Content = $headings['h1'][0];
            if (mb_strlen($h1Content, 'utf8') > 70) {
                $comments[] = "H1 is too long (keep under 70 characters)";
            }
        } elseif ($counts['h1'] === 0) {
            $comments[] = "Missing H1 tag";
        } else {
            $comments[] = "Multiple H1 tags detected";
        }
    
        // -- Hierarchy Validation --
        $lastLevel = 1;
        $hierarchyErrors = 0;
        foreach ($tags as $tag) {
            if (isset($headings[$tag])) {
                $currentLevel = (int) substr($tag, 1);
                if ($currentLevel > $lastLevel + 1) {
                    $hierarchyErrors++;
                    $comments[] = "Jump from H{$lastLevel} to H{$currentLevel} detected";
                }
                $lastLevel = $currentLevel;
            }
        }
        if ($hierarchyErrors === 0) {
            $score += 2;
        } else {
            $comments[] = "$hierarchyErrors hierarchy jump(s) found";
        }
    
        // -- Content Quality Checks --
        $uniqueHeadings = [];
        $duplicates = [];
        foreach ($headings as $tag => $contents) {
            foreach ($contents as $heading) {
                // Remove punctuation and lower-case for comparison
                $key = mb_strtolower(trim(preg_replace('/[^\w\s]/u', '', $heading)));
                if (isset($uniqueHeadings[$key])) {
                    $duplicates[] = $heading;
                }
                $uniqueHeadings[$key] = true;
            }
        }
        if (!empty($duplicates)) {
            $comments[] = "Duplicate headings found: " . implode(", ", array_slice($duplicates, 0, 3));
        }
    
        // -- Structure Validation --
        if ($counts['h2'] >= 2) {
            $score += 1;
        } else {
            $comments[] = "At least 2 H2 tags are recommended for better structure";
        }
    
        // -- Accessibility Check --
        if (empty($headings)) {
            $comments[] = "No headings detected - this affects accessibility";
        }
    
        $percent = ($maxScore > 0) ? round(($score / $maxScore) * 100) : 0;
    
        return [
            'score'           => $score,
            'percent'         => $percent,
            'details'         => [
                'counts'           => $counts,
                'hierarchy_errors' => $hierarchyErrors,
                'duplicates'       => count($duplicates),
                'content_issues'   => [
                    'long_h1' => (isset($h1Content) && mb_strlen($h1Content, 'utf8') > 70)
                ]
            ],
            'recommendations' => $comments
        ];
    }
    
    
    public function showHeading(string $jsonData): string {
        try {
            // Decode the JSON stored in the DB
            $data = json_decode($jsonData, true);
            if (!is_array($data) || !isset($data['raw']) || !isset($data['report'])) {
                return '<div class="alert alert-danger">Invalid heading data.</div>';
            }
    
            $rawHeadings = $data['raw'];
            $report = $data['report'];
            $tags = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
    
            // Build a detailed table of headings
            $tableHTML = '<table class="table table-bordered table-hover">
                            <thead class="table-light">
                              <tr>
                                <th style="width:50px;">Tag</th>
                                <th style="width:100px;">Count</th>
                                <th>Headings</th>
                                <th style="width:250px;">Suggestion</th>
                              </tr>
                            </thead>
                            <tbody>';
            foreach ($tags as $tag) {
                $count = isset($rawHeadings[$tag]) ? count($rawHeadings[$tag]) : 0;
    
                // Build list of headings for this tag
                $headingsList = '';
                if (!empty($rawHeadings[$tag])) {
                    $headingsList = '<ul class="list-unstyled mb-0">';
                    foreach ($rawHeadings[$tag] as $text) {
                        $warnings = [];
                        // For H1, if too long, note it.
                        if ($tag === 'h1' && mb_strlen($text, 'utf8') > 70) {
                            $warnings[] = 'Too long';
                        }
                        $warningHTML = !empty($warnings) ? '<br /><span class="text-warning small">' . implode(', ', $warnings) . '</span>' : '';
                        $headingsList .= '<li>&lt;' . strtoupper($tag) . '&gt; <strong>' . htmlspecialchars($text) . '</strong> &lt;/' . strtoupper($tag) . '&gt;' . $warningHTML . '</li>';
                    }
                    $headingsList .= '</ul>';
                } else {
                    $headingsList = '<em class="text-muted">None found.</em>';
                }
    
                // Build suggestion using our updated logic:
                // For H1: exactly one is OK, none or more than one is an error.
                // For H2: at least two are OK; otherwise, a warning.
                // For other tags, we mark them as OK.
                $suggestion = '';
                if ($tag === 'h1') {
                    if ($count === 1) {
                        $suggestion = '<span class="text-success">OK</span>';
                    } elseif ($count === 0) {
                        $suggestion = '<span class="text-danger">❌ Missing H1</span>';
                    } else {
                        $suggestion = '<span class="text-danger">❌ Multiple H1s detected</span>';
                    }
                } elseif ($tag === 'h2') {
                    if ($count >= 2) {
                        $suggestion = '<span class="text-success">OK</span>';
                    } else {
                        $suggestion = '<span class="text-warning">⚠️ Add more H2s for better structure</span>';
                    }
                } else {
                    // For other headings, simply mark as OK if present, else none found.
                    $suggestion = ($count > 0) ? '<span class="text-success">OK</span>' : '<span class="text-muted">None found</span>';
                }
    
                $tableHTML .= '<tr>
                                <td><strong>' . strtoupper($tag) . '</strong></td>
                                <td>' . $count . '</td>
                                <td>' . $headingsList . '</td>
                                <td>' . $suggestion . '</td>
                              </tr>';
            }
            $tableHTML .= '</tbody></table>';
    
            // Build a formatted summary of recommendations from the report,
            // formatting each recommendation on a new line.
            $recommendations = $report['recommendations'] ?? [];
            $recomHTML = '';
            if (!empty($recommendations)) {
                $recomHTML .= '<ul class="list-group">';
                foreach ($recommendations as $rec) {
                    $recomHTML .= '<li class="list-group-item">' . htmlspecialchars($rec) . '</li>';
                }
                $recomHTML .= '</ul>';
            } else {
                $recomHTML = '<em class="text-muted">No recommendations.</em>';
            }
    
            // Build a summary card for the overall heading report with recommendations below the table.
            $summaryHTML = '
            <div class="card my-3">
               <div class="card-header">
                 <h4>Heading Analysis Summary</h4>
               </div>
               <div class="card-body">
                 <p><strong>Score:</strong> ' . ($report['score'] ?? 0) . ' (Percent: ' . ($report['percent'] ?? 0) . '%)</p>
                 <p><strong>Recommendations:</strong></p>
                 ' . $recomHTML . '
               </div>
            </div>';
    
            return  $tableHTML . $summaryHTML ;
    
        } catch (Exception $e) {
            error_log("Error in showHeading: " . $e->getMessage());
            return '<div class="alert alert-danger">An error occurred while displaying heading data.</div>';
        }
    }
    
    
    
    

    /*===================================================================
     * IMAGE ALT TAG HANDLER
     *=================================================================== 
     */
    /**
 * Processes image tags to extract raw metrics about alt attributes,
 * then computes a detailed report with scoring and suggestions.
 *
 * The returned JSON contains:
 *   - raw: all collected metrics (counts for total images, missing alt, empty alt, short alt, long alt, and redundant alt).
 *   - report: a computed report with:
 *         • score: total points earned (max 10)
 *         • passed: count of conditions passing (2 points each)
 *         • improve: count of conditions “to improve” (1 point each)
 *         • errors: count of error conditions (0 points)
 *         • percent: overall percentage score
 *         • details: a per‑condition status (Pass, To Improve, Error)
 *         • comment: an overall comment.
 *
 * The final JSON is updated in the DB (field "image_alt") and saved in session.
 *
 * @return string JSON-encoded combined raw data and report.
 */
public function processImage(): string {
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
        'suggestions' => []  // raw suggestions
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
        $src = trim($img->getAttribute('src')) ?: 'N/A';
        // Convert relative URL to absolute.
        $src = $this->toAbsoluteUrl($src);
        $alt = $img->getAttribute('alt');
        $title = trim($img->getAttribute('title')) ?: 'N/A';
        $width = trim($img->getAttribute('width')) ?: 'N/A';
        $height = trim($img->getAttribute('height')) ?: 'N/A';
        $class = trim($img->getAttribute('class')) ?: 'N/A';
        $parentTag = $img->parentNode->nodeName;
        $parentTxt = trim($img->parentNode->textContent);
        $position = method_exists($this, 'getNodePosition') ? $this->getNodePosition($img) : 'N/A';

        $data = compact('src', 'title', 'width', 'height', 'class', 'parentTag', 'parentTxt', 'position');

        if (!$img->hasAttribute('alt')) {
            $aggregate($results['images_missing_alt'], $data);
        } elseif (trim($alt) === "") {
            $aggregate($results['images_with_empty_alt'], $data);
        } else {
            $altLength = mb_strlen($alt);
            $normalizedAlt = strtolower($alt);
            $redundantAlt = in_array($normalizedAlt, ['image', 'photo', 'picture', 'logo']);
            // We consider alt text with less than 5 characters as too short.
            if ($altLength < 5) {
                $data['alt'] = $alt;
                $data['length'] = $altLength;
                $aggregate($results['images_with_short_alt'], $data);
            }
            // If alt text is very long (e.g. >100 characters), flag it.
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

    // Calculate totals.
    $totalMissing = array_sum(array_map(fn($i) => $i['count'], $results['images_missing_alt']));
    $totalEmpty   = array_sum(array_map(fn($i) => $i['count'], $results['images_with_empty_alt']));
    $totalShort   = array_sum(array_map(fn($i) => $i['count'], $results['images_with_short_alt']));
    $totalLong    = array_sum(array_map(fn($i) => $i['count'], $results['images_with_long_alt']));
    $totalRedund  = array_sum(array_map(fn($i) => $i['count'], $results['images_with_redundant_alt']));

    // Add raw suggestions.
    if ($totalMissing > 0) {
        $results['suggestions'][] = "There are {$totalMissing} image instance(s) missing alt attributes.";
    }
    if ($totalEmpty > 0) {
        $results['suggestions'][] = "There are {$totalEmpty} image instance(s) with empty alt attributes.";
    }
    if ($totalShort > 0) {
        $results['suggestions'][] = "There are {$totalShort} image instance(s) with very short alt text (<5 chars).";
    }
    if ($totalLong > 0) {
        $results['suggestions'][] = "There are {$totalLong} image instance(s) with very long alt text (>100 chars).";
    }
    if ($totalRedund > 0) {
        $results['suggestions'][] = "There are {$totalRedund} image instance(s) with redundant alt text (e.g., 'image','logo').";
    }
    if ($totalMissing === 0 && $totalEmpty === 0 && $totalShort === 0 && $totalLong === 0 && $totalRedund === 0) {
        $results['suggestions'][] = "Great job! All images have appropriate alt attributes.";
    }

    // --- Compute Report ---
    // We'll define five conditions based on our raw data.
    // (Note: total_images is available from $results['total_images'].)
    $totalImages = $results['total_images'];

    // Condition 1: Missing Alt Attributes.
    // Ideal: 0 missing. If missing ratio <= 5%, then "To Improve", else "Error".
    $ratioMissing = $totalImages > 0 ? $totalMissing / $totalImages : 1;
    if ($ratioMissing == 0) {
        $cond1Status = "Pass";
        $cond1Score = 2;
    } elseif ($ratioMissing <= 0.05) {
        $cond1Status = "To Improve";
        $cond1Score = 1;
    } else {
        $cond1Status = "Error";
        $cond1Score = 0;
    }

    // Condition 2: Empty Alt Text Ratio.
    $ratioEmpty = $totalImages > 0 ? $totalEmpty / $totalImages : 1;
    if ($ratioEmpty <= 0.10) {
        $cond2Status = "Pass";
        $cond2Score = 2;
    } elseif ($ratioEmpty <= 0.20) {
        $cond2Status = "To Improve";
        $cond2Score = 1;
    } else {
        $cond2Status = "Error";
        $cond2Score = 0;
    }

    // Condition 3: Short Alt Text Ratio.
    // Consider only images that have alt text (i.e. total effective = totalImages - missing - empty).
    $imagesWithAlt = ($totalImages - $totalMissing - $totalEmpty);
    $ratioShort = $imagesWithAlt > 0 ? $totalShort / $imagesWithAlt : 1;
    if ($ratioShort == 0) {
        $cond3Status = "Pass";
        $cond3Score = 2;
    } elseif ($ratioShort <= 0.10) {
        $cond3Status = "To Improve";
        $cond3Score = 1;
    } else {
        $cond3Status = "Error";
        $cond3Score = 0;
    }

    // Condition 4: Redundant Alt Text.
    if ($totalRedund == 0) {
        $cond4Status = "Pass";
        $cond4Score = 2;
    } else {
        $cond4Status = "Error";
        $cond4Score = 0;
    }

    // Condition 5: Long Alt Text.
    if ($totalLong == 0) {
        $cond5Status = "Pass";
        $cond5Score = 2;
    } else {
        $cond5Status = "Error";
        $cond5Score = 0;
    }

    $totalScore = $cond1Score + $cond2Score + $cond3Score + $cond4Score + $cond5Score;
    $maxPoints = 10;
    $scorePercent = $maxPoints > 0 ? round(($totalScore / $maxPoints) * 100) : 0;

    // Count conditions that pass, need improvement, or error.
    $passedCount = 0;
    $improveCount = 0;
    $errorCount = 0;
    $conditions = [
        'Alt Missing Ratio' => $cond1Status,
        'Empty Alt Ratio' => $cond2Status,
        'Short Alt Ratio' => $cond3Status,
        'Redundant Alt' => $cond4Status,
        'Long Alt' => $cond5Status
    ];
    foreach ($conditions as $status) {
        if ($status === "Pass") {
            $passedCount++;
        } elseif ($status === "To Improve") {
            $improveCount++;
        } else {
            $errorCount++;
        }
    }

    // Build an overall comment.
    $comment = "Image analysis computed. ";
    if ($scorePercent >= 90) {
        $comment .= "Excellent image alt attribute usage.";
    } elseif ($scorePercent >= 70) {
        $comment .= "Good, but consider improving alt text quality.";
    } elseif ($scorePercent >= 50) {
        $comment .= "Average performance; review alt text issues.";
    } else {
        $comment .= "Poor image alt attributes; significant improvements are needed.";
    }
    
    // Assemble final report.
    $report = [
        'score'   => $totalScore,
        'passed'  => $passedCount,
        'improve' => $improveCount,
        'errors'  => $errorCount,
        'percent' => $scorePercent,
        'details' => $conditions,
        'comment' => $comment
    ];
    
    // Combine raw data and report.
    $completeImageData = [
        'raw' => $results,
        'report' => $report
    ];
    
    $updateStr = jsonEncode($completeImageData);
    updateToDbPrepared($this->con, 'domains_data', ['image_alt' => $updateStr], ['domain' => $this->domainStr]);
    $_SESSION['report_data']['imageAltReport'] = $completeImageData;
    
    return $updateStr;
}

    /**
 * Displays image analysis data.
 *
 * This function displays a header summary that includes an icon, total images,
 * and a border color that reflects the overall issues (none, few, or many).
 * Then it renders a tabbed view for the detailed raw data (Missing Alt, Empty Alt,
 * Short Alt, Long Alt, Redundant Alt) along with any suggestions.
 *
 * @param string $imageData JSON-encoded image analysis data and report.
 * @return string HTML output.
 */
public function showImage($imageData): string {
    $data = jsonDecode($imageData);
    
    if (!is_array($data) || !isset($data['raw']['total_images'])) {
        return '<div class="alert alert-warning">No image data available.</div>';
    }
    
    // Extract raw data.
    $raw = $data['raw'];
    $report = $data['report'] ?? [];
    
    // Calculate overall issues count.
    $issuesCount = array_sum(array_map(fn($i) => $i['count'], $raw['images_missing_alt'] ?? []))
                 + array_sum(array_map(fn($i) => $i['count'], $raw['images_with_empty_alt'] ?? []))
                 + array_sum(array_map(fn($i) => $i['count'], $raw['images_with_short_alt'] ?? []))
                 + array_sum(array_map(fn($i) => $i['count'], $raw['images_with_long_alt'] ?? []))
                 + array_sum(array_map(fn($i) => $i['count'], $raw['images_with_redundant_alt'] ?? []));
    
    // Set border class based on issues.
    if ($issuesCount == 0) {
        $boxClass = 'border-success';
        $headerIcon = themeLink('img/true.png', true);
        $headerText = "No issues detected.";
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
            <img src="' . $headerIcon . '" alt="' . ($issuesCount == 0 ? "No issues" : "Issues detected") . '" 
                 title="' . ($issuesCount == 0 ? "All images have appropriate alt attributes." : "Some image alt attribute issues detected.") . '" 
                 class="me-2" /> 
            <strong>' . $headerText . '</strong>
        </div>
        <div>
            <span class="badge bg-secondary">Total Images: ' . $raw['total_images'] . '</span>
        </div>
    </div>';
    
    // Build nav tabs for detailed raw data.
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
    
    // Helper function (anonymous) to build a table for each category.
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
                // Determine final displayed width/height.
                $rawWidth = $item['width'] ?? 'N/A';
                $rawHeight = $item['height'] ?? 'N/A';
                $imgWidth = (ctype_digit($rawWidth)) ? (int)$rawWidth : 0;
                $imgHeight = (ctype_digit($rawHeight)) ? (int)$rawHeight : 0;
                $thumbWidth = ($imgWidth > 0 && $imgWidth < 50) ? $imgWidth : 50;
                $thumbHeight = ($imgHeight > 0 && $imgHeight < 50) ? $imgHeight : 50;
    
                // Build thumbnail with an overlay if the image src is a data URI.
                $thumbnail = '<div style="position: relative; display: inline-block;">';
                $thumbnail .= '<img src="' . htmlspecialchars($item['src']) . '" alt="Image" style="width:' . $thumbWidth . 'px; height:' . $thumbHeight . 'px; object-fit:cover;">';
                if (stripos($item['src'], 'data:image') === 0) {
                    $thumbnail .= '<span style="position: absolute; bottom: 0; left: 0; background: rgba(0,0,0,0.6); color: #fff; font-size: 10px; padding: 2px;">Encoded</span>';
                }
                $thumbnail .= '</div>';
    
                // Build detailed info block.
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
    
    // Build tab content.
    $tabContent = '<div class="tab-content" id="imageAltTabContent">';
    $tabContent .= '<div class="tab-pane fade show active" id="missing-alt" role="tabpanel" aria-labelledby="missing-alt-tab">';
    $tabContent .= $buildTable('Images Missing Alt Attribute', $raw['images_missing_alt'] ?? []);
    $tabContent .= '</div>';
    $tabContent .= '<div class="tab-pane fade" id="empty-alt" role="tabpanel" aria-labelledby="empty-alt-tab">';
    $tabContent .= $buildTable('Images With Empty Alt Attribute', $raw['images_with_empty_alt'] ?? []);
    $tabContent .= '</div>';
    $tabContent .= '<div class="tab-pane fade" id="short-alt" role="tabpanel" aria-labelledby="short-alt-tab">';
    $tabContent .= $buildTable('Images With Short Alt Text', $raw['images_with_short_alt'] ?? []);
    $tabContent .= '</div>';
    $tabContent .= '<div class="tab-pane fade" id="long-alt" role="tabpanel" aria-labelledby="long-alt-tab">';
    $tabContent .= $buildTable('Images With Long Alt Text', $raw['images_with_long_alt'] ?? []);
    $tabContent .= '</div>';
    $tabContent .= '<div class="tab-pane fade" id="redundant-alt" role="tabpanel" aria-labelledby="redundant-alt-tab">';
    $tabContent .= $buildTable('Images With Redundant Alt Text', $raw['images_with_redundant_alt'] ?? []);
    $tabContent .= '</div>';
    $tabContent .= '</div>';
    
    // Build overall report summary.
    $summaryHtml = '<div class="alert alert-info mt-4">';
    $summaryHtml .= '<strong>Overall Image Alt Score:</strong> ' . ($report['percent'] ?? 0) . '%<br>';
    $summaryHtml .= '<strong>Score:</strong> ' . ($report['score'] ?? 0) . ' out of 10<br>';
    $summaryHtml .= '<strong>Details:</strong><br>';
    foreach ($report['details'] as $cond => $status) {
        $summaryHtml .= htmlspecialchars($cond) . ': ' . htmlspecialchars($status) . '<br>';
    }
    $summaryHtml .= '<strong>Comment:</strong> ' . ($report['comment'] ?? '');
    $summaryHtml .= '</div>';
    
    // Optionally display any overall suggestions from the raw data.
    $suggestionHtml = '';
    if (!empty($raw['suggestions'])) {
        $suggestionHtml .= '<div class="card border-warning mt-4">';
        $suggestionHtml .= '  <div class="card-header bg-warning text-dark"><strong>Suggestions for Improvement</strong></div>';
        $suggestionHtml .= '  <div class="card-body"><ul class="mb-0">';
        foreach ($raw['suggestions'] as $sug) {
            $suggestionHtml .= '<li>' . htmlspecialchars($sug) . '</li>';
        }
        $suggestionHtml .= '  </ul></div>';
        $suggestionHtml .= '</div>';
    }
    
    // Wrap everything in a Bootstrap card.
    $output = '<div id="seoBoxImage" class="card ' . $boxClass . ' my-3 shadow-sm">';
    $output .= '<div class="card-header">' . $headerContent . '</div>';
    $output .= '<div class="card-body">' . $tabs . $tabContent . $suggestionHtml . '</div>';
    $output .= '</div>';
    
    // Append the overall report summary below the card.
    $output .= $summaryHtml;
    
    return $output;
}

    
            
    
    
 /**
 * Convert a (possibly) relative URL to an absolute URL based on the current domain.
 *
 * @param string $url The image path or URL found in the HTML.
 * @return string The absolute URL.
 */
private function toAbsoluteUrl(string $url): string {
    if (empty($url) || preg_match('#^(?:https?:)?//#i', $url) || preg_match('#^data:image#i', $url)) {
        return $url;
    }
    $domain = $this->domainStr;
    if (!preg_match('#^https?://#i', $domain)) {
        $domain = 'https://' . $domain;
    }
    return rtrim($domain, '/') . '/' . ltrim($url, '/');
}



 /**
 * Returns the position of the given node among its siblings.
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
    /**
 * -----------------------------------------------------------------
 * HELPER FUNCTIONS (unchanged)
 * -----------------------------------------------------------------
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
        "yourselves","comments","view"
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
        $density = ($total > 0) ? ($count / $total) * 100 : 0;
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

/**
 * -----------------------------------------------------------------
 * KEYWORD CLOUD PROCESSING & REPORTING
 * -----------------------------------------------------------------
 */


/**
 * Merged function: Builds the keyword cloud + does n-gram consistency checks
 * and returns a single HTML output that includes both the top unigrams cloud
 * and the tabbed consistency tables (Trigrams/Bigrams/Unigrams).
 *
 * Also stores the combined data in the DB.
 *
 * @return string HTML output for immediate echo in domains.php
 */
public function processKeyCloudAndConsistency(): string {
    // Generate the keyword cloud JSON and decode it.
    $keyCloudJson = $this->processKeyCloud();
    $keyCloudData = json_decode($keyCloudJson, true);

    // Retrieve meta data and headings.
    $metaData = json_decode($this->processMeta(), true);
    $headingData = json_decode($this->processHeading(), true);
    // If headings are stored under 'raw', use that.
    $headings = isset($headingData['raw']) && is_array($headingData['raw'])
                    ? $headingData['raw']
                    : $headingData;

    // Build keyword cloud HTML using your helper.
    $keywordCloudHtml = $this->buildKeyCloudHtml($keyCloudData);
    // Build the consistency report HTML.
    $consistencyHtml = $this->showKeyConsistencyNgramsTabs($keyCloudData['fullCloud'], $metaData, $headings);

    // Append the report comment (suggestion) if available.
    $suggestionHtml = '';
    if (isset($keyCloudData['report']['comment']) && !empty($keyCloudData['report']['comment'])) {
        $suggestionHtml = '<div class="mt-3">
            <div class="alert alert-secondary text-center" role="alert">
                <strong>Suggestion:</strong> ' . htmlspecialchars($keyCloudData['report']['comment']) . '
            </div>
        </div>';
    }

    // Save metaData and headings along with the other data.
    $completeKeyCloudData = [
        'keyCloudData' => $keyCloudData['keyCloudData'] ?? [], // existing raw keywords data
        'keyDataHtml'  => $keyCloudData['keyDataHtml'] ?? '',
        'outCount'     => $keyCloudData['outCount'] ?? 0,
        'fullCloud'    => $keyCloudData['fullCloud'] ?? [],
        'report'       => $keyCloudData['report'] ?? [],
        'metaData'     => $metaData,      // NEW: include meta data
        'headings'     => $headingData    // NEW: include full heading data
    ];
    $completeKeyCloudJson = json_encode($completeKeyCloudData);

    // Update DB (one call).
    updateToDbPrepared($this->con, 'domains_data', ['keywords_cloud' => $completeKeyCloudJson], ['domain' => $this->domainStr]);

    // Save complete data to session.
    $_SESSION['report_data']['keyCloudReport'] = $completeKeyCloudData;

    // Return the complete HTML output.
    return $keywordCloudHtml . $consistencyHtml . $suggestionHtml;
}



/**
 * Helper to build the top unigrams “keyword cloud” HTML snippet
 * from the array $keyCloudData.
 */
private function buildKeyCloudHtml(array $keyCloudData): string
{
    $outCount     = $keyCloudData['outCount']  ?? 0;
    $keyDataHtml  = $keyCloudData['keyDataHtml'] ?? '';

    if (!isset($_SESSION['twebUsername']) && !isAllowedStats($this->con, 'seoBox7')) {
        return $this->seoBoxLogin; // or die($this->seoBoxLogin)
    }

    $output = '<div class="row align-items-center mb-3 mt-5">';
    $output .= '    <div class="col-md-3">';
    $output .= '        <h5 class="fw-bold"><i class="fa fa-check me-2"></i>Keywords Cloud</h5>';
    $output .= '    </div>';
    $output .= '    <div class="col-md-9" id="seoBox7">';
    if ($outCount > 0) {
        $output .= '<ul class="keywordsTags">' . $keyDataHtml . '</ul>';
    } else {
        $output .= '<p>' . $this->lang['AN29'] . '</p>';
    }
    $output .= '    </div>';
    $output .= '</div><hr>';
    
    return $output;
}


/**
 * Processes the keyword cloud:
 * - Generates the raw keyword cloud using unigrams (plus bigrams/trigrams).
 * - Computes a score and report (using three conditions) based on the top keywords.
 * - Combines the raw data with the computed report.
 * - Saves the complete JSON in the DB (one call) and in a session variable.
 *
 * Returns the complete JSON string.
 */
public function processKeyCloud(): string {
    $dom = $this->getDom();
    $cloudData = $this->generateKeywordCloud($dom);
    
    // Use unigrams as the primary list; limit to top 15.
    $unigrams = $cloudData['unigrams'];
    $maxKeywords = 20;
    $rawKeywords = array_slice($unigrams, 0, $maxKeywords);
    
    // Build HTML list and array for top keywords.
    $keyDataHtml = '';
    $outArr = [];
    foreach ($rawKeywords as $data) {
        $keyword = $data['phrase'];
        $outArr[] = [$keyword, $data['count'], $data['density']];
        $keyDataHtml .= '<li><span class="keyword">' . htmlspecialchars($keyword) . '</span><span class="number">' . $data['count'] . '</span></li>';
    }
    $outCount = count($outArr);
    
    // --- Scoring Logic (max score = 6) ---
    $score = 0;
    $passed = 0;
    $improve = 0;
    $errors = 0;
    // Condition 1: At least one keyword exists.
    if ($outCount > 0) {
        $passed++;
        $score += 2;
    } else {
        $errors++;
    }
    // Condition 2: No overuse suggestions.
    if (empty($cloudData['suggestions'])) {
        $passed++;
        $score += 2;
    } else {
        $improve++;
    }
    // Condition 3: Check density of top keyword (ideal if ≤ 5%).
    if ($outCount > 0) {
        if ($rawKeywords[0]['density'] <= 5) {
            $passed++;
            $score += 2;
        } else {
            $errors++;
        }
    }
    $maxPossible = 6;
    $percent = ($maxPossible > 0) ? round(($score / $maxPossible) * 100) : 0;
    
    // Build overall comment.
    $comment = "";
    if ($outCount == 0) {
        $comment .= "No significant keywords found. ";
    } else {
        $comment .= "Keywords extracted successfully. ";
    }
    if (!empty($cloudData['suggestions'])) {
        $comment .= implode(" ", $cloudData['suggestions']) . " ";
    } else {
        $comment .= "No overuse issues detected. ";
    }
    if ($outCount > 0) {
        if ($rawKeywords[0]['density'] > 5) {
            $comment .= "Top keyword density is high. ";
        } else {
            $comment .= "Top keyword density is within optimal range. ";
        }
    }
    
    // Build the report array.
    $report = [
        'score'   => $score,
        'passed'  => $passed,
        'improve' => $improve,
        'errors'  => $errors,
        'percent' => $percent,
        'details' => [
            'top_keyword'    => ($outCount > 0 ? $rawKeywords[0] : null),
            'total_keywords' => $outCount
        ],
        'comment' => $comment
    ];
    
    // Combine raw keyword cloud data and report.
    $completeKeyCloudData = [
        'keyCloudData' => $outArr,
        'keyDataHtml'  => $keyDataHtml,
        'outCount'     => $outCount,
        'fullCloud'    => $cloudData,
        'report'       => $report
    ];
    $completeKeyCloudJson = json_encode($completeKeyCloudData);
    
    // Update DB (one call).
    updateToDbPrepared($this->con, 'domains_data', ['keywords_cloud' => $completeKeyCloudJson], ['domain' => $this->domainStr]);
    
    // Save complete data to session.
    $_SESSION['report_data']['keyCloudReport'] = $completeKeyCloudData;
    
    return $completeKeyCloudJson;
}

/**
 * Displays the top keyword cloud (raw view) using the prebuilt HTML.
 */
public function showKeyCloud($data): string {
    $outCount = $data['outCount'] ?? 0;
    $keyDataHtml = $data['keyDataHtml'] ?? '';
    if (!isset($_SESSION['twebUsername']) && !isAllowedStats($this->con, 'seoBox7')) {
        die($this->seoBoxLogin);
    }
    $output = '<div class="seoBox seoBox-keywords lowImpactBox">
                    <div class="msgBox padRight10 bottom5">';
    $output .= ($outCount != 0) ? '<ul class="keywordsTags">' . $keyDataHtml . '</ul>' : '<p>' . $this->lang['AN29'] . '</p>';
    $output .= '</div></div>';
    return $output;
}

/**
 * Processes keyword consistency by checking for each top keyword whether it appears in the meta title, description, and headings.
 * Returns a JSON string.
 */
public function processKeyConsistency($keyCloudData, $metaData, $headings): string {
    $result = [];
    foreach ($keyCloudData as $item) {
        $keyword = $item[0];
        $inTitle = (stripos($metaData['title'] ?? '', $phrase) !== false);
        $inDesc  = (stripos($metaData['description'] ?? '', $phrase) !== false);
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
    $resultJson = json_encode($result);
    updateToDbPrepared($this->con, 'domains_data', ['key_consistency' => $resultJson], ['domain' => $this->domainStr]);
    return $resultJson;
}


/**
 * Displays keyword consistency data in a simple table.
 */
public function showKeyConsistency($consistencyData): string {
    $consistencyData = json_decode($consistencyData, true);
    if (!is_array($consistencyData)) {
        return '<div class="alert alert-warning">No keyword consistency data available.</div>';
    }
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
 * Computes a consistency report for a given n-gram type.
 * Checks whether each n-gram phrase appears in the meta title,
 * meta description, or in any of the headings (from the new JSON format).
 *
 * @param string $type   (Not used in calculation; for reference only)
 * @param array  $ngrams The list of n-grams (each is an array with keys: phrase, count, density)
 * @param array  $metaData The meta data array, expected as decoded JSON with key "raw" (contains "title" and "description")
 * @param array  $headings The headings array, expected as decoded JSON with key "raw" (contains "h1", "h2", etc.)
 * @return array Returns an array with keys "score" and "recommendation"
 */
private function buildConsistencyReportForType(string $type, array $ngrams, array $metaData, array $headings): array {
    // Extract meta title and description from the new format.
    $metaTitle = $metaData['raw']['title'] ?? "";
    $metaDesc  = $metaData['raw']['description'] ?? "";
    
    // Combine all heading texts from headings["raw"]
    $allHeadings = [];
    if (isset($headings['raw']) && is_array($headings['raw'])) {
        foreach ($headings['raw'] as $tag => $texts) {
            if (is_array($texts)) {
                $allHeadings = array_merge($allHeadings, $texts);
            }
        }
    }
    
    $total = count($ngrams);
    $consistent = 0;
    foreach ($ngrams as $item) {
        $phrase = $item['phrase'];
        $found = false;
        // Check meta title and description.
        foreach ([$metaTitle, $metaDesc] as $fieldText) {
            if (!empty($fieldText) && stripos($fieldText, $phrase) !== false) {
                $found = true;
                break;
            }
        }
        // If not found in meta, check in all headings.
        if (!$found) {
            foreach ($allHeadings as $text) {
                if (stripos($text, $phrase) !== false) {
                    $found = true;
                    break;
                }
            }
        }
        if ($found) {
            $consistent++;
        }
    }
    $score = ($total > 0) ? round(($consistent / $total) * 100) : 0;
    $recommendation = "";
    if ($score >= 80) {
       $recommendation = "High consistency.";
    } elseif ($score >= 50) {
       $recommendation = "Moderate consistency; some improvements recommended.";
    } else {
       $recommendation = "Low consistency; significant improvements needed.";
    }
    return [
        'score' => $score,
        'recommendation' => $recommendation
    ];
}

/**
 * Displays keyword consistency data in tabs for Trigrams, Bigrams, and Unigrams.
 * Each tab shows a table of the n-gram data and, below the table, displays the computed consistency
 * report (score and recommendation) for that n-gram type.
 *
 * @param array $fullCloud The full keyword cloud data (with keys "unigrams", "bigrams", "trigrams")
 * @param array $metaData  The meta data array (new format)
 * @param array $headings  The headings array (new format)
 * @return string The formatted HTML output.
 */
public function showKeyConsistencyNgramsTabs($fullCloud, $metaData, $headings): string {
    // Ensure $headings is an array; if not, set to empty.
    if (!is_array($headings)) {
        $headings = [];
    }
    // If headings are stored in the new format, extract the raw headings.
    if (isset($headings['raw']) && is_array($headings['raw'])) {
        $headings = $headings['raw'];
    }
    
    // Set icons for true/false results.
    $this->true  = '<i class="fa fa-check text-success"></i>';
    $this->false = '<i class="fa fa-times text-danger"></i>';
    
    // Extract each n-gram type.
    $unigrams = $fullCloud['unigrams'] ?? [];
    $bigrams  = $fullCloud['bigrams'] ?? [];
    $trigrams = $fullCloud['trigrams'] ?? [];
    
    // Build individual tables using your existing buildConsistencyTable() helper.
    $trigramTable = $this->buildConsistencyTable('Trigrams Keywords Analysis', $trigrams, $metaData, $headings);
    $bigramTable  = $this->buildConsistencyTable('Bigrams Keywords Analysis', $bigrams, $metaData, $headings);
    $unigramTable = $this->buildConsistencyTable('Unigrams Keywords Analysis', $unigrams, $metaData, $headings);
    
    // Compute consistency report for each n-gram type.
    $triReport = $this->buildConsistencyReportForType('Trigrams Keywords Analysis', $trigrams, $metaData, $headings);
    $biReport  = $this->buildConsistencyReportForType('Bigrams Keywords Analysis', $bigrams, $metaData, $headings);
    $uniReport = $this->buildConsistencyReportForType('Unigrams Keywords Analysis', $unigrams, $metaData, $headings);
    
    // Construct the tab layout.
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
            <div class="mt-2">
                <strong>Trigrams Consistency Score:</strong> {$triReport['score']}%<br>
                <em>Recommendation:</em> {$triReport['recommendation']}
            </div>
        </div>
        <div class="tab-pane fade" id="bigrams-pane" role="tabpanel" aria-labelledby="bigrams-tab">
            {$bigramTable}
            <div class="mt-2">
                <strong>Bigrams Consistency Score:</strong> {$biReport['score']}%<br>
                <em>Recommendation:</em> {$biReport['recommendation']}
            </div>
        </div>
        <div class="tab-pane fade" id="unigrams-pane" role="tabpanel" aria-labelledby="unigrams-tab">
            {$unigramTable}
            <div class="mt-2">
                <strong>Unigrams Consistency Score:</strong> {$uniReport['score']}%<br>
                <em>Recommendation:</em> {$uniReport['recommendation']}
            </div>
        </div>
    </div>
    
</div>
HTML;
    return $output;
}



private function buildConsistencyTable($label, $ngrams, $metaData, $headings): string {
    // Extract meta title and description (using 'raw' if available)
    $metaTitle = '';
    $metaDesc  = '';
    if (isset($metaData['raw'])) {
        $metaTitle = $metaData['raw']['title'] ?? '';
        $metaDesc  = $metaData['raw']['description'] ?? '';
    } else {
        $metaTitle = $metaData['title'] ?? '';
        $metaDesc  = $metaData['description'] ?? '';
    }
    
    // Define a suffix for the hide/show classes based on the n-gram type
    $suffix = '';
    if (stripos($label, 'Trigrams') !== false) {
        $suffix = 'TrigramsKeywords';
    } elseif (stripos($label, 'Bigrams') !== false) {
        $suffix = 'BigramsKeywords';
    } elseif (stripos($label, 'Unigrams') !== false) {
        $suffix = 'UnigramsKeywords';
    }
    
    $rows = '';
    $hideCount = 1;
    foreach ($ngrams as $item) {
        $phrase  = $item['phrase'];
        $count   = $item['count'];
        $density = $item['density'] ?? 0;
        $inTitle   = (stripos($metaTitle, $phrase) !== false);
        $inDesc    = (stripos($metaDesc, $phrase) !== false);
        $inHeading = false;
        foreach ($headings as $tag => $texts) {
            foreach ($texts as $text) {
                if (!is_string($text)) {
                    continue;
                }
                if (stripos($text, $phrase) !== false) {
                    $inHeading = true;
                    break 2;
                }
            }
        }
        // Hide rows beyond the first 20 by adding CSS classes.
        $hideClass = ($hideCount > 20) ? 'd-none hideTr' . $suffix : '';
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
    
    // Build the table HTML with a responsive container.
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
    
    // If more than 20 rows exist, add the "Show More" and "Show Less" buttons.
    if ($hideCount > 20) {
        $output .= '<div class="mt-2 text-center">
            <button type="button" class="showMore' . $suffix . ' btn btn-outline-secondary btn-sm">
                Show More <i class="fa fa-angle-double-down"></i>
            </button>
            <button type="button" class="showLess' . $suffix . ' btn btn-outline-secondary btn-sm d-none">
                Show Less
            </button>
        </div>';
    }
    
    $output .= '</div></div>';
    
    return $output;
}



/**
 * Displays the keyword cloud and consistency report from the saved JSON data.
 *
 * @param string $savedJson JSON string saved in the DB.
 * @return string HTML output for the keyword cloud and consistency report.
 */
public function showKeyCloudAndConsistency(string $savedJson): string {
    // Decode the saved JSON data.
    $data = json_decode($savedJson, true);
    if (!is_array($data)) {
        return '<div class="alert alert-warning">No keyword consistency data available.</div>';
    }
    
    // Build the keyword cloud part using the helper.
    $keywordCloudHtml = $this->buildKeyCloudHtml([
        'outCount'    => $data['outCount'] ?? 0,
        'keyDataHtml' => $data['keyDataHtml'] ?? ''
    ]);
    
    // Build the consistency report using your helper.
    // We assume that you saved the meta data and headings with the cloud.
    $consistencyHtml = $this->showKeyConsistencyNgramsTabs(
        $data['fullCloud'] ?? [],
        $data['metaData'] ?? [],
        $data['headings'] ?? []
    );
    
    // Retrieve the suggestion comment from the report if available.
    $suggestion = '';
    if (isset($data['report']) && isset($data['report']['comment'])) {
        $suggestion = '<div class="mt-3">
                          <div class="alert alert-secondary text-center" role="alert">
                            <strong>Suggestion:</strong> ' . htmlspecialchars($data['report']['comment']) . '
                          </div>
                       </div>';
    }
    
    // Concatenate the outputs.
    return $keywordCloudHtml . $consistencyHtml . $suggestion;
}

    



    /*===================================================================
     * TEXT RATIO HANDLER
     *=================================================================== 
     */
   /**
 * Processes the text-to-HTML ratio, computes raw metrics as well as a score report,
 * updates the database, and stores the complete report in a session array.
 *
 * @return string JSON-encoded combined raw data and report.
 */
public function processTextRatio(): string {
    $url = $this->scheme . "://" . $this->host;
    $textRatioData = $this->calculateTextHtmlRatioExtended($url);
    $rawData = $textRatioData['text_html_ratio'] ?? [];
    
    // Extract required values from raw data.
    $ratio = $rawData['ratio_percent'] ?? 0;
    $loadTime = $rawData['load_time_seconds'] ?? 0;
    $wordCount = $rawData['word_count'] ?? 0;
    $htmlSize = $rawData['html_size_bytes'] ?? 0;
    $httpCode = $rawData['http_response_code'] ?? 0;
    
    // --- Condition A: Text Ratio ---
    if ($ratio < 10) {
        $condAStatus = "Error";
        $condAScore = 0;
    } elseif ($ratio < 20) {
        $condAStatus = "To Improve";
        $condAScore = 1;
    } elseif ($ratio <= 50) {
        $condAStatus = "Pass";
        $condAScore = 2;
    } else {
        $condAStatus = "To Improve";
        $condAScore = 1;
    }
    
    // --- Condition B: Load Time (seconds) ---
    if ($loadTime < 1) {
        $condBStatus = "Pass";
        $condBScore = 2;
    } elseif ($loadTime < 3) {
        $condBStatus = "To Improve";
        $condBScore = 1;
    } else {
        $condBStatus = "Error";
        $condBScore = 0;
    }
    
    // --- Condition C: Word Count ---
    if ($wordCount < 200) {
        $condCStatus = "Error";
        $condCScore = 0;
    } elseif ($wordCount < 500) {
        $condCStatus = "To Improve";
        $condCScore = 1;
    } else {
        $condCStatus = "Pass";
        $condCScore = 2;
    }
    
    // --- Condition D: HTTP Response Code ---
    if ($httpCode == 200) {
        $condDStatus = "Pass";
        $condDScore = 2;
    } else {
        $condDStatus = "Error";
        $condDScore = 0;
    }
    
    // --- Condition E: HTML Size (in bytes) ---
    // Thresholds: less than 300KB is Pass, 300-600KB is To Improve, above 600KB is Error.
    if ($htmlSize < (300 * 1024)) {
        $condEStatus = "Pass";
        $condEScore = 2;
    } elseif ($htmlSize < (600 * 1024)) {
        $condEStatus = "To Improve";
        $condEScore = 1;
    } else {
        $condEStatus = "Error";
        $condEScore = 0;
    }
    
    // Total score (max 10 points).
    $totalScore = $condAScore + $condBScore + $condCScore + $condDScore + $condEScore;
    $maxPoints = 10;
    $scorePercent = $maxPoints > 0 ? round(($totalScore / $maxPoints) * 100) : 0;
    
    // Count conditions.
    $passedCount = 0;
    $improveCount = 0;
    $errorCount = 0;
    $conditions = [
        'Text Ratio' => $condAStatus,
        'Load Time'  => $condBStatus,
        'Word Count' => $condCStatus,
        'HTTP Response' => $condDStatus,
        'HTML Size'  => $condEStatus,
    ];
    foreach ($conditions as $status) {
        if ($status === "Pass") {
            $passedCount++;
        } elseif ($status === "To Improve") {
            $improveCount++;
        } else {
            $errorCount++;
        }
    }
    
    // --- Detailed suggestions per condition ---
    if ($condAStatus === "Pass") {
        $suggestA = "Text ratio is within the optimal range.";
    } elseif ($condAStatus === "To Improve") {
        $suggestA = "Text ratio is borderline; consider increasing quality content or reducing excess markup.";
    } else {
        $suggestA = "Text ratio is extremely low; add more quality textual content and reduce unnecessary HTML.";
    }
    
    if ($condBStatus === "Pass") {
        $suggestB = "Load time is optimal.";
    } elseif ($condBStatus === "To Improve") {
        $suggestB = "Load time is slightly high; optimize images or scripts to improve performance.";
    } else {
        $suggestB = "Load time is very slow; consider major performance optimizations.";
    }
    
    if ($condCStatus === "Pass") {
        $suggestC = "Word count is sufficient.";
    } elseif ($condCStatus === "To Improve") {
        $suggestC = "Word count is below optimal; adding more high-quality text could help.";
    } else {
        $suggestC = "Word count is too low; significantly increase textual content for better SEO and user engagement.";
    }
    
    if ($condDStatus === "Pass") {
        $suggestD = "HTTP response is successful.";
    } else {
        $suggestD = "HTTP response error detected; check the page for accessibility issues.";
    }
    
    if ($condEStatus === "Pass") {
        $suggestE = "HTML size is within an acceptable range.";
    } elseif ($condEStatus === "To Improve") {
        $suggestE = "HTML size is borderline; consider cleaning up unnecessary code or markup.";
    } else {
        $suggestE = "HTML size is too large; optimize or minify HTML to reduce bloat.";
    }
    
    $detailedSuggestions = [
        "Text Ratio" => $suggestA,
        "Load Time"  => $suggestB,
        "Word Count" => $suggestC,
        "HTTP Response" => $suggestD,
        "HTML Size"  => $suggestE,
    ];
    
    // Overall comment based on the overall percentage.
    if ($scorePercent >= 90) {
        $overallComment = "Excellent technical SEO performance regarding text content.";
    } elseif ($scorePercent >= 70) {
        $overallComment = "Good performance, though some areas could be improved.";
    } elseif ($scorePercent >= 50) {
        $overallComment = "Average performance; review the detailed suggestions for improvements.";
    } else {
        $overallComment = "Poor technical SEO performance; significant improvements are needed.";
    }
    
    // Build the final report array.
    $report = [
        'score'     => $totalScore,
        'max_points'=> $maxPoints,
        'percent'   => $scorePercent,
        'passed'    => $passedCount,
        'improve'   => $improveCount,
        'errors'    => $errorCount,
        'details'   => $conditions,
        'detailed_suggestions' => $detailedSuggestions,
        'comment'   => $overallComment
    ];
    
    // Combine raw data and report.
    $completeTextRatioData = [
        'raw' => $rawData,
        'report' => $report
    ];
    
    $textRatioJson = jsonEncode($completeTextRatioData);
    updateToDbPrepared($this->con, 'domains_data', ['ratio_data' => $textRatioJson], ['domain' => $this->domainStr]);
    
    // Save the report in a session array.
    if (!isset($_SESSION['seoReports'])) {
        $_SESSION['seoReports'] = [];
    }
    $_SESSION['report_data']['textRatio'] = $completeTextRatioData;
    
    return $textRatioJson;
}

/**
 * Displays the text ratio analysis in a user-friendly layout.
 *
 * Shows a detailed table of the raw metrics along with an overall summary
 * of the computed score, condition statuses, and suggestions.
 *
 * @param string $textRatio JSON-encoded text ratio data.
 * @return string HTML output.
 */
public function showTextRatio($textRatio): string {
    $data = jsonDecode($textRatio);
    if (!is_array($data)) {
        return '<div class="alert alert-danger">' . htmlspecialchars($data) . '</div>';
    }
    
    $raw = $data['raw'] ?? [];
    $report = $data['report'] ?? [];
    
    // Build the raw metrics table.
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
                  <td>' . formatBytes($raw['html_size_bytes'] ?? 0) . '</td>
                  <td>Total size of the HTML source.</td>
              </tr>
              <tr>
                  <td>Text Size (bytes)</td>
                  <td>' . formatBytes($raw['text_size_bytes'] ?? 0) . '</td>
                  <td>Total size of visible text.</td>
              </tr>
              <tr>
                  <td>Text Ratio (%)</td>
                  <td>' . ($raw['ratio_percent'] ?? 0) . '%</td>
                  <td>Percentage of text compared to total HTML.</td>
              </tr>
              <tr>
                  <td>Ratio Category</td>
                  <td>' . ($raw['ratio_category'] ?? 'N/A') . '</td>
                  <td>Indicates if the page is HTML-heavy, Balanced, or Text-heavy.</td>
              </tr>
              <tr>
                  <td>Word Count</td>
                  <td>' . ($raw['word_count'] ?? 0) . '</td>
                  <td>Total number of words in visible text.</td>
              </tr>
              <tr>
                  <td>Estimated Reading Time</td>
                  <td>' . ($raw['estimated_reading_time'] ?? 0) . ' min</td>
                  <td>Approximate time to read the page.</td>
              </tr>
              <tr>
                  <td>Load Time</td>
                  <td>' . ($raw['load_time_seconds'] ?? 0) . ' sec</td>
                  <td>Time taken to fetch the HTML.</td>
              </tr>
              <tr>
                  <td>Total HTML Tags</td>
                  <td>' . ($raw['total_html_tags'] ?? 0) . '</td>
                  <td>Count of all HTML tags.</td>
              </tr>
              <tr>
                  <td>Total Links</td>
                  <td>' . ($raw['total_links'] ?? 0) . '</td>
                  <td>Number of hyperlink tags.</td>
              </tr>
              <tr>
                  <td>Total Images</td>
                  <td>' . ($raw['total_images'] ?? 0) . '</td>
                  <td>Number of image tags.</td>
              </tr>
              <tr>
                  <td>Total Scripts</td>
                  <td>' . ($raw['total_scripts'] ?? 0) . '</td>
                  <td>Number of script tags.</td>
              </tr>
              <tr>
                  <td>Total Styles</td>
                  <td>' . ($raw['total_styles'] ?? 0) . '</td>
                  <td>Number of style tags.</td>
              </tr>
              <tr>
                  <td>HTTP Response Code</td>
                  <td>' . ($raw['http_response_code'] ?? 0) . '</td>
                  <td>Status code received when fetching the page.</td>
              </tr>
          </tbody>
      </table>
    </div>';
    
    // Build a detailed suggestions list from the report's detailed_suggestions.
    $detailedSugHtml = '';
    if (isset($report['detailed_suggestions']) && is_array($report['detailed_suggestions'])) {
        $detailedSugHtml .= '<div class="mt-3"><h5>Detailed Suggestions:</h5><ul>';
        foreach ($report['detailed_suggestions'] as $cond => $sug) {
            $detailedSugHtml .= '<li><strong>' . htmlspecialchars($cond) . ":</strong> " . htmlspecialchars($sug) . '</li>';
        }
        $detailedSugHtml .= '</ul></div>';
    }
    
    // Build overall summary.
    $summary = '<div class="alert alert-info mt-4">';
    $summary .= '<strong>Overall Text Ratio Score:</strong> ' . ($report['percent'] ?? 0) . '%<br>';
    $summary .= '<strong>Score:</strong> ' . ($report['score'] ?? 0) . ' out of ' . ($report['max_points'] ?? 0) . '<br>';
    $summary .= '<strong>Condition Status:</strong><br>';
    if (isset($report['details']) && is_array($report['details'])) {
        foreach ($report['details'] as $cond => $status) {
            $summary .= htmlspecialchars($cond) . ': ' . htmlspecialchars($status) . '<br>';
        }
    }
    $summary .= '<strong>Overall Comment:</strong> ' . htmlspecialchars($report['comment'] ?? '') . '<br>';
    $summary .= '</div>';
    
    // Assemble final output.
    $output = '
    <div id="ajaxTextRatio" class="card mb-3">
        <div class="card-header">
            <h4>' . $this->lang['AN36'] . ': <strong>' . ($raw['ratio_percent'] ?? 0) . '%</strong> (' . ($raw['ratio_category'] ?? 'N/A') . ')</h4>
        </div>
        <div class="card-body">
            <p class="mb-3">
                This metric indicates the percentage of visible text relative to the total HTML markup. A balanced ratio is key for readability and SEO.
            </p>
            ' . $table . '
            ' . $detailedSugHtml . '
        </div>
        <div class="card-footer">
            <small>Consider optimizing your page by reducing unnecessary markup or adding quality textual content.</small>
        </div>
    </div>' . $summary;
    
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
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_NOBODY, true); // We don't need the body
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        // For testing, disable SSL verification (remove these lines in production)
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $response = curl_exec($ch);
        if ($response === false) {
            // Log the error message to your PHP error log.
            $error = curl_error($ch);
            error_log("Curl error for URL ($url): $error");
            curl_close($ch);
            return 0;
        }
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return (int)$httpCode;
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
    /**
 * Processes in-page links by extracting various metrics and computing a link analysis report.
 * The returned structure follows the same pattern as processMeta() and processHeading():
 * it contains a 'raw' key (with all metrics) and a 'report' key (with score, passes, to improve, errors, details, and a comment).
 * The complete JSON is saved in the DB (in the "links_analyser" column) and in a session variable.
 *
 * @return string JSON-encoded combined raw data and report.
 */
public function processInPageLinks(): string {
    // Use pre-loaded DOMDocument if available.
    if (isset($this->dom) && $this->dom instanceof DOMDocument) {
        $doc = $this->dom;
    } else {
        $doc = new DOMDocument();
        @$doc->loadHTML(mb_convert_encoding($this->html, 'HTML-ENTITIES', 'UTF-8'));
        $this->dom = $doc;
    }
    
    // Initialize arrays and counters.
    $internalLinks = [];
    $externalLinks = [];
    $uniqueLinkSet = [];
    $externalDomainSet = [];
    
    $totalTargetBlank = 0;
    $totalHttps = 0;
    $totalHttp = 0;
    $totalTracking = 0;
    $totalTextLength = 0;
    $totalImageLinks = 0;
    $totalNoFollow = 0;
    $totalDoFollow = 0;
    $totalEmptyLinks = 0;
    
    // Count links by position (for potential use).
    $linksByPosition = [
        'header'  => 0,
        'nav'     => 0,
        'main'    => 0,
        'footer'  => 0,
        'aside'   => 0,
        'section' => 0,
        'body'    => 0,
    ];
    
    $baseUrl = $this->scheme . "://" . $this->host;
    $myHost = strtolower($this->urlParse['host']);
    
    // Loop through all anchor tags.
    $anchors = $doc->getElementsByTagName('a');
    foreach ($anchors as $a) {
        $rawHref = trim($a->getAttribute('href'));
        if ($rawHref === "" || $rawHref === "#") {
            continue;
        }
        $href = $rawHref;
        $rel = strtolower($a->getAttribute('rel'));
        $target = strtolower($a->getAttribute('target'));
        $anchorText = trim(strip_tags($a->textContent));
        if ($anchorText === "") {
            $totalEmptyLinks++;
        }
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
        $position = $this->getLinkPosition($a->parentNode);
        if (isset($linksByPosition[$position])) {
            $linksByPosition[$position]++;
        } else {
            $linksByPosition['body']++;
        }
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
                'href'        => $href,
                'follow_type' => $followType,
                'target'      => $target,
                'innertext'   => $anchorText,
                'rel'         => $rel
            ];
            if (isset($parsed['host'])) {
                $externalDomainSet[strtolower($parsed['host'])] = true;
            }
        }
        $uniqueLinkSet[$href] = true;
    }
    
    // Prepare raw metrics.
    $rawData = [
        'total_links'                   => count($internalLinks) + count($externalLinks),
        'total_internal_links'          => count($internalLinks),
        'total_external_links'          => count($externalLinks),
        'unique_links_count'            => count($uniqueLinkSet),
        'total_nofollow_links'          => $totalNoFollow,
        'total_dofollow_links'          => $totalDoFollow,
        'percentage_nofollow_links'     => (count($internalLinks)+count($externalLinks)) > 0 ? round(($totalNoFollow / (count($internalLinks)+count($externalLinks)))*100, 2) : 0,
        'percentage_dofollow_links'     => (count($internalLinks)+count($externalLinks)) > 0 ? round(($totalDoFollow / (count($internalLinks)+count($externalLinks)))*100, 2) : 0,
        'total_target_blank_links'      => $totalTargetBlank,
        'total_image_links'             => $totalImageLinks,
        'total_text_links'              => count($internalLinks),
        'total_empty_links'             => $totalEmptyLinks,
        'external_domains'              => array_keys($externalDomainSet),
        'unique_external_domains_count' => count($externalDomainSet),
        'total_https_links'             => $totalHttps,
        'total_http_links'              => $totalHttp,
        'total_tracking_links'          => $totalTracking,
        'total_non_tracking_links'      => (count($internalLinks)+count($externalLinks)) - $totalTracking,
        'average_anchor_text_length'    => count($internalLinks) > 0 ? round($totalTextLength / count($internalLinks), 2) : 0,
        'link_diversity_score'          => (count($internalLinks)+count($externalLinks)) > 0 ? round(count($uniqueLinkSet) / (count($internalLinks)+count($externalLinks)), 2) : 0,
        // NEW: Save raw external links array for later display.
        'external_links'                => $externalLinks
    ];
    
    // -----------------------------------------------------------------
    // Compute the report following the same logic pattern as meta/heading.
    // We'll define five conditions and assign points (Pass = 2, To Improve = 1, Error = 0).
    // Maximum score is 10.
    // Condition A: At least one link exists.
    $passA = ($rawData['total_links'] > 0) ? 2 : 0;
    $passAStatus = ($rawData['total_links'] > 0) ? 'Pass' : 'Error';
    
    // Condition B: Link Diversity Score >= 0.5 (otherwise, if between 0.3-0.5, to improve).
    if ($rawData['link_diversity_score'] >= 0.5) {
        $passB = 2;
        $passBStatus = 'Pass';
    } elseif ($rawData['link_diversity_score'] >= 0.3) {
        $passB = 1;
        $passBStatus = 'To Improve';
    } else {
        $passB = 0;
        $passBStatus = 'Error';
    }
    
    // Condition C: Average Anchor Text Length: >= 10 = pass; 5-10 = to improve; < 5 = error.
    if ($rawData['average_anchor_text_length'] >= 10) {
        $passC = 2;
        $passCStatus = 'Pass';
    } elseif ($rawData['average_anchor_text_length'] >= 5) {
        $passC = 1;
        $passCStatus = 'To Improve';
    } else {
        $passC = 0;
        $passCStatus = 'Error';
    }
    
    // Condition D: Empty Link Ratio (empty_ratio = total_empty_links / total_links).
    $emptyRatio = ($rawData['total_links'] > 0) ? $rawData['total_empty_links'] / $rawData['total_links'] : 1;
    if ($emptyRatio <= 0.1) {
        $passD = 2;
        $passDStatus = 'Pass';
    } elseif ($emptyRatio <= 0.2) {
        $passD = 1;
        $passDStatus = 'To Improve';
    } else {
        $passD = 0;
        $passDStatus = 'Error';
    }
    
    // Condition E: Tracking Links: Ideally, zero tracking links.
    $passE = ($rawData['total_tracking_links'] == 0) ? 2 : 0;
    $passEStatus = ($rawData['total_tracking_links'] == 0) ? 'Pass' : 'Error';
    
    $totalScore = $passA + $passB + $passC + $passD + $passE;
    $maxScore = 10;
    $scorePercent = ($maxScore > 0) ? round(($totalScore / $maxScore) * 100) : 0;
    
    // Build an overall comment.
    $comment = "";
    if ($rawData['total_links'] == 0) {
        $comment .= "No links found on the page. ";
    } else {
        $comment .= "Links extracted successfully. ";
    }
    if ($passBStatus !== 'Pass') {
        $comment .= "Link diversity is low. ";
    }
    if ($passCStatus !== 'Pass') {
        $comment .= "Average anchor text length is below optimal. ";
    }
    if ($passDStatus !== 'Pass') {
        $comment .= "A high proportion of links have empty anchor text. ";
    }
    if ($passEStatus !== 'Pass') {
        $comment .= "Tracking parameters are detected in some links. ";
    }
    
    // Prepare a details summary.
    $conditions = [
        'Total Links' => $passAStatus,
        'Diversity Score' => $passBStatus,
        'Anchor Text Length' => $passCStatus,
        'Empty Link Ratio' => $passDStatus,
        'Tracking Links' => $passEStatus
    ];
    
    $report = [
        'score'   => $totalScore,
        'passed'  => ($passAStatus==='Pass' ? 1 : 0) + ($passBStatus==='Pass' ? 1 : 0) + ($passCStatus==='Pass' ? 1 : 0) + ($passDStatus==='Pass' ? 1 : 0) + ($passEStatus==='Pass' ? 1 : 0),
        'improve' => ($passBStatus==='To Improve' ? 1 : 0) + ($passCStatus==='To Improve' ? 1 : 0) + ($passDStatus==='To Improve' ? 1 : 0),
        'errors'  => ($passAStatus==='Error' ? 1 : 0) + ($passBStatus==='Error' ? 1 : 0) + ($passCStatus==='Error' ? 1 : 0) + ($passDStatus==='Error' ? 1 : 0) + ($passEStatus==='Error' ? 1 : 0),
        'percent' => $scorePercent,
        'details' => $conditions,
        'comment' => $comment
    ];
    
    // Combine raw data and report.
    $completeLinkData = [
        'raw'    => $rawData,
        'report' => $report
    ];
    
    $reportJson = json_encode($completeLinkData);
    updateToDbPrepared($this->con, 'domains_data', ['links_analyser' => $reportJson], ['domain' => $this->domainStr]);
    $_SESSION['report_data']['linksReport'] = $completeLinkData;
    return $reportJson;
}

public function showInPageLinks($linksData): string {
    // 1) Decode if $linksData is a JSON string.
    if (is_string($linksData)) {
        $linksData = json_decode($linksData, true);
    }
    if (!is_array($linksData)) {
        return '<div class="alert alert-danger">Invalid link data provided.</div>';
    }
    
    // 2) Extract raw metrics and computed report.
    $raw = $linksData['raw'] ?? [];
    $report = $linksData['report'] ?? [];
    
    // 3) Build a table of raw metrics.
    $rawMetrics = [
        'Total Links'                   => $raw['total_links'] ?? 'N/A',
        'Internal Links'                => $raw['total_internal_links'] ?? 'N/A',
        'External Links'                => $raw['total_external_links'] ?? 'N/A',
        'Unique Links'                  => $raw['unique_links_count'] ?? 'N/A',
        'Empty Links'                   => $raw['total_empty_links'] ?? 'N/A',
        'Link Diversity Score'          => $raw['link_diversity_score'] ?? 'N/A',
        'Dofollow Links'                => isset($raw['total_dofollow_links'], $raw['percentage_dofollow_links']) ? $raw['total_dofollow_links'] . ' (' . $raw['percentage_dofollow_links'] . '%)' : 'N/A',
        'Nofollow Links'                => isset($raw['total_nofollow_links'], $raw['percentage_nofollow_links']) ? $raw['total_nofollow_links'] . ' (' . $raw['percentage_nofollow_links'] . '%)' : 'N/A',
        'Target Blank Links'            => $raw['total_target_blank_links'] ?? 'N/A',
        'HTTPS Links'                   => $raw['total_https_links'] ?? 'N/A',
        'HTTP Links'                    => $raw['total_http_links'] ?? 'N/A',
        'Total Image Links'             => $raw['total_image_links'] ?? 'N/A',
        'Total Text Links'              => $raw['total_text_links'] ?? 'N/A',
        'Avg. Anchor Text Length'       => $raw['average_anchor_text_length'] ?? 'N/A',
        'Total Tracking Links'          => $raw['total_tracking_links'] ?? 'N/A',
        'Non-Tracking Links'            => $raw['total_non_tracking_links'] ?? 'N/A',
        'Unique External Domains'       => $raw['unique_external_domains_count'] ?? 'N/A'
    ];
    
    $rawTableRows = '';
    foreach ($rawMetrics as $metric => $value) {
        $rawTableRows .= "<tr><td>{$metric}</td><td>{$value}</td></tr>";
    }
    $rawTable = '<table class="table table-bordered table-sm">
                    <thead>
                        <tr>
                            <th>Metric</th>
                            <th>Value</th>
                        </tr>
                    </thead>
                    <tbody>' . $rawTableRows . '</tbody>
                 </table>';
    
    // 4) Build the detailed report card.
    // Define human-friendly messages for each condition.
    $conditionMessages = [
        'Total Links' => [
            'Pass' => 'The page has sufficient links.',
            'Error' => 'No links found on the page.'
        ],
        'Diversity Score' => [
            'Pass' => 'Link diversity is healthy.',
            'To Improve' => 'Link diversity could be improved.',
            'Error' => 'Link diversity is critically low.'
        ],
        'Anchor Text Length' => [
            'Pass' => 'Average anchor text length is within the optimal range.',
            'To Improve' => 'Anchor text length is lower than optimal. Consider adding more descriptive anchor texts.',
            'Error' => 'Anchor text is too short, which may hurt SEO.'
        ],
        'Empty Link Ratio' => [
            'Pass' => 'Very few links have empty anchor text.',
            'To Improve' => 'Some links have empty anchor text. Consider adding descriptive text.',
            'Error' => 'A high proportion of links have empty anchor text. It is recommended to provide descriptive anchor texts.'
        ],
        'Tracking Links' => [
            'Pass' => 'No tracking parameters are detected in the links.',
            'Error' => 'Tracking parameters are found, which might affect user privacy and link value.'
        ]
    ];
    
    // We'll iterate through the conditions in the report details.
    $detailsHtml = '';
    $suggestionsList = [];
    if (isset($report['details']) && is_array($report['details'])) {
        foreach ($report['details'] as $condition => $status) {
            $statusClean = ucfirst(trim($status));
            $message = isset($conditionMessages[$condition][$statusClean])
                        ? $conditionMessages[$condition][$statusClean]
                        : $statusClean;
            $detailsHtml .= htmlspecialchars($condition) . ': ' . htmlspecialchars($message) . '<br>';
            if ($statusClean !== 'Pass') {
                $suggestionsList[] = $message;
            }
        }
    }
    
    $reportHtml = '<div class="card mb-3">';
    $reportHtml .= '<div class="card-header"><strong>Detailed Link Analysis Report</strong></div>';
    $reportHtml .= '<div class="card-body">';
    $reportHtml .= '<p><strong>Score:</strong> ' . ($report['score'] ?? 0) . ' (Percentage: ' . ($report['percent'] ?? 0) . '%)</p>';
    $reportHtml .= '<p><strong>Passed:</strong> ' . ($report['passed'] ?? 0) . ', <strong>To Improve:</strong> ' . ($report['improve'] ?? 0) . ', <strong>Errors:</strong> ' . ($report['errors'] ?? 0) . '</p>';
    $reportHtml .= '<p><strong>Condition Status:</strong><br>' . $detailsHtml . '</p>';
    $reportHtml .= '<p><strong>Overall Comment:</strong> ' . ($report['comment'] ?? '') . '</p>';
    $reportHtml .= '</div></div>';
    
    // 5) Process external links: use the "external_links" key from raw data.
    $externalLinksRaw = $raw['external_links'] ?? [];
    $uniqueExternalLinks = [];
    foreach ($externalLinksRaw as $ext) {
        $href = $ext['href'];
        if (!isset($uniqueExternalLinks[$href])) {
            $uniqueExternalLinks[$href] = $ext;
            $uniqueExternalLinks[$href]['count'] = 1;
        } else {
            $uniqueExternalLinks[$href]['count']++;
        }
    }
    
    // Build External Links table.
    $externalLinksTable = '';
    if (!empty($uniqueExternalLinks)) {
        $externalLinksTable .= '<div class="table-responsive"><table class="table table-striped table-hover">';
        $externalLinksTable .= '<thead class="table-dark"><tr><th>Link</th><th>Follow Type</th><th>Anchor Text</th><th>Count</th></tr></thead><tbody>';
        foreach ($uniqueExternalLinks as $ext) {
            $displayText = !empty($ext['innertext']) ? $ext['innertext'] : $ext['href'];
            $externalLinksTable .= '<tr>';
            $externalLinksTable .= '<td>' . htmlspecialchars($ext['href']) . '</td>';
            $externalLinksTable .= '<td>' . htmlspecialchars($ext['follow_type']) . '</td>';
            $externalLinksTable .= '<td>' . htmlspecialchars($displayText) . '</td>';
            $externalLinksTable .= '<td>' . $ext['count'] . '</td>';
            $externalLinksTable .= '</tr>';
        }
        $externalLinksTable .= '</tbody></table></div>';
    } else {
        $externalLinksTable = '<div class="alert alert-info">No external links found.</div>';
    }
    
    // 6) Build External Domains list.
    $externalDomains = $raw['external_domains'] ?? [];
    $domainsHtml = '';
    if (!empty($externalDomains)) {
        $domainsHtml .= '<ul class="list-group">';
        foreach ($externalDomains as $domain) {
            $domainsHtml .= '<li class="list-group-item">' . htmlspecialchars($domain) . '</li>';
        }
        $domainsHtml .= '</ul>';
    } else {
        $domainsHtml = '<div class="alert alert-info">No external domains found.</div>';
    }
    
    // 7) Build the nav tabs and tab content.
    $html = '<div class="container my-4">';
    
    // Nav Tabs.
    $html .= '<ul class="nav nav-tabs" id="linkReportTabs" role="tablist">';
    $html .= '  <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="summary-tab" data-bs-toggle="tab" data-bs-target="#summaryTab" type="button" role="tab" aria-controls="summaryTab" aria-selected="true">
                        Summary
                    </button>
                </li>';
    $html .= '  <li class="nav-item" role="presentation">
                    <button class="nav-link" id="externalLinks-tab" data-bs-toggle="tab" data-bs-target="#externalLinksTab" type="button" role="tab" aria-controls="externalLinksTab" aria-selected="false">
                        External Links
                    </button>
                </li>';
    $html .= '  <li class="nav-item" role="presentation">
                    <button class="nav-link" id="externalDomains-tab" data-bs-toggle="tab" data-bs-target="#externalDomainsTab" type="button" role="tab" aria-controls="externalDomainsTab" aria-selected="false">
                        External Domains
                    </button>
                </li>';
    $html .= '</ul>';
    
    // Tab Content.
    $html .= '<div class="tab-content" id="linkReportTabsContent">';
    
    // Tab 1: Summary.
    $html .= '<div class="tab-pane fade show active" id="summaryTab" role="tabpanel" aria-labelledby="summary-tab">';
    $html .= '  <div class="card my-3">';
    $html .= '    <div class="card-header"><h5>Raw Metrics</h5></div>';
    $html .= '    <div class="card-body">' . $rawTable . '</div>';
    $html .= '  </div>';
    $html .= $reportHtml; // Detailed report card appended below.
    // Also, if there are suggestions gathered from conditions, show them.
    if (!empty($suggestionsList)) {
        $html .= '<div class="alert alert-warning mt-3"><strong>Suggestions:</strong><ul>';
        foreach ($suggestionsList as $sug) {
            $html .= '<li>' . htmlspecialchars($sug) . '</li>';
        }
        $html .= '</ul></div>';
    }
    $html .= '</div>';
    
    // Tab 2: External Links.
    $html .= '<div class="tab-pane fade" id="externalLinksTab" role="tabpanel" aria-labelledby="externalLinks-tab">';
    $html .= '  <div class="card my-3">';
    $html .= '    <div class="card-header"><h5>Unique External Links</h5></div>';
    $html .= '    <div class="card-body">' . $externalLinksTable . '</div>';
    $html .= '  </div>';
    $html .= '</div>';
    
    // Tab 3: External Domains.
    $html .= '<div class="tab-pane fade" id="externalDomainsTab" role="tabpanel" aria-labelledby="externalDomains-tab">';
    $html .= '  <div class="card my-3">';
    $html .= '    <div class="card-header"><h5>External Domains</h5></div>';
    $html .= '    <div class="card-body">' . $domainsHtml . '</div>';
    $html .= '  </div>';
    $html .= '</div>';
    
    $html .= '</div>'; // End tab-content.
    
  
    
    $html .= '</div>'; // End container.
    
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
    /**
 * Processes the site cards by extracting meta tags for each social platform,
 * then computes a score/report for each platform. The final output is a JSON‑encoded
 * structure with keys:
 *    - raw: the full raw meta data grouped by platform.
 *    - report: a computed report that contains, for each platform, the score (Pass =2, To Improve =1, Error =0),
 *              percentage of required tags present, status ("Pass", "To Improve", "Error"), list of missing tags,
 *              and a suggestion. It also includes an overall score computed as an average.
 *
 * This JSON is then updated into the database (in the "sitecards" column) and stored in the session.
 *
 * @return string JSON-encoded combined raw data and report.
 */
public function processSiteCards(): string {
    // Get the DOM from stored HTML.
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
    
    // Iterate through all meta tags.
    foreach ($metaTags as $tag) {
        // Process tags that use "property" (typically Open Graph).
        if ($tag->hasAttribute('property')) {
            $property = $tag->getAttribute('property');
            $content  = $tag->getAttribute('content');
            if (strpos($property, 'og:') === 0) {
                // These tags are used for Facebook, LinkedIn, Pinterest, WhatsApp, and Discord.
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
                // Assign Twitter meta tags to the 'x' key.
                $cards['x'][$name] = $content;
            }
            if (strpos($name, 'google:') === 0) {
                $cards['google'][$name] = $content;
            }
        }
    }
    
    // Ensure keys expected for preview exist.
    if (!isset($cards['x']['twitter:url'])) {
        $cards['x']['twitter:url'] = '';
    }
    if (!isset($cards['google']['google:url'])) {
        $cards['google']['google:url'] = '';
    }
    
    /*
     * Now calculate a score/report for each platform.
     * For each platform, we compare its meta data to a list of required tags.
     * For each tag: if present, count as pass; if missing, note it.
     * We then assign:
     *    - If 100% of required tags are present: status "Pass" (score 2)
     *    - If at least 50% are present: "To Improve" (score 1)
     *    - Otherwise: "Error" (score 0)
     */
    $cardTypes = [
        'facebook' => [
            'label'    => 'FACEBOOK',
            'required' => ['og:title', 'og:description', 'og:image', 'og:url', 'og:type'],
            'preview'  => ['og:title', 'og:description', 'og:image', 'og:url']
        ],
        'x' => [
            'label'    => 'X (FORMERLY TWITTER)',
            'required' => ['twitter:card', 'twitter:title', 'twitter:description', 'twitter:image'],
            'preview'  => ['twitter:title', 'twitter:description', 'twitter:image', 'twitter:url']
        ],
        'linkedin' => [
            'label'    => 'LINKEDIN',
            'required' => ['og:title', 'og:description', 'og:image', 'og:url'],
            'preview'  => ['og:title', 'og:description', 'og:image', 'og:url']
        ],
        'discord' => [
            'label'    => 'DISCORD',
            'required' => ['og:title', 'og:description', 'og:image', 'og:url'],
            'preview'  => ['og:title', 'og:description', 'og:image', 'og:url']
        ],
        'pinterest' => [
            'label'    => 'PINTEREST',
            'required' => ['og:title', 'og:description', 'og:image', 'og:url'],
            'preview'  => ['og:title', 'og:description', 'og:image', 'og:url']
        ],
        'whatsapp' => [
            'label'    => 'WHATSAPP',
            'required' => ['og:title', 'og:description', 'og:image', 'og:url'],
            'preview'  => ['og:title', 'og:description', 'og:image', 'og:url']
        ],
        'google' => [
            'label'    => 'GOOGLE',
            'required' => ['google:title', 'google:description', 'google:image'],
            'preview'  => ['google:title', 'google:description', 'google:image', 'google:url']
        ],
    ];
    
    // We'll compute an individual report per platform.
    $cardReport = [];
    $totalScoreAccum = 0;
    $platformCount = 0;
    
    // Use our helper below to calculate score for a single platform.
    foreach ($cardTypes as $platform => $settings) {
        $required = $settings['required'];
        $platformData = $cards[$platform] ?? [];
        $missing = [];
        $presentCount = 0;
        foreach ($required as $tag) {
            if (isset($platformData[$tag]) && trim($platformData[$tag]) !== "") {
                $presentCount++;
            } else {
                $missing[] = $tag;
            }
        }
        $totalReq = count($required);
        $percentage = ($totalReq > 0) ? ($presentCount / $totalReq) * 100 : 0;
        if ($percentage == 100) {
            $status = "Pass";
            $score = 2;
            $suggestion = "All required meta tags are present.";
        } elseif ($percentage >= 50) {
            $status = "To Improve";
            $score = 1;
            $suggestion = "Missing tags: " . implode(", ", $missing) . ". Consider adding them.";
        } else {
            $status = "Error";
            $score = 0;
            $suggestion = "Most required tags are missing: " . implode(", ", $missing) . ".";
        }
        $cardReport[$platform] = [
            'label'       => $settings['label'],
            'score'       => $score,
            'percentage'  => round($percentage),
            'status'      => $status,
            'missing'     => $missing,
            'suggestion'  => $suggestion
        ];
        $totalScoreAccum += $score;
        $platformCount++;
    }
    
    // Compute overall average score (each platform has max 2 points).
    $overallPercent = $platformCount > 0 ? round(($totalScoreAccum / ($platformCount * 2)) * 100) : 0;
    if ($overallPercent == 100) {
        $overallStatus = "Excellent";
    } elseif ($overallPercent >= 70) {
        $overallStatus = "Good";
    } elseif ($overallPercent >= 50) {
        $overallStatus = "Average";
    } else {
        $overallStatus = "Poor";
    }
    
    // Collect overall suggestions from platforms that are not "Pass".
    $overallSuggestions = [];
    foreach ($cardReport as $platform => $rep) {
        if ($rep['status'] !== "Pass") {
            $overallSuggestions[] = "{$rep['label']}: " . $rep['suggestion'];
        }
    }
    
    // Prepare complete report.
    $completeCardsData = [
        'raw'    => $cards,
        'report' => [
            'platforms'      => $cardReport,
            'overall_score'  => $overallPercent,
            'overall_status' => $overallStatus,
            'suggestions'    => $overallSuggestions
        ]
    ];
    
    $jsonData = json_encode($completeCardsData);
    updateToDbPrepared($this->con, 'domains_data', ['sitecards' => $jsonData], ['domain' => $this->domainStr]);
    $_SESSION['report_data']['sitecardsReport'] = $completeCardsData;
    
    return $jsonData;
}
    

    
/**
 * Displays the social cards along with their score/report.
 * The display uses Bootstrap tabs for each platform.
 * Each tab shows the platform's preview, a table of required meta tags (highlighting missing ones),
 * and its computed score, status, and suggestion.
 * An overall summary (average score, overall status, and combined suggestions) is shown at the top.
 *
 * @param string $cardsData JSON-encoded site card data and report.
 * @return string HTML output.
 */
public function showCards($cardsData): string {
    $data = jsonDecode($cardsData);
  
    if (!is_array($data)) {
        return '<div class="alert alert-warning">No card data available.</div>';
    }
  
    // Use the same $cardTypes definition as in processSiteCards().
    $cardTypes = [
        'facebook' => [
            'label'    => 'FACEBOOK',
            'required' => ['og:title', 'og:description', 'og:image', 'og:url', 'og:type'],
            'preview'  => ['og:title', 'og:description', 'og:image', 'og:url'],
            'data'     => $data['raw']['facebook'] ?? []
        ],
        'x' => [
            'label'    => 'X (FORMERLY TWITTER)',
            'required' => ['twitter:card', 'twitter:title', 'twitter:description', 'twitter:image'],
            'preview'  => ['twitter:title', 'twitter:description', 'twitter:image', 'twitter:url'],
            'data'     => $data['raw']['x'] ?? []
        ],
        'linkedin' => [
            'label'    => 'LINKEDIN',
            'required' => ['og:title', 'og:description', 'og:image', 'og:url'],
            'preview'  => ['og:title', 'og:description', 'og:image', 'og:url'],
            'data'     => $data['raw']['linkedin'] ?? []
        ],
        'discord' => [
            'label'    => 'DISCORD',
            'required' => ['og:title', 'og:description', 'og:image', 'og:url'],
            'preview'  => ['og:title', 'og:description', 'og:image', 'og:url'],
            'data'     => $data['raw']['discord'] ?? []
        ],
        'pinterest' => [
            'label'    => 'PINTEREST',
            'required' => ['og:title', 'og:description', 'og:image', 'og:url'],
            'preview'  => ['og:title', 'og:description', 'og:image', 'og:url'],
            'data'     => $data['raw']['pinterest'] ?? []
        ],
        'whatsapp' => [
            'label'    => 'WHATSAPP',
            'required' => ['og:title', 'og:description', 'og:image', 'og:url'],
            'preview'  => ['og:title', 'og:description', 'og:image', 'og:url'],
            'data'     => $data['raw']['whatsapp'] ?? []
        ],
        'google' => [
            'label'    => 'GOOGLE',
            'required' => ['google:title', 'google:description', 'google:image'],
            'preview'  => ['google:title', 'google:description', 'google:image', 'google:url'],
            'data'     => $data['raw']['google'] ?? []
        ],
    ];
  
    // Overall report data.
    $overallReport = $data['report'] ?? [];
    $overallScore = $overallReport['overall_score'] ?? 0;
    $overallStatus = $overallReport['overall_status'] ?? '';
    $overallSuggestions = $overallReport['suggestions'] ?? [];
  
    $overallSummary = '<div class="alert alert-info mt-3">
                        <strong>Overall Social Card Score:</strong> ' . $overallScore . '% (' . $overallStatus . ')
                        <br><em>' . (!empty($overallSuggestions) ? implode(" | ", $overallSuggestions) : 'All required meta tags are present.') . '</em>
                       </div>';
  
    // Build tab navigation.
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
    
        // Extract preview data.
        list($titleKey, $descKey, $imgKey, $urlKey) = $card['preview'];
        $cData = $card['data'];
        $title = $cData[$titleKey] ?? '';
        $desc  = $cData[$descKey] ?? '';
        $image = $cData[$imgKey] ?? '';
        $url   = $cData[$urlKey] ?? '';
    
        // Parse domain from URL.
        $domain = '';
        if (!empty($url)) {
            $parsed = parse_url($url);
            $domain = $parsed['host'] ?? '';
        }
    
        // Build preview HTML.
        $previewHtml = $this->buildPlatformPreview($key, $title, $desc, $image, $domain);
    
        // Build meta tags table for required fields.
        $missing = [];
        $tableHtml = '<div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead class="table-light">
                        <tr><th style="width: 40%;">Meta Tag</th><th style="width: 60%;">Value</th></tr>
                    </thead>
                    <tbody>';
        foreach ($cardTypes[$key]['required'] as $tag) {
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
    
        // Retrieve the pre-calculated score for this platform from the overall report.
        $platformReport = $overallReport['platforms'][$key] ?? ['score' => 0, 'status' => 'Error', 'percentage' => 0, 'suggestion' => 'No data'];
        $scoreBox = '<div class="alert alert-' . ($platformReport['score'] == 2 ? 'success' : ($platformReport['score'] == 1 ? 'warning' : 'danger')) . ' mt-2">
                        <strong>Status:</strong> ' . $platformReport['status'] . ' (' . $platformReport['percentage'] . '%)<br>
                        <em>' . $platformReport['suggestion'] . '</em>
                     </div>';
    
        // Combine preview, table and score box.
        $fullContent = '
                <div class="row">
                    <div class="col-md-5 mb-3">' . $previewHtml . '</div>
                    <div class="col-md-7 mb-3">' . $tableHtml . $scoreBox . '</div>
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
                      <div class="card-body">'.$tabsNav .'
                      <div class="tab-content mt-3" id="cardsTabContent">' . $tabsContent . '</div>'. $overallSummary .'
                       </div></div>';
  
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
    

    /**
 * Helper: Calculate the score and report for a given platform's meta data.
 *
 * @param array $cardData    The meta data array for the platform.
 * @param array $requiredTags The list of required meta tags.
 * @return array An array containing:
 *   - score: (int) 2 for pass, 1 for to improve, 0 for error.
 *   - percentage: (int) percentage of required tags present.
 *   - status: A string "Pass", "To Improve", or "Error".
 *   - missing: An array of missing meta tags.
 *   - suggestion: A suggestion message.
 */
private function calculateCardScore(array $cardData, array $requiredTags): array {
    $missing = [];
    $present = [];
    foreach ($requiredTags as $tag) {
        if (isset($cardData[$tag]) && trim($cardData[$tag]) !== "") {
            $present[] = $tag;
        } else {
            $missing[] = $tag;
        }
    }
    $total = count($requiredTags);
    $presentCount = count($present);
    $percentage = ($total > 0) ? ($presentCount / $total) * 100 : 0;
    
    if ($percentage == 100) {
        $status = "Pass";
        $score = 2;
        $suggestion = "All required meta tags are present.";
    } elseif ($percentage >= 50) {
        $status = "To Improve";
        $score = 1;
        $suggestion = "Missing meta tags: " . implode(", ", $missing) . ". Consider adding them.";
    } else {
        $status = "Error";
        $score = 0;
        $suggestion = "Most required meta tags are missing: " . implode(", ", $missing) . ".";
    }
    return [
        'score' => $score,
        'percentage' => round($percentage),
        'status' => $status,
        'missing' => $missing,
        'suggestion' => $suggestion
    ];
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
 * Then, it computes a score report (using six conditions – each worth 2 points, total max = 12)
 * and combines the raw data and report into one JSON. This JSON is saved in the DB and stored
 * in the session for later use.
 *
 * @return string JSON encoded array containing both raw data and the report.
 */
public function processServerInfo(): string {
    // (A) Gather Raw Data (unchanged)
    $scheme = $this->scheme ?? 'http';
    $host = $this->urlParse['host'] ?? '';
    if ($host === '') {
        throw new Exception("Cannot process server info because the host is missing in the URL.");
    }
    $fullUrl = $scheme . "://" . $host;
    $serverIP = gethostbyname($host);
    $dnsRecords = $this->checkDNSRecords($host);
    $ipInfo = $this->checkIP($host);
    $headers = @get_headers($fullUrl, 1) ?: [];
    $serverSignature = isset($headers['Server']) ? $headers['Server'] : 'N/A';
    $sslInfo = $this->checkSSL($host);
    $technologyUsed = $this->detectFromHtml($this->html, $headers, $host);
    $whoisInfo = $this->fetchDomainRdap($host);
    
    $rawData = [
        'dns_records'      => $dnsRecords,
        'server_ip'        => $serverIP,
        'ip_info'          => $ipInfo,
        'server_signature' => $serverSignature,
        'ssl_info'         => $sslInfo,
        'technology_used'  => $technologyUsed,
        'whois_info'       => $whoisInfo
    ];
    
    // (B) Compute Score Report
    // We'll use six conditions (each worth 2 points; total max = 12)
    $maxScore = 12;
    $score = 0;
    $details = [];
    $suggestions = [];
    
    // Condition 1: DNS records exist.
    if (!empty($dnsRecords)) {
        $details['DNS Records'] = 'Pass';
        $score += 2;
    } else {
        $details['DNS Records'] = 'Error';
        $suggestions[] = 'No DNS records found. Verify your domain DNS settings.';
    }
    
    // Condition 2: Valid Server IP detected.
    if (filter_var($serverIP, FILTER_VALIDATE_IP)) {
        $details['Server IP'] = 'Pass';
        $score += 2;
    } else {
        $details['Server IP'] = 'Error';
        $suggestions[] = 'A valid server IP was not detected.';
    }
    
    // Condition 3: IP Geolocation data available.
    if (!empty($ipInfo['geo']) && !isset($ipInfo['geo']['error'])) {
        $details['IP Geolocation'] = 'Pass';
        $score += 2;
    } else {
        $details['IP Geolocation'] = 'Error';
        $suggestions[] = 'Unable to retrieve IP geolocation data.';
    }
    
    // Condition 4: SSL enabled.
    if (isset($sslInfo['has_ssl']) && $sslInfo['has_ssl'] === true) {
        $details['SSL'] = 'Pass';
        $score += 2;
    } else {
        $details['SSL'] = 'Error';
        $suggestions[] = 'SSL is not enabled or certificate information is missing.';
    }
    
    // Condition 5: Technology used detected.
    if (!empty($technologyUsed) && is_array($technologyUsed)) {
        $details['Technology'] = 'Pass';
        $score += 2;
    } else {
        $details['Technology'] = 'Error';
        $suggestions[] = 'No technologies were detected. Ensure your server sends standard headers.';
    }
    
    // Condition 6: Whois info available.
    if (!empty($whoisInfo)) {
        $details['Whois'] = 'Pass';
        $score += 2;
    } else {
        $details['Whois'] = 'Error';
        $suggestions[] = 'Whois data is missing. Verify your domain registration details.';
    }
    
    $percent = ($maxScore > 0) ? round(($score / $maxScore) * 100) : 0;
    if ($score == $maxScore) {
        $overallComment = "Excellent server configuration. All checks passed.";
    } elseif ($score >= 8) {
        $overallComment = "Good server configuration. Minor improvements can be made.";
    } elseif ($score >= 4) {
        $overallComment = "Server configuration needs improvement.";
    } else {
        $overallComment = "Poor server configuration. Immediate attention required.";
    }
    
    $report = [
        'score'       => $score,
        'max_score'   => $maxScore,
        'percent'     => $percent,
        'details'     => $details,
        'suggestions' => $suggestions,
        'comment'     => $overallComment
    ];
    
    // (C) Combine Raw Data and Report, then save
    $combined = [
        'raw'    => $rawData,
        'report' => $report
    ];
    
    $combinedJson = json_encode($combined);
    updateToDbPrepared($this->con, 'domains_data', ['server_loc' => $combinedJson], ['domain' => $this->domainStr]);
    
    // Save to session for later use (e.g., in the cleanout process)
    $_SESSION['report_data']['serverInfo'] = $combined;
    
    return $combinedJson;
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
 * Displays the server information stored in the "server_loc" JSON in a tabbed layout,
 * and appends a Server Score Report based on the computed report.
 *
 * @param string $jsonData JSON-encoded server info (raw data + report) from DB.
 * @return string HTML output.
 */
public function showServerInfo(string $jsonData): string {
    // Decode the combined JSON data (raw data + report)
    $combined = json_decode($jsonData, true);
    if (!is_array($combined)) {
        return '<div class="alert alert-danger">No server information available.</div>';
    }
    
    // Extract raw data and report.
    $data = $combined['raw'] ?? [];
    $report = $combined['report'] ?? [];
    
    // --- Existing Raw Data Tabs (unchanged) ---
    // Helper functions for date formatting & domain age.
    $formatDateFriendly = function ($rawDate) {
        $ts = strtotime($rawDate);
        return ($ts !== false) ? date('M d, Y H:i:s', $ts) : $rawDate;
    };
    $computeDomainAge = function ($rawDate) {
        $ts = strtotime($rawDate);
        if ($ts === false) {
            return ['years' => 'N/A', 'days' => 'N/A'];
        }
        $daysDiff = floor((time() - $ts) / 86400);
        $years = round($daysDiff / 365, 1);
        return ['years' => $years, 'days' => $daysDiff];
    };
    
    // Build DNS Info Accordion.
    $dnsRecords = $data['dns_records'] ?? [];
    $dnsHtml = '<div class="alert alert-info">No DNS records found.</div>';
    if (!empty($dnsRecords)) {
        $dnsByType = [];
        foreach ($dnsRecords as $rec) {
            $type = isset($rec['type']) ? strtoupper($rec['type']) : 'UNKNOWN';
            $dnsByType[$type][] = $rec;
        }
        $dnsHtml = '<div class="accordion" id="dnsAccordion">';
        $i = 0;
        foreach ($dnsByType as $type => $records) {
            $i++;
            $collapseId = "collapseDns{$i}";
            $expanded = 'false';
            $collapseClass = 'accordion-collapse collapse';
            $buttonClass = 'accordion-button collapsed';
            $dnsHtml .= '<div class="accordion-item">
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
                    $dnsHtml .= '<tr>
                          <td>' . htmlspecialchars($fieldKey) . '</td>
                          <td>' . htmlspecialchars((string)$fieldValue) . '</td>
                        </tr>';
                }
                $dnsHtml .= '<tr><td colspan="2" class="bg-light"></td></tr>';
            }
            $dnsHtml .= '</tbody>
                    </table>
                  </div>
                </div>
              </div>';
        }
        $dnsHtml .= '</div>';
    }
    
    // Build WHOIS + Domain Info (Name Servers Tab).
    $whoisInfo = $data['whois_info'] ?? [];
    $rawWhois = '';
    if (is_array($whoisInfo) && isset($whoisInfo['raw_data'])) {
        $rawWhois = trim($whoisInfo['raw_data']);
    } elseif (is_string($whoisInfo)) {
        $rawWhois = trim($whoisInfo);
    }
    $domainName = $this->urlParse['host'] ?? 'N/A';
    $registrar = 'N/A';
    $ianaID = 'N/A';
    $registrarUrl = 'N/A';
    $whoisServer = 'N/A';
    $abuseContact = 'N/A';
    $domainStatus = 'N/A';
    $createdDate = 'N/A';
    $expiryDate = 'N/A';
    $updatedDate = 'N/A';
    $hostedIP = $data['server_ip'] ?? 'N/A';
    $nsRecordsParsed = [];
    $domainAgeYears = 'N/A';
    $domainAgeDays = 'N/A';
    
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
        $domainAgeDays = $age['days'];
    }
    
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
    $expiryDateFriendly = ($expiryDate !== 'N/A') ? $formatDateFriendly($expiryDate) : 'N/A';
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
    
    $serverIP = $data['server_ip'] ?? null;
    $geo = $data['ip_info']['geo'] ?? [];
    $city = $geo['city'] ?? 'N/A';
    $region = $geo['region'] ?? 'N/A';
    $country = $geo['country'] ?? 'N/A';
    $asn = $geo['as'] ?? 'N/A';
    $isp = $geo['isp'] ?? 'N/A';
    $org = $geo['org'] ?? 'N/A';
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
    
    $sslHtml = '<div class="alert alert-info">No SSL certificate details found.</div>';
    $sslInfo = $data['ssl_info'] ?? [];
    if (is_array($sslInfo) && !empty($sslInfo['ssl_info']) && is_array($sslInfo['ssl_info'])) {
        $sslCert = $sslInfo['ssl_info'];
        $subject = $sslCert['subject'] ?? [];
        $issuer = $sslCert['issuer'] ?? [];
        $validFromUnix = $sslCert['validFrom_time_t'] ?? null;
        $validToUnix = $sslCert['validTo_time_t'] ?? null;
        $validFrom = $validFromUnix ? date('M d, Y H:i:s', $validFromUnix) : 'N/A';
        $validTo = $validToUnix ? date('M d, Y H:i:s', $validToUnix) : 'N/A';
        $san = $sslCert['extensions']['subjectAltName'] ?? 'N/A';
        $keyUsage = $sslCert['extensions']['keyUsage'] ?? 'N/A';
        $extendedKeyUsage = $sslCert['extensions']['extendedKeyUsage'] ?? 'N/A';
        $certPolicies = $sslCert['extensions']['certificatePolicies'] ?? 'N/A';
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
    
    $techUsed = $data['technology_used'] ?? [];
    $techHtml = '<div class="alert alert-info">No technology information found.</div>';
    if (!empty($techUsed)) {
        $techHtml = '<ul class="list-group">';
        foreach ($techUsed as $tech) {
            $techHtml .= '<li class="list-group-item">' . htmlspecialchars($tech) . '</li>';
        }
        $techHtml .= '</ul>';
    }
    
    $whoisHtml = '<div class="alert alert-info">No WHOIS data available.</div>';
    if ($rawWhois !== '') {
        $whoisFormatted = preg_replace(
            '/^(.*?:)/m',
            '<strong>$1</strong>',
            nl2br(htmlspecialchars($rawWhois))
        );
        $whoisHtml = '<div>' . $whoisFormatted . '</div>';
    }
    
    $rawTabsHtml = '
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
    
   // --- Build the Server Score Report Card (Updated Look) ---
$score = $report['score'] ?? 0;
$maxScore = $report['max_score'] ?? 12;
$percent = $report['percent'] ?? 0;
$overallComment = $report['comment'] ?? '';
$detailsArr = $report['details'] ?? [];
$suggestionsArr = $report['suggestions'] ?? [];

// Build a progress bar color based on percentage
$progressBarClass = ($percent >= 80) ? 'bg-success' : (($percent >= 50) ? 'bg-warning' : 'bg-danger');

// Build condition details list with icons
$conditionsHtml = '<ul class="list-group mb-3">';
foreach ($detailsArr as $cond => $status) {
    // Choose an icon based on the status value
    $icon = '';
    $statusLower = strtolower($status);
    if ($statusLower === 'pass') {
        $icon = '<i class="fa fa-check-circle text-success"></i>';
    } elseif ($statusLower === 'error') {
        $icon = '<i class="fa fa-times-circle text-danger"></i>';
    } else {
        $icon = '<i class="fa fa-info-circle text-warning"></i>';
    }
    $conditionsHtml .= '<li class="list-group-item d-flex justify-content-between align-items-center">'
        . htmlspecialchars($cond)
        . '<span>' . $icon . ' ' . htmlspecialchars($status) . '</span></li>';
}
$conditionsHtml .= '</ul>';

// Build suggestions list if available
$suggestionsHtml = '';
if (!empty($suggestionsArr)) {
    $suggestionsHtml .= '<div class="alert alert-warning">';
    $suggestionsHtml .= '<h5 class="mb-1">Suggestions for Improvement:</h5>';
    $suggestionsHtml .= '<ul class="mb-0">';
    foreach ($suggestionsArr as $sug) {
        $suggestionsHtml .= '<li>' . htmlspecialchars($sug) . '</li>';
    }
    $suggestionsHtml .= '</ul></div>';
}

// Final Server Score Report Card HTML
$reportCard = '
<div class="card my-3 shadow-sm">
  <div class="card-header bg-primary text-white">
    <h4 class="mb-0">Server Score Report: <strong>' . $score . ' / ' . $maxScore . '</strong> (' . $percent . '%)</h4>
  </div>
  <div class="card-body">
    <div class="progress mb-3" style="height: 25px;">
      <div class="progress-bar ' . $progressBarClass . '" role="progressbar" style="width: ' . $percent . '%;" aria-valuenow="' . $percent . '" aria-valuemin="0" aria-valuemax="100">' . $percent . '%</div>
    </div>
    <p><strong>Overall Comment:</strong> ' . htmlspecialchars($overallComment) . '</p>
    <h5>Condition Details:</h5>
    ' . $conditionsHtml . '
    ' . $suggestionsHtml . '
  </div>
</div>';
    
$finalOutput = $rawTabsHtml . $reportCard;
return $finalOutput;
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
 * It then computes a score report (with Pass, Error, or To Improve ratings)
 * for:
 *   • JSON‑LD Presence
 *   • Microdata Presence
 *   • RDFa Presence
 *   • Organization Schema (in JSON‑LD)
 *   • Markup Quality (based on suggestions)
 *
 * The complete result (raw data plus report) is stored in the DB.
 *
 * @return string JSON-encoded complete schema data (raw + report).
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

    // --- JSON‑LD Extraction ---
    $jsonLdScripts = $xpath->query('//script[@type="application/ld+json"]');
    foreach ($jsonLdScripts as $js) {
        $json = trim($js->nodeValue);
        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $schemas['suggestions']['json_ld'][] = "Invalid JSON found in one of the ld+json scripts.";
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
    if (empty($schemas['json_ld'])) {
        // Provide a suggestion if no JSON‑LD found.
        $schemas['suggestions']['json_ld'][] = "No JSON‑LD markup was detected. Google recommends using JSON‑LD for structured data.";
    }
    if (empty($schemas['json_ld']['Organization'])) {
        $schemas['suggestions']['json_ld'][] = "Organization schema is missing. Adding organization details (e.g., your company name, logo, and contact info) can enhance your brand's presence in search results.";
    }

    // --- Microdata Extraction ---
    $microItems = $xpath->query('//*[@itemscope]');
    foreach ($microItems as $item) {
        $typeUrl = $item->getAttribute('itemtype');
        $type = $typeUrl ? basename(parse_url($typeUrl, PHP_URL_PATH)) : 'undefined';
        $schemas['microdata'][$type][] = $item->nodeName;
    }
    if (empty($schemas['microdata'])) {
        $schemas['suggestions']['microdata'][] = "No microdata was found. Consider adding microdata to enrich your page's structured data.";
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
        $schemas['suggestions']['rdfa'][] = "No RDFa data was detected. Adding RDFa can provide extra context to search engines.";
    }

    if (empty($schemas['json_ld']) && empty($schemas['microdata']) && empty($schemas['rdfa'])) {
        $schemas = ['schema_data' => 'No schema data found.'];
    }

    // --- SCORE LOGIC ---
    $details = [];
    $score = 0;
    $passedCount = 0;
    $improveCount = 0;
    $errorCount = 0;

    // JSON‑LD Presence
    if (!empty($schemas['json_ld']) && is_array($schemas['json_ld'])) {
        $details['JSON‑LD Presence'] = "Pass – JSON‑LD markup is present.";
        $score += 2; $passedCount++;
    } else {
        $details['JSON‑LD Presence'] = "Error – JSON‑LD markup is missing. It is highly recommended to include JSON‑LD.";
        $errorCount++;
    }
    // Microdata Presence
    if (!empty($schemas['microdata']) && is_array($schemas['microdata'])) {
        $details['Microdata Presence'] = "Pass – Microdata markup is present.";
        $score += 2; $passedCount++;
    } else {
        $details['Microdata Presence'] = "Error – No microdata was detected. Consider adding microdata for better structured data.";
        $errorCount++;
    }
    // RDFa Presence
    if (!empty($schemas['rdfa']) && is_array($schemas['rdfa'])) {
        $details['RDFa Presence'] = "Pass – RDFa markup is present.";
        $score += 2; $passedCount++;
    } else {
        $details['RDFa Presence'] = "Error – RDFa markup is missing. Including RDFa can help provide additional context.";
        $errorCount++;
    }
    // Organization Schema (within JSON‑LD)
    if (!empty($schemas['json_ld']['Organization'])) {
        $details['Organization Schema'] = "Pass – Organization schema is present.";
        $score += 2; $passedCount++;
    } else {
        $details['Organization Schema'] = "Error – Organization schema is missing. Adding organization details is important for brand recognition.";
        $errorCount++;
    }
    // Markup Quality: if there are any suggestions, mark as "To Improve"
    $markupSuggestions = array_merge(
        $schemas['suggestions']['json_ld'] ?? [],
        $schemas['suggestions']['microdata'] ?? [],
        $schemas['suggestions']['rdfa'] ?? []
    );
    if (empty($markupSuggestions)) {
        $details['Markup Quality'] = "Pass – Markup quality is excellent.";
        $score += 2; $passedCount++;
    } else {
        $details['Markup Quality'] = "To Improve – Some issues were noted: " . implode(" ", $markupSuggestions);
        $score += 1; $improveCount++;
    }

    $maxScore = 10;
    $percent = round(($score / $maxScore) * 100);

    // Build overall comment based on the scores
    $comment = "";
    if ($details['JSON‑LD Presence'] && strpos($details['JSON‑LD Presence'], 'Error') !== false) {
        $comment .= "JSON‑LD markup is missing. ";
    }
    if ($details['Microdata Presence'] && strpos($details['Microdata Presence'], 'Error') !== false) {
        $comment .= "Microdata is missing. ";
    }
    if ($details['RDFa Presence'] && strpos($details['RDFa Presence'], 'Error') !== false) {
        $comment .= "RDFa markup is missing. ";
    }
    if ($details['Organization Schema'] && strpos($details['Organization Schema'], 'Error') !== false) {
        $comment .= "Organization schema is missing. ";
    }
    if ($details['Markup Quality'] && strpos($details['Markup Quality'], 'To Improve') !== false) {
        $comment .= "Overall, the structured data markup quality could be improved. ";
    }

    $report = [
        'score'   => $score,
        'passed'  => $passedCount,
        'improve' => $improveCount,
        'errors'  => $errorCount,
        'percent' => $percent,
        'details' => $details,
        'comment' => trim($comment)
    ];

    $completeSchema = [
        'raw'    => $schemas,
        'report' => $report
    ];

    $schemaJson = json_encode($completeSchema);
    updateToDbPrepared($this->con, 'domains_data', ['schema_data' => $schemaJson], ['domain' => $this->domainStr]);
    // Optionally, store the report in session for later use (if needed)
    $_SESSION['report_data']['schemaReport'] = $completeSchema;
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
 * Displays the stored schema data in a tabbed layout along with a score summary.
 *
 * The top section shows the overall schema report:
 *   - JSON‑LD Presence
 *   - Microdata Presence
 *   - RDFa Presence
 *   - Organization Schema
 *   - Markup Quality
 *
 * Each is accompanied by a descriptive status message.
 *
 * @param string $jsonData JSON-encoded schema data (raw and report) from the database.
 * @return string HTML output.
 */
public function showSchema(string $jsonData): string
{
    $data = json_decode($jsonData, true);
    if (!is_array($data)) {
        return '<div class="alert alert-danger">No schema data available.</div>';
    }

    // Build the score summary card
    $report = $data['report'] ?? [];
    $scoreSummary = '<div class="card mb-4 border-info">';
    $scoreSummary .= '<div class="card-header bg-info text-white"><h4>Schema Analysis Report</h4></div>';
    $scoreSummary .= '<div class="card-body">';
    if (!empty($report)) {
        $scoreSummary .= '<p><strong>Score:</strong> ' . ($report['score'] ?? 0) . ' / 10 ';
        $scoreSummary .= '(<strong>' . ($report['percent'] ?? 0) . '%</strong>)</p>';
        $scoreSummary .= '<ul class="list-group">';
        foreach ($report['details'] as $metric => $status) {
            $icon = ($status === 'Pass' || strpos($status, 'Pass') !== false)
                ? '<i class="fa fa-check text-success"></i>'
                : ((strpos($status, 'To Improve') !== false)
                    ? '<i class="fa fa-exclamation-triangle text-warning"></i>'
                    : '<i class="fa fa-times text-danger"></i>');
            $scoreSummary .= '<li class="list-group-item">' . $icon . ' <strong>' . htmlspecialchars($metric) . ':</strong> ' . htmlspecialchars($status) . '</li>';
        }
        $scoreSummary .= '</ul>';
        $scoreSummary .= '<p class="mt-2"><em>' . htmlspecialchars($report['comment'] ?? '') . '</em></p>';
    } else {
        $scoreSummary .= '<div class="alert alert-warning">No score data available.</div>';
    }
    $scoreSummary .= '</div></div>';

    // -------------------------------------------------------------------------
    // 1. Build JSON‑LD Section
    // -------------------------------------------------------------------------
    $jsonLdContent = '';
    if (!empty($data['raw']['json_ld']) && is_array($data['raw']['json_ld'])) {
        // Order types so that "Organization" is first if present.
        $types = array_keys($data['raw']['json_ld']);
        $ordered = [];
        if (isset($data['raw']['json_ld']['Organization'])) {
            $ordered[] = 'Organization';
        }
        $otherTypes = array_diff($types, ['Organization']);
        sort($otherTypes);
        $ordered = array_merge($ordered, $otherTypes);

        // Build sub-tabs for JSON‑LD
        $subTabNav = '<ul class="nav nav-pills mb-3" id="jsonLdSubTab" role="tablist">';
        $subTabContent = '<div class="tab-content" id="jsonLdSubTabContent">';
        $i = 0;
        foreach ($ordered as $type) {
            $count = count($data['raw']['json_ld'][$type]);
            $activeClass = ($i === 0) ? 'active' : '';
            $paneId = 'jsonld-' . preg_replace('/\s+/', '-', strtolower($type));
            $subTabNav .= '
            <li class="nav-item" role="presentation">
                <button class="nav-link ' . $activeClass . '" id="' . $paneId . '-tab" data-bs-toggle="tab" data-bs-target="#' . $paneId . '" type="button" role="tab" aria-controls="' . $paneId . '" aria-selected="' . ($i === 0 ? 'true' : 'false') . '">
                    ' . htmlspecialchars($type) . ' (' . $count . ')
                </button>
            </li>';
            $subTabContent .= '<div class="tab-pane show ' . $activeClass . '" id="' . $paneId . '" role="tabpanel" aria-labelledby="' . $paneId . '-tab">';
            foreach ($data['raw']['json_ld'][$type] as $index => $node) {
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
        if (!empty($data['raw']['suggestions']['json_ld'])) {
            $jsonLdContent .= '<div class="container mt-2"><div class="alert alert-warning">';
            $jsonLdContent .= '<strong>Suggestions:</strong> ' . implode(" ", $data['raw']['suggestions']['json_ld']);
            $jsonLdContent .= '</div></div>';
        }
    } else {
        $jsonLdContent = '<div class="alert alert-info">No JSON‑LD schema data found.</div>';
    }

    // -------------------------------------------------------------------------
    // 2. Microdata Section
    // -------------------------------------------------------------------------
    $microContent = '';
    if (!empty($data['raw']['microdata']) && is_array($data['raw']['microdata'])) {
        $microContent .= '<div class="container mt-3"><h4>Microdata</h4>';
        $microContent .= $this->renderGoogleStyle($data['raw']['microdata'], 'micro');
        $microContent .= '</div>';
        if (!empty($data['raw']['suggestions']['microdata'])) {
            $microContent .= '<div class="container mt-2"><div class="alert alert-warning">';
            $microContent .= '<strong>Suggestions:</strong> ' . implode(" ", $data['raw']['suggestions']['microdata']);
            $microContent .= '</div></div>';
        }
    } else {
        $microContent = '<div class="alert alert-info">No Microdata found.</div>';
    }

    // -------------------------------------------------------------------------
    // 3. RDFa Section
    // -------------------------------------------------------------------------
    $rdfaContent = '';
    if (!empty($data['raw']['rdfa']) && is_array($data['raw']['rdfa'])) {
        $rdfaContent .= '<div class="container mt-3"><h4>RDFa</h4>';
        $rdfaContent .= $this->renderGoogleStyle($data['raw']['rdfa'], 'rdfa');
        $rdfaContent .= '</div>';
        if (!empty($data['raw']['suggestions']['rdfa'])) {
            $rdfaContent .= '<div class="container mt-2"><div class="alert alert-warning">';
            $rdfaContent .= '<strong>Suggestions:</strong> ' . implode(" ", $data['raw']['suggestions']['rdfa']);
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
    // 4. Global Suggestions
    // -------------------------------------------------------------------------
    $globalSuggestions = [];
    if (empty($data['raw']['json_ld'])) {
        $globalSuggestions[] = 'No JSON‑LD markup was detected. Google recommends JSON‑LD for modern structured data.';
    }
    if (empty($data['raw']['microdata'])) {
        $globalSuggestions[] = 'No microdata found. While JSON‑LD is preferred, microdata can be useful on older systems.';
    }
    if (empty($data['raw']['rdfa'])) {
        $globalSuggestions[] = 'No RDFa markup was detected. RDFa can add inline semantics for additional context.';
    }
    if (empty($data['raw']['json_ld']['Organization'])) {
        $globalSuggestions[] = 'Organization markup is missing. Consider adding organization details for better brand presence.';
    }
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
            . $scoreSummary
            . '</div>';
}


 /*===================================================================
     * Page Analytics Handelers
     *=================================================================== 
     */
/**
 * processPageAnalytics()
 *
 * Gathers data for a variety of on‑page SEO checks and stores the results as JSON in the
 * `page_analytics` field in your database.
 *
 * The checks include:
 *
 *  1. Encoding
 *  2. Doc Type
 *  3. W3C Validity
 *  4. Analytics
 *  5. Mobile Compatibility
 *  6. IP Canonicalization
 *  7. XML Sitemap
 *  8. Robots.txt
 *  9. URL Rewrite
 * 10. Embedded Objects
 * 11. Iframe
 * 12. Usability
 * 13. URL
 * 14. Canonical Tag
 * 15. Canonical Tag Accuracy
 * 16. Hreflang Tags
 * 17. AMP HTML
 * 18. Robots Meta Tag
 * 19. Favicon and Touch Icons
 * 20. HTTP Status Code
 * 21. Indexability (noindex/nofollow)
 * 22. URL Canonicalization & Redirects
 * 23. Content Freshness (Last-Modified/Publish Date)
 * 24. Language and Localization Tags
 * 25. Error Page Handling
 * 26. Page Security Headers (CSP, X-Frame-Options, etc.)
 * 27. Google Safe Browsing
 * 28. Gzip Compression
 * 29. Structured Data
 * 30. Cache Headers
 * 31. CDN Usage
 * 32. Noindex Tag
 * 33. Nofollow Tag
 * 34. Meta Refresh
 * 35. SPF Record
 * 36. Ads.txt
 *
 * @return string JSON string stored in `page_analytics`.
 */
public function processPageAnalytics(): string {
    $results = [];

    // 1) ENCODING
    $encoding = 'Not Detected';
    if (preg_match('#<meta[^>]+charset=[\'"]?([^\'">]+)#i', $this->html, $m)) {
        $encoding = strtoupper(trim($m[1]));
    } elseif (preg_match('#<meta[^>]+http-equiv=[\'"]content-type[\'"].+charset=([^\'">]+)#i', $this->html, $m)) {
        $encoding = strtoupper(trim($m[1]));
    }
    $results['Encoding'] = $encoding;

    // 2) DOC TYPE
    $docType = 'Missing';
    if (preg_match('#<!doctype\s+html.*?>#is', $this->html)) {
        $docType = 'HTML5';
    } elseif (preg_match('#<!doctype\s+html\s+public\s+"-//w3c//dtd xhtml 1\.0#i', $this->html)) {
        $docType = 'XHTML 1.0';
    } elseif (preg_match('#<!doctype\s+html\s+public\s+"-//w3c//dtd html 4\.01#i', $this->html)) {
        $docType = 'HTML 4.01';
    }
    $results['Doc Type'] = $docType;

    // 3) W3C VALIDITY
    // (This example doesn't run actual validation; ideally, you'd integrate the validator.)
    $results['W3C Validity'] = 'Not validated';

    // 4) ANALYTICS
    $analytics = 'Not Found';
    if (preg_match('/UA-\d{4,}-\d{1,}/i', $this->html) || stripos($this->html, "gtag(") !== false) {
        $analytics = 'Google Analytics Detected';
    }
    $results['Analytics'] = $analytics;

    // 5) MOBILE COMPATIBILITY
    $mobileCompatible = (preg_match('#<meta[^>]+name=[\'"]viewport[\'"]#i', $this->html)) ? 'Yes' : 'No';
    $results['Mobile Compatibility'] = $mobileCompatible;

    // 6) IP CANONICALIZATION
    $hostIP = gethostbyname($this->urlParse['host']);
    if (!empty($hostIP) && filter_var($hostIP, FILTER_VALIDATE_IP)) {
        $ipCanResult = ($hostIP == $this->urlParse['host']) ? 'No redirection from IP' : 'IP resolves to domain';
    } else {
        $ipCanResult = 'Not Checked';
    }
    $results['IP Canonicalization'] = $ipCanResult;

    // 7) XML SITEMAP
    $sitemapStatus = 'Not Found';
    $possibleSitemaps = ['/sitemap.xml', '/sitemap_index.xml'];
    $foundSitemapUrl = null;
    foreach ($possibleSitemaps as $s) {
        $testUrl = $this->scheme . '://' . $this->host . $s;
        if ($this->getHttpResponseCode($testUrl) == 200) {
            $sitemapStatus = 'Found';
            $foundSitemapUrl = $testUrl;
            break;
        }
    }
    $results['XML Sitemap'] = ($foundSitemapUrl) ? "$sitemapStatus at $foundSitemapUrl" : $sitemapStatus;

    // 8) ROBOTS.TXT
    $robotsUrl = $this->scheme . '://' . $this->host . '/robots.txt';
    $results['Robots.txt'] = ($this->getHttpResponseCode($robotsUrl) == 200) ? "Found at $robotsUrl" : "Not Found";

    // 9) URL REWRITE
    $results['URL Rewrite'] = (isset($this->urlParse['query']) && !empty($this->urlParse['query']))
        ? 'Likely using query strings' : 'Clean URLs detected';

    // 10) EMBEDDED OBJECTS
    $embeddedFound = (preg_match('#<embed\b[^>]*>#i', $this->html) || preg_match('#<object\b[^>]*>#i', $this->html))
        ? 'Yes' : 'No';
    $results['Embedded Objects'] = ($embeddedFound === 'No')
        ? 'No embedded objects detected. This is ideal.'
        : 'Embedded objects detected. Review if they are necessary.';

    // 11) IFRAME
    $results['Iframe'] = (preg_match('#<iframe\b[^>]*>#i', $this->html)) ? 'Yes' : 'No';

    // 12) USABILITY
    $results['Usability'] = ($mobileCompatible === 'Yes')
        ? 'Mobile meta tag found. Possibly good usability.'
        : 'Mobile meta tag not found. Usability may be affected.';

    // 13) URL
    $hostLength = strlen($this->host);
    $hyphenCount = substr_count($this->host, '-');
    $results['URL'] = "Scheme: {$this->scheme}, Host: {$this->host} (Length: {$hostLength}, Hyphens: {$hyphenCount})";

    // 14) CANONICAL TAG
    $canonical = 'Not Found';
    if (preg_match('#<link\s+rel=["\']canonical["\'][^>]+href=["\']([^"\']+)["\']#i', $this->html, $m)) {
        $canonical = $m[1];
    }
    $results['Canonical Tag'] = $canonical;

    // 15) CANONICAL TAG ACCURACY
    $homepageUrl = $this->scheme . '://' . $this->host;
    if ($canonical === 'Not Found') {
        $results['Canonical Tag Accuracy'] = 'No canonical tag found';
    } else {
        $results['Canonical Tag Accuracy'] = (trim($canonical, '/') === trim($homepageUrl, '/'))
            ? 'Canonical tag accurately matches homepage URL'
            : 'Canonical tag does not match homepage URL';
    }

    // 16) HREFLANG TAGS
    $hreflang = 'Not Found';
    if (preg_match_all('#<link\s+rel=["\']alternate["\']\s+hreflang=["\']([^"\']+)["\'][^>]+href=["\']([^"\']+)["\']#i', $this->html, $matches)) {
        $count = count($matches[0]);
        $tagsList = [];
        for ($i = 0; $i < $count; $i++) {
            $tagsList[] = $matches[1][$i] . ' => ' . $matches[2][$i];
        }
        $hreflang = "Found ({$count} tag" . ($count > 1 ? 's' : '') . "): " . implode(', ', $tagsList);
    }
    $results['Hreflang Tags'] = $hreflang;

    // 17) AMP HTML
    $amp = 'Not Found';
    if (preg_match('#<link\s+rel=["\']amphtml["\']\s+href=["\']([^"\']+)["\']#i', $this->html, $m)) {
        $amp = $m[1];
    }
    $results['AMP HTML'] = $amp;

    // 18) ROBOTS META TAG
    $robotsMeta = 'Not Found';
    if (preg_match('#<meta\s+name=["\']robots["\']\s+content=["\']([^"\']+)["\']#i', $this->html, $m)) {
        $robotsMeta = $m[1];
    }
    $results['Robots Meta Tag'] = $robotsMeta;

    // 19) FAVICON & TOUCH ICONS
    $faviconUrl = $this->getFaviconUrl();
    $results['Favicon and Touch Icons'] = (!empty($faviconUrl))
        ? '<img src="' . htmlspecialchars($faviconUrl) . '" alt="Favicon" style="vertical-align:middle; margin-right:5px;"> Favicon and touch icons detected.'
        : 'No favicon or touch icons detected. This may affect brand recognition in bookmarks and mobile devices.';
 
    // 20) HTTP STATUS CODE
    $httpStatus = $this->getHttpResponseCode($homepageUrl);
    switch ($httpStatus) {
        case 200:
            $statusMsg = 'HTTP 200 OK: The page is accessible.';
            break;
        case 404:
            $statusMsg = 'HTTP 404 Not Found: The page was not found.';
            break;
        case 301:
        case 302:
            $statusMsg = "HTTP {$httpStatus} Redirect: The page is redirecting.";
            break;
        default:
            $statusMsg = "HTTP {$httpStatus}: Check the response for details.";
    }
    $results['HTTP Status Code'] = $statusMsg;

    // 21) INDEXABILITY
    $indexability = 'Indexable';
    if (preg_match('#<meta\s+name=["\']robots["\']\s+content=["\']([^"\']+)["\']#i', $this->html, $m)) {
        $content = strtolower($m[1]);
        if (strpos($content, 'noindex') !== false) {
            $indexability = 'Noindex Detected';
        }
        if (strpos($content, 'nofollow') !== false) {
            $indexability .= ' & Nofollow Detected';
        }
    }
    $results['Indexability'] = $indexability;

    // 22) URL CANONICALIZATION & REDIRECTS
    if (!empty($canonical) && $canonical !== 'Not Found') {
        $results['URL Canonicalization & Redirects'] = ($canonical == $homepageUrl)
            ? 'Canonical URL matches homepage'
            : 'Canonical URL differs from homepage';
    } else {
        $results['URL Canonicalization & Redirects'] = 'Not Checked';
    }

    // 23) CONTENT FRESHNESS
    $freshness = 'Not Detected';
    if (preg_match('#<meta\s+property=["\']article:published_time["\']\s+content=["\']([^"\']+)["\']#i', $this->html, $m)) {
        $freshness = 'Published on ' . $m[1];
    } elseif (preg_match('#<meta\s+http-equiv=["\']last-modified["\']\s+content=["\']([^"\']+)["\']#i', $this->html, $m)) {
        $freshness = 'Last Modified: ' . $m[1];
    }
    $results['Content Freshness'] = $freshness;

    // 24) LANGUAGE & LOCALIZATION TAGS
    $langTag = 'Not Detected';
    if (preg_match('#<html[^>]+lang=["\']([^"\']+)["\']#i', $this->html, $m)) {
        $langTag = $m[1];
    }
    $results['Language and Localization Tags'] = $langTag;

    // 25) ERROR PAGE HANDLING
    $errorPage = (stripos($this->html, '404 Not Found') !== false || stripos($this->html, 'Page Not Found') !== false)
        ? 'Custom 404 Detected' : 'Standard Page';
    $results['Error Page Handling'] = $errorPage;

    // 26) PAGE SECURITY HEADERS
    $headers = @get_headers($homepageUrl, 1) ?: [];
    $csp = isset($headers['Content-Security-Policy']) ? 'CSP Detected' : 'No CSP';
    $xFrame = isset($headers['X-Frame-Options']) ? 'X-Frame-Options Detected' : 'No X-Frame-Options';
    $results['Page Security Headers'] = $csp . ' | ' . $xFrame;

    // 27) GOOGLE SAFE BROWSING
    $results['Google Safe Browsing'] = safeBrowsing($this->host);

    // 28) GZIP COMPRESSION
    $gzipData = $this->processGzip();
    if (is_array($gzipData)) {
        list($origSize, $compSize, $isCompressed, $fallbackSize, $headerData, $bodyData) = $gzipData;
        $savings = ($origSize > 0) ? round((($origSize - $compSize) / $origSize) * 100, 2) : 0;
        $results['Gzip Compression'] = "Original size: " . formatBytes($origSize) .
            ", Compressed: " . formatBytes($compSize) .
            " (saving " . $savings . "%)";
    } else {
        $results['Gzip Compression'] = "Not available";
    }

    // 29) STRUCTURED DATA
    $schema = $this->processSchema();
    $results['Structured Data'] = ($schema && $schema !== 'No schema data found.')
        ? 'Structured data detected'
        : 'No structured data detected';

    // 30) CACHE HEADERS
    $cacheHeaders = isset($headers['Cache-Control']) ? 'Cache headers detected' : 'No cache headers found';
    $results['Cache Headers'] = $cacheHeaders;

    // 31) CDN USAGE
    $cdnUsage = 'No CDN detected';
    if (!empty($headers)) {
        $serverVal = $headers['Server'] ?? '';
        if (is_array($serverVal)) {
            $serverVal = implode(' ', $serverVal);
        }
        $serverVal = trim((string)$serverVal);
        $server = strtolower($serverVal);
        // Note: variable $via was referenced but not defined; assuming it should be obtained similarly.
        $via = '';
        if (isset($headers['Via'])) {
            $via = is_array($headers['Via']) ? implode(' ', $headers['Via']) : $headers['Via'];
        }
        if (strpos($via, 'cloudflare') !== false || strpos($server, 'cloudflare') !== false) {
            $cdnUsage = 'Likely using Cloudflare';
        } elseif (strpos($via, 'fastly') !== false) {
            $cdnUsage = 'Likely using Fastly';
        }
    }
    $results['CDN Usage'] = $cdnUsage;

    // 32) NOINDEX TAG
    $noindex = 'No';
    if (preg_match('#<meta\s+name=["\']robots["\']\s+content=["\']([^"\']+)["\']#i', $this->html, $m)) {
        if (stripos($m[1], 'noindex') !== false) {
            $noindex = 'Yes';
        }
    }
    $results['Noindex Tag'] = $noindex;

    // 33) NOFOLLOW TAG
    $nofollow = 'No';
    if (preg_match('#<meta\s+name=["\']robots["\']\s+content=["\']([^"\']+)["\']#i', $this->html, $m)) {
        if (stripos($m[1], 'nofollow') !== false) {
            $nofollow = 'Yes';
        }
    }
    $results['Nofollow Tag'] = $nofollow;

    // 34) META REFRESH
    $metaRefresh = (preg_match('#<meta\s+http-equiv=["\']refresh["\']#i', $this->html)) ? 'Detected' : 'Not Detected';
    $results['Meta Refresh'] = $metaRefresh;

    // 35) SPF RECORD
    $spf = 'Not Found';
    $txtRecords = dns_get_record($this->host, DNS_TXT);
    if ($txtRecords) {
        foreach ($txtRecords as $record) {
            if (isset($record['txt']) && stripos($record['txt'], 'v=spf1') !== false) {
                $spf = 'SPF record found';
                break;
            }
        }
    }
    $results['SPF Record'] = $spf;

    // 36) ADS.TXT
    $adsUrl = $this->scheme . '://' . $this->host . '/ads.txt';
    $adsCode = $this->getHttpResponseCode($adsUrl);
    if ($adsCode == 200) {
        $results['Ads.txt'] = $adsUrl;
    } else {
        $results['Ads.txt'] = 'Ads.txt not found on your domain. <i class="fa fa-times text-danger"></i> Consider adding an ads.txt file to control your ad inventory.';
    }

    // -----------------------------------------------------------------
    // Compute score/report based on ideal conditions.
    // For each check in a defined set, we compare the actual result to an ideal.
    $scoreDetails = [];
    $totalScore = 0;
    $maxPoints = 0;
    
    // Define the ideal conditions (adjust as necessary).
    $idealConditions = [
        'Encoding' => 'UTF-8',
        'Doc Type' => 'HTML5',
        'W3C Validity' => 'Valid',
        'Analytics' => 'Google Analytics Detected',
        'Mobile Compatibility' => 'Yes',
        'IP Canonicalization' => 'No redirection from IP',
        'XML Sitemap' => 'Found',
        'Robots.txt' => 'Found',
        'URL Rewrite' => 'Clean URLs detected',
        'Embedded Objects' => 'No embedded objects detected. This is ideal.',
        'Iframe' => 'No',
        'Usability' => 'Mobile meta tag found. Possibly good usability.',
        'Canonical Tag' => 'Found',
        'Canonical Tag Accuracy' => 'Canonical tag accurately matches homepage URL',
        'Hreflang Tags' => 'Found',
        'AMP HTML' => 'Found',
        'Robots Meta Tag' => 'Found',
        'Favicon and Touch Icons' => 'Favicon and touch icons detected',
        'HTTP Status Code' => 'HTTP 200 OK',
        'Indexability' => 'Indexable',
        'URL Canonicalization & Redirects' => 'Canonical URL matches homepage',
        'Content Freshness' => 'Detected',
        'Language and Localization Tags' => 'Detected',
        'Error Page Handling' => 'Standard Page',
        'Page Security Headers' => 'CSP Detected',
        'Google Safe Browsing' => 'Safe',
        'Gzip Compression' => 'High',  // e.g. saving >50%
        'Structured Data' => 'Structured data detected',
        'Cache Headers' => 'Cache headers detected',
        'CDN Usage' => 'Likely using Cloudflare',
        'Noindex Tag' => 'No',
        'Nofollow Tag' => 'No',
        'Meta Refresh' => 'Not Detected',
        'SPF Record' => 'SPF record found',
        'Ads.txt' => 'Found'
    ];
    
    foreach ($idealConditions as $check => $ideal) {
        $maxPoints += 2;
        $actual = $results[$check] ?? 'Not Found';
        // For some checks, we use a substring match.
        if (stripos($actual, $ideal) !== false) {
            $scoreDetails[$check] = "Pass";
            $totalScore += 2;
        } else {
            // If actual is not "Not Found", mark it as "To Improve", else "Error".
            if (stripos($actual, 'Not Found') === false && stripos($actual, 'No') === false) {
                $scoreDetails[$check] = "To Improve";
                $totalScore += 1;
            } else {
                $scoreDetails[$check] = "Error";
            }
        }
    }
    
    $overallScorePercent = $maxPoints > 0 ? round(($totalScore / $maxPoints) * 100) : 0;
    if ($overallScorePercent == 100) {
        $overallStatus = "Excellent";
    } elseif ($overallScorePercent >= 70) {
        $overallStatus = "Good";
    } elseif ($overallScorePercent >= 50) {
        $overallStatus = "Average";
    } else {
        $overallStatus = "Poor";
    }
    
    // Build an overall comment.
    $overallComment = "Page analytics computed. ";
    if ($overallScorePercent >= 90) {
        $overallComment .= "Excellent technical SEO performance.";
    } elseif ($overallScorePercent >= 70) {
        $overallComment .= "Good performance, but there is room for improvement.";
    } elseif ($overallScorePercent >= 50) {
        $overallComment .= "Average performance; review the suggestions.";
    } else {
        $overallComment .= "Poor technical SEO; significant improvements are needed.";
    }
    
    $finalReport = [
        'raw' => $results,
        'report' => [
            'score' => $totalScore,
            'max_points' => $maxPoints,
            'percent' => $overallScorePercent,
            'details' => $scoreDetails,
            'comment' => $overallComment
        ]
    ];
    $_SESSION['report_data']['pageAnalyticsReport'] = $finalReport;
    $jsonOutput = json_encode($finalReport);
    updateToDbPrepared($this->con, 'domains_data', ['page_analytics' => $jsonOutput], ['domain' => $this->domainStr]);
    
    return $jsonOutput;
}


/**
 * Displays the page analytics data in a grouped, user-friendly layout.
 *
 * The data is grouped into three categories:
 *   1. General Information
 *   2. Meta Tags & SEO Settings
 *   3. Technical & Advanced
 *
 * An overall summary (score, points, and comment) is displayed at the top,
 * and suggestions (if any) are listed below.
 *
 * @param string $jsonData JSON-encoded page analytics data.
 * @return string HTML output.
 */
public function showPageAnalytics(string $jsonData): string {
    $data = json_decode($jsonData, true);
    if (!is_array($data)) {
        return '<div class="alert alert-danger">Invalid or missing page analytics data.</div>';
    }
    
    // Define groups and the keys (headings) that belong in each group.
    $groups = [
        "General Information" => [
            "Encoding",
            "Doc Type",
            "W3C Validity",
            "Analytics",
            "Mobile Compatibility",
            "IP Canonicalization",
            "URL",
            "HTTP Status Code",
            "Indexability",
            "URL Canonicalization & Redirects"
        ],
        "Meta Tags & SEO Settings" => [
            "XML Sitemap",
            "Robots.txt",
            "URL Rewrite",
            "Canonical Tag",
            "Canonical Tag Accuracy",
            "Hreflang Tags",
            "AMP HTML",
            "Robots Meta Tag",
            "Favicon and Touch Icons",
            "Noindex Tag",
            "Nofollow Tag",
            "Meta Refresh"
        ],
        "Technical & Advanced" => [
            "Embedded Objects",
            "Iframe",
            "Usability",
            "Content Freshness",
            "Language and Localization Tags",
            "Error Page Handling",
            "Page Security Headers",
            "Google Safe Browsing",
            "Gzip Compression",
            "Structured Data",
            "Cache Headers",
            "CDN Usage",
            "SPF Record",
            "Ads.txt"
        ]
    ];
    
    // Build cards for each check.
    $cards = [];
    $suggestions = [];
    foreach ($data['raw'] as $heading => $value) {
        // Only include headings that are in our defined groups.
        foreach ($groups as $group) {
            if (in_array($heading, $group)) {
                $cards[$heading] = $this->buildCardData($heading, $value, $suggestions);
            }
        }
    }
    
    // Build HTML output by groups.
    $html = '<div class="container my-4">';
    foreach ($groups as $groupName => $headings) {
        $html .= '<h3 class="mb-3">' . htmlspecialchars($groupName) . '</h3>';
        $html .= '<div class="row row-cols-1 row-cols-md-2 g-4 mb-4">';
        foreach ($headings as $heading) {
            if (!isset($cards[$heading])) {
                continue;
            }
            $card = $cards[$heading];
            $html .= '<div class="col">';
            $html .= '<div class="card h-100 shadow-sm">';
            $html .= '  <div class="card-body d-flex align-items-start">';
            $html .= '    <div class="me-3" style="font-size:1.8rem;">' . $card['icon'] . '</div>';
            $html .= '    <div>';
            $html .= '      <h5 class="card-title mb-1">' . htmlspecialchars($card['heading']) . '</h5>';
            // If the description has HTML (for keys like Favicon or Ads.txt), output it raw.
            if (in_array($card['heading'], ['Favicon and Touch Icons', 'Ads.txt'])) {
                $html .= '      <p class="card-text text-muted small mb-0">' . $card['description'] . '</p>';
            } else {
                $html .= '      <p class="card-text text-muted small mb-0">' . htmlspecialchars($card['description']) . '</p>';
            }
            $html .= '    </div>';
            $html .= '  </div>';
            $html .= '</div>';
            $html .= '</div>';
        }
        $html .= '</div>';
    }
    
    // Overall summary.
    $overallReport = $data['report'] ?? [];
    $summaryHtml = '<div class="alert alert-info mt-4">';
    $summaryHtml .= '<strong>Overall Page Analytics Score:</strong> ' . ($overallReport['percent'] ?? 0) . '%<br>';
    $summaryHtml .= '<strong>Score:</strong> ' . ($overallReport['score'] ?? 0) . ' out of ' . ($overallReport['max_points'] ?? 0) . '<br>';
    $summaryHtml .= '<strong>Comment:</strong> ' . ($overallReport['comment'] ?? '');
    $summaryHtml .= '</div>';
    
    // Optionally, display suggestions.
    if (!empty($suggestions)) {
        $summaryHtml .= '<div class="card border-warning mt-4">';
        $summaryHtml .= '  <div class="card-header bg-warning text-dark"><strong>Suggestions for Improvement</strong></div>';
        $summaryHtml .= '  <div class="card-body"><ul class="mb-0">';
        foreach ($suggestions as $sug) {
            $summaryHtml .= '<li>' . htmlspecialchars($sug) . '</li>';
        }
        $summaryHtml .= '  </ul></div>';
        $summaryHtml .= '</div>';
    }
    
    $html .= $summaryHtml;
    $html .= '</div>';
    return $html;
}
 


/**
 * buildCardData()
 *
 * Builds a card array with keys:
 *   - 'icon': HTML for a Font Awesome icon based on the value.
 *   - 'heading': The check heading.
 *   - 'description': A user-friendly message.
 *
 * Also appends suggestions for problematic items.
 *
 * @param string $heading
 * @param string $value
 * @param array  &$suggestions
 * @return array
 */
private function buildCardData(string $heading, string $value, array &$suggestions): array
{
    $iconHtml = $this->getFaIcon($heading, $value);
    $description = $value; // Default description

    switch ($heading) {

        case 'AMP HTML':
            if (stripos($value, 'Not Found') !== false) {
                $description = 'AMP version not detected. Consider implementing AMP for improved mobile speed.';
                $suggestions[] = 'Add a valid <link rel="amphtml" href="your-amp-version-url"> tag.';
            } else {
                $description = 'AMP HTML detected: ' . $value;
            }
            break;

        case 'Favicon and Touch Icons':
            if (stripos($value, 'Not Found') !== false) {
                $description = 'No favicon or touch icons detected. This may affect brand recognition in bookmarks and mobile devices.';
                $suggestions[] = 'Include a favicon (<link rel="icon" ...>) and an apple-touch-icon for better recognition.';
            } else {
                // Example using Google’s favicon API
                $faviconUrl = 'https://www.google.com/s2/favicons?domain=' . urlencode($this->host);
                $description = '<img src="' . $faviconUrl . '" alt="Favicon" style="vertical-align:middle; margin-right:5px;"> Favicon and touch icons detected.';
            }
            break;

        case 'Content Freshness':
            if (stripos($value, 'Not Detected') !== false) {
                $description = 'No publication or last-modified date detected.';
                $suggestions[] = 'Include meta tags such as <meta property="article:published_time" content="..."> or <meta http-equiv="last-modified" content="...">.';
            } else {
                $description = $value;
            }
            break;

        case 'Language and Localization Tags':
            if (stripos($value, 'Not Detected') !== false) {
                $description = 'No language attribute found in the HTML tag.';
                $suggestions[] = 'Add a language attribute to your <html> tag (e.g., <html lang="en">).';
            } else {
                $description = 'Language detected: ' . $value;
            }
            break;

        case 'Canonical Tag Accuracy':
            if (stripos($value, 'accurately matches') !== false) {
                $description = $value;
            } else {
                $description = $value;
                $suggestions[] = 'Ensure the canonical tag exactly matches your homepage URL to avoid duplicate content issues.';
            }
            break;

        // New cases added below:

        case 'Noindex Tag':
            // If "No" means no noindex meta tag is present, that is generally good.
            if (trim(strtolower($value)) === 'no') {
                $description = 'No noindex meta tag found. Your page is indexable.';
            } else {
                $description = 'Noindex meta tag detected. This may prevent your page from being indexed.';
                $suggestions[] = 'Remove the noindex meta tag if you want your page to be indexed.';
            }
            break;

        case 'Nofollow Tag':
            if (trim(strtolower($value)) === 'no') {
                $description = 'No nofollow meta tag found. All links are crawlable.';
            } else {
                $description = 'Nofollow meta tag detected. Some links may not pass link juice.';
                $suggestions[] = 'Review your nofollow usage if you want all links to be followed.';
            }
            break;

        case 'Ads.txt':
            if (stripos($value, 'not found') !== false) {
                $description = 'Ads.txt not found on your domain. <i class="fa fa-times text-danger"></i> Consider adding an ads.txt file to control your ad inventory.';
                $suggestions[] = 'Add an ads.txt file to your website root.';
            } else {
                $description = 'Ads.txt found: ' . $value;
            }
            break;

        case 'HTTP Status Code':
            // Here we translate the code into a human‑readable message.
            $code = intval($value);
            switch ($code) {
                case 200:
                    $description = 'HTTP 200 OK: The page is accessible.';
                    break;
                case 404:
                    $description = 'HTTP 404 Not Found: The page was not found.';
                    break;
                case 301:
                case 302:
                    $description = "HTTP $code Redirect: The page is redirecting.";
                    break;
                default:
                    $description = "HTTP $code: Check the response for details.";
            }
            break;

        case 'Hreflang Tags':
            // Assuming $value now contains a string like "Found (2 tags): en => http://..., fr => http://..."
            $description = $value;
            break;

        case 'Embedded Objects':
            if (stripos($value, 'No') !== false) {
                $description = 'No embedded objects detected. This is ideal.';
            } else {
                $description = 'Embedded objects detected. Review if they are necessary.';
                $suggestions[] = 'Ensure that any embedded objects (e.g. videos, Flash, etc.) are not negatively affecting page load.';
            }
            break;

        default:
            // For most items, if the value contains positive keywords, show check.
            if (strpos(strtolower($value), 'found') !== false ||
                strpos(strtolower($value), 'yes') !== false ||
                strpos(strtolower($value), 'detected') !== false ||
                strpos(strtolower($value), 'html5') !== false ||
                strpos(strtolower($value), 'clean') !== false ||
                strpos(strtolower($value), 'accurately') !== false) {
                $description = $value;
            } else {
                $description = $value;
            }
            break;
    }

    return [
        'icon' => $iconHtml,
        'heading' => $heading,
        'description' => $description,
    ];
}




/**
 * getFaIcon()
 *
 * Returns a Font Awesome icon based on the heading and its value.
 * Adjusts the positive/negative conditions for each check.
 *
 * @param string $heading
 * @param string $value
 * @return string HTML icon markup.
 */
private function getFaIcon(string $heading, string $value): string
{
    $valLower = strtolower((string)$value); 

    switch ($heading) {
        case 'Noindex Tag':
        case 'Nofollow Tag':
            // "No" is good here.
            return (trim($valLower) === 'no')
                ? '<i class="fa fa-check text-success"></i>'
                : '<i class="fa fa-times text-danger"></i>';
        case 'Ads.txt':
            return (stripos($valLower, 'not found') !== false)
                ? '<i class="fa fa-times text-danger"></i>'
                : '<i class="fa fa-check text-success"></i>';
        case 'HTTP Status Code':
            // Use a check for 200, else an error icon.
            return ($value == '200')
                ? '<i class="fa fa-check text-success"></i>'
                : '<i class="fa fa-exclamation-triangle text-warning"></i>';
        case 'Hreflang Tags':
        case 'AMP HTML':
        case 'Favicon and Touch Icons':
        case 'Content Freshness':
        case 'Language and Localization Tags':
            if (strpos($valLower, 'not found') !== false || strpos($valLower, 'not detected') !== false) {
                return '<i class="fa fa-times text-danger"></i>';
            } else {
                return '<i class="fa fa-check text-success"></i>';
            }
        case 'Canonical Tag Accuracy':
            return (strpos($valLower, 'accurately matches') !== false)
                ? '<i class="fa fa-check text-success"></i>'
                : '<i class="fa fa-times text-danger"></i>';
        case 'Embedded Objects':
            return (stripos($valLower, 'no') !== false)
                ? '<i class="fa fa-check text-success"></i>'
                : '<i class="fa fa-exclamation-triangle text-warning"></i>';
        default:
            // For other headings, a simple positive/negative check.
            if (strpos($valLower, 'found') !== false ||
                strpos($valLower, 'yes') !== false ||
                strpos($valLower, 'detected') !== false ||
                strpos($valLower, 'html5') !== false ||
                strpos($valLower, 'clean') !== false ||
                strpos($valLower, 'accurately') !== false) {
                return '<i class="fa fa-check text-success"></i>';
            }
            if (strpos($valLower, 'not found') !== false ||
                strpos($valLower, 'no') !== false ||
                strpos($valLower, 'missing') !== false ||
                strpos($valLower, 'error') !== false ||
                strpos($valLower, 'not detected') !== false) {
                return '<i class="fa fa-times text-danger"></i>';
            }
            return '<i class="fa fa-info-circle text-secondary"></i>';
    }
}

/**
 * Helper: getFaviconUrl()
 *
 * Attempts to extract the favicon URL from the page’s HTML.
 *
 * @return string The favicon URL if found, or an empty string.
 */
private function getFaviconUrl(): string {
    // Use a lookahead to check for a rel attribute containing icon, shortcut icon, or apple-touch-icon,
    // then capture the href value regardless of attribute order.
    if (preg_match('#<link(?=[^>]*rel=["\'](?:icon|shortcut icon|apple-touch-icon)["\'])[^>]*href=["\']([^"\']+)["\']#i', $this->html, $matches)) {
        $favicon = trim($matches[1]);
        // Convert relative URL to absolute if necessary.
        if (!preg_match('#^https?://#i', $favicon)) {
            $favicon = rtrim($this->scheme . '://' . $this->host, '/') . '/' . ltrim($favicon, '/');
        }
        return $favicon;
    }
    return '';
}



 /*===================================================================
     * Social URL Handelers
     *=================================================================== 
     */

  /**
 * Processes social page URLs from the HTML.
 * Scans all anchor tags and extracts URLs that match common social network patterns.
 * Ignores "base" URLs (like https://www.pinterest.com/) without additional path components.
 * Computes a score report based on the presence of social URLs.
 * Stores the results (raw data and score report) as a JSON-encoded associative array in the "social_urls" field.
 *
 * @return string JSON data of social URLs (raw + report).
 */
public function processSocialUrls(): string {
    $doc = $this->getDom();
    $xpath = new DOMXPath($doc);
    
    // Define social networks and regex patterns to match in the href attribute.
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
    
    // Define "base" URLs for each network to ignore if no additional path exists.
    $baseUrls = [
        'facebook'    => ['https://facebook.com', 'https://www.facebook.com'],
        'x'           => ['https://twitter.com', 'https://www.twitter.com', 'https://x.com', 'https://www.x.com'],
        'instagram'   => ['https://instagram.com', 'https://www.instagram.com'],
        'linkedin'    => ['https://linkedin.com', 'https://www.linkedin.com'],
        'youtube'     => ['https://youtube.com', 'https://www.youtube.com'],
        'pinterest'   => ['https://pinterest.com', 'https://www.pinterest.com'],
        'discord'     => ['https://discord.com', 'https://www.discord.com'],
        'whatsapp'    => ['https://wa.me', 'https://whatsapp.com', 'https://www.whatsapp.com'],
        'tripadvisor' => ['https://tripadvisor.com', 'https://www.tripadvisor.com'],
        'tiktok'      => ['https://tiktok.com', 'https://www.tiktok.com']
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
                // Remove trailing slash for comparison.
                $normalizedHref = rtrim($href, '/');
                // Check if the URL is exactly one of the base URLs for this network.
                $skip = false;
                if (isset($baseUrls[$network])) {
                    foreach ($baseUrls[$network] as $base) {
                        if ($normalizedHref === rtrim($base, '/')) {
                            $skip = true;
                            break;
                        }
                    }
                }
                if ($skip) {
                    continue;
                }
                $urls[] = $href;
            }
        }
        if (!empty($urls)) {
            // Save unique URLs for this social network.
            $socialUrls[$network] = array_values(array_unique($urls));
        }
    }
    
    // ----- SCORE LOGIC -----
    // Award 2 points per network if at least one URL is found.
    $details = [];
    $totalScore = 0;
    $maxScore = count($socialPatterns) * 2; // e.g. 10 networks * 2 points each = 20
    foreach ($socialPatterns as $network => $pattern) {
        if (!empty($socialUrls[$network])) {
            $details[ucfirst($network) . " Presence"] = "Pass – " . ucfirst($network) . " URL(s) found.";
            $totalScore += 2;
        } else {
            $details[ucfirst($network) . " Presence"] = "Error – No " . ucfirst($network) . " URL found.";
        }
    }
    
    // Compute overall percentage.
    $percent = ($maxScore > 0) ? round(($totalScore / $maxScore) * 100) : 0;
    
    // Overall comment based on score.
    $comment = "";
    if ($totalScore == $maxScore) {
        $comment = "Excellent – All social URLs are present.";
    } elseif ($totalScore >= ($maxScore / 2)) {
        $comment = "Some social URLs are missing. Consider adding the missing profiles for a more complete online presence.";
    } else {
        $comment = "Critical – Most social URLs are missing. It's highly recommended to include your social network profiles.";
    }
    
    $report = [
        'score'     => $totalScore,
        'max_score' => $maxScore,
        'percent'   => $percent,
        'details'   => $details,
        'comment'   => $comment
    ];
    
    $completeSocialData = [
        'raw'    => $socialUrls,
        'report' => $report
    ];
    
    $jsonData = jsonEncode($completeSocialData);
    updateToDbPrepared($this->con, 'domains_data', ['social_urls' => $jsonData], ['domain' => $this->domainStr]);
    $_SESSION['report_data']['socialUrlsReport'] = $completeSocialData;
    return $jsonData;
}

/**
 * Displays the processed social page URLs in a nicely formatted card along with the score report.
 * Uses Font Awesome icons for each social network.
 *
 * @param string $socialData JSON-encoded social URLs (raw + report) from DB.
 * @return string HTML output.
 */
public function showSocialUrls($socialData): string {
    $data = jsonDecode($socialData, true);
    if (!is_array($data) || empty($data['raw'])) {
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
    
    // Build the score summary card.
    $report = $data['report'] ?? [];
    $scoreSummary = '<div class="card mb-4 border-primary">';
    $scoreSummary .= '<div class="card-header bg-primary text-white"><h4>Social URLs Report</h4></div>';
    $scoreSummary .= '<div class="card-body">';
    if (!empty($report)) {
        $scoreSummary .= '<p><strong>Score:</strong> ' . ($report['score'] ?? 0) . ' / ' . ($report['max_score'] ?? 0);
        $scoreSummary .= ' (<strong>' . ($report['percent'] ?? 0) . '%</strong>)</p>';
        $scoreSummary .= '<ul class="list-group">';
        foreach ($report['details'] as $metric => $status) {
            $icon = (strpos($status, 'Pass') !== false)
                ? '<i class="fa fa-check text-success"></i>'
                : '<i class="fa fa-times text-danger"></i>';
            $scoreSummary .= '<li class="list-group-item">' . $icon . ' <strong>' . htmlspecialchars($metric) . ':</strong> ' . htmlspecialchars($status) . '</li>';
        }
        $scoreSummary .= '</ul>';
        $scoreSummary .= '<p class="mt-2"><em>' . htmlspecialchars($report['comment'] ?? '') . '</em></p>';
    } else {
        $scoreSummary .= '<div class="alert alert-warning">No score data available.</div>';
    }
    $scoreSummary .= '</div></div>';
    
    // Build the social URLs card.
    $output = '<div class="card my-3 shadow-sm">
                    <div class="card-header"><strong>Social Page URLs</strong></div>
                    <div class="card-body">';
    
    // Loop through each network found.
    foreach ($data['raw'] as $network => $urls) {
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
    
    // Return the combined output with the score summary at the top.
    return '<div class="container my-3">'  . $output . $scoreSummary. '</div>';
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
  /**
 * Processes PageSpeed Insights data concurrently for desktop and mobile.
 *
 * Fetches data from Google PageSpeed Insights, filters the response to extract only
 * the necessary fields, computes a performance score report based on:
 *   - Score ≥ 90: Pass
 *   - Score 70–89: To Improve
 *   - Score < 70: Error
 *
 * The consolidated report structure now includes:
 *   - 'desktop' and 'mobile': Filtered reports.
 *   - 'raw': The full filtered reports (raw data) for each platform.
 *   - 'report': The computed score report.
 *   - Other meta data (timestamp, url, cache_hit).
 *
 * This JSON is stored in the DB (field "page_speed_insight") and in session (for later use).
 *
 * @return string JSON encoded array with keys: pagespeed (including raw and report keys).
 */
public function processPageSpeedInsightConcurrent(): string {
    // Get API key from DB settings or use a default key.
    if (isset($GLOBALS['con'])) {
        $db = reviewerSettings($GLOBALS['con']);
        $apiKey = urldecode($db['insights_api']);
    } else {
        $apiKey = 'AIzaSyAO7dTSPW3f8lOKJ0pP4nPxSMUY29ne-K0';
    }
    
    // Build the target URL.
    $targetUrl = $this->scheme . "://" . $this->host;
    $encodedUrl = urlencode($targetUrl);
    
    // Build the endpoints for desktop and mobile (requesting screenshots).
    $desktopUrl = "https://www.googleapis.com/pagespeedonline/v5/runPagespeed?key={$apiKey}&screenshot=true&snapshots=true&locale=en_US&url={$encodedUrl}&strategy=desktop";
    $mobileUrl  = "https://www.googleapis.com/pagespeedonline/v5/runPagespeed?key={$apiKey}&screenshot=true&snapshots=true&locale=en_US&url={$encodedUrl}&strategy=mobile";
    
    $endpoints = [
        'desktop' => $desktopUrl,
        'mobile'  => $mobileUrl
    ];
    
    $multiCurl = curl_multi_init();
    $handles = [];
    foreach ($endpoints as $key => $url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);  // Timeout after 30 seconds.
        curl_multi_add_handle($multiCurl, $ch);
        $handles[$key] = $ch;
    }
    
    // Execute the handles concurrently.
    $running = null;
    do {
        curl_multi_exec($multiCurl, $running);
        curl_multi_select($multiCurl);
    } while ($running > 0);
    
    // Prepare the results.
    $rawReports = [];  // Filtered reports.
    $scores = [];      // Performance scores.
    $screenshots = []; // Screenshot data.
    
    foreach ($handles as $key => $ch) {
        $response = curl_multi_getcontent($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            
            // Extract and remove screenshot data.
            if (isset($data['lighthouseResult']['audits']['final-screenshot']['details']['data'])) {
                $screenshots[$key . 'Screenshot'] = $data['lighthouseResult']['audits']['final-screenshot']['details']['data'];
                unset($data['lighthouseResult']['audits']['final-screenshot']);
            } else {
                $screenshots[$key . 'Screenshot'] = '';
            }
            
            // Extract performance score.
            if (isset($data['lighthouseResult']['categories']['performance']['score'])) {
                $scores[$key . 'Score'] = intval($data['lighthouseResult']['categories']['performance']['score'] * 100);
            } else {
                $scores[$key . 'Score'] = 0;
            }
            
            // Filter the API response.
            $rawReports[$key] = $this->filterReport($data);
        } else {
            // In case of error.
            $scores[$key . 'Score'] = 0;
            $screenshots[$key . 'Screenshot'] = '';
            $rawReports[$key] = [];
        }
        curl_multi_remove_handle($multiCurl, $ch);
        curl_close($ch);
    }
    curl_multi_close($multiCurl);
    
    // Build the consolidated report structure.
    $pagespeedReport = [
        'desktop'   => isset($rawReports['desktop']) ? $rawReports['desktop'] : [],
        'mobile'    => isset($rawReports['mobile']) ? $rawReports['mobile'] : [],
        'raw'       => $rawReports, // Include the full filtered raw reports.
        'timestamp' => date('c'),
        'url'       => $targetUrl,
        'cache_hit' => isset($rawReports['mobile']['fetchTime']) ? $rawReports['mobile']['fetchTime'] : ''
    ];
    
    // ----- SCORE LOGIC -----
    $evaluateScore = function($score) {
        if ($score >= 90) {
            return ['status' => 'Pass', 'message' => 'Excellent performance.'];
        } elseif ($score >= 70) {
            return ['status' => 'To Improve', 'message' => 'Performance is acceptable, but there is room for improvement.'];
        } else {
            return ['status' => 'Error', 'message' => 'Performance is poor. Immediate improvements are needed.'];
        }
    };
    
    $desktopScore = $scores['desktopScore'] ?? 0;
    $mobileScore  = $scores['mobileScore']  ?? 0;
    
    $desktopEval = $evaluateScore($desktopScore);
    $mobileEval  = $evaluateScore($mobileScore);
    
    $overallComment = "";
    if ($desktopEval['status'] === 'Pass' && $mobileEval['status'] === 'Pass') {
        $overallComment = "Outstanding! Both desktop and mobile performance are excellent.";
    } elseif ($desktopEval['status'] === 'Error' && $mobileEval['status'] === 'Error') {
        $overallComment = "Critical issues detected on both desktop and mobile. Immediate optimizations are necessary.";
    } else {
        $overallComment = "Mixed results. Review the detailed diagnostics for opportunities to improve performance.";
    }
    
    $scoreReport = [
        'desktop' => [
            'score'   => $desktopScore,
            'status'  => $desktopEval['status'],
            'message' => $desktopEval['message']
        ],
        'mobile'  => [
            'score'   => $mobileScore,
            'status'  => $mobileEval['status'],
            'message' => $mobileEval['message']
        ],
        'overall' => [
            'comment' => $overallComment,
            'percent' => round((($desktopScore + $mobileScore) / 2), 0)
        ]
    ];
    
    // Add the score report to the consolidated structure.
    $pagespeedReport['report'] = $scoreReport;
    
    // Encode the consolidated report as JSON.
    $jsonOutput = jsonEncode(['pagespeed' => $pagespeedReport]);
    
    // Save the report (without screenshots) in the "page_speed_insight" field.
    updateToDbPrepared($this->con, 'domains_data', ['page_speed_insight' => $jsonOutput], ['domain' => $this->domainStr]);
    
    // Save screenshots separately.
    if (!empty($screenshots['desktopScreenshot'])) {
        updateToDbPrepared($this->con, 'domains_data', ['desktop_screenshot' => $screenshots['desktopScreenshot']], ['domain' => $this->domainStr]);
    }
    if (!empty($screenshots['mobileScreenshot'])) {
        updateToDbPrepared($this->con, 'domains_data', ['mobile_screenshot' => $screenshots['mobileScreenshot']], ['domain' => $this->domainStr]);
    }
    
    // Store the complete report in session for later use.
    $_SESSION['report_data']['pageSpeedReport'] = ['pagespeed' => $pagespeedReport];
 
    
    return $jsonOutput;
}

/**
 * Displays the PageSpeed Insights report including the performance score report.
 * This updated function expects that the JSON output from processPageSpeedInsightConcurrent()
 * contains a "pagespeed" key with a "report" sub‐object that holds desktop and mobile scores.
 *
 * It also embeds an inline script to update the global window.pageSpeedReport variable
 * so that the gauge initialization function (initPageSpeedGauges) picks up the correct scores.
 *
 * @param string $jsonData JSON-encoded PageSpeed Insights data.
 * @return string HTML output.
 */
public function showPageSpeedInsightConcurrent(string $jsonData): string
{
    // Attempt to decode the JSON data.
    $data = json_decode($jsonData, true);
    if (!is_array($data) || !isset($data['pagespeed'])) {
        return '<div class="alert alert-warning">No PageSpeed Insights data available.</div>';
    }

    $report = $data['pagespeed'];
    
    // Extract scores from the score report; default to 0 if not set.
    $scoreReport = isset($report['report']) && is_array($report['report']) ? $report['report'] : [];
    $desktopScore = isset($scoreReport['desktop']['score']) ? intval($scoreReport['desktop']['score']) : 0;
    $mobileScore  = isset($scoreReport['mobile']['score'])  ? intval($scoreReport['mobile']['score'])  : 0;
    
    // Prepare the HTML for the detailed report.
    // (This is your original HTML output for the PageSpeed report.)
    $timestamp = htmlspecialchars($report['timestamp'] ?? '');
    $url       = htmlspecialchars($report['url'] ?? '');
    $cacheHit  = htmlspecialchars($report['cache_hit'] ?? '');
    
    // Build the Score Report section.
    $scoreSection = '
    <div class="card mb-4 shadow-sm">
      <div class="card-header bg-secondary text-white">
        <h5 class="mb-0">Performance Score Report</h5>
      </div>
      <div class="card-body">
        <table class="table table-bordered table-sm">
          <thead class="table-light">
            <tr>
              <th>Platform</th>
              <th>Score (%)</th>
              <th>Status</th>
              <th>Comment</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>Desktop</td>
              <td>' . $desktopScore . '</td>
              <td>' . (isset($scoreReport['desktop']['status']) ? htmlspecialchars($scoreReport['desktop']['status']) : 'N/A') . '</td>
              <td>' . (isset($scoreReport['desktop']['message']) ? htmlspecialchars($scoreReport['desktop']['message']) : '') . '</td>
            </tr>
            <tr>
              <td>Mobile</td>
              <td>' . $mobileScore . '</td>
              <td>' . (isset($scoreReport['mobile']['status']) ? htmlspecialchars($scoreReport['mobile']['status']) : 'N/A') . '</td>
              <td>' . (isset($scoreReport['mobile']['message']) ? htmlspecialchars($scoreReport['mobile']['message']) : '') . '</td>
            </tr>
            <tr>
              <th colspan="3">Overall Average Score</th>
              <td>' . (isset($scoreReport['overall']['percent']) ? intval($scoreReport['overall']['percent']) . '% - ' . htmlspecialchars($scoreReport['overall']['comment']) : 'N/A') . '</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>';

    // Build the detailed reports for Desktop and Mobile.
    // (This part uses your existing code to render the lab metrics and diagnostics.)
    $renderPlatformReport = function(string $platform, array $reportData) {
        $allowedMetrics = [
            'timeToFirstByte'        => 'Time to First Byte',
            'firstContentfulPaint'   => 'First Contentful Paint',
            'speedIndex'             => 'Speed Index',
            'largestContentfulPaint' => 'Largest Contentful Paint',
            'interactive'            => 'Time to Interactive',
            'totalBlockingTime'      => 'Total Blocking Time',
            'cumulativeLayoutShift'  => 'Cumulative Layout Shift'
        ];
        $metricsInMilliseconds = [
            'timeToFirstByte',
            'firstContentfulPaint',
            'speedIndex',
            'largestContentfulPaint',
            'interactive',
            'totalBlockingTime'
        ];
        $formatMetricValue = function($key, $value) use ($metricsInMilliseconds) {
            if (!is_numeric($value)) return $value;
            if (in_array($key, $metricsInMilliseconds, true)) {
                return round($value / 1000, 2) . ' s';
            }
            if ($key === 'cumulativeLayoutShift') {
                return round($value, 2);
            }
            return $value;
        };
        
        $score = isset($reportData['score']) ? intval($reportData['score']) : 0;
        $metrics = isset($reportData['metrics']) && is_array($reportData['metrics']) ? $reportData['metrics'] : [];
        $diagnostics = isset($reportData['diagnostics']) && is_array($reportData['diagnostics']) ? $reportData['diagnostics'] : [];
        
        // Build the metrics table.
        $rows = '';
        $foundMetric = false;
        foreach ($allowedMetrics as $key => $label) {
            if (isset($metrics[$key])) {
                $foundMetric = true;
                $displayVal = $formatMetricValue($key, $metrics[$key]);
                $rows .= "<tr><td>" . htmlspecialchars($label) . "</td><td>" . htmlspecialchars($displayVal) . "</td></tr>";
            }
        }
        if (!$foundMetric) {
            $rows = '<tr><td colspan="2">No key metrics available.</td></tr>';
        }
        
        // Build diagnostics accordion.
        $diagHtml = '';
        if (!empty($diagnostics)) {
            foreach ($diagnostics as $i => $item) {
                $title = isset($item['title']) ? htmlspecialchars($item['title']) : 'N/A';
                $description = isset($item['description']) ? htmlspecialchars($item['description']) : '';
                $impact = isset($item['impact']) ? htmlspecialchars($item['impact']) : '';
                $recommendation = isset($item['recommendation']) ? htmlspecialchars($item['recommendation']) : '';
                $diagHtml .= <<<HTML
<div class="accordion-item">
  <h2 class="accordion-header" id="{$platform}Heading{$i}">
    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
            data-bs-target="#{$platform}Collapse{$i}" aria-expanded="false"
            aria-controls="{$platform}Collapse{$i}">
      {$title}
    </button>
  </h2>
  <div id="{$platform}Collapse{$i}" class="accordion-collapse collapse"
       aria-labelledby="{$platform}Heading{$i}" data-bs-parent="#{$platform}DiagnosticsAccordion">
    <div class="accordion-body">
      <strong>Description:</strong> {$description}<br>
      <strong>Impact:</strong> {$impact}<br>
      <strong>Recommendation:</strong> {$recommendation}
    </div>
  </div>
</div>
HTML;
            }
        } else {
            $diagHtml = '<p>No diagnostic opportunities found.</p>';
        }
        
        // Gauge canvas ID (for your gauge script)
        $canvasId = ($platform === 'Desktop') ? 'desktopPageSpeed' : 'mobilePageSpeed';
        return <<<HTML
<div class="card mb-4">
  <div class="card-header">
    <h5 class="mb-0">{$platform} Detailed Report</h5>
  </div>
  <div class="card-body">
    <div class="row mb-3">
      <div class="col-md-4 text-center">
        <canvas id="{$canvasId}" width="250" height="250"></canvas>
      </div>
      <div class="col-md-8">
        <h6>Key Lab Metrics</h6>
        <table class="table table-sm table-striped">
          <thead>
            <tr><th>Metric</th><th>Value</th></tr>
          </thead>
          <tbody>
            {$rows}
          </tbody>
        </table>
      </div>
    </div>
    <h6>Diagnostics &amp; Opportunities</h6>
    <div class="accordion" id="{$platform}DiagnosticsAccordion">
      {$diagHtml}
    </div>
  </div>
</div>
HTML;
    };

    $desktopHtml = $renderPlatformReport('Desktop', isset($report['desktop']) ? $report['desktop'] : []);
    $mobileHtml  = $renderPlatformReport('Mobile',  isset($report['mobile']) ? $report['mobile'] : []);

    // Build top-level tabs for Desktop and Mobile.
    $tabs = '
    <ul class="nav nav-tabs" id="pagespeedReportTab" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="desktop-tab" data-bs-toggle="tab" data-bs-target="#desktopReportTab" type="button" role="tab" aria-controls="desktopReportTab" aria-selected="true">Desktop</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="mobile-tab" data-bs-toggle="tab" data-bs-target="#mobileReportTab" type="button" role="tab" aria-controls="mobileReportTab" aria-selected="false">Mobile</button>
      </li>
    </ul>';

    $tabContent = '
    <div class="tab-content" id="pagespeedReportTabContent">
      <div class="tab-pane fade show active" id="desktopReportTab" role="tabpanel" aria-labelledby="desktop-tab">
        ' . $desktopHtml . '
      </div>
      <div class="tab-pane fade" id="mobileReportTab" role="tabpanel" aria-labelledby="mobile-tab">
        ' . $mobileHtml . '
      </div>
    </div>';

    // Build an inline script to update the global pageSpeedReport variable and reinitialize gauges.
    $globalScript = '<script type="text/javascript">
      window.pageSpeedReport = {
        desktop: { score: ' . $desktopScore . ' },
        mobile: { score: ' . $mobileScore . ' }
      };
      console.log("Updated global pageSpeedReport:", window.pageSpeedReport);
      if (typeof initPageSpeedGauges === "function") {
          initPageSpeedGauges();
      }
    </script>';

    // Assemble the final HTML.
    $html = '
<div class="container my-4">
  <h3>PageSpeed Insights Detailed Report</h3>
  <p><strong>URL:</strong> ' . $url . '</p>
  <p><strong>Report Generated:</strong> ' . $timestamp . '</p>
  <p><strong>Cache Hit Timestamp:</strong> ' . $cacheHit . '</p>
  ' . $scoreSection . '
  ' . $tabs . '
  ' . $tabContent . '
</div>
' . $globalScript;

// Return the final assembled HTML.
    return $html;
}





/**
 * filterReport()
 *
 * Given the full decoded PageSpeed API response in $data, this function extracts and returns only the
 * fields necessary for your detailed SEO analysis. It removes extraneous data.
 *
 * The returned array includes:
 * - score: Performance score (multiplied by 100)
 * - metrics: Key lab metrics (e.g. FCP, LCP, TTI, CLS, TBT)
 * - diagnostics: List of opportunities (each with title, description, impact, and recommendation)
 * - category_scores: Category scores from Lighthouse
 * - score_class: Textual description (if any) for the score
 * - version: Lighthouse version used
 * - fetchTime: The fetch time (if available)
 *
 * @param array $data Full decoded API response.
 * @return array Filtered report.
 */
private function filterReport(array $data): array {
    $lr = $data['lighthouseResult'] ?? [];
    
    // Extract performance score
    $score = isset($lr['categories']['performance']['score'])
        ? intval($lr['categories']['performance']['score'] * 100)
        : 0;
    
    // Extract lab metrics from audits->metrics->details->items[0]
    $metrics = [];
    if (isset($lr['audits']['metrics']['details']['items'][0])) {
        $metrics = $lr['audits']['metrics']['details']['items'][0];
    }
    
    // Extract diagnostics (only opportunities)
    $diagnostics = [];
    if (isset($lr['audits']) && is_array($lr['audits'])) {
        foreach ($lr['audits'] as $auditId => $audit) {
            if (isset($audit['details']['type']) && $audit['details']['type'] === 'opportunity') {
                $diagnostics[] = [
                    'title'         => $audit['title'] ?? $auditId,
                    'description'   => $audit['description'] ?? '',
                    'impact'        => $audit['impact'] ?? '',
                    'recommendation'=> $audit['recommendation'] ?? ''
                ];
            }
        }
    }
    
    // Additional fields
    $category_scores = $lr['category_scores'] ?? [];
    $score_class = $lr['score_class'] ?? [];
    $version = $lr['version'] ?? '';
    $fetchTime = $lr['fetchTime'] ?? '';

    return [
        'score'           => $score,
        'metrics'         => $metrics,
        'diagnostics'     => $diagnostics,
        'category_scores' => $category_scores,
        'score_class'     => $score_class,
        'version'         => $version,
        'fetchTime'       => $fetchTime
    ];
}


    /*===================================================================
     * CLEAN OUT HANDLER
     *=================================================================== 
     */
 
     public function cleanOut(): void
     {
         // Ensure that the session is active
         if (session_status() !== PHP_SESSION_ACTIVE) {
             session_start();
             log_message('debug', "Session started in cleanOut().");
         } else {
             log_message('debug', "Session already active in cleanOut().");
         }
     
         log_message('debug', "CleanOut Function Called");
     
         // List the module keys that produce a single pass/improve/error in their 'report'.
         $moduleKeys = [
             'meta',
             'headingReport',
             'keyCloudReport',
             'linksReport',
             'sitecardsReport',
             'imageAltReport',
             'textRatio',
             'serverInfo',
             'schemaReport',
             'socialUrlsReport',
             'pageAnalyticsReport',
             'pageSpeedReport',
         ];
     
         $totalPassed  = 0;
         $totalImprove = 0;
         $totalErrors  = 0;
     
         // Log the entire session data for debugging purposes
         log_message('debug', "Session report_data: " . print_r($_SESSION['report_data'], true));
     
         // Loop over each module and add up the scores.
         foreach ($moduleKeys as $key) {
             if (!isset($_SESSION['report_data'][$key]['report'])) {
                 log_message('debug', "Module key '$key' not found in session.");
                 continue;
             }
             $rpt = $_SESSION['report_data'][$key]['report'];
     
             $passed  = (int) ($rpt['passed']  ?? 0);
             $improve = (int) ($rpt['improve'] ?? 0);
             $errors  = (int) ($rpt['errors']  ?? 0);
     
             log_message('debug', "Module '$key' values: passed=$passed, improve=$improve, errors=$errors");
     
             $totalPassed  += $passed;
             $totalImprove += $improve;
             $totalErrors  += $errors;
         }
     
         $totalChecks = $totalPassed + $totalImprove + $totalErrors;
         $maxPoints = $totalChecks * 2;
         $currentPoints = ($totalPassed * 2) + ($totalImprove * 1);
         $overallPercent = ($maxPoints > 0) ? round(($currentPoints / $maxPoints) * 100) : 0;
     
         log_message('debug', "Total Passed: $totalPassed, Improve: $totalImprove, Errors: $totalErrors");
         log_message('debug', "Total Checks: $totalChecks, Max Points: $maxPoints, Current Points: $currentPoints, Overall Percent: $overallPercent");
     
         $scoreData = [
             'passed'  => $totalPassed,
             'improve' => $totalImprove,
             'errors'  => $totalErrors,
             'percent' => $overallPercent,
         ];
     
         $consolidatedReports = [
             'overallScore'        => $scoreData,
             'meta'                => $_SESSION['report_data']['meta']                ?? [],
             'headingReport'       => $_SESSION['report_data']['headingReport']       ?? [],
             'keyCloudReport'      => $_SESSION['report_data']['keyCloudReport']      ?? [],
             'linksReport'         => $_SESSION['report_data']['linksReport']         ?? [],
             'sitecardsReport'     => $_SESSION['report_data']['sitecardsReport']     ?? [],
             'imageAltReport'      => $_SESSION['report_data']['imageAltReport']      ?? [],
             'textRatio'           => $_SESSION['report_data']['textRatio']           ?? [],
             'serverInfo'          => $_SESSION['report_data']['serverInfo']          ?? [],
             'schemaReport'        => $_SESSION['report_data']['schemaReport']        ?? [],
             'socialUrlsReport'    => $_SESSION['report_data']['socialUrlsReport']    ?? [],
             'pageAnalyticsReport' => $_SESSION['report_data']['pageAnalyticsReport'] ?? [],
             'pageSpeedReport'     => $_SESSION['report_data']['pageSpeedReport']     ?? [],
         ];
     
         $finalReportJson = json_encode($consolidatedReports);
         if (json_last_error() !== JSON_ERROR_NONE) {
             error_log("JSON encoding error in cleanOut(): " . json_last_error_msg());
         } else {
             log_message('debug', "Final report JSON: " . $finalReportJson);
         }
     
         // Prepare update parameters and where clause
         $updateData = [
             'score'        => json_encode($scoreData), 
             'completed'    => 'yes' // Ensure that your DB schema accepts this value
         ];
         $whereClause = ['domain' => $this->domainStr];
     
         // Log the update data
         log_message('debug', "CleanOut: Updating domains_data with: " . print_r($updateData, true) . " WHERE " . print_r($whereClause, true));
     
         // Update the database with the final consolidated report and mark the analysis as complete.
         $error = updateToDbPrepared(
             $this->con,
             'domains_data',
             $updateData,
             $whereClause,
             false,   // Use default type definitions (all strings)
             '',      // No custom type definition string
             true     // Enable debug mode to output SQL query details
         );
     
         if (!empty($error)) {
             log_message('debug', "Error updating domains_data: " . $error);
         } else {
             log_message('debug', "Update successful. Domain " . $this->domainStr . " marked as completed with score.");
         }
     
         // Optionally add to recent sites.
         $data = mysqliPreparedQuery(
             $this->con,
             "SELECT * FROM domains_data WHERE domain=?",
             's',
             [$this->domainStr]
         );
         if ($data !== false) {
             $pageSpeedInsightData = json_decode($data['page_speed_insight'] ?? '', true);
             $desktopScore = (empty($pageSpeedInsightData['pagespeed']['report']['desktop']['score']))
                 ? 0
                 : $pageSpeedInsightData['pagespeed']['report']['desktop']['score'];
     
             $username = $_SESSION['twebUsername'] ?? 'Guest';
             $ip = $data['server_ip'] ?? 'N/A';
             $other = json_encode([$overallPercent, $desktopScore]);
             addToRecentSites($this->con, $this->domainStr, $ip, $username, $other);
             log_message('debug', "Added domain to recent sites: " . $this->domainStr);
         } else {
             log_message('debug', "No data found in DB for domain: " . $this->domainStr);
         }
     }
     


}
?>
