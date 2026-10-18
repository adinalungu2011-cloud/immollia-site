<?php
// IMMOLLIA — téléchargement du fichier de toutes les inscriptions (s'ouvre dans Excel ou Numbers).
// Accès uniquement avec la clé privée envoyée dans chaque e-mail « Nouvelle inscription ».
$home = rtrim((string)(getenv('HOME') ?: dirname(__DIR__)), '/');
$dir  = $home . '/immollia-data';
$cle  = is_file($dir . '/cle-export.txt') ? trim((string)file_get_contents($dir . '/cle-export.txt')) : '';
$k    = isset($_GET['k']) ? (string)$_GET['k'] : '';

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
if ($cle === '' || !hash_equals($cle, $k)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Lien invalide. Utilisez le lien indiqué dans le dernier e-mail « Nouvelle inscription IMMOLLIA ».");
}
$fichier = $dir . '/inscriptions.csv';
if (!is_file($fichier)) {
    header('Content-Type: text/plain; charset=utf-8');
    exit('Aucune inscription pour le moment.');
}
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="inscriptions-immollia-' . date('Y-m-d') . '.csv"');
header('Content-Length: ' . filesize($fichier));
readfile($fichier);
