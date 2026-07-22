<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/Core/proteger.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movimentações - Controle Financeiro</title>
    <link rel="stylesheet" href="/assets/css/base.css">
    <link rel="stylesheet" href="/assets/css/pages/movimentacoes.css">
    <link rel="stylesheet" href="/assets/css/components.css">
</head>
<body>
    <main class="container">
        <h1>Movimentações</h1>
        <p class="subtitle">Visualize e gerencie todas as suas transações.</p>

        <?php require dirname(__DIR__) . '/partials/carteira_switcher.php'; ?>

        <div class="top-row">
            <nav class="actions" aria-label="Navegação principal">
                <?php if (telaClientePermitida($permissoesCliente ?? [], 'dashboard')): ?>
                    <a href="/dashboard" class="button">Dashboard</a>
                <?php endif; ?>
                <?php if (telaClientePermitida($permissoesCliente ?? [], 'categorias')): ?>
                    <a href="/categorias" class="button">Categorias</a>
                <?php endif; ?>
                <a href="/logout" class="button">Sair</a>
            </nav>
        </div>

        <?php if ($mensagem !== ''): ?>
            <div class="msg <?= $tipoMensagem === 'erro' ? 'erro' : 'sucesso' ?>" role="<?= $tipoMensagem === 'erro' ? 'alert' : 'status' ?>" aria-live="<?= $tipoMensagem === 'erro' ? 'assertive' : 'polite' ?>">
                <?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <section class="new-transaction-card" aria-labelledby="nova-movimentacao-titulo">
            <h2 id="nova-movimentacao-titulo">Registrar nova movimentação</h2>
            <p class="form-help">Cadastre receitas e despesas também por esta aba. Em caso de parcelamento, uma movimentação será criada para cada mês.</p>

            <form method="post" action="<?= htmlspecialchars($movimentacoesAction, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="acao" value="adicionar_transacao">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                <div class="transaction-form-grid">
                    <div class="form-group">
                        <label for="nova-tipo">Tipo</label>
                        <select id="nova-tipo" name="tipo" required>
                            <option value="receita">Receita</option>
                            <option value="despesa">Despesa</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="nova-categoria">Categoria</label>
                        <select id="nova-categoria" name="categoria_id" aria-describedby="nova-categoria-ajuda">
                            <option value="">Sem categoria</option>
                            <?php foreach ($categorias as $categoria): ?>
                                <option
                                    value="<?= (int) $categoria['id'] ?>"
                                    data-tipo="<?= htmlspecialchars($categoria['tipo'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                                    data-nome="<?= htmlspecialchars($categoria['nome'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                                ><?= htmlspecialchars($categoria['nome'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small id="nova-categoria-ajuda" class="field-help">
                            As categorias são filtradas conforme o tipo selecionado.
                            <?php if (telaClientePermitida($permissoesCliente ?? [], 'categorias')): ?>
                                <a href="/categorias">Gerenciar categorias</a>
                            <?php endif; ?>
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="novo-valor">Valor total (R$)</label>
                        <input type="text" id="novo-valor" name="valor" inputmode="decimal" placeholder="1500,00" required>
                    </div>

                    <div class="form-group" id="parcelas-group" hidden>
                        <label for="novas-parcelas">Quantidade de parcelas</label>
                        <input
                            type="number"
                            id="novas-parcelas"
                            name="parcelas"
                            min="1"
                            max="<?= MAX_PARCELAS_TRANSACAO ?>"
                            step="1"
                            value="1"
                            inputmode="numeric"
                            aria-describedby="parcelas-ajuda parcelas-preview"
                            disabled
                            required
                        >
                        <small id="parcelas-ajuda" class="field-help">Digite de 1 a <?= MAX_PARCELAS_TRANSACAO ?> parcelas.</small>
                    </div>

                    <div class="form-group">
                        <label for="nova-data">Data</label>
                        <input type="date" id="nova-data" name="data" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="form-group description-field">
                        <label for="nova-descricao">Descrição (opcional)</label>
                        <textarea id="nova-descricao" name="descricao" maxlength="255" placeholder="Observações sobre esta movimentação"></textarea>
                    </div>
                </div>

                <p id="parcelas-preview" class="installment-preview" aria-live="polite" hidden>
                    Informe o valor total para visualizar a divisão das parcelas.
                </p>

                <button type="submit" class="button new-transaction-submit">Salvar movimentação</button>
            </form>
        </section>

        <?php require dirname(__DIR__) . '/partials/movimentacao_form_script.php'; ?>

        <section class="csv-import-card" aria-labelledby="importar-csv-titulo">
            <div class="csv-import-header">
                <h2 id="importar-csv-titulo">Importar movimentações por CSV</h2>
                <a
                    href="/movimentacoes?download=modelo_csv"
                    class="csv-template-button"
                    download="modelo_movimentacoes.csv"
                >Baixar modelo CSV</a>
            </div>
            <p class="form-help">
                Aceita colunas separadas por ponto e vírgula ou vírgula, valores como
                <strong>1.234,56</strong> e datas como <strong>16/07/2026</strong>.
                As colunas obrigatórias são Data e Valor; Tipo pode ser informado ou identificado pelo sinal negativo.
                No modelo baixado, substitua ou remova as linhas de exemplo antes de importar.
            </p>

            <form
                method="post"
                action="<?= htmlspecialchars($movimentacoesAction, ENT_QUOTES, 'UTF-8') ?>"
                enctype="multipart/form-data"
                class="csv-import-form"
            >
                <input type="hidden" name="acao" value="importar_csv">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-group">
                    <label for="arquivo-csv">Arquivo CSV ou TXT</label>
                    <input
                        type="file"
                        id="arquivo-csv"
                        name="arquivo_csv"
                        accept=".csv,.txt,text/csv,text/plain"
                        required
                        aria-describedby="arquivo-csv-ajuda"
                    >
                    <small id="arquivo-csv-ajuda" class="field-help">
                        Até 5 MB e 1.000 linhas. Categorias que ainda não existem serão criadas automaticamente.
                    </small>
                </div>

                <button type="submit">Importar CSV</button>
            </form>

            <div class="csv-example" aria-label="Exemplo de cabeçalho CSV">
                data;tipo;valor;categoria;descricao;parcelas
            </div>
        </section>

        <div class="stats">
            <div class="stat-card receita">
                <h2>Total de Receitas</h2>
                <div class="stat-value">R$ <?= number_format($totalReceitas, 2, ',', '.') ?></div>
            </div>
            <div class="stat-card despesa">
                <h2>Total de Despesas</h2>
                <div class="stat-value">R$ <?= number_format($totalDespesas, 2, ',', '.') ?></div>
            </div>
            <div class="stat-card">
                <h2>Saldo do Período</h2>
                <div class="stat-value">R$ <?= number_format($saldo, 2, ',', '.') ?></div>
            </div>
        </div>

        <div class="filters">
            <h2>Filtros</h2>
            <form method="get" action="/movimentacoes">
                <div class="filter-row">
                    <div class="form-group">
                        <label for="inicio">Data Inicial</label>
                        <input type="date" id="inicio" name="inicio" value="<?= htmlspecialchars($filtroDataInicio, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="form-group">
                        <label for="fim">Data Final</label>
                        <input type="date" id="fim" name="fim" value="<?= htmlspecialchars($filtroDataFim, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="form-group">
                        <label for="filtro-tipo">Tipo</label>
                        <select id="filtro-tipo" name="tipo">
                            <option value="">Todos</option>
                            <option value="receita" <?= $filtroTipo === 'receita' ? 'selected' : '' ?>>Receitas</option>
                            <option value="despesa" <?= $filtroTipo === 'despesa' ? 'selected' : '' ?>>Despesas</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="button">Filtrar</button>
            </form>
        </div>

        <?php if ($totalTransacoesCarteira > 0): ?>
            <section class="bulk-actions" aria-labelledby="excluir-todas-titulo">
                <div>
                    <h2 id="excluir-todas-titulo">Excluir todos os lançamentos</h2>
                    <p>Esta opção apagará permanentemente <?= $totalTransacoesCarteira ?> <?= $totalTransacoesCarteira === 1 ? 'lançamento' : 'lançamentos' ?> da carteira em uso, inclusive os que não aparecem no filtro atual. As categorias serão mantidas.</p>
                </div>
                <form
                    method="post"
                    action="<?= htmlspecialchars($movimentacoesAction, ENT_QUOTES, 'UTF-8') ?>"
                    class="delete-all-form"
                    onsubmit="return confirm('Esta ação não pode ser desfeita. Deseja excluir permanentemente os <?= $totalTransacoesCarteira ?> lançamentos da carteira em uso?')"
                >
                    <input type="hidden" name="acao" value="deletar_todas">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="delete-all-button">Excluir tudo</button>
                </form>
            </section>
        <?php endif; ?>

        <h2 id="lista-movimentacoes-titulo" class="sr-only">Lista de movimentações</h2>
        <?php if (count($transacoes) === 0): ?>
            <div class="no-data">
                <p>Nenhuma transação encontrada para este período.</p>
            </div>
        <?php else: ?>
            <div class="transactions-container" role="region" aria-labelledby="lista-movimentacoes-titulo" tabindex="0">
                <table class="transactions-table" role="table">
                    <caption class="sr-only">Movimentações encontradas no período selecionado</caption>
                    <thead role="rowgroup">
                        <tr role="row">
                            <th scope="col" role="columnheader">Data</th>
                            <th scope="col" role="columnheader">Tipo</th>
                            <th scope="col" role="columnheader">Categoria</th>
                            <th scope="col" role="columnheader">Descrição</th>
                            <th scope="col" role="columnheader">Valor</th>
                            <th scope="col" role="columnheader">Parcela</th>
                            <th scope="col" role="columnheader">Registrado por</th>
                            <th scope="col" role="columnheader">Ações</th>
                        </tr>
                    </thead>
                    <tbody role="rowgroup">
                        <?php foreach ($transacoes as $t): ?>
                            <?php
                            $totalParcelas = max(1, (int) ($t['total_parcelas'] ?? 1));
                            $numeroParcela = max(1, (int) ($t['numero_parcela'] ?? 1));
                            $parcelaExibida = $totalParcelas > 1
                                ? "{$numeroParcela}/{$totalParcelas}"
                                : '-';
                            $confirmacaoExclusao = $totalParcelas > 1
                                ? sprintf(
                                    'Excluir somente a parcela %d de %d? As demais parcelas permanecerão cadastradas.',
                                    $numeroParcela,
                                    $totalParcelas
                                )
                                : 'Deseja realmente excluir esta movimentação?';
                            ?>
                            <tr role="row">
                                <td data-label="Data" role="cell"><?= date('d/m/Y', strtotime($t['data'])) ?></td>
                                <td data-label="Tipo" role="cell">
                                    <span class="type-badge <?= $t['tipo'] ?>">
                                        <?= $t['tipo'] === 'receita' ? 'Receita' : 'Despesa' ?>
                                    </span>
                                </td>
                                <td data-label="Categoria" role="cell">
                                    <?php if ($t['categoria']): ?>
                                        <span class="tx-category">
                                            <span class="color-dot" style="--category-color: <?= htmlspecialchars($t['cor'], ENT_QUOTES, 'UTF-8') ?>"></span>
                                            <span><?= htmlspecialchars($t['categoria'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </span>
                                    <?php else: ?>
                                        <span class="tx-category tx-category-muted">Sem categoria</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Descrição" role="cell"><?= htmlspecialchars($t['descricao'] ?: '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td data-label="Valor" role="cell" class="transaction-value <?= htmlspecialchars($t['tipo'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= $t['tipo'] === 'receita' ? '+' : '-' ?> R$ <?= number_format((float) $t['valor'], 2, ',', '.') ?>
                                </td>
                                <td data-label="Parcela" role="cell"><span class="installment-cell"><?= htmlspecialchars($parcelaExibida, ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td data-label="Registrado por" role="cell"><?= htmlspecialchars($t['registrado_por'] ?: '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td data-label="Ações" role="cell">
                                    <div class="tx-actions">
                                        <button
                                            type="button"
                                            class="tx-edit-btn"
                                            aria-label="Editar movimentação de <?= date('d/m/Y', strtotime($t['data'])) ?>: <?= htmlspecialchars($t['descricao'] ?: $t['categoria'], ENT_QUOTES, 'UTF-8') ?>"
                                            onclick="abrirEdicao(<?= (int) $t['id'] ?>, this)"
                                        >Editar</button>
                                        <form method="post" action="<?= htmlspecialchars($movimentacoesAction, ENT_QUOTES, 'UTF-8') ?>" class="tx-delete-form" onsubmit="return confirm('<?= htmlspecialchars($confirmacaoExclusao, ENT_QUOTES, 'UTF-8') ?>')">
                                            <input type="hidden" name="acao" value="deletar">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                            <button
                                                type="submit"
                                                class="tx-delete-btn"
                                                aria-label="Excluir movimentação de <?= date('d/m/Y', strtotime($t['data'])) ?>: <?= htmlspecialchars($t['descricao'] ?: $t['categoria'], ENT_QUOTES, 'UTF-8') ?>"
                                            >Excluir</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>

    <?php
    $edicaoAction = $movimentacoesAction;
    require dirname(__DIR__) . '/partials/transacao_edit_modal.php';
    ?>

</body>
</html>
