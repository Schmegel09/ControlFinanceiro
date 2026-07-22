<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Core/proteger.php';
require_once dirname(__DIR__, 2) . '/config/conexao.php';
require_once dirname(__DIR__) . '/Models/UsuarioModel.php';
require_once dirname(__DIR__) . '/Services/SaasService.php';

garantirEstruturaSaas($pdo);

$mensagem = '';
$exibirReenvioConfirmacao = false;

if (($_GET['cadastro'] ?? '') === 'pendente') {
    $mensagem = 'Cadastro realizado. O acesso será liberado após a aprovação do administrador.';
} elseif (($_GET['reset'] ?? '') === 'sucesso') {
    $mensagem = 'Senha alterada com sucesso. Faça login.';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $email = trim(is_string($_POST['email'] ?? null) ? $_POST['email'] : '');
    $senha = is_string($_POST['senha'] ?? null) ? $_POST['senha'] : '';

    if ($email === '' || $senha === '') {
        $mensagem = 'Preencha todos os campos.';
    } else {
        $usuario = buscarUsuarioPorEmail($pdo, $email);

        if ($usuario && password_verify($senha, (string) $usuario['senha'])) {
            if (empty($usuario['email_verificado_em'])) {
                $mensagem = 'Confirme seu e-mail antes de entrar. Se não recebeu ou se o link expirou, solicite um novo abaixo.';
                $exibirReenvioConfirmacao = true;
                require dirname(__DIR__) . '/Views/auth/login.php';
                return;
            }

            autenticarUsuario(
                (int) $usuario['id'],
                (string) $usuario['nome'],
                (string) $usuario['email'],
                (string) ($usuario['papel_sistema'] ?? 'usuario')
            );
            $destino = obterDestinoAposLogin();
            $superAdmin = usuarioSuperAdminAtual($pdo, (int) $usuario['id']);

            if (!$superAdmin && $destino === '/dashboard') {
                $permissoes = obterPermissoesUsuario($pdo, (int) $usuario['id']);
                $destino = urlPrimeiraTelaPermitida($permissoes) ?? '/dashboard';
            }

            header('Location: ' . $destino, true, 302);
            exit;
        }

        if ($mensagem === '') {
            $mensagem = 'E-mail ou senha inválidos.';
        }
    }
}

require dirname(__DIR__) . '/Views/auth/login.php';
