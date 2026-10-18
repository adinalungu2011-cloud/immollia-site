<?php
// Renvoie le nombre de membres pour le compteur du site : {"membres": 48}
require __DIR__ . '/membres-compte.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode(['membres' => immollia_membres()]);
