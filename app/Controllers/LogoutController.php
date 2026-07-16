<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Core/proteger.php';

/**
 * O logout é uma ação de controller e não possui view própria:
 * a rota /logout encerra a sessão e retorna o usuário ao login.
 */
encerrarSessao();

header('Location: /login', true, 303);
exit;
