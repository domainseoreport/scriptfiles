<?php
/**
 * Turbo Website Reviewer - PHP Script
 * Author: Balaji
 * Copyright 2023 ProThemes.Biz
 *
 * This script handles AJAX requests for various SEO and website performance tests.
 * It processes both GET and POST requests. Depending on the parameters sent, it extracts
 * meta information, headings, images, keyword cloud, text ratio, compression tests,
 * domain resolution, broken links, robots/sitemap checks, mobile friendliness,
 * and many other SEO metrics.
 */

// Prevent direct access to the script.
defined('APP_NAME') or die(header('HTTP/1.1 403 Forbidden'));

// Include the SEO tools library.
require_once(LIB_DIR . 'SeoTools.php'); 

// Define the temporary directory constant for cached files.
define('TEMP_DIR', APP_DIR . 'temp' . D_S);

/*
 * ---------------------------------------------------------------------
 * GET REQUEST HANDLER
 * ---------------------------------------------------------------------
 *
 * If the 'getImage' parameter is set in the GET request, this block returns a 
 * base64-encoded snapshot of the requested website.
 */
if (isset($_GET['getImage'])) {
    // If a custom snapshot link is available in the session, use it.
    if (isset($_SESSION['snap'])) {
        $customSnapAPI = true;
        $customSnapLink = $_SESSION['snap'];
    }
    // Close session writing to prevent session lock issues.
    session_write_close();

    // Clean and prepare the requested site URL.
    $my_url = clean_url(raino_trim($_GET['site']));

    // Retrieve image data by generating a snapshot from the site.
    $imageData = getMyData(getSiteSnap($my_url, $item_purchase_code, $baseLink, $customSnapAPI, $customSnapLink));
    
    // Clear output buffers and return the base64 encoded image data.
    ob_clean();
    echo base64_encode($imageData);
    die();
}

/*
 * ---------------------------------------------------------------------
 * PREPARE LOGIN BOX HTML
 * ---------------------------------------------------------------------
 *
 * This HTML snippet is displayed when a user is not logged in or does not have
 * permission to view certain statistics.
 */
$seoBoxLogin = '<div class="lowImpactBox">
    <div class="msgBox">   
        ' . $lang['39'] . '
        <br /><br />
        <div class="altImgGroup">
            <a class="btn btn-success forceLogin" target="_blank" href="' . createLink('account/login', true) . '" title="' . $lang['40'] . '">
                ' . $lang['40'] . '
            </a>
        </div>
        <br />
    </div>
</div>';

/*
 * ---------------------------------------------------------------------
 * POST REQUEST HANDLER
 * ---------------------------------------------------------------------
 *
 * This block handles various POST requests by checking for specific keys in $_POST.
 * Each block processes a particular SEO test and returns HTML output while updating the database.
 */
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Retrieve and sanitize the website URL from POST data.
    $my_url = 'http://' . clean_url(raino_trim($_POST['url']));
    // Retrieve the unique hash code used for caching.
    $hashCode = raino_trim($_POST['hashcode']);
    // Construct the filename where the website source data is stored.
    $filename = TEMP_DIR . $hashCode . '.tdata';
    // Define a unique separator string used to split output sections.
    $sepUnique = '!!!!8!!!!';

    // Parse the input URL to extract the scheme and host.
    $my_url_parse = parse_url($my_url);
    $inputHost = $my_url_parse['scheme'] . "://" . $my_url_parse['host'];
    // Normalize host by removing "www." for consistent DB lookups.
    $my_url_host = str_replace("www.", "", $my_url_parse['host']);
    // Prepare a normalized domain string for database storage.
    $domainStr = escapeTrim($con, strtolower($my_url_host));

    // Define HTML for "true" and "false" icons.
    $true = '<img src="' . themeLink('img/true.png', true) . '" alt="' . trans('True', $lang['10'], true) . '" />';
    $false = '<img src="' . themeLink('img/false.png', true) . '" alt="' . trans('False', $lang['9'], true) . '" />';

    // Retrieve the stored HTML source data from the cached file.
    $sourceData = getMyData($filename);

    // Replace uppercase meta tag names with lowercase to avoid issues.
    $html = str_ireplace(array("Title", "TITLE"), "title", $sourceData);
    $html = str_ireplace(array("Description", "DESCRIPTION"), "description", $html);
    $html = str_ireplace(array("Keywords", "KEYWORDS"), "keywords", $html);
    $html = str_ireplace(array("Content", "CONTENT"), "content", $html);
    $html = str_ireplace(array("Meta", "META"), "meta", $html);
    $html = str_ireplace(array("Name", "NAME"), "name", $html);

    // If no source data is found, exit with an error message.
    if ($sourceData == '')
        die($lang['AN10']);

    // Instantiate the SeoTools class with the necessary parameters.
    $seoTools = new SeoTools($html, $con, $domainStr, $lang, $my_url_parse, $sepUnique, $seoBoxLogin);    

    /*
     * -----------------------------------------------------------------
     * META DATA HANDLER
     * -----------------------------------------------------------------
     * Extracts the page title, description, and keywords from the HTML source,
     * updates the database, and outputs the result if requested.
     */
    if (isset($_POST['meta'])) {
        log_message('debug', "Meta Tag Call for URL {$my_url_host}");
        $metaData = $seoTools->processMeta(); 
        if (isset($_POST['metaOut'])) { 
            echo $seoTools->showMeta($metaData);
            die();
        }
    }

    /*
     * -----------------------------------------------------------------
     * HEADING DATA HANDLER
     * -----------------------------------------------------------------
     * Extracts all heading tags (H1-H6) from the HTML source,
     * updates the database, and outputs the result if requested.
     */
    if (isset($_POST['heading'])) {
        log_message('debug', "Heading Tag Call for URL {$my_url_host}");
        $headings = $seoTools->processHeading(); 
        if (isset($_POST['headingOut'])) {
            echo $seoTools->showHeading($headings);
            die();
        }
    }

    /*
     * -----------------------------------------------------------------
     * LOAD DOM LIBRARY (if needed)
     * -----------------------------------------------------------------
     * Loads the simple_html_dom library to parse the HTML source into a DOM object.
     */
    if (isset($_POST['loaddom'])) {
        log_message('debug', "DOM Library Tag Call for URL {$my_url_host}");
        require_once(LIB_DIR . "simple_html_dom.php");
        $domData = load_html($sourceData);
    }

    /*
     * -----------------------------------------------------------------
     * IMAGE ALT TAG CHECKER
     * -----------------------------------------------------------------
     * Checks for images missing an "alt" attribute, updates the database,
     * and outputs the results.
     */
    if (isset($_POST['image'])) {
        log_message('debug', "Image Tag Call for URL {$my_url_host}");
        $imageData = $seoTools->processImage(); 
        echo $seoTools->showImage($imageData);
        die();
    }

    /*
     * -----------------------------------------------------------------
     * KEYWORD CLOUD GENERATOR
     * -----------------------------------------------------------------
     * Processes a keyword cloud by analyzing the HTML source for keywords,
     * updates the database, and outputs the result if requested.
     */
    if (isset($_POST['keycloud'])) {
        log_message('debug', "Keyword Cloud Tag Call for URL {$my_url_host}");
        $keyCloud = $seoTools->processKeyCloud();
        if (isset($_POST['keycloudOut'])) {
            echo $seoTools->showKeyCloud($keyCloud);
            die();
        }
    }

    /*
     * -----------------------------------------------------------------
     * KEYWORD CONSISTENCY CHECKER
     * -----------------------------------------------------------------
     * Checks whether keywords appear in the title, description, and headings,
     * updates the database, and outputs a consistency table.
     */
    if (isset($_POST['keyConsistency'])) {
        log_message('debug', "Keyword Consistency Tag Call for URL {$my_url_host}");
        $metaData = jsonDecode($seoTools->processMeta());
        $headingData = jsonDecode($seoTools->processHeading());
        $keyCloudResult = $seoTools->processKeyCloud();
        $consistency = $seoTools->processKeyConsistency(
            $keyCloudResult['keyCloudData'] ?? $keyCloudResult, 
            $metaData, 
            $headingData[0] // Assuming headings are stored in the first element.
        );
        echo $seoTools->showKeyConsistency($consistency);
        die();
    }

    /*
     * -----------------------------------------------------------------
     * TEXT TO HTML RATIO CALCULATOR
     * -----------------------------------------------------------------
     * Calculates the ratio of text content to HTML code,
     * updates the database, and outputs the result.
     */
    if (isset($_POST['textRatio'])) {
        log_message('debug', "Text Ratio Tag Call for URL {$my_url_host}");
        $textRatio = $seoTools->processTextRatio();  
        echo $seoTools->showTextRatio($textRatio);
        die();
    }

    /*
     * -----------------------------------------------------------------
     * GZIP COMPRESSION TEST
     * -----------------------------------------------------------------
     * Checks whether GZIP compression is enabled on the site,
     * updates the database, and outputs the result.
     */
    if (isset($_POST['gzip'])) {
        log_message('debug', "GZIP Tag Call for URL {$my_url_host}");
        $gzipData = $seoTools->processGzip();
        echo $seoTools->showGzip($gzipData);
        die();
    }

    /*
     * -----------------------------------------------------------------
     * WWW RESOLVE CHECK
     * -----------------------------------------------------------------
     * Determines if the site resolves properly with and without "www",
     * updates the database, and outputs the result.
     */
    if (isset($_POST['www_resolve'])) {
        log_message('debug', "WWW Resolve Tag Call for URL {$my_url_host}");
        $resolveData = $seoTools->processWWWResolve();
        echo $seoTools->showWWWResolve($resolveData);
        die();
    }

    /*
     * -----------------------------------------------------------------
     * IP CANONICALIZATION CHECK
     * -----------------------------------------------------------------
     * Checks whether accessing the site via its IP address redirects to the correct domain,
     * updates the database, and outputs the result.
     */
    if (isset($_POST['ip_can'])) {
        log_message('debug', "IP Canonicalization Tag Call for URL {$my_url_host}");
        $ipData = $seoTools->processIPCanonicalization();
        echo $seoTools->showIPCanonicalization($ipData);
        die();
    }

    /*
     * -----------------------------------------------------------------
     * IN-PAGE LINKS ANALYZER
     * -----------------------------------------------------------------
     * Analyzes all internal and external links on the page,
     * updates the database, and outputs the result if requested.
     */
    if (isset($_POST['in_page'])) {
        log_message('debug', "In-Page Links Tag Call for URL {$my_url_host}");
        $linksData = $seoTools->processInPageLinks();
        if (isset($_POST['inPageoutput'])) {
            echo $seoTools->showInPageLinks($linksData);
            die();
        }
    }

    /*
     * -----------------------------------------------------------------
     * BROKEN LINKS CHECKER
     * -----------------------------------------------------------------
     * Checks for broken links (HTTP 404 errors) on the page,
     * updates the database, and outputs the result.
     */
    if (isset($_POST['brokenlinks'])) {
        log_message('debug', "Broken Links Tag Call for URL {$my_url_host}");
        $brokenLinks = $seoTools->processBrokenLinks();
        echo $seoTools->showBrokenLinks($brokenLinks);
        die();
    }

    /*
     * -----------------------------------------------------------------
     * ROBOTS.TXT CHECKER
     * -----------------------------------------------------------------
     * Checks for the existence of a robots.txt file on the site,
     * updates the database, and outputs the result.
     */
    if (isset($_POST['robot'])) {
        log_message('debug', "Robots.txt Tag Call for URL {$my_url_host}");
        $robotsData = $seoTools->processRobots();
        echo $seoTools->showRobots($robotsData);
        die();
    }

    /*
     * -----------------------------------------------------------------
     * SITEMAP CHECKER
     * -----------------------------------------------------------------
     * Checks for the existence of a sitemap, updates the database,
     * and outputs the result.
     */
    if (isset($_POST['sitemap'])) {
        log_message('debug', "Sitemap Tag Call for URL {$my_url_host}");
        $sitemapData = $seoTools->processSitemap();
        echo $seoTools->showSitemap($sitemapData);
        die();
    }

    /*
     * -----------------------------------------------------------------
     * EMBEDDED OBJECT CHECK
     * -----------------------------------------------------------------
     * Checks for embedded objects (<object> or <embed> tags) on the page,
     * updates the database, and outputs the result.
     */
    if (isset($_POST['embedded'])) {
        log_message('debug', "Embedded Objects Tag Call for URL {$my_url_host}");
        $embeddedCheck = $seoTools->processEmbedded();
        echo $seoTools->showEmbedded($embeddedCheck);
        die();
    }

    /*
     * -----------------------------------------------------------------
     * IFRAME CHECK
     * -----------------------------------------------------------------
     * Checks for <iframe> tags on the page,
     * updates the database, and outputs the result.
     */
    if (isset($_POST['iframe'])) {
        log_message('debug', "iFrame Tag Call for URL {$my_url_host}");
        $iframeCheck = $seoTools->processIframe();
        echo $seoTools->showIframe($iframeCheck);
        die();
    }

    /*
     * -----------------------------------------------------------------
     * WHOIS DATA RETRIEVAL
     * -----------------------------------------------------------------
     * Retrieves WHOIS information for the domain,
     * updates the database, and outputs the result.
     */
    if (isset($_POST['whois'])) {
        log_message('debug', "WHOIS Tag Call for URL {$my_url_host}");
        $whoisData = $seoTools->processWhois();
        echo $seoTools->showWhois($whoisData);
        die();
    }

    /*
     * -----------------------------------------------------------------
     * INDEXED PAGES COUNTER
     * -----------------------------------------------------------------
     * Determines the number of pages indexed by Google for the site,
     * updates the database, and outputs the result.
     */
    if (isset($_POST['indexedPages'])) {
        log_message('debug', "Indexed Pages Tag Call for URL {$my_url_host}");
        $indexed = $seoTools->processIndexedPages();
        echo $seoTools->showIndexedPages($indexed);
        die();
    }

    /*
     * -----------------------------------------------------------------
     * BACKLINK COUNTER / ALEXA RANK / SITE WORTH
     * -----------------------------------------------------------------
     * Retrieves Alexa ranking data, backlink count, and calculates site worth,
     * updates the database, and outputs the result.
     */
    if (isset($_POST['backlinks'])) {
        log_message('debug', "Backlinks Tag Call for URL {$my_url_host}");
        $alexaData = $seoTools->processBacklinks();
        echo $seoTools->showBacklinks($alexaData);
        die();
    }

    /*
     * -----------------------------------------------------------------
     * URL LENGTH & FAVICON CHECKER
     * -----------------------------------------------------------------
     * Checks the length of the host name and retrieves the favicon via Google's service,
     * updates the database, and outputs the result.
     */
    if (isset($_POST['urlLength'])) {
        log_message('debug', "URL Length Tag Call for URL {$my_url_host}");
        $urlLengthData = $seoTools->processUrlLength();
        echo $seoTools->showUrlLength($urlLengthData);
        die();
    }

    /*
     * -----------------------------------------------------------------
     * CUSTOM 404 PAGE CHECKER
     * -----------------------------------------------------------------
     * Checks if a custom 404 error page is in place by comparing the size of a test error page,
     * updates the database, and outputs the result.
     */
    if (isset($_POST['errorPage'])) {
        log_message('debug', "Custom 404 Page Tag Call for URL {$my_url_host}");
        $errorPageSize = $seoTools->processErrorPage();
        echo $seoTools->showErrorPage($errorPageSize);
        die();
    }

    /*
     * -----------------------------------------------------------------
     * PAGE LOAD / SIZE / LANGUAGE CHECKER
     * -----------------------------------------------------------------
     * Measures page load time, calculates HTML size, and attempts to detect the language code,
     * updates the database, and outputs the result.
     */
    if (isset($_POST['pageLoad'])) {
        log_message('debug', "Page Load Tag Call for URL {$my_url_host}");
        $pageLoadData = $seoTools->processPageLoad();
        echo $seoTools->showPageLoad($pageLoadData);
        die();
    }

    /*
     * -----------------------------------------------------------------
     * PAGE SPEED INSIGHT CHECKER
     * -----------------------------------------------------------------
     * Retrieves desktop and mobile speed scores using the PageSpeed Insights API,
     * updates the database, and outputs the result.
     */
    if (isset($_POST['pageSpeedInsightChecker'])) {
        log_message('debug', "Page Speed Insight Tag Call for URL {$my_url_host}");
        $psiData = $seoTools->processPageSpeedInsight();
        echo $seoTools->showPageSpeedInsight($psiData);
        die();
    }

    /*
     * -----------------------------------------------------------------
     * DOMAIN & TYPO AVAILABILITY CHECKER
     * -----------------------------------------------------------------
     * Checks the availability of the domain across various TLDs and suggests typo domains,
     * updates the database, and outputs the result.
     */
    if (isset($_POST['availabilityChecker'])) {
        log_message('debug', "Availability Checker Tag Call for URL {$my_url_host}");
        $availData = $seoTools->processAvailabilityChecker();
        echo $seoTools->showAvailabilityChecker($availData);
        die();
    }

    /*
     * -----------------------------------------------------------------
     * EMAIL PRIVACY CHECK
     * -----------------------------------------------------------------
     * Searches for email addresses in the HTML source to detect privacy issues,
     * updates the database, and outputs the result.
     */
    if (isset($_POST['emailPrivacy'])) {
        log_message('debug', "Email Privacy Tag Call for URL {$my_url_host}");
        $emailCount = $seoTools->processEmailPrivacy();
        echo $seoTools->showEmailPrivacy($emailCount);
        die();
    }

    /*
     * -----------------------------------------------------------------
     * SAFE BROWSING CHECK
     * -----------------------------------------------------------------
     * Checks if the site is flagged by safe browsing services,
     * updates the database, and outputs the result.
     */
    if (isset($_POST['safeBrowsing'])) {
        log_message('debug', "Safe Browsing Tag Call for URL {$my_url_host}");
        $safeBrowsingStats = $seoTools->processSafeBrowsing();
        echo $seoTools->showSafeBrowsing($safeBrowsingStats);
        die();
    }

    /*
     * -----------------------------------------------------------------
     * MOBILE FRIENDLINESS CHECK
     * -----------------------------------------------------------------
     * Tests if the site is mobile-friendly,
     * updates the database, and outputs the result.
     */
    if (isset($_POST['mobileCheck'])) {
        log_message('debug', "Mobile Friendliness Tag Call for URL {$my_url_host}");
        $mobileData = $seoTools->processMobileCheck();
        echo $seoTools->showMobileCheck($mobileData);
        die();
    }

    /*
     * -----------------------------------------------------------------
     * MOBILE COMPATIBILITY CHECKER
     * -----------------------------------------------------------------
     * Checks for elements (iframes, objects, embeds) that may not be mobile compatible,
     * updates the database, and outputs the result.
     */
    if (isset($_POST['mobileCom'])) {
        log_message('debug', "Mobile Compatibility Tag Call for URL {$my_url_host}");
        $mobileComData = $seoTools->processMobileCom();
        echo $seoTools->showMobileCom($mobileComData);
        die();
    }

    /*
     * -----------------------------------------------------------------
     * SERVER LOCATION INFORMATION
     * -----------------------------------------------------------------
     * Retrieves the server's IP, country, and ISP details,
     * updates the database, and outputs the result.
     */
    if (isset($_POST['serverIP'])) {
        log_message('debug', "Server IP Tag Call for URL {$my_url_host}");
        $serverIPData = $seoTools->processServerIP();
        echo $seoTools->showServerIP($serverIPData);
        die();
    }

    /*
     * -----------------------------------------------------------------
     * SPEED TIPS ANALYZER
     * -----------------------------------------------------------------
     * Analyzes the number of CSS/JS files, nested tables, and inline CSS usage,
     * updates the database, and outputs suggestions to improve speed.
     */
    if (isset($_POST['speedTips'])) {
        log_message('debug', "Speed Tips Tag Call for URL {$my_url_host}");
        $speedTipsData = $seoTools->processSpeedTips();
        echo $seoTools->showSpeedTips($speedTipsData);
        die();
    }

    /*
     * -----------------------------------------------------------------
     * ANALYTICS & DOCUMENT TYPE CHECKER
     * -----------------------------------------------------------------
     * Checks for analytics code (e.g., Google Analytics) and the DOCTYPE declaration,
     * updates the database, and outputs the result.
     */
    if (isset($_POST['docType'])) {
        log_message('debug', "DocType Tag Call for URL {$my_url_host}");
        $docTypeData = $seoTools->processDocType();
        echo $seoTools->showDocType($docTypeData);
        die();
    }

    /*
     * -----------------------------------------------------------------
     * W3C VALIDITY CHECKER
     * -----------------------------------------------------------------
     * Validates the HTML using the W3C Validator API,
     * updates the database, and outputs the result.
     */
    if (isset($_POST['w3c'])) {
        log_message('debug', "W3C Validity Tag Call for URL {$my_url_host}");
        
        $w3cData = $seoTools->processW3c(); 
        echo $seoTools->showW3c($w3cData);
        die();
    }

    /*
     * -----------------------------------------------------------------
     * ENCODING TYPE CHECKER
     * -----------------------------------------------------------------
     * Checks the charset meta tag to determine the document's encoding,
     * updates the database, and outputs the result.
     */
    if (isset($_POST['encoding'])) {
        log_message('debug', "Encoding Tag Call for URL {$my_url_host}");
        $charset = $seoTools->processEncoding();
        echo $seoTools->showEncoding($charset);
        die();
    }

    /*
     * -----------------------------------------------------------------
     * SOCIAL DATA RETRIEVAL
     * -----------------------------------------------------------------
     * (This block may be unreachable if a previous die() is encountered.)
     * Gathers social media statistics from the HTML source, updates the database,
     * and outputs the result.
     */
    if (isset($_POST['socialData'])) {
        $social_Msg = $lang['AN216'];
        $socialClass = 'lowImpactBox';

        $socialData = getSocialData($sourceData);

        $facebook_like = ($socialData['fb'] === '-') ? $false : $true . ' ' . $socialData['fb'];
        $twit_count = ($socialData['twit'] === '-') ? $false : $true . ' ' . $socialData['twit'];
        $insta_count = ($socialData['insta'] === '-') ? $false : $true . ' ' . $socialData['insta'];

        $updateStr = serBase(array($facebook_like, $twit_count, $insta_count, 0));
        updateToDbPrepared($con, 'domains_data', array('social' => $updateStr), array('domain' => $domainStr));

        if (!isset($_SESSION['twebUsername'])) {
            if (!isAllowedStats($con, 'seoBox44')) {
                die($seoBoxLogin);
            }
        }

        echo '<div class="' . $socialClass . '">
            <div class="msgBox">   
                ' . $lang['AN167'] . '<br />
                <div class="altImgGroup">
                    <br>
                    <div class="social-box"><i class="fa fa-facebook social-facebook"></i> Facebook: ' . $facebook_like . '</div><br>
                    <div class="social-box"><i class="fa fa-twitter social-linkedin"></i> Twitter: ' . $twit_count . '</div><br>
                    <div class="social-box"><i class="fa fa-instagram social-google"></i> Instagram: ' . $insta_count . '</div>
                </div>
                <br />
            </div>
            <div class="seoBox44 suggestionBox">' . $social_Msg . '</div>
        </div>';
        die();
    }

    /*
     * -----------------------------------------------------------------
     * VISITORS LOCALIZATION
     * -----------------------------------------------------------------
     * Retrieves visitor data (e.g., Alexa rankings) for localization purposes,
     * and outputs the result.
     */
    if (isset($_POST['visitorsData'])) {
        $visitors_Msg = $lang['AN219'];
        $visitorsClass = 'lowImpactBox';

        $data = mysqliPreparedQuery($con, "SELECT alexa FROM domains_data WHERE domain=?", 's', array($domainStr));
        if ($data !== false) {
            $alexa = decSerBase($data['alexa']);
            $alexaDatas = array(
                array('', 'Popularity at', $alexa[1]),
                array('', 'Regional Rank', $alexa[2])
            );
        }

        $alexaDataCount = count($alexaDatas);
        $visitorsMsg = '';
        foreach ($alexaDatas as $alexaData) {
            $visitorsMsg .= '<tr><td>' . $alexaData[1] . '</td><td>' . $alexaData[2] . '</td></tr>';
        }

        if (!isset($_SESSION['twebUsername'])) {
            if (!isAllowedStats($con, 'seoBox47')) {
                die($seoBoxLogin);
            }
        }

        echo '<div class="' . $visitorsClass . '">
            <div class="msgBox">   
                ' . $lang['AN171'] . '<br /><br />
                ' . (($alexaDataCount != 0) ? '
                <table class="table table-hover table-bordered table-striped">
                    <tbody>' . $visitorsMsg . '</tbody>
                </table>' : $lang['AN170']) . '
                <br />
            </div>
            <div class="seoBox47 suggestionBox">' . $visitors_Msg . '</div>
        </div>';
        die();
    }

    /*
     * -----------------------------------------------------------------
     * CLEAN OUT / FINALIZE ANALYSIS
     * -----------------------------------------------------------------
     * Updates the final score, adds the site to recent history, and clears the cached data.
     */
    if (isset($_POST['cleanOut'])) {
        $passscore = raino_trim($_POST['passscore']);
        $improvescore = raino_trim($_POST['improvescore']);
        $errorscore = raino_trim($_POST['errorscore']);
        $score = array($passscore, $improvescore, $errorscore);
        $updateStr = serBase($score);
        updateToDbPrepared($con, 'domains_data', array('score' => $updateStr, 'completed' => 'yes'), array('domain' => $domainStr));

        $data = mysqliPreparedQuery($con, "SELECT * FROM domains_data WHERE domain=?", 's', array($domainStr));
        if ($data !== false) {
            $pageSpeedInsightData = decSerBase($data['page_speed_insight']);
            $alexa = decSerBase($data['alexa']);
            $finalScore = ($passscore == '') ? '0' : $passscore;
            $globalRank = ($alexa[0] == '') ? '0' : $alexa[0];
            $pageSpeed = ($pageSpeedInsightData[0] == '') ? '0' : $pageSpeedInsightData[0];

            $username = (!isset($_SESSION['twebUsername'])) ? trans('Guest', $lang['11'], true) : $_SESSION['twebUsername'];

            if ($globalRank == 'No Global Rank')
                $globalRank = 0;

            $other = serBase(array($finalScore, $globalRank, $pageSpeed));
            addToRecentSites($con, $domainStr, $ip, $username, $other);
        }

        // Clear the cached data file.
        delFile($filename);
    }
} // End of POST REQUEST HANDLER

// End of AJAX Handler script.
die();
?>