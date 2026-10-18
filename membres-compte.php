<?php
// IMMOLLIA — compteur de membres affiché sur le site (« 47 membres »).
// = membres déjà présentes avant le site + chaque nouvelle inscription (une fois par adresse e-mail).
// Ne sont pas comptés : les essais (prénom ou nom contenant « test ») et les 5 membres fondatrices,
// déjà incluses dans le chiffre de départ (reconnues par prénom + nom, accents et tirets ignorés).
const IMMOLLIA_MEMBRES_AVANT_SITE = 47; // à ajuster à la main si besoin
const IMMOLLIA_FONDATRICES = [
    ['adina', 'lungu'], ['anne', 'boucaut'], ['anne', 'basso'], ['audrey', 'morvan'],
    ['elodie', 'mauge'], ['emilie', 'viala'],
];

function immollia_simplifier($t) {
    $t = mb_strtolower(trim($t));
    $t = strtr($t, ['à'=>'a','â'=>'a','ä'=>'a','ç'=>'c','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','î'=>'i','ï'=>'i','ô'=>'o','ö'=>'o','ù'=>'u','û'=>'u','ü'=>'u','ÿ'=>'y']);
    return preg_replace('/[^a-z]+/', '', $t);
}

function immollia_est_fondatrice($prenom, $nom) {
    $p = immollia_simplifier($prenom); $n = immollia_simplifier($nom);
    foreach (IMMOLLIA_FONDATRICES as $f) {
        if (strpos($p, $f[0]) === 0 && strpos($n, $f[1]) !== false) return true;
    }
    return false;
}

function immollia_membres() {
    $home = rtrim((string)(getenv('HOME') ?: dirname(__DIR__)), '/');
    $fichier = $home . '/immollia-data/inscriptions.csv';
    $emails = [];
    if (is_file($fichier) && ($fh = @fopen($fichier, 'r'))) {
        fgetcsv($fh, 0, ';'); // ligne d'en-tête
        while (($l = fgetcsv($fh, 0, ';')) !== false) {
            if (count($l) < 4) continue;
            if (stripos($l[1] . ' ' . $l[2], 'test') !== false) continue;
            if (immollia_est_fondatrice($l[1], $l[2])) continue;
            $emails[mb_strtolower(trim($l[3]))] = true;
        }
        fclose($fh);
    }
    return IMMOLLIA_MEMBRES_AVANT_SITE + count($emails);
}
