<?php
defined('APP_NAME') or die(header('HTTP/1.0 403 Forbidden'));
?>
<!DOCTYPE html>
<html>
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta http-equiv="Content-Language" content="<?php echo ACTIVE_LANG; ?>" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo $themeOptions['general']['favicon']; ?>" />

    <!-- Meta Data -->
    <title><?php echo $metaTitle; ?></title>
    <meta property="site_name" content="<?php echo $site_name; ?>"/>
    <meta name="description" content="<?php echo $des; ?>" />
    <meta name="keywords" content="<?php echo $keyword; ?>" />
    <meta name="author" content="Balaji" />

    <!-- Open Graph -->
    <meta property="og:title" content="<?php echo $metaTitle; ?>" />
    <meta property="og:site_name" content="<?php echo $site_name; ?>" />
    <meta property="og:type" content="website" />
    <meta property="og:description" content="<?php echo $des; ?>" />

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,800;1,900&display=swap" rel="stylesheet">

    <?php
      // Generate canonical and hreflang links if needed.
      genCanonicalData($baseURL, $currentLink, $loadedLanguages, false, isSelected($themeOptions['general']['langSwitch']));
    ?>

    <!-- Main CSS Files -->
    <!-- Use Bootstrap 5 CSS from CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="<?php themeLink('css/font-awesome.min.css'); ?>" rel="stylesheet" />
    <link href="<?php themeLink('css/custom.css'); ?>" rel="stylesheet" type="text/css" />
    <?php if($isRTL) echo '<link href="'.themeLink('css/rtl.css', true).'" rel="stylesheet" type="text/css" />'; ?>
    <?php if($themeOptions['custom']['css'] != '') echo '<style>'.htmlPrint($themeOptions['custom']['css'], true).'</style>'; ?>

    <!-- IMPORTANT: Load jQuery once (for your jQuery‐dependent plugins) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
  </head>

  <body data-spy="scroll" data-target="#scroll-menu" data-offset="50" id="top">
    
    <!-- mobile-nav -->
    <nav class="mobile-nav">
      <ul class="main-nav">
        <?php 
          foreach($headerLinks as $headerLink)
            echo $headerLink[1];
        ?>
      </ul>
      
      <ul class="login-nav">
        <?php echo $loginNav; ?>
      </ul>
      
      <ul class="main-nav">
        <li class="wrapper-submenu">
          <?php if(isSelected($themeOptions['general']['langSwitch'])){ ?>
            <a href="javascript:void(0)"><?php echo strtoupper(ACTIVE_LANG); ?> <i class="fa fa-angle-down"></i></a>
            <div class="submenu">
              <ul class="submenu-nav">
                <?php 
                  foreach($loadedLanguages as $language){
                    echo '<li><a href="'.$baseURL.$language[2].'">'.$language[3].'</a></li>';
                  }
                ?>
              </ul>
              <span class="arrow"></span>
            </div>
          <?php } ?>
        </li>
      </ul>
    </nav>
    <!-- mobile-nav -->

    <div class="main-content">
      <!-- desktop-nav -->
      <div class="wrapper-header fixed-top">
        <div class="container main-header" id="header">
          <a href="<?php createLink(); ?>">
            <div class="logo">
              <?php echo $themeOptions['general']['themeLogo']; ?>
            </div>
          </a>
          <a href="javascript:void(0)" class="start-mobile-nav"><span class="fa fa-bars"></span></a>
          <nav class="desktop-nav">
            <ul class="main-nav">
              <?php 
                foreach($headerLinks as $headerLink)
                  echo $headerLink[1];
              ?>
            </ul>
            <ul class="login-nav">
              <?php if(isSelected($themeOptions['general']['langSwitch'])){ ?>
                <li class="dropdown">
                  <a href="javascript:void(0)" data-bs-toggle="dropdown" class="dropdown-toggle" aria-expanded="false">
                    <i class="fa fa-globe fa-lg"></i>
                  </a>
                  <ul class="dropdown-menu">
                    <?php 
                      foreach($loadedLanguages as $language){
                        echo '<li><a class="dropdown-item" href="'.$baseURL.$language[2].'">'.$language[3].'</a></li>';
                      }
                    ?>
                  </ul>
                </li>
                <li class="lang-li"><a><?php echo strtoupper(ACTIVE_LANG); ?></a></li>
              <?php } echo $loginNav; ?>
            </ul>
          </nav>
        </div>
      </div>
      <!-- desktop-nav -->

      <?php if($controller == CON_MAIN){ ?>
        <section class="headturbo" id="headturbo">
          <div class="headturbo-wrap" id="headturbo-wrap">
            <div class="texture-overlay"></div>
            <div class="container">
              <div class="row">
                <div style="height: 870px;" class="headturbo-img d-none d-md-block"></div>
                <div class="col-md-12 text-center">
                  <div class="headturbo-content">
                    <h1 class="pulse"><?php trans('Instantly Analyze Your SEO Issues',$lang['145']); ?></h1>
                    <h2><?php trans('Helps to identify your SEO mistakes and better optimize your site content.',$lang['146']); ?></h2>
                    <form class="turboform" method="POST" action="<?php createLink('domain'); ?>" onsubmit="return fixURL();">
                      <div class="input-group review">
                        <input type="text" autocomplete="off" spellcheck="false" class="form-control" placeholder="<?php trans('Type Your Website Address',$lang['147']); ?>" name="url" />
                        <button class="btn btn-green" type="submit" id="review-btn">
                          <span class="glyphicon glyphicon-search"></span> <?php trans('REVIEW',$lang['148']); ?>
                        </button>
                      </div>
                    </form>
                    <br />
                    <ul class="top-link list-inline">
                      <?php 
                        if($themeOptions['general']['example1'] != '') echo '<li><a href="'.$baseLink.'domain/'.$themeOptions['general']['example1'][0].'">'.htmlPrint($themeOptions['general']['example1'][1], true).'</a></li>'; 
                        if($themeOptions['general']['example2'] != '') echo '<li><a href="'.$baseLink.'domain/'.$themeOptions['general']['example2'][0].'">'.htmlPrint($themeOptions['general']['example2'][1], true).'</a></li>'; 
                        if($themeOptions['general']['example3'] != '') echo '<li><a href="'.$baseLink.'domain/'.$themeOptions['general']['example3'][0].'">'.htmlPrint($themeOptions['general']['example3'][1], true).'</a></li>'; 
                      ?>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>
      <?php } else { ?>
        <div class="bg-primary-color page-block">
           
        </div>
      <?php } ?>
    </div>