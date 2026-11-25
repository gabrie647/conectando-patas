const conteudo = document.getElementById('conteudo');
const aviso = document.getElementById('aviso');
const resumo = document.getElementById('resumo');
const lista = document.getElementById('lista');
const abas = document.getElementById('abas');
const btnMais = document.getElementById('btnMais');

const ASSUNTOS = {
    adocao: 'Quero adotar um animal',
    resgate: 'Quero informar um animal',
    voluntario: 'Quero ser voluntário',
    duvida: 'Dúvida',
    sugestao: 'Sugestão',
    outro: 'Outro assunto'
};

let abaAtual = 'mensagens';
let pagina = 1;

function criar(tag, classe, texto) {
    const e = document.createElement(tag);
    if (classe) e.className = classe;
    if (texto !== undefined) e.textContent = texto;
    return e;
}

function formatarData(texto) {
    const d = new Date(String(texto).replace(' ', 'T') + 'Z');
    return isNaN(d) ? '' : d.toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
}

function mostrarAviso(texto, ok) {
    aviso.textContent = texto;
    aviso.className = 'aviso-painel' + (ok ? ' ok' : '');
    aviso.hidden = false;
}

async function api(acao, parametros, enviar) {
    let resposta;
    if (enviar) {
        const dados = new FormData();
        dados.append('acao', acao);
        Object.entries(parametros || {}).forEach(([k, v]) => dados.append(k, v));
        resposta = await fetch('/backend/admin.php', {
            method: 'POST',
            body: dados,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'fetch' }
        });
    } else {
        const q = new URLSearchParams({ acao, ...(parametros || {}) });
        resposta = await fetch('/backend/admin.php?' + q.toString(), { credentials: 'same-origin' });
    }

    if (resposta.status === 401) {
        window.location.href = '../login/index.html?next=/admin/index.html';
        throw new Error('Login necessário.');
    }
    let dados;
    try { dados = await resposta.json(); } catch (e) { throw new Error('Resposta inválida do servidor.'); }
    if (!resposta.ok || !dados.sucesso) {
        const erro = new Error(dados.mensagem || 'Falha HTTP ' + resposta.status + '.');
        erro.status = resposta.status;
        throw erro;
    }
    return dados;
}

function botao(texto, classe, aoClicar) {
    const b = criar('button', 'btn btn-pequeno ' + classe, texto);
    b.type = 'button';
    b.addEventListener('click', async () => {
        b.disabled = true;
        try { await aoClicar(); } catch (erro) { mostrarAviso(erro.message); b.disabled = false; }
    });
    return b;
}

async function recarregar() {
    await Promise.all([carregarResumo(), carregarLista(true)]);
}

async function carregarResumo() {
    const { resumo: r } = await api('resumo');
    resumo.replaceChildren();
    [
        [r.mensagens_novas, 'mensagens novas'],
        [r.mensagens_total, 'mensagens no total'],
        [r.pets_pendentes, 'pets aguardando análise'],
        [r.pets_aprovados, 'pets publicados']
    ].forEach(([numero, rotulo]) => {
        const c = criar('div', 'cartao');
        c.appendChild(criar('strong', '', String(numero)));
        c.appendChild(criar('span', '', rotulo));
        resumo.appendChild(c);
    });
}

function itemMensagem(m) {
    const item = criar('article', 'item-painel' + (m.lida ? '' : ' nao-lida'));
    const corpo = criar('div');

    const titulo = criar('h3', '', m.nome);
    if (!m.lida) titulo.appendChild(criar('span', 'selo novo', 'NOVA'));
    titulo.appendChild(criar('span', 'selo', ASSUNTOS[m.assunto] || m.assunto));
    corpo.appendChild(titulo);
    corpo.appendChild(criar('p', 'info', m.email + ' · ' + formatarData(m.criado_em)));
    corpo.appendChild(criar('p', 'texto', m.mensagem));

    const acoes = criar('div', 'acoes');
    const responder = criar('a', 'btn btn-laranja btn-pequeno', 'Responder por e-mail');
    responder.href = 'mailto:' + encodeURIComponent(m.email).replace('%40', '@') + '?subject=' + encodeURIComponent('Re: ' + (ASSUNTOS[m.assunto] || 'Conectando Patas'));
    acoes.appendChild(responder);
    acoes.appendChild(botao(m.lida ? 'Marcar como não lida' : 'Marcar como lida', 'btn-contorno', async () => {
        await api('mensagem_lida', { id: m.id, lida: m.lida ? '0' : '1' }, true);
        await recarregar();
    }));
    acoes.appendChild(botao('Excluir', 'btn-perigo', async () => {
        if (!confirm('Excluir esta mensagem de ' + m.nome + '?')) throw new Error('Exclusão cancelada.');
        await api('mensagem_excluir', { id: m.id }, true);
        await recarregar();
    }));
    corpo.appendChild(acoes);
    item.appendChild(corpo);
    return item;
}

function itemPet(p) {
    const item = criar('article', 'item-painel');
    if (p.tem_foto) {
        const img = criar('img', 'miniatura');
        img.src = '/backend/foto.php?id=' + encodeURIComponent(p.id);
        img.alt = 'Foto enviada pelo responsável';
        img.loading = 'lazy';
        item.appendChild(img);
    }

    const corpo = criar('div');
    const titulo = criar('h3', '', 'Pet #' + p.id);
    titulo.appendChild(criar('span', 'selo ' + p.status, p.status.toUpperCase()));
    corpo.appendChild(titulo);
    corpo.appendChild(criar('p', 'info', p.nome_responsavel + ' · ' + p.email + ' · ' + formatarData(p.criado_em)));
    corpo.appendChild(criar('p', 'texto', p.descricao));
    corpo.appendChild(criar('p', 'info', 'Local: ' + p.localizacao));

    const acoes = criar('div', 'acoes');
    const mudar = async (status) => { await api('pet_status', { id: p.id, status }, true); await recarregar(); };
    if (p.status !== 'aprovado') acoes.appendChild(botao('Aprovar e publicar', 'btn-ok', () => mudar('aprovado')));
    if (p.status !== 'recusado') acoes.appendChild(botao('Recusar', 'btn-contorno', () => mudar('recusado')));
    if (p.status === 'aprovado') acoes.appendChild(botao('Despublicar', 'btn-contorno', () => mudar('pendente')));
    acoes.appendChild(botao('Excluir', 'btn-perigo', async () => {
        if (!confirm('Excluir o registro #' + p.id + ' definitivamente?')) throw new Error('Exclusão cancelada.');
        await api('pet_excluir', { id: p.id }, true);
        await recarregar();
    }));
    corpo.appendChild(acoes);
    item.appendChild(corpo);
    return item;
}

async function carregarLista(reiniciar) {
    if (reiniciar) {
        pagina = 1;
        lista.replaceChildren();
        btnMais.hidden = true;
    }
    const dados = abaAtual === 'mensagens'
        ? await api('mensagens', { pagina })
        : await api('pets', { status: abaAtual, pagina });

    dados.itens.forEach(i => lista.appendChild(abaAtual === 'mensagens' ? itemMensagem(i) : itemPet(i)));
    if (!lista.children.length) {
        const vazio = criar('div', 'estado-vazio');
        vazio.appendChild(criar('p', '', abaAtual === 'mensagens' ? 'Nenhuma mensagem por enquanto.' : 'Nenhum registro nesta lista.'));
        lista.appendChild(vazio);
    }
    btnMais.hidden = !dados.tem_mais;
}

abas.addEventListener('click', async (evento) => {
    const alvo = evento.target.closest('button[data-aba]');
    if (!alvo) return;
    abaAtual = alvo.dataset.aba;
    abas.querySelectorAll('button').forEach(b => b.setAttribute('aria-selected', String(b === alvo)));
    aviso.hidden = true;
    try { await carregarLista(true); } catch (erro) { mostrarAviso(erro.message); }
});

btnMais.addEventListener('click', async () => {
    pagina += 1;
    try { await carregarLista(false); } catch (erro) { mostrarAviso(erro.message); }
});

(async function iniciar() {
    try {
        await carregarResumo();
        conteudo.hidden = false;
        await carregarLista(true);
    } catch (erro) {
        if (erro.status === 403) {
            mostrarAviso('Esta conta não tem permissão de administrador. Peça para incluírem seu e-mail na variável ADMIN_EMAILS do servidor.');
        } else {
            mostrarAviso(erro.message);
        }
    }
})();
