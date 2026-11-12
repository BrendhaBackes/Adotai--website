<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

exigirLogin();
validarCsrf();

$aviso = '';
$erro = '';
$veterinarios = [];
$dados = [
    'id_veterinario' => (int)($_GET['id_veterinario'] ?? $_POST['id_veterinario'] ?? 0),
    'data_consulta' => (string)($_POST['data_consulta'] ?? ''),
    'horario' => (string)($_POST['horario'] ?? ''),
    'observacao' => trim((string)($_POST['observacao'] ?? '')),
];

try {
    $db = db();
    $veterinarios = $db->query("SELECT id_veterinario, nome, especialidade FROM veterinario WHERE status = 'Ativo' ORDER BY nome")->fetchAll();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = DateTimeImmutable::createFromFormat('!Y-m-d', $dados['data_consulta']);
        $errosData = DateTimeImmutable::getLastErrors();
        $dataValida = $data !== false
            && ($errosData === false || ($errosData['warning_count'] === 0 && $errosData['error_count'] === 0))
            && $data->format('Y-m-d') === $dados['data_consulta']
            && $dados['data_consulta'] >= date('Y-m-d');
        $horarioValido = preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $dados['horario']) === 1;
        $veterinarioValido = false;
        foreach ($veterinarios as $veterinario) {
            if ((int)$veterinario['id_veterinario'] === $dados['id_veterinario']) {
                $veterinarioValido = true;
                break;
            }
        }

        if (!$veterinarioValido) {
            $aviso = 'Selecione um veterinário ativo.';
        } elseif (!$dataValida) {
            $aviso = 'Selecione uma data válida a partir de hoje.';
        } elseif (!$horarioValido) {
            $aviso = 'Informe um horário válido.';
        } elseif (strlen($dados['observacao']) > 5000) {
            $aviso = 'A observação deve ter até 5.000 caracteres.';
        } else {
            $stmt = $db->prepare(
                "INSERT INTO consulta (id_usuario, id_veterinario, data_consulta, horario, observacao, status)
                 VALUES (:usuario, :veterinario, :data, :horario, :observacao, 'Solicitada')"
            );
            $stmt->execute([
                'usuario' => usuarioId(),
                'veterinario' => $dados['id_veterinario'],
                'data' => $dados['data_consulta'],
                'horario' => $dados['horario'],
                'observacao' => $dados['observacao'] !== '' ? $dados['observacao'] : null,
            ]);
            $_SESSION['flash_consulta'] = 'Consulta solicitada com sucesso.';
            header('Location: minhas_consultas.php');
            exit;
        }
    }
} catch (Throwable $e) {
    $erro = mensagemErro($e);
}

cabecalho('Agendar consulta');
?>
<?php if ($erro): ?><div class="notice error"><?= h($erro) ?></div><?php endif; ?>
<?php if ($aviso): ?><div class="notice error"><?= h($aviso) ?></div><?php endif; ?>
<?php if (!$veterinarios && !$erro): ?><div class="empty-state"><h2>Nenhum veterinário disponível</h2><p>Não há profissionais ativos para agendamento no momento.</p><a class="btn btn-primary" href="veterinarios.php">Ver profissionais</a></div><?php else: ?>
<form class="form-card" method="post">
    <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
    <div class="form-grid">
        <label>Veterinário
            <select name="id_veterinario" required>
                <option value="">Selecione</option>
                <?php foreach ($veterinarios as $veterinario): ?>
                    <option value="<?= (int)$veterinario['id_veterinario'] ?>" <?= $dados['id_veterinario'] === (int)$veterinario['id_veterinario'] ? 'selected' : '' ?>><?= h($veterinario['nome']) ?><?= !empty($veterinario['especialidade']) ? ' — ' . h($veterinario['especialidade']) : '' ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Data
            <input type="date" name="data_consulta" min="<?= date('Y-m-d') ?>" required value="<?= h($dados['data_consulta']) ?>">
        </label>
        <label>Horário
            <input type="time" name="horario" required value="<?= h($dados['horario']) ?>">
        </label>
        <label class="full">Observações
            <textarea name="observacao" rows="4" maxlength="5000" placeholder="Conte brevemente o motivo da consulta"><?= h($dados['observacao']) ?></textarea>
        </label>
    </div>
    <button class="btn btn-primary" type="submit">Solicitar agendamento</button>
</form>
<?php endif; ?>
<?php rodape(); ?>
