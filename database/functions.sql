-- =====================================================================
-- ADOTAI | ATIVIDADE DE BANCO DE DADOS - OBJETOS PROGRAMAVEIS
-- ATENCAO: preencher grupo e integrantes antes de entregar.
-- Grupo: [PREENCHER] | Integrantes: [PREENCHER]
-- Banco: adotai_site | SGBD de referencia: MariaDB 10.4.32
-- Versao do script: 2.0
--
-- Base: dump adotai_site enviado pelo grupo e regras observadas nos arquivos
-- catalogo.php, lista_interesse.php, animal.php, _bootstrap.php e database.php.
-- Regras alinhadas ao sistema: catalogo publico = aprovado + disponivel;
-- interesse = usuario + animal; aprovacao administrativa = aprovado/reprovado.
--
-- IMPORTANTE:
-- 1) Faça backup e execute primeiro em uma COPIA de teste do banco.
-- 2) Se o MariaDB estiver na porta 3307, ajuste DB_PORT no database.php
--    (o arquivo enviado esta configurado na porta 3306).
-- 3) A conta que executa o script precisa de CREATE VIEW, CREATE ROUTINE,
--    CREATE TRIGGER, CREATE USER e GRANT. A view de seguranca pode exigir root.
-- 4) A conta adotai_leitor e demonstrativa. Troque a senha antes de uso real.
-- 5) Os testes de escrita estao comentados: execute-os individualmente,
--    confira os resultados e restaure o status do animal usado no teste.
-- 6) O dump possui mais de 10 animais e usuarios sem lista de interesse,
--    permitindo demonstrar agregacoes e LEFT JOIN sem criar dados artificiais.
-- =====================================================================

USE adotai_site;

-- ---------------------------------------------------------------------
-- LIMPEZA DOS OBJETOS DESTE TRABALHO (nao apaga tabelas do projeto)
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_adicionar_favorito;
DROP PROCEDURE IF EXISTS sp_atualizar_aprovacao_animal;
DROP PROCEDURE IF EXISTS sp_relatorio_animais_periodo;

DROP VIEW IF EXISTS vw_catalogo_animais;
DROP VIEW IF EXISTS vw_resumo_categoria;
DROP VIEW IF EXISTS vw_usuarios_favoritos;
DROP VIEW IF EXISTS vw_perfil_protetor_publico;

DROP FUNCTION IF EXISTS fn_rotulo_animal;
DROP FUNCTION IF EXISTS fn_classificar_idade;
DROP FUNCTION IF EXISTS fn_qtd_favoritos_usuario;

DROP TRIGGER IF EXISTS trg_auditar_aprovacao_animal;

-- ---------------------------------------------------------------------
-- TABELA DE LOG DO TRIGGER (tabela auxiliar do trabalho)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS log_aprovacao_animal (
    id_log BIGINT NOT NULL AUTO_INCREMENT,
    id_animal INT NOT NULL,
    status_anterior VARCHAR(20) NOT NULL,
    status_novo VARCHAR(20) NOT NULL,
    observacao_anterior TEXT NULL,
    observacao_nova TEXT NULL,
    data_alteracao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    usuario_banco VARCHAR(288) NOT NULL,
    PRIMARY KEY (id_log),
    KEY idx_log_aprovacao_animal (id_animal, data_alteracao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- VIEWS
-- =====================================================================

-- VIEW 1
-- Finalidade: apresentar o catalogo publico com animais aprovados e disponiveis.
-- Parametros: nenhum; os filtros de pesquisa sao aplicados no SELECT da view.
-- Exemplo: SELECT * FROM vw_catalogo_animais WHERE cidade='Curitiba' ORDER BY categoria,nome_animal;
CREATE OR REPLACE VIEW vw_catalogo_animais AS
SELECT
    a.id_animal AS id_animal,
    a.nome_animal AS nome_animal,
    CONCAT(UPPER(a.especie), ' - ', a.nome_animal) AS identificacao,
    a.especie AS especie,
    c.nome_categoria AS categoria,
    a.raca AS raca,
    COALESCE(NULLIF(p.nome_organizacao, ''), CONCAT('Protetor #', p.id_protetor)) AS responsavel,
    a.cidade AS cidade,
    a.uf AS uf,
    a.idade_meses AS idade_meses,
    CASE
        WHEN a.idade_meses IS NULL THEN 'Nao informada'
        WHEN a.idade_meses < 12 THEN 'Filhote'
        WHEN a.idade_meses < 84 THEN 'Adulto'
        ELSE 'Idoso'
    END AS faixa_etaria,
    DATE_FORMAT(a.data_cadastro, '%d/%m/%Y') AS data_cadastro_formatada,
    DATEDIFF(CURDATE(), a.data_cadastro) AS dias_no_catalogo,
    a.status_adocao AS status_adocao
FROM animal AS a
INNER JOIN categoria AS c
    ON c.id_categoria = a.id_categoria
INNER JOIN protetor AS p
    ON p.id_protetor = a.id_protetor
INNER JOIN usuario AS u
    ON u.id = p.id_usuario
WHERE a.status_aprovacao = 'Aprovado'
  AND a.status_adocao = 'Disponível';

-- VIEW 2
-- Finalidade: resumir quantidade, disponibilidade e idade dos animais por categoria.
-- Parametros: nenhum; permite filtrar categorias no SELECT externo.
-- Exemplo: SELECT * FROM vw_resumo_categoria WHERE total_animais >= 2 ORDER BY total_animais DESC,categoria;
CREATE OR REPLACE VIEW vw_resumo_categoria AS
SELECT
    c.id_categoria AS id_categoria,
    c.nome_categoria AS categoria,
    COUNT(a.id_animal) AS total_animais,
    COALESCE(SUM(CASE WHEN a.status_adocao = 'Disponível' THEN 1 ELSE 0 END), 0) AS disponiveis,
    COALESCE(SUM(CASE WHEN a.status_adocao = 'Em processo' THEN 1 ELSE 0 END), 0) AS em_processo,
    COALESCE(SUM(CASE WHEN a.status_adocao = 'Adotado' THEN 1 ELSE 0 END), 0) AS adotados,
    ROUND(AVG(a.idade_meses), 1) AS media_idade_meses,
    MIN(a.idade_meses) AS menor_idade_meses,
    MAX(a.idade_meses) AS maior_idade_meses
FROM categoria AS c
INNER JOIN animal AS a
    ON a.id_categoria = c.id_categoria
GROUP BY c.id_categoria, c.nome_categoria
HAVING COUNT(a.id_animal) >= (
    SELECT AVG(contagem_categoria)
    FROM (
        SELECT COUNT(*) AS contagem_categoria
        FROM animal
        GROUP BY id_categoria
    ) AS medias_categoria
);

-- VIEW 3
-- Finalidade: exibir quantos animais cada usuario salvou, inclusive usuarios sem favoritos.
-- Parametros: nenhum.
-- Exemplo: SELECT * FROM vw_usuarios_favoritos WHERE total_favoritos=0 ORDER BY nome_usuario,id_usuario;
CREATE OR REPLACE VIEW vw_usuarios_favoritos AS
SELECT
    u.id AS id_usuario,
    COALESCE(NULLIF(u.nome, ''), u.email) AS nome_usuario,
    u.status_conta AS status_conta,
    COALESCE(COUNT(la.id_animal), 0) AS total_favoritos,
    CASE
        WHEN COUNT(la.id_animal) = 0 THEN 'Sem favoritos'
        WHEN COUNT(la.id_animal) BETWEEN 1 AND 3 THEN 'Poucos favoritos'
        ELSE 'Interessado ativo'
    END AS perfil_interesse
FROM usuario AS u
LEFT JOIN lista_interesse AS li
    ON li.id_usuario = u.id
LEFT JOIN lista_animal AS la
    ON la.id_lista = li.id_lista
GROUP BY u.id, u.nome, u.email, u.status_conta;

-- VIEW 4 - VIEW DE SEGURANCA
-- Finalidade: disponibilizar somente dados institucionais publicos de protetores aprovados.
-- Parametros: nenhum.
-- Exemplo: SELECT * FROM vw_perfil_protetor_publico WHERE uf='PR' ORDER BY cidade,organizacao;
CREATE OR REPLACE VIEW vw_perfil_protetor_publico
    (id_protetor, organizacao, cidade, uf, situacao)
AS
SELECT
    p.id_protetor,
    COALESCE(NULLIF(p.nome_organizacao, ''), CONCAT('Protetor independente #', p.id_protetor)),
    p.cidade,
    p.uf,
    p.status_aprovacao
FROM protetor AS p
WHERE p.status_aprovacao = 'Aprovado';

-- Conta de leitura com permissao concedida SOMENTE sobre a view publica.
-- Em um banco de teste, a revogacao garante que uma execucao anterior do script
-- nao deixe privilegios extras nessa conta dedicada.
CREATE USER IF NOT EXISTS 'adotai_leitor'@'localhost'
    IDENTIFIED BY 'Trocar_Esta_Senha_2026!';
REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'adotai_leitor'@'localhost';
GRANT SELECT ON adotai_site.vw_perfil_protetor_publico
    TO 'adotai_leitor'@'localhost';

-- =====================================================================
-- FUNCTIONS
-- =====================================================================

-- FUNCTION 1
-- Finalidade: padronizar o rotulo textual de identificacao de um animal.
-- Parametros: p_nome (nome) e p_especie (especie).
-- Exemplo: SELECT fn_rotulo_animal('Luna','Gato');
DELIMITER $$
CREATE FUNCTION fn_rotulo_animal(
    p_nome VARCHAR(50),
    p_especie VARCHAR(30)
)
RETURNS VARCHAR(120)
DETERMINISTIC
NO SQL
BEGIN
    RETURN CONCAT(
        UPPER(COALESCE(p_especie, 'ESPECIE NAO INFORMADA')),
        ' - ',
        COALESCE(NULLIF(TRIM(p_nome), ''), 'SEM NOME')
    );
END$$
DELIMITER ;

-- FUNCTION 2
-- Finalidade: classificar a fase de vida do animal conforme idade em meses.
-- Parametros: p_idade_meses (idade em meses).
-- Exemplo: SELECT fn_classificar_idade(8);
DELIMITER $$
CREATE FUNCTION fn_classificar_idade(
    p_idade_meses INT
)
RETURNS VARCHAR(30)
DETERMINISTIC
NO SQL
BEGIN
    RETURN CASE
        WHEN p_idade_meses IS NULL THEN 'Nao informada'
        WHEN p_idade_meses < 12 THEN 'Filhote'
        WHEN p_idade_meses < 84 THEN 'Adulto'
        ELSE 'Idoso'
    END;
END$$
DELIMITER ;

-- FUNCTION 3
-- Finalidade: contar quantos animais estao salvos na lista de um usuario.
-- Parametros: p_id_usuario (identificador do usuario).
-- Exemplo: SELECT id, fn_qtd_favoritos_usuario(id) FROM usuario;
DELIMITER $$
CREATE FUNCTION fn_qtd_favoritos_usuario(
    p_id_usuario INT
)
RETURNS INT
READS SQL DATA
BEGIN
    DECLARE v_total INT DEFAULT 0;

    SELECT COALESCE(COUNT(la.id_animal), 0)
      INTO v_total
      FROM lista_interesse AS li
      LEFT JOIN lista_animal AS la
        ON la.id_lista = li.id_lista
     WHERE li.id_usuario = p_id_usuario;

    RETURN COALESCE(v_total, 0);
END$$
DELIMITER ;

-- =====================================================================
-- PROCEDURES
-- =====================================================================

-- PROCEDURE 1 - TRANSACIONAL, GRAVA EM DUAS TABELAS E DEVOLVE OUT
-- Finalidade: criar a lista do usuario se necessario e adicionar animal disponivel.
-- Parametros: IN p_id_usuario, IN p_id_animal e OUT p_id_lista.
-- Exemplo: CALL sp_adicionar_favorito(4,2,@id_lista); SELECT @id_lista;
DELIMITER $$
CREATE PROCEDURE sp_adicionar_favorito(
    IN p_id_usuario INT,
    IN p_id_animal INT,
    OUT p_id_lista INT
)
BEGIN
    DECLARE v_id_lista INT DEFAULT NULL;
    DECLARE v_total INT DEFAULT 0;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    SET p_id_lista = NULL;
    START TRANSACTION;

    IF p_id_usuario IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Informe o usuario.';
    END IF;

    SELECT COUNT(*)
      INTO v_total
      FROM usuario
     WHERE id = p_id_usuario;

    IF v_total = 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Usuario nao encontrado.';
    END IF;

    SELECT MAX(id_lista)
      INTO v_id_lista
      FROM lista_interesse
     WHERE id_usuario = p_id_usuario;

    IF v_id_lista IS NULL THEN
        INSERT INTO lista_interesse (id_usuario)
        VALUES (p_id_usuario);

        SET v_id_lista = LAST_INSERT_ID();
    END IF;

    -- A validacao do animal ocorre depois da possivel criacao da lista:
    -- se falhar, o handler executa ROLLBACK e desfaz a primeira gravacao.
    SELECT COUNT(*)
      INTO v_total
      FROM animal
     WHERE id_animal = p_id_animal
       AND status_aprovacao = 'Aprovado'
       AND status_adocao = 'Disponível';

    IF p_id_animal IS NULL OR v_total = 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Animal inexistente, nao aprovado ou indisponivel.';
    END IF;

    SELECT COUNT(*)
      INTO v_total
      FROM lista_animal AS la
     WHERE la.id_lista = v_id_lista
       AND la.id_animal = p_id_animal;

    IF v_total > 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Este animal ja esta na lista de interesse do usuario.';
    END IF;

    INSERT INTO lista_animal (id_lista, id_animal)
    VALUES (v_id_lista, p_id_animal);

    SET p_id_lista = v_id_lista;
    COMMIT;
END$$
DELIMITER ;

-- PROCEDURE 2 - UPDATE COM CASE/COALESCE
-- Finalidade: aprovar ou reprovar um cadastro e registrar a observacao administrativa.
-- Parametros: IN p_id_animal, IN p_decisao (APROVAR/REPROVAR), IN p_observacao.
-- Exemplo: CALL sp_atualizar_aprovacao_animal(18,'REPROVAR','Documentacao pendente');
DELIMITER $$
CREATE PROCEDURE sp_atualizar_aprovacao_animal(
    IN p_id_animal INT,
    IN p_decisao VARCHAR(10),
    IN p_observacao TEXT
)
BEGIN
    IF p_id_animal IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Informe o animal que sera revisado.';
    ELSEIF NOT EXISTS (
        SELECT 1 FROM animal WHERE id_animal = p_id_animal
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Animal nao encontrado.';
    ELSEIF UPPER(COALESCE(p_decisao, '')) NOT IN ('APROVAR', 'REPROVAR') THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Decisao invalida. Use APROVAR ou REPROVAR.';
    END IF;

    UPDATE animal
       SET status_aprovacao =
            CASE
                WHEN UPPER(p_decisao) = 'APROVAR' THEN 'Aprovado'
                ELSE 'Reprovado'
            END,
           observacao_admin =
            COALESCE(NULLIF(TRIM(p_observacao), ''), observacao_admin)
     WHERE id_animal = p_id_animal;
END$$
DELIMITER ;

-- PROCEDURE 3 - RETORNA CONJUNTO DE LINHAS E CHAMA FUNCTION
-- Finalidade: gerar relatorio de animais cadastrados por protetor e categoria em um periodo.
-- Parametros: IN p_data_inicio e IN p_data_fim.
-- Exemplo: CALL sp_relatorio_animais_periodo('2026-10-01','2026-10-31');
DELIMITER $$
CREATE PROCEDURE sp_relatorio_animais_periodo(
    IN p_data_inicio DATE,
    IN p_data_fim DATE
)
BEGIN
    IF p_data_inicio IS NULL OR p_data_fim IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Informe as duas datas do periodo.';
    ELSEIF p_data_inicio > p_data_fim THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'A data inicial nao pode ser posterior a data final.';
    END IF;

    SELECT
        p.id_protetor AS id_protetor,
        COALESCE(NULLIF(p.nome_organizacao, ''), CONCAT('Protetor #', p.id_protetor)) AS protetor,
        c.nome_categoria AS categoria,
        COUNT(a.id_animal) AS animais_cadastrados,
        SUM(CASE WHEN a.status_adocao = 'Disponível' THEN 1 ELSE 0 END) AS disponiveis,
        SUM(CASE WHEN a.status_adocao = 'Em processo' THEN 1 ELSE 0 END) AS em_processo,
        fn_classificar_idade(CAST(ROUND(AVG(a.idade_meses)) AS SIGNED)) AS fase_etaria_media,
        DATE_FORMAT(MIN(a.data_cadastro), '%d/%m/%Y') AS primeiro_cadastro
    FROM animal AS a
    INNER JOIN categoria AS c
        ON c.id_categoria = a.id_categoria
    INNER JOIN protetor AS p
        ON p.id_protetor = a.id_protetor
    WHERE a.data_cadastro >= p_data_inicio
      AND a.data_cadastro < DATE_ADD(p_data_fim, INTERVAL 1 DAY)
    GROUP BY p.id_protetor, p.nome_organizacao, c.id_categoria, c.nome_categoria
    HAVING COUNT(a.id_animal) > 0
    ORDER BY animais_cadastrados DESC, protetor ASC, categoria ASC;
END$$
DELIMITER ;

-- =====================================================================
-- TRIGGER BONUS: AUDITORIA DE ALTERACAO DA APROVACAO
-- =====================================================================

-- Finalidade: registrar automaticamente mudancas no status de aprovacao.
-- Disparo: AFTER UPDATE em animal, somente quando o status realmente muda.
-- Exemplo: CALL sp_atualizar_aprovacao_animal(18,'REPROVAR','Teste'); SELECT * FROM log_aprovacao_animal;
DELIMITER $$
CREATE TRIGGER trg_auditar_aprovacao_animal
AFTER UPDATE ON animal
FOR EACH ROW
BEGIN
    IF NOT (OLD.status_aprovacao <=> NEW.status_aprovacao) THEN
        INSERT INTO log_aprovacao_animal (
            id_animal,
            status_anterior,
            status_novo,
            observacao_anterior,
            observacao_nova,
            usuario_banco
        )
        VALUES (
            OLD.id_animal,
            OLD.status_aprovacao,
            NEW.status_aprovacao,
            OLD.observacao_admin,
            NEW.observacao_admin,
            CURRENT_USER()
        );
    END IF;
END$$
DELIMITER ;

-- =====================================================================
-- TESTES E EVIDENCIAS - EXECUTAR MANUALMENTE, UM BLOCO POR VEZ
-- =====================================================================

-- A) VIEWS: cada consulta deve ser executada e fotografada com o resultado.
-- VIEW 1: BETWEEN + IN + LIKE + IS NOT NULL + ORDER BY com dois criterios.
-- SELECT id_animal, nome_animal, especie, categoria, faixa_etaria
-- FROM vw_catalogo_animais
-- WHERE idade_meses BETWEEN 0 AND 60
--   AND especie IN ('Cachorro', 'Gato')
--   AND nome_animal LIKE '%a%'
--   AND raca IS NOT NULL
-- ORDER BY categoria ASC, nome_animal ASC;

-- VIEW 2: agregacao, GROUP BY, HAVING e subquery.
-- SELECT id_categoria, categoria, total_animais, disponiveis, media_idade_meses
-- FROM vw_resumo_categoria
-- WHERE total_animais >= 2
-- ORDER BY total_animais DESC, categoria ASC;

-- VIEW 3: LEFT JOIN + COALESCE; usuarios sem favoritos aparecem com zero.
-- SELECT id_usuario, nome_usuario, total_favoritos, perfil_interesse
-- FROM vw_usuarios_favoritos
-- WHERE total_favoritos = 0
-- ORDER BY nome_usuario ASC, id_usuario ASC;

-- VIEW 4: view publica de seguranca e operador IS NULL.
-- SELECT id_protetor, organizacao, cidade, uf, situacao
-- FROM vw_perfil_protetor_publico
-- WHERE cidade IS NULL OR uf IS NULL
-- ORDER BY cidade ASC, organizacao ASC;

-- LIMITACAO: tente executar em ambiente de teste; deve falhar por ser agregada.
-- UPDATE vw_resumo_categoria
-- SET total_animais = 99
-- WHERE id_categoria = 1;
-- Explique: a view agrega varias linhas de animal por categoria com GROUP BY,
-- COUNT e SUM. Nao existe uma unica linha-base que corresponda ao valor agregado
-- que se tentou alterar; por isso o MariaDB nao consegue atualizar essa view.

-- B) FUNCTIONS: chamada dentro de SELECT sobre tabelas reais.
-- SELECT id_animal, nome_animal,
--        fn_rotulo_animal(nome_animal, especie) AS rotulo,
--        fn_classificar_idade(idade_meses) AS fase_etaria
-- FROM animal
-- ORDER BY id_animal;

-- SELECT id, email,
--        fn_qtd_favoritos_usuario(id) AS total_favoritos
-- FROM usuario
-- ORDER BY id;

-- Casos-limite: nome/especie nulos, idade nula e usuario sem favoritos/inexistente.
-- SELECT fn_rotulo_animal(NULL, NULL) AS rotulo_sem_dados,
--        fn_classificar_idade(NULL) AS idade_sem_dados,
--        fn_qtd_favoritos_usuario(999999) AS favoritos_usuario_inexistente;

-- C) PROCEDURE 1: sucesso, OUT e conferencia das duas tabelas.
-- Antes: confirme que o usuario 4 ainda nao possui lista.
-- SELECT * FROM lista_interesse WHERE id_usuario = 4;
-- CALL sp_adicionar_favorito(4, 2, @id_lista);
-- SELECT @id_lista AS id_lista_retornada;
-- SELECT * FROM lista_interesse WHERE id_usuario = 4;
-- SELECT * FROM lista_animal WHERE id_lista = @id_lista;

-- Erro de validacao: usuario inexistente.
-- CALL sp_adicionar_favorito(999999, 2, @id_lista);

-- Erro de duplicidade: execute somente depois do teste de sucesso.
-- CALL sp_adicionar_favorito(4, 2, @id_lista);

-- ROLLBACK REAL: use o usuario 8, conforme a evidencia capturada. A procedure cria a lista
-- dentro da transacao e depois recebe um animal inexistente; o SIGNAL aciona
-- o handler, que faz ROLLBACK. Execute cada consulta separadamente e compare.
-- SELECT * FROM lista_interesse WHERE id_usuario = 8;
-- CALL sp_adicionar_favorito(8, 999999, @id_lista);
-- SELECT * FROM lista_interesse WHERE id_usuario = 8;
-- Resultado esperado: nenhuma nova lista persistida para o usuario 8.

-- D) PROCEDURE 2: alteracao + trigger de auditoria.
-- SELECT id_animal, status_aprovacao, observacao_admin FROM animal WHERE id_animal = 18;
-- CALL sp_atualizar_aprovacao_animal(18, 'REPROVAR', 'Teste de auditoria');
-- SELECT id_animal, status_aprovacao, observacao_admin FROM animal WHERE id_animal = 18;
-- SELECT * FROM log_aprovacao_animal WHERE id_animal = 18 ORDER BY id_log DESC;
-- Validacao: deve apresentar mensagem de decisao invalida.
-- CALL sp_atualizar_aprovacao_animal(18, 'TALVEZ', 'Teste invalido');
-- IMPORTANTE: restaure o animal apos as evidencias:
-- CALL sp_atualizar_aprovacao_animal(18, 'APROVAR', 'Cadastro restaurado apos teste');
-- SELECT id_animal, status_aprovacao FROM animal WHERE id_animal = 18;

-- E) PROCEDURE 3: conjunto de linhas e validacao de periodo.
-- CALL sp_relatorio_animais_periodo('2026-10-01', '2026-10-31');
-- CALL sp_relatorio_animais_periodo('2026-10-31', '2026-10-01');

-- =====================================================================
-- MATRIZ DE RECURSOS (transcrever tambem no relatorio)
-- =====================================================================
-- JOIN de 3 ou mais tabelas: vw_catalogo_animais (animal/categoria/protetor/usuario)
--   e sp_relatorio_animais_periodo (animal/categoria/protetor).
-- LEFT JOIN + COALESCE: vw_usuarios_favoritos (usuario/lista_interesse/lista_animal).
-- GROUP BY + HAVING: vw_resumo_categoria e sp_relatorio_animais_periodo.
-- CASE: vw_catalogo_animais, vw_resumo_categoria, vw_usuarios_favoritos e procedures.
-- Funcao de string: UPPER/CONCAT em vw_catalogo_animais e fn_rotulo_animal.
-- Funcao de data: DATE_FORMAT/DATEDIFF/CURDATE na view; DATE_ADD no relatorio.
-- Subquery: subconsulta derivada no HAVING de vw_resumo_categoria.
-- View de seguranca + GRANT: vw_perfil_protetor_publico.
-- Function dentro de SELECT: fn_rotulo_animal, fn_classificar_idade e fn_qtd_favoritos_usuario.
-- Parametro OUT: sp_adicionar_favorito (p_id_lista).
-- Validacao SIGNAL: as tres procedures.
-- Transacao + handler + ROLLBACK: sp_adicionar_favorito.
-- LAST_INSERT_ID: sp_adicionar_favorito, para recuperar a lista recem-criada.
-- Procedure que chama function: sp_relatorio_animais_periodo chama fn_classificar_idade.
-- Trigger bonus: trg_auditar_aprovacao_animal registra OLD/NEW e CURRENT_USER().
-- =====================================================================
