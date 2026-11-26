<?php
// =====================================================
// api/veterinarios.php
// Devolve em JSON os veterinários cadastrados e ativos, com os dados
// de contato, para o catálogo (scripts/catalogo_veterinario_handler.js)
// gerar os cards em pages/catalogo_veterinario.html.
//
// GET, sem parâmetros.
//
// Resposta:
//   { ok: true, veterinarios: [ { id_veterinario, nome, crmv, especialidade, descricao,
//       servicos, valor_consulta, foto_url, email, telefone, endereco, cidade, uf } ] }
//   { ok: false, erro: "codigo" }
//
// Regras de preenchimento (a tabela veterinario costuma ter muitos campos vazios):
//   e-mail / telefone -> os do veterinário; se vazios, os da conta (usuario);
//   cidade / UF       -> os do veterinário; se vazios, os do endereço da conta (endereco).
// Dados sensíveis (CNPJ, id da conta) NÃO são enviados.
// =====================================================
require __DIR__ . '/db_conn.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Avisos do PHP escritos na tela quebrariam o JSON: eles vão só para o log de erros
ini_set('display_errors', '0');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'erro' => 'metodo']);
    exit;
}

try {
    // Só veterinários ativos. Se o veterinário tem conta vinculada, a conta também precisa estar ativa.
    $resultado = $conn->query(
        "SELECT v.id_veterinario,
                v.nome,
                v.crmv,
                NULLIF(v.especialidade, '')  AS especialidade,
                NULLIF(v.descricao, '')      AS descricao,
                NULLIF(v.servicos, '')       AS servicos,
                v.valor_consulta,
                NULLIF(v.foto_url, '')       AS foto_url,
                NULLIF(v.endereco, '')       AS endereco,
                COALESCE(NULLIF(v.email, ''),    NULLIF(u.email, ''))    AS email,
                COALESCE(NULLIF(v.telefone, ''), NULLIF(u.telefone, '')) AS telefone,
                COALESCE(NULLIF(v.cidade, ''),   NULLIF(e.cidade, ''))   AS cidade,
                COALESCE(NULLIF(v.uf, ''),       NULLIF(e.uf, ''))       AS uf
           FROM veterinario v
           LEFT JOIN usuario  u ON u.id         = v.id_usuario
           LEFT JOIN endereco e ON e.id_usuario = v.id_usuario
          WHERE v.status = 'Ativo'
            AND (u.id IS NULL OR u.status_conta = 'Ativa')
          ORDER BY v.nome, v.id_veterinario"
    );

    $veterinarios = [];
    while ($linha = $resultado->fetch_assoc()) {
        $linha['id_veterinario'] = (int) $linha['id_veterinario'];
        $linha['valor_consulta'] = $linha['valor_consulta'] === null ? null : (float) $linha['valor_consulta'];
        $veterinarios[] = $linha;
    }

    echo json_encode(['ok' => true, 'veterinarios' => $veterinarios], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

} catch (Throwable $e) {
    error_log('Erro ao listar veterinários: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'servidor']);
}
