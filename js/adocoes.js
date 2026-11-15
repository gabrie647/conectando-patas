// Minha conta: solicitações de adoção (feitas por mim e recebidas para decidir).
// Todo texto vindo do servidor entra na página com textContent (nunca innerHTML).
(function () {
    const API = '/backend/adocao.php';
    const $ = (id) => document.getElementById(id);
    const aviso = $('aviso');

    const ROTULOS = {
        pendente: 'Em análise',
        aprovada: 'Aprovada',
        recusada: 'Recusada',
        cancelada: 'Cancelada',
        encerrada: 'Encerrada'
    };

    function criar(tag, classe, texto) {
        const el = document.createElement(tag);
        if (classe) el.className = classe;
        if (texto !== undefined) el.textContent = texto;
        return el;
    }

    function botao(texto, classe, aoClicar) {
        const b = criar('button', classe, texto);
        b.type = 'button';
        b.addEventListener('click', aoClicar);
        return b;
    }

    function formatarData(texto) {
        const d = new Date(String(texto).replace(' ', 'T'));
        return isNaN(d) ? '' : d.toLocaleDateString('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' });
    }

    function mostrarAviso(texto, ok) {
        aviso.textContent = texto;
        aviso.classList.toggle('ok', !!ok);
        aviso.hidden = false;
        aviso.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    async function pedir(acao, dadosExtra) {
        let resposta;
        if (dadosExtra) {
            const dados = new FormData();
            dados.append('acao', acao);
            Object.keys(dadosExtra).forEach((k) => dados.append(k, dadosExtra[k]));
            resposta = await fetch(API, { method: 'POST', body: dados, credentials: 'same-origin' });
        } else {
            resposta = await fetch(API + '?acao=' + encodeURIComponent(acao), { credentials: 'same-origin' });
        }
        const corpo = await resposta.json().catch(() => ({}));
        if (!resposta.ok || !corpo.sucesso) {
            throw new Error(corpo.mensagem || 'Não foi possível carregar agora.');
        }
        return corpo;
    }

    function cartaoMinha(s) {
        const cartao = criar('div', 'item-painel');
        cartao.appendChild(criar('h3', '', s.descricao));
        cartao.appendChild(criar('p', 'info', 'Status: ' + (ROTULOS[s.status] || s.status) + ' · enviada em ' + formatarData(s.criado_em)));
        cartao.appendChild(criar('p', '', s.mensagem));
        if (s.status === 'aprovada' && s.contato) {
            cartao.appendChild(criar('p', 'info', 'Fale com o responsável: ' + s.contato.nome + ' — ' + s.contato.email));
        }
        if (s.status === 'pendente') {
            cartao.appendChild(botao('Cancelar solicitação', 'btn btn-contorno', () => cancelar(s.id)));
        }
        return cartao;
    }

    function cartaoRecebida(s) {
        const cartao = criar('div', 'item-painel');
        cartao.appendChild(criar('h3', '', s.descricao));
        cartao.appendChild(criar('p', 'info', 'Pedido de: ' + s.solicitante.nome + ' · ' + s.solicitante.email));
        cartao.appendChild(criar('p', 'info', 'Enviado em ' + formatarData(s.criado_em) + ' · ' + (ROTULOS[s.status] || s.status)));
        cartao.appendChild(criar('p', '', s.mensagem));
        if (s.status === 'pendente') {
            const acoes = criar('div', 'acoes-painel');
            acoes.appendChild(botao('Aprovar adoção', 'btn btn-laranja', () => decidir(s.id, 'aprovar')));
            acoes.appendChild(botao('Recusar', 'btn btn-contorno', () => decidir(s.id, 'recusar')));
            cartao.appendChild(acoes);
        }
        return cartao;
    }

    async function carregarMinhas() {
        const lista = $('listaSolicitacoes');
        const vazio = $('semSolicitacoes');
        try {
            const dados = await pedir('minhas');
            lista.textContent = '';
            dados.itens.forEach((s) => lista.appendChild(cartaoMinha(s)));
            vazio.hidden = dados.itens.length > 0;
        } catch (erro) {
            lista.textContent = '';
            vazio.hidden = false;
            vazio.textContent = erro.message;
        }
    }

    async function carregarRecebidas() {
        const lista = $('listaRecebidas');
        const vazio = $('semRecebidas');
        try {
            const dados = await pedir('recebidas');
            lista.textContent = '';
            dados.itens.forEach((s) => lista.appendChild(cartaoRecebida(s)));
            vazio.hidden = dados.itens.length > 0;
        } catch (erro) {
            lista.textContent = '';
            vazio.hidden = false;
            vazio.textContent = erro.message;
        }
    }

    async function decidir(id, decisao) {
        const pergunta = decisao === 'aprovar'
            ? 'Confirmar a adoção? O animal será marcado como adotado e sairá da lista pública.'
            : 'Recusar esta solicitação?';
        if (!window.confirm(pergunta)) return;
        try {
            const dados = await pedir('decidir', { solicitacao: id, decisao: decisao });
            mostrarAviso(dados.mensagem, true);
            await Promise.all([carregarRecebidas(), carregarMinhas()]);
        } catch (erro) {
            mostrarAviso(erro.message, false);
        }
    }

    async function cancelar(id) {
        if (!window.confirm('Cancelar esta solicitação?')) return;
        try {
            const dados = await pedir('cancelar', { solicitacao: id });
            mostrarAviso(dados.mensagem, true);
            await carregarMinhas();
        } catch (erro) {
            mostrarAviso(erro.message, false);
        }
    }

    carregarMinhas();
    carregarRecebidas();
})();
