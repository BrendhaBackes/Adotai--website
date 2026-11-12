<?php
// =====================================================
// api/sessao.php
// Informa ao JavaScript das páginas se há alguém logado.
// Usado por scripts/header_auth.js para montar o header.
// =====================================================
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (empty($_SESSION['logado'])) {
    echo json_encode(['logado' => false]);
    exit;
}

echo json_encode([
    'logado' => true,
    'nome'   => $_SESSION['usuario_nome'] ?? '',
    'tipo'   => $_SESSION['usuario_tipo'] ?? null,
]);
