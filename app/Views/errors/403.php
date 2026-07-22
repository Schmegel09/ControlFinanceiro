<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/Core/proteger.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso negado - Controle Financeiro</title>
    <link rel="stylesheet" href="/assets/css/base.css">
    <link rel="stylesheet" href="/assets/css/pages/access-control.css">
</head>
<body>
    <main class="access-card">
        <span class="access-code">403</span>
        <h1>Acesso negado</h1>
        <p>Esta área é exclusiva do superadministrador do sistema.</p>
        <div class="access-actions">
            <a href="/dashboard">Voltar ao dashboard</a>
            <a href="/logout" class="secondary">Sair</a>
        </div>
    </main>
</body>
</html>
