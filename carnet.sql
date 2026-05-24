-- ============================================================
-- carnet.sql — Base de données Skooly — Scénario 1 Démo
-- Tables : ecoles, classes, matieres, enseignants,
--          eleves, parents, eleve_parents
-- Version démo — MySQL 8.0+
-- ============================================================

-- ============================================================
-- TABLE 1 : ecoles
-- ============================================================
CREATE TABLE ecoles (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom         VARCHAR(255) NOT NULL,
    slug        VARCHAR(255) NOT NULL UNIQUE COMMENT 'ex: notre-dame-quaben',
    adresse     TEXT,
    telephone   VARCHAR(20),
    email       VARCHAR(255),
    logo_url    VARCHAR(500),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Établissements scolaires';

-- ============================================================
-- TABLE 2 : classes
-- ============================================================
CREATE TABLE classes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ecole_id        INT UNSIGNED NOT NULL,
    nom             VARCHAR(100) NOT NULL COMMENT 'ex: 3ème B, Terminale C',
    niveau          VARCHAR(50)  COMMENT 'ex: 3ème, Terminale',
    annee_scolaire  VARCHAR(10)  NOT NULL COMMENT 'ex: 2025-2026',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_classes_ecole
        FOREIGN KEY (ecole_id) REFERENCES ecoles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Classes scolaires — appartiennent à une école';

-- ============================================================
-- TABLE 3 : enseignants
-- ============================================================
CREATE TABLE enseignants (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ecole_id    INT UNSIGNED NOT NULL,
    prenom      VARCHAR(100) NOT NULL,
    nom         VARCHAR(100) NOT NULL,
    email       VARCHAR(255) UNIQUE,
    telephone   VARCHAR(20),
    photo_url   VARCHAR(500),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_enseignants_ecole
        FOREIGN KEY (ecole_id) REFERENCES ecoles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Enseignants — appartiennent à une école';

-- ============================================================
-- TABLE 4 : matieres
-- ============================================================
CREATE TABLE matieres (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ecole_id        INT UNSIGNED NOT NULL,
    enseignant_id   INT UNSIGNED NULL COMMENT 'Enseignant responsable de cette matière',
    nom             VARCHAR(100) NOT NULL COMMENT 'ex: Mathématiques, Français',
    heures_semaine  TINYINT UNSIGNED DEFAULT 2 COMMENT 'Nombre d heures par semaine',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_matieres_ecole
        FOREIGN KEY (ecole_id) REFERENCES ecoles(id) ON DELETE CASCADE,
    CONSTRAINT fk_matieres_enseignant
        FOREIGN KEY (enseignant_id) REFERENCES enseignants(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Matières — chaque matière a un enseignant responsable';

-- ============================================================
-- TABLE 5 : classe_matieres (jointure classe ↔ matière)
-- Une classe a plusieurs matières
-- ============================================================
CREATE TABLE classe_matieres (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    classe_id   INT UNSIGNED NOT NULL,
    matiere_id  INT UNSIGNED NOT NULL,

    UNIQUE KEY unique_classe_matiere (classe_id, matiere_id),

    CONSTRAINT fk_cm_classe
        FOREIGN KEY (classe_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_cm_matiere
        FOREIGN KEY (matiere_id) REFERENCES matieres(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Jointure : quelles matières sont enseignées dans quelle classe';

-- ============================================================
-- TABLE 6 : eleves
-- ============================================================
CREATE TABLE eleves (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ecole_id        INT UNSIGNED NOT NULL,
    classe_id       INT UNSIGNED NOT NULL,
    prenom          VARCHAR(100) NOT NULL,
    nom             VARCHAR(100) NOT NULL,
    date_naissance  DATE,
    genre           ENUM('M', 'F'),
    photo_url       VARCHAR(500) COMMENT 'Photo de l élève',

    -- Champs pour la liaison parent (Scénario 1)
    code_secret     VARCHAR(20) NOT NULL UNIQUE
                    COMMENT 'Code lisible pour liaison parent — ex: SKL-2026-4521',
    qr_token        VARCHAR(255) NOT NULL UNIQUE
                    COMMENT 'Token encodé dans le QR Code',

    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_eleves_classe (classe_id),
    INDEX idx_eleves_code_secret (code_secret),

    CONSTRAINT fk_eleves_ecole
        FOREIGN KEY (ecole_id) REFERENCES ecoles(id) ON DELETE CASCADE,
    CONSTRAINT fk_eleves_classe
        FOREIGN KEY (classe_id) REFERENCES classes(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Élèves — ont un code_secret et qr_token pour la liaison parent';

-- ============================================================
-- TABLE 7 : parents
-- ============================================================
CREATE TABLE parents (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ecole_id    INT UNSIGNED NOT NULL COMMENT 'École de rattachement',
    prenom      VARCHAR(100) NOT NULL,
    nom         VARCHAR(100) NOT NULL,
    telephone   VARCHAR(20)  COMMENT 'Utilisé pour le login app_mobile',
    email       VARCHAR(255) COMMENT 'Utilisé pour le login app_mobile',
    password    VARCHAR(255) NOT NULL COMMENT 'Mot de passe hashé',
    photo_url   VARCHAR(500),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_parents_telephone (telephone),
    INDEX idx_parents_email (email),

    CONSTRAINT fk_parents_ecole
        FOREIGN KEY (ecole_id) REFERENCES ecoles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Parents — peuvent se connecter par téléphone ou email';

-- ============================================================
-- TABLE 8 : eleve_parents (jointure élève ↔ parent)
-- TABLE CENTRALE DU SCÉNARIO 1
-- Un élève peut avoir plusieurs parents
-- Un parent peut avoir plusieurs enfants
-- ============================================================
CREATE TABLE eleve_parents (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    eleve_id    INT UNSIGNED NOT NULL,
    parent_id   INT UNSIGNED NOT NULL,
    lien        ENUM('pere', 'mere', 'tuteur', 'autre') DEFAULT 'tuteur'
                COMMENT 'Lien de parenté',
    methode     ENUM('qr_code', 'code_secret', 'admin') DEFAULT 'admin'
                COMMENT 'Comment la liaison a été créée',
    lie_le      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                COMMENT 'Date de création du lien',

    UNIQUE KEY unique_eleve_parent (eleve_id, parent_id),

    CONSTRAINT fk_ep_eleve
        FOREIGN KEY (eleve_id) REFERENCES eleves(id) ON DELETE CASCADE,
    CONSTRAINT fk_ep_parent
        FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Liaison élève-parent — créée par QR Code, code secret ou admin';

-- ============================================================
-- DONNÉES DE DÉMO
-- ============================================================

-- École
INSERT INTO ecoles (id, nom, slug, adresse, telephone, email)
VALUES (1, 'Lycée Notre-Dame de Quaben', 'notre-dame-quaben',
        'Libreville, Gabon', '+241 01 23 45 67', 'contact@ndq.ga');

-- Classe
INSERT INTO classes (id, ecole_id, nom, niveau, annee_scolaire)
VALUES (1, 1, '3ème B', '3ème', '2025-2026');

-- Enseignants
INSERT INTO enseignants (id, ecole_id, prenom, nom, email, telephone)
VALUES
  (1, 1, 'Jean', 'Obiang',  'obiang@ndq.ga',  '+241 06 11 11 11'),
  (2, 1, 'Marie', 'Nze',    'nze@ndq.ga',     '+241 06 22 22 22'),
  (3, 1, 'Paul', 'Koumba',  'koumba@ndq.ga',  '+241 06 33 33 33');

-- Matières
INSERT INTO matieres (id, ecole_id, enseignant_id, nom, heures_semaine)
VALUES
  (1, 1, 1, 'Mathématiques', 4),
  (2, 1, 2, 'Français',      4),
  (3, 1, 3, 'Histoire-Géo',  3);

-- Matières de la classe 3ème B
INSERT INTO classe_matieres (classe_id, matiere_id)
VALUES (1, 1), (1, 2), (1, 3);

-- Élève
INSERT INTO eleves (id, ecole_id, classe_id, prenom, nom,
                    date_naissance, genre, code_secret, qr_token)
VALUES (
    1, 1, 1, 'Junior', 'Nguema',
    '2010-03-15', 'M',
    'SKL-2026-4521',
    'token_demo_junior_nguema_abc123'
);

-- Parents
INSERT INTO parents (id, ecole_id, prenom, nom, telephone, email, password)
VALUES
  (1, 1, 'Ewosso', 'D-Gall',  '+241 06 11 22 33', 'ewosso@mail.com',  '$2y$12$demo_hash_1'),
  (2, 1, 'Claire', 'Ewosso',  '+241 06 44 55 66', 'claire@mail.com',  '$2y$12$demo_hash_2'),
  (3, 1, 'Fidèle', 'Nguema',  '+241 06 77 88 99', 'fidele@mail.com',  '$2y$12$demo_hash_3');

-- Liaison élève-parents (3 parents liés à Junior)
INSERT INTO eleve_parents (eleve_id, parent_id, lien, methode)
VALUES
  (1, 1, 'pere',   'admin'),
  (1, 2, 'mere',   'admin'),
  (1, 3, 'tuteur', 'admin');

-- ============================================================
-- VUES UTILES POUR LA DÉMO
-- ============================================================

-- Vue : élève avec sa classe, son école et ses matières
CREATE OR REPLACE VIEW v_eleve_complet AS
SELECT
    e.id            AS eleve_id,
    e.prenom        AS eleve_prenom,
    e.nom           AS eleve_nom,
    e.photo_url,
    e.code_secret,
    e.qr_token,
    c.nom           AS classe_nom,
    c.niveau,
    ec.nom          AS ecole_nom,
    ec.slug         AS ecole_slug,
    GROUP_CONCAT(
        CONCAT(m.nom, ' (', ens.prenom, ' ', ens.nom, ')')
        ORDER BY m.nom SEPARATOR ' | '
    )               AS matieres_enseignants
FROM eleves e
JOIN classes c      ON e.classe_id = c.id
JOIN ecoles ec      ON e.ecole_id = ec.id
LEFT JOIN classe_matieres cm ON c.id = cm.classe_id
LEFT JOIN matieres m         ON cm.matiere_id = m.id
LEFT JOIN enseignants ens    ON m.enseignant_id = ens.id
GROUP BY e.id, e.prenom, e.nom, e.photo_url, e.code_secret,
         e.qr_token, c.nom, c.niveau, ec.nom, ec.slug;

-- Vue : parent avec ses enfants liés
CREATE OR REPLACE VIEW v_parent_enfants AS
SELECT
    p.id            AS parent_id,
    p.prenom        AS parent_prenom,
    p.nom           AS parent_nom,
    p.telephone,
    p.email,
    e.id            AS eleve_id,
    e.prenom        AS eleve_prenom,
    e.nom           AS eleve_nom,
    e.photo_url,
    c.nom           AS classe_nom,
    ec.nom          AS ecole_nom,
    ep.lien,
    ep.methode,
    ep.lie_le
FROM parents p
LEFT JOIN eleve_parents ep ON p.id = ep.parent_id
LEFT JOIN eleves e         ON ep.eleve_id = e.id
LEFT JOIN classes c        ON e.classe_id = c.id
LEFT JOIN ecoles ec        ON e.ecole_id = ec.id;

-- ============================================================
-- RÉSUMÉ DES TABLES
-- ============================================================
-- ecoles          → établissements scolaires
-- classes         → classes (appartiennent à une école)
-- enseignants     → enseignants (appartiennent à une école)
-- matieres        → matières (ont un enseignant responsable)
-- classe_matieres → jointure classe ↔ matière
-- eleves          → élèves (ont code_secret + qr_token)
-- parents         → parents (login téléphone ou email)
-- eleve_parents   → jointure élève ↔ parent (TABLE CENTRALE)
-- ============================================================
