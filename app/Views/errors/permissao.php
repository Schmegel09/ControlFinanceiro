<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/Core/proteger.php';

/** @var array<string, bool> $permissoesCliente */
$linksTelas = [
    'dashboard' => ['/dashboard', 'Dashboard'],
    'movimentacoes' => ['/movimentacoes', 'Movimentações'],
    'categorias' => ['/categorias', 'Categorias'],
    'relatorios' => ['/relatorios', 'Relatórios'],
    'carteiras' => ['/carteiras', 'Carteiras'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tela não liberada - Controle Financeiro</title>
    <link rel="stylesheet" href="/assets/css/base.css">
    <link rel="stylesheet" href="/assets/css/pages/access-control.css">
</head>
<body>
    <main class="access-card">
        <span class="access-code">403</span>
        <h1>Tela não liberada</h1>
        <p>Seu plano não possui acesso a esta área. Entre em contato com o administrador caso precise da liberação.</p>
        <div class="access-actions screen-links">
            <?php foreach ($linksTelas as $tela => [$url, $rotulo]): ?>
                <?php if (($permissoesCliente[$tela] ?? false) === true): ?>
                    <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($rotulo, ENT_QUOTES, 'UTF-8') ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
            <a href="/logout" class="secondary">Sair</a>
        </div>
    </main>
</body>
</html>
