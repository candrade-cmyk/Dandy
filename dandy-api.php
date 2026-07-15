<?php
/**
 * Endpoint para guardar y listar los reportes de visita de Dandy.
 * Sube este archivo al mismo directorio del servidor donde vive el .env
 * (junto a index.php). Requiere que el .env tenga una línea:
 *   DANDY_API_TOKEN=algo-largo-y-aleatorio
 * y que reporte-dandy.html use ese mismo valor en DANDY_API_TOKEN.
 */

header('Content-Type: application/json; charset=utf-8');

function loadEnv($path) {
    $vars = [];
    if (!is_readable($path)) return $vars;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $vars[trim($key)] = trim($value);
    }
    return $vars;
}

$env = loadEnv(__DIR__ . '/.env');

$token = $_SERVER['HTTP_X_DANDY_TOKEN'] ?? ($_GET['token'] ?? '');
if (empty($env['DANDY_API_TOKEN']) || !hash_equals($env['DANDY_API_TOKEN'], (string) $token)) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host={$env['DB_HOST']};dbname={$env['DB_NAME']};charset=utf8mb4",
        $env['DB_USER'],
        $env['DB_PASS'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de conexión a la base de datos']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    if (empty($data['institucion'])) {
        http_response_code(422);
        echo json_encode(['error' => 'institucion es requerida']);
        exit;
    }

    $stmt = $pdo->prepare('
        INSERT INTO `Dandy`
        (institucion, estudiantes, fecha_visita, contacto, acompanantes, nivel_riesgo,
         proforma_firmada, nota_academica, precio_cobro, num_pagos, observaciones, seguimiento, opinion)
        VALUES
        (:institucion, :estudiantes, :fecha_visita, :contacto, :acompanantes, :nivel_riesgo,
         :proforma_firmada, :nota_academica, :precio_cobro, :num_pagos, :observaciones, :seguimiento, :opinion)
    ');

    $stmt->execute([
        ':institucion'      => $data['institucion'],
        ':estudiantes'      => $data['estudiantes'] ?? 0,
        ':fecha_visita'     => $data['fecha_visita'] ?? null,
        ':contacto'         => $data['contacto'] ?? null,
        ':acompanantes'     => $data['acompanantes'] ?? null,
        ':nivel_riesgo'     => $data['nivel_riesgo'] ?? null,
        ':proforma_firmada' => !empty($data['proforma_firmada']) ? 1 : 0,
        ':nota_academica'   => !empty($data['nota_academica']) ? 1 : 0,
        ':precio_cobro'     => $data['precio_cobro'] ?? null,
        ':num_pagos'        => $data['num_pagos'] ?? null,
        ':observaciones'    => $data['observaciones'] ?? null,
        ':seguimiento'      => $data['seguimiento'] ?? null,
        ':opinion'          => $data['opinion'] ?? null,
    ]);

    echo json_encode(['ok' => true, 'id' => (int) $pdo->lastInsertId()]);
    exit;
}

if ($method === 'GET') {
    $stmt = $pdo->query('SELECT * FROM `Dandy` ORDER BY created_at DESC');
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);
