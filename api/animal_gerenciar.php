<?php
// =====================================================
// api/animal_gerenciar.php
// Ações do protetor sobre os PRÓPRIOS animais (aba "Meus animais"
// de pages/protetor_painel.html, chamado por scripts/protetor_painel_handler.js).
//
// Só POST. A ação vem na URL (?acao=...):
//
//   POST ?acao=status   -> id_animal, status_adocao ('Disponível'|'Em processo'|'Adotado')
//   POST ?acao=remover  -> id_animal
//
// O protetor NUNCA vem da requisição: é descoberto pela sessão, e toda consulta
// confere que o animal pertence a ele (id_protetor). Ninguém mexe no animal de outro.
//
// Resposta JSON:
//   { ok: true, ... }  ou  { ok: false, erro: "codigo", mensagem: "texto para a tela" }
// =====================================================
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
ini_set('display_errors', '0');

// ==================================================
// CONFIGURAÇÕES  (edite aqui!)
// ==================================================
const STATUS_ADOCAO = ['Disponível', 'Em processo', 'Adotado'];   // valores do ENUM animal.status_adocao
const PASTA_FOTOS   = __DIR__ . '/../fotos';                       // mesma pasta do animal_cadastrar.php

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

function texto(string $campo): string {
    $valor = $_POST[$campo] ?? '';
    return is_string($valor) ? trim($valor) : '';
}

function idPositivo(string $campo): ?int {
    $valor = texto($campo);
    return ($valor !== '' && strlen($valor) <= 10 && ctype_digit($valor) && (int) $valor > 0)
        ? (int) $valor
        : null;
}

// ==================================================
// 1) QUEM ESTÁ PEDINDO? (só protetor logado)
// ==================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    recusar(405, 'metodo', 'Requisição inválida.');
}

if (empty($_SESSION['logado'])) {
    recusar(401, 'nao_logado', 'Sua sessão expirou. Entre novamente na sua conta.');
}

if (($_SESSION['usuario_tipo'] ?? '') !== 'protetor') {
    recusar(403, 'tipo_nao_permitido', 'Apenas contas de protetor podem alterar animais.');
}

// Protege contra requisições forjadas de outro site (o JS manda este cabeçalho)
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'fetch') {
    recusar(403, 'origem', 'Requisição não autorizada.');
}

$idUsuario = (int) ($_SESSION['usuario_id'] ?? 0);
session_write_close();

$acao = isset($_GET['acao']) && is_string($_GET['acao']) ? $_GET['acao'] : '';
if (!in_array($acao, ['status', 'remover'], true)) {
    recusar(404, 'acao', 'Ação desconhecida.');
}

$idAnimal = idPositivo('id_animal');
if ($idAnimal === null) {
    recusar(422, 'id_animal', 'Animal inválido.');
}

// ==================================================
// 2) EXECUÇÃO
// ==================================================
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    require __DIR__ . '/db_conn.php';
    $conn->set_charset('utf8mb4');

    // O protetor é descoberto pela conta logada
    $stmt = $conn->prepare('SELECT id_protetor FROM protetor WHERE id_usuario = ?');
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $protetor = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$protetor) {
        recusar(403, 'sem_protetor', 'Não encontramos o seu perfil de protetor.');
    }
    $idProtetor = (int) $protetor['id_protetor'];

    // O animal precisa existir E ser deste protetor
    $stmt = $conn->prepare(
        'SELECT id_animal, nome_animal, foto_url, status_aprovacao
           FROM animal WHERE id_animal = ? AND id_protetor = ?'
    );
    $stmt->bind_param('ii', $idAnimal, $idProtetor);
    $stmt->execute();
    $animal = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$animal) {
        recusar(404, 'animal', 'Animal não encontrado.');
    }

    // ---------------- Mudar o andamento da adoção ----------------
    if ($acao === 'status') {
        $novo = texto('status_adocao');

        if (!in_array($novo, STATUS_ADOCAO, true)) {
            recusar(422, 'status_adocao', 'Status de adoção inválido.');
        }
        // Animal que ainda não foi aprovado nem está no catálogo: não há adoção para acompanhar
        if ($animal['status_aprovacao'] !== 'Aprovado') {
            recusar(409, 'nao_aprovado', 'Só é possível alterar o status de animais já aprovados pelo administrador.');
        }

        $stmt = $conn->prepare('UPDATE animal SET status_adocao = ? WHERE id_animal = ? AND id_protetor = ?');
        $stmt->bind_param('sii', $novo, $idAnimal, $idProtetor);
        $stmt->execute();
        $stmt->close();

        responder(200, ['ok' => true, 'status_adocao' => $novo]);
    }

    // ---------------- Remover o animal ----------------
    $conn->begin_transaction();

    // lista_animal tem chave estrangeira para animal: tira o animal das listas de interesse antes
    $stmt = $conn->prepare('DELETE FROM lista_animal WHERE id_animal = ?');
    $stmt->bind_param('i', $idAnimal);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('DELETE FROM animal WHERE id_animal = ? AND id_protetor = ?');
    $stmt->bind_param('ii', $idAnimal, $idProtetor);
    $stmt->execute();
    $stmt->close();

    // Se o animal não tem outro registro usando a mesma foto, dá para apagar o arquivo também
    $arquivoApagar = null;
    $fotoUrl = (string) ($animal['foto_url'] ?? '');
    if ($fotoUrl !== '' && strpos($fotoUrl, 'fotos/') === 0 && strpos($fotoUrl, '..') === false) {
        $stmt = $conn->prepare('SELECT 1 FROM animal WHERE foto_url = ? LIMIT 1');
        $stmt->bind_param('s', $fotoUrl);
        $stmt->execute();
        $outroUsa = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if (!$outroUsa) {
            $arquivoApagar = PASTA_FOTOS . '/' . substr($fotoUrl, strlen('fotos/'));
        }
    }

    $conn->commit();

    // O arquivo só é apagado depois do commit (se o banco falhasse, a foto continuaria no lugar)
    if ($arquivoApagar !== null && is_file($arquivoApagar)) {
        @unlink($arquivoApagar);
    }

    responder(200, ['ok' => true]);

} catch (Throwable $e) {
    try { $conn->rollback(); } catch (Throwable $ignorado) {}
    error_log('Erro em api/animal_gerenciar.php (acao=' . $acao . '): ' . $e->getMessage());
    recusar(500, 'servidor', 'Não foi possível concluir agora. Tente novamente.');
}
