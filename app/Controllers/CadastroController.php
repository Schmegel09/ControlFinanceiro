<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Core/proteger.php';
require_once dirname(__DIR__, 2) . '/config/conexao.php';
require_once dirname(__DIR__) . '/Models/UsuarioModel.php';

$mensagem = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $nome = trim(is_string($_POST['nome'] ?? null) ? $_POST['nome'] : '');
    $email = trim(is_string($_POST['email'] ?? null) ? $_POST['email'] : '');
    $senha = is_string($_POST['senha'] ?? null) ? $_POST['senha'] : '';

    if ($nome === '' || $email === '' || $senha === '') {
        $mensagem = 'Preencha todos os campos.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensagem = 'Informe um e-mail válido.';
    } elseif (strlen($senha) < 6) {
        $mensagem = 'A senha precisa ter pelo menos 6 caracteres.';
    } elseif (buscarUsuarioPorEmail($pdo, $email)) {
        $mensagem = 'Este e-mail já está cadastrado.';
    } else {
        try {
            criarUsuario($pdo, $nome, $email, password_hash($senha, PASSWORD_DEFAULT));
            header('Location: /login?cadastro=sucesso', true, 303);
            exit;
        } catch (Throwable $erro) {
            error_log($erro->getMessage());
            $mensagem = 'Não foi possível concluir o cadastro. Tente novamente.';
        }
    }
}

require dirname(__DIR__) . '/Views/auth/cadastro.php';
