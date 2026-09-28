-- =====================================================
--  IMMO-GEST CI v2 — Base de données complète
--  Compatible MySQL / MariaDB / phpMyAdmin
--  Date : 2026-09-24
-- =====================================================
--
-- CHANGEMENTS vs v1 (schema.sql) :
--  * Contrats.type_contrat : ENUM('echu','a_echoir') — correction typo 'edu'
--  * Agences : champs légaux complets (sigle, RCCM, CNPS, agrément…) + logo
--  * Propriétaires : profession, genre, situation_matrimoniale, nombre_enfant,
--    taux_commission, mandat ; agence_id
--  * États des lieux : colonne `nom` ajoutée
--  * Albums.module : enum étendu à tous les modules + nouvelles tables
--  * Multi-agence : agence_id sur utilisateurs, biens, caisses, locataires, proprietaires
--  * Rôles cahier : superviseur, administrateur, gerant, comptable, caissier,
--    assistant, proprietaire, locataire (+ NULL pour Accès refusé)
--  * Permissions par action (voir/créer/modifier/supprimer/valider) + templates rôles
--  * Contrats : caution, avance, frais_agence
--  * Locataires : pièce identité, adresse, agence_id
--  * Locaux : surface, nombre_pieces, charges, caution_mois, avance_mois
--  * Loyers : montant_paye, reste_a_payer, penalite
--  * Transactions : mode_paiement, référence, catégorie, reçu, validation, journée, annulation
--  * Nouvelles tables : roles_permissions, recus, reversements, depenses,
--    versements_banque, demandes_location, relances, notifications,
--    journal_activite, parametres, restitutions_caution
--  * Journées caisses : fond_initial, ecart
--
-- COMPTES DÉMO (login / mot de passe) :
--   superviseur / Super@2026     — Superviseur (toutes agences)
--   admin       / Admin@2026     — Administrateur
--   gerant_adj  / GerantAdj@2026 — Gérant Agence Adjamé
--   gerant_coc  / GerantCoc@2026 — Gérant Agence Cocody
--   comptable   / Compta@2026    — Comptable
--   caissier    / Caisse@2026    — Caissier
--   assistant   / Assist@2026    — Assistant / Agent
--   proprio     / Proprio@2026   — Propriétaire (espace lecture)
--   locataire   / Locat@2026     — Locataire (espace lecture)
--   sansrole    / SansRole@2026  — Aucun rôle → page Accès refusé
--
-- =====================================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+00:00';

CREATE DATABASE IF NOT EXISTS `rouahimo`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `rouahimo`;

-- =====================================================
-- SUPPRESSION (ordre inverse des dépendances)
-- =====================================================
DROP TABLE IF EXISTS `journal_activite`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `relances`;
DROP TABLE IF EXISTS `albums`;
DROP TABLE IF EXISTS `reclamations`;
DROP TABLE IF EXISTS `restitutions_caution`;
DROP TABLE IF EXISTS `demandes_location`;
DROP TABLE IF EXISTS `versements_banque`;
DROP TABLE IF EXISTS `depenses`;
DROP TABLE IF EXISTS `reversements`;
DROP TABLE IF EXISTS `recus`;
DROP TABLE IF EXISTS `transactions`;
DROP TABLE IF EXISTS `loyers`;
DROP TABLE IF EXISTS `etats_lieux`;
DROP TABLE IF EXISTS `contrats`;
DROP TABLE IF EXISTS `locaux`;
DROP TABLE IF EXISTS `biens`;
DROP TABLE IF EXISTS `journees_caisses`;
DROP TABLE IF EXISTS `utilisateurs_caisses`;
DROP TABLE IF EXISTS `caisses`;
DROP TABLE IF EXISTS `utilisateurs_permissions`;
DROP TABLE IF EXISTS `parametres`;
DROP TABLE IF EXISTS `roles_permissions`;
DROP TABLE IF EXISTS `utilisateurs`;
DROP TABLE IF EXISTS `proprietaires`;
DROP TABLE IF EXISTS `locataires`;
DROP TABLE IF EXISTS `agences`;
DROP TABLE IF EXISTS `societes`;


-- =====================================================
-- 1. SOCIÉTÉS
-- =====================================================
CREATE TABLE `societes` (
    `societe_id`              INT AUTO_INCREMENT PRIMARY KEY,
    `nom`                     VARCHAR(150) NOT NULL,
    `sigle`                   VARCHAR(50) DEFAULT NULL,
    `registre_commerce`       VARCHAR(50) DEFAULT NULL,
    `compte_contribuable`     VARCHAR(50) DEFAULT NULL,
    `cnps`                    VARCHAR(50) DEFAULT NULL,
    `numero_agrement`         VARCHAR(50) DEFAULT NULL,
    `responsable`             VARCHAR(120) DEFAULT NULL,
    `telephone`               VARCHAR(30) DEFAULT NULL,
    `email`                   VARCHAR(100) DEFAULT NULL,
    `site_web`                VARCHAR(150) DEFAULT NULL,
    `adresse`                 TEXT DEFAULT NULL,
    `pays`                    VARCHAR(80) DEFAULT 'Côte d''Ivoire',
    `ville`                   VARCHAR(80) DEFAULT NULL,
    `quartier`                VARCHAR(80) DEFAULT NULL,
    `logo`                    VARCHAR(255) DEFAULT NULL,
    `date_creation`           DATE DEFAULT NULL,
    `date_enregistrement`     DATETIME DEFAULT CURRENT_TIMESTAMP,
    `statut`                  ENUM('actif','inactif') DEFAULT 'actif'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 2. AGENCES (champs légaux complets + logo)
-- =====================================================
CREATE TABLE `agences` (
    `agence_id`               INT AUTO_INCREMENT PRIMARY KEY,
    `societe_id`              INT NOT NULL,
    `nom`                     VARCHAR(150) NOT NULL,
    `sigle`                   VARCHAR(50) DEFAULT NULL,
    `registre_commerce`       VARCHAR(50) DEFAULT NULL,
    `compte_contribuable`     VARCHAR(50) DEFAULT NULL,
    `cnps`                    VARCHAR(50) DEFAULT NULL,
    `numero_agrement`         VARCHAR(50) DEFAULT NULL,
    `responsable`             VARCHAR(120) DEFAULT NULL,
    `telephone`               VARCHAR(30) DEFAULT NULL,
    `email`                   VARCHAR(100) DEFAULT NULL,
    `site_web`                VARCHAR(150) DEFAULT NULL,
    `pays`                    VARCHAR(80) DEFAULT 'Côte d''Ivoire',
    `ville`                   VARCHAR(80) DEFAULT NULL,
    `quartier`                VARCHAR(80) DEFAULT NULL,
    `adresse`                 TEXT DEFAULT NULL,
    `logo`                    VARCHAR(255) DEFAULT NULL,
    `date_creation`           DATE DEFAULT NULL,
    `date_enregistrement`     DATETIME DEFAULT CURRENT_TIMESTAMP,
    `statut`                  ENUM('actif','inactif') DEFAULT 'actif',
    KEY `idx_agences_societe` (`societe_id`),
    CONSTRAINT `fk_agences_societe` FOREIGN KEY (`societe_id`)
        REFERENCES `societes` (`societe_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 3. LOCATAIRES
-- =====================================================
CREATE TABLE `locataires` (
    `locataire_id`            INT AUTO_INCREMENT PRIMARY KEY,
    `agence_id`               INT DEFAULT NULL,
    `nom`                     VARCHAR(80) NOT NULL,
    `prenom`                  VARCHAR(80) NOT NULL,
    `telephone`               VARCHAR(30) DEFAULT NULL,
    `email`                   VARCHAR(100) DEFAULT NULL,
    `profession`              VARCHAR(100) DEFAULT NULL,
    `genre`                   ENUM('M','F') DEFAULT NULL,
    `situation_matrimoniale`  VARCHAR(30) DEFAULT NULL,
    `nombre_enfants`          INT DEFAULT 0,
    `piece_identite_type`     VARCHAR(40) DEFAULT NULL,
    `piece_identite_numero`   VARCHAR(60) DEFAULT NULL,
    `date_delivrance_piece`   DATE DEFAULT NULL,
    `date_expiration_piece`   DATE DEFAULT NULL,
    `adresse`                 TEXT DEFAULT NULL,
    `date_enregistrement`     DATETIME DEFAULT CURRENT_TIMESTAMP,
    `statut`                  ENUM('actif','inactif') DEFAULT 'actif',
    KEY `idx_locataires_agence` (`agence_id`),
    KEY `idx_locataires_nom` (`nom`,`prenom`),
    CONSTRAINT `fk_locataires_agence` FOREIGN KEY (`agence_id`)
        REFERENCES `agences` (`agence_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 4. PROPRIÉTAIRES
-- =====================================================
CREATE TABLE `proprietaires` (
    `proprietaire_id`         INT AUTO_INCREMENT PRIMARY KEY,
    `agence_id`               INT DEFAULT NULL,
    `nom`                     VARCHAR(80) NOT NULL,
    `prenom`                  VARCHAR(80) DEFAULT NULL,
    `telephone`               VARCHAR(30) DEFAULT NULL,
    `email`                   VARCHAR(100) DEFAULT NULL,
    `profession`              VARCHAR(100) DEFAULT NULL,
    `genre`                   ENUM('M','F') DEFAULT NULL,
    `situation_matrimoniale`  VARCHAR(30) DEFAULT NULL,
    `nombre_enfant`           INT DEFAULT 0,
    `adresse`                 TEXT DEFAULT NULL,
    `taux_commission`         DECIMAL(5,2) DEFAULT 10.00,
    `date_debut_mandat`       DATE DEFAULT NULL,
    `date_fin_mandat`         DATE DEFAULT NULL,
    `numero_mandat`           VARCHAR(50) DEFAULT NULL,
    `date_enregistrement`     DATETIME DEFAULT CURRENT_TIMESTAMP,
    `statut`                  ENUM('actif','inactif') DEFAULT 'actif',
    KEY `idx_proprietaires_agence` (`agence_id`),
    CONSTRAINT `fk_proprietaires_agence` FOREIGN KEY (`agence_id`)
        REFERENCES `agences` (`agence_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 5. UTILISATEURS
-- =====================================================
CREATE TABLE `utilisateurs` (
    `utilisateur_id`          INT AUTO_INCREMENT PRIMARY KEY,
    `agence_id`               INT DEFAULT NULL,
    `nom`                     VARCHAR(80) NOT NULL,
    `prenom`                  VARCHAR(80) NOT NULL,
    `login`                   VARCHAR(50) NOT NULL,
    `mdp`                     VARCHAR(255) NOT NULL,
    `telephone`               VARCHAR(30) DEFAULT NULL,
    `email`                   VARCHAR(100) DEFAULT NULL,
    `role`                    ENUM(
                                'superviseur','administrateur','gerant','comptable',
                                'caissier','assistant','proprietaire','locataire',
                                'gestionnaire','partenaire','client'
                              ) DEFAULT NULL,
    `locataire_id`            INT DEFAULT NULL,
    `proprietaire_id`         INT DEFAULT NULL,
    `photo`                   TEXT DEFAULT NULL,
    `type_photo`              VARCHAR(20) DEFAULT NULL,
    `date_enregistrement`     DATETIME DEFAULT CURRENT_TIMESTAMP,
    `statut`                  ENUM('actif','inactif') DEFAULT 'actif',
    UNIQUE KEY `uk_login` (`login`),
    KEY `idx_utilisateurs_agence` (`agence_id`),
    KEY `idx_utilisateurs_role` (`role`),
    CONSTRAINT `fk_utilisateurs_agence` FOREIGN KEY (`agence_id`)
        REFERENCES `agences` (`agence_id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_utilisateurs_locataire` FOREIGN KEY (`locataire_id`)
        REFERENCES `locataires` (`locataire_id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_utilisateurs_proprietaire` FOREIGN KEY (`proprietaire_id`)
        REFERENCES `proprietaires` (`proprietaire_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 6. TEMPLATES PERMISSIONS PAR RÔLE
-- =====================================================
CREATE TABLE `roles_permissions` (
    `role_permission_id`      INT AUTO_INCREMENT PRIMARY KEY,
    `role`                    ENUM(
                                'superviseur','administrateur','gerant','comptable',
                                'caissier','assistant','proprietaire','locataire',
                                'gestionnaire','partenaire','client'
                              ) NOT NULL,
    `module`                  ENUM(
                                'accueil','locataire','proprietaire','caisse',
                                'recus','parametre','administration','dashboard','rapport'
                              ) NOT NULL,
    `peut_voir`               TINYINT(1) NOT NULL DEFAULT 0,
    `peut_creer`              TINYINT(1) NOT NULL DEFAULT 0,
    `peut_modifier`           TINYINT(1) NOT NULL DEFAULT 0,
    `peut_supprimer`          TINYINT(1) NOT NULL DEFAULT 0,
    `peut_valider`            TINYINT(1) NOT NULL DEFAULT 0,
    UNIQUE KEY `uk_role_module` (`role`,`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 7. PERMISSIONS UTILISATEURS
-- =====================================================
CREATE TABLE `utilisateurs_permissions` (
    `permission_id`           INT AUTO_INCREMENT PRIMARY KEY,
    `utilisateur_id`          INT NOT NULL,
    `module`                  ENUM(
                                'accueil','locataire','proprietaire','caisse',
                                'recus','parametre','administration','dashboard','rapport'
                              ) NOT NULL,
    `peut_voir`               TINYINT(1) NOT NULL DEFAULT 0,
    `peut_creer`              TINYINT(1) NOT NULL DEFAULT 0,
    `peut_modifier`           TINYINT(1) NOT NULL DEFAULT 0,
    `peut_supprimer`          TINYINT(1) NOT NULL DEFAULT 0,
    `peut_valider`            TINYINT(1) NOT NULL DEFAULT 0,
    `statut`                  ENUM('actif','inactif') DEFAULT 'actif',
    UNIQUE KEY `uk_user_module` (`utilisateur_id`,`module`),
    CONSTRAINT `fk_perm_utilisateur` FOREIGN KEY (`utilisateur_id`)
        REFERENCES `utilisateurs` (`utilisateur_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- 8. CAISSES
-- =====================================================
CREATE TABLE `caisses` (
    `caisse_id`               INT AUTO_INCREMENT PRIMARY KEY,
    `agence_id`               INT DEFAULT NULL,
    `nom`                     VARCHAR(100) NOT NULL,
    `solde`                   DECIMAL(15,2) DEFAULT 0.00,
    `plafond`                 DECIMAL(15,2) DEFAULT NULL,
    `statut`                  ENUM('actif','inactif') DEFAULT 'actif',
    KEY `idx_caisses_agence` (`agence_id`),
    CONSTRAINT `fk_caisses_agence` FOREIGN KEY (`agence_id`)
        REFERENCES `agences` (`agence_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 9. UTILISATEURS ↔ CAISSES
-- =====================================================
CREATE TABLE `utilisateurs_caisses` (
    `id`                      INT AUTO_INCREMENT PRIMARY KEY,
    `utilisateur_id`          INT NOT NULL,
    `caisse_id`               INT NOT NULL,
    `date_enregistrement`     DATETIME DEFAULT CURRENT_TIMESTAMP,
    `statut`                  ENUM('actif','inactif') DEFAULT 'actif',
    UNIQUE KEY `uk_user_caisse` (`utilisateur_id`,`caisse_id`),
    CONSTRAINT `fk_uc_utilisateur` FOREIGN KEY (`utilisateur_id`)
        REFERENCES `utilisateurs` (`utilisateur_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_uc_caisse` FOREIGN KEY (`caisse_id`)
        REFERENCES `caisses` (`caisse_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 10. JOURNÉES DE CAISSE
-- =====================================================
CREATE TABLE `journees_caisses` (
    `journee_id`                  INT AUTO_INCREMENT PRIMARY KEY,
    `caisse_id`                   INT NOT NULL,
    `date_ouverture`              DATE NOT NULL,
    `heure_ouverture`             TIME DEFAULT NULL,
    `date_fermeture`              DATE DEFAULT NULL,
    `heure_fermeture`             TIME DEFAULT NULL,
    `fond_initial`                DECIMAL(15,2) DEFAULT 0.00,
    `nombre_entrees`              INT DEFAULT 0,
    `nombre_sorties`              INT DEFAULT 0,
    `total_entrees`               DECIMAL(15,2) DEFAULT 0.00,
    `total_sorties`               DECIMAL(15,2) DEFAULT 0.00,
    `solde_theorique`             DECIMAL(15,2) DEFAULT NULL,
    `solde_physique`              DECIMAL(15,2) DEFAULT NULL,
    `ecart`                       DECIMAL(15,2) DEFAULT NULL,
    `utilisateur_id_ouverture`    INT DEFAULT NULL,
    `utilisateur_id_fermeture`    INT DEFAULT NULL,
    `statut`                      ENUM('ouverte','fermee','actif','inactif') DEFAULT 'ouverte',
    KEY `idx_jc_caisse_date` (`caisse_id`,`date_ouverture`),
    CONSTRAINT `fk_jc_caisse` FOREIGN KEY (`caisse_id`)
        REFERENCES `caisses` (`caisse_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_jc_user_ouv` FOREIGN KEY (`utilisateur_id_ouverture`)
        REFERENCES `utilisateurs` (`utilisateur_id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_jc_user_ferm` FOREIGN KEY (`utilisateur_id_fermeture`)
        REFERENCES `utilisateurs` (`utilisateur_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 11. BIENS
-- =====================================================
CREATE TABLE `biens` (
    `bien_id`                 INT AUTO_INCREMENT PRIMARY KEY,
    `agence_id`               INT DEFAULT NULL,
    `proprietaire_id`         INT DEFAULT NULL,
    `nom`                     VARCHAR(150) NOT NULL,
    `description`             TEXT DEFAULT NULL,
    `pays`                    VARCHAR(80) DEFAULT 'Côte d''Ivoire',
    `ville`                   VARCHAR(80) DEFAULT NULL,
    `quartier`                VARCHAR(80) DEFAULT NULL,
    `adresse`                 TEXT DEFAULT NULL,
    `longitude`               DECIMAL(10,7) DEFAULT NULL,
    `latitude`                DECIMAL(10,7) DEFAULT NULL,
    `statut`                  ENUM('actif','inactif') DEFAULT 'actif',
    KEY `idx_biens_agence` (`agence_id`),
    KEY `idx_biens_proprietaire` (`proprietaire_id`),
    CONSTRAINT `fk_biens_agence` FOREIGN KEY (`agence_id`)
        REFERENCES `agences` (`agence_id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_biens_proprietaire` FOREIGN KEY (`proprietaire_id`)
        REFERENCES `proprietaires` (`proprietaire_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 12. LOCAUX
-- =====================================================
CREATE TABLE `locaux` (
    `local_id`                INT AUTO_INCREMENT PRIMARY KEY,
    `bien_id`                 INT NOT NULL,
    `nom`                     VARCHAR(100) DEFAULT NULL,
    `description`             TEXT DEFAULT NULL,
    `type_local`              ENUM('appartement','maison','magasin','bureau','studio','villa','autre') DEFAULT NULL,
    `surface`                 DECIMAL(8,2) DEFAULT NULL,
    `nombre_pieces`           INT DEFAULT NULL,
    `prix_loyer`              DECIMAL(12,2) DEFAULT NULL,
    `charges`                 DECIMAL(12,2) DEFAULT 0.00,
    `caution_mois`            INT DEFAULT 2,
    `avance_mois`             INT DEFAULT 1,
    `statut`                  ENUM('occupe','libre','reserve') DEFAULT 'libre',
    KEY `idx_locaux_bien` (`bien_id`),
    KEY `idx_locaux_statut` (`statut`),
    CONSTRAINT `fk_locaux_bien` FOREIGN KEY (`bien_id`)
        REFERENCES `biens` (`bien_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 13. CONTRATS
-- =====================================================
CREATE TABLE `contrats` (
    `contrat_id`              INT AUTO_INCREMENT PRIMARY KEY,
    `locataire_id`            INT NOT NULL,
    `local_id`                INT NOT NULL,
    `date_signature`          DATE DEFAULT NULL,
    `date_debut`              DATE NOT NULL,
    `date_fin`                DATE DEFAULT NULL,
    `duree`                   INT DEFAULT NULL,
    `date_preavis`            DATE DEFAULT NULL,
    `loyer_nu`                DECIMAL(12,2) NOT NULL,
    `caution`                 DECIMAL(12,2) DEFAULT 0.00,
    `avance`                  DECIMAL(12,2) DEFAULT 0.00,
    `frais_agence`            DECIMAL(12,2) DEFAULT 0.00,
    `type_montant`            VARCHAR(30) DEFAULT NULL,
    `type_contrat`            ENUM('echu','a_echoir') DEFAULT 'a_echoir',
    `frequence`               ENUM('journalier','hebdomadaire','mensuel','trimestriel','semestriel','annuel') DEFAULT 'mensuel',
    `motif_sortie`            TEXT DEFAULT NULL,
    `echeance`                DATE DEFAULT NULL,
    `statut`                  ENUM('provisoire','actif','inactif') DEFAULT 'provisoire',
    KEY `idx_contrats_locataire` (`locataire_id`),
    KEY `idx_contrats_local` (`local_id`),
    KEY `idx_contrats_statut` (`statut`),
    CONSTRAINT `fk_contrats_locataire` FOREIGN KEY (`locataire_id`)
        REFERENCES `locataires` (`locataire_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_contrats_local` FOREIGN KEY (`local_id`)
        REFERENCES `locaux` (`local_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 14. ÉTATS DES LIEUX
-- =====================================================
CREATE TABLE `etats_lieux` (
    `etat_id`                 INT AUTO_INCREMENT PRIMARY KEY,
    `contrat_id`              INT NOT NULL,
    `utilisateur_id`          INT DEFAULT NULL,
    `nom`                     VARCHAR(150) DEFAULT NULL,
    `date_etat`               DATE NOT NULL,
    `type_etat`               ENUM('entree','sortie') DEFAULT NULL,
    `observations`            TEXT DEFAULT NULL,
    `statut`                  ENUM('actif','inactif') DEFAULT 'actif',
    CONSTRAINT `fk_el_contrat` FOREIGN KEY (`contrat_id`)
        REFERENCES `contrats` (`contrat_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_el_utilisateur` FOREIGN KEY (`utilisateur_id`)
        REFERENCES `utilisateurs` (`utilisateur_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 15. LOYERS
-- =====================================================
CREATE TABLE `loyers` (
    `loyer_id`                INT AUTO_INCREMENT PRIMARY KEY,
    `contrat_id`              INT NOT NULL,
    `montant`                 DECIMAL(12,2) NOT NULL,
    `montant_paye`            DECIMAL(12,2) DEFAULT 0.00,
    `reste_a_payer`           DECIMAL(12,2) DEFAULT 0.00,
    `penalite`                DECIMAL(12,2) DEFAULT 0.00,
    `mois`                    TINYINT DEFAULT NULL,
    `annee`                   SMALLINT DEFAULT NULL,
    `date_echeance`           DATE DEFAULT NULL,
    `statut`                  ENUM('en_attente','paye','partiel','annule','en_retard') DEFAULT 'en_attente',
    KEY `idx_loyers_contrat` (`contrat_id`),
    KEY `idx_loyers_statut` (`statut`),
    KEY `idx_loyers_periode` (`annee`,`mois`),
    CONSTRAINT `fk_loyers_contrat` FOREIGN KEY (`contrat_id`)
        REFERENCES `contrats` (`contrat_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- 16. TRANSACTIONS
-- =====================================================
CREATE TABLE `transactions` (
    `transaction_id`          INT AUTO_INCREMENT PRIMARY KEY,
    `date_transaction`        DATE NOT NULL,
    `heure`                   TIME DEFAULT NULL,
    `montant`                 DECIMAL(15,2) NOT NULL,
    `type_transaction`        ENUM('entree','sortie') DEFAULT NULL,
    `categorie`               ENUM(
                                'loyer','caution','avance','frais','depense',
                                'reversement','versement_banque','remboursement_caution','autre'
                              ) DEFAULT 'autre',
    `mode_paiement`           ENUM(
                                'especes','orange_money','mtn_momo','moov_money',
                                'wave','cheque','virement'
                              ) DEFAULT NULL,
    `reference_paiement`      VARCHAR(100) DEFAULT NULL,
    `objet`                   VARCHAR(200) DEFAULT NULL,
    `numero_recu`             VARCHAR(30) DEFAULT NULL,
    `loyer_id`                INT DEFAULT NULL,
    `caisse_id`               INT DEFAULT NULL,
    `journee_id`              INT DEFAULT NULL,
    `utilisateur_id`          INT DEFAULT NULL,
    `valide_par`              INT DEFAULT NULL,
    `date_validation`         DATETIME DEFAULT NULL,
    `annule_transaction_id`   INT DEFAULT NULL,
    `etat`                    ENUM('valide','refuse','en_attente') DEFAULT 'en_attente',
    `statut`                  ENUM('succes','echec','en_attente','annule') DEFAULT 'en_attente',
    `nature_beneficiaire`     ENUM('locataire','proprietaire','utilisateur','autres') DEFAULT NULL,
    `id_beneficiaire`         INT DEFAULT NULL,
    `nom_beneficiaire`        VARCHAR(150) DEFAULT NULL,
    KEY `idx_trans_date` (`date_transaction`),
    KEY `idx_trans_caisse` (`caisse_id`),
    KEY `idx_trans_categorie` (`categorie`),
    CONSTRAINT `fk_trans_loyer` FOREIGN KEY (`loyer_id`)
        REFERENCES `loyers` (`loyer_id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_trans_caisse` FOREIGN KEY (`caisse_id`)
        REFERENCES `caisses` (`caisse_id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_trans_journee` FOREIGN KEY (`journee_id`)
        REFERENCES `journees_caisses` (`journee_id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_trans_utilisateur` FOREIGN KEY (`utilisateur_id`)
        REFERENCES `utilisateurs` (`utilisateur_id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_trans_valide_par` FOREIGN KEY (`valide_par`)
        REFERENCES `utilisateurs` (`utilisateur_id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_trans_annule` FOREIGN KEY (`annule_transaction_id`)
        REFERENCES `transactions` (`transaction_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 17. REÇUS
-- =====================================================
CREATE TABLE `recus` (
    `recu_id`                 INT AUTO_INCREMENT PRIMARY KEY,
    `numero`                  VARCHAR(30) NOT NULL,
    `transaction_id`          INT DEFAULT NULL,
    `type_recu`               ENUM('recu','annulation','reversement','remboursement') DEFAULT 'recu',
    `nb_impressions`          INT NOT NULL DEFAULT 0,
    `qr_code`                 VARCHAR(255) DEFAULT NULL,
    `montant_lettres`         VARCHAR(255) DEFAULT NULL,
    `agence_id`               INT DEFAULT NULL,
    `date_recu`               DATETIME DEFAULT CURRENT_TIMESTAMP,
    `statut`                  ENUM('actif','inactif') DEFAULT 'actif',
    UNIQUE KEY `uk_recu_numero` (`numero`),
    KEY `idx_recus_transaction` (`transaction_id`),
    CONSTRAINT `fk_recus_transaction` FOREIGN KEY (`transaction_id`)
        REFERENCES `transactions` (`transaction_id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_recus_agence` FOREIGN KEY (`agence_id`)
        REFERENCES `agences` (`agence_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 18. REVERSEMENTS
-- =====================================================
CREATE TABLE `reversements` (
    `reversement_id`          INT AUTO_INCREMENT PRIMARY KEY,
    `proprietaire_id`         INT NOT NULL,
    `agence_id`               INT DEFAULT NULL,
    `mois`                    TINYINT NOT NULL,
    `annee`                   SMALLINT NOT NULL,
    `montant_brut`            DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `commission`              DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `depenses`                DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `montant_net`             DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `transaction_id`          INT DEFAULT NULL,
    `date_reversement`        DATE DEFAULT NULL,
    `statut`                  ENUM('brouillon','valide','paye','annule') DEFAULT 'brouillon',
    KEY `idx_rev_proprio_periode` (`proprietaire_id`,`annee`,`mois`),
    CONSTRAINT `fk_rev_proprietaire` FOREIGN KEY (`proprietaire_id`)
        REFERENCES `proprietaires` (`proprietaire_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_rev_agence` FOREIGN KEY (`agence_id`)
        REFERENCES `agences` (`agence_id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_rev_transaction` FOREIGN KEY (`transaction_id`)
        REFERENCES `transactions` (`transaction_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 19. DÉPENSES
-- =====================================================
CREATE TABLE `depenses` (
    `depense_id`              INT AUTO_INCREMENT PRIMARY KEY,
    `bien_id`                 INT DEFAULT NULL,
    `local_id`                INT DEFAULT NULL,
    `proprietaire_id`         INT DEFAULT NULL,
    `agence_id`               INT DEFAULT NULL,
    `libelle`                 VARCHAR(200) NOT NULL,
    `montant`                 DECIMAL(12,2) NOT NULL,
    `date_depense`            DATE NOT NULL,
    `reversement_id`          INT DEFAULT NULL,
    `transaction_id`          INT DEFAULT NULL,
    `statut`                  ENUM('actif','inactif','deduite') DEFAULT 'actif',
    KEY `idx_depenses_proprio` (`proprietaire_id`),
    CONSTRAINT `fk_dep_bien` FOREIGN KEY (`bien_id`)
        REFERENCES `biens` (`bien_id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_dep_local` FOREIGN KEY (`local_id`)
        REFERENCES `locaux` (`local_id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_dep_proprietaire` FOREIGN KEY (`proprietaire_id`)
        REFERENCES `proprietaires` (`proprietaire_id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_dep_agence` FOREIGN KEY (`agence_id`)
        REFERENCES `agences` (`agence_id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_dep_reversement` FOREIGN KEY (`reversement_id`)
        REFERENCES `reversements` (`reversement_id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_dep_transaction` FOREIGN KEY (`transaction_id`)
        REFERENCES `transactions` (`transaction_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 20. VERSEMENTS BANQUE
-- =====================================================
CREATE TABLE `versements_banque` (
    `versement_id`            INT AUTO_INCREMENT PRIMARY KEY,
    `caisse_id`               INT NOT NULL,
    `journee_id`              INT DEFAULT NULL,
    `banque`                  VARCHAR(100) NOT NULL,
    `montant`                 DECIMAL(15,2) NOT NULL,
    `reference_bordereau`     VARCHAR(80) DEFAULT NULL,
    `date_versement`          DATE NOT NULL,
    `utilisateur_id`          INT DEFAULT NULL,
    `transaction_id`          INT DEFAULT NULL,
    `statut`                  ENUM('actif','inactif') DEFAULT 'actif',
    CONSTRAINT `fk_vb_caisse` FOREIGN KEY (`caisse_id`)
        REFERENCES `caisses` (`caisse_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_vb_journee` FOREIGN KEY (`journee_id`)
        REFERENCES `journees_caisses` (`journee_id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_vb_utilisateur` FOREIGN KEY (`utilisateur_id`)
        REFERENCES `utilisateurs` (`utilisateur_id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_vb_transaction` FOREIGN KEY (`transaction_id`)
        REFERENCES `transactions` (`transaction_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 21. DEMANDES DE LOCATION
-- =====================================================
CREATE TABLE `demandes_location` (
    `demande_id`              INT AUTO_INCREMENT PRIMARY KEY,
    `local_id`                INT NOT NULL,
    `nom`                     VARCHAR(80) NOT NULL,
    `prenom`                  VARCHAR(80) NOT NULL,
    `telephone`               VARCHAR(30) DEFAULT NULL,
    `email`                   VARCHAR(100) DEFAULT NULL,
    `profession`              VARCHAR(100) DEFAULT NULL,
    `piece_identite_type`     VARCHAR(40) DEFAULT NULL,
    `piece_identite_numero`   VARCHAR(60) DEFAULT NULL,
    `message`                 TEXT DEFAULT NULL,
    `statut`                  ENUM('nouvelle','en_cours','acceptee','refusee') DEFAULT 'nouvelle',
    `traite_par`              INT DEFAULT NULL,
    `contrat_id`              INT DEFAULT NULL,
    `date_demande`            DATETIME DEFAULT CURRENT_TIMESTAMP,
    `date_traitement`         DATETIME DEFAULT NULL,
    KEY `idx_demandes_statut` (`statut`),
    CONSTRAINT `fk_dl_local` FOREIGN KEY (`local_id`)
        REFERENCES `locaux` (`local_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_dl_traite` FOREIGN KEY (`traite_par`)
        REFERENCES `utilisateurs` (`utilisateur_id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_dl_contrat` FOREIGN KEY (`contrat_id`)
        REFERENCES `contrats` (`contrat_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 22. RESTITUTIONS DE CAUTION
-- =====================================================
CREATE TABLE `restitutions_caution` (
    `restitution_id`          INT AUTO_INCREMENT PRIMARY KEY,
    `contrat_id`              INT NOT NULL,
    `caution`                 DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `reparations`             DECIMAL(12,2) DEFAULT 0.00,
    `impayes`                 DECIMAL(12,2) DEFAULT 0.00,
    `montant_rendu`           DECIMAL(12,2) DEFAULT 0.00,
    `transaction_id`          INT DEFAULT NULL,
    `date_restitution`        DATE DEFAULT NULL,
    `statut`                  ENUM('brouillon','valide','paye') DEFAULT 'brouillon',
    CONSTRAINT `fk_rc_contrat` FOREIGN KEY (`contrat_id`)
        REFERENCES `contrats` (`contrat_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_rc_transaction` FOREIGN KEY (`transaction_id`)
        REFERENCES `transactions` (`transaction_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 23. RÉCLAMATIONS
-- =====================================================
CREATE TABLE `reclamations` (
    `reclamation_id`          INT AUTO_INCREMENT PRIMARY KEY,
    `nom`                     VARCHAR(150) DEFAULT NULL,
    `observation`             TEXT DEFAULT NULL,
    `contrat_id`              INT DEFAULT NULL,
    `date_reclamation`        DATE DEFAULT NULL,
    `date_reglement`          DATE DEFAULT NULL,
    `utilisateur_id`          INT DEFAULT NULL,
    `statut`                  ENUM('valide','rejete','en_attente') DEFAULT 'en_attente',
    CONSTRAINT `fk_recla_contrat` FOREIGN KEY (`contrat_id`)
        REFERENCES `contrats` (`contrat_id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_recla_utilisateur` FOREIGN KEY (`utilisateur_id`)
        REFERENCES `utilisateurs` (`utilisateur_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 24. ALBUMS
-- =====================================================
CREATE TABLE `albums` (
    `album_id`                INT AUTO_INCREMENT PRIMARY KEY,
    `nom`                     VARCHAR(150) DEFAULT NULL,
    `type_media`              ENUM('photos','videos','audio','documents') DEFAULT 'photos',
    `url`                     TEXT DEFAULT NULL,
    `module`                  ENUM(
                                'reclamation','transaction','loyer','etat_lieux','contrat',
                                'bien','local','proprietaire','locataire','journee_caisse',
                                'utilisateur_caisse','caisse','utilisateur_permission',
                                'agence','utilisateur','societe','depense','versement_banque',
                                'demande_location','reversement','restitution_caution'
                              ) DEFAULT NULL,
    `module_id`               INT DEFAULT NULL,
    `statut`                  ENUM('actif','inactif') DEFAULT 'actif',
    KEY `idx_albums_module` (`module`,`module_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 25. RELANCES
-- =====================================================
CREATE TABLE `relances` (
    `relance_id`              INT AUTO_INCREMENT PRIMARY KEY,
    `loyer_id`                INT NOT NULL,
    `canal`                   ENUM('whatsapp','sms','email') NOT NULL,
    `message`                 TEXT DEFAULT NULL,
    `date_relance`            DATETIME DEFAULT CURRENT_TIMESTAMP,
    `utilisateur_id`          INT DEFAULT NULL,
    `statut`                  ENUM('envoyee','echec','planifiee') DEFAULT 'envoyee',
    CONSTRAINT `fk_rel_loyer` FOREIGN KEY (`loyer_id`)
        REFERENCES `loyers` (`loyer_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_rel_utilisateur` FOREIGN KEY (`utilisateur_id`)
        REFERENCES `utilisateurs` (`utilisateur_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 26. NOTIFICATIONS
-- =====================================================
CREATE TABLE `notifications` (
    `notification_id`         INT AUTO_INCREMENT PRIMARY KEY,
    `utilisateur_id`          INT NOT NULL,
    `type`                    VARCHAR(50) DEFAULT NULL,
    `titre`                   VARCHAR(150) NOT NULL,
    `message`                 TEXT DEFAULT NULL,
    `lu`                      TINYINT(1) NOT NULL DEFAULT 0,
    `date_notification`       DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_notif_user` (`utilisateur_id`,`lu`),
    CONSTRAINT `fk_notif_utilisateur` FOREIGN KEY (`utilisateur_id`)
        REFERENCES `utilisateurs` (`utilisateur_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 27. JOURNAL D'ACTIVITÉ
-- =====================================================
CREATE TABLE `journal_activite` (
    `journal_id`              INT AUTO_INCREMENT PRIMARY KEY,
    `utilisateur_id`          INT DEFAULT NULL,
    `action`                  VARCHAR(80) NOT NULL,
    `module`                  VARCHAR(50) DEFAULT NULL,
    `element_id`              INT DEFAULT NULL,
    `details`                 TEXT DEFAULT NULL,
    `ip`                      VARCHAR(45) DEFAULT NULL,
    `appareil`                VARCHAR(150) DEFAULT NULL,
    `date_action`             DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_journal_date` (`date_action`),
    KEY `idx_journal_user` (`utilisateur_id`),
    CONSTRAINT `fk_journal_utilisateur` FOREIGN KEY (`utilisateur_id`)
        REFERENCES `utilisateurs` (`utilisateur_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 28. PARAMÈTRES
-- =====================================================
CREATE TABLE `parametres` (
    `parametre_id`            INT AUTO_INCREMENT PRIMARY KEY,
    `agence_id`               INT DEFAULT NULL,
    `cle`                     VARCHAR(80) NOT NULL,
    `valeur`                  TEXT DEFAULT NULL,
    `description`             VARCHAR(255) DEFAULT NULL,
    UNIQUE KEY `uk_param_agence_cle` (`agence_id`,`cle`),
    CONSTRAINT `fk_param_agence` FOREIGN KEY (`agence_id`)
        REFERENCES `agences` (`agence_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- DONNÉES DÉMO — Côte d''Ivoire / Abidjan / FCFA
-- =====================================================

INSERT INTO `societes` (
  `nom`,`sigle`,`registre_commerce`,`compte_contribuable`,`cnps`,`numero_agrement`,
  `responsable`,`telephone`,`email`,`site_web`,`adresse`,`pays`,`ville`,`quartier`,
  `logo`,`date_creation`,`statut`
) VALUES (
  'IMMO-GEST Côte d''Ivoire SARL','IGCI','CI-ABJ-2020-B-15234','0104567A','CNPS-789456',
  'AGR-IMMO-2021-045','Kouassi Yao Jean','+225 07 08 09 10 11','contact@immogest-ci.ci',
  'https://www.immogest-ci.ci','Boulevard de la République, Plateau','Côte d''Ivoire','Abidjan','Plateau',
  'assets/logo-rouahimo.svg','2020-03-15','actif'
);

INSERT INTO `agences` (
  `societe_id`,`nom`,`sigle`,`registre_commerce`,`compte_contribuable`,`cnps`,`numero_agrement`,
  `responsable`,`telephone`,`email`,`site_web`,`pays`,`ville`,`quartier`,`adresse`,
  `logo`,`date_creation`,`statut`
) VALUES
(1,'Agence Adjamé','IGCI-ADJ','CI-ABJ-2020-B-15234-A','0104567A-01','CNPS-789456-A','AGR-IMMO-2021-045-A',
 'Traoré Awa','+225 07 01 02 03 04','adjame@immogest-ci.ci','https://www.immogest-ci.ci/adjame',
 'Côte d''Ivoire','Abidjan','Adjamé','Marché Adjamé, Avenue 16','logos/adjame.svg','2020-05-01','actif'),
(1,'Agence Cocody','IGCI-COC','CI-ABJ-2020-B-15234-C','0104567A-02','CNPS-789456-C','AGR-IMMO-2021-045-C',
 'N''Guessan Kouadio','+225 05 06 07 08 09','cocody@immogest-ci.ci','https://www.immogest-ci.ci/cocody',
 'Côte d''Ivoire','Abidjan','Cocody','Riviera 3, près Carrefour de la Vie','logos/cocody.svg','2021-01-12','actif');

INSERT INTO `proprietaires` (
  `agence_id`,`nom`,`prenom`,`telephone`,`email`,`profession`,`genre`,`situation_matrimoniale`,
  `nombre_enfant`,`adresse`,`taux_commission`,`date_debut_mandat`,`date_fin_mandat`,`numero_mandat`,`statut`
) VALUES
(1,'Koffi','Jean-Baptiste','+225 07 11 22 33 44','jb.koffi@email.ci','Commerçant','M','marie',3,
 'Yopougon Sicogi','10.00','2022-01-01','2027-01-01','MDT-2022-001','actif'),
(1,'Bamba','Aminata','+225 05 22 33 44 55','aminata.bamba@email.ci','Comptable','F','marie',2,
 'Adjamé Liberté','10.00','2023-03-01','2028-03-01','MDT-2023-014','actif'),
(2,'Ouattara','Seydou','+225 01 33 44 55 66','seydou.ouattara@email.ci','Ingénieur','M','celibataire',0,
 'Cocody Angré 8ème Tranche','12.00','2021-06-15','2026-06-15','MDT-2021-008','actif'),
(2,'Koné','Mariame','+225 07 44 55 66 77','mariame.kone@email.ci','Médecin','F','marie',1,
 'Riviera Palmeraie','10.00','2022-09-01','2027-09-01','MDT-2022-022','actif'),
(2,'Diallo','Ibrahim','+225 05 55 66 77 88','ibrahim.diallo@email.ci','Entrepreneur','M','marie',4,
 'Marcory Zone 4','8.00','2024-01-01','2029-01-01','MDT-2024-003','actif');

INSERT INTO `locataires` (
  `agence_id`,`nom`,`prenom`,`telephone`,`email`,`profession`,`genre`,`situation_matrimoniale`,
  `nombre_enfants`,`piece_identite_type`,`piece_identite_numero`,`date_delivrance_piece`,`date_expiration_piece`,`adresse`,`statut`
) VALUES
(1,'Yao','Affoué','+225 07 12 13 14 15','affoue.yao@email.ci','Enseignante','F','marie',2,'CNI','CI001234567','2022-03-12','2032-03-11','Adjamé 220 Logements','actif'),
(1,'Touré','Mamadou','+225 05 23 24 25 26','mamadou.toure@email.ci','Chauffeur','M','celibataire',0,'CNI','CI002345678','2021-06-01','2031-05-31','Adjamé Bracodi','actif'),
(1,'Soro','Fatou','+225 01 34 35 36 37','fatou.soro@email.ci','Couturière','F','veuve',1,'Attestation','ATT-2023-889','2023-01-15','2028-01-14','Yopougon Maroc','actif'),
(1,'Aka','Christian','+225 07 45 46 47 48','christian.aka@email.ci','Informaticien','M','marie',1,'CNI','CI003456789','2020-11-20','2030-11-19','Plateau','actif'),
(2,'Coulibaly','Aïcha','+225 05 56 57 58 59','aicha.coulibaly@email.ci','Banquière','F','marie',2,'Passeport','P2023456789','2019-04-02','2029-04-01','Cocody Danga','actif'),
(2,'Gbaké','Laurent','+225 07 67 68 69 70','laurent.gbake@email.ci','Architecte','M','celibataire',0,'CNI','CI004567890','2022-08-09','2032-08-08','Riviera 2','actif'),
(2,'Traoré','Salimata','+225 01 78 79 80 81','salimata.traore@email.ci','Pharmacienne','F','marie',3,'CNI','CI005678901','2021-02-14','2031-02-13','Cocody II Plateaux','actif'),
(2,'N''dri','Yves','+225 05 89 90 91 92','yves.ndri@email.ci','Journaliste','M','divorce',1,'CNI','CI006789012','2018-09-30','2028-09-29','Marcory Résidentiel','actif'),
(2,'Brou','Esther','+225 07 90 91 92 93','esther.brou@email.ci','Étudiante','F','celibataire',0,'CNI','CI007890123','2024-01-10','2034-01-09','Cocody Université','actif'),
(1,'Konan','Pascal','+225 01 01 02 03 04','pascal.konan@email.ci','Électricien','M','marie',2,'CNI','CI008901234','2023-05-22','2033-05-21','Adjamé Mosquée','actif');

INSERT INTO `utilisateurs` (
  `agence_id`,`nom`,`prenom`,`login`,`mdp`,`telephone`,`email`,`role`,
  `locataire_id`,`proprietaire_id`,`photo`,`statut`
) VALUES
(NULL,'Kouamé','Onno','superviseur','$2y$10$rwdxz3VB3Jx/gd7VtNhp5.t3HS0fHUhzR0EdZH8nEyPbpDlZQR/Um','+225 07 00 00 00 01','onno@immogest-ci.ci','superviseur',NULL,NULL,'biens/palmiers.jpg','actif'),
(1,'Diabaté','Adjoa','admin','$2y$10$Kdd0RujSyJn5CuQ9lalkC.hDOxgPz/bBWYIfdWvLwyayKtyRBvy3.','+225 07 00 00 00 02','admin@immogest-ci.ci','administrateur',NULL,NULL,'biens/hibiscus.jpg','actif'),
(1,'Traoré','Awa','gerant_adj','$2y$10$533zqp.3KzuTfd1cxNHPT.9yFJODIc1fzS8fvsOOoUCOAjbp5HLry','+225 07 01 02 03 04','awa.traore@immogest-ci.ci','gerant',NULL,NULL,'biens/interieur-a12.jpg','actif'),
(2,'N''Guessan','Kouadio','gerant_coc','$2y$10$euRZ4ZJDJMH60UMfH59ahuTTZagsITRQ94ByH/qAqEpoN74ryDk4a','+225 05 06 07 08 09','kouadio.nguessan@immogest-ci.ci','gerant',NULL,NULL,'biens/riviera.jpg','actif'),
(1,'Koffi','Sébastien','comptable','$2y$10$VmrJCeAmBW8ZEpAca8KYtOw9GMrogJ/DQK3Uw0uFRtxAOzKEyLY66','+225 07 10 11 12 13','sebastien.koffi@immogest-ci.ci','comptable',NULL,NULL,'biens/plateau.jpg','actif'),
(1,'Bakayoko','Moussa','caissier','$2y$10$dEpYmLxg7yLTLWbQUyoh6.ksTrl5jOOaWvObm0rdApqpG6b7N5wge','+225 05 14 15 16 17','moussa.bakayoko@immogest-ci.ci','caissier',NULL,NULL,'biens/williamsville.jpg','actif'),
(2,'Assi','Clarisse','assistant','$2y$10$FJQi3zEJGVsMz9px8EU7Zueh.zKJa/Mk2kjyms3ARBFIp8Cd7Zyfu','+225 07 18 19 20 21','clarisse.assi@immogest-ci.ci','assistant',NULL,NULL,'biens/riviera2.jpg','actif'),
(2,'Ouattara','Seydou','proprio','$2y$10$SOdVu5P10I0WCV9HfuL47uIawOsJv6CpQoRikm9mGfXGmq4F7NaT2','+225 01 33 44 55 66','seydou.ouattara@email.ci','proprietaire',NULL,3,'biens/riviera.jpg','actif'),
(2,'Coulibaly','Aïcha','locataire','$2y$10$omQoDbMTrAm/yileT1HPj.7hOTILXupq.dWlIddZ1RPVHwwXIWN0q','+225 05 56 57 58 59','aicha.coulibaly@email.ci','locataire',5,NULL,'biens/hibiscus.jpg','actif'),
(1,'Zadi','Hervé','sansrole','$2y$10$Djz7gU0Me1nFjy.qOTSRh.V9IALdDNltjkATb2EnPUbfzUYguOfXW','+225 07 99 88 77 66','herve.zadi@email.ci',NULL,NULL,NULL,NULL,'actif');

INSERT INTO `roles_permissions` (`role`,`module`,`peut_voir`,`peut_creer`,`peut_modifier`,`peut_supprimer`,`peut_valider`) VALUES
('superviseur','accueil',1,1,1,1,1),
('superviseur','locataire',1,1,1,1,1),
('superviseur','proprietaire',1,1,1,1,1),
('superviseur','caisse',1,1,1,1,1),
('superviseur','recus',1,1,1,1,1),
('superviseur','parametre',1,1,1,1,1),
('superviseur','administration',1,1,1,1,1),
('administrateur','accueil',1,1,1,1,1),
('administrateur','locataire',1,1,1,1,1),
('administrateur','proprietaire',1,1,1,1,1),
('administrateur','caisse',1,1,1,1,1),
('administrateur','recus',1,1,1,1,1),
('administrateur','parametre',1,1,1,1,1),
('administrateur','administration',1,1,1,1,1),
('gerant','accueil',1,1,1,0,1),
('gerant','locataire',1,1,1,0,1),
('gerant','proprietaire',1,1,1,0,1),
('gerant','caisse',1,0,0,0,1),
('gerant','recus',1,0,0,0,1),
('gerant','parametre',1,0,0,0,0),
('gerant','administration',0,0,0,0,0),
('comptable','accueil',1,0,0,0,0),
('comptable','locataire',1,0,0,0,0),
('comptable','proprietaire',1,0,1,0,0),
('comptable','caisse',1,1,1,0,0),
('comptable','recus',1,1,0,0,0),
('comptable','parametre',0,0,0,0,0),
('comptable','administration',0,0,0,0,0),
('caissier','accueil',1,0,0,0,0),
('caissier','locataire',1,0,0,0,0),
('caissier','proprietaire',0,0,0,0,0),
('caissier','caisse',1,1,0,0,0),
('caissier','recus',1,1,0,0,0),
('caissier','parametre',0,0,0,0,0),
('caissier','administration',0,0,0,0,0),
('assistant','accueil',1,0,0,0,0),
('assistant','locataire',1,1,1,0,0),
('assistant','proprietaire',1,0,0,0,0),
('assistant','caisse',0,0,0,0,0),
('assistant','recus',1,0,0,0,0),
('assistant','parametre',0,0,0,0,0),
('assistant','administration',0,0,0,0,0),
('proprietaire','accueil',1,0,0,0,0),
('proprietaire','locataire',0,0,0,0,0),
('proprietaire','proprietaire',1,0,0,0,0),
('proprietaire','caisse',0,0,0,0,0),
('proprietaire','recus',1,0,0,0,0),
('proprietaire','parametre',0,0,0,0,0),
('proprietaire','administration',0,0,0,0,0),
('locataire','accueil',1,0,0,0,0),
('locataire','locataire',1,0,0,0,0),
('locataire','proprietaire',0,0,0,0,0),
('locataire','caisse',0,0,0,0,0),
('locataire','recus',1,0,0,0,0),
('locataire','parametre',0,0,0,0,0),
('locataire','administration',0,0,0,0,0),
('superviseur','dashboard',1,1,1,1,1),
('superviseur','rapport',1,1,1,1,1),
('administrateur','dashboard',1,1,1,1,1),
('administrateur','rapport',1,1,1,1,1),
('gerant','dashboard',1,0,0,0,0),
('gerant','rapport',1,0,0,0,0),
('comptable','dashboard',1,0,0,0,0),
('comptable','rapport',1,0,0,0,0),
('caissier','dashboard',1,0,0,0,0),
('caissier','rapport',0,0,0,0,0),
('assistant','dashboard',1,0,0,0,0),
('assistant','rapport',0,0,0,0,0),
('proprietaire','dashboard',1,0,0,0,0),
('proprietaire','rapport',0,0,0,0,0),
('locataire','dashboard',1,0,0,0,0),
('locataire','rapport',0,0,0,0,0);

INSERT INTO `utilisateurs_permissions` (`utilisateur_id`,`module`,`peut_voir`,`peut_creer`,`peut_modifier`,`peut_supprimer`,`peut_valider`,`statut`)
SELECT u.utilisateur_id, rp.module, rp.peut_voir, rp.peut_creer, rp.peut_modifier, rp.peut_supprimer, rp.peut_valider, 'actif'
FROM utilisateurs u
JOIN roles_permissions rp ON rp.role = u.role
WHERE u.role IS NOT NULL;

INSERT INTO `caisses` (`agence_id`,`nom`,`solde`,`plafond`,`statut`) VALUES
(1,'Caisse Principale Adjamé',2450000.00,5000000.00,'actif'),
(2,'Caisse Principale Cocody',1875000.00,5000000.00,'actif'),
(1,'Caisse Secondaire Adjamé',150000.00,1000000.00,'actif');

INSERT INTO `utilisateurs_caisses` (`utilisateur_id`,`caisse_id`,`statut`) VALUES
(6,1,'actif'),(5,1,'actif'),(5,2,'actif'),(3,1,'actif'),(4,2,'actif');

INSERT INTO `journees_caisses` (
  `caisse_id`,`date_ouverture`,`heure_ouverture`,`date_fermeture`,`heure_fermeture`,
  `fond_initial`,`nombre_entrees`,`nombre_sorties`,`total_entrees`,`total_sorties`,
  `solde_theorique`,`solde_physique`,`ecart`,
  `utilisateur_id_ouverture`,`utilisateur_id_fermeture`,`statut`
) VALUES
(1,'2026-09-24','08:00:00',NULL,NULL,500000.00,4,1,875000.00,200000.00,1175000.00,NULL,NULL,6,NULL,'ouverte'),
(2,'2026-09-23','08:15:00','2026-09-23','17:45:00',300000.00,3,2,650000.00,400000.00,550000.00,545000.00,-5000.00,4,4,'fermee');

INSERT INTO `biens` (`agence_id`,`proprietaire_id`,`nom`,`description`,`pays`,`ville`,`quartier`,`adresse`,`longitude`,`latitude`,`statut`) VALUES
(1,1,'Résidence Les Palmiers','Immeuble R+3 avec parking','Côte d''Ivoire','Abidjan','Yopougon','Sicogi, près gare routière',-4.0835000,5.3364000,'actif'),
(1,2,'Immeuble Liberté','Petit immeuble de standing','Côte d''Ivoire','Abidjan','Adjamé','Liberté, rue des Écoles',-4.0201000,5.3532000,'actif'),
(2,3,'Villa Angré Premium','Villa moderne 2 niveaux','Côte d''Ivoire','Abidjan','Cocody','Angré 8ème Tranche, lot 245',-3.9782000,5.3965000,'actif'),
(2,4,'Résidence Palmeraie','Appartements haut standing','Côte d''Ivoire','Abidjan','Riviera','Palmeraie, boulevard Lagunaire',-3.9608000,5.3551000,'actif'),
(2,5,'Complexe Zone 4','Locaux commerciaux + studios','Côte d''Ivoire','Abidjan','Marcory','Zone 4, rue du Commerce',-3.9956000,5.2928000,'actif'),
(1,1,'Bureau Plateau Centre','Bureaux au cœur du Plateau','Côte d''Ivoire','Abidjan','Plateau','Avenue Chardy, immeuble Alpha',-4.0267000,5.3219000,'actif');

INSERT INTO `locaux` (`bien_id`,`nom`,`description`,`type_local`,`surface`,`nombre_pieces`,`prix_loyer`,`charges`,`caution_mois`,`avance_mois`,`statut`) VALUES
(1,'Apt A1','Appartement 3 pièces clair','appartement',75.00,3,150000.00,15000.00,2,1,'occupe'),
(1,'Apt A2','Appartement 2 pièces','appartement',55.00,2,120000.00,12000.00,2,1,'occupe'),
(1,'Apt A3','Appartement disponible','appartement',75.00,3,150000.00,15000.00,2,1,'libre'),
(2,'Apt B1','F3 Adjamé','appartement',80.00,3,135000.00,10000.00,2,1,'occupe'),
(2,'Magasin B2','Magasin en rez-de-chaussée','magasin',40.00,1,200000.00,20000.00,3,1,'occupe'),
(2,'Apt B3','Studio meublé','studio',28.00,1,80000.00,5000.00,2,1,'libre'),
(3,'Villa principale','Villa 5 pièces + jardin','villa',220.00,5,450000.00,50000.00,3,2,'occupe'),
(3,'Studio jardin','Studio indépendant','studio',35.00,1,100000.00,8000.00,2,1,'reserve'),
(4,'Apt C1 Riviera','Appartement standing 4 pièces','appartement',110.00,4,280000.00,25000.00,2,1,'occupe'),
(4,'Apt C2 Riviera','Appartement 3 pièces vue lagune','appartement',95.00,3,250000.00,22000.00,2,1,'occupe'),
(4,'Apt C3 Riviera','Appartement libre','appartement',95.00,3,250000.00,22000.00,2,1,'libre'),
(5,'Studio D1','Studio étudiant','studio',25.00,1,70000.00,5000.00,2,1,'occupe'),
(5,'Magasin D2','Local commercial','magasin',60.00,1,250000.00,30000.00,3,1,'libre'),
(5,'Bureau D3','Bureau climatisé','bureau',45.00,2,180000.00,15000.00,2,1,'occupe'),
(6,'Bureau E1','Open space 6 postes','bureau',90.00,1,350000.00,40000.00,3,1,'libre');

INSERT INTO `contrats` (
  `locataire_id`,`local_id`,`date_signature`,`date_debut`,`date_fin`,`duree`,`date_preavis`,
  `loyer_nu`,`caution`,`avance`,`frais_agence`,`type_contrat`,`frequence`,`echeance`,`statut`
) VALUES
(1,1,'2024-01-10','2024-02-01','2026-01-31',24,'2025-11-30',150000.00,300000.00,150000.00,75000.00,'a_echoir','mensuel','2026-01-31','actif'),
(2,2,'2023-06-01','2023-07-01','2025-06-30',24,NULL,120000.00,240000.00,120000.00,60000.00,'echu','mensuel','2025-06-30','inactif'),
(3,4,'2025-01-15','2025-02-01','2027-01-31',24,NULL,135000.00,270000.00,135000.00,67500.00,'a_echoir','mensuel','2027-01-31','actif'),
(4,5,'2024-08-01','2024-09-01','2026-08-31',24,'2026-06-30',200000.00,600000.00,200000.00,100000.00,'a_echoir','mensuel','2026-08-31','actif'),
(5,7,'2023-03-01','2023-04-01','2026-03-31',36,NULL,450000.00,1350000.00,900000.00,225000.00,'a_echoir','mensuel','2026-03-31','actif'),
(6,9,'2024-05-10','2024-06-01','2026-05-31',24,NULL,280000.00,560000.00,280000.00,140000.00,'a_echoir','mensuel','2026-05-31','actif'),
(7,10,'2025-07-01','2025-08-01','2027-07-31',24,NULL,250000.00,500000.00,250000.00,125000.00,'a_echoir','mensuel','2027-07-31','actif'),
(8,12,'2024-11-01','2024-12-01','2025-11-30',12,'2025-09-30',70000.00,140000.00,70000.00,35000.00,'a_echoir','mensuel','2025-11-30','actif'),
(9,8,'2026-09-20','2026-10-01','2027-09-30',12,NULL,100000.00,200000.00,100000.00,50000.00,'a_echoir','mensuel','2027-09-30','provisoire'),
(10,14,'2025-02-01','2025-03-01','2026-02-28',12,NULL,180000.00,360000.00,180000.00,90000.00,'echu','mensuel','2026-02-28','actif');

INSERT INTO `etats_lieux` (`contrat_id`,`utilisateur_id`,`nom`,`date_etat`,`type_etat`,`observations`,`statut`) VALUES
(1,7,'État des lieux entrée Apt A1','2024-02-01','entree','Peintures neuves, climatiseur OK, 2 clés remises','actif'),
(3,7,'État des lieux entrée Apt B1','2025-02-01','entree','Carrelage intact, cuisine équipée','actif'),
(5,7,'État des lieux entrée Villa Angré','2023-04-01','entree','Jardin entretenu, piscine hors service temporaire','actif'),
(2,7,'État des lieux sortie Apt A2','2025-06-30','sortie','Traces d''humidité salle de bain, retenue 50 000 FCFA','actif');

INSERT INTO `loyers` (`contrat_id`,`montant`,`montant_paye`,`reste_a_payer`,`penalite`,`mois`,`annee`,`date_echeance`,`statut`) VALUES
(1,150000.00,150000.00,0.00,0.00,6,2026,'2026-06-05','paye'),
(1,150000.00,150000.00,0.00,0.00,7,2026,'2026-07-05','paye'),
(1,150000.00,75000.00,75000.00,0.00,8,2026,'2026-08-05','partiel'),
(1,150000.00,0.00,150000.00,15000.00,9,2026,'2026-09-05','en_retard'),
(3,135000.00,135000.00,0.00,0.00,7,2026,'2026-07-05','paye'),
(3,135000.00,135000.00,0.00,0.00,8,2026,'2026-08-05','paye'),
(3,135000.00,0.00,135000.00,13500.00,9,2026,'2026-09-05','en_retard'),
(5,450000.00,450000.00,0.00,0.00,7,2026,'2026-07-05','paye'),
(5,450000.00,450000.00,0.00,0.00,8,2026,'2026-08-05','paye'),
(5,450000.00,450000.00,0.00,0.00,9,2026,'2026-09-05','paye'),
(6,280000.00,280000.00,0.00,0.00,8,2026,'2026-08-05','paye'),
(6,280000.00,140000.00,140000.00,0.00,9,2026,'2026-09-05','partiel'),
(7,250000.00,250000.00,0.00,0.00,8,2026,'2026-08-05','paye'),
(7,250000.00,0.00,250000.00,0.00,9,2026,'2026-09-05','en_attente'),
(8,70000.00,70000.00,0.00,0.00,8,2026,'2026-08-05','paye'),
(8,70000.00,0.00,70000.00,7000.00,9,2026,'2026-09-05','en_retard');

INSERT INTO `transactions` (
  `date_transaction`,`heure`,`montant`,`type_transaction`,`categorie`,`mode_paiement`,
  `reference_paiement`,`objet`,`numero_recu`,`loyer_id`,`caisse_id`,`journee_id`,
  `utilisateur_id`,`valide_par`,`date_validation`,`etat`,`statut`,
  `nature_beneficiaire`,`id_beneficiaire`,`nom_beneficiaire`
) VALUES
('2026-09-24','09:15:00',150000.00,'entree','loyer','orange_money','OM-20260924-88421','Loyer juin 2026 Yao Affoué','RC-2026-0001',1,1,1,6,NULL,NULL,'valide','succes','locataire',1,'Yao Affoué'),
('2026-09-24','10:02:00',150000.00,'entree','loyer','especes',NULL,'Loyer juillet 2026 Yao Affoué','RC-2026-0002',2,1,1,6,NULL,NULL,'valide','succes','locataire',1,'Yao Affoué'),
('2026-09-24','11:30:00',75000.00,'entree','loyer','wave','WV-778812','Paiement partiel août 2026 Yao Affoué','RC-2026-0003',3,1,1,6,NULL,NULL,'valide','succes','locataire',1,'Yao Affoué'),
('2026-09-24','14:00:00',300000.00,'entree','caution','mtn_momo','MM-445566','Caution Apt A1','RC-2026-0004',NULL,1,1,6,NULL,NULL,'valide','succes','locataire',1,'Yao Affoué'),
('2026-09-24','15:20:00',200000.00,'sortie','depense','especes',NULL,'Réparation climatiseur Apt A1',NULL,NULL,1,1,6,3,'2026-09-24 15:45:00','valide','succes','autres',NULL,'Technicien Froid+'),
('2026-09-23','09:40:00',450000.00,'entree','loyer','virement','VIR-SGBCI-99221','Loyer sept 2026 Coulibaly Aïcha','RC-2026-0005',10,2,2,4,NULL,NULL,'valide','succes','locataire',5,'Coulibaly Aïcha'),
('2026-09-23','11:00:00',280000.00,'entree','loyer','moov_money','MV-332211','Loyer août 2026 Gbaké Laurent','RC-2026-0006',11,2,2,4,NULL,NULL,'valide','succes','locataire',6,'Gbaké Laurent'),
('2026-09-23','14:30:00',350000.00,'sortie','reversement','cheque','CHQ-001245','Reversement août Ouattara Seydou','RC-2026-0007',NULL,2,2,5,4,'2026-09-23 15:00:00','valide','succes','proprietaire',3,'Ouattara Seydou'),
('2026-09-23','16:00:00',50000.00,'sortie','versement_banque','virement','BRD-ECOBANK-778','Versement banque journée',NULL,NULL,2,2,4,NULL,NULL,'valide','succes','autres',NULL,'Ecobank Cocody'),
('2026-09-22','10:00:00',135000.00,'entree','loyer','especes',NULL,'Loyer août Soro Fatou','RC-2026-0008',6,1,NULL,6,NULL,NULL,'valide','succes','locataire',3,'Soro Fatou'),
('2026-09-20','09:00:00',100000.00,'entree','avance','orange_money','OM-20260920-1122','Avance demande Brou Esther','RC-2026-0009',NULL,2,NULL,4,NULL,NULL,'valide','succes','locataire',9,'Brou Esther'),
('2026-09-18','16:30:00',50000.00,'sortie','remboursement_caution','especes',NULL,'Remboursement partiel caution Touré','RC-2026-0010',NULL,1,NULL,5,3,'2026-09-18 17:00:00','valide','succes','locataire',2,'Touré Mamadou'),
('2026-09-24','16:00:00',200000.00,'entree','frais','cheque','CHQ-998877','Frais d''agence contrat provisoire','RC-2026-0011',NULL,1,1,6,NULL,NULL,'valide','succes','locataire',9,'Brou Esther');

INSERT INTO `recus` (`numero`,`transaction_id`,`type_recu`,`nb_impressions`,`qr_code`,`montant_lettres`,`agence_id`,`date_recu`) VALUES
('RC-2026-0001',1,'recu',1,'QR-RC-2026-0001','Cent cinquante mille francs CFA',1,'2026-09-24 09:15:00'),
('RC-2026-0002',2,'recu',1,'QR-RC-2026-0002','Cent cinquante mille francs CFA',1,'2026-09-24 10:02:00'),
('RC-2026-0003',3,'recu',2,'QR-RC-2026-0003','Soixante-quinze mille francs CFA',1,'2026-09-24 11:30:00'),
('RC-2026-0004',4,'recu',1,'QR-RC-2026-0004','Trois cent mille francs CFA',1,'2026-09-24 14:00:00'),
('RC-2026-0005',6,'recu',1,'QR-RC-2026-0005','Quatre cent cinquante mille francs CFA',2,'2026-09-23 09:40:00'),
('RC-2026-0006',7,'recu',1,'QR-RC-2026-0006','Deux cent quatre-vingt mille francs CFA',2,'2026-09-23 11:00:00'),
('RC-2026-0007',8,'reversement',1,'QR-RC-2026-0007','Trois cent cinquante mille francs CFA',2,'2026-09-23 14:30:00'),
('RC-2026-0008',10,'recu',1,'QR-RC-2026-0008','Cent trente-cinq mille francs CFA',1,'2026-09-22 10:00:00'),
('RC-2026-0009',11,'recu',1,'QR-RC-2026-0009','Cent mille francs CFA',2,'2026-09-20 09:00:00'),
('RC-2026-0010',12,'remboursement',1,'QR-RC-2026-0010','Cinquante mille francs CFA',1,'2026-09-18 16:30:00'),
('RC-2026-0011',13,'recu',0,'QR-RC-2026-0011','Deux cent mille francs CFA',1,'2026-09-24 16:00:00');

INSERT INTO `reversements` (`proprietaire_id`,`agence_id`,`mois`,`annee`,`montant_brut`,`commission`,`depenses`,`montant_net`,`transaction_id`,`date_reversement`,`statut`) VALUES
(3,2,8,2026,730000.00,87600.00,45000.00,597400.00,NULL,'2026-09-05','valide'),
(3,2,9,2026,450000.00,54000.00,0.00,396000.00,8,'2026-09-23','paye'),
(1,1,8,2026,270000.00,27000.00,200000.00,43000.00,NULL,'2026-09-10','brouillon'),
(4,2,8,2026,530000.00,53000.00,25000.00,452000.00,NULL,'2026-09-08','valide');

INSERT INTO `depenses` (`bien_id`,`local_id`,`proprietaire_id`,`agence_id`,`libelle`,`montant`,`date_depense`,`reversement_id`,`transaction_id`,`statut`) VALUES
(1,1,1,1,'Réparation climatiseur Apt A1',200000.00,'2026-09-24',3,5,'deduite'),
(3,7,3,2,'Entretien jardin Villa Angré',45000.00,'2026-08-20',1,NULL,'deduite'),
(4,9,4,2,'Remplacement serrure Apt C1',25000.00,'2026-08-15',4,NULL,'deduite'),
(5,12,5,2,'Peinture studio D1',35000.00,'2026-09-01',NULL,NULL,'actif');

INSERT INTO `versements_banque` (`caisse_id`,`journee_id`,`banque`,`montant`,`reference_bordereau`,`date_versement`,`utilisateur_id`,`transaction_id`,`statut`) VALUES
(2,2,'Ecobank Côte d''Ivoire',50000.00,'BRD-ECOBANK-778','2026-09-23',4,9,'actif'),
(1,NULL,'SGBCI Adjamé',500000.00,'BRD-SGBCI-991','2026-09-20',5,NULL,'actif');

INSERT INTO `demandes_location` (
  `local_id`,`nom`,`prenom`,`telephone`,`email`,`profession`,
  `piece_identite_type`,`piece_identite_numero`,`message`,`statut`,`traite_par`,`contrat_id`
) VALUES
(3,'Doumbia','Karim','+225 07 22 33 44 55','karim.doumbia@email.ci','Commercial','CNI','CI009012345',
 'Je suis intéressé par l''appartement A3, disponible dès octobre.','nouvelle',NULL,NULL),
(6,'Sangaré','Mariam','+225 05 33 44 55 66','mariam.sangare@email.ci','Infirmière','CNI','CI010123456',
 'Studio meublé idéal pour moi, pouvez-vous me rappeler ?','en_cours',7,NULL),
(8,'Brou','Esther','+225 07 90 91 92 93','esther.brou@email.ci','Étudiante','CNI','CI007890123',
 'Je confirme ma demande pour le studio jardin.','acceptee',4,9),
(11,'Kouassi','Patrick','+225 01 44 55 66 77','patrick.kouassi@email.ci','Avocat','Passeport','P2024987654',
 'Budget max 260 000 FCFA charges comprises.','nouvelle',NULL,NULL);

INSERT INTO `restitutions_caution` (`contrat_id`,`caution`,`reparations`,`impayes`,`montant_rendu`,`transaction_id`,`date_restitution`,`statut`) VALUES
(2,240000.00,50000.00,0.00,190000.00,12,'2026-09-18','paye');

INSERT INTO `reclamations` (`nom`,`observation`,`contrat_id`,`date_reclamation`,`date_reglement`,`utilisateur_id`,`statut`) VALUES
('Fuite d''eau cuisine','Fuite sous l''évier depuis 3 jours','1','2026-09-15',NULL,7,'en_attente'),
('Climatiseur en panne','Ne refroidit plus la nuit','5','2026-09-10','2026-09-24',7,'valide'),
('Bruit voisinage','Travaux nocturnes appartement voisin','6','2026-09-18',NULL,7,'en_attente');

INSERT INTO `albums` (`nom`,`type_media`,`url`,`module`,`module_id`,`statut`) VALUES
('Façade Les Palmiers','photos','biens/palmiers.jpg','bien',1,'actif'),
('Salon Apt A1','photos','biens/interieur-a12.jpg','local',1,'actif'),
('Cuisine Apt A1','photos','biens/hibiscus.jpg','local',1,'actif'),
('Villa Angré ext','photos','biens/riviera.jpg','bien',3,'actif'),
('Riviera C1 salon','photos','biens/riviera2.jpg','local',9,'actif'),
('Apt A3 libre','photos','biens/palmiers.jpg','local',3,'actif'),
('Apt C3 Riviera','photos','biens/riviera2.jpg','local',11,'actif'),
('Studio B3','photos','biens/williamsville.jpg','local',6,'actif'),
('Magasin D2','photos','biens/plateau.jpg','local',13,'actif'),
('Bureau E1','photos','biens/plateau.jpg','local',15,'actif'),
('CNI Yao Affoué','photos','biens/interieur-a12.jpg','locataire',1,'actif'),
('CNI Coulibaly','photos','biens/riviera.jpg','locataire',5,'actif'),
('État lieux entrée A1','photos','biens/interieur-a12.jpg','etat_lieux',1,'actif'),
('Bordereau Ecobank','photos','biens/plateau.jpg','versement_banque',1,'actif'),
('Facture clim','documents','biens/hibiscus.jpg','depense',1,'actif'),
('Logo Adjamé','photos','logos/adjame.svg','agence',1,'actif'),
('Logo Cocody','photos','logos/cocody.svg','agence',2,'actif'),
('Photo ID demande Doumbia','photos','biens/williamsville.jpg','demande_location',1,'actif'),
('Réclamation fuite','photos','biens/hibiscus.jpg','reclamation',1,'actif'),
('Contrat signé A1','documents','biens/palmiers.jpg','contrat',1,'actif'),
('Proprio Ouattara','photos','biens/riviera.jpg','proprietaire',3,'actif');

INSERT INTO `relances` (`loyer_id`,`canal`,`message`,`date_relance`,`utilisateur_id`,`statut`) VALUES
(4,'whatsapp','Bonjour Mme Yao Affoué, votre loyer de septembre 2026 (150 000 FCFA + pénalité 15 000) est en retard. Merci de régulariser. Agence Adjamé IMMO-GEST CI.','2026-09-12 10:00:00',6,'envoyee'),
(4,'sms','IMMO-GEST CI: Loyer sept 2026 impayé 165000 FCFA. Contactez Agence Adjamé.','2026-09-15 09:00:00',6,'envoyee'),
(7,'whatsapp','Bonjour Mme Soro, rappel loyer septembre 135 000 FCFA + pénalité. Merci.','2026-09-14 11:30:00',7,'envoyee'),
(16,'email','Objet: Relance loyer septembre — Studio D1','2026-09-16 08:00:00',4,'envoyee');

INSERT INTO `notifications` (`utilisateur_id`,`type`,`titre`,`message`,`lu`,`date_notification`) VALUES
(6,'loyer_retard','Loyer en retard','4 loyers en retard aujourd''hui (Adjamé)',0,'2026-09-24 08:05:00'),
(3,'demande_location','Nouvelle demande','Doumbia Karim pour Apt A3',0,'2026-09-24 09:20:00'),
(4,'caisse','Caisse clôturée','Écart de -5 000 FCFA sur journée du 23/09',1,'2026-09-23 17:50:00'),
(5,'reversement','Reversement à valider','Reversement août Koffi J-B en brouillon',0,'2026-09-10 14:00:00'),
(8,'releve','Relevé mensuel','Votre relevé septembre 2026 est disponible',0,'2026-09-24 07:00:00'),
(9,'recu','Nouveau reçu','Reçu RC-2026-0005 disponible',1,'2026-09-23 09:45:00'),
(1,'systeme','Bienvenue','Compte superviseur activé sur IMMO-GEST CI',1,'2026-09-01 10:00:00');

INSERT INTO `journal_activite` (`utilisateur_id`,`action`,`module`,`element_id`,`details`,`ip`,`appareil`,`date_action`) VALUES
(6,'encaissement','caisse',1,'Encaissement loyer RC-2026-0001 150000 FCFA','41.189.12.10','Android Chrome','2026-09-24 09:15:05'),
(6,'encaissement','caisse',2,'Encaissement loyer RC-2026-0002','41.189.12.10','Android Chrome','2026-09-24 10:02:10'),
(3,'validation','caisse',5,'Validation décaissement réparation clim 200000','41.189.12.22','Windows Edge','2026-09-24 15:45:00'),
(4,'cloture_caisse','caisse',2,'Clôture journée Cocody écart -5000','41.202.55.8','iPhone Safari','2026-09-23 17:45:30'),
(1,'modification_role','administration',10,'Création utilisateur sansrole (démo Accès refusé)','197.159.1.1','Desktop Chrome','2026-09-20 11:00:00'),
(7,'creation','locataire',9,'Création fiche Brou Esther','41.202.55.15','Android Chrome','2026-09-20 08:30:00'),
(4,'acceptation_demande','demande_location',3,'Demande Brou Esther acceptée → contrat provisoire 9','41.202.55.8','iPhone Safari','2026-09-20 10:15:00'),
(5,'reversement','proprietaire',2,'Reversement payé Ouattara Seydou 396000','41.189.12.30','Windows Chrome','2026-09-23 14:35:00');

INSERT INTO `parametres` (`agence_id`,`cle`,`valeur`,`description`) VALUES
(NULL,'devise','FCFA','Devise par défaut'),
(NULL,'penalite_type','pourcentage','Type de pénalité : pourcentage ou montant fixe'),
(NULL,'penalite_taux','10','Taux de pénalité (%) après délai'),
(NULL,'penalite_delai_jours','5','Jours de grâce après échéance'),
(NULL,'seuil_validation_decaissement','100000','Montant au-delà duquel validation gérant requise'),
(NULL,'modele_relance_whatsapp','Bonjour {nom}, votre loyer de {mois} ({montant} FCFA) est en retard. Merci de régulariser. {agence}.','Modèle message WhatsApp'),
(NULL,'modele_relance_sms','{agence}: Loyer {mois} impayé {montant} FCFA. Contactez-nous.','Modèle SMS'),
(NULL,'modele_recu','RC-{annee}-{seq}','Format numérotation reçus'),
(1,'theme_couleur','#E8650A','Couleur principale Agence Adjamé'),
(2,'theme_couleur','#E8650A','Couleur principale Agence Cocody'),
(1,'recu_prefixe','RC-2026-','Préfixe reçus Adjamé'),
(2,'recu_prefixe','RC-2026-','Préfixe reçus Cocody'),
(NULL,'cinetpay_ready','0','Point d''intégration CinetPay/PayDunya (0/1)'),
(NULL,'relance_auto_jours_avant','3','Relance auto N jours avant échéance'),
(NULL,'relance_auto_jours_apres','2','Relance auto N jours après échéance');

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- Fin du script ROUAHIMO (base rouahimo)
-- =====================================================
