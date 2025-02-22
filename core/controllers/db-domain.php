<?php

defined('DB_DOMAIN') or die(header('HTTP/1.1 403 Forbidden'));

require_once(LIB_DIR . 'SeoTools.php');
 

// echo "<pre>";
// print_r($_SESSION);
// echo "<pre>";
// die();
/*
 * @author Balaji
 * @name Turbo Website Reviewer - PHP Script
 * @copyright 2023 ProThemes.Biz
 *
 */

//Login Box
$seoBoxLogin = '<div class="lowImpactBox">
<div class="msgBox">   
        '.$lang['39'].'
        
    <br /><br /> <div class="altImgGroup"> <a class="btn btn-success forceLogin" target="_blank" href="'.createLink('account/login',true).'" title="'.$lang['40'].'"> '.$lang['40'].' </a></div><br />
</div>
</div>';
$seoTools = new SeoTools($dummyHtml, $con, $domainStr, $lang, $urlParse, $sepUnique, $seoBoxLogin);   
//Meta Data   

$savedMetaJson = $data['meta_data'] ?? '';
$meta_data = jsonDecode($savedMetaJson);

if ($savedMetaJson) {
    $metaHtml = $seoTools->showMeta($savedMetaJson);
    $metaParts = explode($seoTools->getSeparator(), $metaHtml);
}
 


 



 
  
 
 
$seoBox5 = '<div class="'.$classKey.'">
    <div class="msgBox">
        <div class="googlePreview">
        
            <!-- First Row: Mobile & Tablet Views -->
            <div class="row">
                <div class="col-md-6">
                    <div class="google-preview-box mobile-preview">
                        <h6>Mobile View</h6>
                        <p class="google-title"><a href="#">'.$meta_data["raw"]["title"].'</a></p>
                        <p class="google-url"><span class="bold">'.$my_url_parse['host'].'</span>/</p>
                        <p class="google-desc">'.$meta_data["raw"]["description"].'</p>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="google-preview-box tablet-preview">
                        <h6>Tablet View</h6>
                        <p class="google-title"><a href="#">'.$meta_data["raw"]["title"].'</a></p>
                        <p class="google-url"><span class="bold">'.$my_url_parse['host'].'</span>/</p>
                        <p class="google-desc">'.$meta_data["raw"]["description"].'</p>
                    </div>
                </div>
            </div>

            <!-- Second Row: Desktop View -->
            <div class="row mt-3">
                <div class="col-12 ">
                    <div class="google-preview-box desktop-preview mt-5">
                        <h6>Desktop View</h6>
                        <p class="google-title"><a href="#">'.$meta_data["raw"]["title"].'</a></p>
                        <p class="google-url"><span class="bold">'.$my_url_parse['host'].'</span>/</p>
                        <p class="google-desc">'.$meta_data["raw"]["description"].'</p>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>';



// Decode your headings data 
$headings = $data['headings']; 
$seoBox4 = $seoTools->showHeading($headings);;

$imageData =  $data['image_alt'] ;
$seoBox6  = $seoTools->showImage($imageData);;

$savedKeywordsJson = $data['keywords_cloud'] ?? '';

if ($savedKeywordsJson) {
    // Use the new showKeyCloudAndConsistency() method to get the full HTML output.
    $seoBox8 = $seoTools->showKeyCloudAndConsistency($savedKeywordsJson);
} else {
    $seoBox8 = '<div class="alert alert-warning">No keyword cloud data available.</div>';
}
 
//Text to HTML Ratio
$textRatio =  $data['ratio_data'];  
$seoBox9 = $seoTools->showTextRatio($textRatio);


//Site Card
$sitecards = $data['sitecards'];  
$seoBox51 = $seoTools->showCards($sitecards); 

//Site Card
$socialURLs = $data['social_urls'];  
$seoBox52 = $seoTools->showSocialUrls($socialURLs);

//Page Analysis Report
$page_analytics = $data['page_analytics'];  
$seoBox54 = $seoTools->showPageAnalytics($page_analytics);

$showPageSpeedInsight = $data['page_speed_insight'];  
if (is_array($showPageSpeedInsight)) {
    $showPageSpeedInsight = json_encode($showPageSpeedInsight);
}
$seoBox55 = $seoTools->showPageSpeedInsightConcurrent($showPageSpeedInsight);

//Check GZIP Compression  
// $gzipData =  $data['gzip']; 
// $seoBox10 =  $seoTools->showGzip($gzipData); 


//WWW Resolve
$resolveData = $data['resolve'];  
$seoBox11 = $seoTools->showWWWResolve($resolveData); 

// //IP Canonicalization
// $ipData = $data['ip_can'];  
// $seoBox12 = $seoTools->showIPCanonicalization($ipData);


 
$links_analyser = $data['links_analyser'];   
$seoBox13 = $seoTools->showInPageLinks($links_analyser);

$seoBox17 = '<div class="'.$urlRewritingClass.'">
<div class="msgBox">       
     '.$urlRewritingMsg.'
    <br />
    <br />
</div>
<div class="seoBox17 suggestionBox">
'.$url_RewritingMsg.'
</div> 
</div>';

$seoBox18 = '<div class="'.$linkUnderScoreClass.'">
<div class="msgBox">       
     '.$linkUnderScoreMsg.'
    <br />
    <br />
</div>
<div class="seoBox18 suggestionBox">
'.$link_UnderScoreMsg.'
</div> 
</div>';


//Broken Links
$bLinks = decSerBase($data['broken_links']);
$broken_Msg = $lang['AN186'];
$hideMe = $brokenMsg = $brokenClass = $brokenLinks = '';
$totalDataCount = 0;

foreach($bLinks as $bLink){
    
    if($totalDataCount == 3)
        $hideMe = 'hideTr hideTr5';
    $brokenLinks.= '<tr class="'.$hideMe.'"><td>'.$bLink.'</td></tr>';
    $totalDataCount++;
}

if($totalDataCount == 0){
    $brokenClass = 'passedBox';
    $brokenMsg = $lang['AN68'];
}else{
    $brokenClass = 'errorBox';
    $brokenMsg = $lang['AN69'];
}
    
$seoBox14 = '<div class="'.$brokenClass.'">
<div class="msgBox">       
     '.$brokenMsg.'
    <br /><br />
    
    '.(($totalDataCount != 0)? '
    <table class="table table-responsive">
        <tbody>
            '.$brokenLinks.'
	   </tbody>
    </table>' : '').'
            
    '.(($totalDataCount > 3)? '
        <div class="showLinks showLinks5">
            <a class="showMore showMore5">'.$lang['AN18'].' <br /> <i class="fa fa-angle-double-down"></i></a>
            <a class="showLess showLess5"><i class="fa fa-angle-double-up"></i> <br /> '.$lang['AN19'].'</a>
    </div>' : '').'
    
</div>
<div class="seoBox14 suggestionBox">
'.$broken_Msg.'
</div> 
</div>';


  
 
//WHOIS Data
$class = 'lowImpactBox';
$whoisData = $hideMe = '';
$totalDataCount = 0;
$domainAgeMsg = $lang['AN193'];
$whoisDataMsg = $lang['AN194'];

$whois_data = decSerBase($data['whois']);
$whoisRaw = $whois_data[0];
$domainAge = $whois_data[1];
$createdDate = $whois_data[2];
$updatedDate = $whois_data[3];
$expiredDate = $whois_data[4];

$myLines = preg_split("/\r\n|\n|\r/", $whoisRaw);
foreach($myLines as $line){
    if(!empty($line)){
    if($totalDataCount == 5)
        $hideMe = 'hideTr hideTr6';
    $whoisData.='<tr class="'.$hideMe.'"><td>'.$line.'</td></tr>';
    $totalDataCount++;
    }
}

$seoBox21 = '<div class="'.$class.'">
<div class="msgBox">       
     '.$lang['AN85'].'
    <br /><br />
    <div class="altImgGroup">
        <p><i class="fa fa-paw solveMsgGreen"></i> '.$lang['AN86'].': '.$domainAge.'</p>
        <p><i class="fa fa-paw solveMsgGreen"></i> '.$lang['AN87'].': '.$createdDate.'</p>
        <p><i class="fa fa-paw solveMsgGreen"></i> '.$lang['AN88'].': '.$updatedDate.'</p>
        <p><i class="fa fa-paw solveMsgGreen"></i> '.$lang['AN89'].': '.$expiredDate.'</p>
    </div>
</div>
<div class="seoBox21 suggestionBox">
'.$domainAgeMsg.'
</div> 
</div>';


$seoBox22 = '<div class="'.$class.'">
<div class="msgBox">       
     '.$lang['AN84'].'
    <br /><br />

    '.(($totalDataCount != 0)? '
    <table class="table table-hover table-bordered table-striped">
        <tbody>
            '.$whoisData.'
        </tbody>
    </table>' : $lang['AN90']).'
            
    '.(($totalDataCount > 5)? '
        <div class="showLinks showLinks6">
            <a class="showMore showMore6">'.$lang['AN18'].' <br /> <i class="fa fa-angle-double-down"></i></a>
            <a class="showLess showLess6"><i class="fa fa-angle-double-up"></i> <br /> '.$lang['AN19'].'</a>
    </div>' : '').'
    
</div>
<div class="seoBox22 suggestionBox">
'.$whoisDataMsg.'
</div> 
</div>';
 

 


 

//Domain & Typo Availability Checker
$domain_Msg = $lang['AN204'];
$typo_Msg = $lang['AN205'] ;
$typoMsg = $domainMsg = '';
$typoClass = $domainClass = 'lowImpactBox';

$domain_typo = decSerBase($data['domain_typo']);
$doArr = $domain_typo[0];
$tyArr = $domain_typo[1];

foreach($doArr as $doStr){
    
    //Get the status of domain name
    $topDomain = $doStr[0];
    $domainAvailabilityStats = $doStr[1];
    
    //Response Code - Reason
    //2 - Domain is already taken!
    //3 - Domain is available
    //4 - No WHOIS entry was found for that TLD
    //5 - WHOIS Query failed
    if($domainAvailabilityStats=='2')
        $domainStatsMsg = $lang['AN132'];
    elseif($domainAvailabilityStats=='3')
        $domainStatsMsg = $lang['AN131'];
    else
         $domainStatsMsg = $lang['AN133'];
    
   $domainMsg.= '<tr> <td>'.$topDomain.'</td> <td>'.$domainStatsMsg.'</td> </tr>';
}

foreach($tyArr as $tyStr){
    
    //Get the status of domain name
    $topDomain = $tyStr[0];
    $domainAvailabilityStats = $tyStr[1];
    
    //Response Code - Reason
    //2 - Domain is already taken!
    //3 - Domain is available
    //4 - No WHOIS entry was found for that TLD
    //5 - WHOIS Query failed
    if($domainAvailabilityStats=='2')
        $domainStatsMsg = $lang['AN132'];
    elseif($domainAvailabilityStats=='3')
        $domainStatsMsg = $lang['AN131'];
    else
         $domainStatsMsg = $lang['AN133'];
    
   $typoMsg.= '<tr> <td>'.$topDomain.'</td> <td>'.$domainStatsMsg.'</td> </tr>';
}
 
  
//Server Location Information
$server_loc = $data['server_loc']; 
            
$seoBox36 = $seoTools->showServerInfo($server_loc);

 
//Schema Data
 
$schemadata = $data['schema_data'];    
$seoBox44 = $seoTools->showSchema($schemadata);

//Visitors Localization
$visitors_Msg = $lang['AN219'];
$visitorsMsg = '';
$visitorsClass = 'lowImpactBox';

$alexaDatas = array();
$alexaDatas[] = array('', 'Popularity at', $alexa[1]);
$alexaDatas[] = array('', 'Regional Rank', $alexa[2]);

$alexaDataCount = count($alexaDatas);

foreach($alexaDatas as $alexaData)
    $visitorsMsg.='<tr><td>'.$alexaData[1].'</td><td>'.$alexaData[2].'</td><tr>';

$seoBox47 = '<div class="'.$visitorsClass.'">
<div class="msgBox">   
    '.$lang['AN171'] .'<br /><br />
    '.(($alexaDataCount != 0)? '
    <table class="table table-hover table-bordered table-striped">
        <tbody>
            '.$visitorsMsg.'
        </tbody>
    </table>' : $lang['AN170']).'
    <br />
</div>
<div class="seoBox47 suggestionBox">
'.$visitors_Msg.'
</div> 
</div>';

 

//Get Final Score Data
$score = json_decode(($data['score']), true); 

$passScore = $score["passed"];
$improveScore = $score["improve"];
$errorScore = $score["errors"];
$overallPercent = $score["percent"];


//Get the data
$date_raw = date_create(Trim($data['date']));
$disDate = date_format($date_raw,"F, j Y h:i:s A");

//Login Access Check
if(!isset($_SESSION['twebUsername'])){
    if($enable_reg){
        foreach($reviewerSettings['reviewer_list'] as $reviewer)
            ${$reviewer} = $seoBoxLogin;
    }
}