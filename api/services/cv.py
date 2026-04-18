"""
services/cv.py — Lecture de fichiers CV (PDF, DOCX, image) + sync profils externes
"""
from __future__ import annotations
import json, os, re
from pathlib import Path

import pdfplumber
import pytesseract
from docx import Document
from PIL import Image

from db import get_conn
from services.ia import extraire_competences_cv
from services.matching import encoder, vers_json

EXTENSIONS = {".pdf", ".docx", ".jpg", ".jpeg", ".png"}


# ── Lecture ───────────────────────────────────────────────────
def lire_pdf(chemin: str) -> str:
    texte = ""
    with pdfplumber.open(chemin) as pdf:
        for page in pdf.pages:
            texte += (page.extract_text() or "") + "\n"
    return texte.strip()


def lire_docx(chemin: str) -> str:
    doc = Document(chemin)
    return "\n".join(p.text for p in doc.paragraphs).strip()


def lire_image(chemin: str) -> str:
    img = Image.open(chemin)
    return pytesseract.image_to_string(img, lang="fra+eng").strip()


def lire_cv(chemin: str) -> str:
    ext = os.path.splitext(chemin)[1].lower()
    if ext == ".pdf":   return lire_pdf(chemin)
    if ext == ".docx":  return lire_docx(chemin)
    if ext in (".jpg",".jpeg",".png"): return lire_image(chemin)
    raise ValueError(f"Format non supporté : {ext}")


# ── Sync profils externes ─────────────────────────────────────
def _racine() -> Path:
    return Path(__file__).resolve().parents[2]

def _uploads() -> Path:
    env = os.getenv("UPLOAD_DIR", "public/uploads/").strip()
    if env.startswith("../"):env = env[3:]
    return (_racine() / env).resolve()

def _rel(p: Path) -> str:
    return str(p.relative_to(_racine())).replace("\\", "/")

def _deviner_nom(nom_fichier: str) -> str:
    base = Path(nom_fichier).stem
    # Ignore les fichiers au format cv_{id}_{timestamp} générés par le système
    if re.match(r'^cv_\d+_\d+$', base, re.IGNORECASE):
        return "Profil externe"
    base = re.sub(r"^cv[_-]?", "", base, flags=re.IGNORECASE)
    # Supprime les numéros en début (ex: "04_Traore" → "Traore")
    base = re.sub(r"^\d+[_\-\s]*", "", base)
    base = re.sub(r"[_\-]+", " ", base)
    return re.sub(r"\s+", " ", base).strip().title() or "Profil externe"


def sync_profils_externes() -> int:
    """Indexe les CV dans uploads/ sans compte candidat (type='externe')."""
    dossier = _uploads()
    if not dossier.exists(): return 0
    conn = get_conn()
    nb = 0
    try:
        with conn.cursor() as cur:
            # Compare par nom_fichier (chemin_fichier est NULL pour les inscrits)
            cur.execute("SELECT nom_fichier FROM cv_files WHERE candidat_id IS NOT NULL")
            inscrits_noms = {r["nom_fichier"] for r in cur.fetchall()}

            cur.execute("SELECT chemin_fichier, mtime FROM cv_files WHERE candidat_id IS NULL")
            deja = {r["chemin_fichier"]: int(r["mtime"] or 0) for r in cur.fetchall()}

            for f in dossier.iterdir():
                if not f.is_file() or f.suffix.lower() not in EXTENSIONS: continue
                # Ignore si ce fichier appartient déjà à un candidat inscrit
                if f.name in inscrits_noms: continue
                rel = _rel(f)
                mtime = int(f.stat().st_mtime)
                if deja.get(rel) == mtime: continue

                try:
                    texte = lire_cv(str(f))
                    emb_json = vers_json(encoder(texte)) if texte else None
                    m = re.search(r'(\d{1,2})\s*(?:ans?|années?)\s*(?:d[\'e]?)?\s*(?:expérience|experience)',
                                  texte, re.IGNORECASE)
                    annees = int(m.group(1)) if m else 0
                    nom = _deviner_nom(f.name)
                    comps_json = json.dumps(extraire_competences_cv(texte), ensure_ascii=False) if texte else "[]"
                except Exception:
                    continue

                cur.execute("""
                    INSERT INTO cv_files (candidat_id,nom_fichier,chemin_fichier,type_fichier,
                        mtime,nom_complet,texte_brut,embedding_json,competences,annees_exp,indexe_le)
                    VALUES (NULL,%s,%s,%s,%s,%s,%s,%s,%s,%s,CURRENT_TIMESTAMP)
                    ON DUPLICATE KEY UPDATE
                        mtime=VALUES(mtime),texte_brut=VALUES(texte_brut),
                        embedding_json=VALUES(embedding_json),competences=VALUES(competences),
                        annees_exp=VALUES(annees_exp),indexe_le=CURRENT_TIMESTAMP
                """, (f.name, rel, f.suffix.lower().lstrip("."),
                      mtime, nom, texte, emb_json, comps_json, annees))
                nb += 1

        conn.commit()
    finally:
        conn.close()
    return nb
