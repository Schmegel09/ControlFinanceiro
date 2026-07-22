<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/Core/proteger.php';

/** @var array{permitido: bool, status_efetivo: string, mensagem: string, cliente: array<string, mixed>|null} $acessoCliente */
$clienteBloqueado = $acessoCliente['cliente'];
$titulosStatus = [
    'pendente' => 'Cadastro aguardando aprovação',
    'em_atraso' => 'Assinatura em atraso',
    'bloqueado' => 'Acesso temporariamente bloqueado',
    'cancelado' => 'Assinatura cancelada',
];
$tituloBloqueio = $titulosStatus[$acessoCliente['status_efetivo']] ?? 'Acesso indisponível';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso bloqueado - Controle Financeiro</title>
    <link rel="stylesheet" href="/assets/css/base.css">
    <link rel="stylesheet" href="/assets/css/pages/access-control.css">
</head>
<body>
    <main class="access-card">
        <span class="status-pill status-<?= htmlspecialchars($acessoCliente['status_efetivo'], ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars(str_replace('_', ' ', $acessoCliente['status_efetivo']), ENT_QUOTES, 'UTF-8') ?>
        </span>
        <h1><?= htmlspecialchars($tituloBloqueio, ENT_QUOTES, 'UTF-8') ?></h1>
        <p><?= htmlspecialchars($acessoCliente['mensagem'], ENT_QUOTES, 'UTF-8') ?></p>

        <?php if (is_array($clienteBloqueado)): ?>
            <dl class="access-details">
                <div>
                    <dt>Cliente</dt>
                    <dd><?= htmlspecialchars((string) $clienteBloqueado['nome'], ENT_QUOTES, 'UTF-8') ?></dd>
                </div>
                <?php if (!empty($clienteBloqueado['vencimento'])): ?>
                    <div>
                        <dt>Vencimento</dt>
                        <dd><?= date('d/m/Y', strtotime((string) $clienteBloqueado['vencimento'])) ?></dd>
                    </div>
                <?php endif; ?>
            </dl>
        <?php endif; ?>

        <p class="access-help">Entre em contato com o responsável pelo sistema para regularizar ou liberar sua conta. Seus dados permanecem armazenados.</p>
        <div class="access-actions">
            <a href="/logout" class="secondary">Sair da conta</a>
        </div>
    </main>
</body>
</html>
