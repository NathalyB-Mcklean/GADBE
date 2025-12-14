<?php
$password = 'MiPassword123';
$hash = password_hash($password, PASSWORD_DEFAULT);

// Conectar a la base de datos
$pdo = new PDO("mysql:host=localhost;dbname=bienestar_estudiantil", "root", "");

// Insertar admin
$stmt = $pdo->prepare("INSERT INTO usuarios (correo, password_hash, nombre, rol) VALUES (?, ?, ?, ?)");
$stmt->execute(['admin1@utp.ac.pa', $hash, 'Administrador', 'Administrador']);

echo "Administrador creado con contraseña: $password";
?>