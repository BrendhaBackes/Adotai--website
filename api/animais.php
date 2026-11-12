<?php
// =====================================================
// api/animais.php
// Devolve em JSON os animais cadastrados e aprovados,
// para o catálogo (scripts/catalogo_handler.js) gerar os cards.
// =====================================================
require __DIR__ . '/db_conn.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    // Só aparecem animais aprovados pelo admin.
    // A ordem segue o enum de status_adocao: Disponível, Em processo, Adotado.
    $resultado = $conn->query(
        "SELECT id_animal, nome_animal, descricao, especie, raca, sexo, cidade, uf,
                status_adocao, foto_url, idade_meses
           FROM animal
          WHERE status_aprovacao = 'Aprovado'
          ORDER BY status_adocao, data_cadastro DESC, id_animal DESC"
    );

    $animais = [];
    while ($linha = $resultado->fetch_assoc()) {
        $linha['id_animal']   = (int) $linha['id_animal'];
        $linha['idade_meses'] = $linha['idade_meses'] === null ? null : (int) $linha['idade_meses'];
        $animais[] = $linha;
    }

    echo json_encode(['ok' => true, 'animais' => $animais], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('Erro ao listar animais: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'Não foi possível carregar os animais.']);
}
