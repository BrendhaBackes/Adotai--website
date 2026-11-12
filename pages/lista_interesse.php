<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

exigirLogin();
validarCsrf();

$aviso = '';
$erro = '';
$itens = [];

try {
    $db = db();
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adicionar'])) {
        $animalId = filter_var($_POST['adicionar'], FILTER_VALIDATE_INT);
        if ($animalId === false || $animalId < 0) {
            throw new InvalidArgumentException('Animal inválido.');
        }
        $stmt = $db->prepare("SELECT id_animal FROM animal WHERE id_animal = :id AND status_aprovacao = 'Aprovado' AND status_adocao = 'Disponível' LIMIT 1");
        $stmt->execute(['id' => $animalId]);
        if ($stmt->fetchColumn() === false) {
            throw new InvalidArgumentException('Esse animal não está mais disponível para adoção.');
        }

        $db->beginTransaction();
        $stmt = $db->prepare('INSERT IGNORE INTO lista_interesse (id_usuario) VALUES (:usuario)');
        $stmt->execute(['usuario' => usuarioId()]);
        $stmt = $db->prepare('SELECT id_lista FROM lista_interesse WHERE id_usuario = :usuario LIMIT 1');
        $stmt->execute(['usuario' => usuarioId()]);
        $listaId = (int)$stmt->fetchColumn();
        if ($listaId <= 0) {
            throw new InvalidArgumentException('Não foi possível criar sua lista de interesse.');
        }
        $stmt = $db->prepare('INSERT INTO lista_animal (id_lista, id_animal) VALUES (:lista, :animal) ON DUPLICATE KEY UPDATE id_animal = VALUES(id_animal)');
        $stmt->execute(['lista' => $listaId, 'animal' => $animalId]);
        $db->commit();
        $aviso = 'Animal adicionado à sua lista.';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remover'])) {
        $animalId = filter_var($_POST['remover'], FILTER_VALIDATE_INT);
        if ($animalId === false || $animalId < 0) {
            throw new InvalidArgumentException('Animal inválido.');
        }
        $stmt = $db->prepare('DELETE la FROM lista_animal la INNER JOIN lista_interesse li ON li.id_lista = la.id_lista WHERE li.id_usuario = :usuario AND la.id_animal = :animal');
        $stmt->execute(['usuario' => usuarioId(), 'animal' => $animalId]);
        $aviso = 'Animal removido da lista.';
    }

    $stmt = $db->prepare(
        "SELECT a.*, p.email_contato, p.telefone_contato, p.canal_contato
         FROM lista_interesse li
         INNER JOIN lista_animal la ON la.id_lista = li.id_lista
         INNER JOIN animal a ON a.id_animal = la.id_animal
         LEFT JOIN protetor p ON p.id_protetor = a.id_protetor
         WHERE li.id_usuario = :usuario AND a.status_aprovacao = 'Aprovado'
         ORDER BY a.data_cadastro DESC, a.nome_animal ASC"
    );
    $stmt->execute(['usuario' => usuarioId()]);
    $itens = $stmt->fetchAll();
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    if ($e instanceof InvalidArgumentException) {
        $aviso = $e->getMessage();
    } else {
        $erro = mensagemErro($e);
    }
}

cabecalho('Minha lista de interesse');
?>
<?php if ($erro): ?><div class="notice error"><?= h($erro) ?></div><?php endif; ?>
<?php if ($aviso): ?><div class="notice success"><?= h($aviso) ?></div><?php endif; ?>
<?php if (!$itens): ?>
    <div class="empty-state">
        <h2>Sua lista está vazia</h2>
        <p>Quando encontrar um animal especial, adicione-o à sua lista.</p>
        <a class="btn btn-primary" href="catalogo.php">Explorar animais</a>
    </div>
<?php else: ?>
    <div class="cards-grid">
        <?php foreach ($itens as $animal): ?>
            <article class="animal-card">
                <img src="<?= h(animalFotoSrc($animal['foto_url'] ?? '')) ?>" alt="Foto de <?= h($animal['nome_animal']) ?>" loading="lazy">
                <div class="animal-title"><h2><?= h($animal['nome_animal']) ?></h2></div>
                <p><?= h($animal['especie']) ?> · <?= h($animal['porte'] ?? 'Porte não informado') ?></p>
                <?php if (($animal['canal_contato'] ?? 'whatsapp') === 'whatsapp' && !empty($animal['telefone_contato'])): ?>
                    <a class="btn btn-outline" target="_blank" rel="noopener noreferrer" href="<?= h(linkWhatsApp($animal['telefone_contato'])) ?>">Conversar pelo WhatsApp</a>
                <?php elseif (!empty($animal['email_contato'])): ?>
                    <a class="btn btn-outline" href="mailto:<?= h($animal['email_contato']) ?>">Enviar e-mail ao responsável</a>
                <?php elseif (!empty($animal['telefone_contato'])): ?>
                    <a class="btn btn-outline" href="tel:<?= h(preg_replace('/\D+/', '', $animal['telefone_contato']) ?? '') ?>">Ligar para o responsável</a>
                <?php else: ?>
                    <p class="form-help">O responsável ainda não cadastrou um contato direto. Veja o anúncio para acompanhar.</p>
                <?php endif; ?>
                <div class="card-actions">
                    <a class="btn btn-primary" href="animal.php?id=<?= (int)$animal['id_animal'] ?>">Ver detalhes</a>
                    <form method="post">
                        <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
                        <button class="btn btn-outline" type="submit" name="remover" value="<?= (int)$animal['id_animal'] ?>">Remover</button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php rodape(); ?>
