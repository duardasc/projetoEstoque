<?php
session_start(); 


$_SESSION = array();

// Se você usa cookies para a sessão, deve apagá-los também
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}


session_destroy();

// Redirecionar para a página de login
header('Location: login.php');
exit();
?>
