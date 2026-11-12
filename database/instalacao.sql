-- Instala??o limpa do banco Adota? (MySQL/MariaDB).
-- Cria o banco, as tabelas e os dados de demonstra??o.
CREATE DATABASE IF NOT EXISTS adotai_site CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE adotai_site;
SET NAMES utf8mb4;

-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geraÃ§Ã£o: 03/11/2026 Ã s 02:36
-- VersÃ£o do servidor: 10.4.32-MariaDB
-- VersÃ£o do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `adotai_site`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `admin`
--

CREATE TABLE `admin` (
  `id_admin` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `nivel_acesso` char(1) NOT NULL,
  `data_concessao` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `admin_convite`
--

CREATE TABLE `admin_convite` (
  `id_convite` int(11) NOT NULL,
  `id_admin` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `data_expiracao` date NOT NULL,
  `data_envio` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `adotante`
--

CREATE TABLE `adotante` (
  `id_adotante` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `adotante`
--

INSERT INTO `adotante` (`id_adotante`, `id_usuario`) VALUES
(1, 4),
(2, 5),
(5, 8);

-- --------------------------------------------------------

--
-- Estrutura para tabela `animal`
--

CREATE TABLE `animal` (
  `id_animal` int(11) NOT NULL,
  `id_categoria` int(11) NOT NULL,
  `id_protetor` int(11) NOT NULL,
  `nome_animal` varchar(50) NOT NULL,
  `descricao` varchar(1000) NOT NULL,
  `especie` varchar(30) NOT NULL DEFAULT 'Cachorro',
  `raca` varchar(80) DEFAULT NULL,
  `idade_meses` smallint(5) UNSIGNED DEFAULT NULL,
  `sexo` enum('Macho','FÃªmea','NÃ£o informado') NOT NULL DEFAULT 'NÃ£o informado',
  `porte` enum('Pequeno','MÃ©dio','Grande') DEFAULT NULL,
  `cor` varchar(80) DEFAULT NULL,
  `castrado` tinyint(1) NOT NULL DEFAULT 0,
  `vacinado` tinyint(1) NOT NULL DEFAULT 0,
  `vermifugado` tinyint(1) NOT NULL DEFAULT 0,
  `condicao_saude` varchar(500) DEFAULT NULL,
  `compatibilidade_criancas` enum('Sim','NÃ£o','NÃ£o informado') NOT NULL DEFAULT 'NÃ£o informado',
  `compatibilidade_animais` enum('Sim','NÃ£o','NÃ£o informado') NOT NULL DEFAULT 'NÃ£o informado',
  `nivel_energia` enum('Baixo','Moderado','Alto') NOT NULL DEFAULT 'Moderado',
  `cidade` varchar(80) DEFAULT NULL,
  `uf` char(2) DEFAULT 'PR',
  `status_adocao` enum('DisponÃ­vel','Em processo','Adotado') NOT NULL DEFAULT 'DisponÃ­vel',
  `data_cadastro` date DEFAULT NULL,
  `foto_url` varchar(255) DEFAULT NULL,
  `status_aprovacao` varchar(20) NOT NULL DEFAULT 'Aprovado',
  `observacao_admin` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `animal`
--

INSERT INTO `animal` (`id_animal`, `id_categoria`, `id_protetor`, `nome_animal`, `descricao`, `especie`, `raca`, `idade_meses`, `sexo`, `porte`, `cor`, `castrado`, `vacinado`, `vermifugado`, `condicao_saude`, `compatibilidade_criancas`, `compatibilidade_animais`, `nivel_energia`, `cidade`, `uf`, `status_adocao`, `data_cadastro`, `foto_url`, `status_aprovacao`, `observacao_admin`) VALUES
(0, 1, 1, 'Frank', 'Pug de 2 anos, cheio de personalidade e saliÃªncia. Se identifica como pastor alemÃ£o, tem gÃªnero fluido e adora uma balada. Bolsonarista e com fama de misÃ³gino, Frank procura uma famÃ­lia que aguente suas extravagÃ¢ncias.', 'Cachorro', 'Pug', 24, 'Macho', 'Pequeno', 'Bege e preto', 0, 0, 0, 'NÃ£o informado', 'NÃ£o informado', 'NÃ£o informado', 'Alto', 'Curitiba', 'PR', 'DisponÃ­vel', '2026-10-19', 'fotos/cachorros/frank-pug-bege-preto.jpg', 'Aprovado', NULL),
(1, 1, 1, 'Thor', 'Cachorro brincalhÃ£o, carinhoso e sociÃ¡vel, procurando uma famÃ­lia responsÃ¡vel.', 'Cachorro', 'Vira-lata', 18, 'Macho', 'MÃ©dio', 'Caramelo', 1, 1, 1, 'SaudÃ¡vel', 'Sim', 'Sim', 'Alto', 'Curitiba', 'PR', 'DisponÃ­vel', '2026-10-01', 'fotos/cachorros/thor-vira-lata-caramelo.jpg', 'Aprovado', NULL),
(2, 2, 1, 'Luna', 'Gata tranquila que gosta de carinho e de ambientes aconchegantes.', 'Gato', 'SRD', 8, 'FÃªmea', 'Pequeno', 'Cinza', 1, 1, 1, 'SaudÃ¡vel', 'Sim', 'Sim', 'Baixo', 'Curitiba', 'PR', 'DisponÃ­vel', '2026-10-02', 'fotos/gatos/luna-gata-cinza.jpg', 'Aprovado', NULL),
(3, 1, 2, 'Max', 'CÃ£o adulto, leal e atento, ideal para uma famÃ­lia disposta a oferecer passeios diÃ¡rios.', 'Cachorro', 'Labrador', 48, 'Macho', 'Grande', 'Preto', 1, 1, 1, 'SaudÃ¡vel', 'Sim', 'Sim', 'Alto', 'SÃ£o JosÃ© dos Pinhais', 'PR', 'DisponÃ­vel', '2026-10-03', 'fotos/cachorros/max-labrador-preto.jpg', 'Aprovado', NULL),
(4, 2, 2, 'Mel', 'Gatinha curiosa e independente, que precisa de um lar seguro e acolhedor.', 'Gato', 'SRD', 5, 'FÃªmea', 'Pequeno', 'Tricolor', 0, 1, 1, 'Em acompanhamento veterinÃ¡rio', 'Sim', 'NÃ£o informado', 'Moderado', 'Curitiba', 'PR', 'DisponÃ­vel', '2026-10-04', 'fotos/gatos/mel-gata-tricolor.jpg', 'Aprovado', NULL),
(5, 1, 3, 'Bob', 'Cachorro adulto e tranquilo, resgatado e pronto para encontrar um novo lar.', 'Cachorro', 'Vira-lata', 72, 'Macho', 'MÃ©dio', 'Marrom', 1, 1, 1, 'SaudÃ¡vel', 'NÃ£o informado', 'Sim', 'Baixo', 'Colombo', 'PR', 'Em processo', '2026-10-05', 'fotos/cachorros/bob-vira-lata-marrom.jpg', 'Aprovado', NULL),
(16, 1, 1, 'RelÃ¢mpago', 'Cavalo de perfil tranquilo, destinado a um cenÃ¡rio demonstrativo de acolhimento e cuidados responsÃ¡veis.', 'Cavalo', 'Quarto de Milha', 72, 'Macho', 'Grande', 'Castanho', 0, 0, 0, 'NÃ£o informado', 'NÃ£o informado', 'NÃ£o informado', 'Alto', 'Curitiba', 'PR', 'DisponÃ­vel', '2026-10-15', 'fotos/outros/relampago-cavalo-quarto-de-milha-castanho.jpg', 'Aprovado', NULL),
(17, 7, 2, 'Cacau', 'Capivara fictÃ­cia para demonstraÃ§Ã£o do catÃ¡logo de espÃ©cies silvestres e educaÃ§Ã£o sobre conservaÃ§Ã£o.', 'Capivara', 'Capivara', 24, 'FÃªmea', 'Grande', 'Marrom', 0, 0, 0, 'NÃ£o informado', 'NÃ£o informado', 'NÃ£o informado', 'Moderado', 'Curitiba', 'PR', 'DisponÃ­vel', '2026-10-15', 'fotos/outros/cacau-capivara-marrom.jpg', 'Aprovado', NULL),
(18, 7, 3, 'Ziggy', 'Raposa fictÃ­cia para demonstraÃ§Ã£o de cadastro de animais exÃ³ticos; nÃ£o representa oferta real de adoÃ§Ã£o.', 'Raposa', 'Raposa-vermelha', 18, 'Macho', 'MÃ©dio', 'Avermelhado', 0, 0, 0, 'NÃ£o informado', 'NÃ£o informado', 'NÃ£o informado', 'Alto', 'Colombo', 'PR', 'DisponÃ­vel', '2026-10-15', 'fotos/outros/ziggy-raposa-vermelha.jpg', 'Aprovado', NULL),
(19, 7, 1, 'Nina', 'OuriÃ§o-africano fictÃ­cio para testar o cadastro de espÃ©cies nÃ£o convencionais.', 'OuriÃ§o', 'OuriÃ§o-pigmeu-africano', 12, 'FÃªmea', 'Pequeno', 'Marrom e branco', 0, 0, 0, 'NÃ£o informado', 'NÃ£o informado', 'NÃ£o informado', 'Moderado', 'Curitiba', 'PR', 'DisponÃ­vel', '2026-10-15', 'fotos/outros/nina-ourico-pigmeu-africano.jpg', 'Aprovado', NULL),
(20, 7, 2, 'Bento', 'Jabuti-piranga que precisa de um ambiente seguro, espaÃ§o adequado, temperatura controlada e alimentaÃ§Ã£o especÃ­fica para sua espÃ©cie.', 'Jabuti', 'Jabuti-piranga', 36, 'Macho', 'Pequeno', 'Marrom e amarelo', 0, 0, 0, 'NÃ£o informado', 'NÃ£o informado', 'NÃ£o informado', 'Baixo', 'SÃ£o JosÃ© dos Pinhais', 'PR', 'DisponÃ­vel', '2026-10-15', 'fotos/repteis/bento-jabuti-piranga-marrom-amarelo.jpg', 'Aprovado', NULL),
(21, 3, 3, 'Lilo', 'Arara-canindÃ© de plumagem azul e amarela, ativa e curiosa. Precisa de espaÃ§o adequado, enriquecimento ambiental e cuidados especializados.', 'Ave', 'Arara-canindÃ©', 48, 'FÃªmea', 'Grande', 'Azul e amarelo', 0, 0, 0, 'NÃ£o informado', 'NÃ£o informado', 'NÃ£o informado', 'Alto', 'Colombo', 'PR', 'DisponÃ­vel', '2026-10-15', 'fotos/aves/lilo-arara-caninde-azul-amarela.jpg', 'Aprovado', NULL),
(22, 7, 1, 'Tico', 'Sagui de pequeno porte, curioso e cheio de energia. Gosta de explorar o ambiente e precisa de espaÃ§o amplo, enriquecimento ambiental, alimentaÃ§Ã£o especÃ­fica e cuidados especializados.', 'Sagui', 'Sagui', 18, 'Macho', 'Pequeno', 'Cinza e branco', 0, 0, 0, 'NÃ£o informado', 'NÃ£o informado', 'NÃ£o informado', 'Alto', 'Curitiba', 'PR', 'DisponÃ­vel', '2026-10-15', 'fotos/outros/tico-sagui-cinza-branco.jpg', 'Aprovado', NULL),
(23, 3, 2, 'PÃ©rola', 'PavÃ£o de plumagem azul e verde, imponente e ativo. Precisa de uma Ã¡rea externa ampla e segura para caminhar, abrir a cauda e explorar o ambiente. Sua criaÃ§Ã£o exige planejamento, abrigo adequado e cuidados especializados.', 'Ave', 'PavÃ£o-azul', 30, 'FÃªmea', 'Grande', 'Azul e verde', 0, 0, 0, 'NÃ£o informado', 'NÃ£o informado', 'NÃ£o informado', 'Alto', 'SÃ£o JosÃ© dos Pinhais', 'PR', 'DisponÃ­vel', '2026-10-15', 'fotos/aves/perola-pavao-azul-verde.jpg', 'Aprovado', NULL),
(24, 3, 1, 'Billy', 'Calopsita dÃ³cil e curiosa, ideal para quem deseja conhecer os cuidados com aves.', 'Ave', 'Calopsita', 12, 'Macho', 'Pequeno', 'Cinza e amarelo', 0, 0, 0, 'NÃ£o informado', 'NÃ£o informado', 'NÃ£o informado', 'Moderado', 'Curitiba', 'PR', 'DisponÃ­vel', '2026-10-15', 'fotos/aves/billy-calopsita-cinza-amarela.jpg', 'Aprovado', NULL),
(25, 1, 2, 'Jhully', 'Cadela carinhosa, com pelagem longa e personalidade companheira.', 'Cachorro', 'Lhasa Apso', 24, 'FÃªmea', 'Pequeno', 'Branco e caramelo', 1, 1, 1, 'SaudÃ¡vel', 'Sim', 'Sim', 'Moderado', 'Curitiba', 'PR', 'DisponÃ­vel', '2026-10-15', 'fotos/cachorros/jhully-lhasa-apso-branco-caramelo.jpg', 'Aprovado', NULL),
(26, 1, 3, 'Melzinha', 'Cadela brincalhona que gosta de companhia e passeios.', 'Cachorro', 'Shih-tzu', 18, 'FÃªmea', 'Pequeno', 'Branco e marrom', 1, 1, 1, 'SaudÃ¡vel', 'Sim', 'Sim', 'Moderado', 'Curitiba', 'PR', 'DisponÃ­vel', '2026-10-15', 'fotos/cachorros/melzinha-shih-tzu-branco-marrom.jpg', 'Aprovado', NULL),
(27, 2, 1, 'Apollo', 'Gato tranquilo, curioso e acostumado a ambientes internos.', 'Gato', 'SiamÃªs', 14, 'Macho', 'Pequeno', 'Creme e marrom', 1, 1, 1, 'SaudÃ¡vel', 'Sim', 'Sim', 'Moderado', 'Curitiba', 'PR', 'DisponÃ­vel', '2026-10-15', 'fotos/gatos/apollo-siames-creme-marrom.jpg', 'Aprovado', NULL),
(28, 1, 2, 'Nina', 'Cadela pequena, atenta e cheia de energia.', 'Cachorro', 'Pinscher', 10, 'FÃªmea', 'Pequeno', 'Preto e caramelo', 0, 1, 1, 'SaudÃ¡vel', 'NÃ£o informado', 'NÃ£o informado', 'Alto', 'Curitiba', 'PR', 'DisponÃ­vel', '2026-10-15', 'fotos/cachorros/nina-pinscher-preto-caramelo.jpg', 'Aprovado', NULL),
(29, 2, 3, 'Simba', 'Gato sociÃ¡vel que adora brincar e descansar em lugares aconchegantes.', 'Gato', 'SRD', 8, 'Macho', 'Pequeno', 'Laranja e branco', 0, 1, 1, 'SaudÃ¡vel', 'Sim', 'Sim', 'Alto', 'Curitiba', 'PR', 'DisponÃ­vel', '2026-10-15', 'fotos/gatos/simba-srd-laranja-branco.jpg', 'Aprovado', NULL),
(30, 1, 1, 'Floquinho', 'CÃ£o de pequeno porte, companheiro e com muita disposiÃ§Ã£o para passear.', 'Cachorro', 'Poodle', 36, 'Macho', 'Pequeno', 'Branco', 1, 1, 1, 'SaudÃ¡vel', 'Sim', 'Sim', 'Moderado', 'SÃ£o JosÃ© dos Pinhais', 'PR', 'DisponÃ­vel', '2026-10-15', 'fotos/cachorros/floquinho-poodle-branco.jpg', 'Aprovado', NULL),
(31, 2, 2, 'Cookie', 'Gatinha independente, curiosa e carinhosa quando ganha confianÃ§a.', 'Gato', 'SRD', 6, 'FÃªmea', 'Pequeno', 'Tricolor', 0, 1, 0, 'NÃ£o informado', 'NÃ£o informado', 'Sim', 'Moderado', 'Curitiba', 'PR', 'DisponÃ­vel', '2026-10-15', 'fotos/gatos/cookie-srd-tricolor.jpg', 'Aprovado', NULL),
(32, 4, 3, 'PaÃ§oca', 'Coelho curioso que precisa de espaÃ§o seguro e alimentaÃ§Ã£o adequada.', 'Coelho', 'Mini Lop', 9, 'Macho', 'Pequeno', 'Caramelo', 0, 0, 0, 'NÃ£o informado', 'NÃ£o informado', 'NÃ£o informado', 'Moderado', 'Curitiba', 'PR', 'DisponÃ­vel', '2026-10-15', 'fotos/coelhos/pacoca-mini-lop-caramelo.jpg', 'Aprovado', NULL),
(33, 4, 1, 'Lola', 'Coelha tranquila, com pelagem macia e comportamento curioso.', 'Coelho', 'Mini Rex', 11, 'FÃªmea', 'Pequeno', 'Cinza', 0, 0, 0, 'NÃ£o informado', 'NÃ£o informado', 'NÃ£o informado', 'Baixo', 'Pinhais', 'PR', 'DisponÃ­vel', '2026-10-15', 'fotos/coelhos/lola-mini-rex-cinza.jpg', 'Aprovado', NULL),
(34, 1, 2, 'Apollo Jr.', 'CÃ£o de porte grande, brincalhÃ£o e que precisa de passeios regulares.', 'Cachorro', 'Golden Retriever', 30, 'Macho', 'Grande', 'Dourado', 1, 1, 1, 'SaudÃ¡vel', 'Sim', 'Sim', 'Alto', 'Curitiba', 'PR', 'DisponÃ­vel', '2026-10-15', 'fotos/cachorros/apollo-jr-golden-retriever-dourado.jpg', 'Aprovado', NULL),
(35, 2, 3, 'Jade', 'Gata de pelagem escura, observadora e tranquila.', 'Gato', 'SRD', 20, 'FÃªmea', 'Pequeno', 'Preto', 1, 1, 1, 'SaudÃ¡vel', 'Sim', 'Sim', 'Baixo', 'Curitiba', 'PR', 'DisponÃ­vel', '2026-10-15', 'fotos/gatos/jade-srd-preto.jpg', 'Aprovado', NULL),
(36, 3, 1, 'LelÃ©', 'Periquito-australiano ativo, que gosta de interagir com o ambiente.', 'Ave', 'Periquito-australiano', 8, 'FÃªmea', 'Pequeno', 'Verde e amarelo', 0, 0, 0, 'NÃ£o informado', 'NÃ£o informado', 'NÃ£o informado', 'Alto', 'Curitiba', 'PR', 'DisponÃ­vel', '2026-10-15', 'fotos/aves/lele-periquito-australiano-verde-amarelo.jpg', 'Aprovado', NULL),
(37, 5, 2, 'Amendoim', 'Hamster pequeno e curioso, com hÃ¡bitos principalmente noturnos.', 'Roedor', 'SÃ­rio', 5, 'Macho', 'Pequeno', 'Dourado e branco', 0, 0, 0, 'NÃ£o informado', 'NÃ£o informado', 'NÃ£o informado', 'Moderado', 'Curitiba', 'PR', 'DisponÃ­vel', '2026-10-15', 'fotos/roedores/amendoim-hamster-sirio-dourado.jpg', 'Aprovado', NULL),
(38, 5, 3, 'Pipoca', 'Porquinho-da-Ã­ndia sociÃ¡vel que precisa de alimentaÃ§Ã£o rica em fibras.', 'Roedor', 'AbissÃ­nio', 7, 'FÃªmea', 'Pequeno', 'Marrom e branco', 0, 0, 0, 'NÃ£o informado', 'NÃ£o informado', 'Sim', 'Moderado', 'Colombo', 'PR', 'DisponÃ­vel', '2026-10-15', 'fotos/roedores/pipoca-porquinho-da-india-marrom-branco.jpg', 'Aprovado', NULL),
(40, 6, 2, 'Tuga', 'Jabuti de demonstraÃ§Ã£o cadastrado para testar a categoria de rÃ©pteis.', 'Jabuti', 'Jabuti-piranga', 36, 'Macho', 'Pequeno', 'Marrom e amarelo', 0, 0, 0, 'NÃ£o informado', 'NÃ£o informado', 'NÃ£o informado', 'Baixo', 'Curitiba', 'PR', 'DisponÃ­vel', '2026-10-15', 'fotos/repteis/tuga-jabuti-piranga-marrom-amarelo.jpg', 'Aprovado', NULL),
(41, 1, 3, 'Belinha', 'Cadela de pernas curtas, curiosa e companheira.', 'Cachorro', 'Dachshund', 22, 'FÃªmea', 'Pequeno', 'Marrom', 1, 1, 1, 'SaudÃ¡vel', 'Sim', 'Sim', 'Moderado', 'Curitiba', 'PR', 'DisponÃ­vel', '2026-10-15', 'fotos/cachorros/belinha-dachshund-marrom.jpg', 'Aprovado', NULL),
(42, 2, 1, 'Ozzy', 'Gato de pelagem longa que precisa de escovaÃ§Ã£o frequente.', 'Gato', 'Persa', 16, 'Macho', 'Pequeno', 'Branco', 1, 1, 1, 'SaudÃ¡vel', 'Sim', 'NÃ£o informado', 'Baixo', 'Curitiba', 'PR', 'DisponÃ­vel', '2026-10-15', 'fotos/gatos/ozzy-persa-branco.jpg', 'Aprovado', NULL),
(43, 1, 2, 'Kiko', 'CÃ£o curioso e ativo, ideal para uma rotina com passeios e brincadeiras.', 'Cachorro', 'Beagle', 20, 'Macho', 'MÃ©dio', 'Tricolor', 1, 1, 1, 'SaudÃ¡vel', 'Sim', 'Sim', 'Alto', 'AraucÃ¡ria', 'PR', 'DisponÃ­vel', '2026-10-15', 'fotos/cachorros/kiko-beagle-tricolor.jpg', 'Aprovado', NULL),
(44, 2, 3, 'Cacau', 'Gata afetuosa, de olhos claros e pelagem em tons contrastantes.', 'Gato', 'SiamÃªs', 10, 'FÃªmea', 'Pequeno', 'Creme e marrom', 0, 1, 1, 'SaudÃ¡vel', 'Sim', 'Sim', 'Moderado', 'Curitiba', 'PR', 'DisponÃ­vel', '2026-10-15', 'fotos/gatos/cacau-siames-creme-marrom.jpg', 'Aprovado', NULL),
(45, 3, 1, 'Melodia', 'CanÃ¡rio de canto caracterÃ­stico, cadastrado para demonstraÃ§Ã£o.', 'Ave', 'CanÃ¡rio-belga', 9, 'Macho', 'Pequeno', 'Amarelo', 0, 0, 0, 'NÃ£o informado', 'NÃ£o informado', 'NÃ£o informado', 'Moderado', 'Curitiba', 'PR', 'DisponÃ­vel', '2026-10-15', 'fotos/aves/melodia-canario-belga-amarelo.jpg', 'Aprovado', NULL),
(46, 1, 2, 'Fifi', 'Cadela de porte pequeno, ativa e companheira.', 'Cachorro', 'Yorkshire Terrier', 15, 'FÃªmea', 'Pequeno', 'Cinza e caramelo', 1, 1, 1, 'SaudÃ¡vel', 'Sim', 'Sim', 'Alto', 'Curitiba', 'PR', 'DisponÃ­vel', '2026-10-15', 'fotos/cachorros/fifi-yorkshire-terrier-cinza-caramelo.jpg', 'Aprovado', NULL),
(47, 2, 3, 'Mingau', 'Gato tranquilo, de pelagem clara, que gosta de descansar perto das pessoas.', 'Gato', 'SRD', 28, 'Macho', 'Pequeno', 'Branco e cinza', 1, 1, 1, 'SaudÃ¡vel', 'Sim', 'Sim', 'Baixo', 'Curitiba', 'PR', 'DisponÃ­vel', '2026-10-15', 'fotos/gatos/mingau-srd-branco-cinza.jpg', 'Aprovado', NULL),
(49, 4, 2, 'Pompom', 'Coelho de pelagem fofa que precisa de ambiente seguro e enriquecimento.', 'Coelho', 'Lionhead', 6, 'FÃªmea', 'Pequeno', 'Branco e cinza', 0, 0, 0, 'NÃ£o informado', 'NÃ£o informado', 'NÃ£o informado', 'Moderado', 'Pinhais', 'PR', 'DisponÃ­vel', '2026-10-15', 'fotos/coelhos/pompom-lionhead-branco-cinza.jpg', 'Aprovado', NULL),
(50, 1, 3, 'Toddy', 'CÃ£o sem raÃ§a definida, brincalhÃ£o e Ã  procura de uma famÃ­lia.', 'Cachorro', 'Vira-lata', 12, 'Macho', 'MÃ©dio', 'Caramelo', 0, 1, 1, 'SaudÃ¡vel', 'Sim', 'Sim', 'Alto', 'Curitiba', 'PR', 'DisponÃ­vel', '2026-10-15', 'fotos/cachorros/toddy-vira-lata-caramelo.jpg', 'Aprovado', NULL),
(69, 4, 1, 'Lucas Teixeira', 'Coelho alto, nerd e safado. Especialista em Como Treinar o Seu DragÃ£o, usa Ã³culos e assiste a animes de romance. Procura uma dama para adotÃ¡-lo e viver seu prÃ³prio romance de anime.', 'Coelho', 'Coelho-comum', 24, 'Macho', 'MÃ©dio', 'Branco e marrom', 0, 1, 1, 'SaudÃ¡vel', 'Sim', 'Sim', 'Alto', 'Curitiba', 'PR', 'DisponÃ­vel', '2026-10-19', 'fotos/coelhos/lucas-teixeira-coelho.jpg', 'Aprovado', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `categoria`
--

CREATE TABLE `categoria` (
  `id_categoria` int(11) NOT NULL,
  `nome_categoria` varchar(80) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `categoria`
--

INSERT INTO `categoria` (`id_categoria`, `nome_categoria`) VALUES
(1, 'Cachorro'),
(2, 'Gato'),
(3, 'Ave'),
(4, 'Coelho'),
(5, 'Roedor'),
(6, 'RÃ©ptil'),
(7, 'Outro');

-- --------------------------------------------------------

--
-- Estrutura para tabela `consulta`
--

CREATE TABLE `consulta` (
  `id_consulta` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_veterinario` int(11) NOT NULL,
  `data_consulta` date DEFAULT NULL,
  `horario` time DEFAULT NULL,
  `observacao` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Agendada'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `endereco`
--

CREATE TABLE `endereco` (
  `id_usuario` int(11) NOT NULL,
  `cep` char(8) NOT NULL,
  `uf` char(2) NOT NULL,
  `cidade` varchar(80) NOT NULL,
  `bairro` varchar(50) NOT NULL,
  `rua` varchar(50) NOT NULL,
  `numero` varchar(7) NOT NULL,
  `complemento` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `endereco`
--

INSERT INTO `endereco` (`id_usuario`, `cep`, `uf`, `cidade`, `bairro`, `rua`, `numero`, `complemento`) VALUES
(1, '80010000', 'PR', 'Curitiba', 'Centro', 'Rua das Flores', '100', NULL),
(2, '80020000', 'PR', 'Curitiba', 'SÃ£o Francisco', 'Rua dos Animais', '200', NULL),
(3, '83405000', 'PR', 'Colombo', 'Centro', 'Rua da EsperanÃ§a', '300', NULL),
(4, '80030000', 'PR', 'Curitiba', 'RebouÃ§as', 'Rua das Palmeiras', '400', NULL),
(5, '83005000', 'PR', 'SÃ£o JosÃ© dos Pinhais', 'Centro', 'Rua do Sol', '500', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `lista_animal`
--

CREATE TABLE `lista_animal` (
  `id_lista` int(11) NOT NULL,
  `id_animal` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `lista_animal`
--

INSERT INTO `lista_animal` (`id_lista`, `id_animal`) VALUES
(2, 0);

-- --------------------------------------------------------

--
-- Estrutura para tabela `lista_interesse`
--

CREATE TABLE `lista_interesse` (
  `id_lista` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `lista_interesse`
--

INSERT INTO `lista_interesse` (`id_lista`, `id_usuario`) VALUES
(2, 8);

-- --------------------------------------------------------

--
-- Estrutura para tabela `ongs`
--

CREATE TABLE `ongs` (
  `id_ong` int(11) NOT NULL,
  `id_protetor` int(11) NOT NULL,
  `cnpj` char(14) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `ongs`
--

INSERT INTO `ongs` (`id_ong`, `id_protetor`, `cnpj`) VALUES
(1, 1, '12345678000199'),
(2, 2, '23456789000188'),
(3, 3, '34567890000177');

-- --------------------------------------------------------

--
-- Estrutura para tabela `plano`
--

CREATE TABLE `plano` (
  `id_plano` int(10) UNSIGNED NOT NULL,
  `nome` varchar(120) NOT NULL,
  `descricao` text NOT NULL,
  `publico_alvo` varchar(120) DEFAULT NULL,
  `beneficios` text DEFAULT NULL,
  `valor` decimal(10,2) NOT NULL DEFAULT 0.00,
  `periodicidade` varchar(20) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `destaque` tinyint(1) NOT NULL DEFAULT 0,
  `ordem_exibicao` int(11) NOT NULL DEFAULT 0,
  `data_cadastro` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `plano`
--

INSERT INTO `plano` (`id_plano`, `nome`, `descricao`, `publico_alvo`, `beneficios`, `valor`, `periodicidade`, `ativo`, `destaque`, `ordem_exibicao`, `data_cadastro`) VALUES
(1, 'Adotante', 'Encontre seu novo melhor amigo e acompanhe suas oportunidades de adoÃ§Ã£o.', 'Pessoas interessadas em adotar', 'Visualizar animais disponÃ­veis;Salvar animais favoritos;Demonstrar interesse em adoÃ§Ãµes;Acompanhar solicitaÃ§Ãµes', 0.00, 'Gratuito', 1, 0, 1, '2026-11-01 00:54:29'),
(2, 'Protetor Independente', 'Divulgue animais resgatados e organize seus processos de adoÃ§Ã£o.', 'Protetores independentes', 'Cadastrar animais;Gerenciar interessados;Divulgar animais;Acompanhar processos de adoÃ§Ã£o', 0.00, 'Gratuito', 1, 1, 2, '2026-11-01 00:54:29'),
(3, 'ONG / InstituiÃ§Ã£o', 'Tenha mais organizaÃ§Ã£o para divulgar animais e conectar instituiÃ§Ãµes a novos adotantes.', 'ONGs e instituiÃ§Ãµes de proteÃ§Ã£o animal', 'Divulgar animais;Gerenciar interessados;Organizar processos de adoÃ§Ã£o;Centralizar informaÃ§Ãµes', 0.00, 'Gratuito', 1, 0, 3, '2026-11-01 00:54:29');

-- --------------------------------------------------------

--
-- Estrutura para tabela `plano_de_assinatura`
--

CREATE TABLE `plano_de_assinatura` (
  `id_plano` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `vencimento` date DEFAULT NULL,
  `nome` varchar(50) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `id_veterinario` int(11) DEFAULT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `tipo_registro` varchar(20) NOT NULL DEFAULT 'assinatura',
  `descricao` text DEFAULT NULL,
  `periodicidade` varchar(30) DEFAULT NULL,
  `beneficios` text DEFAULT NULL,
  `destaque` tinyint(1) NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'Ativo',
  `ordem` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `plano_de_assinatura`
--

INSERT INTO `plano_de_assinatura` (`id_plano`, `usuario_id`, `vencimento`, `nome`, `valor`, `id_veterinario`, `id_usuario`, `tipo_registro`, `descricao`, `periodicidade`, `beneficios`, `destaque`, `status`, `ordem`) VALUES
(1, NULL, NULL, 'Adotante', 0.00, NULL, NULL, 'plano', 'Plano gratuito para pessoas que desejam encontrar um animal para adoÃ§Ã£o.', 'Gratuito', 'Visualizar animais disponÃ­veis\nAdicionar animais aos favoritos\nDemonstrar interesse em adoÃ§Ãµes', 0, 'Ativo', 1),
(2, NULL, NULL, 'Protetor Independente', 0.00, NULL, NULL, 'plano', 'Plano gratuito para protetores que desejam divulgar animais para adoÃ§Ã£o.', 'Gratuito', 'Cadastrar animais para adoÃ§Ã£o\nGerenciar anÃºncios\nReceber interessados na adoÃ§Ã£o', 0, 'Ativo', 2),
(3, NULL, NULL, 'ONG / InstituiÃ§Ã£o', 0.00, NULL, NULL, 'plano', 'Plano gratuito para instituiÃ§Ãµes de proteÃ§Ã£o animal.', 'Gratuito', 'Divulgar animais disponÃ­veis\nGerenciar animais cadastrados\nAmpliar o alcance das adoÃ§Ãµes', 1, 'Ativo', 3);

-- --------------------------------------------------------

--
-- Estrutura para tabela `protetor`
--

CREATE TABLE `protetor` (
  `id_protetor` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `status_aprovacao` varchar(15) NOT NULL,
  `nome_organizacao` varchar(150) DEFAULT NULL,
  `descricao` text DEFAULT NULL,
  `email_contato` varchar(150) DEFAULT NULL,
  `telefone_contato` varchar(25) DEFAULT NULL,
  `endereco` varchar(255) DEFAULT NULL,
  `cidade` varchar(100) DEFAULT NULL,
  `uf` char(2) DEFAULT NULL,
  `foto_url` varchar(255) DEFAULT NULL,
  `cpf` char(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `protetor`
--

INSERT INTO `protetor` (`id_protetor`, `id_usuario`, `status_aprovacao`, `nome_organizacao`, `descricao`, `email_contato`, `telefone_contato`, `endereco`, `cidade`, `uf`, `foto_url`) VALUES
(1, 1, 'Aprovado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(2, 2, 'Aprovado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(3, 3, 'Aprovado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `suporte`
--

CREATE TABLE `suporte` (
  `id` int(11) NOT NULL,
  `descricao` varchar(1000) NOT NULL,
  `titulo` varchar(100) NOT NULL,
  `resposta` varchar(1000) DEFAULT NULL,
  `data_abertura` date NOT NULL,
  `usuario_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuario`
--

CREATE TABLE `usuario` (
  `id` int(11) NOT NULL,
  `nome` varchar(120) NOT NULL DEFAULT '',
  `status_conta` varchar(15) NOT NULL,
  `telefone` char(14) NOT NULL,
  `email` varchar(100) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `data_criacao_conta` date NOT NULL,
  `foto_perfil_url` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `usuario`
--

INSERT INTO `usuario` (`id`, `nome`, `status_conta`, `telefone`, `email`, `senha`, `data_criacao_conta`, `foto_perfil_url`) VALUES
(1, '', 'Ativa', '41990000001', 'contato@patinhasfelizes.test', 'TESTE_NAO_USAR_LOGIN', '2026-10-01', NULL),
(2, '', 'Ativa', '41990000002', 'contato@amigobicho.test', 'TESTE_NAO_USAR_LOGIN', '2026-10-02', NULL),
(3, '', 'Ativa', '41990000003', 'contato@laranimal.test', 'TESTE_NAO_USAR_LOGIN', '2026-10-03', NULL),
(4, '', 'Ativa', '41990000004', 'mariana.adotante@test', 'TESTE_NAO_USAR_LOGIN', '2026-10-04', NULL),
(5, '', 'Ativa', '41990000005', 'pedro.adotante@test', 'TESTE_NAO_USAR_LOGIN', '2026-10-05', NULL),
(8, 'eduardo', 'Ativa', '41984036315', 'eduardocavalheirodias4@gmail.com', '$2y$10$XDZKJ2RX17xjjs2detJU1uituhKS6hehQLLXvw.1KknkpIRCr4Fvq', '2026-11-01', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `veterinario`
--

CREATE TABLE `veterinario` (
  `id_veterinario` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `cnpj` char(14) DEFAULT NULL,
  `crmv` varchar(30) DEFAULT NULL,
  `especialidade` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `telefone` varchar(25) DEFAULT NULL,
  `cidade` varchar(100) DEFAULT NULL,
  `uf` char(2) DEFAULT NULL,
  `status` varchar(15) NOT NULL DEFAULT 'Ativo',
  `data_cadastro` datetime NOT NULL DEFAULT current_timestamp(),
  `id_usuario` int(11) DEFAULT NULL,
  `descricao` text DEFAULT NULL,
  `endereco` varchar(255) DEFAULT NULL,
  `servicos` text DEFAULT NULL,
  `planos` text DEFAULT NULL,
  `valor_consulta` decimal(10,2) DEFAULT NULL,
  `foto_url` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `veterinario`
--

INSERT INTO `veterinario` (`id_veterinario`, `nome`, `cnpj`, `crmv`, `especialidade`, `email`, `telefone`, `cidade`, `uf`, `status`, `data_cadastro`, `id_usuario`, `descricao`, `endereco`, `servicos`, `planos`, `valor_consulta`, `foto_url`) VALUES
(1, 'Ana Martins', NULL, 'CRMV-PR-1001', NULL, NULL, NULL, NULL, NULL, 'Ativo', '2026-11-01 22:13:16', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(2, 'Carlos Oliveira', NULL, 'CRMV-PR-1002', NULL, NULL, NULL, NULL, NULL, 'Ativo', '2026-11-01 22:13:16', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(3, 'Fernanda Souza', NULL, 'CRMV-PR-1003', NULL, NULL, NULL, NULL, NULL, 'Ativo', '2026-11-01 22:13:16', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(4, 'Roberto Lima', NULL, 'CRMV-PR-1004', NULL, NULL, NULL, NULL, NULL, 'Ativo', '2026-11-01 22:13:16', NULL, NULL, NULL, NULL, NULL, NULL, NULL);

--
-- Ãndices para tabelas despejadas
--

--
-- Ãndices de tabela `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id_admin`),
  ADD KEY `fk_admin_usuario` (`id_usuario`);

--
-- Ãndices de tabela `admin_convite`
--
ALTER TABLE `admin_convite`
  ADD PRIMARY KEY (`id_convite`),
  ADD KEY `fk_convite_admin` (`id_admin`),
  ADD KEY `fk_convite_usuario` (`id_usuario`);

--
-- Ãndices de tabela `adotante`
--
ALTER TABLE `adotante`
  ADD PRIMARY KEY (`id_adotante`),
  ADD KEY `fk_adotante_usuario` (`id_usuario`);

--
-- Ãndices de tabela `animal`
--
ALTER TABLE `animal`
  ADD PRIMARY KEY (`id_animal`),
  ADD KEY `fk_animal_categoria` (`id_categoria`),
  ADD KEY `fk_animal_protetor` (`id_protetor`),
  ADD KEY `idx_animal_aprovacao` (`status_aprovacao`);

--
-- Ãndices de tabela `categoria`
--
ALTER TABLE `categoria`
  ADD PRIMARY KEY (`id_categoria`);

--
-- Ãndices de tabela `consulta`
--
ALTER TABLE `consulta`
  ADD PRIMARY KEY (`id_consulta`),
  ADD KEY `fk_consulta_usuario` (`id_usuario`),
  ADD KEY `fk_consulta_veterinario` (`id_veterinario`);

--
-- Ãndices de tabela `endereco`
--
ALTER TABLE `endereco`
  ADD PRIMARY KEY (`id_usuario`);

--
-- Ãndices de tabela `lista_animal`
--
ALTER TABLE `lista_animal`
  ADD PRIMARY KEY (`id_lista`,`id_animal`),
  ADD KEY `fk_lista_animal_animal` (`id_animal`),
  ADD KEY `fk_IDlista_listainteresse` (`id_lista`);

--
-- Ãndices de tabela `lista_interesse`
--
ALTER TABLE `lista_interesse`
  ADD PRIMARY KEY (`id_lista`,`id_usuario`),
  ADD UNIQUE KEY `uq_lista_interesse_usuario` (`id_usuario`),
  ADD KEY `fk_lista_usuario` (`id_usuario`);

--
-- Ãndices de tabela `ongs`
--
ALTER TABLE `ongs`
  ADD PRIMARY KEY (`id_ong`),
  ADD KEY `fk_ong_protetor` (`id_protetor`);

--
-- Ãndices de tabela `plano`
--
ALTER TABLE `plano`
  ADD PRIMARY KEY (`id_plano`),
  ADD KEY `idx_plano_ativo_ordem` (`ativo`,`ordem_exibicao`);

--
-- Ãndices de tabela `plano_de_assinatura`
--
ALTER TABLE `plano_de_assinatura`
  ADD PRIMARY KEY (`id_plano`),
  ADD KEY `fk_plano_veterinario` (`id_veterinario`),
  ADD KEY `fk_plano_usuario` (`id_usuario`);

--
-- Ãndices de tabela `protetor`
--
ALTER TABLE `protetor`
  ADD PRIMARY KEY (`id_protetor`),
  ADD KEY `fk_protetor_usuario` (`id_usuario`);

--
-- Ãndices de tabela `suporte`
--
ALTER TABLE `suporte`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_suporte_usuario` (`usuario_id`);

--
-- Ãndices de tabela `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Ãndices de tabela `veterinario`
--
ALTER TABLE `veterinario`
  ADD PRIMARY KEY (`id_veterinario`),
  ADD UNIQUE KEY `uq_veterinario_usuario` (`id_usuario`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `adotante`
--
ALTER TABLE `adotante`
  MODIFY `id_adotante` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de tabela `animal`
--
ALTER TABLE `animal`
  MODIFY `id_animal` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=74;

--
-- AUTO_INCREMENT de tabela `consulta`
--
ALTER TABLE `consulta`
  MODIFY `id_consulta` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `lista_interesse`
--
ALTER TABLE `lista_interesse`
  MODIFY `id_lista` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `plano`
--
ALTER TABLE `plano`
  MODIFY `id_plano` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `plano_de_assinatura`
--
ALTER TABLE `plano_de_assinatura`
  MODIFY `id_plano` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `protetor`
--
ALTER TABLE `protetor`
  MODIFY `id_protetor` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `usuario`
--
ALTER TABLE `usuario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT de tabela `veterinario`
--
ALTER TABLE `veterinario`
  MODIFY `id_veterinario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- RestriÃ§Ãµes para tabelas despejadas
--

--
-- RestriÃ§Ãµes para tabelas `admin`
--
ALTER TABLE `admin`
  ADD CONSTRAINT `fk_admin_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id`);

--
-- RestriÃ§Ãµes para tabelas `admin_convite`
--
ALTER TABLE `admin_convite`
  ADD CONSTRAINT `fk_convite_admin` FOREIGN KEY (`id_admin`) REFERENCES `admin` (`id_admin`),
  ADD CONSTRAINT `fk_convite_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id`);

--
-- RestriÃ§Ãµes para tabelas `adotante`
--
ALTER TABLE `adotante`
  ADD CONSTRAINT `fk_adotante_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id`);

--
-- RestriÃ§Ãµes para tabelas `animal`
--
ALTER TABLE `animal`
  ADD CONSTRAINT `fk_animal_categoria` FOREIGN KEY (`id_categoria`) REFERENCES `categoria` (`id_categoria`),
  ADD CONSTRAINT `fk_animal_protetor` FOREIGN KEY (`id_protetor`) REFERENCES `protetor` (`id_protetor`);

--
-- RestriÃ§Ãµes para tabelas `consulta`
--
ALTER TABLE `consulta`
  ADD CONSTRAINT `fk_consulta_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id`),
  ADD CONSTRAINT `fk_consulta_veterinario` FOREIGN KEY (`id_veterinario`) REFERENCES `veterinario` (`id_veterinario`);

--
-- RestriÃ§Ãµes para tabelas `endereco`
--
ALTER TABLE `endereco`
  ADD CONSTRAINT `fk_endereco_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id`);

--
-- RestriÃ§Ãµes para tabelas `lista_animal`
--
ALTER TABLE `lista_animal`
  ADD CONSTRAINT `fk_lista_animal_animal` FOREIGN KEY (`id_animal`) REFERENCES `animal` (`id_animal`),
  ADD CONSTRAINT `fk_lista_animal_lista` FOREIGN KEY (`id_lista`) REFERENCES `lista_interesse` (`id_lista`);

--
-- RestriÃ§Ãµes para tabelas `lista_interesse`
--
ALTER TABLE `lista_interesse`
  ADD CONSTRAINT `fk_lista_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id`);

--
-- RestriÃ§Ãµes para tabelas `ongs`
--
ALTER TABLE `ongs`
  ADD CONSTRAINT `fk_ong_protetor` FOREIGN KEY (`id_protetor`) REFERENCES `protetor` (`id_protetor`);

--
-- RestriÃ§Ãµes para tabelas `plano_de_assinatura`
--
ALTER TABLE `plano_de_assinatura`
  ADD CONSTRAINT `fk_plano_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id`),
  ADD CONSTRAINT `fk_plano_veterinario` FOREIGN KEY (`id_veterinario`) REFERENCES `veterinario` (`id_veterinario`);

--
-- RestriÃ§Ãµes para tabelas `protetor`
--
ALTER TABLE `protetor`
  ADD CONSTRAINT `fk_protetor_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id`);

--
-- RestriÃ§Ãµes para tabelas `suporte`
--
ALTER TABLE `suporte`
  ADD CONSTRAINT `fk_suporte_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuario` (`id`);
COMMIT;

--
-- AUTO_INCREMENT de tabela `ongs`
--
ALTER TABLE `ongs`
  MODIFY `id_ong` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `admin`
--

ALTER TABLE `admin` 
  MODIFY `id_admin` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `admin_convite`
--

ALTER TABLE `admin_convite` 
  MODIFY `id_convite` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `suporte`
--
  
ALTER TABLE `suporte`       
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `categoria`
--

ALTER TABLE `categoria`
  MODIFY `id_categoria` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;


/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- Recursos adicionais usados pelo site atual.
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
