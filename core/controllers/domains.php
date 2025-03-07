<?php
// Prevent direct access to the script.
defined('APP_NAME') or die(header('HTTP/1.1 403 Forbidden'));

// Include the SEO tools library.
require_once(LIB_DIR . 'SeoTools.php');

// Define the temporary directory constant for cached files.
define('TEMP_DIR', APP_DIR . 'temp' . D_S);

/*
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

/*
 * ---------------------------------------------------------------------
 * GET REQUEST HANDLER
 * ---------------------------------------------------------------------
 */
if (isset($_GET['getImage'])) {
    // Check if custom snapshot API is configured.
    if (isset($_SESSION['snap'])) {
        $customSnapAPI = true;
        $customSnapLink = $_SESSION['snap'];
    }
    // Close the session early.
    session_write_close();
    
    // Clean and fetch the URL from GET parameter.
    $my_url = clean_url(raino_trim($_GET['site']));
    log_message('debug', "GET Request: Processing snapshot for URL: {$my_url}");
    
    // Get the site snapshot and encode the image data.
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
    // Verify that the URL parameter is provided.



    if (!isset($_POST['url']) || trim($_POST['url']) === '') {
        error_log("Error: No URL parameter provided in the POST request.");
        die("Error: URL parameter is missing.");
    }

    // Retrieve and sanitize the website URL.
    $urlInput = raino_trim($_POST['url']);
    log_message('debug', "POST Request: Received URL input: {$urlInput}");

    // Prepend "http://" if missing.
    if (!preg_match('#^https?://#i', $urlInput)) {
        $urlInput = 'http://' . $urlInput;
        log_message('debug', "URL missing scheme. Prepended http://: {$urlInput}");
    }
    // Uncomment the next line if you wish to use clean_url() on the input.
    // $my_url = clean_url($urlInput);
    $my_url = $urlInput; 
    
    // Log the final URL being processed.
    error_log("Final URL being parsed: " . $my_url);
    log_message('info', "Called {$my_url}");

    // Retrieve the unique hash code for caching.
    $hashCode = raino_trim($_POST['hashcode']);
    log_message('debug', "Hash code received: {$hashCode}");

    // Construct the filename where the website source data is stored.
    $filename = TEMP_DIR . $hashCode . '.tdata';
    log_message('debug', "Filename for cached data: {$filename}");

    // Define a unique separator string for splitting output sections.
    $sepUnique = '!!!!8!!!!';

    // Parse the input URL to extract the scheme and host.
    $my_url_parse = parse_url($my_url);
    if (!isset($my_url_parse['scheme']) || !isset($my_url_parse['host'])) {
        error_log("Error: Invalid URL provided: {$my_url}");
        die("Error: Invalid URL provided. Please include both scheme and host.");
    }
    $inputHost = $my_url_parse['scheme'] . "://" . $my_url_parse['host'];
    $my_url_host = str_replace("www.", "", $my_url_parse['host']);
    $domainStr = escapeTrim($con, strtolower($my_url_host));

    log_message('info', "Parsed URL: {$my_url} | Host: {$my_url_host} | Domain String: {$domainStr}");

    // Define HTML for "true" and "false" icons.
    $true = '<img src="' . themeLink('img/true.png', true) . '" alt="' . trans('True', $lang['10'], true) . '" />';
    $false = '<img src="' . themeLink('img/false.png', true) . '" alt="' . trans('False', $lang['9'], true) . '" />';

    // Retrieve the stored HTML source data from the cached file.
    $sourceData = getMyData($filename);
    if ($sourceData == '') {
        log_message('error', "Source data is empty for file: {$filename}");
        die($lang['AN10']);
    }

    // Normalize the HTML by converting uppercase meta tags to lowercase.
    $html = str_ireplace(["Title", "TITLE"], "title", $sourceData);
    $html = str_ireplace(["Description", "DESCRIPTION"], "description", $html);
    $html = str_ireplace(["Keywords", "KEYWORDS"], "keywords", $html);
    $html = str_ireplace(["Content", "CONTENT"], "content", $html);
    $html = str_ireplace(["Meta", "META"], "meta", $html);
    $html = str_ireplace(["Name", "NAME"], "name", $html);

    // Log debug info after processing HTML.
    log_message('info', "Processed HTML for URL: {$my_url}");

    // Instantiate the SeoTools class with the cached HTML and parameters.
    $seoTools = new SeoTools($html, $con, $domainStr, $lang, $my_url_parse, $sepUnique, $seoBoxLogin);

    /*
     * -----------------------------------------------------------------
     * META DATA HANDLER - Generates meta tags.
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
     * HEADING DATA HANDLER - Generates heading tags.
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
        log_message('debug', "Keyword Cloud + Consistency Call for URL {$my_url_host}");
        $htmlOutput = $seoTools->processKeyCloudAndConsistency();
        echo $htmlOutput;
        die();
    }

    /*
     * -----------------------------------------------------------------
     * IN-PAGE LINKS ANALYZER - Analyzes in-page links.
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
     * CARDS FUNCTION - Generates site cards.
     * -----------------------------------------------------------------
     */
    if (isset($_POST['sitecards'])) {
        log_message('debug', "Site Cards Call for URL {$my_url_host}");
        $sitecardsData = $seoTools->processSiteCards();
        echo $seoTools->showCards($sitecardsData);
        die();
    }

    /*
     * -----------------------------------------------------------------
     * PAGE ANALYTICS REPORT - Generates page analytics.
     * -----------------------------------------------------------------
     */
    if (isset($_POST['PageAnalytics'])) {
        log_message('debug', "Page Analytics Report Call for URL {$my_url_host}");
        $pageAnalyticsJson = $seoTools->processPageAnalytics();
        echo $seoTools->showPageAnalytics($pageAnalyticsJson);
        die();
    }

    /*
     * -----------------------------------------------------------------
     * IMAGE ALT TAG CHECKER - Processes image alt tags.
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
     * TEXT TO HTML RATIO CALCULATOR - Calculates text-to-HTML ratio.
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
     * SERVER LOCATION INFORMATION - Retrieves server info.
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
     * SCHEMA DATA RETRIEVAL - Retrieves and shows schema data.
     * -----------------------------------------------------------------
     */
    if (isset($_POST['SchemaData'])) {
        log_message('debug', "Schema Data Call for URL {$my_url_host}");
        $schemaJson = $seoTools->processSchema();
        echo $seoTools->showSchema($schemaJson);
        die();
    }
    
    /*
     * -----------------------------------------------------------------
     * SOCIAL URL RETRIEVAL - Retrieves social URLs.
     * -----------------------------------------------------------------
     */
    if (isset($_POST['socialurls'])) {
        log_message('debug', "Social URL Call for URL {$my_url_host}");
        $socialURLs = $seoTools->processSocialUrls();
        echo $seoTools->showSocialUrls($socialURLs);
        die();
    }
    
    /*
     * -----------------------------------------------------------------
     * GOOGLE PAGESPEED INSIGHTS REPORT - Retrieves PageSpeed Insights data.
     * -----------------------------------------------------------------
     */
    if (isset($_POST['PageSpeedInsights'])) {
        log_message('debug', "Page Insights Report Call for URL {$my_url_host}");
        // Process and store the PageSpeed Insights report concurrently.
        // This returns a JSON string.
        $jsonData = $seoTools->processPageSpeedInsightConcurrent();
        echo $seoTools->showPageSpeedInsightConcurrent($jsonData);
        die();
    }

    /*
     * -----------------------------------------------------------------
     * CLEAN OUT / FINALIZE ANALYSIS - Finalizes and cleans up the analysis.
     * -----------------------------------------------------------------
     */
    if (isset($_POST['cleanOut'])) {
        log_message('debug', "Clean Out Call for URL {$my_url_host}");
        $seoTools->cleanOut();
    }

    // Retrieve the final score from the database.
    if (isset($_POST['getFinalScore'])) {
        $data = mysqliPreparedQuery(
            $con,
            "SELECT score FROM domains_data WHERE domain=?",
            's',
            [$domainStr]
        );
        if ($data !== false) {
            log_message('info', "Final score retrieved for domain {$domainStr}");
            // Output the score field as JSON.
            echo $data['score'];
        } else {
            log_message('error', "Final score not found for domain {$domainStr}");
            echo json_encode(['passed' => 0, 'improve' => 0, 'errors' => 0, 'percent' => 0]);
        }
        die();
    }
}
// End of AJAX Handler.
die();
?>
