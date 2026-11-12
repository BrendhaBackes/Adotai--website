-- ============================================================================
-- Adotaí - estrutura do banco adotai_site (MariaDB 10.4+ / MySQL 8)
--
-- Só as tabelas: chaves primárias, índices, UNIQUE, AUTO_INCREMENT e chaves
-- estrangeiras ficam dentro do próprio CREATE TABLE (sem ALTER TABLE).
-- Os dados estão em adotai_site_dados.sql - rode este arquivo primeiro.
--
-- Importe em um banco vazio. Se adotai_site já tiver tabelas, apague-o antes.
-- ============================================================================

CREATE DATABASE IF NOT EXISTS adotai_site CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE adotai_site;

SET time_zone = '+00:00';
SET NAMES utf8mb4;


-- ---------- usuario: conta base de todos os perfis ----------
CREATE TABLE usuario (
  id                  INT          NOT NULL AUTO_INCREMENT,
  nome                VARCHAR(120) NOT NULL DEFAULT '',
  status_conta        VARCHAR(15)  NOT NULL,
  telefone            CHAR(14)     NOT NULL,
  email               VARCHAR(100) NOT NULL,
  senha               VARCHAR(255) NOT NULL,
  data_criacao_conta  DATE         NOT NULL,
  foto_perfil_url     VARCHAR(50)  DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY email (email)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------- veterinario ----------
CREATE TABLE veterinario (
  id_veterinario  INT           NOT NULL AUTO_INCREMENT,
  nome            VARCHAR(100)  NOT NULL,
  cnpj            CHAR(14)      DEFAULT NULL,
  crmv            VARCHAR(30)   DEFAULT NULL,
  especialidade   VARCHAR(100)  DEFAULT NULL,
  email           VARCHAR(150)  DEFAULT NULL,
  telefone        VARCHAR(25)   DEFAULT NULL,
  cidade          VARCHAR(100)  DEFAULT NULL,
  uf              CHAR(2)       DEFAULT NULL,
  status          VARCHAR(15)   NOT NULL DEFAULT 'Ativo',
  data_cadastro   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  id_usuario      INT           DEFAULT NULL,
  descricao       TEXT          DEFAULT NULL,
  endereco        VARCHAR(255)  DEFAULT NULL,
  servicos        TEXT          DEFAULT NULL,
  planos          TEXT          DEFAULT NULL,
  valor_consulta  DECIMAL(10,2) DEFAULT NULL,
  foto_url        VARCHAR(255)  DEFAULT NULL,
  PRIMARY KEY (id_veterinario),
  UNIQUE KEY uq_veterinario_usuario (id_usuario),   -- um usuário só pode ser um veterinário
  UNIQUE KEY uq_veterinario_crmv (crmv),            -- impede CRMV duplicado
  CONSTRAINT fk_veterinario_usuario FOREIGN KEY (id_usuario) REFERENCES usuario (id)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------- categoria: tipos de animal ----------
CREATE TABLE categoria (
  id_categoria    INT         NOT NULL AUTO_INCREMENT,
  nome_categoria  VARCHAR(80) NOT NULL,
  PRIMARY KEY (id_categoria)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------- protetor (pessoa com CPF; ONGs ficam em "ongs") ----------
CREATE TABLE protetor (
  id_protetor       INT          NOT NULL AUTO_INCREMENT,
  id_usuario        INT          NOT NULL,
  status_aprovacao  VARCHAR(15)  NOT NULL,
  nome_organizacao  VARCHAR(150) DEFAULT NULL,
  descricao         TEXT         DEFAULT NULL,
  email_contato     VARCHAR(150) DEFAULT NULL,
  telefone_contato  VARCHAR(25)  DEFAULT NULL,
  endereco          VARCHAR(255) DEFAULT NULL,
  cidade            VARCHAR(100) DEFAULT NULL,
  uf                CHAR(2)      DEFAULT NULL,
  foto_url          VARCHAR(255) DEFAULT NULL,
  cpf               CHAR(11)     DEFAULT NULL,
  PRIMARY KEY (id_protetor),
  UNIQUE KEY uq_protetor_cpf (cpf),                 -- impede CPF duplicado
  CONSTRAINT fk_protetor_usuario FOREIGN KEY (id_usuario) REFERENCES usuario (id)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------- ongs: dados de CNPJ de um protetor ----------
CREATE TABLE ongs (
  id_ong       INT      NOT NULL AUTO_INCREMENT,
  id_protetor  INT      NOT NULL,
  cnpj         CHAR(14) DEFAULT NULL,
  PRIMARY KEY (id_ong),
  UNIQUE KEY uq_ongs_cnpj (cnpj),                   -- impede CNPJ duplicado
  UNIQUE KEY uq_ongs_protetor (id_protetor),        -- um protetor tem no máximo uma ONG
  CONSTRAINT fk_ong_protetor FOREIGN KEY (id_protetor) REFERENCES protetor (id_protetor)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------- adotante ----------
CREATE TABLE adotante (
  id_adotante  INT NOT NULL AUTO_INCREMENT,
  id_usuario   INT NOT NULL,
  PRIMARY KEY (id_adotante),
  CONSTRAINT fk_adotante_usuario FOREIGN KEY (id_usuario) REFERENCES usuario (id)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------- animal ----------
CREATE TABLE animal (
  id_animal                 INT           NOT NULL AUTO_INCREMENT,
  id_categoria              INT           NOT NULL,
  id_protetor               INT           NOT NULL,
  nome_animal               VARCHAR(50)   NOT NULL,
  descricao                 VARCHAR(1000) NOT NULL,
  especie                   VARCHAR(30)   NOT NULL DEFAULT 'Cachorro',
  raca                      VARCHAR(80)   DEFAULT NULL,
  idade_meses               SMALLINT UNSIGNED DEFAULT NULL,
  sexo                      ENUM('Macho','Fêmea','Não informado') NOT NULL DEFAULT 'Não informado',
  porte                     ENUM('Pequeno','Médio','Grande') DEFAULT NULL,
  cor                       VARCHAR(80)   DEFAULT NULL,
  castrado                  TINYINT(1)    NOT NULL DEFAULT 0,
  vacinado                  TINYINT(1)    NOT NULL DEFAULT 0,
  vermifugado               TINYINT(1)    NOT NULL DEFAULT 0,
  condicao_saude            VARCHAR(500)  DEFAULT NULL,
  compatibilidade_criancas  ENUM('Sim','Não','Não informado') NOT NULL DEFAULT 'Não informado',
  compatibilidade_animais   ENUM('Sim','Não','Não informado') NOT NULL DEFAULT 'Não informado',
  nivel_energia             ENUM('Baixo','Moderado','Alto') NOT NULL DEFAULT 'Moderado',
  cidade                    VARCHAR(80)   DEFAULT NULL,
  uf                        CHAR(2)       DEFAULT 'PR',
  status_adocao             ENUM('Disponível','Em processo','Adotado') NOT NULL DEFAULT 'Disponível',
  data_cadastro             DATE          DEFAULT NULL,
  foto_url                  VARCHAR(255)  DEFAULT NULL,
  status_aprovacao          VARCHAR(20)   NOT NULL DEFAULT 'Aprovado',
  observacao_admin          TEXT          DEFAULT NULL,
  PRIMARY KEY (id_animal),
  KEY idx_animal_aprovacao (status_aprovacao),
  CONSTRAINT fk_animal_categoria FOREIGN KEY (id_categoria) REFERENCES categoria (id_categoria),
  CONSTRAINT fk_animal_protetor  FOREIGN KEY (id_protetor)  REFERENCES protetor (id_protetor)
) ENGINE=InnoDB AUTO_INCREMENT=74 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------- endereco: um endereço por usuário ----------
CREATE TABLE endereco (
  id_usuario   INT          NOT NULL,
  cep          CHAR(8)      NOT NULL,
  uf           CHAR(2)      NOT NULL,
  cidade       VARCHAR(80)  NOT NULL,
  bairro       VARCHAR(50)  NOT NULL,
  rua          VARCHAR(50)  NOT NULL,
  numero       VARCHAR(7)   NOT NULL,
  complemento  VARCHAR(100) DEFAULT NULL,
  PRIMARY KEY (id_usuario),
  CONSTRAINT fk_endereco_usuario FOREIGN KEY (id_usuario) REFERENCES usuario (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------- lista_interesse: cada usuário tem uma lista; lista_animal guarda os animais dela ----------
CREATE TABLE lista_interesse (
  id_lista    INT NOT NULL AUTO_INCREMENT,
  id_usuario  INT NOT NULL,
  PRIMARY KEY (id_lista, id_usuario),
  UNIQUE KEY uq_lista_interesse_usuario (id_usuario),
  CONSTRAINT fk_lista_usuario FOREIGN KEY (id_usuario) REFERENCES usuario (id)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE lista_animal (
  id_lista   INT NOT NULL,
  id_animal  INT NOT NULL,
  PRIMARY KEY (id_lista, id_animal),
  CONSTRAINT fk_lista_animal_lista  FOREIGN KEY (id_lista)  REFERENCES lista_interesse (id_lista),
  CONSTRAINT fk_lista_animal_animal FOREIGN KEY (id_animal) REFERENCES animal (id_animal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------- consulta ----------
CREATE TABLE consulta (
  id_consulta     INT         NOT NULL AUTO_INCREMENT,
  id_usuario      INT         NOT NULL,
  id_veterinario  INT         NOT NULL,
  data_consulta   DATE        DEFAULT NULL,
  horario         TIME        DEFAULT NULL,
  observacao      TEXT        DEFAULT NULL,
  status          VARCHAR(20) NOT NULL DEFAULT 'Agendada',
  PRIMARY KEY (id_consulta),
  CONSTRAINT fk_consulta_usuario     FOREIGN KEY (id_usuario)     REFERENCES usuario (id),
  CONSTRAINT fk_consulta_veterinario FOREIGN KEY (id_veterinario) REFERENCES veterinario (id_veterinario)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------- plano: planos exibidos na página "Planos" ----------
CREATE TABLE plano (
  id_plano        INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  nome            VARCHAR(120)  NOT NULL,
  descricao       TEXT          NOT NULL,
  publico_alvo    VARCHAR(120)  DEFAULT NULL,
  beneficios      TEXT          DEFAULT NULL,
  valor           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  periodicidade   VARCHAR(20)   DEFAULT NULL,
  ativo           TINYINT(1)    NOT NULL DEFAULT 1,
  destaque        TINYINT(1)    NOT NULL DEFAULT 0,
  ordem_exibicao  INT           NOT NULL DEFAULT 0,
  data_cadastro   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_plano),
  KEY idx_plano_ativo_ordem (ativo, ordem_exibicao)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------- plano_de_assinatura ----------
CREATE TABLE plano_de_assinatura (
  id_plano        INT           NOT NULL AUTO_INCREMENT,
  usuario_id      INT           DEFAULT NULL,
  vencimento      DATE          DEFAULT NULL,
  nome            VARCHAR(50)   NOT NULL,
  valor           DECIMAL(10,2) NOT NULL,
  id_veterinario  INT           DEFAULT NULL,
  id_usuario      INT           DEFAULT NULL,
  tipo_registro   VARCHAR(20)   NOT NULL DEFAULT 'assinatura',
  descricao       TEXT          DEFAULT NULL,
  periodicidade   VARCHAR(30)   DEFAULT NULL,
  beneficios      TEXT          DEFAULT NULL,
  destaque        TINYINT(1)    NOT NULL DEFAULT 0,
  status          VARCHAR(20)   NOT NULL DEFAULT 'Ativo',
  ordem           INT           NOT NULL DEFAULT 0,
  PRIMARY KEY (id_plano),
  CONSTRAINT fk_plano_veterinario FOREIGN KEY (id_veterinario) REFERENCES veterinario (id_veterinario),
  CONSTRAINT fk_plano_usuario     FOREIGN KEY (id_usuario)     REFERENCES usuario (id)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------- suporte ----------
CREATE TABLE suporte (
  id             INT           NOT NULL AUTO_INCREMENT,
  descricao      VARCHAR(1000) NOT NULL,
  titulo         VARCHAR(100)  NOT NULL,
  resposta       VARCHAR(1000) DEFAULT NULL,
  data_abertura  DATE          NOT NULL,
  usuario_id     INT           NOT NULL,
  PRIMARY KEY (id),
  CONSTRAINT fk_suporte_usuario FOREIGN KEY (usuario_id) REFERENCES usuario (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------- admin e convites de admin ----------
CREATE TABLE admin (
  id_admin        INT     NOT NULL AUTO_INCREMENT,
  id_usuario      INT     NOT NULL,
  nivel_acesso    CHAR(1) NOT NULL,
  data_concessao  DATE    NOT NULL,
  PRIMARY KEY (id_admin),
  CONSTRAINT fk_admin_usuario FOREIGN KEY (id_usuario) REFERENCES usuario (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE admin_convite (
  id_convite      INT  NOT NULL AUTO_INCREMENT,
  id_admin        INT  NOT NULL,
  id_usuario      INT  NOT NULL,
  data_expiracao  DATE NOT NULL,
  data_envio      DATE NOT NULL,
  PRIMARY KEY (id_convite),
  CONSTRAINT fk_convite_admin   FOREIGN KEY (id_admin)   REFERENCES admin (id_admin),
  CONSTRAINT fk_convite_usuario FOREIGN KEY (id_usuario) REFERENCES usuario (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
