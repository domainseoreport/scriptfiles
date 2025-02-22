<?php
//domains.php File
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
     * META DATA HANDLER - Used to generate meta tags.
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
     * HEADING DATA HANDLER - Used to generate heading tags.
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
 * KEYWORD CLOUD + CONSISTENCY MERGED
 * -----------------------------------------------------------------
 */

 
if (isset($_POST['keycloudAll'])) { 
    $htmlOutput = $seoTools->processKeyCloudAndConsistency();
    echo $htmlOutput;
    die();
}

        /*
    //  * -----------------------------------------------------------------
    //  * KEYWORD CLOUD GENERATOR - Used to generate keyword cloud.
    //  * -----------------------------------------------------------------
    //  */
    // if (isset($_POST['keycloud'])) {
    //     log_message('debug', "Keyword Cloud Tag Call for URL {$my_url_host}");
    //     $htmlOutput = $seoTools->processKeyCloudAndConsistency();
    //     echo $htmlOutput;
    //     $keyCloud = $seoTools->processKeyCloud();
    //     if (isset($_POST['keycloudOut'])) {
    //         echo $seoTools->showKeyCloud($keyCloud);
    //         die();
    //     }
    // }

    // /*
    //  * -----------------------------------------------------------------
    //  * KEYWORD CONSISTENCY CHECKER - Used to check keyword consistency.
    //  * -----------------------------------------------------------------
    //  */
    // if (isset($_POST['keyConsistency'])) {
    //     // 1) Get the meta data in its full structure
    //     $metaData    = jsonDecode($seoTools->processMeta()); 
    //     // 2) Get the headings in their full structure
    //     $headingData = jsonDecode($seoTools->processHeading()); 
    
    //     // 3) Get the full keyword cloud data
    //     $keyCloudResult = $seoTools->processKeyCloud();
    //     $fullCloud      = $keyCloudResult['fullCloud'] ?? [];
    
    //     // 4) Pass the entire $metaData and $headingData
    //     //    NOT just $headingData['raw'] or $metaData['raw']
    //     echo $seoTools->showKeyConsistencyNgramsTabs($fullCloud, $metaData, $headingData);
    //     die();
    // }

    /*
     * -----------------------------------------------------------------
     * IN-PAGE LINKS ANALYZER - Used to analyze in-page links.
     * -----------------------------------------------------------------
     */
    if (isset($_POST['linkanalysis'])) {
        log_message('debug', "In-Page Links Tag Call for URL {$my_url_host}");
        $linksData = $seoTools->processInPageLinks();
        echo $seoTools->showInPageLinks($linksData);
        die();
    }
    
     /*
     * -----------------------------------------------------------------
     * Cards Function to show site card - Used to generate site cards.
     * -----------------------------------------------------------------
     */
    if (isset($_POST['sitecards'])) {
        log_message('debug', "Site Cards for {$my_url_host}");
        $sitecardsData = $seoTools->processSiteCards();
        echo $seoTools->showCards($sitecardsData);
        die();
    }
    
       /*
     * -----------------------------------------------------------------
     * Page Analytics Report - Used to generate page analytics.
     * -----------------------------------------------------------------
     */
    if (isset($_POST['PageAnalytics'])) { 
        log_message('debug', "Page Analytic report {$my_url_host}");
        $pageAnalyticsJson = $seoTools->processPageAnalytics();
        echo $seoTools->showPageAnalytics($pageAnalyticsJson);
        die();
    }
    
    /*
     * -----------------------------------------------------------------
     * LOAD DOM LIBRARY (if needed)
     * -----------------------------------------------------------------
     */
    // if (isset($_POST['loaddom'])) {
    //     log_message('debug', "DOM Library Tag Call for URL {$my_url_host}");
    //     require_once(LIB_DIR . "simple_html_dom.php");
    //     $domData = load_html($sourceData);
    // }

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
     * WWW RESOLVE CHECK
     * -----------------------------------------------------------------
     */
    // if (isset($_POST['www_resolve'])) {
    //     log_message('debug', "WWW Resolve Tag Call for URL {$my_url_host}");
    //     $resolveData = $seoTools->processWWWResolve();
    //     echo $seoTools->showWWWResolve($resolveData);
    //     die();
    // }
 

    

    /*
     * -----------------------------------------------------------------
     * BROKEN LINKS CHECKER
     * -----------------------------------------------------------------
    //  */
    // if (isset($_POST['brokenlinks'])) {
    //     log_message('debug', "Broken Links Tag Call for URL {$my_url_host}");
    //     $brokenLinks = $seoTools->processBrokenLinks();
    //     echo $seoTools->showBrokenLinks($brokenLinks);
    //     die();
    // }
 
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
     * Schema Data RETRIEVAL
     * -----------------------------------------------------------------
     */
    if (isset($_POST['SchemaData'])) { 
        $schemaJson = $seoTools->processSchema();  // Process and store schema data.
        echo $seoTools->showSchema($schemaJson);
        die();
    }
    
      /*
     * -----------------------------------------------------------------
     * SOCIAL URL  RETRIEVAL
     * -----------------------------------------------------------------
     */
    if (isset($_POST['socialurls'])) { 
        $socialURLs = $seoTools->processSocialUrls();  // Process and store schema data.
        echo $seoTools->showSocialUrls($socialURLs);
        die();
    }
    
     /*
     * -----------------------------------------------------------------
     * Google PageSpeed Insights Report
     * -----------------------------------------------------------------
     */
    if (isset($_POST['PageSpeedInsights'])) { 
        log_message('debug', "Page Insights report for {$my_url_host}");
        // Process and store the PageSpeed Insights report concurrently.
        // This returns a JSON string.
        $jsonData = $seoTools->processPageSpeedInsightConcurrent();
        
        // Then pass the JSON string to the show function.
        echo $seoTools->showPageSpeedInsightConcurrent($jsonData);
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

    if (isset($_POST['getFinalScore'])) {
        $data = mysqliPreparedQuery(
            $con,
            "SELECT score FROM domains_data WHERE domain=?",
            's',
            [$domainStr]
        );
        if ($data !== false) {
            // Output the score field as JSON. For example, if the stored score is a JSON string.
            echo $data['score'];
            $piyush=122;
        } else {
            echo json_encode(['passed' => 0, 'improve' => 0, 'errors' => 0, 'percent' => 0]);
        }
        die();
    }


}
// End of AJAX Handler.
die();
?>
