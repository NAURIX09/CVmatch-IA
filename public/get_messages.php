<?php
// get_messages.php — Messages d'un candidat
require_once 'config.php';
requireLogin('candidat');
header('Content-Type: application/json');
try {
    $stmt = db()->prepare("SELECT m.id,m.sujet,m.corps,m.lu,m.envoye_le, CONCAT(c.prenom,' ',c.nom) as from_nom
                           FROM messages m LEFT JOIN recruteurs c ON c.id=m.from_user_id
                           WHERE m.to_user_id=? ORDER BY m.envoye_le DESC LIMIT 50");
    $stmt->execute([$_SESSION['user_id']]);
    echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
} catch(Exception $e) { echo json_encode([]); }
