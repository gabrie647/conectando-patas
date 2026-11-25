// Favoritos salvos na conta. Usado pela home, pela página de animais e por Minha conta.
// Visitante que clica no coração é levado para o login e volta para a página depois.
window.Favoritos = (function () {
    const API = '/backend/favoritos.php';
    let ids = new Set();
    let logado = false;

    const pronto = fetch(API + '?acao=ids', { cache: 'no-store', credentials: 'same-origin' })
        .then((r) => (r.ok ? r.json() : null))
        .then((dados) => {
            if (dados && dados.sucesso) {
                logado = true;
                ids = new Set(dados.ids.map(Number));
            }
        })
        .catch(() => {});

    function aplicarVisual(botao, ativo) {
        botao.classList.toggle('ativo', ativo);
        const icone = botao.querySelector('i');
        if (icone) {
            icone.classList.toggle('fa-regular', !ativo);
            icone.classList.toggle('fa-solid', ativo);
        }
        botao.setAttribute('aria-pressed', String(ativo));
    }

    async function alternar(id, favoritar) {
        const dados = new FormData();
        dados.append('acao', 'alternar');
        dados.append('animal', String(id));
        dados.append('favoritar', favoritar ? '1' : '0');
        try {
            const resposta = await fetch(API, { method: 'POST', body: dados, credentials: 'same-origin' });
            if (resposta.status === 401) return { precisaLogin: true };
            const corpo = await resposta.json().catch(() => ({}));
            if (!resposta.ok || !corpo.sucesso) {
                return { ok: false, mensagem: corpo.mensagem || 'Não foi possível atualizar seus favoritos.' };
            }
            if (favoritar) ids.add(Number(id));
            else ids.delete(Number(id));
            return { ok: true };
        } catch (erro) {
            return { ok: false, mensagem: 'Erro de conexão. Tente novamente.' };
        }
    }

    // Coloca o coração no estado certo (quando os ids chegarem) e liga o clique.
    function ligarBotao(botao, id) {
        pronto.then(() => {
            if (ids.has(Number(id))) aplicarVisual(botao, true);
        });

        botao.addEventListener('click', async (evento) => {
            evento.preventDefault();
            evento.stopPropagation();
            await pronto;
            if (!logado) {
                window.location.href = '/login/index.html?next=' + encodeURIComponent(location.pathname);
                return;
            }
            const querFavoritar = !botao.classList.contains('ativo');
            const resultado = await alternar(id, querFavoritar);
            if (resultado.precisaLogin) {
                window.location.href = '/login/index.html?next=' + encodeURIComponent(location.pathname);
                return;
            }
            if (!resultado.ok) {
                alert(resultado.mensagem);
                return;
            }
            aplicarVisual(botao, querFavoritar);
        });
    }

    return { pronto, ligarBotao, alternar, aplicarVisual };
})();
