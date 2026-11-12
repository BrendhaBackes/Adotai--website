<?php
// =====================================================
// api/animal_cadastrar.php
// Recebe o formulário de pages/protetor_painel.html e cadastra
// o animal na tabela `animal`, ligado ao protetor logado.
//
// Recebe por POST (multipart/form-data):
//   nome_animal, descricao, id_categoria, especie, raca, idade_meses,
//   sexo, porte, cor, castrado, vacinado, vermifugado, condicao_saude,
//   compatibilidade_criancas, compatibilidade_animais
//   foto (arquivo, opcional)
//
// A foto é salva em fotos/<pasta da categoria>/ e só o caminho
// (ex.: fotos/cachorros/thor-vira-lata-caramelo.jpg) vai para animal.foto_url.
// O id do protetor NUNCA vem do formulário: sai da sessão / do banco.
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

// true  = animal de protetor com status 'Aprovado' já entra 'Aprovado' (aparece no catálogo na hora);
//         de protetor 'Pendente' entra 'Pendente' (o admin precisa aprovar).
// false = todo animal novo entra 'Pendente' e só aparece no catálogo depois que o admin aprovar.
const PUBLICAR_DIRETO_SE_PROTETOR_APROVADO = true;

const FOTO_MAX_BYTES = 5 * 1024 * 1024;   // 5 MB (igual ao limite do JS)

// Tipos de imagem aceitos (conferidos pelo CONTEÚDO do arquivo) => extensão salva
const FOTO_TIPOS = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

// Pasta dentro de fotos/ para cada id_categoria (mesmas pastas que o site já usa)
const PASTAS_POR_CATEGORIA = [
    1 => 'cachorros',
    2 => 'gatos',
    3 => 'aves',
    4 => 'coelhos',
    5 => 'roedores',
    6 => 'repteis',
    7 => 'outros',
];
const PASTA_OUTROS = 'outros';                 // categoria nova que não esteja na lista acima
const PASTA_FOTOS  = __DIR__ . '/../fotos';    // pasta fotos/ na raiz do site

// ==================================================
// FUNÇÕES AUXILIARES
// ==================================================
function responder(int $status, array $dados): void {
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

// erro => código curto (para o programador) | mensagem => texto que o protetor lê na tela
function recusar(int $status, string $erro, string $mensagem): void {
    responder($status, ['ok' => false, 'erro' => $erro, 'mensagem' => $mensagem]);
}

// Texto enviado por POST, sem espaços nas pontas ('' se não veio ou veio como lista)
function texto(string $campo): string {
    $valor = $_POST[$campo] ?? '';
    return is_string($valor) ? trim($valor) : '';
}

// Transforma "Thor, Vira-lata!" em "thor-vira-lata" (nome de arquivo seguro)
function slug(string $t): string {
    $t = mb_strtolower($t, 'UTF-8');
    $t = strtr($t, [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c', 'ñ' => 'n',
    ]);
    $t = preg_replace('/[^a-z0-9]+/', '-', $t);
    return trim($t, '-');
}

// ==================================================
// 1) QUEM ESTÁ ENVIANDO? (só protetor logado)
// ==================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    recusar(405, 'metodo', 'Requisição inválida.');
}

if (empty($_SESSION['logado'])) {
    recusar(401, 'nao_logado', 'Sua sessão expirou. Entre novamente na sua conta.');
}

if (($_SESSION['usuario_tipo'] ?? '') !== 'protetor') {
    recusar(403, 'tipo_nao_permitido', 'Apenas contas de protetor podem cadastrar animais.');
}

// Se o envio passou do limite do PHP (post_max_size), $_POST e $_FILES chegam vazios
if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    recusar(413, 'envio_grande', 'O envio é maior do que o servidor aceita. Tente uma foto menor.');
}

$idUsuario = (int) $_SESSION['usuario_id'];

// ==================================================
// 2) VALIDA OS CAMPOS (o JS já conferiu, mas o servidor confere de novo)
// ==================================================
$nome        = texto('nome_animal');
$descricao   = texto('descricao');
$idCategoria = (int) texto('id_categoria');
$especie     = texto('especie');
$raca        = texto('raca');
$cor         = texto('cor');
$condicao    = texto('condicao_saude');
$idadeTxt    = texto('idade_meses');
$sexo        = texto('sexo');
$porte       = texto('porte');
$criancas    = texto('compatibilidade_criancas');
$animais     = texto('compatibilidade_animais');

if ($nome === '' || mb_strlen($nome) > 50) {
    recusar(422, 'nome_animal', 'Informe o nome do animal (até 50 caracteres).');
}
if (mb_strlen($descricao) < 20 || mb_strlen($descricao) > 1000) {
    recusar(422, 'descricao', 'A descrição deve ter entre 20 e 1000 caracteres.');
}
if ($especie === '' || mb_strlen($especie) > 30) {
    recusar(422, 'especie', 'Informe a espécie do animal (até 30 caracteres).');
}
if (mb_strlen($raca) > 80) {
    recusar(422, 'raca', 'A raça deve ter no máximo 80 caracteres.');
}
if (mb_strlen($cor) > 80) {
    recusar(422, 'cor', 'A cor deve ter no máximo 80 caracteres.');
}
if (mb_strlen($condicao) > 500) {
    recusar(422, 'condicao_saude', 'A condição de saúde deve ter no máximo 500 caracteres.');
}

// Idade: vazia = não informada (NULL). Senão, de 0 a 611 meses (50 anos e 11 meses)
$idadeMeses = null;
if ($idadeTxt !== '') {
    if (!ctype_digit($idadeTxt) || (int) $idadeTxt > 611) {
        recusar(422, 'idade', 'Idade inválida. Use de 0 a 50 anos e de 0 a 11 meses.');
    }
    $idadeMeses = (int) $idadeTxt;
}

// Valores que o banco só aceita dentro do ENUM
if (!in_array($sexo, ['Macho', 'Fêmea', 'Não informado'], true)) {
    recusar(422, 'sexo', 'Selecione o sexo do animal.');
}
if ($porte !== '' && !in_array($porte, ['Pequeno', 'Médio', 'Grande'], true)) {
    recusar(422, 'porte', 'Porte inválido.');
}
foreach ([$criancas, $animais] as $compat) {
    if (!in_array($compat, ['Sim', 'Não', 'Não informado'], true)) {
        recusar(422, 'compatibilidade', 'Convivência com crianças e animais inválida.');
    }
}

// Castrado / vacinado / vermifugado: TINYINT(1) -> só 0 ou 1
$castrado    = texto('castrado')    === '1' ? 1 : 0;
$vacinado    = texto('vacinado')    === '1' ? 1 : 0;
$vermifugado = texto('vermifugado') === '1' ? 1 : 0;

// Campos opcionais vazios viram NULL no banco
$raca     = $raca     === '' ? null : $raca;
$cor      = $cor      === '' ? null : $cor;
$condicao = $condicao === '' ? null : $condicao;
$porte    = $porte    === '' ? null : $porte;

// ==================================================
// 3) VALIDA A FOTO (opcional)
// ==================================================
$foto    = $_FILES['foto'] ?? null;
$temFoto = is_array($foto) && ($foto['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
$extensaoFoto = null;

if ($temFoto) {
    if ($foto['error'] === UPLOAD_ERR_INI_SIZE || $foto['error'] === UPLOAD_ERR_FORM_SIZE) {
        recusar(413, 'foto', 'A foto é muito grande. O limite é 5 MB.');
    }
    if ($foto['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($foto['tmp_name'])) {
        recusar(422, 'foto', 'Não foi possível receber a foto. Tente novamente.');
    }
    if ($foto['size'] > FOTO_MAX_BYTES) {
        recusar(413, 'foto', 'A foto é muito grande. O limite é 5 MB.');
    }

    // Confere o tipo pelo conteúdo real do arquivo (a extensão enviada não é confiável)
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($foto['tmp_name']);
    if (!isset(FOTO_TIPOS[$mime]) || @getimagesize($foto['tmp_name']) === false) {
        recusar(422, 'foto', 'Formato de foto não aceito. Use JPG, PNG ou WebP.');
    }
    $extensaoFoto = FOTO_TIPOS[$mime];
}

$fotoSalva = null;   // caminho completo do arquivo gravado (para apagar se o INSERT falhar)

try {
    // ==================================================
    // 4) CONFERE CATEGORIA E PROTETOR NO BANCO
    // ==================================================
    $stmt = $conn->prepare('SELECT 1 FROM categoria WHERE id_categoria = ?');
    $stmt->bind_param('i', $idCategoria);
    $stmt->execute();
    $categoriaExiste = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if (!$categoriaExiste) {
        recusar(422, 'id_categoria', 'Selecione uma categoria válida.');
    }

    // O protetor é descoberto pela conta logada (nunca pelo formulário)
    $stmt = $conn->prepare('SELECT id_protetor, status_aprovacao, cidade, uf FROM protetor WHERE id_usuario = ?');
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $protetor = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$protetor) {
        recusar(403, 'sem_protetor', 'Não encontramos o seu perfil de protetor.');
    }
    $idProtetor = (int) $protetor['id_protetor'];

    // Cidade/UF do animal = onde o protetor mora (tabela endereco); se não tiver, usa a do perfil
    $stmt = $conn->prepare('SELECT cidade, uf FROM endereco WHERE id_usuario = ?');
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $endereco = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $cidade = $endereco['cidade'] ?? ($protetor['cidade'] ?? null);
    $uf     = $endereco['uf']     ?? ($protetor['uf']     ?? null);

    // Status do animal: decide se aparece no catálogo agora ou depois da aprovação do admin
    $publicar = PUBLICAR_DIRETO_SE_PROTETOR_APROVADO && $protetor['status_aprovacao'] === 'Aprovado';
    $statusAprovacao = $publicar ? 'Aprovado' : 'Pendente';

    // ==================================================
    // 5) SALVA A FOTO NA PASTA fotos/<categoria>/
    // ==================================================
    $fotoUrl = null;   // vai para animal.foto_url

    if ($temFoto) {
        $pasta = PASTAS_POR_CATEGORIA[$idCategoria] ?? PASTA_OUTROS;
        $dir   = PASTA_FOTOS . '/' . $pasta;

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Não foi possível criar a pasta ' . $dir);
        }

        // Nome no padrão do site: nome-raca-cor  (ex.: thor-vira-lata-caramelo)
        $base = implode('-', array_filter([slug($nome), slug((string) $raca), slug((string) $cor)]));
        $base = substr($base, 0, 80);
        $base = trim($base, '-') ?: 'animal';

        $arquivo = $base . '.' . $extensaoFoto;
        if (file_exists($dir . '/' . $arquivo)) {
            // já existe uma foto com esse nome: acrescenta um sufixo para não sobrescrever
            $arquivo = $base . '-' . bin2hex(random_bytes(3)) . '.' . $extensaoFoto;
        }

        $destino = $dir . '/' . $arquivo;
        if (!move_uploaded_file($foto['tmp_name'], $destino)) {
            throw new RuntimeException('Não foi possível gravar a foto em ' . $destino);
        }
        chmod($destino, 0644);

        $fotoSalva = $destino;
        $fotoUrl   = 'fotos/' . $pasta . '/' . $arquivo;   // caminho relativo, como o catálogo espera
    }

    // ==================================================
    // 6) GRAVA O ANIMAL, LIGADO AO PROTETOR
    // ==================================================
    // Não informamos: id_animal (auto), nivel_energia (padrão 'Moderado'),
    // status_adocao (padrão 'Disponível') e observacao_admin.
    $stmt = $conn->prepare(
        'INSERT INTO animal
            (id_categoria, id_protetor, nome_animal, descricao, especie, raca, idade_meses,
             sexo, porte, cor, castrado, vacinado, vermifugado, condicao_saude,
             compatibilidade_criancas, compatibilidade_animais, cidade, uf,
             data_cadastro, foto_url, status_aprovacao)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, ?)'
    );
    $stmt->bind_param(
        'iissssisssiiisssssss',
        $idCategoria, $idProtetor, $nome, $descricao, $especie, $raca, $idadeMeses,
        $sexo, $porte, $cor, $castrado, $vacinado, $vermifugado, $condicao,
        $criancas, $animais, $cidade, $uf,
        $fotoUrl, $statusAprovacao
    );
    $stmt->execute();
    $idAnimal = $conn->insert_id;
    $stmt->close();

    responder(201, [
        'ok'               => true,
        'id_animal'        => $idAnimal,
        'foto_url'         => $fotoUrl,
        'status_aprovacao' => $statusAprovacao,
        'mensagem'         => $publicar
            ? 'Animal cadastrado com sucesso! Ele já aparece no catálogo.'
            : 'Animal cadastrado com sucesso! Ele aparecerá no catálogo assim que for aprovado.',
    ]);

} catch (Throwable $e) {
    // Se a foto já foi gravada mas o animal não, apaga a foto para não deixar arquivo órfão
    if ($fotoSalva !== null && is_file($fotoSalva)) {
        @unlink($fotoSalva);
    }
    error_log('Erro ao cadastrar animal: ' . $e->getMessage());
    recusar(500, 'servidor', 'Não foi possível cadastrar o animal agora. Tente novamente.');
}
