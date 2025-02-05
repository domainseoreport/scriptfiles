<?php
defined('APP_NAME') or die(header('HTTP/1.1 403 Forbidden'));

/*
 * @author Balaji
 * @name: Turbo Website Reviewer
 * @copyright 2022 ProThemes.Biz
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

              <div class="box box-success">
                <div class="box-header with-border">
                  <h3 class="box-title"><?php echo $subTitle; ?></h3>
                </div><!-- /.box-header -->

                <div class="box-body">
                 <?php
                if(isset($msg1)){
                    echo $msg1;
                }?>
                <div class="row">
                    <div class="col-md-8">
                        <form action="#" method="POST">
    
                        <br />
                                    
                        <div class="form-group">
                            <label for="mozAccess">MOZ Access ID</label>
                            <input type="text" placeholder="Enter your MOZ access id" name="mozAccess" id="mozAccess" value="<?php echo $mozAccess; ?>" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="mozSecret">MOZ Secret Key</label>
                            <input type="text" placeholder="Enter your MOZ secret key" name="mozSecret" id="mozSecret" value="<?php echo $mozSecret; ?>" class="form-control">
                        </div>
                        
                        <input type="hidden" name="mozSel" value="1" />
                        
                        <input type="submit" name="save" value="Save" class="btn btn-primary"/>                  
        
                        </form>
                    </div>
                </div>

                
                </div><!-- /.box-body -->
      
              </div><!-- /.box -->
      
      
        </section><!-- /.content -->
      </div><!-- /.content-wrapper -->
