<?php
defined('APP_NAME') or die(header('HTTP/1.0 403 Forbidden'));

/*
 * @author Balaji
 * @name: Turbo Website Reviewer
 * @copyright © 2017 ProThemes.Biz
 *
 */
?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <h1>
        <?php echo $pageTitle; ?>  
        <small>Control panel</small>
      </h1>
      <ol class="breadcrumb">
        <li><a href="<?php adminLink(); ?>"><i class="<?php getAdminMenuIcon($controller,$menuBarLinks); ?>"></i> Admin</a></li>
        <li class="active"><a href="<?php adminLink($controller); ?>"><?php echo $pageTitle; ?></a> </li>
      </ol>
    </section>

    <!-- Main content -->
    <section class="content">

          <div class="box box-primary">
            <div class="box-header with-border">
              <h3 class="box-title"><?php echo $subTitle; ?></h3>
            </div><!-- /.box-header ba-la-ji -->
            <form action="#" method="POST" enctype="multipart/form-data">
            <div class="box-body">
          
            <?php if(isset($msg)) echo $msg; ?><br />
         <div class="row" style="padding-left: 5px;">
            <div class="col-md-10">
           <div class="form-group">
                <label for="limit">How much domains allowed for unregistered users? <small>(Count will resets everyday)</small></label>
                <input type="number" placeholder="Enter your visitors limit" id="limit" name="limit" value="<?php echo $db['free_limit']; ?>" class="form-control" />
           </div>

            <div class="form-group">
            <label>Restrict some stats from unregistered users with 'Login' button:</label>
                <select name="reviewerList[]" class="form-control select2" multiple="multiple" data-placeholder="Which stats need premium access?" style="width: 100%;">
                  <?php 
                    foreach($reviewerList as $reviewer=>$reviewerName){
                        if (in_array($reviewer, $db['reviewer_list']))
                            echo '<option selected="" value="'.$reviewer.'">'.$reviewerName.'</option>';
                        else
                            echo '<option value="'.$reviewer.'">'.$reviewerName.'</option>';
                    }
                  ?>
                </select>
            </div>
            
           <div class="form-group">
                <label>PageSpeed Insights API Key:</label>
                <input type="text" name="insights_api" value="<?php echo $db['insights_api']; ?>" class="form-control" />
           </div>
            
          <div class="form-group">
            <label for="snap">Website Snapshot Service:</label>
            <select id="opt" name="snap[options]" class="form-control">
                <option <?php isSelected($db['snap']['options'], true, 1, 'prothemes_free'); ?> value="prothemes_free">ProThemes.Biz Free API</option>
                <option <?php isSelected($db['snap']['options'], true, 1, 'prothemes_pro'); ?> value="prothemes_pro">ProThemes.Biz Premium API</option>
                <!-- Main content <option <?php isSelected($db['snap']['options'], true, 1, 'pagepeeker_free'); ?> value="pagepeeker_free">PagePeeker Free API</option>
                <option <?php isSelected($db['snap']['options'], true, 1, 'pagepeeker_pro'); ?> value="pagepeeker_pro">PagePeeker Premium API</option>-->
                <option <?php isSelected($db['snap']['options'], true, 1, 'custom'); ?> value="custom">Custom API URL</option>
            </select>
          </div>
          
          <div class="hide" id="opt-prothemes_free">
            <!-- No Options -->
          </div>
          
          <div class="hide" id="opt-prothemes_pro">
               <div class="form-group">
                    <label>Snap Premium URL with Key:</label>
                    <input type="text" name="snap[prothemes_pro]" value="<?php echo $db['snap']['prothemes_pro']; ?>" class="form-control" />
               </div>
          </div>
          <div class="hide" id="opt-custom">
               <div class="form-group">
                    <label>Custom API Link:</label>
                    <input type="text" name="snap[custom]" value="<?php echo $db['snap']['custom']; ?>" class="form-control" />
               </div>
          </div>
          
           <div class="form-group">
                <label>Meta title format for cached domains:</label>
                <input type="text" placeholder="Enter your meta title" name="domain_data[domain][title]" value="<?php echo $db['domain_data']['domain']['title']; ?>" class="form-control" />
           </div>
           
           <div class="form-group">
                <label>Meta description format for cached domains:</label>
                <textarea placeholder="Enter your meta description" name="domain_data[domain][des]" class="form-control"><?php echo $db['domain_data']['domain']['des']; ?></textarea>
           </div>
           
           <div class="alert alert-danger" id="linkAlertBox">
                <div class="row">
                    <div class="col-md-6">
                        <b>{{domainName}}</b> will be replaced to Domain Name <br />
                        <b>{{passScore}}</b> will be replaced to Passed Score <br />
                    </div>
                    <div class="col-md-6">
                        <b>{{improveScore}}</b> will be replaced to Improve Score <br />
                        <b>{{errorScore}}</b> will be replaced to Error Score <br />
                    </div>
                </div>
           </div>
                
           <div class="form-group">
                <label>Meta title format for competitive analysis page:</label>
                <input type="text" placeholder="Enter your meta title" name="compare_data[compare][title]" value="<?php echo $db['compare_data']['compare']['title']; ?>" class="form-control" />
           </div>
           
           <div class="form-group">
                <label>Meta description format for competitive analysis page:</label>
                <textarea placeholder="Enter your meta description" name="compare_data[compare][des]" class="form-control"><?php echo $db['compare_data']['compare']['des']; ?></textarea>
           </div>
           
           <div class="alert alert-success" id="linkAlertBox">
                <div class="row">
                    <div class="col-md-6">
                        <b>{{firstDomainName}}</b> will be replaced to Website URL <br />
                        <b>{{first_passScore}}</b> will be replaced to Website Passed Score <br />
                        <b>{{first_improveScore}}</b> will be replaced to Website Improve Score <br />
                        <b>{{first_errorScore}}</b> will be replaced to Website Error Score <br />
                    </div>
                    <div class="col-md-6">
                        <b>{{secDomainName}}</b> will be replaced to Competitor URL <br />
                        <b>{{sec_passScore}}</b> will be replaced to Competitor Passed Score <br />
                        <b>{{sec_improveScore}}</b> will be replaced to Competitor Improve Score <br />
                        <b>{{sec_errorScore}}</b> will be replaced to Competitor Error Score <br />
                    </div>
                </div>
           </div>       

            <br /> <br />
            <div class="box-header with-border">
              <h3 class="box-title">PDF Report Settings</h3>
            </div><!-- /.box-header -->
                
                <br />
                   <div class="form-group">
                        <label for="pdfCopyright">PDF Copyright Text:</label>
                        <input type="text" placeholder="Enter your PDF Copyright Text" id="pdfCopyright" name="pdfCopyright" value="<?php echo $pdfCopyright; ?>" class="form-control" />
                   </div>
                   <br />
                   <div class="form-group">
                        <label for="headerLogoBox">PDF Header Logo:</label><br />
                        <img class="headerLogoBox" id="headerLogoBox" alt="Header Logo" src="<?php echo $headerLogo; ?>" />   
                        <br />
                        Recommended Size: (JPEG 150x75px)
                        <input type="file" name="headerUpload" id="headerUpload" class="btn btn-default" />
                   </div>
                   <br />
                   <div class="form-group">
                        <label for="footerLogoBox">PDF Footer Logo:</label><br />
                        <img class="footerLogoBox" id="footerLogoBox" alt="Footer Logo" src="<?php echo $footerLogo; ?>" />   
                        <br />
                        Recommended Size: (JPEG 100x50px)
                        <input type="file" name="footerUpload" id="footerUpload" class="btn btn-default" />
                   </div>                
                </div>
                </div> 
                             
            <br /> <br />
            <div class="">
                <input type="submit" value="Save Settings" class="btn btn-primary" />
            </div>
            </div><!-- /.box-body -->
            <br />            
            </form>
          </div><!-- /.box -->
  
    </section><!-- /.content -->
  </div><!-- /.content-wrapper -->
  
<?php
$footerAddArr[] = <<<EOD
    <script> 
       $(function () {
        $(".select2").select2();
       });
      var oldSel;  
      $(function () {
        var selVal = jQuery('select[id="opt"]').val();
        oldSel = selVal;
        $('#opt-'+selVal).removeClass("hide");
        $('#opt-'+selVal).fadeIn();
      });      
      $('select[id="opt"]').on('change', function() {
            var selVal = jQuery('select[id="opt"]').val();
            $('#opt-'+oldSel).fadeOut();
            $('#opt-'+selVal).removeClass("hide");
            $('#opt-'+selVal).fadeIn();
            oldSel = selVal;
      });
      function readURL(input,box){
            if (input.files && input.files[0]) {
                var reader = new FileReader();
        
                reader.onload = function (e) {
                    $(box).attr('src', e.target.result);
                }
                reader.readAsDataURL(input.files[0]);
            }
      }  
      $("#headerUpload").change(function(){
        readURL(this,'#headerLogoBox');
      });
      $("#footerUpload").change(function(){
        readURL(this,'#footerLogoBox');
      });  
  </script>  
EOD;
?>