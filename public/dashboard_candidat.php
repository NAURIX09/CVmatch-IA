<?php
require_once 'config.php';
requireLogin('candidat');

$user_id   = (int)$_SESSION['user_id'];
$nom_complet = $_SESSION['nom'] ?? 'Candidat';
$initiales = implode('', array_map(fn($w)=>strtoupper($w[0]??''), array_filter(explode(' ', $nom_complet))));
$initiales = substr($initiales, 0, 2);

// Charger le profil
try {
    $stmt = db()->prepare("SELECT * FROM candidats WHERE id=? LIMIT 1");
    $stmt->execute([$user_id]);
    $profil = $stmt->fetch() ?: [];
} catch(Exception $e) { $profil = []; }

$tags = array_filter(array_map('trim', explode(',', $profil['competences'] ?? '')));

// Charger le dernier CV depuis cv_files
try {
    $stmt_cv = db()->prepare("SELECT * FROM cv_files WHERE candidat_id=? ORDER BY id DESC LIMIT 1");
    $stmt_cv->execute([$user_id]);
    $cv_actuel = $stmt_cv->fetch() ?: [];
} catch(Exception $e) { $cv_actuel = []; }

// Compétences extraites par l'IA depuis le CV
$tags_ia = [];
if (!empty($cv_actuel['competences'])) {
    $decoded = json_decode($cv_actuel['competences'], true);
    if (is_array($decoded)) $tags_ia = $decoded;
}
// Compétences manuelles du profil (fusionnées avec IA si pas encore sauvegardées)
$tags = array_filter(array_map('trim', explode(',', $profil['competences'] ?? '')));
// Si pas de compétences manuelles mais IA en a extrait, on utilise les IA
if (empty($tags) && !empty($tags_ia)) $tags = $tags_ia;
try {
    $nb_messages = db()->prepare("SELECT COUNT(*) FROM messages WHERE to_user_id=? AND lu=0");
    $nb_messages->execute([$user_id]);
    $nb_messages = (int)$nb_messages->fetchColumn();
} catch(Exception $e) { $nb_messages = 0; }

// Traitement POST
$success = $error = '';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action = $_POST['action'] ?? '';

    // Mise à jour profil
    if ($action==='update_profil') {
        $ville   = trim($_POST['ville']   ?? '');
        $tel     = trim($_POST['telephone']?? '');
        $exp     = (int)($_POST['annees_exp'] ?? 0);
        $comps   = trim($_POST['competences'] ?? '');
        $intitule= trim($_POST['intitule_poste'] ?? '');
        try {
            db()->prepare("UPDATE candidats SET ville=?,telephone=?,annees_exp=?,competences=?,intitule_poste=? WHERE id=?")
                ->execute([$ville,$tel,$exp,$comps,$intitule,$user_id]);
            $success = 'Profil mis à jour avec succès !';
            header('Location: dashboard_candidat.php?success=1'); exit;
        } catch(Exception $e) { $error = 'Erreur lors de la mise à jour.'; }
    }

    // Upload CV
    if ($action==='upload_cv' && isset($_FILES['cv_file'])) {
        $upload = uploadCV($_FILES['cv_file'], $user_id);
        if ($upload['ok']) {
            try {
                // Insérer dans cv_files pour que Python puisse indexer
                $rel = 'cvmatch/public/uploads/' . $upload['nom'];
                db()->prepare("
                    INSERT INTO cv_files (candidat_id, nom_fichier, chemin_fichier, type_fichier, depose_le)
                    VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
                    ON DUPLICATE KEY UPDATE nom_fichier=VALUES(nom_fichier), type_fichier=VALUES(type_fichier), depose_le=CURRENT_TIMESTAMP
                ")->execute([$user_id, $upload['nom'], $rel, $upload['ext']]);
                // Appel API Python pour indexer (extraction texte + embedding + compétences)
                callAPI('/candidats/upload/'.$user_id, []);
                header('Location: dashboard_candidat.php?success=cv'); exit;
            } catch(Exception $e) { $error='Erreur base de données : '.$e->getMessage(); }
        } else { $error = $upload['error']; }
    }
}

$success_msg = match($_GET['success']??'') {
    '1'  => 'Profil mis à jour !',
    'cv' => 'CV uploadé et indexé avec succès !',
    default => '',
};
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CVMatch IA — Mon espace</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    body{display:flex;}
    .add-skill-row{display:flex;gap:8px;margin-top:8px;}
  </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sb-brand">
    <img src="assets/css/PHOTO/logo.png" alt="logo" style="width:32px;height:32px;border-radius:50%;object-fit:cover;margin-right:8px;vertical-align:middle;">
    CV<span class="accent">match</span> <span class="ver">ia</span>
  </div>
  <div class="sb-section">Menu</div>
  <a href="#" class="sb-item active" onclick="showPage('profil',this)">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
    <span>Mon profil</span>
  </a>
  <a href="#" class="sb-item" onclick="showPage('cv',this)">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
    <span>Mon CV</span>
  </a>
  <a href="#" class="sb-item" onclick="showPage('messages',this)">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
    <span>Messages</span>
    <?php if ($nb_messages): ?><span class="sb-badge"><?= $nb_messages ?></span><?php endif; ?>
  </a>
  <div class="sb-section">Compte</div>
  <a href="logout.php" class="sb-item">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
    <span>Déconnexion</span>
  </a>
  <div class="sb-bottom">
    <div class="sb-user">
      <div class="sb-avatar"><?= htmlspecialchars($initiales) ?></div>
      <div>
        <div class="sb-uname"><?= htmlspecialchars($nom_complet) ?></div>
        <div class="sb-urole">Candidat</div>
      </div>
    </div>
  </div>
</aside>

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <div class="topbar-title" id="topbarTitle">Mon profil</div>
    <div class="topbar-right">
      <span class="badge <?= ($cv_actuel['nom_fichier'] ?? null)?'badge-green':'badge-gray' ?>">
        <?= ($cv_actuel['nom_fichier'] ?? null)?'✓ CV indexé':'Pas de CV' ?>
      </span>
    </div>
  </div>

  <!-- PAGE PROFIL -->
  <div class="content" id="page-profil">
    <?php if ($success_msg): ?>
    <div class="alert alert-success">✓ <?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Welcome banner -->
    <div class="welcome-banner">
      <div>
        <div class="wb-greeting">Bonjour, <span class="accent"><?= htmlspecialchars(explode(' ',$nom_complet)[0]) ?></span></div>
        <div class="wb-sub">Votre profil est visible par les recruteurs CVMatch IA</div>
      </div>
      <div class="wb-status">
        <div class="status-dot"></div>
        <div class="status-text"><?= ($cv_actuel['nom_fichier'] ?? null)?'Profil actif · Indexé':'Déposez votre CV' ?></div>
      </div>
    </div>

    <!-- Stats -->
    <div class="grid-3">
      <div class="stat-card">
        <div class="stat-card-label">Score moyen obtenu</div>
        <div class="stat-card-val">—<span class="unit">%</span></div>
        <div class="stat-card-delta">Visible après recherche</div>
      </div>
      <div class="stat-card">
        <div class="stat-card-label">Compétences renseignées</div>
        <div class="stat-card-val"><?= count($tags_ia) ?: count($tags) ?></div>
        <div class="stat-card-delta">Extraites par l'IA</div>
      </div>
      <div class="stat-card">
        <div class="stat-card-label">Messages non lus</div>
        <div class="stat-card-val"><?= $nb_messages ?></div>
        <?php if ($nb_messages): ?><div class="stat-card-delta"><?= $nb_messages ?> nouveaux</div><?php endif; ?>
      </div>
    </div>

    <!-- Infos + Compétences -->
    <div class="grid-2">
      <div class="card">
        <div class="panel-title">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="1.5" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
          Informations personnelles
        </div>
        <form method="POST" id="formProfil">
          <input type="hidden" name="action" value="update_profil">
          <div class="form-row">
            <div class="form-group"><label>Prénom</label>
              <input class="form-control" type="text" value="<?= htmlspecialchars($profil['prenom']??'') ?>" disabled style="opacity:.6"></div>
            <div class="form-group"><label>Nom</label>
              <input class="form-control" type="text" value="<?= htmlspecialchars($profil['nom']??'') ?>" disabled style="opacity:.6"></div>
          </div>
          <div class="form-row">
            <div class="form-group"><label>Ville</label>
              <input class="form-control" type="text" name="ville" placeholder="Abidjan" value="<?= htmlspecialchars($profil['ville']??'') ?>"></div>
            <div class="form-group"><label>Téléphone</label>
              <input class="form-control" type="tel" name="telephone" placeholder="+225 07 00 00 00" value="<?= htmlspecialchars($profil['telephone']??'') ?>"></div>
          </div>
          <div class="form-row">
            <div class="form-group"><label>Intitulé du poste</label>
              <input class="form-control" type="text" name="intitule_poste" placeholder="Développeur Full Stack" value="<?= htmlspecialchars($profil['intitule_poste']??'') ?>"></div>
            <div class="form-group"><label>Années d'expérience</label>
              <select class="form-control" name="annees_exp">
                <?php foreach([0,1,2,3,5,7,10] as $y): ?>
                <option value="<?=$y?>" <?=($profil['annees_exp']??0)==$y?'selected':''?>><?=$y < 1?'Moins de 1 an':$y.' an(s)'?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <input type="hidden" name="competences" id="competencesInput" value="<?= htmlspecialchars($profil['competences']??'') ?>">
          <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:12px">Enregistrer</button>
        </form>
      </div>

      <div class="card">
        <div class="panel-title">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="1.5" stroke-linecap="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
          Compétences
        </div>

        <?php if (!empty($tags_ia)): ?>
        <div style="margin-bottom:14px;padding:12px;background:var(--primary-bg);border:1px solid var(--primary-border);border-radius:var(--r-md)">
          <div style="font-size:11px;font-weight:600;color:var(--ink2);text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px">
            ✦ Compétences extraites par l'IA
          </div>
          <div style="font-size:11px;color:var(--muted);margin-bottom:10px">Ces informations ont été extraites automatiquement depuis votre CV.</div>
          <div style="font-size:11px;color:var(--muted);margin-bottom:8px;font-weight:500">Compétences techniques</div>
          <div class="tags-wrap" style="margin-bottom:10px">
            <?php foreach ($tags_ia as $t): ?>
            <span class="skill-tag"><?= htmlspecialchars($t) ?></span>
            <?php endforeach; ?>
          </div>
          <?php if (!empty($cv_actuel['annees_exp'])): ?>
          <div style="font-size:11px;color:var(--muted);font-weight:500;margin-top:8px">Expérience déclarée</div>
          <div style="font-size:15px;font-weight:700;color:var(--ink);margin-top:2px"><?= (int)$cv_actuel['annees_exp'] ?> an<?= (int)$cv_actuel['annees_exp'] > 1 ? 's' : '' ?></div>
          <?php endif; ?>
        </div>
        <?php else: ?>
        <div style="font-size:13px;color:var(--muted);padding:20px;text-align:center">
          Déposez votre CV pour que l'IA extraie vos compétences automatiquement.
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- PAGE CV -->
  <div class="content" id="page-cv" style="display:none">
    <div style="max-width:600px">
      <div style="font-family:'Orbitron',sans-serif;font-size:18px;font-weight:800;margin-bottom:4px">Mon Curriculum Vitae</div>
      <div style="font-size:13px;color:var(--muted);margin-bottom:20px">Déposez votre CV pour être visible dans les recherches IA</div>

      <?php if ($cv_actuel['nom_fichier'] ?? null): ?>
      <div class="card" style="margin-bottom:16px">
        <div class="panel-title">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="1.5"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          CV actuel
        </div>
        <div style="display:flex;align-items:center;gap:12px;padding:14px;background:var(--green-bg);border:1px solid var(--green-border);border-radius:var(--r-md)">
          <div class="cv-file-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.5" stroke-linecap="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
          <div>
            <div style="font-size:13px;font-weight:600;color:var(--green)"><?= htmlspecialchars($cv_actuel['nom_fichier'] ?? '') ?></div>
            <div style="font-size:11px;color:var(--muted);margin-top:1px">Indexé · Embedding généré</div>
          </div>
          <a href="cv_view.php?file=uploads/<?= urlencode($cv_actuel['nom_fichier'] ?? '') ?>" target="_blank" class="btn btn-ghost btn-xs" style="margin-left:auto">Voir ↗</a>
        </div>
      </div>
      <?php endif; ?>

      <div class="card">
        <div class="panel-title">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="1.5" stroke-linecap="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          <?= ($cv_actuel['nom_fichier'] ?? null) ? 'Remplacer le CV' : 'Déposer mon CV' ?>
        </div>
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="action" value="upload_cv">
          <div class="upload-zone" id="dropZone" onclick="document.getElementById('cvFile').click()">
            <input type="file" id="cvFile" name="cv_file" accept=".pdf,.docx,.jpg,.jpeg,.png" onchange="handleFile(this)">
            <div class="upload-icon"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="1.2" stroke-linecap="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg></div>
            <div class="upload-title" id="uploadTitle">Glissez votre CV ici</div>
            <div class="upload-sub">ou cliquez pour parcourir</div>
            <div class="upload-formats">
              <span class="upload-fmt">PDF</span><span class="upload-fmt">DOCX</span>
              <span class="upload-fmt">JPG</span><span class="upload-fmt">PNG</span>
              <span class="upload-fmt">Max 5 Mo</span>
            </div>
          </div>
          <button type="submit" class="btn btn-gold" id="uploadBtn" style="width:100%;justify-content:center;padding:12px;margin-top:14px;display:none">
            Uploader et indexer →
          </button>
        </form>
        <div style="margin-top:16px;padding:12px;background:var(--primary-bg);border-radius:var(--r-md);font-size:12px;color:var(--ink2);line-height:1.6">
          <strong>Après l'upload</strong>, notre IA extraira automatiquement vos compétences, générera un embedding vectoriel et rendra votre profil visible dans les recherches des recruteurs.
        </div>
      </div>
    </div>
  </div>

  <!-- PAGE MESSAGES -->
  <div class="content" id="page-messages" style="display:none">
    <div style="font-family:'Orbitron',sans-serif;font-size:18px;font-weight:800;margin-bottom:20px">Mes messages</div>
    <div id="messagesList">
      <div style="text-align:center;padding:32px;color:var(--muted)">Chargement...</div>
    </div>
  </div>
</div>

<div id="toast"></div>

<script>
// ── Pages ─────────────────────────────────────────────────────
function showPage(page, el) {
  document.querySelectorAll('[id^="page-"]').forEach(p=>p.style.display='none');
  document.getElementById('page-'+page).style.display='flex';
  document.querySelectorAll('.sb-item').forEach(b=>b.classList.remove('active'));
  if (el) el.classList.add('active');
  const titles = {profil:'Mon profil', cv:'Mon CV', messages:'Mes messages'};
  document.getElementById('topbarTitle').textContent = titles[page] || page;
  if (page==='messages') loadMessages();
}

// ── Upload ────────────────────────────────────────────────────
function handleFile(input) {
  if (!input.files.length) return;
  document.getElementById('uploadTitle').textContent = '✓ ' + input.files[0].name;
  document.getElementById('uploadBtn').style.display='flex';
}

const dz = document.getElementById('dropZone');
dz.addEventListener('dragover', e=>{e.preventDefault();dz.classList.add('drag-over');});
dz.addEventListener('dragleave', ()=>dz.classList.remove('drag-over'));
dz.addEventListener('drop', e=>{
  e.preventDefault(); dz.classList.remove('drag-over');
  const f = e.dataTransfer.files[0];
  if (f) { document.getElementById('cvFile').files = e.dataTransfer.files; handleFile({files:[f]}); }
});

// ── Messages ──────────────────────────────────────────────────
async function loadMessages() {
  try {
    const resp = await fetch('get_messages.php');
    const data = await resp.json();
    if (!data.length) {
      document.getElementById('messagesList').innerHTML = '<div style="text-align:center;padding:32px;color:var(--muted)">Aucun message reçu</div>';
      return;
    }
    document.getElementById('messagesList').innerHTML = data.map(m=>`
      <div class="card card-hover" style="margin-bottom:10px;${!m.lu?'border-left:3px solid var(--primary)':''}">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
          <div style="font-weight:600;font-size:13px">${m.from_nom||'Recruteur'}</div>
          <div style="font-size:11px;color:var(--muted)">${m.envoye_le}</div>
        </div>
        ${m.sujet?`<div style="font-size:13px;font-weight:500;margin-bottom:4px">${m.sujet}</div>`:''}
        <div style="font-size:13px;color:var(--ink2);line-height:1.5">${m.corps}</div>
      </div>`).join('');
  } catch(e) {
    document.getElementById('messagesList').innerHTML = '<div style="text-align:center;color:var(--red);padding:24px">Erreur de chargement</div>';
  }
}

// ── Toast ─────────────────────────────────────────────────────
function showToast(msg, type='') {
  const t = document.getElementById('toast');
  t.textContent=msg; t.className='show '+type;
  setTimeout(()=>t.className='', 3000);
}
</script>
</body>
</html>
