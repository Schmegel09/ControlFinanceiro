<?php

declare(strict_types=1);

if (!isset($edicaoAction, $csrfToken, $transacoesJson, $categoriasJson)) {
    throw new RuntimeException('Dados do modal de edição não foram informados.');
}
?>

<div
    id="tx-edit-modal"
    class="tx-modal"
    role="dialog"
    aria-modal="true"
    aria-labelledby="tx-edit-title"
    aria-describedby="tx-installment-note"
    aria-hidden="true"
>
    <div class="tx-modal-content">
        <div class="tx-modal-header">
            <h2 id="tx-edit-title">Editar movimentação</h2>
            <button type="button" class="tx-close-btn" aria-label="Fechar edição" onclick="fecharEdicao()">&times;</button>
        </div>

        <form method="post" action="<?= htmlspecialchars($edicaoAction, ENT_QUOTES, 'UTF-8') ?>" id="tx-edit-form">
            <input type="hidden" name="acao" value="editar">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="id" id="tx-edit-id">

            <div class="tx-form-grid">
                <div class="tx-form-group">
                    <label for="tx-edit-tipo">Tipo</label>
                    <select id="tx-edit-tipo" name="tipo" required>
                        <option value="receita">Receita</option>
                        <option value="despesa">Despesa</option>
                    </select>
                </div>

                <div class="tx-form-group">
                    <label for="tx-edit-data">Data</label>
                    <input type="date" id="tx-edit-data" name="data" required>
                </div>

                <div class="tx-form-group full">
                    <label for="tx-edit-categoria">Categoria</label>
                    <select id="tx-edit-categoria" name="categoria_id">
                        <option value="">Sem categoria</option>
                    </select>
                </div>

                <div class="tx-form-group">
                    <label for="tx-edit-valor">Valor (R$)</label>
                    <input type="text" id="tx-edit-valor" name="valor" inputmode="decimal" required>
                </div>

                <div class="tx-form-group full">
                    <label for="tx-edit-descricao">Descrição (opcional)</label>
                    <textarea id="tx-edit-descricao" name="descricao" maxlength="255"></textarea>
                </div>
            </div>

            <p id="tx-installment-note" class="tx-installment-note"></p>
            <button type="submit" class="tx-save-btn">Salvar alterações</button>
        </form>
    </div>
</div>

<script>
    const transacoesEdicaoData = <?= $transacoesJson ?>;
    const categoriasEdicaoData = <?= $categoriasJson ?>;
    const txModal = document.getElementById('tx-edit-modal');
    const txTipo = document.getElementById('tx-edit-tipo');
    const txCategoria = document.getElementById('tx-edit-categoria');
    const txConteudoPagina = document.querySelector('.container');
    let txBotaoOrigem = null;
    let txCategoriaSelecionada = '';
    let txCategoriaLegada = '';

    function preencherCategoriasEdicao(categoriaSelecionada = '', categoriaLegada = '') {
        txCategoria.innerHTML = '';

        if (categoriaLegada) {
            const opcaoLegada = document.createElement('option');
            opcaoLegada.value = '__legacy__';
            opcaoLegada.textContent = `Manter categoria atual: ${categoriaLegada}`;
            txCategoria.appendChild(opcaoLegada);
        }

        const semCategoria = document.createElement('option');
        semCategoria.value = '';
        semCategoria.textContent = 'Sem categoria';
        txCategoria.appendChild(semCategoria);

        categoriasEdicaoData
            .filter(categoria => categoria.tipo === txTipo.value)
            .forEach(categoria => {
                const opcao = document.createElement('option');
                opcao.value = categoria.id;
                opcao.textContent = categoria.nome;
                txCategoria.appendChild(opcao);
            });

        txCategoria.value = categoriaLegada ? '__legacy__' : String(categoriaSelecionada || '');
    }

    function abrirEdicao(id, botao = null) {
        const transacao = transacoesEdicaoData.find(item => String(item.id) === String(id));

        if (!transacao) {
            window.alert('Não foi possível localizar esta movimentação. Atualize a página e tente novamente.');
            return;
        }

        txBotaoOrigem = botao;
        const categoriaGerenciada = categoriasEdicaoData.some(categoria =>
            String(categoria.id) === String(transacao.categoria_id)
            && categoria.tipo === transacao.tipo
        );
        txCategoriaSelecionada = categoriaGerenciada ? transacao.categoria_id : '';
        txCategoriaLegada = !categoriaGerenciada
            && transacao.categoria
            && transacao.categoria !== 'Sem categoria'
            ? transacao.categoria
            : '';

        document.getElementById('tx-edit-id').value = transacao.id;
        txTipo.value = transacao.tipo;
        document.getElementById('tx-edit-data').value = transacao.data;
        document.getElementById('tx-edit-valor').value = String(transacao.valor).replace('.', ',');
        document.getElementById('tx-edit-descricao').value = transacao.descricao || '';
        preencherCategoriasEdicao(txCategoriaSelecionada, txCategoriaLegada);

        const parcela = Number(transacao.numero_parcela || 1);
        const totalParcelas = Number(transacao.total_parcelas || 1);
        const avisoParcela = document.getElementById('tx-installment-note');

        if (totalParcelas > 1) {
            avisoParcela.textContent = `Você está editando somente a parcela ${parcela} de ${totalParcelas}. As demais parcelas permanecerão como estão.`;
            avisoParcela.classList.add('visible');
        } else {
            avisoParcela.textContent = '';
            avisoParcela.classList.remove('visible');
        }

        txModal.classList.add('active');
        txModal.setAttribute('aria-hidden', 'false');
        if (txConteudoPagina && 'inert' in txConteudoPagina) {
            txConteudoPagina.inert = true;
        }
        document.body.style.overflow = 'hidden';
        txTipo.focus();
    }

    function fecharEdicao() {
        txModal.classList.remove('active');
        txModal.setAttribute('aria-hidden', 'true');
        if (txConteudoPagina && 'inert' in txConteudoPagina) {
            txConteudoPagina.inert = false;
        }
        document.body.style.overflow = '';

        if (txBotaoOrigem) {
            txBotaoOrigem.focus();
        }
    }

    txTipo.addEventListener('change', function () {
        txCategoriaSelecionada = '';
        txCategoriaLegada = '';
        preencherCategoriasEdicao();
    });

    txModal.addEventListener('click', function (event) {
        if (event.target === txModal) {
            fecharEdicao();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (!txModal.classList.contains('active')) {
            return;
        }

        if (event.key === 'Escape') {
            fecharEdicao();
            return;
        }

        if (event.key === 'Tab') {
            const elementosFocaveis = Array.from(txModal.querySelectorAll(
                'button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [href], [tabindex]:not([tabindex="-1"])'
            ));
            const primeiroElemento = elementosFocaveis[0];
            const ultimoElemento = elementosFocaveis[elementosFocaveis.length - 1];

            if (!primeiroElemento || !ultimoElemento) {
                event.preventDefault();
            } else if (event.shiftKey && document.activeElement === primeiroElemento) {
                event.preventDefault();
                ultimoElemento.focus();
            } else if (!event.shiftKey && document.activeElement === ultimoElemento) {
                event.preventDefault();
                primeiroElemento.focus();
            }
        }
    });
</script>
