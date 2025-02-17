<?php

defined('DB_DOMAIN') or die(header('HTTP/1.1 403 Forbidden'));

require_once(LIB_DIR . 'SeoTools.php');

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
$meta_data = jsonDecode($data['meta_data']);
// echo "<pre>";
// print_r($meta_data);
// echo "</pre>";
 
$metatitle = $meta_data["title"];
$metadescription = $meta_data["description"];
$metakeywords = $meta_data["keywords"];

$lenTitle = mb_strlen($metatitle,'utf8');
$lenDes = mb_strlen($metadescription,'utf8');

//Check Empty Data
$site_title = ($metatitle == '' ? $lang['AN11'] : $metatitle);
$site_description = ($metadescription == '' ? $lang['AN12'] : $metadescription);
$site_keywords = ($metakeywords == '' ? $lang['AN15'] : $metakeywords);

$titleMsg = $lang['AN173'];
$desMsg = $lang['AN174'];
$keyMsg = $lang['AN175'];
$googleMsg = $lang['AN177'];

if($lenTitle < 10)
    $classTitle = 'improveBox';
elseif($lenTitle < 70)
    $classTitle = 'passedBox';
else
    $classTitle = 'errorBox';

if($lenDes < 70)
    $classDes = 'improveBox';
elseif($lenDes < 300)
    $classDes = 'passedBox';
else
    $classDes = 'errorBox';
    
$classKey = 'lowImpactBox';


$seoBox1 = '<div class="'.$classTitle.'">
<div class="msgBox bottom10">       
'.$site_title.'
<br />
<b>'.$lang['AN13'].':</b> '.$lenTitle.' '.$lang['AN14'].' 
</div>
<div class="seoBox1 suggestionBox">
'.$titleMsg.'
</div> 
</div>';


$seoBox2 = '<div class="'.$classDes.'">
<div class="msgBox padRight10 bottom10">       
'.$site_description.'
<br />
<b>'.$lang['AN13'].':</b> '.$lenDes.' '.$lang['AN14'].' 
</div>
<div class="seoBox2 suggestionBox">
'.$desMsg.'
</div> 
</div>';

$seoBox3 = '<div class="'.$classKey.'">
<div class="msgBox padRight10">       
'.$site_keywords.'
<br /><br />
</div>
<div class="seoBox3 suggestionBox">
'.$keyMsg.'
</div> 
</div>';



 
  
 
 
$seoBox5 = '<div class="'.$classKey.'">
    <div class="msgBox">
        <div class="googlePreview">
        
            <!-- First Row: Mobile & Tablet Views -->
            <div class="row">
                <div class="col-md-6">
                    <div class="google-preview-box mobile-preview">
                        <h6>Mobile View</h6>
                        <p class="google-title"><a href="#">'.$site_title.'</a></p>
                        <p class="google-url"><span class="bold">'.$my_url_parse['host'].'</span>/</p>
                        <p class="google-desc">'.$site_description.'</p>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="google-preview-box tablet-preview">
                        <h6>Tablet View</h6>
                        <p class="google-title"><a href="#">'.$site_title.'</a></p>
                        <p class="google-url"><span class="bold">'.$my_url_parse['host'].'</span>/</p>
                        <p class="google-desc">'.$site_description.'</p>
                    </div>
                </div>
            </div>

            <!-- Second Row: Desktop View -->
            <div class="row mt-3">
                <div class="col-12 ">
                    <div class="google-preview-box desktop-preview mt-5">
                        <h6>Desktop View</h6>
                        <p class="google-title"><a href="#">'.$site_title.'</a></p>
                        <p class="google-url"><span class="bold">'.$my_url_parse['host'].'</span>/</p>
                        <p class="google-desc">'.$site_description.'</p>
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

//Keyword Cloud
$keywords_cloud = jsonDecode($data['keywords_cloud']); 
$seoBox7 = $seoTools->showKeyCloud($keywords_cloud);

  
$fullCloud = $keywords_cloud['fullCloud'] ?? [];
$seoBox8 = $seoTools->showKeyConsistencyNgramsTabs($fullCloud, $meta_data, $headings[0]);;
 
//Text to HTML Ratio
$textRatio =  $data['ratio_data'];  
$seoBox9 = $seoTools->showTextRatio($textRatio);

//Check GZIP Compression  
$gzipData =  $data['gzip']; 
$seoBox10 =  $seoTools->showGzip($gzipData); 


//WWW Resolve
$resolveData = $data['resolve'];  
$seoBox11 = $seoTools->showWWWResolve($resolveData); 

//IP Canonicalization
$ipData = $data['ip_can'];  
$seoBox12 = $seoTools->showIPCanonicalization($ipData);


 
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


//Robots.txt Checker
$robot_Msg = $lang['AN187'];
$robotLink = $robotMsg = $robotClass = '';

$robotLink = $inputHost .'/robots.txt';
$httpCode = base64_decode($data['robots']);

if($httpCode == '404'){
    $robotClass = 'errorBox';
    $robotMsg = $lang['AN74'] . '<br>' . '<a href="'.$robotLink.'" title="'.$lang['AN75'].'" rel="nofollow" target="_blank">'.$robotLink.'</a>';
}else{
    $robotClass = 'passedBox';
    $robotMsg = $lang['AN73'] . '<br>' . '<a href="'.$robotLink.'" title="'.$lang['AN75'].'" rel="nofollow" target="_blank">'.$robotLink.'</a>';
}

$seoBox16 = '<div class="'.$robotClass.'">
<div class="msgBox">       
     '.$robotMsg.'
    <br /><br />
</div>
<div class="seoBox16 suggestionBox">
'.$robot_Msg.'
</div> 
</div>';


//Sitemap Checker
$sitemap_Msg = $lang['AN188'];
$sitemapLink = $sitemapMsg = $sitemapClass = '';

$sitemapLink = $inputHost .'/sitemap.xml';
$httpCode = base64_decode($data['sitemap']);

if($httpCode == '404'){
    $sitemapClass = 'errorBox';
    $sitemapMsg = $lang['AN71'] . '<br>' . '<a href="'.$sitemapLink.'" title="'.$lang['AN72'].'" rel="nofollow" target="_blank">'.$sitemapLink.'</a>';
}else{
    $sitemapClass = 'passedBox';
    $sitemapMsg = $lang['AN70'] . '<br>' . '<a href="'.$sitemapLink.'" title="'.$lang['AN72'].'" rel="nofollow" target="_blank">'.$sitemapLink.'</a>';
}

$seoBox15 = '<div class="'.$sitemapClass.'">
<div class="msgBox">       
     '.$sitemapMsg.'
    <br /><br />
</div>
<div class="seoBox15 suggestionBox">
'.$sitemap_Msg.'
</div> 
</div>';

//Embedded Object Check
$embedded_Msg = $lang['AN191'];
$embeddedMsg = $embeddedClass = '';
$embeddedCheck = false;

$embeddedCheck = filter_var($data['embedded'], FILTER_VALIDATE_BOOLEAN);

if($embeddedCheck){
    $embeddedClass = 'errorBox';
    $embeddedMsg = $lang['AN78'];
}else{
    $embeddedClass = 'passedBox';
    $embeddedMsg = $lang['AN77'];
}

$seoBox19 = '<div class="'.$embeddedClass.'">
<div class="msgBox">       
     '.$embeddedMsg.'
    <br /><br />
</div>
<div class="seoBox19 suggestionBox">
'.$embedded_Msg.'
</div> 
</div>';

//iframe Check
$iframe_Msg = $lang['AN192'];
$iframeMsg = $iframeClass = '';
$iframeCheck = false;
    
$iframeCheck = filter_var($data['iframe'], FILTER_VALIDATE_BOOLEAN);

if($iframeCheck){
    $iframeClass = 'errorBox';
    $iframeMsg = $lang['AN80'];
}else{
    $iframeClass = 'passedBox';
    $iframeMsg = $lang['AN79'];
}

$seoBox20 = '<div class="'.$iframeClass.'">
<div class="msgBox">       
     '.$iframeMsg.'
    <br /><br />
</div>
<div class="seoBox20 suggestionBox">
'.$iframe_Msg.'
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

//Mobile Friendliness
$isMobileFriendlyMsg = '';
$mobileClass = $mobileScreenClass = 'lowImpactBox';

$mobileCheckMsg = $lang['AN195'];
$mobileScreenClassMsg = $lang['AN196'];;

$mobData = decSerBase($data['mobile_fri']);
$mobileScore = $mobData[0];
$isMobileFriendly = filter_var($mobData[1], FILTER_VALIDATE_BOOLEAN);

if($isMobileFriendly){
    $mobileClass = 'passedBox';
    $isMobileFriendlyMsg.=$lang['AN116'].'<br>'.str_replace('[score]',$mobileScore,$lang['AN117']);
}else{
    $mobileClass = 'errorBox';
    $isMobileFriendlyMsg.=$lang['AN118'].'<br>'.str_replace('[score]',$mobileScore,$lang['AN117']);
}

$screenData = getMobilePreview($domainStr);
if($screenData == '')
    $mobileScreenData = '';
else
    $mobileScreenData  = '<img src="data:image/jpeg;base64,'.$screenData.'" />';

$seoBox23 = '<div class="'.$mobileClass.'">
<div class="msgBox">       
    '.$isMobileFriendlyMsg.'
    <br /><br />
</div>
<div class="seoBox23 suggestionBox">
'.$mobileCheckMsg.'
</div> 
</div>';

$seoBox24 = '<div class="'.$mobileScreenClass.'">
<div class="msgBox">       
    <div class="mobileView">'.$mobileScreenData.'</div>
    <br />
</div>
<div class="seoBox24 suggestionBox">
'.$mobileScreenClassMsg.'
</div> 
</div>';


//Mobile Compatibility  
$mobileCom_Msg = $lang['AN197'];
$mobileComMsg = $mobileComClass = '';
$mobileComCheck = false;
    
$mobileComCheck = filter_var($data['mobile_com'], FILTER_VALIDATE_BOOLEAN);

if($mobileComCheck){
    $mobileComClass = 'errorBox';
    $mobileComMsg = $lang['AN121'];
}else{
    $mobileComClass = 'passedBox';
    $mobileComMsg = $lang['AN120'];
}

$seoBox25 = '<div class="'.$mobileComClass.'">
<div class="msgBox">       
     '.$mobileComMsg.'
    <br /><br />
</div>
<div class="seoBox25 suggestionBox">
'.$mobileCom_Msg.'
</div> 
</div>';


//URL Length & Favicon 
$favIconMsg = $urlLengthMsg = '';
$favIconClass = 'lowImpactBox';
$urlLength_Msg = $lang['AN198'];
$favIcon_Msg = $lang['AN199'];

$hostWord = explode('.',$my_url_host);

if(strlen($hostWord[0]) < 15){
    $urlLengthClass = 'passedBox';
}else{
    $urlLengthClass = 'errorBox';
}
$urlLengthMsg = $my_url .'<br>'.str_replace('[count]',strlen($hostWord[0]),$lang['AN122']);
$favIconMsg = '<img src="https://www.google.com/s2/favicons?domain='.$my_url.'" alt="FavIcon" />  '.$lang['AN123'];

$seoBox26 = '<div class="'.$urlLengthClass.'">
<div class="msgBox">       
     '.$urlLengthMsg.'
    <br /><br />
</div>
<div class="seoBox26 suggestionBox">
'.$urlLength_Msg.'
</div> 
</div>';

$seoBox27 = '<div class="'.$favIconClass.'">
<div class="msgBox">       
    '.$favIconMsg.'
    <br /><br />
</div>
<div class="seoBox27 suggestionBox">
'.$favIcon_Msg.'
</div> 
</div>';


//Custom 404 Page Checker
$errorPage_Msg = $lang['AN200'];
$errorPageMsg = $errorPageClass = '';
$errorPageCheck = false;

$pageSize = base64_decode($data['404_page']);

if($pageSize < 1500){
    //Default Error Page
    $errorPageCheck = false;
    $errorPageClass = 'errorBox';
    $errorPageMsg = $lang['AN125'];
}else{
   //Custom Error Page 
   $errorPageCheck = true;
   $errorPageClass = 'passedBox';
   $errorPageMsg = $lang['AN124'];
}

$seoBox28 = '<div class="'.$errorPageClass.'">
<div class="msgBox">       
     '.$errorPageMsg.'
    <br /><br />
</div>
<div class="seoBox28 suggestionBox">
'.$errorPage_Msg.'
</div> 
</div>';

//Page Size / Load Time / Language
$size_Msg = $lang['AN201'];
$load_Msg = $lang['AN202'];
$lang_Msg = $lang['AN203'];

$sizeMsg = $loadMsg = $langMsg = '';
$langCode = null;

$load_time_data = decSerBase($data['load_time']);
$timeTaken = $load_time_data[0];
$dataSize = $load_time_data[1];
$langCode = $load_time_data[2];

$dataSize = size_as_kb($dataSize);
if($dataSize < 320){
    $sizeClass = 'passedBox'; 
}else{
    $sizeClass = 'errorBox'; 
}

$sizeMsg = str_replace('[size]',$dataSize,$lang['AN126']);

$timeTaken = round($timeTaken,2);

if($timeTaken < 1){
    $loadClass = 'passedBox'; 
}else{
    $loadClass = 'errorBox';
}
$loadMsg = str_replace('[time]',$timeTaken,$lang['AN127']);

if($langCode == null){
    //Error 
    $langClass = 'errorBox';
    $langMsg.= $lang['AN129'] . '<br>';
}else{
    //Passed
    $langClass = 'passedBox';
    $langMsg.= $lang['AN128'] . '<br>';
}
$langCode  = lang_code_to_lnag($langCode);
$langMsg.= str_replace('[language]',$langCode,$lang['AN130']);

$seoBox29 = '<div class="'.$sizeClass.'">
<div class="msgBox">       
     '.$sizeMsg.'
    <br /><br />
</div>
<div class="seoBox29 suggestionBox">
'.$size_Msg.'
</div> 
</div>';

$seoBox30 = '<div class="'.$loadClass.'">
<div class="msgBox">       
     '.$loadMsg.'
    <br /><br />
</div>
<div class="seoBox30 suggestionBox">
'.$load_Msg.'
</div> 
</div>';

$seoBox31 = '<div class="'.$langClass.'">
<div class="msgBox">       
     '.$langMsg.'
    <br /><br />
</div>
<div class="seoBox31 suggestionBox">
'.$lang_Msg.'
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

$seoBox32 = '<div class="'.$domainClass.'">
<div class="msgBox"> 
    <table class="table table-hover table-bordered table-striped">
        <tbody>
            <tr> <th>'.$lang['AN134'].'</th> <th>'.$lang['AN135'].'</th> </tr> 
            '.$domainMsg.'
        </tbody>
    </table>
    <br />
</div>
<div class="seoBox32 suggestionBox">
'.$domain_Msg.'
</div> 
</div>';

$seoBox33 = '<div class="'.$typoClass.'">
<div class="msgBox"> 
    <table class="table table-hover table-bordered table-striped">
        <tbody>
            <tr> <th>'.$lang['AN134'].'</th> <th>'.$lang['AN135'].'</th> </tr> 
            '.$typoMsg.'
        </tbody>
    </table>
    <br />
</div>
<div class="seoBox33 suggestionBox">
'.$typo_Msg.'
</div> 
</div>';

//Email Privacy    
$emailPrivacy_Msg = $lang['AN206'];
$emailPrivacyMsg = $emailPrivacyClass = '';

$emailCount = $data['email_privacy'];

if($emailCount == 0){
    //No Email
    $emailPrivacyClass = 'passedBox';
    $emailPrivacyMsg = $lang['AN136'];
}else{
    //Emails Found
    $emailPrivacyClass = 'errorBox';
    $emailPrivacyMsg = $lang['AN137'];
}
         
$seoBox34 = '<div class="'.$emailPrivacyClass.'">
<div class="msgBox">       
     '.$emailPrivacyMsg.'
    <br /><br />
</div>
<div class="seoBox34 suggestionBox">
'.$emailPrivacy_Msg.'
</div> 
</div>';

//Safe Browsing
    
$safeBrowsing_Msg = $lang['AN207'];
$safeBrowsingMsg = $safeBrowsingClass = '';

$safeBrowsingStats = $data['safe_bro'];
//204 The website is not blacklisted 
//200 The website is blacklisted
//501 Something went wrong

if($safeBrowsingStats == 204){
    $safeBrowsingMsg = $lang['AN138'];
    $safeBrowsingClass = 'passedBox';
}elseif($safeBrowsingStats == 200){
    $safeBrowsingMsg = $lang['AN139'];
    $safeBrowsingClass = 'errorBox';
}else{
    $safeBrowsingMsg = $lang['AN140'];
    $safeBrowsingClass = 'improveBox';
} 

$seoBox35 = '<div class="'.$safeBrowsingClass.'">
<div class="msgBox">       
     '.$safeBrowsingMsg.'
    <br /><br />
</div>
<div class="seoBox35 suggestionBox">
'.$safeBrowsing_Msg.'
</div> 
</div>';

  
//Server Location Information
$server_loc = $data['server_loc']; 
            
$seoBox36 = $seoTools->showServerInfo($server_loc);

//Speed Tips 
$speedTips_Msg = $lang['AN209'];
$speedTipsMsg = $speedTipsBody = '';    
$speedTipsCheck = $cssCount = $jsCount = 0;

$speedData = decSerBase($data['speed_tips']);
$cssCount = $speedData[0];
$jsCount = $speedData[1];
$nestedTables = $speedData[2];
$inlineCss = $speedData[3];

$speedTipsBody.= '<br>';

if($cssCount > 5){
    $speedTipsCheck++;
    $speedTipsBody.=  $false . ' ' . $lang['AN145'];
}else
    $speedTipsBody.=  $true . ' ' . $lang['AN144'];

$speedTipsBody.= '<br><br>';
    
if($jsCount > 5){
    $speedTipsCheck++;
    $speedTipsBody.=  $false . ' ' . $lang['AN147'];
}else
    $speedTipsBody.=  $true . ' ' . $lang['AN146'];
    
$speedTipsBody.= '<br><br>';

if($nestedTables == 1){
    $speedTipsCheck++;
    $speedTipsBody.=  $false . ' ' . $lang['AN149'];
}else
    $speedTipsBody.=  $true . ' ' . $lang['AN148'];

$speedTipsBody.= '<br><br>';    

if($inlineCss == 1){
    $speedTipsCheck++;
    $speedTipsBody.=  $false . ' ' . $lang['AN151'];
}else
    $speedTipsBody.=  $true . ' ' . $lang['AN150'];

if($speedTipsCheck == 0)
       $speedTipsClass = 'passedBox';
elseif($speedTipsCheck > 2) 
       $speedTipsClass = 'errorBox';
else
       $speedTipsClass = 'improveBox';

$speedTipsMsg = $lang['AN152'];
         
$seoBox37 = '<div class="'.$speedTipsClass.'">
<div class="msgBox">       
    '.$speedTipsMsg.'
    <br />
    <div class="altImgGroup">
        '.$speedTipsBody.'
    </div>
    <br />
</div>
<div class="seoBox37 suggestionBox">
'.$speedTips_Msg.'
</div> 
</div>';


//Analytics & Doc Type  
$docType_Msg = $lang['AN212'];
$analytics_Msg = $lang['AN210'];
$docType = $analyticsClass = $analyticsMsg = $docTypeClass = $docTypeMsg = '';   
$anCheck = false;
$docCheck = false;

$anDataArr = decSerBase($data['analytics']);

$anCheck = filter_var($anDataArr[0], FILTER_VALIDATE_BOOLEAN);
$docCheck = filter_var($anDataArr[1], FILTER_VALIDATE_BOOLEAN);
$docType = $anDataArr[2];

if ($anCheck){
    //Found
    $analyticsClass = 'passedBox';
    $analyticsMsg = $lang['AN154'];
}else{
    ///Not Found
    $analyticsClass = 'errorBox';
    $analyticsMsg = $lang['AN153'];
}

if(!$docCheck){
   $docTypeMsg = $lang['AN155'];
   $docTypeClass = 'improveBox';
}else{
    $docTypeMsg = $lang['AN156'] . ' ' . $docType;
    $docTypeClass = 'passedBox';
}

$seoBox38 = '<div class="'.$analyticsClass.'">
<div class="msgBox">
    '.$analyticsMsg.'
    <br /><br />
</div>
<div class="seoBox38 suggestionBox">
'.$analytics_Msg.'
</div> 
</div>';
         
$seoBox40 = '<div class="'.$docTypeClass.'">
<div class="msgBox">
    '.$docTypeMsg.'
    <br /><br />
</div>
<div class="seoBox40 suggestionBox">
'.$docType_Msg.'
</div> 
</div>';

//W3C Validity    
$w3c_Msg = $lang['AN211'];
$w3Data = $w3cMsg = '';
$w3cClass = 'lowImpactBox';
$w3DataCheck = 0;

$w3DataCheck = Trim($data['w3c']);

if($w3DataCheck == '1'){
    //Valid
    $w3cMsg = $lang['AN157'];
}elseif($w3DataCheck == '2'){
    //Not Valid
   $w3cMsg = $lang['AN158'];
}else{
    //Error
    $w3cMsg = $lang['AN10'];  
}

$seoBox39 = '<div class="'.$w3cClass.'">
<div class="msgBox">       
     '.$w3cMsg.'
    <br /><br />
</div>
<div class="seoBox39 suggestionBox">
'.$w3c_Msg.'
</div> 
</div>';

//Encoding Type
$encoding_Msg = $lang['AN213'];
$encodingMsg = $encodingClass = '';
$charterSet = null;

$charterSet = base64_decode($data['encoding']);

if($charterSet!=null){
    $encodingClass = 'passedBox';
    $encodingMsg = $lang['AN159'] . ' '. $charterSet;
}
else{ 
    $encodingClass = 'errorBox';
    $encodingMsg = $lang['AN160'];
}

$seoBox41 = '<div class="'.$encodingClass.'">
<div class="msgBox">       
     '.$encodingMsg.'
    <br /><br />
</div>
<div class="seoBox41 suggestionBox">
'.$encoding_Msg.'
</div> 
</div>';

//Indexed Pages
$indexedPages_Msg = $lang['AN214'];
$indexProgress = $indexedPagesMsg = $indexedPagesClass = '';
$datVal = $outData = 0;

$outData = intval(base64_decode($data['indexed']));

if($outData < 50){
    $datVal = 25;
    $indexedPagesClass = 'errorBox';
    $indexProgress = 'danger';
}elseif($outData < 200){
    $datVal = 75;
    $indexedPagesClass = 'improveBox';
    $indexProgress = 'warning';
}else{
    $datVal = 100;
    $indexedPagesClass = 'passedBox';
    $indexProgress = 'success';
}

$indexedPagesMsg = '<div style="width:'.$datVal.'%" aria-valuemax="'.$datVal.'" aria-valuemin="0" aria-valuenow="'.$datVal.'" role="progressbar" class="progress-bar progress-bar-'.$indexProgress.'">
    '.number_format($outData).' '.$lang['AN162'].'
</div>';
    
$seoBox42 = '<div class="'.$indexedPagesClass.'">
<div class="msgBox">    
    '.$lang['AN161'].'<br />   <br /> 
     <div class="progress">
        '.$indexedPagesMsg.'
     </div>
    <br />
</div>
<div class="seoBox42 suggestionBox">
'.$indexedPages_Msg.'
</div> 
</div>';

//Backlink Counter / Traffic / Worth 
$backlinks_Msg = $lang['AN215'];
$alexa_Msg =  $lang['AN218'];
$worth_Msg =  $lang['AN217'];
$alexaMsg = $worthMsg = $backProgress = $backlinksMsg = $backlinksClass = '';
$alexaClass = $worthClass = 'lowImpactBox';

$alexa = decSerBase($data['alexa']);
$alexa_rank = $alexa[0];
$alexa_pop = $alexa[1];
$regional_rank = $alexa[2];    
$alexa_back = intval(str_replace(',','',$alexa[3]));

if($alexa_back < 50){
    $datVal = 25;
    $backlinksClass = 'errorBox';
    $backProgress = 'danger';
}elseif($alexa_back < 100){
    $datVal = 75;
    $backlinksClass = 'improveBox';
    $backProgress = 'warning';
}else{
    $datVal = 100;
    $backlinksClass = 'passedBox';
    $backProgress = 'success';
}

$backlinksMsg = '<div style="width:'.$datVal.'%" aria-valuemax="'.$datVal.'" aria-valuemin="0" aria-valuenow="'.$datVal.'" role="progressbar" class="progress-bar progress-bar-'.$backProgress.'">
    '.number_format($alexa_back).' '.$lang['AN163'].'
</div>';

if($alexa_rank == 'No Global Rank')
    $alexaMsg = $lang['AN165'];
else
    $alexaMsg = ordinalNum(str_replace(',','',$alexa_rank)) . ' '. $lang['AN164'];

$alexa_rank = ($alexa_rank == 'No Global Rank' ? '0' : $alexa_rank);
$worthMsg = "$". number_format(calPrice($alexa_rank))." USD";
        
$seoBox43 = '<div class="'.$backlinksClass.'">
<div class="msgBox">     
    '.$lang['AN166'].'<br />   <br /> 
     <div class="progress">  
     '.$backlinksMsg.'
     </div>
     <br />
</div>
<div class="seoBox43 suggestionBox">
'.$backlinks_Msg.'
</div> 
</div>';

$seoBox45 = '<div class="'.$worthClass.'">
<div class="msgBox">       
     '.$worthMsg.'
    <br /><br />
</div>
<div class="seoBox45 suggestionBox">
'.$worth_Msg.'
</div> 
</div>';

$seoBox46 = '<div class="'.$alexaClass.'">
<div class="msgBox">       
     '.$alexaMsg.'
    <br /><br />
</div>
<div class="seoBox46 suggestionBox">
'.$alexa_Msg.'
</div> 
</div>';


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

//Page Speed Insight Checker
$pageSpeedInsightDesktop_Msg = $lang['AN220'];
$pageSpeedInsightMobile_Msg = $lang['AN221'];
$desktopMsg = $mobileMsg = $pageSpeedInsightData = $seoBox48 = $seoBox49 = '';
$desktopClass = $mobileClass = $desktopSpeed = $mobileSpeed = '';
$speedStr = $lang['117']; $mobileStr = $lang['119']; $desktopStr = $lang['118'];

$pageSpeedInsightData = decSerBase($data['page_speed_insight']); 

$desktopScore = $pageSpeedInsightData[0];
$mobileScore = $pageSpeedInsightData[1];

if(intval($desktopScore) < 50){
    $desktopClass = 'errorBox';
    $desktopSpeed = $lang['125'];
}elseif(intval($desktopScore) < 79){
    $desktopClass = 'improveBox';
    $desktopSpeed = $lang['126'];
}else{
    $desktopClass = 'passedBox';
    $desktopSpeed = $lang['124'];
}
    
if(intval($mobileScore) < 50){
    $mobileClass = 'errorBox';
    $mobileSpeed = $lang['125'];
}elseif(intval($mobileScore) < 79){
    $mobileClass = 'improveBox';
    $mobileSpeed = $lang['126'];
}else{
    $mobileClass = 'passedBox';
    $mobileSpeed = $lang['126'];
}
    
$desktopMsg = <<< EOT
<script type="text/javascript">var desktopPageSpeed = new Gauge({
	renderTo  : 'desktopPageSpeed',
	width     : 250,
	height    : 250,
	glow      : true,
	units     : '$speedStr',
    title       : '$desktopStr',
    minValue    : 0,
    maxValue    : 100,
    majorTicks  : ['0','20','40','60','80','100'],
    minorTicks  : 5,
    strokeTicks : true,
    valueFormat : {
        int : 2,
        dec : 0,
        text : '%'
    },
    valueBox: {
        rectStart: '#888',
        rectEnd: '#666',
        background: '#CFCFCF'
    },
    valueText: {
        foreground: '#CFCFCF'
    },
	highlights : [{
		from  : 0,
		to    : 40,
		color : '#EFEFEF'
	},{
		from  : 40,
		to    : 60,
		color : 'LightSalmon'
	}, {
		from  : 60,
		to    : 80,
		color : 'Khaki'
	}, {
		from  : 80,
		to    : 100,
		color : 'PaleGreen'
	}],
	animation : {
		delay : 10,
		duration: 300,
		fn : 'bounce'
	}
});

desktopPageSpeed.onready = function() {
    desktopPageSpeed.setValue($desktopScore);
};


desktopPageSpeed.draw();</script>
EOT;

$mobileMsg = <<< EOT
<script>var mobilePageSpeed = new Gauge({
	renderTo  : 'mobilePageSpeed',
	width     : 250,
	height    : 250,
	glow      : true,
	units     : '$speedStr',
    title       : '$mobileStr',
    minValue    : 0,
    maxValue    : 100,
    majorTicks  : ['0','20','40','60','80','100'],
    minorTicks  : 5,
    strokeTicks : true,
    valueFormat : {
        int : 2,
        dec : 0,
        text : '%'
    },
    valueBox: {
        rectStart: '#888',
        rectEnd: '#666',
        background: '#CFCFCF'
    },
    valueText: {
        foreground: '#CFCFCF'
    },
	highlights : [{
		from  : 0,
		to    : 40,
		color : '#EFEFEF'
	},{
		from  : 40,
		to    : 60,
		color : 'LightSalmon'
	}, {
		from  : 60,
		to    : 80,
		color : 'Khaki'
	}, {
		from  : 80,
		to    : 100,
		color : 'PaleGreen'
	}],
	animation : {
		delay : 10,
		duration: 300,
		fn : 'bounce'
	}
});

mobilePageSpeed.onready = function() {
    mobilePageSpeed.setValue($mobileScore);
};


mobilePageSpeed.draw();</script>
EOT;

    
$seoBox48 = '<div class="'.$desktopClass.'">
<div class="msgBox">
    <div class="row">
        <div class="col-sm-6 text-center">
            <canvas id="desktopPageSpeed"></canvas>
            '.$desktopMsg.'
        </div>
        <div class="col-sm-6">
        <h2>'.$desktopScore.' / 100</h2>
        <h4>'.$lang['123'].'</h4>
        <p><strong>'.ucfirst($my_url_host).'</strong> '.$lang['127'].' <strong>'.$desktopSpeed.'</strong>. '.$lang['128'].'</p>
        </div>
    </div>   
</div>
<div class="seoBox48 suggestionBox">
'.$pageSpeedInsightDesktop_Msg.'
</div> 
</div>';

$seoBox49 = '<div class="'.$mobileClass.'">
<div class="msgBox">   
    <div class="row">
        <div class="col-sm-6 text-center">
            <canvas id="mobilePageSpeed"></canvas>
            '.$mobileMsg.'
        </div>
    <div class="col-sm-6">
        <h2>'.$mobileScore.' / 100</h2>
        <h4>'.$lang['123'].'</h4>
        <p><strong>'.ucfirst($my_url_host).'</strong> '.$lang['129'].' <strong>'.$mobileSpeed.'</strong>. '.$lang['128'].'</p>
    </div>
    </div>
</div>
<div class="seoBox49 suggestionBox">
'.$pageSpeedInsightMobile_Msg.'
</div> 
</div>';

//Get Final Score Data
$score = decSerBase($data['score']); 
$passScore = $score[0];
$improveScore = $score[1];
$errorScore = $score[2];

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