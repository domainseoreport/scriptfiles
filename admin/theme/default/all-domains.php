<?php
defined('APP_NAME') or die(header('HTTP/1.0 403 Forbidden'));

/*
 * @author Balaji
 * @name: Turbo Website Reviewer
 * @copyright © 2016 ProThemes.Biz
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
            <form action="#" method="POST">
            <div class="box-body">
          
            <?php if(isset($msg)) echo $msg; ?><br />
            
            <?php if($pointOut == 'ban'){ ?>
                <div class="form-group">
                    <div class="form-group">
                        <label for="domain">Domain Name:</label>
                        <input type="text" class="form-control" id="domain" name="domain" value="<?php echo $banDomainName; ?>"  readonly="" />
                    </div>
                    <div class="form-group">
                        <label for="reason">Reason: <small>(Optional)</small></label>
                        <textarea class="form-control" id="reason" name="reason" placeholder="Reason to ban?"></textarea>
                    </div>
                </div><button type="submit" class="btn btn-primary">Add</button>
            
            <?php } else { ?>
            <table cellpadding="0" cellspacing="0" border="0" class="table table-striped table-bordered" id="mySitesTable">
            	<thead>
            		<tr>
                      <th>Domain Name</th>
                      <th>Last Updated Date</th>
                      <th>Cached</th>
                      <th>Domain Score</th>
                      <th>Export</th>
                      <th>Actions</th>
            		</tr>
            	</thead>         
                <tbody>                        
                </tbody>
            </table>
            <?php } ?>
            
            <br />
            
            </div><!-- /.box-body -->
            </form>
          </div><!-- /.box -->
  
    </section><!-- /.content -->
  </div><!-- /.content-wrapper -->
  
<?php 
$ajaxLink = adminLink('?route=ajax/allDomains',true); 
$footerAddArr[] = <<<EOD
    <script type="text/javascript" language="javascript" class="init">
    $(document).ready(function() {
    	$('#mySitesTable').dataTable( {
    		"processing": true,
    		"serverSide": true,
    		"ajax": "$ajaxLink"
    	} );
    } );
    </script>
EOD;
?>