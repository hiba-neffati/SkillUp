<?php
session_start();

define('DB_HOST', 'localhost');
define('DB_NAME', 'skillup');
define('DB_USER', 'root');
define('DB_PASS', '');
define('UPLOAD_DIR', __DIR__ . '/../uploads/cours/');

try {
    $db = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die('Erreur de connexion : ' . $e->getMessage());
}

// ========== FONCTIONS D'AUTHENTIFICATION ==========

function estConnecte() {
    return isset($_SESSION['user_id']);
}

function estAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function exigerConnecte() {
    if (!estConnecte()) {
        redirect('login.php');
    }
}

function exigerAdmin() {
    if (!estAdmin()) {
        redirect('../index.php');
    }
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

// ========== FONCTIONS UTILITAIRES ==========

function nettoyer($texte) {
    return htmlspecialchars($texte, ENT_QUOTES, 'UTF-8');
}

?>
