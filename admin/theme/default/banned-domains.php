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
              <?php if($pointOut != 'add') { ?>
                <div style="position:absolute; top:4px; right:15px;">
                  <a class="btn btn-primary btn-sm" href="<?php adminLink($controller.'/add'); ?>"><i class="fa fa-plus"></i> &nbsp; Add Domain</a>
                </div>
              <?php } ?>
            </div><!-- /.box-header ba-la-ji -->
            <form action="#" method="POST">
            <div class="box-body">
          
            <?php if(isset($msg)) echo $msg; ?><br />
                
            <?php if($pointOut == 'add'){ ?>
            
                <div class="form-group">
                    <div class="form-group">
                        <label for="domain">Domain Name:</label>
                        <input type="text" class="form-control" id="domain" name="domain" placeholder="Enter domain name to ban" />
                    </div>
                    <div class="form-group">
                        <label for="reason">Reason: <small>(Optional)</small></label>
                        <textarea class="form-control" id="reason" name="reason" placeholder="Reason to ban?"></textarea>
                    </div>
                </div><button type="submit" class="btn btn-primary">Add</button>
                
            <?php } else { if(count($bannedList) == 0) { echo '<div class="text-center"><b>Empty!</b></div>'; } else { ?>
            
              <table id="seoToolTable" class="table table-bordered table-striped">
                <thead>
                  <tr>
                    <th>Banned Domain</th>
                    <th>Banned Reason</th>
                    <th>Added Date</th>
                    <th>Delete</th>
                  </tr>
                </thead>
                <tbody>
                <?php  foreach($bannedList as $domainVal=>$bannedDomain){                
                    echo '<tr>
                    <td>'.$domainVal.'</td>
                    <td>'.$bannedDomain["reason"].'</td>
                    <td>'.$bannedDomain["added_at"].'</td>
                    <td><a class="btn btn-danger btn-xs" onclick="return confirm(\'Are you sure you want to delete this item?\');" title="Delete" href='.adminLink('banned-domains/delete/'.$domainVal,true).'> <i class="fa fa-trash-o"></i> &nbsp; Delete </a></td>
                  </tr>';
                } }
                ?>

                </tbody>
              </table>
              
            <?php } ?>  
            <br />      
                 </div><!-- /.box-body -->
            </form>
        </div>
      
        </section><!-- /.content -->
      </div><!-- /.content-wrapper -->