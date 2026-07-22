<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/Core/proteger.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carteiras - Controle Financeiro</title>
    <link rel="stylesheet" href="/assets/css/base.css">
    <link rel="stylesheet" href="/assets/css/pages/carteiras.css">
</head>
<body>
    <main class="container">
        <h1>Minhas carteiras</h1>
        <p class="subtitle">Mantenha seus dados pessoais separados e compartilhe somente a carteira do casal.</p>

        <nav class="nav" aria-label="Navegação principal">
            <?php if (telaClientePermitida($permissoesCliente ?? [], 'dashboard')): ?>
                <a href="/dashboard" class="button">Dashboard</a>
            <?php endif; ?>
            <?php if (telaClientePermitida($permissoesCliente ?? [], 'movimentacoes')): ?>
                <a href="/movimentacoes" class="button">Movimentações</a>
            <?php endif; ?>
            <?php if (telaClientePermitida($permissoesCliente ?? [], 'categorias')): ?>
                <a href="/categorias" class="button">Categorias</a>
            <?php endif; ?>
            <?php if (telaClientePermitida($permissoesCliente ?? [], 'relatorios')): ?>
                <a href="/relatorios" class="button">Relatórios</a>
            <?php endif; ?>
            <a href="/logout" class="button">Sair</a>
        </nav>

        <?php if ($mensagemCarteiras !== ''): ?>
            <div class="msg <?= $tipoMensagemCarteiras ?>" role="<?= $tipoMensagemCarteiras === 'erro' ? 'alert' : 'status' ?>">
                <?= htmlspecialchars($mensagemCarteiras, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <div class="grid">
            <section class="card">
                <h2>Carteira em uso</h2>
                <p>Escolha qual conjunto de dados aparecerá no sistema.</p>
                <form method="post" action="/carteiras">
                    <input type="hidden" name="acao" value="selecionar">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfTokenCarteiras, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="voltar" value="/carteiras">
                    <div class="form-group">
                        <label for="carteira-id">Carteira</label>
                        <select id="carteira-id" name="carteira_id">
                            <?php foreach ($carteirasDisponiveis as $carteira): ?>
                                <option value="<?= (int) $carteira['id'] ?>" <?= (int) $carteira['id'] === $carteiraId ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($carteira['nome'], ENT_QUOTES, 'UTF-8') ?> — <?= $carteira['tipo'] === 'pessoal' ? 'Privada' : 'Compartilhada' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit">Usar esta carteira</button>
                </form>
            </section>

            <?php if (!$carteiraCasal): ?>
                <section class="card">
                    <h2>Criar carteira do casal</h2>
                    <p>Depois de criar, adicione a outra pessoa pelo e-mail da conta dela.</p>
                    <form method="post" action="/carteiras">
                        <input type="hidden" name="acao" value="criar_casal">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfTokenCarteiras, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="form-group">
                            <label for="nome-carteira">Nome</label>
                            <input type="text" id="nome-carteira" name="nome" maxlength="100" value="Carteira do casal" required>
                        </div>
                        <button type="submit">Criar carteira</button>
                    </form>
                </section>
            <?php else: ?>
                <section class="card">
                    <h2>Adicionar a outra pessoa</h2>
                    <?php if (count($membrosCarteiraCasal) < 2 && $carteiraCasal['papel'] === 'administrador'): ?>
                        <p>A pessoa precisa primeiro possuir uma conta cadastrada no sistema.</p>
                        <form method="post" action="/carteiras">
                            <input type="hidden" name="acao" value="adicionar_parceiro">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfTokenCarteiras, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="carteira_id" value="<?= (int) $carteiraCasal['id'] ?>">
                            <div class="form-group">
                                <label for="email-parceiro">E-mail da outra pessoa</label>
                                <input type="email" id="email-parceiro" name="email" required>
                            </div>
                            <button type="submit">Compartilhar carteira</button>
                        </form>
                    <?php else: ?>
                        <p>A carteira do casal já está configurada com seus integrantes.</p>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if ($carteiraCasal): ?>
                <section class="card full">
                    <h2>Integrantes de <?= htmlspecialchars($carteiraCasal['nome'], ENT_QUOTES, 'UTF-8') ?></h2>
                    <ul class="member-list">
                        <?php foreach ($membrosCarteiraCasal as $membro): ?>
                            <li class="member">
                                <div>
                                    <strong><?= htmlspecialchars($membro['nome'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <small><?= htmlspecialchars($membro['email'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars(ucfirst($membro['papel']), ENT_QUOTES, 'UTF-8') ?></small>
                                </div>
                                <span class="status <?= (int) $membro['online'] === 1 ? 'online' : '' ?>" data-member-id="<?= (int) $membro['id'] ?>">
                                    <?= (int) $membro['online'] === 1 ? 'Online agora' : 'Offline' ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>
        </div>
    </main>
    <script>
        (() => {
            if (typeof window.fetch !== 'function') return;

            const refreshMembers = async () => {
                try {
                    const response = await fetch('/api/presenca/status', {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                        cache: 'no-store'
                    });
                    if (!response.ok) return;

                    const data = await response.json();
                    if (!Array.isArray(data.membros)) return;

                    data.membros.forEach((member) => {
                        const status = document.querySelector(`[data-member-id="${Number(member.id)}"]`);
                        if (!status) return;
                        status.classList.toggle('online', member.online === true);
                        status.textContent = member.online === true ? 'Online agora' : 'Offline';
                    });
                } catch (error) {
                    // Mantém o último estado exibido se a atualização falhar.
                }
            };

            window.setInterval(refreshMembers, 30000);
        })();
    </script>
</body>
</html>
