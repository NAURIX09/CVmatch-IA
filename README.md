# CVMatch IA v3.0 — Guide d'installation

## Structure du projet

```
cvmatch/
├── public/              ← Racine web PHP (WAMP/XAMPP)
│   ├── assets/css/style.css
│   ├── uploads/         ← CV uploadés (créer ce dossier)
│   ├── index.php
│   ├── config.php
│   ├── login_candidat.php
│   ├── login_recruteur.php
│   ├── dashboard_candidat.php
│   ├── dashboard_recruteur.php
│   ├── cv_view.php
│   ├── logout.php
│   ├── get_candidats.php
│   └── get_messages.php
├── api/                 ← Microservice Python FastAPI
│   ├── main.py
│   ├── db.py
│   ├── requirements.txt
│   ├── .env.example     ← Copier en .env
│   ├── services/
│   │   ├── ia.py        ← Client Groq (Llama 3)
│   │   ├── matching.py  ← Embeddings + scoring
│   │   └── cv.py        ← Lecture PDF/DOCX/Image
│   └── routes/
│       ├── candidats.py
│       ├── recherche.py
│       └── conversation.py
└── database.sql         ← Schéma MySQL complet
```

---

## Installation pas à pas

### 1. Base de données MySQL

```sql
-- Dans phpMyAdmin ou terminal :
mysql -u root -p < database.sql
```

Comptes de démonstration créés :
- **Recruteur** : admin@cvmatch.ci / Admin1234!
- **Candidat**  : demo@candidat.ci / Demo1234!

---

### 2. PHP (WAMP / XAMPP)

1. Copiez le dossier `public/` dans `C:/wamp64/www/cvmatch/` (ou équivalent)
2. Créez le dossier `public/uploads/` s'il n'existe pas
3. Éditez `public/config.php` si nécessaire (BDD, API_KEY)
4. Accédez à : `http://localhost/cvmatch/public/`

---

### 3. Python API

#### Prérequis
- Python 3.10+
- Clé API Groq gratuite : https://console.groq.com

#### Installation

```bash
cd cvmatch/api

# Créer l'environnement virtuel
python -m venv venv
venv\Scripts\activate     # Windows
# source venv/bin/activate  # Linux/Mac

# Installer les dépendances
pip install -r requirements.txt

# Configurer l'environnement
copy .env.example .env
# Éditez .env et renseignez votre GROQ_API_KEY
```

#### Lancer l'API

```bash
uvicorn main:app --reload --port 8000
```

Tester : http://localhost:8000/sante

---

### 4. Vérification

- Landing page : http://localhost/cvmatch/public/
- API santé    : http://localhost:8000/sante
- API docs     : http://localhost:8000/docs
- API test     : http://localhost:8000/test

---

## Configuration .env (api/.env)

```env
GROQ_API_KEY=gsk_VOTRE_CLE_GROQ
DB_HOST=localhost
DB_PORT=3306
DB_USER=root
DB_PASS=
DB_NAME=cvmatch_ia
API_KEY=cvmatch-secret-2025
UPLOAD_DIR=../public/uploads/
```

> ⚠️ L'`API_KEY` doit être **identique** dans `.env` et dans `public/config.php`

---

## Technologies

| Couche | Technologie |
|--------|------------|
| Frontend | PHP 8+, CSS3, JS vanilla |
| Backend PHP | PDO MySQL, sessions sécurisées |
| API Python | FastAPI + Uvicorn |
| IA Matching | Sentence-Transformers (MiniLM-L12-v2) |
| IA Génération | Groq API (Llama 3.1 8B Instant) |
| Extraction CV | pdfplumber, python-docx, pytesseract (OCR) |
| Base de données | MySQL 8+ |

---

## Scoring multicritères

| Signal | Poids | Description |
|--------|-------|-------------|
| Sémantique | 40% | Similarité cosinus (sentence-transformers) |
| Compétences | 30% | Correspondance exacte + fuzzy + alias |
| Expérience | 20% | Années demandées vs disponibles |
| Localisation | 10% | Ville / région Afrique de l'Ouest |

Seuil minimum : **15%** — en dessous le profil est ignoré.
Top **10** profils retournés, triés par score décroissant.
