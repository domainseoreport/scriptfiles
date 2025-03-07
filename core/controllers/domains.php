<?php
// domains.php
defined('APP_NAME') or die(header('HTTP/1.1 403 Forbidden'));
require_once(LIB_DIR . 'SeoTools.php');
define('TEMP_DIR', APP_DIR . 'temp' . D_S);
// Dynamic CORS header based on the incoming Origin header
if (isset($_SERVER['HTTP_ORIGIN'])) {
    $origin = $_SERVER['HTTP_ORIGIN'];
    // Allow only subdomains matching http://[subdomain].localhost
    if (preg_match('/^http:\/\/[a-z0-9-]+\.localhost$/i', $origin)) {
        header("Access-Control-Allow-Origin: " . $origin);
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Max-Age: 86400"); // cache for 1 day
    }
}
/*
 * This script handles AJAX requests for various SEO and website performance tests.
 */

if (isset($_GET['getImage'])) {
    if (isset($_SESSION['snap'])) {
        $customSnapAPI = true;
        $customSnapLink = $_SESSION['snap'];
    }
    session_write_close();
    $my_url = clean_url(raino_trim($_GET['site']));
    log_message('debug', "GET Request: Processing snapshot for URL: {$my_url}");
    $imageData = getMyData(getSiteSnap($my_url, $item_purchase_code, $baseLink, $customSnapAPI, $customSnapLink));
    ob_clean();
    echo base64_encode($imageData);
    die();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['url']) || trim($_POST['url']) === '') {
        error_log("Error: No URL parameter provided in the POST request.");
        die("Error: URL parameter is missing.");
    }
    $urlInput = raino_trim($_POST['url']);
    log_message('debug', "POST Request: Received URL input: {$urlInput}");
    if (!preg_match('#^https?://#i', $urlInput)) {
        $urlInput = 'http://' . $urlInput;
        log_message('debug', "URL missing scheme. Prepended http://: {$urlInput}");
    }
    $my_url = $urlInput;
    error_log("Final URL being parsed: " . $my_url);
    log_message('info', "Called {$my_url}");
    $hashCode = raino_trim($_POST['hashcode']);
    log_message('debug', "Hash code received: {$hashCode}");
    $filename = TEMP_DIR . $hashCode . '.tdata';
    log_message('debug', "Filename for cached data: {$filename}");
    $sepUnique = '!!!!8!!!!';
    $my_url_parse = parse_url($my_url);
    if (!isset($my_url_parse['scheme']) || !isset($my_url_parse['host'])) {
        error_log("Error: Invalid URL provided: {$my_url}");
        die("Error: Invalid URL provided. Please include both scheme and host.");
    }
    $inputHost = $my_url_parse['scheme'] . "://" . $my_url_parse['host'];
    $my_url_host = str_replace("www.", "", $my_url_parse['host']);
    $domainStr = escapeTrim($con, strtolower($my_url_host));
    log_message('info', "Parsed URL: {$my_url} | Host: {$my_url_host} | Domain String: {$domainStr}");
    $true = '<img src="' . themeLink('img/true.png', true) . '" alt="' . trans('True', $lang['10'], true) . '" />';
    $false = '<img src="' . themeLink('img/false.png', true) . '" alt="' . trans('False', $lang['9'], true) . '" />';
    $sourceData = getMyData($filename);
    if ($sourceData == '') {
        log_message('error', "Source data is empty for file: {$filename}");
        die($lang['AN10']);
    }
    $html = str_ireplace(["Title", "TITLE"], "title", $sourceData);
    $html = str_ireplace(["Description", "DESCRIPTION"], "description", $html);
    $html = str_ireplace(["Keywords", "KEYWORDS"], "keywords", $html);
    $html = str_ireplace(["Content", "CONTENT"], "content", $html);
    $html = str_ireplace(["Meta", "META"], "meta", $html);
    $html = str_ireplace(["Name", "NAME"], "name", $html);
    log_message('info', "Processed HTML for URL: {$my_url}");
    $seoTools = new SeoTools($html, $con, $domainStr, $lang, $my_url_parse, $sepUnique, $seoBoxLogin);

    // Process AJAX calls based on POST parameters:
    if (isset($_POST['meta'])) {
        log_message('debug', "Meta Tag Call for URL {$my_url_host}");
        $metaData = $seoTools->processMeta();
        if (isset($_POST['metaOut'])) {
            echo $seoTools->showMeta($metaData);
            die();
        }
    }
    if (isset($_POST['heading'])) {
        log_message('debug', "Heading Tag Call for URL {$my_url_host}");
        $headings = $seoTools->processHeading();
        if (isset($_POST['headingOut'])) {
            echo $seoTools->showHeading($headings);
            die();
        }
    }
    if (isset($_POST['keycloudAll'])) {
        log_message('debug', "Keyword Cloud + Consistency Call for URL {$my_url_host}");
        $htmlOutput = $seoTools->processKeyCloudAndConsistency();
        echo $htmlOutput;
        die();
    }
    if (isset($_POST['linkanalysis'])) {
        log_message('debug', "In-Page Links Tag Call for URL {$my_url_host}");
        $linksData = $seoTools->processInPageLinks();
        echo $seoTools->showInPageLinks($linksData);
        die();
    }
    if (isset($_POST['sitecards'])) {
        log_message('debug', "Site Cards Call for URL {$my_url_host}");
        $sitecardsData = $seoTools->processSiteCards();
        echo $seoTools->showCards($sitecardsData);
        die();
    }
    if (isset($_POST['PageAnalytics'])) {
        log_message('debug', "Page Analytics Report Call for URL {$my_url_host}");
        $pageAnalyticsJson = $seoTools->processPageAnalytics();
        echo $seoTools->showPageAnalytics($pageAnalyticsJson);
        die();
    }
    if (isset($_POST['image'])) {
        log_message('debug', "Image Tag Call for URL {$my_url_host}");
        $imageData = $seoTools->processImage();
        echo $seoTools->showImage($imageData);
        die();
    }
    if (isset($_POST['textRatio'])) {
        log_message('debug', "Text Ratio Tag Call for URL {$my_url_host}");
        $textRatio = $seoTools->processTextRatio();
        echo $seoTools->showTextRatio($textRatio);
        die();
    }
    if (isset($_POST['serverIP'])) {
        log_message('debug', "Server IP Tag Call for URL {$my_url_host}");
        $serverDataJson = $seoTools->processServerInfo();
        echo $seoTools->showServerInfo($serverDataJson);
        die();
    }
    if (isset($_POST['SchemaData'])) {
        log_message('debug', "Schema Data Call for URL {$my_url_host}");
        $schemaJson = $seoTools->processSchema();
        echo $seoTools->showSchema($schemaJson);
        die();
    }
    if (isset($_POST['socialurls'])) {
        log_message('debug', "Social URL Call for URL {$my_url_host}");
        $socialURLs = $seoTools->processSocialUrls();
        echo $seoTools->showSocialUrls($socialURLs);
        die();
    }
    if (isset($_POST['PageSpeedInsights'])) {
        log_message('debug', "Page Insights Report Call for URL {$my_url_host}");
        $jsonData = $seoTools->processPageSpeedInsightConcurrent();
        echo $seoTools->showPageSpeedInsightConcurrent($jsonData);
        die();
    }
    if (isset($_POST['cleanOut'])) {
        log_message('debug', "Clean Out Call for URL {$my_url_host}");
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
            log_message('info', "Final score retrieved for domain {$domainStr}");
            echo $data['score'];
        } else {
            log_message('error', "Final score not found for domain {$domainStr}");
            echo json_encode(['passed' => 0, 'improve' => 0, 'errors' => 0, 'percent' => 0]);
        }
        die();
    }
}
die();
?>
