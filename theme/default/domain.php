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
    
    
);

// HTML for the loading bar animation
$loadingBar = '
    <div class="text-center">
        <img src="' . themeLink('img/load.gif', true) . '" alt="Loading..." />
        <br /><br />
        ' . $lang['33'] . '...
        <br /><br />
    </div>
';

// If update is found, initialize each SEO box with the loading bar content
if ($updateFound) {
    for ($i = 1; $i <= 49; $i++) {
        ${'seoBox' . $i} = $loadingBar;
    }
} else {
    ?>
    <!-- Define JavaScript variables for non-update scenario -->
    <script>
        var passScore    = '<?php echo makeJavascriptStr($passScore); ?>';
        var improveScore = '<?php echo makeJavascriptStr($improveScore); ?>';
        var errorScore   = '<?php echo makeJavascriptStr($errorScore); ?>';
    </script>
<?php } ?>

<script>
    // Set up additional configuration variables for JavaScript use
    var hashCode    = '<?php echo $hashCode; ?>';
    var inputHost   = '<?php echo $my_url_host; ?>';
    var isOnline    = '<?php echo $isOnline; ?>';
    var pdfUrl      = '<?php echo $pdfUrl; ?>';
    var pdfMsg      = '<?php echo makeJavascriptStr($lang['34']); ?>';
    var domainPath  = '<?php createLink('domains'); ?>';
    var scoreTxt    = '<?php echo makeJavascriptStr($lang['195']); ?>';
    var CANV_GAUGE_FONTS_PATH = '<?php themeLink('fonts'); ?>';
</script>

<!-- Include external JavaScript and CSS files -->
<script src="<?php themeLink('js/circle-progress.js'); ?>"></script>
<script src="<?php themeLink('js/pagespeed.min.js'); ?>" type="text/javascript"></script>
<link href="<?php themeLink('css/www.css'); ?>" rel="stylesheet" />

<div class="container">
    <div class="row">
        <div class="col-sm-12">
            <?php
            // If there is an error, display the error message and a link to the homepage
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
                <div id="overview">
                    <br />
                    <?php if ($updateFound) { ?>
                        <!-- Inline styles for the progress bar; consider moving these to an external CSS file -->
                        <style>
                            .progress-bar-primary {
                                background-color: #3498DC;
                            }
                            .progress {
                                margin-top: 20px;
                                background-color: #d2d2d2;
                            }
                        </style>
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
                        <!-- Screenshot Display -->
                        <div class="col-md-4 screenBox">
                            <div id="screenshot">
                                <div id="screenshotData">
                                    <div class="loader">
                                        <div class="side"></div>
                                        <div class="side"></div>
                                        <div class="side"></div>
                                        <div class="side"></div>
                                        <div class="side"></div>
                                        <div class="side"></div>
                                        <div class="side"></div>
                                        <div class="side"></div>
                                    </div>
                                    <div class="loaderLabel">
                                        <?php trans('Loading...', $lang['23']); ?>
                                    </div>
                                </div>
                                <div class="computer"></div>
                            </div>
                        </div>
                        <!-- URL Host and Progress Bars -->
                        <div class="col-md-5 levelBox">
                            <div>
                                <h1><?php echo ucfirst($my_url_host); ?></h1>
                            </div>
                            <div class="timeBox">
                                <?php echo $disDate; ?>
                            </div>
                            <div class="progressBox">
                                <span class="scoreProgress-label passedBox">
                                    <?php trans('Passed', $lang['26']); ?>
                                </span>
                                <div class="scoreProgress scoreProgress-xs scoreProgress-success">
                                    <div id="passScore" aria-valuemax="100" aria-valuenow="0" aria-valuemin="0" role="progressbar" class="scoreProgress-bar">
                                        <span class="scoreProgress-value">0%</span>
                                    </div>
                                </div>
                            </div>
                            <div class="progressBox">
                                <span class="scoreProgress-label improveBox">
                                    <?php trans('To Improve', $lang['25']); ?>
                                </span>
                                <div class="scoreProgress scoreProgress-xs scoreProgress-warning">
                                    <div id="improveScore" aria-valuemax="100" aria-valuenow="0" aria-valuemin="0" role="progressbar" class="scoreProgress-bar">
                                        <span class="scoreProgress-value">0%</span>
                                    </div>
                                </div>
                            </div>
                            <div class="progressBox">
                                <span class="scoreProgress-label errorBox">
                                    <?php trans('Errors', $lang['24']); ?>
                                </span>
                                <div class="scoreProgress scoreProgress-xs scoreProgress-danger">
                                    <div id="errorScore" aria-valuemax="100" aria-valuenow="0" aria-valuemin="0" role="progressbar" class="scoreProgress-bar">
                                        <span class="scoreProgress-value">0%</span>
                                    </div>
                                </div>
                            </div>
                            <br />
                        </div>
                        <!-- Overall Score Gauge -->
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

                <!-- Social Sharing and Download/Update Links -->
                <div class="row">
                    <div class="col-md-9">
                        <div class="top40">
                            <ul class="social-icons icon-circle icon-rotate list-unstyled list-inline text-center">
                                <li><?php trans('SHARE', $lang['122']); ?></li>
                                <li> <a target="_blank" rel="nofollow" href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $shareLink; ?>"><i class="fa fa-facebook"></i></a></li>  
                                <li><a target="_blank" rel="nofollow" href="https://twitter.com/home?status=<?php echo $shareLink; ?>"><i class="fa fa-twitter"></i></a> </li> 
                                <li> <a target="_blank" rel="nofollow" href="https://pinterest.com/pin/create/button/?url=<?php echo $shareLink; ?>"><i class="fa fa-pinterest"></i></a> </li>
                                <li> <a target="_blank" rel="nofollow" href="https://www.tumblr.com/share/link?url=<?php echo $shareLink; ?>"><i class="fa fa-tumblr"></i></a> </li> 
                                <li> <a target="_blank" rel="nofollow" href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo $shareLink; ?>"><i class="fa fa-linkedin"></i></a> </li> 
                                <li> <a target="_blank" rel="nofollow" href="https://del.icio.us/post?url=<?php echo $shareLink; ?>"><i class="fa fa-delicious"></i></a> </li> 
                                <li> <a target="_blank" rel="nofollow" href="https://www.stumbleupon.com/submit?url=<?php echo $shareLink; ?>"><i class="fa fa-stumbleupon"></i></a></li> 
                                <li> <a target="_blank" rel="nofollow" href="https://www.reddit.com/login?dest=https://www.reddit.com/submit?url=<?php echo $shareLink; ?>&title=<?php echo ucfirst($my_url_host); ?>"><i class="fa fa-reddit"></i></a></li> 
                                <li> <a target="_blank" rel="nofollow" href="https://digg.com/submit?phase=2&url=<?php echo $shareLink; ?>"><i class="fa fa-digg"></i></a></li> 
                                <li> <a target="_blank" rel="nofollow" href="https://vk.com/share.php?url=<?php echo $shareLink; ?>"><i class="fa fa-vk"></i></a></li>   
                                <!-- Additional social sharing links omitted for brevity -->
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-3 align-items-center">
                        <div class="pdfBox">
                            <a href="<?php echo $pdfUrl; ?>" id="pdfLink" class="btn btn-lgreen btn-sm">
                                <i class="fa fa-cloud-download"></i> 
                                <?php trans('Download as PDF', $lang['28']); ?>
                            </a>
                            <a href="<?php echo $updateUrl; ?>" class="btn btn-red btn-sm">
                                <i class="fa fa-refresh"></i> 
                                <?php trans('Update Data', $lang['27']); ?>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="clearfix"></div>

                <!-- SEO Analysis Sections -->


        <div class="clearfix"></div>
                 
               <div id="seo1">   
                <h2 class="seoBox-title">
                    <?php trans('SEO',$lang['35']); ?>
                </h2>
               <div class="seoBox"  >
                     <?php outHeadBox($lang['AN1'],$solveMsg,1); ?> 
                    <div class="contentBox" id="seoBox1">
                        <?php echo $seoBox1; ?>
                    </div>
                    <?php //outQuestionBox($lang['AN4']); ?>
	            </div>
                
                <div class="seoBox" >
                    <?php outHeadBox($lang['AN2'],$solveMsg,1); ?>
                    <div class="contentBox" id="seoBox2">
                        <?php echo $seoBox2; ?>
                    </div>
                    <?php //outQuestionBox($lang['AN4']); ?>
	            </div>
                
                <div class="seoBox">
                    <?php outHeadBox($lang['AN3'],$solveMsg,1); ?>
                    <div class="contentBox" id="seoBox3">
                        <?php echo $seoBox3; ?>
                    </div>
                    <?php //outQuestionBox($lang['AN4']); ?>
	            </div>  
                
                <div class="seoBox headingResult"  >
                    <?php outHeadBox($lang['AN16'],$solveMsg,2); ?>
                    <div class="contentBox" id="seoBox4">
                    <?php echo $seoBox4; ?>
                    </div>
                    <?php outQuestionBox($lang['AN4']); ?>
	            </div>  
                
                <div class="seoBox">
                    <?php outHeadBox($lang['AN17'],$solveMsg,4); ?>
                    <div class="contentBox" id="seoBox5">
                        <?php echo $seoBox5; ?>
                    </div>
                    <?php outQuestionBox($lang['AN4']); ?>
	            </div>  
                
                <div class="seoBox altImgResult">
                    <?php outHeadBox($lang['AN20'],$solveMsg,1); ?>
                    <div class="contentBox" id="seoBox6">
                        <?php echo $seoBox6; ?>
                    </div>
                    <?php outQuestionBox($lang['AN4']); ?>
	            </div>  
                
                <div class="seoBox">
                    <?php outHeadBox($lang['AN28'],$solveMsg,4); ?>
                    <div class="contentBox" id="seoBox7">
                        <?php echo $seoBox7; ?>
                    </div>
                    <?php outQuestionBox($lang['AN4']); ?>
	            </div>  
                
                <div class="seoBox keyConsResult">
                    <?php outHeadBox($lang['AN30'],$solveMsg,1); ?>
                    <div class="contentBox" id="seoBox8">
                        <?php echo $seoBox8; ?>
                    </div>
                    <?php outQuestionBox($lang['AN4']); ?>
	            </div>  
                
                <div class="seoBox">
                    <?php outHeadBox($lang['AN35'],$solveMsg,2); ?>
                    <div class="contentBox" id="seoBox9">
                        <?php echo $seoBox9; ?>
                    </div>
                    <?php outQuestionBox($lang['AN4']); ?>
	            </div> 
                
                <div class="seoBox">
                    <?php outHeadBox($lang['AN40'],$solveMsg,2); ?>
                    <div class="contentBox" id="seoBox10">
                        <?php echo $seoBox10; ?>
                    </div>
                    <?php outQuestionBox($lang['AN4']); ?>
	            </div> 
                
                <div class="seoBox">
                    <?php outHeadBox($lang['AN45'],$solveMsg,1); ?>
                    <div class="contentBox" id="seoBox11">
                        <?php echo $seoBox11; ?>
                    </div>
                    <?php outQuestionBox($lang['AN4']); ?>
	            </div> 
                
                <div class="seoBox">
                    <?php outHeadBox($lang['AN48'],$solveMsg,1); ?>
                    <div class="contentBox" id="seoBox12">
                        <?php echo $seoBox12; ?>
                    </div>
                    <?php outQuestionBox($lang['AN4']); ?>
	            </div> 
                        
                <div class="seoBox">
                    <?php outHeadBox($lang['AN59'],$solveMsg,1); ?>
                    <div class="contentBox" id="seoBox15">
                        <?php echo $seoBox15; ?>
                    </div>
                    <?php outQuestionBox($lang['AN4']); ?>
	            </div> 
                        
                <div class="seoBox">
                    <?php outHeadBox($lang['AN60'],$solveMsg,1); ?>
                    <div class="contentBox" id="seoBox16">
                        <?php echo $seoBox16; ?>
                    </div>
                    <?php outQuestionBox($lang['AN4']); ?>
	            </div> 
                
                <div class="seoBox">
                    <?php outHeadBox($lang['AN61'],$solveMsg,2); ?>
                    <div class="contentBox" id="seoBox17">
                        <?php echo $seoBox17; ?>
                    </div>
                    <?php outQuestionBox($lang['AN4']); ?>
	            </div> 
                
                <div class="seoBox">
                    <?php outHeadBox($lang['AN62'],$solveMsg,3); ?>
                    <div class="contentBox" id="seoBox18">
                        <?php echo $seoBox18; ?>
                    </div>
                    <?php outQuestionBox($lang['AN4']); ?>
	            </div>
                
                <div class="seoBox">
                    <?php outHeadBox($lang['AN63'],$solveMsg,1); ?>
                    <div class="contentBox" id="seoBox19">
                        <?php echo $seoBox19; ?>
                    </div>
                    <?php outQuestionBox($lang['AN4']); ?>
	            </div>
                
                <div class="seoBox">
                    <?php outHeadBox($lang['AN76'],$solveMsg,1); ?>
                    <div class="contentBox" id="seoBox20">
                        <?php echo $seoBox20; ?>
                    </div>
                    <?php outQuestionBox($lang['AN4']); ?>
	            </div>
                
                <div class="seoBox">
                    <?php outHeadBox($lang['AN81'],$solveMsg,4); ?>
                    <div class="contentBox" id="seoBox21">
                        <?php echo $seoBox21; ?>
                    </div>
                    <?php outQuestionBox($lang['AN4']); ?>
	            </div>
                
                <div class="seoBox whois">
                    <?php outHeadBox($lang['AN83'],$solveMsg,4); ?>
                    <div class="contentBox" id="seoBox22">
                        <?php echo $seoBox22; ?>
                    </div>
                    <?php outQuestionBox($lang['AN4']); ?>
	            </div>
                
                <div class="seoBox">
                    <?php outHeadBox($lang['AN110'],$solveMsg,2); ?>
                    <div class="contentBox" id="seoBox42">
                        <?php echo $seoBox42; ?>
                    </div>
                    <?php outQuestionBox($lang['AN4']); ?>
	            </div>
                
                <div class="seoBox">
                    <?php outHeadBox($lang['AN111'],$solveMsg,4); ?>
                    <div class="contentBox" id="seoBox43">
                        <?php echo $seoBox43; ?>
                    </div>
                    <?php outQuestionBox($lang['AN4']); ?>
	            </div>
                
                </div>
 
                <div id="usability">
                
                <div class="clearSep"></div>
                <h2 class="seoBox-title">
                    <?php trans('Usability',$lang['16']); ?>
                </h2>
                <div class="seoBox">
                    <?php outHeadBox($lang['AN94'],$solveMsg,4); ?>
                    <div class="contentBox" id="seoBox26">
                        <?php echo $seoBox26; ?>
                    </div>
                    <?php outQuestionBox($lang['AN4']); ?>
	            </div>
                
                <div class="seoBox">
                    <?php outHeadBox($lang['AN95'],$solveMsg,1); ?>
                    <div class="contentBox" id="seoBox27">
                        <?php echo $seoBox27; ?>
                    </div>
                    <?php outQuestionBox($lang['AN4']); ?>
	            </div>
                
                <div class="seoBox">
                    <?php outHeadBox($lang['AN96'],$solveMsg,1); ?>
                    <div class="contentBox" id="seoBox28">
                        <?php echo $seoBox28; ?>
                    </div>
                    <?php outQuestionBox($lang['AN4']); ?>
	            </div>
                
                <div class="seoBox">
                    <?php outHeadBox($lang['AN97'],$solveMsg,2); ?>
                    <div class="contentBox" id="seoBox29">
                        <?php echo $seoBox29; ?>
                    </div>
                    <?php outQuestionBox($lang['AN4']); ?>
	            </div>
                
                <div class="seoBox">
                    <?php outHeadBox($lang['AN98'],$solveMsg,3); ?>
                    <div class="contentBox" id="seoBox30">
                        <?php echo $seoBox30; ?>
                    </div>
                    <?php outQuestionBox($lang['AN4']); ?>
	            </div>
                
                <div class="seoBox">
                    <?php outHeadBox($lang['120'],$solveMsg,3); ?>
                    <div class="contentBox" id="seoBox48">
                        <?php echo $seoBox48; ?>
                    </div>
                    <?php outQuestionBox($lang['AN4']); ?>
	            </div> 
                
                <div class="seoBox">
                    <?php outHeadBox($lang['AN99'],$solveMsg,1); ?> 
                    <div class="contentBox" id="seoBox31">
                        <?php echo $seoBox31; ?> 
                    </div>
                    <?php outQuestionBox($lang['AN4']); ?>
	            </div>
                
                <div class="seoBox">
                    <?php outHeadBox($lang['AN100'],$solveMsg,4); ?>
                    <div class="contentBox" id="seoBox32">
                        <?php echo $seoBox32; ?>
                    </div>
                    <?php outQuestionBox($lang['AN4']); ?>
	            </div>
                
                <div class="seoBox">
                    <?php outHeadBox($lang['AN101'],$solveMsg,4); ?>
                    <div class="contentBox" id="seoBox33">
                        <?php echo $seoBox33; ?>
                    </div>
                    <?php outQuestionBox($lang['AN4']); ?>
	            </div>
                
                <div class="seoBox">
                    <?php outHeadBox($lang['AN102'],$solveMsg,1); ?>
                    <div class="contentBox" id="seoBox34">
                        <?php echo $seoBox34; ?>
                    </div>
                    <?php outQuestionBox($lang['AN4']); ?>
	            </div>
                
                <div class="seoBox">
                    <?php outHeadBox($lang['AN103'],$solveMsg,1); ?>
                    <div class="contentBox" id="seoBox35">
                        <?php echo $seoBox35; ?>
                    </div>
                    <?php outQuestionBox($lang['AN4']); ?>
	            </div>
                
                </div>
                
                <div id="mobile">
                    <div class="clearSep"></div>
                    <h2 class="seoBox-title">
                        <?php trans('Mobile',$lang['17']); ?>
                    </h2>
                <div class="seoBox">
                    <?php outHeadBox($lang['AN91'],$solveMsg,3); ?>
                    <div class="contentBox" id="seoBox23">
                        <?php echo $seoBox23; ?>
                    </div>
                    <?php outQuestionBox($lang['AN4']); ?>
	            </div>
                
                <div class="seoBox">
                    <?php outHeadBox($lang['AN92'],$solveMsg,4); ?>
                    <div class="contentBox" id="seoBox24">
                        <?php echo $seoBox24; ?>
                    </div>
                    <?php outQuestionBox($lang['AN4']); ?>
	            </div>
                
                <div class="seoBox">
                    <?php outHeadBox($lang['AN93'],$solveMsg,2); ?>
                    <div class="contentBox" id="seoBox25">
                        <?php echo $seoBox25; ?>
                    </div>
                    <?php outQuestionBox($lang['AN4']); ?>
	            </div>
                
                <div class="seoBox">
                    <?php outHeadBox($lang['121'],$solveMsg,3); ?>
                    <div class="contentBox" id="seoBox49">
                        <?php echo $seoBox49; ?>
                    </div>
                    <?php outQuestionBox($lang['AN4']); ?>
                </div>
                
                </div>
                
                <div id="technologies">
                <div class="clearSep"></div>
                <h2 class="seoBox-title">
                    <?php trans('Technologies',$lang['18']); ?>
                </h2>
                <div class="seoBox">
                    <?php outHeadBox($lang['AN104'],$solveMsg,4); ?>
                    <div class="contentBox" id="seoBox36">
                        <?php echo $seoBox36; ?>
                    </div>
                    <?php outQuestionBox($lang['AN4']); ?>
	            </div>
                
                <div class="seoBox">
                    <?php outHeadBox($lang['AN105'],$solveMsg,3); ?>
                    <div class="contentBox" id="seoBox37">
                        <?php echo $seoBox37; ?>
                    </div>
                    <?php outQuestionBox($lang['AN4']); ?>
	            </div>
                
                <div class="seoBox">
                    <?php outHeadBox($lang['AN106'],$solveMsg,1); ?>
                    <div class="contentBox" id="seoBox38">
                        <?php echo $seoBox38; ?>
                    </div>
                    <?php outQuestionBox($lang['AN4']); ?>
	            </div>
                
                <div class="seoBox">
                    <?php outHeadBox($lang['AN107'],$solveMsg,2); ?>
                    <div class="contentBox" id="seoBox39">
                        <?php echo $seoBox39; ?>
                    </div>
                    <?php outQuestionBox($lang['AN4']); ?>
	            </div>
                
                <div class="seoBox">
                    <?php outHeadBox($lang['AN108'],$solveMsg,4); ?>
                    <div class="contentBox" id="seoBox40">
                        <?php echo $seoBox40; ?>
                    </div>
                    <?php outQuestionBox($lang['AN4']); ?>
	            </div>
                
                <div class="seoBox">
                    <?php outHeadBox($lang['AN109'],$solveMsg,1); ?>
                    <div class="contentBox" id="seoBox41">
                        <?php echo $seoBox41; ?>
                    </div>
                    <?php outQuestionBox($lang['AN4']); ?>
	            </div>
                
                </div>
                
                <div id="social">
               
                <div class="clearSep"></div>
                <h2 class="seoBox-title">
                    <?php trans('Social',$lang['19']); ?>
                </h2>
                <div class="seoBox">
                    <?php outHeadBox($lang['AN112'],$solveMsg,3); ?>
                    <div class="contentBox" id="seoBox44">
                        <?php echo $seoBox44; ?>
                    </div>
                    <?php outQuestionBox($lang['AN4']); ?>
	            </div>
                
                </div>
                
                <div id="visitors" class="hide">
                <div class="clearSep"></div>
                <h2 class="seoBox-title">
                    <?php trans('Visitors',$lang['20']); ?>
                </h2>
                     onclick="javascript:showSuggestion('seoBox45');">
                    <?php outHeadBox($lang['AN113'],$solveMsg,4); ?>
                    <div class="contentBox" id="seoBox45">
                        <?php echo $seoBox45; ?>
                    </div>
                    <?php outQuestionBox($lang['AN4']); ?>
	            </div>
                
                <div class="seoBox hide">
                    <?php outHeadBox($lang['AN114'],$solveMsg,3); ?>
                    <div class="contentBox" id="seoBox46">
                        <?php echo $seoBox46; ?>
                    </div>
                    <?php outQuestionBox($lang['AN4']); ?>
	            </div>
                
                <div class="seoBox hide">
                    <?php outHeadBox($lang['AN115'],$solveMsg,4); ?>
                    <div class="contentBox" id="seoBox47">
                        <?php echo $seoBox47; ?>
                    </div>
                    <?php outQuestionBox($lang['AN4']); ?>
	            </div>
                </div>
                
                <div id="link-analysis">
                    <div class="clearSep"></div>
                    <h2 class="seoBox-title">
                        <?php trans('Link Analysis',$lang['21']); ?>
                    </h2>
                    <div class="seoBox inPage">
                        <?php outHeadBox($lang['AN51'],$solveMsg,1); ?>
                        <div class="contentBox" id="seoBox13">
                            <?php echo $seoBox13; ?>
                        </div>
                        <?php outQuestionBox($lang['AN4']); ?>
    	            </div> 
                    
                    <div class="seoBox brokenLinks">
                        <?php outHeadBox($lang['AN58'],$solveMsg,1); ?>
                        <div class="contentBox" id="seoBox14">
                            <?php echo $seoBox14; ?>
                        </div>
                        <?php outQuestionBox($lang['AN4']); ?>
    	            </div> 

               </div>  
                  
                           <!-- New Site Analysis Form -->
                           <div class="text-center">
                    <br /> &nbsp; <br />
                    <h4 style="color: #989ea8;">
                        <?php trans('Try New Site', $lang['38']); ?>
                    </h4>
                    <form method="POST" action="<?php createLink('domain'); ?>" onsubmit="return fixURL();">
                        <div class="input-group reviewBox">
                            <div class="input-container">
                                <input type="text" tabindex="1" placeholder="<?php trans('Website URL to review', $lang['37']); ?>" id="url" name="url" class="form-control reviewIn"/>
                            </div>
                            <div class="input-group-btn">
                                <button tabindex="2" type="submit" name="generate" class="btn btn-info url-lg">
                                    <span class="ready"><?php trans('Analyze', $lang['36']); ?></span>
                                </button>
                            </div>
                        </div>
                    </form>
                    <br />
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
 
<!-- Conditionally include JavaScript based on update status -->
<?php if ($updateFound) { ?>
    <script src="<?php themeLink('js/domain.js?v6'); ?>" type="text/javascript"></script>
<?php } else { ?>
    <script src="<?php themeLink('js/dbdomain.js?v6'); ?>" type="text/javascript"></script>
<?php } ?>

