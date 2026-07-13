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
    <title>Login - Controle Financeiro</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; justify-content: center; align-items: center; padding: 20px; }
        .container { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); max-width: 420px; width: 100%; }
        h1 { color: #333; margin-bottom: 30px; font-size: 28px; text-align: center; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; font-size: 14px; }
        input { width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; transition: border-color 0.3s; }
        input:focus { outline: none; border-color: #667eea; }
        button { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 20px; border: none; border-radius: 6px; cursor: pointer; width: 100%; font-weight: 600; font-size: 16px; transition: transform 0.2s, box-shadow 0.2s; margin-top: 10px; }
        button:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4); }
        .msg { padding: 14px; margin-bottom: 20px; border-radius: 6px; font-size: 14px; border-left: 4px solid; background: #fff5f5; color: #721c24; border-left-color: #f5c6cb; }
        .msg.sucesso { background: #f0f9f7; color: #155724; border-left-color: #c3e6cb; }
        a { color: #667eea; text-decoration: none; font-weight: 500; transition: color 0.2s; }
        a:hover { color: #764ba2; text-decoration: underline; }
        p { margin-top: 15px; text-align: center; font-size: 14px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Controle Financeiro</h1>

        <?php if ($mensagem !== ''): ?>
            <div class="msg <?= strpos($mensagem, 'sucesso') !== false ? 'sucesso' : '' ?>">
                <?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="post" action="index.php?page=login">
            <div class="form-group">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" required autofocus>
            </div>

            <div class="form-group">
                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha" minlength="6" required>
            </div>

            <button type="submit">Entrar</button>
        </form>

        <p>
            <a href="index.php?page=recuperar-senha">Esqueci minha senha</a>
        </p>
        <p>
            Não tem conta? <a href="index.php?page=cadastro">Criar uma</a>
        </p>
    </div>
</body>
</html>
