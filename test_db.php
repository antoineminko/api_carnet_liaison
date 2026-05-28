<?php
$pdo = new PDO('mysql:host=mysql-sirh.alwaysdata.net;port=3306;dbname=sirh_carnet', 'sirh', 'Dev21!!21');
$stmt = $pdo->query('SELECT id, nom, prenom, fcm_token FROM parent_users');
$parents = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($parents as $p) {
    echo 'Parent ' . $p['id'] . ' (' . $p['prenom'] . ' ' . $p['nom'] . '): ' . (empty($p['fcm_token']) ? 'NO_TOKEN' : 'HAS_TOKEN') . "\n";
}
