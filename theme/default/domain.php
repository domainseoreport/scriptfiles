<?php
// Prevent direct access to this script
defined('APP_NAME') or die(header('HTTP/1.1 403 Forbidden'));

/*
 * Author: Balaji
 * Theme: Default Style
 * Copyright 2023 ProThemes.Biz
 */

// Array of messages used for solving SEO issues
$solveMsg = array(
    // Fill in as required...
);

// HTML for the loading bar animation
$loadingBar = '
    <div class="text-center">
         <div class="spinner-border text-primary" role="status">
            <span class="sr-only">' . $lang['33'] . '</span>
         </div>
         <br /><br />   
    </div>
';

// If update is found, initialize each SEO box with the loading bar content
if ($updateFound) {
    for ($i = 1; $i <= 60; $i++) {
        ${'seoBox' . $i} = $loadingBar;
    }
   
} else {

    ?>
    <!-- Define JavaScript variables for non-update scenario -->
    <script>
           var passScore = parseInt('<?php echo makeJavascriptStr($passScore); ?>', 10) || 0;
            var improveScore = parseInt('<?php echo makeJavascriptStr($improveScore); ?>', 10) || 0;
            var errorScore = parseInt('<?php echo makeJavascriptStr($errorScore); ?>', 10) || 0;
            var overallPercent = parseInt('<?php echo makeJavascriptStr($overallPercent); ?>', 10) || 0;
            console.log("JS passScore =", passScore);
            console.log("JS improveScore =", improveScore);
            console.log("JS errorScore =", errorScore);
            console.log("JS overallPercent =", overallPercent);
</script>

<?php } ?>
 
<script>
    // Setup configuration variables for JavaScript use
    var hashCode    = '<?php echo $hashCode; ?>';
    // Use the domain_access_url from DB if available; otherwise, fall back to $my_url_host
    var inputHost   = '<?php echo !empty($data["domain_access_url"]) ? $data["domain_access_url"] : $my_url_host; ?>';
    var isOnline    = '<?php echo $isOnline; ?>';
    var pdfUrl      = '<?php echo $pdfUrl; ?>';
    var pdfMsg      = '<?php echo makeJavascriptStr($lang['34']); ?>';
    var domainPath  = '<?php createLink('domains'); ?>';
    var scoreTxt    = '<?php echo makeJavascriptStr($lang['195']); ?>';
    var CANV_GAUGE_FONTS_PATH = '<?php themeLink('fonts'); ?>';
    var domainId    = <?php echo $data["id"]?>;
</script>

<!-- Include external JavaScript and CSS files -->
<script src="<?php themeLink('js/circle-progress.js'); ?>"></script>
<script src="<?php themeLink('js/pagespeed.min.js'); ?>" type="text/javascript"></script>
<link href="<?php themeLink('css/www.css'); ?>" rel="stylesheet" />

<!-- Main Container -->
<div class="container">
    <div class="row">
        <div class="col-sm-12">
            <?php
            // If an error is set, display an alert and a homepage button.
            if (isset($error)) {
                echo '
                    <br/><br/>
                    <div class="alert alert-error">
                        <strong>' . $lang['53'] . '</strong> ' . $error . '
                    </div>
                    <br/><br/>
                    <div class="text-center">
                        <a class="btn btn-info" href="' . $baseURL . '">' . trans('Go to homepage', $lang['22'], true) . '</a>
                    </div>
                    <br/>';
            } else {
            ?>
                <!-- Overview Section -->
                <div id="overview">
                    <br />
                    <?php if ($updateFound) { ?>
                        <!-- Progress Bar displayed during update -->
                        <div class="progress progress-lg" id="progress-bar">
                            <div id="progressbar" class="progress-bar progress-bar-primary progress-bar-striped active" role="progressbar" aria-valuemin="5" aria-valuemax="100">
                                <div style="font-weight: bold; position: absolute; width: 100%; color: white;">
                                    <?php trans('Analyzing Website', $lang['MS6']); ?> - 
                                    <span id="progress-label">0%</span> 
                                    <?php trans('Complete', $lang['MS7']); ?>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                    <br />

                    <!-- Scoreboard Section -->
                    <div id="scoreBoard" class="row">
                        <!-- Screenshot Column -->
                        <div class="col-md-4 screenBox">
                            <div id="screenshot">
                                <div id="screenshotData"> 
                                    <?php echo $screenshot;?>
                                </div>
                                <div class="computer"></div>
                            </div>
                        </div>
                        <!-- Details Column -->
                        <div class="col-md-5 levelBox">
                            <div>
                                <h1><?php echo ucfirst($my_url_host); ?></h1>
                            </div>
                            <div class="timeBox">
                                <?php echo $disDate; ?>
                            </div>
                            <!-- Passed Progress -->
                            <div class="progressBox">
                                <span class="scoreProgress-label passedBox">
                                    <?php trans('Passed', $lang['26']); ?>
                                </span>
                                <div class="scoreProgress scoreProgress-xs scoreProgress-success">
                                    <div id="passScore" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" class="scoreProgress-bar">
                                        <span class="scoreProgress-value">0%</span>
                                    </div>
                                </div>
                            </div>
                            <!-- To Improve Progress -->
                            <div class="progressBox">
                                <span class="scoreProgress-label improveBox">
                                    <?php trans('To Improve', $lang['25']); ?>
                                </span>
                                <div class="scoreProgress scoreProgress-xs scoreProgress-warning">
                                    <div id="improveScore" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" class="scoreProgress-bar">
                                        <span class="scoreProgress-value">0%</span>
                                    </div>
                                </div>
                            </div>
                            <!-- Errors Progress -->
                            <div class="progressBox">
                                <span class="scoreProgress-label errorBox">
                                    <?php trans('Errors', $lang['24']); ?>
                                </span>
                                <div class="scoreProgress scoreProgress-xs scoreProgress-danger">
                                    <div id="errorScore" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" class="scoreProgress-bar">
                                        <span class="scoreProgress-value">0%</span>
                                    </div>
                                </div>
                            </div>
                            <br />
                        </div>
                        <!-- Overall Score Column -->
                        <div class="col-md-2 circleBox">
                            <div class="second circle" data-size="130" data-thickness="5">
                                <canvas width="130" height="130"></canvas>
                                <strong id="overallscore">
                                    0<i class="newI"><?php echo $lang['195']; ?></i>
                                </strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Social Sharing and Download/Update Links Section -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="top40">
                            <ul class="social-icons icon-circle icon-rotate list-unstyled list-inline text-center">
                                <li><?php trans('SHARE', $lang['122']); ?></li>
                                <li><a target="_blank" rel="nofollow" href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $shareLink; ?>"><i class="fa fa-facebook"></i></a></li>
                                <li><a target="_blank" rel="nofollow" href="https://twitter.com/home?status=<?php echo $shareLink; ?>"><i class="fa fa-twitter"></i></a></li>
                                <li><a target="_blank" rel="nofollow" href="https://pinterest.com/pin/create/button/?url=<?php echo $shareLink; ?>"><i class="fa fa-pinterest"></i></a></li>
                                <li><a target="_blank" rel="nofollow" href="https://www.tumblr.com/share/link?url=<?php echo $shareLink; ?>"><i class="fa fa-tumblr"></i></a></li>
                                <li><a target="_blank" rel="nofollow" href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo $shareLink; ?>"><i class="fa fa-linkedin"></i></a></li>
                                <li><a target="_blank" rel="nofollow" href="https://del.icio.us/post?url=<?php echo $shareLink; ?>"><i class="fa fa-delicious"></i></a></li>
                                <li><a target="_blank" rel="nofollow" href="https://www.stumbleupon.com/submit?url=<?php echo $shareLink; ?>"><i class="fa fa-stumbleupon"></i></a></li>
                                <li><a target="_blank" rel="nofollow" href="https://www.reddit.com/login?dest=https://www.reddit.com/submit?url=<?php echo $shareLink; ?>&title=<?php echo ucfirst($my_url_host); ?>"><i class="fa fa-reddit"></i></a></li>
                                <li><a target="_blank" rel="nofollow" href="https://digg.com/submit?phase=2&url=<?php echo $shareLink; ?>"><i class="fa fa-digg"></i></a></li>
                                <li><a target="_blank" rel="nofollow" href="https://vk.com/share.php?url=<?php echo $shareLink; ?>"><i class="fa fa-vk"></i></a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-6 align-items-center">
                       
                   
                    

                    <div class="card p-3 mb-4" style="max-width: 400px;">
  <div class="d-flex justify-content-between align-items-center">
    <div>
      <!-- Title -->
      <h5 class="card-title mb-1" style="font-weight: 600;">Analysis Status</h5>

      <!-- Last Analyzed Date -->
      <?php if (!empty($lastAnalyzedDate)): ?>
        <small class="text-muted">
          <strong>Last Analyzed:</strong> 
          <?php echo htmlspecialchars($lastAnalyzedDate); ?>
        </small>
      <?php else: ?>
        <small class="text-muted">No previous analysis date found.</small>
      <?php endif; ?>
    </div>

    <div>
      <?php if ($canUpdateNow): ?>
        <!-- Re-analyze button is enabled -->
        <a href="<?php echo $updateUrl; ?>" class="btn btn-success btn-sm">
          <i class="fa fa-refresh me-1"></i> Re‑Analyze
        </a>
      <?php else: ?>
        <!-- Re-analyze is disabled, show the next possible date -->
        <button class="btn btn-secondary btn-sm" type="button" disabled
                data-bs-toggle="tooltip"
                data-bs-placement="top"
                title="Next update available on <?php echo htmlspecialchars($nextAnalyzeDate); ?>">
          <i class="fa fa-clock-o me-1"></i> Re‑Analyze on 
          <?php echo htmlspecialchars($nextAnalyzeDate); ?>
        </button>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- (Optional) Initialize Bootstrap tooltips -->
<script>
  var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });
</script>





                    </div>
                </div>

                <div class="clearfix"></div>

                <!-- SEO Analysis Sections -->

            <div id="seo1">
    <h2 class="seoBox-title"><?php trans('SEO', $lang['35']); ?></h2>

    <!-- Meta Information Section (Title, Description, Keywords) -->
    <div class="card shadow seo-card mb-4">
        <div class="card-header border-bottom">
            <h5 class="card-title mb-0">Meta Information</h5>
        </div>
        <div class="card-body">
            <!-- Title Section -->
            <!-- Title Section -->
                <div class="col-md-12" id="seoBox1">
                    <?php echo $metaParts[0] ?? ''; ?>
                </div>
                <!-- Description Section -->
                <div class="col-md-12" id="seoBox2">
                    <?php echo $metaParts[1] ?? ''; ?>
                </div>
                <!-- Keywords Section -->
                <div class="col-md-12" id="seoBox3">
                    <?php echo $metaParts[2] ?? ''; ?>
                </div>
        </div>
    </div>
</div>

                    <!-- Heading Tags Section -->
                    <div class="card shadow seo-card mb-4">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="me-3">
                                    <i class="fa fa-check-circle text-success" style="font-size:2rem;"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold"><?php outHeadBox($lang['AN16'], $solveMsg, 2); ?></h5>
                                </div>
                            </div>
                            <div class="contentBox" id="seoBox4">
                                <?php echo $seoBox4; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Keywords Cloud Section -->
                            <div class="card shadow seo-card mb-4">
                                <div class="card-body"> 
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="me-3">
                                            <i class="fa fa-check-circle text-success" style="font-size:2rem;"></i>
                                        </div>
                                        <div>
                                            <h5 class="fw-bold"><?php outHeadBox($lang['AN30'], $solveMsg, 1); ?></h5>
                                        </div>
                                    </div>
                                    <div class="contentBox" id="seoBox8">
                                        <?php echo $seoBox8; ?>
                                    </div>
                                </div>
                            </div>

                    <!-- Link Analysis Section -->
                    <div id="link-analysis" class="container mt-4">
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title mb-0"><?php trans('Link Analysis', $lang['21']); ?></h2>
                            </div>
                            <div class="card-body">
                                <div class="contentBox" id="seoBox13">
                                    <?php echo $seoBox13; ?>
                                </div>
                            </div>
                        </div>
                    </div>


                    <div id="card-analysis" class="container mt-4">
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title mb-0">Cards <?php trans('Link Analysis', $lang['21']); ?></h2>
                            </div>
                            <div class="card-body">
                                <div class="contentBox" id="seoBox51">
                                <?php echo $seoBox51; ?>
                                </div>
                            </div>
                        </div>
                    </div>


                     <!-- Social URL Section -->
                   <div id="page-analysis" class="container mt-4">
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title mb-0">Cards <?php trans('Link Analysis', $lang['21']); ?></h2>
                            </div>
                            <div class="seoBox54 card-body">
                                <div class="contentBox" id="seoBox54">
                                <?php echo $seoBox54; ?>
                                </div>
                            </div>
                        </div>
                    </div>


                <!-- page speed insight -->
                   <div id="page-analysis" class="container mt-4">
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title mb-0">Cards <?php trans('Link Analysis', $lang['21']); ?></h2>
                            </div>
                            <div class="seoBox55 card-body">
                                <div class="contentBox" id="seoBox55">
                                <?php echo $seoBox55; ?>
                                </div>
                            </div>
                        </div>
                    </div>

            <!-- Social URL Section -->
                   <div id="card-analysis" class="container mt-4">
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title mb-0">Cards <?php trans('Link Analysis', $lang['21']); ?></h2>
                            </div>
                            <div class="seoBox52 card-body">
                                <div class="contentBox" id="seoBox52">
                                <?php echo $seoBox52; ?>
                                </div>
                            </div>
                        </div>
                    </div>


                    <!-- Text/HTML Ratio Section -->
                    <div id="text-analysis" class="container mt-4">
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title mb-0"><?php outHeadBox($lang['AN35'], $solveMsg, 2); ?></h2>
                            </div>
                            <div class="card-body">
                                <div class="contentBox" id="seoBox9">
                                    <?php echo $seoBox9; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Alt Attribute Section -->
                    <div id="image-analysis" class="container mt-4">
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title mb-0"><?php outHeadBox($lang['AN20'], $solveMsg, 1); ?></h2>
                            </div>
                            <div class="card-body">
                                <div class="contentBox" id="seoBox6">
                                    <?php echo $seoBox6; ?>
                                </div>
                            </div>
                        </div>
                    </div>
<?php //////////////////////////////////////////// Google Preview Section ///////////////////////////////////////////////////////// ?>    


                    <div id="image-analysis" class="container mt-4">
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title mb-0"><?php outHeadBox($lang['AN17'],$solveMsg,4); ?></h2>
                            </div>
                            <div class="card-body">
                                <div class="contentBox" id="seoBox5">
                                    <?php echo $seoBox5; ?>
                                </div>
                            </div>
                        </div>
                    </div>


               
 

  <?php //////////////////////////////////////////// Server Details Section ///////////////////////////////////////////////////////// ?>





                    <div id="serverdetails-analysis" class="container mt-4">
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title mb-0"><?php outHeadBox($lang['AN104'], $solveMsg, 4); ?></h2>
                            </div>
                            <div class="card-body">
                                <div class="contentBox" id="seoBox36">
                                    <?php echo $seoBox36; ?>
                                 
                                </div>
                            </div>
                        </div>
                    </div>

  
<?php //////////////////////////////////////////// Schema Section ///////////////////////////////////////////////////////// ?> 


                    <div id="serverdetails-analysis" class="container mt-4">    
                        <div class="card" id="social">
                            <div class="card-header">
                                <h2 class="card-title mb-0"><?php trans('Social',$lang['19']); ?></h2>
                            </div>
                            <div class="card-body">
                                <div class="contentBox" id="seoBox44">
                                    <?php echo $seoBox44; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                     
                
 
 
 
  
 
<?php ////////////////////////////////////////////  Section ///////////////////////////////////////////////////////// ?>                    
 
                </div>
 
          
                
          
                
                 
                
                
                
                <div id="visitors" class="hide">
                <div class="clearSep"></div>
             
                
       
                
                <div class="seoBox hide">
                    <?php outHeadBox($lang['AN115'],$solveMsg,4); ?> 
                    <div class="contentBox" id="seoBox47">
                        <?php echo $seoBox47; ?> 
                    </div>
                    <?php outQuestionBox($lang['AN4']); ?>
	            </div>
                </div>
                
               
                  
                           <!-- New Site Analysis Form -->
                <div class="text-center my-4">
                    <h4 style="color: #989ea8;"><?php trans('Try New Site', $lang['38']); ?></h4>
                    <form method="POST" action="<?php createLink('domain'); ?>" onsubmit="return fixURL();">
                        <div class="input-group reviewBox">
                            <input type="text" tabindex="1" placeholder="<?php trans('Website URL to review', $lang['37']); ?>" id="url" name="url" class="form-control reviewIn"/>
                            <button tabindex="2" type="submit" name="generate" class="btn btn-info">
                                <span class="ready"><?php trans('Analyze', $lang['36']); ?></span>
                            </button>
                        </div>
                    </form>
                </div>
            <?php } ?>
            <br /> 

           <!-- Advertisement Section -->
           <div class="xd_top_box bottom40 text-center">
                <?php echo $ads_720x90; ?>
            </div>
        </div>
    </div>
</div>
<?php if (!$updateFound) { ?>
<script>
  window.seoReport = {
    passScore: <?php echo json_encode($passScore); ?>,
    improveScore: <?php echo json_encode($improveScore); ?>,
    errorScore: <?php echo json_encode($errorScore); ?>
  };
  console.log("DB session seoReport:", window.seoReport);
</script>
<?php } ?><?php

?>
<div class="toast-container position-fixed bottom-0 end-0 p-3">
  <div id="updateToast" class="toast align-items-center text-bg-info border-0" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body">
        <?php echo $nextUpdateMessage; ?>
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  </div>
</div>
<!-- Conditionally include JavaScript based on update status -->
<?php if ($updateFound) { ?>
    <script src="<?php themeLink('js/domain.js?v6'); ?>" type="text/javascript"></script>
<?php } else { ?>
    <script src="<?php themeLink('js/dbdomain.js?v6'); ?>" type="text/javascript"></script>
<?php } ?>

