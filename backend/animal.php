<?php
// Página de detalhe do animal com metadados gerados no servidor: título, descrição, imagem e endereço.
// O corpo da página continua em animal.html (os dados são carregados pela API).
require_once __DIR__ . "/seo.php";
require_once __DIR__ . "/conexao.php";

const ESPECIE_SEO = ["cao" => "cão", "gato" => "gato", "outro" => "animal"];
const IDADE_SEO = ["filhote" => "filhote", "adulto" => "adulto", "idoso" => "idoso"];

function escapar_html(string $texto): string
{
    return htmlspecialchars($texto, ENT_QUOTES | ENT_HTML5, "UTF-8");
}

$base = base_publica();
$id = filter_var($_GET["id"] ?? null, FILTER_VALIDATE_INT);
$pet = null;
if ($id && $id > 0) {
    try {
        $stmt = $pdo->prepare(
            "SELECT id, nome_animal, localizacao, especie, idade
             FROM registros_pets
             WHERE id = :id AND status = 'aprovado' AND foto_dados IS NOT NULL"
        );
        $stmt->execute([":id" => $id]);
        $pet = $stmt->fetch() ?: null;
    } catch (Throwable $e) {
        error_log("animal.php: " . $e->getMessage());
    }
}

$modelo = @file_get_contents(__DIR__ . "/../animal.html");
if ($modelo === false) {
    http_response_code(500);
    exit;
}

if ($pet) {
    $nome = trim((string)($pet["nome_animal"] ?? "")) ?: null;
    $local = trim((string)($pet["localizacao"] ?? "")) ?: null;
    $especie = ESPECIE_SEO[$pet["especie"]] ?? "animal";
    $idade = IDADE_SEO[$pet["idade"]] ?? null;

    $titulo = ($nome ?? ucfirst($especie)) . " para adoção" . ($local ? " em " . $local : "") . " | Conectando Patas";

    $frase = ($nome ? "Conheça " . $nome : "Conheça este " . $especie)
        . ($idade ? ", " . $idade : "")
        . ($local ? " em " . $local : "")
        . ", disponível para adoção. Envie sua solicitação no Conectando Patas.";
    $descricao = mb_strimwidth($frase, 0, 160, "…", "UTF-8");

    $status = 200;
    $robots = "";
    $urlCanonica = $base . "/animal.html?id=" . (int)$pet["id"];
    $imagem = $base . "/backend/foto.php?id=" . (int)$pet["id"];
} else {
    $status = 404;
    $titulo = "Animal não encontrado | Conectando Patas";
    $descricao = "Este animal não está disponível para adoção. Veja os outros animais no Conectando Patas.";
    $robots = '<meta name="robots" content="noindex, nofollow">';
    $urlCanonica = $base . "/animais.html";
    $imagem = null;
}

// Substituição por função: o texto do animal pode conter $ ou \, que preg_replace interpretaria.
$tituloHtml = '<title>' . escapar_html($titulo) . '</title>';
$html = preg_replace_callback('#<title>.*?</title>#s', fn($m) => $tituloHtml, $modelo, 1);

$metaDescricao = '<meta name="description" content="' . escapar_html($descricao) . '">';
$trocouDescricao = 0;
$html = preg_replace_callback('#<meta name="description" content="[^"]*">#', fn($m) => $metaDescricao, $html, 1, $trocouDescricao);
if ($trocouDescricao === 0) {
    $html = preg_replace_callback('#<title>.*?</title>#s', fn($m) => $m[0] . "\n    " . $metaDescricao, $html, 1);
}

$extras = [
    $robots,
    '<link rel="canonical" href="' . escapar_html($urlCanonica) . '">',
    '<meta property="og:type" content="website">',
    '<meta property="og:site_name" content="Conectando Patas">',
    '<meta property="og:title" content="' . escapar_html($titulo) . '">',
    '<meta property="og:description" content="' . escapar_html($descricao) . '">',
    '<meta property="og:url" content="' . escapar_html($urlCanonica) . '">',
    '<meta property="og:locale" content="pt_BR">',
    '<meta name="twitter:card" content="summary_large_image">',
];
if ($imagem) {
    $extras[] = '<meta property="og:image" content="' . escapar_html($imagem) . '">';
    $extras[] = '<meta name="twitter:image" content="' . escapar_html($imagem) . '">';
}
$html = str_replace('</head>', implode("\n    ", array_filter($extras)) . "\n</head>", $html);

http_response_code($status);
header("Content-Type: text/html; charset=UTF-8");
header("Cache-Control: public, max-age=300");
echo $html;
