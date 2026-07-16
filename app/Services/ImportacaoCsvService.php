<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Core/proteger.php';
require_once __DIR__ . '/TransacaoService.php';
require_once dirname(__DIR__) . '/Models/CategoriaModel.php';
require_once dirname(__DIR__) . '/Models/TransacaoModel.php';

const TAMANHO_MAXIMO_CSV = 5 * 1024 * 1024;
const MAXIMO_LINHAS_CSV = 1000;

function gerarModeloImportacaoCsv(): string
{
    $stream = fopen('php://temp', 'w+b');
    if ($stream === false) {
        throw new RuntimeException('Não foi possível gerar o modelo CSV.');
    }

    try {
        // BOM UTF-8 melhora o reconhecimento de acentos ao abrir o arquivo no Excel.
        fwrite($stream, "\xEF\xBB\xBF");

        $linhas = [
            ['data', 'tipo', 'valor', 'categoria', 'descricao', 'parcelas'],
            ['16/07/2026', 'despesa', '1234,56', 'Mercado', 'Compra do mês', '1'],
            ['17/07/2026', 'receita', '2500,00', 'Salário', 'Pagamento mensal', '1'],
            ['18/07/2026', 'despesa', '1200,00', 'Eletrônicos', 'Notebook parcelado', '12'],
        ];

        foreach ($linhas as $linha) {
            fputcsv($stream, $linha, ';', '"', '');
        }

        rewind($stream);
        $conteudo = stream_get_contents($stream);

        if (!is_string($conteudo)) {
            throw new RuntimeException('Não foi possível finalizar o modelo CSV.');
        }

        return $conteudo;
    } finally {
        fclose($stream);
    }
}

function normalizarTextoCsv(string $texto): string
{
    $texto = trim($texto);

    if ($texto === '' || preg_match('//u', $texto) === 1) {
        return $texto;
    }

    if (!function_exists('iconv')) {
        return $texto;
    }

    $convertido = iconv('Windows-1252', 'UTF-8//IGNORE', $texto);
    return is_string($convertido) ? trim($convertido) : $texto;
}

function normalizarCabecalhoCsv(string $cabecalho): string
{
    $cabecalho = normalizarTextoCsv(ltrim($cabecalho, "\xEF\xBB\xBF"));
    $semAcentos = function_exists('iconv')
        ? iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $cabecalho)
        : false;
    $cabecalho = is_string($semAcentos) ? $semAcentos : $cabecalho;
    $cabecalho = strtolower(trim($cabecalho));

    return trim((string) preg_replace('/[^a-z0-9]+/', '_', $cabecalho), '_');
}

/**
 * @param array<int, string> $cabecalhos
 * @param array<int, string> $alternativas
 */
function localizarColunaCsv(array $cabecalhos, array $alternativas): ?int
{
    foreach ($alternativas as $alternativa) {
        $indice = array_search($alternativa, $cabecalhos, true);
        if ($indice !== false) {
            return (int) $indice;
        }
    }

    return null;
}

function detectarDelimitadorCsv(string $primeiraLinha): string
{
    // No padrão brasileiro o ponto e vírgula separa as colunas e a vírgula
    // permanece disponível para os centavos (ex.: 1.234,56).
    return substr_count($primeiraLinha, ';') > 0 ? ';' : ',';
}

/**
 * Tolera também um CSV separado por vírgula cujo valor brasileiro não veio
 * entre aspas, por exemplo: 16/07/2026,despesa,1.234,56,Mercado.
 * Nesse caso as duas partes numéricas são reunidas antes do processamento.
 *
 * @param array<int, string|null> $linha
 * @return array<int, string|null>
 */
function corrigirValorDecimalCsv(
    array $linha,
    string $delimitador,
    int $colunaValor,
    int $totalCabecalhos
): array {
    if ($delimitador !== ',' || count($linha) !== $totalCabecalhos + 1) {
        return $linha;
    }

    $parteInteira = trim((string) ($linha[$colunaValor] ?? ''));
    $parteDecimal = trim((string) ($linha[$colunaValor + 1] ?? ''));

    if (
        preg_match('/^-?(?:R\$\s*)?\d{1,3}(?:\.\d{3})*$|^-?\d+$/', $parteInteira) !== 1
        || preg_match('/^\d{1,2}$/', $parteDecimal) !== 1
    ) {
        return $linha;
    }

    $linha[$colunaValor] = $parteInteira . ',' . $parteDecimal;
    array_splice($linha, $colunaValor + 1, 1);

    return $linha;
}

function normalizarDataImportacaoCsv(string $data): ?string
{
    $data = trim($data);

    foreach (['!Y-m-d', '!d/m/Y', '!d-m-Y'] as $formato) {
        $objeto = DateTimeImmutable::createFromFormat($formato, $data);
        $erros = DateTimeImmutable::getLastErrors();

        if (
            $objeto instanceof DateTimeImmutable
            && ($erros === false || ($erros['warning_count'] === 0 && $erros['error_count'] === 0))
        ) {
            return $objeto->format('Y-m-d');
        }
    }

    return null;
}

/**
 * @return array{tipo: string, valor: string}|null
 */
function normalizarTipoEValorCsv(string $tipoInformado, string $valorInformado): ?array
{
    $valorInformado = normalizarTextoCsv($valorInformado);
    $valorNegativo = str_contains($valorInformado, '-')
        || (str_starts_with(trim($valorInformado), '(') && str_ends_with(trim($valorInformado), ')'));
    $valorSemSinal = str_replace(['-', '(', ')'], '', $valorInformado);
    $valor = normalizarValorTransacao($valorSemSinal);

    if ($valor === null) {
        return null;
    }

    $tipoNormalizado = normalizarCabecalhoCsv($tipoInformado);
    $tiposReceita = ['receita', 'entrada', 'credito', 'credit', 'income', 'c'];
    $tiposDespesa = ['despesa', 'saida', 'debito', 'debit', 'expense', 'd'];

    if ($tipoNormalizado === '') {
        $tipo = $valorNegativo ? 'despesa' : 'receita';
    } elseif (in_array($tipoNormalizado, $tiposReceita, true)) {
        $tipo = 'receita';
    } elseif (in_array($tipoNormalizado, $tiposDespesa, true)) {
        $tipo = 'despesa';
    } else {
        return null;
    }

    return ['tipo' => $tipo, 'valor' => $valor];
}

function chaveCategoriaCsv(string $nome, string $tipo): string
{
    $nome = normalizarTextoCsv($nome);
    $nome = function_exists('mb_strtolower')
        ? mb_strtolower($nome, 'UTF-8')
        : strtolower($nome);

    return $tipo . '|' . $nome;
}

/**
 * @param array<string, int> $mapaCategorias
 */
function obterCategoriaImportacaoCsv(
    PDO $pdo,
    int $usuarioId,
    int $carteiraId,
    string $nome,
    string $tipo,
    array &$mapaCategorias
): ?int {
    $nome = normalizarTextoCsv($nome);
    if ($nome === '' || normalizarCabecalhoCsv($nome) === 'sem_categoria') {
        return null;
    }

    if (tamanhoTextoTransacao($nome) > 100) {
        throw new InvalidArgumentException('a categoria possui mais de 100 caracteres');
    }

    $chave = chaveCategoriaCsv($nome, $tipo);
    if (isset($mapaCategorias[$chave])) {
        return $mapaCategorias[$chave];
    }

    $cor = $tipo === 'receita' ? '#167552' : '#b42f3c';
    inserirCategoria($pdo, $usuarioId, $carteiraId, $nome, $tipo, $cor);
    $categoriaId = (int) $pdo->lastInsertId();
    $mapaCategorias[$chave] = $categoriaId;

    return $categoriaId;
}

/**
 * @return array{sucesso: bool, mensagem: string, data_inicio?: string, data_fim?: string}
 */
function importarMovimentacoesCsv(PDO $pdo, int $usuarioId, int $carteiraId, array $arquivo): array
{
    $erroUpload = (int) ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE);
    $tamanho = (int) ($arquivo['size'] ?? 0);
    $caminho = is_string($arquivo['tmp_name'] ?? null) ? $arquivo['tmp_name'] : '';
    $nomeArquivo = is_string($arquivo['name'] ?? null) ? $arquivo['name'] : '';

    if ($erroUpload !== UPLOAD_ERR_OK || $caminho === '' || !is_file($caminho)) {
        return ['sucesso' => false, 'mensagem' => 'Selecione um arquivo CSV válido para importar.'];
    }

    if ($tamanho <= 0 || $tamanho > TAMANHO_MAXIMO_CSV) {
        return ['sucesso' => false, 'mensagem' => 'O arquivo CSV deve possuir no máximo 5 MB.'];
    }

    $extensao = strtolower(pathinfo($nomeArquivo, PATHINFO_EXTENSION));
    if (!in_array($extensao, ['csv', 'txt'], true)) {
        return ['sucesso' => false, 'mensagem' => 'O arquivo precisa possuir a extensão .csv ou .txt.'];
    }

    $handle = fopen($caminho, 'rb');
    if ($handle === false) {
        return ['sucesso' => false, 'mensagem' => 'Não foi possível abrir o arquivo CSV.'];
    }

    try {
        $primeiraLinha = fgets($handle);
        if (!is_string($primeiraLinha) || trim($primeiraLinha) === '') {
            return ['sucesso' => false, 'mensagem' => 'O arquivo CSV está vazio.'];
        }

        $delimitador = detectarDelimitadorCsv($primeiraLinha);
        rewind($handle);
        $cabecalhoOriginal = fgetcsv($handle, 0, $delimitador, '"', '');
        if (!is_array($cabecalhoOriginal)) {
            return ['sucesso' => false, 'mensagem' => 'Não foi possível identificar o cabeçalho do CSV.'];
        }

        $cabecalhos = array_map(
            static fn (mixed $valor): string => normalizarCabecalhoCsv((string) $valor),
            $cabecalhoOriginal
        );
        $colunaData = localizarColunaCsv($cabecalhos, ['data', 'date', 'data_lancamento', 'dt_lancamento']);
        $colunaValor = localizarColunaCsv($cabecalhos, ['valor', 'amount', 'valor_lancamento']);
        $colunaTipo = localizarColunaCsv($cabecalhos, ['tipo', 'type', 'natureza', 'operacao']);
        $colunaCategoria = localizarColunaCsv($cabecalhos, ['categoria', 'category']);
        $colunaDescricao = localizarColunaCsv($cabecalhos, ['descricao', 'description', 'historico', 'memo']);
        $colunaParcelas = localizarColunaCsv($cabecalhos, ['parcelas', 'parcela', 'quantidade_parcelas']);

        if ($colunaData === null || $colunaValor === null) {
            return [
                'sucesso' => false,
                'mensagem' => 'O CSV precisa ter pelo menos as colunas Data e Valor.',
            ];
        }

        $mapaCategorias = [];
        foreach (listarCategoriasCarteira($pdo, $carteiraId) as $categoria) {
            $mapaCategorias[chaveCategoriaCsv((string) $categoria['nome'], (string) $categoria['tipo'])]
                = (int) $categoria['id'];
        }

        $importadas = 0;
        $ignoradas = 0;
        $erros = [];
        $datasImportadas = [];
        $numeroLinha = 1;

        while (($linha = fgetcsv($handle, 0, $delimitador, '"', '')) !== false) {
            $numeroLinha++;

            if ($numeroLinha > MAXIMO_LINHAS_CSV + 1) {
                $erros[] = 'limite de ' . MAXIMO_LINHAS_CSV . ' linhas atingido';
                break;
            }

            if (count(array_filter($linha, static fn (mixed $valor): bool => trim((string) $valor) !== '')) === 0) {
                continue;
            }

            $linha = corrigirValorDecimalCsv(
                $linha,
                $delimitador,
                $colunaValor,
                count($cabecalhos)
            );

            $obter = static fn (?int $indice): string => $indice === null
                ? ''
                : normalizarTextoCsv((string) ($linha[$indice] ?? ''));
            $data = normalizarDataImportacaoCsv($obter($colunaData));
            $tipoEValor = normalizarTipoEValorCsv($obter($colunaTipo), $obter($colunaValor));

            if ($data === null || $tipoEValor === null) {
                $ignoradas++;
                $erros[] = "linha {$numeroLinha}: data, tipo ou valor inválido";
                continue;
            }

            $parcelasRaw = $obter($colunaParcelas);
            $parcelas = $parcelasRaw === '' ? 1 : (ctype_digit($parcelasRaw) ? (int) $parcelasRaw : 0);

            try {
                $categoriaId = obterCategoriaImportacaoCsv(
                    $pdo,
                    $usuarioId,
                    $carteiraId,
                    $obter($colunaCategoria),
                    $tipoEValor['tipo'],
                    $mapaCategorias
                );
                $resultado = criarTransacao($pdo, $usuarioId, $carteiraId, [
                    'tipo' => $tipoEValor['tipo'],
                    'valor' => $tipoEValor['valor'],
                    'data' => $data,
                    'categoria_id' => $categoriaId === null ? '' : (string) $categoriaId,
                    'descricao' => $obter($colunaDescricao),
                    'parcelas' => (string) $parcelas,
                ]);
            } catch (Throwable $erro) {
                $resultado = ['sucesso' => false, 'mensagem' => $erro->getMessage()];
            }

            if ($resultado['sucesso']) {
                $importadas++;
                $datasImportadas[] = $data;
            } else {
                $ignoradas++;
                $erros[] = "linha {$numeroLinha}: {$resultado['mensagem']}";
            }
        }

        if ($importadas === 0) {
            $detalhe = $erros === [] ? '' : ' ' . implode(' ', array_slice($erros, 0, 3));
            return ['sucesso' => false, 'mensagem' => 'Nenhuma movimentação foi importada.' . $detalhe];
        }

        sort($datasImportadas);
        $separadorNome = $delimitador === ';' ? 'ponto e vírgula (;)' : 'vírgula (,)';
        $mensagem = "Importação concluída com {$importadas} movimentação(ões). "
            . "Separador identificado: {$separadorNome}.";

        if ($ignoradas > 0) {
            $mensagem .= " {$ignoradas} linha(s) foram ignoradas. " . implode(' ', array_slice($erros, 0, 3));
        }

        return [
            'sucesso' => true,
            'mensagem' => $mensagem,
            'data_inicio' => $datasImportadas[0],
            'data_fim' => $datasImportadas[count($datasImportadas) - 1],
        ];
    } finally {
        fclose($handle);
    }
}
