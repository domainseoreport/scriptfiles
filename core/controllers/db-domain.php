<?php
defined('DB_DOMAIN') or die(header('HTTP/1.1 403 Forbidden'));
 
require_once(LIB_DIR . 'SeoTools.php');

// Get the Domain ID from your data.
$domainId = $data['id'];

// Define a Login Box (used when user is not logged in)
$seoBoxLogin = '
<div class="lowImpactBox">
  <div class="msgBox">
    ' . $lang['39'] . '
    <br /><br />
    <div class="altImgGroup">
      <a class="btn btn-success forceLogin" target="_blank" href="' . createLink('account/login', true) . '" title="' . $lang['40'] . '">
        ' . $lang['40'] . '
      </a>
    </div>
    <br />
  </div>
</div>';


$updatedDate = $data['updated_date'] ?? null;
$lastAnalyzedDate = '';
$nextAnalyzeDate = '';
$canUpdateNow = true;

if (!empty($updatedDate)) {
    // Convert to timestamp
    $lastUpdateTs = strtotime($updatedDate);
    // Format as e.g. "10.03.2025"
    $lastAnalyzedDate = date('d.m.Y', $lastUpdateTs);

    // Next update is 24 hours after last update
    $nextUpdateTs = $lastUpdateTs + 86400;
    $nextAnalyzeDate = date('d.m.Y', $nextUpdateTs);

    // If we haven't hit nextUpdateTs yet, then updates are disallowed
    if (time() < $nextUpdateTs) {
        $canUpdateNow = false;
    }
}


// Initialize the SeoTools class.
$seoTools = new SeoTools($dummyHtml, $con, $domainStr, $lang, $urlParse, $sepUnique, $seoBoxLogin, $domainId);   




// -------------------------------------------------------------------------
// Meta Data
// -------------------------------------------------------------------------
$savedMetaJson = fetchDomainModuleData($con, $domainId, "meta") ?? '';
$meta_data = jsonDecode($savedMetaJson);
if ($savedMetaJson) {
    // Call showMeta() to get the meta analysis output.
    $metaHtml = $seoTools->showMeta($savedMetaJson);
    // You can split the output if you need individual parts.
    $metaParts = explode($seoTools->getSeparator(), $metaHtml);
}

// -------------------------------------------------------------------------
// Google Preview (from meta data)
// -------------------------------------------------------------------------
$seoBox5 = $seoTools->showGooglePreview($savedMetaJson);

// -------------------------------------------------------------------------
// Headings
// -------------------------------------------------------------------------
$headings = fetchDomainModuleData($con, $domainId, "heading") ?? ''; 
$seoBox4 = $seoTools->showHeading($headings);

// -------------------------------------------------------------------------
// Image Alt Tags
// -------------------------------------------------------------------------
$imageData = fetchDomainModuleData($con, $domainId, "image_alt") ?? '';
$seoBox6  = $seoTools->showImage($imageData);

// -------------------------------------------------------------------------
// Keyword Cloud & Consistency
// -------------------------------------------------------------------------
$savedKeywordsJson = fetchDomainModuleData($con, $domainId, "keywords_cloud") ?? '';
if ($savedKeywordsJson) {
    $seoBox8 = $seoTools->showKeyCloudAndConsistency($savedKeywordsJson);
} else {
    $seoBox8 = '<div class="alert alert-warning">No keyword cloud data available.</div>';
}

// -------------------------------------------------------------------------
// Text-to-HTML Ratio
// -------------------------------------------------------------------------
$textRatio = fetchDomainModuleData($con, $domainId, "text_ratio") ?? '';
$seoBox9 = $seoTools->showTextRatio($textRatio);

// -------------------------------------------------------------------------
// Social Card (Site Cards)
// -------------------------------------------------------------------------
$sitecards = fetchDomainModuleData($con, $domainId, "sitecards") ?? '';
$seoBox51 = $seoTools->showCards($sitecards);

// -------------------------------------------------------------------------
// Social URLs
// -------------------------------------------------------------------------
$socialURLs = fetchDomainModuleData($con, $domainId, "social_urls") ?? '';
$seoBox52 = $seoTools->showSocialUrls($socialURLs);

// -------------------------------------------------------------------------
// Page Analytics Report
// -------------------------------------------------------------------------
$page_analytics = fetchDomainModuleData($con, $domainId, "page_analytics") ?? '';
$seoBox54 = $seoTools->showPageAnalytics($page_analytics);

// -------------------------------------------------------------------------
// PageSpeed Insights
// -------------------------------------------------------------------------
$showPageSpeedInsight = fetchDomainModuleData($con, $domainId, "page_speed") ?? '';
if (is_array($showPageSpeedInsight)) {
    $showPageSpeedInsight = json_encode($showPageSpeedInsight);
}
$seoBox55 = $seoTools->showPageSpeedInsightConcurrent($showPageSpeedInsight);

// -------------------------------------------------------------------------
// In-Page Links
// -------------------------------------------------------------------------
$links_analyser = fetchDomainModuleData($con, $domainId, "linkanalysis") ?? '';
$seoBox13 = $seoTools->showInPageLinks($links_analyser);

// -------------------------------------------------------------------------
// Server Information
// -------------------------------------------------------------------------
$server_loc = fetchDomainModuleData($con, $domainId, "server_info") ?? '';
$seoBox36 = $seoTools->showServerInfo($server_loc);

// -------------------------------------------------------------------------
// Schema Data
// -------------------------------------------------------------------------
$schemadata = fetchDomainModuleData($con, $domainId, "schema") ?? '';
$seoBox44 = $seoTools->showSchema($schemadata);

// -------------------------------------------------------------------------
// Screen Shot
// -------------------------------------------------------------------------
$websiteScreenshot = fetchDomainModuleData($con, $domainId, "desktop_screenshot") ?? '';
if ($websiteScreenshot) {
    $screenshot = '<img src="'.$websiteScreenshot.'" alt="'.$data["domain"].'" />';
} else {
    $screenshot = '<img src="<?php echo $screenshot;?>" alt="'.$data["domain"].'" />';
}


// -------------------------------------------------------------------------
// Final Score
// -------------------------------------------------------------------------
$score = json_decode($data['score'], true);
$passScore = $score["passed"];
$improveScore = $score["improve"];
$errorScore = $score["errors"];
$overallPercent = $score["percent"];

// Date & Time
$date_raw = date_create(trim($data['date']));
$disDate = date_format($date_raw, "F, j Y h:i:s A");

// Login Access Check
if (!isset($_SESSION['twebUsername'])) {
    if ($enable_reg) {
        foreach ($reviewerSettings['reviewer_list'] as $reviewer) {
            ${$reviewer} = $seoBoxLogin;
        }
    }
}
?>
