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
            <form action="#" method="POST">
            <div class="box-body">
          
            <?php if(isset($msg)) echo $msg; ?><br />
                <div class="form-group">
                    <div class="form-group">
                        <label for="ban_word">Enter Bad Words:</label>
                        <input required="" type="text" class="form-control" id="ban_word" name="ban_word" placeholder="Enter the words to ban (word1,word2,word3 etc...)" />
                    </div>
                    <div class="form-group">
    		            <div class="checkbox">
    			           <label class="checkbox inline">
    				          <input checked="" name="domain" type="checkbox" /> Detect from domain name
                           </label>
    		            </div>
    		            <div class="checkbox">
    			           <label class="checkbox inline">
    				          <input name="title" type="checkbox" /> Detect from website title
                           </label>
    		            </div>
    		            <div class="checkbox">
    			           <label class="checkbox inline">
    				          <input name="description" type="checkbox" /> Detect from website description
                           </label>
    		            </div>
    		            <div class="checkbox">
    			           <label class="checkbox inline">
    				          <input name="keywords" type="checkbox" /> Detect from website keywords
                           </label>
    		            </div>
	  	           </div>
                </div><button type="submit" class="btn btn-primary">Add</button>
                 </div><!-- /.box-body -->
            </form>
        </div>
                            
         <div class="box box-danger">
            <div class="box-header with-border">
                <!-- tools box -->

                <h3 class="box-title">
                    Banned Words
                </h3>
            </div>

            <div class="box-body">
            <?php if(count($badWordArr) == 0) { echo '<div class="text-center"><b>Empty!</b></div>'; } else { ?>
            
              <table id="badWordTable" class="table table-bordered table-striped">
                <thead>
                  <tr>
                    <th>Words</th>
                    <th>Detect From</th>
                    <th>Added Date</th>
                    <th>Delete</th>
                  </tr>
                </thead>
                <tbody>
                <?php  foreach($badWordArr as $key => $badWord){                
                    echo '<tr>
                    <td>'.$badWord[0].'</td>'; ?>
                    <td><ul style="list-style: outside none none;">
                        <li><?php if(isSelected($badWord[2])) echo $true;  else  echo $false; ?> Domain Name</li>
                        <li><?php if(isSelected($badWord[3])) echo $true;  else  echo $false; ?> Title</li>
                        <li><?php if(isSelected($badWord[4])) echo $true;  else  echo $false; ?> Description</li>
                        <li><?php if(isSelected($badWord[5])) echo $true;  else  echo $false; ?> Keywords</li>
                    </ul></td>
                    <?php echo '
                    <td>'.$badWord[1].'</td>
                    <td><a class="btn btn-danger btn-xs" onclick="return confirm(\'Are you sure you want to delete this item?\');" title="Delete" href='.adminLink($controller.'/delete/'.$key,true).'> <i class="fa fa-trash-o"></i> &nbsp; Delete </a></td>
                  </tr>';
                } }
                ?>
                </tbody>
              </table>
                </div><!-- /.box-body -->
            </div>
      
        </section><!-- /.content -->
      </div><!-- /.content-wrapper -->
<?php
$footerAddArr[] = <<<EOD
  <script>
      $(function () {
        $('input').iCheck({
          checkboxClass: 'icheckbox_square-blue',
          radioClass: 'iradio_square-blue',
          increaseArea: '20%'
        });
      });
  </script>  
EOD;
?> 