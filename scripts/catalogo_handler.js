// =====================================================
// Catálogo: busca os animais no banco (api/animais.php),
// gera os cards, aplica os filtros e abre o card em destaque
// (zoom no centro da tela) com a descrição completa e o botão "Quero adotar".
// =====================================================
(function () {
    const base = new URL('../', document.currentScript.src);   // raiz do site (pasta adotai/)

    const URL_API       = new URL('api/animais.php', base).href;
    const URL_INTERESSE = new URL('api/interesse.php', base).href;
    const URL_LOGIN     = new URL('pages/login.html', base).href;
    const FOTO_PADRAO   = new URL('images/franky.jpeg', base).href;   // usada se o animal não tiver foto

    // As fotos no banco estão como 'fotos/cachorros/frank.jpg'.
    // Este é o endereço da pasta onde essas imagens estão. Ajuste se elas ficarem em outro lugar
    // (por exemplo: new URL('images/', base)).
    const PASTA_FOTOS = base;

    // ==================================================
    // FILTROS  (a chave é o "value" do radio no catalogo.html)
    // Para criar um filtro novo: adicione o radio no HTML e uma linha aqui.
    // ==================================================
    const ESPECIES_CAES  = ['cachorro', 'cão', 'cao'];
    const ESPECIES_GATOS = ['gato'];

    const FILTROS = {
        'todos':    ()  => true,
        'Cães':     (a) => ESPECIES_CAES.includes(minusculo(a.especie)),
        'Gatos':    (a) => ESPECIES_GATOS.includes(minusculo(a.especie)),
        // Outros = qualquer espécie que não seja cão nem gato (aves, coelhos, roedores, répteis...)
        'Outros':   (a) => ![...ESPECIES_CAES, ...ESPECIES_GATOS].includes(minusculo(a.especie)),
        'Filhotes': (a) => a.idade_meses !== null && a.idade_meses < 12,
    };

    const lista = document.getElementById('animal-card-list');
    const aviso = document.getElementById('catalogo-aviso');

    let animais = [];

    // ---------- Utilitários ----------
    function minusculo(texto) {
        return String(texto || '').trim().toLowerCase();
    }

    // Remove acentos e espaços para virar nome de classe CSS: "Em processo" -> "em-processo"
    function paraClasse(texto) {
        return minusculo(texto).normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/\s+/g, '-');
    }

    function criar(tag, classe, texto) {
        const el = document.createElement(tag);
        if (classe) el.className = classe;
        if (texto !== undefined) el.textContent = texto;   // textContent: nunca interpreta HTML
        return el;
    }

    function mostrarAviso(texto) {
        aviso.textContent = texto;
        aviso.hidden = !texto;
    }

    function criarFoto(a, classe) {
        const img = criar('img', classe);
        img.loading = 'lazy';
        img.alt = `${a.nome_animal}, ${minusculo(a.especie)} ${minusculo(a.sexo)}`;
        img.src = a.foto_url ? new URL(a.foto_url, PASTA_FOTOS).href : FOTO_PADRAO;
        img.addEventListener('error', () => {
            if (img.src !== FOTO_PADRAO) img.src = FOTO_PADRAO;   // foto não encontrada
        }, { once: true });
        return img;
    }

    function criarTitulo(a, idDoTitulo) {
        const titulo = criar('div', 'animal-title');
        const h2 = criar('h2', '', a.nome_animal);
        if (idDoTitulo) h2.id = idDoTitulo;
        titulo.append(h2, criar('span', 'animal-sex ' + paraClasse(a.sexo), a.sexo));
        return titulo;
    }

    const tipoDoAnimal  = a => [a.especie, a.raca].filter(Boolean).join(' • ');           // "Cachorro • Pug"
    const localDoAnimal = a => [a.cidade, a.uf].filter(Boolean).join(' - ') || 'Local não informado';

    function criarEtiquetaStatus(a) {
        return criar('span', 'animal-status ' + paraClasse(a.status_adocao), a.status_adocao);
    }

    // ---------- Card da lista ----------
    function criarCard(a) {
        const card = criar('article', 'animal-card');
        card.tabIndex = 0;
        card.setAttribute('role', 'button');
        card.setAttribute('aria-label', `Ver detalhes de ${a.nome_animal}`);

        // Descrição (o CSS corta em 3 linhas; a versão completa aparece no card em destaque)
        const descricao = criar('p', 'animal-desc', a.descricao || '');

        const status = criar('p', 'animal-status-linha');
        status.appendChild(criarEtiquetaStatus(a));

        card.append(
            criarFoto(a),
            criarTitulo(a),
            criar('p', '', tipoDoAnimal(a)),
            criar('p', '', localDoAnimal(a)),
            descricao,
            status
        );

        card.addEventListener('click', () => abrirDestaque(a, card));
        card.addEventListener('keydown', e => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                abrirDestaque(a, card);
            }
        });
        return card;
    }

    // ==================================================
    // CARD EM DESTAQUE (zoom no centro da tela)
    // ==================================================
    const overlay = criar('div', 'animal-overlay');
    const destaque = criar('div', 'animal-modal');
    destaque.setAttribute('role', 'dialog');
    destaque.setAttribute('aria-modal', 'true');
    destaque.setAttribute('aria-labelledby', 'animal-modal-titulo');
    destaque.tabIndex = -1;
    overlay.hidden = true;
    overlay.appendChild(destaque);
    document.body.appendChild(overlay);

    let cardOrigem = null;
    let fechando = false;

    const semAnimacao = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Transformação que faz o card em destaque "coincidir" com o card da lista
    function transformacaoDoCard(card) {
        const de = card.getBoundingClientRect();
        const para = destaque.getBoundingClientRect();
        const dx = (de.left + de.width / 2) - (para.left + para.width / 2);
        const dy = (de.top + de.height / 2) - (para.top + para.height / 2);
        const escala = de.width / para.width;
        return `translate(${dx}px, ${dy}px) scale(${escala})`;
    }

    function cancelarAnimacoes() {
        destaque.getAnimations().forEach(x => x.cancel());
        overlay.getAnimations().forEach(x => x.cancel());
    }

    function preencherDestaque(a) {
        const fechar = criar('button', 'animal-modal-fechar', '×');
        fechar.type = 'button';
        fechar.setAttribute('aria-label', 'Fechar');
        fechar.addEventListener('click', fecharDestaque);

        const corpo = criar('div', 'animal-modal-corpo');

        const status = criar('p', 'animal-status-linha');
        status.appendChild(criarEtiquetaStatus(a));

        // Descrição completa
        const descricao = criar('p', 'animal-modal-desc', a.descricao || 'Este animal ainda não tem descrição.');

        // Botão "Quero adotar": só funciona para animais disponíveis
        const statusClasse = paraClasse(a.status_adocao);
        const disponivel = statusClasse === 'disponivel';
        const botao = criar('button', 'btn-adotar',
            disponivel ? 'Quero adotar' : (statusClasse === 'em-processo' ? 'Adoção em andamento' : 'Já adotado'));
        botao.type = 'button';
        botao.disabled = !disponivel;

        const mensagem = criar('p', 'animal-modal-msg');
        mensagem.setAttribute('role', 'status');

        botao.addEventListener('click', () => queroAdotar(a, botao, mensagem));

        corpo.append(
            criarTitulo(a, 'animal-modal-titulo'),
            criar('p', 'animal-modal-info', tipoDoAnimal(a)),
            criar('p', 'animal-modal-info', localDoAnimal(a)),
            status,
            descricao,
            botao,
            mensagem
        );

        destaque.replaceChildren(fechar, criarFoto(a, 'animal-modal-foto'), corpo);
        destaque.scrollTop = 0;
    }

    function abrirDestaque(a, card) {
        cardOrigem = card;
        fechando = false;
        cancelarAnimacoes();
        preencherDestaque(a);

        overlay.hidden = false;
        document.body.classList.add('modal-aberto');

        if (!semAnimacao()) {
            destaque.animate(
                [{ transform: transformacaoDoCard(card), opacity: 0.5 },
                 { transform: 'translate(0, 0) scale(1)', opacity: 1 }],
                { duration: 300, easing: 'cubic-bezier(0.2, 0.8, 0.2, 1)' }
            );
            overlay.animate([{ opacity: 0 }, { opacity: 1 }], { duration: 220 });
        }

        destaque.querySelector('.animal-modal-fechar').focus();
    }

    function fecharDestaque() {
        if (overlay.hidden || fechando) return;
        fechando = true;

        const terminar = () => {
            cancelarAnimacoes();
            overlay.hidden = true;
            destaque.replaceChildren();
            document.body.classList.remove('modal-aberto');
            fechando = false;
            if (cardOrigem && cardOrigem.isConnected) cardOrigem.focus({ preventScroll: true });
        };

        if (semAnimacao() || !cardOrigem || !cardOrigem.isConnected) {
            terminar();
            return;
        }

        const anim = destaque.animate(
            [{ transform: 'translate(0, 0) scale(1)', opacity: 1 },
             { transform: transformacaoDoCard(cardOrigem), opacity: 0 }],
            { duration: 220, easing: 'ease-in', fill: 'forwards' }
        );
        overlay.animate([{ opacity: 1 }, { opacity: 0 }], { duration: 220, fill: 'forwards' });
        anim.addEventListener('finish', terminar);
    }

    // Clicar fora do card (no fundo escuro) fecha
    overlay.addEventListener('click', e => {
        if (e.target === overlay) fecharDestaque();
    });

    document.addEventListener('keydown', e => {
        if (overlay.hidden) return;

        if (e.key === 'Escape') {
            fecharDestaque();
            return;
        }

        // mantém o Tab dentro do card em destaque
        if (e.key === 'Tab') {
            const focaveis = [...destaque.querySelectorAll('button:not([disabled])')];
            if (!focaveis.length) {
                e.preventDefault();
                destaque.focus();
                return;
            }
            const primeiro = focaveis[0];
            const ultimo = focaveis[focaveis.length - 1];
            if (e.shiftKey && document.activeElement === primeiro) {
                e.preventDefault();
                ultimo.focus();
            } else if (!e.shiftKey && document.activeElement === ultimo) {
                e.preventDefault();
                primeiro.focus();
            }
        }
    });

    // ---------- Botão "Quero adotar" ----------
    // Registra o interesse na lista do usuário (api/interesse.php).
    // Se não houver ninguém logado, leva para a tela de login.
    async function queroAdotar(a, botao, mensagem) {
        botao.disabled = true;
        mensagem.textContent = '';

        try {
            const resposta = await fetch(URL_INTERESSE, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ id_animal: a.id_animal }),
            });
            const dados = await resposta.json().catch(() => null);

            if (dados && dados.ok) {
                // avisa o header para colocar o animal na lista de interesses (header_auth.js)
                window.dispatchEvent(new CustomEvent('interesse:registrado', {
                    detail: {
                        id_animal: a.id_animal,
                        nome_animal: a.nome_animal,
                        especie: a.especie,
                        status_adocao: a.status_adocao,
                        foto_url: a.foto_url,
                    },
                }));

                botao.textContent = 'Interesse registrado ✓';
                mensagem.textContent = dados.ja_registrado
                    ? 'Você já tinha demonstrado interesse neste animal.'
                    : 'Pronto! Seu interesse foi registrado.';
                return;   // botão continua desativado
            }

            if (dados && dados.erro === 'nao_logado') {
                mensagem.textContent = 'Entre na sua conta para adotar. Levando você ao login...';
                setTimeout(() => { window.location.href = URL_LOGIN; }, 1500);
                return;
            }

            mensagem.textContent = (dados && dados.mensagem) || 'Não foi possível registrar agora. Tente novamente.';
        } catch (e) {
            mensagem.textContent = 'Não foi possível registrar agora. Tente novamente.';
        }

        botao.disabled = false;
    }

    // ---------- Desenha a lista de acordo com o filtro escolhido ----------
    function desenhar() {
        const escolhido = document.querySelector('input[name="animals"]:checked');
        const filtro = FILTROS[escolhido ? escolhido.value : 'todos'] || FILTROS.todos;

        const visiveis = animais.filter(filtro);
        lista.replaceChildren(...visiveis.map(criarCard));

        mostrarAviso(visiveis.length ? '' : 'Nenhum animal encontrado nesta categoria.');
    }

    document.querySelectorAll('input[name="animals"]').forEach(radio =>
        radio.addEventListener('change', desenhar)
    );

    // ---------- Busca os dados ----------
    mostrarAviso('Carregando animais...');

    fetch(URL_API, { cache: 'no-store' })
        .then(r => (r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status))))
        .then(dados => {
            if (!dados.ok) throw new Error(dados.erro || 'Resposta inválida');
            animais = dados.animais;
            desenhar();
        })
        .catch(() => mostrarAviso('Não foi possível carregar os animais agora. Tente novamente em instantes.'));
})();
