<?php

declare(strict_types=1);

session_start();

require_once dirname(__DIR__) . '/config/conexao.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: /login');
    exit;
}

require_once dirname(__DIR__) . '/includes/categorias_controller.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categorias - Controle Financeiro</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; min-height: 100dvh; padding: clamp(10px, 2vw, 20px); }
        .container { width: 100%; max-width: 1000px; margin: 0 auto; background: white; padding: clamp(20px, 3vw, 32px); border-radius: 18px; box-shadow: 0 18px 50px rgba(0,0,0,0.15); }
        h1 { color: #333; margin-bottom: 8px; font-size: clamp(26px, 4vw, 34px); line-height: 1.2; overflow-wrap: anywhere; }
        .subtitle { color: #666; margin-bottom: 24px; font-size: 16px; }
        .top-row { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 16px; margin-bottom: 28px; }
        .actions { display: flex; gap: 12px; flex-wrap: wrap; }
        a.button, button.button { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: 12px 22px; background: #4f5fc9; color: white; text-align: center; text-decoration: none; border: none; border-radius: 10px; font-weight: 600; transition: transform 0.2s, box-shadow 0.2s; cursor: pointer; }
        a.button:hover, button.button:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(102, 126, 234, 0.25); }
        .form-card { background: #f7f8ff; border-radius: 18px; padding: 24px; margin-bottom: 24px; }
        .form-card h2 { margin-bottom: 18px; color: #333; font-size: 20px; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 16px; }
        .form-group { display: flex; flex-direction: column; }
        label { margin-bottom: 8px; color: #555; font-size: 14px; }
        input, select { padding: 12px 14px; border-radius: 10px; border: 1px solid #d7dbf0; font-size: 15px; }
        .form-card button[type="submit"] { min-height: 44px; background: #4f5fc9; color: white; padding: 12px 24px; border: none; border-radius: 12px; font-size: 15px; font-weight: 700; cursor: pointer; }
        .form-card button[type="submit"]:hover { background: #3f4fae; }
        .category-lists { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 24px; align-items: start; }
        .list-card { min-width: 0; background: #f7f8ff; border-radius: 18px; padding: 24px; }
        .list-card > h2 { margin-bottom: 18px; color: #333; font-size: 20px; }
        .category-list { display: grid; gap: 12px; }
        .category-item { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; gap: 16px; align-items: center; padding: 16px; background: white; border-radius: 12px; margin-bottom: 12px; border-left: 4px solid; }
        .category-list .category-item { margin-bottom: 0; }
        .category-item.receita { border-left-color: #167552; }
        .category-item.despesa { border-left-color: #b42f3c; }
        .color-box { width: 24px; height: 24px; border-radius: 6px; }
        .category-info { min-width: 0; }
        .category-info h3 { margin-bottom: 4px; color: #333; font-size: 16px; overflow-wrap: anywhere; }
        .category-meta { display: flex; flex-wrap: wrap; gap: 4px 10px; color: #666; font-size: 13px; line-height: 1.4; }
        .msg { margin-bottom: 20px; padding: 16px 18px; border-radius: 14px; font-size: 15px; }
        .msg.sucesso { background: #e8f7ef; color: #1f7a47; }
        .msg.erro { background: #ffe8e8; color: #922d2d; }
        .category-item form { margin: 0; }
        .category-actions { display: flex; align-items: center; gap: 8px; }
        .edit-btn, .delete-btn { min-height: 44px; padding: 10px 14px; color: white; text-decoration: none; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; }
        .edit-btn { background: #4f5fc9; }
        .edit-btn:hover { background: #3f4fae; }
        .delete-btn:hover { background: #a92733; }
        .delete-btn { background: #c93643; }
        .edit-btn:focus-visible, .delete-btn:focus-visible, .modal-close:focus-visible, .modal-save:focus-visible { outline: 3px solid #293b91; outline-offset: 2px; }
        .empty-categories { padding: 18px; border: 1px dashed #cdd3e8; border-radius: 12px; background: white; color: #666; line-height: 1.5; }
        .category-modal { display: none; position: fixed; inset: 0; z-index: 1000; align-items: center; justify-content: center; padding: 20px; overflow-y: auto; background: rgba(25, 28, 45, 0.62); }
        .category-modal.active { display: flex; }
        .modal-content { width: min(540px, 100%); max-height: calc(100dvh - 40px); overflow-y: auto; padding: 26px; border-radius: 18px; background: white; box-shadow: 0 24px 70px rgba(0, 0, 0, 0.28); }
        .modal-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
        .modal-header h2 { margin: 0; color: #333; font-size: 23px; }
        .modal-close { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 44px; width: 44px; height: 44px; padding: 0; border: none; background: transparent; color: #666; font-size: 30px; line-height: 1; cursor: pointer; }
        .modal-close:hover { color: #222; }
        .modal-form { display: grid; gap: 16px; }
        .modal-help { padding: 12px 14px; border-radius: 10px; background: #fff7df; color: #795b00; font-size: 13px; line-height: 1.5; }
        .modal-save { min-height: 44px; padding: 12px 18px; border: none; border-radius: 10px; background: #4f5fc9; color: white; font-size: 15px; font-weight: 700; cursor: pointer; }
        .modal-save:hover { background: #3f4fae; }
        @media (max-width: 840px) {
            .category-lists { grid-template-columns: 1fr; }
        }
        @media (max-width: 640px) {
            .actions { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); width: 100%; gap: 8px; }
            .actions a:last-child { grid-column: 1 / -1; }
            .category-item { grid-template-columns: auto minmax(0, 1fr); gap: 12px; }
            .category-actions { grid-column: 1 / -1; display: grid; grid-template-columns: 1fr 1fr; }
            .category-actions form, .category-actions button { width: 100%; }
        }
        @media (max-width: 480px) {
            body { padding: 0; }
            .container { min-height: 100vh; min-height: 100dvh; border-radius: 0; box-shadow: none; }
            .form-card, .list-card { padding: 18px; }
            .form-row { grid-template-columns: 1fr; }
            .actions a.button { padding-inline: 10px; }
            .modal-content { padding: 22px 18px; }
        }
    </style>
    <?php require dirname(__DIR__) . '/includes/responsive_styles.php'; ?>
</head>
<body>
    <main class="container">
        <h1>Categorias</h1>
        <p class="subtitle">Crie e gerencie as categorias para suas receitas e despesas.</p>

        <div class="top-row">
            <nav class="actions" aria-label="Navegação principal">
                <a href="/dashboard" class="button">Dashboard</a>
                <a href="/movimentacoes" class="button">Movimentações</a>
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
                                    <div class="color-box" style="background-color: <?= htmlspecialchars($cat['cor'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></div>
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
