"""
routes/conversation.py — Agent IA conversationnel + historique
"""
import json, os, re, uuid
from typing import Any, Dict, List, Optional
from dotenv import load_dotenv
from fastapi import APIRouter, Header, HTTPException
from pydantic import BaseModel

from db import get_conn
from services.ia import _client

load_dotenv()
router  = APIRouter(prefix="/conversation", tags=["conversation"])
CLE_API = os.getenv("API_KEY", "cvmatch-secret-2025")

# Modèle puissant pour le chat — meilleure analyse de CV
MODELE_CHAT = "llama-3.3-70b-versatile"


def _check(cle: str):
    if cle != CLE_API:
        raise HTTPException(401, "Non autorisé")


def _charger_textes_cv(profils: list) -> list:
    """Enrichit les profils avec le texte brut de leur CV depuis la base."""
    if not profils:
        return profils
    conn = get_conn()
    try:
        with conn.cursor() as cur:
            for p in profils:
                cv_id = p.get("id")
                candidat_id = p.get("user_id")
                if candidat_id:
                    cur.execute(
                        "SELECT texte_brut FROM cv_files WHERE candidat_id=%s ORDER BY id DESC LIMIT 1",
                        (candidat_id,)
                    )
                else:
                    cur.execute(
                        "SELECT texte_brut FROM cv_files WHERE id=%s LIMIT 1",
                        (cv_id,)
                    )
                row = cur.fetchone()
                if row and row["texte_brut"]:
                    p["cv_texte_brut"] = row["texte_brut"][:3000]  # max 3000 chars par CV
    except Exception:
        pass
    finally:
        conn.close()
    return profils


class ChatRequest(BaseModel):
    message:          str
    historique:       List[Dict[str,str]] = []
    derniers_profils: List[Dict[str,Any]] = []
    session_id:       Optional[str]       = None
    recruteur_id:     Optional[int]       = None
    requete_texte:    Optional[str]       = None


class SauvegarderMsg(BaseModel):
    session_id:   str
    recruteur_id: int
    role:         str
    message:      str
    profils:      Optional[List[Any]] = None
    requete_texte:Optional[str]       = None


@router.post("/chat")
def chat(req: ChatRequest, x_api_key: str = Header(default="")):
    _check(x_api_key)

    # Détection rapide de questions hors-sujet
    msg_lower = req.message.lower().strip()
    mots_hors_sujet = [
        r'^\d+\s*[\+\-\*\/]\s*\d+',  # calculs
        r'^(bonjour|salut|hello|hi|hey|coucou)[\s!]*$',  # salutations seules
        r'^(merci|thanks|ok|oui|non|yes|no)[\s!]*$',  # réponses courtes
    ]
    import re as _re
    for pattern in mots_hors_sujet:
        if _re.match(pattern, msg_lower):
            return {"reponse": "Je suis spécialisé dans l'analyse de CVs. Posez-moi une question sur les candidats.", "profils": req.derniers_profils}

    # Enrichir les profils avec les textes bruts des CV
    profils_enrichis = _charger_textes_cv(list(req.derniers_profils))

    # Construire le contexte CV pour le prompt
    cv_context = ""
    for i, p in enumerate(profils_enrichis, 1):
        nom = f"{p.get('prenom','')} {p.get('nom','')}".strip() or "Profil externe"
        score = p.get("score", 0)
        cv_context += f"\n--- Candidat #{i} : {nom} (Score: {score}%) ---\n"
        cv_context += f"Ville: {p.get('ville','—')} | Expérience: {p.get('annees_exp',0)} an(s)\n"
        comps = p.get("competences", [])
        if comps:
            cv_context += f"Compétences: {', '.join(comps[:10])}\n"
        if p.get("cv_texte_brut"):
            cv_context += f"Texte CV:\n{p['cv_texte_brut']}\n"

    systeme = f"""Tu es un Expert Analyste CV Senior pour CVMatch IA. Tu analyses des CVs avec une rigueur extrême, une logique parfaite et un esprit structuré.

Tu as accès UNIQUEMENT aux CVs des candidats suivants, retournés par la dernière recherche du recruteur :
{cv_context}

RÈGLES OBLIGATOIRES :
- Tu lis et analyses UNIQUEMENT ces CVs, pas d'autres.
- Tu réponds toujours en français naturel, clair et professionnel.
- Tu utilises des tableaux Markdown dès que c'est utile (comparaison, extraction, classement).
- Tu fais des déductions logiques quand l'info n'est pas explicite.
- Tu peux extraire n'importe quelle information précise : compétences, expériences, langues, etc.
- Tu filtres, compares, classes ou analyses selon la question posée, même si elle est très spécifique.
- Ne réponds jamais en JSON brut sauf si l'utilisateur le demande explicitement.
- Si la question n'est PAS liée aux CVs ou au recrutement, réponds UNIQUEMENT : "Je suis spécialisé dans l'analyse de CVs. Posez-moi une question sur les candidats."
- Si une information n'est pas dans les CVs fournis, dis-le clairement."""

    messages = (
        [{"role":"system","content":systeme}]
        + req.historique
        + [{"role":"user","content":req.message}]
    )

    reponse = _client.chat.completions.create(
        model=MODELE_CHAT, max_tokens=2048, temperature=0.3, messages=messages
    )
    contenu = reponse.choices[0].message.content.strip()

    # Nettoie les blocs markdown de code JSON si présents
    contenu = re.sub(r'```(?:json)?\s*\{.*?\}\s*```', '', contenu, flags=re.DOTALL)

    texte_rep = contenu
    profils   = req.derniers_profils

    if req.session_id and req.recruteur_id:
        _persister(req.session_id, req.recruteur_id, req.message, texte_rep, list(profils), req.requete_texte)

    return {"reponse": texte_rep, "profils": profils}


def _persister(session_id, recruteur_id, msg_u, msg_a, profils, requete):
    pjson = json.dumps(profils, ensure_ascii=False) if profils else None
    conn = get_conn()
    try:
        with conn.cursor() as cur:
            cur.execute(
                "INSERT INTO conversation_history (session_id,recruteur_id,role,message,requete_texte) VALUES(%s,%s,'utilisateur',%s,%s)",
                (session_id, recruteur_id, msg_u, requete)
            )
            cur.execute(
                "INSERT INTO conversation_history (session_id,recruteur_id,role,message,profils_json,requete_texte) VALUES(%s,%s,'assistant',%s,%s,%s)",
                (session_id, recruteur_id, msg_a, pjson, requete)
            )
            conn.commit()
    except Exception:
        pass
    finally:
        conn.close()


@router.get("/historique/nouvelle-session")
def nouvelle_session(x_api_key: str = Header(default="")):
    _check(x_api_key)
    return {"session_id": str(uuid.uuid4())}


@router.post("/historique/sauvegarder")
def sauvegarder(req: SauvegarderMsg, x_api_key: str = Header(default="")):
    _check(x_api_key)
    if req.role not in ("utilisateur","assistant"):
        raise HTTPException(400, "role invalide")
    pjson = json.dumps(req.profils, ensure_ascii=False) if req.profils else None
    conn = get_conn()
    try:
        with conn.cursor() as cur:
            cur.execute(
                "INSERT INTO conversation_history (session_id,recruteur_id,role,message,profils_json,requete_texte) VALUES(%s,%s,%s,%s,%s,%s)",
                (req.session_id, req.recruteur_id, req.role, req.message, pjson, req.requete_texte)
            )
            conn.commit()
        return {"statut":"ok","session_id":req.session_id}
    finally:
        conn.close()


@router.get("/historique/{session_id}")
def obtenir_session(session_id: str, x_api_key: str = Header(default="")):
    _check(x_api_key)
    conn = get_conn()
    try:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT id,role,message,profils_json,requete_texte,cree_le FROM conversation_history WHERE session_id=%s ORDER BY cree_le ASC",
                (session_id,)
            )
            lignes = cur.fetchall()
        if not lignes:
            raise HTTPException(404, "Session introuvable")
        messages, derniers = [], []
        for l in lignes:
            profils = None
            if l["profils_json"]:
                try: profils = json.loads(l["profils_json"])
                except: pass
            messages.append({"id":l["id"],"role":l["role"],"message":l["message"],
                             "profils":profils,"cree_le":str(l["cree_le"])})
            if l["role"]=="assistant" and profils:
                derniers = profils
        return {"session_id":session_id,"messages":messages,"derniers_profils":derniers}
    finally:
        conn.close()


@router.get("/historique/sessions/{recruteur_id}")
def lister_sessions(recruteur_id: int, x_api_key: str = Header(default="")):
    _check(x_api_key)
    conn = get_conn()
    try:
        with conn.cursor() as cur:
            cur.execute(
                """SELECT session_id, MAX(requete_texte) AS requete,
                          MIN(cree_le) AS debut, COUNT(*) AS nb
                   FROM conversation_history WHERE recruteur_id=%s
                   GROUP BY session_id ORDER BY debut DESC LIMIT 20""",
                (recruteur_id,)
            )
            return {"recruteur_id":recruteur_id,"sessions":[
                {"session_id":l["session_id"],"requete_texte":l["requete"],
                 "debut":str(l["debut"]),"nb_messages":l["nb"]}
                for l in cur.fetchall()
            ]}
    finally:
        conn.close()
