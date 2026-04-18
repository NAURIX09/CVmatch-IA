"""
services/matching.py — Embeddings + algorithme de matching hybride multicritères
Scoring : sémantique 40% | compétences 30% | expérience 20% | localisation 10%
"""
from __future__ import annotations
import json, re
import numpy as np

try:
    from sentence_transformers import SentenceTransformer
    _model = SentenceTransformer("paraphrase-multilingual-MiniLM-L12-v2")
    _MODEL_OK = True
except Exception:
    _model = None; _MODEL_OK = False

POIDS = {"semantique": 0.40, "competences": 0.30, "experience": 0.20, "localisation": 0.10}
MOTS_SENIOR = ["senior","confirmé","expert","lead","5 ans","6 ans","7 ans","8 ans","10 ans"]
MOTS_JUNIOR = ["junior","débutant","stage","0 an","1 an","moins d"]
VILLES_AOF  = ["abidjan","bouaké","yamoussoukro","daloa","san-pédro","korhogo",
               "dakar","bamako","lomé","accra","cotonou","ouagadougou","conakry"]


# ── Embeddings ────────────────────────────────────────────────
def encoder(texte: str) -> list[float]:
    if not _MODEL_OK or _model is None: return []
    return _model.encode(texte, normalize_embeddings=True).tolist()

def vers_json(v: list[float]) -> str:
    return json.dumps(v)

def depuis_json(s: str | None) -> np.ndarray | None:
    if not s: return None
    try: return np.array(json.loads(s), dtype=np.float32)
    except: return None

def cosinus(a: np.ndarray, b: np.ndarray) -> float:
    d = float(np.linalg.norm(a) * np.linalg.norm(b))
    return 0.0 if d == 0 else float(np.dot(a, b) / d)


# ── Scoring compétences ───────────────────────────────────────
_ALIAS = {"js":"javascript","py":"python","ts":"typescript","node":"nodejs",
          "react":"reactjs","vue":"vuejs","pg":"postgresql","mongo":"mongodb",
          "ml":"machine learning","dl":"deep learning"}

def _match_comp(c1: str, c2: str) -> bool:
    a, b = c1.lower().strip(), c2.lower().strip()
    if a == b or a in b or b in a: return True
    return _ALIAS.get(a, a) == _ALIAS.get(b, b)

def _score_competences(requete: str, competences: list, texte: str) -> float:
    mots = [m for m in re.findall(r"\w+", requete.lower()) if len(m) > 2]
    if not mots: return 0.5
    txt = (texte or "").lower()
    comps = [c for c in (competences or []) if isinstance(c, str)]
    trouves = sum(1 for m in mots if any(_match_comp(m, c) for c in comps) or m in txt)
    return trouves / len(mots)


# ── Scoring expérience ────────────────────────────────────────
def _parse_annees(texte) -> float:
    if texte is None: return 0.0
    try: return float(texte)
    except (ValueError, TypeError): pass
    t = str(texte).lower()
    if any(m in t for m in ["débutant","junior","stage","moins"]): return 0.5
    nums = re.findall(r"(\d+\.?\d*)", t)
    if nums:
        n = float(nums[0])
        if "+" in t or "plus" in t: n += 0.5
        return n
    return 1.0

def _score_experience(requete: str, annees_cv: float) -> float:
    q = requete.lower()
    nums = re.findall(r"(\d+\.?\d*)\s*(?:ans?|an)", q)
    if nums: demandees = float(nums[0])
    elif any(m in q for m in MOTS_SENIOR): demandees = 5.0
    elif any(m in q for m in MOTS_JUNIOR): demandees = 1.0
    else: return 0.7
    if demandees == 0: return 0.75
    ratio = annees_cv / demandees
    if ratio >= 1.0: return min(1.0, 0.70 + (ratio - 1.0) * 0.15)
    return round(ratio * 0.70, 3)


# ── Scoring localisation ──────────────────────────────────────
def _score_localisation(requete: str, ville: str) -> float:
    if not ville: return 0.3
    q, v = requete.lower(), ville.lower().strip()
    if v in q or q in v: return 1.0
    if any(c in q for c in VILLES_AOF) and v in VILLES_AOF: return 0.6
    if not any(c in q for c in VILLES_AOF + ["paris","lyon","marseille"]): return 0.7
    return 0.2


# ── Algorithme principal ──────────────────────────────────────
def classer_candidats(requete: str, candidats: list[dict]) -> list[dict]:
    """
    Classe les candidats selon un score composite multicritères.
    Retourne les 10 meilleurs (seuil ≥ 15%).
    """
    if not candidats: return []
    vreq = np.array(encoder(requete), dtype=np.float32) if _MODEL_OK else None
    resultats = []

    for c in candidats:
        texte = c.get("raw_text","") or ""
        comps = c.get("competences",[]) or []
        annees = _parse_annees(c.get("annees_exp", 0))
        ville  = c.get("ville","") or c.get("ville_cv","") or ""

        # Sémantique
        if _MODEL_OK and vreq is not None:
            vc = depuis_json(c.get("embedding_json"))
            if vc is not None and vc.shape == vreq.shape:
                s_sem = max(0.0, cosinus(vreq, vc))
            elif texte:
                vc = np.array(encoder(texte), dtype=np.float32)
                s_sem = max(0.0, cosinus(vreq, vc))
            else:
                s_sem = 0.0
        else:
            s_sem = 0.0

        s_comp = _score_competences(requete, comps, texte)
        s_exp  = _score_experience(requete, annees)
        s_loc  = _score_localisation(requete, ville)

        if _MODEL_OK:
            score = (s_sem*POIDS["semantique"] + s_comp*POIDS["competences"] +
                     s_exp*POIDS["experience"]  + s_loc*POIDS["localisation"]) * 100
        else:
            score = (s_comp*0.60 + s_exp*0.25 + s_loc*0.15) * 100

        if score >= 15:
            resultats.append({**c, "score": round(score, 1),
                "_score_detail": {
                    "semantique":  round(s_sem*100, 1),
                    "competences": round(s_comp*100, 1),
                    "experience":  round(s_exp*100, 1),
                    "localisation":round(s_loc*100, 1),
                }})

    resultats.sort(key=lambda x: x["score"], reverse=True)
    return resultats[:5]
