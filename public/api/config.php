<?php
// api/config.php

// Detección automática del entorno (Local vs Producción)
$esLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1']) 
        || strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false;

if ($esLocal) {
    // Entorno de Desarrollo Local (XAMPP)
    $dbHost = 'localhost';
    $dbName = 'ollintem_db';
    $dbUser = 'root';
    $dbPass = '';
} else {
    // Entorno de Producción (Servidor Web / cPanel)
    $dbHost = getenv('DB_HOST') ?: 'localhost';
    $dbName = getenv('DB_NAME') ?: 'ollintem_db';
    $dbUser = getenv('DB_USER') ?: 'ollintem_user_prod'; // Cambiar por usuario real del servidor
    $dbPass = getenv('DB_PASS') ?: 'CONTRASEÑA_SEGURA_DB_PROD'; // Cambiar por contraseña real de BD
}

// Servidor SMTP de Correo (PHPMailer)
$smtpHost    = getenv('SMTP_HOST') ?: 'mail.ollintem.com.mx';
$smtpUser    = getenv('SMTP_USER') ?: 'saul@ollintem.com.mx';
$smtpPass    = getenv('SMTP_PASS') ?: '?J973w2wm';
$smtpPort    = (int)(getenv('SMTP_PORT') ?: 465); // SSL

// Correo del administrador
$correoAdmin = getenv('CORREO_ADMIN') ?: 'Mahoraga250.0@gmail.com';