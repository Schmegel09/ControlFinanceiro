<?php

declare(strict_types=1);

// A tela de administração de clientes é um painel mestre e não deve
// interagir com o contexto de carteiras do usuário.
if (isset($_SERVER['REQUEST_URI']) && str_starts_with($_SERVER['REQUEST_URI'], '/admin-clientes')) {
    return;
}

require_once dirname(__DIR__) . '/Core/proteger.php';
require_once dirname(__DIR__, 2) . '/config/conexao.php';
require_once dirname(__DIR__) . '/Services/CarteiraService.php';

/**
 * @return array{mensagem: string, tipo: string}
 */
function consumirFlashCarteiras(): array
{
    $flash = $_SESSION['flash_carteiras'] ?? null;
    unset($_SESSION['flash_carteiras']);

    if (!is_array($flash) || !is_string($flash['mensagem'] ?? null)) {
        return ['mensagem' => '', 'tipo' => 'sucesso'];
    }

    return ['mensagem' => $flash['mensagem'], 'tipo' => ($flash['sucesso'] ?? false) ? 'sucesso' : 'erro'];
}

$usuarioId = (int) ($_SESSION['usuario_id'] ?? 0);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $acao = is_string($_POST['acao'] ?? null) ? $_POST['acao'] : '';
    $resultado = ['sucesso' => false, 'mensagem' => 'Ação de carteira inválida.'];
    $destino = '/carteiras';

    if (!tokenCsrfCarteirasValido($_POST['csrf_token'] ?? null)) {
        $resultado = ['sucesso' => false, 'mensagem' => 'Sua sessão expirou. Atualize a página.'];
    } elseif ($acao === 'selecionar') {
        $idRaw = is_scalar($_POST['carteira_id'] ?? null) ? (string) $_POST['carteira_id'] : '';
        $id = ctype_digit($idRaw) ? (int) $idRaw : 0;
        $carteira = buscarCarteiraDoUsuario($pdo, $usuarioId, $id);

        if ($carteira) {
            $_SESSION['carteira_id'] = $id;
            $resultado = ['sucesso' => true, 'mensagem' => 'Carteira alterada com sucesso.'];
            $voltar = is_string($_POST['voltar'] ?? null) ? $_POST['voltar'] : '/dashboard';
            $destino = str_starts_with($voltar, '/') && !str_starts_with($voltar, '//')
                ? $voltar
                : '/dashboard';
        } else {
            $resultado = ['sucesso' => false, 'mensagem' => 'Carteira não encontrada.'];
        }
    } elseif ($acao === 'criar_casal') {
        $nome = is_string($_POST['nome'] ?? null) ? $_POST['nome'] : '';
        $resultado = criarCarteiraCasal($pdo, $usuarioId, $nome);

        if ($resultado['sucesso'] && isset($resultado['carteira_id'])) {
            $_SESSION['carteira_id'] = $resultado['carteira_id'];
        }
    } elseif ($acao === 'adicionar_parceiro') {
        $idRaw = is_scalar($_POST['carteira_id'] ?? null) ? (string) $_POST['carteira_id'] : '';
        $id = ctype_digit($idRaw) ? (int) $idRaw : 0;
        $email = is_string($_POST['email'] ?? null) ? $_POST['email'] : '';
        $resultado = adicionarParceiroCarteira($pdo, $usuarioId, $id, $email);
    } else {
        // Se a ação não pertence a este controller, não faz nada.
        return;
    }

    $_SESSION['flash_carteiras'] = $resultado;
    header('Location: ' . $destino, true, 303);
    exit;
}

$contextoCarteira = prepararContextoCarteira($pdo, $usuarioId);
$carteiraAtual = $contextoCarteira['carteira'];
$carteiraId = (int) $carteiraAtual['id'];
$carteirasDisponiveis = $contextoCarteira['carteiras'];
$membrosCarteiraAtual = $contextoCarteira['membros'];
$csrfTokenCarteiras = tokenCsrfCarteiras();
$carteiraCasal = null;

foreach ($carteirasDisponiveis as $carteiraDisponivel) {
    if ($carteiraDisponivel['tipo'] === 'casal') {
        $carteiraCasal = $carteiraDisponivel;
        break;
    }
}

$membrosCarteiraCasal = $carteiraCasal
    ? listarMembrosCarteira($pdo, (int) $carteiraCasal['id'])
    : [];

$flash = consumirFlashCarteiras();
$mensagemCarteiras = $flash['mensagem'];
$tipoMensagemCarteiras = $flash['tipo'];

// A view só deve ser incluída se esta for a página de carteiras.
// Isso evita que este controller tente renderizar sua view em outras páginas.
if (str_starts_with(($_SERVER['REQUEST_URI'] ?? ''), '/carteiras')) {
    require dirname(__DIR__) . '/Views/carteiras/index.php';
}
