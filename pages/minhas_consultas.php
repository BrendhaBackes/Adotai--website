<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

exigirLogin();
$consultas = [];
$erro = '';
$aviso = (string)($_SESSION['flash_consulta'] ?? '');
unset($_SESSION['flash_consulta']);

try {
    $stmt = db()->prepare(
        'SELECT c.*, v.nome AS veterinario_nome
         FROM consulta c
         INNER JOIN veterinario v ON v.id_veterinario = c.id_veterinario
         WHERE c.id_usuario = :usuario
         ORDER BY c.data_consulta DESC, c.horario DESC'
    );
    $stmt->execute(['usuario' => usuarioId()]);
    $consultas = $stmt->fetchAll();
} catch (Throwable $e) {
    $erro = mensagemErro($e);
}

cabecalho('Minhas consultas');
?>
<?php if ($aviso): ?><div class="notice success"><?= h($aviso) ?></div><?php endif; ?>
<?php if ($erro): ?>
    <div class="notice error"><?= h($erro) ?></div>
<?php elseif (!$consultas): ?>
    <div class="empty-state">
        <h2>Nenhuma consulta agendada</h2>
        <p>Quando você agendar uma consulta, ela aparecerá aqui.</p>
        <a class="btn btn-primary" href="veterinarios.php">Encontrar veterinário</a>
    </div>
<?php else: ?>
    <div class="notice">Os horários abaixo são solicitações. A clínica confirma a disponibilidade diretamente com você.</div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Veterinário</th><th>Data</th><th>Horário</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($consultas as $consulta): ?>
                <tr>
                    <td><?= h($consulta['veterinario_nome']) ?></td>
                    <td><?= !empty($consulta['data_consulta']) ? date('d/m/Y', strtotime($consulta['data_consulta'])) : 'A confirmar' ?></td>
                    <td><?= !empty($consulta['horario']) ? h(substr((string)$consulta['horario'], 0, 5)) : 'A confirmar' ?></td>
                    <td><?= h($consulta['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php rodape(); ?>
