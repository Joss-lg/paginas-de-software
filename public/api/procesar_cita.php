<?php
/**
 * API Endpoint para registrar citas de demostración
 * @author Ollintem
 */

declare(strict_types=1);

// Configuración de cabeceras para admitir peticiones JSON y prevenir problemas de CORS
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Validar que la petición sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['exito' => false, 'mensaje' => 'Método no permitido. Utilice POST.']);
    exit;
}

// Configuración de la base de datos
$dbHost = 'localhost';
$dbName = 'ollintem_db';
$dbUser = 'root'; // Cambiar en producción
$dbPass = '';     // Cambiar en producción

try {
    // Inicializar conexión PDO con manejo estricto de excepciones
    $opciones = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, $opciones);

    // Capturar el cuerpo de la petición en formato JSON
    $inputJSON = file_get_contents('php://input');
    $datos = json_decode($inputJSON, true);

    if (json_last_error() !== JSON_ERROR_NONE || empty($datos)) {
        throw new InvalidArgumentException('El formato de los datos enviados no es válido.');
    }

    // Extraer y sanitizar datos básicos
    $nombre = trim($datos['nombreContratista'] ?? '');
    $producto = trim($datos['productoInteres'] ?? '');
    $correo = filter_var(trim($datos['correoElectronico'] ?? ''), FILTER_SANITIZE_EMAIL);
    $telefono = trim($datos['telefonoContacto'] ?? '');
    $fechaTexto = trim($datos['fechaCita'] ?? ''); // Ej: "12 de octubre de 2026"
    $horaTexto = trim($datos['horaCita'] ?? '');   // Ej: "10:00 AM"

    // Validar campos obligatorios
    if (empty($nombre) || empty($producto) || empty($correo) || empty($telefono) || empty($fechaTexto) || empty($horaTexto)) {
        throw new InvalidArgumentException('Todos los campos son obligatorios.');
    }

    // Validar formato de correo
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('El correo electrónico proporcionado no es válido.');
    }

    // Transformar fecha y hora de formato texto al formato estándar de SQL (YYYY-MM-DD / HH:MM:SS)
    // Nota: Dependiendo de cómo envíes la fecha desde JS, podrías necesitar una conversión. 
    // Para este ejemplo, asumiremos que desde Astro (JS) enviaremos un formato ISO estandarizado al servidor.
    $fechaSql = date('Y-m-d', strtotime(str_replace(' de ', ' ', $fechaTexto))); 
    $horaSql = date('H:i:s', strtotime($horaTexto));

    // Preparar la consulta SQL
    $sql = "INSERT INTO citas_demostracion 
            (nombre_contratista, producto_interes, correo_electronico, telefono_contacto, fecha_cita, hora_cita) 
            VALUES 
            (:nombre, :producto, :correo, :telefono, :fecha, :hora)";
            
    $stmt = $pdo->prepare($sql);
    
    // Ejecutar la inserción vinculando los parámetros de forma segura
    $stmt->execute([
        ':nombre' => $nombre,
        ':producto' => $producto,
        ':correo' => $correo,
        ':telefono' => $telefono,
        ':fecha' => $fechaSql,
        ':hora' => $horaSql
    ]);

    // Respuesta de éxito
    http_response_code(201); // 201 Created
    echo json_encode([
        'exito' => true,
        'mensaje' => 'La cita se ha registrado correctamente.',
        'id_cita' => $pdo->lastInsertId()
    ]);

} catch (InvalidArgumentException $e) {
    // Error de validación del cliente
    http_response_code(400); // 400 Bad Request
    echo json_encode(['exito' => false, 'mensaje' => $e->getMessage()]);

} catch (PDOException $e) {
    // Error de base de datos
    http_response_code(500); // 500 Internal Server Error
    // En producción, evita enviar $e->getMessage() para no exponer detalles de la BD
    echo json_encode(['exito' => false, 'mensaje' => 'Ocurrió un error interno al procesar la cita.']);

} catch (Exception $e) {
    // Error general
    http_response_code(500);
    echo json_encode(['exito' => false, 'mensaje' => 'Ocurrió un error inesperado.']);
}