// =====================================================
// Cadastro: muda o formulário conforme o tipo de usuário,
// aplica máscaras e valida CPF / CNPJ / senhas.
// (Estas validações são só conveniência: o PHP precisa validar de novo.)
// =====================================================

const form = document.getElementById('cadastro-form');

const blocoProtetor    = document.getElementById('bloco-protetor');
const blocoVeterinario = document.getElementById('bloco-veterinario');
const campoResponsavel = document.getElementById('campo-responsavel');
const inputResponsavel = document.getElementById('responsavel');

const labelNome = document.getElementById('label-nome');
const inputNome = document.getElementById('nome');
const labelDoc  = document.getElementById('label-documento');
const inputDoc  = document.getElementById('documento');

const inputCrmv = document.getElementById('crmv');
const inputTel  = document.getElementById('telefone');
const senha     = document.getElementById('senha');
const confirmar = document.getElementById('confirmar_senha');

const soDigitos = v => v.replace(/\D/g, '');

// ---------- Máscaras ----------
function mascaraCPF(v) {
    return soDigitos(v).slice(0, 11)
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
}

function mascaraCNPJ(v) {
    return soDigitos(v).slice(0, 14)
        .replace(/^(\d{2})(\d)/, '$1.$2')
        .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
        .replace(/\.(\d{3})(\d)/, '.$1/$2')
        .replace(/(\d{4})(\d)/, '$1-$2');
}

function mascaraTelefone(v) {
    v = soDigitos(v).slice(0, 11);
    if (v.length > 10) return v.replace(/(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3');
    if (v.length > 6)  return v.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
    if (v.length > 2)  return v.replace(/(\d{2})(\d{0,5})/, '($1) $2');
    return v ? `(${v}` : '';
}

// ---------- Validação de CPF e CNPJ (dígitos verificadores) ----------
function cpfValido(valor) {
    const c = soDigitos(valor);
    if (c.length !== 11 || /^(\d)\1+$/.test(c)) return false;

    for (let t = 9; t < 11; t++) {
        let soma = 0;
        for (let i = 0; i < t; i++) soma += Number(c[i]) * (t + 1 - i);
        const digito = ((soma * 10) % 11) % 10;
        if (digito !== Number(c[t])) return false;
    }
    return true;
}

function cnpjValido(valor) {
    const c = soDigitos(valor);
    if (c.length !== 14 || /^(\d)\1+$/.test(c)) return false;

    const calcular = (qtd) => {
        const pesos = qtd === 12
            ? [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]
            : [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        let soma = 0;
        for (let i = 0; i < qtd; i++) soma += Number(c[i]) * pesos[i];
        const resto = soma % 11;
        return resto < 2 ? 0 : 11 - resto;
    };

    return calcular(12) === Number(c[12]) && calcular(13) === Number(c[13]);
}

// ---------- Tipo de usuário -> o que aparece no formulário ----------
function mostrar(elemento, visivel) {
    elemento.hidden = !visivel;
    // fieldset desabilitado = não é validado e não é enviado ao servidor
    if (elemento.tagName === 'FIELDSET') elemento.disabled = !visivel;
}

function atualizarFormulario() {
    const tipo = form.elements['tipo_usuario'].value;
    const categoria = form.elements['categoria_protetor'].value;

    const ehProtetor = tipo === 'protetor';
    const ehOng = ehProtetor && categoria === 'ong';
    const ehVeterinario = tipo === 'veterinario';

    mostrar(blocoProtetor, ehProtetor);
    mostrar(blocoVeterinario, ehVeterinario);

    // Responsável só existe para ONG
    campoResponsavel.hidden = !ehOng;
    inputResponsavel.disabled = !ehOng;

    // Textos que mudam conforme ONG x pessoa
    labelNome.textContent = ehOng ? 'Nome da ONG' : 'Nome completo';
    inputNome.placeholder = ehOng ? 'Nome da organização' : 'Seu nome completo';

    labelDoc.textContent = ehOng ? 'CNPJ' : 'CPF';
    inputDoc.placeholder = ehOng ? '00.000.000/0000-00' : '000.000.000-00';
    inputDoc.maxLength = ehOng ? 18 : 14;

    // Reaplica a máscara certa ao trocar entre CPF e CNPJ
    inputDoc.value = ehOng ? mascaraCNPJ(inputDoc.value) : mascaraCPF(inputDoc.value);
    validarDocumento();
}

function validarDocumento() {
    const ehOng = form.elements['categoria_protetor'].value === 'ong';
    const valido = ehOng ? cnpjValido(inputDoc.value) : cpfValido(inputDoc.value);
    const msg = ehOng ? 'CNPJ inválido.' : 'CPF inválido.';
    inputDoc.setCustomValidity(valido ? '' : msg);
}

function conferirSenhas() {
    const diferente = confirmar.value !== '' && confirmar.value !== senha.value;
    confirmar.setCustomValidity(diferente ? 'As senhas não coincidem.' : '');
}

// ---------- Eventos ----------
form.querySelectorAll('input[name="tipo_usuario"], input[name="categoria_protetor"]')
    .forEach(radio => radio.addEventListener('change', atualizarFormulario));

inputDoc.addEventListener('input', () => {
    const ehOng = form.elements['categoria_protetor'].value === 'ong';
    inputDoc.value = ehOng ? mascaraCNPJ(inputDoc.value) : mascaraCPF(inputDoc.value);
    validarDocumento();
});

inputTel.addEventListener('input', () => {
    inputTel.value = mascaraTelefone(inputTel.value);
});

inputCrmv.addEventListener('input', () => {
    inputCrmv.value = soDigitos(inputCrmv.value);
});

senha.addEventListener('input', conferirSenhas);
confirmar.addEventListener('input', conferirSenhas);

// Botões "Mostrar / Ocultar" das duas senhas
document.querySelectorAll('.toggle-senha').forEach(botao => {
    botao.addEventListener('click', () => {
        const campo = document.getElementById(botao.dataset.alvo);
        const visivel = campo.type === 'text';
        campo.type = visivel ? 'password' : 'text';
        botao.textContent = visivel ? 'Mostrar' : 'Ocultar';
        botao.setAttribute('aria-label', visivel ? 'Mostrar senha' : 'Ocultar senha');
        botao.setAttribute('aria-pressed', String(!visivel));
    });
});

// Mensagens vindas do PHP por redirecionamento (?erro=...)
const mensagensErro = {
    email_existente: 'Já existe uma conta com este e-mail.',
    documento_existente: 'Já existe uma conta com este CPF/CNPJ.',
    dados_invalidos: 'Confira os dados informados e tente novamente.',
};

const erro = new URLSearchParams(location.search).get('erro');
if (erro) {
    const caixa = document.getElementById('cadastro-erro');
    caixa.textContent = mensagensErro[erro] || 'Não foi possível concluir o cadastro. Tente novamente.';
    caixa.hidden = false;
}

// Estado inicial
atualizarFormulario();
