<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/Core/proteger.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Cadastro - Controle Financeiro</title>

    <link rel="stylesheet" href="/assets/css/base.css">
    <link rel="stylesheet" href="/assets/css/pages/cadastro.css">
</head>
<body>
    <main class="container">
        <h1>Criar Conta</h1>

        <?php if ($mensagem !== ''): ?>
            <div
                class="msg <?= strpos($mensagem, 'sucesso') !== false
                    ? 'sucesso'
                    : '' ?>"
                role="alert"
            >
                <?= htmlspecialchars(
                    $mensagem,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="/cadastro">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    $csrfToken,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >

            <div class="form-group">
                <label for="nome">Nome</label>

                <input
                    type="text"
                    id="nome"
                    name="nome"
                    maxlength="120"
                    required
                    autofocus
                    autocomplete="name"
                >
            </div>

            <div class="form-group">
                <label for="email">E-mail</label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    maxlength="190"
                    required
                    autocomplete="email"
                >
            </div>

            <div class="form-group">
                <label for="senha">
                    Senha (mínimo 6 caracteres)
                </label>

                <input
                    type="password"
                    id="senha"
                    name="senha"
                    minlength="6"
                    maxlength="255"
                    required
                    autocomplete="new-password"
                >
            </div>

            <button type="submit">
                Cadastrar
            </button>
        </form>

        <p>
            Já tem uma conta?
            <a href="/login">Faça login</a>
        </p>
    </main>
</body>
</html>