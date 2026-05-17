<?php
// ============================================
// Fonctions Métier du projet SkillUp
// ============================================

// ========== USERS ==========

function inscrireUtilisateur($nom, $email, $motdepasse) {
    global $db;
    
    $nom = nettoyer($nom);
    $email = nettoyer($email);
    
    // Vérifier si email existe
    $req = $db->prepare('SELECT id FROM users WHERE email = ?');
    $req->execute([$email]);
    if ($req->rowCount() > 0) {
        return ['success' => false, 'message' => 'Email déjà utilisé'];
    }
    
    // Hasher le mot de passe
    $hash = password_hash($motdepasse, PASSWORD_DEFAULT);
    
    // Insérer l'utilisateur
    $req = $db->prepare('INSERT INTO users (nom, email, mot_de_passe, role) VALUES (?, ?, ?, ?)');
    $req->execute([$nom, $email, $hash, 'user']);
    
    return ['success' => true, 'message' => 'Inscription réussie'];
}

function connecterUtilisateur($email, $motdepasse) {
    global $db;
    
    $email = nettoyer($email);
    
    $req = $db->prepare('SELECT id, nom, email, mot_de_passe, role FROM users WHERE email = ?');
    $req->execute([$email]);
    
    if ($req->rowCount() === 0) {
        return ['success' => false, 'message' => 'Email ou mot de passe incorrect'];
    }
    
    $user = $req->fetch(PDO::FETCH_ASSOC);
    
    if (!password_verify($motdepasse, $user['mot_de_passe'])) {
        return ['success' => false, 'message' => 'Email ou mot de passe incorrect'];
    }
    
    // Créer la session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['nom'] = $user['nom'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    
    return ['success' => true, 'message' => 'Connexion réussie'];
}

function deconnecterUtilisateur() {
    session_destroy();
    return true;
}

// ========== COURS ==========

function obtenirTousLesCours() {
    global $db;
    
    $req = $db->query('SELECT * FROM cours ORDER BY date_creation DESC');
    return $req->fetchAll(PDO::FETCH_ASSOC);
}

function obtenirCours($id) {
    global $db;
    
    $id = (int)$id;
    $req = $db->prepare('SELECT * FROM cours WHERE id = ?');
    $req->execute([$id]);
    
    return $req->fetch(PDO::FETCH_ASSOC);
}

function creerCours($titre, $description, $prix, $image_url) {
    global $db;
    
    $titre = nettoyer($titre);
    $description = nettoyer($description);
    $prix = (float)$prix;
    
    $req = $db->prepare('INSERT INTO cours (titre, description, prix, image_url) VALUES (?, ?, ?, ?)');
    $req->execute([$titre, $description, $prix, $image_url]);
    
    return $db->lastInsertId();
}

function modifierCours($id, $titre, $description, $prix, $image_url = null) {
    global $db;
    
    $id = (int)$id;
    $titre = nettoyer($titre);
    $description = nettoyer($description);
    $prix = (float)$prix;
    
    if ($image_url) {
        $req = $db->prepare('UPDATE cours SET titre = ?, description = ?, prix = ?, image_url = ? WHERE id = ?');
        $req->execute([$titre, $description, $prix, $image_url, $id]);
    } else {
        $req = $db->prepare('UPDATE cours SET titre = ?, description = ?, prix = ? WHERE id = ?');
        $req->execute([$titre, $description, $prix, $id]);
    }
    
    return true;
}

function supprimerCours($id) {
    global $db;
    
    $id = (int)$id;
    
    // Récupérer l'image pour la supprimer
    $cours = obtenirCours($id);
    if ($cours && file_exists(UPLOAD_DIR . $cours['image_url'])) {
        unlink(UPLOAD_DIR . $cours['image_url']);
    }
    
    // Supprimer les réservations associées
    $req = $db->prepare('DELETE FROM reservations WHERE cours_id = ?');
    $req->execute([$id]);
    
    // Supprimer le cours
    $req = $db->prepare('DELETE FROM cours WHERE id = ?');
    $req->execute([$id]);
    
    return true;
}

// ========== RÉSERVATIONS ==========

function creerReservation($user_id, $cours_id, $date, $heure) {
    global $db;
    
    $user_id = (int)$user_id;
    $cours_id = (int)$cours_id;
    $date = nettoyer($date);
    $heure = nettoyer($heure);
    
    // Vérifier que le créau n'existe pas déjà
    $req = $db->prepare('SELECT id FROM reservations WHERE cours_id = ? AND date_reservation = ? AND heure_reservation = ? AND statut != "annulée"');
    $req->execute([$cours_id, $date, $heure]);
    
    if ($req->rowCount() > 0) {
        return ['success' => false, 'message' => 'Ce créneau est déjà réservé'];
    }
    
    try {
        $req = $db->prepare('INSERT INTO reservations (user_id, cours_id, date_reservation, heure_reservation) VALUES (?, ?, ?, ?)');
        $req->execute([$user_id, $cours_id, $date, $heure]);
        return ['success' => true, 'message' => 'Réservation créée'];
    } catch(PDOException $e) {
        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

function obtenirReservationsUtilisateur($user_id) {
    global $db;
    
    $user_id = (int)$user_id;
    
    $req = $db->prepare('
        SELECT r.*, c.titre, c.prix, c.image_url
        FROM reservations r
        JOIN cours c ON r.cours_id = c.id
        WHERE r.user_id = ?
        ORDER BY r.date_reservation DESC
    ');
    $req->execute([$user_id]);
    
    return $req->fetchAll(PDO::FETCH_ASSOC);
}

function obtenirToutesLesReservations() {
    global $db;
    
    $req = $db->query('
        SELECT r.*, c.titre, c.prix, u.nom, u.email
        FROM reservations r
        JOIN cours c ON r.cours_id = c.id
        JOIN users u ON r.user_id = u.id
        ORDER BY r.date_commande DESC
    ');
    
    return $req->fetchAll(PDO::FETCH_ASSOC);
}

function changerStatutReservation($id, $statut) {
    global $db;
    
    $id = (int)$id;
    $statut_valides = ['en_attente', 'confirmée', 'annulée', 'terminée'];
    
    if (!in_array($statut, $statut_valides)) {
        return false;
    }
    
    $req = $db->prepare('UPDATE reservations SET statut = ? WHERE id = ?');
    $req->execute([$statut, $id]);
    
    return true;
}

// ========== STATISTIQUES ==========

function obtenirNombreTotalReservations() {
    global $db;
    
    $req = $db->query('SELECT COUNT(*) as total FROM reservations WHERE statut != "annulée"');
    $result = $req->fetch(PDO::FETCH_ASSOC);
    
    return $result['total'];
}

function obtenirChiffresAffaires() {
    global $db;
    
    $req = $db->query('
        SELECT SUM(c.prix) as total
        FROM reservations r
        JOIN cours c ON r.cours_id = c.id
        WHERE r.statut != "annulée"
    ');
    $result = $req->fetch(PDO::FETCH_ASSOC);
    
    return $result['total'] ?? 0;
}

function obtenirTop3Cours() {
    global $db;
    
    $req = $db->query('
        SELECT c.id, c.titre, COUNT(r.id) as nombre_reservations
        FROM cours c
        LEFT JOIN reservations r ON c.id = r.cours_id AND r.statut != "annulée"
        GROUP BY c.id
        ORDER BY nombre_reservations DESC
        LIMIT 3
    ');
    
    return $req->fetchAll(PDO::FETCH_ASSOC);
}

// ========== UPLOAD IMAGE ==========

function telechargerImage($fichier) {
    $extensions = ['jpg', 'jpeg', 'png', 'gif'];
    
    if (!isset($fichier['tmp_name']) || $fichier['error'] !== 0) {
        return ['success' => false, 'message' => 'Erreur lors du téléchargement'];
    }
    
    $nom_fichier = $fichier['name'];
    $extension = strtolower(pathinfo($nom_fichier, PATHINFO_EXTENSION));
    
    if (!in_array($extension, $extensions)) {
        return ['success' => false, 'message' => 'Extension non autorisée'];
    }
    
    if ($fichier['size'] > 5 * 1024 * 1024) { // 5MB max
        return ['success' => false, 'message' => 'Fichier trop volumineux'];
    }
    
    // Créer le répertoire s'il n'existe pas
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    
    // Générer un nom unique
    $nouveau_nom = time() . '_' . uniqid() . '.' . $extension;
    $chemin = UPLOAD_DIR . $nouveau_nom;
    
    if (move_uploaded_file($fichier['tmp_name'], $chemin)) {
        return ['success' => true, 'nom_fichier' => $nouveau_nom];
    } else {
        return ['success' => false, 'message' => 'Erreur lors du déplacement du fichier'];
    }
}

?>
