<?php
// controllers/pacientes_api.php - API para gestión de pacientes

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

if (ob_get_level()) ob_end_clean();
ob_start();

session_start();
require_once __DIR__ . '/../config/db.php';

function json_out($data, $code = 200) {
    if (ob_get_level()) ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Verificar que sea staff
if (empty($_SESSION['Id_usuario'])) {
    json_out(['ok' => false, 'error' => 'No autorizado'], 403);
}

$rol = $_SESSION['Rol'] ?? '';
if ($rol !== 'medico' && $rol !== 'secretaria') {
    json_out(['ok' => false, 'error' => 'No autorizado'], 403);
}

try {
    $pdo = db();
} catch (Throwable $e) {
    error_log('Database error: ' . $e->getMessage());
    json_out(['ok' => false, 'error' => 'Error de conexión'], 500);
}

$action = $_GET['action'] ?? '';

// ========== LISTAR PACIENTES ==========
if ($action === 'list') {
    try {
        $stmt = $pdo->query("
            SELECT 
                p.Id_paciente,
                p.Activo as activo,
                p.Nro_carnet as nro_carnet,
                p.Libreta_sanitaria as libreta_sanitaria,
                u.Nombre,
                u.Apellido,
                u.dni,
                u.email,
                os.Nombre as obra_social,
                COUNT(DISTINCT t.Id_turno) as total_turnos
            FROM paciente p
            JOIN usuario u ON u.Id_usuario = p.Id_usuario
            LEFT JOIN obra_social os ON os.Id_obra_social = p.Id_obra_social
            LEFT JOIN turno t ON t.Id_paciente = p.Id_paciente
            GROUP BY p.Id_paciente
            ORDER BY u.Apellido, u.Nombre
        ");
        
        $pacientes = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $pacientes[] = [
                'Id_paciente' => (int)$row['Id_paciente'],
                'nombre_completo' => trim($row['Apellido'] . ', ' . $row['Nombre']),
                'dni' => $row['dni'],
                'email' => $row['email'],
                'obra_social' => $row['obra_social'],
                'nro_carnet' => $row['nro_carnet'],
                'libreta_sanitaria' => $row['libreta_sanitaria'],
                'activo' => (bool)$row['activo'],
                'total_turnos' => (int)$row['total_turnos']
            ];
        }
        
        json_out(['ok' => true, 'pacientes' => $pacientes]);
        
    } catch (Throwable $e) {
        error_log('Error listing patients: ' . $e->getMessage());
        json_out(['ok' => false, 'error' => 'Error al cargar pacientes'], 500);
    }
}

// ========== HISTORIAL CLÍNICO ==========
if ($action === 'historial') {
    try {
        $pacienteId = (int)($_GET['paciente_id'] ?? 0);
        
        if ($pacienteId <= 0) {
            json_out(['ok' => false, 'error' => 'ID de paciente inválido'], 400);
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                t.Fecha as fecha,
                CONCAT(um.Apellido, ', ', um.Nombre) as medico,
                e.Nombre as especialidad,
                d.Diagnostico as diagnostico,
                d.Sintomas as sintomas,
                d.Observaciones as observaciones,
                r.Medicamentos as medicamentos,
                r.Duracion_Tratamiento as duracion
            FROM turno t
            JOIN medico m ON m.Id_medico = t.Id_medico
            JOIN usuario um ON um.Id_usuario = m.Id_usuario
            JOIN especialidad e ON e.Id_Especialidad = m.Id_Especialidad
            LEFT JOIN diagnostico d ON d.Id_turno = t.Id_turno
            LEFT JOIN receta r ON r.Id_diagnostico = d.Id_diagnostico
            WHERE t.Id_paciente = ?
                AND t.Atendido = 1
            ORDER BY t.Fecha DESC
            LIMIT 50
        ");
        
        $stmt->execute([$pacienteId]);
        
        $historial = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $historial[] = [
                'fecha' => $row['fecha'],
                'medico' => $row['medico'],
                'especialidad' => $row['especialidad'],
                'diagnostico' => $row['diagnostico'],
                'sintomas' => $row['sintomas'],
                'observaciones' => $row['observaciones'],
                'medicamentos' => $row['medicamentos'],
                'duracion' => $row['duracion']
            ];
        }
        
        json_out(['ok' => true, 'historial' => $historial]);
        
    } catch (Throwable $e) {
        error_log('Error loading patient history: ' . $e->getMessage());
        json_out(['ok' => false, 'error' => 'Error al cargar historial'], 500);
    }
}

// Acción no soportada
json_out(['ok' => false, 'error' => 'Acción no soportada'], 400);