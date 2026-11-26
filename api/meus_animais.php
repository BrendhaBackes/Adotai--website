<?php
// =====================================================
// api/meus_animais.php
// Devolve em JSON os animais cadastrados pelo protetor logado,
// para a aba "Meus animais" de pages/protetor_painel.html
// (scripts/protetor_painel_handler.js monta os cards).
//
// GET, sem parâmetros.
// O id do protetor NUNCA vem da requisição: ele é descoberto pela conta
// logada (sessão -> tabela protetor), igual ao api/animal_cadastrar.php.
// Assim, um protetor jamais consegue ver os animais de outro.
//
// Resposta:
//   { ok: true, animais: [ { id_animal, nome_animal, descricao, especie, raca, idade_meses,
//       sexo, porte, cor, castrado, vacinado, vermifugado, cidade, uf, status_adocao,
//       data_cadastro, foto_url, status_aprovacao, observacao_admin } ] }
//   { ok: false, erro: "codigo", mensagem: "texto para a tela" }
//
// Aparecem animais de QUALQUER status de aprovação (Pendente, Aprovado, Recusado):
// o protetor precisa acompanhar o que ainda espera o admin e ler o motivo de uma recusa.
// =====================================================
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Avisos do PHP escritos na tela quebrariam o JSON: eles vão só para o log de erros
ini_set('display_errors', '0');

// ==================================================
// FUNÇÕES AUXILIARES
// ==================================================
function responder(int $status, array $dados): void {
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

// erro => código curto (para o programador) | mensagem => texto que o protetor lê na tela
function recusar(int $status, string $erro, string $mensagem): void {
    responder($status, ['ok' => false, 'erro' => $erro, 'mensagem' => $mensagem]);
}

// ==================================================
// 1) QUEM ESTÁ PEDINDO? (só protetor logado)
// ==================================================
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    recusar(405, 'metodo', 'Requisição inválida.');
}

if (empty($_SESSION['logado'])) {
    recusar(401, 'nao_logado', 'Sua sessão expirou. Entre novamente na sua conta.');
}

if (($_SESSION['usuario_tipo'] ?? '') !== 'protetor') {
    recusar(403, 'tipo_nao_permitido', 'Apenas contas de protetor podem ver esta lista.');
}

$idUsuario = (int) ($_SESSION['usuario_id'] ?? 0);

// Libera a sessão já (não vamos mais escrever nela), para não travar outros pedidos do painel
session_write_close();

// ==================================================
// 2) BUSCA NO BANCO
// ==================================================
// Faz o mysqli lançar exceções (inclusive se o banco estiver fora do ar, antes do die() do db_conn.php)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    require __DIR__ . '/db_conn.php';
    $conn->set_charset('utf8mb4');

    // O protetor é descoberto pela conta logada (nunca pela requisição)
    $stmt = $conn->prepare('SELECT id_protetor FROM protetor WHERE id_usuario = ?');
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $protetor = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$protetor) {
        recusar(403, 'sem_protetor', 'Não encontramos o seu perfil de protetor.');
    }
    $idProtetor = (int) $protetor['id_protetor'];

    // Só os animais ligados a este protetor, do mais novo para o mais antigo
    $stmt = $conn->prepare(
        'SELECT id_animal, nome_animal, descricao, especie, raca, idade_meses, sexo, porte, cor,
                castrado, vacinado, vermifugado, cidade, uf, status_adocao, data_cadastro,
                foto_url, status_aprovacao, observacao_admin
           FROM animal
          WHERE id_protetor = ?
          ORDER BY data_cadastro DESC, id_animal DESC'
    );
    $stmt->bind_param('i', $idProtetor);
    $stmt->execute();
    $resultado = $stmt->get_result();

    $animais = [];
    while ($linha = $resultado->fetch_assoc()) {
        $linha['id_animal']   = (int) $linha['id_animal'];
        $linha['idade_meses'] = $linha['idade_meses'] === null ? null : (int) $linha['idade_meses'];
        $linha['castrado']    = (int) $linha['castrado'];
        $linha['vacinado']    = (int) $linha['vacinado'];
        $linha['vermifugado'] = (int) $linha['vermifugado'];
        $animais[] = $linha;
    }
    $stmt->close();

    responder(200, ['ok' => true, 'animais' => $animais]);

} catch (Throwable $e) {
    error_log('Erro em api/meus_animais.php: ' . $e->getMessage());
    recusar(500, 'servidor', 'Não foi possível carregar os seus animais agora. Tente novamente.');
}
