"""
========================================================
 main.py — Point d'entrée CVMatch IA API (FastAPI)

 LANCER :
   cd cvmatch/api
   uvicorn main:app --reload --port 8000

 DOCS : http://localhost:8000/docs
 SANTÉ: http://localhost:8000/sante
========================================================
"""
import os
from dotenv import load_dotenv
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

from db import get_conn
from routes import candidats, recherche, conversation

load_dotenv()

app = FastAPI(title="CVMatch IA API", version="3.0.0")

app.add_middleware(
    CORSMiddleware,
    allow_origins=["http://localhost", "http://127.0.0.1", "http://localhost:80"],
    allow_methods=["*"],
    allow_headers=["*"],
)

app.include_router(candidats.router)
app.include_router(recherche.router)
app.include_router(conversation.router)


@app.get("/sante")
def sante():
    try:
        conn = get_conn(); conn.close(); bdd_ok = True
    except Exception:
        bdd_ok = False
    return {"statut": "ok", "bdd_connectee": bdd_ok}


@app.get("/health")
def health():
    return sante()


@app.get("/test")
def test():
    resultats = {}
    try:
        from groq import Groq
        client = Groq(api_key=os.getenv("GROQ_API_KEY"))
        r = client.chat.completions.create(
            model="llama-3.1-8b-instant",
            messages=[{"role":"user","content":'Réponds: {"ok":true}'}],
            max_tokens=20, temperature=0.1
        )
        resultats["groq"] = '"ok"' in r.choices[0].message.content
    except Exception as e:
        resultats["groq"] = False; resultats["groq_err"] = str(e)

    try:
        from sentence_transformers import SentenceTransformer
        m = SentenceTransformer("paraphrase-multilingual-MiniLM-L12-v2")
        v = m.encode("test")
        resultats["sentence_transformers"] = len(v) > 0
    except Exception as e:
        resultats["sentence_transformers"] = False; resultats["st_err"] = str(e)

    try:
        conn = get_conn(); conn.close(); resultats["bdd"] = True
    except Exception as e:
        resultats["bdd"] = False; resultats["bdd_err"] = str(e)

    resultats["statut"] = "ok" if all(v for k,v in resultats.items() if not k.endswith("_err")) else "problemes"
    return resultats
