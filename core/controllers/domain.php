<?php
defined('APP_NAME') or die(header('HTTP/1.1 403 Forbidden'));

// Define the temporary directory constant for cached files.
define('TEMP_DIR', APP_DIR.'temp'.D_S);

/*
 * Author: Balaji
 * Name: Rainbow PHP Framework
 * Copyright 2020 ProThemes.Biz
 *
 * This file handles POST requests for processing a given URL.
 * It validates input, parses the domain, checks for banned/restricted domains,
 * and then either updates or creates a domain record before initiating further SEO analysis.
 */

//----------------------------
// POST REQUEST Handler
//----------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['url']) && trim($_POST['url']) != '') {
        $urlInput = raino_trim($_POST['url']);
        log_message('debug', "Received URL input: $urlInput");

        // Prepend "http://" so that a bare domain becomes a full URL.
        $fullUrl = 'http://' . clean_url($urlInput);
        log_message('debug', "Full URL after cleaning: $fullUrl");

        // Validate the URL format.
        if (!filter_var($fullUrl, FILTER_VALIDATE_URL)) {
            log_message('error', "Invalid domain format provided: $urlInput");
            $_SESSION['TWEB_CALLBACK_ERR'] = trans('Input Site is not valid!', $lang['8'], true);
            redirectTo(createLink('', true));
            die();
        }
        
        // Parse the URL to extract its components.
        $myUrl = parse_url($fullUrl);
        if (!isset($myUrl['host']) || empty($myUrl['host'])) {
            log_message('error', "Parsed domain is empty for input: $urlInput");
            $_SESSION['TWEB_CALLBACK_ERR'] = trans('Input Site is not valid!', $lang['8'], true);
            redirectTo(createLink('', true));
            die();
        }
        
        // Remove the "www." prefix (if any) and force lowercase.
        $myUrlHost = strtolower(str_replace('www.', '', $myUrl['host']));
        log_message('debug', "Parsed host: $myUrlHost");

        // Redirect to the appropriate controller URL.
        redirectTo(createLink($controller . '/' . $myUrlHost, true));
        die();
    } else {
        log_message('error', "No URL provided in POST request.");
        $_SESSION['TWEB_HOMEPAGE'] = 1;
        $_SESSION['TWEB_CALLBACK_ERR'] = trans('Input Site is not valid!', $lang['8'], true);
        redirectTo(createLink('', true));
        die();
    }
}

//----------------------------
// Check User Request
//----------------------------
if ($pointOut == '') {
    header('Location: ' . $baseURL);
    log_message('info', "No pointOut provided; redirecting to base URL.");
} elseif (strpos($pointOut, '.') === false) {
    $_SESSION['TWEB_HOMEPAGE'] = 1;
    log_message('error', "Invalid domain (no dot found) in: " . $_POST['url']);
    redirectTo(createLink('', true));
    die(trans('Input Site is not valid!', $lang['8'], true));
}

// Initialize flags and variables.
$domainFound   = $updateFound = $customSnapAPI = $newDomain = false;
$pdfUrl        = $updateUrl = $shareLink = $pageTitle = $des = $keyword = $customSnapLink = '';
$isOnline      = '0';
$nowDate       = date('m/d/Y h:i:sA');
$disDate       = date('F, j Y h:i:s A');

// Define HTML for "true" and "false" icons.
$true  = '<img src="'.themeLink('img/true.png', true).'" alt="'.trans('True', $lang['10'], true).'" />';
$false = '<img src="'.themeLink('img/false.png', true).'" alt="'.trans('False', $lang['9'], true).'" />';

//----------------------------
// Update Check
//----------------------------
if (isset($route[2])) {
    $updateCheck = strtolower(raino_trim($route[2]));
    log_message('debug', "Update check parameter: $updateCheck");
    if ($updateCheck == 'update')
        $updateFound = true;
}

//----------------------------
// Domain and URL Setup
//----------------------------
$my_url = raino_trim($pointOut);
$my_url = 'http://' . clean_url($my_url);
log_message('debug', "Final URL to process: $my_url");

// Parse the URL components.
$my_url_parse = parse_url($my_url);
$inputHost    = $my_url_parse['scheme'] . "://" . $my_url_parse['host'];
$my_url_host  = str_replace('www.', '', $my_url_parse['host']);
$domainStr    = escapeTrim($con, strtolower($my_url_host));
$pageTitle    = $domainName = ucfirst($my_url_host);

if ($my_url_host == '') {
    log_message('error', "Empty host after parsing URL: $my_url");
    die(trans('Input Site is not valid!', $lang['8'], true));
}

//----------------------------
// Domain Restriction and Banned Domains
//----------------------------
$list = getDomainRestriction($con);
log_message('debug', "Retrieved domain restriction list.");

// Check Banned Domains.
if (in_array($domainStr, $list[4])) {
    log_message('error', "Domain restricted (banned): $domainStr");
    redirectTo(createLink('warning/restricted-domains/' . $domainStr, true));
    die();
}

// Check for restricted words.
foreach ($list[0] as $badWord) {
    if (check_str_contains($domainStr, trim($badWord), true)) {
        log_message('error', "Domain contains restricted word: $badWord in $domainStr");
        redirectTo(createLink('warning/restricted-words', true));
        die();
    }
}

//----------------------------
// Share Link and Reviewer Settings
//----------------------------
$shareLink = createLink($controller . '/' . $domainStr, true);
$reviewerSettings = reviewerSettings($con);
log_message('debug', "Reviewer settings loaded for domain: $domainStr");

$updateUrl = createLink($controller . '/' . $domainStr . '/' . 'update', true);
$pdfUrl    = createLink('genpdf/' . $domainStr, true);

//----------------------------
// Login Access Check
//----------------------------
if ($enable_reg) {
    if (!isset($_SESSION['twebUsername'])) {
        if ($updateFound) {
            if (!isset($_SESSION['twebAdminToken'])) {
                log_message('error', "User not logged in for update. Redirecting to login.");
                redirectTo(createLink('account/login', true));
                die();
            }
        }
        $username = trans('Guest', $lang['11'], true);
        $reviewerSettings['reviewer_list'] = unserialize($reviewerSettings['reviewer_list']);
        $freeLimit = (int)$reviewerSettings['free_limit'];
        $pdfUrl    = $updateUrl = createLink('account/login', true);
    } else {
        $username = $_SESSION['twebUsername'];
    }
    log_message('debug', "User logged in as: $username");
}

//----------------------------
// Screenshot API Setup
//----------------------------
$reviewerSettings['snap_service'] = dbStrToArr($reviewerSettings['snap_service']);
if ($reviewerSettings['snap_service']['options'] == 'prothemes_pro') {
    $customSnapAPI  = true;
    $customSnapLink = $reviewerSettings['snap_service']['prothemes_pro'];
    $_SESSION['snap'] = $reviewerSettings['snap_service']['prothemes_pro'];
} elseif ($reviewerSettings['snap_service']['options'] == 'custom') {
    $customSnapAPI  = true;
    $customSnapLink = $reviewerSettings['snap_service']['custom'];
    $_SESSION['snap'] = $reviewerSettings['snap_service']['custom'];
} else {
    if (isset($_SESSION['snap']))
        unset($_SESSION['snap']);
}
log_message('debug', "Screenshot API configured.");

//----------------------------
// Check Domain Existence in DB
//----------------------------
$data = mysqliPreparedQuery($con, "SELECT * FROM domains_data WHERE domain=?", 's', array($domainStr));
if ($data !== false) {
    if ($data['completed'] == 'yes') {
        $domainFound = true;
        log_message('info', "Domain already processed: $domainStr");
    } else {
        $updateFound   = true;
        $domainFound   = false;
        log_message('debug', "Domain found but not completed: $domainStr");
    }
} else {
    $updateFound = true;
    $domainFound = false;
    $newDomain   = true;
    log_message('debug', "New domain detected: $domainStr");
}

// If new domain, check if accessible; otherwise, use stored URL.
if ($newDomain) {
    $my_url = isDomainAccessible(clean_url($my_url));
    log_message('debug', "New domain accessibility check complete for: $my_url");
} else {
    $my_url = $data['domain_access_url'];
    log_message('debug', "Using stored domain access URL: $my_url");
}

// Hash Code for caching.
$hashCode = md5($my_url_host);
$filename = TEMP_DIR . $hashCode . '.tdata';
log_message('debug', "Hash code generated: $hashCode");

//----------------------------
// Fetch Data of the URL if Update is Needed
//----------------------------
if ($updateFound) {
    
    if (!$my_url) {
        $error = trans('The provided domain is not accessible or does not exist!', $lang['8'], true);
        log_message('error', "Domain not accessible: " . clean_url($my_url));
        $_SESSION['TWEB_CALLBACK_ERR'] = $error;
        redirectTo(createLink('', true));
        die();
    }
     
    // Check captcha verification if required.
    $capOkay = true;
    extract(loadCapthca($con));
    if (isSelected($reviewer_page)) {
        if (!isset($_SESSION['twebReviewerFine'])) {
            $capOkay = false;
            $_SESSION['twebReviewerDomain'] = $domainStr;
            log_message('debug', "Captcha not verified for domain: $domainStr");
            redirectTo(createLink('check/verfication', true));
            die();
        } else {
            unset($_SESSION['twebReviewerFine']);
            unset($_SESSION['twebReviewerDomain']);
            $capOkay = true;
        }
    }
    
    if ($capOkay) {
        // Attempt to fetch the source HTML content.
        $sourceData = robustFetchHtml($my_url);
        if ($sourceData === false) {
            log_message('error', "Failed to fetch content for URL: {$my_url}");
            echo "Failed to fetch content.";
            die();
        }
        
        // Second attempt if content is empty.
        if ($sourceData == '') {
            $sourceData = getMyData($my_url);
            if ($sourceData == '') {
                $error = trans('Input Site is not valid!', $lang['8'], true);
                log_message('error', "Empty content fetched for URL: {$my_url}");
            }
        }
        
        if (!isset($error)) {
            // Mark domain as online and store the raw HTML.
            $isOnline = '1';
            putMyData($filename, $sourceData);
            log_message('info', "Content stored in temp file $filename for URL {$my_url}");
            
            // Extract meta information.
            $metaData    = extractMetaData($sourceData);
            $title       = isset($metaData['title']) ? $metaData['title'] : '';
            $description = isset($metaData['description']) ? $metaData['description'] : '';
            $keywords    = isset($metaData['keywords']) ? $metaData['keywords'] : '';
            
            // Check for restricted words.
            if (hasRestrictedWords($title, $description, $keywords, $list)) {
                log_message('error', "Domain {$domainStr} contains restricted words.");
                redirectTo(createLink('warning/restricted-words', true));
                die();
            }
            
            // Create domain record if this is a new domain.
            if ($newDomain) {
                if (!isset($_SESSION['TWEB_HOMEPAGE'])) {
                    $_SESSION['TWEB_HOMEPAGE'] = 1;
                    log_message('debug', "New domain to add in DB: {$my_url}");
                    redirectTo(createLink('', true));
                    die();
                }
            
                $result = createDomainRecord($con, $domainStr, $my_url, $nowDate, $title, $description, $keywords);
                if ($result !== true) {
                    log_message('error', "Not able to insert domain in DB: $result for URL {$my_url}");
                    $error = $result;
                } else {
                    log_message('info', "New domain record created for: $domainStr");
                }
            }
            
            // Instantiate the SeoAnalyzer for further SEO analysis.
            require_once dirname(__DIR__) . '/SeoAnalyzer.php';
            $seo = new SeoAnalyzer();
            log_message('debug', "SeoAnalyzer instantiated for domain: $domainStr");
            // Further processing of SEO analysis can be done here...
        }
    }
}

//----------------------------
// Continue with Domain Processing or Load Existing Data
//----------------------------
if (!isset($error)) {
    if ($updateFound) {
        // For new or updated domains, enforce free user limits if applicable.
        if (!isset($_SESSION['twebAdminToken'])) {
            if (!isset($_SESSION['twebUsername'])) {
                if (isset($_SESSION['TWEB_FREE_LIMIT'])) {
                    $limitUsed = (int)$_SESSION['TWEB_FREE_LIMIT'];
                    if ($limitUsed == $freeLimit) {
                        log_message('info', "User reached free limit for domain processing.");
                        redirectTo($updateUrl);
                        die();
                    } else {
                        $limitUsed++;
                        $_SESSION['TWEB_FREE_LIMIT'] = $limitUsed;
                    }
                } else {
                    $_SESSION['TWEB_FREE_LIMIT'] = 1;
                }
            }
        }
    } else { 
        // Domain processing has been completed previously. Load stored data.
        log_message('info', "Domain already processed. Loading DB data for {$domainStr}");
        define('DB_DOMAIN', true); 
        require(CON_DIR.'db-domain.php');  
        $reviewerSettings['domain_data'] = dbStrToArr($reviewerSettings['domain_data']); 
        $metaTitle = shortCodeFilter($reviewerSettings['domain_data']['domain']['title']);
        $des = shortCodeFilter($reviewerSettings['domain_data']['domain']['des']);
    }
} else {
    // If an error occurred, log it and redirect.
    log_message('error', "Error in domain processing: " . $error);
    $_SESSION['TWEB_CALLBACK_ERR'] = $error;
    redirectTo(createLink('', true));
    die();
}
?>
