<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/Core/proteger.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Controle Financeiro</title>
    <link rel="stylesheet" href="/assets/css/base.css">
    <link rel="stylesheet" href="/assets/css/pages/login.css">
</head>
<body>
    <main class="container">
        <h1>Controle Financeiro</h1>

        <?php if ($mensagem !== ''): ?>
            <?php $loginComSucesso = strpos($mensagem, 'sucesso') !== false || strpos($mensagem, 'Cadastro realizado') !== false; ?>
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
        <?php if ($exibirReenvioConfirmacao ?? false): ?>
            <p>
                <a href="/verificar-email">Reenviar confirmação de e-mail</a>
            </p>
        <?php endif; ?>
        <p>
            Não tem conta? <a href="/cadastro">Criar uma</a>
        </p>
    </main>
</body>
</html>
