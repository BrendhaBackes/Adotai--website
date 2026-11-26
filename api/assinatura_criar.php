<?php
// =====================================================
// api/assinatura_criar.php
// Botão "Assinar plano" de pages/catalogo_planos.html
// (scripts/catalogo_planos_handler.js).
//
// Só POST:  id_plano  (id de um plano do CATÁLOGO: tipo_registro = 'plano')
//
// Regras:
//   - só adotante ou protetor logado assina (veterinário e admin, não);
//   - o plano precisa existir, estar 'Ativo' e, se for de um veterinário, o veterinário
//     também precisa estar ativo (mesmas regras do api/planos.php);
//   - o tipo do usuário precisa bater com o público escolhido na criação do plano
//     (publico_alvo: 'adotante' | 'protetor' | 'ambos');
//   - não assina duas vezes o mesmo plano enquanto a assinatura estiver ativa.
//
// A assinatura é alegórica: não há pagamento. Ela vira uma NOVA linha em
// plano_de_assinatura (tipo_registro = 'assinatura') com os dados do plano copiados,
// ligada ao usuário da sessão (o usuário NUNCA vem da requisição).
//
// Resposta JSON:
//   { ok: true, id_assinatura, vencimento, mensagem }
//   { ok: false, erro: "codigo", mensagem: "texto para a tela" }
// =====================================================
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
ini_set('display_errors', '0');

// ==================================================
// CONFIGURAÇÕES  (edite aqui!)
// ==================================================
// Tipos de usuário que podem assinar. O tipo também é o valor de publico_alvo que o libera.
const TIPOS_PERMITIDOS = ['adotante', 'protetor'];

// Meses até o vencimento, conforme a cobrança. 'Pagamento único' e 'Gratuito' não vencem.
const MESES_POR_PERIODO = ['Mensal' => 1, 'Trimestral' => 3, 'Semestral' => 6, 'Anual' => 12];

const ROTULO_PUBLICO = ['adotante' => 'adotantes', 'protetor' => 'protetores'];

// ==================================================
// FUNÇÕES AUXILIARES
// ==================================================
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
// 1) QUEM ESTÁ PEDINDO?
// ==================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    recusar(405, 'metodo', 'Requisição inválida.');
}
if (empty($_SESSION['logado'])) {
    recusar(401, 'nao_logado', 'Entre na sua conta para assinar um plano.');
}

$tipoUsuario = $_SESSION['usuario_tipo'] ?? '';
if (!in_array($tipoUsuario, TIPOS_PERMITIDOS, true)) {
    recusar(403, 'tipo_nao_permitido', 'Apenas contas de adotante e protetor podem assinar planos.');
}
// Protege contra requisições forjadas de outro site (o JS manda este cabeçalho)
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'fetch') {
    recusar(403, 'origem', 'Requisição não autorizada.');
}

$idUsuario = (int) ($_SESSION['usuario_id'] ?? 0);
session_write_close();

$idPlano = idPositivo('id_plano');
if ($idPlano === null) {
    recusar(422, 'id_plano', 'Plano inválido.');
}

// ==================================================
// 2) EXECUÇÃO
// ==================================================
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    require __DIR__ . '/db_conn.php';
    $conn->set_charset('utf8mb4');

    // O plano precisa estar no catálogo e disponível (mesmas regras do api/planos.php)
    $stmt = $conn->prepare(
        "SELECT p.nome, p.valor, p.id_veterinario, p.descricao, p.periodicidade,
                p.beneficios, p.destaque, p.ordem, p.publico_alvo
           FROM plano_de_assinatura p
      LEFT JOIN veterinario v ON v.id_veterinario = p.id_veterinario
          WHERE p.id_plano = ?
            AND p.tipo_registro = 'plano'
            AND p.status = 'Ativo'
            AND (p.id_veterinario IS NULL OR v.status = 'Ativo')"
    );
    $stmt->bind_param('i', $idPlano);
    $stmt->execute();
    $plano = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$plano) {
        recusar(404, 'plano', 'Este plano não está mais disponível.');
    }

    // O público do plano precisa incluir o tipo do usuário
    if ($plano['publico_alvo'] !== 'ambos' && $plano['publico_alvo'] !== $tipoUsuario) {
        $so = ROTULO_PUBLICO[$plano['publico_alvo']] ?? 'outro público';
        recusar(403, 'publico_incompativel', "Este plano é exclusivo para $so.");
    }

    // Não assina duas vezes. O catálogo e a assinatura são linhas separadas, sem ligação por id:
    // o plano é reconhecido por nome + veterinário (<=> compara também NULL = NULL).
    $idVeterinario = $plano['id_veterinario'] === null ? null : (int) $plano['id_veterinario'];

    $stmt = $conn->prepare(
        "SELECT 1 FROM plano_de_assinatura
          WHERE tipo_registro = 'assinatura' AND status = 'Ativo'
            AND (usuario_id = ? OR id_usuario = ?)
            AND nome = ? AND id_veterinario <=> ?"
    );
    $stmt->bind_param('iisi', $idUsuario, $idUsuario, $plano['nome'], $idVeterinario);
    $stmt->execute();
    $jaAssinou = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if ($jaAssinou) {
        recusar(409, 'ja_assinado', 'Você já tem uma assinatura ativa deste plano.');
    }

    // Vencimento: hoje + período (sem vencimento para pagamento único e gratuito)
    $meses = MESES_POR_PERIODO[$plano['periodicidade'] ?? ''] ?? null;
    $vencimento = $meses === null
        ? null
        : (new DateTime('today'))->modify("+$meses month")->format('Y-m-d');

    // Grava a assinatura: uma cópia do plano ligada ao usuário
    $destaque = (int) $plano['destaque'];
    $ordem    = (int) $plano['ordem'];

    $stmt = $conn->prepare(
        "INSERT INTO plano_de_assinatura
            (usuario_id, id_usuario, vencimento, nome, valor, id_veterinario, tipo_registro,
             descricao, periodicidade, beneficios, destaque, status, ordem, publico_alvo)
         VALUES (?, ?, ?, ?, ?, ?, 'assinatura', ?, ?, ?, ?, 'Ativo', ?, ?)"
    );
    $stmt->bind_param(
        'iisssisssiis',
        $idUsuario, $idUsuario, $vencimento, $plano['nome'], $plano['valor'], $idVeterinario,
        $plano['descricao'], $plano['periodicidade'], $plano['beneficios'], $destaque, $ordem,
        $plano['publico_alvo']
    );
    $stmt->execute();
    $idAssinatura = $conn->insert_id;
    $stmt->close();

    responder(201, [
        'ok'            => true,
        'id_assinatura' => $idAssinatura,
        'vencimento'    => $vencimento,
        'mensagem'      => 'Plano assinado com sucesso!',
    ]);

} catch (Throwable $e) {
    error_log('Erro em api/assinatura_criar.php: ' . $e->getMessage());
    recusar(500, 'servidor', 'Não foi possível assinar agora. Tente novamente.');
}
