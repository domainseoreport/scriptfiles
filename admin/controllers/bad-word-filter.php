<?php

defined('APP_NAME') or die(header('HTTP/1.0 403 Forbidden'));

/*
 * @author Balaji
 * @name: Turbo Website Reviewer
 * @copyright © 2017 ProThemes.Biz
 *
 */

$pageTitle = 'Bad Words Filter';
$subTitle = 'Add Word to Ban';
$fullLayout = 1; $footerAdd = true; $footerAddArr = $badWordArr = array();

//Get bad word list
$badWordsData =  mysqli_query($con, "SELECT * FROM domain_restriction WHERE id='1'");
$badWordsDataDB = mysqli_fetch_array($badWordsData);
$badWordArr = decSerBase(Trim($badWordsDataDB['words']));

//Delete Action
if($pointOut == 'delete'){
    $key = $args[0];
    if($args[0] != ''){
        array_splice($badWordArr,$key,1);
        $newArr = serBase($badWordArr);
        
        $query = "UPDATE domain_restriction SET words='$newArr' WHERE id='1'";
        mysqli_query($con, $query);

        if (mysqli_errno($con)) {
            $msg = errorMsgAdmin(mysqli_error($con));
        } else {
            header('Location:'.adminLink($controller,true));
            die();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $domain = $title = $description = $keywords = 'off';
    
    if(isset($_POST['domain']))
        $domain = escapeTrim($con,$_POST['domain']);
    else
        $domain = 'off';
        
    if(isset($_POST['title']))
        $title = escapeTrim($con,$_POST['title']);
    else
        $title = 'off';
        
    if(isset($_POST['description']))
        $description = escapeTrim($con,$_POST['description']);
    else
        $description = 'off';
        
    if(isset($_POST['keywords']))
        $keywords = escapeTrim($con,$_POST['keywords']); 
    else
        $keywords = 'off';
 
    $ban_word = escapeTrim($con, $_POST['ban_word']);
    array_unshift($badWordArr,array($ban_word, $date, $domain, $title, $description, $keywords));

    $newArr = serBase($badWordArr);
    $query = "UPDATE domain_restriction SET words='$newArr' WHERE id='1'";
    mysqli_query($con, $query);
   
    if (mysqli_errno($con))   
            $msg = errorMsgAdmin(mysqli_error($con));
    else
        $msg = successMsgAdmin('Bad word added to database successfully.');
}

//True (or) False Image
$true = '<img src="'.themeLink('dist/img/true.png',true).'" alt="'.trans('True',$lang['10'],true).'" />';
$false = '<img src="'.themeLink('dist/img/false.png',true).'" alt="'.trans('False',$lang['9'],true).'" />';

?>