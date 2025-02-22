<?php
defined('APP_NAME') or die(header('HTTP/1.1 403 Forbidden'));
define('TEMP_DIR', APP_DIR.'temp'.D_S);
  

/*
 * @author Balaji
 * @name: Rainbow PHP Framework
 * @copyright 2020 ProThemes.Biz
 *
 */

/// POST REQUEST Handler
// POST REQUEST Handler
// POST REQUEST Handler
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['url']) && trim($_POST['url']) != '') {
        $urlInput = raino_trim($_POST['url']);
        // Prepend "http://" so that a bare domain becomes a full URL.
        $fullUrl = 'http://' . clean_url($urlInput);
        // Validate the URL format.
        if (!filter_var($fullUrl, FILTER_VALIDATE_URL)) {
            // Set the error message in the session (so that the target page can display it)
            $_SESSION['TWEB_CALLBACK_ERR'] = trans('Input Site is not valid!', $lang['8'], true);
            log_message('debug', "Invalid domain format provided: " . $urlInput);
            redirectTo(createLink('', true));
            die();
        }
        
        // Parse the URL to get its components.
        $myUrl = parse_url($fullUrl);
        if (!isset($myUrl['host']) || empty($myUrl['host'])) {
            $_SESSION['TWEB_CALLBACK_ERR'] = trans('Input Site is not valid!', $lang['8'], true);
            log_message('debug', "Parsed domain is empty: " . $urlInput);
            redirectTo(createLink('', true));
            die();
        }
        
        // Remove the "www." prefix (if any) and force lowercase.
        $myUrlHost = strtolower(str_replace('www.', '', $myUrl['host']));
        // Redirect to the appropriate controller URL.
        redirectTo(createLink($controller . '/' . $myUrlHost, true));
        die();
    } else {
        // When no URL is provided, set a homepage flag and an error message.
        $_SESSION['TWEB_HOMEPAGE'] = 1;
        $_SESSION['TWEB_CALLBACK_ERR'] = trans('Input Site is not valid!', $lang['8'], true);
        log_message('debug', "Not Valid Domain provided: " . (isset($_POST['url']) ? $_POST['url'] : ''));
        redirectTo(createLink('', true));
        die();
    }
}

//Check User Request
if ($pointOut == '')
    header('Location: ' . $baseURL);
elseif (strpos($pointOut, '.') === false){
    $_SESSION['TWEB_HOMEPAGE'] = 1;
    log_message('debug', "Not Valid Domain: {$_POST['url']}");
    redirectTo(createLink('', true));
    die(trans('Input Site is not valid!', $lang['8'], true));
}
//Default Value
$domainFound   = $updateFound = $customSnapAPI = $newDomain = false;
$pdfUrl        = $updateUrl = $shareLink = $pageTitle = $des = $keyword = $customSnapLink = '';
$isOnline      = '0';
$nowDate       = date('m/d/Y h:i:sA');
$disDate       = date('F, j Y h:i:s A');

//True (or) False Image
$true  = '<img src="'.themeLink('img/true.png', true).'" alt="'.trans('True', $lang['10'], true).'" />';
$false = '<img src="'.themeLink('img/false.png', true).'" alt="'.trans('False', $lang['9'], true).'" />';

if (isset($route[2])) {
    $updateCheck = strtolower(raino_trim($route[2]));
    if ($updateCheck == 'update')
        $updateFound = true;
}

//Get User Request
$my_url = raino_trim($pointOut);
$my_url = 'http://' . clean_url($my_url);

//Parse Host
$my_url_parse = parse_url($my_url);
$inputHost    = $my_url_parse['scheme'] . "://" . $my_url_parse['host'];
$my_url_host  = str_replace('www.', '', $my_url_parse['host']);
$domainStr    = escapeTrim($con, strtolower($my_url_host));
$pageTitle    = $domainName = ucfirst($my_url_host);

 
//Check Empty Host
if ($my_url_host == '')
    die(trans('Input Site is not valid!', $lang['8'], true));

//Get Domain Restriction
$list = getDomainRestriction($con);

//Check Banned Domains 
if (in_array($domainStr, $list[4])) {
    redirectTo(createLink('warning/restricted-domains/' . $domainStr, true));
    die();
}

//Check Bad Words
foreach ($list[0] as $badWord) {
    if (check_str_contains($domainStr, trim($badWord), true)) {
        redirectTo(createLink('warning/restricted-words', true));
        die();
    }
}

//Share Link
$shareLink = createLink($controller . '/' . $domainStr, true);

//////////////////// Link of the URL created here

//Load Reviewer Settings
$reviewerSettings = reviewerSettings($con);

$updateUrl = createLink($controller . '/' . $domainStr . '/' . 'update', true);
$pdfUrl    = createLink('genpdf/' . $domainStr, true);

//Login Access Check
if ($enable_reg) {
    if (!isset($_SESSION['twebUsername'])) {
        if ($updateFound) {
            if (!isset($_SESSION['twebAdminToken'])) {
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
}

//Screenshot API
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

//Check Domain Name Exists
$data = mysqliPreparedQuery($con, "SELECT * FROM domains_data WHERE domain=?", 's', array($domainStr));
 

if ($data !== false) {
    if ($data['completed'] == 'yes') {
        $domainFound = true;
        
    } else {
        $updateFound   = true;
        $domainFound   = false;
    }
} else {
    $updateFound = true;
    $domainFound = false;
    $newDomain   = true;
}
if($newDomain){
    $my_url = isDomainAccessible(clean_url($my_url));
}else{
    $my_url = $data['domain_access_url']; 
} 
//Hash Code
$hashCode = md5($my_url_host);
$filename = TEMP_DIR . $hashCode . '.tdata';

//Get Data of the URL
if ($updateFound) {
    // Attempt to get the best accessible URL.
   
   
    if (!$my_url) {
        $error = trans('The provided domain is not accessible or does not exist!', $lang['8'], true);
        log_message('debug', "Domain not accessible: " . clean_url($my_url));
        
        $_SESSION['TWEB_CALLBACK_ERR'] = $error;
        redirectTo(createLink('', true));
        die();
    }
     

    $capOkay = true;
    extract(loadCapthca($con));
    if (isSelected($reviewer_page)) {
        if (!isset($_SESSION['twebReviewerFine'])) {
            $capOkay = false;
            $_SESSION['twebReviewerDomain'] = $domainStr;
            redirectTo(createLink('check/verfication', true));
            die();
        } else {
            unset($_SESSION['twebReviewerFine']);
            unset($_SESSION['twebReviewerDomain']);
            $capOkay = true;
        }
    }
    
    if ($capOkay) {
        // Fetch the source data using robustFetchHtml.
        $sourceData = robustFetchHtml($my_url);
        if ($sourceData === false) {
            echo "Failed to fetch content.";
            log_message('debug', "Failed to fetch content for URL: {$my_url}");
            die();
        }
        
        if ($sourceData == '') {
            // Second try
            $sourceData = getMyData($my_url);
            if ($sourceData == '')
                $error = trans('Input Site is not valid!', $lang['8'], true);
        }
        
        if (!isset($error)) {
            // Mark domain as online and store the raw HTML.
            $isOnline = '1';
            putMyData($filename, $sourceData);
            log_message('debug', "Content Stored in Temp File $filename for URL {$my_url}");
            
            // Extract meta information
            $metaData    = extractMetaData($sourceData);
            $title       = isset($metaData['title']) ? $metaData['title'] : '';
            $description = isset($metaData['description']) ? $metaData['description'] : '';
            $keywords    = isset($metaData['keywords']) ? $metaData['keywords'] : '';
            
            // Bad Word Filter - Block sites containing restricted words
            if (hasRestrictedWords($title, $description, $keywords, $list)) {
                redirectTo(createLink('warning/restricted-words', true));
                die();
            }
            
            // Create Domain Record if this is a new domain.
            if ($newDomain) {
                if (!isset($_SESSION['TWEB_HOMEPAGE'])) {
                    $_SESSION['TWEB_HOMEPAGE'] = 1;
                    log_message('debug', "New Domain to add in DB: {$my_url}");
                    redirectTo(createLink('', true));
                    die();
                }
            
                $result = createDomainRecord($con, $domainStr,$my_url,$nowDate, $title, $description, $keywords);
                
                if ($result !== true) {
                    log_message('debug', "Not able to insert domain in DB: $result for URL {$my_url}");
                    $error = $result;
                }
            }
            
            // At this point, you can instantiate your SeoAnalyzer to perform further analysis.
            require_once dirname(__DIR__) . '/SeoAnalyzer.php';
            $seo = new SeoAnalyzer();
            //$analysisResult = $seo->analyze($domainStr);  // Pass the URL or domain string as needed.
            
            // Further processing of $analysisResult as desired...
        }
    }
}



if (!isset($error)) {
    if ($updateFound) {
        //New or Update the data
        
        if (!isset($_SESSION['twebAdminToken'])) {
            //Free Users 
            if (!isset($_SESSION['twebUsername'])) {
                if (isset($_SESSION['TWEB_FREE_LIMIT'])) {
                    $limitUsed = (int)$_SESSION['TWEB_FREE_LIMIT'];
                    if ($limitUsed == $freeLimit) {
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
        log_message('debug', "In Else $error");
        //Extract DB Data
        define('DB_DOMAIN', true); 
        require(CON_DIR.'db-domain.php');  
        $reviewerSettings['domain_data'] = dbStrToArr($reviewerSettings['domain_data']); 
        $metaTitle = shortCodeFilter($reviewerSettings['domain_data']['domain']['title']);
        $des = shortCodeFilter($reviewerSettings['domain_data']['domain']['des']);
    }
} else {
    log_message('debug', "In Domain Else $error");
    $_SESSION['TWEB_CALLBACK_ERR'] = $error;
    redirectTo(createLink('', true));
    die();
}
?>
