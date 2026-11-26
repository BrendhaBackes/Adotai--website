<?php
// =====================================================
// api/plano_listar.php
// Devolve os planos cadastrados pelo veterinário logado, para a aba
// "Meus planos" de pages/cadastrar_plano.html (scripts/cadastrar_plano_handler.js).
//
// Responde por GET, em JSON:
//   { "ok": true, "planos": [
//       { "id_plano": 4, "nome": "...", "descricao": "...", "valor": 49.9,
//         "periodicidade": "Mensal", "beneficios": ["...", "..."],
//         "destaque": true, "status": "Ativo", "publico_alvo": "ambos" }, ... ] }
//
// O veterinário é descoberto pela conta logada (sessão), nunca por parâmetro da URL:
// ninguém consegue listar os planos de outro veterinário.
// Só entram os planos do catálogo (tipo_registro = 'plano'), do mais novo para o mais antigo.
// =====================================================
session_start();
require __DIR__ . '/db_conn.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function responder(int $status, array $dados): void {
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

// erro => código curto (para o programador) | mensagem => texto que o veterinário lê na tela
function recusar(int $status, string $erro, string $mensagem): void {
    responder($status, ['ok' => false, 'erro' => $erro, 'mensagem' => $mensagem]);
}

// ==================================================
// 1) QUEM ESTÁ PEDINDO? (só veterinário logado)
// ==================================================
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    recusar(405, 'metodo', 'Requisição inválida.');
}

if (empty($_SESSION['logado'])) {
    recusar(401, 'nao_logado', 'Sua sessão expirou. Entre novamente na sua conta.');
}

if (($_SESSION['usuario_tipo'] ?? '') !== 'veterinario') {
    recusar(403, 'tipo_nao_permitido', 'Apenas contas de veterinário têm planos cadastrados.');
}

$idUsuario = (int) $_SESSION['usuario_id'];

// ==================================================
// 2) BUSCA OS PLANOS DO VETERINÁRIO
// ==================================================
try {
    $stmt = $conn->prepare(
        "SELECT p.id_plano, p.nome, p.descricao, p.valor, p.periodicidade,
                p.beneficios, p.destaque, p.status, p.publico_alvo
           FROM plano_de_assinatura p
           JOIN veterinario v ON v.id_veterinario = p.id_veterinario
          WHERE v.id_usuario = ?
            AND p.tipo_registro = 'plano'
          ORDER BY p.id_plano DESC"
    );
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $resultado = $stmt->get_result();

    $planos = [];
    while ($linha = $resultado->fetch_assoc()) {
        // beneficios é um TEXT com um item por linha -> vira lista (sem linhas vazias)
        $itens = preg_split('/\R/u', (string) $linha['beneficios']) ?: [];
        $itens = array_values(array_filter(array_map('trim', $itens), 'strlen'));

        $planos[] = [
            'id_plano'      => (int) $linha['id_plano'],
            'nome'          => $linha['nome'],
            'descricao'     => $linha['descricao'] ?? '',
            'valor'         => (float) $linha['valor'],
            'periodicidade' => $linha['periodicidade'],
            'beneficios'    => $itens,
            'destaque'      => (bool) $linha['destaque'],
            'status'        => $linha['status'],
            'publico_alvo'  => $linha['publico_alvo'],
        ];
    }
    $stmt->close();

    responder(200, ['ok' => true, 'planos' => $planos]);

} catch (Throwable $e) {
    error_log('Erro ao listar planos do veterinário: ' . $e->getMessage());
    recusar(500, 'servidor', 'Não foi possível carregar os seus planos agora. Tente novamente.');
}
