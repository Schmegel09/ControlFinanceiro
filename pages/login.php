<?php

declare(strict_types=1);

session_start();

if (isset($_SESSION['usuario_id'])) {
    header('Location: index.php?page=dashboard');
    exit;
}

require_once dirname(__DIR__) . '/config/conexao.php';

$mensagem = '';

if (isset($_GET['cadastro']) && $_GET['cadastro'] === 'sucesso') {
    $mensagem = 'Cadastro realizado com sucesso. Faça login.';
}

if (isset($_GET['reset']) && $_GET['reset'] === 'sucesso') {
    $mensagem = 'Senha alterada com sucesso. Faça login.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if ($email === '' || $senha === '') {
        $mensagem = 'Preencha todos os campos.';
    } else {
        $consulta = $pdo->prepare(
            'SELECT id, nome, senha FROM Usuarios WHERE email = :email LIMIT 1'
        );

        $consulta->execute([
            ':email' => $email,
        ]);

        $usuario = $consulta->fetch();

        if ($usuario && password_verify($senha, $usuario['senha'])) {
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];

            header('Location: index.php?page=dashboard');
            exit;
        }

        $mensagem = 'E-mail ou senha inválidos.';
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
</head>
<body>
    <h1>Login</h1>

    <?php if ($mensagem !== ''): ?>
        <p><?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <form method="post" action="index.php?page=login">
        <div>
            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" required>
        </div>

        <div>
            <label for="senha">Senha</label>
            <input type="password" id="senha" name="senha" minlength="6" required>
        </div>

        <button type="submit">Entrar</button>
    </form>

    <p>
        <a href="index.php?page=recuperar-senha">Esqueci minha senha</a>
    </p>
    <p>
        <a href="index.php?page=cadastro">Criar conta</a>
    </p>
</body>
</html>
