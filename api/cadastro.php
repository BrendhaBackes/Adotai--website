<?php
// =====================================================
// api/cadastro.php
// Insere um novo usuário (adotante, protetor ou veterinário)
// no banco adotai_site. Sem validação de formato: só checa
// se o cadastro já existe e grava.
// =====================================================
require __DIR__ . '/db_conn.php';

// Faz o mysqli lançar exceções (assim o try/catch + rollback funciona)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

// Só aceita POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/cadastro.html');
    exit;
}

// Volta para a tela de cadastro com uma mensagem (tratada em cadastro_handler.js)
function voltar(string $erro): void {
    header('Location: ../pages/cadastro.html?erro=' . urlencode($erro));
    exit;
}

// Verifica se já existe algum registro: existe($conn, 'SELECT 1 FROM x WHERE y = ?', 's', $valor)
function existe(mysqli $conn, string $sql, string $tipos, ...$params): bool {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($tipos, ...$params);
    $stmt->execute();
    $achou = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $achou;
}

// ---------- Dados recebidos do formulário ----------
$tipo     = $_POST['tipo_usuario'] ?? 'adotante';
$nome     = trim($_POST['nome'] ?? '');
$email    = trim($_POST['email'] ?? '');
$telefone = preg_replace('/\D/', '', $_POST['telefone'] ?? ''); // só dígitos (a coluna é char(14))
$senha    = $_POST['senha'] ?? '';

$nomeUsuario = $nome;   // vai para usuario.nome
$nomeOrg     = null;    // protetor.nome_organizacao (só ONG)
$cnpj        = null;    // ongs.cnpj (só ONG)
$cpf         = null;    // protetor.cpf (só protetor pessoa física)
$crmv        = null;    // veterinario.crmv

if ($tipo === 'protetor') {
    $categoria = $_POST['categoria_protetor'] ?? 'pessoa';
    $documento = preg_replace('/\D/', '', $_POST['documento'] ?? '');

    if ($categoria === 'ong') {
        $nomeUsuario = trim($_POST['responsavel'] ?? '');  // usuário = responsável
        $nomeOrg     = $nome;                              // nome da ONG
        $cnpj        = $documento;
    } else {
        $cpf = $documento;
    }
}

if ($tipo === 'veterinario') {
    $crmv = preg_replace('/\D/', '', $_POST['crmv'] ?? '') . '/' . ($_POST['crmv_uf'] ?? '');
}

// ---------- Verifica se o cadastro já existe ----------
try {
    if (existe($conn, 'SELECT 1 FROM usuario WHERE email = ?', 's', $email)) {
        voltar('email_existente');
    }

    if ($cnpj !== null && existe($conn, 'SELECT 1 FROM ongs WHERE cnpj = ?', 's', $cnpj)) {
        voltar('documento_existente');
    }

    if ($cpf !== null && existe($conn, 'SELECT 1 FROM protetor WHERE cpf = ?', 's', $cpf)) {
        voltar('documento_existente');
    }

    if ($crmv !== null && existe($conn, 'SELECT 1 FROM veterinario WHERE crmv = ?', 's', $crmv)) {
        voltar('documento_existente');
    }

    // ---------- Grava tudo em uma transação ----------
    $conn->begin_transaction();

    $hash = password_hash($senha, PASSWORD_DEFAULT);

    $stmt = $conn->prepare(
        "INSERT INTO usuario (nome, status_conta, telefone, email, senha, data_criacao_conta)
         VALUES (?, 'Ativa', ?, ?, ?, CURDATE())"
    );
    $stmt->bind_param('ssss', $nomeUsuario, $telefone, $email, $hash);
    $stmt->execute();
    $idUsuario = $conn->insert_id;
    $stmt->close();

    if ($tipo === 'adotante') {
        $stmt = $conn->prepare('INSERT INTO adotante (id_usuario) VALUES (?)');
        $stmt->bind_param('i', $idUsuario);
        $stmt->execute();
        $stmt->close();

    } elseif ($tipo === 'protetor') {
        $stmt = $conn->prepare(
            "INSERT INTO protetor (id_usuario, status_aprovacao, nome_organizacao, cpf, email_contato, telefone_contato)
             VALUES (?, 'Pendente', ?, ?, ?, ?)"
        );
        $stmt->bind_param('issss', $idUsuario, $nomeOrg, $cpf, $email, $telefone);
        $stmt->execute();
        $idProtetor = $conn->insert_id;
        $stmt->close();

        if ($cnpj !== null) {
            $stmt = $conn->prepare('INSERT INTO ongs (id_protetor, cnpj) VALUES (?, ?)');
            $stmt->bind_param('is', $idProtetor, $cnpj);
            $stmt->execute();
            $stmt->close();
        }

    } elseif ($tipo === 'veterinario') {
        $stmt = $conn->prepare(
            'INSERT INTO veterinario (nome, crmv, email, telefone, id_usuario) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('ssssi', $nome, $crmv, $email, $telefone, $idUsuario);
        $stmt->execute();
        $stmt->close();

    } else {
        throw new Exception('Tipo de usuário desconhecido.');
    }

    $conn->commit();
    header('Location: ../pages/login.html');
    exit;

} catch (Throwable $e) {
    // desfaz o que já tinha sido gravado (se a transação chegou a começar)
    try { $conn->rollback(); } catch (Throwable $ignorado) {}
    error_log('Erro no cadastro: ' . $e->getMessage());
    voltar('erro_cadastro');
}
