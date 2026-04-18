"""
routes/recherche.py — POST /recherche
Matching IA multicritères + résumé Groq
"""
import json, os
from dotenv import load_dotenv
from fastapi import APIRouter, Header, HTTPException
from pydantic import BaseModel

from db import get_conn
from services.cv import sync_profils_externes
from services.ia import generer_resume_candidat, valider_requete_metier
from services.matching import classer_candidats

load_dotenv()
router  = APIRouter(tags=["recherche"])
CLE_API = os.getenv("API_KEY", "cvmatch-secret-2025")


class RequeteRecherche(BaseModel):
    texte_requete: str


@router.post("/recherche")
def rechercher(req: RequeteRecherche, x_api_key: str = Header(default="")):
    if x_api_key != CLE_API:
        raise HTTPException(401, "Non autorisé")

    # Étape 1 — Validation métier IA (avec timeout strict)
    ref = {"est_valide":True,"titre_normalise":req.texte_requete,"competences_attendues":[],"formations":[]}
    try:
        from concurrent.futures import ThreadPoolExecutor, TimeoutError as FutureTimeout
        with ThreadPoolExecutor(max_workers=1) as ex:
            future = ex.submit(valider_requete_metier, req.texte_requete)
            try:
                result = future.result(timeout=15)
                ref = result
            except (FutureTimeout, Exception):
                pass  # timeout → on laisse passer la requête brute
    except Exception:
        pass

    # Si le métier n'est pas reconnu, on bloque et on retourne l'erreur
    message_err = ref.get("message_erreur", "")
    est_invalide = not ref.get("est_valide", True)
    if est_invalide:
        return {
            "profils": [],
            "reference_metier": ref,
            "erreur": message_err or "Métier non reconnu. Veuillez préciser votre recherche."
        }

    # Étape 2 — Sync CV externes
    try:
        sync_profils_externes()
    except Exception:
        pass

    conn = get_conn()
    try:
        with conn.cursor() as cur:
            # Candidats inscrits avec CV
            cur.execute("""
                SELECT c.id, c.id AS user_id, c.nom, c.prenom, c.ville, c.annees_exp,
                       cv.competences, cv.texte_brut AS raw_text,
                       cv.ville_cv, cv.embedding_json,
                       CONCAT('uploads/', cv.nom_fichier) AS chemin_cv,
                       c.intitule_poste, 'inscrit' AS type_profil
                FROM candidats c
                JOIN cv_files cv ON cv.candidat_id = c.id
                WHERE cv.texte_brut IS NOT NULL
                  AND cv.id = (SELECT MAX(cv2.id) FROM cv_files cv2 WHERE cv2.candidat_id = c.id)
            """)
            candidats = cur.fetchall()

            # Profils externes
            cur.execute("""
                SELECT id, NULL AS user_id,
                       nom_complet AS nom, '' AS prenom,
                       ville_cv AS ville, annees_exp,
                       competences, texte_brut AS raw_text,
                       NULL AS formation, embedding_json,
                       CONCAT('uploads/', nom_fichier) AS chemin_cv,
                       NULL AS intitule_poste, 'externe' AS type_profil
                FROM cv_files WHERE candidat_id IS NULL
                ORDER BY indexe_le DESC
            """)
            candidats.extend(cur.fetchall())

        # Désérialise compétences JSON
        for c in candidats:
            if isinstance(c.get("competences"), str):
                try: c["competences"] = json.loads(c["competences"] or "[]")
                except: c["competences"] = []
            elif c.get("competences") is None:
                c["competences"] = []

        # Requête enrichie
        requete_enrichie = "\n".join(filter(None, [
            req.texte_requete,
            ref.get("titre_normalise",""),
            " ".join(ref.get("competences_attendues",[]) or []),
            " ".join(ref.get("formations",[]) or []),
        ]))

        # Matching
        profils = classer_candidats(requete_enrichie, candidats)

        # Résumés IA + nettoyage
        for p in profils:
            try:
                from concurrent.futures import ThreadPoolExecutor, TimeoutError as FutureTimeout
                with ThreadPoolExecutor(max_workers=1) as ex:
                    future = ex.submit(generer_resume_candidat,
                        {"nom":p.get("nom",""), "prenom":p.get("prenom",""),
                         "competences":p.get("competences",[]),
                         "ville":p.get("ville",""),
                         "experience":p.get("annees_exp",0),
                         "intitule_poste":p.get("intitule_poste",""),
                         "type_profil":p.get("type_profil","inscrit")},
                        req.texte_requete, ref)
                    p["resume_ia"] = future.result(timeout=10)
            except Exception:
                p["resume_ia"] = "Résumé IA non disponible."
            p.pop("raw_text", None)
            p.pop("embedding_json", None)

        return {"profils": profils, "reference_metier": ref}

    finally:
        conn.close()
