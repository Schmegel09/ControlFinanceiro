<?php

declare(strict_types=1);

if (!defined('MAX_PARCELAS_TRANSACAO')) {
    throw new RuntimeException('O limite de parcelas não foi definido.');
}
?>

<script>
    const novaMovimentacaoTipo = document.getElementById('nova-tipo');
    const novaMovimentacaoCategoria = document.getElementById('nova-categoria');
    const novaMovimentacaoCategoriaAjuda = document.getElementById('nova-categoria-ajuda');
    const categoriasNovaMovimentacaoData = Array.from(
        novaMovimentacaoCategoria.querySelectorAll('option[data-tipo]')
    ).map(opcao => ({
        id: opcao.value,
        tipo: opcao.dataset.tipo,
        nome: opcao.dataset.nome || opcao.textContent,
    }));
    const novoValor = document.getElementById('novo-valor');
    const novasParcelas = document.getElementById('novas-parcelas');
    const parcelasGroup = document.getElementById('parcelas-group');
    const parcelasPreview = document.getElementById('parcelas-preview');

    function obterValorPreview(valorInformado) {
        const valor = valorInformado.replace(/R\$/gi, '').replace(/\s/g, '');

        if (!valor) {
            return null;
        }

        const ultimaVirgula = valor.lastIndexOf(',');
        const ultimoPonto = valor.lastIndexOf('.');
        let normalizado = valor;

        if (ultimaVirgula >= 0 && ultimoPonto >= 0) {
            const separadorDecimal = ultimaVirgula > ultimoPonto ? ',' : '.';
            const separadorMilhar = separadorDecimal === ',' ? '.' : ',';
            normalizado = valor.split(separadorMilhar).join('').replace(separadorDecimal, '.');
        } else if (ultimaVirgula >= 0) {
            const casasDecimais = valor.length - ultimaVirgula - 1;
            normalizado = casasDecimais <= 2
                ? valor.replace(',', '.')
                : valor.replace(/,/g, '');
        } else if (ultimoPonto >= 0) {
            const casasDecimais = valor.length - ultimoPonto - 1;
            normalizado = casasDecimais <= 2
                ? valor
                : valor.replace(/\./g, '');
        }

        if (!/^\d+(?:\.\d{1,2})?$/.test(normalizado)) {
            return null;
        }

        const numero = Number(normalizado);
        return Number.isFinite(numero) && numero > 0 ? numero : null;
    }

    function formatarReal(valorCentavos) {
        return (valorCentavos / 100).toLocaleString('pt-BR', {
            style: 'currency',
            currency: 'BRL',
        });
    }

    function atualizarPreviewParcelas() {
        if (novaMovimentacaoTipo.value !== 'despesa') {
            return;
        }

        const valor = obterValorPreview(novoValor.value);
        const quantidade = Number(novasParcelas.value);
        parcelasPreview.classList.remove('error');

        if (valor === null) {
            parcelasPreview.textContent = 'Informe um valor total válido para visualizar a divisão das parcelas.';
            return;
        }

        if (!Number.isInteger(quantidade) || quantidade < 1 || quantidade > <?= MAX_PARCELAS_TRANSACAO ?>) {
            parcelasPreview.textContent = 'Informe uma quantidade entre 1 e <?= MAX_PARCELAS_TRANSACAO ?> parcelas.';
            parcelasPreview.classList.add('error');
            return;
        }

        const totalCentavos = Math.round(valor * 100);
        if (totalCentavos < quantidade) {
            parcelasPreview.textContent = 'O valor total precisa permitir pelo menos R$ 0,01 por parcela.';
            parcelasPreview.classList.add('error');
            return;
        }

        if (quantidade === 1) {
            parcelasPreview.innerHTML = `<strong>Pagamento à vista:</strong> ${formatarReal(totalCentavos)}.`;
            return;
        }

        const valorBase = Math.floor(totalCentavos / quantidade);
        const parcelasComAcrescimo = totalCentavos % quantidade;

        if (parcelasComAcrescimo === 0) {
            parcelasPreview.innerHTML = `<strong>${quantidade} parcelas</strong> de ${formatarReal(valorBase)}, uma por mês.`;
            return;
        }

        const parcelasBase = quantidade - parcelasComAcrescimo;
        const partes = [
            `${parcelasComAcrescimo} ${parcelasComAcrescimo === 1 ? 'parcela' : 'parcelas'} de ${formatarReal(valorBase + 1)}`,
        ];

        if (parcelasBase > 0) {
            partes.push(`${parcelasBase} ${parcelasBase === 1 ? 'parcela' : 'parcelas'} de ${formatarReal(valorBase)}`);
        }

        parcelasPreview.innerHTML = `<strong>${quantidade} parcelas:</strong> ${partes.join(' e ')}, uma por mês.`;
    }

    function atualizarCamposParcelamento() {
        const permiteParcelamento = novaMovimentacaoTipo.value === 'despesa';

        parcelasGroup.hidden = !permiteParcelamento;
        parcelasPreview.hidden = !permiteParcelamento;
        novasParcelas.disabled = !permiteParcelamento;

        if (!permiteParcelamento) {
            novasParcelas.value = '1';
            parcelasPreview.classList.remove('error');
            parcelasPreview.textContent = 'Informe o valor total para visualizar a divisão das parcelas.';
            return;
        }

        atualizarPreviewParcelas();
    }

    function atualizarCategoriasNovaMovimentacao() {
        novaMovimentacaoCategoria.innerHTML = '<option value="">Sem categoria</option>';
        const categoriasDoTipo = categoriasNovaMovimentacaoData
            .filter(categoria => categoria.tipo === novaMovimentacaoTipo.value)
            .sort((categoriaA, categoriaB) => categoriaA.nome.localeCompare(categoriaB.nome, 'pt-BR'));

        categoriasDoTipo.forEach(categoria => {
            const opcao = document.createElement('option');
            opcao.value = categoria.id;
            opcao.textContent = categoria.nome;
            novaMovimentacaoCategoria.appendChild(opcao);
        });

        const nomeTipo = novaMovimentacaoTipo.value === 'receita' ? 'receita' : 'despesa';
        novaMovimentacaoCategoriaAjuda.firstChild.textContent = categoriasDoTipo.length > 0
            ? `${categoriasDoTipo.length} ${categoriasDoTipo.length === 1 ? 'categoria disponível' : 'categorias disponíveis'} para ${nomeTipo}. `
            : `Nenhuma categoria de ${nomeTipo} cadastrada. `;
    }

    novaMovimentacaoTipo.addEventListener('change', atualizarCategoriasNovaMovimentacao);
    novaMovimentacaoTipo.addEventListener('change', atualizarCamposParcelamento);
    novoValor.addEventListener('input', atualizarPreviewParcelas);
    novasParcelas.addEventListener('input', atualizarPreviewParcelas);
    atualizarCategoriasNovaMovimentacao();
    atualizarCamposParcelamento();
</script>
