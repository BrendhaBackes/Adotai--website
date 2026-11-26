<?php
// =====================================================
// api/assinatura_cancelar.php
// Cancela uma assinatura do adotante logado (botão "Cancelar assinatura"
// de pages/meus_planos.html, chamado por scripts/meus_planos_handler.js).
//
// Só POST:  id_plano  (o id da linha de assinatura em plano_de_assinatura)
//
// O usuário NUNCA vem da requisição: sai da sessão, e a consulta confere que a
// assinatura é dele. Não há cobrança nem estorno (assinatura alegórica): o
// cancelamento só muda o status da linha para 'Cancelado', preservando o histórico.
//
// Resposta JSON:
//   { ok: true }  ou  { ok: false, erro: "codigo", mensagem: "texto para a tela" }
// =====================================================
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
ini_set('display_errors', '0');

function responder(int $status, array $dados): void {
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function recusar(int $status, string $erro, string $mensagem): void {
    responder($status, ['ok' => false, 'erro' => $erro, 'mensagem' => $mensagem]);
}

function idPositivo(string $campo): ?int {
    $valor = $_POST[$campo] ?? '';
    $valor = is_string($valor) ? trim($valor) : '';
    return ($valor !== '' && strlen($valor) <= 10 && ctype_digit($valor) && (int) $valor > 0)
        ? (int) $valor
        : null;
}

// ==================================================
// 1) QUEM ESTÁ PEDINDO? (só adotante logado)
// ==================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    recusar(405, 'metodo', 'Requisição inválida.');
}
if (empty($_SESSION['logado'])) {
    recusar(401, 'nao_logado', 'Sua sessão expirou. Entre novamente na sua conta.');
}
if (($_SESSION['usuario_tipo'] ?? '') !== 'adotante') {
    recusar(403, 'tipo_nao_permitido', 'Apenas contas de adotante podem cancelar assinaturas.');
}
// Protege contra requisições forjadas de outro site (o JS manda este cabeçalho)
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'fetch') {
    recusar(403, 'origem', 'Requisição não autorizada.');
}

$idUsuario = (int) ($_SESSION['usuario_id'] ?? 0);
session_write_close();

$idPlano = idPositivo('id_plano');
if ($idPlano === null) {
    recusar(422, 'id_plano', 'Assinatura inválida.');
}

// ==================================================
// 2) EXECUÇÃO
// ==================================================
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    require __DIR__ . '/db_conn.php';
    $conn->set_charset('utf8mb4');

    // A assinatura precisa existir, ser deste usuário e estar ativa
    $stmt = $conn->prepare(
        "SELECT status FROM plano_de_assinatura
          WHERE id_plano = ? AND tipo_registro = 'assinatura'
            AND (usuario_id = ? OR id_usuario = ?)"
    );
    $stmt->bind_param('iii', $idPlano, $idUsuario, $idUsuario);
    $stmt->execute();
    $assinatura = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$assinatura) {
        recusar(404, 'assinatura', 'Assinatura não encontrada.');
    }
    if ($assinatura['status'] !== 'Ativo') {
        recusar(409, 'ja_cancelada', 'Esta assinatura já foi cancelada. Atualize a página.');
    }

    $stmt = $conn->prepare(
        "UPDATE plano_de_assinatura SET status = 'Cancelado'
          WHERE id_plano = ? AND tipo_registro = 'assinatura' AND (usuario_id = ? OR id_usuario = ?)"
    );
    $stmt->bind_param('iii', $idPlano, $idUsuario, $idUsuario);
    $stmt->execute();
    $stmt->close();

    responder(200, ['ok' => true]);

} catch (Throwable $e) {
    error_log('Erro em api/assinatura_cancelar.php: ' . $e->getMessage());
    recusar(500, 'servidor', 'Não foi possível cancelar agora. Tente novamente.');
}
