<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Core/proteger.php';
require_once dirname(__DIR__, 2) . '/config/conexao.php';
require_once dirname(__DIR__) . '/Models/UsuarioModel.php';

$mensagem = '';

if (($_GET['cadastro'] ?? '') === 'sucesso') {
    $mensagem = 'Cadastro realizado com sucesso. Faça login.';
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
            autenticarUsuario((int) $usuario['id'], (string) $usuario['nome']);
            header('Location: ' . obterDestinoAposLogin(), true, 302);
            exit;
        }

        $mensagem = 'E-mail ou senha inválidos.';
    }
}

require dirname(__DIR__) . '/Views/auth/login.php';
