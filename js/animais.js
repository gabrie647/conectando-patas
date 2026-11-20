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

const ROTULO_ESPECIE = { cao: "Cão", gato: "Gato", outro: "Outro" };
const ROTULO_IDADE = { filhote: "Filhote", adulto: "Adulto", idoso: "Idoso" };

function resumir(texto, limite) {
    return texto.length > limite ? texto.slice(0, limite - 1).trimEnd() + "…" : texto;
}

function criarCartao(pet) {
    // Mesma estrutura dos cards da home (.card-animal), preenchida com o que o usuário cadastrou.
    // Cadastros antigos (sem nome, espécie ou idade) usam um texto de apoio.
    const titulo = pet.nome_animal || resumir(pet.descricao, 40);
    const card = criar("article", "card-animal");

    const imagem = criar("div", "imagem-animal");
    const img = document.createElement("img");
    img.src = pet.foto;
    img.alt = titulo;
    img.loading = "lazy";
    imagem.appendChild(img);

    const etiquetas = criar("div", "tags-animal");
    if (ROTULO_IDADE[pet.idade]) etiquetas.appendChild(criar("span", "tag", ROTULO_IDADE[pet.idade]));
    if (ROTULO_ESPECIE[pet.especie]) etiquetas.appendChild(criar("span", "tag tipo", ROTULO_ESPECIE[pet.especie]));
    if (etiquetas.childNodes.length) imagem.appendChild(etiquetas);

    const favorito = criar("button", "botao-favorito");
    favorito.type = "button";
    favorito.setAttribute("aria-label", "Favoritar " + titulo);
    favorito.setAttribute("aria-pressed", "false");
    const icone = criar("i", "fa-regular fa-heart");
    icone.setAttribute("aria-hidden", "true");
    favorito.appendChild(icone);
    window.Favoritos.ligarBotao(favorito, pet.id);
    imagem.appendChild(favorito);

    const dados = criar("div", "dados-animal");

    const nomeIdade = criar("div", "nome-idade");
    nomeIdade.appendChild(criar("h3", "", titulo));
    nomeIdade.appendChild(criar("span", "", ROTULO_IDADE[pet.idade] || ""));
    dados.appendChild(nomeIdade);

    const local = criar("p", "localizacao");
    const iconeLocal = criar("i", "fa-solid fa-location-dot");
    iconeLocal.setAttribute("aria-hidden", "true");
    local.appendChild(iconeLocal);
    const conteudo = conteudoLocal(pet.localizacao);
    local.appendChild(conteudo instanceof Node ? conteudo : document.createTextNode(conteudo));
    dados.appendChild(local);

    // A descrição escrita pelo usuário
    dados.appendChild(criar("p", "descricao-animal", resumir(pet.descricao, 160)));

    const botao = criar("a", "btn-card");
    botao.href = "./animal.html?id=" + encodeURIComponent(pet.id);
    botao.appendChild(document.createTextNode("VER DETALHES "));
    const seta = criar("i", "fa-solid fa-arrow-right");
    seta.setAttribute("aria-hidden", "true");
    botao.appendChild(seta);
    dados.appendChild(botao);

    card.appendChild(imagem);
    card.appendChild(dados);
    return card;
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
        const resposta = await fetch('/backend/animais.php?' + parametros.toString(), { cache: 'no-store' });
        const dados = await resposta.json();
        if (meuPedido !== pedidoAtual) return;
        if (!resposta.ok || !dados.sucesso) throw new Error(dados.mensagem || 'Falha ao carregar.');

        grade.querySelectorAll('.esqueleto').forEach(e => e.remove());
        dados.itens.forEach(pet => grade.appendChild(criarCartao(pet)));

        if (!grade.children.length) {
            if (termo) mostrarVazio('Nada encontrado', 'Tente buscar com outras palavras.');
            else mostrarVazio('Nenhum animal por aqui ainda', 'Assim que um animal for cadastrado, seu card aparecerá aqui para todos os visitantes.');
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

// Mantém a vitrine atualizada para quem já está com a página aberta.
// A API não usa cache; a cada 20 segundos verificamos se entrou algum animal novo.
document.addEventListener('visibilitychange', () => {
    if (!document.hidden && pagina === 1 && !termo) carregar(true);
});
window.setInterval(() => {
    if (!document.hidden && pagina === 1 && !termo && !carregando) carregar(true);
}, 20000);
