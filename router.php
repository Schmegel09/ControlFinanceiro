<?php

if (php_sapi_name() === 'cli-server') {
    $url = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    if ($url === '/api.php' || str_starts_with($url, '/api/')) {
        if (str_starts_with($url, '/api/')) {
            $_GET['path'] = substr($url, strlen('/api/'));
        }

        require __DIR__ . '/api.php';
        return true;
    }

    $file = __DIR__ . $url;

    // Se for um arquivo que existe (css, js, imagens, etc), servir direto
    if (is_file($file) && strpos($file, '.php') === false) {
        return false;
    }

    // Se for um arquivo .php ou diretório, rotear para index.php
    if (is_file($file) || is_dir($file)) {
        // Remover .php da URL se existir
        $_GET['page'] = basename($url, '.php');
        $_SERVER['REQUEST_URI'] = '/?page=' . $_GET['page'];
    }
}

require_once __DIR__ . '/index.php';
