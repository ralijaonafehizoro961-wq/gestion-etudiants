<?php
require_once 'config.php';

$pdo     = getConnection();
$message = '';
$erreur  = '';
$mode    = $_GET['mode'] ?? 'liste';
$id      = intval($_GET['id'] ?? 0);



// AJOUTER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_ajouter'])) {
    $matricule = trim($_POST['matricule'] ?? '');
    $nom       = strtoupper(trim($_POST['nom'] ?? ''));
    $prenom    = ucfirst(strtolower(trim($_POST['prenom'] ?? '')));
    $email     = trim($_POST['email'] ?? '') ?: null;
    $telephone = trim($_POST['telephone'] ?? '') ?: null;
    $niveau    = $_POST['niveau'] ?? 'L1';
    $filiere   = trim($_POST['filiere'] ?? '') ?: null;

    if (!$matricule || !$nom || !$prenom) {
        $erreur = "Matricule, nom et prénom sont obligatoires.";
        $mode   = 'ajouter';
    } else {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO etudiants (matricule, nom, prenom, email, telephone, niveau, filiere)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$matricule, $nom, $prenom, $email, $telephone, $niveau, $filiere]);
            $message = "Etudiant ajouté avec succès !";
            $mode    = 'liste';
        } catch (PDOException $e) {
            $erreur = "Ce matricule ou cet email est déjà utilisé.";
            $mode   = 'ajouter';
        }
    }
}

// MODIFIER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_modifier'])) {
    $id        = intval($_POST['id'] ?? 0);
    $nom       = strtoupper(trim($_POST['nom'] ?? ''));
    $prenom    = ucfirst(strtolower(trim($_POST['prenom'] ?? '')));
    $email     = trim($_POST['email'] ?? '') ?: null;
    $telephone = trim($_POST['telephone'] ?? '') ?: null;
    $niveau    = $_POST['niveau'] ?? 'L1';
    $filiere   = trim($_POST['filiere'] ?? '') ?: null;
    $statut    = $_POST['statut'] ?? 'actif';

    if (!$nom || !$prenom) {
        $erreur = "Nom et prénom sont obligatoires.";
        $mode   = 'modifier';
    } else {
        try {
            $stmt = $pdo->prepare(
                "UPDATE etudiants SET nom=?, prenom=?, email=?, telephone=?, niveau=?, filiere=?, statut=?
                 WHERE id=?"
            );
            $stmt->execute([$nom, $prenom, $email, $telephone, $niveau, $filiere, $statut, $id]);
            $message = "Etudiant modifié avec succès !";
            $mode    = 'liste';
        } catch (PDOException $e) {
            $erreur = "Cet email est déjà utilisé par un autre étudiant.";
            $mode   = 'modifier';
        }
    }
}

// SUPPRIMER
if ($mode === 'supprimer' && $id) {
    $stmt = $pdo->prepare("SELECT nom, prenom FROM etudiants WHERE id = ?");
    $stmt->execute([$id]);
    $etu = $stmt->fetch();
    if ($etu) {
        $pdo->prepare("DELETE FROM etudiants WHERE id = ?")->execute([$id]);
        $message = $etu['prenom'] . " " . $etu['nom'] . " supprimé avec succès !";
    }
    $mode = 'liste';
}

// CHARGER ÉTUDIANT POUR MODIFIER
$etudiantEdit = null;
if ($mode === 'modifier' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM etudiants WHERE id = ?");
    $stmt->execute([$id]);
    $etudiantEdit = $stmt->fetch();
    if (!$etudiantEdit) $mode = 'liste';
}

// LISTE + RECHERCHE
$search    = trim($_GET['search'] ?? '');
$etudiants = [];
if ($mode === 'liste' || $mode === 'dashboard') {
    if ($search) {
        $stmt = $pdo->prepare(
            "SELECT * FROM etudiants
             WHERE nom LIKE ? OR prenom LIKE ? OR matricule LIKE ?
             ORDER BY nom"
        );
        $stmt->execute(["%$search%", "%$search%", "%$search%"]);
    } else {
        $stmt = $pdo->query("SELECT * FROM etudiants ORDER BY nom");
    }
    $etudiants = $stmt->fetchAll();
}

// STATS
$total   = $pdo->query("SELECT COUNT(*) FROM etudiants")->fetchColumn();
$actifs  = $pdo->query("SELECT COUNT(*) FROM etudiants WHERE statut='actif'")->fetchColumn();
$niveaux = $pdo->query("SELECT niveau, COUNT(*) as nb FROM etudiants GROUP BY niveau ORDER BY niveau")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion Etudiants</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #07070f; color: #e2e8f0; min-height: 100vh; }

        /* NAV */
        nav {
            background: #1a1a2e;
            padding: 0 30px;
            display: flex;
            align-items: center;
            gap: 5px;
            height: 56px;
            border-bottom: 2px solid #7c3aed;
        }
        .nav-brand { color: #7c3aed; font-size: 17px; font-weight: bold; margin-right: auto; }
        .nav-brand i { margin-right: 8px; }
        .nav-link {
            color: #94a3b8;
            text-decoration: none;
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 7px;
            transition: all 0.2s;
        }
        .nav-link:hover, .nav-link.active { background: #7c3aed; color: white; }

        /* CONTAINER */
        .container { max-width: 1100px; margin: 30px auto; padding: 0 20px; }

        /* ALERTS */
        .alert { padding: 12px 18px; border-radius: 6px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #064e3b; color: #6ee7b7; border-left: 4px solid #10b981; }
        .alert-danger  { background: #7f1d1d; color: #fca5a5; border-left: 4px solid #ef4444; }

        /* STATS */
        .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }
        .stat-card {
            background: #0f0f1a;
            border: 1px solid #1e1e30;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }
        .stat-card i { font-size: 28px; margin-bottom: 10px; }
        .stat-number { font-size: 36px; font-weight: bold; }
        .stat-label  { color: #475569; font-size: 13px; margin-top: 4px; }

        /* PAGE HEADER */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .page-header h1 { font-size: 22px; color: #e2e8f0; display: flex; align-items: center; gap: 10px; }
        .page-header h1 i { color: #7c3aed; }

        /* SEARCH */
        .search-bar { display: flex; gap: 10px; margin-bottom: 20px; }
        .search-bar input {
            flex: 1;
            padding: 10px 15px;
            background: #0f0f1a;
            border: 1px solid #2d3748;
            color: #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
            max-width: 380px;
        }
        .search-bar input:focus { outline: none; border-color: #7c3aed; }

        /* TABLE */
        .table-wrap { background: #0f0f1a; border-radius: 8px; overflow: hidden; border: 1px solid #1e1e30; }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            background: #1a1a2e;
            color: #06b6d4;
            padding: 13px 16px;
            text-align: left;
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        tbody td { padding: 13px 16px; border-bottom: 1px solid #1a1a2e; font-size: 14px; }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover td { background: #13131f; }
        .empty-row td { text-align: center; padding: 40px; color: #475569; }

        /* BADGE */
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .badge-actif   { background: #064e3b; color: #6ee7b7; }
        .badge-inactif { background: #374151; color: #9ca3af; }
        .badge-niveau  { background: #1e1e3f; color: #818cf8; }

        /* BUTTONS */
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-size: 13px;
            transition: opacity 0.2s;
        }
        .btn:hover { opacity: 0.85; }
        .btn-primary { background: #7c3aed; color: white; }
        .btn-success { background: #10b981; color: white; }
        .btn-danger  { background: #ef4444; color: white; }
        .btn-warning { background: #f59e0b; color: #1a1a1a; }
        .btn-ghost   { background: #1e1e30; color: #94a3b8; }
        .btn-sm { padding: 5px 10px; font-size: 12px; }
        .actions { display: flex; gap: 6px; }

        /* FORM */
        .form-card {
            background: #0f0f1a;
            border: 1px solid #1e1e30;
            border-radius: 8px;
            padding: 28px;
            max-width: 620px;
        }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group.full { grid-column: 1 / -1; }
        .form-group label { font-size: 13px; color: #06b6d4; font-weight: bold; }
        .form-group label i { margin-right: 5px; }
        .form-group input, .form-group select {
            padding: 10px 12px;
            background: #1a1a2e;
            border: 1px solid #2d3748;
            color: #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #7c3aed;
        }
        .form-group input:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .form-actions { display: flex; gap: 10px; margin-top: 24px; padding-top: 20px; border-top: 1px solid #1e1e30; }

        /* NIVEAU CARDS */
        .niveau-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px; margin-top: 16px; }
        .niveau-card {
            background: #0f0f1a;
            border: 1px solid #1e1e30;
            border-radius: 8px;
            padding: 16px;
            text-align: center;
        }
        .niveau-card .nb { font-size: 28px; font-weight: bold; color: #7c3aed; }
        .niveau-card .lv { font-size: 13px; color: #475569; margin-top: 4px; }

        .table-footer { padding: 12px 16px; color: #475569; font-size: 13px; border-top: 1px solid #1e1e30; }
    </style>
</head>
<body>

<!-- NAV -->
<nav>
    <span class="nav-brand"><i class="fas fa-graduation-cap"></i>Gestion Etudiants</span>
    <a href="?mode=liste" class="nav-link <?= $mode === 'liste' ? 'active' : '' ?>">
        <i class="fas fa-list"></i> Etudiants
    </a>
    <a href="?mode=ajouter" class="nav-link <?= $mode === 'ajouter' ? 'active' : '' ?>">
        <i class="fas fa-plus"></i> Ajouter
    </a>
    <a href="?mode=dashboard" class="nav-link <?= $mode === 'dashboard' ? 'active' : '' ?>">
        <i class="fas fa-chart-bar"></i> Dashboard
    </a>
</nav>

<div class="container">

<?php if ($message): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<?php if ($erreur): ?>
<div class="alert alert-danger">
    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($erreur) ?>
</div>
<?php endif; ?>


<?php if ($mode === 'dashboard'): ?>
<!-- ══════════════════════════════════════════ -->
<!-- DASHBOARD                                  -->
<!-- ══════════════════════════════════════════ -->
<div class="page-header">
    <h1><i class="fas fa-chart-bar"></i> Dashboard</h1>
</div>

<div class="stats">
    <div class="stat-card">
        <i class="fas fa-users" style="color:#06b6d4;"></i>
        <div class="stat-number" style="color:#06b6d4;"><?= $total ?></div>
        <div class="stat-label">Total etudiants</div>
    </div>
    <div class="stat-card">
        <i class="fas fa-user-check" style="color:#10b981;"></i>
        <div class="stat-number" style="color:#10b981;"><?= $actifs ?></div>
        <div class="stat-label">Etudiants actifs</div>
    </div>
    <div class="stat-card">
        <i class="fas fa-user-times" style="color:#f59e0b;"></i>
        <div class="stat-number" style="color:#f59e0b;"><?= $total - $actifs ?></div>
        <div class="stat-label">Etudiants inactifs</div>
    </div>
</div>

<h2 style="color:#94a3b8; font-size:15px; margin-bottom:12px; text-transform:uppercase; letter-spacing:1px;">
    <i class="fas fa-layer-group" style="color:#7c3aed;"></i> Repartition par niveau
</h2>
<div class="niveau-grid">
    <?php foreach ($niveaux as $n): ?>
    <div class="niveau-card">
        <div class="nb"><?= $n['nb'] ?></div>
        <div class="lv"><?= htmlspecialchars($n['niveau']) ?></div>
    </div>
    <?php endforeach; ?>
</div>


<?php elseif ($mode === 'ajouter'): ?>
<!-- ══════════════════════════════════════════ -->
<!-- FORMULAIRE AJOUTER                         -->
<!-- ══════════════════════════════════════════ -->
<div class="page-header">
    <h1><i class="fas fa-user-plus"></i> Ajouter un etudiant</h1>
    <a href="?mode=liste" class="btn btn-ghost">
        <i class="fas fa-arrow-left"></i> Retour
    </a>
</div>

<div class="form-card">
    <form method="POST">
        <input type="hidden" name="action_ajouter" value="1">
        <div class="form-grid">
            <div class="form-group">
                <label><i class="fas fa-id-badge"></i> Matricule *</label>
                <input type="text" name="matricule" placeholder="ETU-2025-001"
                       value="<?= htmlspecialchars($_POST['matricule'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label><i class="fas fa-layer-group"></i> Niveau</label>
                <select name="niveau">
                    <?php foreach (['L1','L2','L3','M1','M2'] as $n): ?>
                    <option value="<?= $n ?>" <?= ($_POST['niveau'] ?? 'L1') === $n ? 'selected' : '' ?>><?= $n ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label><i class="fas fa-user"></i> Nom *</label>
                <input type="text" name="nom"
                       value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label><i class="fas fa-user"></i> Prenom *</label>
                <input type="text" name="prenom"
                       value="<?= htmlspecialchars($_POST['prenom'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label><i class="fas fa-envelope"></i> Email</label>
                <input type="email" name="email"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label><i class="fas fa-phone"></i> Telephone</label>
                <input type="text" name="telephone"
                       value="<?= htmlspecialchars($_POST['telephone'] ?? '') ?>">
            </div>
            <div class="form-group full">
                <label><i class="fas fa-code-branch"></i> Filiere</label>
                <input type="text" name="filiere" placeholder="Informatique"
                       value="<?= htmlspecialchars($_POST['filiere'] ?? '') ?>">
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-success">
                <i class="fas fa-save"></i> Enregistrer
            </button>
            <a href="?mode=liste" class="btn btn-ghost">
                <i class="fas fa-times"></i> Annuler
            </a>
        </div>
    </form>
</div>


<?php elseif ($mode === 'modifier' && $etudiantEdit): ?>
<!-- ══════════════════════════════════════════ -->
<!-- FORMULAIRE MODIFIER                        -->
<!-- ══════════════════════════════════════════ -->
<div class="page-header">
    <h1><i class="fas fa-user-edit"></i> Modifier un etudiant</h1>
    <a href="?mode=liste" class="btn btn-ghost">
        <i class="fas fa-arrow-left"></i> Retour
    </a>
</div>

<div class="form-card">
    <form method="POST">
        <input type="hidden" name="action_modifier" value="1">
        <input type="hidden" name="id" value="<?= $etudiantEdit['id'] ?>">
        <div class="form-grid">
            <div class="form-group">
                <label><i class="fas fa-id-badge"></i> Matricule</label>
                <input type="text" value="<?= htmlspecialchars($etudiantEdit['matricule']) ?>" disabled>
            </div>
            <div class="form-group">
                <label><i class="fas fa-layer-group"></i> Niveau</label>
                <select name="niveau">
                    <?php
                    $currentNiveau = $_POST['niveau'] ?? $etudiantEdit['niveau'];
                    foreach (['L1','L2','L3','M1','M2'] as $n):
                    ?>
                    <option value="<?= $n ?>" <?= $currentNiveau === $n ? 'selected' : '' ?>><?= $n ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label><i class="fas fa-user"></i> Nom *</label>
                <input type="text" name="nom"
                       value="<?= htmlspecialchars($_POST['nom'] ?? $etudiantEdit['nom']) ?>" required>
            </div>
            <div class="form-group">
                <label><i class="fas fa-user"></i> Prenom *</label>
                <input type="text" name="prenom"
                       value="<?= htmlspecialchars($_POST['prenom'] ?? $etudiantEdit['prenom']) ?>" required>
            </div>
            <div class="form-group">
                <label><i class="fas fa-envelope"></i> Email</label>
                <input type="email" name="email"
                       value="<?= htmlspecialchars($_POST['email'] ?? $etudiantEdit['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label><i class="fas fa-phone"></i> Telephone</label>
                <input type="text" name="telephone"
                       value="<?= htmlspecialchars($_POST['telephone'] ?? $etudiantEdit['telephone'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label><i class="fas fa-code-branch"></i> Filiere</label>
                <input type="text" name="filiere"
                       value="<?= htmlspecialchars($_POST['filiere'] ?? $etudiantEdit['filiere'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label><i class="fas fa-toggle-on"></i> Statut</label>
                <select name="statut">
                    <?php
                    $currentStatut = $_POST['statut'] ?? $etudiantEdit['statut'];
                    foreach (['actif' => 'Actif', 'inactif' => 'Inactif'] as $val => $label):
                    ?>
                    <option value="<?= $val ?>" <?= $currentStatut === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-success">
                <i class="fas fa-save"></i> Enregistrer
            </button>
            <a href="?mode=liste" class="btn btn-ghost">
                <i class="fas fa-times"></i> Annuler
            </a>
        </div>
    </form>
</div>


<?php else: ?>
<!-- ══════════════════════════════════════════ -->
<!-- LISTE                                      -->
<!-- ══════════════════════════════════════════ -->
<div class="page-header">
    <h1><i class="fas fa-users"></i> Liste des etudiants</h1>
    <a href="?mode=ajouter" class="btn btn-primary">
        <i class="fas fa-plus"></i> Ajouter
    </a>
</div>

<form method="GET" class="search-bar">
    <input type="hidden" name="mode" value="liste">
    <input type="text" name="search"
           placeholder="Rechercher par nom, prenom ou matricule..."
           value="<?= htmlspecialchars($search) ?>">
    <button type="submit" class="btn btn-primary">
        <i class="fas fa-search"></i> Chercher
    </button>
    <?php if ($search): ?>
    <a href="?mode=liste" class="btn btn-ghost">
        <i class="fas fa-times"></i> Effacer
    </a>
    <?php endif; ?>
</form>

<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th><i class="fas fa-id-badge"></i> Matricule</th>
                <th><i class="fas fa-user"></i> Nom</th>
                <th><i class="fas fa-user"></i> Prenom</th>
                <th><i class="fas fa-envelope"></i> Email</th>
                <th><i class="fas fa-layer-group"></i> Niveau</th>
                <th><i class="fas fa-code-branch"></i> Filiere</th>
                <th><i class="fas fa-toggle-on"></i> Statut</th>
                <th><i class="fas fa-cog"></i> Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($etudiants)): ?>
            <tr class="empty-row">
                <td colspan="8">
                    <i class="fas fa-inbox" style="font-size:32px; display:block; margin-bottom:10px;"></i>
                    Aucun etudiant trouvé.
                </td>
            </tr>
            <?php else: ?>
            <?php foreach ($etudiants as $e): ?>
            <tr>
                <td><?= htmlspecialchars($e['matricule']) ?></td>
                <td><strong><?= htmlspecialchars($e['nom']) ?></strong></td>
                <td><?= htmlspecialchars($e['prenom']) ?></td>
                <td><?= htmlspecialchars($e['email'] ?? '-') ?></td>
                <td><span class="badge badge-niveau"><?= htmlspecialchars($e['niveau']) ?></span></td>
                <td><?= htmlspecialchars($e['filiere'] ?? '-') ?></td>
                <td>
                    <span class="badge badge-<?= $e['statut'] ?>">
                        <i class="fas fa-circle" style="font-size:8px;"></i>
                        <?= $e['statut'] ?>
                    </span>
                </td>
                <td>
                    <div class="actions">
                        <a href="?mode=modifier&id=<?= $e['id'] ?>" class="btn btn-warning btn-sm">
                            <i class="fas fa-edit"></i> Modifier
                        </a>
                        <a href="?mode=supprimer&id=<?= $e['id'] ?>"
                           class="btn btn-danger btn-sm"
                           onclick="return confirm('Supprimer <?= htmlspecialchars($e['prenom'].' '.$e['nom'], ENT_QUOTES) ?> ?')">
                            <i class="fas fa-trash"></i> Supprimer
                        </a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <div class="table-footer">
        <i class="fas fa-info-circle"></i>
        <?= count($etudiants) ?> etudiant(s)
        <?= $search ? "pour la recherche \"" . htmlspecialchars($search) . "\"" : "au total" ?>
    </div>
</div>

<?php endif; ?>
</div>
</body>
</html>