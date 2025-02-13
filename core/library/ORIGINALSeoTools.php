<?php
/**
 * SeoTools.php
 *
 * This class encapsulates all SEO and website performance analysis functions.
 * Each method performs a specific test (meta data, headings, image alt tags, text ratio,
 * gzip compression, mobile friendliness, robots.txt, sitemap, analytics & DOCTYPE,
 * W3C validity, encoding, indexed pages, backlinks/Alexa/site worth, social data,
 * visitors localization, page speed insight, availability checker, email privacy,
 * safe browsing, server IP, speed tips, and clean out) and updates the database
 * accordingly. The methods return structured data (or HTML snippets) that are used
 * to render AJAX responses.
 *
 * Note: Helper functions (e.g. clean_url(), raino_trim(), serBase(), updateToDbPrepared(),
 * getMyData(), calTextRatio(), compressionTest(), getMobileFriendly(), getHttpCode(),
 * googleIndex(), alexaRank(), backlinkCount(), getSocialData(), load_html(), truncate(),
 * safeBrowsing(), host_info(), domainAvailability(), typos(), addToRecentSites(), delFile(),
 * lang_code_to_lnag()) must be defined elsewhere in your project.
 *
 * Author: Balaji
 * Copyright 2023 ProThemes.Biz
 */

class SeoTools {

    protected $con;
    protected $lang;
    protected $themePath;
    protected $sepUnique;

    // Constructor accepts the database connection, language array, and theme path.
    public function __construct($con, $lang, $themePath) {
        $this->con = $con;
        $this->lang = $lang;
        $this->themePath = $themePath;
        $this->sepUnique = '!!!!8!!!!';
    }

    // ------------------- META DATA -------------------
    /**
     * processMetaData()
     * Extracts the page title, description, and keywords from HTML,
     * updates the database, and returns the meta data.
     */
    public function processMetaData($html, $domainStr) {

        
        $doc = new DOMDocument();
        // Suppress warnings from malformed HTML.
        @$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $title = $doc->getElementsByTagName('title')->item(0)->nodeValue;
        $description = $keywords = '';
        foreach ($doc->getElementsByTagName('meta') as $meta) {
            $name = strtolower($meta->getAttribute('name'));
            if ($name == 'description') {
                $description = $meta->getAttribute('content');
            }
            if ($name == 'keywords') {
                $keywords = $meta->getAttribute('content');
            }
        }
        $updateStr = serBase([$title, $description, $keywords]);
        updateToDbPrepared($this->con, 'domains_data', ['meta_data' => $updateStr], ['domain' => $domainStr]);
        return [
            'title' => $title,
            'description' => $description,
            'keywords' => $keywords,
            'lenTitle' => mb_strlen($title, 'utf8'),
            'lenDescription' => mb_strlen($description, 'utf8'),
        ];
    }

    // ------------------- HEADINGS -------------------
    /**
     * processHeadings()
     * Extracts H1–H6 tags from HTML, builds an HTML summary table,
     * updates the database, and returns heading counts and the table HTML.
     */
    public function processHeadings($html, $domainStr) {
        $doc = new DOMDocument();
        @$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $tags = ['h1','h2','h3','h4','h5','h6'];
        $headings = [];
        $headStr = '';
        $hideCount = 0;
        $hideClass = '';
        foreach ($tags as $tag) {
            $nodes = $doc->getElementsByTagName($tag);
            foreach ($nodes as $node) {
                if ($hideCount == 3) {
                    $hideClass = 'hideTr hideTr1';
                }
                $text = trim(strip_tags($node->textContent));
                $headings[$tag][] = $text;
                if (strlen($text) >= 100) {
                    $headStr .= '<tr class="'.$hideClass.'"><td>&lt;'.strtoupper($tag).'&gt; <b>' . truncate($text, 20, 100) . '</b> &lt;/'.strtoupper($tag).'&gt;</td></tr>';
                } else {
                    $headStr .= '<tr class="'.$hideClass.'"><td>&lt;'.strtoupper($tag).'&gt; <b>' . $text . '</b> &lt;/'.strtoupper($tag).'&gt;</td></tr>';
                }
                $hideCount++;
            }
        }
        $updateStr = serBase([$headings, $headStr]);
        updateToDbPrepared($this->con, 'domains_data', ['headings' => $updateStr], ['domain' => $domainStr]);
        return [
            'counts' => [
                'h1' => isset($headings['h1']) ? count($headings['h1']) : 0,
                'h2' => isset($headings['h2']) ? count($headings['h2']) : 0,
                'h3' => isset($headings['h3']) ? count($headings['h3']) : 0,
                'h4' => isset($headings['h4']) ? count($headings['h4']) : 0,
                'h5' => isset($headings['h5']) ? count($headings['h5']) : 0,
                'h6' => isset($headings['h6']) ? count($headings['h6']) : 0,
            ],
            'html' => $headStr,
        ];
    }

    // ------------------- IMAGE ALT TAGS -------------------
    /**
     * processImageAlts()
     * Analyzes the HTML for <img> tags missing the "alt" attribute,
     * updates the database, and returns the total number of images,
     * the number missing alt text, and an array of such image sources.
     */
    public function processImageAlts($html, $domainStr) {
        // Load HTML using simple_html_dom (assumed available)
        $dom = load_html($html);
        $imageCount = 0;
        $missingAltCount = 0;
        $missingAltDetails = [];
        if (!empty($dom)) {
            foreach ($dom->find('img') as $img) {
                $src = trim($img->getAttribute('src'));
                if ($src != "") {
                    $imageCount++;
                    if (trim($img->getAttribute('alt')) == "") {
                        $missingAltCount++;
                        $missingAltDetails[] = $src;
                    }
                }
            }
        }
        $updateStr = serBase([$imageCount, $missingAltCount, $missingAltDetails]);
        updateToDbPrepared($this->con, 'domains_data', ['image_alt' => $updateStr], ['domain' => $domainStr]);
        return [
            'total' => $imageCount,
            'missingAlt' => $missingAltCount,
            'details' => $missingAltDetails,
        ];
    }

    // ------------------- TEXT RATIO -------------------
    /**
     * processTextRatio()
     * Calculates the ratio of visible text to the overall HTML content.
     */
    public function processTextRatio($html, $domainStr) {
        $ratio = calTextRatio($html);  // Expected: [HTML length, text length, percentage]
        $updateStr = serBase($ratio);
        updateToDbPrepared($this->con, 'domains_data', ['ratio_data' => $updateStr], ['domain' => $domainStr]);
        return $ratio;
    }

    // ------------------- GZIP COMPRESSION -------------------
    /**
     * processGzip()
     * Checks whether the website uses GZIP compression by comparing
     * the uncompressed and compressed sizes. Updates DB and returns results.
     */
    public function processGzip($my_url_host, $domainStr, $true, $false) {
        $outData = compressionTest($my_url_host);  // [comSize, unComSize, isGzip, gzdataSize, header, body]
        list($comSize, $unComSize, $isGzip, $gzdataSize, $header, $body) = $outData;
        if (trim($body) == "") {
            $gzipHead = $this->lang['AN10'];
            $gzipClass = 'improveBox';
        } else {
            $body = 'Data!';
            if ($isGzip) {
                $percentage = round(((intval($unComSize) - intval($comSize)) / intval($unComSize)) * 100, 1);
                $gzipClass = 'passedBox';
                $gzipHead = $this->lang['AN42'];
                $gzipBody = $true . ' ' . str_replace(
                    ['[total-size]', '[compressed-size]', '[percentage]'],
                    [size_as_kb($unComSize), size_as_kb($comSize), $percentage],
                    $this->lang['AN41']
                );
            } else {
                $percentage = round(((intval($unComSize) - intval($gzdataSize)) / intval($unComSize)) * 100, 1);
                $gzipClass = 'errorBox';
                $gzipHead = $this->lang['AN43'];
                $gzipBody = $false . ' ' . str_replace(
                    ['[total-size]', '[compressed-size]', '[percentage]'],
                    [size_as_kb($unComSize), size_as_kb($gzdataSize), $percentage],
                    $this->lang['AN44']
                );
            }
        }
        $header = 'Data!';
        $updateStr = serBase([$comSize, $unComSize, $isGzip, $gzdataSize, $header, $body]);
        updateToDbPrepared($this->con, 'domains_data', ['gzip' => $updateStr], ['domain' => $domainStr]);
        return [
            'class' => $gzipClass,
            'head'  => $gzipHead,
            'body'  => $gzipBody,
        ];
    }

    // ------------------- MOBILE FRIENDLINESS -------------------
    /**
     * processMobileFriendly()
     * Checks mobile friendliness using an external API, saves a mobile screenshot,
     * updates DB, and returns status and preview.
     */
    public function processMobileFriendly($my_url, $domainStr) {
        $jsonData = getMobileFriendly($my_url);  // Expected: ['score'=>..., 'passed'=>..., 'screenshot'=>...]
        $mobileScore = intval($jsonData['score']);
        $isMobileFriendly = $jsonData['passed'];
        if ($jsonData != null || $jsonData == "") {
            if ($isMobileFriendly) {
                $mobileClass = 'passedBox';
                $friendlyMsg = $this->lang['AN116'] . '<br>' . str_replace('[score]', $mobileScore, $this->lang['AN117']);
            } else {
                $mobileClass = 'errorBox';
                $friendlyMsg = $this->lang['AN118'] . '<br>' . str_replace('[score]', $mobileScore, $this->lang['AN117']);
            }
            $screenData = $jsonData['screenshot'];
            // Store mobile preview screenshot.
            storeMobilePreview($domainStr, $screenData);
            $mobileScreenData = ($screenData == '') ? '' : '<img src="data:image/jpeg;base64,' . $screenData . '" />';
        } else {
            $mobileClass = 'errorBox';
            $friendlyMsg = $this->lang['AN10'];
            $mobileScreenData = $this->lang['AN119'];
        }
        $mobData = [$mobileScore, $isMobileFriendly];
        $updateStr = serBase($mobData);
        updateToDbPrepared($this->con, 'domains_data', ['mobile_fri' => $updateStr], ['domain' => $domainStr]);
        return [
            'class' => $mobileClass,
            'msg' => $friendlyMsg,
            'screen' => $mobileScreenData,
            'checkMsg' => $this->lang['AN195'],
            'screenMsg' => $this->lang['AN196']
        ];
    }

    // ------------------- ROBOTS.TXT -------------------
    /**
     * processRobots()
     * Checks whether a robots.txt file exists, updates DB, and returns its status.
     */
    public function processRobots($inputHost, $domainStr) {
        $robotLink = $inputHost . '/robots.txt';
        $httpCode = getHttpCode($robotLink);
        $updateStr = base64_encode($httpCode);
        updateToDbPrepared($this->con, 'domains_data', ['robots' => $updateStr], ['domain' => $domainStr]);
        if ($httpCode == '404') {
            $robotClass = 'errorBox';
            $robotMsg = $this->lang['AN74'] . '<br><a href="' . $robotLink . '" title="' . $this->lang['AN75'] . '" rel="nofollow" target="_blank">' . $robotLink . '</a>';
        } else {
            $robotClass = 'passedBox';
            $robotMsg = $this->lang['AN73'] . '<br><a href="' . $robotLink . '" title="' . $this->lang['AN75'] . '" rel="nofollow" target="_blank">' . $robotLink . '</a>';
        }
        return [
            'class' => $robotClass,
            'msg' => $robotMsg,
            'tip' => $this->lang['AN187']
        ];
    }

    // ------------------- SITEMAP -------------------
    /**
     * processSitemap()
     * Checks for a sitemap, updates the DB, and returns sitemap status.
     */
    public function processSitemap($inputHost, $domainStr) {
        $sitemapInfo = getSitemapInfo($inputHost);
        $httpCode = $sitemapInfo['httpCode'];
        $sitemapLink = $sitemapInfo['sitemapLink'];
        $updateStr = base64_encode($httpCode);
        updateToDbPrepared($this->con, 'domains_data', ['sitemap' => $updateStr], ['domain' => $domainStr]);
        if ($httpCode == '404') {
            $sitemapClass = 'errorBox';
            $sitemapMsg = $this->lang['AN71'] . '<br><a href="' . $sitemapLink . '" title="' . $this->lang['AN72'] . '" rel="nofollow" target="_blank">' . $sitemapLink . '</a>';
        } else {
            $sitemapClass = 'passedBox';
            $sitemapMsg = $this->lang['AN70'] . '<br><a href="' . $sitemapLink . '" title="' . $this->lang['AN72'] . '" rel="nofollow" target="_blank">' . $sitemapLink . '</a>';
        }
        return [
            'class' => $sitemapClass,
            'msg' => $sitemapMsg,
            'tip' => $this->lang['AN188']
        ];
    }

    // ------------------- ANALYTICS & DOCTYPE -------------------
    /**
     * processAnalyticsDocType()
     * Checks for analytics tracking code and detects the document DOCTYPE.
     */
    public function processAnalyticsDocType($sourceData, $domainStr) {
        // Check for Google Analytics tracking code.
        if (preg_match("/\bua-\d{4,9}-\d{1,4}\b/i", $sourceData) || check_str_contains($sourceData, "gtag('")) {
            $analyticsClass = 'passedBox';
            $analyticsMsg = $this->lang['AN154'];
            $anCheck = true;
        } else {
            $analyticsClass = 'errorBox';
            $analyticsMsg = $this->lang['AN153'];
            $anCheck = false;
        }
        // Define known DOCTYPEs.
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
        preg_match("#<!DOCTYPE[^>]*>#is", $sourceData, $matches);
        if (!isset($matches[0])) {
            $docTypeMsg = $this->lang['AN155'];
            $docTypeClass = 'improveBox';
            $docType = '';
        } else {
            $docType = array_search(strtolower(preg_replace('/\s+/', ' ', trim($matches[0]))), array_map('strtolower', $doctypes));
            $docTypeMsg = $this->lang['AN156'] . ' ' . $docType;
            $docTypeClass = 'passedBox';
        }
        $updateStr = serBase([$anCheck, !empty($docType), $docType]);
        updateToDbPrepared($this->con, 'domains_data', ['analytics' => $updateStr], ['domain' => $domainStr]);
        return [
            'analytics' => ['class' => $analyticsClass, 'msg' => $analyticsMsg, 'tip' => $this->lang['AN210']],
            'doctype' => ['class' => $docTypeClass, 'msg' => $docTypeMsg, 'tip' => $this->lang['AN212']]
        ];
    }

    // ------------------- W3C VALIDITY -------------------
    /**
     * processW3CValidity()
     * Uses the W3C validator API to check if the document is valid HTML.
     */
    public function processW3CValidity($my_url_host, $domainStr) {
        $w3Data = curlGET('https://validator.w3.org/nu/?doc=http%3A%2F%2F' . $my_url_host . '%2F');
        if ($w3Data != '') {
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
        updateToDbPrepared($this->con, 'domains_data', ['w3c' => $w3DataCheck], ['domain' => $domainStr]);
        return [
            'class' => 'lowImpactBox',
            'msg' => $w3cMsg,
            'tip' => $this->lang['AN211']
        ];
    }

    // ------------------- ENCODING -------------------
    /**
     * processEncoding()
     * Checks the charset meta tag to determine the document's encoding.
     */
    public function processEncoding($sourceData, $domainStr) {
        $pattern = '<meta[^>]+charset=[\'"]?(.*?)[\'"]?[\/\s]>';
        preg_match("#{$pattern}#is", $sourceData, $matches);
        $charset = isset($matches[1]) ? trim(mb_strtoupper($matches[1])) : null;
        if ($charset) {
            $encodingClass = 'passedBox';
            $encodingMsg = $this->lang['AN159'] . ' ' . $charset;
        } else {
            $encodingClass = 'errorBox';
            $encodingMsg = $this->lang['AN160'];
        }
        $updateStr = base64_encode($charset);
        updateToDbPrepared($this->con, 'domains_data', ['encoding' => $updateStr], ['domain' => $domainStr]);
        return [
            'class' => $encodingClass,
            'msg' => $encodingMsg,
            'tip' => $this->lang['AN213']
        ];
    }

    // ------------------- INDEXED PAGES -------------------
    /**
     * processIndexedPages()
     * Uses a Google index query to count how many pages are indexed for the site.
     */
    public function processIndexedPages($my_url_host, $domainStr) {
        $indexed = trim(str_replace(',', '', googleIndex($my_url_host)));
        $value = intval($indexed);
        if ($value < 50) {
            $datVal = 25;
            $class = 'errorBox';
            $progress = 'danger';
        } elseif ($value < 200) {
            $datVal = 75;
            $class = 'improveBox';
            $progress = 'warning';
        } else {
            $datVal = 100;
            $class = 'passedBox';
            $progress = 'success';
        }
        $updateStr = base64_encode($indexed);
        updateToDbPrepared($this->con, 'domains_data', ['indexed' => $updateStr], ['domain' => $domainStr]);
        $bar = '<div style="width:' . $datVal . '%" aria-valuemax="' . $datVal . '" aria-valuemin="0" aria-valuenow="' . $datVal . '" role="progressbar" class="progress-bar progress-bar-' . $progress . '">
            ' . number_format($value) . ' ' . $this->lang['AN162'] . '
        </div>';
        return [
            'class' => $class,
            'bar' => $bar,
            'tip' => $this->lang['AN214']
        ];
    }

    // ------------------- BACKLINKS, ALEXA, SITE WORTH -------------------
    /**
     * processBacklinksAlexaWorth()
     * Retrieves Alexa ranking data, backlink count, calculates site worth,
     * updates the database, and returns formatted output.
     */
    public function processBacklinksAlexaWorth($my_url_host, $domainStr) {
        $alexa = alexaRank($my_url_host);
        $alexa[3] = backlinkCount(clean_url($my_url_host), $this->con);
        $updateStr = serBase([(string)$alexa[0], (string)$alexa[1], (string)$alexa[2], (string)$alexa[3]]);
        updateToDbPrepared($this->con, 'domains_data', ['alexa' => $updateStr], ['domain' => $domainStr]);
        $alexa_rank = $alexa[0];
        $alexa_back = intval($alexa[3]);
        if ($alexa_back < 50) {
            $datVal = 25;
            $class = 'errorBox';
            $progress = 'danger';
        } elseif ($alexa_back < 100) {
            $datVal = 75;
            $class = 'improveBox';
            $progress = 'warning';
        } else {
            $datVal = 100;
            $class = 'passedBox';
            $progress = 'success';
        }
        $backlinksBar = '<div style="width:' . $datVal . '%" aria-valuemax="' . $datVal . '" aria-valuemin="0" aria-valuenow="' . $datVal . '" role="progressbar" class="progress-bar progress-bar-' . $progress . '">
            ' . number_format($alexa_back) . ' ' . $this->lang['AN163'] . '
        </div>';
        if ($alexa_rank == 'No Global Rank')
            $alexaMsg = $this->lang['AN165'];
        else
            $alexaMsg = ordinalNum(str_replace(',', '', $alexa_rank)) . ' ' . $this->lang['AN164'];
        $globalRank = ($alexa_rank == 'No Global Rank' ? '0' : $alexa_rank);
        $worth = "$" . number_format(calPrice($globalRank)) . " USD";
        return [
            'backlinks' => ['class' => $class, 'bar' => $backlinksBar, 'tip' => $this->lang['AN215']],
            'alexa' => ['msg' => $alexaMsg, 'tip' => $this->lang['AN218']],
            'worth' => ['msg' => $worth, 'tip' => $this->lang['AN217']]
        ];
    }

    // ------------------- SOCIAL DATA -------------------
    /**
     * processSocialData()
     * Extracts social media statistics from the source HTML, updates the database,
     * and returns the formatted social data.
     */
    public function processSocialData($sourceData, $domainStr, $true, $false) {
        $socialData = getSocialData($sourceData);
        $fb = $socialData['fb'];
        $twit = $socialData['twit'];
        $insta = $socialData['insta'];
        $stumble = 0;
        $updateStr = serBase([$fb, $twit, $insta, $stumble]);
        updateToDbPrepared($this->con, 'domains_data', ['social' => $updateStr], ['domain' => $domainStr]);
        $fb = ($fb === '-') ? $false : $true . ' ' . $fb;
        $twit = ($twit === '-') ? $false : $true . ' ' . $twit;
        $insta = ($insta === '-') ? $false : $true . ' ' . $insta;
        $html = '<div class="altImgGroup">
                    <div class="social-box"><i class="fa fa-facebook social-facebook"></i> Facebook: ' . $fb . '</div>
                    <div class="social-box"><i class="fa fa-twitter social-linkedin"></i> Twitter: ' . $twit . '</div>
                    <div class="social-box"><i class="fa fa-instagram social-google"></i> Instagram: ' . $insta . '</div>
                 </div>';
        return [
            'html' => $html,
            'tip' => $this->lang['AN216']
        ];
    }

    // ------------------- VISITORS LOCALIZATION -------------------
    /**
     * processVisitorsLocalization()
     * Retrieves visitor data (from Alexa) from the database and returns an HTML table.
     */
    public function processVisitorsLocalization($domainStr) {
        $data = mysqliPreparedQuery($this->con, "SELECT alexa FROM domains_data WHERE domain=?", 's', [$domainStr]);
        $visitorsMsg = '';
        if ($data !== false) {
            $alexa = decSerBase($data['alexa']);
            $alexaDatas = [
                ['', 'Popularity at', $alexa[1]],
                ['', 'Regional Rank', $alexa[2]]
            ];
            foreach ($alexaDatas as $a) {
                $visitorsMsg .= '<tr><td>' . $a[1] . '</td><td>' . $a[2] . '</td></tr>';
            }
        }
        $html = '<table class="table table-hover table-bordered table-striped"><tbody>' . $visitorsMsg . '</tbody></table>';
        return [
            'html' => $html,
            'tip' => $this->lang['AN219']
        ];
    }

    // ------------------- PAGE SPEED INSIGHT -------------------
    /**
     * processPageSpeedInsight()
     * Retrieves desktop and mobile PageSpeed scores via an external API,
     * updates the database, and returns HTML (including JavaScript gauge code).
     */
    public function processPageSpeedInsight($inputHost, $my_url_host, $domainStr) {
        $desktopScore = pageSpeedInsightChecker($inputHost, 'desktop');
        $mobileScore = pageSpeedInsightChecker($inputHost, 'mobile');
        $updateStr = serBase([$desktopScore, $mobileScore]);
        updateToDbPrepared($this->con, 'domains_data', ['page_speed_insight' => $updateStr], ['domain' => $domainStr]);
        $desktopJS = <<<EOT
<script>
var desktopPageSpeed = new Gauge({
    renderTo  : 'desktopPageSpeed',
    width     : 250,
    height    : 250,
    glow      : true,
    units     : '{$this->lang['117']}',
    title     : '{$this->lang['118']}',
    minValue  : 0,
    maxValue  : 100,
    majorTicks: ['0','20','40','60','80','100'],
    minorTicks: 5,
    strokeTicks: true,
    valueFormat: { int: 2, dec: 0, text: '%' },
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
desktopPageSpeed.onready = function() {
    desktopPageSpeed.setValue($desktopScore);
};
desktopPageSpeed.draw();
</script>
EOT;
        $mobileJS = <<<EOT
<script>
var mobilePageSpeed = new Gauge({
    renderTo  : 'mobilePageSpeed',
    width     : 250,
    height    : 250,
    glow      : true,
    units     : '{$this->lang['117']}',
    title     : '{$this->lang['119']}',
    minValue  : 0,
    maxValue  : 100,
    majorTicks: ['0','20','40','60','80','100'],
    minorTicks: 5,
    strokeTicks: true,
    valueFormat: { int: 2, dec: 0, text: '%' },
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
mobilePageSpeed.onready = function() {
    mobilePageSpeed.setValue($mobileScore);
};
mobilePageSpeed.draw();
</script>
EOT;
        $desktopClass = (intval($desktopScore) < 50) ? 'errorBox' : ((intval($desktopScore) < 79) ? 'improveBox' : 'passedBox');
        $mobileClass = (intval($mobileScore) < 50) ? 'errorBox' : ((intval($mobileScore) < 79) ? 'improveBox' : 'passedBox');
        $desktopHTML = '<div class="' . $desktopClass . '"><canvas id="desktopPageSpeed"></canvas>' . $desktopJS . '</div>';
        $mobileHTML = '<div class="' . $mobileClass . '"><canvas id="mobilePageSpeed"></canvas>' . $mobileJS . '</div>';
        return [
            'desktop' => $desktopHTML,
            'mobile' => $mobileHTML,
            'tip' => [$this->lang['AN220'], $this->lang['AN221']]
        ];
    }

    // ------------------- KEY CLOUD -------------------
    /**
     * processKeycloud()
     * Processes keyword cloud data using the KD class and updates the DB.
     */
    public function processKeycloud($sourceData, $domainStr) {
        $obj = new KD();
        $obj->domain = ""; // Set accordingly if needed.
        $obj->domainData = $sourceData;
        $resdata = $obj->result();
        $keyData = '';
        $keyCount = 0;
        $outArr = [];
        foreach ($resdata as $outData) {
            if (isset($outData['keyword'])) {
                $keyword = trim($outData['keyword']);
                if ($keyword != "") {
                    $outArr[] = [$keyword, $outData['count'], $outData['percent']];
                    $keyData .= '<li><span class="keyword">' . $keyword . '</span><span class="number">' . $outData['count'] . '</span></li>';
                    $keyCount++;
                    if ($keyCount == 15) break;
                }
            }
        }
        $updateStr = serBase([$keyCount, $outArr]);
        updateToDbPrepared($this->con, 'domains_data', ['keywords_cloud' => $updateStr], ['domain' => $domainStr]);
        $html = ($keyCount != 0) ? '<ul class="keywordsTags">' . $keyData . '</ul>' : $this->lang['AN29'];
        return [
            'html' => $html,
            'tip' => $this->lang['AN179']
        ];
    }

    // ------------------- KEYWORD CONSISTENCY -------------------
    /**
     * processKeywordConsistency()
     * Checks if the given keywords appear in the title, description, and headings.
     */
    public function processKeywordConsistency($title, $description, $headingsArray, $keywordsArray) {
        $keywordConsistencyData = '';
        $score = 0;
        $hideCount = 1;
        foreach ($keywordsArray as $keywordData) {
            $keyword = $keywordData[0];
            $count = $keywordData[1];
            $inTitle = check_str_contains($title, $keyword, true);
            $inDescription = check_str_contains($description, $keyword, true);
            $inHeadings = false;
            foreach ($headingsArray as $tag => $texts) {
                foreach ($texts as $text) {
                    if (check_str_contains($text, $keyword, true)) {
                        $inHeadings = true;
                        break 2;
                    }
                }
            }
            if ($inTitle) $score++;
            if ($inDescription) $score++;
            if ($inHeadings) $score++;
            $hideClass = ($hideCount == 5) ? 'hideTr hideTr3' : '';
            $keywordConsistencyData .= '<tr class="'.$hideClass.'">
                <td>'.$keyword.'</td>
                <td>'.$count.'</td>
                <td>'.($inTitle ? $this->lang['True'] : $this->lang['False']).'</td>
                <td>'.($inDescription ? $this->lang['True'] : $this->lang['False']).'</td>
                <td>'.($inHeadings ? $this->lang['True'] : $this->lang['False']).'</td>
            </tr>';
            $hideCount++;
        }
        $class = ($score == 0) ? 'errorBox' : (($score < 4) ? 'improveBox' : 'passedBox');
        $html = '<table class="table table-striped"><thead><tr>
                    <th>'.$this->lang['AN31'].'</th>
                    <th>'.$this->lang['AN32'].'</th>
                    <th>'.$this->lang['AN33'].'</th>
                    <th>'.$this->lang['AN34'].'</th>
                    <th>&lt;H&gt;</th></tr></thead><tbody>'.$keywordConsistencyData.'</tbody></table>';
        return [
            'html' => $html,
            'class' => $class,
            'tip' => $this->lang['AN180']
        ];
    }

    // ------------------- BROKEN LINKS -------------------
    /**
     * processBrokenLinks()
     * Checks both internal and external links for HTTP 404 errors,
     * updates the database, and returns an HTML table of broken links.
     */
    public function processBrokenLinks($int_data, $ext_data, $inputHost, $domainStr) {
        $brokenLinks = '';
        $bLinks = [];
        $totalCount = 0;
        foreach ($int_data as $link) {
            $iLink = trim($link['href']);
            if (substr($iLink, 0, 4) == "tel:") continue;
            if (substr($iLink, 0, 2) == "//") {
                $iLink = 'http:' . $iLink;
            } elseif (substr($iLink, 0, 1) == "/") {
                $iLink = $inputHost . $iLink;
            }
            $httpCode = getHttpCode($iLink);
            if ($httpCode == 404) {
                $brokenLinks .= '<tr><td>' . $iLink . '</td></tr>';
                $bLinks[] = $iLink;
                $totalCount++;
            }
        }
        foreach ($ext_data as $link) {
            $eLink = trim($link['href']);
            $httpCode = getHttpCode($eLink);
            if ($httpCode == 404) {
                $brokenLinks .= '<tr><td>' . $eLink . '</td></tr>';
                $bLinks[] = $eLink;
                $totalCount++;
            }
        }
        $updateStr = serBase($bLinks);
        updateToDbPrepared($this->con, 'domains_data', ['broken_links' => $updateStr], ['domain' => $domainStr]);
        $class = ($totalCount == 0) ? 'passedBox' : 'errorBox';
        $msg = ($totalCount == 0) ? $this->lang['AN68'] : $this->lang['AN69'];
        $html = '<div><strong>' . $msg . '</strong><br><table class="table table-responsive"><tbody>' . $brokenLinks . '</tbody></table></div>';
        return [
            'html' => $html,
            'class' => $class,
            'tip' => $this->lang['AN186']
        ];
    }

    // ------------------- EMBEDDED OBJECTS -------------------
    /**
     * processEmbeddedObjects()
     * Checks whether the HTML contains any <object> or <embed> tags.
     */
    public function processEmbeddedObjects($domData, $domainStr) {
        $embeddedCheck = false;
        if (!empty($domData)) {
            if (!empty($domData->find('object')) || !empty($domData->find('embed'))) {
                $embeddedCheck = true;
            }
        }
        updateToDbPrepared($this->con, 'domains_data', ['embedded' => $embeddedCheck], ['domain' => $domainStr]);
        $class = $embeddedCheck ? 'errorBox' : 'passedBox';
        $msg = $embeddedCheck ? $this->lang['AN78'] : $this->lang['AN77'];
        return [
            'class' => $class,
            'msg' => $msg,
            'tip' => $this->lang['AN191']
        ];
    }

    // ------------------- IFRAMES -------------------
    /**
     * processIframes()
     * Checks whether the HTML contains any <iframe> tags.
     */
    public function processIframes($domData, $domainStr) {
        $iframeCheck = false;
        if (!empty($domData)) {
            if (!empty($domData->find('iframe'))) {
                $iframeCheck = true;
            }
        }
        updateToDbPrepared($this->con, 'domains_data', ['iframe' => $iframeCheck], ['domain' => $domainStr]);
        $class = $iframeCheck ? 'errorBox' : 'passedBox';
        $msg = $iframeCheck ? $this->lang['AN80'] : $this->lang['AN79'];
        return [
            'class' => $class,
            'msg' => $msg,
            'tip' => $this->lang['AN192']
        ];
    }

    // ------------------- WHOIS DATA -------------------
    /**
     * processWhois()
     * Retrieves WHOIS data for the domain, updates the database,
     * and returns formatted HTML output.
     */
    public function processWhois($my_url_host, $domainStr, $my_url) {
        $whois = new whois();
        $site = $whois->cleanUrl($my_url_host);
        $whois_data = $whois->whoislookup($site);
        $whoisRaw = $whois_data[0];
        $updateStr = serBase($whois_data);
        updateToDbPrepared($this->con, 'domains_data', ['whois' => $updateStr], ['domain' => $domainStr]);
        $lines = preg_split("/\r\n|\n|\r/", $whoisRaw);
        $html = '';
        $count = 0;
        foreach ($lines as $line) {
            if (!empty($line)) {
                $html .= '<tr><td>' . $line . '</td></tr>';
                $count++;
                if ($count == 5) { $html .= '<tr class="hideTr hideTr6"><td>...</td></tr>'; break; }
            }
        }
        return [
            'html' => '<table class="table table-striped">' . $html . '</table>',
            'tip' => $this->lang['AN194']
        ];
    }

    // ------------------- MOBILE COMPATIBILITY -------------------
    /**
     * processMobileCompatibility()
     * Checks for elements (iframes, objects, embeds) that may hurt mobile compatibility.
     */
    public function processMobileCompatibility($domData, $domainStr) {
        $check = false;
        if (!empty($domData)) {
            if (!empty($domData->find('iframe')) || !empty($domData->find('object')) || !empty($domData->find('embed'))) {
                $check = true;
            }
        }
        updateToDbPrepared($this->con, 'domains_data', ['mobile_com' => $check], ['domain' => $domainStr]);
        $class = $check ? 'errorBox' : 'passedBox';
        $msg = $check ? $this->lang['AN121'] : $this->lang['AN120'];
        return [
            'class' => $class,
            'msg' => $msg,
            'tip' => $this->lang['AN197']
        ];
    }

    // ------------------- URL LENGTH & FAVICON -------------------
    /**
     * processUrlLength()
     * Checks the length of the domain’s first part and fetches a favicon via Google.
     */
    public function processUrlLength($my_url, $my_url_host) {
        $hostParts = explode('.', $my_url_host);
        $count = strlen($hostParts[0]);
        $class = ($count < 15) ? 'passedBox' : 'errorBox';
        $msg = $my_url . '<br>' . str_replace('[count]', $count, $this->lang['AN122']);
        $favIcon = '<img src="https://www.google.com/s2/favicons?domain=' . $my_url . '" alt="FavIcon" /> ' . $this->lang['AN123'];
        return [
            'urlLength' => ['class' => $class, 'msg' => $msg, 'tip' => $this->lang['AN198']],
            'favIcon' => ['class' => 'lowImpactBox', 'msg' => $favIcon, 'tip' => $this->lang['AN199']]
        ];
    }

    // ------------------- CUSTOM 404 PAGE -------------------
    /**
     * processErrorPage()
     * Checks if a custom 404 error page is in place by comparing page size.
     */
    public function processErrorPage($my_url, $domainStr) {
        $pageSize = strlen(curlGET($my_url . '/404error-test-page-by-atoz-seo-tools'));
        $updateStr = base64_encode($pageSize);
        updateToDbPrepared($this->con, 'domains_data', ['404_page' => $updateStr], ['domain' => $domainStr]);
        if ($pageSize < 1500) {
            $class = 'errorBox';
            $msg = $this->lang['AN125'];
        } else {
            $class = 'passedBox';
            $msg = $this->lang['AN124'];
        }
        return [
            'class' => $class,
            'msg' => $msg,
            'tip' => $this->lang['AN200']
        ];
    }

    // ------------------- PAGE LOAD / SIZE / LANGUAGE -------------------
    /**
     * processPageLoad()
     * Measures the page load time, the size of the HTML content, and attempts
     * to detect the language. Updates DB and returns combined HTML output.
     */
    public function processPageLoad($my_url, $domainStr) {
        $timeStart = microtime(true);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $my_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.3; WOW64; rv:36.0) Gecko/20100101 Firefox/36.0');
        curl_setopt($ch, CURLOPT_REFERER, $my_url);
        $htmlContent = curl_exec($ch);
        curl_close($ch);
        $timeEnd = microtime(true);
        $timeTaken = $timeEnd - $timeStart;
        $dataSize = strlen($htmlContent);
        // Detect language from the <html lang="..."> tag or meta tag.
        $pattern = '<html[^>]+lang=[\'"]?(.*?)[\'"]?[\/\s]>';
        preg_match("#{$pattern}#is", $htmlContent, $matches);
        $langCode = isset($matches[1]) ? trim(mb_substr($matches[1], 0, 5)) : null;
        if (!$langCode) {
            $pattern = '<meta[^>]+http-equiv=[\'"]?content-language[\'"]?[^>]+content=[\'"]?(.*?)[\'"]?[\/\s]>';
            preg_match("#{$pattern}#is", $htmlContent, $matches);
            $langCode = isset($matches[1]) ? trim(mb_substr($matches[1], 0, 5)) : null;
        }
        $updateStr = serBase([$timeTaken, $dataSize, $langCode]);
        updateToDbPrepared($this->con, 'domains_data', ['load_time' => $updateStr], ['domain' => $domainStr]);
        $sizeInKb = size_as_kb($dataSize);
        $sizeClass = ($sizeInKb < 320) ? 'passedBox' : 'errorBox';
        $loadClass = ($timeTaken < 1) ? 'passedBox' : 'errorBox';
        $sizeMsg = str_replace('[size]', $sizeInKb, $this->lang['AN126']);
        $loadMsg = str_replace('[time]', round($timeTaken, 2), $this->lang['AN127']);
        $langClass = $langCode ? 'passedBox' : 'errorBox';
        $langMsg = $langCode ? $this->lang['AN128'] : $this->lang['AN129'];
        $langMsg .= str_replace('[language]', lang_code_to_lnag($langCode), $this->lang['AN130']);
        $htmlOutput = '<div class="'.$sizeClass.'"><div class="msgBox">'.$sizeMsg.'</div></div>' . $this->sepUnique .
                      '<div class="'.$loadClass.'"><div class="msgBox">'.$loadMsg.'</div></div>' . $this->sepUnique .
                      '<div class="'.$langClass.'"><div class="msgBox">'.$langMsg.'</div></div>';
        return [
            'html' => $htmlOutput,
            'tip' => [$this->lang['AN201'], $this->lang['AN202'], $this->lang['AN203']]
        ];
    }

    // ------------------- AVAILABILITY CHECKER -------------------
    /**
     * processAvailabilityChecker()
     * Checks the availability of the domain across alternate TLDs and common typos,
     * updates the database, and returns formatted HTML output.
     */
    public function processAvailabilityChecker($my_url_host, $domainStr, $my_url) {
        $doArr = $tyArr = [];
        $tldCodes = ['com','net','org','biz','us','info','eu'];
        $domainParts = explode('.', $my_url_host);
        $hostTLD = trim(end($domainParts));
        $domainWord = $domainParts[0];
        $tldCount = 0;
        $domainMsg = '';
        foreach ($tldCodes as $tld) {
            if ($tldCount == 5) break;
            if ($tld != $hostTLD) {
                $topDomain = $domainWord . '.' . $tld;
                // Instantiate a domainAvailability object using the servers file.
                $checker = new domainAvailability(file_get_contents(LIB_DIR.'domainAvailabilityservers.tdata'));
                $status = $checker->isAvailable($topDomain);
                $doArr[] = [$topDomain, $status];
                $msg = ($status=='2') ? $this->lang['AN132'] : (($status=='3') ? $this->lang['AN131'] : $this->lang['AN133']);
                $domainMsg .= '<tr><td>'.$topDomain.'</td><td>'.$msg.'</td></tr>';
                $tldCount++;
            }
        }
        // Process common typos.
        $typo = new typos();
        $typoWords = $typo->get($domainWord);
        $typoMsg = '';
        $typoCount = 0;
        foreach ($typoWords as $word) {
            if ($typoCount == 5) break;
            $topDomain = $word . '.' . $hostTLD;
            $checker = new domainAvailability(file_get_contents(LIB_DIR.'domainAvailabilityservers.tdata'));
            $status = $checker->isAvailable($topDomain);
            $tyArr[] = [$topDomain, $status];
            $msg = ($status=='2') ? $this->lang['AN132'] : (($status=='3') ? $this->lang['AN131'] : $this->lang['AN133']);
            $typoMsg .= '<tr><td>'.$topDomain.'</td><td>'.$msg.'</td></tr>';
            $typoCount++;
        }
        $updateStr = serBase([$doArr, $typoWords]);
        updateToDbPrepared($this->con, 'domains_data', ['domain_typo' => $updateStr], ['domain' => $domainStr]);
        $domainHTML = '<div class="seoBox32"><table class="table table-striped"><tr><th>'.$this->lang['AN134'].'</th><th>'.$this->lang['AN135'].'</th></tr>'.$domainMsg.'</table></div>';
        $typoHTML = '<div class="seoBox33"><table class="table table-striped"><tr><th>'.$this->lang['AN134'].'</th><th>'.$this->lang['AN135'].'</th></tr>'.$typoMsg.'</table></div>';
        return [
            'domain' => $domainHTML,
            'typo' => $typoHTML,
            'tip' => [$this->lang['AN204'], $this->lang['AN205']]
        ];
    }

    // ------------------- EMAIL PRIVACY -------------------
    /**
     * processEmailPrivacy()
     * Searches for email addresses in the source data, updates the database,
     * and returns a status.
     */
    public function processEmailPrivacy($sourceData, $domainStr) {
        preg_match_all("/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6})/", $sourceData, $matches, PREG_SET_ORDER);
        $emailCount = count($matches);
        updateToDbPrepared($this->con, 'domains_data', ['email_privacy' => $emailCount], ['domain' => $domainStr]);
        $class = ($emailCount == 0) ? 'passedBox' : 'errorBox';
        $msg = ($emailCount == 0) ? $this->lang['AN136'] : $this->lang['AN137'];
        return [
            'class' => $class,
            'msg' => $msg,
            'tip' => $this->lang['AN206']
        ];
    }

    // ------------------- SAFE BROWSING -------------------
    /**
     * processSafeBrowsing()
     * Checks whether the site is blacklisted via a safe browsing API,
     * updates the database, and returns a status.
     */
    public function processSafeBrowsing($my_url_host, $domainStr) {
        $status = safeBrowsing($my_url_host);  // Expected: 204 (not blacklisted), 200 (blacklisted), 501 (error)
        updateToDbPrepared($this->con, 'domains_data', ['safe_bro' => $status], ['domain' => $domainStr]);
        if ($status == 204) {
            $msg = $this->lang['AN138'];
            $class = 'passedBox';
        } elseif ($status == 200) {
            $msg = $this->lang['AN139'];
            $class = 'errorBox';
        } else {
            $msg = $this->lang['AN140'];
            $class = 'improveBox';
        }
        return [
            'class' => $class,
            'msg' => $msg,
            'tip' => $this->lang['AN207']
        ];
    }

    // ------------------- SERVER LOCATION -------------------
    /**
     * processServerIP()
     * Retrieves server IP, country, and ISP info, updates the database,
     * and returns formatted HTML.
     */
    public function processServerIP($my_url_host, $domainStr) {
        $hostIP = gethostbyname($my_url_host);
        $data_list = host_info($my_url_host);
        $updateStr = serBase($data_list);
        updateToDbPrepared($this->con, 'domains_data', ['server_loc' => $updateStr], ['domain' => $domainStr]);
        $html = '<table class="table table-striped">
                    <tr><th>'.$this->lang['AN141'].'</th><th>'.$this->lang['AN142'].'</th><th>'.$this->lang['AN143'].'</th></tr>
                    <tr><td>'.$hostIP.'</td><td>'.$data_list[1].'</td><td>'.$data_list[2].'</td></tr>
                 </table>';
        return [
            'html' => $html,
            'tip' => $this->lang['AN208']
        ];
    }

    // ------------------- SPEED TIPS -------------------
    /**
     * processSpeedTips()
     * Analyzes counts of CSS links, JavaScript tags, nested tables, and inline CSS,
     * updates the database, and returns suggestions for speed improvements.
     */
    public function processSpeedTips($sourceData, $domainStr, $true, $false) {
        // Count CSS links.
        preg_match_all("#<link[^>]*>#is", $sourceData, $matches);
        $cssCount = 0;
        if (!empty($matches[0])) {
            foreach ($matches[0] as $tagVal) {
                if (preg_match("#(?=.*\bstylesheet\b)(?=.*\bhref=([\"'][^\"']*[\"']))#is", $tagVal))
                    $cssCount++;
            }
        }
        // Count JavaScript tags.
        preg_match_all("#<script[^>]*>#is", $sourceData, $matches);
        $jsCount = 0;
        if (!empty($matches[0])) {
            foreach ($matches[0] as $tagVal) {
                if (preg_match("#src=([\"'][^\"']*[\"'])#is", $tagVal))
                    $jsCount++;
            }
        }
        // Check for nested tables.
        $nestedTables = preg_match("#<(td|th)[^>]*>.*?<table[^>]*>.*?</table>.*?</(td|th)>#is", $sourceData);
        // Check for inline CSS.
        $inlineCss = preg_match("#<[^>]+style=[\"'][^\"']+[\"'][^>]*>#is", $sourceData);
        $speedTipsCheck = 0;
        $tips = '';
        if ($cssCount > 5) { $speedTipsCheck++; $tips .= $false . ' ' . $this->lang['AN145'] . '<br>'; }
        else { $tips .= $true . ' ' . $this->lang['AN144'] . '<br>'; }
        if ($jsCount > 5) { $speedTipsCheck++; $tips .= $false . ' ' . $this->lang['AN147'] . '<br>'; }
        else { $tips .= $true . ' ' . $this->lang['AN146'] . '<br>'; }
        if ($nestedTables == 1) { $speedTipsCheck++; $tips .= $false . ' ' . $this->lang['AN149'] . '<br>'; }
        else { $tips .= $true . ' ' . $this->lang['AN148'] . '<br>'; }
        if ($inlineCss == 1) { $speedTipsCheck++; $tips .= $false . ' ' . $this->lang['AN151'] . '<br>'; }
        else { $tips .= $true . ' ' . $this->lang['AN150'] . '<br>'; }
        $class = ($speedTipsCheck == 0) ? 'passedBox' : (($speedTipsCheck > 2) ? 'errorBox' : 'improveBox');
        $updateStr = serBase([$cssCount, $jsCount, $nestedTables, $inlineCss]);
        updateToDbPrepared($this->con, 'domains_data', ['speed_tips' => $updateStr], ['domain' => $domainStr]);
        return [
            'class' => $class,
            'tips' => $tips,
            'tip' => $this->lang['AN209']
        ];
    }

    // ------------------- CLEAN OUT / FINALIZATION -------------------
    /**
     * processCleanOut()
     * Finalizes the analysis by updating overall scores, adding the site to recent history,
     * and deleting the cached file.
     */
    public function processCleanOut($passscore, $improvescore, $errorscore, $domainStr, $filename, $ip) {
        $score = [$passscore, $improvescore, $errorscore];
        $updateStr = serBase($score);
        updateToDbPrepared($this->con, 'domains_data', ['score' => $updateStr, 'completed' => 'yes'], ['domain' => $domainStr]);
        $data = mysqliPreparedQuery($this->con, "SELECT * FROM domains_data WHERE domain=?", 's', [$domainStr]);
        if ($data !== false) {
            $pageSpeedInsightData = decSerBase($data['page_speed_insight']);
            $alexa = decSerBase($data['alexa']);
            $finalScore = ($passscore == '') ? '0' : $passscore;
            $globalRank = ($alexa[0] == '') ? '0' : $alexa[0];
            $pageSpeed = ($pageSpeedInsightData[0] == '') ? '0' : $pageSpeedInsightData[0];
            $username = isset($_SESSION['twebUsername']) ? $_SESSION['twebUsername'] : trans('Guest', $this->lang['11'], true);
            if ($globalRank == 'No Global Rank') $globalRank = 0;
            $other = serBase([$finalScore, $globalRank, $pageSpeed]);
            addToRecentSites($this->con, $domainStr, $ip, $username, $other);
        }
        delFile($filename);
    }

} // End of SeoTools class

/**
 * List of helper functions that must be defined elsewhere in your project:
 *
 * clean_url(), raino_trim(), serBase(), updateToDbPrepared(), getMyData(),
 * calTextRatio(), compressionTest(), getMobileFriendly(), getHttpCode(), googleIndex(),
 * alexaRank(), backlinkCount(), getSocialData(), load_html(), truncate(), safeBrowsing(),
 * host_info(), domainAvailability(), typos(), addToRecentSites(), delFile(), lang_code_to_lnag()
 */

?>
