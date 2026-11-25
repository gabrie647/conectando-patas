<?php
// Funções comuns do SEO (sitemap.xml e robots.txt).
// O endereço público vem de APP_URL; sem ela, usa o host da requisição.
function base_publica(): string
{
    $configurada = rtrim((string)getenv("APP_URL"), "/");
    if ($configurada !== "") {
        return $configurada;
    }
    $https = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off")
        || (($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "") === "https");
    $host = preg_replace('/[^A-Za-z0-9.\-:]/', '', $_SERVER["HTTP_HOST"] ?? "localhost");
    return ($https ? "https://" : "http://") . $host;
}
