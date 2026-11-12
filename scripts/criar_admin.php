<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$email = trim((string)($argv[1] ?? ''));
$senha = (string)(getenv('ADMIN_PASSWORD') ?: '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Uso: php scripts/criar_admin.php email@exemplo.com\n");
    exit(1);
}
if (strlen($senha) < 8) {
    fwrite(STDERR, "Defina ADMIN_PASSWORD no ambiente com pelo menos 8 caracteres.\n");
    exit(1);
}

try {
    $db = getConnection();
    $stmt = $db->prepare("SELECT id FROM usuario WHERE email = :email AND status_conta = 'Ativa' LIMIT 1");
    $stmt->execute(['email' => $email]);
    $usuarioId = (int)$stmt->fetchColumn();
    if ($usuarioId <= 0) {
        throw new InvalidArgumentException('Não há uma conta ativa com esse e-mail.');
    }

    $stmt = $db->prepare('SELECT id_admin FROM admin WHERE id_usuario = :id LIMIT 1');
    $stmt->execute(['id' => $usuarioId]);
    if ($stmt->fetchColumn()) {
        fwrite(STDOUT, "A conta já é administradora.\n");
        exit;
    }

    $db->beginTransaction();
    $stmt = $db->prepare('UPDATE usuario SET senha = :senha WHERE id = :id');
    $stmt->execute(['senha' => password_hash($senha, PASSWORD_DEFAULT), 'id' => $usuarioId]);
    $adminId = (int)$db->query('SELECT COALESCE(MAX(id_admin), 0) + 1 FROM admin')->fetchColumn();
    $stmt = $db->prepare('INSERT INTO admin (id_admin, id_usuario, nivel_acesso, data_concessao) VALUES (:admin, :usuario, :nivel, CURRENT_DATE)');
    $stmt->execute(['admin' => $adminId, 'usuario' => $usuarioId, 'nivel' => 'A']);
    $db->commit();
    fwrite(STDOUT, "Conta promovida a administradora. Entre novamente para atualizar a sessão.\n");
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Adotai criar admin: ' . $e->getMessage());
    fwrite(STDERR, $e instanceof InvalidArgumentException ? $e->getMessage() . "\n" : "Não foi possível criar o administrador. Confira o banco e a migração.\n");
    exit(1);
}
