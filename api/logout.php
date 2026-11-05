<?php
// =====================================================
// api/logout.php
// Encerra a sessão e volta para a página inicial.
// =====================================================
session_start();

$_SESSION = [];

// apaga também o cookie de sessão do navegador
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

session_destroy();

header('Location: ../index.html');
exit;
