<?php
// =====================================================
// api/lista_desejos.php
// Devolve em JSON os animais da lista de desejos (lista de interesses) do adotante
// logado, com TODOS os dados do animal e o telefone do protetor, para a página
// pages/lista_desejos.html (scripts/lista_desejos_handler.js monta os cards).
//
// GET, sem parâmetros. O usuário vem da sessão, nunca da requisição:
// ninguém consegue ver a lista de outra pessoa.
//
// Só entram animais ainda aprovados (um animal recusado ou em nova análise
// não está mais no catálogo). Animais já adotados continuam na lista, com o status.
// O telefone segue a mesma regra do api/animais.php: contato do protetor,
// ou o telefone da conta se o contato estiver vazio.
//
// Resposta:
//   { ok: true, animais: [ { id_animal, nome_animal, descricao, especie, raca, idade_meses, sexo,
//       porte, cor, castrado, vacinado, vermifugado, condicao_saude, compatibilidade_criancas,
//       compatibilidade_animais, cidade, uf, status_adocao, data_cadastro, foto_url,
//       protetor_nome, telefone_protetor } ] }
//   { ok: false, erro: "codigo", mensagem: "texto para a tela" }
// =====================================================
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
ini_set('display_errors', '0');

// Quem pode ter lista de desejos (mantenha igual ao TIPOS_PERMITIDOS do api/interesse.php)
const TIPOS_PERMITIDOS = ['adotante'];

function responder(int $status, array $dados): void {
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function recusar(int $status, string $erro, string $mensagem): void {
    responder($status, ['ok' => false, 'erro' => $erro, 'mensagem' => $mensagem]);
}

// ==================================================
// 1) QUEM ESTÁ PEDINDO?
// ==================================================
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    recusar(405, 'metodo', 'Requisição inválida.');
}
if (empty($_SESSION['logado'])) {
    recusar(401, 'nao_logado', 'Sua sessão expirou. Entre novamente na sua conta.');
}
if (!in_array($_SESSION['usuario_tipo'] ?? '', TIPOS_PERMITIDOS, true)) {
    recusar(403, 'tipo_nao_permitido', 'Apenas contas de adotante têm lista de desejos.');
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

    $stmt = $conn->prepare(
        "SELECT a.id_animal, a.nome_animal, a.descricao, a.especie, a.raca, a.idade_meses, a.sexo,
                a.porte, a.cor, a.castrado, a.vacinado, a.vermifugado, a.condicao_saude,
                a.compatibilidade_criancas, a.compatibilidade_animais, a.cidade, a.uf,
                a.status_adocao, a.data_cadastro, a.foto_url,
                COALESCE(NULLIF(p.nome_organizacao, ''), NULLIF(u.nome, '')) AS protetor_nome,
                COALESCE(NULLIF(p.telefone_contato, ''), NULLIF(u.telefone, '')) AS telefone_protetor
           FROM lista_interesse li
           JOIN lista_animal la  ON la.id_lista   = li.id_lista
           JOIN animal a         ON a.id_animal   = la.id_animal
      LEFT JOIN protetor p       ON p.id_protetor = a.id_protetor
      LEFT JOIN usuario u        ON u.id          = p.id_usuario
          WHERE li.id_usuario = ?
            AND a.status_aprovacao = 'Aprovado'
          ORDER BY a.nome_animal, a.id_animal"
    );
    $stmt->bind_param('i', $idUsuario);
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
    error_log('Erro em api/lista_desejos.php: ' . $e->getMessage());
    recusar(500, 'servidor', 'Não foi possível carregar a sua lista de desejos agora. Tente novamente.');
}
