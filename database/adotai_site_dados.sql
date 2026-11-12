-- ============================================================================
-- Adotaí - dados iniciais do banco adotai_site
--
-- Rode DEPOIS de adotai_site_estrutura.sql (as tabelas precisam existir).
-- As tabelas estão na ordem das chaves estrangeiras: "pais" antes dos "filhos".
--
-- CONTAS DE DEMONSTRAÇÃO (uma de cada tipo) - senha de todas: adotai@test2026
--   Adotante    adotante.demo@adotai.com
--   Protetor    protetor.demo@adotai.com      (pessoa física, já aprovado)
--   Veterinário veterinario.demo@adotai.com
--   Admin       admin.demo@adotai.com
-- Troque ou apague estas contas antes de publicar o site de verdade.
-- ============================================================================

USE adotai_site;
SET NAMES utf8mb4;


-- usuario
INSERT INTO usuario (id, nome, status_conta, telefone, email, senha, data_criacao_conta, foto_perfil_url) VALUES
(1, '', 'Ativa', '41990000001', 'contato@patinhasfelizes.test', 'TESTE_NAO_USAR_LOGIN', '2026-10-01', NULL),
(2, '', 'Ativa', '41990000002', 'contato@amigobicho.test', 'TESTE_NAO_USAR_LOGIN', '2026-10-02', NULL),
(3, '', 'Ativa', '41990000003', 'contato@laranimal.test', 'TESTE_NAO_USAR_LOGIN', '2026-10-03', NULL),
(4, '', 'Ativa', '41990000004', 'mariana.adotante@test', 'TESTE_NAO_USAR_LOGIN', '2026-10-04', NULL),
(5, '', 'Ativa', '41990000005', 'pedro.adotante@test', 'TESTE_NAO_USAR_LOGIN', '2026-10-05', NULL),
(8, 'eduardo', 'Ativa', '41984036315', 'eduardocavalheirodias4@gmail.com', '$2y$10$XDZKJ2RX17xjjs2detJU1uituhKS6hehQLLXvw.1KknkpIRCr4Fvq', '2026-11-01', NULL),
(9,  'Adotante Demo',    'Ativa', '41990000009', 'adotante.demo@adotai.com',    '$2y$10$.xEZMRQ25EogBaNRstSzfe8cq9SN1o0CG.OPaC9tvPUBRsAVlJYkG', '2026-11-12', NULL),
(10, 'Protetor Demo',    'Ativa', '41990000010', 'protetor.demo@adotai.com',    '$2y$10$.xEZMRQ25EogBaNRstSzfe8cq9SN1o0CG.OPaC9tvPUBRsAVlJYkG', '2026-11-12', NULL),
(11, 'Veterinário Demo', 'Ativa', '41990000011', 'veterinario.demo@adotai.com', '$2y$10$.xEZMRQ25EogBaNRstSzfe8cq9SN1o0CG.OPaC9tvPUBRsAVlJYkG', '2026-11-12', NULL),
(12, 'Admin Demo',       'Ativa', '41990000012', 'admin.demo@adotai.com',       '$2y$10$.xEZMRQ25EogBaNRstSzfe8cq9SN1o0CG.OPaC9tvPUBRsAVlJYkG', '2026-11-12', NULL);


-- veterinario
INSERT INTO veterinario (id_veterinario, nome, cnpj, crmv, especialidade, email, telefone, cidade, uf, status, data_cadastro, id_usuario, descricao, endereco, servicos, planos, valor_consulta, foto_url) VALUES
(1, 'Ana Martins', NULL, 'CRMV-PR-1001', NULL, NULL, NULL, NULL, NULL, 'Ativo', '2026-11-01 22:13:16', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(2, 'Carlos Oliveira', NULL, 'CRMV-PR-1002', NULL, NULL, NULL, NULL, NULL, 'Ativo', '2026-11-01 22:13:16', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(3, 'Fernanda Souza', NULL, 'CRMV-PR-1003', NULL, NULL, NULL, NULL, NULL, 'Ativo', '2026-11-01 22:13:16', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(4, 'Roberto Lima', NULL, 'CRMV-PR-1004', NULL, NULL, NULL, NULL, NULL, 'Ativo', '2026-11-01 22:13:16', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(5, 'Veterinário Demo', NULL, 'CRMV-PR-1005', NULL, 'veterinario.demo@adotai.com', '41990000011', 'Curitiba', 'PR', 'Ativo', '2026-11-12 00:00:00', 11, NULL, NULL, NULL, NULL, NULL, NULL);


-- categoria
INSERT INTO categoria (id_categoria, nome_categoria) VALUES
(1, 'Cachorro'),
(2, 'Gato'),
(3, 'Ave'),
(4, 'Coelho'),
(5, 'Roedor'),
(6, 'Réptil'),
(7, 'Outro');


-- protetor
INSERT INTO protetor (id_protetor, id_usuario, status_aprovacao, nome_organizacao, descricao, email_contato, telefone_contato, endereco, cidade, uf, foto_url) VALUES
(1, 1, 'Aprovado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(2, 2, 'Aprovado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(3, 3, 'Aprovado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- protetor demo (pessoa física, já aprovada; CPF é de exemplo)
INSERT INTO protetor (id_protetor, id_usuario, status_aprovacao, nome_organizacao, descricao, email_contato, telefone_contato, endereco, cidade, uf, foto_url, cpf) VALUES
(4, 10, 'Aprovado', NULL, NULL, 'protetor.demo@adotai.com', '41990000010', NULL, 'Curitiba', 'PR', NULL, '52998224725');


-- ongs
INSERT INTO ongs (id_ong, id_protetor, cnpj) VALUES
(1, 1, '12345678000199'),
(2, 2, '23456789000188'),
(3, 3, '34567890000177');


-- adotante
INSERT INTO adotante (id_adotante, id_usuario) VALUES
(1, 4),
(2, 5),
(5, 8),
(6, 9);


-- admin demo (nivel_acesso '1')
INSERT INTO admin (id_admin, id_usuario, nivel_acesso, data_concessao) VALUES
(1, 12, '1', '2026-11-12');


-- animal
INSERT INTO animal (id_animal, id_categoria, id_protetor, nome_animal, descricao, especie, raca, idade_meses, sexo, porte, cor, castrado, vacinado, vermifugado, condicao_saude, compatibilidade_criancas, compatibilidade_animais, nivel_energia, cidade, uf, status_adocao, data_cadastro, foto_url, status_aprovacao, observacao_admin) VALUES
(1, 1, 1, 'Thor', 'Cachorro brincalhão, carinhoso e sociável, procurando uma família responsável.', 'Cachorro', 'Vira-lata', 18, 'Macho', 'Médio', 'Caramelo', 1, 1, 1, 'Saudável', 'Sim', 'Sim', 'Alto', 'Curitiba', 'PR', 'Disponível', '2026-10-01', 'fotos/cachorros/thor-vira-lata-caramelo.jpg', 'Aprovado', NULL),
(2, 2, 1, 'Luna', 'Gata tranquila que gosta de carinho e de ambientes aconchegantes.', 'Gato', 'SRD', 8, 'Fêmea', 'Pequeno', 'Cinza', 1, 1, 1, 'Saudável', 'Sim', 'Sim', 'Baixo', 'Curitiba', 'PR', 'Disponível', '2026-10-02', 'fotos/gatos/luna-gata-cinza.jpg', 'Aprovado', NULL),
(3, 1, 2, 'Max', 'Cão adulto, leal e atento, ideal para uma família disposta a oferecer passeios diários.', 'Cachorro', 'Labrador', 48, 'Macho', 'Grande', 'Preto', 1, 1, 1, 'Saudável', 'Sim', 'Sim', 'Alto', 'São José dos Pinhais', 'PR', 'Disponível', '2026-10-03', 'fotos/cachorros/max-labrador-preto.jpg', 'Aprovado', NULL),
(4, 2, 2, 'Mel', 'Gatinha curiosa e independente, que precisa de um lar seguro e acolhedor.', 'Gato', 'SRD', 5, 'Fêmea', 'Pequeno', 'Tricolor', 0, 1, 1, 'Em acompanhamento veterinário', 'Sim', 'Não informado', 'Moderado', 'Curitiba', 'PR', 'Disponível', '2026-10-04', 'fotos/gatos/mel-gata-tricolor.jpg', 'Aprovado', NULL),
(5, 1, 3, 'Bob', 'Cachorro adulto e tranquilo, resgatado e pronto para encontrar um novo lar.', 'Cachorro', 'Vira-lata', 72, 'Macho', 'Médio', 'Marrom', 1, 1, 1, 'Saudável', 'Não informado', 'Sim', 'Baixo', 'Colombo', 'PR', 'Em processo', '2026-10-05', 'fotos/cachorros/bob-vira-lata-marrom.jpg', 'Aprovado', NULL),
(16, 1, 1, 'Relâmpago', 'Cavalo de perfil tranquilo, destinado a um cenário demonstrativo de acolhimento e cuidados responsáveis.', 'Cavalo', 'Quarto de Milha', 72, 'Macho', 'Grande', 'Castanho', 0, 0, 0, 'Não informado', 'Não informado', 'Não informado', 'Alto', 'Curitiba', 'PR', 'Disponível', '2026-10-15', 'fotos/outros/relampago-cavalo-quarto-de-milha-castanho.jpg', 'Aprovado', NULL),
(17, 7, 2, 'Cacau', 'Capivara fictícia para demonstração do catálogo de espécies silvestres e educação sobre conservação.', 'Capivara', 'Capivara', 24, 'Fêmea', 'Grande', 'Marrom', 0, 0, 0, 'Não informado', 'Não informado', 'Não informado', 'Moderado', 'Curitiba', 'PR', 'Disponível', '2026-10-15', 'fotos/outros/cacau-capivara-marrom.jpg', 'Aprovado', NULL),
(18, 7, 3, 'Ziggy', 'Raposa fictícia para demonstração de cadastro de animais exóticos; não representa oferta real de adoção.', 'Raposa', 'Raposa-vermelha', 18, 'Macho', 'Médio', 'Avermelhado', 0, 0, 0, 'Não informado', 'Não informado', 'Não informado', 'Alto', 'Colombo', 'PR', 'Disponível', '2026-10-15', 'fotos/outros/ziggy-raposa-vermelha.jpg', 'Aprovado', NULL),
(19, 7, 1, 'Nina', 'Ouriço-africano fictício para testar o cadastro de espécies não convencionais.', 'Ouriço', 'Ouriço-pigmeu-africano', 12, 'Fêmea', 'Pequeno', 'Marrom e branco', 0, 0, 0, 'Não informado', 'Não informado', 'Não informado', 'Moderado', 'Curitiba', 'PR', 'Disponível', '2026-10-15', 'fotos/outros/nina-ourico-pigmeu-africano.jpg', 'Aprovado', NULL),
(20, 7, 2, 'Bento', 'Jabuti-piranga que precisa de um ambiente seguro, espaço adequado, temperatura controlada e alimentação específica para sua espécie.', 'Jabuti', 'Jabuti-piranga', 36, 'Macho', 'Pequeno', 'Marrom e amarelo', 0, 0, 0, 'Não informado', 'Não informado', 'Não informado', 'Baixo', 'São José dos Pinhais', 'PR', 'Disponível', '2026-10-15', 'fotos/repteis/bento-jabuti-piranga-marrom-amarelo.jpg', 'Aprovado', NULL),
(21, 3, 3, 'Lilo', 'Arara-canindé de plumagem azul e amarela, ativa e curiosa. Precisa de espaço adequado, enriquecimento ambiental e cuidados especializados.', 'Ave', 'Arara-canindé', 48, 'Fêmea', 'Grande', 'Azul e amarelo', 0, 0, 0, 'Não informado', 'Não informado', 'Não informado', 'Alto', 'Colombo', 'PR', 'Disponível', '2026-10-15', 'fotos/aves/lilo-arara-caninde-azul-amarela.jpg', 'Aprovado', NULL),
(22, 7, 1, 'Tico', 'Sagui de pequeno porte, curioso e cheio de energia. Gosta de explorar o ambiente e precisa de espaço amplo, enriquecimento ambiental, alimentação específica e cuidados especializados.', 'Sagui', 'Sagui', 18, 'Macho', 'Pequeno', 'Cinza e branco', 0, 0, 0, 'Não informado', 'Não informado', 'Não informado', 'Alto', 'Curitiba', 'PR', 'Disponível', '2026-10-15', 'fotos/outros/tico-sagui-cinza-branco.jpg', 'Aprovado', NULL),
(23, 3, 2, 'Pérola', 'Pavão de plumagem azul e verde, imponente e ativo. Precisa de uma área externa ampla e segura para caminhar, abrir a cauda e explorar o ambiente. Sua criação exige planejamento, abrigo adequado e cuidados especializados.', 'Ave', 'Pavão-azul', 30, 'Fêmea', 'Grande', 'Azul e verde', 0, 0, 0, 'Não informado', 'Não informado', 'Não informado', 'Alto', 'São José dos Pinhais', 'PR', 'Disponível', '2026-10-15', 'fotos/aves/perola-pavao-azul-verde.jpg', 'Aprovado', NULL),
(24, 3, 1, 'Billy', 'Calopsita dócil e curiosa, ideal para quem deseja conhecer os cuidados com aves.', 'Ave', 'Calopsita', 12, 'Macho', 'Pequeno', 'Cinza e amarelo', 0, 0, 0, 'Não informado', 'Não informado', 'Não informado', 'Moderado', 'Curitiba', 'PR', 'Disponível', '2026-10-15', 'fotos/aves/billy-calopsita-cinza-amarela.jpg', 'Aprovado', NULL),
(25, 1, 2, 'Jhully', 'Cadela carinhosa, com pelagem longa e personalidade companheira.', 'Cachorro', 'Lhasa Apso', 24, 'Fêmea', 'Pequeno', 'Branco e caramelo', 1, 1, 1, 'Saudável', 'Sim', 'Sim', 'Moderado', 'Curitiba', 'PR', 'Disponível', '2026-10-15', 'fotos/cachorros/jhully-lhasa-apso-branco-caramelo.jpg', 'Aprovado', NULL),
(26, 1, 3, 'Melzinha', 'Cadela brincalhona que gosta de companhia e passeios.', 'Cachorro', 'Shih-tzu', 18, 'Fêmea', 'Pequeno', 'Branco e marrom', 1, 1, 1, 'Saudável', 'Sim', 'Sim', 'Moderado', 'Curitiba', 'PR', 'Disponível', '2026-10-15', 'fotos/cachorros/melzinha-shih-tzu-branco-marrom.jpg', 'Aprovado', NULL),
(27, 2, 1, 'Apollo', 'Gato tranquilo, curioso e acostumado a ambientes internos.', 'Gato', 'Siamês', 14, 'Macho', 'Pequeno', 'Creme e marrom', 1, 1, 1, 'Saudável', 'Sim', 'Sim', 'Moderado', 'Curitiba', 'PR', 'Disponível', '2026-10-15', 'fotos/gatos/apollo-siames-creme-marrom.jpg', 'Aprovado', NULL),
(28, 1, 2, 'Nina', 'Cadela pequena, atenta e cheia de energia.', 'Cachorro', 'Pinscher', 10, 'Fêmea', 'Pequeno', 'Preto e caramelo', 0, 1, 1, 'Saudável', 'Não informado', 'Não informado', 'Alto', 'Curitiba', 'PR', 'Disponível', '2026-10-15', 'fotos/cachorros/nina-pinscher-preto-caramelo.jpg', 'Aprovado', NULL),
(29, 2, 3, 'Simba', 'Gato sociável que adora brincar e descansar em lugares aconchegantes.', 'Gato', 'SRD', 8, 'Macho', 'Pequeno', 'Laranja e branco', 0, 1, 1, 'Saudável', 'Sim', 'Sim', 'Alto', 'Curitiba', 'PR', 'Disponível', '2026-10-15', 'fotos/gatos/simba-srd-laranja-branco.jpg', 'Aprovado', NULL),
(30, 1, 1, 'Floquinho', 'Cão de pequeno porte, companheiro e com muita disposição para passear.', 'Cachorro', 'Poodle', 36, 'Macho', 'Pequeno', 'Branco', 1, 1, 1, 'Saudável', 'Sim', 'Sim', 'Moderado', 'São José dos Pinhais', 'PR', 'Disponível', '2026-10-15', 'fotos/cachorros/floquinho-poodle-branco.jpg', 'Aprovado', NULL),
(31, 2, 2, 'Cookie', 'Gatinha independente, curiosa e carinhosa quando ganha confiança.', 'Gato', 'SRD', 6, 'Fêmea', 'Pequeno', 'Tricolor', 0, 1, 0, 'Não informado', 'Não informado', 'Sim', 'Moderado', 'Curitiba', 'PR', 'Disponível', '2026-10-15', 'fotos/gatos/cookie-srd-tricolor.jpg', 'Aprovado', NULL),
(32, 4, 3, 'Paçoca', 'Coelho curioso que precisa de espaço seguro e alimentação adequada.', 'Coelho', 'Mini Lop', 9, 'Macho', 'Pequeno', 'Caramelo', 0, 0, 0, 'Não informado', 'Não informado', 'Não informado', 'Moderado', 'Curitiba', 'PR', 'Disponível', '2026-10-15', 'fotos/coelhos/pacoca-mini-lop-caramelo.jpg', 'Aprovado', NULL),
(33, 4, 1, 'Lola', 'Coelha tranquila, com pelagem macia e comportamento curioso.', 'Coelho', 'Mini Rex', 11, 'Fêmea', 'Pequeno', 'Cinza', 0, 0, 0, 'Não informado', 'Não informado', 'Não informado', 'Baixo', 'Pinhais', 'PR', 'Disponível', '2026-10-15', 'fotos/coelhos/lola-mini-rex-cinza.jpg', 'Aprovado', NULL),
(34, 1, 2, 'Apollo Jr.', 'Cão de porte grande, brincalhão e que precisa de passeios regulares.', 'Cachorro', 'Golden Retriever', 30, 'Macho', 'Grande', 'Dourado', 1, 1, 1, 'Saudável', 'Sim', 'Sim', 'Alto', 'Curitiba', 'PR', 'Disponível', '2026-10-15', 'fotos/cachorros/apollo-jr-golden-retriever-dourado.jpg', 'Aprovado', NULL),
(35, 2, 3, 'Jade', 'Gata de pelagem escura, observadora e tranquila.', 'Gato', 'SRD', 20, 'Fêmea', 'Pequeno', 'Preto', 1, 1, 1, 'Saudável', 'Sim', 'Sim', 'Baixo', 'Curitiba', 'PR', 'Disponível', '2026-10-15', 'fotos/gatos/jade-srd-preto.jpg', 'Aprovado', NULL),
(36, 3, 1, 'Lelé', 'Periquito-australiano ativo, que gosta de interagir com o ambiente.', 'Ave', 'Periquito-australiano', 8, 'Fêmea', 'Pequeno', 'Verde e amarelo', 0, 0, 0, 'Não informado', 'Não informado', 'Não informado', 'Alto', 'Curitiba', 'PR', 'Disponível', '2026-10-15', 'fotos/aves/lele-periquito-australiano-verde-amarelo.jpg', 'Aprovado', NULL),
(37, 5, 2, 'Amendoim', 'Hamster pequeno e curioso, com hábitos principalmente noturnos.', 'Roedor', 'Sírio', 5, 'Macho', 'Pequeno', 'Dourado e branco', 0, 0, 0, 'Não informado', 'Não informado', 'Não informado', 'Moderado', 'Curitiba', 'PR', 'Disponível', '2026-10-15', 'fotos/roedores/amendoim-hamster-sirio-dourado.jpg', 'Aprovado', NULL),
(38, 5, 3, 'Pipoca', 'Porquinho-da-índia sociável que precisa de alimentação rica em fibras.', 'Roedor', 'Abissínio', 7, 'Fêmea', 'Pequeno', 'Marrom e branco', 0, 0, 0, 'Não informado', 'Não informado', 'Sim', 'Moderado', 'Colombo', 'PR', 'Disponível', '2026-10-15', 'fotos/roedores/pipoca-porquinho-da-india-marrom-branco.jpg', 'Aprovado', NULL),
(40, 6, 2, 'Tuga', 'Jabuti de demonstração cadastrado para testar a categoria de répteis.', 'Jabuti', 'Jabuti-piranga', 36, 'Macho', 'Pequeno', 'Marrom e amarelo', 0, 0, 0, 'Não informado', 'Não informado', 'Não informado', 'Baixo', 'Curitiba', 'PR', 'Disponível', '2026-10-15', 'fotos/repteis/tuga-jabuti-piranga-marrom-amarelo.jpg', 'Aprovado', NULL),
(41, 1, 3, 'Belinha', 'Cadela de pernas curtas, curiosa e companheira.', 'Cachorro', 'Dachshund', 22, 'Fêmea', 'Pequeno', 'Marrom', 1, 1, 1, 'Saudável', 'Sim', 'Sim', 'Moderado', 'Curitiba', 'PR', 'Disponível', '2026-10-15', 'fotos/cachorros/belinha-dachshund-marrom.jpg', 'Aprovado', NULL),
(42, 2, 1, 'Ozzy', 'Gato de pelagem longa que precisa de escovação frequente.', 'Gato', 'Persa', 16, 'Macho', 'Pequeno', 'Branco', 1, 1, 1, 'Saudável', 'Sim', 'Não informado', 'Baixo', 'Curitiba', 'PR', 'Disponível', '2026-10-15', 'fotos/gatos/ozzy-persa-branco.jpg', 'Aprovado', NULL),
(43, 1, 2, 'Kiko', 'Cão curioso e ativo, ideal para uma rotina com passeios e brincadeiras.', 'Cachorro', 'Beagle', 20, 'Macho', 'Médio', 'Tricolor', 1, 1, 1, 'Saudável', 'Sim', 'Sim', 'Alto', 'Araucária', 'PR', 'Disponível', '2026-10-15', 'fotos/cachorros/kiko-beagle-tricolor.jpg', 'Aprovado', NULL),
(44, 2, 3, 'Cacau', 'Gata afetuosa, de olhos claros e pelagem em tons contrastantes.', 'Gato', 'Siamês', 10, 'Fêmea', 'Pequeno', 'Creme e marrom', 0, 1, 1, 'Saudável', 'Sim', 'Sim', 'Moderado', 'Curitiba', 'PR', 'Disponível', '2026-10-15', 'fotos/gatos/cacau-siames-creme-marrom.jpg', 'Aprovado', NULL),
(45, 3, 1, 'Melodia', 'Canário de canto característico, cadastrado para demonstração.', 'Ave', 'Canário-belga', 9, 'Macho', 'Pequeno', 'Amarelo', 0, 0, 0, 'Não informado', 'Não informado', 'Não informado', 'Moderado', 'Curitiba', 'PR', 'Disponível', '2026-10-15', 'fotos/aves/melodia-canario-belga-amarelo.jpg', 'Aprovado', NULL),
(46, 1, 2, 'Fifi', 'Cadela de porte pequeno, ativa e companheira.', 'Cachorro', 'Yorkshire Terrier', 15, 'Fêmea', 'Pequeno', 'Cinza e caramelo', 1, 1, 1, 'Saudável', 'Sim', 'Sim', 'Alto', 'Curitiba', 'PR', 'Disponível', '2026-10-15', 'fotos/cachorros/fifi-yorkshire-terrier-cinza-caramelo.jpg', 'Aprovado', NULL),
(47, 2, 3, 'Mingau', 'Gato tranquilo, de pelagem clara, que gosta de descansar perto das pessoas.', 'Gato', 'SRD', 28, 'Macho', 'Pequeno', 'Branco e cinza', 1, 1, 1, 'Saudável', 'Sim', 'Sim', 'Baixo', 'Curitiba', 'PR', 'Disponível', '2026-10-15', 'fotos/gatos/mingau-srd-branco-cinza.jpg', 'Aprovado', NULL),
(49, 4, 2, 'Pompom', 'Coelho de pelagem fofa que precisa de ambiente seguro e enriquecimento.', 'Coelho', 'Lionhead', 6, 'Fêmea', 'Pequeno', 'Branco e cinza', 0, 0, 0, 'Não informado', 'Não informado', 'Não informado', 'Moderado', 'Pinhais', 'PR', 'Disponível', '2026-10-15', 'fotos/coelhos/pompom-lionhead-branco-cinza.jpg', 'Aprovado', NULL),
(50, 1, 3, 'Toddy', 'Cão sem raça definida, brincalhão e à procura de uma família.', 'Cachorro', 'Vira-lata', 12, 'Macho', 'Médio', 'Caramelo', 0, 1, 1, 'Saudável', 'Sim', 'Sim', 'Alto', 'Curitiba', 'PR', 'Disponível', '2026-10-15', 'fotos/cachorros/toddy-vira-lata-caramelo.jpg', 'Aprovado', NULL),
(69, 4, 1, 'Lucas Teixeira', 'Coelho alto, nerd e safado. Especialista em Como Treinar o Seu Dragão, usa óculos e assiste a animes de romance. Procura uma dama para adotá-lo e viver seu próprio romance de anime.', 'Coelho', 'Coelho-comum', 24, 'Macho', 'Médio', 'Branco e marrom', 0, 1, 1, 'Saudável', 'Sim', 'Sim', 'Alto', 'Curitiba', 'PR', 'Disponível', '2026-10-19', 'fotos/coelhos/lucas-teixeira-coelho.jpg', 'Aprovado', NULL),
(70, 1, 1, 'Frank', 'Pug de 2 anos, cheio de personalidade e saliência. Se identifica como pastor alemão, tem gênero fluido e adora uma balada. Bolsonarista e com fama de misógino, Frank procura uma família que aguente suas extravagâncias.', 'Cachorro', 'Pug', 24, 'Macho', 'Pequeno', 'Bege e preto', 0, 0, 0, 'Não informado', 'Não informado', 'Não informado', 'Alto', 'Curitiba', 'PR', 'Disponível', '2026-10-19', 'fotos/cachorros/frank-pug-bege-preto.jpg', 'Aprovado', NULL);


-- endereco
INSERT INTO endereco (id_usuario, cep, uf, cidade, bairro, rua, numero, complemento) VALUES
(1, '80010000', 'PR', 'Curitiba', 'Centro', 'Rua das Flores', '100', NULL),
(2, '80020000', 'PR', 'Curitiba', 'São Francisco', 'Rua dos Animais', '200', NULL),
(3, '83405000', 'PR', 'Colombo', 'Centro', 'Rua da Esperança', '300', NULL),
(4, '80030000', 'PR', 'Curitiba', 'Rebouças', 'Rua das Palmeiras', '400', NULL),
(5, '83005000', 'PR', 'São José dos Pinhais', 'Centro', 'Rua do Sol', '500', NULL),
(9,  '80030000', 'PR', 'Curitiba', 'Rebouças', 'Rua das Palmeiras', '400', NULL),
(10, '80010000', 'PR', 'Curitiba', 'Centro', 'Rua das Flores', '100', NULL);


-- lista_interesse
INSERT INTO lista_interesse (id_lista, id_usuario) VALUES
(2, 8);


-- lista_animal
INSERT INTO lista_animal (id_lista, id_animal) VALUES
(2, 70);


-- plano
INSERT INTO plano (id_plano, nome, descricao, publico_alvo, beneficios, valor, periodicidade, ativo, destaque, ordem_exibicao, data_cadastro) VALUES
(1, 'Adotante', 'Encontre seu novo melhor amigo e acompanhe suas oportunidades de adoção.', 'Pessoas interessadas em adotar', 'Visualizar animais disponíveis;Salvar animais favoritos;Demonstrar interesse em adoções;Acompanhar solicitações', 0.00, 'Gratuito', 1, 0, 1, '2026-11-01 00:54:29'),
(2, 'Protetor Independente', 'Divulgue animais resgatados e organize seus processos de adoção.', 'Protetores independentes', 'Cadastrar animais;Gerenciar interessados;Divulgar animais;Acompanhar processos de adoção', 0.00, 'Gratuito', 1, 1, 2, '2026-11-01 00:54:29'),
(3, 'ONG / Instituição', 'Tenha mais organização para divulgar animais e conectar instituições a novos adotantes.', 'ONGs e instituições de proteção animal', 'Divulgar animais;Gerenciar interessados;Organizar processos de adoção;Centralizar informações', 0.00, 'Gratuito', 1, 0, 3, '2026-11-01 00:54:29');


-- plano_de_assinatura
INSERT INTO plano_de_assinatura (id_plano, usuario_id, vencimento, nome, valor, id_veterinario, id_usuario, tipo_registro, descricao, periodicidade, beneficios, destaque, status, ordem) VALUES
(1, NULL, NULL, 'Adotante', 0.00, NULL, NULL, 'plano', 'Plano gratuito para pessoas que desejam encontrar um animal para adoção.', 'Gratuito', 'Visualizar animais disponíveis\nAdicionar animais aos favoritos\nDemonstrar interesse em adoções', 0, 'Ativo', 1),
(2, NULL, NULL, 'Protetor Independente', 0.00, NULL, NULL, 'plano', 'Plano gratuito para protetores que desejam divulgar animais para adoção.', 'Gratuito', 'Cadastrar animais para adoção\nGerenciar anúncios\nReceber interessados na adoção', 0, 'Ativo', 2),
(3, NULL, NULL, 'ONG / Instituição', 0.00, NULL, NULL, 'plano', 'Plano gratuito para instituições de proteção animal.', 'Gratuito', 'Divulgar animais disponíveis\nGerenciar animais cadastrados\nAmpliar o alcance das adoções', 1, 'Ativo', 3);
