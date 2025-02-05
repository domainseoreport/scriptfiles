<?php
defined('APP_NAME') or die(header('HTTP/1.1 403 Forbidden'));
define('TEMP_DIR',APP_DIR.'temp'.D_S);
    
/*
 * @author Balaji
 * @name: Rainbow PHP Framework
 * @copyright 2020 ProThemes.Biz
 *
 */

//POST REQUEST Handler
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['url'])){

        
        $myUrl = parse_url('http://'.clean_url(raino_trim($_POST['url'])));
    
        $myUrlHost = strtolower(str_replace('www.','',$myUrl['host']));
        redirectTo(createLink($controller.'/'.$myUrlHost,true));
        die();
    }else{
        die(trans('Input Site is not valid!',$lang['8'],true));
    }
}



//Check User Request
if ($pointOut == '')
    header('Location: '.$baseURL);
elseif(strpos($pointOut, '.') === false) 
    die(trans('Input Site is not valid!',$lang['8'],true));


//Default Value
$domainFound = $updateFound = $customSnapAPI = $newDomain = false;
$pdfUrl = $updateUrl = $shareLink = $pageTitle = $des = $keyword = $customSnapLink = '';
$isOnline = '0';
$nowDate = date('m/d/Y h:i:sA');
$disDate = date('F, j Y h:i:s A');

//True (or) False Image
$true = '<img src="'.themeLink('img/true.png',true).'" alt="'.trans('True',$lang['10'],true).'" />';
$false = '<img src="'.themeLink('img/false.png',true).'" alt="'.trans('False',$lang['9'],true).'" />';

if(isset($route[2])){
   $updateCheck = strtolower(raino_trim($route[2]));
   if($updateCheck == 'update')
       $updateFound = true;
}
 
//Get User Request
$my_url = raino_trim($pointOut);
 
$my_url = 'http://'.clean_url($my_url);


//Parse Host
$my_url_parse = parse_url($my_url);
$inputHost = $my_url_parse['scheme'] . "://" . $my_url_parse['host'];
$my_url_host = str_replace('www.','',$my_url_parse['host']);
$domainStr = escapeTrim($con, strtolower($my_url_host));
$pageTitle = $domainName = ucfirst($my_url_host);

//Check Empty Host
if($my_url_host == '')
    die(trans('Input Site is not valid!',$lang['8'],true));

//Get Domain Restriction
$list = getDomainRestriction($con);


//Check Banned Domains 
if(in_array($domainStr,$list[4])){
    redirectTo(createLink('warning/restricted-domains/'.$domainStr,true));
    die();
}

//Check Bad Words
foreach ($list[0] as $badWord){
    if(check_str_contains($domainStr, trim($badWord), true)) {
        redirectTo(createLink('warning/restricted-words',true));
        die();
    }
}

//Share Link
$shareLink = createLink($controller.'/'.$domainStr,true);

//////////////////// Link of the URL created here

//Load Reviewer Settings
$reviewerSettings = reviewerSettings($con);

$updateUrl = createLink($controller.'/'.$domainStr.'/'.'update',true);
 
$pdfUrl = createLink('genpdf/'.$domainStr,true);

//Login Access Check
if($enable_reg){
    if(!isset($_SESSION['twebUsername'])){
        if($updateFound){
          if(!isset($_SESSION['twebAdminToken'])){
              redirectTo(createLink('account/login',true));
              die();
          }
        }
        $username = trans('Guest',$lang['11'],true);
        $reviewerSettings['reviewer_list'] = unserialize($reviewerSettings['reviewer_list']);
        $freeLimit = (int)$reviewerSettings['free_limit'];
        $pdfUrl = $updateUrl = createLink('account/login',true);
    }else{
        $username = $_SESSION['twebUsername'];
    }
}

//Screenshot API
$reviewerSettings['snap_service'] = dbStrToArr($reviewerSettings['snap_service']);
if($reviewerSettings['snap_service']['options'] == 'prothemes_pro'){
    $customSnapAPI = true;
    $customSnapLink = $reviewerSettings['snap_service']['prothemes_pro'];
    $_SESSION['snap'] = $reviewerSettings['snap_service']['prothemes_pro'];
}elseif($reviewerSettings['snap_service']['options'] == 'custom'){
    $customSnapAPI = true;
    $customSnapLink = $reviewerSettings['snap_service']['custom'];
    $_SESSION['snap'] = $reviewerSettings['snap_service']['custom'];
}else{
    if(isset($_SESSION['snap']))
        unset($_SESSION['snap']);
}

//Check Domain Name Exists
$data = mysqliPreparedQuery($con, "SELECT * FROM domains_data WHERE domain=?",'s',array($domainStr));   //// Check domain exists or not if exists then show else fetch the data


if($data !== false){
    if($data['completed'] == 'yes'){
        $domainFound = true;
    }else{
        $updateFound = true;
        $domainFound = false;
    }
}else{
    $updateFound = true;
    $domainFound = false;
    $newDomain = true;
}

//Hash Code
$hashCode = md5($my_url_host);
$filename = TEMP_DIR.$hashCode.'.tdata';



//Get Data of the URL
if($updateFound){
    $my_url = isDomainAccessible(clean_url($my_url)); /// fetch the best URL for the operation 
  
    $capOkay = true;
    extract(loadCapthca($con));
    if(isSelected($reviewer_page)){
        if(!isset($_SESSION['twebReviewerFine'])){
            $capOkay = false;
            $_SESSION['twebReviewerDomain'] = $domainStr;
            redirectTo(createLink('check/verfication',true));
            die();
        }else{
            unset($_SESSION['twebReviewerFine']);
            unset($_SESSION['twebReviewerDomain']);
            $capOkay = true;
        }
    }
    
    if($capOkay){
       // $sourceData = urlcGET($my_url);
       $sourceData = robustFetchHtml($my_url); 

       if ($sourceData === false) {
            // Handle the error (e.g., log it or show an error message)
            echo "Failed to fetch content.";
        }  
     
        if($sourceData == ''){
            //Second try
            $sourceData = getMyData($my_url);
            
            if($sourceData == '')
                $error  = trans('Input Site is not valid!',$lang['8'],true);
        }


        
        // Assume $error is not set before.
        if (!isset($error)) {

            // Mark domain as online and store the raw HTML.
            $isOnline = '1';
            putMyData($filename, $sourceData);
        
            // Extract meta information
            $metaData = extractMetaData($sourceData);
            $title       = $metaData['title'] ?? '';
            $description = $metaData['description'] ?? '';
            $keywords    = $metaData['keywords'] ?? '';
        
            // Bad Word Filter - Block sites containing restricted words
            if (hasRestrictedWords($title, $description, $keywords, $list)) {
                redirectTo(createLink('warning/restricted-words', true));
                die();
            }
         
            // Create Domain Record if this is a new domain.
            if ($newDomain) {
                if (!isset($_SESSION['TWEB_HOMEPAGE'])) {
                    $_SESSION['TWEB_HOMEPAGE'] = 1;
                    redirectTo(createLink('', true));
                    die();
                }
        

                
                // Insert into database with meta information
                $result = createDomainRecord($con, $domainStr, $nowDate, $title, $description, $keywords);
     
                if ($result !== true) {
                    $error = $result;
                }
            }
        
            // At this point, you can instantiate your SeoAnalyzer to perform further operations.
            require_once dirname(__DIR__) . '/SeoAnalyzer.php'; // Adjust the path accordingly.
            $seo = new SeoAnalyzer();
            $analysisResult = $seo->analyze($domainStr);  // Pass the URL or domain string as needed.
        
            // Process the analysis result as desired...
        }

      
         
    }
    
}


 



if(!isset($error)){
    if($updateFound){
        //New or Update the data
        
        if(!isset($_SESSION['twebAdminToken'])){
            //Free Users
            if(!isset($_SESSION['twebUsername'])){
                if(isset($_SESSION['TWEB_FREE_LIMIT'])){
                    $limitUsed = (int)$_SESSION['TWEB_FREE_LIMIT'];
                    if($limitUsed == $freeLimit){
                        redirectTo($updateUrl);
                        die();
                    }else{
                        $limitUsed++;
                        $_SESSION['TWEB_FREE_LIMIT'] = $limitUsed;
                    }
                }else{
                    $_SESSION['TWEB_FREE_LIMIT'] = 1;
                }
            }
        }
        
    }else{
        //Extract DB Data
        define('DB_DOMAIN',true);
        require(CON_DIR.'db-domain.php');
        $reviewerSettings['domain_data'] = dbStrToArr($reviewerSettings['domain_data']);
        $metaTitle = shortCodeFilter($reviewerSettings['domain_data']['domain']['title']);
        $des = shortCodeFilter($reviewerSettings['domain_data']['domain']['des']);
    }
}else{
    $_SESSION['TWEB_CALLBACK_ERR'] = $error;
    redirectTo(createLink('',true));
    die();
}