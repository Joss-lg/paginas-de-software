<?php
/**
 * API Endpoint para registrar citas de demostración y encolar notificación
 * @author Ollintem
 */

declare(strict_types=1);

// 1. Ocultar errores en la salida para no revelar rutas/datos del servidor
ini_set('display_errors', '0');
error_reporting(0);

require_once __DIR__ . '/config.php';

// 2. Autonomía CORS dinámica
$origenPermitido = getenv('APP_URL') ?: '*';
header("Access-Control-Allow-Origin: {$origenPermitido}");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=utf-8");

// Responder a preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['exito' => false, 'mensaje' => 'Método no permitido. Utilice POST.']);
    exit;
}

function convertirFechaEspanolASql(string $fechaTexto): string {
    $meses = [
        'enero' => '01', 'febrero' => '02', 'marzo' => '03', 'abril' => '04',
        'mayo' => '05', 'junio' => '06', 'julio' => '07', 'agosto' => '08',
        'septiembre' => '09', 'octubre' => '10', 'noviembre' => '11', 'diciembre' => '12'
    ];
    
    $textoLower = mb_strtolower(trim($fechaTexto), 'UTF-8');
    if (preg_match('/(\d{1,2})\s+de\s+([a-záéíóú]+)\s+de\s+(\d{4})/iu', $textoLower, $coincidencias)) {
        $dia = str_pad($coincidencias[1], 2, '0', STR_PAD_LEFT);
        $mesNombre = $coincidencias[2];
        $anio = $coincidencias[3];
        if (isset($meses[$mesNombre])) {
            return "{$anio}-{$meses[$mesNombre]}-{$dia}";
        }
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaTexto)) {
        return $fechaTexto;
    }

    return date('Y-m-d');
}

try {
    $opciones = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, $opciones);

    $inputJSON = file_get_contents('php://input');
    $datos = json_decode($inputJSON, true);

    if (json_last_error() !== JSON_ERROR_NONE || empty($datos)) {
        throw new InvalidArgumentException('El formato de los datos enviados no es válido.');
    }

    $nombre = trim($datos['nombreContratista'] ?? '');
    $producto = trim($datos['productoInteres'] ?? '');
    $correo = filter_var(trim($datos['correoElectronico'] ?? ''), FILTER_SANITIZE_EMAIL);
    $telefono = trim($datos['telefonoContacto'] ?? '');
    $fechaTexto = trim($datos['fechaCita'] ?? ''); 
    $horaTexto = trim($datos['horaCita'] ?? '');    

    if (empty($nombre) || empty($producto) || empty($correo) || empty($telefono) || empty($fechaTexto) || empty($horaTexto)) {
        throw new InvalidArgumentException('Todos los campos son obligatorios.');
    }

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('El correo electrónico proporcionado no es válido.');
    }

    $fechaSql = convertirFechaEspanolASql($fechaTexto); 
    $horaSql = date('H:i:s', strtotime($horaTexto));

    $pdo->beginTransaction();

    $sqlCita = "INSERT INTO citas_demostracion 
                (nombre_contratista, producto_interes, correo_electronico, telefono_contacto, fecha_cita, hora_cita, estado) 
                VALUES 
                (:nombre, :producto, :correo, :telefono, :fecha, :hora, 'pendiente')";
            
    $stmtCita = $pdo->prepare($sqlCita);
    $stmtCita->execute([
        ':nombre'   => $nombre,
        ':producto' => $producto,
        ':correo'   => $correo,
        ':telefono' => $telefono,
        ':fecha'    => $fechaSql,
        ':hora'     => $horaSql
    ]);

    $idCita = $pdo->lastInsertId();

    // 3. CORRECCIÓN: Usar variable centralizada en lugar de hardcodear el correo
    $asuntoOperador = "Nueva Solicitud de Demo: " . $nombre;
    
    $cuerpoOperador = "
    <!DOCTYPE html>
    <html lang='es'>
    <head>
        <meta charset='UTF-8'>
    </head>
    <body style='margin: 0; padding: 20px; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif;'>
        <table align='center' border='0' cellpadding='0' cellspacing='0' width='100%' style='max-width: 560px; background-color: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);'>
            <!-- Encabezado -->
            <tr>
                <td style='background-color: #0f172a; padding: 24px; text-align: left;'>
                    <h1 style='color: #ffffff; margin: 0; font-size: 18px; font-weight: 600; letter-spacing: 0.5px;'>Ollintem</h1>
                    <p style='color: #94a3b8; margin: 4px 0 0 0; font-size: 13px;'>Notificación de Sistema • Cita de Demostración</p>
                </td>
            </tr>
            <!-- Contenido -->
            <tr>
                <td style='padding: 24px;'>
                    <h2 style='color: #1e293b; margin: 0 0 16px 0; font-size: 16px; font-weight: 600;'>Detalles de la Solicitud</h2>
                    
                    <table border='0' cellpadding='0' cellspacing='0' width='100%' style='font-size: 14px; border-collapse: collapse;'>
                        <tr>
                            <td style='padding: 10px 12px; background-color: #f8fafc; font-weight: 600; color: #475569; width: 40%; border-bottom: 1px solid #edf2f7;'>Cliente / Empresa</td>
                            <td style='padding: 10px 12px; background-color: #f8fafc; color: #0f172a; border-bottom: 1px solid #edf2f7;'>{$nombre}</td>
                        </tr>
                        <tr>
                            <td style='padding: 10px 12px; font-weight: 600; color: #475569; border-bottom: 1px solid #edf2f7;'>Software / Producto</td>
                            <td style='padding: 10px 12px; color: #0f172a; border-bottom: 1px solid #edf2f7;'>{$producto}</td>
                        </tr>
                        <tr>
                            <td style='padding: 10px 12px; background-color: #f8fafc; font-weight: 600; color: #475569; border-bottom: 1px solid #edf2f7;'>Correo Contacto</td>
                            <td style='padding: 10px 12px; background-color: #f8fafc; color: #2563eb; border-bottom: 1px solid #edf2f7;'>{$correo}</td>
                        </tr>
                        <tr>
                            <td style='padding: 10px 12px; font-weight: 600; color: #475569; border-bottom: 1px solid #edf2f7;'>Teléfono</td>
                            <td style='padding: 10px 12px; color: #0f172a; border-bottom: 1px solid #edf2f7;'>{$telefono}</td>
                        </tr>
                        <tr>
                            <td style='padding: 10px 12px; background-color: #f8fafc; font-weight: 600; color: #475569; border-bottom: 1px solid #edf2f7;'>Fecha Agendada</td>
                            <td style='padding: 10px 12px; background-color: #f8fafc; color: #0f172a; border-bottom: 1px solid #edf2f7;'>{$fechaSql}</td>
                        </tr>
                        <tr>
                            <td style='padding: 10px 12px; font-weight: 600; color: #475569;'>Hora Agendada</td>
                            <td style='padding: 10px 12px; color: #0f172a;'>{$horaSql} hrs</td>
                        </tr>
                    </table>
                </td>
            </tr>
            <!-- Pie de Página -->
            <tr>
                <td style='background-color: #f8fafc; padding: 16px 24px; border-top: 1px solid #e2e8f0; text-align: center; font-size: 12px; color: #64748b;'>
                    Este mensaje se generó automáticamente al registrar una nueva cita en la plataforma.
                </td>
            </tr>
        </table>
    </body>
    </html>";

    $sqlCorreo = "INSERT INTO correos_pendientes 
                  (cita_id, destinatario, asunto, cuerpo, estado, intentos, creado_en) 
                  VALUES 
                  (:cita_id, :destinatario, :asunto, :cuerpo, 'pendiente', 0, NOW())";

    $stmtCorreo = $pdo->prepare($sqlCorreo);
    $stmtCorreo->execute([
        ':cita_id'      => $idCita,
        ':destinatario' => $correoAdmin, // Usando variable de config.php
        ':asunto'       => $asuntoOperador,
        ':cuerpo'       => $cuerpoOperador
    ]);

    $pdo->commit();

    http_response_code(201);
    echo json_encode([
        'exito' => true,
        'mensaje' => 'La cita se ha registrado correctamente y el correo ha sido encolado.',
        'id_cita' => $idCita
    ]);

} catch (InvalidArgumentException $e) {
    // 4. Errores de validación sí se pueden mostrar al usuario
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['exito' => false, 'mensaje' => $e->getMessage()]);

} catch (\Throwable $e) {
    // 5. CORRECCIÓN: Agrupa PDOException y Exception/Error. 
    // Oculta el mensaje real (ej. $e->getMessage()) para no exponer BD o Servidor.
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    // En producción podrías usar error_log($e->getMessage()); para ver el error en el servidor
    echo json_encode([
        'exito' => false, 
        'mensaje' => 'Ocurrió un error interno al procesar la cita. Por favor, inténtelo más tarde.'
    ]);
}