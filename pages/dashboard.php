<?php

declare(strict_types=1);

session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: index.php?page=login');
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Controle Financeiro</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 40px; border-radius: 10px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
        h1 { color: #333; margin-bottom: 10px; font-size: 32px; }
        .subtitle { color: #999; margin-bottom: 30px; font-size: 16px; }
        .welcome-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 8px; margin-bottom: 30px; text-align: center; }
        .welcome-card h2 { font-size: 24px; margin-bottom: 10px; }
        .welcome-card p { font-size: 18px; opacity: 0.9; }
        .actions { display: flex; gap: 15px; justify-content: center; flex-wrap: wrap; }
        a { display: inline-block; padding: 12px 24px; background: white; color: #667eea; text-decoration: none; border-radius: 6px; font-weight: 600; transition: transform 0.2s, box-shadow 0.2s; border: none; cursor: pointer; }
        a:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(0,0,0,0.2); }
        .logout-btn { background: #ff6b6b; color: white; }
        .logout-btn:hover { background: #ff5252; }
        .menu { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-top: 30px; }
        .menu h3 { color: #555; margin-bottom: 15px; }
        .menu ul { list-style: none; }
        .menu li { margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Bem-vindo ao Controle Financeiro</h1>
        <p class="subtitle">Gerencie suas finanças com facilidade</p>

        <div class="welcome-card">
            <h2><?= htmlspecialchars($_SESSION['usuario_nome'] ?? 'Usuário', ENT_QUOTES, 'UTF-8') ?></h2>
            <p>Você está autenticado no sistema</p>
        </div>

        <div class="actions">
            <a href="index.php?page=logout" class="logout-btn">Sair da Conta</a>
        </div>

        <div class="menu">
            <h3>Funcionalidades</h3>
            <ul>
                <li>💰 Gerenciamento de despesas e receitas</li>
                <li>📊 Relatórios e análises</li>
                <li>🔐 Recuperação de senha segura</li>
                <li>👤 Perfil e configurações</li>
            </ul>
        </div>
    </div>
</body>
</html>
