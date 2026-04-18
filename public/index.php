<?php
require_once 'config.php';
startSession();
if (isLoggedIn()) {
    header('Location: ' . ($_SESSION['role']==='recruteur' ? 'dashboard_recruteur.php' : 'dashboard_candidat.php'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CVMatch IA — Le matching intelligent</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    body{display:flex;flex-direction:column;min-height:100vh;}
    main{flex:1;display:flex;flex-direction:column;}

    /* Hero */
    .hero{display:flex;flex-direction:column;align-items:center;text-align:center;padding:80px 48px 60px;position:relative;overflow:hidden; background-image: url('ROULANT.jpg'); background-size: cover;}
    .hero::before{content:'';position:absolute;top:-60px;left:50%;transform:translateX(-50%);width:600px;height:400px;background:radial-gradient(ellipse at center,rgba(29, 132, 84, 0.12) 0%,transparent 70%);pointer-events:none;}
    .hero-badge{display:inline-flex;align-items:center;gap:6px;padding:5px 14px;background:var(--gold-bg);border:1px solid var(--gold-border);border-radius:var(--r-full);font-size:12px;font-weight:500;color:var(--gold);margin-bottom:28px;letter-spacing:.02em;}
    .hero-dot{width:6px;height:6px;border-radius:50%;background:var(--gold);animation:pulse 2s infinite;}
    h1{font-family:'Orbitron',sans-serif;font-size:clamp(36px,5vw,60px);font-weight:800;color:var(--ink);line-height:1.08;letter-spacing:-2px;max-width:700px;margin-bottom:20px;}
    h1 em{font-style:normal;color:var(--gold);}
    .hero-sub{font-size:17px;color:var(--ink2);max-width:500px;line-height:1.7;font-weight:300;margin-bottom:44px;}
    .cta-row{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;}

    /* Features */
    .features-section{padding:0 48px 64px;}
    .features-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;}
    .feat{padding:28px;border:1px solid var(--border);border-radius:var(--r-lg);background:var(--white);transition:border-color .2s;}
    .feat:hover{border-color:var(--gold);}
    .feat-icon{width:44px;height:44px;border-radius:var(--r-md);background:var(--gold-bg);display:flex;align-items:center;justify-content:center;margin-bottom:16px;}
    .feat-title{font-family:'Orbitron',sans-serif;font-size:16px;font-weight:700;color:var(--ink);margin-bottom:8px;}
    .feat-desc{font-size:13px;color:var(--muted);line-height:1.7;font-weight:300;}

    /* Footer */
    footer{background:var(--ink);padding:24px 48px;display:flex;align-items:center;justify-content:space-between;}
    .footer-brand{font-family:'Orbitron',sans-serif;font-weight:800;font-size:16px;color:rgba(255,255,255,.9);}
    .footer-brand span{color:var(--gold);}
    .footer-right{font-size:12px;color:rgba(255,255,255,.35);}
  </style>
</head>
<body>

<nav class="navbar">
  <div class="navbar-left">
    <img src="assets/css/PHOTO/logo.png" alt="CVMatch logo" class="logo-img">
    <a href="index.php" class="brand">CV<span class="accent">match</span> <span style="color:rgba(255,255,255,.25);font-weight:300;font-size:15px">ia</span></a>
  </div>
  <div class="nav-links">
    <a href="login_candidat.php" class="btn btn-outline btn-sm">Candidats</a>
    <a href="login_recruteur.php" class="btn btn-gold btn-sm">Espace recruteur</a>
  </div>
</nav>

<main>
  <div class="hero">
    <div class="hero-badge">
      <div class="hero-dot"></div>
     Analyseur de CV Intelligent
    </div>
    <h1>Le matching IA qui <em>connecte</em><br>vos talents aux meilleures opportunités.</h1>
    <p class="hero-sub">Déposez un CV ou décrivez un poste en langage naturel.<br>Notre IA analyse, classe et explique — instantanément.</p>
    <div class="cta-row">
      <a href="login_recruteur.php" class="btn btn-primary btn-lg">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 00-4 0v2"/></svg>
        Je suis recruteur
      </a>
      <a href="login_candidat.php" class="btn btn-gold btn-lg">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
        Je suis candidat
      </a>
    </div>
  </div>

  <div class="features-section">
    <div class="features-grid">
      <div class="feat">
        <div class="feat-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--gold)" stroke-width="1.5" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg></div>
        <div class="feat-title">Matching sémantique</div>
        <div class="feat-desc">Sentence-Transformers encode le sens réel. "Cuisinier" matche "Chef de cuisine". Scoring 40% sémantique + 30% compétences + 20% expérience + 10% localisation.</div>
      </div>
      <div class="feat">
        <div class="feat-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--gold)" stroke-width="1.5" stroke-linecap="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg></div>
        <div class="feat-title">Agent conversationnel</div>
        <div class="feat-desc">Après les résultats, posez des questions libres en français. "Montre-moi seulement les profils d'Abidjan." Llama 3 via Groq répond instantanément.</div>
      </div>
      <div class="feat">
        <div class="feat-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--gold)" stroke-width="1.5" stroke-linecap="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
        <div class="feat-title">Extraction automatique</div>
        <div class="feat-desc">PDF, DOCX, JPG, PNG — pdfplumber + Tesseract OCR. Compétences extraites par IA, embedding vectoriel généré et stocké en base.</div>
      </div>
    </div>
  </div>
</main>

<footer>
  <div class="footer-brand">CV<span>match</span> ia</div>
  <div class="footer-right">© <?= date('Y') ?> CVMatch IA · Conçu pour le marché de l'emploi</div>
</footer>

</body>
</html>
