// =====================================================
// PEGAR ELEMENTOS DO HTML
// =====================================================

const formulario = document.getElementById("formulario");
const mensagem = document.getElementById("mensagem");
const foto = document.getElementById("foto");
const preview = document.getElementById("preview");
const previewContainer = document.querySelector(".preview-container");
const nomeArquivo = document.getElementById("nomeArquivo");
const localizacao = document.getElementById("localizacao");
const btnLocalizacao = document.getElementById("btnLocalizacao");

const btnEnviar = formulario.querySelector(".btn-success");
const btnLimpar = formulario.querySelector(".btn-danger");


// =====================================================
// VERIFICAR ELEMENTOS
// =====================================================

console.log("Formulário:", formulario);
console.log("Mensagem:", mensagem);
console.log("Foto:", foto);
console.log("Localização:", localizacao);


// =====================================================
// PRÉ-VISUALIZAÇÃO DA FOTO
// =====================================================

foto.addEventListener("change", function () {

    const arquivo = foto.files[0];

    if (!arquivo) {
        limparPreview();
        return;
    }

    if (!arquivo.type.startsWith("image/")) {

        alert("Selecione uma imagem válida.");

        foto.value = "";

        limparPreview();

        return;
    }

    const tamanhoMaximo = 5 * 1024 * 1024;

    if (arquivo.size > tamanhoMaximo) {

        alert("A imagem deve ter no máximo 5 MB.");

        foto.value = "";

        limparPreview();

        return;
    }

    nomeArquivo.textContent =
        `✓ ${arquivo.name}`;

    const leitor = new FileReader();

    leitor.onload = function (evento) {

        preview.src =
            evento.target.result;

        previewContainer.style.display =
            "block";

        previewContainer.style.opacity =
            "0";

        previewContainer.style.transform =
            "scale(0.96)";

        setTimeout(() => {

            previewContainer.style.transition =
                "opacity .3s ease, transform .3s ease";

            previewContainer.style.opacity =
                "1";

            previewContainer.style.transform =
                "scale(1)";

        }, 20);

    };

    leitor.readAsDataURL(arquivo);

});


// =====================================================
// FUNÇÃO LIMPAR PREVIEW
// =====================================================

function limparPreview() {

    preview.src = "";

    previewContainer.style.display =
        "none";

    previewContainer.style.opacity =
        "0";

    previewContainer.style.transform =
        "scale(0.96)";

    nomeArquivo.textContent =
        "";

}


// =====================================================
// MAPA INTERATIVO (Leaflet + OpenStreetMap) E GPS
// =====================================================

const campoLatitude = document.getElementById("latitude");
const campoLongitude = document.getElementById("longitude");
const mapaStatus = document.getElementById("mapaStatus");
const textoBotaoGps = btnLocalizacao.innerHTML;

let mapa = null;
let marcador = null;
let controleBusca = 0; // evita que uma resposta antiga do endereço sobrescreva a nova

function mostrarStatus(texto, erro) {
    mapaStatus.textContent = texto || "";
    mapaStatus.classList.toggle("erro", !!erro);
}

function iniciarMapa() {
    const el = document.getElementById("mapa");

    if (typeof L === "undefined") {
        el.classList.add("mapa-indisponivel");
        el.textContent = "Não foi possível carregar o mapa. Digite a cidade ou bairro no campo acima.";
        return;
    }

    // Começa mostrando o Brasil inteiro
    mapa = L.map(el, { scrollWheelZoom: false }).setView([-14.2, -51.9], 4);

    L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>'
    }).addTo(mapa);

    // Clique/toque no mapa posiciona o marcador (alternativa quando o GPS falha)
    mapa.on("click", function (e) {
        definirPosicao(e.latlng.lat, e.latlng.lng, false);
    });

    // Rolagem do mouse só dá zoom depois de clicar no mapa (não atrapalha a rolagem da página)
    mapa.on("focus", function () { mapa.scrollWheelZoom.enable(); });
    mapa.on("blur", function () { mapa.scrollWheelZoom.disable(); });

    // O mapa fica dentro de um cartão animado: recalcula o tamanho depois de carregar
    setTimeout(function () { mapa.invalidateSize(); }, 300);
    window.addEventListener("load", function () { mapa.invalidateSize(); });
}

function criarIcone() {
    return L.divIcon({
        className: "marcador-pet",
        html: '<i class="bi bi-geo-alt-fill"></i>',
        iconSize: [40, 40],
        iconAnchor: [20, 38]
    });
}

function definirPosicao(lat, lng, ajustarZoom) {
    if (!mapa) return;

    const posicao = L.latLng(lat, lng);

    if (!marcador) {
        marcador = L.marker(posicao, { draggable: true, icon: criarIcone() }).addTo(mapa);
        marcador.on("dragend", function () {
            const p = marcador.getLatLng();
            definirPosicao(p.lat, p.lng, false);
        });
    } else {
        marcador.setLatLng(posicao);
    }

    campoLatitude.value = lat.toFixed(6);
    campoLongitude.value = lng.toFixed(6);

    if (ajustarZoom) {
        mapa.setView(posicao, 17);
    } else {
        mapa.panTo(posicao);
    }

    buscarEndereco(lat, lng);
}

// Endereço aproximado (OpenStreetMap Nominatim). Se falhar, usa as coordenadas.
async function buscarEndereco(lat, lng) {
    const minhaBusca = ++controleBusca;
    const textoCoord = "Latitude: " + lat.toFixed(6) + ", Longitude: " + lng.toFixed(6);

    localizacao.value = textoCoord;
    mostrarStatus("Identificando o endereço aproximado...");

    try {
        const url = "https://nominatim.openstreetmap.org/reverse?format=jsonv2&zoom=16&accept-language=pt-BR" +
            "&lat=" + encodeURIComponent(lat) + "&lon=" + encodeURIComponent(lng);
        const resposta = await fetch(url);
        if (!resposta.ok) throw new Error("HTTP " + resposta.status);
        const dados = await resposta.json();
        if (minhaBusca !== controleBusca) return;

        const a = dados.address || {};
        const bairro = a.suburb || a.neighbourhood || a.quarter || a.city_district || "";
        const cidade = a.city || a.town || a.village || a.municipality || "";
        const estado = a.state || "";
        const partes = [bairro, cidade, estado].filter(Boolean);

        if (partes.length) {
            localizacao.value = partes.join(", ");
            mostrarStatus("Local aproximado: " + localizacao.value);
        } else {
            mostrarStatus("Local marcado no mapa.");
        }
    } catch (erro) {
        if (minhaBusca !== controleBusca) return;
        console.warn("Não foi possível identificar o endereço:", erro);
        mostrarStatus("Local marcado no mapa (endereço não identificado).");
    }
}

function restaurarBotaoGps() {
    btnLocalizacao.disabled = false;
    btnLocalizacao.innerHTML = textoBotaoGps;
}

btnLocalizacao.addEventListener("click", function () {

    if (!navigator.geolocation) {
        mostrarStatus("Seu navegador não suporta localização. Toque no mapa para marcar o local.", true);
        return;
    }

    if (!window.isSecureContext && location.hostname !== "localhost") {
        mostrarStatus("O GPS só funciona em site seguro (HTTPS). Toque no mapa para marcar o local.", true);
        return;
    }

    btnLocalizacao.disabled = true;
    btnLocalizacao.innerHTML = '<i class="bi bi-arrow-repeat girando"></i><span>Localizando...</span>';
    mostrarStatus("Aguardando a autorização de localização...");

    navigator.geolocation.getCurrentPosition(
        function (posicao) {
            definirPosicao(posicao.coords.latitude, posicao.coords.longitude, true);

            const precisao = Math.round(posicao.coords.accuracy);
            if (precisao > 500) {
                mostrarStatus("Localização aproximada (±" + precisao + " m). Arraste o marcador para ajustar.");
            }

            btnLocalizacao.disabled = false;
            btnLocalizacao.innerHTML = '<i class="bi bi-check-lg"></i><span>Localização obtida</span>';
            btnLocalizacao.classList.add("localizacao-ok");

            setTimeout(function () {
                btnLocalizacao.classList.remove("localizacao-ok");
                restaurarBotaoGps();
            }, 2500);
        },
        function (erro) {
            let msg = "Não foi possível obter sua localização.";
            if (erro.code === 1) msg = "Permissão de localização negada.";
            else if (erro.code === 2) msg = "Não foi possível determinar sua localização.";
            else if (erro.code === 3) msg = "A localização demorou muito para responder.";

            mostrarStatus(msg + " Toque no mapa para marcar o local manualmente.", true);
            restaurarBotaoGps();
        },
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
    );
});

function limparMapa() {
    controleBusca++;
    campoLatitude.value = "";
    campoLongitude.value = "";
    mostrarStatus("");
    if (mapa && marcador) {
        mapa.removeLayer(marcador);
        marcador = null;
        mapa.setView([-14.2, -51.9], 4);
    }
}

iniciarMapa();


// =====================================================
// ENVIO DO FORMULÁRIO PARA O BACKEND
// =====================================================

formulario.addEventListener("submit", async function (evento) {
    evento.preventDefault();

    const campoNome = document.getElementById("nomeResponsavel");
    const campoEmail = document.getElementById("emailResponsavel");
    const texto = mensagem.value.trim();
    const local = localizacao.value.trim();
    const arquivo = foto.files[0];

    if (!campoNome.value.trim()) {
        alert("Por favor, informe seu nome.");
        campoNome.focus();
        return;
    }
    if (!campoEmail.value.trim() || !campoEmail.checkValidity()) {
        alert("Por favor, informe um e-mail válido.");
        campoEmail.focus();
        return;
    }
    if (!texto) {
        mostrarErro(mensagem, "Por favor, escreva algumas informações sobre o pet.");
        return;
    }
    if (!local) {
        mostrarErro(localizacao, "Por favor, informe a localização.");
        return;
    }
    const especie = document.getElementById("especie").value;
    const idade = document.getElementById("idade").value;
    if (!especie || !idade) {
        alert("Informe a espécie e a idade do pet.");
        return;
    }
    if (!arquivo) {
        alert("Por favor, selecione uma foto do pet.");
        foto.click();
        return;
    }

    const dados = new FormData();
    dados.append("nome_animal", document.getElementById("nomeAnimal").value.trim());
    dados.append("especie", especie);
    dados.append("idade", idade);
    dados.append("nome", campoNome.value.trim());
    dados.append("email", campoEmail.value.trim());
    dados.append("mensagem", texto);
    dados.append("localizacao", local);
    if (campoLatitude.value && campoLongitude.value) {
        dados.append("latitude", campoLatitude.value);
        dados.append("longitude", campoLongitude.value);
    }
    dados.append("foto", arquivo);
    dados.append("website", document.getElementById("website").value);

    const textoOriginal = btnEnviar.innerHTML;
    btnEnviar.disabled = true;
    btnEnviar.classList.add("enviando");
    btnEnviar.innerHTML = '<i class="bi bi-arrow-repeat girando"></i> Enviando...';

    try {
        const resposta = await fetch("/backend/registro-pet.php", {
            method: "POST",
            body: dados
        });
        const textoResposta = await resposta.text();
        let resultado;
        try {
            resultado = JSON.parse(textoResposta);
        } catch (erroJson) {
            console.error("Resposta não JSON do servidor:", textoResposta);
            throw new Error("O servidor retornou uma resposta inválida. Confira os logs do Render.");
        }
        if (!resposta.ok || !resultado.sucesso) {
            throw new Error(resultado.mensagem || `Falha HTTP ${resposta.status}.`);
        }

        btnEnviar.classList.remove("enviando");
        btnEnviar.classList.add("sucesso");
        btnEnviar.innerHTML = '<i class="bi bi-check-lg"></i> Registro enviado!';
        alert(resultado.mensagem);
        formulario.reset();
        limparPreview();
        localizacao.value = "";
        limparMapa();
    } catch (erro) {
        console.error("Erro ao enviar registro do pet:", erro);
        alert(erro.message || "Não foi possível conectar ao servidor. Verifique o serviço no Render.");
    } finally {
        setTimeout(() => {
            btnEnviar.disabled = false;
            btnEnviar.classList.remove("enviando", "sucesso");
            btnEnviar.innerHTML = textoOriginal;
        }, 1200);
    }
});


// =====================================================
// MOSTRAR ERRO
// =====================================================

function mostrarErro(
    elemento,
    mensagemErro
) {

    alert(mensagemErro);

    elemento.focus();


    elemento.style.transform =
        "translateX(-5px)";

    setTimeout(() => {

        elemento.style.transform =
            "translateX(5px)";

    }, 70);

    setTimeout(() => {

        elemento.style.transform =
            "translateX(-3px)";

    }, 140);

    setTimeout(() => {

        elemento.style.transform =
            "translateX(0)";

    }, 210);

}


// =====================================================
// BOTÃO RESET
// =====================================================

formulario.addEventListener(
    "reset",
    function () {

        setTimeout(() => {

            limparPreview();

            localizacao.value = "";

            btnEnviar.disabled =
                false;

            btnEnviar.classList.remove(
                "enviando",
                "sucesso"
            );

            btnEnviar.innerHTML = `
                <i class="bi bi-send"></i>
                <span>
                    Enviar Registro
                </span>
            `;

            limparMapa();

            btnLocalizacao.classList.remove("localizacao-ok");
            restaurarBotaoGps();

        }, 10);

    }
);