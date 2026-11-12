USE adotai_site;

ALTER TABLE protetor
    ADD COLUMN tipo_perfil VARCHAR(20) NOT NULL DEFAULT 'ong',
    ADD COLUMN canal_contato VARCHAR(20) NOT NULL DEFAULT 'whatsapp';

ALTER TABLE veterinario
    ADD COLUMN canal_contato VARCHAR(20) NOT NULL DEFAULT 'whatsapp';

CREATE TABLE plano_veterinario (
    id_plano INT NOT NULL AUTO_INCREMENT,
    id_veterinario INT(11) NOT NULL,
    nome VARCHAR(120) NOT NULL,
    descricao TEXT NOT NULL,
    beneficios TEXT NULL,
    valor DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    periodicidade VARCHAR(20) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    data_cadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_plano),
    KEY idx_plano_veterinario_publico (id_veterinario, ativo),
    CONSTRAINT fk_plano_veterinario_perfil
        FOREIGN KEY (id_veterinario) REFERENCES veterinario (id_veterinario)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
