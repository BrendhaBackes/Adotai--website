<?php
// =====================================================
// api/login.php
// Confere e-mail e senha enviados por pages/login.html e,
// se estiverem corretos, grava o usuário logado em $_SESSION
// (com o tipo: admin, protetor, veterinario ou adotante).
// =====================================================
session_start();
require __DIR__ . '/db_conn.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

// Só aceita POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/login.html');
    exit;
}

// Login inválido: volta ao formulário (login_handler.js mostra a mensagem com ?erro=1)
function falhar(): void {
    header('Location: ../pages/login.html?erro=1');
    exit;
}

// Busca uma linha (ou null) numa consulta com um único parâmetro inteiro
function buscar(mysqli $conn, string $sql, int $id): ?array {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $linha = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $linha ?: null;
}

$email = trim($_POST['email'] ?? '');
$senha = $_POST['senha'] ?? '';

try {
    // ---------- 1) Usuário e senha ----------
    $stmt = $conn->prepare('SELECT id, nome, email, senha, status_conta FROM usuario WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$usuario || !password_verify($senha, $usuario['senha'])) {
        falhar();
    }

    // Só entra quem está com a conta ativa
    if ($usuario['status_conta'] !== 'Ativa') {
        falhar();
    }

    // Se o algoritmo de hash padrão do PHP mudar, atualiza o hash salvo
    if (password_needs_rehash($usuario['senha'], PASSWORD_DEFAULT)) {
        $novoHash = password_hash($senha, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('UPDATE usuario SET senha = ? WHERE id = ?');
        $stmt->bind_param('si', $novoHash, $usuario['id']);
        $stmt->execute();
        $stmt->close();
    }

    // ---------- 2) Descobre o tipo de usuário ----------
    // Prioridade caso a pessoa esteja em mais de uma tabela: admin > protetor > veterinario > adotante
    $id = (int) $usuario['id'];
    $tipo = null;
    $perfil = [];

    if ($admin = buscar($conn, 'SELECT id_admin, nivel_acesso FROM admin WHERE id_usuario = ?', $id)) {
        $tipo   = 'admin';
        $perfil = ['id_perfil' => (int) $admin['id_admin'], 'nivel_acesso' => $admin['nivel_acesso']];

    } elseif ($protetor = buscar($conn, 'SELECT id_protetor, status_aprovacao FROM protetor WHERE id_usuario = ?', $id)) {
        $tipo   = 'protetor';
        $perfil = [
            'id_perfil'        => (int) $protetor['id_protetor'],
            'status_aprovacao' => $protetor['status_aprovacao'],   // 'Pendente', 'Aprovado'...
            'eh_ong'           => (bool) buscar($conn, 'SELECT id_ong FROM ongs WHERE id_protetor = ?', (int) $protetor['id_protetor']),
        ];

    } elseif ($vet = buscar($conn, 'SELECT id_veterinario FROM veterinario WHERE id_usuario = ?', $id)) {
        $tipo   = 'veterinario';
        $perfil = ['id_perfil' => (int) $vet['id_veterinario']];

    } elseif ($adotante = buscar($conn, 'SELECT id_adotante FROM adotante WHERE id_usuario = ?', $id)) {
        $tipo   = 'adotante';
        $perfil = ['id_perfil' => (int) $adotante['id_adotante']];
    }

    // Usuário existe mas não pertence a nenhum tipo: não deixa entrar
    if ($tipo === null) {
        falhar();
    }

    // ---------- 3) Grava a sessão ----------
    session_regenerate_id(true);   // novo ID de sessão ao logar (evita fixação de sessão)

    $_SESSION['logado']       = true;
    $_SESSION['usuario_id']   = $id;
    $_SESSION['usuario_nome'] = $usuario['nome'];
    $_SESSION['usuario_email'] = $usuario['email'];
    $_SESSION['usuario_tipo'] = $tipo;
    $_SESSION['perfil']       = $perfil;

    header('Location: ../index.html');
    exit;

} catch (Throwable $e) {
    error_log('Erro no login: ' . $e->getMessage());
    falhar();
}
