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
// ENVIO DO FORMULÁRIO
// =====================================================

formulario.addEventListener(
    "submit",
    function (evento) {

        evento.preventDefault();


        // =================================================
        // PEGAR NOME
        // =================================================

        const campoNome =
            document.querySelector(
                'input[placeholder="Nome"]'
            );

        const nome =
            campoNome
                ? campoNome.value.trim()
                : "";


        // =================================================
        // PEGAR E-MAIL
        // =================================================

        const campoEmail =
            document.getElementById("inputEmail4");

        const email =
            campoEmail
                ? campoEmail.value.trim()
                : "";


        // =================================================
        // PEGAR OUTROS VALORES
        // =================================================

        const texto =
            mensagem.value.trim();

        const local =
            localizacao.value.trim();

        const arquivo =
            foto.files[0];


        // =================================================
        // VALIDAR NOME
        // =================================================

        if (nome === "") {

            alert(
                "Por favor, informe seu nome."
            );

            if (campoNome) {
                campoNome.focus();
            }

            return;
        }


        // =================================================
        // VALIDAR E-MAIL
        // =================================================

        if (email === "") {

            alert(
                "Por favor, informe seu e-mail."
            );

            if (campoEmail) {
                campoEmail.focus();
            }

            return;
        }


        // =================================================
        // VALIDAR MENSAGEM
        // =================================================

        if (texto === "") {

            mostrarErro(
                mensagem,
                "Por favor, escreva algumas informações sobre o pet."
            );

            return;
        }


        // =================================================
        // VALIDAR LOCALIZAÇÃO
        // =================================================

        if (local === "") {

            mostrarErro(
                localizacao,
                "Por favor, informe a localização."
            );

            return;
        }


        // =================================================
        // VALIDAR FOTO
        // =================================================

        if (!arquivo) {

            alert(
                "Por favor, selecione uma foto do pet."
            );

            foto.click();

            return;
        }


        // =================================================
        // CRIAR OBJETO
        // =================================================

        const dadosRegistro = {

            nome: nome,

            email: email,

            mensagem: texto,

            localizacao: local,

            foto: arquivo.name,

            tipoFoto: arquivo.type,

            tamanhoFoto: arquivo.size,

            data:
                new Date().toLocaleString("pt-BR")

        };


        // =================================================
        // MOSTRAR DADOS NO CONSOLE
        // =================================================

        console.log(
            "================================="
        );

        console.log(
            "🐾 REGISTRO PET"
        );

        console.log(
            "================================="
        );

        console.log(
            "Nome:",
            dadosRegistro.nome
        );

        console.log(
            "E-mail:",
            dadosRegistro.email
        );

        console.log(
            "Mensagem:",
            dadosRegistro.mensagem
        );

        console.log(
            "Localização:",
            dadosRegistro.localizacao
        );

        console.log(
            "Foto:",
            dadosRegistro.foto
        );

        console.log(
            "Tipo:",
            dadosRegistro.tipoFoto
        );

        console.log(
            "Tamanho:",
            dadosRegistro.tamanhoFoto
        );

        console.log(
            "Data:",
            dadosRegistro.data
        );

        console.log(
            "Arquivo:",
            arquivo
        );

        console.log(
            "================================="
        );


        // =================================================
        // ANIMAÇÃO DO BOTÃO
        // =================================================

        const textoOriginal =
            btnEnviar.innerHTML;

        btnEnviar.disabled = true;

        btnEnviar.classList.add(
            "enviando"
        );

        btnEnviar.innerHTML = `
            <i class="bi bi-arrow-repeat girando"></i>
            Enviando...
        `;


        // =================================================
        // SIMULAÇÃO DE ENVIO
        // =================================================

        setTimeout(() => {

            btnEnviar.classList.remove(
                "enviando"
            );

            btnEnviar.classList.add(
                "sucesso"
            );

            btnEnviar.innerHTML = `
                <i class="bi bi-check-lg"></i>
                Registro enviado!
            `;

            alert(
                "🐾 Registro enviado com sucesso!"
            );

            formulario.reset();

            limparPreview();


            // =================================================
            // RESTAURAR BOTÃO
            // =================================================

            setTimeout(() => {

                btnEnviar.disabled =
                    false;

                btnEnviar.classList.remove(
                    "sucesso"
                );

                btnEnviar.innerHTML =
                    textoOriginal;

            }, 1800);

        }, 1200);

    }
);


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