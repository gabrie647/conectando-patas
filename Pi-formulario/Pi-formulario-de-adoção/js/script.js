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
// BOTÃO DE LOCALIZAÇÃO
// =====================================================

btnLocalizacao.addEventListener(
    "click",
    function () {

        if (!navigator.geolocation) {

            alert(
                "Seu navegador não suporta localização."
            );

            return;
        }

        btnLocalizacao.disabled = true;

        btnLocalizacao.innerHTML = `
            <i class="bi bi-arrow-repeat girando"></i>
        `;

        navigator.geolocation.getCurrentPosition(

            function (posicao) {

                const latitude =
                    posicao.coords.latitude;

                const longitude =
                    posicao.coords.longitude;

                const precisao =
                    posicao.coords.accuracy;

                localizacao.value =
                    `Latitude: ${latitude.toFixed(6)}, ` +
                    `Longitude: ${longitude.toFixed(6)}`;

                console.log(
                    "Latitude:",
                    latitude
                );

                console.log(
                    "Longitude:",
                    longitude
                );

                console.log(
                    "Precisão aproximada:",
                    `${Math.round(precisao)} metros`
                );

                btnLocalizacao.innerHTML = `
                    <i class="bi bi-check-lg"></i>
                `;

                btnLocalizacao.classList.add(
                    "localizacao-ok"
                );

                btnLocalizacao.disabled = false;

                setTimeout(() => {

                    btnLocalizacao.innerHTML = `
                        <i class="bi bi-geo-alt"></i>
                    `;

                    btnLocalizacao.classList.remove(
                        "localizacao-ok"
                    );

                }, 2500);

            },

            function (erro) {

                console.log(
                    "Erro ao obter localização:",
                    erro
                );

                let mensagemErro =
                    "Não foi possível obter sua localização.";

                if (erro.code === 1) {

                    mensagemErro =
                        "Permissão de localização negada.";

                }

                else if (erro.code === 2) {

                    mensagemErro =
                        "Não foi possível determinar sua localização.";

                }

                else if (erro.code === 3) {

                    mensagemErro =
                        "A localização demorou muito para responder.";

                }

                alert(mensagemErro);

                btnLocalizacao.disabled =
                    false;

                btnLocalizacao.innerHTML = `
                    <i class="bi bi-geo-alt"></i>
                `;

            }

        );

    }
);


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
    if (!arquivo) {
        alert("Por favor, selecione uma foto do pet.");
        foto.click();
        return;
    }

    const dados = new FormData();
    dados.append("nome", campoNome.value.trim());
    dados.append("email", campoEmail.value.trim());
    dados.append("mensagem", texto);
    dados.append("localizacao", local);
    dados.append("foto", arquivo);

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

            btnLocalizacao.disabled =
                false;

            btnLocalizacao.classList.remove(
                "localizacao-ok"
            );

            btnLocalizacao.innerHTML = `
                <i class="bi bi-geo-alt"></i>
            `;

        }, 10);

    }
);