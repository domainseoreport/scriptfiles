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
 */
if (isset($_GET['getImage'])) {
    if (isset($_SESSION['snap'])) {
        $customSnapAPI = true;
        $customSnapLink = $_SESSION['snap'];
    }
    session_write_close();
    $my_url = clean_url(raino_trim($_GET['site']));
    $imageData = getMyData(getSiteSnap($my_url, $item_purchase_code, $baseLink, $customSnapAPI, $customSnapLink));
    ob_clean();
    echo base64_encode($imageData);
    die();
}

/*
 * ---------------------------------------------------------------------
 * POST REQUEST HANDLER
 * ---------------------------------------------------------------------
 */
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Check that the URL parameter is provided and not empty.
    if (!isset($_POST['url']) || trim($_POST['url']) === '') {
        error_log("No URL parameter provided in the POST request.");
        die("Error: URL parameter is missing.");
    }

    // Retrieve and sanitize the website URL from POST data.
    $urlInput = raino_trim($_POST['url']);

    // If the URL does not start with "http://" or "https://", prepend "http://"
    if (!preg_match('#^https?://#i', $urlInput)) {
        $urlInput = 'http://' . $urlInput;
    }
    // For debugging, bypass clean_url() to see if it affects the scheme.
    // Uncomment the following line if you want to use clean_url():
    // $my_url = clean_url($urlInput);
    $my_url = $urlInput;
    
    // Debug: Log the final URL.
    error_log("Final URL being parsed: " . $my_url);
    log_message('info', "Called {$my_url}");

    // Retrieve the unique hash code for caching.
    $hashCode = raino_trim($_POST['hashcode']);

    // Construct the filename where the website source data is stored.
    $filename = TEMP_DIR . $hashCode . '.tdata';

    // Define a unique separator string used to split output sections.
    $sepUnique = '!!!!8!!!!';

    // Parse the input URL to extract scheme and host.
    $my_url_parse = parse_url($my_url);
    // Ensure that a valid URL is provided by checking for scheme and host.
    if (!isset($my_url_parse['scheme']) || !isset($my_url_parse['host'])) {
        error_log("Invalid URL provided: {$my_url}");
        die("Error: Invalid URL provided. Please include both scheme and host.");
    }
    $inputHost = $my_url_parse['scheme'] . "://" . $my_url_parse['host'];
    $my_url_host = str_replace("www.", "", $my_url_parse['host']);
    $domainStr = escapeTrim($con, strtolower($my_url_host));

    // Define HTML for "true" and "false" icons.
    $true = '<img src="' . themeLink('img/true.png', true) . '" alt="' . trans('True', $lang['10'], true) . '" />';
    $false = '<img src="' . themeLink('img/false.png', true) . '" alt="' . trans('False', $lang['9'], true) . '" />';

    // Retrieve the stored HTML source data from the cached file.
    $sourceData = getMyData($filename);
    if ($sourceData == '')
        die($lang['AN10']);

    // Normalize the HTML (replace uppercase meta tag names with lowercase).
    $html = str_ireplace(["Title", "TITLE"], "title", $sourceData);
    $html = str_ireplace(["Description", "DESCRIPTION"], "description", $html);
    $html = str_ireplace(["Keywords", "KEYWORDS"], "keywords", $html);
    $html = str_ireplace(["Content", "CONTENT"], "content", $html);
    $html = str_ireplace(["Meta", "META"], "meta", $html);
    $html = str_ireplace(["Name", "NAME"], "name", $html);

    // Log debug info to help trace values.
    log_message('info', "Called my_url: {$my_url} | Parsed host: {$my_url_host} | domainStr: {$domainStr}");

    // Instantiate the SeoTools class with the cached HTML and parameters.
    $seoTools = new SeoTools($html, $con, $domainStr, $lang, $my_url_parse, $sepUnique, $seoBoxLogin);

    /*
     * -----------------------------------------------------------------
     * META DATA HANDLER
     * -----------------------------------------------------------------
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
     */
    if (isset($_POST['keyConsistency'])) {
        log_message('debug', "Keyword Consistency Tag Call for URL {$my_url_host}");
        $metaData    = jsonDecode($seoTools->processMeta());
        $headingData = jsonDecode($seoTools->processHeading());
        $keyCloudResult = $seoTools->processKeyCloud();
        $fullCloud = $keyCloudResult['fullCloud'] ?? [];
        echo $seoTools->showKeyConsistencyNgramsTabs($fullCloud, $metaData, $headingData[0]);
        die();
    }

    /*
     * -----------------------------------------------------------------
     * TEXT TO HTML RATIO CALCULATOR
     * -----------------------------------------------------------------
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
     */
    if (isset($_POST['in_page'])) {
        log_message('debug', "In-Page Links Tag Call for URL {$my_url_host}");
        $linksData = $seoTools->processInPageLinks();
        echo $seoTools->showInPageLinks($linksData);
        die();
    }

    /*
     * -----------------------------------------------------------------
     * BROKEN LINKS CHECKER
     * -----------------------------------------------------------------
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
     */
    if (isset($_POST['whois'])) {
        log_message('debug', "WHOIS Tag Call for URL {$my_url_host}");
        echo "Whois";
        //$whoisData = $seoTools->processWhois();
        //echo $seoTools->showWhois($whoisData);
        die();
    }

    /*
     * -----------------------------------------------------------------
     * INDEXED PAGES COUNTER
     * -----------------------------------------------------------------
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
     */
    if (isset($_POST['serverIP'])) {
        log_message('debug', "Server IP Tag Call for URL {$my_url_host}");
        $serverDataJson = $seoTools->processServerInfo();
        echo $seoTools->showServerInfo($serverDataJson);
        die();
    }

    /*
     * -----------------------------------------------------------------
     * SPEED TIPS ANALYZER
     * -----------------------------------------------------------------
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
     */
    if (isset($_POST['socialData'])) {
        // $socialData = $seoTools->processSocialData();
        // echo $seoTools->showSocialData($socialData);
        $schemaJson = $seoTools->processSchema();  // Process and store schema data.
        echo $seoTools->showSchema($schemaJson);
        die();
    }

    /*
     * -----------------------------------------------------------------
     * VISITORS LOCALIZATION
     * -----------------------------------------------------------------
     */
    if (isset($_POST['visitorsData'])) {
        $visitorsData = $seoTools->processVisitorsData();
        echo $seoTools->showVisitorsData($visitorsData);
        die();
    }

    /*
     * -----------------------------------------------------------------
     * CLEAN OUT / FINALIZE ANALYSIS
     * -----------------------------------------------------------------
     */
    if (isset($_POST['cleanOut'])) {
        $seoTools->cleanOut();
    }
}
// End of AJAX Handler.
die();
?>
