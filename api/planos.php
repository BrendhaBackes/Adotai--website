<?php
// =====================================================
// api/planos.php
// Devolve em JSON os planos ativos, para o catálogo de planos
// (scripts/catalogo_planos_handler.js) gerar os cards.
//
// Público (não exige login), igual ao api/animais.php.
//
// Responde por GET:
//   { "ok": true, "planos": [
//       { "id_plano": 4, "nome": "...", "descricao": "...", "valor": 49.9,
//         "periodicidade": "Mensal", "beneficios": ["...", "..."],
//         "destaque": true, "publico_alvo": "ambos",
//         "veterinario": "Clínica Patinhas" | null, "cidade": "...", "uf": "PR" }, ... ] }
//
// Só entram planos do catálogo (tipo_registro = 'plano') com status 'Ativo'.
// Os planos da própria Adotaí não têm veterinário (veterinario = null).
// Planos de um veterinário que não está ativo não aparecem.
// =====================================================
require __DIR__ . '/db_conn.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $resultado = $conn->query(
        "SELECT p.id_plano, p.nome, p.descricao, p.valor, p.periodicidade,
                p.beneficios, p.destaque, p.publico_alvo,
                v.nome AS veterinario, v.cidade, v.uf
           FROM plano_de_assinatura p
      LEFT JOIN veterinario v ON v.id_veterinario = p.id_veterinario
          WHERE p.tipo_registro = 'plano'
            AND p.status = 'Ativo'
            AND (p.id_veterinario IS NULL OR v.status = 'Ativo')
          ORDER BY p.destaque DESC, p.ordem ASC, p.id_plano DESC"
    );

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
            'publico_alvo'  => $linha['publico_alvo'],
            'veterinario'   => $linha['veterinario'],
            'cidade'        => $linha['cidade'],
            'uf'            => $linha['uf'],
        ];
    }

    echo json_encode(['ok' => true, 'planos' => $planos], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

} catch (Throwable $e) {
    error_log('Erro ao listar planos: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'Não foi possível carregar os planos.']);
}
