-- ============================================================
-- SCRIPT DE RÉINITIALISATION COMPLÈTE DES DONNÉES
-- ⚠️  ATTENTION : Cette action est IRRÉVERSIBLE !
-- Exécuter dans phpMyAdmin ou la console SQL d'AlwaysData
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE `admin_informations`;
TRUNCATE TABLE `messages`;
TRUNCATE TABLE `conversations`;
TRUNCATE TABLE `appointments`;
TRUNCATE TABLE `attendances`;
TRUNCATE TABLE `devoirs`;
TRUNCATE TABLE `device_tokens`;
TRUNCATE TABLE `eleve_parents`;
TRUNCATE TABLE `eleves`;
TRUNCATE TABLE `enseignants`;
TRUNCATE TABLE `classes`;
TRUNCATE TABLE `parent_users`;
TRUNCATE TABLE `ecoles`;

SET FOREIGN_KEY_CHECKS = 1;

-- ✅ Toutes les tables sont vidées.
-- Les tables "migrations" et "password_reset_tokens" sont conservées.
