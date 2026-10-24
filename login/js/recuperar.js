// Serve as páginas esqueci.html (pedir o link) e redefinir.html (criar nova senha).
const mensagemDiv = document.getElementById('mensagem');

function mostrarMensagem(texto, tipo) {
    mensagemDiv.textContent = texto;
    mensagemDiv.className = 'mensagem ' + tipo;
    mensagemDiv.style.display = 'block';
}

async function enviar(url, dados) {
    const resposta = await fetch(url, { method: 'POST', body: dados, credentials: 'same-origin' });
    let resultado;
    try { resultado = await resposta.json(); } catch (e) { throw new Error('O servidor retornou uma resposta inválida.'); }
    if (!resposta.ok || !resultado.sucesso) throw new Error(resultado.mensagem || 'Falha HTTP ' + resposta.status + '.');
    return resultado;
}

const formEsqueci = document.getElementById('formEsqueci');
if (formEsqueci) {
    const botao = document.getElementById('btnEnviar');
    formEsqueci.addEventListener('submit', async (evento) => {
        evento.preventDefault();
        mensagemDiv.style.display = 'none';
        const email = document.getElementById('email').value.trim();
        const confirmarEmail = document.getElementById('confirmarEmail').value.trim();
        if (email.toLowerCase() !== confirmarEmail.toLowerCase()) {
            mostrarMensagem('Os endereços de e-mail não coincidem. Confira os dois campos.', 'erro');
            return;
        }
        if (!document.getElementById('confirmacaoEmail').checked) {
            mostrarMensagem('Confirme que você tem acesso ao endereço de e-mail informado.', 'erro');
            return;
        }
        botao.disabled = true;
        botao.textContent = 'Enviando...';
        try {
            const dados = new FormData();
            dados.append('email', email);
            const resultado = await enviar('/backend/esqueci-senha.php', dados);
            mostrarMensagem(resultado.mensagem, 'sucesso');
        } catch (erro) {
            mostrarMensagem(erro.message || 'Não foi possível conectar ao servidor.', 'erro');
        } finally {
            botao.disabled = false;
            botao.textContent = 'Enviar link de recuperação';
        }
    });
}

const formRedefinir = document.getElementById('formRedefinir');
if (formRedefinir) {
    const botao = document.getElementById('btnSalvar');
    // O token vem depois do # no link do e-mail, assim não aparece em logs do servidor.
    const token = new URLSearchParams(window.location.hash.slice(1)).get('token') || '';
    if (!/^[a-f0-9]{64}$/.test(token)) {
        botao.disabled = true;
        mostrarMensagem('Link inválido. Peça um novo link na tela "Esqueci minha senha".', 'erro');
    }

    formRedefinir.addEventListener('submit', async (evento) => {
        evento.preventDefault();
        mensagemDiv.style.display = 'none';
        const senha = document.getElementById('senha').value;
        const confirmar = document.getElementById('confirmarSenha').value;
        if (senha.length < 8) { mostrarMensagem('A senha precisa ter pelo menos 8 caracteres.', 'erro'); return; }
        if (senha !== confirmar) { mostrarMensagem('As senhas não são iguais.', 'erro'); return; }

        botao.disabled = true;
        botao.textContent = 'Salvando...';
        try {
            const dados = new FormData();
            dados.append('token', token);
            dados.append('senha', senha);
            dados.append('confirmarSenha', confirmar);
            const resultado = await enviar('/backend/redefinir-senha.php', dados);
            mostrarMensagem(resultado.mensagem, 'sucesso');
            history.replaceState(null, '', window.location.pathname);
            setTimeout(() => { window.location.href = './index.html'; }, 1800);
        } catch (erro) {
            mostrarMensagem(erro.message || 'Não foi possível conectar ao servidor.', 'erro');
            botao.disabled = false;
            botao.textContent = 'Salvar nova senha';
        }
    });
}
