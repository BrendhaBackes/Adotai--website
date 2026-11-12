// =====================================================
// Painel do administrador (pages/admin_painel.html):
//  - só deixa entrar quem está logado como admin;
//  - abas: Visão geral / Animais / Protetores / Suporte;
//  - Animais: aprovar ou recusar (com observação para o protetor);
//  - Protetores: aprovar ou recusar o cadastro;
//  - Suporte: responder chamados.
//
// Os dados vêm de api/admin.php (veja o objeto API logo abaixo,
// com o formato de cada resposta).
// Para ver o painel funcionando SEM o back-end, ponha MODO_DEMO = true.
// =====================================================
(function () {
    const base = new URL('../', document.currentScript.src);   // raiz do site (pasta adotai/)

    const URL_SESSAO  = new URL('api/sessao.php', base).href;
    const URL_LOGIN   = new URL('pages/login.html', base).href;
    const URL_INICIO  = new URL('index.html', base).href;
    const FOTO_PADRAO = new URL('images/franky.jpeg', base).href;   // se o animal não tiver foto

    // true  = quem não for admin logado é redirecionado.
    const EXIGIR_ADMIN = true;

    // true = usa dados de exemplo (sem PHP, sem login) só para ver o visual.
    // Em produção deixe false.
    const MODO_DEMO = false;

    // ==================================================
    // CONTRATO COM O BACK-END  (api/admin.php)
    //
    // Uma única página PHP atende o painel; a ação vai na URL (?acao=...).
    // Toda resposta é JSON:  { ok: true, ... }  ou  { ok: false, erro: "codigo", mensagem: "texto" }
    //
    // GET  ?acao=animais    -> { ok, animais: [ {
    //          id_animal, nome_animal, descricao, especie, raca, idade_meses, sexo, porte, cor,
    //          castrado, vacinado, vermifugado, condicao_saude, compatibilidade_criancas,
    //          compatibilidade_animais, cidade, uf, status_adocao, data_cadastro, foto_url,
    //          status_aprovacao, observacao_admin, protetor_nome } ] }
    // POST ?acao=animal_status     { id_animal, status_aprovacao ('Aprovado'|'Recusado'), observacao_admin }
    //
    // GET  ?acao=protetores -> { ok, protetores: [ {
    //          id_protetor, nome, nome_organizacao, eh_ong, email, telefone, cidade, uf,
    //          documento (CPF/CNPJ), data_cadastro, status_aprovacao } ] }
    // POST ?acao=protetor_status   { id_protetor, status_aprovacao ('Aprovado'|'Recusado') }
    //
    // GET  ?acao=suporte    -> { ok, chamados: [ {
    //          id, titulo, descricao, resposta (null se aberto), data_abertura,
    //          usuario_nome, usuario_email } ] }
    // POST ?acao=suporte_responder { id, resposta }
    //
    // Erros: 401 = sessão expirada (volta ao login) | 403 tipo_nao_permitido = não é admin (volta ao início)
    // ==================================================
    const ENDPOINT_ADMIN = 'api/admin.php';

    const API = {
        animais:          `${ENDPOINT_ADMIN}?acao=animais`,
        animalStatus:     `${ENDPOINT_ADMIN}?acao=animal_status`,
        protetores:       `${ENDPOINT_ADMIN}?acao=protetores`,
        protetorStatus:   `${ENDPOINT_ADMIN}?acao=protetor_status`,
        suporte:          `${ENDPOINT_ADMIN}?acao=suporte`,
        suporteResponder: `${ENDPOINT_ADMIN}?acao=suporte_responder`,
    };

    // ---------- Utilitários ----------
    const $ = (id) => document.getElementById(id);

    function criar(tag, classe, texto) {
        const el = document.createElement(tag);
        if (classe) el.className = classe;
        if (texto !== undefined) el.textContent = texto;   // textContent: nunca interpreta HTML
        return el;
    }

    // "Em processo" -> "em-processo"  (para virar classe CSS)
    function paraClasse(texto) {
        return String(texto || '').trim().toLowerCase()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/\s+/g, '-');
    }

    // minúsculo e sem acento (para a busca)
    function normalizar(texto) {
        return String(texto || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }

    function formatarData(valor) {
        const m = String(valor || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
        return m ? `${m[3]}/${m[2]}/${m[1]}` : 'Não informada';
    }

    function formatarIdade(valor) {
        if (valor === null || valor === undefined || valor === '') return 'Não informada';
        const total = Number(valor);
        const anos = Math.floor(total / 12);
        const meses = total % 12;
        const partes = [];
        if (anos) partes.push(`${anos} ${anos === 1 ? 'ano' : 'anos'}`);
        if (meses || !anos) partes.push(`${meses} ${meses === 1 ? 'mês' : 'meses'}`);
        return partes.join(' e ');
    }

    const simNao = (v) => (Number(v) === 1 ? 'Sim' : 'Não');
    const vazio  = (v) => (v === null || v === undefined || v === '' ? 'Não informado' : String(v));
    const fotoDe = (caminho) => (caminho ? new URL(caminho, base).href : FOTO_PADRAO);

    function selo(texto, status) {
        return criar('span', `selo selo-${paraClasse(status || texto)}`, texto);
    }

    function botao(texto, classe, aoClicar) {
        const b = criar('button', 'btn-mini ' + (classe || ''), texto);
        b.type = 'button';
        b.addEventListener('click', () => aoClicar(b));
        return b;
    }

    // ==================================================
    // 1. SESSÃO: só admin entra aqui
    // ==================================================
    if (!MODO_DEMO) {
        fetch(URL_SESSAO, { credentials: 'same-origin', cache: 'no-store' })
            .then(r => (r.ok ? r.json() : null))
            .then(dados => {
                if (!EXIGIR_ADMIN || !dados) return;   // sem resposta: não bloqueia a tela

                if (!dados.logado)            { location.replace(URL_LOGIN);  return; }
                if (dados.tipo !== 'admin')   { location.replace(URL_INICIO); return; }

                if (dados.nome) $('painel-nome').textContent = dados.nome;
            })
            .catch(() => { /* sem resposta: mantém a página como está */ });
    }

    // ==================================================
    // 2. COMUNICAÇÃO COM O SERVIDOR
    // ==================================================
    // Sem "dados" faz GET; com "dados" faz POST. Devolve o JSON ou lança um Error com a mensagem.
    async function requisitar(chave, dados) {
        if (MODO_DEMO) return demoRequisitar(chave, dados);

        // O cabeçalho X-Requested-With é exigido pelo admin.php nas ações que gravam
        const opcoes = {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'X-Requested-With': 'fetch' },
        };
        if (dados) {
            opcoes.method = 'POST';
            opcoes.body = new URLSearchParams(dados);
        }

        let resposta, corpo;
        try {
            resposta = await fetch(new URL(API[chave], base).href, opcoes);
            corpo = await resposta.json();
        } catch (e) {
            throw new Error('Não foi possível se conectar ao servidor. Tente novamente.');
        }

        if (resposta.status === 401) { location.replace(URL_LOGIN); throw new Error('Sessão expirada.'); }
        if (resposta.status === 403 && corpo.erro === 'tipo_nao_permitido') {
            location.replace(URL_INICIO);   // não é (mais) admin
            throw new Error(corpo.mensagem || 'Acesso negado.');
        }
        if (!resposta.ok || !corpo.ok) throw new Error(corpo.mensagem || 'Não foi possível concluir a ação.');
        return corpo;
    }

    // ==================================================
    // 3. ESTADO
    // ==================================================
    let animais    = [];
    let protetores = [];
    let chamados   = [];
    const carregado = { animais: false, protetores: false, suporte: false };

    // ==================================================
    // 4. AVISO GERAL (sucesso / erro das ações)
    // ==================================================
    const aviso = $('painel-aviso');
    let timerAviso = null;

    function avisar(texto, tipo) {
        clearTimeout(timerAviso);
        aviso.textContent = texto;
        aviso.className = 'aviso ' + tipo;   // 'sucesso' | 'erro'
        aviso.hidden = false;
        if (tipo === 'sucesso') timerAviso = setTimeout(() => { aviso.hidden = true; }, 6000);
    }

    // ==================================================
    // 5. ABAS
    // O nome da aba é o id sem o "aba-": botão #aba-animais -> section #painel-animais.
    // Para criar uma aba nova: botão + section no HTML (com aria-controls) e a lista abaixo se atualiza.
    // ==================================================
    const abas = [...document.querySelectorAll('.painel-aba')];
    const nomeDaAba = (aba) => aba.id.replace('aba-', '');

    function selecionarAba(nome, focar) {
        if (!abas.some(a => nomeDaAba(a) === nome)) nome = 'resumo';

        abas.forEach(aba => {
            const ativa = nomeDaAba(aba) === nome;
            aba.setAttribute('aria-selected', String(ativa));
            aba.tabIndex = ativa ? 0 : -1;
            $(aba.getAttribute('aria-controls')).hidden = !ativa;
            if (ativa && focar) aba.focus();
        });

        history.replaceState(null, '', '#' + nome);
    }

    abas.forEach((aba, i) => {
        aba.addEventListener('click', () => selecionarAba(nomeDaAba(aba)));

        // setas do teclado navegam entre as abas
        aba.addEventListener('keydown', (e) => {
            const passo = { ArrowDown: 1, ArrowRight: 1, ArrowUp: -1, ArrowLeft: -1 }[e.key];
            if (passo) {
                e.preventDefault();
                selecionarAba(nomeDaAba(abas[(i + passo + abas.length) % abas.length]), true);
            }
        });
    });

    // Cartões da visão geral: levam para a aba (e já deixam o filtro certo marcado)
    document.querySelectorAll('[data-ir-aba]').forEach(el => {
        el.addEventListener('click', () => {
            const nome = el.dataset.irAba;
            const radio = document.querySelector(`input[name="filtro-${nome}"][value="${el.dataset.filtro}"]`);
            if (radio) radio.checked = true;
            renderizarTudo();
            selecionarAba(nome, true);
        });
    });

    // ==================================================
    // 6. JANELAS (<dialog>)
    // ==================================================
    const dlgAnimal    = $('dlg-animal');
    const dlgConfirmar = $('dlg-confirmar');

    // Botões "Fechar"/"Cancelar" e clique no fundo escuro fecham a janela
    [dlgAnimal, dlgConfirmar].forEach(dlg => {
        dlg.querySelectorAll('[data-fechar]').forEach(b => b.addEventListener('click', () => dlg.close()));
        dlg.addEventListener('click', (e) => { if (e.target === dlg) dlg.close(); });
    });

    // Pergunta "tem certeza?" -> Promise<boolean>
    function confirmar(titulo, texto, rotuloOk) {
        return new Promise(resolver => {
            const ok = $('dlg-confirmar-ok');
            $('dlg-confirmar-titulo').textContent = titulo;
            $('dlg-confirmar-texto').textContent = texto;
            ok.textContent = rotuloOk;
            dlgConfirmar.returnValue = '';

            const aoConfirmar = () => dlgConfirmar.close('ok');
            ok.addEventListener('click', aoConfirmar, { once: true });
            dlgConfirmar.addEventListener('close', () => {
                ok.removeEventListener('click', aoConfirmar);
                resolver(dlgConfirmar.returnValue === 'ok');
            }, { once: true });

            dlgConfirmar.showModal();
        });
    }

    // ==================================================
    // 7. LISTAS: utilitários
    // ==================================================
    const valorFiltro = (nome) => document.querySelector(`input[name="${nome}"]:checked`).value;

    function mostrarAvisoLista(id, texto) {
        const el = $(id);
        el.textContent = texto;
        el.hidden = !texto;
    }

    function preencher(containerId, itens) {
        const lista = $(containerId);
        lista.replaceChildren(...itens);
    }

    // ==================================================
    // 8. ANIMAIS
    // ==================================================
    function renderizarAnimais() {
        if (!carregado.animais) return;

        const filtro = valorFiltro('filtro-animais');
        const busca  = normalizar($('busca-animais').value);

        const filtrados = animais.filter(a =>
            (filtro === 'todos' || a.status_aprovacao === filtro) &&
            (!busca || normalizar([a.nome_animal, a.especie, a.raca, a.protetor_nome].join(' ')).includes(busca))
        );

        preencher('lista-animais', filtrados.map(itemAnimal));

        const rotulos = { Pendente: 'pendente', Aprovado: 'aprovado', Recusado: 'recusado' };
        mostrarAvisoLista('aviso-animais', filtrados.length ? '' :
            (filtro === 'todos' || busca ? 'Nenhum animal encontrado.' : `Nenhum animal ${rotulos[filtro]}.`));
    }

    function itemAnimal(a) {
        const item = criar('article', 'item');

        const foto = criar('img', 'item-foto');
        foto.src = fotoDe(a.foto_url);
        foto.alt = `Foto de ${a.nome_animal}`;
        foto.loading = 'lazy';
        foto.addEventListener('error', () => { foto.src = FOTO_PADRAO; }, { once: true });

        const info = criar('div', 'item-info');
        const titulo = criar('h3', 'item-titulo', a.nome_animal);
        titulo.append(selo(a.status_aprovacao), selo(a.status_adocao));
        info.append(
            titulo,
            criar('p', 'item-linha', [a.especie, a.raca].filter(Boolean).join(' • ')),
            criar('p', 'item-linha', `Protetor: ${vazio(a.protetor_nome)} • Cadastrado em ${formatarData(a.data_cadastro)}`),
        );

        const acoes = criar('div', 'item-acoes');
        acoes.append(botao('Ver detalhes', '', () => abrirAnimal(a, false)));
        if (a.status_aprovacao !== 'Aprovado') {
            acoes.append(botao('Aprovar', 'aprovar', (b) => decidirAnimal(a, 'Aprovado', '', [b])));
        }
        if (a.status_aprovacao !== 'Recusado') {
            // Recusar exige uma observação: abre a janela já com o cursor no campo
            acoes.append(botao('Recusar', 'recusar', () => abrirAnimal(a, true)));
        }

        item.append(foto, info, acoes);
        return item;
    }

    // ---------- Janela de detalhes do animal ----------
    let animalAberto = null;
    const dlgObs  = $('dlg-animal-obs');
    const dlgErro = $('dlg-animal-erro');
    const btnAprovar = $('dlg-animal-aprovar');
    const btnRecusar = $('dlg-animal-recusar');

    function abrirAnimal(a, focarObservacao) {
        animalAberto = a;

        $('dlg-animal-titulo').textContent = a.nome_animal;

        const foto = $('dlg-animal-foto');
        foto.src = fotoDe(a.foto_url);
        foto.alt = `Foto de ${a.nome_animal}`;
        foto.onerror = () => { foto.onerror = null; foto.src = FOTO_PADRAO; };

        $('dlg-animal-badges').replaceChildren(selo(a.status_aprovacao), selo(a.status_adocao));

        const campos = [
            ['Protetor', vazio(a.protetor_nome)],
            ['Cadastrado em', formatarData(a.data_cadastro)],
            ['Espécie', vazio(a.especie)],
            ['Raça', vazio(a.raca)],
            ['Idade', formatarIdade(a.idade_meses)],
            ['Sexo', vazio(a.sexo)],
            ['Porte', vazio(a.porte)],
            ['Cor', vazio(a.cor)],
            ['Castrado', simNao(a.castrado)],
            ['Vacinado', simNao(a.vacinado)],
            ['Vermifugado', simNao(a.vermifugado)],
            ['Local', [a.cidade, a.uf].filter(Boolean).join(' - ') || 'Não informado'],
            ['Convive com crianças', vazio(a.compatibilidade_criancas)],
            ['Convive com outros animais', vazio(a.compatibilidade_animais)],
            ['Condição de saúde', vazio(a.condicao_saude), true],
            ['Descrição', vazio(a.descricao), true],
        ];

        $('dlg-animal-detalhes').replaceChildren(...campos.map(([rotulo, valor, largo]) => {
            const bloco = criar('div', largo ? 'largo' : '');
            bloco.append(criar('dt', '', rotulo), criar('dd', '', valor));
            return bloco;
        }));

        dlgObs.value = a.observacao_admin || '';
        limparErroJanela();
        btnAprovar.hidden = a.status_aprovacao === 'Aprovado';
        btnRecusar.hidden = a.status_aprovacao === 'Recusado';

        dlgAnimal.showModal();
        dlgAnimal.querySelector('.janela-corpo').scrollTop = 0;
        if (focarObservacao) {
            dlgObs.focus();
            dlgObs.scrollIntoView({ block: 'center' });
        }
    }

    function limparErroJanela() {
        dlgErro.hidden = true;
        dlgObs.closest('.campo').classList.remove('tem-erro');
    }

    dlgObs.addEventListener('input', limparErroJanela);

    btnAprovar.addEventListener('click', () => {
        decidirAnimal(animalAberto, 'Aprovado', dlgObs.value.trim(), [btnAprovar, btnRecusar]);
    });

    btnRecusar.addEventListener('click', () => {
        const obs = dlgObs.value.trim();
        if (!obs) {
            dlgErro.textContent = 'Explique o motivo da recusa para o protetor.';
            dlgErro.hidden = false;
            dlgObs.closest('.campo').classList.add('tem-erro');
            dlgObs.focus();
            return;
        }
        decidirAnimal(animalAberto, 'Recusado', obs, [btnAprovar, btnRecusar]);
    });

    // Envia a decisão ao servidor e atualiza a tela. "botoes" ficam desativados durante o envio.
    async function decidirAnimal(a, status, observacao, botoes) {
        botoes.forEach(b => { b.disabled = true; });

        try {
            await requisitar('animalStatus', {
                id_animal: a.id_animal,
                status_aprovacao: status,
                observacao_admin: observacao,
            });

            a.status_aprovacao = status;
            a.observacao_admin = observacao || null;
            if (dlgAnimal.open) dlgAnimal.close();
            renderizarTudo();
            avisar(`${a.nome_animal} foi ${status === 'Aprovado' ? 'aprovado' : 'recusado'}.`, 'sucesso');

        } catch (erro) {
            if (dlgAnimal.open) {
                dlgErro.textContent = erro.message;
                dlgErro.hidden = false;
            } else {
                avisar(erro.message, 'erro');
            }
        } finally {
            botoes.forEach(b => { b.disabled = false; });
        }
    }

    // ==================================================
    // 9. PROTETORES
    // ==================================================
    function renderizarProtetores() {
        if (!carregado.protetores) return;

        const filtro = valorFiltro('filtro-protetores');
        const busca  = normalizar($('busca-protetores').value);

        const filtrados = protetores.filter(p =>
            (filtro === 'todos' || p.status_aprovacao === filtro) &&
            (!busca || normalizar([p.nome, p.nome_organizacao, p.email].join(' ')).includes(busca))
        );

        preencher('lista-protetores', filtrados.map(itemProtetor));

        const rotulos = { Pendente: 'pendente', Aprovado: 'aprovado', Recusado: 'recusado' };
        mostrarAvisoLista('aviso-protetores', filtrados.length ? '' :
            (filtro === 'todos' || busca ? 'Nenhum protetor encontrado.' : `Nenhum protetor ${rotulos[filtro]}.`));
    }

    const nomeDoProtetor = (p) => p.nome_organizacao || p.nome || 'Sem nome';

    function itemProtetor(p) {
        const item = criar('article', 'item');
        const nome = nomeDoProtetor(p);

        const inicial = criar('div', 'item-inicial', nome.trim().charAt(0).toUpperCase());
        inicial.setAttribute('aria-hidden', 'true');

        const info = criar('div', 'item-info');
        const titulo = criar('h3', 'item-titulo', nome);
        titulo.append(selo(p.status_aprovacao), selo(Number(p.eh_ong) ? 'ONG' : 'Pessoa física', 'neutro'));
        info.append(
            titulo,
            criar('p', 'item-linha', [p.email, p.telefone].filter(Boolean).join(' • ')),
            criar('p', 'item-linha', [
                [p.cidade, p.uf].filter(Boolean).join(' - '),
                p.documento ? `${Number(p.eh_ong) ? 'CNPJ' : 'CPF'}: ${p.documento}` : '',
                `Cadastro em ${formatarData(p.data_cadastro)}`,
            ].filter(Boolean).join(' • ')),
        );

        const acoes = criar('div', 'item-acoes');
        if (p.status_aprovacao !== 'Aprovado') {
            acoes.append(botao('Aprovar', 'aprovar', (b) => decidirProtetor(p, 'Aprovado', b)));
        }
        if (p.status_aprovacao !== 'Recusado') {
            acoes.append(botao('Recusar', 'recusar', (b) => decidirProtetor(p, 'Recusado', b)));
        }

        item.append(inicial, info, acoes);
        return item;
    }

    async function decidirProtetor(p, status, btn) {
        if (status === 'Recusado') {
            const certeza = await confirmar(
                'Recusar protetor?',
                `${nomeDoProtetor(p)} não poderá cadastrar animais enquanto o cadastro estiver recusado.`,
                'Recusar'
            );
            if (!certeza) return;
        }

        btn.disabled = true;
        try {
            await requisitar('protetorStatus', { id_protetor: p.id_protetor, status_aprovacao: status });
            p.status_aprovacao = status;
            renderizarTudo();
            avisar(`${nomeDoProtetor(p)} foi ${status === 'Aprovado' ? 'aprovado' : 'recusado'}.`, 'sucesso');
        } catch (erro) {
            avisar(erro.message, 'erro');
        } finally {
            btn.disabled = false;
        }
    }

    // ==================================================
    // 10. SUPORTE
    // ==================================================
    function renderizarSuporte() {
        if (!carregado.suporte) return;

        const filtro = valorFiltro('filtro-suporte');
        const filtrados = chamados.filter(c =>
            filtro === 'todos' || (filtro === 'abertos' ? !c.resposta : !!c.resposta)
        );

        preencher('lista-suporte', filtrados.map(itemChamado));

        mostrarAvisoLista('aviso-suporte', filtrados.length ? '' :
            (filtro === 'abertos' ? 'Nenhum chamado em aberto.' : 'Nenhum chamado encontrado.'));
    }

    function itemChamado(c) {
        const item = criar('article', 'item item-suporte');

        const titulo = criar('h3', 'item-titulo', c.titulo);
        titulo.append(c.resposta ? selo('Respondido', 'aprovado') : selo('Em aberto', 'pendente'));

        item.append(
            titulo,
            criar('p', 'item-linha', `${vazio(c.usuario_nome)} • ${vazio(c.usuario_email)} • Aberto em ${formatarData(c.data_abertura)}`),
            criar('p', 'item-texto', c.descricao),
        );

        if (c.resposta) {
            const caixa = criar('div', 'resposta-box');
            caixa.append(criar('strong', '', 'Resposta enviada'), document.createTextNode(c.resposta));
            item.append(caixa);
            return item;
        }

        // Chamado em aberto: campo de resposta
        const form = criar('form', 'resposta-form');
        const campo = criar('div', 'campo');
        const area = criar('textarea');
        area.rows = 3;
        area.maxLength = 1000;
        area.placeholder = 'Escreva a resposta para o usuário...';
        area.setAttribute('aria-label', `Resposta para "${c.titulo}"`);
        const erro = criar('p', 'campo-erro');
        erro.hidden = true;
        campo.append(area, erro);

        const enviar = criar('button', 'btn-mini aprovar', 'Enviar resposta');
        enviar.type = 'submit';

        area.addEventListener('input', () => { erro.hidden = true; campo.classList.remove('tem-erro'); });

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const texto = area.value.trim();
            if (!texto) {
                erro.textContent = 'Escreva a resposta antes de enviar.';
                erro.hidden = false;
                campo.classList.add('tem-erro');
                area.focus();
                return;
            }

            enviar.disabled = true;
            try {
                await requisitar('suporteResponder', { id: c.id, resposta: texto });
                c.resposta = texto;
                renderizarTudo();
                avisar('Resposta enviada.', 'sucesso');
            } catch (falha) {
                avisar(falha.message, 'erro');
            } finally {
                enviar.disabled = false;
            }
        });

        form.append(campo, enviar);
        item.append(form);
        return item;
    }

    // ==================================================
    // 11. VISÃO GERAL E SELOS DAS ABAS
    // ==================================================
    function renderizarResumo() {
        const numero = (id, valor, pronto) => { $(id).textContent = pronto ? String(valor) : '–'; };
        const publicados = animais.filter(a => a.status_aprovacao === 'Aprovado');

        numero('stat-animais-pendentes', animais.filter(a => a.status_aprovacao === 'Pendente').length, carregado.animais);
        numero('stat-animais-disponiveis', publicados.filter(a => a.status_adocao === 'Disponível').length, carregado.animais);
        numero('stat-animais-adotados', publicados.filter(a => a.status_adocao === 'Adotado').length, carregado.animais);
        numero('stat-protetores-pendentes', protetores.filter(p => p.status_aprovacao === 'Pendente').length, carregado.protetores);
        numero('stat-protetores-total', protetores.filter(p => p.status_aprovacao === 'Aprovado').length, carregado.protetores);
        numero('stat-suporte-abertos', chamados.filter(c => !c.resposta).length, carregado.suporte);

        const selos = [
            ['badge-animais',    animais.filter(a => a.status_aprovacao === 'Pendente').length,    carregado.animais],
            ['badge-protetores', protetores.filter(p => p.status_aprovacao === 'Pendente').length, carregado.protetores],
            ['badge-suporte',    chamados.filter(c => !c.resposta).length,                         carregado.suporte],
        ];
        selos.forEach(([id, qtd, pronto]) => {
            $(id).textContent = String(qtd);
            $(id).hidden = !pronto || qtd === 0;
        });
    }

    function renderizarTudo() {
        renderizarAnimais();
        renderizarProtetores();
        renderizarSuporte();
        renderizarResumo();
    }

    // Filtros e buscas
    document.querySelectorAll('input[name^="filtro-"]').forEach(r => r.addEventListener('change', renderizarTudo));
    $('busca-animais').addEventListener('input', renderizarAnimais);
    $('busca-protetores').addEventListener('input', renderizarProtetores);

    // ==================================================
    // 12. CARREGAMENTO INICIAL
    // Cada lista carrega sozinha: se uma falhar, as outras continuam funcionando.
    // ==================================================
    function carregar(chave, campoResposta, destino, avisoId, receber) {
        mostrarAvisoLista(avisoId, 'Carregando...');
        return requisitar(chave)
            .then(corpo => {
                receber(corpo[campoResposta] || []);
                carregado[destino] = true;
                mostrarAvisoLista(avisoId, '');
            })
            .catch(erro => mostrarAvisoLista(avisoId, erro.message));
    }

    Promise.all([
        carregar('animais',    'animais',    'animais',    'aviso-animais',    v => { animais = v; }),
        carregar('protetores', 'protetores', 'protetores', 'aviso-protetores', v => { protetores = v; }),
        carregar('suporte',    'chamados',   'suporte',    'aviso-suporte',    v => { chamados = v; }),
    ]).then(renderizarTudo);

    selecionarAba(location.hash.replace('#', ''));

    // ==================================================
    // 13. DADOS DE EXEMPLO (só usados com MODO_DEMO = true)
    // ==================================================
    const demo = {
        animais: [
            { id_animal: 1, nome_animal: 'Thor', descricao: 'Muito brincalhão e carinhoso. Resgatado na rua com poucos meses e hoje convive bem com toda a família.', especie: 'Cachorro', raca: 'Vira-lata', idade_meses: 18, sexo: 'Macho', porte: 'Médio', cor: 'Caramelo', castrado: 1, vacinado: 1, vermifugado: 0, condicao_saude: 'Saudável', compatibilidade_criancas: 'Sim', compatibilidade_animais: 'Não informado', cidade: 'Curitiba', uf: 'PR', status_adocao: 'Disponível', data_cadastro: '2026-11-10', foto_url: 'fotos/cachorros/thor-vira-lata-caramelo.jpg', status_aprovacao: 'Pendente', observacao_admin: null, protetor_nome: 'Patinhas Felizes' },
            { id_animal: 2, nome_animal: 'Luna', descricao: 'Gata calma, gosta de colo e de ficar perto da janela.', especie: 'Gato', raca: 'SRD', idade_meses: 30, sexo: 'Fêmea', porte: 'Pequeno', cor: 'Cinza', castrado: 1, vacinado: 1, vermifugado: 1, condicao_saude: 'Saudável, sem restrições.', compatibilidade_criancas: 'Sim', compatibilidade_animais: 'Sim', cidade: 'Colombo', uf: 'PR', status_adocao: 'Disponível', data_cadastro: '2026-11-09', foto_url: 'fotos/gatos/luna-gata-cinza.jpg', status_aprovacao: 'Pendente', observacao_admin: null, protetor_nome: 'Lar Animal' },
            { id_animal: 3, nome_animal: 'Kiko', descricao: 'Beagle muito energético, precisa de passeios diários.', especie: 'Cachorro', raca: 'Beagle', idade_meses: 48, sexo: 'Macho', porte: 'Médio', cor: 'Tricolor', castrado: 0, vacinado: 1, vermifugado: 1, condicao_saude: null, compatibilidade_criancas: 'Sim', compatibilidade_animais: 'Sim', cidade: 'Curitiba', uf: 'PR', status_adocao: 'Em processo', data_cadastro: '2026-10-20', foto_url: 'fotos/cachorros/kiko-beagle-tricolor.jpg', status_aprovacao: 'Aprovado', observacao_admin: null, protetor_nome: 'Amigo Bicho' },
            { id_animal: 4, nome_animal: 'Billy', descricao: 'Calopsita que adora assobiar.', especie: 'Ave', raca: 'Calopsita', idade_meses: 14, sexo: 'Macho', porte: 'Pequeno', cor: 'Cinza e amarelo', castrado: 0, vacinado: 0, vermifugado: 0, condicao_saude: 'Saudável', compatibilidade_criancas: 'Sim', compatibilidade_animais: 'Não', cidade: 'Curitiba', uf: 'PR', status_adocao: 'Adotado', data_cadastro: '2026-10-05', foto_url: 'fotos/aves/billy-calopsita-cinza-amarela.jpg', status_aprovacao: 'Aprovado', observacao_admin: null, protetor_nome: 'Patinhas Felizes' },
            { id_animal: 5, nome_animal: 'Lola', descricao: 'Coelha dócil, acostumada a ser manuseada.', especie: 'Coelho', raca: 'Mini Rex', idade_meses: 10, sexo: 'Fêmea', porte: 'Pequeno', cor: 'Cinza', castrado: 0, vacinado: 0, vermifugado: 1, condicao_saude: null, compatibilidade_criancas: 'Não informado', compatibilidade_animais: 'Não informado', cidade: 'Colombo', uf: 'PR', status_adocao: 'Disponível', data_cadastro: '2026-10-28', foto_url: 'fotos/coelhos/lola-mini-rex-cinza.jpg', status_aprovacao: 'Recusado', observacao_admin: 'Foto sem o animal em destaque. Envie outra imagem.', protetor_nome: 'Lar Animal' },
        ],
        protetores: [
            { id_protetor: 1, nome: 'Marina Duarte', nome_organizacao: 'Patinhas Felizes', eh_ong: 1, email: 'contato@patinhasfelizes.test', telefone: '(41) 99000-0001', cidade: 'Curitiba', uf: 'PR', documento: '12.345.678/0001-99', data_cadastro: '2026-10-01', status_aprovacao: 'Aprovado' },
            { id_protetor: 4, nome: 'Carlos Lima', nome_organizacao: null, eh_ong: 0, email: 'carlos.lima@exemplo.test', telefone: '(41) 99000-0044', cidade: 'Pinhais', uf: 'PR', documento: '123.456.789-09', data_cadastro: '2026-11-11', status_aprovacao: 'Pendente' },
            { id_protetor: 5, nome: 'Ana Paula', nome_organizacao: 'Resgate Patas Unidas', eh_ong: 1, email: 'ana@patasunidas.test', telefone: '(41) 99000-0055', cidade: 'São José dos Pinhais', uf: 'PR', documento: '45.678.901/0001-23', data_cadastro: '2026-11-12', status_aprovacao: 'Pendente' },
        ],
        chamados: [
            { id: 1, titulo: 'Não consigo editar meu perfil', descricao: 'Ao clicar em salvar nada acontece. Já tentei em dois navegadores.', resposta: null, data_abertura: '2026-11-11', usuario_nome: 'Joana Reis', usuario_email: 'joana@exemplo.test' },
            { id: 2, titulo: 'Dúvida sobre o processo de adoção', descricao: 'Preciso levar algum documento na visita?', resposta: 'Olá! Leve um documento com foto e comprovante de residência.', data_abertura: '2026-11-08', usuario_nome: 'Pedro Alves', usuario_email: 'pedro@exemplo.test' },
        ],
    };

    // Imita o servidor: devolve cópias dos dados e aplica as mudanças nos exemplos
    function demoRequisitar(chave, dados) {
        const copia = (x) => JSON.parse(JSON.stringify(x));
        const achar = (lista, campo, valor) => lista.find(i => String(i[campo]) === String(valor));

        const respostas = {
            animais:    () => ({ ok: true, animais: copia(demo.animais) }),
            protetores: () => ({ ok: true, protetores: copia(demo.protetores) }),
            suporte:    () => ({ ok: true, chamados: copia(demo.chamados) }),
            animalStatus: () => {
                const a = achar(demo.animais, 'id_animal', dados.id_animal);
                a.status_aprovacao = dados.status_aprovacao;
                a.observacao_admin = dados.observacao_admin || null;
                return { ok: true };
            },
            protetorStatus: () => {
                achar(demo.protetores, 'id_protetor', dados.id_protetor).status_aprovacao = dados.status_aprovacao;
                return { ok: true };
            },
            suporteResponder: () => {
                achar(demo.chamados, 'id', dados.id).resposta = dados.resposta;
                return { ok: true };
            },
        };

        return new Promise(resolver => setTimeout(() => resolver(respostas[chave]()), 250));
    }
})();
