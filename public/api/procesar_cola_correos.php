<?php
/**
 * Worker en segundo plano para procesar la cola de correos
 * @author Ollintem
 */

declare(strict_types=1);

// Opcional: Asegurar que el script solo se ejecute por consola (Cron Job) o mediante un token seguro
// if (php_sapi_name() !== 'cli' && ($_GET['token'] ?? '') !== 'TU_TOKEN_SECRETO') {
//     http_response_code(403);
//     exit("Acceso denegado.");
// }

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

// Ocultar errores en salida para producción
ini_set('display_errors', '0');
error_reporting(0);

require __DIR__ . '/mail/Exception.php';
require __DIR__ . '/mail/PHPMailer.php';
require __DIR__ . '/mail/SMTP.php';
require_once __DIR__ . '/config.php';

try {
    $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Obtener hasta 5 correos pendientes con menos de 3 intentos
    $stmt = $pdo->query("SELECT * FROM correos_pendientes WHERE estado = 'pendiente' AND intentos < 3 LIMIT 5");
    $correos = $stmt->fetchAll();

    if (empty($correos)) {
        exit("No hay correos pendientes por enviar.\n");
    }

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = $smtpHost;
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtpUser;
    $mail->Password   = $smtpPass;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = $smtpPort;

    // Configuración indispensable para renderizar HTML y tildes correctamente
    $mail->CharSet    = 'UTF-8';
    $mail->isHTML(true);

    // Remitente estandarizado
    $mail->setFrom($smtpUser, 'Ollintem');

    foreach ($correos as $row) {
        try {
            $mail->clearAddresses();
            $mail->addAddress($row['destinatario']);
            $mail->Subject = $row['asunto'];
            $mail->Body    = $row['cuerpo'];

            $mail->send();

            // Marcar como enviado
            $update = $pdo->prepare("UPDATE correos_pendientes SET estado = 'enviado', enviado_en = NOW() WHERE id = :id");
            $update->execute([':id' => $row['id']]);

            echo "Correo #{$row['id']} enviado correctamente a {$row['destinatario']}\n";

        } catch (PHPMailerException $e) {
            // Incrementar intentos y registrar error limpiando caracteres extraños si es necesario
            $errorInfo = mb_substr($mail->ErrorInfo, 0, 255, 'UTF-8');
            $update = $pdo->prepare("UPDATE correos_pendientes SET intentos = intentos + 1, ultimo_error = :error WHERE id = :id");
            $update->execute([
                ':error' => $errorInfo,
                ':id'    => $row['id']
            ]);

            echo "Error al enviar correo #{$row['id']}: {$errorInfo}\n";
        }
    }

} catch (\Throwable $e) {
    echo "Error general en el worker: " . $e->getMessage() . "\n";
}