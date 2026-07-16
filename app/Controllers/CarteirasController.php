<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Core/proteger.php';
require_once dirname(__DIR__, 2) . '/config/conexao.php';
require_once dirname(__DIR__) . '/Services/CarteiraService.php';

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
    }

    if ($destino === '/carteiras') {
        $_SESSION['flash_carteiras'] = $resultado;
    } else {
        unset($_SESSION['flash_carteiras']);
    }
    header('Location: ' . $destino, true, 303);
    exit;
}

$flashCarteiras = $_SESSION['flash_carteiras'] ?? null;
unset($_SESSION['flash_carteiras']);
$mensagemCarteiras = is_array($flashCarteiras) && is_string($flashCarteiras['mensagem'] ?? null)
    ? $flashCarteiras['mensagem']
    : '';
$tipoMensagemCarteiras = is_array($flashCarteiras) && ($flashCarteiras['sucesso'] ?? false)
    ? 'sucesso'
    : 'erro';

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

require dirname(__DIR__) . '/Views/carteiras/index.php';
