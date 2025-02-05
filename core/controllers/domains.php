<?php
/**
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

defined('APP_NAME') or die(header('HTTP/1.1 403 Forbidden'));

// Define the temporary directory constant
define('TEMP_DIR', APP_DIR . 'temp' . D_S);

 
/*
 * ---------------------------------------------------------------------
 * GET REQUEST HANDLER
 * ---------------------------------------------------------------------
 */

// If a GET request has the 'getImage' parameter set, return a base64-encoded website thumbnail.
if (isset($_GET['getImage'])) {
    // If a custom snapshot link is available in session, use it.
    if (isset($_SESSION['snap'])) {
        $customSnapAPI = true;
        $customSnapLink = $_SESSION['snap'];
    }
    // Close session writing to prevent lock issues
    session_write_close();

    // Clean and prepare the requested site URL
    $my_url = clean_url(raino_trim($_GET['site']));

    // Get image data by generating a snapshot from the site
    $imageData = getMyData(getSiteSnap($my_url, $item_purchase_code, $baseLink, $customSnapAPI, $customSnapLink));
    
    // Clean any output buffers and return the base64 encoded image data
    ob_clean();
    echo base64_encode($imageData);
    die();
}

// Prepare a login box HTML string (to be used when the user is not logged in and a stat is not allowed)
$seoBoxLogin = '<div class="lowImpactBox">
<div class="msgBox">   
        ' . $lang['39'] . '
        
    <br /><br /> <div class="altImgGroup"> <a class="btn btn-success forceLogin" target="_blank" href="' . createLink('account/login', true) . '" title="' . $lang['40'] . '"> ' . $lang['40'] . ' </a></div><br />
</div>
</div>';

/*
 * ---------------------------------------------------------------------
 * POST REQUEST HANDLER
 * ---------------------------------------------------------------------
 *
 * This block handles various POST requests by checking if a particular key is set in $_POST.
 * Each section processes a specific SEO test and returns HTML output. It also updates the database
 * with the processed results.
 */
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Retrieve and sanitize the website URL from POST data.
    $my_url = 'http://' . clean_url(raino_trim($_POST['url']));
    // Get the unique hash code used to store/retrieve cached data.
    $hashCode = raino_trim($_POST['hashcode']);

    // Construct the filename where the source data is stored.
    $filename = TEMP_DIR . $hashCode . '.tdata';

    // Define a separator string used to split output sections.
    $sepUnique = '!!!!8!!!!';

    // Parse the input URL to extract the scheme and host.
    $my_url_parse = parse_url($my_url);
    $inputHost = $my_url_parse['scheme'] . "://" . $my_url_parse['host'];
    // Remove "www." for a normalized host string.
    $my_url_host = str_replace("www.", "", $my_url_parse['host']);
    // Prepare a normalized domain string for database storage.
    $domainStr = escapeTrim($con, strtolower($my_url_host));

    // Define HTML for a "true" or "false" image icon using theme links.
    $true = '<img src="' . themeLink('img/true.png', true) . '" alt="' . trans('True', $lang['10'], true) . '" />';
    $false = '<img src="' . themeLink('img/false.png', true) . '" alt="' . trans('False', $lang['9'], true) . '" />';

    // Retrieve the stored source data (the HTML source of the website).
    $sourceData = getMyData($filename);

    // Fix uppercase meta tag issues by replacing various cases with lower-case equivalents.
    $html = str_ireplace(array("Title", "TITLE"), "title", $sourceData);
    $html = str_ireplace(array("Description", "DESCRIPTION"), "description", $html);
    $html = str_ireplace(array("Keywords", "KEYWORDS"), "keywords", $html);
    $html = str_ireplace(array("Content", "CONTENT"), "content", $html);
    $html = str_ireplace(array("Meta", "META"), "meta", $html);
    $html = str_ireplace(array("Name", "NAME"), "name", $html);

    // If no source data is found, exit with an error message.
    if ($sourceData == '')
        die($lang['AN10']);

    /*
     * -----------------------------------------------------------------
     * META DATA HANDLER
     * -----------------------------------------------------------------
     * If 'meta' is set in POST, extract the page title, description, and keywords
     * from the HTML source and update the database.
     */
    if (isset($_POST['meta'])) {

        $title = $description = $keywords = '';
        $doc = new DOMDocument();
        // Suppress warnings and load HTML (converted to proper HTML entities)
        @$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $nodes = $doc->getElementsByTagName('title');
        $title = $nodes->item(0)->nodeValue;
        $metas = $doc->getElementsByTagName('meta');

        // Loop through meta tags and extract description and keywords.
        for ($i = 0; $i < $metas->length; $i++) {
            $meta = $metas->item($i);
            if ($meta->getAttribute('name') == 'description')
                $description = $meta->getAttribute('content');
            if ($meta->getAttribute('name') == 'keywords')
                $keywords = $meta->getAttribute('content');
        }

        // Serialize meta data for database storage.
        $updateStr = serBase(array($title, $description, $keywords));
        updateToDbPrepared($con, 'domains_data', array('meta_data' => $updateStr), array('domain' => $domainStr));

        // Calculate lengths of title and description.
        $lenTitle = mb_strlen($title, 'utf8');
        $lenDes = mb_strlen($description, 'utf8');

        // Set default messages if any data is missing.
        $site_title = ($title == '' ? $lang['AN11'] : $title);
        $site_description = ($description == '' ? $lang['AN12'] : $description);
        $site_keywords = ($keywords == '' ? $lang['AN15'] : $keywords);

        // If metaOut is set, output the results with suggestions.
        if (isset($_POST['metaOut'])) {

            $titleMsg = $lang['AN173'];
            $desMsg = $lang['AN174'];
            $keyMsg = $lang['AN175'];
            $googleMsg = $lang['AN177'];

            if ($lenTitle < 10)
                $classTitle = 'improveBox';
            elseif ($lenTitle < 70)
                $classTitle = 'passedBox';
            else
                $classTitle = 'errorBox';

            if ($lenDes < 70)
                $classDes = 'improveBox';
            elseif ($lenDes < 300)
                $classDes = 'passedBox';
            else
                $classDes = 'errorBox';

            $classKey = 'lowImpactBox';

            // Check if the user is logged in or if the stats can be shown
            if (!isset($_SESSION['twebUsername'])) {
                if (!isAllowedStats($con, 'seoBox1')) {
                    die($seoBoxLogin . $sepUnique . $seoBoxLogin . $sepUnique . $seoBoxLogin . $sepUnique . $seoBoxLogin);
                }
            }

            // Output the title, description, keywords, and Google preview in separate HTML blocks.
            echo '<div class="' . $classTitle . '">
    <div class="msgBox bottom10">       
    ' . $site_title . '
    <br />
    <b>' . $lang['AN13'] . ':</b> ' . $lenTitle . ' ' . $lang['AN14'] . ' 
    </div>
    <div class="seoBox1 suggestionBox">
    ' . $titleMsg . '
    </div> 
    </div>';

            echo $sepUnique; // Separate the sections

            echo '<div class="' . $classDes . '">
    <div class="msgBox padRight10 bottom10">       
    ' . $site_description . '
    <br />
    <b>' . $lang['AN13'] . ':</b> ' . $lenDes . ' ' . $lang['AN14'] . ' 
    </div>
    <div class="seoBox2 suggestionBox">
    ' . $desMsg . '
    </div> 
    </div>';

            echo $sepUnique; // Separate

            echo '<div class="' . $classKey . '">
    <div class="msgBox padRight10">       
    ' . $site_keywords . '
    <br /><br />
    </div>
    <div class="seoBox3 suggestionBox">
    ' . $keyMsg . '
    </div> 
    </div>';

            echo $sepUnique; // Separate

            echo '<div class="' . $classKey . '">
    <div class="msgBox">       
         <div class="googlePreview">
    		<p>' . $site_title . '</p>
    		<p><span class="bold">' . $my_url_parse['host'] . '</span>/</p>
    		<p>' . $site_description . '</p>
        </div>
    <br />
    </div>
    <div class="seoBox5 suggestionBox">
    ' . $googleMsg . '
    </div> 
    </div>';

            die();
        }
    } // End meta handler


    /*
     * -----------------------------------------------------------------
     * HEADING DATA HANDLER
     * -----------------------------------------------------------------
     * Extract all headings (H1-H6) from the HTML source, count them, and output them.
     */
    if (isset($_POST['heading'])) {
        $doc = new DOMDocument();
        @$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));

        // List of heading tags to process.
        $tags = array('h1', 'h2', 'h3', 'h4', 'h5', 'h6');
        $h1Count = $h2Count = $h3Count = $h4Count = $h5Count = $h6Count = 0;
        $elementListData = $texts = array();
        $hideCount = 0;
        $hideClass = $headStr = '';

        // Loop through each heading tag and build output.
        foreach ($tags as $tag) {
            $elementList = $doc->getElementsByTagName($tag);
            foreach ($elementList as $element) {
                if ($hideCount == 3)
                    $hideClass = 'hideTr hideTr1';
                // Get the text content and remove any extra whitespace.
                $headContent = strip_tags($element->textContent);
                $texts[$element->tagName][] = $headContent;
                if (strlen($headContent) >= 100)
                    $headStr .= '<tr class="' . $hideClass . '"> <td>&lt;' . strtoupper($element->tagName) . '&gt; <b>' . truncate($headContent, 20, 100) . '</b> &lt;/' . strtoupper($element->tagName) . '&gt;</td> </tr>';
                else
                    $headStr .= '<tr class="' . $hideClass . '"> <td>&lt;' . strtoupper($element->tagName) . '&gt; <b>' . $headContent . '</b> &lt;/' . strtoupper($element->tagName) . '&gt;</td> </tr>';
                $elementListData[$tag][] = array(strtoupper($element->tagName), $headContent);
                $hideCount++;
            }
        }

        // Serialize and update heading data in the database.
        $updateStr = serBase(array($elementListData, $texts));
        updateToDbPrepared($con, 'domains_data', array('headings' => $updateStr), array('domain' => $domainStr));

        // If output is requested, display the heading counts and table.
        if (isset($_POST['headingOut'])) {

            $headMsg = $lang['AN176'];

            $h1Count = isset($texts['h1']) ? count($texts['h1']) : 0;
            $h2Count = isset($texts['h2']) ? count($texts['h2']) : 0;
            $h3Count = isset($texts['h3']) ? count($texts['h3']) : 0;
            $h4Count = isset($texts['h4']) ? count($texts['h4']) : 0;
            $h5Count = isset($texts['h5']) ? count($texts['h5']) : 0;
            $h6Count = isset($texts['h6']) ? count($texts['h6']) : 0;

            // Determine class based on the counts of H1 and H2 tags.
            if ($h1Count > 2)
                $class = 'improveBox';
            elseif ($h1Count != 0 && $h2Count != 0)
                $class = 'passedBox';
            else
                $class = 'errorBox';

            // Check user authentication/permission before showing the stats.
            if (!isset($_SESSION['twebUsername'])) {
                if (!isAllowedStats($con, 'seoBox4')) {
                    die($seoBoxLogin);
                }
            }

            // Output a table with counts for each heading tag and the list of headings.
            echo '<div class="' . $class . '">
            <div class="msgBox">       
            <table class="table table-striped table-responsive centerTable">
    			<thead>
    				<tr>
                		<th>&lt;H1&gt;</th>
                        <th>&lt;H2&gt;</th>
                        <th>&lt;H3&gt;</th>
                        <th>&lt;H4&gt;</th>
                        <th>&lt;H5&gt;</th>
                        <th>&lt;H6&gt;</th>
          			</tr>
    		    </thead>
      			<tbody>
                    <tr>
            			<td>' . $h1Count . '</td>
                        <td>' . $h2Count . '</td>
                        <td>' . $h3Count . '</td>
                        <td>' . $h4Count . '</td>
                        <td>' . $h5Count . '</td>
                        <td>' . $h6Count . '</td>
                    </tr>
               </tbody>
            </table>
            
            <table class="table table-striped table-responsive">
                <tbody>
                    ' . $headStr . '
        	   </tbody>
            </table>
            ' . (($hideCount > 3) ? '
            <div class="showLinks showLinks1">
                <a class="showMore showMore1">' . $lang['AN18'] . ' <br /> <i class="fa fa-angle-double-down"></i></a>
                <a class="showLess showLess1"><i class="fa fa-angle-double-up"></i> <br /> ' . $lang['AN19'] . '</a>
            </div>' : '') . '
            
            <br />
            </div>
    <div class="seoBox4 suggestionBox">
    ' . $headMsg . '
    </div> 
    </div>';
            die();
        }
    } // End heading handler

    /*
     * -----------------------------------------------------------------
     * LOAD DOM LIBRARY (if needed)
     * -----------------------------------------------------------------
     * When the 'loaddom' key is set in POST, load the simple_html_dom library
     * and parse the source HTML into a DOM object.
     */
    if (isset($_POST['loaddom'])) {
        // Include the simple HTML DOM library
        require_once(LIB_DIR . "simple_html_dom.php");
        // Parse the HTML source into a DOM object for further analysis.
        $domData = load_html($sourceData);
    }

    /*
     * -----------------------------------------------------------------
     * IMAGE ALT TAG CHECKER
     * -----------------------------------------------------------------
     * Checks for images in the DOM that are missing the "alt" attribute.
     * It counts all valid images and those missing the alt attribute, then outputs the result.
     */
    if (isset($_POST['image'])) {

        $imageCount = 0;
        $imageWithOutAltTag = 0;
        $hideClass = $imageWithOutAltTagData = '';
        $imageMsg = $lang['AN178'];
        $imgArr = array();

        // If the DOM has been loaded, process all <img> tags.
        if (!empty($domData)) {
            $domContent = $domData->find('img');
            if (!empty($domContent)) {
                foreach ($domContent as $imgData) {
                    if (Trim($imgData->getAttribute('src')) != "") {
                        // Count this as a valid image.
                        $imageCount++;
                        if (Trim($imgData->getAttribute('alt')) == "") {
                            // Image without alt tag
                            if ($imageWithOutAltTag == 3) $hideClass = 'hideTr hideTr2';
                            $imageWithOutAltTagData .= '<tr class="' . $hideClass . '"> <td>' . Trim($imgData->getAttribute('src')) . '</td> </tr>';
                            $imgArr[] = Trim($imgData->getAttribute('src'));
                            $imageWithOutAltTag++;
                        }
                    }
                }
            }
        }

        // Update database with the count of images and those missing alt tags.
        $updateStr = serBase(array($imageCount, $imageWithOutAltTag, $imgArr));
        updateToDbPrepared($con, 'domains_data', array('image_alt' => $updateStr), array('domain' => $domainStr));

        // Determine CSS class based on how many images are missing alt text.
        if ($imageWithOutAltTag == 0)
            $altClass = 'passedBox';
        elseif ($imageWithOutAltTag < 2)
            $altClass = 'improveBox';
        else
            $altClass = 'errorBox';

        // Free memory by setting the DOM variable to null.
        $domData = null;

        // Check if the user is allowed to view this data.
        if (!isset($_SESSION['twebUsername'])) {
            if (!isAllowedStats($con, 'seoBox6')) {
                die($seoBoxLogin);
            }
        }

        // Output the results in HTML format.
        echo '<div class="' . $altClass . '">
    <div class="msgBox">       
        ' . str_replace('[image-count]', $imageCount, $lang['AN21']) . ' <br />
        <div class="altImgGroup"> 
        ' . (($imageWithOutAltTag == 0) ? '
        <img src="' . $theme_path . 'img/true.png" alt="' . $lang['AN24'] . '" title="' . $lang['AN25'] . '" /> ' . $lang['AN27'] . '<br />' : ' 
        <img src="' . $theme_path . 'img/false.png" alt="' . $lang['AN23'] . '" title="' . $lang['AN22'] . '" />
         ' . str_replace('[missing-alt-tag]', $imageWithOutAltTag, $lang['AN26']) . '
        </div>
        <br />
        <table class="table table-striped table-responsive">
            <tbody>
                  ' . $imageWithOutAltTagData . '
    	   </tbody>
        </table>') . '
        
        ' . (($imageWithOutAltTag > 3) ? '
        <div class="showLinks showLinks2">
            <a class="showMore showMore2">' . $lang['AN18'] . ' <br /> <i class="fa fa-angle-double-down"></i></a>
            <a class="showLess showLess2"><i class="fa fa-angle-double-up"></i> <br /> ' . $lang['AN19'] . '</a>
        </div>' : '') . '
        
        <br />
    </div>
    <div class="seoBox6 suggestionBox">
    ' . $imageMsg . '
    </div> 
    </div>';
        die();
    } // End image alt tag handler

    /*
     * -----------------------------------------------------------------
     * KEYWORD CLOUD GENERATOR
     * -----------------------------------------------------------------
     * Process the keyword cloud by analyzing the source data for keywords,
     * filtering out unwanted characters and words, and then output a list.
     */
    if (isset($_POST['keycloud'])) {
        $obj = new KD();
        $obj->domain = $my_url;
        $obj->domainData = $sourceData;
        $resdata = $obj->result();
        $keyData = '';
        $blockChars = $blockWords = $outArr = array();
        $keyCount = 0;

        // Process each keyword from the results.
        foreach ($resdata as $outData) {
            if (isset($outData['keyword'])) {
                $outData['keyword'] = Trim($outData['keyword']);
                if ($outData['keyword'] != null || $outData['keyword'] != "") {

                    // Block unwanted characters and words.
                    $blockChars = array('~', '=', '+', '?', ':', '_', '[', ']', '"', '.', '!', '@', '#', '$', '%', '^', '&', '*', '(', ')', '<', '>', '{', '}', '|', '\\', '/', ',');
                    $blockWords = array('and', 'is', 'was', 'to', 'into', 'with', 'without', 'than', 'then', 'that', 'these', 'this', 'their', 'them', 'from', 'your', 'able', 'which', 'when', 'what', 'who');
                    $blockCharsBol = false;
                    foreach ($blockChars as $blockChar) {
                        if (check_str_contains($outData['keyword'], $blockChar)) {
                            $blockCharsBol = true;
                            break;
                        }
                    }

                    // Check if keyword contains numbers or is a blocked word.
                    if (!preg_match('/[0-9]+/', $outData['keyword'])) {
                        if (!$blockCharsBol) {
                            if (!in_array($outData['keyword'], $blockWords)) {
                                if ($keyCount == 15)
                                    break;
                                $outArr[] = array($outData['keyword'], $outData['count'], $outData['percent']);
                                $keyData .= '<li><span class="keyword">' . $outData['keyword'] . '</span><span class="number">' . $outData['count'] . '</span></li>';
                                $keyCount++;
                            }
                        }
                    }
                }
            }
        }
        $outCount = count($outArr);

        // Update the keyword cloud data in the database.
        $updateStr = serBase(array($outCount, $outArr));
        updateToDbPrepared($con, 'domains_data', array('keywords_cloud' => $updateStr), array('domain' => $domainStr));

        // If keycloudOut is set, output the keyword cloud.
        if (isset($_POST['keycloudOut'])) {

            $keycloudClass = 'lowImpactBox';
            $keyMsg = $lang['AN179'];

            if (!isset($_SESSION['twebUsername'])) {
                if (!isAllowedStats($con, 'seoBox7')) {
                    die($seoBoxLogin);
                }
            }

            echo '<div class="' . $keycloudClass . '">
    <div class="msgBox padRight10 bottom5">       
         ' . (($outCount != 0) ? '
        <ul class="keywordsTags">
              ' . $keyData . '  
        </ul>' : ' ' . $lang['AN29']) . '  
    </div>
    <div class="seoBox7 suggestionBox">
    ' . $keyMsg . '
    </div> 
    </div>';
            die();
        }
    } // End keyword cloud handler

    /*
     * -----------------------------------------------------------------
     * KEYWORD CONSISTENCY CHECKER
     * -----------------------------------------------------------------
     * Checks whether the keywords appear in the title, description, and headings.
     * It then outputs a table with the consistency status.
     */
    if (isset($_POST['keyConsistency'])) {

        $hideClass = $keywordConsistencyTitle = $keywordConsistencyDes = $keywordConsistencyH = $keywordConsistencyData = '';
        $hideCount = 1;
        $keywordConsistencyScore = 0;

        // Loop through each keyword from the previously built keyword array.
        foreach ($outArr as $outKey) {
            // Check if the keyword exists in the title.
            if (check_str_contains($title, $outKey[0], true)) {
                $keywordConsistencyTitle = $true;
                $keywordConsistencyScore++;
            } else {
                $keywordConsistencyTitle = $false;
            }

            // Check if the keyword exists in the description.
            if (check_str_contains($description, $outKey[0], true)) {
                $keywordConsistencyDes = $true;
                $keywordConsistencyScore++;
            } else {
                $keywordConsistencyDes = $false;
            }

            // Check if the keyword exists in any heading.
            $keywordConsistencyH = $false;
            foreach ($texts as $htags) {
                foreach ($htags as $htag) {
                    if (check_str_contains($htag, $outKey[0], true)) {
                        $keywordConsistencyH = $true;
                        break 2;
                    }
                }
            }

            if ($hideCount == 5)
                $hideClass = 'hideTr hideTr3';

            $keywordConsistencyData .= '<tr class="' . $hideClass . '"> 
                <td>' . $outKey[0] . '</td> 
                <td>' . $outKey[1] . '</td> 
                <td>' . $keywordConsistencyTitle . '</td>
                <td>' . $keywordConsistencyDes . '</td>
                <td>' . $keywordConsistencyH . '</td>   
                </tr>';
            $hideCount++;
        }

        // Determine the CSS class based on the overall consistency score.
        if ($keywordConsistencyScore == 0)
            $keywordConsistencyClass = 'errorBox';
        elseif ($keywordConsistencyScore < 4)
            $keywordConsistencyClass = 'improveBox';
        else
            $keywordConsistencyClass = 'passedBox';

        $keyMsg = $lang['AN180'];

        if (!isset($_SESSION['twebUsername'])) {
            if (!isAllowedStats($con, 'seoBox8')) {
                die($seoBoxLogin);
            }
        }

        // Output the keyword consistency table.
        echo '<div class="' . $keywordConsistencyClass . '">
    <div class="msgBox">       
        <table class="table table-striped table-responsive">
		    <thead>
				<tr>
            		<th>' . $lang['AN31'] . '</th>
                    <th>' . $lang['AN32'] . '</th>
                    <th>' . $lang['AN33'] . '</th>
                    <th>' . $lang['AN34'] . '</th>
                    <th>&lt;H&gt;</th>
      			</tr>
		    </thead>
            <tbody>
                ' . $keywordConsistencyData . '
    	   </tbody>
        </table>
        
        ' . (($hideCount > 5) ? '
            <div class="showLinks showLinks3">
                <a class="showMore showMore3">' . $lang['AN18'] . ' <br /> <i class="fa fa-angle-double-down"></i></a>
                <a class="showLess showLess3"><i class="fa fa-angle-double-up"></i> <br /> ' . $lang['AN19'] . '</a>
        </div>' : '') . '
        
    </div>
    <div class="seoBox8 suggestionBox">
    ' . $keyMsg . '
    </div> 
    </div>';
        die();
    } // End keyword consistency handler

    /*
     * -----------------------------------------------------------------
     * TEXT TO HTML RATIO CALCULATOR
     * -----------------------------------------------------------------
     * Calculates the ratio of text to HTML code in the source data.
     */
    if (isset($_POST['textRatio'])) {
        $textRatio = calTextRatio($sourceData);

        // Update database with text ratio data.
        $updateStr = serBase($textRatio);
        updateToDbPrepared($con, 'domains_data', array('ratio_data' => $updateStr), array('domain' => $domainStr));

        // Set CSS class based on the calculated text ratio.
        if (round($textRatio[2]) < 2)
            $textClass = 'errorBox';
        elseif (round($textRatio[2]) < 10)
            $textClass = 'improveBox';
        else
            $textClass = 'passedBox';

        $textMsg = $lang['AN181'];

        if (!isset($_SESSION['twebUsername'])) {
            if (!isAllowedStats($con, 'seoBox9')) {
                die($seoBoxLogin);
            }
        }

        // Output the text ratio results and related details.
        echo '<div class="' . $textClass . '">
    <div class="msgBox">       
        ' . $lang['AN36'] . ': <b>' . round($textRatio[2], 2) . '%</b><br />
        <br />
        <table class="table table-responsive">
            <tbody>
                <tr> 
                <td>' . $lang['AN37'] . '</td> 
                <td>' . $textRatio[1] . ' ' . $lang['AN39'] . '</td> 
                </tr>
                
                <tr> 
                <td>' . $lang['AN38'] . '</td> 
                <td>' . $textRatio[0] . ' ' . $lang['AN39'] . '</td>  
                </tr>
    	   </tbody>
        </table>
    </div>
    <div class="seoBox9 suggestionBox">
    ' . $textMsg . '
    </div> 
    </div>';
        die();
    } // End text ratio handler

    /*
     * -----------------------------------------------------------------
     * GZIP COMPRESSION TEST
     * -----------------------------------------------------------------
     * Checks whether the website is using GZIP compression and outputs the results.
     */
    if (isset($_POST['gzip'])) {

        $gzipClass = $gzipHead = $gzipBody = '';
        $outData = compressionTest($my_url_host);

        $comSize = $outData[0];
        $unComSize = $outData[1];
        $isGzip = $outData[2];
        $gzdataSize = $outData[3];
        $header = $outData[4];
        $body = Trim($outData[5]);

        if ($body == "") {
            $gzipHead = $lang['AN10'];
            $gzipClass = 'improveBox';
        } else {
            $body = 'Data!';
            if ($isGzip) {
                $percentage = round(((((int)$unComSize - (int)$comSize) / (int)$unComSize) * 100), 1);
                $gzipClass = 'passedBox';
                $gzipHead = $lang['AN42'];
                $gzipBody = $true . ' ' . str_replace(array('[total-size]', '[compressed-size]', '[percentage]'), array(size_as_kb($unComSize), size_as_kb($comSize), $percentage), $lang['AN41']);
            } else {
                $percentage = round(((((int)$unComSize - (int)$gzdataSize) / (int)$unComSize) * 100), 1);
                $gzipClass = 'errorBox';
                $gzipHead = $lang['AN43'];
                $gzipBody = $false . ' ' . str_replace(array('[total-size]', '[compressed-size]', '[percentage]'), array(size_as_kb($unComSize), size_as_kb($gzdataSize), $percentage), $lang['AN44']);
            }
        }
        $header = 'Data!';

        // Update database with gzip test data.
        $updateStr = serBase(array($outData[0], $outData[1], $outData[2], $outData[3], $header, $body));
        updateToDbPrepared($con, 'domains_data', array('gzip' => $updateStr), array('domain' => $domainStr));

        $gzipMsg = $lang['AN182'];

        if (!isset($_SESSION['twebUsername'])) {
            if (!isAllowedStats($con, 'seoBox10')) {
                die($seoBoxLogin);
            }
        }

        // Output the gzip compression test results.
        echo '<div class="' . $gzipClass . '">
    <div class="msgBox">       
         ' . $gzipHead . '
        <br />
        <div class="altImgGroup">
            ' . $gzipBody . '
        </div>
        <br />
    </div>
    <div class="seoBox10 suggestionBox">
    ' . $gzipMsg . '
    </div> 
    </div>';
        die();
    } // End gzip handler

    /*
     * -----------------------------------------------------------------
     * WWW RESOLVE CHECK
     * -----------------------------------------------------------------
     * Determines whether the site resolves correctly with and without "www".
     */
    if (isset($_POST['www_resolve'])) {

        $www_resolveMsg = $lang['AN183'];
        $resolveClass = 'improveBox';
        $resolveMsg = $lang['AN47'];
        $re301 = false;
        $url_with_www = "http://www.$my_url_host";
        $url_no_www = "http://$my_url_host";

        // Get HTTP status codes for both URLs.
        $data1 = getHttpCode($url_with_www, false);
        $data2 = getHttpCode($url_no_www, false);

        $updateStr = serBase(array($data1, $data2));
        updateToDbPrepared($con, 'domains_data', array('resolve' => $updateStr), array('domain' => $domainStr));

        // If either URL returns a 301 redirect, mark as passed.
        if ($data1 == '301') {
            $re301 = true;
            $resolveClass = 'passedBox';
            $resolveMsg = $lang['AN46'];
        }

        if ($data2 == '301') {
            $re301 = true;
            $resolveClass = 'passedBox';
            $resolveMsg = $lang['AN46'];
        }

        if (!isset($_SESSION['twebUsername'])) {
            if (!isAllowedStats($con, 'seoBox11')) {
                die($seoBoxLogin);
            }
        }

        // Output the resolution status.
        echo '<div class="' . $resolveClass . '">
    <div class="msgBox">       
         ' . $resolveMsg . '
        <br />
        <br />
    </div>
    <div class="seoBox11 suggestionBox">
    ' . $www_resolveMsg . '
    </div> 
    </div>';
        die();
    } // End www resolve handler

    /*
     * -----------------------------------------------------------------
     * IP CANONICALIZATION CHECK
     * -----------------------------------------------------------------
     * Checks if accessing the site via its IP address redirects to the correct domain.
     */
    if (isset($_POST['ip_can'])) {

        $ip_canMsg = $lang['AN184'];
        $ipClass = 'improveBox';
        $hostIP = $ipMsg = $redirectURLhost = '';
        $tType = false;

        // Get the IP address for the domain.
        $hostIP = gethostbyname($my_url_host);
        $ch = curl_init($hostIP);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        $response = curl_exec($ch);
        preg_match_all('/^Location:(.*)$/mi', $response, $matches);
        curl_close($ch);

        if (!empty($matches[1])) {
            $redirectURL = 'http://' . clean_url(trim($matches[1][0]));
            $redirectURLparse = parse_url($redirectURL);
            if (!isset($redirectURLparse['host']))
                $redirectURLparse['host'] = '';
            $redirectURLhost = str_replace('www.', '', $redirectURLparse['host']);
            if ($my_url_host == $redirectURLhost) {
                $ipMsg = str_replace(array('[ip]', '[host]'), array($hostIP, $my_url_host), $lang['AN50']);
                $ipClass = 'passedBox';
            } else {
                $ipMsg = str_replace(array('[ip]', '[host]'), array($hostIP, $my_url_host), $lang['AN49']);
            }
            $tType = true;
        } else {
            $ipMsg = str_replace(array('[ip]', '[host]'), array($hostIP, $my_url_host), $lang['AN49']);
            $tType = false;
        }

        $updateStr = serBase(array($hostIP, $tType, $my_url_host, $redirectURLhost));
        updateToDbPrepared($con, 'domains_data', array('ip_can' => $updateStr), array('domain' => $domainStr));

        if (!isset($_SESSION['twebUsername'])) {
            if (!isAllowedStats($con, 'seoBox12')) {
                die($seoBoxLogin);
            }
        }

        // Output the IP canonicalization results.
        echo '<div class="' . $ipClass . '">
    <div class="msgBox">       
         ' . $ipMsg . '
        <br />
        <br />
    </div>
    <div class="seoBox12 suggestionBox">
    ' . $ip_canMsg . '
    </div> 
    </div>';
        die();
    } // End IP canonicalization handler

    /*
     * -----------------------------------------------------------------
     * IN-PAGE LINKS ANALYSER
     * -----------------------------------------------------------------
     * Analyzes all internal and external links on the page, checks for URL rewriting
     * and underscores in URLs, and outputs the results.
     */
    if (isset($_POST['in_page'])) {

        $in_pageMsg = $lang['AN185'];
        $link_UnderScoreMsg = $lang['AN190'];
        $url_RewritingMsg = $lang['AN189'];
        $inPageClass = 'improveBox';
        $urlRewritingClass = $urlRewritingMsg = $linkUnderScoreMsg = $linkUnderScoreClass = $hideMe = $inPageData = $inPageMsg = '';
        $totalDataCount = 0;

        // Define variables for collecting link data.
        $ex_data_arr = $ex_data = array();
        $t_count = $i_links = $e_links = $i_nofollow = $e_nofollow = 0;

        // Default values for URL rewriting and underscore check.
        $urlRewriting = true;
        $webFormats = array('html', 'htm', 'xhtml', 'xht', 'mhtml', 'mht', 'asp', 'aspx', 'cgi', 
            'ihtml', 'jsp', 'las', 'pl', 'php', 'php3', 'phtml', 'shtml');
        $linkUnderScore = false;

        // If the DOM has been loaded, find all <a> tags.
        if (!empty($domData)) {
            $domContent = $domData->find("a");
            if (!empty($domContent)) {
                foreach ($domContent as $href) {
                    if (!in_array($href->href, $ex_data_arr)) {
                        if (substr($href->href, 0, 1) != "" && $href->href != "#") {
                            $ex_data_arr[] = $href->href;
                            $ex_data[] = array('href' => $href->href, 'rel' => $href->rel, 'innertext' => Trim(strip_tags($href->plaintext)));
                        }
                    }
                }
            }
        }

        // Save the links data in the database.
        $updateStr = serBase($ex_data);
        updateToDbPrepared($con, 'domains_data', array('links_analyser' => $updateStr), array('domain' => $domainStr));

        // Process internal links.
        foreach ($ex_data as $count => $link) {
            $t_count++;
            $parse_urls = parse_url($link['href']);
            $type = strtolower($link['rel']);
            $myIntHost = $path = '';
            if (isset($parse_urls['path']))
                $path = $parse_urls['path'];

            if (isset($parse_urls['host']))
                $myIntHost = $parse_urls['host'];

            if ($myIntHost == $my_url_host || $myIntHost == "www." . $my_url_host) {
                $i_links++;

                $int_data[$i_links]['inorout'] = $lang['AN52'];
                $int_data[$i_links]['href'] = $link['href'];
                $int_data[$i_links]['text'] = $link['innertext'];

                if (mb_strpos($link['href'], "_") !== false)
                    $linkUnderScore = true;

                $dotStr = $exStr = '';
                $exStr = explode('.', $path);
                $dotStr = Trim(end($exStr));
                if ($dotStr != $path) {
                    if (in_array($dotStr, $webFormats))
                        $urlRewriting = false;
                }

                if ($type == 'dofollow' || ($type != 'dofollow' && $type != 'nofollow'))
                    $int_data[$i_links]['follow_type'] = "dofollow";

                if ($type == 'nofollow') {
                    $i_nofollow++;
                    $int_data[$i_links]['follow_type'] = "nofollow";
                }
            } elseif ((substr($link['href'], 0, 2) != "//") && (substr($link['href'], 0, 1) == "/")) {
                $i_links++;
                $int_data[$i_links]['inorout'] = $lang['AN52'];
                $int_data[$i_links]['href'] = $inputHost . $link['href'];
                $int_data[$i_links]['text'] = $link['innertext'];

                if (mb_strpos($link['href'], "_") !== false)
                    $linkUnderScore = true;

                $dotStr = $exStr = '';
                $exStr = explode('.', $path);
                $dotStr = Trim(end($exStr));
                if ($dotStr != $path) {
                    if (in_array($dotStr, $webFormats))
                        $urlRewriting = false;
                }

                if ($type == 'dofollow' || ($type != 'dofollow' && $type != 'nofollow'))
                    $int_data[$i_links]['follow_type'] = "dofollow";

                if ($type == 'nofollow') {
                    $i_nofollow++;
                    $int_data[$i_links]['follow_type'] = "nofollow";
                }
            } else {
                if (substr($link['href'], 0, 7) != "http://" && substr($link['href'], 0, 8) != "https://" &&
                    substr($link['href'], 0, 2) != "//" && substr($link['href'], 0, 1) != "/" && substr($link['href'], 0, 1) != "#" &&
                    substr($link['href'], 0, 2) != "//" && substr($link['href'], 0, 6) != "mailto" && substr($link['href'], 0, 4) != "tel:" && substr($link['href'], 0, 10) != "javascript") {

                    $i_links++;
                    $int_data[$i_links]['inorout'] = $lang['AN52'];
                    $int_data[$i_links]['href'] = $inputHost . '/' . $link['href'];
                    $int_data[$i_links]['text'] = $link['innertext'];
                    if (mb_strpos($link['href'], "_") !== false)
                        $linkUnderScore = true;

                    $dotStr = $exStr = '';
                    $exStr = explode('.', $path);
                    $dotStr = Trim(end($exStr));
                    if ($dotStr != $path) {
                        if (in_array($dotStr, $webFormats))
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

        // Process external links.
        foreach ($ex_data as $count => $link) {
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

        // Free memory
        $domData = null;

        // If output is requested, generate HTML output for internal and external links.
        if (isset($_POST['inPageoutput'])) {
            foreach ($int_data as $internalData) {
                if ($totalDataCount == 5)
                    $hideMe = 'hideTr hideTr4';
                $inPageData .= '<tr class="' . $hideMe . '"><td><a target="_blank" href="' . $internalData['href'] . '" title="' . $internalData['text'] . '" rel="nofollow">' . ($internalData['text'] == '' ? $internalData['href'] : $internalData['text']) . '</a></td><td>' . $internalData['inorout'] . '</td><td>' . ucfirst($internalData['follow_type']) . '</td></tr>';
                $totalDataCount++;
            }

            foreach ($ext_data as $externalData) {
                if ($totalDataCount == 5)
                    $hideMe = 'hideTr hideTr4';
                $inPageData .= '<tr class="' . $hideMe . '"><td><a target="_blank" href="' . $externalData['href'] . '" title="' . $externalData['text'] . '" rel="nofollow">' . ($externalData['text'] == '' ? $externalData['href'] : $externalData['text']) . '</a></td><td>' . $externalData['inorout'] . '</td><td>' . ucfirst($externalData['follow_type']) . '</td></tr>';
                $totalDataCount++;
            }

            if ($t_count < 200)
                $inPageClass = 'passedBox';

            $inPageMsg = str_replace('[count]', $t_count, $lang['AN57']);

            if ($linkUnderScore) {
                $linkUnderScoreClass = 'errorBox';
                $linkUnderScoreMsg = $lang['AN65'];
            } else {
                $linkUnderScoreClass = 'passedBox';
                $linkUnderScoreMsg = $lang['AN64'];
            }

            if ($urlRewriting) {
                $urlRewritingClass = 'passedBox';
                $urlRewritingMsg = $lang['AN66'];
            } else {
                $urlRewritingClass  = 'errorBox';
                $urlRewritingMsg = $lang['AN67'];
            }

            $seoBox13 = '<div class="' . $inPageClass . '">
    <div class="msgBox">       
         ' . $inPageMsg . '
        <br /><br />
        <table class="table table-responsive">
            <thead>
                <tr>
                <th>' . $lang['AN54'] . '</th>
                <th>' . $lang['AN55'] . '</th>
                <th>' . $lang['AN56'] . '</th>
                </tr>
            </thead>
            <tbody>
                ' . $inPageData . '
    	   </tbody>
        </table>
        
        ' . (($totalDataCount > 5) ? '
            <div class="showLinks showLinks4">
                <a class="showMore showMore4">' . $lang['AN18'] . ' <br /> <i class="fa fa-angle-double-down"></i></a>
                <a class="showLess showLess4">' . $lang['AN19'] . '</a>
        </div>' : '') . '
        
    </div>
    <div class="seoBox13 suggestionBox">
    ' . $in_pageMsg . '
    </div> 
    </div>';

            $seoBox17 = '<div class="' . $urlRewritingClass . '">
    <div class="msgBox">       
         ' . $urlRewritingMsg . '
        <br />
        <br />
    </div>
    <div class="seoBox17 suggestionBox">
    ' . $url_RewritingMsg . '
    </div> 
    </div>';

            $seoBox18 = '<div class="' . $linkUnderScoreClass . '">
    <div class="msgBox">       
         ' . $linkUnderScoreMsg . '
        <br />
        <br />
    </div>
    <div class="seoBox18 suggestionBox">
    ' . $link_UnderScoreMsg . '
    </div> 
    </div>';

            if (!isset($_SESSION['twebUsername'])) {
                if (!isAllowedStats($con, 'seoBox13'))
                    $seoBox13 = $seoBoxLogin;

                if (!isAllowedStats($con, 'seoBox17'))
                    $seoBox17 = $seoBoxLogin;

                if (!isAllowedStats($con, 'seoBox18'))
                    $seoBox18 = $seoBoxLogin;
            }

            // Output the three sections separated by the unique separator.
            echo $seoBox13 . $sepUnique . $seoBox17 . $sepUnique . $seoBox18;
            die();
        }
    } // End in-page links analyser

    /*
     * -----------------------------------------------------------------
     * BROKEN LINKS CHECKER
     * -----------------------------------------------------------------
     * Checks internal and external links for a 404 HTTP status to identify broken links.
     */
    if (isset($_POST['brokenlinks'])) {
        session_write_close();

        $broken_Msg = $lang['AN186'];
        $hideMe = $brokenMsg = $brokenClass = $brokenLinks = '';
        $bLinks = array();
        $totalDataCount = 0;

        // Check internal links.
        foreach ($int_data as $internal_link) {

            $iLink = Trim($internal_link['href']);

            if (substr($iLink, 0, 4) == "tel:")
                continue;

            if (substr($iLink, 0, 2) == "//") {
                $iLink = 'http:' . $iLink;
            } elseif (substr($iLink, 0, 1) == "/") {
                $iLink = $inputHost . $iLink;
            }

            $httpCode = getHttpCode($iLink);

            if ($httpCode == 404) {
                if ($totalDataCount == 3)
                    $hideMe = 'hideTr hideTr5';
                $brokenLinks .= '<tr class="' . $hideMe . '"><td>' . $iLink . '</td></tr>';
                $bLinks[] = $iLink;
                $totalDataCount++;
            }
        }

        // Check external links.
        foreach ($ext_data as $external_link) {
            $eLink = Trim($external_link['href']);

            $httpCode = getHttpCode($eLink);

            if ($httpCode == 404) {
                if ($totalDataCount == 3)
                    $hideMe = 'hideTr hideTr5';
                $brokenLinks .= '<tr class="' . $hideMe . '"><td>' . $eLink . '</td></tr>';
                $bLinks[] = $eLink;
                $totalDataCount++;
            }
        }

        $updateStr = serBase($bLinks);
        updateToDbPrepared($con, 'domains_data', array('broken_links' => $updateStr), array('domain' => $domainStr));

        // Set the CSS class based on the number of broken links found.
        if ($totalDataCount == 0) {
            $brokenClass = 'passedBox';
            $brokenMsg = $lang['AN68'];
        } else {
            $brokenClass = 'errorBox';
            $brokenMsg = $lang['AN69'];
        }

        if (!isset($_SESSION['twebUsername'])) {
            if (!isAllowedStats($con, 'seoBox14')) {
                die($seoBoxLogin);
            }
        }

        // Output the broken links report.
        echo '<div class="' . $brokenClass . '">
    <div class="msgBox">       
         ' . $brokenMsg . '
        <br /><br />
        
        ' . (($totalDataCount != 0) ? '
        <table class="table table-responsive">
            <tbody>
                ' . $brokenLinks . '
    	   </tbody>
        </table>' : '') . '
                
        ' . (($totalDataCount > 3) ? '
            <div class="showLinks showLinks5">
                <a class="showMore showMore5">' . $lang['AN18'] . ' <br /> <i class="fa fa-angle-double-down"></i></a>
                <a class="showLess showLess5">' . $lang['AN19'] . '</a>
        </div>' : '') . '
        
    </div>
    <div class="seoBox14 suggestionBox">
    ' . $broken_Msg . '
    </div> 
    </div>';
        die();
    } // End broken links handler

    /*
     * -----------------------------------------------------------------
     * ROBOTS.TXT CHECKER
     * -----------------------------------------------------------------
     * Checks if a robots.txt file exists on the site and outputs its status.
     */
    if (isset($_POST['robot'])) {
        $robot_Msg = $lang['AN187'];
        $robotLink = $robotMsg = $robotClass = '';

        $robotLink = $inputHost . '/robots.txt';
        $httpCode = getHttpCode($robotLink);

        $updateStr = base64_encode($httpCode);
        updateToDbPrepared($con, 'domains_data', array('robots' => $updateStr), array('domain' => $domainStr));

        if ($httpCode == '404') {
            $robotClass = 'errorBox';
            $robotMsg = $lang['AN74'] . '<br>' . '<a href="' . $robotLink . '" title="' . $lang['AN75'] . '" rel="nofollow" target="_blank">' . $robotLink . '</a>';
        } else {
            $robotClass = 'passedBox';
            $robotMsg = $lang['AN73'] . '<br>' . '<a href="' . $robotLink . '" title="' . $lang['AN75'] . '" rel="nofollow" target="_blank">' . $robotLink . '</a>';
        }

        if (!isset($_SESSION['twebUsername'])) {
            if (!isAllowedStats($con, 'seoBox16')) {
                die($seoBoxLogin);
            }
        }

        // Output the robots.txt check result.
        echo '<div class="' . $robotClass . '">
    <div class="msgBox">       
         ' . $robotMsg . '
        <br /><br />
    </div>
    <div class="seoBox16 suggestionBox">
    ' . $robot_Msg . '
    </div> 
    </div>';
        die();
    } // End robots.txt handler

    /*
     * -----------------------------------------------------------------
     * SITEMAP CHECKER
     * -----------------------------------------------------------------
     * Checks if a sitemap exists for the site and outputs the result.
     */
    if (isset($_POST['sitemap'])) {
        $sitemap_Msg = $lang['AN188'];
        $sitemapLink = $sitemapMsg = $sitemapClass = '';

        $sitemapInfo = getSitemapInfo($inputHost);
        $httpCode = $sitemapInfo['httpCode'];
        $sitemapLink = $sitemapInfo['sitemapLink'];

        $updateStr = base64_encode($httpCode);
        updateToDbPrepared($con, 'domains_data', array('sitemap' => $updateStr), array('domain' => $domainStr));

        if ($httpCode == '404') {
            $sitemapClass = 'errorBox';
            $sitemapMsg = $lang['AN71'] . '<br>' . '<a href="' . $sitemapLink . '" title="' . $lang['AN72'] . '" rel="nofollow" target="_blank">' . $sitemapLink . '</a>';
        } else {
            $sitemapClass = 'passedBox';
            $sitemapMsg = $lang['AN70'] . '<br>' . '<a href="' . $sitemapLink . '" title="' . $lang['AN72'] . '" rel="nofollow" target="_blank">' . $sitemapLink . '</a>';
        }

        if (!isset($_SESSION['twebUsername'])) {
            if (!isAllowedStats($con, 'seoBox15')) {
                die($seoBoxLogin);
            }
        }

        // Output the sitemap checker result.
        echo '<div class="' . $sitemapClass . '">
    <div class="msgBox">       
         ' . $sitemapMsg . '
        <br /><br />
    </div>
    <div class="seoBox15 suggestionBox">
    ' . $sitemap_Msg . '
    </div> 
    </div>';
        die();
    } // End sitemap handler

    /*
     * -----------------------------------------------------------------
     * EMBEDDED OBJECT CHECK
     * -----------------------------------------------------------------
     * Checks if the page contains any <object> or <embed> tags and flags them.
     */
    if (isset($_POST['embedded'])) {

        $embedded_Msg = $lang['AN191'];
        $embeddedMsg = $embeddedClass = '';
        $embeddedCheck = false;

        if (!empty($domData)) {
            $domContent = $domData->find('object');
            if (!empty($domContent)) {
                foreach ($domContent as $embedded)
                    $embeddedCheck = true;
            }
            $domContent = $domData->find('embed');
            if (!empty($domContent)) {
                foreach ($domContent as $embedded)
                    $embeddedCheck = true;
            }
        }

        updateToDbPrepared($con, 'domains_data', array('embedded' => $embeddedCheck), array('domain' => $domainStr));

        if ($embeddedCheck) {
            $embeddedClass = 'errorBox';
            $embeddedMsg = $lang['AN78'];
        } else {
            $embeddedClass = 'passedBox';
            $embeddedMsg = $lang['AN77'];
        }

        // Clean up memory
        $domData = null;

        if (!isset($_SESSION['twebUsername'])) {
            if (!isAllowedStats($con, 'seoBox19')) {
                die($seoBoxLogin);
            }
        }

        // Output the embedded object check result.
        echo '<div class="' . $embeddedClass . '">
    <div class="msgBox">       
         ' . $embeddedMsg . '
        <br /><br />
    </div>
    <div class="seoBox19 suggestionBox">
    ' . $embedded_Msg . '
    </div> 
    </div>';
        die();
    } // End embedded object check

    /*
     * -----------------------------------------------------------------
     * IFRAME CHECK
     * -----------------------------------------------------------------
     * Checks if the page contains any <iframe> tags.
     */
    if (isset($_POST['iframe'])) {

        $iframe_Msg = $lang['AN192'];
        $iframeMsg = $iframeClass = '';
        $iframeCheck = false;

        if (!empty($domData)) {
            $domContent = $domData->find('iframe');
            if (!empty($domContent)) {
                foreach ($domContent as $iframe)
                    $iframeCheck = true;
            }
        }

        updateToDbPrepared($con, 'domains_data', array('iframe' => $iframeCheck), array('domain' => $domainStr));

        if ($iframeCheck) {
            $iframeClass = 'errorBox';
            $iframeMsg = $lang['AN80'];
        } else {
            $iframeClass = 'passedBox';
            $iframeMsg = $lang['AN79'];
        }

        // Clean up memory
        $domData = null;

        if (!isset($_SESSION['twebUsername'])) {
            if (!isAllowedStats($con, 'seoBox20')) {
                die($seoBoxLogin);
            }
        }

        // Output the iframe check result.
        echo '<div class="' . $iframeClass . '">
    <div class="msgBox">       
         ' . $iframeMsg . '
        <br /><br />
    </div>
    <div class="seoBox20 suggestionBox">
    ' . $iframe_Msg . '
    </div> 
    </div>';
        die();
    } // End iframe check

    /*
     * -----------------------------------------------------------------
     * WHOIS DATA RETRIEVAL
     * -----------------------------------------------------------------
     * Retrieves WHOIS information for the domain, including domain age and key dates.
     */
    if (isset($_POST['whois'])) {
        $class = 'lowImpactBox';
        $whoisData = $hideMe = '';
        $totalDataCount = 0;
        $domainAgeMsg = $lang['AN193'];
        $whoisDataMsg = $lang['AN194'];

        $whois = new whois;
        $site = $whois->cleanUrl($my_url_host);
        $whois_data = $whois->whoislookup($site);
        $whoisRaw = $whois_data[0];
        $domainAge = $whois_data[1];
        $createdDate = $whois_data[2];
        $updatedDate = $whois_data[3];
        $expiredDate = $whois_data[4];

        $updateStr = serBase($whois_data);
        updateToDbPrepared($con, 'domains_data', array('whois' => $updateStr), array('domain' => $domainStr));

        // Prepare the WHOIS raw output for display.
        $myLines = preg_split("/\r\n|\n|\r/", $whoisRaw);
        foreach ($myLines as $line) {
            if (!empty($line)) {
                if ($totalDataCount == 5)
                    $hideMe = 'hideTr hideTr6';
                $whoisData .= '<tr class="' . $hideMe . '"><td>' . $line . '</td></tr>';
                $totalDataCount++;
            }
        }

        $seoBox21 = '<div class="' . $class . '">
    <div class="msgBox">       
         ' . $lang['AN85'] . '
        <br /><br />
        <div class="altImgGroup">
            <p><i class="fa fa-paw solveMsgGreen"></i> ' . $lang['AN86'] . ': ' . $domainAge . '</p>
            <p><i class="fa fa-paw solveMsgGreen"></i> ' . $lang['AN87'] . ': ' . $createdDate . '</p>
            <p><i class="fa fa-paw solveMsgGreen"></i> ' . $lang['AN88'] . ': ' . $updatedDate . '</p>
            <p><i class="fa fa-paw solveMsgGreen"></i> ' . $lang['AN89'] . ': ' . $expiredDate . '</p>
        </div>
    </div>
    <div class="seoBox21 suggestionBox">
    ' . $domainAgeMsg . '
    </div> 
    </div>';

        $seoBox22 = '<div class="' . $class . '">
    <div class="msgBox">       
         ' . $lang['AN84'] . '
        <br /><br />

        ' . (($totalDataCount != 0) ? '
        <table class="table table-hover table-bordered table-striped">
            <tbody>
                ' . $whoisData . '
            </tbody>
        </table>' : $lang['AN90']) . '
                
        ' . (($totalDataCount > 5) ? '
            <div class="showLinks showLinks6">
                <a class="showMore showMore6">' . $lang['AN18'] . ' <br /> <i class="fa fa-angle-double-down"></i></a>
                <a class="showLess showLess6">' . $lang['AN19'] . '</a>
        </div>' : '') . '
        
    </div>
    <div class="seoBox22 suggestionBox">
    ' . $whoisDataMsg . '
    </div> 
    </div>';

        if (!isset($_SESSION['twebUsername'])) {
            if (!isAllowedStats($con, 'seoBox21'))
                $seoBox21 = $seoBoxLogin;
            if (!isAllowedStats($con, 'seoBox22'))
                $seoBox22 = $seoBoxLogin;
        }

        // Output the WHOIS data.
        echo $seoBox21 . $sepUnique . $seoBox22;
        die();
    } // End WHOIS handler

    /*
     * -----------------------------------------------------------------
     * MOBILE FRIENDLINESS CHECKER
     * -----------------------------------------------------------------
     * Uses an external API to check if the website is mobile friendly and displays a mobile preview.
     */
    if (isset($_POST['mobileCheck'])) {
        $isMobileFriendlyMsg = '';
        $mobileClass = $mobileScreenClass = 'lowImpactBox';

        $mobileCheckMsg = $lang['AN195'];
        $mobileScreenClassMsg = $lang['AN196'];

        // Get mobile friendly results (assumed to return a JSON with score, passed, and screenshot data).
        $jsonData = getMobileFriendly($my_url);
        $mobileScore = intval($jsonData['score']);
        $isMobileFriendly = $jsonData['passed'];

        if ($jsonData != null || $jsonData == "") {

            if ($isMobileFriendly) {
                $mobileClass = 'passedBox';
                $isMobileFriendlyMsg .= $lang['AN116'] . '<br>' . str_replace('[score]', $mobileScore, $lang['AN117']);
            } else {
                $mobileClass = 'errorBox';
                $isMobileFriendlyMsg .= $lang['AN118'] . '<br>' . str_replace('[score]', $mobileScore, $lang['AN117']);
            }

            $screenData = $jsonData['screenshot'];

            // Store mobile preview screenshot in the database.
            storeMobilePreview($domainStr, $screenData);

            if ($screenData == '')
                $mobileScreenData = '';
            else
                $mobileScreenData = '<img src="data:image/jpeg;base64,' . $screenData . '" />';
        } else {
            $isMobileFriendlyMsg = $lang['AN10'];
            $mobileScreenData = $lang['AN119'];
        }

        $mobData = array($mobileScore, $isMobileFriendly);
        $updateStr = serBase($mobData);
        updateToDbPrepared($con, 'domains_data', array('mobile_fri' => $updateStr), array('domain' => $domainStr));

        $seoBox23 = '<div class="' . $mobileClass . '">
    <div class="msgBox">       
        ' . $isMobileFriendlyMsg . '
        <br /><br />
    </div>
    <div class="seoBox23 suggestionBox">
    ' . $mobileCheckMsg . '
    </div> 
    </div>';

        $seoBox24 = '<div class="' . $mobileScreenClass . '">
    <div class="msgBox">       
        <div class="mobileView">' . $mobileScreenData . '</div>
        <br />
    </div>
    <div class="seoBox24 suggestionBox">
    ' . $mobileScreenClassMsg . '
    </div> 
    </div>';

        if (!isset($_SESSION['twebUsername'])) {
            if (!isAllowedStats($con, 'seoBox23'))
                $seoBox23 = $seoBoxLogin;
            if (!isAllowedStats($con, 'seoBox24'))
                $seoBox24 = $seoBoxLogin;
        }

        // Output mobile friendliness results.
        echo $seoBox23 . $sepUnique . $seoBox24;
        die();
    } // End mobile friendliness handler

    /*
     * -----------------------------------------------------------------
     * MOBILE COMPATIBILITY CHECKER
     * -----------------------------------------------------------------
     * Checks for elements (like iframes, objects, embeds) that may not be mobile compatible.
     */
    if (isset($_POST['mobileCom'])) {

        $mobileCom_Msg = $lang['AN197'];
        $mobileComMsg = $mobileComClass = '';
        $mobileComCheck = false;

        if (!empty($domData)) {
            $domContent = $domData->find('iframe');
            if (!empty($domContent)) {
                foreach ($domContent as $iframe)
                    $mobileComCheck = true;
            }

            $domContent = $domData->find('object');
            if (!empty($domContent)) {
                foreach ($domContent as $embedded)
                    $mobileComCheck = true;
            }

            $domContent = $domData->find('embed');
            if (!empty($domContent)) {
                foreach ($domContent as $embedded)
                    $mobileComCheck = true;
            }
        }

        updateToDbPrepared($con, 'domains_data', array('mobile_com' => $mobileComCheck), array('domain' => $domainStr));

        if ($mobileComCheck) {
            $mobileComClass = 'errorBox';
            $mobileComMsg = $lang['AN121'];
        } else {
            $mobileComClass = 'passedBox';
            $mobileComMsg = $lang['AN120'];
        }

        // Free DOM memory.
        $domData = null;

        if (!isset($_SESSION['twebUsername'])) {
            if (!isAllowedStats($con, 'seoBox25')) {
                die($seoBoxLogin);
            }
        }

        // Output mobile compatibility result.
        echo '<div class="' . $mobileComClass . '">
    <div class="msgBox">       
         ' . $mobileComMsg . '
        <br /><br />
    </div>
    <div class="seoBox25 suggestionBox">
    ' . $mobileCom_Msg . '
    </div> 
    </div>';
        die();
    } // End mobile compatibility handler

    /*
     * -----------------------------------------------------------------
     * URL LENGTH & FAVICON CHECKER
     * -----------------------------------------------------------------
     * Checks the length of the host name and fetches the favicon using Google’s favicon service.
     */
    if (isset($_POST['urlLength'])) {

        $favIconMsg = $urlLengthMsg = '';
        $favIconClass = 'lowImpactBox';
        $urlLength_Msg = $lang['AN198'];
        $favIcon_Msg = $lang['AN199'];

        $hostWord = explode('.', $my_url_host);

        if (strlen($hostWord[0]) < 15) {
            $urlLengthClass = 'passedBox';
        } else {
            $urlLengthClass = 'errorBox';
        }
        $urlLengthMsg = $my_url . '<br>' . str_replace('[count]', strlen($hostWord[0]), $lang['AN122']);
        $favIconMsg = '<img src="https://www.google.com/s2/favicons?domain=' . $my_url . '" alt="FavIcon" />  ' . $lang['AN123'];

        echo '<div class="' . $urlLengthClass . '">
    <div class="msgBox">       
         ' . $urlLengthMsg . '
        <br /><br />
    </div>
    <div class="seoBox26 suggestionBox">
    ' . $urlLength_Msg . '
    </div> 
    </div>';

        echo $sepUnique; // Separate sections

        echo '<div class="' . $favIconClass . '">
    <div class="msgBox">       
        ' . $favIconMsg . '
        <br /><br />
    </div>
    <div class="seoBox27 suggestionBox">
    ' . $favIcon_Msg . '
    </div> 
    </div>';

        die();
    } // End URL length & favicon handler

    /*
     * -----------------------------------------------------------------
     * CUSTOM 404 PAGE CHECKER
     * -----------------------------------------------------------------
     * Checks if the custom 404 page is in place by comparing the size of the test error page.
     */
    if (isset($_POST['errorPage'])) {

        $errorPage_Msg = $lang['AN200'];
        $errorPageMsg = $errorPageClass = '';
        $errorPageCheck = false;
        $pageSize = strlen(curlGET($my_url . '/404error-test-page-by-atoz-seo-tools'));

        $updateStr = base64_encode($pageSize);
        updateToDbPrepared($con, 'domains_data', array('404_page' => $updateStr), array('domain' => $domainStr));

        if ($pageSize < 1500) {
            // Default Error Page detected
            $errorPageCheck = false;
            $errorPageClass = 'errorBox';
            $errorPageMsg = $lang['AN125'];
        } else {
            // Custom Error Page detected
            $errorPageCheck = true;
            $errorPageClass = 'passedBox';
            $errorPageMsg = $lang['AN124'];
        }

        if (!isset($_SESSION['twebUsername'])) {
            if (!isAllowedStats($con, 'seoBox28')) {
                die($seoBoxLogin);
            }
        }

        // Output the custom 404 page check result.
        echo '<div class="' . $errorPageClass . '">
    <div class="msgBox">       
         ' . $errorPageMsg . '
        <br /><br />
    </div>
    <div class="seoBox28 suggestionBox">
    ' . $errorPage_Msg . '
    </div> 
    </div>';
        die();
    } // End custom 404 page handler

    /*
     * -----------------------------------------------------------------
     * PAGE SIZE / LOAD TIME / LANGUAGE CHECKER
     * -----------------------------------------------------------------
     * Measures page load time, calculates the size of the HTML data, and attempts to detect the language code.
     */
    if (isset($_POST['pageLoad'])) {

        $size_Msg = $lang['AN201'];
        $load_Msg = $lang['AN202'];
        $lang_Msg = $lang['AN203'];

        $sizeMsg = $loadMsg = $langMsg = '';
        $langCode = null;

        $timeStart = microtime(true);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $my_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.3; WOW64; rv:36.0) Gecko/20100101 Firefox/36.0');
        curl_setopt($ch, CURLOPT_REFERER, $my_url);
        $html = curl_exec($ch);
        curl_close($ch);
        $timeEnd = microtime(true);
        $timeTaken = $timeEnd - $timeStart;
        $dataSize = strlen($html);

        $patternCode = '<html[^>]+lang=[\'"]?(.*?)[\'"]?[\/\s>]';
        preg_match("#{$patternCode}#is", $html, $matches);
        if (isset($matches[1])) {
            $langCode = Trim(mb_substr($matches[1], 0, 5));
        } else {
            $patternCode = '<meta[^>]+http-equiv=[\'"]?content-language[\'"]?[^>]+content=[\'"]?(.*?)[\'"]?[\/\s>]';
            preg_match("#{$patternCode}#is", $html, $matches);
            $langCode = isset($matches[1]) ? Trim(mb_substr($matches[1], 0, 5)) : null;
        }

        $updateStr = serBase(array($timeTaken, $dataSize, $langCode));
        updateToDbPrepared($con, 'domains_data', array('load_time' => $updateStr), array('domain' => $domainStr));

        $dataSize = size_as_kb($dataSize);
        if ($dataSize < 320) {
            $sizeClass = 'passedBox';
        } else {
            $sizeClass = 'errorBox';
        }

        $sizeMsg = str_replace('[size]', $dataSize, $lang['AN126']);

        $timeTaken = round($timeTaken, 2);

        if ($timeTaken < 1) {
            $loadClass = 'passedBox';
        } else {
            $loadClass = 'errorBox';
        }
        $loadMsg = str_replace('[time]', $timeTaken, $lang['AN127']);

        if ($langCode == null) {
            $langClass = 'errorBox';
            $langMsg .= $lang['AN129'] . '<br>';
        } else {
            $langClass = 'passedBox';
            $langMsg .= $lang['AN128'] . '<br>';
        }
        $langCode = lang_code_to_lnag($langCode);
        $langMsg .= str_replace('[language]', $langCode, $lang['AN130']);

        $seoBox29 = '<div class="' . $sizeClass . '">
    <div class="msgBox">       
         ' . $sizeMsg . '
        <br /><br />
    </div>
    <div class="seoBox29 suggestionBox">
    ' . $size_Msg . '
    </div> 
    </div>';

        $seoBox30 = '<div class="' . $loadClass . '">
    <div class="msgBox">       
         ' . $loadMsg . '
        <br /><br />
    </div>
    <div class="seoBox30 suggestionBox">
    ' . $load_Msg . '
    </div> 
    </div>';

        $seoBox31 = '<div class="' . $langClass . '">
    <div class="msgBox">       
         ' . $langMsg . '
        <br /><br />
    </div>
    <div class="seoBox31 suggestionBox">
    ' . $lang_Msg . '
    </div> 
    </div>';

        if (!isset($_SESSION['twebUsername'])) {
            if (!isAllowedStats($con, 'seoBox29'))
                $seoBox29 = $seoBoxLogin;
            if (!isAllowedStats($con, 'seoBox30'))
                $seoBox30 = $seoBoxLogin;
            if (!isAllowedStats($con, 'seoBox31'))
                $seoBox31 = $seoBoxLogin;
        }

        // Output page load, size, and language data.
        echo $seoBox29 . $sepUnique . $seoBox30 . $sepUnique . $seoBox31;
        die();
    } // End page load handler

    /*
     * -----------------------------------------------------------------
     * DOMAIN & TYPO AVAILABILITY CHECKER
     * -----------------------------------------------------------------
     * Checks the availability of the domain on multiple TLDs and suggests typo domains.
     */
    if (isset($_POST['availabilityChecker'])) {

        $domain_Msg = $lang['AN204'];
        $typo_Msg = $lang['AN205'];
        $typoMsg = $domainMsg = '';
        $typoClass = $domainClass = 'lowImpactBox';

        $doArr = $tyArr = array();

        // Server list file containing WHOIS servers.
        $path = LIB_DIR . 'domainAvailabilityservers.tdata';

        if (file_exists($path)) {
            $contents = file_get_contents($path);
            $serverList = json_decode($contents, true);
        }
        $tldCodes = array('com', 'net', 'org', 'biz', 'us', 'info', 'eu');
        $domainWord = explode('.', $my_url_host);
        $hostTLD = Trim(end($domainWord));
        $domainWord = $domainWord[0];
        $tldCount = 0;
        foreach ($tldCodes as $tldCode) {
            if ($tldCount == 5)
                break;
            if ($tldCode != $hostTLD) {
                $topDomain = $domainWord . '.' . $tldCode;
                // Check the availability of the domain.
                $domainAvailabilityChecker = new domainAvailability($serverList);
                $domainAvailabilityStats = $domainAvailabilityChecker->isAvailable($topDomain);
                $doArr[] = array($topDomain, $domainAvailabilityStats);
                // Response codes:
                // 2 - Domain is already taken.
                // 3 - Domain is available.
                // 4 - No WHOIS entry was found for that TLD.
                // 5 - WHOIS Query failed.
                if ($domainAvailabilityStats == '2')
                    $domainStatsMsg = $lang['AN132'];
                elseif ($domainAvailabilityStats == '3')
                    $domainStatsMsg = $lang['AN131'];
                else
                    $domainStatsMsg = $lang['AN133'];

                $domainMsg .= '<tr> <td>' . $topDomain . '</td> <td>' . $domainStatsMsg . '</td> </tr>';
                $tldCount++;
            }
        }

        $typo = new typos();
        $domainTypoWords = $typo->get($domainWord);

        $typoCount = 0;
        foreach ($domainTypoWords as $domainTypoWord) {
            if ($typoCount == 5)
                break;
            $topDomain = $domainTypoWord . '.' . $hostTLD;
            // Check the availability of the typo domain.
            $domainAvailabilityChecker = new domainAvailability($serverList);
            $domainAvailabilityStats = $domainAvailabilityChecker->isAvailable($topDomain);
            $tyArr[] = array($topDomain, $domainAvailabilityStats);
            if ($domainAvailabilityStats == '2')
                $domainStatsMsg = $lang['AN132'];
            elseif ($domainAvailabilityStats == '3')
                $domainStatsMsg = $lang['AN131'];
            else
                $domainStatsMsg = $lang['AN133'];

            $typoMsg .= '<tr> <td>' . $topDomain . '</td> <td>' . $domainStatsMsg . '</td> </tr>';
            $typoCount++;
        }

        $updateStr = serBase(array($doArr, $tyArr));
        updateToDbPrepared($con, 'domains_data', array('domain_typo' => $updateStr), array('domain' => $domainStr));

        $seoBox32 = '<div class="' . $domainClass . '">
    <div class="msgBox"> 
        <table class="table table-hover table-bordered table-striped">
            <tbody>
                <tr> <th>' . $lang['AN134'] . '</th> <th>' . $lang['AN135'] . '</th> </tr> 
                ' . $domainMsg . '
            </tbody>
        </table>
        <br />
    </div>
    <div class="seoBox32 suggestionBox">
    ' . $domain_Msg . '
    </div> 
    </div>';

        $seoBox33 = '<div class="' . $typoClass . '">
    <div class="msgBox"> 
        <table class="table table-hover table-bordered table-striped">
            <tbody>
                <tr> <th>' . $lang['AN134'] . '</th> <th>' . $lang['AN135'] . '</th> </tr> 
                ' . $typoMsg . '
            </tbody>
        </table>
        <br />
    </div>
    <div class="seoBox33 suggestionBox">
    ' . $typo_Msg . '
    </div> 
    </div>';

        if (!isset($_SESSION['twebUsername'])) {
            if (!isAllowedStats($con, 'seoBox32'))
                $seoBox32 = $seoBoxLogin;
            if (!isAllowedStats($con, 'seoBox33'))
                $seoBox33 = $seoBoxLogin;
        }

        // Output domain and typo availability results.
        echo $seoBox32 . $sepUnique . $seoBox33;
        die();
    } // End domain & typo availability handler

    /*
     * -----------------------------------------------------------------
     * EMAIL PRIVACY CHECK
     * -----------------------------------------------------------------
     * Searches for email addresses in the source data and outputs a privacy warning if found.
     */
    if (isset($_POST['emailPrivacy'])) {

        $emailPrivacy_Msg = $lang['AN206'];
        $emailPrivacyMsg = $emailPrivacyClass = '';

        preg_match_all("/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6})/", $sourceData, $matches, PREG_SET_ORDER);

        $emailCount = count($matches);

        updateToDbPrepared($con, 'domains_data', array('email_privacy' => $emailCount), array('domain' => $domainStr));

        if ($emailCount == 0) {
            $emailPrivacyClass = 'passedBox';
            $emailPrivacyMsg = $lang['AN136'];
        } else {
            $emailPrivacyClass = 'errorBox';
            $emailPrivacyMsg = $lang['AN137'];
        }

        if (!isset($_SESSION['twebUsername'])) {
            if (!isAllowedStats($con, 'seoBox34')) {
                die($seoBoxLogin);
            }
        }

        // Output the email privacy result.
        echo '<div class="' . $emailPrivacyClass . '">
    <div class="msgBox">       
         ' . $emailPrivacyMsg . '
        <br /><br />
    </div>
    <div class="seoBox34 suggestionBox">
    ' . $emailPrivacy_Msg . '
    </div> 
    </div>';
        die();
    } // End email privacy handler

    /*
     * -----------------------------------------------------------------
     * SAFE BROWSING CHECK
     * -----------------------------------------------------------------
     * Checks whether the site is blacklisted by a safe browsing service.
     */
    if (isset($_POST['safeBrowsing'])) {

        $safeBrowsing_Msg = $lang['AN207'];
        $safeBrowsingMsg = $safeBrowsingClass = '';

        $safeBrowsingStats = safeBrowsing($my_url_host);
        // Expected responses:
        // 204 - Not blacklisted
        // 200 - Blacklisted
        // 501 - Error

        updateToDbPrepared($con, 'domains_data', array('safe_bro' => $safeBrowsingStats), array('domain' => $domainStr));

        if ($safeBrowsingStats == 204) {
            $safeBrowsingMsg = $lang['AN138'];
            $safeBrowsingClass = 'passedBox';
        } elseif ($safeBrowsingStats == 200) {
            $safeBrowsingMsg = $lang['AN139'];
            $safeBrowsingClass = 'errorBox';
        } else {
            $safeBrowsingMsg = $lang['AN140'];
            $safeBrowsingClass = 'improveBox';
        }

        if (!isset($_SESSION['twebUsername'])) {
            if (!isAllowedStats($con, 'seoBox35')) {
                die($seoBoxLogin);
            }
        }

        // Output safe browsing result.
        echo '<div class="' . $safeBrowsingClass . '">
    <div class="msgBox">       
         ' . $safeBrowsingMsg . '
        <br /><br />
    </div>
    <div class="seoBox35 suggestionBox">
    ' . $safeBrowsing_Msg . '
    </div> 
    </div>';
        die();
    } // End safe browsing handler

    /*
     * -----------------------------------------------------------------
     * SERVER LOCATION INFORMATION
     * -----------------------------------------------------------------
     * Retrieves the server IP, country, and ISP for the host.
     */
    if (isset($_POST['serverIP'])) {

        $serverIP_Msg = $lang['AN208'];
        $serverIPClass = 'lowImpactBox';

        $getHostIP = gethostbyname($my_url_host);
        $data_list = host_info($my_url_host);

        $updateStr = serBase($data_list);
        updateToDbPrepared($con, 'domains_data', array('server_loc' => $updateStr), array('domain' => $domainStr));

        $domain_ip = $data_list[0];
        $domain_country = $data_list[1];
        $domain_isp = $data_list[2];

        if (!isset($_SESSION['twebUsername'])) {
            if (!isAllowedStats($con, 'seoBox36')) {
                die($seoBoxLogin);
            }
        }

        // Output the server location information in a table.
        echo '<div class="' . $serverIPClass . '">
    <div class="msgBox">   
        <table class="table table-hover table-bordered table-striped">
            <tbody>
                <tr> 
                    <th>' . $lang['AN141'] . '</th> 
                    <th>' . $lang['AN142'] . '</th>
                    <th>' . $lang['AN143'] . '</th>
                </tr> 
                <tr> 
                    <td>' . $getHostIP . '</td> 
                    <td>' . $domain_country . '</td>
                    <td>' . $domain_isp . '</td>
                </tr> 
            </tbody>
        </table>
        <br />
    </div>
    <div class="seoBox36 suggestionBox">
    ' . $serverIP_Msg . '
    </div> 
    </div>';
        die();
    } // End server location handler

    /*
     * -----------------------------------------------------------------
     * SPEED TIPS ANALYSER
     * -----------------------------------------------------------------
     * Analyzes the number of CSS, JS, nested tables, and inline CSS occurrences
     * to provide suggestions on how to improve site speed.
     */
    if (isset($_POST['speedTips'])) {

        $speedTips_Msg = $lang['AN209'];
        $speedTipsMsg = $speedTipsBody = '';
        $speedTipsCheck = $cssCount = $jsCount = 0;

        // Define regular expressions to identify CSS, JS, nested tables, and inline CSS.
        $cssTagPatternCode = '<link[^>]*>';
        $cssPatternCode = '(?=.*\bstylesheet\b)(?=.*\bhref=("[^"]*"|\'[^\']*\')).*';
        $jsTagPatternCode = '<script[^>]*>';
        $jsPatternCode = 'src=("[^"]*"|\'[^\']*\')';
        $tablePatternCode = "<(td|th)(?:[^>]*)>(.*?)<table(?:[^>]*)>(.*?)</table(?:[^>]*)>(.*?)</(td|th)(?:[^>]*)>";
        $inlineCssPatternCode = "<(.+)style=\"[^\"].+\"[^>]*>(.*?)<\/[^>]*>";

        // Count CSS links with stylesheet attribute.
        preg_match_all("#{$cssTagPatternCode}#is", $sourceData, $matches);
        if (!isset($matches[0]))
            $cssCount = 0;
        else {
            foreach ($matches[0] as $tagVal) {
                if (preg_match("#{$cssPatternCode}#is", $tagVal))
                    $cssCount++;
            }
        }

        // Count JavaScript tags that include a source attribute.
        preg_match_all("#{$jsTagPatternCode}#is", $sourceData, $matches);
        if (!isset($matches[0]))
            $jsCount = 0;
        else {
            foreach ($matches[0] as $tagVal) {
                if (preg_match("#{$jsPatternCode}#is", $tagVal))
                    $jsCount++;
            }
        }

        // Check for nested tables.
        $nestedTables = preg_match("#{$tablePatternCode}#is", $sourceData);

        // Check for inline CSS.
        $inlineCss = preg_match("#{$inlineCssPatternCode}#is", $sourceData);

        $updateStr = serBase(array($cssCount, $jsCount, $nestedTables, $inlineCss));
        updateToDbPrepared($con, 'domains_data', array('speed_tips' => $updateStr), array('domain' => $domainStr));

        $speedTipsBody .= '<br>';

        if ($cssCount > 5) {
            $speedTipsCheck++;
            $speedTipsBody .= $false . ' ' . $lang['AN145'];
        } else
            $speedTipsBody .= $true . ' ' . $lang['AN144'];

        $speedTipsBody .= '<br><br>';

        if ($jsCount > 5) {
            $speedTipsCheck++;
            $speedTipsBody .= $false . ' ' . $lang['AN147'];
        } else
            $speedTipsBody .= $true . ' ' . $lang['AN146'];

        $speedTipsBody .= '<br><br>';

        if ($nestedTables == 1) {
            $speedTipsCheck++;
            $speedTipsBody .= $false . ' ' . $lang['AN149'];
        } else
            $speedTipsBody .= $true . ' ' . $lang['AN148'];

        $speedTipsBody .= '<br><br>';

        if ($inlineCss == 1) {
            $speedTipsCheck++;
            $speedTipsBody .= $false . ' ' . $lang['AN151'];
        } else
            $speedTipsBody .= $true . ' ' . $lang['AN150'];

        if ($speedTipsCheck == 0)
            $speedTipsClass = 'passedBox';
        elseif ($speedTipsCheck > 2)
            $speedTipsClass = 'errorBox';
        else
            $speedTipsClass = 'improveBox';

        $speedTipsMsg = $lang['AN152'];

        if (!isset($_SESSION['twebUsername'])) {
            if (!isAllowedStats($con, 'seoBox37')) {
                die($seoBoxLogin);
            }
        }

        // Output the speed tips suggestions.
        echo '<div class="' . $speedTipsClass . '">
    <div class="msgBox">       
        ' . $speedTipsMsg . '
        <br />
        <div class="altImgGroup">
            ' . $speedTipsBody . '
        </div>
        <br />
    </div>
    <div class="seoBox37 suggestionBox">
    ' . $speedTips_Msg . '
    </div> 
    </div>';
        die();
    } // End speed tips handler

    /*
     * -----------------------------------------------------------------
     * ANALYTICS & DOCUMENT TYPE CHECKER
     * -----------------------------------------------------------------
     * Checks for the presence of analytics code (like Google Analytics)
     * and identifies the DOCTYPE declaration in the HTML source.
     */
    if (isset($_POST['docType'])) {

        $docType_Msg = $lang['AN212'];
        $analytics_Msg = $lang['AN210'];
        $docType = $analyticsClass = $analyticsMsg = $docTypeClass = $docTypeMsg = '';
        $anCheck = false;
        $docCheck = false;

        // List of known DOCTYPEs.
        $doctypes = array(
            'HTML 5' => '<!DOCTYPE html>',
            'HTML 4.01 Strict' => '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">',
            'HTML 4.01 Transitional' => '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">',
            'HTML 4.01 Frameset' => '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Frameset//EN" "http://www.w3.org/TR/html4/frameset.dtd">',
            'XHTML 1.0 Strict' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">',
            'XHTML 1.0 Transitional' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">',
            'XHTML 1.0 Frameset' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Frameset//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-frameset.dtd">',
            'XHTML 1.1' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">',
        );

        // Check for Google Analytics tracking code.
        if (preg_match("/\bua-\d{4,9}-\d{1,4}\b/i", $sourceData)) {
            $analyticsClass = 'passedBox';
            $analyticsMsg = $lang['AN154'];
            $anCheck = true;
        } elseif (check_str_contains($sourceData, "gtag('")) {
            $analyticsClass = 'passedBox';
            $analyticsMsg = $lang['AN154'];
            $anCheck = true;
        } else {
            $analyticsClass = 'errorBox';
            $analyticsMsg = $lang['AN153'];
        }

        // Detect the DOCTYPE from the source data.
        $patternCode = "<!DOCTYPE[^>]*>";
        preg_match("#{$patternCode}#is", $sourceData, $matches);
        if (!isset($matches[0])) {
            $docTypeMsg = $lang['AN155'];
            $docTypeClass = 'improveBox';
        } else {
            $docType = array_search(strtolower(preg_replace('/\s+/', ' ', Trim($matches[0]))), array_map('strtolower', $doctypes));
            $docTypeMsg = $lang['AN156'] . ' ' . $docType;
            $docTypeClass = 'passedBox';
            $docCheck = true;
        }

        $updateStr = serBase(array($anCheck, $docCheck, $docType));
        updateToDbPrepared($con, 'domains_data', array('analytics' => $updateStr), array('domain' => $domainStr));

        $seoBox38 = '<div class="' . $analyticsClass . '">
    <div class="msgBox">
        ' . $analyticsMsg . '
        <br /><br />
    </div>
    <div class="seoBox38 suggestionBox">
    ' . $analytics_Msg . '
    </div> 
    </div>';

        $seoBox40 = '<div class="' . $docTypeClass . '">
    <div class="msgBox">
        ' . $docTypeMsg . '
        <br /><br />
    </div>
    <div class="seoBox40 suggestionBox">
    ' . $docType_Msg . '
    </div> 
    </div>';

        if (!isset($_SESSION['twebUsername'])) {
            if (!isAllowedStats($con, 'seoBox38'))
                $seoBox38 = $seoBoxLogin;
            if (!isAllowedStats($con, 'seoBox40'))
                $seoBox40 = $seoBoxLogin;
        }

        // Output the analytics and document type result.
        echo $seoBox38 . $sepUnique . $seoBox40;
        die();
    } // End analytics & doc type handler

    /*
     * -----------------------------------------------------------------
     * W3C VALIDITY CHECKER
     * -----------------------------------------------------------------
     * Uses the W3C validator API to check if the document is valid HTML.
     */
    if (isset($_POST['w3c'])) {

        $w3c_Msg = $lang['AN211'];
        $w3Data = $w3cMsg = '';
        $w3cClass = 'lowImpactBox';
        $w3DataCheck = 0;

        // Fetch validation results from the W3C validator.
        $w3Data = curlGET('https://validator.w3.org/nu/?doc=http%3A%2F%2F' . $my_url_host . '%2F');
        if ($w3Data != '') {
            if (check_str_contains($w3Data, 'document validates')) {
                $w3cMsg = $lang['AN157'];
                $w3DataCheck = '1';
            } else {
                $w3cMsg = $lang['AN158'];
                $w3DataCheck = '2';
            }
        } else {
            $w3cMsg = $lang['AN10'];
            $w3DataCheck = '3';
        }

        updateToDbPrepared($con, 'domains_data', array('w3c' => $w3DataCheck), array('domain' => $domainStr));

        if (!isset($_SESSION['twebUsername'])) {
            if (!isAllowedStats($con, 'seoBox39')) {
                die($seoBoxLogin);
            }
        }

        // Output the W3C validity result.
        echo '<div class="' . $w3cClass . '">
    <div class="msgBox">       
         ' . $w3cMsg . '
        <br /><br />
    </div>
    <div class="seoBox39 suggestionBox">
    ' . $w3c_Msg . '
    </div> 
    </div>';
        die();
    } // End W3C validity handler

    /*
     * -----------------------------------------------------------------
     * ENCODING TYPE CHECKER
     * -----------------------------------------------------------------
     * Checks for the charset meta tag to determine the document's encoding.
     */
    if (isset($_POST['encoding'])) {

        $encoding_Msg = $lang['AN213'];
        $encodingMsg = $encodingClass = '';
        $charterSet = null;

        $charterSetPattern = '<meta[^>]+charset=[\'"]?(.*?)[\'"]?[\/\s>]';
        preg_match("#{$charterSetPattern}#is", $sourceData, $matches);

        if (isset($matches[1]))
            $charterSet = Trim(mb_strtoupper($matches[1]));
        if ($charterSet != null) {
            $encodingClass = 'passedBox';
            $encodingMsg = $lang['AN159'] . ' ' . $charterSet;
        } else {
            $encodingClass = 'errorBox';
            $encodingMsg = $lang['AN160'];
        }

        $updateStr = base64_encode($charterSet);
        updateToDbPrepared($con, 'domains_data', array('encoding' => $updateStr), array('domain' => $domainStr));

        if (!isset($_SESSION['twebUsername'])) {
            if (!isAllowedStats($con, 'seoBox41')) {
                die($seoBoxLogin);
            }
        }

        // Output the encoding result.
        echo '<div class="' . $encodingClass . '">
    <div class="msgBox">       
         ' . $encodingMsg . '
        <br /><br />
    </div>
    <div class="seoBox41 suggestionBox">
    ' . $encoding_Msg . '
    </div> 
    </div>';
        die();
    } // End encoding type handler

    /*
     * -----------------------------------------------------------------
     * INDEXED PAGES COUNTER
     * -----------------------------------------------------------------
     * Uses a Google index query to determine how many pages are indexed for the site.
     */
    if (isset($_POST['indexedPages'])) {

        $indexedPages_Msg = $lang['AN214'];
        $indexProgress = $indexedPagesMsg = $indexedPagesClass = '';
        $datVal = $outData = 0;

        $outData = Trim(str_replace(',', '', googleIndex($my_url_host)));

        if (intval($outData) < 50) {
            $datVal = 25;
            $indexedPagesClass = 'errorBox';
            $indexProgress = 'danger';
        } elseif (intval($outData) < 200) {
            $datVal = 75;
            $indexedPagesClass = 'improveBox';
            $indexProgress = 'warning';
        } else {
            $datVal = 100;
            $indexedPagesClass = 'passedBox';
            $indexProgress = 'success';
        }

        $updateStr = base64_encode($outData);
        updateToDbPrepared($con, 'domains_data', array('indexed' => $updateStr), array('domain' => $domainStr));

        $indexedPagesMsg = '<div style="width:' . $datVal . '%" aria-valuemax="' . $datVal . '" aria-valuemin="0" aria-valuenow="' . $datVal . '" role="progressbar" class="progress-bar progress-bar-' . $indexProgress . '">
        ' . number_format($outData) . ' ' . $lang['AN162'] . '
    </div>';

        if (!isset($_SESSION['twebUsername'])) {
            if (!isAllowedStats($con, 'seoBox42')) {
                die($seoBoxLogin);
            }
        }

        // Output the indexed pages count.
        echo '<div class="' . $indexedPagesClass . '">
    <div class="msgBox">    
        ' . $lang['AN161'] . '<br />   <br /> 
         <div class="progress">
            ' . $indexedPagesMsg . '
         </div>
        <br />
    </div>
    <div class="seoBox42 suggestionBox">
    ' . $indexedPages_Msg . '
    </div> 
    </div>';
        die();
    } // End indexed pages handler

    /*
     * -----------------------------------------------------------------
     * BACKLINK COUNTER / ALEXA RANK / SITE WORTH
     * -----------------------------------------------------------------
     * Retrieves Alexa ranking data, backlink count, and calculates site worth.
     */
    if (isset($_POST['backlinks'])) {

        $backlinks_Msg = $lang['AN215'];
        $alexa_Msg = $lang['AN218'];
        $worth_Msg = $lang['AN217'];
        $alexaMsg = $worthMsg = $backProgress = $backlinksMsg = $backlinksClass = '';
        $alexaClass = $worthClass = 'lowImpactBox';

        $alexa = alexaRank($my_url_host);

        // Get backlink count.
        $alexa[3] = backlinkCount(clean_url($my_url_host), $con);

        $updateStr = serBase(array((string)$alexa[0], (string)$alexa[1], (string)$alexa[2], (string)$alexa[3]));
        updateToDbPrepared($con, 'domains_data', array('alexa' => $updateStr), array('domain' => $domainStr));

        $alexa_rank = $alexa[0];
        $alexa_pop = $alexa[1];
        $regional_rank = $alexa[2];
        $alexa_back = intval($alexa[3]);

        if ($alexa_back < 50) {
            $datVal = 25;
            $backlinksClass = 'errorBox';
            $backProgress = 'danger';
        } elseif ($alexa_back < 100) {
            $datVal = 75;
            $backlinksClass = 'improveBox';
            $backProgress = 'warning';
        } else {
            $datVal = 100;
            $backlinksClass = 'passedBox';
            $backProgress = 'success';
        }

        $backlinksMsg = '<div style="width:' . $datVal . '%" aria-valuemax="' . $datVal . '" aria-valuemin="0" aria-valuenow="' . $datVal . '" role="progressbar" class="progress-bar progress-bar-' . $backProgress . '">
        ' . number_format($alexa_back) . ' ' . $lang['AN163'] . '
    </div>';

        if ($alexa_rank == 'No Global Rank')
            $alexaMsg = $lang['AN165'];
        else
            $alexaMsg = ordinalNum(str_replace(',', '', $alexa_rank)) . ' ' . $lang['AN164'];

        $alexa_rank = ($alexa_rank == 'No Global Rank' ? '0' : $alexa_rank);
        $worthMsg = "$" . number_format(calPrice($alexa_rank)) . " USD";

        $seoBox43 = '<div class="' . $backlinksClass . '">
    <div class="msgBox">     
        ' . $lang['AN166'] . '<br />   <br /> 
         <div class="progress">  
         ' . $backlinksMsg . '
         </div>
         <br />
    </div>
    <div class="seoBox43 suggestionBox">
    ' . $backlinks_Msg . '
    </div> 
    </div>';

        $seoBox45 = '<div class="' . $worthClass . '">
    <div class="msgBox">       
         ' . $worthMsg . '
        <br /><br />
    </div>
    <div class="seoBox45 suggestionBox">
    ' . $worth_Msg . '
    </div> 
    </div>';

        $seoBox46 = '<div class="' . $alexaClass . '">
    <div class="msgBox">       
         ' . $alexaMsg . '
        <br /><br />
    </div>
    <div class="seoBox46 suggestionBox">
    ' . $alexa_Msg . '
    </div> 
    </div>';

        if (!isset($_SESSION['twebUsername'])) {
            if (!isAllowedStats($con, 'seoBox43'))
                $seoBox43 = $seoBoxLogin;
            if (!isAllowedStats($con, 'seoBox45'))
                $seoBox45 = $seoBoxLogin;
            if (!isAllowedStats($con, 'seoBox46'))
                $seoBox46 = $seoBoxLogin;
        }

        // Output backlink, Alexa, and site worth data.
        echo $seoBox43 . $sepUnique . $seoBox45 . $sepUnique . $seoBox46;
        die();
    } // End backlink/Alexa/worth handler

    /*
     * -----------------------------------------------------------------
     * SOCIAL DATA RETRIEVAL
     * -----------------------------------------------------------------
     * Gathers social media statistics from the source data (Facebook likes, Twitter count, Instagram count).
     */
    if (isset($_POST['socialData'])) {

        $social_Msg = $lang['AN216'];
        $socialMsg = '';
        $socialClass = 'lowImpactBox';

        $socialData = getSocialData($sourceData);

        $facebook_like = $socialData['fb'];
        $twit_count = $socialData['twit'];
        $insta_count = $socialData['insta'];
        $stumble_count = 0;
        $socialMsg = $lang['AN167'];

        $updateStr = serBase(array($facebook_like, $twit_count, $insta_count, $stumble_count));
        updateToDbPrepared($con, 'domains_data', array('social' => $updateStr), array('domain' => $domainStr));

        if (!isset($_SESSION['twebUsername'])) {
            if (!isAllowedStats($con, 'seoBox44')) {
                die($seoBoxLogin);
            }
        }

        if ($facebook_like === '-')
            $facebook_like = $false;
        else
            $facebook_like = $true . ' ' . $facebook_like;

        if ($twit_count === '-')
            $twit_count = $false;
        else
            $twit_count = $true . ' ' . $twit_count;

        if ($insta_count === '-')
            $insta_count = $false;
        else
            $insta_count = $true . ' ' . $insta_count;

        // Output social data.
        echo '<div class="' . $socialClass . '">
    <div class="msgBox">   
            ' . $socialMsg . '
        <br />
        <div class="altImgGroup">
            <br><div class="social-box"><i class="fa fa-facebook social-facebook"></i> Facebook: ' . $facebook_like . '</div><br>
            <div class="social-box"><i class="fa fa-twitter social-linkedin"></i> Twitter: ' . $twit_count . ' </div><br>
            <div class="social-box"><i class="fa fa-instagram social-google"></i> Instagram: ' . $insta_count . '</div>
        </div>
        <br />
    </div>
    <div class="seoBox44 suggestionBox">
    ' . $social_Msg . '
    </div> 
    </div>';
        die();
    } // End social data handler

    /*
     * -----------------------------------------------------------------
     * VISITORS LOCALIZATION
     * -----------------------------------------------------------------
     * Retrieves visitor data from Alexa and outputs localization information.
     */
    if (isset($_POST['visitorsData'])) {

        $visitors_Msg = $lang['AN219'];
        $visitorsMsg = '';
        $visitorsClass = 'lowImpactBox';

        $data = mysqliPreparedQuery($con, "SELECT alexa FROM domains_data WHERE domain=?", 's', array($domainStr));
        if ($data !== false) {
            $alexa = decSerBase($data['alexa']);

            $alexaDatas = array();
            $alexaDatas[] = array('', 'Popularity at', $alexa[1]);
            $alexaDatas[] = array('', 'Regional Rank', $alexa[2]);
        }

        $alexaDataCount = count($alexaDatas);

        foreach ($alexaDatas as $alexaData)
            $visitorsMsg .= '<tr><td>' . $alexaData[1] . '</td><td>' . $alexaData[2] . '</td><tr>';

        if (!isset($_SESSION['twebUsername'])) {
            if (!isAllowedStats($con, 'seoBox47')) {
                die($seoBoxLogin);
            }
        }

        // Output visitor localization information.
        echo '<div class="' . $visitorsClass . '">
    <div class="msgBox">   
        ' . $lang['AN171'] . '<br /><br />
        ' . (($alexaDataCount != 0) ? '
        <table class="table table-hover table-bordered table-striped">
            <tbody>
                ' . $visitorsMsg . '
            </tbody>
        </table>' : $lang['AN170']) . '
        <br />
    </div>
    <div class="seoBox47 suggestionBox">
    ' . $visitors_Msg . '
    </div> 
    </div>';
        die();
    } // End visitors localization handler

    /*
     * -----------------------------------------------------------------
     * PAGE SPEED INSIGHT CHECKER
     * -----------------------------------------------------------------
     * Uses an external PageSpeed Insight checker for both desktop and mobile.
     */
    if (isset($_POST['pageSpeedInsightChecker'])) {

        $pageSpeedInsightDesktop_Msg = $lang['AN220'];
        $pageSpeedInsightMobile_Msg = $lang['AN221'];
        $desktopMsg = $mobileMsg = $pageSpeedInsightData = $seoBox48 = $seoBox49 = '';
        $desktopClass = $mobileClass = $desktopSpeed = $mobileSpeed = '';
        $speedStr = $lang['117']; $mobileStr = $lang['119']; $desktopStr = $lang['118'];

        $desktopScore = pageSpeedInsightChecker($inputHost, 'desktop');
        $mobileScore = pageSpeedInsightChecker($inputHost, 'mobile');

        if (intval($desktopScore) < 50) {
            $desktopClass = 'errorBox';
            $desktopSpeed = $lang['125'];
        } elseif (intval($desktopScore) < 79) {
            $desktopClass = 'improveBox';
            $desktopSpeed = $lang['126'];
        } else {
            $desktopClass = 'passedBox';
            $desktopSpeed = $lang['124'];
        }

        if (intval($mobileScore) < 50) {
            $mobileClass = 'errorBox';
            $mobileSpeed = $lang['125'];
        } elseif (intval($mobileScore) < 79) {
            $mobileClass = 'improveBox';
            $mobileSpeed = $lang['126'];
        } else {
            $mobileClass = 'passedBox';
            $mobileSpeed = $lang['126'];
        }

        $pageSpeedInsightData = array($desktopScore, $mobileScore);

        $updateStr = serBase($pageSpeedInsightData);
        updateToDbPrepared($con, 'domains_data', array('page_speed_insight' => $updateStr), array('domain' => $domainStr));

$desktopMsg = <<< EOT
<script>var desktopPageSpeed = new Gauge({
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

        $seoBox48 = '<div class="' . $desktopClass . '">
    <div class="msgBox">
        <div class="row">
            <div class="col-sm-6 text-center">
                <canvas id="desktopPageSpeed"></canvas>
                ' . $desktopMsg . '
            </div>
            <div class="col-sm-6">
            <h2>' . $desktopScore . ' / 100</h2>
            <h4>' . $lang['123'] . '</h4>
            <p><strong>' . ucfirst($my_url_host) . '</strong> ' . $lang['127'] . ' <strong>' . $desktopSpeed . '</strong>. ' . $lang['128'] . '</p>
            </div>
        </div>   
    </div>
    <div class="seoBox48 suggestionBox">
    ' . $pageSpeedInsightDesktop_Msg . '
    </div> 
    </div>';

        $seoBox49 = '<div class="' . $mobileClass . '">
    <div class="msgBox">   
        <div class="row">
            <div class="col-sm-6 text-center">
                <canvas id="mobilePageSpeed"></canvas>
                ' . $mobileMsg . '
            </div>
        <div class="col-sm-6">
            <h2>' . $mobileScore . ' / 100</h2>
            <h4>' . $lang['123'] . '</h4>
            <p><strong>' . ucfirst($my_url_host) . '</strong> ' . $lang['129'] . ' <strong>' . $mobileSpeed . '</strong>. ' . $lang['128'] . '</p>
        </div>
        </div>
    </div>
    <div class="seoBox49 suggestionBox">
    ' . $pageSpeedInsightMobile_Msg . '
    </div> 
    </div>';

        if (!isset($_SESSION['twebUsername'])) {
            if (!isAllowedStats($con, 'seoBox48'))
                $seoBox48 = $seoBoxLogin;
            if (!isAllowedStats($con, 'seoBox49'))
                $seoBox49 = $seoBoxLogin;
        }

        // Output the PageSpeed Insight results.
        echo $seoBox48 . $sepUnique . $seoBox49;
        die();
    } // End PageSpeed Insight handler

    /*
     * -----------------------------------------------------------------
     * CLEAN OUT / FINALIZE ANALYSIS
     * -----------------------------------------------------------------
     * Updates the final score and cleans up cached data.
     */
    if (isset($_POST['cleanOut'])) {
        $passscore = raino_trim($_POST['passscore']);
        $improvescore = raino_trim($_POST['improvescore']);
        $errorscore = raino_trim($_POST['errorscore']);

        $score = array($passscore, $improvescore, $errorscore);

        $updateStr = serBase($score);
        updateToDbPrepared($con, 'domains_data', array('score' => $updateStr, 'completed' => 'yes'), array('domain' => $domainStr));

        $data = mysqliPreparedQuery($con, "SELECT * FROM domains_data WHERE domain=?", 's', array($domainStr));
        if ($data !== false) {
            $pageSpeedInsightData = decSerBase($data['page_speed_insight']);
            $alexa = decSerBase($data['alexa']);

            $finalScore = ($passscore == '') ? '0' : $passscore;
            $globalRank = ($alexa[0] == '') ? '0' : $alexa[0];
            $pageSpeed = ($pageSpeedInsightData[0] == '') ? '0' : $pageSpeedInsightData[0];

            // Determine username
            if (!isset($_SESSION['twebUsername']))
                $username = trans('Guest', $lang['11'], true);
            else
                $username = $_SESSION['twebUsername'];

            if ($globalRank == 'No Global Rank')
                $globalRank = 0;

            $other = serBase(array($finalScore, $globalRank, $pageSpeed));

            // Add site into recent history.
            addToRecentSites($con, $domainStr, $ip, $username, $other);
        }

        // Clear cached data file.
        delFile($filename);
    }

} // End of POST REQUEST HANDLER

// End of AJAX Handler script.
die();
?>
