<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/conexao.php';

$mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if ($nome === '' || $email === '' || $senha === '') {
        $mensagem = 'Preencha todos os campos.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensagem = 'Informe um e-mail válido.';
    } elseif (strlen($senha) < 6) {
        $mensagem = 'A senha precisa ter pelo menos 6 caracteres.';
    } else {
        $consulta = $pdo->prepare(
            'SELECT id FROM Usuarios WHERE email = :email LIMIT 1'
        );

        $consulta->execute([
            ':email' => $email,
        ]);

        if ($consulta->fetch()) {
            $mensagem = 'Este e-mail já está cadastrado.';
        } else {
            $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare(
                'INSERT INTO Usuarios (nome, email, senha)
                 VALUES (:nome, :email, :senha)'
            );

            $stmt->execute([
                ':nome' => $nome,
                ':email' => $email,
                ':senha' => $senhaHash,
            ]);

            header('Location: index.php?page=login&cadastro=sucesso');
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro</title>
</head>
<body>

    <h1>Criar conta</h1>

    <?php if ($mensagem !== ''): ?>
        <p><?= htmlspecialchars($mensagem) ?></p>
    <?php endif; ?>

    <form method="post" action="index.php?page=cadastro">
        <div>
            <label for="nome">Nome</label>
            <input
                type="text"
                id="nome"
                name="nome"
                required
            >
        </div>

        <div>
            <label for="email">E-mail</label>
            <input
                type="email"
                id="email"
                name="email"
                required
            >
        </div>

        <div>
            <label for="senha">Senha</label>
            <input
                type="password"
                id="senha"
                name="senha"
                minlength="6"
                required
            >
        </div>

        <button type="submit">Cadastrar</button>
    </form>

    <p>
        <a href="index.php?page=login">Já tenho uma conta</a>
    </p>

</body>
</html>