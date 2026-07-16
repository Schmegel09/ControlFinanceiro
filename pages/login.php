<?php

declare(strict_types=1);

/*
Legenda (login.php):
- Esta página é carregada via o dispatcher/front controller quando a rota `/login` é acessada.
- Para alterar o endpoint de envio do formulário, edite a `action` do form (atualmente `/login`).
*/

session_start();

if (isset($_SESSION['usuario_id'])) {
    header('Location: /dashboard');
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

            header('Location: /dashboard');
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
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; min-height: 100dvh; display: flex; justify-content: center; align-items: center; padding: clamp(12px, 4vw, 20px); }
        .container { background: white; padding: clamp(26px, 7vw, 40px); border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); max-width: 420px; width: 100%; }
        h1 { color: #333; margin-bottom: 30px; font-size: clamp(24px, 7vw, 28px); line-height: 1.2; text-align: center; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; font-size: 14px; }
        input { width: 100%; min-height: 44px; padding: 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 16px; transition: border-color 0.3s; }
        input:focus { outline: none; border-color: #667eea; }
        button { min-height: 44px; background: linear-gradient(135deg, #4f5fc9 0%, #6d3f91 100%); color: white; padding: 12px 20px; border: none; border-radius: 6px; cursor: pointer; width: 100%; font-weight: 600; font-size: 16px; transition: transform 0.2s, box-shadow 0.2s; margin-top: 10px; }
        button:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4); }
        .msg { padding: 14px; margin-bottom: 20px; border-radius: 6px; font-size: 14px; border-left: 4px solid; background: #fff5f5; color: #721c24; border-left-color: #f5c6cb; }
        .msg.sucesso { background: #f0f9f7; color: #155724; border-left-color: #c3e6cb; }
        a { color: #4656bd; text-decoration: none; font-weight: 500; transition: color 0.2s; }
        a:hover { color: #764ba2; text-decoration: underline; }
        p { margin-top: 15px; text-align: center; font-size: 14px; line-height: 1.5; color: #666; }
        @media (max-height: 700px) { body { align-items: flex-start; } }
        @media (max-width: 480px) { .container { padding: 28px 20px; } }
    </style>
    <?php require dirname(__DIR__) . '/includes/responsive_styles.php'; ?>
</head>
<body>
    <main class="container">
        <h1>Controle Financeiro</h1>

        <?php if ($mensagem !== ''): ?>
            <?php $loginComSucesso = strpos($mensagem, 'sucesso') !== false; ?>
            <div class="msg <?= $loginComSucesso ? 'sucesso' : '' ?>" role="<?= $loginComSucesso ? 'status' : 'alert' ?>">
                <?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="post" action="/login">
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
            <a href="/recuperar-senha">Esqueci minha senha</a>
        </p>
        <p>
            Não tem conta? <a href="/cadastro">Criar uma</a>
        </p>
    </main>
</body>
</html>
