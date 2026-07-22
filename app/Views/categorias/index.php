<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/Core/proteger.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categorias - Controle Financeiro</title>
    <link rel="stylesheet" href="/assets/css/base.css">
    <link rel="stylesheet" href="/assets/css/pages/categorias.css">
    <link rel="stylesheet" href="/assets/css/components.css">
</head>
<body>
    <main class="container">
        <h1>Categorias</h1>
        <p class="subtitle">Crie e gerencie as categorias para suas receitas e despesas.</p>

        <?php require dirname(__DIR__) . '/partials/carteira_switcher.php'; ?>

        <div class="top-row">
            <nav class="actions" aria-label="Navegação principal">
                <?php if (telaClientePermitida($permissoesCliente ?? [], 'dashboard')): ?>
                    <a href="/dashboard" class="button">Dashboard</a>
                <?php endif; ?>
                <?php if (telaClientePermitida($permissoesCliente ?? [], 'movimentacoes')): ?>
                    <a href="/movimentacoes" class="button">Movimentações</a>
                <?php endif; ?>
                <a href="/logout" class="button logout-btn">Sair</a>
            </nav>
        </div>

        <?php if ($mensagem !== ''): ?>
            <div class="msg <?= $tipoMensagem === 'erro' ? 'erro' : 'sucesso' ?>" role="<?= $tipoMensagem === 'erro' ? 'alert' : 'status' ?>" aria-live="<?= $tipoMensagem === 'erro' ? 'assertive' : 'polite' ?>">
                <?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <div class="form-card">
            <h2>Nova categoria</h2>
            <form method="post" action="/categorias">
                <input type="hidden" name="acao" value="adicionar">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfTokenCategorias, ENT_QUOTES, 'UTF-8') ?>">
                <div class="form-row">
                    <div class="form-group">
                        <label for="nome">Nome</label>
                        <input type="text" id="nome" name="nome" maxlength="100" placeholder="Ex: Salário, Alimentação" required>
                    </div>
                    <div class="form-group">
                        <label for="tipo">Tipo</label>
                        <select id="tipo" name="tipo" required>
                            <option value="receita">Receita</option>
                            <option value="despesa">Despesa</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="cor">Cor</label>
                        <input type="color" id="cor" name="cor" value="#667eea">
                    </div>
                </div>
                <button type="submit" class="button">Criar categoria</button>
            </form>
        </div>

        <div class="category-lists">
            <?php
            $gruposCategorias = [
                ['titulo' => 'Receitas', 'tipo' => 'receita', 'itens' => $categoriasReceita],
                ['titulo' => 'Despesas', 'tipo' => 'despesa', 'itens' => $categoriasDespesa],
            ];
            ?>
            <?php foreach ($gruposCategorias as $grupo): ?>
                <section class="list-card" aria-labelledby="categorias-<?= $grupo['tipo'] ?>-titulo">
                    <h2 id="categorias-<?= $grupo['tipo'] ?>-titulo"><?= $grupo['titulo'] ?> (<?= count($grupo['itens']) ?>)</h2>
                    <?php if (count($grupo['itens']) === 0): ?>
                        <p class="empty-categories">Nenhuma categoria de <?= $grupo['tipo'] ?> criada.</p>
                    <?php else: ?>
                        <div class="category-list">
                            <?php foreach ($grupo['itens'] as $cat): ?>
                                <article class="category-item <?= $grupo['tipo'] ?>">
                                    <div class="color-box" style="--category-color: <?= htmlspecialchars($cat['cor'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></div>
                                    <div class="category-info">
                                        <h3><?= htmlspecialchars($cat['nome'], ENT_QUOTES, 'UTF-8') ?></h3>
                                        <div class="category-meta">
                                            <span>Criada em <?= date('d/m/Y', strtotime($cat['criada_em'])) ?></span>
                                            <span><?= (int) $cat['total_movimentacoes'] ?> <?= (int) $cat['total_movimentacoes'] === 1 ? 'lançamento vinculado' : 'lançamentos vinculados' ?></span>
                                        </div>
                                    </div>
                                    <div class="category-actions">
                                        <button
                                            type="button"
                                            class="edit-btn"
                                            aria-label="Editar categoria <?= htmlspecialchars($cat['nome'], ENT_QUOTES, 'UTF-8') ?>"
                                            onclick="abrirEdicaoCategoria(<?= (int) $cat['id'] ?>, this)"
                                        >Editar</button>
                                        <form method="post" action="/categorias" onsubmit="return confirm('Deseja excluir esta categoria? Os lançamentos existentes serão mantidos.')">
                                            <input type="hidden" name="acao" value="deletar">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfTokenCategorias, ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="id" value="<?= (int) $cat['id'] ?>">
                                            <button type="submit" class="delete-btn" aria-label="Excluir categoria <?= htmlspecialchars($cat['nome'], ENT_QUOTES, 'UTF-8') ?>">Excluir</button>
                                        </form>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        </div>
    </main>

    <div
        id="category-edit-modal"
        class="category-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="category-edit-title"
        aria-describedby="category-edit-help"
        aria-hidden="true"
    >
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="category-edit-title">Editar categoria</h2>
                <button type="button" class="modal-close" aria-label="Fechar edição" onclick="fecharEdicaoCategoria()">&times;</button>
            </div>
            <form method="post" action="/categorias" id="category-edit-form" class="modal-form">
                <input type="hidden" name="acao" value="editar">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfTokenCategorias, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="id" id="category-edit-id">

                <div class="form-group">
                    <label for="category-edit-name">Nome</label>
                    <input type="text" id="category-edit-name" name="nome" maxlength="100" required>
                </div>
                <div class="form-group">
                    <label for="category-edit-type">Tipo</label>
                    <select id="category-edit-type" name="tipo" required>
                        <option value="receita">Receita</option>
                        <option value="despesa">Despesa</option>
                    </select>
                    <input type="hidden" id="category-edit-type-locked" name="tipo" disabled>
                </div>
                <div class="form-group">
                    <label for="category-edit-color">Cor</label>
                    <input type="color" id="category-edit-color" name="cor" required>
                </div>

                <p id="category-edit-help" class="modal-help"></p>
                <button type="submit" class="modal-save">Salvar alterações</button>
            </form>
        </div>
    </div>

    <script>
        const categoriasCrudData = <?= $categoriasCrudJson ?>;
        const categoryModal = document.getElementById('category-edit-modal');
        const categoryPageContent = document.querySelector('.container');
        const categoryEditName = document.getElementById('category-edit-name');
        const categoryEditType = document.getElementById('category-edit-type');
        const categoryEditTypeLocked = document.getElementById('category-edit-type-locked');
        const categoryEditHelp = document.getElementById('category-edit-help');
        let categoryOriginButton = null;

        function abrirEdicaoCategoria(id, botao = null) {
            const categoria = categoriasCrudData.find(item => String(item.id) === String(id));

            if (!categoria) {
                window.alert('Não foi possível localizar esta categoria. Atualize a página e tente novamente.');
                return;
            }

            categoryOriginButton = botao;
            document.getElementById('category-edit-id').value = categoria.id;
            categoryEditName.value = categoria.nome;
            categoryEditType.value = categoria.tipo;
            document.getElementById('category-edit-color').value = categoria.cor;

            const totalVinculos = Number(categoria.total_movimentacoes || 0);
            categoryEditType.disabled = totalVinculos > 0;
            categoryEditTypeLocked.disabled = totalVinculos === 0;
            categoryEditTypeLocked.value = categoria.tipo;
            categoryEditHelp.textContent = totalVinculos > 0
                ? `Esta categoria possui ${totalVinculos} ${totalVinculos === 1 ? 'lançamento vinculado' : 'lançamentos vinculados'}. O nome e a cor podem ser alterados, mas o tipo deve ser mantido.`
                : 'Esta categoria ainda não possui lançamentos vinculados e pode ter todos os campos alterados.';

            categoryModal.classList.add('active');
            categoryModal.setAttribute('aria-hidden', 'false');
            if (categoryPageContent && 'inert' in categoryPageContent) {
                categoryPageContent.inert = true;
            }
            document.body.style.overflow = 'hidden';
            categoryEditName.focus();
        }

        function fecharEdicaoCategoria() {
            categoryModal.classList.remove('active');
            categoryModal.setAttribute('aria-hidden', 'true');
            if (categoryPageContent && 'inert' in categoryPageContent) {
                categoryPageContent.inert = false;
            }
            document.body.style.overflow = '';

            if (categoryOriginButton) {
                categoryOriginButton.focus();
            }
        }

        categoryModal.addEventListener('click', function (event) {
            if (event.target === categoryModal) {
                fecharEdicaoCategoria();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (!categoryModal.classList.contains('active')) {
                return;
            }

            if (event.key === 'Escape') {
                fecharEdicaoCategoria();
                return;
            }

            if (event.key === 'Tab') {
                const elementosFocaveis = Array.from(categoryModal.querySelectorAll(
                    'button:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'
                ));
                const primeiroElemento = elementosFocaveis[0];
                const ultimoElemento = elementosFocaveis[elementosFocaveis.length - 1];

                if (event.shiftKey && document.activeElement === primeiroElemento) {
                    event.preventDefault();
                    ultimoElemento.focus();
                } else if (!event.shiftKey && document.activeElement === ultimoElemento) {
                    event.preventDefault();
                    primeiroElemento.focus();
                }
            }
        });
    </script>
</body>
</html>
