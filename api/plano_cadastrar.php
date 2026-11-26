<?php
// =====================================================
// api/plano_cadastrar.php
// Recebe o formulário de pages/cadastrar_plano.html e cadastra
// o plano na tabela `plano_de_assinatura`, ligado ao veterinário logado.
//
// Recebe por POST (multipart/form-data):
//   nome, descricao, publico_alvo, valor, periodicidade, beneficios, destaque
//
// O id do veterinário NUNCA vem do formulário: sai da sessão / do banco.
//
// >>> ANTES DE USAR, rode UMA VEZ no banco (a tabela ainda não tem essa coluna): <<<
//
//   ALTER TABLE plano_de_assinatura
//     ADD COLUMN publico_alvo ENUM('adotante','protetor','ambos') NOT NULL DEFAULT 'ambos' AFTER ordem;
//
// (e acrescente a mesma linha em database/adotai_site_estrutura.sql, dentro do CREATE TABLE)
// =====================================================
session_start();
require __DIR__ . '/db_conn.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// ==================================================
// CONFIGURAÇÕES  (edite aqui!)
// ==================================================
const MAX_BENEFICIOS      = 15;    // igual ao limite do JS
const MAX_CHARS_BENEFICIO = 100;   // igual ao limite do JS

const PUBLICOS_ACEITOS      = ['adotante', 'protetor', 'ambos'];
const PERIODICIDADES_ACEITAS = ['Mensal', 'Trimestral', 'Semestral', 'Anual', 'Pagamento único'];

// ==================================================
// FUNÇÕES AUXILIARES
// ==================================================
function responder(int $status, array $dados): void {
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

// erro => código curto (para o programador) | mensagem => texto que o veterinário lê na tela
function recusar(int $status, string $erro, string $mensagem): void {
    responder($status, ['ok' => false, 'erro' => $erro, 'mensagem' => $mensagem]);
}

// Texto enviado por POST, sem espaços nas pontas ('' se não veio ou veio como lista)
function texto(string $campo): string {
    $valor = $_POST[$campo] ?? '';
    return is_string($valor) ? trim($valor) : '';
}

// ==================================================
// 1) QUEM ESTÁ ENVIANDO? (só veterinário logado)
// ==================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    recusar(405, 'metodo', 'Requisição inválida.');
}

if (empty($_SESSION['logado'])) {
    recusar(401, 'nao_logado', 'Sua sessão expirou. Entre novamente na sua conta.');
}

if (($_SESSION['usuario_tipo'] ?? '') !== 'veterinario') {
    recusar(403, 'tipo_nao_permitido', 'Apenas contas de veterinário podem cadastrar planos.');
}

$idUsuario = (int) $_SESSION['usuario_id'];

// ==================================================
// 2) VALIDA OS CAMPOS (o JS já conferiu, mas o servidor confere de novo)
// ==================================================
$nome          = texto('nome');
$descricao     = texto('descricao');
$publicoAlvo   = texto('publico_alvo');
$valor         = str_replace(',', '.', texto('valor'));   // aceita "49,90" e "49.90"
$periodicidade = texto('periodicidade');

if ($nome === '' || mb_strlen($nome) > 50) {
    recusar(422, 'nome', 'Informe o nome do plano (até 50 caracteres).');
}
if (mb_strlen($descricao) < 20 || mb_strlen($descricao) > 1000) {
    recusar(422, 'descricao', 'A descrição deve ter entre 20 e 1000 caracteres.');
}
if (!in_array($publicoAlvo, PUBLICOS_ACEITOS, true)) {
    recusar(422, 'publico_alvo', 'Selecione quem pode assinar o plano.');
}
// Valor: DECIMAL(10,2) => até 8 dígitos antes do ponto e 2 casas decimais
if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $valor)) {
    recusar(422, 'valor', 'Informe um valor válido, com até 2 casas decimais.');
}
if (!in_array($periodicidade, PERIODICIDADES_ACEITAS, true)) {
    recusar(422, 'periodicidade', 'Selecione a forma de cobrança.');
}

// Benefícios: um por linha. O navegador envia as quebras de linha como \r\n,
// por isso divide por QUALQUER quebra (\R) e grava com \n, como já está no banco.
$linhas = preg_split('/\R/u', texto('beneficios')) ?: [];
$beneficios = [];
foreach ($linhas as $linha) {
    $linha = trim($linha);
    if ($linha === '') continue;
    $chave = mb_strtolower($linha);                // repetido = mesma linha sem diferenciar maiúsculas
    if (!isset($beneficios[$chave])) {
        $beneficios[$chave] = $linha;              // mantém a primeira grafia (igual ao JavaScript)
    }
}
$beneficios = array_values($beneficios);

if (count($beneficios) < 1) {
    recusar(422, 'beneficios', 'Informe ao menos um benefício ou cobertura do plano.');
}
if (count($beneficios) > MAX_BENEFICIOS) {
    recusar(422, 'beneficios', 'Use no máximo ' . MAX_BENEFICIOS . ' benefícios.');
}
foreach ($beneficios as $b) {
    if (mb_strlen($b) > MAX_CHARS_BENEFICIO) {
        recusar(422, 'beneficios', 'Cada benefício deve ter no máximo ' . MAX_CHARS_BENEFICIO . ' caracteres.');
    }
}
$beneficiosTxt = implode("\n", $beneficios);

// Destaque: TINYINT(1) -> só 0 ou 1
$destaque = texto('destaque') === '1' ? 1 : 0;

try {
    // ==================================================
    // 3) DESCOBRE O VETERINÁRIO PELA CONTA LOGADA (nunca pelo formulário)
    // ==================================================
    $stmt = $conn->prepare('SELECT id_veterinario, status FROM veterinario WHERE id_usuario = ?');
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $vet = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$vet) {
        recusar(403, 'sem_veterinario', 'Não encontramos o seu perfil de veterinário.');
    }
    if ($vet['status'] !== 'Ativo') {
        recusar(403, 'veterinario_inativo', 'Sua conta de veterinário não está ativa.');
    }
    $idVeterinario = (int) $vet['id_veterinario'];

    // ==================================================
    // 4) NÃO PERMITE DOIS PLANOS COM O MESMO NOME PARA O MESMO VETERINÁRIO
    // ==================================================
    $stmt = $conn->prepare(
        "SELECT 1 FROM plano_de_assinatura
          WHERE id_veterinario = ? AND tipo_registro = 'plano' AND nome = ?"
    );
    $stmt->bind_param('is', $idVeterinario, $nome);
    $stmt->execute();
    $jaExiste = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if ($jaExiste) {
        recusar(409, 'nome_repetido', 'Você já tem um plano com esse nome. Escolha outro nome.');
    }

    // ==================================================
    // 5) GRAVA O PLANO, LIGADO AO VETERINÁRIO
    // ==================================================
    // tipo_registro 'plano' = plano do catálogo (as linhas 'assinatura' são as contratações dos usuários).
    // Não informamos: id_plano (auto), usuario_id, id_usuario, vencimento (são da assinatura) e ordem (padrão 0).
    $stmt = $conn->prepare(
        "INSERT INTO plano_de_assinatura
            (id_veterinario, nome, valor, tipo_registro, descricao, periodicidade,
             beneficios, destaque, status, publico_alvo)
         VALUES (?, ?, ?, 'plano', ?, ?, ?, ?, 'Ativo', ?)"
    );
    $stmt->bind_param(
        'isssssis',
        $idVeterinario, $nome, $valor, $descricao, $periodicidade,
        $beneficiosTxt, $destaque, $publicoAlvo
    );
    $stmt->execute();
    $idPlano = $conn->insert_id;
    $stmt->close();

    responder(201, [
        'ok'       => true,
        'id_plano' => $idPlano,
        'mensagem' => 'Plano cadastrado com sucesso! Ele já está disponível para assinatura.',
    ]);

} catch (Throwable $e) {
    error_log('Erro ao cadastrar plano: ' . $e->getMessage());
    recusar(500, 'servidor', 'Não foi possível cadastrar o plano agora. Tente novamente.');
}
