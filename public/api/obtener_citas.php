<?php
/**
 * API Endpoint para obtener las horas ocupadas de una fecha específica
 */

declare(strict_types=1);

// Ocultar errores en la salida para no revelar rutas del servidor en producción
ini_set('display_errors', '0');
error_reporting(0);

// Importar credenciales unificadas 
require_once __DIR__ . '/config.php';

// Autonomía CORS: Permite origen local en desarrollo, o el dominio estricto en producción
$origenPermitido = getenv('APP_URL') ?: '*';
header("Access-Control-Allow-Origin: {$origenPermitido}");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=utf-8");

// Responder a la solicitud de preflight (CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Conexión estandarizada usando PDO
    $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $fechaParam = trim($_GET['fecha'] ?? '');

    if (empty($fechaParam)) {
        echo json_encode(["exito" => true, "horasOcupadas" => []]);
        exit();
    }

    // 1. Convertir formato de fecha si viene en español ("24 de agosto de 2026")
    $fechaDB = $fechaParam;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaParam)) {
        $mesesES = [
            'enero' => '01', 'febrero' => '02', 'marzo' => '03', 'abril' => '04',
            'mayo' => '05', 'junio' => '06', 'julio' => '07', 'agosto' => '08',
            'septiembre' => '09', 'octubre' => '10', 'noviembre' => '11', 'diciembre' => '12'
        ];
        
        // CORRECCIÓN: Se agregó el modificador 'u' al final del Regex para soporte UTF-8 seguro
        if (preg_match('/(\d{1,2})\s+de\s+([a-záéíóú]+)\s+de\s+(\d{4})/iu', mb_strtolower($fechaParam, 'UTF-8'), $matches)) {
            $dia = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $mesNombre = $matches[2];
            $anio = $matches[3];
            if (isset($mesesES[$mesNombre])) {
                $fechaDB = "$anio-{$mesesES[$mesNombre]}-$dia";
            }
        }
    }

    // 2. Consultar horas ocupadas de forma segura (Previene SQL Injection)
    $stmt = $pdo->prepare("SELECT hora_cita FROM citas_demostracion WHERE fecha_cita = :fecha");
    $stmt->execute([':fecha' => $fechaDB]);
    $resultados = $stmt->fetchAll();

    $horasOcupadas = [];
    foreach ($resultados as $row) {
        $hora24 = $row['hora_cita']; 
        
        // 3. Convertir de 24h a 12h para coincidir con la UI
        $timestamp = strtotime($hora24);
        if ($timestamp !== false) {
            $horasOcupadas[] = date("g:i A", $timestamp); 
        } else {
            $horasOcupadas[] = trim($hora24);
        }
    }

    echo json_encode([
        "exito" => true, 
        "fechaRecibida" => $fechaParam,
        "fechaConvertidaDB" => $fechaDB,
        "horasOcupadas" => $horasOcupadas
    ], JSON_UNESCAPED_UNICODE);

// CORRECCIÓN: Throwable atrapa Excepciones y Errores Fatales de PHP 7+
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "exito" => false, 
        "horasOcupadas" => [], 
        "error" => "Error interno del servidor al consultar disponibilidad."
    ]);
}