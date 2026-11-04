<?php
// IMMOLLIA — formulaire de l'accueil (événement, partenariat, autre demande).
// « Rejoindre la communauté » passe par inscription.php (compteur de membres).
// Chaque message est :
//   1. ajouté au fichier ~/immollia-data/contacts.csv (HORS du dossier web) ;
//   2. envoyé par e-mail à contact@immollia.fr.

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function reponse($ok, $msg = '', $code = 200) {
    http_response_code($code);
    echo json_encode(['ok' => $ok, 'message' => $msg], JSON_UNESCAPED_UNICODE);
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

$motifs   = ['evenement' => 'Participer à un événement', 'partenariat' => 'Proposer un partenariat', 'autre' => 'Autre demande'];
$motif    = $motifs[$champ('motif', 20)] ?? 'Autre demande';
$prenom   = $champ('prenom', 60);
$nom      = $champ('nom', 80);
$email    = mb_strtolower($champ('email', 160));
$tel      = $champ('tel', 30);
$activite = $champ('activite', 80);
$ville    = $champ('ville', 80);
$message  = isset($in['message']) ? trim(mb_substr(strip_tags((string)$in['message']), 0, 3000)) : '';

if ($prenom === '' || $nom === '')             reponse(false, 'Merci d\'indiquer votre prénom et votre nom.', 422);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) reponse(false, 'L\'adresse e-mail semble incomplète.', 422);
if ($message === '')                           reponse(false, 'Merci d\'écrire un petit message.', 422);

// 1. Enregistrement dans le CSV (hors dossier web)
$home = rtrim((string)(getenv('HOME') ?: dirname(__DIR__)), '/');
$dir  = $home . '/immollia-data';
if (!is_dir($dir)) @mkdir($dir, 0700, true);
$fichier = $dir . '/contacts.csv';
$nouveau = !file_exists($fichier);
$fh = @fopen($fichier, 'a');
if (!$fh) reponse(false, 'Envoi impossible pour le moment. Réessayez dans un instant.', 500);
flock($fh, LOCK_EX);
if ($nouveau) {
    fwrite($fh, "\xEF\xBB\xBF");
    fputcsv($fh, ['Date', 'Motif', 'Prénom', 'Nom', 'E-mail', 'Téléphone', 'Activité', 'Ville', 'Message'], ';', '"', '\\');
}
fputcsv($fh, [date('Y-m-d H:i:s'), $motif, $prenom, $nom, $email, $tel, $activite, $ville, $message], ';', '"', '\\');
flock($fh, LOCK_UN);
fclose($fh);

// 2. Prévenir l'équipe par e-mail
$sujet = 'Site IMMOLLIA (' . $motif . ') : ' . $prenom . ' ' . $nom;
$corps = "Nouveau message depuis le site IMMOLLIA.\n\n"
       . "Motif : $motif\nPrénom : $prenom\nNom : $nom\nE-mail : $email\n"
       . "Téléphone : " . ($tel ?: '-') . "\nActivité : " . ($activite ?: '-') . "\nVille : " . ($ville ?: '-') . "\n\n"
       . "Message :\n$message\n";
$entetes = "From: IMMOLLIA <contact@immollia.fr>\r\n"
         . "Reply-To: $email\r\n"
         . "MIME-Version: 1.0\r\n"
         . "Content-Type: text/plain; charset=UTF-8\r\n"
         . "Content-Transfer-Encoding: 8bit\r\n";
@mail('contact@immollia.fr', mb_encode_mimeheader($sujet, 'UTF-8'), $corps, $entetes);

reponse(true);
