<?php
// envoyer_message.php — Envoi de message recruteur → candidat
require_once 'config.php';
requireLogin('recruteur');
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$to   = (int)($data['to_user_id'] ?? 0);
$sujet = trim($data['sujet'] ?? '');
$corps = trim($data['corps'] ?? '');

if (!$to || !$sujet || !$corps) {
    echo json_encode(['ok'=>false,'error'=>'Champs manquants']); exit;
}

// Vérifie que le destinataire est bien un candidat
try {
    $check = db()->prepare("SELECT id FROM candidats WHERE id=? LIMIT 1");
    $check->execute([$to]);
    if (!$check->fetch()) {
        echo json_encode(['ok'=>false,'error'=>'Candidat introuvable']); exit;
    }

    db()->prepare("INSERT INTO messages (from_user_id, from_role, to_user_id, to_role, sujet, corps) VALUES (?,?,?,?,?,?)")
        ->execute([$_SESSION['user_id'], 'recruteur', $to, 'candidat', $sujet, $corps]);

    echo json_encode(['ok'=>true]);
} catch(Exception $e) {
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
