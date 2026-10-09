<?php 
if (session_status() == PHP_SESSION_NONE) {
    if (function_exists('start_secure_session')) {
        start_secure_session();
    } else {
        session_start();
    }
}
if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') 
    $link = "https"; 
else
    $link = "http"; 
$link .= "://"; 
$link .= $_SERVER['HTTP_HOST']; 
$link .= $_SERVER['REQUEST_URI'];
if(!isset($_SESSION['userdata']) && strpos($link, 'login.php') === false){
	redirect('admin/login.php');
}
if(isset($_SESSION['userdata']) && strpos($link, 'login.php') !== false){
	redirect('admin/index.php');
}

