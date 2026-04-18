"""
routes/candidats.py — POST /candidats/upload/{candidat_id}
Indexe le CV d'un candidat inscrit (appelé par PHP après upload)
"""
import json, os
from dotenv import load_dotenv
from fastapi import APIRouter, Header, HTTPException

from db import get_conn
from services.cv import lire_cv
from services.ia import extraire_competences_cv
from services.matching import encoder, vers_json

load_dotenv()
router  = APIRouter(prefix="/candidats", tags=["candidats"])
CLE_API = os.getenv("API_KEY", "cvmatch-secret-2025")
UPLOAD_DIR = os.getenv("UPLOAD_DIR", "../public/uploads/")


@router.post("/upload/{candidat_id}")
def indexer_cv(candidat_id: int, x_api_key: str = Header(default="")):
    if x_api_key != CLE_API:
        raise HTTPException(401, "Non autorisé")

    conn = get_conn()
    try:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT id, nom_fichier FROM cv_files WHERE candidat_id=%s ORDER BY id DESC LIMIT 1",
                (candidat_id,)
            )
            ligne = cur.fetchone()
            if not ligne:
                raise HTTPException(404, "Aucun CV trouvé pour ce candidat")

            chemin = os.path.normpath(
                os.path.join(os.path.dirname(__file__), "../../public/uploads", ligne["nom_fichier"])
            )

            try:
                texte = lire_cv(chemin)
            except Exception as e:
                raise HTTPException(500, f"Erreur lecture CV : {e}")

            try:
                comps = extraire_competences_cv(texte)
                comps_json = json.dumps(comps, ensure_ascii=False)
            except Exception:
                comps_json = "[]"

            try:
                emb = vers_json(encoder(texte))
            except Exception:
                emb = None

            cur.execute(
                """UPDATE cv_files
                   SET texte_brut=%s, embedding_json=%s, competences=%s, indexe_le=CURRENT_TIMESTAMP
                   WHERE id=%s""",
                (texte, emb, comps_json, ligne["id"])
            )
            conn.commit()

        return {"statut": "ok", "candidat_id": candidat_id}

    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(500, f"Erreur serveur : {e}")
    finally:
        conn.close()
