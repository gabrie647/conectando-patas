const formLogin = document.getElementById('formLogin');
const emailInput = document.getElementById('email');
const senhaInput = document.getElementById('senha');
const lembrarCheck = document.getElementById('lembrar');
const btnEntrar = document.getElementById('btnEntrar');
const btnOlhoSenha = document.getElementById('btnOlhoSenha');
const mensagemDiv = document.getElementById('mensagem');

window.addEventListener('DOMContentLoaded', () => {
    const emailSalvo = localStorage.getItem('emailLembrado');
    if (emailSalvo) {
        emailInput.value = emailSalvo;
        lembrarCheck.checked = true;
    }
});

btnOlhoSenha?.addEventListener('click', () => {
    const mostrar = senhaInput.type === 'password';
    senhaInput.type = mostrar ? 'text' : 'password';
    btnOlhoSenha.textContent = mostrar ? '🙈' : '👁️';
    btnOlhoSenha.setAttribute('aria-label', mostrar ? 'Ocultar senha' : 'Mostrar senha');
});

formLogin.addEventListener('submit', async (event) => {
    event.preventDefault();
    esconderMensagem();

    const email = emailInput.value.trim();
    const senha = senhaInput.value;
    if (!validarEmail(email)) {
        mostrarMensagem('Digite um e-mail válido.', 'erro');
        emailInput.focus();
        return;
    }
    if (!senha) {
        mostrarMensagem('Digite sua senha.', 'erro');
        senhaInput.focus();
        return;
    }

    btnEntrar.disabled = true;
    btnEntrar.textContent = 'Verificando...';

    try {
        const dados = new FormData();
        dados.append('email', email);
        dados.append('senha', senha);

        const resposta = await fetch('../backend/login.php', {
            method: 'POST',
            body: dados,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        });
        const resultado = await resposta.json();

        if (!resposta.ok || !resultado.sucesso) {
            throw new Error(resultado.mensagem || 'E-mail ou senha incorretos.');
        }

        if (lembrarCheck.checked) localStorage.setItem('emailLembrado', email);
        else localStorage.removeItem('emailLembrado');

        mostrarMensagem('Login realizado com sucesso!', 'sucesso');
        const proximo = new URLSearchParams(window.location.search).get('next');
        const destino = proximo && /^\/(?!\/)[\w\-./]*$/.test(proximo) ? proximo : '../index.html';
        window.setTimeout(() => { window.location.href = destino; }, 700);
    } catch (erro) {
        mostrarMensagem(erro.message || 'Não foi possível conectar ao servidor.', 'erro');
        btnEntrar.disabled = false;
        btnEntrar.textContent = 'Entrar';
    }
});

function validarEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}
function mostrarMensagem(texto, tipo) {
    mensagemDiv.textContent = texto;
    mensagemDiv.className = `mensagem ${tipo}`;
    mensagemDiv.style.display = 'block';
}
function esconderMensagem() {
    mensagemDiv.style.display = 'none';
    mensagemDiv.textContent = '';
}
