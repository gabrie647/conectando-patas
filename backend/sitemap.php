<?php
// sitemap.xml: páginas públicas, animais aprovados e as fotos de cada animal.
// Páginas que exigem login (conta, adoção, admin) ficam de fora.
require_once __DIR__ . "/seo.php";

header("Content-Type: application/xml; charset=UTF-8");
header("Cache-Control: public, max-age=3600");

$base = base_publica();
$paginas = [
    ["/", "daily", "1.0"],
    ["/animais.html", "daily", "0.9"],
    ["/Pi-formulario/Pi-formulario-inscricao/index.html", "monthly", "0.6"],
    ["/login/index.html", "monthly", "0.3"],
    ["/Contatos/index.html", "monthly", "0.4"],
    ["/termos-de-uso.html", "yearly", "0.2"],
];

$linhas = [];
foreach ($paginas as [$caminho, $frequencia, $prioridade]) {
    $linhas[] = ["loc" => $base . $caminho, "data" => null, "freq" => $frequencia, "prio" => $prioridade, "imagem" => null];
}

if (getenv("DATABASE_URL") !== false && trim((string)getenv("DATABASE_URL")) !== "") {
    try {
        require_once __DIR__ . "/conexao.php";
        $stmt = $pdo->query(
            "SELECT id, criado_em FROM registros_pets
             WHERE status = 'aprovado' AND foto_dados IS NOT NULL
             ORDER BY criado_em DESC LIMIT 5000"
        );
        foreach ($stmt->fetchAll() as $pet) {
            $linhas[] = [
                "loc" => $base . "/animal.html?id=" . (int)$pet["id"],
                "data" => substr((string)$pet["criado_em"], 0, 10),
                "freq" => "weekly",
                "prio" => "0.7",
                "imagem" => $base . "/backend/foto.php?id=" . (int)$pet["id"],
            ];
        }
    } catch (Throwable $e) {
        error_log("Sitemap sem animais: " . $e->getMessage());
    }
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
foreach ($linhas as $l) {
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($l["loc"], ENT_XML1 | ENT_QUOTES, "UTF-8") . "</loc>\n";
    if ($l["data"]) {
        echo "    <lastmod>" . htmlspecialchars($l["data"], ENT_XML1, "UTF-8") . "</lastmod>\n";
    }
    echo "    <changefreq>" . $l["freq"] . "</changefreq>\n";
    echo "    <priority>" . $l["prio"] . "</priority>\n";
    if ($l["imagem"]) {
        echo "    <image:image>\n";
        echo "      <image:loc>" . htmlspecialchars($l["imagem"], ENT_XML1 | ENT_QUOTES, "UTF-8") . "</image:loc>\n";
        echo "    </image:image>\n";
    }
    echo "  </url>\n";
}
echo "</urlset>\n";
