// ======================================================
// BOTÕES COM DATA-SCROLL-TO
// ======================================================

const botoesAdotar =
    document.querySelectorAll('[data-scroll-to]');

botoesAdotar.forEach(botao => {

    botao.addEventListener('click', () => {

        const destino =
            document.getElementById(
                botao.dataset.scrollTo
            );

        destino?.scrollIntoView({
            behavior: 'smooth'
        });

    });

});


// ======================================================
// BOTÕES DE FAVORITO
// ======================================================

const botoesFavorito =
    document.querySelectorAll('.botao-favorito');

botoesFavorito.forEach(coracao => {

    coracao.addEventListener('click', evento => {

        evento.stopPropagation();

        const icone =
            coracao.querySelector('i');

        const favoritado =
            coracao.classList.toggle('ativo');

        if (icone) {

            icone.classList.toggle(
                'fa-regular',
                !favoritado
            );

            icone.classList.toggle(
                'fa-solid',
                favoritado
            );

        }

        coracao.setAttribute(
            'aria-pressed',
            String(favoritado)
        );

    });

});


// ======================================================
// CAMPO DE BUSCA
// ======================================================

const campoBusca =
    document.getElementById('campoBusca');

let cardsAnimais =
    document.querySelectorAll('.card-animal');

campoBusca?.addEventListener(
    'input',
    evento => {

        const termo =
            evento.target.value
                .trim()
                .toLowerCase();

        cardsAnimais.forEach(card => {

            const texto =
                card.textContent.toLowerCase();

            card.hidden =
                !texto.includes(termo);

        });

        paginaAtual = 0;

        setTimeout(() => {

            atualizarCarrossel();

        }, 50);

    }
);


// ======================================================
// CARROSSEL
// ======================================================

const listaAnimais =
    document.getElementById('listaAnimais');

const btnAnterior =
    document.getElementById('btnAnterior');

const btnProximo =
    document.getElementById('btnProximo');

const indicadores =
    document.querySelectorAll('.indicador');

let paginaAtual = 0;


// ======================================================
// QUANTIDADE DE CARDS POR PÁGINA
// ======================================================

function quantidadeCards() {

    if (window.innerWidth <= 560) {

        return 1;

    }

    if (window.innerWidth <= 1000) {

        return 2;

    }

    return 3;

}


// ======================================================
// QUANTIDADE DE PÁGINAS
// ======================================================

function quantidadePaginas() {

    const cards =
        document.querySelectorAll(
            '#listaAnimais .card-animal:not([hidden])'
        );

    const quantidade =
        quantidadeCards();

    if (cards.length === 0) {

        return 0;

    }

    return Math.ceil(
        cards.length / quantidade
    );

}


// ======================================================
// ATUALIZAR INDICADORES
// ======================================================

function atualizarIndicadores() {

    const totalPaginas =
        quantidadePaginas();

    indicadores.forEach(
        (indicador, indice) => {

            indicador.classList.toggle(
                'ativo',
                indice === paginaAtual
            );

            indicador.style.display =
                indice < totalPaginas
                    ? ''
                    : 'none';

        }
    );

}


// ======================================================
// ATUALIZAR BOTÕES
// ======================================================

function atualizarBotoes() {

    const totalPaginas =
        quantidadePaginas();

    if (btnAnterior) {

        btnAnterior.disabled =
            paginaAtual <= 0;

    }

    if (btnProximo) {

        btnProximo.disabled =
            totalPaginas === 0 ||
            paginaAtual >= totalPaginas - 1;

    }

}


// ======================================================
// ATUALIZAR CARROSSEL
// ======================================================

function atualizarCarrossel(suave = true) {

    if (!listaAnimais) {

        return;

    }

    const cards =
        listaAnimais.querySelectorAll(
            '.card-animal:not([hidden])'
        );

    if (!cards.length) {

        listaAnimais.style.transform =
            'translateX(0)';

        atualizarIndicadores();

        atualizarBotoes();

        return;

    }

    const quantidade =
        quantidadeCards();

    const primeiroCard =
        cards[0];

    const larguraCard =
        primeiroCard.getBoundingClientRect().width;

    const estilos =
        window.getComputedStyle(
            listaAnimais
        );

    const gap =
        parseFloat(estilos.gap) || 0;

    const deslocamento =
        paginaAtual *
        quantidade *
        (larguraCard + gap);

    if (suave) {

        listaAnimais.style.transition =
            'transform 0.6s ease';

    } else {

        listaAnimais.style.transition =
            'none';

    }

    listaAnimais.style.transform =
        `translateX(-${deslocamento}px)`;

    atualizarIndicadores();

    atualizarBotoes();

}


// ======================================================
// SETA ANTERIOR
// ======================================================

btnAnterior?.addEventListener(
    'click',
    () => {

        if (paginaAtual > 0) {

            paginaAtual--;

            atualizarCarrossel();

        }

    }
);


// ======================================================
// SETA PRÓXIMO
// ======================================================

btnProximo?.addEventListener(
    'click',
    () => {

        const totalPaginas =
            quantidadePaginas();

        if (
            paginaAtual <
            totalPaginas - 1
        ) {

            paginaAtual++;

            atualizarCarrossel();

        }

    }
);


// ======================================================
// INDICADORES
// ======================================================

indicadores.forEach(
    (indicador, indice) => {

        indicador.addEventListener(
            'click',
            () => {

                const totalPaginas =
                    quantidadePaginas();

                if (
                    indice <
                    totalPaginas
                ) {

                    paginaAtual =
                        indice;

                    atualizarCarrossel();

                }

            }
        );

    }
);


// ======================================================
// VINCULAÇÃO DOS CARDS COM OUTRAS PÁGINAS
// ======================================================

function configurarLinksDosAnimais() {

    const cards =
        document.querySelectorAll(
            '#listaAnimais .card-animal'
        );

    cards.forEach(card => {

        const pagina =
            card.dataset.pagina;

        if (!pagina) {

            return;

        }

        card.style.cursor =
            'pointer';

        card.addEventListener(
            'click',
            evento => {

                if (
                    evento.target.closest(
                        '.botao-favorito'
                    )
                ) {

                    return;

                }

                if (
                    evento.target.closest(
                        'button'
                    )
                ) {

                    return;

                }

                navegarComTransicao(
                    pagina
                );

            }
        );

    });

}


// ======================================================
// REDIMENSIONAMENTO DA TELA
// ======================================================

let larguraAnterior =
    window.innerWidth;

window.addEventListener(
    'resize',
    () => {

        const larguraAtual =
            window.innerWidth;

        const quantidadeAnterior =
            larguraAnterior <= 560
                ? 1
                : larguraAnterior <= 1000
                    ? 2
                    : 3;

        const quantidadeAtual =
            quantidadeCards();

        if (
            quantidadeAnterior !==
            quantidadeAtual
        ) {

            paginaAtual = 0;

        }

        const totalPaginas =
            quantidadePaginas();

        if (
            totalPaginas === 0
        ) {

            paginaAtual = 0;

        }

        else if (
            paginaAtual >=
            totalPaginas
        ) {

            paginaAtual =
                Math.max(
                    0,
                    totalPaginas - 1
                );

        }

        larguraAnterior =
            larguraAtual;

        atualizarCarrossel(false);

    }
);


// ======================================================
// TRANSIÇÃO ENTRE PÁGINAS
// ======================================================

function navegarComTransicao(pagina) {

    if (!pagina) {

        return;

    }

    document.body.classList.add(
        'saindo'
    );

    setTimeout(() => {

        window.location.href =
            pagina;

    }, 400);

}


// ======================================================
// LINKS DO SITE COM TRANSIÇÃO
// ======================================================

document.addEventListener(
    'DOMContentLoaded',
    () => {

        const links =
            document.querySelectorAll(
                'a'
            );

        links.forEach(link => {

            link.addEventListener(
                'click',
                evento => {

                    const destino =
                        link.getAttribute(
                            'href'
                        );

                    if (
                        !destino ||
                        destino === '#' ||
                        destino.startsWith('#') ||
                        link.target === '_blank' ||
                        destino.startsWith('http') ||
                        destino.startsWith('mailto:')
                    ) {

                        return;

                    }

                    evento.preventDefault();

                    navegarComTransicao(
                        destino
                    );

                }
            );

        });

    }
);


// ======================================================
// INICIALIZAÇÃO
// ======================================================

document.addEventListener(
    'DOMContentLoaded',
    () => {

        paginaAtual = 0;

        configurarLinksDosAnimais();

        atualizarCarrossel(false);

    }
);


// ======================================================
// ATUALIZAÇÃO APÓS CARREGAMENTO DAS IMAGENS
// ======================================================

window.addEventListener(
    'load',
    () => {

        atualizarCarrossel(false);

    }
);


// ======================================================
// VINCULAÇÃO DA PÁGINA DE CADASTRO
// ======================================================

// Caminho da página de cadastro
const paginaCadastro =
    window.location.pathname.includes('/Contatos/')
        ? '../Pi-formulario/Pi-formulario-inscricao/index.html'
        : './Pi-formulario/Pi-formulario-inscricao/index.html';


// ======================================================
// BOTÃO "CADASTRE-SE"
// ======================================================

const btnCadastro =
    document.getElementById(
        "btnCadastro"
    );

btnCadastro?.addEventListener(
    "click",
    function () {

        navegarComTransicao(
            paginaCadastro
        );

    }
);


// ======================================================
// BOTÕES "QUERO ADOTAR"
// ======================================================

const botoesQueroAdotar =
    document.querySelectorAll(
        ".btn-quero-adotar"
    );

botoesQueroAdotar.forEach(
    function (botao) {

        botao.addEventListener(
            "click",
            function () {

                navegarComTransicao(
                    paginaCadastro
                );

            }
        );

    }
);