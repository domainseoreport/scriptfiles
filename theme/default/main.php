<?php
defined('APP_NAME') or die(header('HTTP/1.1 403 Forbidden'));

/*
 * @author Balaji
 * @Theme: Default Style
 * @copyright 2023 ProThemes.Biz
 *
 */
?>
<div class="bg-grey-color">

	<!-- begin .container -->
	<div class="container">
		
        <ul id="featured">
          <li>
            <span class="circleBox"><span class="fa fa-line-chart"></span></span>
            <h4><?php trans('Unlimited Analysis',$lang['139']); ?></h4>
            <p><?php trans('Run unlimited analysis on our most powerful servers. Stored reports make it easy to view progress and past work.',$lang['140']); ?></p>
          </li>
          <li>
            <span class="circleBox"><span class="fa fa-server"></span></span>
            <h4><?php trans('In-Depth Reviews',$lang['141']); ?></h4>
            <p><?php trans('With our in-depth website analysis learn how to fix your SEO issues with clear definitions for each SEO metrics.',$lang['142']); ?></p>
          </li>
          <li>
            <span class="circleBox"><span class="fa fa-thumbs-o-up"></span></span>
            <h4><?php trans('Competitive Analysis',$lang['143']); ?></h4>
            <p><?php trans('Side-by-side SEO comparisons with your competitors. See how your SEO can improve against the competition.',$lang['144']); ?></p>
          </li>
        </ul>
        
	</div>
	<!-- end .container -->
	
</div>

<div class="container">
      <div class="row">
          <div id="latest-site">
              <div class="col-md-12">
                <div class="latest-heading">
                  <h4><span class="heading-icon"><i class="fa fa-envira"></i></span><?php trans('Recently Listed',$lang['137']); ?></h4>
                  <a class="btn btn-primary btn-sm pull-right" href="<?php createLink('recent'); ?>"><?php trans('View More',$lang['138']); ?> <i class="fa fa-long-arrow-right"></i></a>
                </div>
              </div>
              

            <div class="row latest-content">
            <?php foreach($domainList as $domain){ ?>
            <div class="col-md-4">
                <div class="sites-block">
                    <a rel="nofollow" href="<?php createLink('domain/'.$domain[0]); ?>"><img alt="<?php echo $domain[0]; ?>" src="<?php createLink('ajax/snap/'.$domain[0]); ?>" class="image-overlay" /></a>
                    <div class="caption">
                        <a href="<?php createLink('domain/'.$domain[0]); ?>"><?php echo ucfirst($domain[0]); ?></a>
                    </div>
                    <div class="details clearfix">
                          <span><strong class="recentStrong"><?php echo $domain[1]; ?><span style="font-size: 12px;">/100</span></strong><?php trans('Score',$lang['134']); ?></span>
                          <span><strong class="recentStrong"><?php echo $domain[3]; ?>%</strong><?php trans('Page Speed',$lang['136']); ?></span>
                      </div>
                </div>
            </div>
            <?php $count++; if($count != $perPage){if($count % 3 == 0){ echo '</div><div class="row latest-content">';} } } ?>
            </div><!-- /.row -->
                
          </div>
      </div>
</div>
