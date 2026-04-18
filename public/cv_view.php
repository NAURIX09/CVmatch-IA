<?php
// cv_view.php — Servir un CV de façon sécurisée
require_once 'config.php';
startSession();
if (empty($_SESSION['user_id'])) { http_response_code(403); exit('Accès refusé.'); }
$file = str_replace(['..','\\',"\0"],'', $_GET['file'] ?? '');
$file = ltrim($file, '/');
if (!str_starts_with($file, 'uploads/')) { http_response_code(400); exit('Chemin invalide.'); }
$fullPath = realpath(__DIR__ . '/' . $file);
$uploadsReal = realpath(UPLOAD_DIR);
if (!$fullPath || !str_starts_with($fullPath, $uploadsReal) || !is_file($fullPath)) {
    http_response_code(404); exit('Fichier introuvable.');
}
$ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
$mimes = ['pdf'=>'application/pdf','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png'];
$mime = $mimes[$ext] ?? 'application/octet-stream';
$disp = in_array($ext, ['pdf','jpg','jpeg','png']) ? 'inline' : 'attachment';
header('Content-Type: '.$mime);
header('Content-Length: '.filesize($fullPath));
header('Content-Disposition: '.$disp.'; filename="'.basename($fullPath).'"');
header('Cache-Control: private, max-age=3600');
readfile($fullPath); exit;
