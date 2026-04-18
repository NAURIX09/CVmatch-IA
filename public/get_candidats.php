<?php
// get_candidats.php — API JSON interne
require_once 'config.php';
requireLogin('recruteur');
header('Content-Type: application/json');
try {
    $rows = db()->query("
        SELECT c.id, c.nom, c.prenom, c.email, c.ville, c.telephone, c.annees_exp, c.competences, c.intitule_poste,
               cv.id AS cv_id, cv.nom_fichier
        FROM candidats c
        LEFT JOIN cv_files cv ON cv.candidat_id = c.id
          AND cv.id = (SELECT MAX(cv2.id) FROM cv_files cv2 WHERE cv2.candidat_id = c.id)
        ORDER BY c.id DESC LIMIT 200
    ")->fetchAll();
    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
} catch(Exception $e) { echo json_encode([]); }
