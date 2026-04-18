<?php
require_once 'config.php';
startSession();
if (isLoggedIn() && $_SESSION['role']==='candidat') { header('Location: dashboard_candidat.php'); exit; }

$error = $success = '';
$tab   = $_GET['tab'] ?? 'register';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action = $_POST['action'] ?? '';

    // ── CONNEXION ────────────────────────────────────────────
    if ($action==='login') {
        $email = trim($_POST['email'] ?? '');
        $pass  = $_POST['password'] ?? '';
        if (!$email || !$pass) {
            $error = 'Veuillez remplir tous les champs.'; $tab='login';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Adresse email invalide.'; $tab='login';
        } else {
            $stmt = db()->prepare("SELECT id,nom,prenom,password_hash FROM candidats WHERE email=? LIMIT 1");
            $stmt->execute([$email]);
            $u = $stmt->fetch();
            if ($u && password_verify($pass, $u['password_hash'])) {
                $_SESSION['user_id'] = $u['id'];
                $_SESSION['role']    = 'candidat';
                $_SESSION['nom']     = $u['prenom'].' '.$u['nom'];
                header('Location: dashboard_candidat.php'); exit;
            } else { $error='Email ou mot de passe incorrect.'; $tab='login'; }
        }
    }

    // ── INSCRIPTION ──────────────────────────────────────────
    if ($action==='register') {
        $prenom = trim($_POST['prenom'] ?? '');
        $nom    = trim($_POST['nom']    ?? '');
        $email  = trim($_POST['email']  ?? '');
        $pass   = $_POST['password']    ?? '';
        $pass2  = $_POST['password2']   ?? '';
        if (!$prenom||!$nom||!$email||!$pass||!$pass2) {
            $error='Tous les champs sont obligatoires.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error='Email invalide.';
        } elseif (strlen($pass)<6) {
            $error='Mot de passe trop court (min. 6 caractères).';
        } elseif ($pass!==$pass2) {
            $error='Les mots de passe ne correspondent pas.';
        } else {
            $check = db()->prepare("SELECT id FROM candidats WHERE email=? LIMIT 1");
            $check->execute([$email]);
            if ($check->fetch()) {
                $error='Cette adresse email est déjà utilisée.';
            } else {
                $hash = password_hash($pass, PASSWORD_BCRYPT);
                $stmt = db()->prepare("INSERT INTO candidats (nom,prenom,email,password_hash) VALUES(?,?,?,?)");
                $stmt->execute([$nom,$prenom,$email,$hash]);
                $id = (int)db()->lastInsertId();
                $_SESSION['user_id'] = $id;
                $_SESSION['role']    = 'candidat';
                $_SESSION['nom']     = $prenom.' '.$nom;
                header('Location: dashboard_candidat.php'); exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CVMatch IA — Espace Candidat</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    body{display:flex;flex-direction:column;min-height:100vh;}
    .page-wrap{flex:1;display:flex;align-items:center;justify-content:center;padding:40px 24px;background:var(--surface);}
    .form-box{width:100%;max-width:460px;animation:fadeInUp .4s ease;}
    .back-link{display:flex;align-items:center;gap:6px;font-size:12px;color:var(--muted);margin-bottom:20px;transition:color .2s;}
    .back-link:hover{color:var(--gold);}
    .page-title{font-family:'Orbitron',sans-serif;font-size:24px;font-weight:800;color:var(--ink);text-align:center;margin-bottom:4px;}
    .page-sub{font-size:14px;color:var(--muted);text-align:center;margin-bottom:24px;}
    .page-sub span{color:var(--gold);font-weight:600;}
    footer{background:var(--ink);padding:14px;text-align:center;font-size:12px;color:rgba(255,255,255,.5);}
  </style>
</head>
<body>

<nav class="navbar">
  <a href="index.php" class="brand">CV<span class="accent">match</span> ia</a>
</nav>

<div class="page-wrap">
  <div class="form-box">
    <a href="index.php" class="back-link">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
      Retour à l'accueil
    </a>

    <div class="page-title">Espace Candidat</div>
    <div class="page-sub">Rejoignez <span>CVMatch IA</span> et soyez visible</div>

    <?php if ($error): ?>
    <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="auth-tabs">
      <button class="auth-tab <?= $tab==='register'?'active':'' ?>" onclick="switchTab('register')">S'inscrire</button>
      <button class="auth-tab <?= $tab==='login'?'active':'' ?>" onclick="switchTab('login')">Se connecter</button>
    </div>

    <!-- INSCRIPTION -->
    <div id="panel-register" <?= $tab!=='register'?'style="display:none"':'' ?>>
      <form method="POST">
        <input type="hidden" name="action" value="register">
        <div class="form-row">
          <div class="form-group">
            <label>Prénom *</label>
            <input class="form-control" type="text" name="prenom" placeholder="Kouamé" value="<?= htmlspecialchars($_POST['prenom']??'') ?>" required>
          </div>
          <div class="form-group">
            <label>Nom *</label>
            <input class="form-control" type="text" name="nom" placeholder="Assi" value="<?= htmlspecialchars($_POST['nom']??'') ?>" required>
          </div>
        </div>
        <div class="form-group">
          <label>Email *</label>
          <input class="form-control" type="email" name="email" placeholder="vous@exemple.com" value="<?= htmlspecialchars($_POST['email']??'') ?>" required>
        </div>
        <div class="form-group">
          <label>Mot de passe * (min. 6 caractères)</label>
          <div class="pw-wrap">
            <input class="form-control" type="password" name="password" id="reg-pw" placeholder="••••••••" required>
            <button type="button" class="pw-eye" onclick="togglePw('reg-pw',this)">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>
        <div class="form-group">
          <label>Confirmer le mot de passe *</label>
          <div class="pw-wrap">
            <input class="form-control" type="password" name="password2" id="reg-pw2" placeholder="••••••••" required>
            <button type="button" class="pw-eye" onclick="togglePw('reg-pw2',this)">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>
        <button type="submit" class="btn btn-gold" style="width:100%;justify-content:center;padding:13px">Créer mon compte →</button>
      </form>
    </div>

    <!-- CONNEXION -->
    <div id="panel-login" <?= $tab!=='login'?'style="display:none"':'' ?>>
      <form method="POST">
        <input type="hidden" name="action" value="login">
        <div class="form-group">
          <label>Email</label>
          <input class="form-control" type="email" name="email" placeholder="vous@exemple.com" required>
        </div>
        <div class="form-group">
          <label>Mot de passe</label>
          <div class="pw-wrap">
            <input class="form-control" type="password" name="password" id="log-pw" placeholder="••••••••" required>
            <button type="button" class="pw-eye" onclick="togglePw('log-pw',this)">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:13px;margin-bottom:10px">Se connecter →</button>
        <button type="button" class="btn btn-ghost" style="width:100%;justify-content:center;padding:12px" onclick="switchTab('register')">Créer un compte gratuitement</button>
      </form>
    </div>
  </div>
</div>

<footer>© <?= date('Y') ?> CVMatch IA</footer>

<script>
function switchTab(t){
  document.getElementById('panel-register').style.display=t==='register'?'block':'none';
  document.getElementById('panel-login').style.display=t==='login'?'block':'none';
  document.querySelectorAll('.auth-tab').forEach((b,i)=>b.classList.toggle('active',(t==='register'&&i===0)||(t==='login'&&i===1)));
}
function togglePw(id,btn){
  const inp=document.getElementById(id);
  const show=inp.type==='password';
  inp.type=show?'text':'password';
  btn.style.color=show?'var(--gold)':'var(--muted)';
}
</script>
</body>
</html>
