<?php
// IMMOLLIA — compteur de membres affiché sur le site (« 47 membres »).
// = membres déjà présentes avant le site + chaque nouvelle inscription (une fois par adresse e-mail).
// Les essais dont le prénom ou le nom contient « test » ne sont pas comptés.
const IMMOLLIA_MEMBRES_AVANT_SITE = 47; // à ajuster à la main si besoin

function immollia_membres() {
    $home = rtrim((string)(getenv('HOME') ?: dirname(__DIR__)), '/');
    $fichier = $home . '/immollia-data/inscriptions.csv';
    $emails = [];
    if (is_file($fichier) && ($fh = @fopen($fichier, 'r'))) {
        fgetcsv($fh, 0, ';'); // ligne d'en-tête
        while (($l = fgetcsv($fh, 0, ';')) !== false) {
            if (count($l) < 4) continue;
            if (stripos($l[1] . ' ' . $l[2], 'test') !== false) continue;
            $emails[mb_strtolower(trim($l[3]))] = true;
        }
        fclose($fh);
    }
    return IMMOLLIA_MEMBRES_AVANT_SITE + count($emails);
}
