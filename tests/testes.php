<?php
// Testes unitários das funções de segurança e validação.
// Execução: php tests/testes.php   (código de saída 0 = tudo passou)
require_once __DIR__ . "/../backend/auth.php";
require_once __DIR__ . "/../backend/protecao.php";
require_once __DIR__ . "/../backend/imagem.php";

$total = 0;
$falhas = 0;

function afirmar(bool $condicao, string $nome): void
{
    global $total, $falhas;
    $total++;
    if (!$condicao) {
        $falhas++;
        echo "FALHOU: $nome\n";
    }
}

// ---------- Senhas
afirmar(validar_senha("curta1") !== null, "senha_menor_que_10");
afirmar(validar_senha("somenteletras") !== null, "senha_sem_numero");
afirmar(validar_senha("123456789012") !== null, "senha_sem_letra");
afirmar(validar_senha("senha1234") !== null, "senha_comum_bloqueada");
afirmar(validar_senha("MinhaSenha2026") === null, "senha_valida_aceita");
afirmar(validar_senha(str_repeat("a1", 40)) !== null, "senha_acima_de_72_bytes");

// ---------- Booleanos do PostgreSQL
afirmar(db_bool("t") === true, "db_bool_t");
afirmar(db_bool("f") === false, "db_bool_f");
afirmar(db_bool(true) === true && db_bool(false) === false, "db_bool_php");
afirmar(db_bool(null) === false, "db_bool_nulo");

// ---------- IP do visitante (X-Forwarded-For só com proxy configurado)
putenv("CLOUDFLARE=0");
putenv("PROXY_HOPS=0");
$_SERVER = ["REMOTE_ADDR" => "10.0.0.5", "HTTP_X_FORWARDED_FOR" => "9.9.9.9"];
afirmar(ip_cliente() === "10.0.0.5", "ip_sem_proxy_ignora_cabecalho");

putenv("PROXY_HOPS=1");
$_SERVER = ["REMOTE_ADDR" => "10.0.0.5", "HTTP_X_FORWARDED_FOR" => "6.6.6.6, 8.8.8.8"];
afirmar(ip_cliente() === "8.8.8.8", "ip_com_um_proxy_usa_o_ultimo");

$_SERVER = ["REMOTE_ADDR" => "10.0.0.5", "HTTP_X_FORWARDED_FOR" => "lixo, nao-e-ip"];
afirmar(ip_cliente() === "10.0.0.5", "ip_invalido_volta_para_o_remoto");
putenv("PROXY_HOPS=0");

// ---------- Imagens
if (function_exists("imagecreatetruecolor") && function_exists("imagepng")) {
    $img = imagecreatetruecolor(20, 10);
    ob_start();
    imagepng($img);
    $png = ob_get_clean();

    $tmp = tempnam(sys_get_temp_dir(), "img");
    file_put_contents($tmp, $png);
    afirmar(imagem_segura($tmp, "image/png") !== null, "imagem_png_valida_aceita");
    afirmar(imagem_segura($tmp, "image/jpeg") === null, "tipo_declarado_diferente_recusado");

    file_put_contents($tmp, "isto nao e uma imagem");
    afirmar(imagem_segura($tmp, "image/png") === null, "arquivo_falso_recusado");
    unlink($tmp);
} else {
    echo "AVISO: GD indisponível; testes de imagem pulados.\n";
}

echo "$total testes, $falhas falhas\n";
exit($falhas > 0 ? 1 : 0);
