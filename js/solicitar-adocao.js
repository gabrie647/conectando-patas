// Página "Quero adotar": mostra o animal escolhido e envia a solicitação para /backend/adocao.php.
// Todo texto vindo do servidor entra na página com textContent (nunca innerHTML).
(function () {
    const $ = (id) => document.getElementById(id);
    const aviso = $('aviso');
    const resumo = $('resumoPet');
    const form = $('formSolicitacao');
    const campo = $('mensagem');
    const btn = $('btnEnviar');
    const idAnimal = new URLSearchParams(window.location.search).get('animal');

    function criar(tag, classe, texto) {
        const el = document.createElement(tag);
        if (classe) el.className = classe;
        if (texto !== undefined) el.textContent = texto;
        return el;
    }

    function mostrarAviso(texto, ok) {
        aviso.textContent = texto;
        aviso.classList.toggle('ok', !!ok);
        aviso.hidden = false;
        aviso.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function desabilitarFormulario() {
        form.querySelectorAll('textarea, button').forEach((el) => { el.disabled = true; });
    }

    async function carregarAnimal() {
        if (!idAnimal || !/^\d+$/.test(idAnimal)) {
            resumo.textContent = '';
            resumo.appendChild(criar('p', '', 'Escolha um animal na página de animais para pedir a adoção.'));
            desabilitarFormulario();
            return;
        }
        try {
            const resposta = await fetch('/backend/animais.php?id=' + encodeURIComponent(idAnimal), { credentials: 'same-origin' });
            const dados = await resposta.json().catch(() => ({}));
            if (!resposta.ok || !dados.sucesso) {
                resumo.textContent = '';
                resumo.appendChild(criar('p', '', dados.mensagem || 'Este animal não está disponível para adoção.'));
                desabilitarFormulario();
                return;
            }
            const animal = dados.animal;
            resumo.textContent = '';
            const foto = criar('img');
            foto.src = animal.foto;
            foto.alt = 'Foto do animal';
            const texto = criar('div');
            texto.appendChild(criar('p', '', animal.descricao));
            texto.appendChild(criar('p', 'info', animal.localizacao ? 'Local: ' + animal.localizacao : ''));
            resumo.appendChild(foto);
            resumo.appendChild(texto);
        } catch (erro) {
            resumo.textContent = '';
            resumo.appendChild(criar('p', '', 'Não foi possível carregar o animal agora.'));
            desabilitarFormulario();
        }
    }

    form.addEventListener('submit', async function (evento) {
        evento.preventDefault();
        const mensagem = campo.value.trim();
        if (mensagem.length < 10) {
            mostrarAviso('Escreva pelo menos 10 caracteres na mensagem.', false);
            campo.focus();
            return;
        }

        const dados = new FormData();
        dados.append('acao', 'solicitar');
        dados.append('animal', idAnimal);
        dados.append('mensagem', mensagem);

        btn.disabled = true;
        try {
            const resposta = await fetch('/backend/adocao.php', { method: 'POST', body: dados, credentials: 'same-origin' });
            const resultado = await resposta.json().catch(() => ({}));
            if (!resposta.ok || !resultado.sucesso) {
                mostrarAviso(resultado.mensagem || 'Não foi possível enviar a solicitação.', false);
                btn.disabled = false;
                return;
            }
            mostrarAviso(resultado.mensagem, true);
            desabilitarFormulario();
            setTimeout(() => { window.location.href = './conta.html'; }, 2500);
        } catch (erro) {
            mostrarAviso('Erro de conexão. Tente novamente.', false);
            btn.disabled = false;
        }
    });

    carregarAnimal();
})();
