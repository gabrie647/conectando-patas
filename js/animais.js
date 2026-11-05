const grade = document.getElementById('grade');
const vazio = document.getElementById('vazio');
const vazioTitulo = document.getElementById('vazioTitulo');
const vazioTexto = document.getElementById('vazioTexto');
const btnMais = document.getElementById('btnMais');
const campoBusca = document.getElementById('campoBusca');

let pagina = 1;
let termo = '';
let carregando = false;
let pedidoAtual = 0;

function criar(tag, classe, texto) {
    const elemento = document.createElement(tag);
    if (classe) elemento.className = classe;
    if (texto !== undefined) elemento.textContent = texto;
    return elemento;
}

function linhaMeta(icone, conteudo) {
    const linha = criar('span');
    const i = criar('i', 'fa-solid ' + icone);
    i.setAttribute('aria-hidden', 'true');
    linha.appendChild(i);
    linha.appendChild(conteudo instanceof Node ? conteudo : document.createTextNode(conteudo));
    return linha;
}

function formatarData(texto) {
    const data = new Date(String(texto).replace(' ', 'T') + 'Z');
    return isNaN(data) ? '' : data.toLocaleDateString('pt-BR');
}

function conteudoLocal(localizacao) {
    const m = /Latitude:\s*(-?\d+(?:\.\d+)?),\s*Longitude:\s*(-?\d+(?:\.\d+)?)/i.exec(localizacao);
    if (!m) return localizacao;
    const link = criar('a', '', 'Ver no mapa');
    link.href = 'https://www.openstreetmap.org/?mlat=' + m[1] + '&mlon=' + m[2] + '#map=16/' + m[1] + '/' + m[2];
    link.target = '_blank';
    link.rel = 'noopener noreferrer';
    return link;
}

function criarCartao(pet) {
    const cartao = criar('article', 'cartao-animal');

    const foto = criar('div', 'foto');
    const img = document.createElement('img');
    img.src = pet.foto;
    img.alt = 'Foto do animal registrado em ' + formatarData(pet.criado_em);
    img.loading = 'lazy';
    foto.appendChild(img);

    const corpo = criar('div', 'corpo');
    corpo.appendChild(criar('p', 'descricao', pet.descricao));

    const meta = criar('div', 'meta');
    meta.appendChild(linhaMeta('fa-location-dot', conteudoLocal(pet.localizacao)));
    meta.appendChild(linhaMeta('fa-calendar', 'Registrado em ' + formatarData(pet.criado_em)));
    corpo.appendChild(meta);

    const botao = criar('a', 'btn btn-laranja btn-pequeno');
    botao.href = './Contatos/index.html?assunto=adocao&animal=' + encodeURIComponent(pet.id);
    const coracao = criar('i', 'fa-regular fa-heart');
    coracao.setAttribute('aria-hidden', 'true');
    botao.appendChild(coracao);
    botao.appendChild(document.createTextNode(' Tenho interesse'));
    corpo.appendChild(botao);

    cartao.appendChild(foto);
    cartao.appendChild(corpo);
    return cartao;
}

function mostrarEsqueletos() {
    for (let i = 0; i < 6; i++) grade.appendChild(criar('div', 'esqueleto'));
}

function mostrarVazio(titulo, texto) {
    vazioTitulo.textContent = titulo;
    vazioTexto.textContent = texto;
    vazio.hidden = false;
}

async function carregar(reiniciar) {
    if (carregando && !reiniciar) return;
    const meuPedido = ++pedidoAtual;
    carregando = true;
    btnMais.disabled = true;

    if (reiniciar) {
        pagina = 1;
        grade.replaceChildren();
        btnMais.hidden = true;
    }
    vazio.hidden = true;
    mostrarEsqueletos();

    try {
        const parametros = new URLSearchParams({ pagina: String(pagina) });
        if (termo) parametros.set('q', termo);
        const resposta = await fetch('/backend/animais.php?' + parametros.toString());
        const dados = await resposta.json();
        if (meuPedido !== pedidoAtual) return;
        if (!resposta.ok || !dados.sucesso) throw new Error(dados.mensagem || 'Falha ao carregar.');

        grade.querySelectorAll('.esqueleto').forEach(e => e.remove());
        dados.itens.forEach(pet => grade.appendChild(criarCartao(pet)));

        if (!grade.children.length) {
            if (termo) mostrarVazio('Nada encontrado', 'Tente buscar com outras palavras.');
            else mostrarVazio('Nenhum animal por aqui ainda', 'Assim que a equipe aprovar os primeiros registros, eles aparecerão aqui.');
        }
        btnMais.hidden = !dados.tem_mais;
    } catch (erro) {
        if (meuPedido !== pedidoAtual) return;
        console.error('Erro ao carregar animais:', erro);
        grade.querySelectorAll('.esqueleto').forEach(e => e.remove());
        if (!grade.children.length) mostrarVazio('Não foi possível carregar', 'Tente novamente em instantes.');
    } finally {
        if (meuPedido === pedidoAtual) {
            carregando = false;
            btnMais.disabled = false;
        }
    }
}

btnMais.addEventListener('click', () => {
    pagina += 1;
    carregar(false);
});

let espera;
campoBusca.addEventListener('input', () => {
    clearTimeout(espera);
    espera = setTimeout(() => {
        termo = campoBusca.value.trim();
        carregar(true);
    }, 350);
});

carregar(true);
