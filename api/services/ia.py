"""
services/ia.py — Client Groq + fonctions IA
Modèle : llama-3.1-8b-instant (rapide, gratuit, multilingue)
"""
import json, os, re
from dotenv import load_dotenv
from groq import DefaultHttpxClient, Groq

load_dotenv()

_client = Groq(
    api_key=os.getenv("GROQ_API_KEY"),
    http_client=DefaultHttpxClient(trust_env=False),
)
MODELE = "llama-3.1-8b-instant"


def _extraire_json(contenu: str) -> dict:
    contenu = re.sub(r'```(?:json)?\s*', '', contenu.strip())
    contenu = re.sub(r'```\s*', '', contenu)
    m = re.search(r'\{.*\}', contenu, re.DOTALL)
    if m:
        try: return json.loads(m.group())
        except: return {}
    return {}


def valider_requete_metier(requete: str) -> dict:
    """
    Valide et enrichit la requête avec un référentiel métier.
    Retourne un dict avec est_valide, titre_normalise, competences_attendues, etc.
    """
    prompt = f"""Tu es un expert en recrutement. Réponds UNIQUEMENT en JSON valide, sans texte avant ou après.

Analyse si cette requête correspond à un métier réel reconnu sur le marché de l'emploi.

RÈGLE ABSOLUE : 
- Si le métier N'EXISTE PAS ou est fictif ou absurde → est_valide DOIT être false
- Si le métier EXISTE → est_valide DOIT être true ET message_erreur DOIT être vide ""

Exemples de métiers INVALIDES : "vendeur d'illusion", "chasseur de dragons", "magicien du bonheur", "ninja du marketing"
Exemples de métiers VALIDES : "développeur PHP", "comptable", "médecin", "chauffeur", "avocat", "data analyst", "infirmier", "enseignant", "ingénieur"

JSON si INVALIDE :
{{"est_valide":false,"message_erreur":"❌ '{requete}' n'est pas un métier reconnu sur le marché de l'emploi.","titre_normalise":"","reference_metier":"","competences_attendues":[],"soft_skills":[],"formations":[],"niveau_experience":""}}

JSON si VALIDE :
{{"est_valide":true,"message_erreur":"","titre_normalise":"intitulé normalisé","reference_metier":"description du métier","competences_attendues":["comp1","comp2"],"soft_skills":["soft1"],"formations":["formation1"],"niveau_experience":"junior/senior"}}

Requête à analyser : {requete}"""

    try:
        r = _client.chat.completions.create(
            model=MODELE, max_tokens=700, temperature=0.1,
            messages=[{"role":"user","content":prompt}]
        )
        d = _extraire_json(r.choices[0].message.content or "")
    except Exception:
        d = {}

    d.setdefault("est_valide", False)
    d.setdefault("message_erreur", "")
    d.setdefault("titre_normalise", "")
    d.setdefault("reference_metier", "")
    d.setdefault("competences_attendues", [])
    d.setdefault("soft_skills", [])
    d.setdefault("formations", [])
    d.setdefault("niveau_experience", "")

    # Fallback : si Groq n'a retourné ni titre ni erreur, on accepte la requête brute
    if not d.get("titre_normalise") and not d.get("message_erreur"):
        d["est_valide"] = True
        d["titre_normalise"] = requete.strip()

    return d


def extraire_competences_cv(texte_brut: str) -> list:
    """Extrait les compétences techniques depuis le texte d'un CV (max 15)."""
    prompt = f"""Tu es un expert RH. Lis ce CV et retourne UNIQUEMENT ce JSON valide :
{{"competences":["comp1","comp2",...]}}
Règles : max 15 compétences techniques (langages, frameworks, outils). Pas de soft skills. JSON uniquement.

CV :
\"\"\"{texte_brut[:4000]}\"\"\"
"""
    try:
        r = _client.chat.completions.create(
            model=MODELE, max_tokens=300, temperature=0.1,
            messages=[{"role":"user","content":prompt}]
        )
        d = _extraire_json(r.choices[0].message.content or "")
        return d.get("competences", []) or []
    except Exception:
        return []


def generer_resume_candidat(profil: dict, requete: str, reference_metier: dict | None = None) -> str:
    """
    Génère un résumé 2-3 phrases expliquant la pertinence du candidat.
    Affiché sur chaque carte dans les résultats.
    """
    ref = reference_metier or {}
    prompt = f"""Tu es un expert RH. Évalue ce profil candidat pour la recherche donnée.

Métier recherché : {ref.get('titre_normalise', requete)}
Compétences attendues : {json.dumps(ref.get('competences_attendues', []), ensure_ascii=False)}
Niveau attendu : {ref.get('niveau_experience', '')}
Requête recruteur : {requete}
Profil candidat : {json.dumps(profil, ensure_ascii=False)}

Rédige en français, max 3 phrases :
- Commence par "Profil pertinent", "Profil partiel" ou "Profil non adapté"
- 1-2 raisons concrètes
- Mentionne les lacunes si nécessaire
- Pas d'introduction, pas de formule de politesse"""

    try:
        r = _client.chat.completions.create(
            model=MODELE, max_tokens=150, temperature=0.3,
            messages=[{"role":"user","content":prompt}]
        )
        return r.choices[0].message.content.strip()
    except Exception:
        return "Résumé IA non disponible."
