<?php

defined('DB_DOMAIN') or die(header('HTTP/1.1 403 Forbidden'));

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







// die();

// Decode your headings data 
$headings = jsonDecode($data['headings']); 

// Check that the decoded data is an array and has at least one element
if (!is_array($headings) || !isset($headings[0])) {
    die("Invalid headings data.");
}

// Typically, $headings[0] contains the arrays of heading tags => array of heading texts
$elementListData = $headings[0];

// If there's a second array with text data for counting, you can use it here:
$texts = isset($headings[1]) ? $headings[1] : [];

// Define the tags
$tags = array('h1','h2','h3','h4','h5','h6');

// Determine how many headings exist for each tag
$counts = [];
foreach ($tags as $tag) {
    // Use $texts if available, otherwise fall back to $elementListData
    if (isset($texts[$tag])) {
        $counts[$tag] = count($texts[$tag]);
    } elseif (isset($elementListData[$tag])) {
        $counts[$tag] = count($elementListData[$tag]);
    } else {
        $counts[$tag] = 0;
    }
}

// Decide the outer container’s CSS class based on H1/H2 logic
if ($counts['h1'] > 2) {
    $boxClass = 'improveBox'; 
} elseif ($counts['h1'] > 0 && $counts['h2'] > 0) {
    $boxClass = 'passedBox';
} else {
    $boxClass = 'errorBox';
}

// Suggestion text from language array (ensure $lang is defined)
$headMsg = isset($lang['AN176']) ? $lang['AN176'] : "Please review your heading structure.";

// Define helper function for suggestions if not already defined
if (!function_exists('getHeadingSuggestion')) {
    function getHeadingSuggestion($tag, $count) {
        $tagUpper = strtoupper($tag);
        if ($count === 0) {
            if ($tag === 'h1') {
                return "No {$tagUpper} found. We recommend having at least one H1 tag for SEO.";
            } else {
                return "No {$tagUpper} found. Consider adding if needed for structure.";
            }
        }
        if ($tag === 'h1' && $count > 2) {
            return "You have more than 2 H1 headings. Typically, only one is recommended.";
        }
        return "Looks good for {$tagUpper}.";
    }
}

// Build the table rows for each tag
$rowsHtml = "";
foreach ($tags as $tag) {
    $count = $counts[$tag];
    // Build a bullet list of headings, or show "None found."
    if (isset($elementListData[$tag]) && count($elementListData[$tag]) > 0) {
        $headingsList = '<ul class="list-unstyled mb-0">';
        foreach ($elementListData[$tag] as $text) {
            $headingsList .= '<li>&lt;' . $tag . '&gt; <b>' . htmlspecialchars($text) . '</b> &lt;/' . $tag . '&gt;</li>';
        }
        $headingsList .= '</ul>';
    } else {
        $headingsList = '<em class="text-muted">None found.</em>';
    }
    
    // Build the suggestion text
    $suggestion = getHeadingSuggestion($tag, $count);
    
    // Add one table row
    $rowsHtml .= '
        <tr>
            <td><strong>' . strtoupper($tag) . '</strong></td>
            <td>' . $count . '</td>
            <td>' . $headingsList . '</td>
            <td class="text-muted small">' . $suggestion . '</td>
        </tr>';
}

// Wrap everything in the main box
$seoBox4 = ' 
    <div class="contentBox" id="seoBox4">
        <div class="msgBox">
            <table class="table table-bordered table-hover table-responsive">
                <thead>
                    <tr>
                        <th width="50px">Tag</th>
                        <th width="100px">Count</th>
                        <th>Headings</th>
                        <th width="200px">Suggestion</th>
                    </tr>
                </thead>
                <tbody>
                    ' . $rowsHtml . '
                </tbody>
            </table>
        </div>
        <div class="seoBox4 suggestionBox">
             ' . $headMsg . '
        </div>
    </div>
    <div class="questionBox" data-original-title="More Information" data-toggle="tooltip" data-placement="top">
        <i class="fa fa-question-circle grayColor"></i>
    </div> ';
 





  $image_alt = jsonDecode($data['image_alt']);

// Ensure each key is at least an empty array so array_map won't fail
$image_alt['images_missing_alt']        = $image_alt['images_missing_alt']        ?? [];
$image_alt['images_with_empty_alt']     = $image_alt['images_with_empty_alt']     ?? [];
$image_alt['images_with_short_alt']     = $image_alt['images_with_short_alt']     ?? [];
$image_alt['images_with_long_alt']      = $image_alt['images_with_long_alt']      ?? [];
$image_alt['images_with_redundant_alt'] = $image_alt['images_with_redundant_alt'] ?? [];
$image_alt['suggestions']              = $image_alt['suggestions']              ?? [];

// Total images
$imageCount = $image_alt['total_images'] ?? 0;

// Aggregate images missing alt attribute.
$imageWithOutAltTag = array_sum(array_map(function($item) {
    return $item['count'];
}, $image_alt['images_missing_alt']));
$imageWithOutAltTagData = '';
foreach ($image_alt['images_missing_alt'] as $item) {
    $imageWithOutAltTagData .= '<tr><td>' . htmlspecialchars($item['src']) . '</td><td>' . $item['count'] . '</td></tr>';
}

// Aggregate images with empty alt attribute.
$imageWithEmptyAltCount = array_sum(array_map(function($item) {
    return $item['count'];
}, $image_alt['images_with_empty_alt']));
$imageWithEmptyAltData = '';
foreach ($image_alt['images_with_empty_alt'] as $item) {
    $imageWithEmptyAltData .= '<tr><td>' . htmlspecialchars($item['src']) . '</td><td>' . $item['count'] . '</td></tr>';
}

// Aggregate images with short alt text.
$imageWithShortAltCount = array_sum(array_map(function($item) {
    return $item['count'];
}, $image_alt['images_with_short_alt']));
$imageWithShortAltData = '';
foreach ($image_alt['images_with_short_alt'] as $item) {
    $imageWithShortAltData .= '<tr><td>' . htmlspecialchars($item['src']) . '</td><td>' . $item['count'] . '</td></tr>';
}

// Aggregate images with long alt text.
$imageWithLongAltCount = array_sum(array_map(function($item) {
    return $item['count'];
}, $image_alt['images_with_long_alt']));
$imageWithLongAltData = '';
foreach ($image_alt['images_with_long_alt'] as $item) {
    $imageWithLongAltData .= '<tr><td>' . htmlspecialchars($item['src']) . '</td><td>' . $item['count'] . '</td></tr>';
}

// Aggregate images with redundant alt text.
$imageWithRedundantAltCount = array_sum(array_map(function($item) {
    return $item['count'];
}, $image_alt['images_with_redundant_alt']));
$imageWithRedundantAltData = '';
foreach ($image_alt['images_with_redundant_alt'] as $item) {
    $imageWithRedundantAltData .= '<tr><td>' . htmlspecialchars($item['src']) . '</td><td>' . $item['count'] . '</td></tr>';
}

// Calculate overall issues count.
$issuesCount = $imageWithOutAltTag 
             + $imageWithEmptyAltCount 
             + $imageWithShortAltCount 
             + $imageWithLongAltCount 
             + $imageWithRedundantAltCount;

// Determine the CSS class based on the overall issue count.
$altClass = ($issuesCount == 0) ? 'passedBox' : (($issuesCount < 3) ? 'improveBox' : 'errorBox');

// Build the suggestion messages.
$imageMsg = '';
foreach ($image_alt['suggestions'] as $sugg) {
    $imageMsg .= '<p>' . htmlspecialchars($sugg) . '</p>';
}

// Build the final HTML output similar to your original $seoBox6.
$seoBox6 = '<div class="' . $altClass . '">
    <div class="msgBox">       
        ' . str_replace('[image-count]', $imageCount, $lang['AN21']) . ' <br />
        <div class="altImgGroup"> 
            ' . (
                ($imageWithOutAltTag == 0)
                ? '<img src="' . $theme_path . 'img/true.png" alt="' . $lang['AN24'] . '" title="' . $lang['AN25'] . '" /> ' . $lang['AN27']
                : '<img src="' . $theme_path . 'img/false.png" alt="' . $lang['AN23'] . '" title="' . $lang['AN22'] . '" />
                   ' . str_replace('[missing-alt-tag]', $imageWithOutAltTag, $lang['AN26'])
            ) . '
        </div>
        <br />
        
        <!-- Table for images missing alt -->
        <table class="table table-striped table-responsive">
            <thead>
              <tr>
                <th>Image Source</th>
                <th>Count</th>
              </tr>
            </thead>
            <tbody>
              ' . $imageWithOutAltTagData . '
            </tbody>
        </table>
        ' . (
            ($imageWithOutAltTag > 3)
            ? '<div class="showLinks showLinks2">
                   <a class="showMore showMore2">' . $lang['AN18'] . ' <br /> <i class="fa fa-angle-double-down"></i></a>
                   <a class="showLess showLess2"><i class="fa fa-angle-double-up"></i> <br /> ' . $lang['AN19'] . '</a>
               </div>'
            : ''
        ) . '
        <br />
        
        <!-- Images With Empty Alt -->
        ' . (
            $imageWithEmptyAltCount > 0 
            ? '<h4>Images With Empty Alt Attribute (' . $imageWithEmptyAltCount . ')</h4>
               <table class="table table-striped table-responsive">
                  <thead>
                    <tr>
                      <th>Image Source</th>
                      <th>Count</th>
                    </tr>
                  </thead>
                  <tbody>' . $imageWithEmptyAltData . '</tbody>
               </table>'
            : ''
        ) . '
        
        <!-- Images With Short Alt -->
        ' . (
            $imageWithShortAltCount > 0 
            ? '<h4>Images With Short Alt Text (' . $imageWithShortAltCount . ')</h4>
               <table class="table table-striped table-responsive">
                  <thead>
                    <tr>
                      <th>Image Source</th>
                      <th>Count</th>
                    </tr>
                  </thead>
                  <tbody>' . $imageWithShortAltData . '</tbody>
               </table>'
            : ''
        ) . '
        
        <!-- Images With Long Alt -->
        ' . (
            $imageWithLongAltCount > 0 
            ? '<h4>Images With Long Alt Text (' . $imageWithLongAltCount . ')</h4>
               <table class="table table-striped table-responsive">
                  <thead>
                    <tr>
                      <th>Image Source</th>
                      <th>Count</th>
                    </tr>
                  </thead>
                  <tbody>' . $imageWithLongAltData . '</tbody>
               </table>'
            : ''
        ) . '
        
        <!-- Images With Redundant Alt -->
        ' . (
            $imageWithRedundantAltCount > 0 
            ? '<h4>Images With Redundant Alt Text (' . $imageWithRedundantAltCount . ')</h4>
               <table class="table table-striped table-responsive">
                  <thead>
                    <tr>
                      <th>Image Source</th>
                      <th>Count</th>
                    </tr>
                  </thead>
                  <tbody>' . $imageWithRedundantAltData . '</tbody>
               </table>'
            : ''
        ) . '
        
    </div>
    <div class="seoBox6 suggestionBox">
        ' . $imageMsg . '
    </div> 
</div>';

  

//Keyword Cloud
$keywords_cloud = jsonDecode($data['keywords_cloud']);
echo "<pre>";
print_r($keywords_cloud);
echo "</pre>";

$outCount = $keywords_cloud[0];
$outArr = $keywords_cloud[1];
$keyData = '';
    
foreach($outArr as $outData){
    $keyData .= '<li><span class="keyword">'.$outData[0].'</span><span class="number">'.$outData[1].'</span></li>';
}
    
$keycloudClass = 'lowImpactBox';
$keyMsg = $lang['AN179'];

$seoBox7 = '<div class="'.$keycloudClass.'">
<div class="msgBox padRight10 bottom5">       
     '.(($outCount != 0)? '
    <ul class="keywordsTags">
          '.$keyData.'  
    </ul>' : ' '.$lang['AN29']).'  
</div>
<div class="seoBox7 suggestionBox">
'.$keyMsg.'
</div> 
</div>';

    
$hideClass = $keywordConsistencyTitle = $keywordConsistencyDes = $keywordConsistencyH = $keywordConsistencyData = '';

$hideCount = 1;
$keywordConsistencyScore = 0;

foreach($outArr as $outKey){
    if(check_str_contains($metatitle, $outKey[0], true)){
        $keywordConsistencyTitle = $true;
        $keywordConsistencyScore++;
    }else{
        $keywordConsistencyTitle = $false;
    }
   
    if(check_str_contains($metadescription, $outKey[0], true)){
        $keywordConsistencyDes = $true;
        $keywordConsistencyScore++;
    }else{
        $keywordConsistencyDes = $false;
    } 
    
    $keywordConsistencyH = $false;
    
    foreach($texts as $htags){
        foreach($htags as $htag){
            if(check_str_contains($htag, $outKey[0], true)){
                $keywordConsistencyH = $true;
                break 2;
            }
        }
    }
        
    if($hideCount == 5)
        $hideClass = 'hideTr hideTr3';
            
    $keywordConsistencyData .= '<tr class="'.$hideClass.'"> 
            <td>'.$outKey[0].'</td> 
            <td>'.$outKey[1].'</td> 
            <td>'.$keywordConsistencyTitle.'</td>
            <td>'.$keywordConsistencyDes.'</td>
            <td>'.$keywordConsistencyH.'</td>   
            </tr>';
    $hideCount++;
}

if($keywordConsistencyScore == 0)
    $keywordConsistencyClass = 'errorBox';
elseif($keywordConsistencyScore < 4)
    $keywordConsistencyClass = 'improveBox';
else
    $keywordConsistencyClass = 'passedBox';
    
$keyMsg = $lang['AN180'];

$seoBox8 = '<div class="'.$keywordConsistencyClass.'">
<div class="msgBox">       
    <table class="table table-striped table-responsive">
	    <thead>
			<tr>
        		<th>'.$lang['AN31'].'</th>
                <th>'.$lang['AN32'].'</th>
                <th>'.$lang['AN33'].'</th>
                <th>'.$lang['AN34'].'</th>
                <th>&lt;H&gt;</th>
  			</tr>
	    </thead>
        <tbody>
            '.$keywordConsistencyData.'
	   </tbody>
    </table>
    
    '.(($hideCount > 5)? '
        <div class="showLinks showLinks3">
            <a class="showMore showMore3">'.$lang['AN18'].' <br /> <i class="fa fa-angle-double-down"></i></a>
            <a class="showLess showLess3"><i class="fa fa-angle-double-up"></i> <br /> '.$lang['AN19'].'</a>
    </div>' : '').'
    
</div>
<div class="seoBox8 suggestionBox">
'.$keyMsg.'
</div> 
</div>';

//Text to HTML Ratio
$textRatio = decSerBase($data['ratio_data']);

if(round($textRatio[2]) < 2)
    $textClass = 'errorBox';
elseif(round($textRatio[2]) < 10)
    $textClass = 'improveBox';
else
    $textClass = 'passedBox';
    
$textMsg = $lang['AN181'];

$seoBox9 = '<div class="'.$textClass.'">
<div class="msgBox">       
    '.$lang['AN36'].': <b>'.round($textRatio[2],2).'%</b><br />
    <br />
    <table class="table table-responsive">
        <tbody>
            <tr> 
            <td>'.$lang['AN37'].'</td> 
            <td>'.$textRatio[1].' '.$lang['AN39'].'</td> 
            </tr>
            
            <tr> 
            <td>'.$lang['AN38'].'</td> 
            <td>'.$textRatio[0].' '.$lang['AN39'].'</td>  
            </tr>
	   </tbody>
    </table>
</div>
<div class="seoBox9 suggestionBox">
'.$textMsg.'
</div> 
</div>';

//Check GZIP Compression 
$gzipClass = $gzipHead = $gzipBody = '';
$outData = decSerBase($data['gzip']);
$comSize = $outData[0];
$unComSize = $outData[1];
$isGzip = $outData[2];
$gzdataSize = $outData[3];
$header = $outData[4];
$body = Trim($outData[5]);

if($body == ""){
    $gzipHead = $lang['AN10'];
    $gzipClass = 'improveBox';
}else{
if($isGzip){
    $percentage = round(((((int)$unComSize - (int)$comSize) / (int)$unComSize) * 100),1);
    $gzipClass = 'passedBox';
    $gzipHead = $lang['AN42'];
    $gzipBody = $true . ' ' . str_replace(array('[total-size]','[compressed-size]','[percentage]'),array(size_as_kb($unComSize),size_as_kb($comSize),$percentage),$lang['AN41']);
}else{
    $percentage = round(((((int)$unComSize - (int)$gzdataSize) / (int)$unComSize) * 100),1);
    $gzipClass = 'errorBox';
    $gzipHead = $lang['AN43'];
    $gzipBody = $false . ' ' . str_replace(array('[total-size]','[compressed-size]','[percentage]'),array(size_as_kb($unComSize),size_as_kb($gzdataSize),$percentage),$lang['AN44']);
}
}

$gzipMsg = $lang['AN182'];
            
$seoBox10 = '<div class="'.$gzipClass.'">
<div class="msgBox">       
     '.$gzipHead.'
    <br />
    <div class="altImgGroup">
        '.$gzipBody.'
    </div>
    <br />
</div>
<div class="seoBox10 suggestionBox">
'.$gzipMsg.'
</div> 
</div>';


//WWW Resolve
$resolve = decSerBase($data['resolve']);

$www_resolveMsg = $lang['AN183'];
$resolveClass = 'improveBox';
$resolveMsg = $lang['AN47'];
$re301 = false;
$url_with_www = "http://www.$my_url_host";
$url_no_www = "http://$my_url_host";

$data1 = $resolve[0];
$data2 = $resolve[1];


if($data1 =='301'){
    $re301 = true;
    $resolveClass = 'passedBox';
    $resolveMsg = $lang['AN46'];
}

if($data2 =='301'){
    $re301= true;
    $resolveClass = 'passedBox';
    $resolveMsg = $lang['AN46'];
}

$seoBox11 = '<div class="'.$resolveClass.'">
<div class="msgBox">       
     '.$resolveMsg.'
    <br />
    <br />
</div>
<div class="seoBox11 suggestionBox">
'.$www_resolveMsg.'
</div> 
</div>';


//IP Canonicalization
$ip_can = decSerBase($data['ip_can']);

$ip_canMsg = $lang['AN184'];
$ipClass = 'improveBox';
$hostIP = $ipMsg = '';
$tType = $ip_can[1];
$redirectURLhost = $ip_can[3];
$hostIP = $ip_can[0];

if($tType){
    if($my_url_host == $redirectURLhost){
        $ipMsg = str_replace(array('[ip]','[host]'),array($hostIP,$my_url_host),$lang['AN50']);
        $ipClass = 'passedBox';
    }else{
       $ipMsg = str_replace(array('[ip]','[host]'),array($hostIP,$my_url_host),$lang['AN49']); 
    }
    $tType = true;
}else{
    $ipMsg = str_replace(array('[ip]','[host]'),array($hostIP,$my_url_host),$lang['AN49']);
}

$seoBox12 = '<div class="'.$ipClass.'">
<div class="msgBox">       
     '.$ipMsg.'
    <br />
    <br />
</div>
<div class="seoBox12 suggestionBox">
'.$ip_canMsg.'
</div> 
</div>';


//In-Page Links analyser
$in_pageMsg = $lang['AN185'];
$link_UnderScoreMsg = $lang['AN190'];
$url_RewritingMsg = $lang['AN189'];
$inPageClass = 'improveBox';
$urlRewritingClass= $urlRewritingMsg = $linkUnderScoreMsg = $linkUnderScoreClass = $hideMe = $inPageData = $inPageMsg = '';
$totalDataCount = 0;

//Define Variables
$int_data = $ex_data_arr = $ex_data = array();
$t_count = $i_links = $e_links = $i_nofollow = $e_nofollow = 0;

//URL Rewriting
$urlRewriting = true;
$webFormats = array('html', 'htm', 'xhtml', 'xht', 'mhtml', 'mht','asp', 'aspx','cgi', 
'ihtml', 'jsp', 'las','pl', 'php', 'php3', 'phtml', 'shtml');

//Underscore on URL's
$linkUnderScore = false;

$ex_data = decSerBase($data['links_analyser']);

//Internal Links
foreach ($ex_data as $count => $link) {
    $t_count++;
    $parse_urls = parse_url($link['href']);
    $type = strtolower($link['rel']);
    $myIntHost = $path = '';
    if(isset($parse_urls['path']))
        $path = $parse_urls['path'];
        
    if(isset($parse_urls['host']))
        $myIntHost = $parse_urls['host'];
        
    if ($myIntHost == $my_url_host || $myIntHost == "www." . $my_url_host) {
        $i_links++;
        
        $int_data[$i_links]['inorout'] = $lang['AN52'];
        $int_data[$i_links]['href'] = $link['href'];
        $int_data[$i_links]['text'] = $link['innertext'];
        
        if(mb_strpos($link['href'], "_") !== false)
            $linkUnderScore = true;

        $dotStr = $exStr = '';
        $exStr = explode('.',$path);
        $dotStr = Trim(end($exStr));
        if($dotStr != $path){
            if(in_array($dotStr,$webFormats))
                $urlRewriting = false;
        }
        
        if ($type == 'dofollow' || ($type != 'dofollow' && $type != 'nofollow'))
            $int_data[$i_links]['follow_type'] = "dofollow";

        if ($type == 'nofollow'){
            $i_nofollow++;
            $int_data[$i_links]['follow_type'] = "nofollow";
        }
        
    } elseif ((substr($link['href'], 0, 2) != "//") && (substr($link['href'], 0, 1) == "/")) {
        $i_links++;
        $int_data[$i_links]['inorout'] = $lang['AN52'];
        $int_data[$i_links]['href'] = $inputHost.$link['href'];
        $int_data[$i_links]['text'] = $link['innertext'];
        
        if(mb_strpos($link['href'], "_") !== false)
            $linkUnderScore = true;
            
        $dotStr = $exStr = '';
        $exStr = explode('.',$path);
        $dotStr = Trim(end($exStr));
        if($dotStr != $path){
            if(in_array($dotStr,$webFormats))
                $urlRewriting = false;
        }
        
        if ($type == 'dofollow' || ($type != 'dofollow' && $type != 'nofollow'))
            $int_data[$i_links]['follow_type'] = "dofollow";
            
        if ($type == 'nofollow') {
            $i_nofollow++;
            $int_data[$i_links]['follow_type'] = "nofollow";
        }
    } else{
            if(substr($link['href'], 0, 7) != "http://" && substr($link['href'], 0, 8) != "https://" &&
            substr($link['href'], 0, 2) != "//" && substr($link['href'], 0, 1) != "/" && substr($link['href'], 0, 1) != "#"
            && substr($link['href'], 0, 2) != "//" && substr($link['href'], 0, 6) != "mailto" && substr($link['href'], 0, 4) != "tel:" && substr($link['href'], 0, 10) != "javascript") {
            
                $i_links++;
                $int_data[$i_links]['inorout'] = $lang['AN52'];
                $int_data[$i_links]['href'] = $inputHost.'/'.$link['href'];
                $int_data[$i_links]['text'] = $link['innertext'];
                if(mb_strpos($link['href'], "_") !== false)
                    $linkUnderScore = true;
                
                $dotStr = $exStr = '';
                $exStr = explode('.',$path);
                $dotStr = Trim(end($exStr));
                if($dotStr != $path){
                    if(in_array($dotStr,$webFormats))
                        $urlRewriting = false;
                }
                
                if ($type == 'dofollow' || ($type != 'dofollow' && $type != 'nofollow'))
                    $int_data[$i_links]['follow_type'] = "dofollow";
                    
                if ($type == 'nofollow') {
                    $i_nofollow++;
                    $int_data[$i_links]['follow_type'] = "nofollow";
                }
            }
		}
}

//External Links
foreach ($ex_data as $count => $link)
{
    $parse_urls = parse_url($link['href']);
    $type = strtolower($link['rel']);
    
    if ($parse_urls !== false && isset($parse_urls['host']) && $parse_urls['host'] !=
        $my_url_host && $parse_urls['host'] != "www." . $my_url_host) {
        $e_links++;
        $ext_data[$e_links]['inorout'] = $lang['AN53'];
        $ext_data[$e_links]['href'] = $link['href'];
        $ext_data[$e_links]['text'] = $link['innertext'];
        if ($type == 'dofollow' || ($type != 'dofollow' && $type != 'nofollow'))
            $ext_data[$e_links]['follow_type'] = "dofollow";
        if ($type == 'nofollow') {
            $e_nofollow++;
            $ext_data[$e_links]['follow_type'] = "nofollow";
        }
    } elseif ((substr($link['href'], 0, 2) == "//") && (substr($link['href'], 0, 1) != "/")) {
        $e_links++;
        $ext_data[$e_links]['inorout'] = $lang['AN53'];
        $ext_data[$e_links]['href'] = $link['href'];
        $ext_data[$e_links]['text'] = $link['innertext'];
        if ($type == 'dofollow' || ($type != 'dofollow' && $type != 'nofollow'))
            $ext_data[$e_links]['follow_type'] = "dofollow";
        if ($type == 'nofollow') {
            $e_nofollow++;
            $ext_data[$e_links]['follow_type'] = "nofollow";
        }
    }
}


foreach($int_data as $internalData){
    if($totalDataCount == 5)
        $hideMe = 'hideTr hideTr4';
    $inPageData.= '<tr class="'.$hideMe.'"><td><a target="_blank" href="'.$internalData['href'].'" title="'.$internalData['text'].'" rel="nofollow">'.($internalData['text']=='' ? $internalData['href'] : $internalData['text']).'</a></td><td>'.$internalData['inorout'].'</td><td>'.ucfirst($internalData['follow_type']).'</td></tr>';
    $totalDataCount++;
}

foreach($ext_data as $externalData){
    if($totalDataCount == 5)
        $hideMe = 'hideTr hideTr4';
    $inPageData.= '<tr class="'.$hideMe.'"><td><a target="_blank" href="'.$externalData['href'].'" title="'.$externalData['text'].'" rel="nofollow">'.($externalData['text']=='' ? $externalData['href'] : $externalData['text']).'</a></td><td>'.$externalData['inorout'].'</td><td>'.ucfirst($externalData['follow_type']).'</td></tr>';
    $totalDataCount++;
}

if($t_count < 200)
    $inPageClass = 'passedBox';
    
$inPageMsg = str_replace('[count]',$t_count,$lang['AN57']);

if($linkUnderScore){
    $linkUnderScoreClass = 'errorBox';
    $linkUnderScoreMsg = $lang['AN65'];
} else{
    $linkUnderScoreClass = 'passedBox';
    $linkUnderScoreMsg = $lang['AN64'];
}

if($urlRewriting){
    $urlRewritingClass = 'passedBox';
    $urlRewritingMsg = $lang['AN66'];
}else{
    $urlRewritingClass  = 'errorBox';
    $urlRewritingMsg = $lang['AN67'];
}
    
$seoBox13 = '<div class="'.$inPageClass.'">
<div class="msgBox">       
     '.$inPageMsg.'
    <br /><br />
    <table class="table table-responsive">
        <thead>
            <tr>
            <th>'.$lang['AN54'].'</th>
            <th>'.$lang['AN55'].'</th>
            <th>'.$lang['AN56'].'</th>
            </tr>
        </thead>
        <tbody>
            '.$inPageData.'
	   </tbody>
    </table>
    
    '.(($totalDataCount > 5)? '
        <div class="showLinks showLinks4">
            <a class="showMore showMore4">'.$lang['AN18'].' <br /> <i class="fa fa-angle-double-down"></i></a>
            <a class="showLess showLess4"><i class="fa fa-angle-double-up"></i> <br /> '.$lang['AN19'].'</a>
    </div>' : '').'
    
</div>
<div class="seoBox13 suggestionBox">
'.$in_pageMsg.'
</div> 
</div>';

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
$serverIP_Msg = $lang['AN208'];
$serverIPClass = 'lowImpactBox';

$getHostIP = gethostbyname($my_url_host);
$data_list = decSerBase($data['server_loc']);
$domain_ip = $data_list[0];
$domain_country =  $data_list[1];
$domain_isp = $data_list[2];
            
$seoBox36 = '<div class="'.$serverIPClass.'">
<div class="msgBox">   
    <table class="table table-hover table-bordered table-striped">
        <tbody>
            <tr> 
                <th>'.$lang['AN141'].'</th> 
                <th>'.$lang['AN142'].'</th>
                <th>'.$lang['AN143'].'</th>
            </tr> 
            <tr> 
                <td>'.$getHostIP.'</td> 
                <td>'.$domain_country.'</td>
                <td>'.$domain_isp.'</td>
            </tr> 
        </tbody>
    </table>
    <br />
</div>
<div class="seoBox36 suggestionBox">
'.$serverIP_Msg.'
</div> 
</div>';

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


//Social Data
$social_Msg = $lang['AN216'];
$socialMsg = '';
$socialClass = 'lowImpactBox';

$social_count = decSerBase($data['social']);
$facebook_like = $social_count[0];
$twit_count = $social_count[1];
$insta_count = $social_count[2];

if($facebook_like === '-')
    $facebook_like = $false;
else
    $facebook_like = $true.' '.$facebook_like;

if($twit_count === '-')
    $twit_count = $false;
else
    $twit_count = $true.' '.$twit_count;

if($insta_count === '-')
    $insta_count = $false;
else
    $insta_count = $true.' '.$insta_count;

$socialMsg = $lang['AN167'];  
        
$seoBox44 = '<div class="'.$socialClass.'">
<div class="msgBox">   
        '.$socialMsg.'
    <br />
    <div class="altImgGroup">
        <br><div class="social-box"><i class="fa fa-facebook social-facebook"></i> Facebook: '.$facebook_like.'</div><br>
        <div class="social-box"><i class="fa fa-twitter social-linkedin"></i> Twitter: '.$twit_count.' </div><br>
        <div class="social-box"><i class="fa fa-instagram social-google"></i> Instagram: '.$insta_count.'</div>
    </div>
    <br />
</div>
<div class="seoBox44 suggestionBox">
'.$social_Msg.'
</div> 
</div>';

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