<?php

declare(strict_types=1);

if (!isset($pdo)) {
    throw new RuntimeException('O objeto $pdo não está disponível em categorias_controller.php.');
}

require_once __DIR__ . '/transacoes_service.php';

$usuarioId = (int) ($_SESSION['usuario_id'] ?? 0);
garantirEstruturaTransacoes($pdo);

function tokenCsrfCategorias(): string
{
    if (!isset($_SESSION['csrf_categorias']) || !is_string($_SESSION['csrf_categorias'])) {
        $_SESSION['csrf_categorias'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_categorias'];
}

function tokenCsrfCategoriasValido(mixed $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_categorias'])
        && is_string($_SESSION['csrf_categorias'])
        && hash_equals($_SESSION['csrf_categorias'], $token);
}

/**
 * @return array{valida: bool, nome: string, tipo: string, cor: string, mensagem: string}
 */
function validarDadosCategoria(array $dados): array
{
    $nome = trim(is_string($dados['nome'] ?? null) ? $dados['nome'] : '');
    $tipo = is_string($dados['tipo'] ?? null) ? $dados['tipo'] : '';
    $cor = trim(is_string($dados['cor'] ?? null) ? $dados['cor'] : '');

    if ($nome === '') {
        return ['valida' => false, 'nome' => '', 'tipo' => '', 'cor' => '', 'mensagem' => 'Informe o nome da categoria.'];
    }

    if (tamanhoTextoTransacao($nome) > 100) {
        return ['valida' => false, 'nome' => '', 'tipo' => '', 'cor' => '', 'mensagem' => 'O nome deve ter no máximo 100 caracteres.'];
    }

    if (!in_array($tipo, ['receita', 'despesa'], true)) {
        return ['valida' => false, 'nome' => '', 'tipo' => '', 'cor' => '', 'mensagem' => 'Selecione um tipo de categoria válido.'];
    }

    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $cor)) {
        return ['valida' => false, 'nome' => '', 'tipo' => '', 'cor' => '', 'mensagem' => 'Selecione uma cor válida.'];
    }

    return [
        'valida' => true,
        'nome' => $nome,
        'tipo' => $tipo,
        'cor' => strtolower($cor),
        'mensagem' => '',
    ];
}

function categoriaDuplicada(PDO $pdo, int $usuarioId, string $nome, string $tipo, int $ignorarId = 0): bool
{
    $sql = 'SELECT id
            FROM categorias
            WHERE usuario_id = :usuario_id AND nome = :nome AND tipo = :tipo';
    $parametros = [
        ':usuario_id' => $usuarioId,
        ':nome' => $nome,
        ':tipo' => $tipo,
    ];

    if ($ignorarId > 0) {
        $sql .= ' AND id <> :id';
        $parametros[':id'] = $ignorarId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametros);

    return $stmt->fetchColumn() !== false;
}

/**
 * @param array{sucesso: bool, mensagem: string} $resultado
 */
function definirFlashCategorias(array $resultado): void
{
    $_SESSION['flash_categorias'] = [
        'mensagem' => $resultado['mensagem'],
        'tipo' => $resultado['sucesso'] ? 'sucesso' : 'erro',
    ];
}

/**
 * @return array{mensagem: string, tipo: string}
 */
function consumirFlashCategorias(): array
{
    $flash = $_SESSION['flash_categorias'] ?? null;
    unset($_SESSION['flash_categorias']);

    if (!is_array($flash) || !is_string($flash['mensagem'] ?? null)) {
        return ['mensagem' => '', 'tipo' => 'sucesso'];
    }

    return [
        'mensagem' => $flash['mensagem'],
        'tipo' => ($flash['tipo'] ?? '') === 'erro' ? 'erro' : 'sucesso',
    ];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $acao = is_string($_POST['acao'] ?? null) ? $_POST['acao'] : '';

    if (!tokenCsrfCategoriasValido($_POST['csrf_token'] ?? null)) {
        $resultado = ['sucesso' => false, 'mensagem' => 'Sua sessão expirou. Atualize a página e tente novamente.'];
    } elseif ($acao === 'adicionar') {
        $dados = validarDadosCategoria($_POST);

        if (!$dados['valida']) {
            $resultado = ['sucesso' => false, 'mensagem' => $dados['mensagem']];
        } elseif (categoriaDuplicada($pdo, $usuarioId, $dados['nome'], $dados['tipo'])) {
            $resultado = ['sucesso' => false, 'mensagem' => 'Já existe uma categoria com esse nome e tipo.'];
        } else {
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO categorias (usuario_id, nome, tipo, cor)
                     VALUES (:usuario_id, :nome, :tipo, :cor)'
                );
                $stmt->execute([
                    ':usuario_id' => $usuarioId,
                    ':nome' => $dados['nome'],
                    ':tipo' => $dados['tipo'],
                    ':cor' => $dados['cor'],
                ]);
                $resultado = ['sucesso' => true, 'mensagem' => 'Categoria criada com sucesso.'];
            } catch (Throwable $erro) {
                error_log($erro->getMessage());
                $resultado = ['sucesso' => false, 'mensagem' => 'Não foi possível criar a categoria. Tente novamente.'];
            }
        }
    } elseif ($acao === 'editar') {
        $idRaw = is_scalar($_POST['id'] ?? null) ? (string) $_POST['id'] : '';
        $id = ctype_digit($idRaw) ? (int) $idRaw : 0;
        $dados = validarDadosCategoria($_POST);

        $stmtCategoria = $pdo->prepare(
            'SELECT id, tipo FROM categorias WHERE id = :id AND usuario_id = :usuario_id'
        );
        $stmtCategoria->execute([':id' => $id, ':usuario_id' => $usuarioId]);
        $categoriaAtual = $stmtCategoria->fetch();

        if ($id <= 0 || !$categoriaAtual) {
            $resultado = ['sucesso' => false, 'mensagem' => 'Categoria não encontrada.'];
        } elseif (!$dados['valida']) {
            $resultado = ['sucesso' => false, 'mensagem' => $dados['mensagem']];
        } elseif (categoriaDuplicada($pdo, $usuarioId, $dados['nome'], $dados['tipo'], $id)) {
            $resultado = ['sucesso' => false, 'mensagem' => 'Já existe outra categoria com esse nome e tipo.'];
        } else {
            $stmtVinculos = $pdo->prepare(
                'SELECT COUNT(*) FROM transacoes WHERE categoria_id = :id AND usuario_id = :usuario_id'
            );
            $stmtVinculos->execute([':id' => $id, ':usuario_id' => $usuarioId]);
            $totalVinculos = (int) $stmtVinculos->fetchColumn();

            if ($categoriaAtual['tipo'] !== $dados['tipo'] && $totalVinculos > 0) {
                $resultado = [
                    'sucesso' => false,
                    'mensagem' => 'O tipo não pode ser alterado porque esta categoria possui lançamentos vinculados.',
                ];
            } else {
                try {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare(
                        'UPDATE categorias
                         SET nome = :nome, tipo = :tipo, cor = :cor
                         WHERE id = :id AND usuario_id = :usuario_id'
                    );
                    $stmt->execute([
                        ':nome' => $dados['nome'],
                        ':tipo' => $dados['tipo'],
                        ':cor' => $dados['cor'],
                        ':id' => $id,
                        ':usuario_id' => $usuarioId,
                    ]);

                    $stmtTransacoes = $pdo->prepare(
                        'UPDATE transacoes
                         SET categoria = :nome
                         WHERE categoria_id = :id AND usuario_id = :usuario_id'
                    );
                    $stmtTransacoes->execute([
                        ':nome' => $dados['nome'],
                        ':id' => $id,
                        ':usuario_id' => $usuarioId,
                    ]);

                    $pdo->commit();
                    $resultado = ['sucesso' => true, 'mensagem' => 'Categoria atualizada com sucesso.'];
                } catch (Throwable $erro) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log($erro->getMessage());
                    $resultado = ['sucesso' => false, 'mensagem' => 'Não foi possível atualizar a categoria. Tente novamente.'];
                }
            }
        }
    } elseif ($acao === 'deletar') {
        $idRaw = is_scalar($_POST['id'] ?? null) ? (string) $_POST['id'] : '';
        $id = ctype_digit($idRaw) ? (int) $idRaw : 0;

        try {
            $pdo->beginTransaction();

            $stmtDesvincular = $pdo->prepare(
                'UPDATE transacoes
                 SET categoria_id = NULL
                 WHERE categoria_id = :id AND usuario_id = :usuario_id'
            );
            $stmtDesvincular->execute([':id' => $id, ':usuario_id' => $usuarioId]);

            $stmt = $pdo->prepare(
                'DELETE FROM categorias WHERE id = :id AND usuario_id = :usuario_id'
            );
            $stmt->execute([':id' => $id, ':usuario_id' => $usuarioId]);

            if ($id <= 0 || $stmt->rowCount() === 0) {
                $pdo->rollBack();
                $resultado = ['sucesso' => false, 'mensagem' => 'Categoria não encontrada.'];
            } else {
                $pdo->commit();
                $resultado = ['sucesso' => true, 'mensagem' => 'Categoria excluída com sucesso. Os lançamentos existentes foram mantidos.'];
            }
        } catch (Throwable $erro) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log($erro->getMessage());
            $resultado = ['sucesso' => false, 'mensagem' => 'Não foi possível excluir a categoria. Tente novamente.'];
        }
    } else {
        $resultado = ['sucesso' => false, 'mensagem' => 'Ação de categoria inválida.'];
    }

    definirFlashCategorias($resultado);
    header('Location: /categorias', true, 303);
    exit;
}

$flash = consumirFlashCategorias();
$mensagem = $flash['mensagem'];
$tipoMensagem = $flash['tipo'];
$csrfTokenCategorias = tokenCsrfCategorias();

$categorias = $pdo->prepare(
    'SELECT
         c.id,
         c.nome,
         c.tipo,
         c.cor,
         c.criada_em,
         COUNT(t.id) AS total_movimentacoes
     FROM categorias c
     LEFT JOIN transacoes t
       ON t.categoria_id = c.id AND t.usuario_id = c.usuario_id
     WHERE c.usuario_id = :usuario_id
     GROUP BY c.id, c.nome, c.tipo, c.cor, c.criada_em
     ORDER BY c.tipo DESC, c.nome ASC, c.id ASC'
);
$categorias->execute([':usuario_id' => $usuarioId]);
$categoriasList = $categorias->fetchAll();

$categoriasReceita = array_values(array_filter(
    $categoriasList,
    static fn (array $categoria): bool => $categoria['tipo'] === 'receita'
));
$categoriasDespesa = array_values(array_filter(
    $categoriasList,
    static fn (array $categoria): bool => $categoria['tipo'] === 'despesa'
));

$jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;
$categoriasCrudJson = json_encode($categoriasList, $jsonFlags) ?: '[]';
