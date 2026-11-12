<?php
// =====================================================
// api/admin.php
// Back-end do painel do administrador (pages/admin_painel.html),
// chamado por scripts/painel_admin_handler.js.
//
// Uma única porta de entrada; a ação vem na URL (?acao=...):
//
//   GET  ?acao=animais            -> todos os animais, de qualquer status de aprovação
//   POST ?acao=animal_status      -> id_animal, status_aprovacao ('Aprovado'|'Recusado'), observacao_admin
//   GET  ?acao=protetores         -> todos os protetores (pessoas e ONGs)
//   POST ?acao=protetor_status    -> id_protetor, status_aprovacao ('Aprovado'|'Recusado')
//   GET  ?acao=suporte            -> todos os chamados de suporte
//   POST ?acao=suporte_responder  -> id, resposta
//
// Toda resposta é JSON:
//   { ok: true, ... }   ou   { ok: false, erro: "codigo", mensagem: "texto para a tela" }
//
// Só atende quem está logado como admin (sessão) e ainda consta como admin ativo no banco.
// =====================================================
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Avisos do PHP escritos na tela quebrariam o JSON: eles vão só para o log de erros
ini_set('display_errors', '0');

// ==================================================
// CONFIGURAÇÕES  (edite aqui!)
// ==================================================
const STATUS_DECISAO = ['Aprovado', 'Recusado'];   // decisões que o admin pode tomar
const OBSERVACAO_MAX = 1000;                       // mesmo limite do campo no painel
const RESPOSTA_MAX   = 1000;                       // suporte.resposta é VARCHAR(1000)

// ==================================================
// FUNÇÕES AUXILIARES
// ==================================================
function responder(int $status, array $dados): void {
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

// erro => código curto (para o programador) | mensagem => texto que o admin lê na tela
function recusar(int $status, string $erro, string $mensagem): void {
    responder($status, ['ok' => false, 'erro' => $erro, 'mensagem' => $mensagem]);
}

// Texto enviado por POST, sem espaços nas pontas ('' se não veio ou veio como lista)
function texto(string $campo): string {
    $valor = $_POST[$campo] ?? '';
    return is_string($valor) ? trim($valor) : '';
}

// Número inteiro positivo enviado por POST (null se vazio ou inválido)
function idPositivo(string $campo): ?int {
    $valor = texto($campo);
    return ($valor !== '' && strlen($valor) <= 10 && ctype_digit($valor) && (int) $valor > 0)
        ? (int) $valor
        : null;
}

// 41990000001 -> (41) 99000-0001   (se não tiver 10 ou 11 dígitos, devolve como está)
function formatarTelefone(?string $telefone): ?string {
    if ($telefone === null || $telefone === '') {
        return null;
    }
    $d = preg_replace('/\D/', '', $telefone);
    if (strlen($d) === 11) {
        return sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 5), substr($d, 7));
    }
    if (strlen($d) === 10) {
        return sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 4), substr($d, 6));
    }
    return $telefone;
}

// 12345678000199 -> 12.345.678/0001-99  |  12345678909 -> 123.456.789-09
function formatarDocumento(?string $doc): ?string {
    if ($doc === null || $doc === '') {
        return null;
    }
    $d = preg_replace('/\D/', '', $doc);
    if (strlen($d) === 14) {
        return sprintf('%s.%s.%s/%s-%s', substr($d, 0, 2), substr($d, 2, 3), substr($d, 5, 3), substr($d, 8, 4), substr($d, 12));
    }
    if (strlen($d) === 11) {
        return sprintf('%s.%s.%s-%s', substr($d, 0, 3), substr($d, 3, 3), substr($d, 6, 3), substr($d, 9));
    }
    return $doc;
}

// ==================================================
// 1) QUEM ESTÁ PEDINDO? (só admin logado)
// ==================================================
if (empty($_SESSION['logado'])) {
    recusar(401, 'nao_logado', 'Sua sessão expirou. Entre novamente na sua conta.');
}

if (($_SESSION['usuario_tipo'] ?? '') !== 'admin') {
    recusar(403, 'tipo_nao_permitido', 'Apenas administradores podem acessar este painel.');
}

$idUsuario = (int) ($_SESSION['usuario_id'] ?? 0);

// Libera a sessão já: o painel faz 3 pedidos ao mesmo tempo e, sem isto, o PHP os atenderia um por vez
session_write_close();

// ==================================================
// 2) QUAL AÇÃO? (e com qual método HTTP)
// ==================================================
$rotas = [
    'animais'           => ['GET',  'listarAnimais'],
    'animal_status'     => ['POST', 'decidirAnimal'],
    'protetores'        => ['GET',  'listarProtetores'],
    'protetor_status'   => ['POST', 'decidirProtetor'],
    'suporte'           => ['GET',  'listarChamados'],
    'suporte_responder' => ['POST', 'responderChamado'],
];

$acao = isset($_GET['acao']) && is_string($_GET['acao']) ? $_GET['acao'] : '';

if (!isset($rotas[$acao])) {
    recusar(404, 'acao', 'Ação desconhecida.');
}

[$metodo, $funcao] = $rotas[$acao];

if ($_SERVER['REQUEST_METHOD'] !== $metodo) {
    recusar(405, 'metodo', 'Requisição inválida.');
}

// Alterações só vêm do painel (o JS manda este cabeçalho; outro site não consegue enviá-lo
// sem permissão do navegador). Protege as ações que gravam contra requisições forjadas.
if ($metodo === 'POST' && ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'fetch') {
    recusar(403, 'origem', 'Requisição não autorizada.');
}

// ==================================================
// 3) AÇÕES DE LEITURA
// ==================================================

// Todos os animais, com o nome de quem os cadastrou (protetor ou ONG)
function listarAnimais(mysqli $conn): void {
    $resultado = $conn->query(
        "SELECT a.id_animal, a.nome_animal, a.descricao, a.especie, a.raca, a.idade_meses,
                a.sexo, a.porte, a.cor, a.castrado, a.vacinado, a.vermifugado, a.condicao_saude,
                a.compatibilidade_criancas, a.compatibilidade_animais, a.cidade, a.uf,
                a.status_adocao, a.data_cadastro, a.foto_url, a.status_aprovacao, a.observacao_admin,
                COALESCE(NULLIF(p.nome_organizacao, ''), NULLIF(u.nome, ''), u.email) AS protetor_nome
           FROM animal a
           JOIN protetor p ON p.id_protetor = a.id_protetor
           JOIN usuario  u ON u.id          = p.id_usuario
          ORDER BY a.data_cadastro DESC, a.id_animal DESC"
    );

    $animais = [];
    while ($linha = $resultado->fetch_assoc()) {
        $linha['id_animal']   = (int) $linha['id_animal'];
        $linha['idade_meses'] = $linha['idade_meses'] === null ? null : (int) $linha['idade_meses'];
        $linha['castrado']    = (int) $linha['castrado'];
        $linha['vacinado']    = (int) $linha['vacinado'];
        $linha['vermifugado'] = (int) $linha['vermifugado'];
        $animais[] = $linha;
    }

    responder(200, ['ok' => true, 'animais' => $animais]);
}

// Todos os protetores. A tabela protetor não guarda nome nem data de cadastro, então:
//   nome / data  -> vêm da conta (usuario);
//   e-mail / telefone -> contato do protetor, ou o da conta se o contato estiver vazio;
//   cidade / UF  -> endereco (igual a api/animal_cadastrar.php), ou o do perfil;
//   documento    -> CNPJ se for ONG (tabela ongs), senão CPF.
function listarProtetores(mysqli $conn): void {
    $resultado = $conn->query(
        "SELECT p.id_protetor,
                COALESCE(NULLIF(u.nome, ''), u.email)                             AS nome,
                NULLIF(p.nome_organizacao, '')                                    AS nome_organizacao,
                (o.id_ong IS NOT NULL)                                            AS eh_ong,
                COALESCE(NULLIF(p.email_contato, ''), u.email)                    AS email,
                COALESCE(NULLIF(p.telefone_contato, ''), NULLIF(u.telefone, ''))  AS telefone,
                COALESCE(NULLIF(e.cidade, ''), NULLIF(p.cidade, ''))              AS cidade,
                COALESCE(NULLIF(e.uf, ''), NULLIF(p.uf, ''))                      AS uf,
                CASE WHEN o.id_ong IS NOT NULL THEN o.cnpj ELSE p.cpf END         AS documento,
                u.data_criacao_conta                                              AS data_cadastro,
                p.status_aprovacao
           FROM protetor p
           JOIN usuario u       ON u.id          = p.id_usuario
           LEFT JOIN ongs o     ON o.id_protetor = p.id_protetor
           LEFT JOIN endereco e ON e.id_usuario  = p.id_usuario
          ORDER BY u.data_criacao_conta DESC, p.id_protetor DESC"
    );

    $protetores = [];
    while ($linha = $resultado->fetch_assoc()) {
        $linha['id_protetor'] = (int) $linha['id_protetor'];
        $linha['eh_ong']      = (int) $linha['eh_ong'];
        $linha['telefone']    = formatarTelefone($linha['telefone']);
        $linha['documento']   = formatarDocumento($linha['documento']);
        $protetores[] = $linha;
    }

    responder(200, ['ok' => true, 'protetores' => $protetores]);
}

// Todos os chamados, com quem abriu. "resposta" vem null enquanto o chamado está em aberto.
function listarChamados(mysqli $conn): void {
    $resultado = $conn->query(
        "SELECT s.id, s.titulo, s.descricao, NULLIF(s.resposta, '') AS resposta, s.data_abertura,
                NULLIF(u.nome, '') AS usuario_nome, u.email AS usuario_email
           FROM suporte s
           JOIN usuario u ON u.id = s.usuario_id
          ORDER BY s.data_abertura DESC, s.id DESC"
    );

    $chamados = [];
    while ($linha = $resultado->fetch_assoc()) {
        $linha['id'] = (int) $linha['id'];
        $chamados[] = $linha;
    }

    responder(200, ['ok' => true, 'chamados' => $chamados]);
}

// ==================================================
// 4) AÇÕES QUE GRAVAM
// ==================================================

// Aprova ou recusa um animal. Recusar exige a observação (o protetor lê o motivo).
// Aprovar sem observação limpa o motivo de uma recusa anterior (igual ao que o painel mostra).
function decidirAnimal(mysqli $conn): void {
    $idAnimal   = idPositivo('id_animal');
    $status     = texto('status_aprovacao');
    $observacao = texto('observacao_admin');

    if ($idAnimal === null) {
        recusar(422, 'id_animal', 'Animal inválido.');
    }
    if (!in_array($status, STATUS_DECISAO, true)) {
        recusar(422, 'status_aprovacao', 'Decisão inválida.');
    }
    if (mb_strlen($observacao) > OBSERVACAO_MAX) {
        recusar(422, 'observacao_admin', 'A observação deve ter no máximo ' . OBSERVACAO_MAX . ' caracteres.');
    }
    if ($status === 'Recusado' && $observacao === '') {
        recusar(422, 'observacao_admin', 'Explique o motivo da recusa para o protetor.');
    }

    $stmt = $conn->prepare('SELECT 1 FROM animal WHERE id_animal = ?');
    $stmt->bind_param('i', $idAnimal);
    $stmt->execute();
    $existe = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if (!$existe) {
        recusar(404, 'animal', 'Animal não encontrado.');
    }

    $observacaoBd = $observacao === '' ? null : $observacao;

    $stmt = $conn->prepare('UPDATE animal SET status_aprovacao = ?, observacao_admin = ? WHERE id_animal = ?');
    $stmt->bind_param('ssi', $status, $observacaoBd, $idAnimal);
    $stmt->execute();
    $stmt->close();

    responder(200, ['ok' => true]);
}

// Aprova ou recusa o cadastro de um protetor
function decidirProtetor(mysqli $conn): void {
    $idProtetor = idPositivo('id_protetor');
    $status     = texto('status_aprovacao');

    if ($idProtetor === null) {
        recusar(422, 'id_protetor', 'Protetor inválido.');
    }
    if (!in_array($status, STATUS_DECISAO, true)) {
        recusar(422, 'status_aprovacao', 'Decisão inválida.');
    }

    $stmt = $conn->prepare('SELECT 1 FROM protetor WHERE id_protetor = ?');
    $stmt->bind_param('i', $idProtetor);
    $stmt->execute();
    $existe = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if (!$existe) {
        recusar(404, 'protetor', 'Protetor não encontrado.');
    }

    $stmt = $conn->prepare('UPDATE protetor SET status_aprovacao = ? WHERE id_protetor = ?');
    $stmt->bind_param('si', $status, $idProtetor);
    $stmt->execute();
    $stmt->close();

    responder(200, ['ok' => true]);
}

// Grava a resposta de um chamado. Chamado que já foi respondido não é sobrescrito.
function responderChamado(mysqli $conn): void {
    $idChamado = idPositivo('id');
    $resposta  = texto('resposta');

    if ($idChamado === null) {
        recusar(422, 'id', 'Chamado inválido.');
    }
    if ($resposta === '') {
        recusar(422, 'resposta', 'Escreva a resposta antes de enviar.');
    }
    if (mb_strlen($resposta) > RESPOSTA_MAX) {
        recusar(422, 'resposta', 'A resposta deve ter no máximo ' . RESPOSTA_MAX . ' caracteres.');
    }

    $stmt = $conn->prepare('SELECT resposta FROM suporte WHERE id = ?');
    $stmt->bind_param('i', $idChamado);
    $stmt->execute();
    $chamado = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$chamado) {
        recusar(404, 'chamado', 'Chamado não encontrado.');
    }
    if ($chamado['resposta'] !== null && $chamado['resposta'] !== '') {
        recusar(409, 'ja_respondido', 'Este chamado já foi respondido. Atualize a página.');
    }

    $stmt = $conn->prepare('UPDATE suporte SET resposta = ? WHERE id = ?');
    $stmt->bind_param('si', $resposta, $idChamado);
    $stmt->execute();
    $stmt->close();

    responder(200, ['ok' => true]);
}

// ==================================================
// 5) CONEXÃO COM O BANCO E EXECUÇÃO
// ==================================================
// Faz o mysqli lançar exceções (inclusive se o banco estiver fora do ar, antes do die() do db_conn.php)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    require __DIR__ . '/db_conn.php';
    $conn->set_charset('utf8mb4');

    // A sessão diz que é admin, mas o banco manda: se o acesso foi revogado
    // (ou a conta suspensa) depois do login, a pessoa perde o acesso na hora.
    $stmt = $conn->prepare(
        "SELECT 1
           FROM admin a
           JOIN usuario u ON u.id = a.id_usuario
          WHERE a.id_usuario = ? AND u.status_conta = 'Ativa'"
    );
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $aindaEhAdmin = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if (!$aindaEhAdmin) {
        recusar(403, 'tipo_nao_permitido', 'Seu acesso de administrador não está mais ativo.');
    }

    $funcao($conn);

} catch (Throwable $e) {
    error_log('Erro em api/admin.php (acao=' . $acao . '): ' . $e->getMessage());
    recusar(500, 'servidor', 'Não foi possível concluir agora. Tente novamente.');
}
