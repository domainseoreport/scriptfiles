<?php
// domain.php
defined('APP_NAME') or die(header('HTTP/1.1 403 Forbidden'));
define('TEMP_DIR', APP_DIR . 'temp' . D_S);



/* ------------------------- Main POST Handler ------------------------- */
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['url']) || trim($_POST['url']) == '') {
        handleErrorAndRedirect("No URL provided in POST request.", $lang['8']);
    }
    // Process the input URL.
    $urlData = processUrlInput($_POST['url']);
    if ($urlData === false) {
        handleErrorAndRedirect("Invalid URL format provided: " . $_POST['url'], $lang['8']);
    }
    
    // Check if domain exists in DB.
    $domainRecord = getDomainByHost($con, $urlData['host']);
    if ($domainRecord && !empty($domainRecord['slug'])) {
         // Redirect to the subdomain URL.
         $subdomainUrl = "http://" . $domainRecord['slug'] . ".localhost";
         redirectTo($subdomainUrl);
    } else {
         // Fallback behavior.
         redirectTo(createLink($controller . '/' . $urlData['host'], true));
    }
    die();
}

/* ------------------------- Router-based Domain Processing ------------------------- */
// Validate $pointOut from the router.
if (empty($pointOut) || strpos($pointOut, '.') === false) {
    header('Location: ' . $baseURL);
    log_message('info', "No valid domain provided; redirecting to base URL.");
    die(trans('Input Site is not valid!', $lang['8'], true));
}

// Initialize variables.
$domainFound   = $updateFound = $customSnapAPI = $newDomain = false;
$pdfUrl        = $updateUrl = $shareLink = $pageTitle = $des = $keyword = $customSnapLink = '';
$isOnline      = '0';
$nowDate       = date('m/d/Y h:i:sA');
$disDate       = date('F, j Y h:i:s A');

$trueIcon  = '<img src="' . themeLink('img/true.png', true) . '" alt="' . trans('True', $lang['10'], true) . '" />';
$falseIcon = '<img src="' . themeLink('img/false.png', true) . '" alt="' . trans('False', $lang['9'], true) . '" />';

// Check for update flag (e.g. if URL is /update)
// Check for update flag (e.g. if URL is /update)
if (defined('SUBDOMAIN_ROUTE')) {
    if (isset($route[0]) && strtolower(raino_trim($route[0])) == 'update') {
        $updateFound = true;
        log_message('debug', "Update flag set (subdomain route).");
        // Only remove the flag if there are more elements (if desired)
        if (count($route) > 1) {
            array_shift($route);
        }
    }
} else {
    if (isset($route[2]) && strtolower(raino_trim($route[2])) == 'update') {
        $updateFound = true;
        log_message('debug', "Update flag set (normal routing).");
    }
}

// Setup the domain URL.
$my_url = 'http://' . clean_url(raino_trim($pointOut));
log_message('debug', "Processing URL: $my_url");
$my_url_parse = parse_url($my_url);
if (empty($my_url_parse['host'])) {
    handleErrorAndRedirect("Empty host after parsing URL: $my_url", $lang['8']);
}
$inputHost   = $my_url_parse['scheme'] . "://" . $my_url_parse['host'];
$my_url_host = str_replace('www.', '', $my_url_parse['host']);
$domainStr   = escapeTrim($con, strtolower($my_url_host));
$pageTitle   = $domainName = ucfirst($my_url_host);

// Enforce domain restrictions.
$restrictionList = getDomainRestriction($con);
log_message('debug', "Domain restrictions loaded.");
enforceDomainRestrictions($domainStr, $restrictionList);

// Set share link and load reviewer settings.
$shareLink = createLink($controller . '/' . $domainStr, true);
$reviewerSettings = reviewerSettings($con);
log_message('debug', "Reviewer settings loaded for $domainStr");

// For subdomains, force update URL to be simply "/update"
if (defined('SUBDOMAIN_ROUTE')) {
    $updateUrl = createLink('update', true);
} else {
    $updateUrl = createLink($controller . '/' . $domainStr . '/update', true);
}
$pdfUrl = createLink('genpdf/' . $domainStr, true);

// (Login check code omitted here if not required for updates)

// Setup Screenshot API.
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

// Check if the domain exists in DB.
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
    $newDomain = true;
    log_message('debug', "New domain: $domainStr");
}

// Determine the working URL.
if ($newDomain) {
    $workingUrl = isDomainAccessible(clean_url($my_url));
    if (!$workingUrl) {
        handleErrorAndRedirect("The provided domain is not accessible!", $lang['8']);
    }
    $my_url = $workingUrl;
    log_message('debug', "New domain accessible URL: $my_url");
} else {
    $my_url = $data['domain_access_url'];
    log_message('debug', "Using stored URL: $my_url");
}

// Generate hash for caching.
$hashCode = md5($my_url_host);
$filename = TEMP_DIR . $hashCode . '.tdata';
log_message('debug', "Generated hash: $hashCode");

// If update is needed, fetch content.
if ($updateFound) {
    $sourceData = fetchHtmlContent($my_url);
    if ($sourceData === false) {
        log_message('error', "Failed to fetch content for $my_url");
        die("Failed to fetch content.");
    }
    putMyData($filename, $sourceData);
    log_message('info', "Content stored in $filename");
    
    // Extract meta info.
    $metaData = extractMetaData($sourceData);
    $title = $metaData['title'] ?? '';
    $description = $metaData['description'] ?? '';
    $keywords = $metaData['keywords'] ?? '';
    
    if (hasRestrictedWords($title, $description, $keywords, $restrictionList)) {
        log_message('error', "Domain $domainStr has restricted words.");
        redirectTo(createLink('warning/restricted-words', true));
        die();
    }
    
    if ($newDomain) {
        if (!isset($_SESSION['TWEB_HOMEPAGE'])) {
            $_SESSION['TWEB_HOMEPAGE'] = 1;
            log_message('debug', "New domain to add: $my_url");
            // Continue processing instead of an immediate redirect.
        }
        
        $result = createDomainRecord($con, $domainStr, $my_url, $nowDate, $title, $description, $keywords);
      
        if ($result !== true) {
            log_message('error', "DB insert error: $result for $my_url");
            die($result);
        } else {
            log_message('info', "New domain record created for: $domainStr");
            // Check if the slug exists in the returned record or generate one.
            if (isset($domainRecord['slug']) && !empty($domainRecord['slug'])) {
                $subdomainSlug = $domainRecord['slug'];
            } else {
                // Fallback: define createSlug if not already defined.
                if (!function_exists('createSlug')) {
                    function createSlug($domain) {
                        // Simple slug: convert to lowercase and replace dots with dashes.
                        return str_replace('.', '-', strtolower($domain));
                    }
                }
                $subdomainSlug = createSlug($domainStr);
            }
            $subdomainUrl = "http://" . $subdomainSlug . ".localhost";
            redirectTo($subdomainUrl);
            die();
        }
    }
    
    // Instantiate SeoAnalyzer.
    require_once dirname(__DIR__) . '/SeoAnalyzer.php';
    $seo = new SeoAnalyzer();
    log_message('debug', "SeoAnalyzer instantiated for $domainStr");
}

if (!defined('SUBDOMAIN_ROUTE')) {
    // We are on the main domain (e.g. http://localhost/domain/…)
    $domainRecord = getDomainByHost($con, $domainStr);
    if ($domainRecord && !empty($domainRecord['slug'])) {
        // Redirect to the subdomain URL (e.g. http://silverandgem-com.localhost)
        $subdomainUrl = "http://" . $domainRecord['slug'] . ".localhost";
        redirectTo($subdomainUrl);
        die();
    }
}

if (!isset($error)) {
    if ($updateFound) {
        // (Optional: Add user-limit check code if needed)
    } else { 
        log_message('info', "Loading DB data for $domainStr");
        define('DB_DOMAIN', true);
        require(CON_DIR . 'db-domain.php');
        $reviewerSettings['domain_data'] = dbStrToArr($reviewerSettings['domain_data']);
        $metaTitle = shortCodeFilter($reviewerSettings['domain_data']['domain']['title']);
        $des = shortCodeFilter($reviewerSettings['domain_data']['domain']['des']);
    }
} else {
    log_message('error', "Domain processing error: " . $error);
    $_SESSION['TWEB_CALLBACK_ERR'] = $error;
    redirectTo(createLink('', true));
    die();
}
?>
