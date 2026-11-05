<?php
// =====================================================
// api/interesse.php
// Botão "Quero adotar": registra o animal na lista de interesse
// do usuário logado (tabelas lista_interesse e lista_animal).
// Recebe por POST: id_animal
// =====================================================
session_start();
require __DIR__ . '/db_conn.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Quais tipos de usuário podem demonstrar interesse
// (para testar com outra conta, acrescente o tipo aqui: 'admin', 'protetor'...)
const TIPOS_PERMITIDOS = ['adotante'];

function responder(int $status, array $dados): void {
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(405, ['ok' => false, 'erro' => 'metodo', 'mensagem' => 'Requisição inválida.']);
}

if (empty($_SESSION['logado'])) {
    responder(401, ['ok' => false, 'erro' => 'nao_logado', 'mensagem' => 'Entre na sua conta para adotar.']);
}

if (!in_array($_SESSION['usuario_tipo'] ?? '', TIPOS_PERMITIDOS, true)) {
    responder(403, ['ok' => false, 'erro' => 'tipo_nao_permitido',
                    'mensagem' => 'Apenas contas de adotante podem demonstrar interesse em adoção.']);
}

$idUsuario = (int) $_SESSION['usuario_id'];
$idAnimal  = (int) ($_POST['id_animal'] ?? -1);

try {
    // O animal precisa existir, estar aprovado e disponível
    $stmt = $conn->prepare("SELECT status_adocao FROM animal WHERE id_animal = ? AND status_aprovacao = 'Aprovado'");
    $stmt->bind_param('i', $idAnimal);
    $stmt->execute();
    $animal = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$animal) {
        responder(404, ['ok' => false, 'erro' => 'animal', 'mensagem' => 'Animal não encontrado.']);
    }
    if ($animal['status_adocao'] !== 'Disponível') {
        responder(409, ['ok' => false, 'erro' => 'indisponivel', 'mensagem' => 'Este animal não está mais disponível.']);
    }

    $conn->begin_transaction();

    // Cada usuário tem uma lista de interesse (lista_interesse.id_usuario é único)
    $stmt = $conn->prepare('SELECT id_lista FROM lista_interesse WHERE id_usuario = ?');
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $lista = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($lista) {
        $idLista = (int) $lista['id_lista'];
    } else {
        $stmt = $conn->prepare('INSERT INTO lista_interesse (id_usuario) VALUES (?)');
        $stmt->bind_param('i', $idUsuario);
        $stmt->execute();
        $idLista = $conn->insert_id;
        $stmt->close();
    }

    // Já está na lista?
    $stmt = $conn->prepare('SELECT 1 FROM lista_animal WHERE id_lista = ? AND id_animal = ?');
    $stmt->bind_param('ii', $idLista, $idAnimal);
    $stmt->execute();
    $jaTem = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if (!$jaTem) {
        $stmt = $conn->prepare('INSERT INTO lista_animal (id_lista, id_animal) VALUES (?, ?)');
        $stmt->bind_param('ii', $idLista, $idAnimal);
        $stmt->execute();
        $stmt->close();
    }

    $conn->commit();
    responder(200, ['ok' => true, 'ja_registrado' => $jaTem]);

} catch (Throwable $e) {
    try { $conn->rollback(); } catch (Throwable $ignorado) {}
    error_log('Erro ao registrar interesse: ' . $e->getMessage());
    responder(500, ['ok' => false, 'erro' => 'servidor', 'mensagem' => 'Não foi possível registrar agora. Tente novamente.']);
}
