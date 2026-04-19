<?php
require_once 'config.php';
requireLogin('recruteur');

$nom        = $_SESSION['nom'] ?? 'Recruteur';
$initiales  = implode('', array_map(fn($w)=>strtoupper($w[0]), array_filter(explode(' ', $nom))));
$initiales  = substr($initiales, 0, 2);
$entreprise = $_SESSION['entreprise'] ?? '';

// Stats rapides
try {
    $nb_candidats = db()->query("SELECT COUNT(DISTINCT candidat_id) FROM cv_files WHERE candidat_id IS NOT NULL")->fetchColumn();
    $nb_sessions  = db()->prepare("SELECT COUNT(DISTINCT session_id) FROM conversation_history WHERE recruteur_id=?");
    $nb_sessions->execute([$_SESSION['user_id']]);
    $nb_sessions  = $nb_sessions->fetchColumn();
    $nb_cv_analyses = db()->query("SELECT COUNT(*) FROM cv_files WHERE texte_brut IS NOT NULL")->fetchColumn();
    $nb_cv_externes = db()->query("SELECT COUNT(*) FROM cv_files WHERE candidat_id IS NULL")->fetchColumn();
} catch(Exception $e) {
    $nb_candidats = 0; $nb_sessions = 0; $nb_cv_analyses = 0; $nb_cv_externes = 0;
}

// Historique des sessions
$sessions = [];
try {
    $stmt = db()->prepare("SELECT session_id, MAX(requete_texte) as requete, MIN(cree_le) as debut, COUNT(*) as nb
                           FROM conversation_history WHERE recruteur_id=?
                           GROUP BY session_id ORDER BY debut DESC LIMIT 10");
    $stmt->execute([$_SESSION['user_id']]);
    $sessions = $stmt->fetchAll();
} catch(Exception $e) {}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CVMatch IA — Dashboard Recruteur</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    body{display:flex;}
    .results-header{display:flex;align-items:center;justify-content:space-between;}
    .results-title{font-family:'Orbitron',sans-serif;font-size:15px;font-weight:700;}
    .results-sub{font-size:12px;color:var(--muted);margin-top:2px;}
    .action-btn{padding:7px 14px;background:var(--white);border:1px solid var(--border);border-radius:var(--r-md);font-size:12px;cursor:pointer;color:var(--ink2);transition:all .15s;display:inline-flex;align-items:center;gap:5px;}
    .action-btn:hover{border-color:var(--gold);color:var(--gold);}
    .history-item{display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:var(--r-md);border:1px solid var(--border);background:var(--surface);margin-bottom:8px;cursor:pointer;transition:all .15s;}
    .history-item:hover{border-color:var(--gold);background:var(--gold-bg);}
    .history-icon{width:32px;height:32px;border-radius:var(--r-sm);background:var(--ink);display:flex;align-items:center;justify-content:center;flex-shrink:0;}
    .history-query{font-size:13px;font-weight:500;color:var(--ink);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .history-meta{font-size:11px;color:var(--muted);margin-top:1px;}
    .empty-state{text-align:center;padding:40px;color:var(--muted);}
    .empty-icon{margin-bottom:12px;opacity:.3;}
  </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sb-brand">
    <img src="assets/css/PHOTO/logo.png" alt="logo" style="width:32px;height:32px;border-radius:50%;object-fit:cover;margin-right:8px;vertical-align:middle;">
    CV<span class="accent">match</span> <span class="ver">ia</span>
  </div>

  <div class="sb-section">Recherche</div>
  <a href="#" class="sb-item active" onclick="showPage('recherche')">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
    <span>Recherche IA</span>
  </a>
  <a href="#" class="sb-item" onclick="showPage('historique')">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
    <span>Historique</span>
  </a>
  <a href="#" class="sb-item" onclick="showPage('candidats')">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
    <span>Candidats</span>
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
        <div class="sb-uname"><?= htmlspecialchars($nom) ?></div>
        <div class="sb-urole"><?= $entreprise ? htmlspecialchars($entreprise) : 'Recruteur' ?></div>
      </div>
    </div>
  </div>
</aside>

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <div class="topbar-title" id="topbarTitle">Recherche IA</div>
    <div class="topbar-right">
      <div class="notif-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg><div class="notif-dot"></div></div>
    </div>
  </div>

  <!-- PAGE RECHERCHE -->
  <div class="content" id="page-recherche">

    <div class="grid-4">
      <div class="stat-card"><div class="stat-card-label">Candidats indexés</div><div class="stat-card-val"><?= $nb_candidats ?></div></div>
      <div class="stat-card"><div class="stat-card-label">Sessions IA</div><div class="stat-card-val"><?= $nb_sessions ?></div></div>
      <div class="stat-card"><div class="stat-card-label">Temps moyen</div><div class="stat-card-val">10<span class="unit">s</span></div></div>
      <div class="stat-card"><div class="stat-card-label">CV externes</div><div class="stat-card-val"><?= $nb_cv_externes ?></div></div>
    </div>

    <div class="search-area">
      <div class="search-label">Décrivez le profil en langage naturel</div>
      <div class="search-box">
        <div class="search-input-wrap">
          <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <input class="search-input" id="searchInput" type="text" placeholder="Ex: Développeur PHP senior, MySQL, 3 ans exp., Abidjan..." onkeydown="if(event.key==='Enter')runSearch()">
        </div>
        <button class="search-btn" id="searchBtn" onclick="runSearch()">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
          Analyser avec l'IA
        </button>
      </div>
      <div class="search-hints">
        <span class="search-hint" onclick="setHint(this)">Développeur PHP · MySQL · 3 ans · Abidjan</span>
        <span class="search-hint" onclick="setHint(this)">Data Analyst · Python · Power BI · senior</span>
        <span class="search-hint" onclick="setHint(this)">Chef de projet IT · 5 ans d'expérience</span>
        <span class="search-hint" onclick="setHint(this)">Comptable · Excel · 2 ans exp.</span>
      </div>
    </div>

    <div id="loadingState" style="display:none;text-align:center;padding:32px">
      <div style="width:36px;height:36px;border:3px solid var(--gold-bg);border-top-color:var(--gold);border-radius:50%;animation:spin 1s linear infinite;margin:0 auto 12px"></div>
      <div style="font-family:'Orbitron',sans-serif;font-weight:600;color:var(--ink)">Analyse en cours...</div>
      <div style="font-size:12px;color:var(--muted);margin-top:4px">Llama 3 via Groq · Matching multicritères</div>
    </div>

    <div id="resultsSection" style="display:none">
      <div class="results-header" style="margin-bottom:12px">
        <div>
          <div class="results-title" id="resultsTitle">Résultats</div>
          <div class="results-sub">Triés par score de matching · Analysé par Llama 3</div>
        </div>
        <div style="display:flex;gap:8px">
          <button class="action-btn" onclick="exportCSV()">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Exporter
          </button>
        </div>
      </div>
      <div id="cardsGrid"></div>

      <!-- CHAT IA -->
      <div class="chat-panel" style="margin-top:16px">
        <div class="chat-header">
          <div class="chat-icon">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--gold)" stroke-width="1.5" stroke-linecap="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
          </div>
          <div>
            <div class="chat-title">Agent IA conversationnel</div>
            <div class="chat-sub">Posez des questions sur les résultats en français</div>
          </div>
          <div style="margin-left:auto;width:7px;height:7px;border-radius:50%;background:#4caf50;animation:pulse 2s infinite;"></div>
        </div>
        <div class="chat-messages" id="chatMessages">
          <div class="chat-msg ai"><div class="chat-bubble">Bonjour ! J'ai analysé les candidats. Vous pouvez me demander de filtrer, comparer ou expliquer les scores. Que souhaitez-vous savoir ?</div></div>
        </div>
        <div class="chat-input-row">
          <input class="chat-input" id="chatInput" placeholder="Ex: Montre-moi seulement ceux d'Abidjan..." onkeydown="if(event.key==='Enter')sendChat()">
          <button class="btn btn-gold btn-sm" onclick="sendChat()">Envoyer →</button>
        </div>
      </div>
    </div>

  </div>

  <!-- PAGE HISTORIQUE -->
  <div class="content" id="page-historique" style="display:none">
    <div style="font-family:'Orbitron',sans-serif;font-size:18px;font-weight:800;margin-bottom:4px">Historique des recherches</div>
    <div style="font-size:13px;color:var(--muted);margin-bottom:20px"><?= count($sessions) ?> session(s) enregistrée(s)</div>
    <?php if ($sessions): ?>
      <?php foreach ($sessions as $s): ?>
      <div class="history-item" onclick="reloadSession('<?= htmlspecialchars($s['session_id']) ?>')">
        <div class="history-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--gold)" stroke-width="1.5" stroke-linecap="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg></div>
        <div style="flex:1;min-width:0">
          <div class="history-query"><?= htmlspecialchars($s['requete'] ?: 'Recherche sans titre') ?></div>
          <div class="history-meta"><?= date('d/m/Y H:i', strtotime($s['debut'])) ?> · <?= $s['nb'] ?> messages</div>
        </div>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--muted)" stroke-width="1.5" stroke-linecap="round"><path d="M9 18l6-6-6-6"/></svg>
      </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="empty-state">
        <div class="empty-icon"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg></div>
        <div style="font-weight:600;margin-bottom:4px">Aucune recherche enregistrée</div>
        <div style="font-size:13px">Lancez votre première recherche IA</div>
      </div>
    <?php endif; ?>
  </div>

  <!-- PAGE CANDIDATS -->
  <div class="content" id="page-candidats" style="display:none">
    <div style="font-family:'Orbitron',sans-serif;font-size:18px;font-weight:800;margin-bottom:20px">Tous les candidats</div>
    <div id="allCandidats">
      <div style="text-align:center;padding:32px">
        <div style="font-size:13px;color:var(--muted)">Chargement...</div>
      </div>
    </div>
  </div>
</div>

<div id="toast"></div>

<!-- MODAL MESSAGE -->
<div class="modal-overlay" id="modalMessage">
  <div class="modal">
    <button class="modal-close" onclick="fermerModal()">×</button>
    <div class="modal-title">Envoyer un message</div>
    <div class="modal-sub">À : <span id="modalDestNom"></span></div>
    <input type="hidden" id="modalDestId">
    <div class="form-group">
      <label>Sujet</label>
      <input class="form-control" id="modalSujet" type="text" placeholder="Objet du message">
    </div>
    <div class="form-group">
      <label>Message</label>
      <textarea class="form-control" id="modalCorps" rows="4" placeholder="Votre message..."></textarea>
    </div>
    <button class="btn btn-gold" style="width:100%;justify-content:center;padding:12px" onclick="envoyerMessage()">Envoyer →</button>
  </div>
</div>

<script>
const API_BASE = '<?= PYTHON_API ?>';
const API_KEY  = '<?= API_KEY ?>';
let currentResults = [];
let sessionId = null;
let chatHistory = [];

// ── Navigation ────────────────────────────────────────────────
function showPage(page) {
  document.querySelectorAll('[id^="page-"]').forEach(el => el.style.display='none');
  document.getElementById('page-'+page).style.display='flex';
  document.querySelectorAll('.sb-item').forEach(el => el.classList.remove('active'));
  event.currentTarget.classList.add('active');
  const titles = {recherche:'Recherche IA', historique:'Historique', candidats:'Candidats'};
  document.getElementById('topbarTitle').textContent = titles[page] || page;
  if (page==='candidats') loadAllCandidats();
}

// ── Recherche ─────────────────────────────────────────────────
function setHint(el) { document.getElementById('searchInput').value = el.textContent.trim(); }

async function runSearch() {
  const q = document.getElementById('searchInput').value.trim();
  if (!q) { showToast('Veuillez saisir une requête', 'error'); return; }

  const btn = document.getElementById('searchBtn');
  btn.disabled = true;
  btn.classList.add('loading');
  btn.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> Analyse...';

  document.getElementById('resultsSection').style.display='none';
  document.getElementById('loadingState').style.display='block';

  try {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 120000); // 2 min max

    const resp = await fetch(API_BASE + '/recherche', {
      method: 'POST',
      headers: {'Content-Type':'application/json','X-API-Key':API_KEY},
      body: JSON.stringify({texte_requete: q}),
      signal: controller.signal
    });
    clearTimeout(timer);
    const data = await resp.json();
    currentResults = data.profils || [];

    // Métier non reconnu — afficher l'erreur
    if (data.erreur) {
      document.getElementById('loadingState').style.display='none';
      document.getElementById('resultsSection').style.display='block';
      document.getElementById('cardsGrid').innerHTML =
        `<div style="padding:32px;text-align:center;background:var(--red-bg);border:1px solid var(--red-border);border-radius:var(--r-lg)">
          <div style="font-size:22px;margin-bottom:8px">❌</div>
          <div style="font-weight:700;color:var(--red);font-size:15px;margin-bottom:6px">Métier non reconnu</div>
          <div style="font-size:13px;color:var(--red)">${data.erreur}</div>
          <div style="font-size:12px;color:var(--muted);margin-top:10px">Essayez un intitulé de poste reconnu, ex: "Développeur PHP", "Comptable", "Chef de projet"</div>
        </div>`;
      document.getElementById('resultsTitle').textContent = '0 profil(s) trouvé(s)';
      showToast(data.erreur, 'error');
      btn.disabled = false; btn.classList.remove('loading');
      btn.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg> Analyser avec l\'IA';
      return;
    }

    renderResults(currentResults, q);

    // Nouvelle session chat
    const sessResp = await fetch(API_BASE + '/conversation/historique/nouvelle-session', {
      headers: {'X-API-Key': API_KEY}
    });
    const sessData = await sessResp.json();
    sessionId = sessData.session_id;
    chatHistory = [];
    document.getElementById('chatMessages').innerHTML =
      '<div class="chat-msg ai"><div class="chat-bubble">J\'ai analysé <strong>' + currentResults.length + ' candidat(s)</strong> pour votre recherche. Posez-moi vos questions !</div></div>';

  } catch(e) {
    showToast('Erreur API — vérifiez que le serveur Python est démarré', 'error');
    document.getElementById('loadingState').style.display='none';
  }

  btn.disabled = false;
  btn.classList.remove('loading');
  btn.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg> Analyser avec l\'IA';
}

// ── Rendu résultats ───────────────────────────────────────────
function scoreClass(s) {
  if (s >= 70) return 'score-high';
  if (s >= 45) return 'score-mid';
  return 'score-low';
}
function scoreLabel(s) {
  if (s >= 70) return 'Profil pertinent';
  if (s >= 45) return 'Profil partiel';
  return 'Profil non adapté';
}

function renderResults(profils, query) {
  document.getElementById('loadingState').style.display='none';
  document.getElementById('resultsSection').style.display='block';
  document.getElementById('resultsTitle').textContent = profils.length + ' profil(s) trouvé(s)';

  if (!profils.length) {
    document.getElementById('cardsGrid').innerHTML =
      `<div style="padding:32px;text-align:center;background:var(--primary-bg);border:1px solid var(--primary-border);border-radius:var(--r-lg)">
        <div style="font-size:28px;margin-bottom:12px">🔍</div>
        <div style="font-weight:700;color:var(--ink);font-size:15px;margin-bottom:8px">Aucun candidat disponible</div>
        <div style="font-size:13px;color:var(--ink2);margin-bottom:6px">Le métier <strong>${query}</strong> est reconnu mais aucun CV correspondant n'est disponible dans notre base.</div>
        <div style="font-size:12px;color:var(--muted)">Déposez des CV dans le dossier uploads/ ou invitez des candidats à s'inscrire.</div>
      </div>`;
    return;
  }

  const html = profils.map((p, i) => {
    const nom = (p.prenom||'')+' '+(p.nom||'');
    const initiales = nom.trim().split(' ').map(w=>w[0]||'').join('').substring(0,2).toUpperCase();
    const score = Math.round(p.score || 0);
    const detail = p._score_detail || {};
    const comps = Array.isArray(p.competences) ? p.competences.slice(0,5) : [];
    const tagsHtml = comps.map(c=>`<span class="skill-tag">${c}</span>`).join('');
    const avatarColors = [
      ['rgba(200,146,26,.18)','#8a6010'],
      ['rgba(26,60,110,.12)','#1A3C6E'],
      ['rgba(100,100,100,.12)','#555'],
      ['rgba(46,125,50,.12)','#2E7D32'],
    ];
    const [bg, color] = avatarColors[i % avatarColors.length];
    const cvLink = p.chemin_cv ? `<a href="cv_view.php?file=${encodeURIComponent(p.chemin_cv)}" target="_blank" class="btn btn-ghost btn-xs">CV</a>` : '';

    return `<div class="candidate-card ${i===0?'rank-1':''}" style="animation-delay:${i*0.08}s">
      <div class="c-rank ${i===0?'top':''}">#${i+1}</div>
      <div class="c-avatar" style="background:${bg};color:${color}">${initiales}</div>
      <div class="c-body">
        <div class="c-top">
          <div>
            <div class="c-name">${nom.trim() || 'Profil externe'}</div>
            <div class="c-meta">
              ${p.intitule_poste ? p.intitule_poste + ' · ' : ''}
              <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
              ${p.ville || '—'} · ${p.annees_exp || 0} an(s)
            </div>
          </div>
          <span class="score-pill ${scoreClass(score)}">${score}% · ${scoreLabel(score)}</span>
        </div>
        ${tagsHtml ? '<div class="tags-wrap" style="margin-bottom:8px">' + tagsHtml + '</div>' : ''}
        ${p.resume_ia ? '<div class="c-ia">' + p.resume_ia + '</div>' : ''}
        <div class="score-breakdown">
          <div class="score-mini"><div class="score-mini-label">Sémantique</div><div class="score-mini-val">${Math.round(detail.semantique||0)}%</div></div>
          <div class="score-mini"><div class="score-mini-label">Compétences</div><div class="score-mini-val">${Math.round(detail.competences||0)}%</div></div>
          <div class="score-mini"><div class="score-mini-label">Expérience</div><div class="score-mini-val">${Math.round(detail.experience||0)}%</div></div>
          <div class="score-mini"><div class="score-mini-label">Localisation</div><div class="score-mini-val">${Math.round(detail.localisation||0)}%</div></div>
        </div>
        <div class="c-actions">
          ${p.type_profil==='inscrit' ? `<button onclick="contactCandidat(${p.user_id||p.id}, '${(p.prenom||'')+' '+(p.nom||'')}')" class="btn btn-primary btn-xs">Contacter</button>` : ''}
          ${p.type_profil!=='inscrit' && p.email ? `<a href="mailto:${p.email}" class="btn btn-ghost btn-xs">Contacter</a>` : ''}
          ${cvLink}
        </div>
      </div>
    </div>`;
  }).join('');

  document.getElementById('cardsGrid').innerHTML = html;
}

// ── Chat IA ───────────────────────────────────────────────────
async function sendChat() {
  const inp = document.getElementById('chatInput');
  const msg = inp.value.trim();
  if (!msg) return;
  inp.value = '';

  const msgs = document.getElementById('chatMessages');
  msgs.innerHTML += `<div class="chat-msg user"><div class="chat-bubble">${msg}</div></div>`;
  const typingId = 'typing_' + Date.now();
  msgs.innerHTML += `<div class="chat-msg ai chat-typing show" id="${typingId}"><div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div></div>`;
  msgs.scrollTop = msgs.scrollHeight;

  chatHistory.push({role:'user', content:msg});

  try {
    const resp = await fetch(API_BASE + '/conversation/chat', {
      method:'POST',
      headers:{'Content-Type':'application/json','X-API-Key':API_KEY},
      body: JSON.stringify({
        message: msg,
        historique: chatHistory,
        derniers_profils: currentResults,
        session_id: sessionId,
        recruteur_id: <?= (int)$_SESSION['user_id'] ?>,
        requete_texte: document.getElementById('searchInput').value.trim() || null
      })
    });
    const data = await resp.json();
    document.getElementById(typingId)?.remove();
    const rep = data.reponse || 'Désolé, je n\'ai pas compris.';
    msgs.innerHTML += `<div class="chat-msg ai"><div class="chat-bubble">${rep}</div></div>`;
    chatHistory.push({role:'assistant', content:rep});
  } catch(e) {
    document.getElementById(typingId)?.remove();
    msgs.innerHTML += `<div class="chat-msg ai"><div class="chat-bubble" style="color:var(--red)">Erreur de connexion à l'API.</div></div>`;
  }
  msgs.scrollTop = msgs.scrollHeight;
}

// ── Candidats ─────────────────────────────────────────────────
async function loadAllCandidats() {
  try {
    const resp = await fetch('get_candidats.php');
    const data = await resp.json();
    if (!data.length) {
      document.getElementById('allCandidats').innerHTML = '<div class="empty-state"><div>Aucun candidat inscrit</div></div>';
      return;
    }
    const html = data.map(c => {
      const nom = (c.prenom||'')+' '+(c.nom||'');
      const initiales = nom.trim().split(' ').map(w=>w[0]||'').join('').substring(0,2).toUpperCase();
      return `<div class="candidate-card" style="margin-bottom:8px">
        <div class="c-avatar" style="background:rgba(200,146,26,.15);color:#8a6010">${initiales}</div>
        <div class="c-body">
          <div class="c-top">
            <div>
              <div class="c-name">${nom.trim()}</div>
              <div class="c-meta">${c.email} · ${c.ville||'—'} · ${c.annees_exp||'0'} an(s)</div>
            </div>
            ${c.cv_id ? '<span class="badge badge-green">CV déposé</span>' : '<span class="badge badge-gray">Pas de CV</span>'}
          </div>
          ${c.competences ? '<div class="tags-wrap">' + c.competences.split(',').slice(0,4).map(t=>`<span class="skill-tag">${t.trim()}</span>`).join('') + '</div>' : ''}
        </div>
      </div>`;
    }).join('');
    document.getElementById('allCandidats').innerHTML = html;
  } catch(e) {
    document.getElementById('allCandidats').innerHTML = '<div class="empty-state"><div>Erreur de chargement</div></div>';
  }
}

// ── Contacter candidat ────────────────────────────────────────
function contactCandidat(userId, nomCandidat) {
  document.getElementById('modalDestId').value = userId;
  document.getElementById('modalDestNom').textContent = nomCandidat || 'ce candidat';
  document.getElementById('modalMessage').classList.add('open');
}

function fermerModal() {
  document.getElementById('modalMessage').classList.remove('open');
  document.getElementById('modalSujet').value = '';
  document.getElementById('modalCorps').value = '';
}

async function envoyerMessage() {
  const userId = document.getElementById('modalDestId').value;
  const sujet  = document.getElementById('modalSujet').value.trim();
  const corps  = document.getElementById('modalCorps').value.trim();
  if (!sujet || !corps) { showToast('Remplissez tous les champs', 'error'); return; }

  try {
    const resp = await fetch('envoyer_message.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({to_user_id: userId, sujet, corps})
    });
    const data = await resp.json();
    if (data.ok) {
      showToast('Message envoyé !', 'success');
      fermerModal();
    } else {
      showToast(data.error || 'Erreur envoi', 'error');
    }
  } catch(e) {
    showToast('Erreur de connexion', 'error');
  }
}

// ── Recharger une session historique ─────────────────────────
async function reloadSession(sid) {
  try {
    const resp = await fetch(API_BASE + '/conversation/historique/' + sid, {
      headers: {'X-API-Key': API_KEY}
    });
    if (!resp.ok) { showToast('Session introuvable', 'error'); return; }
    const data = await resp.json();

    currentResults = data.derniers_profils || [];
    sessionId = sid;
    chatHistory = [];

    // Affiche la page recherche
    document.querySelectorAll('[id^="page-"]').forEach(el => el.style.display='none');
    document.getElementById('page-recherche').style.display='flex';
    document.querySelectorAll('.sb-item').forEach(el => el.classList.remove('active'));
    document.getElementById('topbarTitle').textContent = 'Recherche IA';

    // Reconstruit le chat
    const msgs = document.getElementById('chatMessages');
    msgs.innerHTML = '';
    data.messages.forEach(m => {
      const role = m.role === 'utilisateur' ? 'user' : 'ai';
      msgs.innerHTML += `<div class="chat-msg ${role}"><div class="chat-bubble">${m.message}</div></div>`;
      chatHistory.push({role: m.role === 'utilisateur' ? 'user' : 'assistant', content: m.message});
    });
    msgs.scrollTop = msgs.scrollHeight;

    // Affiche les résultats si disponibles
    if (currentResults.length) {
      renderResults(currentResults, data.messages[0]?.requete_texte || '');
    } else {
      document.getElementById('resultsSection').style.display='block';
      document.getElementById('loadingState').style.display='none';
    }
  } catch(e) {
    showToast('Erreur chargement session', 'error');
  }
}

// ── Export CSV ────────────────────────────────────────────────
function exportCSV() {
  if (!currentResults.length) { showToast('Aucun résultat à exporter','error'); return; }
  const rows = [['Rang','Nom','Prénom','Score','Ville','Expérience','Compétences']];
  currentResults.forEach((p,i) => {
    rows.push([i+1, p.nom||'', p.prenom||'', Math.round(p.score||0)+'%', p.ville||'', (p.annees_exp||0)+'ans',
      (Array.isArray(p.competences)?p.competences:Object.keys(p.competences||{})).join(';')]);
  });
  const csv = rows.map(r=>r.join(',')).join('\n');
  const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
  const url  = URL.createObjectURL(blob);
  const a    = document.createElement('a'); a.href=url; a.download='cvmatch_resultats.csv'; a.click();
  showToast('Export CSV téléchargé','success');
}

// ── Toast ─────────────────────────────────────────────────────
function showToast(msg, type='') {
  const t = document.getElementById('toast');
  t.textContent = msg; t.className = 'show ' + type;
  setTimeout(()=>t.className='', 3000);
}
</script>
</body>
</html>
