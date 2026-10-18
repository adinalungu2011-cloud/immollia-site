<?php
// IMMOLLIA — enregistrement des inscriptions (page « Rejoindre », QR code du RENT).
// Chaque inscription est :
//   1. ajoutée au fichier ~/immollia-data/inscriptions.csv (HORS du dossier web : jamais accessible en ligne,
//      à télécharger depuis le Gestionnaire de fichiers de cPanel) ;
//   2. envoyée par e-mail à contact@immollia.fr.
// Le CSV est la référence : même si un e-mail se perd, l'inscription est conservée.

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function reponse($ok, $msg = '', $code = 200, $extra = []) {
    http_response_code($code);
    echo json_encode(['ok' => $ok, 'message' => $msg] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') reponse(false, 'Méthode non autorisée.', 405);

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) $in = $_POST;

$champ = function ($k, $max = 120) use ($in) {
    $v = isset($in[$k]) ? (string)$in[$k] : '';
    $v = trim(preg_replace('/[\r\n\t]+/', ' ', strip_tags($v)));
    return mb_substr($v, 0, $max);
};

// Piège à robots : ce champ est invisible pour les humains.
if ($champ('site') !== '') reponse(true);

$prenom   = $champ('prenom', 60);
$nom      = $champ('nom', 80);
$email    = mb_strtolower($champ('email', 160));
$tel      = $champ('tel', 30);
$activite = $champ('activite', 80);
$source   = preg_replace('/[^a-z0-9_-]/i', '', $champ('source', 30)) ?: 'site';
$news     = !empty($in['news']) ? 'oui' : 'non';
$quand    = $champ('quand', 40); // horodatage de l'appareil (inscriptions enregistrées hors connexion)

$chiffres = preg_replace('/\D+/', '', $tel);
if ($prenom === '' || $nom === '')                    reponse(false, 'Merci d\'indiquer votre prénom et votre nom.', 422);
if (!filter_var($email, FILTER_VALIDATE_EMAIL))        reponse(false, 'L\'adresse e-mail semble incomplète.', 422);
if (strlen($chiffres) < 9 || strlen($chiffres) > 15)  reponse(false, 'Le numéro de téléphone semble incomplet.', 422);

// 1. Enregistrement dans le CSV (hors dossier web)
$home = rtrim((string)(getenv('HOME') ?: dirname(__DIR__)), '/');
$dir  = $home . '/immollia-data';
if (!is_dir($dir)) @mkdir($dir, 0700, true);
$fichier = $dir . '/inscriptions.csv';
$nouveau = !file_exists($fichier);
$fh = @fopen($fichier, 'a');
if (!$fh) reponse(false, 'Enregistrement impossible pour le moment. Réessayez dans un instant.', 500);
flock($fh, LOCK_EX);
if ($nouveau) {
    fwrite($fh, "\xEF\xBB\xBF"); // BOM : accents corrects à l'ouverture dans Excel
    fputcsv($fh, ['Date', 'Prénom', 'Nom', 'E-mail', 'Téléphone', 'Activité', 'Source', 'Accepte les actualités', 'Saisie sur l\'appareil'], ';');
}
fputcsv($fh, [date('Y-m-d H:i:s'), $prenom, $nom, $email, $tel, $activite, $source, $news, $quand], ';');
flock($fh, LOCK_UN);
fclose($fh);

// Lien privé de téléchargement du fichier complet (clé créée une fois, gardée hors du dossier web)
$cleFichier = $dir . '/cle-export.txt';
$cle = is_file($cleFichier) ? trim((string)file_get_contents($cleFichier)) : '';
if ($cle === '') { $cle = bin2hex(random_bytes(16)); @file_put_contents($cleFichier, $cle); @chmod($cleFichier, 0600); }
$lienFichier = 'https://www.immollia.fr/inscriptions.php?k=' . $cle;

// 2. Prévenir l'équipe par e-mail
$sujet = 'Nouvelle inscription IMMOLLIA (' . $source . ') : ' . $prenom . ' ' . $nom;
$corps = "Nouvelle inscription depuis le site IMMOLLIA.\n\n"
       . "Prénom : $prenom\nNom : $nom\nE-mail : $email\nTéléphone : $tel\n"
       . "Activité : " . ($activite ?: '-') . "\nSource : $source\n"
       . "Accepte les actualités : $news\n"
       . ($quand ? "Saisie sur l'appareil le : $quand\n" : '')
       . "\nÀ faire : l'ajouter à la communauté WhatsApp et lui envoyer le message de bienvenue.\n"
       . "\nToutes les inscriptions dans un fichier Excel (lien privé, ne pas le transférer) :\n$lienFichier\n";
$entetes = "From: IMMOLLIA <contact@immollia.fr>\r\n"
         . "Reply-To: $email\r\n"
         . "MIME-Version: 1.0\r\n"
         . "Content-Type: text/plain; charset=UTF-8\r\n"
         . "Content-Transfer-Encoding: 8bit\r\n";
@mail('contact@immollia.fr', mb_encode_mimeheader($sujet, 'UTF-8'), $corps, $entetes);

require __DIR__ . '/membres-compte.php';
reponse(true, '', 200, ['membres' => immollia_membres()]);
