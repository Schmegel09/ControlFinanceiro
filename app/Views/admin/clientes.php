<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/Core/proteger.php';

/** @var array<int, array<string, mixed>> $clientes */
/** @var array<int, array<string, mixed>> $usuariosSistema */
/** @var array<string, int> $resumoClientes */
/** @var string $csrfTokenAdmin */
/** @var string $mensagem */
/** @var string $tipoMensagem */

$rotulosStatus = [
    'pendente' => 'Pendente',
    'ativo' => 'Ativo',
    'em_atraso' => 'Em atraso',
    'bloqueado' => 'Bloqueado',
    'cancelado' => 'Cancelado',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes - Administração</title>
    <link rel="stylesheet" href="/assets/css/base.css">
    <link rel="stylesheet" href="/assets/css/pages/admin-clientes.css">
</head>
<body>
    <main class="admin-container">
        <header class="admin-header">
            <div>
                <span class="admin-eyebrow">Superadministração</span>
                <h1>Controle de clientes</h1>
                <p>Libere, renove ou bloqueie o acesso sem excluir os dados financeiros.</p>
            </div>
            <nav aria-label="Navegação administrativa">
                <a href="/dashboard">Dashboard</a>
                <a href="/logout" class="secondary">Sair</a>
            </nav>
        </header>

        <?php if (($mensagem ?? '') !== ''): ?>
            <div class="admin-message <?= ($tipoMensagem ?? 'sucesso') === 'erro' ? 'error' : 'success' ?>" role="<?= ($tipoMensagem ?? 'sucesso') === 'erro' ? 'alert' : 'status' ?>">
                <?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <section class="summary-grid" aria-label="Resumo dos clientes">
            <?php foreach ($rotulosStatus as $status => $rotulo): ?>
                <article>
                    <span><?= htmlspecialchars($rotulo, ENT_QUOTES, 'UTF-8') ?></span>
                    <strong><?= (int) ($resumoClientes[$status] ?? 0) ?></strong>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="system-roles" aria-labelledby="system-roles-title">
            <header>
                <div>
                    <span class="admin-eyebrow dark">Permissões do sistema</span>
                    <h2 id="system-roles-title">Superadministradores</h2>
                    <p>Promova somente pessoas autorizadas. Essa função ignora bloqueios de assinatura e administra todos os clientes.</p>
                </div>
            </header>

            <div class="user-role-list">
                <?php foreach ($usuariosSistema as $usuarioSistema): ?>
                    <?php
                    $ehSuperAdmin = (string) $usuarioSistema['papel_sistema'] === 'superadmin';
                    $protegidoAmbiente = (bool) $usuarioSistema['protegido_pelo_ambiente'];
                    $ehUsuarioAtual = (int) $usuarioSistema['id'] === (int) ($_SESSION['usuario_id'] ?? 0);
                    ?>
                    <article class="user-role-row">
                        <div class="user-role-identity">
                            <strong><?= htmlspecialchars((string) $usuarioSistema['nome'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <span><?= htmlspecialchars((string) $usuarioSistema['email'], ENT_QUOTES, 'UTF-8') ?></span>
                            <small>
                                <?= htmlspecialchars((string) ($usuarioSistema['cliente_nome'] ?? 'Sem cliente'), ENT_QUOTES, 'UTF-8') ?>
                                · E-mail <?= empty($usuarioSistema['email_verificado_em']) ? 'não confirmado' : 'confirmado' ?>
                            </small>
                        </div>

                        <div class="user-role-control">
                            <?php if ($ehSuperAdmin || $protegidoAmbiente): ?>
                                <span class="role-badge">Superadministrador</span>
                            <?php else: ?>
                                <span class="role-badge regular">Usuário</span>
                            <?php endif; ?>

                            <?php if ($protegidoAmbiente): ?>
                                <span class="protected-label">Protegido pelo .env</span>
                            <?php elseif ($ehUsuarioAtual): ?>
                                <span class="protected-label">Sua conta</span>
                            <?php else: ?>
                                <form method="post" action="/admin-clientes" onsubmit="return confirm('Confirma a alteração desta permissão administrativa?')">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfTokenAdmin, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="acao" value="alterar_papel">
                                    <input type="hidden" name="usuario_id" value="<?= (int) $usuarioSistema['id'] ?>">
                                    <input type="hidden" name="papel_sistema" value="<?= $ehSuperAdmin ? 'usuario' : 'superadmin' ?>">
                                    <button type="submit" class="role-action <?= $ehSuperAdmin ? 'remove' : '' ?>">
                                        <?= $ehSuperAdmin ? 'Remover função' : 'Tornar superadmin' ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="client-list" aria-label="Clientes cadastrados">
            <?php if ($clientes === []): ?>
                <div class="empty-state">Nenhum cliente cadastrado.</div>
            <?php endif; ?>

            <?php foreach ($clientes as $cliente): ?>
                <?php $statusCliente = (string) $cliente['status']; ?>
                <article class="client-card">
                    <header class="client-card-header">
                        <div>
                            <span class="status-pill status-<?= htmlspecialchars($statusCliente, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($rotulosStatus[$statusCliente] ?? $statusCliente, ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <h2><?= htmlspecialchars((string) $cliente['nome'], ENT_QUOTES, 'UTF-8') ?></h2>
                            <p><?= htmlspecialchars((string) ($cliente['emails'] ?: 'Sem usuário vinculado'), ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <dl>
                            <div>
                                <dt>ID</dt>
                                <dd>#<?= (int) $cliente['id'] ?></dd>
                            </div>
                            <div>
                                <dt>Usuários</dt>
                                <dd><?= (int) $cliente['total_usuarios'] ?></dd>
                            </div>
                            <div>
                                <dt>E-mails OK</dt>
                                <dd><?= (int) $cliente['emails_verificados'] ?>/<?= (int) $cliente['total_usuarios'] ?></dd>
                            </div>
                        </dl>
                    </header>

                    <div class="quick-actions" aria-label="Ações rápidas do cliente">
                        <span>Ações rápidas</span>
                        <?php
                        $acoesRapidas = [
                            'ativo' => ['Liberar', 'release'],
                            'em_atraso' => ['Marcar em atraso', 'warning'],
                            'bloqueado' => ['Bloquear', 'danger'],
                            'cancelado' => ['Cancelar', 'danger-outline'],
                        ];
                        ?>
                        <?php foreach ($acoesRapidas as $novoStatus => [$rotuloAcao, $classeAcao]): ?>
                            <?php if ($statusCliente !== $novoStatus): ?>
                                <form
                                    method="post"
                                    action="/admin-clientes"
                                    onsubmit="return confirm('Confirma a ação: <?= htmlspecialchars($rotuloAcao, ENT_QUOTES, 'UTF-8') ?>?')"
                                >
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfTokenAdmin, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="cliente_id" value="<?= (int) $cliente['id'] ?>">
                                    <input type="hidden" name="acao" value="alterar_status_rapido">
                                    <input type="hidden" name="novo_status" value="<?= htmlspecialchars($novoStatus, ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="quick-action <?= htmlspecialchars($classeAcao, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($rotuloAcao, ENT_QUOTES, 'UTF-8') ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>

                    <form method="post" action="/admin-clientes" class="client-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfTokenAdmin, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="cliente_id" value="<?= (int) $cliente['id'] ?>">
                        <input type="hidden" name="acao" value="salvar">

                        <label>
                            <span>Nome do cliente</span>
                            <input type="text" name="nome" maxlength="120" required value="<?= htmlspecialchars((string) $cliente['nome'], ENT_QUOTES, 'UTF-8') ?>">
                        </label>
                        <label>
                            <span>Domínio do cliente</span>
                            <input type="text" name="dominio" maxlength="190" placeholder="cliente.seudominio.com" value="<?= htmlspecialchars((string) ($cliente['dominio'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        </label>
                        <label>
                            <span>Status</span>
                            <select name="status" required>
                                <?php foreach ($rotulosStatus as $status => $rotulo): ?>
                                    <option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>" <?= $status === $statusCliente ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($rotulo, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            <span>Vencimento</span>
                            <input type="date" name="vencimento" value="<?= htmlspecialchars((string) ($cliente['vencimento'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        </label>
                        <label>
                            <span>Dias de tolerância</span>
                            <input type="number" name="dias_tolerancia" min="0" max="90" required value="<?= (int) $cliente['dias_tolerancia'] ?>">
                        </label>
                        <label class="reason-field">
                            <span>Motivo ou orientação para o bloqueio</span>
                            <input type="text" name="motivo_bloqueio" maxlength="255" placeholder="Ex.: pagamento pendente" value="<?= htmlspecialchars((string) ($cliente['motivo_bloqueio'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        </label>

                        <fieldset class="screen-permissions">
                            <legend>Telas liberadas para este cliente</legend>
                            <div>
                                <?php foreach (TELAS_CLIENTE as $tela => $rotuloTela): ?>
                                    <label class="permission-option">
                                        <input
                                            type="checkbox"
                                            name="telas[]"
                                            value="<?= htmlspecialchars($tela, ENT_QUOTES, 'UTF-8') ?>"
                                            <?= (($cliente['permissoes'][$tela] ?? false) === true) ? 'checked' : '' ?>
                                        >
                                        <span><?= htmlspecialchars($rotuloTela, ENT_QUOTES, 'UTF-8') ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>

                        <button type="submit">Salvar controle de acesso</button>
                    </form>

                    <form method="post" action="/admin-clientes" class="renew-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfTokenAdmin, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="cliente_id" value="<?= (int) $cliente['id'] ?>">
                        <input type="hidden" name="acao" value="renovar">
                        <label>
                            <span>Renovar por</span>
                            <select name="dias_renovacao">
                                <option value="30">30 dias</option>
                                <option value="60">60 dias</option>
                                <option value="90">90 dias</option>
                                <option value="365">1 ano</option>
                            </select>
                        </label>
                        <button type="submit">Renovar e liberar</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </section>
    </main>
</body>
</html>
