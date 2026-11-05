<?php
// =====================================================
// api/interesses.php
// Devolve a lista de interesses do usuário logado (versão simplificada
// dos animais) para o dropdown do header (scripts/header_auth.js).
// =====================================================
session_start();
require __DIR__ . '/db_conn.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (empty($_SESSION['logado'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'erro' => 'nao_logado']);
    exit;
}

$idUsuario = (int) $_SESSION['usuario_id'];

try {
    $stmt = $conn->prepare(
        'SELECT a.id_animal, a.nome_animal, a.especie, a.status_adocao, a.foto_url
           FROM lista_interesse li
           JOIN lista_animal la ON la.id_lista  = li.id_lista
           JOIN animal a        ON a.id_animal = la.id_animal
          WHERE li.id_usuario = ?
          ORDER BY a.nome_animal'
    );
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $resultado = $stmt->get_result();

    $animais = [];
    while ($linha = $resultado->fetch_assoc()) {
        $linha['id_animal'] = (int) $linha['id_animal'];
        $animais[] = $linha;
    }
    $stmt->close();

    echo json_encode(['ok' => true, 'animais' => $animais], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('Erro ao listar interesses: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'servidor']);
}
