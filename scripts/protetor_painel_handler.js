// =====================================================
// Painel do protetor:
//  - confere se quem está logado é protetor;
//  - troca entre as abas (Cadastrar animal / Meus animais) e abre a aba
//    indicada no endereço (ex.: protetor_painel.html#animais);
//  - aba "Meus animais": lista os animais do protetor logado (api/meus_animais.php);
//  - formulário de cadastro de animal: contadores de caracteres,
//    idade em anos + meses -> idade_meses, validação e envio.
// (As validações aqui são só conveniência: o PHP precisa validar de novo.)
// =====================================================
(function () {
    const base = new URL('../', document.currentScript.src);   // raiz do site (pasta adotai/)

    const URL_SESSAO   = new URL('api/sessao.php', base).href;
    const URL_CADASTRO = new URL('api/animal_cadastrar.php', base).href;
    const URL_LISTA    = new URL('api/meus_animais.php', base).href;
    const URL_GERENCIAR = new URL('api/animal_gerenciar.php', base).href;
    const FOTO_PADRAO  = new URL('images/franky.jpeg', base).href;   // usada se o animal não tiver foto
    const URL_LOGIN    = new URL('pages/login.html', base).href;
    const URL_INICIO   = new URL('index.html', base).href;

    // true  = quem não for protetor logado é redirecionado.
    // Ponha false se quiser abrir a página para testar o visual sem login.
    const EXIGIR_PROTETOR = true;

    // ---------- Utilitários ----------
    function criar(tag, classe, texto) {
        const el = document.createElement(tag);
        if (classe) el.className = classe;
        if (texto !== undefined) el.textContent = texto;   // textContent: nunca interpreta HTML
        return el;
    }

    const $ = (id) => document.getElementById(id);

    // ==================================================
    // 1. SESSÃO: só protetor entra aqui
    // ==================================================
    fetch(URL_SESSAO, { credentials: 'same-origin', cache: 'no-store' })
        .then(r => (r.ok ? r.json() : null))
        .then(dados => {
            if (!EXIGIR_PROTETOR || !dados) return;   // sem resposta: não bloqueia a tela

            if (!dados.logado)              { location.replace(URL_LOGIN);  return; }
            if (dados.tipo !== 'protetor')  { location.replace(URL_INICIO); return; }

            if (dados.nome) $('painel-nome').textContent = dados.nome;
        })
        .catch(() => { /* sem resposta: mantém a página como está */ });

    // ==================================================
    // 2. ABAS
    // Para criar uma aba nova: botão .painel-aba (id "aba-xxx") + section
    // .painel-card (id "painel-xxx") no HTML e uma linha em ABAS aqui.
    // ==================================================
    const ABAS = {
        cadastro: { aba: $('aba-cadastro'), painel: $('painel-cadastro') },
        animais:  { aba: $('aba-animais'),  painel: $('painel-animais')  },
    };
    const ORDEM_ABAS = Object.keys(ABAS);

    function selecionarAba(nome, focar) {
        if (!ABAS[nome]) nome = 'cadastro';

        ORDEM_ABAS.forEach(chave => {
            const ativa = chave === nome;
            ABAS[chave].aba.setAttribute('aria-selected', String(ativa));
            ABAS[chave].aba.tabIndex = ativa ? 0 : -1;
            ABAS[chave].painel.hidden = !ativa;
        });

        if (focar) ABAS[nome].aba.focus();
        history.replaceState(null, '', '#' + nome);

        if (nome === 'animais') carregarMeusAnimais();   // sempre mostra a lista atualizada
    }

    ORDEM_ABAS.forEach((chave, i) => {
        const { aba } = ABAS[chave];

        aba.addEventListener('click', () => selecionarAba(chave));

        // setas do teclado navegam entre as abas
        aba.addEventListener('keydown', (e) => {
            const teclas = { ArrowDown: 1, ArrowRight: 1, ArrowUp: -1, ArrowLeft: -1 };
            if (e.key in teclas) {
                e.preventDefault();
                const prox = (i + teclas[e.key] + ORDEM_ABAS.length) % ORDEM_ABAS.length;
                selecionarAba(ORDEM_ABAS[prox], true);
            }
        });
    });

    // botões com data-ir-aba="cadastro" levam para a aba indicada
    document.querySelectorAll('[data-ir-aba]').forEach(btn => {
        btn.addEventListener('click', () => selecionarAba(btn.dataset.irAba, true));
    });

    // ==================================================
    // 3. ABA "MEUS ANIMAIS"
    // Busca em api/meus_animais.php os animais ligados ao protetor logado
    // (o servidor descobre o protetor pela sessão: nenhum id é enviado daqui)
    // e desenha um card para cada um.
    // ==================================================
    const listaAnimais   = $('meus-animais-lista');
    const vazioAnimais   = $('meus-animais-vazio');
    const resumoAnimais  = $('meus-animais-resumo');
    const carregandoAnim = $('meus-animais-carregando');
    const erroAnimais    = $('meus-animais-erro');
    const erroAnimaisTxt = $('meus-animais-erro-texto');

    let pedidoAtual = 0;   // só a resposta do pedido mais recente é desenhada

    // Texto sem acento, minúsculo e com hífens: "Em processo" -> "em-processo" (sufixo das classes .selo-*)
    function paraClasse(texto) {
        return String(texto).normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .toLowerCase().replace(/[^a-z0-9]+/g, '-');
    }

    // 18 -> "1 ano e 6 meses" | 8 -> "8 meses" | 24 -> "2 anos"
    function formatarIdade(meses) {
        if (meses === null || meses === undefined) return 'Idade não informada';
        const anos = Math.floor(meses / 12);
        const resto = meses % 12;
        const partes = [];
        if (anos > 0)  partes.push(anos === 1 ? '1 ano' : `${anos} anos`);
        if (resto > 0 || anos === 0) partes.push(resto === 1 ? '1 mês' : `${resto} meses`);
        return partes.join(' e ');
    }

    // "2026-11-10" -> "10/11/2026" (sem passar por Date: evita erro de fuso horário)
    function formatarData(iso) {
        const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso || '');
        return m ? `${m[3]}/${m[2]}/${m[1]}` : null;
    }

    function selo(texto, status) {
        return criar('span', `selo selo-${paraClasse(status)}`, texto);
    }

    function criarFotoAnimal(a) {
        const img = criar('img', 'animal-foto');
        img.loading = 'lazy';
        img.alt = `Foto de ${a.nome_animal}`;
        img.src = a.foto_url ? new URL(a.foto_url, base).href : FOTO_PADRAO;
        img.addEventListener('error', () => {
            if (img.src !== FOTO_PADRAO) img.src = FOTO_PADRAO;   // foto não encontrada
        }, { once: true });
        return img;
    }

    function criarCardAnimal(a) {
        const info = criar('div', 'animal-info');

        // Nome + selos: aprovação do admin e, se já está no catálogo, o andamento da adoção
        const topo = criar('div', 'animal-topo');
        topo.appendChild(criar('h3', 'animal-nome', a.nome_animal));

        if (a.status_aprovacao === 'Aprovado') {
            topo.appendChild(selo('Publicado no catálogo', 'aprovado'));
            if (a.status_adocao) topo.appendChild(selo(a.status_adocao, a.status_adocao));
        } else if (a.status_aprovacao === 'Pendente') {
            topo.appendChild(selo('Aguardando aprovação', 'pendente'));
        } else if (a.status_aprovacao === 'Recusado') {
            topo.appendChild(selo('Recusado', 'recusado'));
        } else {
            topo.appendChild(selo(a.status_aprovacao || 'Sem status', 'pendente'));
        }
        info.appendChild(topo);

        // Espécie · raça · sexo · porte · idade
        const detalhes = [a.especie, a.raca, a.sexo !== 'Não informado' ? a.sexo : null, a.porte, formatarIdade(a.idade_meses)]
            .filter(Boolean).join(' · ');
        info.appendChild(criar('p', 'animal-detalhes', detalhes));

        // Local · data do cadastro
        const local = [a.cidade ? (a.uf ? `${a.cidade} - ${a.uf}` : a.cidade) : null,
                       formatarData(a.data_cadastro) ? `Cadastrado em ${formatarData(a.data_cadastro)}` : null]
            .filter(Boolean).join(' · ');
        if (local) info.appendChild(criar('p', 'animal-detalhes', local));

        // Cor e convivência (só aparecem se informados)
        const extras = [
            a.cor ? `Cor: ${a.cor}` : null,
            ['Sim', 'Não'].includes(a.compatibilidade_criancas) ? `Crianças: ${a.compatibilidade_criancas}` : null,
            ['Sim', 'Não'].includes(a.compatibilidade_animais) ? `Outros animais: ${a.compatibilidade_animais}` : null,
        ].filter(Boolean).join(' · ');
        if (extras) info.appendChild(criar('p', 'animal-detalhes', extras));

        // Cuidados de saúde já feitos
        const cuidados = [['castrado', 'Castrado'], ['vacinado', 'Vacinado'], ['vermifugado', 'Vermifugado']]
            .filter(([campo]) => Number(a[campo]) === 1);
        if (cuidados.length) {
            const chips = criar('ul', 'animal-chips');
            cuidados.forEach(([, rotulo]) => chips.appendChild(criar('li', 'animal-chip', rotulo)));
            info.appendChild(chips);
        }

        // Textos longos do formulário (descrição até 1000 e condição de saúde até 500 caracteres):
        // aparecem completos, com as quebras de linha digitadas e sem estourar o card
        if (a.descricao) {
            const bloco = criar('div', 'animal-bloco');
            bloco.append(criar('strong', 'animal-bloco-rotulo', 'Descrição'), criar('p', 'animal-texto', a.descricao));
            info.appendChild(bloco);
        }
        if (a.condicao_saude) {
            const bloco = criar('div', 'animal-bloco');
            bloco.append(criar('strong', 'animal-bloco-rotulo', 'Condição de saúde'), criar('p', 'animal-texto', a.condicao_saude));
            info.appendChild(bloco);
        }

        // Recados do administrador
        if (a.status_aprovacao === 'Pendente') {
            info.appendChild(criar('p', 'animal-nota animal-nota-pendente',
                'Cadastro enviado para analise.'));
        } else if (a.status_aprovacao === 'Recusado') {
            const nota = criar('p', 'animal-nota animal-nota-recusado');
            nota.appendChild(criar('strong', null, 'Motivo da recusa: '));
            nota.appendChild(document.createTextNode(a.observacao_admin || 'não informado pelo administrador.'));
            info.appendChild(nota);
        } else if (a.observacao_admin) {
            const nota = criar('p', 'animal-nota animal-nota-pendente');
            nota.appendChild(criar('strong', null, 'Observação do administrador: '));
            nota.appendChild(document.createTextNode(a.observacao_admin));
            info.appendChild(nota);
        }

        info.appendChild(criarGerenciar(a));

        const card = criar('article', 'animal-item');
        card.append(criarFotoAnimal(a), info);
        return card;
    }

    // ---------- Gerenciar o animal: status da adoção e remoção ----------
    const STATUS_ADOCAO = ['Disponível', 'Em processo', 'Adotado'];

    // Envia uma ação para api/animal_gerenciar.php. Devolve o JSON ou lança Error com a mensagem para a tela.
    async function enviarAcao(acao, dados) {
        let resposta, corpo;
        try {
            resposta = await fetch(`${URL_GERENCIAR}?acao=${acao}`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'fetch' },   // o PHP exige este cabeçalho
                body: new URLSearchParams(dados),
            });
            corpo = await resposta.json();
        } catch (e) {
            throw new Error('Não foi possível se conectar ao servidor. Tente novamente.');
        }

        if (resposta.status === 401) { location.replace(URL_LOGIN); throw new Error('Sessão expirada.'); }
        if (!resposta.ok || !corpo.ok) throw new Error(corpo.mensagem || 'Não foi possível concluir a ação.');
        return corpo;
    }

    // Formulário de edição dentro do card: nome, descrição, idade e condição de saúde
    // (mesmos limites do cadastro; o PHP confere tudo de novo).
    function criarFormEdicao(a, aoCancelar) {
        const form = criar('form', 'animal-editar');
        form.noValidate = true;
        const idDe = (n) => `editar-${n}-${a.id_animal}`;

        // Monta "rótulo + controle + erro (+ contador)"
        function campo(rotulo, controle, max) {
            const bloco = criar('div', 'campo');
            const label = criar('label', '', rotulo);
            label.htmlFor = controle.id;
            const erro = criar('p', 'campo-erro');
            erro.hidden = true;
            const rodape = criar('div', 'campo-rodape');
            rodape.appendChild(erro);

            if (max) {
                const contador = criar('span', 'contador');
                const atualizar = () => {
                    contador.textContent = `${controle.value.length} / ${max}`;
                    contador.classList.toggle('no-limite', controle.value.length >= max);
                };
                controle.addEventListener('input', atualizar);
                atualizar();
                rodape.appendChild(contador);
            }

            bloco.append(label, controle, rodape);
            bloco.addEventListener('input', () => { erro.hidden = true; bloco.classList.remove('tem-erro'); });
            return { bloco, erro };
        }

        const inpNome = criar('input');
        inpNome.type = 'text';
        inpNome.id = idDe('nome');
        inpNome.maxLength = 50;
        inpNome.autocomplete = 'off';
        inpNome.value = a.nome_animal || '';

        const inpDesc = criar('textarea');
        inpDesc.id = idDe('descricao');
        inpDesc.rows = 5;
        inpDesc.maxLength = 1000;
        inpDesc.value = a.descricao || '';

        // Idade: anos + meses -> idade_meses
        const temIdade = a.idade_meses !== null && a.idade_meses !== undefined;
        const inpAnos = criar('input');
        inpAnos.type = 'number';
        inpAnos.id = idDe('anos');
        inpAnos.min = '0'; inpAnos.max = '50'; inpAnos.step = '1';
        inpAnos.placeholder = '0';
        inpAnos.value = temIdade ? String(Math.floor(a.idade_meses / 12)) : '';
        const inpMeses = criar('input');
        inpMeses.type = 'number';
        inpMeses.id = idDe('meses');
        inpMeses.min = '0'; inpMeses.max = '11'; inpMeses.step = '1';
        inpMeses.placeholder = '0';
        inpMeses.setAttribute('aria-label', 'Meses');
        inpMeses.value = temIdade ? String(a.idade_meses % 12) : '';

        const idade = criar('div', 'campo');
        const idadeLabel = criar('label', '', 'Idade aproximada');
        idadeLabel.htmlFor = inpAnos.id;
        const grupo = criar('div', 'idade-grupo');
        [[inpAnos, 'anos'], [inpMeses, 'meses']].forEach(([inp, sufixo]) => {
            const caixa = criar('div', 'input-sufixo');
            caixa.append(inp, criar('span', '', sufixo));
            grupo.appendChild(caixa);
        });
        const erroIdade = criar('p', 'campo-erro');
        erroIdade.hidden = true;
        idade.append(idadeLabel, grupo, erroIdade);
        idade.addEventListener('input', () => { erroIdade.hidden = true; idade.classList.remove('tem-erro'); });

        const inpSaude = criar('textarea');
        inpSaude.id = idDe('saude');
        inpSaude.rows = 3;
        inpSaude.maxLength = 500;
        inpSaude.placeholder = 'Ex.: Saudável, sem restrições. Ou informe tratamentos, alergias, deficiências...';
        inpSaude.value = a.condicao_saude || '';

        const cNome  = campo('Nome do animal', inpNome);
        const cDesc  = campo('Descrição', inpDesc, 1000);
        const cSaude = campo('Condição de saúde', inpSaude, 500);

        const erroGeral = criar('p', 'campo-erro animal-editar-erro');
        erroGeral.setAttribute('role', 'alert');
        erroGeral.hidden = true;

        const btnSalvar = criar('button', 'btn-mini btn-mini-salvar', 'Salvar alterações');
        btnSalvar.type = 'submit';
        const btnCancelar = criar('button', 'btn-mini', 'Cancelar');
        btnCancelar.type = 'button';
        btnCancelar.addEventListener('click', aoCancelar);
        const acoes = criar('div', 'animal-editar-acoes');
        acoes.append(btnCancelar, btnSalvar);

        form.append(cNome.bloco, cDesc.bloco, idade, cSaude.bloco, erroGeral, acoes);

        function mostrar(bloco, erro, texto) {
            erro.textContent = texto;
            erro.hidden = false;
            bloco.classList.add('tem-erro');
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            erroGeral.hidden = true;

            const nome  = inpNome.value.trim();
            const desc  = inpDesc.value.trim();
            const saude = inpSaude.value.trim();
            const anosTxt = inpAnos.value.trim();
            const mesesTxt = inpMeses.value.trim();

            let primeiro = null;
            const falhou = (bloco, erro, texto, foco) => {
                mostrar(bloco, erro, texto);
                if (!primeiro) primeiro = foco;
            };

            if (!nome) falhou(cNome.bloco, cNome.erro, 'Informe o nome do animal.', inpNome);

            if (!desc) falhou(cDesc.bloco, cDesc.erro, 'Escreva uma descrição para o animal.', inpDesc);
            else if (desc.length < 20) falhou(cDesc.bloco, cDesc.erro, 'A descrição está muito curta (mínimo de 20 caracteres).', inpDesc);

            let idadeMeses = '';
            if (anosTxt !== '' || mesesTxt !== '') {
                const anos = anosTxt === '' ? 0 : Number(anosTxt);
                const meses = mesesTxt === '' ? 0 : Number(mesesTxt);
                const valida = Number.isInteger(anos) && anos >= 0 && anos <= 50
                            && Number.isInteger(meses) && meses >= 0 && meses <= 11;
                if (valida) idadeMeses = String(anos * 12 + meses);
                else falhou(idade, erroIdade, 'Use de 0 a 50 anos e de 0 a 11 meses.', inpAnos);
            }

            if (primeiro) { primeiro.focus(); return; }

            btnSalvar.disabled = btnCancelar.disabled = true;
            btnSalvar.textContent = 'Salvando...';
            try {
                await enviarAcao('editar', {
                    id_animal: a.id_animal,
                    nome_animal: nome,
                    descricao: desc,
                    idade_meses: idadeMeses,      // '' = não informada (NULL no banco)
                    condicao_saude: saude,        // '' = sem condição informada (NULL)
                });
                carregarMeusAnimais();            // redesenha o card com os dados novos
            } catch (erro) {
                erroGeral.textContent = erro.message;
                erroGeral.hidden = false;
                btnSalvar.disabled = btnCancelar.disabled = false;
                btnSalvar.textContent = 'Salvar alterações';
            }
        });

        return { form, focar: () => inpNome.focus() };
    }

    function criarGerenciar(a) {
        const area = criar('div', 'animal-gerenciar');
        const msg = criar('p', 'animal-gerenciar-erro');
        msg.setAttribute('role', 'alert');
        msg.hidden = true;

        const erro = (texto) => { msg.textContent = texto; msg.hidden = !texto; };

        // ----- Status da adoção (só para animais já aprovados) -----
        if (a.status_aprovacao === 'Aprovado') {
            const rotulo = criar('label', 'animal-gerenciar-rotulo', 'Status da adoção');
            const select = criar('select', 'animal-gerenciar-select');
            select.id = `status-adocao-${a.id_animal}`;
            rotulo.htmlFor = select.id;
            STATUS_ADOCAO.forEach(st => {
                const op = criar('option', '', st);
                op.value = st;
                op.selected = st === a.status_adocao;
                select.appendChild(op);
            });

            select.addEventListener('change', async () => {
                const anterior = a.status_adocao;
                erro('');
                select.disabled = true;
                try {
                    await enviarAcao('status', { id_animal: a.id_animal, status_adocao: select.value });
                    a.status_adocao = select.value;
                    carregarMeusAnimais();   // redesenha com o selo atualizado
                } catch (e) {
                    select.value = anterior;
                    erro(e.message);
                } finally {
                    select.disabled = false;
                }
            });

            const linha = criar('div', 'animal-gerenciar-linha');
            linha.append(rotulo, select);
            area.appendChild(linha);
        }

        // ----- Remover (pede confirmação no próprio card) -----
        const acoes = criar('div', 'animal-gerenciar-acoes');
        const btnEditar = criar('button', 'btn-mini', 'Editar informações');
        btnEditar.type = 'button';
        const btnRemover = criar('button', 'btn-mini btn-mini-perigo', 'Remover animal');
        btnRemover.type = 'button';

        const confirmacao = criar('div', 'animal-gerenciar-confirma');
        confirmacao.hidden = true;
        const pergunta = criar('span', '', `Remover ${a.nome_animal} definitivamente?`);
        const btnSim = criar('button', 'btn-mini btn-mini-perigo-cheio', 'Sim, remover');
        btnSim.type = 'button';
        const btnNao = criar('button', 'btn-mini', 'Cancelar');
        btnNao.type = 'button';
        confirmacao.append(pergunta, btnSim, btnNao);

        btnRemover.addEventListener('click', () => {
            erro('');
            btnRemover.hidden = true;
            confirmacao.hidden = false;
            btnNao.focus();
        });
        btnNao.addEventListener('click', () => {
            confirmacao.hidden = true;
            btnRemover.hidden = false;
            btnRemover.focus();
        });
        btnSim.addEventListener('click', async () => {
            btnSim.disabled = btnNao.disabled = true;
            btnSim.textContent = 'Removendo...';
            try {
                await enviarAcao('remover', { id_animal: a.id_animal });
                carregarMeusAnimais();   // o animal some da lista
            } catch (e) {
                erro(e.message);
                btnSim.disabled = btnNao.disabled = false;
                btnSim.textContent = 'Sim, remover';
            }
        });

        // Editar: troca a linha de botões pelo formulário (criado só na primeira vez)
        let edicao = null;
        btnEditar.addEventListener('click', () => {
            erro('');
            if (!edicao) {
                edicao = criarFormEdicao(a, () => {
                    edicao.form.hidden = true;
                    acoes.hidden = false;
                    btnEditar.focus();
                });
                area.insertBefore(edicao.form, msg);
            }
            acoes.hidden = true;
            edicao.form.hidden = false;
            edicao.focar();
        });

        acoes.append(btnEditar, btnRemover, confirmacao);
        area.append(acoes, msg);
        return area;
    }

    // "3 animais cadastrados · 1 aguardando aprovação · 1 recusado"
    function textoResumo(animais) {
        const conta = (status) => animais.filter(a => a.status_aprovacao === status).length;
        const pendentes = conta('Pendente');
        const recusados = conta('Recusado');
        const partes = [animais.length === 1 ? '1 animal cadastrado' : `${animais.length} animais cadastrados`];
        if (pendentes) partes.push(pendentes === 1 ? '1 aguardando aprovação' : `${pendentes} aguardando aprovação`);
        if (recusados) partes.push(recusados === 1 ? '1 recusado' : `${recusados} recusados`);
        return partes.join(' · ');
    }

    function mostrarMeusAnimais(animais) {
        listaAnimais.replaceChildren(...animais.map(criarCardAnimal));
        vazioAnimais.hidden = animais.length > 0;
        resumoAnimais.hidden = animais.length === 0;
        resumoAnimais.textContent = animais.length ? textoResumo(animais) : '';
    }

    function mostrarErroAnimais(texto) {
        erroAnimaisTxt.textContent = texto;
        erroAnimais.hidden = false;
    }

    async function carregarMeusAnimais() {
        const meuPedido = ++pedidoAtual;

        erroAnimais.hidden = true;
        if (!listaAnimais.hasChildNodes()) {   // 1ª vez: avisa que está carregando
            vazioAnimais.hidden = true;
            carregandoAnim.hidden = false;
        }

        try {
            const resposta = await fetch(URL_LISTA, { credentials: 'same-origin', cache: 'no-store' });
            const corpo = await resposta.json().catch(() => null);

            if (meuPedido !== pedidoAtual) return;   // chegou um pedido mais novo: ignora este

            if (EXIGIR_PROTETOR && resposta.status === 401) { location.replace(URL_LOGIN);  return; }
            if (EXIGIR_PROTETOR && resposta.status === 403 && corpo && corpo.erro === 'tipo_nao_permitido') {
                location.replace(URL_INICIO);
                return;
            }

            if (!resposta.ok || !corpo || !corpo.ok || !Array.isArray(corpo.animais)) {
                mostrarErroAnimais((corpo && corpo.mensagem) || 'Não foi possível carregar os seus animais agora.');
                return;
            }

            mostrarMeusAnimais(corpo.animais);
        } catch (erro) {
            if (meuPedido !== pedidoAtual) return;
            mostrarErroAnimais('Não foi possível se conectar ao servidor. Verifique sua conexão e tente de novo.');
        } finally {
            if (meuPedido === pedidoAtual) carregandoAnim.hidden = true;
        }
    }

    $('meus-animais-tentar').addEventListener('click', carregarMeusAnimais);

    // ==================================================
    // 4. FORMULÁRIO DE CADASTRO DE ANIMAL
    // ==================================================
    const form      = $('animal-form');
    const btnSalvar = $('btn-salvar');
    const avisoOk   = $('aviso-sucesso');
    const avisoErro = $('aviso-erro');

    const selCategoria = $('id_categoria');
    const inpEspecie   = $('especie');
    const inpAnos      = $('idade_anos');
    const inpMeses     = $('idade_meses_extra');
    const inpIdade     = $('idade_meses');     // hidden: vai para o banco

    const inpFoto      = $('foto');
    const fotoPrevia   = $('foto-previa');
    const fotoImg      = $('foto-img');
    const fotoVazio    = $('foto-vazio');
    const btnFotoRem   = $('foto-remover');

    // Regras da foto (o PHP também precisa conferir)
    const FOTO_TIPOS  = ['image/jpeg', 'image/png', 'image/webp'];
    const FOTO_MAX_MB = 5;

    // ---------- Contadores de caracteres ----------
    document.querySelectorAll('[data-contador-de]').forEach(contador => {
        const campo = $(contador.dataset.contadorDe);
        const max = campo.maxLength;

        const atualizar = () => {
            const total = campo.value.length;
            contador.textContent = `${total} / ${max}`;
            contador.classList.toggle('no-limite', total >= max);
        };

        campo.addEventListener('input', atualizar);
        form.addEventListener('reset', () => setTimeout(atualizar));
        atualizar();
    });

    // ---------- Categoria sugere a espécie ----------
    // Só preenche sozinho enquanto o protetor não tiver digitado a espécie.
    let especieDigitada = false;

    inpEspecie.addEventListener('input', () => {
        especieDigitada = inpEspecie.value.trim() !== '';
    });

    selCategoria.addEventListener('change', () => {
        if (especieDigitada) return;
        const opcao = selCategoria.selectedOptions[0];
        inpEspecie.value = opcao ? (opcao.dataset.especie || '') : '';
        limparErro('especie');
    });

    // ---------- Idade: anos + meses -> idade_meses ----------
    // Retorna: '' (não informada), um número (meses) ou null (valor inválido)
    function calcularIdadeMeses() {
        const anosTxt  = inpAnos.value.trim();
        const mesesTxt = inpMeses.value.trim();
        if (anosTxt === '' && mesesTxt === '') return '';

        const anos  = anosTxt  === '' ? 0 : Number(anosTxt);
        const meses = mesesTxt === '' ? 0 : Number(mesesTxt);

        const valido = Number.isInteger(anos)  && anos  >= 0 && anos  <= 50
                    && Number.isInteger(meses) && meses >= 0 && meses <= 11;
        return valido ? anos * 12 + meses : null;
    }

    [inpAnos, inpMeses].forEach(campo => campo.addEventListener('input', () => {
        const idade = calcularIdadeMeses();
        inpIdade.value = idade === null ? '' : idade;
        limparErro('idade');
    }));

    // ---------- Mensagens de erro por campo ----------
    function campoDe(nome) {
        return form.querySelector(`[data-campo="${nome}"]`);
    }

    function mostrarErro(nome, mensagem) {
        const bloco = campoDe(nome);
        const msg = $('erro-' + nome);
        if (!bloco || !msg) return;
        bloco.classList.add('tem-erro');
        bloco.querySelectorAll('input:not([type=hidden]), select, textarea')
             .forEach(el => el.setAttribute('aria-invalid', 'true'));
        msg.textContent = mensagem;
        msg.hidden = false;
    }

    function limparErro(nome) {
        const bloco = campoDe(nome);
        const msg = $('erro-' + nome);
        if (!bloco || !msg) return;
        bloco.classList.remove('tem-erro');
        bloco.querySelectorAll('[aria-invalid]').forEach(el => el.removeAttribute('aria-invalid'));
        msg.hidden = true;
    }

    // ---------- Foto: validação e prévia ----------
    function erroDaFoto(arquivo) {
        if (!arquivo) return '';
        if (!FOTO_TIPOS.includes(arquivo.type)) return 'Formato não aceito. Use JPG, PNG ou WebP.';
        if (arquivo.size > FOTO_MAX_MB * 1024 * 1024) return `A foto é muito grande. O limite é ${FOTO_MAX_MB} MB.`;
        return '';
    }

    function limparPrevia() {
        if (fotoImg.src.startsWith('blob:')) URL.revokeObjectURL(fotoImg.src);
        fotoImg.removeAttribute('src');
        fotoImg.hidden = true;
        fotoVazio.hidden = false;
        fotoPrevia.classList.remove('tem-foto');
        btnFotoRem.hidden = true;
    }

    inpFoto.addEventListener('change', () => {
        const arquivo = inpFoto.files[0];
        limparErro('foto');
        limparPrevia();
        if (!arquivo) return;

        const mensagem = erroDaFoto(arquivo);
        if (mensagem) {
            inpFoto.value = '';   // descarta o arquivo inválido
            mostrarErro('foto', mensagem);
            return;
        }

        fotoImg.src = URL.createObjectURL(arquivo);
        fotoImg.hidden = false;
        fotoVazio.hidden = true;
        fotoPrevia.classList.add('tem-foto');
        btnFotoRem.hidden = false;
    });

    btnFotoRem.addEventListener('click', () => {
        inpFoto.value = '';
        limparPrevia();
        limparErro('foto');
        inpFoto.focus();
    });

    // ---------- Validação ----------
    // Cada regra: [nome do campo, função que devolve a mensagem de erro ou '']
    const REGRAS = [
        ['nome_animal', () => {
            const v = $('nome_animal').value.trim();
            if (!v) return 'Informe o nome do animal.';
            return '';
        }],
        ['descricao', () => {
            const v = $('descricao').value.trim();
            if (!v) return 'Escreva uma descrição para o animal.';
            if (v.length < 20) return 'A descrição está muito curta. Conte um pouco mais (mínimo de 20 caracteres).';
            return '';
        }],
        ['id_categoria', () => (selCategoria.value ? '' : 'Selecione a categoria.')],
        ['especie', () => (inpEspecie.value.trim() ? '' : 'Informe a espécie do animal.')],
        ['idade', () => (calcularIdadeMeses() === null
            ? 'Use de 0 a 50 anos e de 0 a 11 meses.'
            : '')],
        ['foto', () => erroDaFoto(inpFoto.files[0])],   // foto é opcional
    ];

    // Limpa o erro de cada campo assim que o usuário volta a digitar nele
    REGRAS.forEach(([nome]) => {
        const bloco = campoDe(nome);
        if (!bloco || nome === 'foto') return;   // a foto limpa o erro no próprio handler
        bloco.addEventListener('input',  () => limparErro(nome));
        bloco.addEventListener('change', () => limparErro(nome));
    });

    function validar() {
        let primeiroComErro = null;

        REGRAS.forEach(([nome, regra]) => {
            const mensagem = regra();
            if (mensagem) {
                mostrarErro(nome, mensagem);
                if (!primeiroComErro) {
                    primeiroComErro = campoDe(nome).querySelector('input:not([type=hidden]), select, textarea');
                }
            } else {
                limparErro(nome);
            }
        });

        if (primeiroComErro) primeiroComErro.focus();
        return !primeiroComErro;
    }

    // ---------- Montagem dos dados (nomes = colunas da tabela animal) ----------
    function montarDados() {
        const dados = new FormData();
        const valor = (nome) => form.elements[nome].value.trim();
        const marcado = (nome) => (form.elements[nome].checked ? '1' : '0');   // TINYINT(1)

        dados.append('nome_animal', valor('nome_animal'));
        dados.append('descricao', valor('descricao'));
        dados.append('id_categoria', valor('id_categoria'));
        dados.append('especie', valor('especie'));
        dados.append('raca', valor('raca'));
        dados.append('idade_meses', inpIdade.value);   // '' = não informada (NULL no banco)
        dados.append('sexo', form.elements['sexo'].value);
        dados.append('porte', form.elements['porte'].value);   // '' = não informado (NULL)
        dados.append('cor', valor('cor'));
        dados.append('castrado', marcado('castrado'));
        dados.append('vacinado', marcado('vacinado'));
        dados.append('vermifugado', marcado('vermifugado'));
        dados.append('condicao_saude', valor('condicao_saude'));
        dados.append('compatibilidade_criancas', form.elements['compatibilidade_criancas'].value);
        dados.append('compatibilidade_animais', form.elements['compatibilidade_animais'].value);

        // Foto (opcional): o PHP salva o arquivo na pasta fotos/ e grava o caminho em foto_url
        if (inpFoto.files[0]) dados.append('foto', inpFoto.files[0]);

        // O id_protetor NÃO vai daqui: o PHP pega da sessão do usuário logado.
        return dados;
    }

    // ---------- Avisos ----------
    function esconderAvisos() {
        avisoOk.hidden = true;
        avisoErro.hidden = true;
    }

    function mostrarAviso(el, texto) {
        esconderAvisos();
        el.textContent = texto;
        el.hidden = false;
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function restaurarFormulario() {
        form.reset();
        limparPrevia();
        especieDigitada = false;
        inpIdade.value = '';
        REGRAS.forEach(([nome]) => limparErro(nome));
    }

    // ---------- Limpar formulário ----------
    form.addEventListener('reset', () => {
        limparPrevia();
        especieDigitada = false;
        inpIdade.value = '';
        REGRAS.forEach(([nome]) => limparErro(nome));
        esconderAvisos();
    });

    // ---------- Envio ----------
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        esconderAvisos();

        if (!validar()) return;

        btnSalvar.disabled = true;
        btnSalvar.textContent = 'Salvando...';

        try {
            const resposta = await fetch(URL_CADASTRO, {
                method: 'POST',
                body: montarDados(),
                credentials: 'same-origin',
            });

            // Resposta do PHP: { ok, mensagem, ... }. Se não vier JSON válido, cai no catch abaixo
            const resultado = await resposta.json();

            if (resposta.ok && resultado.ok) {
                restaurarFormulario();
                mostrarAviso(avisoOk, resultado.mensagem || 'Animal cadastrado com sucesso!');
            } else {
                mostrarAviso(avisoErro, resultado.mensagem || 'Não foi possível cadastrar o animal. Tente novamente.');
            }
        } catch (erro) {
            mostrarAviso(avisoErro, 'Não foi possível se conectar ao servidor. Tente novamente em instantes.');
        } finally {
            btnSalvar.disabled = false;
            btnSalvar.textContent = 'Cadastrar animal';
        }
    });

    // ==================================================
    // 5. ABA INICIAL
    // Fica por último de propósito: abrir o endereço com "#animais" já dispara o
    // carregamento da lista, que usa elementos e constantes definidos acima.
    // ==================================================
    selecionarAba(location.hash.replace('#', ''));

    // Mudou só o "#..." do endereço (link para #animais, botão voltar): troca a aba
    window.addEventListener('hashchange', () => selecionarAba(location.hash.replace('#', '')));
})();
