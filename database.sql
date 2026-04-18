-- ============================================================
--  CVMatch IA v3.0 — Base de données complète
--  Importer : mysql -u root -p < database.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS cvmatch_ia
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE cvmatch_ia;

-- ============================================================
--  1. CANDIDATS
-- ============================================================
CREATE TABLE IF NOT EXISTS candidats (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nom            VARCHAR(100) NOT NULL,
  prenom         VARCHAR(100) NOT NULL,
  email          VARCHAR(180) NOT NULL UNIQUE,
  password_hash  VARCHAR(255) NOT NULL,
  telephone      VARCHAR(30)  DEFAULT NULL,
  ville          VARCHAR(100) DEFAULT NULL,
  annees_exp     TINYINT UNSIGNED DEFAULT 0,
  competences    TEXT         DEFAULT NULL    COMMENT 'Compétences séparées par virgules',
  intitule_poste VARCHAR(200) DEFAULT NULL,
  photo          VARCHAR(255) DEFAULT NULL,
  cree_le        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  modifie_le     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB COMMENT='Comptes candidats';

-- ============================================================
--  2. RECRUTEURS
-- ============================================================
CREATE TABLE IF NOT EXISTS recruteurs (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nom           VARCHAR(100) NOT NULL,
  prenom        VARCHAR(100) NOT NULL,
  email         VARCHAR(180) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  entreprise    VARCHAR(200) DEFAULT NULL,
  poste         VARCHAR(150) DEFAULT NULL,
  telephone     VARCHAR(30)  DEFAULT NULL,
  cree_le       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB COMMENT='Comptes recruteurs';

-- ============================================================
--  3. CV_FILES — Fichiers CV uploadés + extraction IA
--     candidat_id NULL = profil externe (sans compte)
-- ============================================================
CREATE TABLE IF NOT EXISTS cv_files (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  candidat_id    INT UNSIGNED DEFAULT NULL       COMMENT 'NULL si profil externe',
  nom_fichier    VARCHAR(255) NOT NULL,
  chemin_fichier VARCHAR(500) DEFAULT NULL       COMMENT 'Chemin relatif (profils externes)',
  type_fichier   VARCHAR(10)  NOT NULL           COMMENT 'pdf, docx, jpg...',
  mtime          BIGINT       DEFAULT 0          COMMENT 'Timestamp modif (profils externes)',
  -- Identité (profils externes)
  nom_complet    VARCHAR(255) DEFAULT NULL,
  ville_cv       VARCHAR(100) DEFAULT NULL,
  -- Contenu IA
  texte_brut     LONGTEXT     DEFAULT NULL       COMMENT 'Texte extrait (OCR/PDF)',
  embedding_json MEDIUMTEXT   DEFAULT NULL       COMMENT 'Vecteur 384 floats (JSON)',
  competences    JSON         DEFAULT NULL       COMMENT 'Compétences extraites par IA',
  annees_exp     TINYINT UNSIGNED DEFAULT 0,
  -- Dates
  indexe_le      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  depose_le      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_chemin (chemin_fichier(255)),
  FOREIGN KEY (candidat_id) REFERENCES candidats(id) ON DELETE CASCADE
) ENGINE=InnoDB COMMENT='CV uploadés et indexés';

-- ============================================================
--  4. MESSAGES — Messagerie recruteur ↔ candidat
-- ============================================================
CREATE TABLE IF NOT EXISTS messages (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  from_user_id INT UNSIGNED NOT NULL,
  from_role    ENUM('recruteur','candidat') NOT NULL DEFAULT 'recruteur',
  to_user_id   INT UNSIGNED NOT NULL,
  to_role      ENUM('recruteur','candidat') NOT NULL DEFAULT 'candidat',
  sujet        VARCHAR(255) DEFAULT NULL,
  corps        TEXT         NOT NULL,
  lu           TINYINT(1)   DEFAULT 0,
  envoye_le    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_to (to_user_id, lu)
) ENGINE=InnoDB COMMENT='Messagerie interne';

-- ============================================================
--  5. CONVERSATION_HISTORY — Historique IA recruteur
-- ============================================================
CREATE TABLE IF NOT EXISTS conversation_history (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id   VARCHAR(64)  NOT NULL,
  recruteur_id INT UNSIGNED NOT NULL,
  role         ENUM('utilisateur','assistant') NOT NULL,
  message      TEXT         NOT NULL,
  profils_json MEDIUMTEXT   DEFAULT NULL       COMMENT 'Profils + scores JSON',
  requete_texte VARCHAR(500) DEFAULT NULL,
  cree_le      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_session   (session_id),
  INDEX idx_recruteur (recruteur_id)
) ENGINE=InnoDB COMMENT='Historique conversations IA';

-- ============================================================
--  6. PASSWORD_RESETS
-- ============================================================
CREATE TABLE IF NOT EXISTS password_resets (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email     VARCHAR(180) NOT NULL,
  role      ENUM('candidat','recruteur') NOT NULL,
  token     VARCHAR(64)  NOT NULL UNIQUE,
  expire_le DATETIME     NOT NULL,
  utilise   TINYINT(1)   DEFAULT 0,
  cree_le   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
--  VUES UTILES
-- ============================================================

CREATE OR REPLACE VIEW vue_candidats_complets AS
SELECT
  c.id, c.nom, c.prenom, c.email, c.telephone, c.ville, c.annees_exp,
  c.competences, c.intitule_poste,
  cv.id       AS cv_id,
  cv.nom_fichier,
  cv.texte_brut,
  cv.embedding_json,
  cv.competences AS competences_ia,
  cv.indexe_le   AS cv_indexe_le,
  cv.depose_le   AS cv_depose_le
FROM candidats c
LEFT JOIN cv_files cv ON cv.candidat_id = c.id
  AND cv.id = (SELECT MAX(cv2.id) FROM cv_files cv2 WHERE cv2.candidat_id = c.id);

-- ============================================================
--  COMPTES DE DÉMONSTRATION
--  Recruteur : admin@cvmatch.ci / Admin1234!
--  Candidat  : demo@candidat.ci / Demo1234!
-- ============================================================

SELECT 'Base de données CVMatch IA v3.0 créée avec succès !' AS message;
