USE adotai_site;

ALTER TABLE animal
    ADD COLUMN status_aprovacao VARCHAR(20) NOT NULL DEFAULT 'Aprovado',
    ADD COLUMN observacao_admin TEXT NULL,
    ADD KEY idx_animal_aprovacao (status_aprovacao);

ALTER TABLE protetor
    ADD COLUMN nome_organizacao VARCHAR(150) NULL,
    ADD COLUMN descricao TEXT NULL,
    ADD COLUMN email_contato VARCHAR(150) NULL,
    ADD COLUMN telefone_contato VARCHAR(25) NULL,
    ADD COLUMN endereco VARCHAR(255) NULL,
    ADD COLUMN cidade VARCHAR(100) NULL,
    ADD COLUMN uf CHAR(2) NULL,
    ADD COLUMN foto_url VARCHAR(255) NULL;

ALTER TABLE veterinario
    ADD COLUMN id_usuario INT(11) NULL,
    ADD COLUMN descricao TEXT NULL,
    ADD COLUMN endereco VARCHAR(255) NULL,
    ADD COLUMN servicos TEXT NULL,
    ADD COLUMN planos TEXT NULL,
    ADD COLUMN valor_consulta DECIMAL(10,2) NULL,
    ADD COLUMN foto_url VARCHAR(255) NULL,
    ADD UNIQUE KEY uq_veterinario_usuario (id_usuario);
