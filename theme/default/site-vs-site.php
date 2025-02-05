<?php

defined('APP_NAME') or die(header('HTTP/1.1 403 Forbidden'));

/*
 * @author Balaji
 * @name Turbo Website Reviewer
 * @copyright 2020 ProThemes.Biz
 *
 */

?>

<script>
var domainPath = '<?php createLink('domains'); ?>';
var apiPath = '<?php createLink('ajax/sitevssite'); ?>';
var comparePath = '<?php createLink('compare/[domain1]/vs/[domain2]'); ?>';
var inputError = '<?php echo makeJavascriptStr($lang['8']); ?>';
var inputError1 = '<?php echo makeJavascriptStr($lang['153']); ?>';
var inputError2 = '<?php echo makeJavascriptStr($lang['154']); ?>';
var errWebsite = '<?php echo makeJavascriptStr($lang['155']); ?>';
var errCompetitor = '<?php echo makeJavascriptStr($lang['156']); ?>';
var anCompleted = '<?php echo makeJavascriptStr($lang['157']); ?>';
var processingStr = '<?php echo makeJavascriptStr($lang['192']); ?>';
var oopsStr = '<?php echo makeJavascriptStr($lang['193']); ?>';
var errorStr = '<?php echo makeJavascriptStr($lang['194']); ?>';
var str1 = '<?php echo makeJavascriptStr($lang['158']); ?>';
var str2 = '<?php echo makeJavascriptStr($lang['159']); ?>';
var str3 = '<?php echo makeJavascriptStr($lang['160']); ?>';
var str4 = '<?php echo makeJavascriptStr($lang['161']); ?>';
var str5 = '<?php echo makeJavascriptStr($lang['162']); ?>';
var str6 = '<?php echo makeJavascriptStr($lang['163']); ?>';
var str7 = '<?php echo makeJavascriptStr($lang['164']); ?>';
var str8 = '<?php echo makeJavascriptStr($lang['165']); ?>';
var str9 = '<?php echo makeJavascriptStr($lang['166']); ?>';
var str10 = '<?php echo makeJavascriptStr($lang['167']); ?>';
var str11 = '<?php echo makeJavascriptStr($lang['168']); ?>';
var str12 = '<?php echo makeJavascriptStr($lang['169']); ?>';
var str13 = '<?php echo makeJavascriptStr($lang['170']); ?>';
var str14 = '<?php echo makeJavascriptStr($lang['171']); ?>';
var str15 = '<?php echo makeJavascriptStr($lang['172']); ?>';
var str16 = '<?php echo makeJavascriptStr($lang['173']); ?>';
var str17 = '<?php echo makeJavascriptStr($lang['174']); ?>';
var str18 = '<?php echo makeJavascriptStr($lang['175']); ?>';
var str19 = '<?php echo makeJavascriptStr($lang['176']); ?>';
var str20 = '<?php echo makeJavascriptStr($lang['177']); ?>';
var str21 = '<?php echo makeJavascriptStr($lang['178']); ?>';
var str22 = '<?php echo makeJavascriptStr($lang['179']); ?>';
var str23 = '<?php echo makeJavascriptStr($lang['180']); ?>';
var str24 = '<?php echo makeJavascriptStr($lang['181']); ?>';
var str25 = '<?php echo makeJavascriptStr($lang['182']); ?>';
var str26 = '<?php echo makeJavascriptStr($lang['183']); ?>';
var str27 = '<?php echo makeJavascriptStr($lang['184']); ?>';
var str28 = '<?php echo makeJavascriptStr($lang['185']); ?>';
var str29 = '<?php echo makeJavascriptStr($lang['186']); ?>';
var str30 = '<?php echo makeJavascriptStr($lang['187']); ?>';
var str31 = '<?php echo makeJavascriptStr($lang['188']); ?>';
var str32 = '<?php echo makeJavascriptStr($lang['189']); ?>';
var str33 = '<?php echo makeJavascriptStr($lang['190']); ?>';
var str34 = '<?php echo makeJavascriptStr($lang['191']); ?>';
</script>

<div class="container">
    <div class="row">
      	
    <?php
    if($themeOptions['general']['sidebar'] == 'left')
        require_once(THEME_DIR."sidebar.php");
    ?>  
    
  	<div class="col-md-9 top40">

        <div class="headBox">
            <h4 class="bold600"><?php trans('Set side-by-side domain\'s comparisons', $lang['MS1']); ?></h4>
            <?php trans('Everyone has competition. As a website owner, or an SEO or SEM practitioner, you will agree.', $lang['MS5']); ?>
            <br/>
            <br/>
        </div>   
        
        <div id="sitevssiteBox">
            <div class="form-group">
                <?php if($firstHost == ''){ ?>
                <input tabindex="1" placeholder="<?php trans('Website URL', $lang['MS2']); ?>" id="websiteUrl" name="websiteUrl" class="form-control reviewIn" type="text" />
                <?php } else { ?>
                <input readonly="" value="<?php echo $firstHost; ?>" tabindex="1" placeholder="<?php trans('Website URL', $lang['MS2']); ?>" id="websiteUrl" name="websiteUrl" class="form-control reviewIn" type="text" />
                <?php } ?>
            </div>
            <div class="form-group">
                <input tabindex="2" placeholder="<?php trans('Competitor URL', $lang['MS3']); ?>" id="competitorUrl" name="competitorUrl" class="form-control reviewIn" type="text" />
            </div>                
            <div class="form-group text-center">
                <?php if ($competitive_page) { echo '<style>.phpCap, .g-recaptcha > div { margin: 0 auto; }</style>'.$captchaCode; } ?>
                <button id="sitevssite" tabindex="3" type="submit" name="generate" class="btn btn-info url-lg">
                    <span class="ready"><?php trans('Analyze', $lang['MS4']); ?></span>
                </button>
            </div> 
        </div>
        
        <div id="seoBox" style="display: none;"></div>
        
        <ul id="featured">
          <li>
            <span class="circleBox circleNew"><span class="fa fa-line-chart"></span></span>
            <h4><?php trans('Unlimited Analysis',$lang['139']); ?></h4>
            <p><?php trans('Run unlimited analysis on our most powerful servers. Stored reports make it easy to view progress and past work.',$lang['140']); ?></p>
          </li>
          <li>
            <span class="circleBox circleNew"><span class="fa fa-server"></span></span>
            <h4><?php trans('In-Depth Reviews',$lang['141']); ?></h4>
            <p><?php trans('With our in-depth website analysis learn how to fix your SEO issues with clear definitions for each SEO metrics.',$lang['142']); ?></p>
          </li>
          <li>
            <span class="circleBox circleNew"><span class="fa fa-thumbs-o-up"></span></span>
            <h4><?php trans('Competitive Analysis',$lang['143']); ?></h4>
            <p><?php trans('Side-by-side SEO comparisons with your competitors. See how your SEO can improve against the competition.',$lang['144']); ?></p>
          </li>
        </ul>
        
        <br />
        
        <div class="xd_top_box text-center">
            <?php echo $ads_720x90; ?>
        </div>

    </div>
    
    <?php
    if($themeOptions['general']['sidebar'] == 'right')
        require_once(THEME_DIR."sidebar.php");
    ?>
              	
    </div>
</div> <br />

<script src="<?php themeLink('js/sitevssite.js?v6'); ?>" type="text/javascript"></script>