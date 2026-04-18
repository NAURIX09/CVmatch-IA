<?php
// ============================================================
//  config.php — Configuration centrale CVMatch IA v3
// ============================================================

// ── Base de données ──────────────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_NAME',    'cvmatch_ia');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

// ── API Python (FastAPI) ─────────────────────────────────────
define('PYTHON_API', 'http://localhost:8000');
define('API_KEY',    'cvmatch-secret-2025');

// ── Upload ───────────────────────────────────────────────────
define('UPLOAD_DIR',   __DIR__ . '/uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024);  // 5 Mo
define('ALLOWED_EXT',  ['pdf','docx','jpg','jpeg','png']);

// ── App ──────────────────────────────────────────────────────
define('APP_NAME', 'CVMatch IA');
define('APP_URL',  'http://localhost/cvmatch/public');

// ── Connexion PDO (singleton) ────────────────────────────────
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset='.DB_CHARSET,
                DB_USER, DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            die('<div style="font-family:sans-serif;padding:40px;color:#c62828">
                 <h2>Erreur de connexion MySQL</h2>
                 <p>'.$e->getMessage().'</p>
                 <p>Vérifiez vos paramètres dans config.php</p></div>');
        }
    }
    return $pdo;
}

// Alias pour compatibilité
function getDB(): PDO { return db(); }

// ── Session ──────────────────────────────────────────────────
function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start([
            'cookie_httponly' => true,
            'cookie_samesite' => 'Strict',
        ]);
    }
}

function isLoggedIn(): bool {
    startSession();
    return !empty($_SESSION['user_id']);
}

function requireLogin(string $role = ''): void {
    startSession();
    if (empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }
    if ($role && ($_SESSION['role'] ?? '') !== $role) { header('Location: index.php'); exit; }
}

function logout(): void {
    startSession();
    $_SESSION = [];
    session_destroy();
    header('Location: index.php'); exit;
}

// ── Appel API Python ─────────────────────────────────────────
function callAPI(string $route, array $data = [], string $method = 'POST'): ?array {
    $url = PYTHON_API . $route;
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => ($method === 'POST'),
        CURLOPT_POSTFIELDS     => json_encode($data, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-API-Key: ' . API_KEY,
        ],
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($err || $code !== 200 || !$resp) {
        error_log("CVMatch API [$route] HTTP $code : $err");
        return null;
    }
    return json_decode($resp, true);
}

// ── Toast helper (PHP → JS) ──────────────────────────────────
function jsToast(string $msg, string $type = ''): string {
    $msg = addslashes(htmlspecialchars($msg));
    return "<script>document.addEventListener('DOMContentLoaded',()=>showToast('$msg','$type'));</script>";
}

// ── Upload sécurisé ──────────────────────────────────────────
function uploadCV(array $file, int $userId): array {
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXT)) {
        return ['ok'=>false, 'error'=>'Format non autorisé (PDF, DOCX, JPG, PNG)'];
    }
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['ok'=>false, 'error'=>'Fichier trop volumineux (max 5 Mo)'];
    }
    $nom = 'cv_' . $userId . '_' . time() . '.' . $ext;
    $dest = UPLOAD_DIR . $nom;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['ok'=>false, 'error'=>'Erreur lors de l\'enregistrement'];
    }
    return ['ok'=>true, 'nom'=>$nom, 'ext'=>$ext];
}
