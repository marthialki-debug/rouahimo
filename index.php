<?php
declare(strict_types=1);
$sess = session_save_path();
if ($sess === '' || !is_dir($sess) || !is_writable($sess)) {
    session_save_path(sys_get_temp_dir());
}
session_start();
$cfg = require __DIR__ . '/config.php';

function h(?string $s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function fcfa(float $n): string { return number_format($n, 0, ',', ' ') . ' F'; }
function fcfaPlein(float $n): string { return number_format($n, 0, ',', ' ') . ' FCFA'; }
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    global $cfg;
    $pdo = new PDO(
        "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['name']};charset=utf8mb4",
        $cfg['user'],
        $cfg['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    return $pdo;
}
function me(): ?array { return $_SESSION['user'] ?? null; }
function plein(): bool {
    $r = strtolower((string) (me()['role'] ?? ''));
    return $r === 'superviseur' || $r === 'administrateur';
}
function can(string $module): bool {
    $u = me();
    if (!$u || empty($u['role'])) return false;
    if (plein()) return true;
    return in_array($module, $u['modules'] ?? [], true);
}
function aid(): ?int {
    $u = me();
    if (!$u) return null;
    if (!plein()) return !empty($u['agence_id']) ? (int) $u['agence_id'] : null;
    $c = (int) ($_SESSION['agence_vue'] ?? 0);
    return $c > 0 ? $c : null;
}
function q(string $sql, array $a = []): array {
    $st = db()->prepare($sql);
    $st->execute($a);
    return $st->fetchAll();
}
function one(string $sql, array $a = []): ?array {
    $rows = q($sql, $a);
    return $rows[0] ?? null;
}
function journal(string $action, string $module, ?int $id, string $details): void {
    $u = me();
    db()->prepare('INSERT INTO journal_activite (utilisateur_id, action, module, element_id, details, ip, appareil) VALUES (?,?,?,?,?,?,?)')
        ->execute([$u['utilisateur_id'] ?? null, $action, $module, $id, $details, $_SERVER['REMOTE_ADDR'] ?? '', substr($_SERVER['HTTP_USER_AGENT'] ?? 'Wamp', 0, 150)]);
}
function flash(string $m): void { $_SESSION['flash'] = $m; }
function go(string $to): void { header('Location: ' . $to); exit; }
function prochainRecu(): string {
    $row = one("SELECT numero FROM recus WHERE numero LIKE 'RC-2026-%' ORDER BY numero DESC LIMIT 1");
    $n = 0;
    if ($row && preg_match('/(\d+)$/', $row['numero'], $m)) $n = (int) $m[1];
    return sprintf('RC-2026-%04d', $n + 1);
}
function photoBien(int $bienId): string {
    $r = one('SELECT url FROM albums WHERE module = "bien" AND module_id = ? AND statut = "actif" ORDER BY album_id LIMIT 1', [$bienId]);
    return $r['url'] ?? 'biens/palmiers.jpg';
}
function photoLocal(int $localId, int $bienId): string {
    $r = one('SELECT url FROM albums WHERE module = "local" AND module_id = ? AND statut = "actif" ORDER BY album_id LIMIT 1', [$localId]);
    return $r['url'] ?? photoBien($bienId);
}
function roleLabel(?string $r): string {
    $map = [
        'superviseur' => 'Superviseur', 'administrateur' => 'Administrateur', 'gerant' => 'Gérant',
        'comptable' => 'Comptable', 'caissier' => 'Caissier', 'assistant' => 'Assistant',
        'proprietaire' => 'Propriétaire', 'locataire' => 'Locataire',
    ];
    return $r && isset($map[$r]) ? $map[$r] : ($r ? ucfirst($r) : 'Sans rôle');
}
function wa(string $tel, string $texte): string {
    $d = preg_replace('/\D/', '', $tel) ?? '';
    if ($d !== '' && !str_starts_with($d, '225')) $d = '225' . $d;
    return 'https://wa.me/' . $d . '?text=' . rawurlencode($texte);
}

$errDb = null;
try { db()->query('SELECT 1'); }
catch (Throwable $e) { $errDb = $e->getMessage(); }

if (isset($_GET['logout'])) { $_SESSION = []; session_destroy(); header('Location: index.php'); exit; }
if (me() && isset($_GET['verrou'])) { $_SESSION['verrou'] = 1; go('index.php'); }

$loginError = null;
if (!$errDb && ($_POST['action'] ?? '') === 'login') {
    $login = trim($_POST['login'] ?? '');
    $mdp = (string) ($_POST['mdp'] ?? '');
    $u = one('SELECT * FROM utilisateurs WHERE login = ? AND statut = "actif"', [$login]);
    if (!$u || !password_verify($mdp, (string) $u['mdp'])) {
        $loginError = 'Identifiants incorrects.';
    } else {
        $rows = q('SELECT module, peut_voir FROM utilisateurs_permissions WHERE utilisateur_id = ? AND statut = "actif" AND peut_voir = 1', [$u['utilisateur_id']]);
        $modules = array_column($rows, 'module');
        $_SESSION['user'] = $u + ['modules' => $modules];
        unset($_SESSION['verrou']);
        $next = $_POST['next'] ?? 'accueil';
        $ok = ['accueil', 'dashboard', 'locataires', 'proprietaires', 'caisse', 'parametres', 'rapports'];
        if (!in_array($next, $ok, true)) $next = 'accueil';
        $map = ['accueil' => 'accueil', 'dashboard' => 'dashboard', 'rapports' => 'rapport', 'locataires' => 'locataire', 'proprietaires' => 'proprietaire', 'caisse' => 'caisse', 'parametres' => 'parametre'];
        $mod = $map[$next] ?? 'accueil';
        go(can($mod) ? '?p=' . $next : '?p=refuse');
    }
}

if (!$errDb && me() && ($_POST['action'] ?? '') === 'unlock') {
    if (password_verify((string) ($_POST['mdp'] ?? ''), (string) (me()['mdp'] ?? ''))) {
        unset($_SESSION['verrou']);
        go('?p=accueil');
    }
    $loginError = 'Mot de passe incorrect.';
}

if (!$errDb && me() && !empty($_SESSION['verrou']) && ($_POST['action'] ?? '') !== '' && ($_POST['action'] ?? '') !== 'unlock' && ($_POST['action'] ?? '') !== 'login') {
    flash('Session verrouillée.');
    go('?');
}

if (!$errDb && me() && ($_POST['action'] ?? '') !== '' && !in_array($_POST['action'], ['login', 'unlock'], true)) {
    try {
        $act = $_POST['action'];
        $uid = (int) me()['utilisateur_id'];
        if ($act === 'agence_vue' && plein()) {
            $_SESSION['agence_vue'] = (int) ($_POST['agence_id'] ?? 0);
            $back = $_POST['retour'] ?? 'accueil';
            if (!in_array($back, ['accueil', 'dashboard', 'rapports'], true)) $back = 'accueil';
            go('?p=' . $back . ($back === 'accueil' ? '&s=agences' : ''));
        }
        if ($act === 'locataire_nouveau' && can('locataire')) {
            assurerColonnesCni();
            $cni = trim($_POST['cni'] ?? '');
            if ($cni === '') { flash('Le numéro de CNI est obligatoire.'); go('?p=locataires&s=fiches&nouveau=1'); }
            $ag = (int) ($_POST['agence_id'] ?? aid() ?? 1);
            db()->prepare('INSERT INTO locataires (agence_id, nom, prenom, telephone, email, profession, genre, piece_identite_type, piece_identite_numero, date_delivrance_piece, date_expiration_piece, statut) VALUES (?,?,?,?,?,?,?,"CNI",?,?,?,"actif")')
                ->execute([$ag, trim($_POST['nom'] ?? ''), trim($_POST['prenom'] ?? ''), trim($_POST['telephone'] ?? ''), trim($_POST['email'] ?? ''), trim($_POST['profession'] ?? ''), in_array($_POST['genre'] ?? '', ['M', 'F'], true) ? $_POST['genre'] : null, $cni, ($_POST['delivrance'] ?? '') !== '' ? $_POST['delivrance'] : null, ($_POST['expiration'] ?? '') !== '' ? $_POST['expiration'] : null]);
            $id = (int) db()->lastInsertId();
            sauverPiece($id, 'cni_recto', 'CNI recto');
            sauverPiece($id, 'cni_verso', 'CNI verso');
            journal('creation', 'locataire', $id, 'Nouveau locataire ' . trim($_POST['prenom'] . ' ' . $_POST['nom']));
            flash('Locataire enregistré.');
            go('?p=locataires&s=fiches');
        }
        if ($act === 'proprio_nouveau' && can('proprietaire')) {
            $ag = (int) ($_POST['agence_id'] ?? aid() ?? 1);
            db()->prepare('INSERT INTO proprietaires (agence_id, nom, prenom, telephone, email, genre, taux_commission, numero_mandat, statut) VALUES (?,?,?,?,?,?,?,?,"actif")')
                ->execute([$ag, trim($_POST['nom'] ?? ''), trim($_POST['prenom'] ?? ''), trim($_POST['telephone'] ?? ''), trim($_POST['email'] ?? ''), in_array($_POST['genre'] ?? '', ['M', 'F'], true) ? $_POST['genre'] : null, (float) ($_POST['taux'] ?? 10), trim($_POST['mandat'] ?? '')]);
            $id = (int) db()->lastInsertId();
            journal('creation', 'proprietaire', $id, 'Nouveau propriétaire');
            flash('Propriétaire enregistré.');
            go('?p=proprietaires&s=fiches');
        }
        if ($act === 'locataire_modifier' && can('locataire')) {
            assurerColonnesCni();
            $id = (int) ($_POST['id'] ?? 0);
            $genre = in_array($_POST['genre'] ?? '', ['M', 'F'], true) ? $_POST['genre'] : null;
            $statut = ($_POST['statut'] ?? '') === 'inactif' ? 'inactif' : 'actif';
            $cni = trim($_POST['cni'] ?? '');
            db()->prepare('UPDATE locataires SET nom=?, prenom=?, telephone=?, email=?, profession=?, genre=?, piece_identite_type=?, piece_identite_numero=?, date_delivrance_piece=?, date_expiration_piece=?, statut=? WHERE locataire_id=?')
                ->execute([trim($_POST['nom'] ?? ''), trim($_POST['prenom'] ?? ''), trim($_POST['telephone'] ?? ''), trim($_POST['email'] ?? ''), trim($_POST['profession'] ?? ''), $genre, trim($_POST['piece_type'] ?? 'CNI') ?: 'CNI', $cni !== '' ? $cni : null, ($_POST['delivrance'] ?? '') !== '' ? $_POST['delivrance'] : null, ($_POST['expiration'] ?? '') !== '' ? $_POST['expiration'] : null, $statut, $id]);
            sauverPiece($id, 'cni_recto', 'CNI recto');
            sauverPiece($id, 'cni_verso', 'CNI verso');
            journal('modification', 'locataire', $id, 'Locataire modifié');
            flash('Locataire modifié.');
            go('?p=locataires&s=fiches');
        }
        if ($act === 'locataire_supprimer' && can('locataire')) {
            $id = (int) ($_POST['id'] ?? 0);
            if (one('SELECT contrat_id FROM contrats WHERE locataire_id=? LIMIT 1', [$id])) {
                db()->prepare('UPDATE locataires SET statut="inactif" WHERE locataire_id=?')->execute([$id]);
                journal('modification', 'locataire', $id, 'Locataire désactivé');
                flash('Locataire désactivé : un bail est encore lié.');
            } else {
                db()->prepare('DELETE FROM locataires WHERE locataire_id=?')->execute([$id]);
                journal('suppression', 'locataire', $id, 'Locataire supprimé');
                flash('Locataire supprimé.');
            }
            go('?p=locataires&s=fiches');
        }
        if ($act === 'proprio_modifier' && can('proprietaire')) {
            $id = (int) ($_POST['id'] ?? 0);
            $genre = in_array($_POST['genre'] ?? '', ['M', 'F'], true) ? $_POST['genre'] : null;
            $statut = ($_POST['statut'] ?? '') === 'inactif' ? 'inactif' : 'actif';
            db()->prepare('UPDATE proprietaires SET nom=?, prenom=?, telephone=?, email=?, genre=?, taux_commission=?, numero_mandat=?, statut=? WHERE proprietaire_id=?')
                ->execute([trim($_POST['nom'] ?? ''), trim($_POST['prenom'] ?? ''), trim($_POST['telephone'] ?? ''), trim($_POST['email'] ?? ''), $genre, (float) ($_POST['taux'] ?? 10), trim($_POST['mandat'] ?? ''), $statut, $id]);
            journal('modification', 'proprietaire', $id, 'Propriétaire modifié');
            flash('Propriétaire modifié.');
            go('?p=proprietaires&s=fiches');
        }
        if ($act === 'proprio_supprimer' && can('proprietaire')) {
            $id = (int) ($_POST['id'] ?? 0);
            if (one('SELECT bien_id FROM biens WHERE proprietaire_id=? LIMIT 1', [$id])) {
                db()->prepare('UPDATE proprietaires SET statut="inactif" WHERE proprietaire_id=?')->execute([$id]);
                journal('modification', 'proprietaire', $id, 'Propriétaire désactivé');
                flash('Propriétaire désactivé : un bien est encore lié.');
            } else {
                db()->prepare('DELETE FROM proprietaires WHERE proprietaire_id=?')->execute([$id]);
                journal('suppression', 'proprietaire', $id, 'Propriétaire supprimé');
                flash('Propriétaire supprimé.');
            }
            go('?p=proprietaires&s=fiches');
        }
        if ($act === 'bien_nouveau' && can('proprietaire')) {
            $ag = (int) ($_POST['agence_id'] ?? aid() ?? 1);
            db()->prepare('INSERT INTO biens (agence_id, proprietaire_id, nom, description, ville, quartier, adresse, latitude, longitude, statut) VALUES (?,?,?,?,?,?,?,?,?,"actif")')
                ->execute([$ag, (int) $_POST['proprietaire_id'], trim($_POST['nom'] ?? ''), trim($_POST['description'] ?? ''), 'Abidjan', trim($_POST['quartier'] ?? ''), trim($_POST['adresse'] ?? ''), ($_POST['latitude'] ?? '') !== '' ? $_POST['latitude'] : null, ($_POST['longitude'] ?? '') !== '' ? $_POST['longitude'] : null]);
            $bid = (int) db()->lastInsertId();
            if (!empty($_FILES['photo']['tmp_name'])) {
                $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION) ?: 'jpg';
                $rel = 'uploads/bien-' . $bid . '.' . preg_replace('/[^a-z0-9]/i', '', $ext);
                move_uploaded_file($_FILES['photo']['tmp_name'], __DIR__ . '/' . $rel);
                db()->prepare('INSERT INTO albums (nom, type_media, url, module, module_id, statut) VALUES (?,"photos",?,"bien",?,"actif")')->execute([trim($_POST['nom'] ?? ''), $rel, $bid]);
            }
            if (trim($_POST['local_nom'] ?? '') !== '') {
                db()->prepare('INSERT INTO locaux (bien_id, nom, type_local, nombre_pieces, surface, prix_loyer, statut) VALUES (?,?,?,?,?,?,"libre")')
                    ->execute([$bid, trim($_POST['local_nom']), $_POST['type_local'] ?? 'appartement', (int) ($_POST['nombre_pieces'] ?? 1), (float) ($_POST['surface'] ?? 0), (float) ($_POST['prix_loyer'] ?? 0)]);
            }
            journal('creation', 'bien', $bid, 'Nouveau bien');
            flash('Bien enregistré.');
            go('?p=proprietaires&s=fiches');
        }
        if ($act === 'contrat_nouveau' && can('locataire')) {
            $freq = (string) ($_POST['frequence'] ?? 'mensuel');
            if (!in_array($freq, ['journalier', 'hebdomadaire', 'mensuel', 'trimestriel', 'semestriel', 'annuel'], true)) $freq = 'mensuel';
            $statut = in_array($_POST['statut'] ?? '', ['provisoire', 'actif', 'inactif'], true) ? $_POST['statut'] : 'actif';
            $debut = (string) ($_POST['date_debut'] ?? date('Y-m-d'));
            $fin = ($_POST['date_fin'] ?? '') !== '' ? $_POST['date_fin'] : null;
            $duree = (int) ($_POST['duree'] ?? 0);
            if ($duree <= 0 && $fin) $duree = max(1, (int) round((strtotime((string) $fin) - strtotime($debut)) / 2592000));
            db()->prepare('INSERT INTO contrats (locataire_id, local_id, date_signature, date_debut, date_fin, duree, date_preavis, loyer_nu, caution, avance, type_contrat, frequence, echeance, statut) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([(int) $_POST['locataire_id'], (int) $_POST['local_id'], date('Y-m-d'), $debut, $fin, $duree ?: null, ($_POST['date_preavis'] ?? '') !== '' ? $_POST['date_preavis'] : null, (float) $_POST['loyer_nu'], (float) ($_POST['caution'] ?? 0), (float) ($_POST['avance'] ?? 0), ($_POST['type_contrat'] ?? '') === 'echu' ? 'echu' : 'a_echoir', $freq, $fin, $statut]);
            $id = (int) db()->lastInsertId();
            db()->prepare('UPDATE locaux SET statut = "occupe" WHERE local_id = ?')->execute([(int) $_POST['local_id']]);
            journal('creation', 'contrat', $id, 'Nouveau contrat');
            flash('Contrat enregistré.');
            go('?p=locataires&s=contrats');
        }
        if ($act === 'edl_nouveau' && can('locataire')) {
            db()->prepare('INSERT INTO etats_lieux (contrat_id, utilisateur_id, nom, date_etat, type_etat, observations, statut) VALUES (?,?,?,?,?,?,"actif")')
                ->execute([(int) $_POST['contrat_id'], $uid, trim($_POST['nom'] ?? 'État des lieux'), $_POST['date_etat'] ?: date('Y-m-d'), ($_POST['type_etat'] ?? '') === 'sortie' ? 'sortie' : 'entree', trim($_POST['observations'] ?? '')]);
            journal('creation', 'contrat', (int) $_POST['contrat_id'], 'État des lieux');
            flash('État des lieux enregistré.');
            go('?p=locataires&s=edl');
        }
        if ($act === 'mandat_modifier' && can('proprietaire')) {
            db()->prepare('UPDATE proprietaires SET numero_mandat=?, taux_commission=?, date_debut_mandat=?, date_fin_mandat=? WHERE proprietaire_id=?')
                ->execute([trim($_POST['mandat'] ?? ''), (float) ($_POST['taux'] ?? 10), ($_POST['debut'] ?? '') !== '' ? $_POST['debut'] : null, ($_POST['fin'] ?? '') !== '' ? $_POST['fin'] : null, (int) $_POST['id']]);
            journal('modification', 'proprietaire', (int) $_POST['id'], 'Mandat mis à jour');
            flash('Mandat enregistré.');
            go('?p=proprietaires&s=mandats');
        }
        if ($act === 'depense_nouveau' && can('proprietaire')) {
            $bien = one('SELECT bien_id, proprietaire_id, agence_id FROM biens WHERE bien_id=?', [(int) $_POST['bien_id']]);
            if (!$bien) { flash('Bien introuvable.'); go('?p=proprietaires&s=depenses'); }
            db()->prepare('INSERT INTO depenses (bien_id, proprietaire_id, agence_id, libelle, montant, date_depense, statut) VALUES (?,?,?,?,?,?,"actif")')
                ->execute([(int) $bien['bien_id'], (int) $bien['proprietaire_id'], (int) ($bien['agence_id'] ?? aid() ?? 1), trim($_POST['libelle'] ?? ''), (float) $_POST['montant'], $_POST['date_depense'] ?: date('Y-m-d')]);
            journal('creation', 'bien', (int) $bien['bien_id'], 'Dépense sur le bien');
            flash('Dépense enregistrée.');
            go('?p=proprietaires&s=depenses');
        }
        if ($act === 'caisse_ouvrir' && can('caisse')) {
            $cid = (int) $_POST['caisse_id'];
            if (one('SELECT journee_id FROM journees_caisses WHERE caisse_id = ? AND statut = "ouverte"', [$cid])) {
                flash('Cette caisse a déjà une journée ouverte.');
            } else {
                $fond = (float) $_POST['fond_initial'];
                db()->prepare('INSERT INTO journees_caisses (caisse_id, date_ouverture, heure_ouverture, fond_initial, solde_theorique, utilisateur_id_ouverture, statut) VALUES (?,CURDATE(),CURTIME(),?,?,?,"ouverte")')
                    ->execute([$cid, $fond, $fond, $uid]);
                journal('ouverture_caisse', 'caisse', $cid, 'Ouverture fond ' . $fond);
                flash('Caisse ouverte.');
            }
            go('?p=caisse&s=ouverture');
        }
        if ($act === 'encaisser' && can('caisse')) {
            $cid = (int) $_POST['caisse_id'];
            $jour = one('SELECT * FROM journees_caisses WHERE caisse_id = ? AND statut = "ouverte" ORDER BY journee_id DESC LIMIT 1', [$cid]);
            if (!$jour) { flash('Ouvrez la caisse avant d\'encaisser.'); go('?p=caisse&s=ouverture'); }
            $montant = (float) $_POST['montant'];
            $mode = (string) ($_POST['mode'] ?? 'especes');
            $ref = trim((string) ($_POST['reference'] ?? ''));
            if (!in_array($mode, ['especes', 'orange_money', 'mtn_momo', 'moov_money', 'wave', 'cheque', 'virement'], true)) $mode = 'especes';
            if ($mode !== 'especes' && $ref === '') { flash('La référence est obligatoire hors espèces.'); go('?p=caisse&s=nouvel'); }
            $loyer = (int) ($_POST['loyer_id'] ?? 0);
            $num = prochainRecu();
            db()->beginTransaction();
            db()->prepare('INSERT INTO transactions (date_transaction, heure, montant, type_transaction, categorie, mode_paiement, reference_paiement, objet, numero_recu, loyer_id, caisse_id, journee_id, utilisateur_id, etat, statut, nature_beneficiaire, nom_beneficiaire) VALUES (CURDATE(),CURTIME(),?,"entree","loyer",?,?,?,?,?,?,?,?,"valide","succes","locataire",?)')
                ->execute([$montant, $mode, $ref, trim($_POST['objet'] ?? 'Encaissement loyer'), $num, $loyer ?: null, $cid, $jour['journee_id'], $uid, trim($_POST['benef'] ?? '')]);
            $tid = (int) db()->lastInsertId();
            db()->prepare('INSERT INTO recus (numero, transaction_id, type_recu, agence_id, statut) VALUES (?,?,"recu",?,"actif")')->execute([$num, $tid, aid() ?: (int) (me()['agence_id'] ?? 1)]);
            $rid = (int) db()->lastInsertId();
            db()->prepare('UPDATE caisses SET solde = solde + ? WHERE caisse_id = ?')->execute([$montant, $cid]);
            if ($loyer) {
                db()->prepare('UPDATE loyers SET reste_a_payer = GREATEST(montant - (montant_paye + ?), 0), statut = IF(montant_paye + ? >= montant, "paye", "partiel"), montant_paye = montant_paye + ? WHERE loyer_id = ?')->execute([$montant, $montant, $montant, $loyer]);
            }
            db()->prepare('UPDATE journees_caisses SET nombre_entrees = nombre_entrees + 1, total_entrees = total_entrees + ?, solde_theorique = fond_initial + total_entrees - total_sorties WHERE journee_id = ?')->execute([$montant, $jour['journee_id']]);
            db()->commit();
            journal('encaissement', 'caisse', $tid, $num . ' ' . $montant);
            flash('Reçu ' . $num . ' créé. Il ne peut pas être supprimé.');
            go('?p=recu&id=' . $rid);
        }
        if ($act === 'caisse_sortie' && can('caisse')) {
            $cid = (int) $_POST['caisse_id'];
            $jour = one('SELECT * FROM journees_caisses WHERE caisse_id = ? AND statut = "ouverte" ORDER BY journee_id DESC LIMIT 1', [$cid]);
            if (!$jour) { flash('Ouvrez la caisse avant une sortie.'); go('?p=caisse&s=ouverture'); }
            $montant = (float) $_POST['montant'];
            $seuil = (float) (one('SELECT valeur FROM parametres WHERE cle = "seuil_validation_decaissement" ORDER BY agence_id IS NULL DESC LIMIT 1')['valeur'] ?? 100000);
            $etat = ($montant > $seuil && !plein() && !can('administration')) ? 'en_attente' : 'valide';
            db()->prepare('INSERT INTO transactions (date_transaction, heure, montant, type_transaction, categorie, mode_paiement, objet, caisse_id, journee_id, utilisateur_id, etat, statut, nature_beneficiaire, nom_beneficiaire) VALUES (CURDATE(),CURTIME(),?,"sortie","depense","especes",?,?,?, ?,?,"succes","autres",?)')
                ->execute([$montant, trim($_POST['objet'] ?? 'Sortie'), $cid, $jour['journee_id'], $uid, $etat, trim($_POST['benef'] ?? '')]);
            $tid = (int) db()->lastInsertId();
            if ($etat === 'valide') {
                db()->prepare('UPDATE caisses SET solde = solde - ? WHERE caisse_id = ?')->execute([$montant, $cid]);
                db()->prepare('UPDATE journees_caisses SET nombre_sorties = nombre_sorties + 1, total_sorties = total_sorties + ?, solde_theorique = fond_initial + total_entrees - total_sorties WHERE journee_id = ?')->execute([$montant, $jour['journee_id']]);
            }
            journal('sortie_caisse', 'caisse', $tid, $etat . ' ' . $montant);
            flash($etat === 'valide' ? 'Sortie enregistrée.' : 'Sortie en attente de validation (seuil dépassé).');
            go('?p=caisse&s=sorties');
        }
        if ($act === 'caisse_cloture' && can('caisse')) {
            $jid = (int) $_POST['journee_id'];
            $jour = one('SELECT * FROM journees_caisses WHERE journee_id = ? AND statut = "ouverte"', [$jid]);
            if (!$jour) { flash('Journée introuvable.'); go('?p=caisse&s=cloture'); }
            $physique = (float) $_POST['solde_physique'];
            $theorique = (float) $jour['solde_theorique'];
            db()->prepare('UPDATE journees_caisses SET date_fermeture = CURDATE(), heure_fermeture = CURTIME(), solde_physique = ?, ecart = ?, statut = "fermee", utilisateur_id_fermeture = ? WHERE journee_id = ?')
                ->execute([$physique, $physique - $theorique, $uid, $jid]);
            journal('cloture_caisse', 'caisse', $jid, 'Écart ' . ($physique - $theorique));
            flash('Journée clôturée. Écart : ' . fcfa($physique - $theorique));
            go('?p=caisse&s=cloture');
        }
        if ($act === 'annuler_recu' && can('caisse')) {
            $tid = (int) $_POST['transaction_id'];
            $src = one('SELECT * FROM transactions WHERE transaction_id = ? AND statut <> "annule"', [$tid]);
            if (!$src) { flash('Mouvement introuvable.'); go('?p=caisse&s=recus'); }
            $num = prochainRecu();
            db()->beginTransaction();
            db()->prepare('UPDATE transactions SET statut = "annule" WHERE transaction_id = ?')->execute([$tid]);
            db()->prepare('INSERT INTO transactions (date_transaction, heure, montant, type_transaction, categorie, objet, numero_recu, caisse_id, journee_id, utilisateur_id, annule_transaction_id, etat, statut) VALUES (CURDATE(),CURTIME(),?,"sortie",?,"Annulation",?,?,?,?,?,"valide","succes")')
                ->execute([$src['montant'], $src['categorie'], $num, $src['caisse_id'], $src['journee_id'], $uid, $tid]);
            $nid = (int) db()->lastInsertId();
            db()->prepare('INSERT INTO recus (numero, transaction_id, type_recu, agence_id, statut) VALUES (?,?,"annulation",?,"actif")')->execute([$num, $nid, aid() ?: 1]);
            if ($src['caisse_id'] && $src['type_transaction'] === 'entree') {
                db()->prepare('UPDATE caisses SET solde = solde - ? WHERE caisse_id = ?')->execute([$src['montant'], $src['caisse_id']]);
            }
            db()->commit();
            journal('annulation', 'recus', $nid, 'Annule ' . $src['numero_recu'] . ' par ' . $num);
            flash('Reçu d\'annulation ' . $num . '. L\'original n\'est pas supprimé.');
            go('?p=caisse&s=recus');
        }
        if ($act === 'user_creer' && can('parametre')) {
            $hash = password_hash((string) $_POST['mdp'], PASSWORD_BCRYPT);
            $role = $_POST['role'] ?? 'assistant';
            db()->prepare('INSERT INTO utilisateurs (agence_id, nom, prenom, login, mdp, telephone, role, statut) VALUES (?,?,?,?,?,?,?,"actif")')
                ->execute([(int) ($_POST['agence_id'] ?? 1) ?: null, trim($_POST['nom']), trim($_POST['prenom']), trim($_POST['login']), $hash, trim($_POST['telephone'] ?? ''), $role]);
            $id = (int) db()->lastInsertId();
            db()->prepare('INSERT INTO utilisateurs_permissions (utilisateur_id, module, peut_voir, peut_creer, peut_modifier, peut_supprimer, peut_valider, statut) SELECT ?, module, peut_voir, peut_creer, peut_modifier, peut_supprimer, peut_valider, "actif" FROM roles_permissions WHERE role = ?')->execute([$id, $role]);
            journal('creation', 'utilisateur', $id, 'Nouvel utilisateur ' . $_POST['login']);
            flash('Utilisateur créé.');
            go('?p=parametres&s=users');
        }
        if ($act === 'user_statut' && can('parametre')) {
            $id = (int) $_POST['utilisateur_id'];
            $statut = ($_POST['statut'] ?? '') === 'actif' ? 'actif' : 'inactif';
            db()->prepare('UPDATE utilisateurs SET statut = ? WHERE utilisateur_id = ?')->execute([$statut, $id]);
            journal('statut_utilisateur', 'administration', $id, $statut);
            flash('Compte mis à jour.');
            go('?p=parametres&s=users');
        }
        if ($act === 'agence_params' && can('parametre')) {
            $ag = (int) ($_POST['agence_id'] ?? aid() ?? 1);
            db()->prepare('UPDATE agences SET telephone = ?, email = ?, adresse = ? WHERE agence_id = ?')->execute([trim($_POST['telephone'] ?? ''), trim($_POST['email'] ?? ''), trim($_POST['adresse'] ?? ''), $ag]);
            foreach (['penalite_taux' => $_POST['penalite'] ?? '10', 'seuil_validation_decaissement' => $_POST['seuil'] ?? '100000'] as $cle => $val) {
                db()->prepare('INSERT INTO parametres (agence_id, cle, valeur) VALUES (?,?,?) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)')->execute([$ag, $cle, $val]);
            }
            journal('parametres', 'parametre', $ag, 'Agence et pénalités');
            flash('Paramètres enregistrés.');
            go('?p=parametres&s=agence');
        }
        flash('Action non autorisée.');
        go('?p=accueil');
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        flash('Erreur : ' . $e->getMessage());
        go('?p=' . ($_GET['p'] ?? 'accueil'));
    }
}

if (!$errDb && ($_POST['action'] ?? '') === 'demande' && !me()) {
    try {
        db()->prepare('INSERT INTO demandes_location (local_id, nom, prenom, telephone, email, profession, message, statut) VALUES (?,?,?,?,?,?,?,"nouvelle")')
            ->execute([(int) $_POST['local_id'], trim($_POST['nom'] ?? ''), trim($_POST['prenom'] ?? ''), trim($_POST['telephone'] ?? ''), trim($_POST['email'] ?? ''), trim($_POST['profession'] ?? ''), trim($_POST['message'] ?? '')]);
        $id = (int) db()->lastInsertId();
        db()->prepare('INSERT INTO journal_activite (utilisateur_id, action, module, element_id, details) VALUES (NULL,"demande_publique","demande_location",?,?)')->execute([$id, 'Demande publique']);
        flash('Demande enregistrée. L\'agence vous rappellera.');
        go('?p=public&s=catalogue');
    } catch (Throwable $e) {
        $loginError = $e->getMessage();
    }
}

$p = $_GET['p'] ?? (me() ? 'accueil' : 'arrivee');
$s = $_GET['s'] ?? '';
$map = [
    'accueil' => 'accueil', 'dashboard' => 'dashboard', 'rapports' => 'rapport',
    'locataires' => 'locataire', 'proprietaires' => 'proprietaire', 'caisse' => 'caisse',
    'parametres' => 'parametre', 'contrat' => 'accueil', 'bien' => 'accueil', 'recu' => 'caisse',
];
if (me() && isset($map[$p]) && !can($map[$p])) $p = 'refuse';

$CINQ = [
    ['accueil', 'Accueil', 'accueil'],
    ['locataires', 'Locataires', 'locataire'],
    ['proprietaires', 'Propriétaires', 'proprietaire'],
    ['caisse', 'Caisse', 'caisse'],
    ['parametres', 'Paramètres', 'parametre'],
];
$SOUS = [
    'accueil' => ['vue' => "Vue d'ensemble", 'encaisser' => 'Encaisser un loyer', 'nouveau' => 'Nouveau locataire', 'relances' => 'Relancer les impayés', 'demandes' => 'Demandes de location', 'reclamations' => 'Réclamations', 'activites' => 'Dernières activités', 'agences' => "Sélecteur d'agence"],
    'locataires' => ['fiches' => 'Fiches locataires', 'impayes' => 'Impayés', 'contrats' => 'Contrats', 'locaux' => 'Locaux loués', 'echeancier' => 'Échéancier', 'encaisser' => 'Encaisser un loyer', 'edl' => 'États des lieux', 'reclamations' => 'Réclamations', 'relances' => 'Relances', 'penalites' => 'Pénalités', 'caution' => 'Restitution de caution'],
    'proprietaires' => ['fiches' => 'Fiches et biens', 'mandats' => 'Mandats et commissions', 'loyers' => 'Loyers encaissés', 'reversements' => 'Reversements', 'depenses' => 'Dépenses', 'releves' => 'Relevés mensuels'],
    'caisse' => ['ouverture' => 'Ouverture', 'cloture' => 'Clôture', 'nouvel' => 'Nouvel encaissement', 'encaissements' => 'Encaissements', 'sorties' => 'Décaissement', 'journal' => 'Synthèse / journal', 'banque' => 'Versement banque', 'recus' => 'Reçus', 'rapport' => 'Rapport de journée'],
    'parametres' => ['agence' => 'Agence et logo', 'users' => 'Utilisateurs', 'roles' => 'Rôles et permissions', 'referentiels' => 'Types et fréquences', 'modeles' => 'Modèles', 'penalites' => 'Pénalités et seuil', 'journal' => "Journal d'activité", 'export' => 'Sauvegarde', 'admin' => 'Administration'],
];

function filtre(string $col): array {
    $id = aid();
    return $id ? [" AND $col = ?", [$id]] : ['', []];
}

?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ROUAHIMO</title>
<style>
:root{--teal:#E8650A;--ink:#1F2937;--muted:#6B7280;--line:#F3E4D8;--canvas:#FFF8F3}
html[data-palette="bleu"]{--teal:#2563EB}
html[data-palette="vert"]{--teal:#059669}
html[data-palette="indigo"]{--teal:#4F46E5}
html[data-theme="sombre"]{--ink:#F9FAFB;--muted:#D1D5DB;--line:#44403C;--canvas:#1C1917}
html[data-theme="sombre"] body{background:#292524;color:var(--ink)}
html[data-theme="sombre"] .card,html[data-theme="sombre"] .paper,html[data-theme="sombre"] .table-wrap,html[data-theme="sombre"] .filtres,html[data-theme="sombre"] .pal-menu,html[data-theme="sombre"] .profil-menu,html[data-theme="sombre"] .caisse-card,html[data-theme="sombre"] .caisse-side,html[data-theme="sombre"] .till,html[data-theme="sombre"] .pill-jour,html[data-theme="sombre"] .btn-retour,html[data-theme="sombre"] .btn-hist{background:#292524;color:var(--ink)}
html[data-theme="sombre"] input,html[data-theme="sombre"] select,html[data-theme="sombre"] textarea{background:#1C1917;color:var(--ink)}
*{box-sizing:border-box} body{margin:0;font-family:"Segoe UI",Helvetica,Arial,sans-serif;color:var(--ink);background:#fff;overflow-x:hidden}
a{color:var(--teal);text-decoration:none} img.cover{width:100%;height:160px;object-fit:cover;border-radius:12px}
.side5{position:fixed;top:0;left:0;bottom:0;width:240px;background:#1E293B;border-right:1px solid #334155;display:none;flex-direction:column;gap:4px;padding:16px 12px;z-index:30}
.side5 img{height:36px;margin:0 8px 12px}
.side5 a,.bottom5 a{color:#CBD5E1;border-radius:12px;padding:10px 12px;font-weight:600}
.side5 a.on{background:var(--teal);color:#fff}
.bottom5 a.on{background:transparent;color:var(--teal)}
.side5 a.locked,.bottom5 a.locked{opacity:.4}
.side5 a.back{color:#fff}
.side-login{margin-top:auto;background:#E8650A;color:#fff!important;text-align:center}
.bottom5{display:none}
.fine,.public-top{display:flex;align-items:center;gap:8px;min-height:52px;background:#1E293B;color:#fff;padding:0 12px}
.fine a,.fine span,.public-top a{color:#fff;font-weight:700}
.fine a.login{margin-left:auto;background:#E8650A;border-radius:12px;padding:8px 12px}
main.inset{margin-left:0;background:var(--canvas);min-height:100vh;max-width:100%;overflow-x:hidden}
.pad{padding:16px}
.card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:16px;margin-bottom:12px}
.grid{display:grid;gap:12px;grid-template-columns:repeat(auto-fill,minmax(200px,1fr))}
.kpi4{display:grid;gap:12px;grid-template-columns:repeat(4,minmax(0,1fr));margin-bottom:12px}
.kpi4 .card{display:flex;gap:10px;align-items:center;margin:0}
.dot{width:42px;height:42px;border-radius:99px;display:grid;place-items:center;color:#fff;font-weight:800;flex:0 0 auto}
.actions{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 12px}
.chart-svg{width:100%;height:auto;display:block}
.sub a{display:inline-block;margin:0 8px 8px 0;background:#fff;border:1px solid var(--line);border-radius:12px;padding:8px 10px;color:var(--ink)}
.sub a.on{background:var(--teal);color:#fff;border-color:var(--teal)}
table{width:100%;border-collapse:collapse;font-size:14px} td,th{border-bottom:1px solid var(--line);padding:8px;text-align:left;vertical-align:top}
.btn,.btn-fill,.btn-line{display:inline-block;border-radius:16px;padding:12px 16px;font-weight:700;text-align:center}
.btn,.btn-fill{background:#E8650A;color:#fff;border:0}
.btn-line{background:#fff;color:#E8650A;border:2px solid #E8650A}
input,select,textarea{width:100%;padding:10px;border:1px solid var(--line);border-radius:12px;margin:4px 0 10px}
.muted{color:var(--muted)} .warn{background:#fff4d6;color:#8a5a00;border-radius:12px;padding:12px}
.paper{max-width:760px;margin:0 auto;background:#fff;padding:24px}
.welcome{min-height:calc(100vh - 52px);display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:24px 16px 32px}
.welcome h1{margin:.75rem 0 0;font-size:2rem;letter-spacing:.04em}
.welcome p{margin:.75rem 0 0;color:var(--muted)}
.welcome .btn-fill,.welcome .btn-line{width:min(100%,24rem);min-height:3.25rem;line-height:3.25rem;padding:0 16px;margin-top:.85rem}
.welcome .btn-fill{margin-top:2rem}
.filters{display:grid;gap:8px;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));margin-bottom:12px}
.duo{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.duo a{display:grid;min-height:44px;place-items:center;border-radius:12px;font-weight:700}
.duo a.call{background:#E8650A;color:#fff}
.duo a.wa{border:2px solid #E8650A;color:#E8650A;background:#fff}
.stats{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.stats div{background:#FFF1E6;border-radius:12px;padding:8px 10px}
.stats dt{font-size:12px;color:var(--muted)}
.profil{position:relative;margin-left:0}
.fine-right{margin-left:auto;display:flex;align-items:center;gap:2px}
.theme-btn,.pal summary{list-style:none;cursor:pointer;display:inline-grid;place-items:center;min-width:40px;min-height:40px;border:0;background:transparent;color:#fff}
.pal{position:relative}
.pal summary::-webkit-details-marker{display:none}
.pal-menu{position:absolute;right:0;top:calc(100% + 4px);background:#fff;color:var(--ink);border:1px solid var(--line);border-radius:12px;min-width:160px;padding:6px;z-index:70}
.pal-menu button{display:flex;align-items:center;gap:8px;width:100%;margin:0;border:0;background:#fff;color:var(--ink);border-radius:8px;padding:8px 10px;font-weight:700}
.sw{width:12px;height:12px;border-radius:99px;display:inline-block}
.banner{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;background:var(--teal);color:#fff;border-radius:16px;padding:16px 18px;margin-bottom:12px}
.banner h1{margin:0;font-size:22px;color:#fff}
.banner p{margin:4px 0 0;opacity:.92;font-size:13px}
.banner .btn-new{background:#fff;color:var(--teal);border-radius:999px;padding:10px 14px;font-weight:800}
.filtres{display:flex;flex-wrap:wrap;gap:8px;align-items:center;background:#fff;border:1px solid var(--line);border-radius:14px;padding:10px;margin-bottom:12px}
.filtres input,.filtres select{width:auto;min-width:140px;flex:1;margin:0}
.btn-go{background:var(--teal);color:#fff;border:0;border-radius:10px;min-height:42px;padding:0 14px;font-weight:800}
.btn-clear{display:inline-grid;place-items:center;background:#fff;color:var(--ink);border:1px solid var(--line);border-radius:10px;min-height:42px;padding:0 14px;font-weight:700}
.table-wrap{max-width:100%;overflow-x:auto;background:#fff;border:1px solid var(--line);border-radius:14px}
table.pro{min-width:720px;background:transparent}
table.pro th{font-size:12px;letter-spacing:.04em;text-transform:uppercase;color:var(--muted)}
table.pro td strong{text-transform:uppercase}
.badge{display:inline-block;border-radius:6px;padding:3px 8px;font-size:11px;font-weight:800;letter-spacing:.04em;color:#fff}
.badge.ok{background:#16a34a}
.badge.off{background:#94a3b8}
.badge.wait{background:#f59e0b;color:#1f2937}
.act{display:inline-flex;gap:6px;align-items:center}
.act a,.act button{display:inline-grid;place-items:center;width:34px;height:34px;border-radius:8px;border:0;font-weight:800;margin:0;padding:0}
.act .edit{background:#facc15;color:#1f2937}
.act .del{background:#ef4444;color:#fff}
.sexe{font-size:18px}
.duo a.call{background:var(--teal)}
.btn,.btn-fill{background:var(--teal)}
.profil summary{list-style:none;cursor:pointer;display:flex;align-items:center;gap:6px;color:#fff;font-weight:700}
.profil summary::-webkit-details-marker{display:none}
.profil-menu{position:absolute;right:0;top:calc(100% + 6px);background:#fff;color:var(--ink);border:1px solid var(--line);border-radius:16px;min-width:210px;padding:8px;z-index:60;box-shadow:0 8px 24px rgba(0,0,0,.12)}
.profil-menu a{display:block;color:var(--ink);padding:10px 12px;border-radius:12px;font-weight:600}
.profil-menu a:hover{background:var(--canvas)}
.profil-menu a.danger{color:#B91C1C}
.lock{min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;background:#E8650A;color:#fff;text-align:center;padding:24px}
.lock input{max-width:280px}
.lock a{color:#fff;font-weight:700}
.ico{vertical-align:middle}
.caisse-page{width:100%;max-width:100%;overflow-x:hidden}
.caisse-head{display:flex;flex-wrap:wrap;justify-content:space-between;gap:10px;align-items:flex-start;margin-bottom:12px}
.caisse-head h1{margin:0;font-size:22px}
.caisse-head p{margin:4px 0 0;color:var(--muted);font-size:13px}
.caisse-actions{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.pill-jour,.btn-retour{display:inline-flex;align-items:center;gap:6px;min-height:36px;border-radius:999px;border:1px solid var(--line);background:#fff;color:var(--ink);padding:0 12px;font-weight:700;font-size:13px}
.pill-jour .dot{width:8px;height:8px;border-radius:99px;background:#94a3b8;display:inline-block}
.pill-jour.on .dot{background:#16a34a}
.caisse-board{display:grid;grid-template-columns:minmax(0,1fr);gap:12px}
.caisse-card,.caisse-side,.till{min-width:0;background:#fff;border:1px solid var(--line);border-radius:16px;padding:14px;margin-bottom:12px}
.caisse-card h2,.caisse-side h2{margin:0 0 12px;color:var(--teal);font-size:15px}
.jour-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.jour-grid span{display:block;color:var(--muted);font-size:12px}
.jour-grid strong{display:block;margin-top:3px}
.etat-ok{color:#16a34a}.etat-off{color:var(--muted)}
.caisse-duo{display:grid;grid-template-columns:minmax(0,1fr);gap:10px}
.till h3{margin:0;font-size:14px}
.till-code{color:var(--muted);font-size:11px;letter-spacing:.04em}
.till-top{display:flex;justify-content:space-between;gap:8px;align-items:flex-start}
.kv{display:flex;justify-content:space-between;gap:8px;padding:3px 0;font-size:14px}
.plus{color:#16a34a;font-weight:700}.moins{color:#e11d48;font-weight:700}
.till-moves{margin-top:8px;border-top:1px solid var(--line);padding-top:8px;font-size:13px}
.move{display:flex;justify-content:space-between;gap:8px;padding:2px 0}
.till-actions{display:flex;gap:8px;align-items:center;margin-top:10px}
.btn-ouvrir,.btn-fermer{flex:1;min-height:44px;border:0;border-radius:8px;color:#fff;font-weight:800}
.btn-ouvrir{background:#2563eb}.btn-fermer{background:#f43f5e}
.btn-hist{display:inline-grid;place-items:center;width:44px;height:44px;border-radius:8px;border:1px solid var(--line);background:#fff;color:var(--ink);font-weight:800}
.fond-label{display:block;margin-top:8px;font-size:12px;color:var(--muted)}
.fond-label input{margin:4px 0 0}
.syn-solde{margin:12px 0 0;font-size:12px;letter-spacing:.04em;color:var(--muted);font-weight:800}
.syn-montant{margin:0;font-size:26px;font-weight:800}
.syn-counts{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:12px}
.syn-counts div{background:var(--canvas);border-radius:12px;text-align:center;padding:10px}
.syn-counts strong{display:block;font-size:22px}
@media(min-width:1100px){
  .caisse-board{grid-template-columns:minmax(0,1fr) 270px}
  .caisse-duo{grid-template-columns:minmax(0,1fr) minmax(0,1fr)}
}
@media(max-width:767px){
  .side5{display:none!important}
  main.inset{margin-left:0;padding-bottom:84px}
  .bottom5{position:fixed;left:0;right:0;bottom:0;height:68px;display:flex;background:#1E293B;z-index:40}
  .bottom5 a{flex:1;text-align:center;font-size:11px;padding:8px 2px;background:transparent}
  .bottom5 a.on{background:transparent;color:var(--teal)}
  .wide{display:none}
  .public-top{display:flex}
  .welcome{min-height:calc(100vh - 120px)}
  .kpi4{grid-template-columns:1fr}
}
@media(min-width:768px){
  .side5{display:flex}
  main.inset{margin-left:240px}
  .bottom5{display:none!important}
  .moblogo{display:none}
  .public-top{display:none}
  .wide{display:inline}
}
@media print{.fine,.side5,.bottom5,.public-top,.noprint{display:none!important} main.inset{margin:0;background:#fff}}
</style>
<script>
try {
  var pal = localStorage.getItem("rouahimo-palette") || "orange";
  var theme = localStorage.getItem("rouahimo-theme") || "clair";
  document.documentElement.dataset.palette = pal;
  document.documentElement.dataset.theme = theme;
} catch (e) {}
</script>
</head>
<body>
<?php if ($errDb): ?>
<main class="paper"><h1>Base non trouvée</h1>
<p>Importez <strong>sql/rouahimo.sql</strong> dans phpMyAdmin (base <strong>rouahimo</strong>), puis rechargez.</p>
<p class="muted"><?= h($errDb) ?></p>
<p>WampServer doit être vert. Dossier : C:\wamp64\www\rouahimo — http://localhost/rouahimo/</p>
</main>
<?php else:
if (me() && !empty($_SESSION['verrou'])) {
    $qui = trim((me()['prenom'] ?? '') . ' ' . (me()['nom'] ?? ''));
    echo '<main class="lock"><p>Session verrouillée</p><h1>' . h($qui) . '</h1><p>' . h(roleLabel(me()['role'] ?? null)) . '</p>';
    if ($loginError) echo '<p>' . h($loginError) . '</p>';
    echo '<form method="post" style="width:min(100%,280px)"><input type="hidden" name="action" value="unlock"><label>Mot de passe</label><input name="mdp" type="password" required><button class="btn" type="submit">Déverrouiller</button></form><p><a href="?logout=1">Se déconnecter</a></p></main></body></html>';
    exit;
}
$public = !me() && $p === 'public';
$ico = '<svg class="ico" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="8" r="3.2"/><path d="M5 19c1.5-3 3.8-4.5 7-4.5S17.5 16 19 19"/></svg>';
if ($public) {
    $ps = $_GET['s'] ?? 'accueil';
    $pub = [
        'accueil' => 'Accueil',
        'catalogue' => 'Catalogue',
        'agences' => 'Agences',
        'demande' => 'Logement',
        'contact' => 'Contact',
    ];
    echo '<nav class="side5"><a href="?"><img src="assets/logo-rouahimo-menu.svg" alt="ROUAHIMO"></a><a class="back" href="?">← Retour</a>';
    foreach (['accueil' => 'Accueil', 'catalogue' => 'Catalogue des logements', 'agences' => 'Agences', 'demande' => 'Je veux ce logement', 'contact' => 'Contact'] as $id => $label) {
        echo '<a class="' . ($ps === $id || ($id === 'catalogue' && $ps === 'fiche') ? 'on' : '') . '" href="?p=public&s=' . $id . '">' . h($label) . '</a>';
    }
    echo '<a class="side-login" href="?p=login">Se connecter</a></nav><nav class="bottom5">';
    foreach ($pub as $id => $label) {
        echo '<a class="' . ($ps === $id ? 'on' : '') . '" href="?p=public&s=' . $id . '">' . h($label) . '</a>';
    }
    echo '<a href="?p=login">Connexion</a></nav>';
} else {
    $side = ''; $bas = '';
    foreach ($CINQ as [$id, $label, $mod]) {
        $locked = me() && !can($mod);
        $href = !me() ? '?p=login&next=' . $id : ($locked ? '?p=refuse' : '?p=' . $id);
        $cls = ($p === $id ? 'on' : '') . ($locked ? ' locked' : '');
        $side .= '<a class="' . $cls . '" href="' . $href . '">' . h($label) . '</a>';
        $bas .= '<a class="' . $cls . '" href="' . $href . '">' . h($label) . '</a>';
    }
    echo '<nav class="side5"><a href="' . (me() ? '?p=accueil' : '?') . '"><img src="assets/logo-rouahimo-menu.svg" alt="ROUAHIMO"></a>' . $side . '</nav><nav class="bottom5">' . $bas . '</nav>';
}
?>
<main class="inset">
<?php
if ($public) {
    echo '<div class="public-top"><a href="?">← Retour</a><a href="?">ROUAHIMO</a></div>';
} elseif (me()) {
    echo '<div class="fine"><a href="javascript:history.back()">← Retour</a><a href="?p=dashboard"><span class="wide">Tableau de bord</span><span class="moblogo">▤</span></a>';
    echo '<div class="fine-right"><div class="theme-tools"><details class="pal"><summary aria-label="Couleurs"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3a9 9 0 0 0 0 18h1.2a2 2 0 0 0 0-4H12a2 2 0 0 1 0-4h.5A2.5 2.5 0 0 0 15 10.5 7.5 7.5 0 0 0 12 3z"/><circle cx="7.5" cy="10.5" r="1" fill="currentColor"/><circle cx="9.5" cy="7.5" r="1" fill="currentColor"/><circle cx="14" cy="7.5" r="1" fill="currentColor"/></svg></summary><div class="pal-menu">';
    echo '<button type="button" data-pal="orange"><span class="sw" style="background:#E8650A"></span>Orange</button>';
    echo '<button type="button" data-pal="bleu"><span class="sw" style="background:#2563EB"></span>Bleu</button>';
    echo '<button type="button" data-pal="vert"><span class="sw" style="background:#059669"></span>Vert</button>';
    echo '<button type="button" data-pal="indigo"><span class="sw" style="background:#4F46E5"></span>Indigo</button>';
    echo '</div></details><button type="button" class="theme-btn" id="theme-toggle" aria-label="Clair ou sombre"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 14.5A8.5 8.5 0 1 1 9.5 3 7 7 0 0 0 21 14.5z"/></svg></button></div>';
    echo '<details class="profil"><summary>' . $ico . ' <span class="wide">' . h(roleLabel(me()['role'] ?? null)) . '</span></summary><div class="profil-menu">';
    echo '<p style="margin:4px 12px 0;font-weight:700">' . h(trim((me()['prenom'] ?? '') . ' ' . (me()['nom'] ?? ''))) . '</p>';
    echo '<p class="muted" style="margin:0 12px 8px;font-size:12px">' . h(roleLabel(me()['role'] ?? null)) . '</p>';
    echo '<a href="?p=profil">Mon profil</a><a href="?verrou=1">Verrouiller</a><a class="danger" href="?logout=1">Se déconnecter</a></div></details></div></div>';
} else {
    echo '<div class="fine"><img class="moblogo" src="assets/logo-rouahimo-menu.svg" alt="ROUAHIMO" style="height:32px"><a href="?p=login&next=dashboard"><span class="wide">Tableau de bord</span><span class="moblogo">▤</span></a><a class="login" href="?p=login">Se connecter</a></div>';
}
?>
<div class="pad">
<?php if (!empty($_SESSION['flash'])): ?><p class="warn"><?= h($_SESSION['flash']) ?></p><?php unset($_SESSION['flash']); endif; ?>
<?php
if (!me() && ($p === 'arrivee' || $p === '')) {
    echo '<div class="welcome"><img src="assets/logo-rouahimo.svg" alt="" style="height:64px"><h1>ROUAHIMO</h1><p>Gestion immobilière pour les agences en Côte d\'Ivoire</p>';
    echo '<a class="btn-fill" href="?p=public">Commencer</a><a class="btn-line" href="?p=login">Se connecter</a></div>';
} elseif ($public) {
    renduPublic((string) ($_GET['s'] ?? 'accueil'));
} elseif (!me() && $p === 'login') {
    echo '<div class="paper card"><h1>Se connecter</h1>';
    if ($loginError) echo '<p class="warn">' . h($loginError) . '</p>';
    $next = h((string) ($_GET['next'] ?? 'accueil'));
    echo '<form method="post"><input type="hidden" name="action" value="login"><input type="hidden" name="next" value="' . $next . '"><label>Identifiant</label><input name="login" required><label>Mot de passe</label><input name="mdp" type="password" required><button class="btn" type="submit">Se connecter</button></form></div>';
} elseif ($p === 'profil' && me()) {
    $u = me();
    echo '<h1>Mon profil</h1><div class="card" style="max-width:28rem"><p style="font-size:18px;font-weight:700">' . h(trim(($u['prenom'] ?? '') . ' ' . ($u['nom'] ?? ''))) . '</p>';
    echo '<p class="muted">' . h(roleLabel($u['role'] ?? null)) . '</p><p>Identifiant : ' . h((string) $u['login']) . '</p>';
    echo '<p class="muted">' . h((string) ($u['telephone'] ?? '')) . ' · ' . h((string) ($u['email'] ?? '')) . '</p></div>';
} elseif ($p === 'refuse' || empty(me()['role'])) {
    echo '<div class="paper card"><h1>Accès refusé</h1><p>Votre rôle n\'ouvre pas cette partie.</p><a class="btn" href="?p=accueil">Retour</a></div>';
} else {
    if (isset($SOUS[$p])) {
        echo '<div class="sub noprint">';
        foreach ($SOUS[$p] as $id => $label) {
            echo '<a class="' . ($s === $id ? 'on' : '') . '" href="?p=' . h($p) . '&s=' . h($id) . '">' . h($label) . '</a>';
        }
        echo '</div>';
        if ($s === '') $s = array_key_first($SOUS[$p]);
    }
    [$wa, $aa] = filtre('a.agence_id');
    if ($p === 'dashboard' || $p === 'rapports' || ($p === 'accueil' && $s === 'vue')) {
        [$wt, $at] = filtre('t.agence_id');
        $encJ = (float) (one('SELECT COALESCE(SUM(tr.montant),0) m FROM transactions tr JOIN caisses c ON c.caisse_id=tr.caisse_id WHERE tr.type_transaction="entree" AND tr.date_transaction=CURDATE()' . str_replace('t.agence_id', 'c.agence_id', $wt), $at)['m'] ?? 0);
        $encM = (float) (one('SELECT COALESCE(SUM(tr.montant),0) m FROM transactions tr JOIN caisses c ON c.caisse_id=tr.caisse_id WHERE tr.type_transaction="entree" AND tr.date_transaction>=DATE_FORMAT(CURDATE(),"%Y-%m-01")' . str_replace('t.agence_id', 'c.agence_id', $wt), $at)['m'] ?? 0);
        [$wl, $al] = filtre('loc.agence_id');
        $imp = (float) (one('SELECT COALESCE(SUM(l.reste_a_payer),0) m FROM loyers l JOIN contrats c ON c.contrat_id=l.contrat_id JOIN locaux loc ON loc.local_id=c.local_id JOIN biens b ON b.bien_id=loc.bien_id WHERE l.statut IN ("en_retard","en_attente","partiel")' . str_replace('loc.agence_id', 'b.agence_id', $wl), $al)['m'] ?? 0);
        $occ = one('SELECT SUM(l.statut="occupe") o, COUNT(*) n FROM locaux l JOIN biens b ON b.bien_id=l.bien_id WHERE 1=1' . str_replace('a.', 'b.', $wa), $aa);
        $caisses = q('SELECT nom, solde, statut FROM caisses c WHERE 1=1' . str_replace('a.', 'c.', $wa), $aa);
        $rec = (int) (one('SELECT COUNT(*) n FROM reclamations r LEFT JOIN contrats c ON c.contrat_id=r.contrat_id LEFT JOIN locaux l ON l.local_id=c.local_id LEFT JOIN biens b ON b.bien_id=l.bien_id WHERE r.statut="en_attente"' . ($aa ? ' AND (b.agence_id = ? OR r.contrat_id IS NULL)' : ''), $aa)['n'] ?? 0);
        $dem = (int) (one('SELECT COUNT(*) n FROM demandes_location d JOIN locaux l ON l.local_id=d.local_id JOIN biens b ON b.bien_id=l.bien_id WHERE d.statut="nouvelle"' . str_replace('a.', 'b.', $wa), $aa)['n'] ?? 0);
        if (!($p === 'accueil' && $s === 'vue')) {
        echo '<h1>' . ($p === 'rapports' ? 'Rapport' : 'Tableau de bord') . '</h1><div class="grid">';
        foreach ([['Encaissé aujourd\'hui', fcfa($encJ)], ['Encaissé ce mois', fcfa($encM)], ['Impayés', fcfa($imp)], ['Occupation', (int) $occ['o'] . ' / ' . (int) $occ['n']], ['Réclamations', (string) $rec], ['Demandes', (string) $dem]] as [$lab, $val]) {
            echo '<div class="card"><p class="muted">' . h($lab) . '</p><p style="font-size:22px;font-weight:800">' . h($val) . '</p></div>';
        }
        echo '</div><div class="card"><h2>Caisses</h2><ul>';
        foreach ($caisses as $c) echo '<li>' . h($c['nom']) . ' · ' . fcfa((float) $c['solde']) . ' · ' . h($c['statut']) . '</li>';
        echo '</ul></div>';
        }
        if ($p === 'accueil' && $s === 'vue') {
            $nbImp = (int) (one('SELECT COUNT(DISTINCT c.locataire_id) n FROM loyers l JOIN contrats c ON c.contrat_id=l.contrat_id JOIN locaux loc ON loc.local_id=c.local_id JOIN biens b ON b.bien_id=loc.bien_id WHERE l.date_echeance < CURDATE() AND l.statut <> "paye"' . str_replace('a.', 'b.', $wa), $aa)['n'] ?? 0);
            $ouv = (int) (one('SELECT COUNT(*) n FROM journees_caisses j JOIN caisses c ON c.caisse_id=j.caisse_id WHERE j.statut="ouverte"' . str_replace('a.', 'c.', $wa), $aa)['n'] ?? 0);
            $encAff = $encJ > 0 ? $encJ : (float) (one('SELECT COALESCE(SUM(j.total_entrees),0) m FROM journees_caisses j JOIN caisses c ON c.caisse_id=j.caisse_id WHERE j.statut="ouverte"' . str_replace('a.', 'c.', $wa), $aa)['m'] ?? 0);
            $pct = ((int) $occ['n']) > 0 ? (int) round(100 * (int) $occ['o'] / (int) $occ['n']) : 0;
            $an = (int) date('Y');
            $par = array_fill(1, 12, 0.0);
            foreach (q('SELECT MONTH(tr.date_transaction) m, SUM(tr.montant) s FROM transactions tr JOIN caisses c ON c.caisse_id=tr.caisse_id WHERE tr.type_transaction="entree" AND YEAR(tr.date_transaction)=?' . str_replace('t.agence_id', 'c.agence_id', $wt), array_merge([$an], $at)) as $row) {
                $par[(int) $row['m']] = (float) $row['s'];
            }
            $max = max(1, max($par));
            $pts = [];
            for ($i = 1; $i <= 12; $i++) {
                $x = 36 + ($i - 1) * 58;
                $y = 188 - ($par[$i] / $max) * 150;
                $pts[] = round($x, 1) . ',' . round($y, 1);
            }
            echo '<h1>Accueil</h1><p class="muted">Bienvenue sur ROUAHIMO, votre solution de gestion immobilière en Côte d\'Ivoire.</p><div class="kpi4">';
            echo '<div class="card"><span class="dot" style="background:#E8650A">↓</span><div><p class="muted">Encaissements du jour</p><p style="font-size:20px;font-weight:800;color:#E8650A">' . h(number_format($encAff, 0, ',', ' ') . ' FCFA') . '</p></div></div>';
            echo '<a class="card" href="?p=locataires&s=impayes"><span class="dot" style="background:#EF4444">!</span><div><p class="muted">Impayés</p><p style="font-size:20px;font-weight:800;color:#EF4444">' . $nbImp . ' locataires</p></div></a>';
            echo '<div class="card"><span class="dot" style="background:#16A34A">↗</span><div><p class="muted">Occupation</p><p style="font-size:20px;font-weight:800;color:#16A34A">' . $pct . ' %</p></div></div>';
            echo '<div class="card"><span class="dot" style="background:#2563EB">▣</span><div><p class="muted">Caisses ouvertes</p><p style="font-size:20px;font-weight:800;color:#2563EB">' . $ouv . '</p></div></div>';
            echo '</div><div class="actions">';
            echo '<a class="btn" href="?p=caisse&s=nouvel">Encaisser un loyer</a>';
            echo '<a class="btn-line" href="?p=accueil&s=nouveau">Nouveau locataire</a>';
            echo '<a class="btn-line" href="?p=locataires&s=impayes" style="color:#e11d48;border-color:#e11d48">Relancer les impayés</a>';
            echo '</div><div class="card"><h2>Évolution des encaissements mensuels</h2><p class="muted">Année ' . $an . ' · FCFA</p>';
            echo '<svg class="chart-svg" viewBox="0 0 720 230" role="img" aria-label="Encaissements mensuels">';
            echo '<polyline fill="none" stroke="#E8650A" stroke-width="3" points="' . h(implode(' ', $pts)) . '"/>';
            $labs = ['Jan','Fév','Mar','Avr','Mai','Juin','Juil','Août','Sep','Oct','Nov','Déc'];
            for ($i = 1; $i <= 12; $i++) {
                $x = 36 + ($i - 1) * 58;
                echo '<text x="' . $x . '" y="214" font-size="11" fill="#6B7280" text-anchor="middle">' . $labs[$i - 1] . '</text>';
            }
            echo '</svg></div>';
        }
        if (plein()) {
            echo '<form method="post" class="card noprint"><input type="hidden" name="action" value="agence_vue"><input type="hidden" name="retour" value="' . h($p) . '"><label>Agence</label><select name="agence_id"><option value="0"' . (aid() === null ? ' selected' : '') . '>Toutes</option>';
            foreach (q('SELECT agence_id, nom FROM agences ORDER BY nom') as $a) {
                echo '<option value="' . (int) $a['agence_id'] . '"' . (aid() === (int) $a['agence_id'] ? ' selected' : '') . '>' . h($a['nom']) . '</option>';
            }
            echo '</select><button class="btn">Appliquer</button></form>';
        }
        if (can('rapport')) echo '<p class="noprint"><a class="btn" href="?p=rapports">Ouvrir le rapport</a></p>';
    }
    if ($p === 'accueil' && $s === 'agences' && plein()) {
        echo '<h1>Agences</h1><form method="post" class="card"><input type="hidden" name="action" value="agence_vue"><select name="agence_id"><option value="0">Toutes</option>';
        foreach (q('SELECT agence_id, nom FROM agences ORDER BY nom') as $a) echo '<option value="' . (int) $a['agence_id'] . '"' . (aid() === (int) $a['agence_id'] ? ' selected' : '') . '>' . h($a['nom']) . '</option>';
        echo '</select><button class="btn">Voir cette agence</button></form>';
        foreach (q('SELECT * FROM agences') as $a) echo '<div class="card"><img src="' . h($a['logo']) . '" alt="" style="height:48px"><strong>' . h($a['nom']) . '</strong><p>' . h($a['quartier'] . ' · ' . $a['adresse']) . '</p></div>';
    }
    if (($p === 'accueil' && in_array($s, ['encaisser'], true)) || ($p === 'locataires' && $s === 'encaisser') || ($p === 'caisse' && in_array($s, ['encaissements', 'nouvel'], true))) {
        echo '<h1>' . ($s === 'nouvel' ? 'Nouvel encaissement' : 'Encaisser un loyer') . '</h1><p class="muted">La référence est obligatoire si le mode n\'est pas Espèces. Le reçu RC-2026 n\'est jamais supprimé.</p><form method="post" class="card"><input type="hidden" name="action" value="encaisser"><label>Caisse</label><select name="caisse_id">';
        [$wc, $ac] = filtre('agence_id');
        foreach (q('SELECT * FROM caisses WHERE 1=1' . $wc, $ac) as $c) echo '<option value="' . (int) $c['caisse_id'] . '">' . h($c['nom']) . '</option>';
        echo '</select><label>Loyer</label><select name="loyer_id"><option value="0">Sans échéance</option>';
        foreach (q('SELECT l.loyer_id, l.montant, l.mois, l.annee, t.prenom, t.nom FROM loyers l JOIN contrats c ON c.contrat_id=l.contrat_id JOIN locataires t ON t.locataire_id=c.locataire_id WHERE l.statut<>"paye"' . str_replace('a.agence_id', 't.agence_id', $wa), $aa) as $l) {
            echo '<option value="' . (int) $l['loyer_id'] . '">' . h($l['prenom'] . ' ' . $l['nom'] . ' · ' . $l['mois'] . '/' . $l['annee'] . ' · ' . fcfa((float) $l['montant'])) . '</option>';
        }
        echo '</select><label>Montant</label><input name="montant" required><label>Mode</label><select name="mode"><option value="especes">Espèces</option><option value="orange_money">Orange Money</option><option value="mtn_momo">MTN MoMo</option><option value="moov_money">Moov Money</option><option value="wave">Wave</option><option value="cheque">Chèque</option><option value="virement">Virement</option></select><label>Référence transaction</label><input name="reference" placeholder="Obligatoire hors espèces"><label>Bénéficiaire</label><input name="benef"><button class="btn">Valider et créer le reçu</button></form>';
    }
    if ($p === 'accueil' && $s === 'nouveau') {
        echo '<h1>Nouveau locataire</h1>' . formLocataire();
    }
    if ($p === 'accueil' && in_array($s, ['relances', 'demandes', 'reclamations', 'activites'], true)) $pShow = $s;
    if ($p === 'locataires' && ($s === 'fiches' || $s === 'impayes')) {
        assurerColonnesCni();
        $fq = trim((string) ($_GET['q'] ?? ''));
        $fst = $s === 'impayes' ? 'impaye' : (string) ($_GET['statut'] ?? '');
        $fge = (string) ($_GET['genre'] ?? '');
        $editId = (int) ($_GET['edit'] ?? 0);
        echo '<div class="banner"><div><h1>Gestion des locataires</h1><p>Liste et gestion des locataires</p></div><a class="btn-new" href="?p=locataires&s=fiches&nouveau=1">+ Nouveau locataire</a></div>';
        echo '<form class="filtres" method="get"><input type="hidden" name="p" value="locataires"><input type="hidden" name="s" value="fiches"><input name="q" placeholder="Nom ou prénom" value="' . h($fq) . '">';
        echo '<select name="statut"><option value="">Tous statuts</option><option value="actif"' . ($fst === 'actif' ? ' selected' : '') . '>Actifs</option><option value="impaye"' . ($fst === 'impaye' ? ' selected' : '') . '>Impayés</option><option value="inactif"' . ($fst === 'inactif' ? ' selected' : '') . '>Inactifs</option></select>';
        echo '<select name="genre"><option value="">Tous sexes</option><option value="M"' . ($fge === 'M' ? ' selected' : '') . '>Homme</option><option value="F"' . ($fge === 'F' ? ' selected' : '') . '>Femme</option></select>';
        echo '<button class="btn-go" type="submit">Filtrer</button><a class="btn-clear" href="?p=locataires&s=fiches">Réinitialiser</a></form>';
        if (isset($_GET['nouveau'])) echo formLocataire();
        if ($editId) {
            $row = one('SELECT * FROM locataires WHERE locataire_id=?', [$editId]);
            if ($row) {
                assurerColonnesCni();
                $row = one('SELECT * FROM locataires WHERE locataire_id=?', [$editId]) ?: $row;
                $photos = q('SELECT nom, url FROM albums WHERE module="locataire" AND module_id=? AND statut="actif" ORDER BY album_id DESC', [$editId]);
                echo '<div class="card"><h2>Pièce d\'identité</h2>';
                echo '<p>N° ' . h((string) ($row['piece_identite_numero'] ?? '—')) . ' · ' . h((string) ($row['piece_identite_type'] ?? 'CNI'));
                if (!empty($row['date_delivrance_piece'])) echo ' · délivrée le ' . h((string) $row['date_delivrance_piece']);
                if (!empty($row['date_expiration_piece'])) echo ' · expire le ' . h((string) $row['date_expiration_piece']);
                echo '</p><div style="display:flex;gap:8px;flex-wrap:wrap">';
                foreach ($photos as $ph) echo '<figure style="margin:0"><img src="' . h((string) $ph['url']) . '" alt="' . h((string) $ph['nom']) . '" style="height:92px;width:140px;object-fit:cover;border-radius:8px"><figcaption class="muted">' . h((string) $ph['nom']) . '</figcaption></figure>';
                echo '</div></div>';
                echo '<form method="post" enctype="multipart/form-data" class="card"><input type="hidden" name="action" value="locataire_modifier"><input type="hidden" name="id" value="' . (int) $row['locataire_id'] . '">';
                echo '<label>Nom</label><input name="nom" required value="' . h((string) $row['nom']) . '"><label>Prénom</label><input name="prenom" required value="' . h((string) $row['prenom']) . '">';
                echo '<label>Téléphone</label><input name="telephone" value="' . h((string) $row['telephone']) . '"><label>Email</label><input name="email" value="' . h((string) $row['email']) . '">';
                echo '<label>Profession</label><input name="profession" value="' . h((string) $row['profession']) . '">';
                echo '<label>N° CNI / pièce d\'identité</label><input name="cni" value="' . h((string) ($row['piece_identite_numero'] ?? '')) . '" placeholder="Obligatoire pour une nouvelle fiche">';
                echo '<label>Délivrance</label><input type="date" name="delivrance" value="' . h((string) ($row['date_delivrance_piece'] ?? '')) . '">';
                echo '<label>Expiration</label><input type="date" name="expiration" value="' . h((string) ($row['date_expiration_piece'] ?? '')) . '">';
                echo '<label>Scan recto</label><input type="file" name="cni_recto" accept="image/*"><label>Scan verso (optionnel)</label><input type="file" name="cni_verso" accept="image/*">';
                echo '<label>Sexe</label><select name="genre"><option value="">—</option><option value="M"' . (($row['genre'] ?? '') === 'M' ? ' selected' : '') . '>Homme</option><option value="F"' . (($row['genre'] ?? '') === 'F' ? ' selected' : '') . '>Femme</option></select>';
                echo '<label>Statut</label><select name="statut"><option value="actif"' . ($row['statut'] === 'actif' ? ' selected' : '') . '>Actif</option><option value="inactif"' . ($row['statut'] === 'inactif' ? ' selected' : '') . '>Inactif</option></select>';
                echo '<button class="btn">Enregistrer</button></form>';
            }
        }
        $sql = 'SELECT t.*, (SELECT c.contrat_id FROM contrats c WHERE c.locataire_id=t.locataire_id AND c.statut="actif" LIMIT 1) contrat_id FROM locataires t WHERE 1=1' . str_replace('a.', 't.', $wa);
        $params = $aa;
        if ($fq !== '') {
            $sql .= ' AND (t.nom LIKE ? OR t.prenom LIKE ? OR t.telephone LIKE ? OR t.profession LIKE ?)';
            $like = '%' . $fq . '%';
            array_push($params, $like, $like, $like, $like);
        }
        if ($fst === 'actif' || $fst === 'inactif') { $sql .= ' AND t.statut=?'; $params[] = $fst; }
        if ($fst === 'impaye') $sql .= ' AND EXISTS (SELECT 1 FROM contrats cimp JOIN loyers limp ON limp.contrat_id=cimp.contrat_id WHERE cimp.locataire_id=t.locataire_id AND limp.date_echeance < CURDATE() AND limp.statut <> "paye")';
        if ($fge === 'M' || $fge === 'F') { $sql .= ' AND t.genre=?'; $params[] = $fge; }
        $sql .= ' ORDER BY t.nom, t.prenom';
        echo '<div class="table-wrap"><table class="pro"><thead><tr><th>Code</th><th>Nom & prénom</th><th>Sexe</th><th>Profession</th><th>Téléphone</th><th>Bail</th><th>Statut</th><th>Actions</th></tr></thead><tbody>';
        $rowsL = q($sql, $params);
        $miniatures = [];
        foreach (q('SELECT module_id, url FROM albums WHERE module="locataire" AND nom="CNI recto" AND statut="actif" ORDER BY album_id') as $ph) {
            $miniatures[(int) $ph['module_id']] = (string) $ph['url'];
        }
        if (!$rowsL) echo '<tr><td colspan="8">Aucun locataire ne correspond.</td></tr>';
        foreach ($rowsL as $t) {
            $mini = $miniatures[(int) $t['locataire_id']] ?? '';
            $vignette = $mini !== '' ? '<img src="' . h($mini) . '" alt="" style="height:28px;width:28px;object-fit:cover;border-radius:6px;vertical-align:middle;margin-right:6px">' : '';
            $cni = trim((string) ($t['piece_identite_numero'] ?? ''));
            echo '<tr><td>' . (int) $t['locataire_id'] . '</td><td>' . $vignette . '<strong>' . h(trim($t['prenom'] . ' ' . $t['nom'])) . '</strong>' . ($cni !== '' ? '<div class="muted">CNI ' . h($cni) . '</div>' : '') . '</td><td>' . sexeIco($t['genre'] ?? null) . '</td><td>' . h((string) $t['profession']) . '</td><td>' . h((string) $t['telephone']) . '</td><td>' . ($t['contrat_id'] ? '<a href="?p=contrat&id=' . (int) $t['contrat_id'] . '">Bail #' . (int) $t['contrat_id'] . '</a>' : 'Sans bail') . '</td><td>' . badgePersonne((string) $t['statut']) . '</td><td><span class="act"><a class="edit" href="?p=locataires&s=fiches&edit=' . (int) $t['locataire_id'] . '" aria-label="Modifier">✎</a><form method="post" onsubmit="return confirm(\'Supprimer ce locataire ?\');"><input type="hidden" name="action" value="locataire_supprimer"><input type="hidden" name="id" value="' . (int) $t['locataire_id'] . '"><button class="del" type="submit" aria-label="Supprimer">✕</button></form></span></td></tr>';
        }
        echo '</tbody></table></div>';
    }
    if ($p === 'locataires' && $s === 'contrats') {
        echo '<div class="banner"><div><h1>Contrats</h1><p>Durée, dates, fréquence, caution, avance, préavis, statut</p></div></div>';
        echo '<form method="post" class="card"><input type="hidden" name="action" value="contrat_nouveau"><label>Locataire</label><select name="locataire_id">';
        foreach (q('SELECT locataire_id, prenom, nom FROM locataires WHERE 1=1' . str_replace('a.', '', $wa), $aa) as $t) echo '<option value="' . (int) $t['locataire_id'] . '">' . h($t['prenom'] . ' ' . $t['nom']) . '</option>';
        echo '</select><label>Local libre</label><select name="local_id">';
        foreach (q('SELECT l.local_id, l.nom, b.nom bien FROM locaux l JOIN biens b ON b.bien_id=l.bien_id WHERE l.statut="libre"' . str_replace('a.', 'b.', $wa), $aa) as $l) echo '<option value="' . (int) $l['local_id'] . '">' . h($l['bien'] . ' · ' . $l['nom']) . '</option>';
        echo '</select><label>Début</label><input type="date" name="date_debut" required><label>Fin</label><input type="date" name="date_fin"><label>Durée (mois)</label><input name="duree" inputmode="numeric"><label>Fréquence</label><select name="frequence"><option value="mensuel">Mensuel</option><option value="trimestriel">Trimestriel</option><option value="hebdomadaire">Hebdomadaire</option><option value="annuel">Annuel</option></select><label>Loyer</label><input name="loyer_nu" required><label>Caution</label><input name="caution"><label>Avance</label><input name="avance"><label>Préavis</label><input type="date" name="date_preavis"><label>Statut</label><select name="statut"><option value="actif">Actif</option><option value="provisoire">Provisoire</option><option value="inactif">Inactif</option></select><label>Type</label><select name="type_contrat"><option value="a_echoir">À échoir</option><option value="echu">Échu</option></select><button class="btn">Créer le contrat</button></form>';
        echo '<div class="table-wrap"><table class="pro"><thead><tr><th>Référence</th><th>Locataire</th><th>Durée</th><th>Début</th><th>Fin</th><th>Fréquence</th><th>Caution</th><th>Avance</th><th>Préavis</th><th>Statut</th></tr></thead><tbody>';
        $rowsC = q('SELECT c.*, t.prenom, t.nom, l.nom local_nom, b.nom bien FROM contrats c JOIN locataires t ON t.locataire_id=c.locataire_id JOIN locaux l ON l.local_id=c.local_id JOIN biens b ON b.bien_id=l.bien_id WHERE 1=1' . str_replace('a.', 'b.', $wa), $aa);
        if (!$rowsC) echo '<tr><td colspan="10">Aucun contrat.</td></tr>';
        foreach ($rowsC as $c) {
            echo '<tr><td><a href="?p=contrat&id=' . (int) $c['contrat_id'] . '">Bail #' . (int) $c['contrat_id'] . '</a></td><td>' . h($c['prenom'] . ' ' . $c['nom']) . '<div class="muted">' . h($c['bien'] . ' ' . $c['local_nom']) . '</div></td><td>' . (int) $c['duree'] . ' mois</td><td>' . h((string) $c['date_debut']) . '</td><td>' . h((string) $c['date_fin']) . '</td><td>' . h((string) $c['frequence']) . '</td><td>' . fcfa((float) $c['caution']) . '</td><td>' . fcfa((float) $c['avance']) . '</td><td>' . h($c['date_preavis'] ?: '3 mois') . '</td><td>' . h((string) $c['statut']) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
    if ($p === 'locataires' && $s === 'locaux') {
        echo '<h1>Locaux loués</h1><table>';
        foreach (q('SELECT l.*, b.nom bien FROM locaux l JOIN biens b ON b.bien_id=l.bien_id WHERE l.statut="occupe"' . str_replace('a.', 'b.', $wa), $aa) as $l) {
            echo '<tr><td>' . h($l['bien'] . ' · ' . $l['nom']) . '<div class="muted">' . (int) $l['nombre_pieces'] . ' p. · ' . h((string) $l['surface']) . ' m²</div></td><td>' . fcfa((float) $l['prix_loyer']) . '</td></tr>';
        }
        echo '</table>';
    }
    if ($p === 'locataires' && $s === 'echeancier') {
        echo '<div class="banner"><div><h1>Échéancier</h1><p>Échéances et historique des paiements, liés aux reçus</p></div></div>';
        echo '<div class="table-wrap"><table class="pro"><thead><tr><th>Locataire</th><th>Période</th><th>Échéance</th><th>Montant</th><th>Statut</th><th>Reçu</th></tr></thead><tbody>';
        foreach (q('SELECT l.*, t.prenom, t.nom, (SELECT tr.numero_recu FROM transactions tr WHERE tr.loyer_id=l.loyer_id AND tr.statut<>"annule" ORDER BY tr.transaction_id DESC LIMIT 1) numero_recu, (SELECT r.recu_id FROM transactions tr JOIN recus r ON r.transaction_id=tr.transaction_id WHERE tr.loyer_id=l.loyer_id AND tr.statut<>"annule" ORDER BY tr.transaction_id DESC LIMIT 1) recu_id FROM loyers l JOIN contrats c ON c.contrat_id=l.contrat_id JOIN locataires t ON t.locataire_id=c.locataire_id WHERE 1=1' . str_replace('a.', 't.', $wa) . ' ORDER BY l.annee DESC, l.mois DESC', $aa) as $l) {
            $rec = $l['recu_id'] ? '<a href="?p=recu&id=' . (int) $l['recu_id'] . '">' . h((string) $l['numero_recu']) . '</a>' : '—';
            echo '<tr><td>' . h($l['prenom'] . ' ' . $l['nom']) . '</td><td>' . (int) $l['mois'] . '/' . (int) $l['annee'] . '</td><td>' . h((string) $l['date_echeance']) . '</td><td>' . fcfa((float) $l['montant']) . '</td><td>' . h((string) $l['statut']) . '</td><td>' . $rec . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
    if ($p === 'locataires' && $s === 'edl') {
        echo '<div class="banner"><div><h1>États des lieux</h1><p>Entrée et sortie, observations</p></div></div>';
        echo '<form method="post" class="card"><input type="hidden" name="action" value="edl_nouveau"><label>Contrat</label><select name="contrat_id" required>';
        foreach (q('SELECT c.contrat_id, t.prenom, t.nom FROM contrats c JOIN locataires t ON t.locataire_id=c.locataire_id JOIN locaux l ON l.local_id=c.local_id JOIN biens b ON b.bien_id=l.bien_id WHERE 1=1' . str_replace('a.', 'b.', $wa), $aa) as $c) echo '<option value="' . (int) $c['contrat_id'] . '">Bail #' . (int) $c['contrat_id'] . ' · ' . h($c['prenom'] . ' ' . $c['nom']) . '</option>';
        echo '</select><label>Type</label><select name="type_etat"><option value="entree">Entrée</option><option value="sortie">Sortie</option></select><label>Date</label><input type="date" name="date_etat" value="' . date('Y-m-d') . '"><label>Observations</label><input name="observations" required><button class="btn">Enregistrer</button></form>';
        echo '<div class="table-wrap"><table class="pro"><thead><tr><th>Date</th><th>Type</th><th>Bail</th><th>Observations</th></tr></thead><tbody>';
        $eds = q('SELECT e.*, c.contrat_id, t.prenom, t.nom tn FROM etats_lieux e JOIN contrats c ON c.contrat_id=e.contrat_id JOIN locataires t ON t.locataire_id=c.locataire_id JOIN locaux l ON l.local_id=c.local_id JOIN biens b ON b.bien_id=l.bien_id WHERE 1=1' . str_replace('a.', 'b.', $wa), $aa);
        if (!$eds) echo '<tr><td colspan="4">Aucun état des lieux.</td></tr>';
        foreach ($eds as $e) echo '<tr><td>' . h((string) $e['date_etat']) . '</td><td>' . h((string) $e['type_etat']) . '</td><td>Bail #' . (int) $e['contrat_id'] . '<div class="muted">' . h($e['prenom'] . ' ' . $e['tn']) . '</div></td><td>' . h((string) $e['observations']) . '</td></tr>';
        echo '</tbody></table></div>';
    }
    if (($p === 'locataires' && $s === 'reclamations') || ($p === 'accueil' && $s === 'reclamations')) {
        echo '<h1>Réclamations</h1><ul>';
        foreach (q('SELECT * FROM reclamations ORDER BY date_reclamation DESC') as $r) echo '<li><strong>' . h($r['nom']) . '</strong> · ' . h($r['observation']) . ' · ' . h($r['statut']) . '</li>';
        echo '</ul>';
    }
    if (($p === 'locataires' && $s === 'relances') || ($p === 'accueil' && $s === 'relances')) {
        echo '<h1>Relances</h1><ul>';
        foreach (q('SELECT r.*, t.prenom, t.nom FROM relances r JOIN loyers l ON l.loyer_id=r.loyer_id JOIN contrats c ON c.contrat_id=l.contrat_id JOIN locataires t ON t.locataire_id=c.locataire_id') as $r) echo '<li>' . h($r['canal'] . ' · ' . $r['prenom'] . ' ' . $r['nom']) . '<div class="muted">' . h($r['message']) . '</div></li>';
        echo '</ul>';
    }
    if ($p === 'locataires' && $s === 'penalites') {
        $taux = one('SELECT valeur FROM parametres WHERE cle="penalite_taux" ORDER BY agence_id IS NULL DESC LIMIT 1');
        echo '<h1>Pénalités</h1><p>Taux : ' . h($taux['valeur'] ?? '10') . ' % après le délai de grâce.</p>';
    }
    if ($p === 'locataires' && $s === 'caution') {
        echo '<h1>Cautions</h1><table>';
        foreach (q('SELECT * FROM restitutions_caution') as $r) echo '<tr><td>Bail #' . (int) $r['contrat_id'] . '</td><td>' . fcfa((float) $r['montant_rendu']) . ' · ' . h($r['statut']) . '</td></tr>';
        echo '</table>';
    }
    if ($p === 'proprietaires' && $s === 'fiches') {
        $fq = trim((string) ($_GET['q'] ?? ''));
        $fst = (string) ($_GET['statut'] ?? '');
        $fge = (string) ($_GET['genre'] ?? '');
        $editId = (int) ($_GET['edit'] ?? 0);
        echo '<div class="banner"><div><h1>Gestion des propriétaires</h1><p>Liste et gestion des propriétaires</p></div><a class="btn-new" href="?p=proprietaires&s=fiches&nouveau=1">+ Nouveau propriétaire</a></div>';
        echo '<form class="filtres" method="get"><input type="hidden" name="p" value="proprietaires"><input type="hidden" name="s" value="fiches"><input name="q" placeholder="Nom ou prénom" value="' . h($fq) . '">';
        echo '<select name="statut"><option value="">Tous statuts</option><option value="actif"' . ($fst === 'actif' ? ' selected' : '') . '>Actifs</option><option value="inactif"' . ($fst === 'inactif' ? ' selected' : '') . '>Inactifs</option></select>';
        echo '<select name="genre"><option value="">Tous sexes</option><option value="M"' . ($fge === 'M' ? ' selected' : '') . '>Homme</option><option value="F"' . ($fge === 'F' ? ' selected' : '') . '>Femme</option></select>';
        echo '<button class="btn-go" type="submit">Filtrer</button><a class="btn-clear" href="?p=proprietaires&s=fiches">Réinitialiser</a></form>';
        if (isset($_GET['nouveau'])) {
            echo '<form method="post" class="card"><input type="hidden" name="action" value="proprio_nouveau"><label>Nom</label><input name="nom" required><label>Prénom</label><input name="prenom"><label>Téléphone</label><input name="telephone"><label>Email</label><input name="email"><label>Sexe</label><select name="genre"><option value="">—</option><option value="M">Homme</option><option value="F">Femme</option></select><label>Commission %</label><input name="taux" value="10"><label>N° mandat</label><input name="mandat"><button class="btn">Enregistrer</button></form>';
        }
        if ($editId) {
            $row = one('SELECT * FROM proprietaires WHERE proprietaire_id=?', [$editId]);
            if ($row) {
                echo '<form method="post" class="card"><input type="hidden" name="action" value="proprio_modifier"><input type="hidden" name="id" value="' . (int) $row['proprietaire_id'] . '"><label>Nom</label><input name="nom" required value="' . h((string) $row['nom']) . '"><label>Prénom</label><input name="prenom" value="' . h((string) $row['prenom']) . '"><label>Téléphone</label><input name="telephone" value="' . h((string) $row['telephone']) . '"><label>Email</label><input name="email" value="' . h((string) $row['email']) . '"><label>Sexe</label><select name="genre"><option value="">—</option><option value="M"' . (($row['genre'] ?? '') === 'M' ? ' selected' : '') . '>Homme</option><option value="F"' . (($row['genre'] ?? '') === 'F' ? ' selected' : '') . '>Femme</option></select><label>Commission %</label><input name="taux" value="' . h((string) $row['taux_commission']) . '"><label>N° mandat</label><input name="mandat" value="' . h((string) $row['numero_mandat']) . '"><label>Statut</label><select name="statut"><option value="actif"' . ($row['statut'] === 'actif' ? ' selected' : '') . '>Actif</option><option value="inactif"' . ($row['statut'] === 'inactif' ? ' selected' : '') . '>Inactif</option></select><button class="btn">Enregistrer</button></form>';
            }
        }
        $sql = 'SELECT p.*, (SELECT COUNT(*) FROM biens b WHERE b.proprietaire_id=p.proprietaire_id) nb FROM proprietaires p WHERE 1=1' . str_replace('a.', 'p.', $wa);
        $params = $aa;
        if ($fq !== '') {
            $sql .= ' AND (p.nom LIKE ? OR p.prenom LIKE ? OR p.telephone LIKE ?)';
            $like = '%' . $fq . '%';
            array_push($params, $like, $like, $like);
        }
        if ($fst === 'actif' || $fst === 'inactif') { $sql .= ' AND p.statut=?'; $params[] = $fst; }
        if ($fge === 'M' || $fge === 'F') { $sql .= ' AND p.genre=?'; $params[] = $fge; }
        $sql .= ' ORDER BY p.nom, p.prenom';
        echo '<div class="table-wrap"><table class="pro"><thead><tr><th>Code</th><th>Nom & prénom</th><th>Sexe</th><th>Téléphone</th><th>Biens</th><th>Mandat</th><th>Statut</th><th>Actions</th></tr></thead><tbody>';
        $rowsP = q($sql, $params);
        if (!$rowsP) echo '<tr><td colspan="8">Aucun propriétaire ne correspond.</td></tr>';
        foreach ($rowsP as $pr) {
            echo '<tr><td>' . (int) $pr['proprietaire_id'] . '</td><td><strong>' . h(trim($pr['prenom'] . ' ' . $pr['nom'])) . '</strong></td><td>' . sexeIco($pr['genre'] ?? null) . '</td><td>' . h((string) $pr['telephone']) . '</td><td>' . (int) $pr['nb'] . '</td><td>' . h((string) $pr['numero_mandat']) . '</td><td>' . badgePersonne((string) $pr['statut']) . '</td><td><span class="act"><a class="edit" href="?p=proprietaires&s=fiches&edit=' . (int) $pr['proprietaire_id'] . '" aria-label="Modifier">✎</a><form method="post" onsubmit="return confirm(\'Supprimer ce propriétaire ?\');"><input type="hidden" name="action" value="proprio_supprimer"><input type="hidden" name="id" value="' . (int) $pr['proprietaire_id'] . '"><button class="del" type="submit" aria-label="Supprimer">✕</button></form></span></td></tr>';
        }
        echo '</tbody></table></div>';
        echo '<h2 style="margin:18px 0 8px">Biens</h2><form method="post" enctype="multipart/form-data" class="card"><input type="hidden" name="action" value="bien_nouveau"><label>Propriétaire</label><select name="proprietaire_id">';
        foreach (q('SELECT proprietaire_id, prenom, nom FROM proprietaires WHERE 1=1' . str_replace('a.', '', $wa), $aa) as $pr) echo '<option value="' . (int) $pr['proprietaire_id'] . '">' . h($pr['prenom'] . ' ' . $pr['nom']) . '</option>';
        echo '</select><label>Nom du bien</label><input name="nom" required><label>Quartier</label><input name="quartier"><label>Adresse</label><input name="adresse"><label>Latitude</label><input name="latitude"><label>Longitude</label><input name="longitude"><label>Photo</label><input type="file" name="photo" accept="image/*"><label>Premier local</label><input name="local_nom"><label>Pièces</label><input name="nombre_pieces"><label>Loyer</label><input name="prix_loyer"><button class="btn">Ajouter le bien</button></form>';
        echo '<div class="table-wrap"><table class="pro"><thead><tr><th>Bien</th><th>Propriétaire</th><th>Quartier</th><th>Statut</th></tr></thead><tbody>';
        foreach (q('SELECT b.*, p.prenom, p.nom pn FROM biens b JOIN proprietaires p ON p.proprietaire_id=b.proprietaire_id WHERE 1=1' . str_replace('a.', 'b.', $wa), $aa) as $b) {
            echo '<tr><td><a href="?p=bien&id=' . (int) $b['bien_id'] . '">' . h($b['nom']) . '</a></td><td>' . h($b['prenom'] . ' ' . $b['pn']) . '</td><td>' . h((string) $b['quartier']) . '</td><td>' . badgePersonne((string) $b['statut']) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
    if ($p === 'proprietaires' && $s === 'mandats') {
        echo '<div class="banner"><div><h1>Mandats</h1><p>Numéro, dates et commission de gestion</p></div></div>';
        echo '<form method="post" class="card"><input type="hidden" name="action" value="mandat_modifier"><label>Propriétaire</label><select name="id" required>';
        foreach (q('SELECT proprietaire_id, prenom, nom, numero_mandat, taux_commission FROM proprietaires WHERE 1=1' . str_replace('a.', '', $wa), $aa) as $pr) echo '<option value="' . (int) $pr['proprietaire_id'] . '">' . h($pr['prenom'] . ' ' . $pr['nom']) . '</option>';
        echo '</select><label>N° mandat</label><input name="mandat" required><label>Commission %</label><input name="taux" value="10"><label>Début</label><input type="date" name="debut"><label>Fin</label><input type="date" name="fin"><button class="btn">Enregistrer</button></form>';
        echo '<div class="table-wrap"><table class="pro"><thead><tr><th>Propriétaire</th><th>Mandat</th><th>Début</th><th>Fin</th><th>Commission</th></tr></thead><tbody>';
        foreach (q('SELECT * FROM proprietaires WHERE 1=1' . str_replace('a.', '', $wa), $aa) as $pr) echo '<tr><td>' . h($pr['prenom'] . ' ' . $pr['nom']) . '</td><td>' . h((string) $pr['numero_mandat']) . '</td><td>' . h((string) $pr['date_debut_mandat']) . '</td><td>' . h((string) $pr['date_fin_mandat']) . '</td><td>' . h((string) $pr['taux_commission']) . ' %</td></tr>';
        echo '</tbody></table></div>';
    }
    if ($p === 'proprietaires' && $s === 'loyers') {
        echo '<h1>Loyers encaissés</h1><ul>';
        foreach (q('SELECT * FROM transactions WHERE type_transaction="entree" AND categorie="loyer" ORDER BY date_transaction DESC LIMIT 30') as $t) echo '<li>' . h($t['date_transaction'] . ' · ' . $t['objet']) . ' · ' . fcfa((float) $t['montant']) . '</li>';
        echo '</ul>';
    }
    if ($p === 'proprietaires' && ($s === 'reversements' || $s === 'releves')) {
        $mois = (int) ($_GET['mois'] ?? date('n'));
        $annee = (int) ($_GET['annee'] ?? date('Y'));
        if ($mois < 1 || $mois > 12) $mois = (int) date('n');
        $titre = $s === 'releves' ? 'Relevé mensuel' : 'Reversements';
        echo '<div class="banner"><div><h1>' . $titre . '</h1><p>Brut, commission, dépenses, net</p></div></div>';
        echo '<form class="filtres" method="get"><input type="hidden" name="p" value="proprietaires"><input type="hidden" name="s" value="' . h($s) . '"><select name="mois">';
        for ($i = 1; $i <= 12; $i++) echo '<option value="' . $i . '"' . ($i === $mois ? ' selected' : '') . '>' . $i . '</option>';
        echo '</select><select name="annee">';
        foreach ([$annee - 1, $annee, $annee + 1] as $y) echo '<option value="' . $y . '"' . ($y === $annee ? ' selected' : '') . '>' . $y . '</option>';
        echo '</select><button class="btn-go" type="submit">Voir</button></form>';
        $lignes = lignesReversement($mois, $annee, $wa, $aa);
        $bid = (int) ($_GET['bordereau'] ?? 0);
        echo '<div class="table-wrap"><table class="pro"><thead><tr><th>Propriétaire</th><th>Brut</th><th>Commission</th><th>Dépenses</th><th>Net</th><th></th></tr></thead><tbody>';
        foreach ($lignes as $l) {
            echo '<tr><td>' . h($l['prenom'] . ' ' . $l['nom']) . '</td><td>' . fcfa($l['brut']) . '</td><td>' . fcfa($l['commission']) . ' · ' . h((string) $l['taux']) . ' %</td><td>' . fcfa($l['depenses']) . '</td><td><strong>' . fcfa($l['net']) . '</strong></td><td><a href="?p=proprietaires&s=' . h($s) . '&mois=' . $mois . '&annee=' . $annee . '&bordereau=' . (int) $l['proprietaire_id'] . '">Bordereau</a></td></tr>';
        }
        echo '</tbody></table></div>';
        if ($s === 'releves') {
            echo '<div class="paper" id="doc-print"><p class="muted">ROUAHIMO · ' . $mois . '/' . $annee . '</p><h2>Relevé mensuel</h2>';
            foreach ($lignes as $l) echo '<p>' . h($l['prenom'] . ' ' . $l['nom']) . ' · brut ' . fcfa($l['brut']) . ' · commission ' . fcfa($l['commission']) . ' · dépenses ' . fcfa($l['depenses']) . ' · net ' . fcfa($l['net']) . '</p>';
            echo '<p class="noprint"><button class="btn" onclick="print()">Imprimer le relevé</button></p></div>';
        }
        foreach ($lignes as $l) {
            if ((int) $l['proprietaire_id'] !== $bid) continue;
            echo '<div class="paper" id="doc-print"><p style="font-weight:800;color:#E8650A">ROUAHIMO</p><h1>Reçu de reversement</h1><p>RV-' . $annee . '-' . str_pad((string) $mois, 2, '0', STR_PAD_LEFT) . '-' . (int) $l['proprietaire_id'] . '</p>';
            echo '<p><strong>' . h($l['prenom'] . ' ' . $l['nom']) . '</strong><br>Mandat ' . h((string) $l['numero_mandat']) . ' · commission ' . h((string) $l['taux']) . ' %</p>';
            echo '<p>Loyers encaissés (brut) ' . fcfa($l['brut']) . '<br>Commission ' . fcfa($l['commission']) . '<br>Dépenses sur les biens ' . fcfa($l['depenses']) . '</p>';
            echo '<p style="font-size:28px;font-weight:800">Net ' . fcfa($l['net']) . '</p>';
            echo '<p class="noprint"><button class="btn" onclick="print()">Imprimer le bordereau</button></p></div>';
        }
    }
    if ($p === 'proprietaires' && $s === 'depenses') {
        echo '<div class="banner"><div><h1>Dépenses sur les biens</h1><p>Travaux et charges déduits du reversement</p></div></div>';
        echo '<form method="post" class="card"><input type="hidden" name="action" value="depense_nouveau"><label>Bien</label><select name="bien_id" required>';
        foreach (q('SELECT b.bien_id, b.nom FROM biens b WHERE 1=1' . str_replace('a.', 'b.', $wa), $aa) as $b) echo '<option value="' . (int) $b['bien_id'] . '">' . h($b['nom']) . '</option>';
        echo '</select><label>Libellé</label><input name="libelle" required><label>Montant</label><input name="montant" required><label>Date</label><input type="date" name="date_depense" value="' . date('Y-m-d') . '"><button class="btn">Ajouter</button></form>';
        echo '<div class="table-wrap"><table class="pro"><thead><tr><th>Date</th><th>Bien</th><th>Libellé</th><th>Montant</th></tr></thead><tbody>';
        $deps = q('SELECT d.*, b.nom bien FROM depenses d LEFT JOIN biens b ON b.bien_id=d.bien_id WHERE 1=1' . str_replace('a.', 'd.', $wa), $aa);
        if (!$deps) echo '<tr><td colspan="4">Aucune dépense.</td></tr>';
        foreach ($deps as $d) echo '<tr><td>' . h((string) $d['date_depense']) . '</td><td>' . h((string) $d['bien']) . '</td><td>' . h((string) $d['libelle']) . '</td><td>' . fcfa((float) $d['montant']) . '</td></tr>';
        echo '</tbody></table></div>';
    }
    if ($p === 'caisse' && ($s === 'ouverture' || $s === '')) {
        [$wc, $ac] = filtre('agence_id');
        $caisses = q('SELECT * FROM caisses WHERE statut = "actif"' . $wc . ' ORDER BY caisse_id', $ac);
        $lignes = [];
        $jourRef = null;
        $entrees = 0.0;
        $sorties = 0.0;
        $solde = 0.0;
        $nbOuv = 0;
        foreach ($caisses as $c) {
            $ouv = one('SELECT j.*, u.prenom, u.nom FROM journees_caisses j LEFT JOIN utilisateurs u ON u.utilisateur_id = j.utilisateur_id_ouverture WHERE j.caisse_id = ? AND j.statut = "ouverte" ORDER BY j.journee_id DESC LIMIT 1', [(int) $c['caisse_id']]);
            $mvs = q('SELECT heure, objet, montant, type_transaction FROM transactions WHERE caisse_id = ? ORDER BY transaction_id DESC LIMIT 3', [(int) $c['caisse_id']]);
            $est = (bool) $ouv;
            if ($est) {
                $nbOuv++;
                $entrees += (float) $ouv['total_entrees'];
                $sorties += (float) $ouv['total_sorties'];
                if (!$jourRef) $jourRef = $ouv;
            }
            $solde += (float) $c['solde'];
            $lignes[] = ['c' => $c, 'ouv' => $ouv, 'mvs' => $mvs, 'est' => $est];
        }
        $jourOn = $nbOuv > 0;
        $dateJ = $jourRef ? date('d/m/Y', strtotime((string) $jourRef['date_ouverture'])) : '—';
        $heureJ = $jourRef && $jourRef['heure_ouverture'] ? substr((string) $jourRef['heure_ouverture'], 0, 8) : '—';
        $qui = $jourRef ? trim(($jourRef['prenom'] ?? '') . ' ' . ($jourRef['nom'] ?? '')) : '—';
        echo '<div class="caisse-page"><header class="caisse-head"><div><h1>Ouverture de caisse</h1><p>Ouverture et fermeture de caisse</p></div><div class="caisse-actions">';
        echo '<span class="pill-jour' . ($jourOn ? ' on' : '') . '"><i class="dot"></i> Journée ' . ($jourOn ? 'ouverte' : 'fermée') . '</span>';
        echo '<a class="btn-retour" href="?p=accueil">← Retour</a></div></header><div class="caisse-board"><div>';
        echo '<section class="caisse-card"><h2>1. État de la journée</h2><div class="jour-grid">';
        echo '<div><span>Statut</span><strong class="' . ($jourOn ? 'etat-ok' : 'etat-off') . '">' . ($jourOn ? 'OUVERTE' : 'FERMÉE') . '</strong></div>';
        echo '<div><span>Date de la journée</span><strong>' . h($dateJ) . '</strong></div>';
        echo '<div><span>Heure d\'ouverture</span><strong>' . h($heureJ) . '</strong></div>';
        echo '<div><span>Ouverte par</span><strong>' . h($qui !== '' ? $qui : '—') . '</strong></div></div></section>';
        echo '<section class="caisse-card"><h2>2. Caisses de l\'agence (' . count($lignes) . ' caisses)</h2><div class="caisse-duo">';
        foreach ($lignes as $L) {
            $c = $L['c'];
            $ouv = $L['ouv'];
            echo '<article class="till"><div class="till-top"><div><h3>' . h(function_exists('mb_strtoupper') ? mb_strtoupper((string) $c['nom']) : strtoupper((string) $c['nom'])) . '</h3><div class="till-code">CAISSE-' . str_pad((string) $c['caisse_id'], 3, '0', STR_PAD_LEFT) . '</div></div>';
            echo '<span class="badge ' . ($L['est'] ? 'ok' : 'off') . '">' . ($L['est'] ? 'Ouverte' : 'Fermée') . '</span></div>';
            echo '<div class="kv"><span>Solde actuel</span><b>' . fcfaPlein((float) $c['solde']) . '</b></div>';
            echo '<div class="kv"><span>Plafond maximum</span><b>' . fcfaPlein((float) $c['plafond']) . '</b></div>';
            echo '<div class="kv"><span>+ Entrées</span><b class="plus">' . fcfaPlein($ouv ? (float) $ouv['total_entrees'] : 0) . '</b></div>';
            echo '<div class="kv"><span>− Sorties</span><b class="moins">' . fcfaPlein($ouv ? (float) $ouv['total_sorties'] : 0) . '</b></div>';
            echo '<div class="kv"><span>Opérations</span><b>' . (int) ($ouv ? ((int) $ouv['nombre_entrees'] + (int) $ouv['nombre_sorties']) : 0) . '</b></div>';
            echo '<div class="till-moves"><p class="muted">Derniers mouvements</p>';
            if (!$L['mvs']) echo '<div class="move"><span>Aucun mouvement</span></div>';
            foreach ($L['mvs'] as $mv) {
                $signe = ($mv['type_transaction'] ?? '') === 'sortie' ? '−' : '+';
                $cls = $signe === '−' ? 'moins' : 'plus';
                echo '<div class="move"><span>' . h(substr((string) $mv['heure'], 0, 5) . ' ' . $mv['objet']) . '</span><b class="' . $cls . '">' . $signe . ' ' . number_format((float) $mv['montant'], 0, ',', ' ') . '</b></div>';
            }
            echo '</div>';
            if ($L['est']) {
                echo '<form method="post"><input type="hidden" name="action" value="caisse_cloture"><input type="hidden" name="journee_id" value="' . (int) $ouv['journee_id'] . '">';
                echo '<label class="fond-label">Solde compté<input name="solde_physique" inputmode="numeric" value="' . h((string) (float) $ouv['solde_theorique']) . '" required></label>';
                echo '<div class="till-actions"><button class="btn-fermer" type="submit">Fermer</button><a class="btn-hist" href="?p=caisse&s=journal" title="Historique">⏱</a></div></form>';
            } else {
                echo '<form method="post"><input type="hidden" name="action" value="caisse_ouvrir"><input type="hidden" name="caisse_id" value="' . (int) $c['caisse_id'] . '"><input type="hidden" name="fond_initial" value="' . h((string) (float) $c['solde']) . '">';
                echo '<div class="till-actions"><button class="btn-ouvrir" type="submit">Ouvrir</button><a class="btn-hist" href="?p=caisse&s=journal" title="Historique">⏱</a></div></form>';
            }
            echo '</article>';
        }
        echo '</div></section></div>';
        echo '<aside class="caisse-side"><h2>Synthèse caisses</h2><p class="muted">Résumé analytique des caisses de l\'agence.</p>';
        echo '<div class="kv"><span>Statut</span><b class="' . ($jourOn ? 'plus' : '') . '">' . ($jourOn ? 'Journée ouverte' : 'Journée fermée') . '</b></div>';
        echo '<div class="kv"><span>Date</span><b>' . h($dateJ) . '</b></div>';
        echo '<div class="kv"><span>+ Entrées totales</span><b class="plus">+ ' . fcfaPlein($entrees) . '</b></div>';
        echo '<div class="kv"><span>− Sorties totales</span><b class="moins">− ' . fcfaPlein($sorties) . '</b></div>';
        echo '<p class="syn-solde">SOLDE THÉORIQUE</p><p class="syn-montant">' . fcfaPlein($solde) . '</p>';
        echo '<div class="syn-counts"><div><strong>' . $nbOuv . '</strong><span class="muted">Ouvertes</span></div><div><strong>' . (count($lignes) - $nbOuv) . '</strong><span class="muted">Fermées</span></div></div></aside>';
        echo '</div></div>';
    }
    if ($p === 'caisse' && $s === 'sorties') {
        echo '<h1>Sorties</h1><form method="post" class="card"><input type="hidden" name="action" value="caisse_sortie"><label>Caisse</label><select name="caisse_id">';
        [$wc, $ac] = filtre('agence_id');
        foreach (q('SELECT * FROM caisses WHERE 1=1' . $wc, $ac) as $c) echo '<option value="' . (int) $c['caisse_id'] . '">' . h($c['nom']) . '</option>';
        echo '</select><label>Montant</label><input name="montant" required><label>Objet</label><input name="objet" required><button class="btn">Décaisser</button></form>';
    }
    if ($p === 'caisse' && $s === 'banque') {
        echo '<h1>Versements banque</h1><ul>';
        foreach (q('SELECT * FROM versements_banque ORDER BY date_versement DESC') as $v) echo '<li>' . h($v['date_versement'] . ' · ' . $v['banque'] . ' · ' . $v['reference_bordereau']) . ' · ' . fcfa((float) $v['montant']) . '</li>';
        echo '</ul>';
    }
    if ($p === 'caisse' && $s === 'cloture') {
        echo '<h1>Clôture</h1>';
        foreach (q('SELECT j.*, c.nom FROM journees_caisses j JOIN caisses c ON c.caisse_id=j.caisse_id WHERE j.statut="ouverte"' . str_replace('a.', 'c.', $wa), $aa) as $j) {
            echo '<form method="post" class="card"><input type="hidden" name="action" value="caisse_cloture"><input type="hidden" name="journee_id" value="' . (int) $j['journee_id'] . '"><strong>' . h($j['nom']) . '</strong><p>Théorique ' . fcfa((float) $j['solde_theorique']) . '</p><label>Solde compté</label><input name="solde_physique" required><button class="btn">Clôturer</button></form>';
        }
    }
    if ($p === 'caisse' && $s === 'rapport') {
        echo '<h1>Rapport de journée</h1><p class="noprint"><button class="btn" onclick="print()">Imprimer</button></p>';
        foreach (q('SELECT j.*, c.nom FROM journees_caisses j JOIN caisses c ON c.caisse_id=j.caisse_id WHERE 1=1' . str_replace('a.', 'c.', $wa) . ' ORDER BY j.date_ouverture DESC LIMIT 12', $aa) as $j) {
            echo '<div class="card"><strong>' . h($j['nom']) . '</strong><p>' . h($j['date_ouverture'] . ' ' . (string) $j['heure_ouverture']) . ' · ' . h($j['statut']) . '</p><p>Entrées ' . fcfa((float) $j['total_entrees']) . ' · sorties ' . fcfa((float) $j['total_sorties']) . ' · théorique ' . fcfa((float) $j['solde_theorique']) . '</p></div>';
        }
    }
    if ($p === 'caisse' && $s === 'journal') {
        echo '<h1>Journal de caisse</h1><table>';
        foreach (q('SELECT * FROM transactions ORDER BY date_transaction DESC, transaction_id DESC LIMIT 40') as $t) echo '<tr><td>' . h($t['date_transaction'] . ' ' . $t['heure']) . '</td><td>' . h($t['type_transaction'] . ' · ' . $t['objet']) . '</td><td>' . fcfa((float) $t['montant']) . '</td></tr>';
        echo '</table>';
    }
    if ($p === 'caisse' && $s === 'recus') {
        $rq = trim((string) ($_GET['q'] ?? ''));
        $rd = (string) ($_GET['date'] ?? '');
        $rt = (string) ($_GET['type'] ?? '');
        echo '<h1>Reçus</h1><p class="muted">Réimpression = duplicata. Un reçu n\'est jamais supprimé.</p>';
        echo '<form class="filtres" method="get"><input type="hidden" name="p" value="caisse"><input type="hidden" name="s" value="recus"><input name="q" placeholder="Numéro, nom, objet" value="' . h($rq) . '"><input type="date" name="date" value="' . h($rd) . '"><select name="type"><option value="">Tous</option><option value="recu"' . ($rt === 'recu' ? ' selected' : '') . '>Reçu</option><option value="annulation"' . ($rt === 'annulation' ? ' selected' : '') . '>Annulation</option></select><button class="btn-go">Filtrer</button><a class="btn-clear" href="?p=caisse&s=recus">Réinitialiser</a></form>';
        $sqlR = 'SELECT r.*, t.montant, t.objet, t.nom_beneficiaire, t.date_transaction, t.statut tstatut FROM recus r LEFT JOIN transactions t ON t.transaction_id=r.transaction_id WHERE 1=1';
        $pa = [];
        if ($rq !== '') { $sqlR .= ' AND (r.numero LIKE ? OR t.objet LIKE ? OR t.nom_beneficiaire LIKE ?)'; $like = '%' . $rq . '%'; $pa = [$like, $like, $like]; }
        if ($rd !== '') { $sqlR .= ' AND t.date_transaction = ?'; $pa[] = $rd; }
        if ($rt !== '') { $sqlR .= ' AND r.type_recu = ?'; $pa[] = $rt; }
        $sqlR .= ' ORDER BY r.recu_id DESC';
        echo '<div class="table-wrap"><table class="pro"><thead><tr><th>Numéro</th><th>Date</th><th>Bénéficiaire</th><th>Montant</th><th></th></tr></thead><tbody>';
        foreach (q($sqlR, $pa) as $r) {
            echo '<tr><td><a href="?p=recu&id=' . (int) $r['recu_id'] . '&dup=1">' . h($r['numero']) . '</a><div class="muted">' . h($r['type_recu']) . '</div></td><td>' . h((string) $r['date_transaction']) . '</td><td>' . h((string) $r['nom_beneficiaire']) . '<div class="muted">' . h((string) $r['objet']) . '</div></td><td>' . fcfa((float) $r['montant']) . '</td><td><a class="btn" href="?p=recu&id=' . (int) $r['recu_id'] . '&dup=1">Imprimer</a>';
            if ($r['type_recu'] === 'recu' && $r['tstatut'] !== 'annule' && $r['transaction_id']) echo '<form method="post"><input type="hidden" name="action" value="annuler_recu"><input type="hidden" name="transaction_id" value="' . (int) $r['transaction_id'] . '"><button class="btn">Annuler</button></form>';
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
    if ($p === 'parametres' && ($s === 'agence' || $s === 'penalites' || $s === '')) {
        $agid = aid() ?: 1;
        $ag = one('SELECT * FROM agences WHERE agence_id = ?', [$agid]) ?? one('SELECT * FROM agences ORDER BY agence_id LIMIT 1');
        $pen = one('SELECT valeur FROM parametres WHERE cle="penalite_taux" AND (agence_id = ? OR agence_id IS NULL) ORDER BY agence_id IS NULL LIMIT 1', [$ag['agence_id']]);
        $seuil = one('SELECT valeur FROM parametres WHERE cle="seuil_validation_decaissement" AND (agence_id = ? OR agence_id IS NULL) ORDER BY agence_id IS NULL LIMIT 1', [$ag['agence_id']]);
        echo '<h1>' . h($ag['nom']) . '</h1><form method="post" class="card"><input type="hidden" name="action" value="agence_params"><input type="hidden" name="agence_id" value="' . (int) $ag['agence_id'] . '"><img src="' . h($ag['logo']) . '" alt="" style="height:48px"><label>Téléphone</label><input name="telephone" value="' . h($ag['telephone']) . '"><label>Email</label><input name="email" value="' . h($ag['email']) . '"><label>Adresse</label><input name="adresse" value="' . h($ag['adresse']) . '"><label>Pénalité %</label><input name="penalite" value="' . h($pen['valeur'] ?? '10') . '"><label>Seuil de validation</label><input name="seuil" value="' . h($seuil['valeur'] ?? '100000') . '"><button class="btn">Enregistrer</button></form>';
    }
    if ($p === 'parametres' && ($s === 'users' || $s === 'admin')) {
        echo '<h1>Utilisateurs</h1><form method="post" class="card"><input type="hidden" name="action" value="user_creer"><label>Nom</label><input name="nom" required><label>Prénom</label><input name="prenom" required><label>Identifiant</label><input name="login" required><label>Mot de passe</label><input name="mdp" required><label>Rôle</label><select name="role"><option>assistant</option><option>caissier</option><option>comptable</option><option>gerant</option><option>administrateur</option></select><button class="btn">Créer</button></form><table>';
        foreach (q('SELECT * FROM utilisateurs ORDER BY nom') as $u) {
            $next = $u['statut'] === 'actif' ? 'inactif' : 'actif';
            echo '<tr><td>' . h($u['prenom'] . ' ' . $u['nom']) . '<div class="muted">' . h((string) $u['login'] . ' · ' . (string) $u['role']) . '</div></td><td><form method="post"><input type="hidden" name="action" value="user_statut"><input type="hidden" name="utilisateur_id" value="' . (int) $u['utilisateur_id'] . '"><input type="hidden" name="statut" value="' . $next . '"><button class="btn">' . ($u['statut'] === 'actif' ? 'Désactiver' : 'Activer') . '</button></form></td></tr>';
        }
        echo '</table>';
    }
    if ($p === 'parametres' && $s === 'roles') {
        echo '<h1>Rôles</h1><table><tr><th>Rôle</th><th>Module</th><th>Voir</th><th>Créer</th></tr>';
        foreach (q('SELECT * FROM roles_permissions ORDER BY role, module') as $r) echo '<tr><td>' . h($r['role']) . '</td><td>' . h($r['module']) . '</td><td>' . (int) $r['peut_voir'] . '</td><td>' . (int) $r['peut_creer'] . '</td></tr>';
        echo '</table>';
    }
    if ($p === 'parametres' && $s === 'referentiels') echo '<h1>Référentiels</h1><p>Types de locaux : appartement, maison, magasin, bureau, villa, studio. Fréquence : mensuel. Modes : espèces, Orange, Wave, MTN, Moov, virement, chèque. Contrats : à échoir (a_echoir) ou échu.</p>';
    if ($p === 'parametres' && $s === 'modeles') {
        echo '<h1>Modèles</h1><ul>';
        foreach (q('SELECT * FROM parametres WHERE cle LIKE "modele%"') as $m) echo '<li><strong>' . h($m['cle']) . '</strong><div class="muted">' . h($m['valeur']) . '</div></li>';
        echo '</ul>';
    }
    if (($p === 'parametres' && $s === 'journal') || ($p === 'accueil' && $s === 'activites')) {
        echo '<h1>Journal</h1><ul>';
        foreach (q('SELECT * FROM journal_activite ORDER BY date_action DESC LIMIT 30') as $j) echo '<li>' . h($j['date_action'] . ' · ' . $j['action'] . ' · ' . $j['details']) . '</li>';
        echo '</ul>';
    }
    if ($p === 'parametres' && $s === 'export') {
        echo '<h1>Sauvegarde</h1><p>Les données sont dans MySQL, base rouahimo. Exportez-la depuis phpMyAdmin.</p>';
    }
    if ($p === 'accueil' && $s === 'demandes') {
        echo '<h1>Demandes</h1><ul>';
        foreach (q('SELECT d.*, l.nom local_nom, b.nom bien FROM demandes_location d JOIN locaux l ON l.local_id=d.local_id JOIN biens b ON b.bien_id=l.bien_id ORDER BY d.date_demande DESC') as $d) echo '<li><strong>' . h($d['prenom'] . ' ' . $d['nom']) . '</strong> · ' . h($d['bien'] . ' ' . $d['local_nom']) . ' · ' . h($d['statut']) . '</li>';
        echo '</ul>';
    }
    if ($p === 'bien') {
        $b = one('SELECT b.*, p.prenom, p.nom pn, p.telephone, p.email, p.taux_commission FROM biens b JOIN proprietaires p ON p.proprietaire_id=b.proprietaire_id WHERE b.bien_id=?', [(int) ($_GET['id'] ?? 0)]);
        if ($b) {
            echo '<div class="card"><img class="cover" src="' . h(photoBien((int) $b['bien_id'])) . '" alt=""><h1>' . h($b['nom']) . '</h1><p>' . h($b['ville'] . ' · ' . $b['quartier']) . '<br>' . h($b['adresse']) . '<br>GPS ' . h((string) $b['latitude']) . ', ' . h((string) $b['longitude']) . '</p><p>Propriétaire : ' . h($b['prenom'] . ' ' . $b['pn']) . ' · commission ' . h((string) $b['taux_commission']) . ' % · ' . h($b['telephone']) . '</p></div><table>';
            foreach (q('SELECT * FROM locaux WHERE bien_id=?', [(int) $b['bien_id']]) as $l) echo '<tr><td>' . h($l['nom']) . ' · ' . h((string) $l['type_local']) . '<div class="muted">' . (int) $l['nombre_pieces'] . ' p. · ' . h((string) $l['surface']) . ' m²</div></td><td>' . h($l['statut']) . ' · ' . fcfa((float) $l['prix_loyer']) . '</td></tr>';
            echo '</table>';
        }
    }
    if ($p === 'contrat') {
        $c = one('SELECT c.*, t.nom tn, t.prenom tp, t.telephone, l.nom local_nom, l.type_local, l.surface, l.nombre_pieces, b.nom bien, b.adresse, b.quartier, p.nom pn, p.prenom pp, p.taux_commission FROM contrats c JOIN locataires t ON t.locataire_id=c.locataire_id JOIN locaux l ON l.local_id=c.local_id JOIN biens b ON b.bien_id=l.bien_id JOIN proprietaires p ON p.proprietaire_id=b.proprietaire_id WHERE c.contrat_id=?', [(int) ($_GET['id'] ?? 0)]);
        if ($c) {
            $type = $c['type_contrat'] === 'a_echoir' ? 'Terme à échoir' : 'Terme échu';
            echo '<div class="paper"><p class="muted" style="text-align:center">RÉPUBLIQUE DE CÔTE D\'IVOIRE</p><h1 style="text-align:center">CONTRAT DE BAIL</h1><p style="text-align:center">Bail #' . (int) $c['contrat_id'] . '</p>';
            echo '<p>Bailleur : ' . h($c['pp'] . ' ' . $c['pn']) . '.<br>Preneur : ' . h($c['tp'] . ' ' . $c['tn']) . ' · ' . h($c['telephone']) . '.</p>';
            echo '<p>Bien : ' . h($c['bien']) . ', ' . h($c['adresse']) . ', ' . h($c['quartier']) . '.<br>Local : ' . h($c['local_nom']) . ' · ' . h((string) $c['type_local']) . ' · ' . h((string) $c['surface']) . ' m² · ' . (int) $c['nombre_pieces'] . ' pièces.</p>';
            echo '<p>Loyer ' . fcfa((float) $c['loyer_nu']) . ' · ' . h($type) . ' · fréquence ' . h((string) $c['frequence']) . '.<br>Durée ' . (int) $c['duree'] . ' mois · du ' . h((string) $c['date_debut']) . ' au ' . h((string) $c['date_fin']) . '.<br>Caution ' . fcfa((float) $c['caution']) . ' · avance ' . fcfa((float) $c['avance']) . ' · préavis ' . h($c['date_preavis'] ?: '3 mois') . ' · statut ' . h((string) $c['statut']) . '.<br>Commission ' . h((string) $c['taux_commission']) . ' %.</p>';
            echo '<p class="noprint"><button class="btn" onclick="print()">Imprimer</button></p></div>';
        }
    }
    if ($p === 'recu') {
        $r = one('SELECT r.*, t.montant, t.objet, t.date_transaction, t.heure, t.mode_paiement, t.reference_paiement, t.nom_beneficiaire, t.utilisateur_id, a.nom agence, a.telephone, a.email, a.adresse, a.ville, l.mois, l.annee, loc.telephone ltel, u.prenom up, u.nom un, locl.nom local_nom, b.nom bien_nom FROM recus r LEFT JOIN transactions t ON t.transaction_id=r.transaction_id LEFT JOIN agences a ON a.agence_id=r.agence_id LEFT JOIN loyers l ON l.loyer_id=t.loyer_id LEFT JOIN contrats c ON c.contrat_id=l.contrat_id LEFT JOIN locataires loc ON loc.locataire_id=c.locataire_id LEFT JOIN locaux locl ON locl.local_id=c.local_id LEFT JOIN biens b ON b.bien_id=locl.bien_id LEFT JOIN utilisateurs u ON u.utilisateur_id=t.utilisateur_id WHERE r.recu_id=?', [(int) ($_GET['id'] ?? 0)]);
        if ($r) {
            $deja = (int) ($r['nb_impressions'] ?? 0);
            $dup = isset($_GET['dup']) || $deja > 0;
            if ($deja === 0) db()->prepare('UPDATE recus SET nb_impressions = 1 WHERE recu_id = ?')->execute([(int) $r['recu_id']]);
            $modes = ['especes' => 'Espèces', 'orange_money' => 'Orange Money', 'mtn_momo' => 'MTN MoMo', 'moov_money' => 'Moov Money', 'wave' => 'Wave', 'cheque' => 'Chèque', 'virement' => 'Virement'];
            $mode = $modes[$r['mode_paiement'] ?? ''] ?? (string) ($r['mode_paiement'] ?? '—');
            $moisN = [1 => 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
            $periode = !empty($r['mois']) ? ($moisN[(int) $r['mois']] ?? '') . ' ' . (int) $r['annee'] : '—';
            $montant = (float) $r['montant'];
            $chiffres = number_format($montant, 0, ',', ' ') . ' FCFA';
            $lettres = lettresFcfa((int) round($montant));
            $tel = preg_replace('/\D+/', '', (string) ($r['ltel'] ?? ''));
            if ($tel !== '' && substr($tel, 0, 3) !== '225') $tel = '225' . $tel;
            $msg = rawurlencode(($dup ? "DUPLICATA\n" : '') . 'Reçu ' . $r['numero'] . "\nLocataire : " . $r['nom_beneficiaire'] . "\nMontant : " . $chiffres . "\nPériode : " . $periode . "\nMode : " . $mode . "\nROUAHIMO");
            $caissier = trim((string) ($r['up'] ?? '') . ' ' . (string) ($r['un'] ?? ''));
            $local = trim((string) ($r['local_nom'] ?? '') . (!empty($r['bien_nom']) ? ' — ' . $r['bien_nom'] : ''));
            echo '<div class="paper" id="quittance"><div style="display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap"><div><img src="assets/logo-rouahimo.svg" alt="ROUAHIMO" style="height:46px"><div style="font-weight:800;color:#E8650A;letter-spacing:.04em">ROUAHIMO</div></div><div style="text-align:right;font-size:13px"><b>' . h((string) $r['agence']) . '</b><br>' . h((string) $r['adresse']) . '<br>' . h((string) $r['ville']) . ', Côte d\'Ivoire<br>Tél : ' . h((string) $r['telephone']) . '<br>' . h((string) $r['email']) . '</div></div>';
            echo '<hr style="border:0;border-top:2px solid #E8650A;margin:10px 0">';
            if ($dup) echo '<p style="text-align:center;font-weight:800;letter-spacing:.18em;color:#9a3412;margin:0">DUPLICATA</p>';
            echo '<h1 style="text-align:center;margin:8px 0 0;letter-spacing:.08em">REÇU</h1><p style="text-align:center;color:#E8650A;font-weight:800;font-size:22px;margin-top:0">' . h((string) $r['numero']) . '</p>';
            echo '<table style="width:100%">';
            echo '<tr><th>Locataire</th><td>' . h((string) $r['nom_beneficiaire']) . '</td></tr>';
            if ($local !== '') echo '<tr><th>Local</th><td>' . h($local) . '</td></tr>';
            echo '<tr><th>Période</th><td>' . h($periode) . '</td></tr>';
            echo '<tr><th>Montant en chiffres</th><td style="color:#E8650A;font-weight:800">' . h($chiffres) . '</td></tr>';
            echo '<tr><th>Montant en lettres</th><td>' . h($lettres) . '</td></tr>';
            echo '<tr><th>Mode de paiement</th><td>' . h($mode) . '</td></tr>';
            echo '<tr><th>Référence paiement</th><td>' . h((string) ($r['reference_paiement'] ?: '—')) . '</td></tr>';
            echo '<tr><th>Caissier</th><td>' . h($caissier !== '' ? $caissier : 'Caisse') . '</td></tr>';
            echo '<tr><th>Date & Heure</th><td>' . h((string) $r['date_transaction'] . ' ' . (string) $r['heure']) . '</td></tr>';
            echo '<tr><th>Objet</th><td>' . h((string) $r['objet']) . '</td></tr></table>';
            echo '<p>Merci pour votre confiance. Ce reçu est généré automatiquement.</p>';
            echo '<p class="muted">Code de contrôle : ' . h((string) $r['numero']) . '-' . (int) round($montant) . '</p>';
            echo '<p style="text-align:center">ROUAHIMO — Gestion locative, votre partenaire de confiance.</p></div>';
            echo '<p class="noprint"><button class="btn" type="button" onclick="window.print()">Imprimer</button> ';
            echo '<button class="btn" type="button" style="background:#E8650A" onclick="window.print()">PDF</button> ';
            echo '<a class="btn" style="background:#16a34a" href="https://wa.me/' . h($tel) . '?text=' . $msg . '">WhatsApp</a></p>';
            echo '<p class="noprint muted">PDF : dans la boîte d\'impression Chrome ou Edge, choisissez « Enregistrer au format PDF ».</p>';
        }
    }
}
?>
</div>
</main>
<?php endif; ?>
<script>
document.querySelectorAll("[data-pal]").forEach(function (b) {
  b.addEventListener("click", function () {
    var v = b.getAttribute("data-pal") || "orange";
    document.documentElement.dataset.palette = v;
    try { localStorage.setItem("rouahimo-palette", v); } catch (e) {}
  });
});
var themeBtn = document.getElementById("theme-toggle");
if (themeBtn) themeBtn.addEventListener("click", function () {
  var next = document.documentElement.dataset.theme === "sombre" ? "clair" : "sombre";
  document.documentElement.dataset.theme = next;
  try { localStorage.setItem("rouahimo-theme", next); } catch (e) {}
});
</script>
</body>
</html>
<?php
function formLocataire(): string {
    return '<form method="post" enctype="multipart/form-data" class="card"><input type="hidden" name="action" value="locataire_nouveau"><label>Nom</label><input name="nom" required><label>Prénom</label><input name="prenom" required><label>Téléphone</label><input name="telephone"><label>Email</label><input name="email"><label>Profession</label><input name="profession"><label>N° CNI / pièce d\'identité</label><input name="cni" required placeholder="CI…"><label>Délivrance</label><input type="date" name="delivrance"><label>Expiration</label><input type="date" name="expiration"><label>Scan recto</label><input type="file" name="cni_recto" accept="image/*"><label>Scan verso (optionnel)</label><input type="file" name="cni_verso" accept="image/*"><label>Sexe</label><select name="genre"><option value="">—</option><option value="M">Homme</option><option value="F">Femme</option></select><button class="btn">Enregistrer</button></form>';
}

function assurerColonnesCni(): void {
    static $fait = false;
    if ($fait) return;
    $fait = true;
    try {
        $cols = [];
        foreach (q('SHOW COLUMNS FROM locataires') as $c) $cols[$c['Field']] = true;
        if (!isset($cols['date_delivrance_piece'])) db()->exec('ALTER TABLE locataires ADD COLUMN date_delivrance_piece DATE NULL');
        if (!isset($cols['date_expiration_piece'])) db()->exec('ALTER TABLE locataires ADD COLUMN date_expiration_piece DATE NULL');
    } catch (Throwable $e) {
    }
}

function sauverPiece(int $id, string $champ, string $nom): void {
    if ($id <= 0 || empty($_FILES[$champ]['tmp_name']) || !is_uploaded_file($_FILES[$champ]['tmp_name'])) return;
    $ext = strtolower(pathinfo((string) $_FILES[$champ]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) $ext = 'jpg';
    if (!is_dir(__DIR__ . '/uploads')) mkdir(__DIR__ . '/uploads', 0775, true);
    $rel = 'uploads/cni-' . $id . '-' . ($champ === 'cni_verso' ? 'verso' : 'recto') . '.' . $ext;
    if (!move_uploaded_file($_FILES[$champ]['tmp_name'], __DIR__ . '/' . $rel)) return;
    db()->prepare('UPDATE albums SET statut="inactif" WHERE module="locataire" AND module_id=? AND nom=?')->execute([$id, $nom]);
    db()->prepare('INSERT INTO albums (nom, type_media, url, module, module_id, statut) VALUES (?,"photos",?,"locataire",?,"actif")')->execute([$nom, $rel, $id]);
}

function lettresFcfa(int $n): string {
    if ($n === 0) return 'zéro franc CFA';
    $u = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf', 'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize'];
    $d = ['', '', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', 'soixante', 'quatre-vingt', 'quatre-vingt'];
    $dessous100 = function (int $x) use ($u, $d, &$dessous100): string {
        if ($x < 17) return $u[$x];
        if ($x < 20) return 'dix-' . $u[$x - 10];
        if ($x < 70) {
            $q = intdiv($x, 10); $r = $x % 10;
            if ($r === 0) return $d[$q];
            if ($r === 1 && $q < 8) return $d[$q] . ' et un';
            return $d[$q] . '-' . $u[$r];
        }
        if ($x < 80) return 'soixante-' . $dessous100($x - 60);
        if ($x === 80) return 'quatre-vingts';
        return 'quatre-vingt-' . $dessous100($x - 80);
    };
    $dessous1000 = function (int $x) use ($u, $dessous100): string {
        if ($x < 100) return $dessous100($x);
        $c = intdiv($x, 100); $r = $x % 100;
        $cent = $c === 1 ? 'cent' : $u[$c] . ' cent';
        if ($r === 0) return $c > 1 ? $cent . 's' : $cent;
        return $cent . ' ' . $dessous100($r);
    };
    $millions = intdiv($n, 1000000);
    $mille = intdiv($n % 1000000, 1000);
    $reste = $n % 1000;
    $parts = [];
    if ($millions) $parts[] = ($millions === 1 ? 'un million' : $dessous1000($millions) . ' millions');
    if ($mille) $parts[] = ($mille === 1 ? 'mille' : $dessous1000($mille) . ' mille');
    if ($reste) $parts[] = $dessous1000($reste);
    $txt = trim(implode(' ', $parts));
    return ucfirst($txt) . ' francs CFA';
}

function lignesReversement(int $mois, int $annee, string $wa, array $aa): array {
    $rows = q('SELECT proprietaire_id, prenom, nom, numero_mandat, taux_commission, date_debut_mandat, date_fin_mandat FROM proprietaires p WHERE 1=1' . str_replace('a.', 'p.', $wa), $aa);
    $out = [];
    foreach ($rows as $p) {
        $brut = (float) (one('SELECT COALESCE(SUM(l.montant),0) m FROM loyers l JOIN contrats c ON c.contrat_id=l.contrat_id JOIN locaux loc ON loc.local_id=c.local_id JOIN biens b ON b.bien_id=loc.bien_id WHERE b.proprietaire_id=? AND l.statut="paye" AND l.mois=? AND l.annee=?', [(int) $p['proprietaire_id'], $mois, $annee])['m'] ?? 0);
        $dep = (float) (one('SELECT COALESCE(SUM(montant),0) m FROM depenses WHERE proprietaire_id=? AND MONTH(date_depense)=? AND YEAR(date_depense)=? AND statut<>"inactif"', [(int) $p['proprietaire_id'], $mois, $annee])['m'] ?? 0);
        $taux = (float) $p['taux_commission'];
        $com = round($brut * $taux / 100);
        $out[] = $p + ['brut' => $brut, 'commission' => $com, 'depenses' => $dep, 'net' => $brut - $com - $dep, 'taux' => $taux];
    }
    return $out;
}

function badgePersonne(string $statut): string {
    return $statut === 'actif' ? '<span class="badge ok">ACTIF</span>' : '<span class="badge off">INACTIF</span>';
}

function sexeIco(?string $genre): string {
    if ($genre === 'M') return '<span class="sexe" title="Homme">♂</span>';
    if ($genre === 'F') return '<span class="sexe" title="Femme">♀</span>';
    return '<span class="muted">—</span>';
}

function formDemande(?int $localId = null): string {
    $html = '<form method="post" class="card"><input type="hidden" name="action" value="demande"><h2>Je veux ce logement</h2>';
    if ($localId) {
        $html .= '<input type="hidden" name="local_id" value="' . $localId . '">';
    } else {
        $html .= '<label>Logement</label><select name="local_id" required>';
        $rows = q('SELECT l.local_id, l.nom, b.nom bien, b.quartier FROM locaux l JOIN biens b ON b.bien_id=l.bien_id WHERE l.statut="libre" ORDER BY b.nom, l.nom');
        foreach ($rows as $l) $html .= '<option value="' . (int) $l['local_id'] . '">' . h($l['bien'] . ' · ' . $l['nom'] . ' · ' . $l['quartier']) . '</option>';
        $html .= '</select>';
    }
    $html .= '<label>Nom</label><input name="nom" required><label>Prénom</label><input name="prenom" required><label>Téléphone</label><input name="telephone" required><label>Email</label><input name="email"><label>Profession</label><input name="profession"><label>Message</label><textarea name="message"></textarea><button class="btn" type="submit">Envoyer la demande</button></form>';
    return $html;
}

function renduPublic(string $s): void {
    global $loginError;
    if ($loginError) echo '<p class="warn">' . h($loginError) . '</p>';
    $rows = q('SELECT l.local_id, l.nom local_nom, l.description, l.type_local, l.surface, l.nombre_pieces, l.prix_loyer, l.charges, l.caution_mois, l.avance_mois, b.bien_id, b.nom bien, b.ville, b.quartier, b.adresse, a.nom agence, a.telephone, a.email, a.logo, a.adresse adresse_agence, a.quartier quartier_agence, a.ville ville_agence FROM locaux l JOIN biens b ON b.bien_id=l.bien_id JOIN agences a ON a.agence_id=b.agence_id WHERE l.statut="libre" ORDER BY b.ville, b.quartier, b.nom');
    if ($s === 'agences' || $s === 'contact' || $s === 'accueil') {
        $agences = q('SELECT nom, telephone, email, logo, adresse, quartier, ville FROM agences WHERE statut="actif" ORDER BY nom');
    } else {
        $agences = [];
    }
    if ($s === 'accueil' || $s === '') {
        echo '<h1>Trouver un logement</h1><p class="muted">Parcourez les locaux libres des agences ROUAHIMO, sans compte. Le propriétaire reste masqué.</p>';
        echo '<p><a class="btn" href="?p=public&s=catalogue">Voir le catalogue</a></p><h2>Contact</h2><ul>';
        foreach ($agences as $a) echo '<li><strong>' . h($a['nom']) . '</strong> <span class="muted">· ' . h((string) $a['telephone']) . ' · ' . h((string) $a['email']) . '</span></li>';
        echo '</ul>';
        return;
    }
    if ($s === 'agences') {
        echo '<h1>Agences</h1><div class="grid">';
        foreach ($agences as $a) echo '<div class="card"><img src="' . h((string) $a['logo']) . '" alt="" style="height:48px"><strong>' . h($a['nom']) . '</strong><p class="muted">' . h($a['quartier'] . ' · ' . $a['ville']) . '</p><p>' . h((string) $a['adresse']) . '</p><p>' . h((string) $a['telephone']) . '<br>' . h((string) $a['email']) . '</p></div>';
        echo '</div>';
        return;
    }
    if ($s === 'contact') {
        echo '<h1>Contact</h1>';
        foreach ($agences as $a) {
            echo '<div class="card"><strong>' . h($a['nom']) . '</strong><p class="muted">' . h((string) $a['adresse']) . '</p><div class="duo"><a class="call" href="tel:' . h(preg_replace('/\s+/', '', (string) $a['telephone'])) . '">Appeler</a><a class="wa" href="' . h(wa((string) $a['telephone'], 'Bonjour ' . $a['nom'])) . '">WhatsApp</a></div><p><a href="mailto:' . h((string) $a['email']) . '">' . h((string) $a['email']) . '</a></p></div>';
        }
        return;
    }
    if ($s === 'demande') {
        echo '<h1>Je veux ce logement</h1>' . formDemande(null);
        return;
    }
    if ($s === 'fiche') {
        $id = (int) ($_GET['id'] ?? 0);
        $l = null;
        foreach ($rows as $row) if ((int) $row['local_id'] === $id) $l = $row;
        if (!$l) { echo '<p>Logement introuvable.</p>'; return; }
        $loyer = (float) $l['prix_loyer'];
        $charges = (float) $l['charges'];
        if ($charges <= 0) $charges = round($loyer * 0.1);
        $mc = (int) $l['caution_mois']; if ($mc <= 0) $mc = 2;
        $ma = (int) $l['avance_mois']; if ($ma <= 0) $ma = 1;
        $modele = in_array($l['type_local'], ['magasin', 'bureau'], true) ? 'Terme échu' : 'Terme à échoir';
        $texte = 'Bonjour ' . $l['agence'] . ', je souhaite visiter ' . $l['local_nom'] . ' (' . $l['quartier'] . ').';
        echo '<div class="card" style="max-width:36rem"><img class="cover" src="' . h(photoLocal((int) $l['local_id'], (int) $l['bien_id'])) . '" alt="" style="height:220px"><div style="display:flex;gap:10px;align-items:center;margin-top:8px"><img src="' . h((string) $l['logo']) . '" alt="" style="height:42px"><div><h1 style="margin:0">' . h($l['local_nom']) . '</h1><p class="muted">' . h($l['ville'] . ' · ' . $l['quartier'] . ' · ' . $l['agence']) . '</p></div></div>';
        if (!empty($l['description'])) echo '<p>' . h((string) $l['description']) . '</p>';
        echo '<p class="muted">' . h($l['bien'] . ' · ' . $l['adresse']) . '</p>';
        echo '<dl class="stats"><div><dt>Loyer</dt><dd>' . fcfa($loyer) . '</dd></div><div><dt>Charges</dt><dd>' . fcfa($charges) . '</dd></div><div><dt>Caution</dt><dd>' . fcfa($loyer * $mc) . '</dd></div><div><dt>Avance</dt><dd>' . fcfa($loyer * $ma) . '</dd></div><div><dt>Surface</dt><dd>' . h((string) $l['surface']) . ' m²</dd></div><div><dt>Pièces</dt><dd>' . (int) $l['nombre_pieces'] . '</dd></div></dl>';
        echo '<p>Modèle de contrat proposé : <strong>' . h($modele) . '</strong>. Les deux formules existent : terme échu et terme à échoir.</p>';
        echo '<div class="duo"><a class="call" href="tel:' . h(preg_replace('/\s+/', '', (string) $l['telephone'])) . '">Appeler</a><a class="wa" href="' . h(wa((string) $l['telephone'], $texte)) . '">WhatsApp</a></div></div>';
        echo formDemande((int) $l['local_id']);
        return;
    }
    $ville = (string) ($_GET['ville'] ?? '');
    $quartier = (string) ($_GET['quartier'] ?? '');
    $type = (string) ($_GET['type'] ?? '');
    $budget = (int) ($_GET['budget'] ?? 0);
    $villes = array_values(array_unique(array_filter(array_column($rows, 'ville'))));
    $quartiers = array_values(array_unique(array_filter(array_column($rows, 'quartier'))));
    sort($villes); sort($quartiers);
    $types = ['appartement' => 'Appartement', 'maison' => 'Maison', 'magasin' => 'Magasin', 'bureau' => 'Bureau', 'villa' => 'Villa', 'studio' => 'Studio'];
    echo '<h1>Logements libres</h1><p class="muted">Le propriétaire n\'est pas affiché. Vous parlez à l\'agence.</p>';
    echo '<form method="get" class="filters"><input type="hidden" name="p" value="public"><input type="hidden" name="s" value="catalogue">';
    echo '<select name="ville"><option value="">Toutes les villes</option>';
    foreach ($villes as $v) echo '<option' . ($ville === $v ? ' selected' : '') . '>' . h($v) . '</option>';
    echo '</select><select name="quartier"><option value="">Tous les quartiers</option>';
    foreach ($quartiers as $v) echo '<option' . ($quartier === $v ? ' selected' : '') . '>' . h($v) . '</option>';
    echo '</select><select name="type"><option value="">Tous les types</option>';
    foreach ($types as $k => $lab) echo '<option value="' . h($k) . '"' . ($type === $k ? ' selected' : '') . '>' . h($lab) . '</option>';
    echo '</select><select name="budget"><option value="0">Tous les budgets</option>';
    foreach ([100000 => 'Jusqu\'à 100 000', 180000 => 'Jusqu\'à 180 000', 250000 => 'Jusqu\'à 250 000'] as $n => $lab) echo '<option value="' . $n . '"' . ($budget === $n ? ' selected' : '') . '>' . h($lab) . '</option>';
    echo '</select><button class="btn" type="submit">Filtrer</button></form><div class="grid">';
    $n = 0;
    foreach ($rows as $l) {
        if ($ville !== '' && $l['ville'] !== $ville) continue;
        if ($quartier !== '' && $l['quartier'] !== $quartier) continue;
        if ($type !== '' && $l['type_local'] !== $type) continue;
        if ($budget > 0 && (float) $l['prix_loyer'] > $budget) continue;
        $n++;
        $q = 'ville=' . rawurlencode($ville) . '&quartier=' . rawurlencode($quartier) . '&type=' . rawurlencode($type) . '&budget=' . $budget;
        echo '<a class="card" href="?p=public&s=fiche&id=' . (int) $l['local_id'] . '&' . $q . '"><img class="cover" src="' . h(photoLocal((int) $l['local_id'], (int) $l['bien_id'])) . '" alt=""><strong>' . h($l['bien'] . ' · ' . $l['local_nom']) . '</strong><p class="muted">' . h($l['ville'] . ' · ' . $l['quartier']) . '</p><p style="font-weight:800;color:#E8650A">' . fcfa((float) $l['prix_loyer']) . '</p></a>';
    }
    if ($n === 0) echo '<p class="muted">Aucun logement libre avec ces filtres.</p>';
    echo '</div>';
}
?>
