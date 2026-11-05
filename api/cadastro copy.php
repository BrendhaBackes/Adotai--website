<?php
session_start();
require '../api/db_conn.php';
header('Content-Type: application/json; charset=utf-8');

// helper: sends the JSON answer and stops the script
function responder(bool $ok, string $erro = '', array $extra = []): void {
    echo json_encode(array_merge(['ok' => $ok, 'erro' => $erro], $extra));
    exit;
}

// 1) only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(false, 'Invalid request.');
}

// 2) read the data
$tipo      = $_POST['tipo_usuario'] ?? 'adotante';
$nome      = trim($_POST['nome'] ?? '');
$email     = trim($_POST['email'] ?? '');
$telefone  = preg_replace('/\D/', '', $_POST['telefone'] ?? '');   // keeps digits only
$senha     = $_POST['senha'] ?? '';
$confirmar = $_POST['confirmar_senha'] ?? '';

// 3) validate (never trust only the HTML validation)
if (!in_array($tipo, ['adotante', 'protetor', 'veterinario'], true)) responder(false, 'Invalid user type.');
if ($nome === '')                                     responder(false, 'Enter your name.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL))       responder(false, 'Enter a valid email.');
if (strlen($telefone) < 10 || strlen($telefone) > 11) responder(false, 'Enter a valid phone number with area code.');
if (strlen($senha) < 8)                               responder(false, 'The password must have at least 8 characters.');
if ($senha !== $confirmar)                            responder(false, 'The passwords do not match.');
if (!isset($_POST['aceita_termos']))                  responder(false, 'You must accept the terms.');

// 4) extra validation depending on the user type
$nomeUsuario = $nome;

if ($tipo === 'protetor') {
    $categoria   = $_POST['categoria_protetor'] ?? 'pessoa';
    $documento   = preg_replace('/\D/', '', $_POST['documento'] ?? '');
    $responsavel = trim($_POST['responsavel'] ?? '');

    if ($categoria === 'ong') {
        if (strlen($documento) !== 14) responder(false, 'The CNPJ must have 14 digits.');
        if ($responsavel === '')       responder(false, 'Enter the name of the person responsible for the ONG.');
        $nomeUsuario = $responsavel;
        $nomeOrg     = $nome;
    } else {
        if (strlen($documento) !== 11) responder(false, 'The CPF must have 11 digits.');
        $nomeOrg = null;
    }
}

if ($tipo === 'veterinario') {
    $crmv   = preg_replace('/\D/', '', $_POST['crmv'] ?? '');
    $crmvUf = $_POST['crmv_uf'] ?? '';
    if ($crmv === '' || !preg_match('/^[A-Z]{2}$/', $crmvUf)) {
        responder(false, 'Enter the CRMV number and state.');
    }
    $crmvCompleto = $crmv . '/' . $crmvUf;
}

// 5) save to the database
try {
    $stmt = $conn->prepare('SELECT id FROM usuario WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        responder(false, 'This email is already registered.');
    }

    $conn->begin_transaction();

    $hash = password_hash($senha, PASSWORD_DEFAULT);

    $stmt = $conn->prepare(
        "INSERT INTO usuario (nome, status_conta, telefone, email, senha, data_criacao_conta)
         VALUES (?, 'Ativa', ?, ?, ?, CURDATE())"
    );
    $stmt->bind_param('ssss', $nomeUsuario, $telefone, $email, $hash);
    $stmt->execute();
    $idUsuario = $conn->insert_id;

    if ($tipo === 'adotante') {
        $stmt = $conn->prepare('INSERT INTO adotante (id_usuario) VALUES (?)');
        $stmt->bind_param('i', $idUsuario);
        $stmt->execute();

    } elseif ($tipo === 'protetor') {
        $stmt = $conn->prepare(
            "INSERT INTO protetor (id_usuario, status_aprovacao, nome_organizacao, documento, email_contato, telefone_contato)
             VALUES (?, 'Pendente', ?, ?, ?, ?)"
        );
        $stmt->bind_param('issss', $idUsuario, $nomeOrg, $documento, $email, $telefone);
        $stmt->execute();

    } else {
        $stmt = $conn->prepare(
            'INSERT INTO veterinario (nome, crmv, email, telefone, id_usuario) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('ssssi', $nome, $crmvCompleto, $email, $telefone, $idUsuario);
        $stmt->execute();
    }

    $conn->commit();
    header('Location: ../pages/login.html');
    exit;

} catch (Throwable $e) {
    $conn->rollback();
    responder(false, 'Could not create the account. Try again.');
}