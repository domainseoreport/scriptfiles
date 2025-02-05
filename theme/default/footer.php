<?php
defined('APP_NAME') or die(header('HTTP/1.0 403 Forbidden'));

/*
 * @author Balaji
 * @Theme: Default Style
 * @copyright 2019 ProThemes.Biz
 *
 */
?>
<footer>
    <div class="container">
    <div class="row">
    
        <div class="col-md-6 col-sm-12 right-border">
            <div class="footer-about">
            <h2 class="footer-title"><?php trans('About Us',$lang['227']); ?></h2>
            <p><?php htmlPrint($themeOptions['contact']['about']); ?></p>
            </div>
            <div class="copyright hidden-sm hidden-xs">
            <p><?php echo $copyright; ?></p>
            </div>
        </div>
        
        <div class="col-md-6 col-sm-12"> <div class="col-md-6 col-sm-6">
            <div class="contact-info">
            <h2 class="footer-title"><?php trans('Contact Info',$lang['228']); ?></h2>
            
            <div class="single"><i class="fa fa-map-marker"></i><p><?php htmlPrint($themeOptions['contact']['address']); ?></p></div>
            
            <div class="single"><i class="fa fa-phone"></i><p><?php htmlPrint($themeOptions['contact']['phone']); ?></p></div>
            
            <div class="single"><i class="fa fa-envelope"></i><p><?php htmlPrint($themeOptions['contact']['email']); ?></p></div>
            
            <div class="social-icon">
            	<ul class="list-inline">
                    <li><a href="<?php echo $social_links['face']; ?>" target="_blank" rel="nofollow"><i class="fa fa-facebook"></i></a></li>
                    <li><a href="<?php echo $social_links['twit']; ?>" target="_blank" rel="nofollow"><i class="fa fa-twitter"></i></a></li>
                    <li><a href="<?php echo $social_links['gplus']; ?>" target="_blank" rel="nofollow"><i class="fa fa-google-plus"></i></a></li>
                    <li><a href="<?php echo $social_links['linkedin']; ?>" target="_blank" rel="nofollow"><i class="fa fa-linkedin"></i></a></li>
                </ul>
            </div>
        </div> </div>
        
        <div class="col-md-6 col-sm-6 left-border">
            <div class="navigation">
            <h2 class="footer-title"><?php trans('Navigation',$lang['229']); ?></h2>
            <ul class="list-unstyled">
                <?php 
                    foreach($footerLinks as $footerLink)
                    echo $footerLink[1];
                ?>
            </ul>
            </div>
        </div>
        
        <div class="clearfix"></div>
        
        <div class="copyright visible-sm visible-xs">
            <p><?php echo $copyright; ?></p>
        </div>
        
        </div>
        
    </div>
    </div>
</footer>


<!-- Bootstrap -->
<script src="<?php themeLink('js/bootstrap.min.js'); ?>" type="text/javascript"></script>

<?php if($controller == CON_MAIN) { ?> <script type='text/javascript' src='<?php themeLink('js/particleground.min.js'); ?>'></script> <?php } ?>
<script type='text/javascript' src='<?php themeLink('js/sweetalert.min.js'); ?>'></script>

<!-- App JS -->
<script src="<?php themeLink('js/app.js'); ?>" type="text/javascript"></script>

<!-- Master JS -->
<script src="<?php echo $baseURL.'rainbow/master-js'; ?>" type="text/javascript"></script>

<?php if(isset($addtionalCodes)) echo $addtionalCodes;
if(isset($_SESSION['TWEB_CALLBACK_ERR'])){
    $err = $_SESSION['TWEB_CALLBACK_ERR'];
    unset($_SESSION['TWEB_CALLBACK_ERR']);
    echo '<script>sweetAlert(oopsStr, "'. makeJavascriptStr($err) .'" , "error");</script>';
}
?>

<?php if($ga != ''){ ?>
<script>
  (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
  (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
  m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
  })(window,document,'script','//www.google-analytics.com/analytics.js','ga');

  ga('create', '<?php echo $ga; ?>', 'auto');
  ga('send', 'pageview');

</script>
<?php } ?>

<!-- Sign in -->
<div class="modal fade loginme" id="signin" role="dialog" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
				<h4 class="modal-title"><?php trans('Sign In',$lang['93']); ?></h4>
			</div>
            <form method="POST" action="<?php createLink('account/login'); ?>" class="loginme-form">
			<div class="modal-body">
				<div class="alert alert-warning">
					<button type="button" class="close dismiss">&times;</button><span></span>
				</div>
                <?php if($enable_oauth){ ?>
				<div class="form-group connect-with">
					<div class="info"><?php trans('Sign in using social network',$lang['94']); ?></div>
					<a href="<?php createLink('facebook/login'); ?>" class="connect facebook" title="<?php trans('Sign in using Facebook',$lang['95']); ?>"><?php trans('Facebook',$lang['98']); ?></a>
		        	<a href="<?php createLink('google/login'); ?>" class="connect google" title="<?php trans('Sign in using Google',$lang['96']); ?>"><?php trans('Google',$lang['99']); ?></a>  	
		        	<a href="<?php createLink('twitter/login'); ?>" class="connect twitter" title="<?php trans('Sign in using Twitter',$lang['97']); ?>"><?php trans('Twitter',$lang['100']); ?></a>		        
			    </div>
                <?php } ?>
   				<div class="info"><?php trans('Sign in with your username',$lang['101']); ?></div>
				<div class="form-group">
					<label><?php trans('Username',$lang['102']); ?> <br />
						<input type="text" name="username" class="form-input width96" />
					</label>
				</div>	
				<div class="form-group">
					<label><?php trans('Password',$lang['103']); ?> <br />
						<input type="password" name="password" class="form-input width96" />
					</label>
				</div>
			</div>
			<div class="modal-footer"> <br />
				<button type="submit" class="btn btn-primary pull-left"><?php trans('Sign In',$lang['93']); ?></button>
				<div class="pull-right align-right">
				    <a href="<?php createLink('account/forget'); ?>"><?php trans('Forgot Password',$lang['104']); ?></a><br />
					<a href="<?php createLink('account/resend'); ?>"><?php trans('Resend Activation Email',$lang['105']); ?></a>
				</div>
			</div>
			 <input type="hidden" name="signin" value="<?php echo md5($date.$ip); ?>" />
             <input type="hidden" name="quick" value="<?php echo md5(randomPassword()); ?>" />
			</form> 
		</div>
	</div>
</div>  

<!-- Sign up -->
<div class="modal fade loginme" id="signup" role="dialog" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
				<h4 class="modal-title"><?php trans('Sign Up',$lang['106']); ?></h4>
			</div>
			<form action="<?php createLink('account/register'); ?>" method="POST" class="loginme-form">
			<div class="modal-body">
				<div class="alert alert-warning">
					<button type="button" class="close dismiss">&times;</button><span></span>
				</div>
                <?php if($enable_oauth){ ?>
				<div class="form-group connect-with">
					<div class="info"><?php trans('Sign in using social network',$lang['94']); ?></div>
					<a href="<?php createLink('facebook/login'); ?>" class="connect facebook" title="<?php trans('Sign in using Facebook',$lang['95']); ?>"><?php trans('Facebook',$lang['98']); ?></a>
		        	<a href="<?php createLink('google/login'); ?>" class="connect google" title="<?php trans('Sign in using Google',$lang['96']); ?>"><?php trans('Google',$lang['99']); ?></a>  	
		        	<a href="<?php createLink('twitter/login'); ?>" class="connect twitter" title="<?php trans('Sign in using Twitter',$lang['97']); ?>"><?php trans('Twitter',$lang['100']); ?></a>		        
			    </div>
                <?php } ?>
   				<div class="info"><?php trans('Sign up with your email address',$lang['107']); ?></div>
				<div class="form-group">
					<label><?php trans('Username',$lang['102']); ?> <br />
						<input type="text" name="username" class="form-input width96" />
					</label>
				</div>	
				<div class="form-group">
					<label><?php trans('Email',$lang['109']); ?> <br />
						<input type="text" name="email" class="form-input width96" />
					</label>
				</div>
				<div class="form-group">
					<label><?php trans('Full Name',$lang['108']); ?> <br />
						<input type="text" name="full" class="form-input width96" />
					</label>
				</div>
				<div class="form-group">
					<label><?php trans('Password',$lang['103']); ?> <br />
						<input type="password" name="password" class="form-input width96" />
					</label>
				</div>
				</div>
			<div class="modal-footer"> <br />
				<button type="submit" class="btn btn-primary"><?php trans('Sign Up',$lang['106']); ?></button>	
			</div>
			<input type="hidden" name="signup" value="<?php echo md5($date.$ip); ?>" />
            <input type="hidden" name="quick" value="<?php echo md5(randomPassword()); ?>" />
			</form>
		</div>
	</div>
</div>

<!-- XD Box -->
<div class="modal fade loginme" id="xdBox" role="dialog" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button id="xdClose" type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
				<h4 class="modal-title" id="xdTitle"></h4>
			</div>
			<div class="modal-body" id="xdContent">

            </div>
		</div>
	</div>
</div>

</body>
</html>