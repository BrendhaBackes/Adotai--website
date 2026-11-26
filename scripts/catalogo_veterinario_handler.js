// =====================================================
// Catálogo de veterinários: busca os dados em api/veterinarios.php,
// gera um card para cada profissional (com os dados de contato)
// e aplica o filtro da caixa de busca.
// =====================================================
(function () {
    const base = new URL('../', document.currentScript.src);   // raiz do site (pasta adotai/)

    const URL_API = new URL('api/veterinarios.php', base).href;

    const lista = document.getElementById('vet-card-list');
    const aviso = document.getElementById('vet-aviso');
    const busca = document.getElementById('vet-busca');

    let veterinarios = [];

    const moeda = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

    // ---------- Utilitários ----------
    function criar(tag, classe, texto) {
        const el = document.createElement(tag);
        if (classe) el.className = classe;
        if (texto !== undefined) el.textContent = texto;   // textContent: nunca interpreta HTML
        return el;
    }

    // minúsculo e sem acento (para a busca)
    function normalizar(texto) {
        return String(texto || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }

    const soDigitos = (v) => String(v || '').replace(/\D/g, '');

    // 41990000011 -> (41) 99000-0011  (se não tiver 10 ou 11 dígitos, devolve como está)
    function formatarTelefone(valor) {
        const d = soDigitos(valor);
        if (d.length === 11) return `(${d.slice(0, 2)}) ${d.slice(2, 7)}-${d.slice(7)}`;
        if (d.length === 10) return `(${d.slice(0, 2)}) ${d.slice(2, 6)}-${d.slice(6)}`;
        return String(valor || '');
    }

    const telefoneValido = (valor) => [10, 11].includes(soDigitos(valor).length);

    const localDoVet = (v) => [v.cidade, v.uf].filter(Boolean).join(' - ');

    // "servicos" é um TEXT: aceita um serviço por linha ou separados por ponto e vírgula
    function listaDeServicos(v) {
        return String(v.servicos || '').split(/[\n;]+/).map(s => s.trim()).filter(Boolean);
    }

    function mostrarAviso(texto) {
        aviso.textContent = texto;
        aviso.hidden = !texto;
    }

    // ---------- Partes do card ----------
    // Foto do veterinário; sem foto (ou se não carregar), mostra a inicial do nome
    function criarAvatar(v) {
        const inicial = () => {
            const el = criar('div', 'vet-inicial', (v.nome || '?').trim().charAt(0).toUpperCase());
            el.setAttribute('aria-hidden', 'true');
            return el;
        };

        if (!v.foto_url) return inicial();

        const img = criar('img', 'vet-foto');
        img.loading = 'lazy';
        img.alt = `Foto de ${v.nome}`;
        img.src = new URL(v.foto_url, base).href;
        img.addEventListener('error', () => img.replaceWith(inicial()), { once: true });
        return img;
    }

    function linhaContato(rotulo, conteudo) {
        const li = criar('li', 'vet-contato-item');
        li.append(criar('span', 'vet-contato-rotulo', rotulo), conteudo);
        return li;
    }

    function criarLink(texto, href) {
        const a = criar('a', '', texto);
        a.href = href;
        return a;
    }

    // Bloco de contato: telefone (liga direto), e-mail, endereço e botão do WhatsApp
    function criarContato(v) {
        const bloco = criar('div', 'vet-contato');
        const itens = criar('ul', 'vet-contato-lista');

        if (v.telefone) {
            const texto = formatarTelefone(v.telefone);
            itens.append(linhaContato('Telefone',
                telefoneValido(v.telefone) ? criarLink(texto, 'tel:+55' + soDigitos(v.telefone)) : criar('span', '', texto)));
        }
        if (v.email) {
            itens.append(linhaContato('E-mail', criarLink(v.email, 'mailto:' + v.email)));
        }
        if (v.endereco) {
            itens.append(linhaContato('Endereço', criar('span', '', v.endereco)));
        }

        if (!itens.hasChildNodes()) {
            bloco.append(criar('p', 'vet-sem-contato', 'Contato não informado.'));
            return bloco;
        }

        bloco.append(itens);

        // O telefone do cadastro é "Telefone / WhatsApp": oferece a conversa direta
        if (v.telefone && telefoneValido(v.telefone)) {
            const zap = criarLink('Chamar no WhatsApp', 'https://wa.me/55' + soDigitos(v.telefone));
            zap.className = 'vet-btn-whatsapp';
            zap.target = '_blank';
            zap.rel = 'noopener noreferrer';
            bloco.append(zap);
        }

        return bloco;
    }

    // ---------- Card ----------
    function criarCard(v) {
        const card = criar('article', 'vet-card');

        // topo: avatar + nome + CRMV + especialidade
        const topo = criar('div', 'vet-topo');
        const identidade = criar('div', 'vet-identidade');
        identidade.append(criar('h2', 'vet-nome', v.nome));
        if (v.crmv) identidade.append(criar('p', 'vet-crmv', `CRMV: ${v.crmv}`));
        if (v.especialidade) identidade.append(criar('span', 'vet-especialidade', v.especialidade));
        topo.append(criarAvatar(v), identidade);
        card.append(topo);

        // local + valor da consulta
        const local = localDoVet(v);
        if (local) card.append(criar('p', 'vet-info', local));
        if (v.valor_consulta !== null && v.valor_consulta !== undefined) {
            card.append(criar('p', 'vet-info', `Consulta: ${moeda.format(v.valor_consulta)}`));
        }

        // descrição (o CSS corta em 3 linhas)
        if (v.descricao) card.append(criar('p', 'vet-desc', v.descricao));

        // serviços (até 4 etiquetas)
        const servicos = listaDeServicos(v);
        if (servicos.length) {
            const chips = criar('ul', 'vet-servicos');
            servicos.slice(0, 4).forEach(s => chips.append(criar('li', 'vet-servico', s)));
            if (servicos.length > 4) chips.append(criar('li', 'vet-servico', `+${servicos.length - 4}`));
            card.append(chips);
        }

        card.append(criarContato(v));
        return card;
    }

    // ---------- Desenha a lista de acordo com a busca ----------
    function desenhar() {
        const termo = normalizar(busca.value.trim());

        const visiveis = veterinarios.filter(v =>
            !termo || normalizar([v.nome, v.especialidade, v.servicos, v.cidade, v.uf].join(' ')).includes(termo)
        );

        lista.replaceChildren(...visiveis.map(criarCard));

        if (visiveis.length) {
            mostrarAviso('');
        } else {
            mostrarAviso(veterinarios.length
                ? 'Nenhum veterinário encontrado para esta busca.'
                : 'Ainda não há veterinários cadastrados.');
        }
    }

    busca.addEventListener('input', desenhar);

    // ---------- Busca os dados ----------
    mostrarAviso('Carregando veterinários...');

    fetch(URL_API, { cache: 'no-store' })
        .then(r => (r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status))))
        .then(dados => {
            if (!dados.ok || !Array.isArray(dados.veterinarios)) throw new Error(dados.erro || 'Resposta inválida');
            veterinarios = dados.veterinarios;
            desenhar();
        })
        .catch(() => mostrarAviso('Não foi possível carregar os veterinários agora. Tente novamente em instantes.'));
})();
