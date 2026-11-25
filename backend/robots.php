<?php
// robots.txt: o que os buscadores podem visitar e onde está o sitemap.
require_once __DIR__ . "/seo.php";

header("Content-Type: text/plain; charset=UTF-8");
header("Cache-Control: public, max-age=3600");

$base = base_publica();
echo "User-agent: *\n";
echo "Disallow: /backend/\n";
echo "Allow: /backend/foto.php\n";
echo "Disallow: /admin/\n";
echo "Disallow: /conta.html\n";
echo "Disallow: /solicitar-adocao.html\n";
echo "Disallow: /tests/\n";
echo "Disallow: /data-base/\n";
echo "\n";
echo "Sitemap: " . $base . "/sitemap.xml\n";
