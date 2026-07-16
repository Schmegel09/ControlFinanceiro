<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/Core/proteger.php';

/** @var string $evolucaoFinanceiraJson */
/** @var string $despesasPorCategoriaJson */

if (!isset($evolucaoFinanceiraJson, $despesasPorCategoriaJson)) {
    throw new RuntimeException('Dados dos gráficos do Dashboard não foram informados.');
}
?>

<script>
    (() => {
        const evolutionData = <?= $evolucaoFinanceiraJson ?>;
        const categoryData = <?= $despesasPorCategoriaJson ?>;
        const moneyCompact = new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL',
            notation: 'compact',
            maximumFractionDigits: 1
        });

        function prepareCanvas(canvas, fallbackHeight) {
            const box = canvas.getBoundingClientRect();
            const width = Math.max(220, box.width || canvas.parentElement.clientWidth);
            const height = Math.max(180, box.height || fallbackHeight);
            const scale = Math.min(window.devicePixelRatio || 1, 2);
            canvas.width = Math.round(width * scale);
            canvas.height = Math.round(height * scale);
            const context = canvas.getContext('2d');
            context.setTransform(scale, 0, 0, scale, 0, 0);
            return { context, width, height };
        }

        function drawEvolution() {
            const canvas = document.getElementById('financial-evolution-chart');
            if (!canvas || !Array.isArray(evolutionData) || evolutionData.length === 0) return;

            const { context, width, height } = prepareCanvas(canvas, 260);
            const padding = { top: 18, right: 16, bottom: 38, left: width < 430 ? 48 : 62 };
            const plotWidth = width - padding.left - padding.right;
            const plotHeight = height - padding.top - padding.bottom;
            const maximum = Math.max(
                1,
                ...evolutionData.flatMap(item => [Number(item.receitas), Number(item.despesas)])
            );

            context.clearRect(0, 0, width, height);
            context.font = '11px Segoe UI, sans-serif';
            context.textBaseline = 'middle';

            for (let index = 0; index <= 4; index++) {
                const y = padding.top + (plotHeight * index / 4);
                const value = maximum * (1 - index / 4);
                context.beginPath();
                context.strokeStyle = '#dde2f1';
                context.lineWidth = 1;
                context.moveTo(padding.left, y);
                context.lineTo(width - padding.right, y);
                context.stroke();
                context.fillStyle = '#777';
                context.textAlign = 'right';
                context.fillText(moneyCompact.format(value), padding.left - 8, y);
            }

            const xFor = (index) => evolutionData.length === 1
                ? padding.left + plotWidth / 2
                : padding.left + (plotWidth * index / (evolutionData.length - 1));
            const yFor = (value) => padding.top + plotHeight - (Number(value) / maximum * plotHeight);
            const labelStep = Math.max(1, Math.ceil(evolutionData.length / (width < 520 ? 4 : 7)));

            evolutionData.forEach((item, index) => {
                if (index % labelStep !== 0 && index !== evolutionData.length - 1) return;
                context.fillStyle = '#777';
                context.textAlign = 'center';
                context.textBaseline = 'top';
                context.fillText(item.rotulo, xFor(index), height - padding.bottom + 12);
            });

            [
                { key: 'receitas', color: '#209f71' },
                { key: 'despesas', color: '#ef5f5f' }
            ].forEach((series) => {
                context.beginPath();
                context.strokeStyle = series.color;
                context.lineWidth = 3;
                context.lineJoin = 'round';
                context.lineCap = 'round';
                evolutionData.forEach((item, index) => {
                    const x = xFor(index);
                    const y = yFor(item[series.key]);
                    index === 0 ? context.moveTo(x, y) : context.lineTo(x, y);
                });
                context.stroke();

                evolutionData.forEach((item, index) => {
                    context.beginPath();
                    context.fillStyle = '#fff';
                    context.strokeStyle = series.color;
                    context.lineWidth = 2;
                    context.arc(
                        xFor(index),
                        yFor(item[series.key]),
                        evolutionData.length > 20 ? 2.5 : 4,
                        0,
                        Math.PI * 2
                    );
                    context.fill();
                    context.stroke();
                });
            });
        }

        function drawCategories() {
            const canvas = document.getElementById('expense-category-chart');
            if (!canvas || !Array.isArray(categoryData) || categoryData.length === 0) return;

            const { context, width, height } = prepareCanvas(canvas, 190);
            const total = categoryData.reduce((sum, item) => sum + Number(item.total), 0);
            const centerX = width / 2;
            const centerY = height / 2;
            const radius = Math.max(40, Math.min(width, height) / 2 - 8);
            let start = -Math.PI / 2;

            context.clearRect(0, 0, width, height);
            categoryData.forEach((item) => {
                const angle = total > 0 ? Number(item.total) / total * Math.PI * 2 : 0;
                context.beginPath();
                context.strokeStyle = item.cor;
                context.lineWidth = Math.max(20, radius * .34);
                context.arc(centerX, centerY, radius * .77, start, start + angle);
                context.stroke();
                start += angle;
            });
        }

        const renderCharts = () => {
            drawEvolution();
            drawCategories();
        };
        let resizeTimer;
        window.addEventListener('resize', () => {
            window.clearTimeout(resizeTimer);
            resizeTimer = window.setTimeout(renderCharts, 120);
        });
        renderCharts();
    })();
</script>
