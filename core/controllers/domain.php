<?php
defined('APP_NAME') or die(header('HTTP/1.1 403 Forbidden'));
define('TEMP_DIR', APP_DIR . 'temp' . D_S);

/* ------------------------- Main POST Handler ------------------------- */
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['url']) || trim($_POST['url']) == '') {
        handleErrorAndRedirect("No URL provided in POST request.", $lang['8']);
    }
    // Process input URL.
    $urlData = processUrlInput($_POST['url']);
    if ($urlData === false) {
        handleErrorAndRedirect("Invalid URL format provided: " . $_POST['url'], $lang['8']);
    }
    
    // Query the database for a record matching the normalized host.
    // (Make sure getDomainByHost() is implemented to return a row from domains_data.)
    $domainRecord = getDomainByHost($con, $urlData['host']);
    if ($domainRecord && isset($domainRecord['slug']) && !empty($domainRecord['slug'])) {
         // Domain exists in DB and has a slug; redirect to the subdomain URL.
         $subdomainUrl = "http://" . $domainRecord['slug'] . ".localhost";
         redirectTo($subdomainUrl);
    } else {
         // Otherwise, fallback to the existing behavior.
         redirectTo(createLink($controller . '/' . $urlData['host'], true));
    }
    die();
}

/* ------------------------- Router-based Domain Processing ------------------------- */

// Ensure that $pointOut (set by your router) is valid.
if (empty($pointOut) || strpos($pointOut, '.') === false) {
    header('Location: ' . $baseURL);
    log_message('info', "No valid pointOut provided; redirecting to base URL.");
    die(trans('Input Site is not valid!', $lang['8'], true));
}

// Initialize variables.
$domainFound   = $updateFound = $customSnapAPI = $newDomain = false;
$pdfUrl        = $updateUrl = $shareLink = $pageTitle = $des = $keyword = $customSnapLink = '';
$isOnline      = '0';
$nowDate       = date('m/d/Y h:i:sA');
$disDate       = date('F, j Y h:i:s A');

// Define HTML for "true" and "false" icons.
$trueIcon  = '<img src="' . themeLink('img/true.png', true) . '" alt="' . trans('True', $lang['10'], true) . '" />';
$falseIcon = '<img src="' . themeLink('img/false.png', true) . '" alt="' . trans('False', $lang['9'], true) . '" />';

// Check for an update request via $route[2].
if (isset($route[2]) && strtolower(raino_trim($route[2])) == 'update') {
    $updateFound = true;
    log_message('debug', "Update check parameter set.");
}

// Domain and URL Setup.
$my_url = 'http://' . clean_url(raino_trim($pointOut));
log_message('debug', "Final URL to process: $my_url");
$my_url_parse = parse_url($my_url);
if (empty($my_url_parse['host'])) {
    handleErrorAndRedirect("Empty host after parsing URL: $my_url", $lang['8']);
}
$inputHost   = $my_url_parse['scheme'] . "://" . $my_url_parse['host'];
$my_url_host = str_replace('www.', '', $my_url_parse['host']);
$domainStr   = escapeTrim($con, strtolower($my_url_host));
$pageTitle   = $domainName = ucfirst($my_url_host);

// Domain restrictions.
$restrictionList = getDomainRestriction($con);
log_message('debug', "Retrieved domain restriction list.");
enforceDomainRestrictions($domainStr, $restrictionList);

// Set share link and load reviewer settings.
$shareLink = createLink($controller . '/' . $domainStr, true);
$reviewerSettings = reviewerSettings($con);
log_message('debug', "Reviewer settings loaded for domain: $domainStr");

$updateUrl = createLink($controller . '/' . $domainStr . '/update', true);
$pdfUrl    = createLink('genpdf/' . $domainStr, true);

// Login access check.
if ($enable_reg) {
    if (!isset($_SESSION['twebUsername'])) {
        if ($updateFound && !isset($_SESSION['twebAdminToken'])) {
            log_message('error', "User not logged in for update.");
            redirectTo(createLink('account/login', true));
            die();
        }
        $username = trans('Guest', $lang['11'], true);
        $reviewerSettings['reviewer_list'] = unserialize($reviewerSettings['reviewer_list']);
        $freeLimit = (int)$reviewerSettings['free_limit'];
        $pdfUrl =createLink('account/login', true);
        //$pdfUrl = $updateUrl = createLink('account/login', true);
    } else {
        $username = $_SESSION['twebUsername'];
    }
    log_message('debug', "User logged in as: $username");
}

// Screenshot API Setup.
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
    if (isset($_SESSION['snap'])) {
        unset($_SESSION['snap']);
    }
}
log_message('debug', "Screenshot API configured.");

// Check if domain already exists in the database.
$data = mysqliPreparedQuery($con, "SELECT * FROM domains_data WHERE domain=?", 's', [$domainStr]);
if ($data !== false) {
    if ($data['completed'] == 'yes') {
        $domainFound = true;
        log_message('info', "Domain already processed: $domainStr");
    } else {
        $updateFound = true;
        $domainFound = false;
        log_message('debug', "Domain found but not completed: $domainStr");
    }
} else {
    $updateFound = true;
    $domainFound = false;
    $newDomain   = true;
    log_message('debug', "New domain detected: $domainStr");
}

// Determine the working URL.
if ($newDomain) {
    $workingUrl = isDomainAccessible(clean_url($my_url));
    if (!$workingUrl) {
        handleErrorAndRedirect("The provided domain is not accessible or does not exist!", $lang['8']);
    }
    $my_url = $workingUrl;
    log_message('debug', "New domain accessible URL: $my_url");
} else {
    $my_url = $data['domain_access_url'];
    log_message('debug', "Using stored domain access URL: $my_url");
}

// Generate hash for caching.
$hashCode = md5($my_url_host);
$filename = TEMP_DIR . $hashCode . '.tdata';
log_message('debug', "Hash code generated: $hashCode");

// If update is needed, fetch and cache the HTML.
if ($updateFound) {
    $sourceData = fetchHtmlContent($my_url);
    if ($sourceData === false) {
        log_message('error', "Failed to fetch content for URL: {$my_url}");
        die("Failed to fetch content.");
    }
    putMyData($filename, $sourceData);
    log_message('info', "Content stored in temp file $filename for URL {$my_url}");
    
    // Extract meta information.
    $metaData = extractMetaData($sourceData);
    $title = $metaData['title'] ?? '';
    $description = $metaData['description'] ?? '';
    $keywords = $metaData['keywords'] ?? '';
    
    // Check for restricted words in meta.
    if (hasRestrictedWords($title, $description, $keywords, $restrictionList)) {
        log_message('error', "Domain {$domainStr} contains restricted words.");
        redirectTo(createLink('warning/restricted-words', true));
        die();
    }
    
    // For a new domain, create a record.
    if ($newDomain) {
        if (!isset($_SESSION['TWEB_HOMEPAGE'])) {
            $_SESSION['TWEB_HOMEPAGE'] = 1;
            log_message('debug', "New domain to add in DB: {$my_url}");
            redirectTo(createLink('', true));
            die();
        }
        $result = createDomainRecord($con, $domainStr, $my_url, $nowDate, $title, $description, $keywords);
        if ($result !== true) {
            log_message('error', "DB insert error: $result for URL {$my_url}");
            die($result);
        } else {
            log_message('info', "New domain record created for: $domainStr");
        }
    }
    
    // Instantiate SeoAnalyzer for further SEO analysis.
    require_once dirname(__DIR__) . '/SeoAnalyzer.php';
    $seo = new SeoAnalyzer();
    log_message('debug', "SeoAnalyzer instantiated for domain: $domainStr");
    // Further SEO processing goes here...
}

// Enforce free user limits (if applicable) or load stored data.
if (!isset($error)) {
    if ($updateFound) {
        if (!isset($_SESSION['twebAdminToken']) && !isset($_SESSION['twebUsername'])) {
            if (isset($_SESSION['TWEB_FREE_LIMIT'])) {
                $limitUsed = (int)$_SESSION['TWEB_FREE_LIMIT'];
                if ($limitUsed == $freeLimit) {
                    log_message('info', "User reached free limit for domain processing.");
                    redirectTo($updateUrl);
                    die();
                } else {
                    $_SESSION['TWEB_FREE_LIMIT'] = ++$limitUsed;
                }
            } else {
                $_SESSION['TWEB_FREE_LIMIT'] = 1;
            }
        }
    } else { 
        log_message('info', "Domain already processed. Loading DB data for {$domainStr}");
        define('DB_DOMAIN', true);
        require(CON_DIR . 'db-domain.php');
        $reviewerSettings['domain_data'] = dbStrToArr($reviewerSettings['domain_data']);
        $metaTitle = shortCodeFilter($reviewerSettings['domain_data']['domain']['title']);
        $des = shortCodeFilter($reviewerSettings['domain_data']['domain']['des']);
    }
} else {
    log_message('error', "Error in domain processing: " . $error);
    $_SESSION['TWEB_CALLBACK_ERR'] = $error;
    redirectTo(createLink('', true));
    die();
}
?>