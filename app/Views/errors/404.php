<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/Core/proteger.php';

/*
Legenda (404.php):
- Página de erro exibida quando a rota requisitada não existe no `routes/web.php`.
- Para personalizar, edite este arquivo e o layout HTML.
*/

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Página não encontrada</title>
    <link rel="stylesheet" href="/assets/css/base.css">
    <link rel="stylesheet" href="/assets/css/pages/404.css">
</head>
<body>
    <main>
        <h1>404 - Página não encontrada</h1>
        <p>A página que você está procurando não existe.</p>
        <p><a href="/login" class="button">Voltar para o início</a></p>
    </main>
</body>
</html>
