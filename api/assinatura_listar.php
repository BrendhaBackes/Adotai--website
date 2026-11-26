<?php
// =====================================================
// api/assinatura_listar.php
// Devolve em JSON os planos ASSINADOS pelo adotante logado, para a página
// pages/meus_planos.html (scripts/meus_planos_handler.js monta os cards).
//
// GET, sem parâmetros. O usuário vem da sessão, nunca da requisição:
// ninguém consegue listar as assinaturas de outra pessoa.
//
// Uma assinatura é uma linha de plano_de_assinatura com tipo_registro = 'assinatura'
// (as linhas 'plano' são o catálogo). Só aparecem assinaturas com status 'Ativo'.
//
// Resposta:
//   { ok: true, assinaturas: [ { id_plano, nome, descricao, valor, periodicidade, beneficios[],
//       publico_alvo, vencimento, status, veterinario, cidade, uf } ] }
//   { ok: false, erro: "codigo", mensagem: "texto para a tela" }
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

// ==================================================
// 1) QUEM ESTÁ PEDINDO? (só adotante logado)
// ==================================================
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    recusar(405, 'metodo', 'Requisição inválida.');
}
if (empty($_SESSION['logado'])) {
    recusar(401, 'nao_logado', 'Sua sessão expirou. Entre novamente na sua conta.');
}
if (($_SESSION['usuario_tipo'] ?? '') !== 'adotante') {
    recusar(403, 'tipo_nao_permitido', 'Apenas contas de adotante têm planos assinados.');
}

$idUsuario = (int) ($_SESSION['usuario_id'] ?? 0);
session_write_close();

// ==================================================
// 2) BUSCA NO BANCO
// ==================================================
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    require __DIR__ . '/db_conn.php';
    $conn->set_charset('utf8mb4');

    // A tabela tem duas colunas de usuário (usuario_id e id_usuario): vale qualquer uma delas
    $stmt = $conn->prepare(
        "SELECT s.id_plano, s.nome, s.descricao, s.valor, s.periodicidade, s.beneficios,
                s.publico_alvo, s.vencimento, s.status,
                v.nome AS veterinario, v.cidade, v.uf
           FROM plano_de_assinatura s
      LEFT JOIN veterinario v ON v.id_veterinario = s.id_veterinario
          WHERE s.tipo_registro = 'assinatura'
            AND s.status = 'Ativo'
            AND (s.usuario_id = ? OR s.id_usuario = ?)
          ORDER BY s.id_plano DESC"
    );
    $stmt->bind_param('ii', $idUsuario, $idUsuario);
    $stmt->execute();
    $resultado = $stmt->get_result();

    $assinaturas = [];
    while ($linha = $resultado->fetch_assoc()) {
        // beneficios é um TEXT com um item por linha -> vira lista (sem linhas vazias)
        $itens = preg_split('/\R/u', (string) $linha['beneficios']) ?: [];
        $itens = array_values(array_filter(array_map('trim', $itens), 'strlen'));

        $assinaturas[] = [
            'id_plano'      => (int) $linha['id_plano'],
            'nome'          => $linha['nome'],
            'descricao'     => $linha['descricao'] ?? '',
            'valor'         => (float) $linha['valor'],
            'periodicidade' => $linha['periodicidade'],
            'beneficios'    => $itens,
            'publico_alvo'  => $linha['publico_alvo'],
            'vencimento'    => $linha['vencimento'],
            'status'        => $linha['status'],
            'veterinario'   => $linha['veterinario'],
            'cidade'        => $linha['cidade'],
            'uf'            => $linha['uf'],
        ];
    }
    $stmt->close();

    responder(200, ['ok' => true, 'assinaturas' => $assinaturas]);

} catch (Throwable $e) {
    error_log('Erro em api/assinatura_listar.php: ' . $e->getMessage());
    recusar(500, 'servidor', 'Não foi possível carregar os seus planos agora. Tente novamente.');
}
