        const campoSenha = document.getElementById('senha');
        const botaoSenha = document.getElementById('toggle-senha');

        botaoSenha.addEventListener('click', () => {
            const visivel = campoSenha.type === 'text';
            campoSenha.type = visivel ? 'password' : 'text';
            botaoSenha.textContent = visivel ? 'Mostrar' : 'Ocultar';
            botaoSenha.setAttribute('aria-label', visivel ? 'Mostrar senha' : 'Ocultar senha');
            botaoSenha.setAttribute('aria-pressed', String(!visivel));
        });

        // Se o PHP redirecionar de volta com ?erro=1, mostra a mensagem de erro
        if (new URLSearchParams(location.search).get('erro') === '1') {
            document.getElementById('login-erro').hidden = false;
        }