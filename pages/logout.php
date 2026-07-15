<?php

/*
Legenda (logout.php):
- Limpa sessão e redireciona para `/login`.
*/

session_start();

// Remove todas as variáveis de sessão
$_SESSION = array();

// Se estiver usando cookies de sessão, elas também serão removidas
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroi a sessão
session_destroy();

// Redireciona para a página de login
header('Location: /login');
exit;