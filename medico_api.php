<?php
// views/pages/medico_api.php - API para panel médico
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

if (ob_get_level()) ob_end_clean();
ob_start();

session_start();
require_once __DIR__ . '/../../config/db.php';

function json_out($data, $code = 200) { 
  if (ob_get_level()) ob_end_clean();
  http_response_code($code); 
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE); 
  exit; 
}

function ensure_csrf() { 
  $t = $_POST['csrf_token'] ?? '';
  if (!$t || !hash_equals($_SESSION['csrf_token'] ?? '', $t)) {
    json_out(['ok' => false, 'error' => 'CSRF inválido'], 400); 
  }
}

function require_medico($pdo) {
  if (empty($_SESSION['Id_usuario']) || $_SESSION['Rol'] !== 'medico') {
    json_out(['ok' => false, 'error' => 'No autorizado'], 403);
  }
  
  $uid = (int)$_SESSION['Id_usuario'];
  $st = $pdo->prepare("SELECT Id_medico FROM medico WHERE Id_usuario = ? AND Activo = 1 LIMIT 1");
  $st->execute([$uid]);
  $medId = $st->fetchColumn();
  
  if (!$medId) {
    json_out(['ok' => false, 'error' => 'Médico no encontrado'], 404);
  }
  
  return (int)$medId;
}

try {
    $pdo = db();
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => 'Error de conexión'], 500);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$medicoId = require_medico($pdo);

// ========== ESTADÍSTICAS ==========
if ($action === 'stats') {
    try {
        $hoy = date('Y-m-d');
        
        // Turnos de hoy
        $st = $pdo->prepare("
            SELECT COUNT(*) FROM turno 
            WHERE Id_medico = ? AND DATE(Fecha) = ? 
            AND (Estado IS NULL OR Estado <> 'cancelado')
        ");
        $st->execute([$medicoId, $hoy]);
        $turnosHoy = (int)$st->fetchColumn();
        
        // Pendientes de atención hoy
        $st = $pdo->prepare("
            SELECT COUNT(*) FROM turno 
            WHERE Id_medico = ? AND DATE(Fecha) = ? 
            AND (Estado IS NULL OR Estado <> 'cancelado')
            AND Atendido = 0
        ");
        $st->execute([$medicoId, $hoy]);
        $pendientes = (int)$st->fetchColumn();
        
        // Atendidos hoy
        $st = $pdo->prepare("
            SELECT COUNT(*) FROM turno 
            WHERE Id_medico = ? AND DATE(Fecha) = ? AND Atendido = 1
        ");
        $st->execute([$medicoId, $hoy]);
        $atendidos = (int)$st->fetchColumn();
        
        // Esta semana
        $inicioSemana = date('Y-m-d', strtotime('monday this week'));
        $finSemana = date('Y-m-d', strtotime('sunday this week'));
        
        $st = $pdo->prepare("
            SELECT COUNT(*) FROM turno 
            WHERE Id_medico = ? 
            AND DATE(Fecha) BETWEEN ? AND ?
            AND (Estado IS NULL OR Estado <> 'cancelado')
        ");
        $st->execute([$medicoId, $inicioSemana, $finSemana]);
        $semana = (int)$st->fetchColumn();
        
        json_out([
            'ok' => true,
            'stats' => [
                'hoy' => $turnosHoy,
                'pendientes' => $pendientes,
                'atendidos' => $atendidos,
                'semana' => $semana
            ]
        ]);
        
    } catch (Throwable $e) {
        json_out(['ok' => false, 'error' => $e->getMessage()], 500);
    }
}

// ========== TURNOS DE HOY ==========
if ($action === 'turnos_hoy') {
    try {
        $hoy = date('Y-m-d');
        
        $st = $pdo->prepare("
            SELECT 
                t.Id_turno as id,
                t.Fecha as fecha,
                t.Atendido as atendido,
                t.Fecha_Atencion as fecha_atencion,
                p.Id_paciente as paciente_id,
                u.Nombre as paciente_nombre_prim,
                u.Apellido as paciente_apellido,
                u.dni as paciente_dni,
                os.Nombre as obra_social,
                p.Libreta_sanitaria as libreta
            FROM turno t
            JOIN paciente p ON p.Id_paciente = t.Id_paciente
            JOIN usuario u ON u.Id_usuario = p.Id_usuario
            LEFT JOIN obra_social os ON os.Id_obra_social = p.Id_obra_social
            WHERE t.Id_medico = ?
                AND DATE(t.Fecha) = ?
                AND (t.Estado IS NULL OR t.Estado <> 'cancelado')
            ORDER BY t.Fecha ASC
        ");
        $st->execute([$medicoId, $hoy]);
        
        $turnos = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $turnos[] = [
                'id' => (int)$row['id'],
                'fecha' => $row['fecha'],
                'atendido' => (bool)$row['atendido'],
                'fecha_atencion' => $row['fecha_atencion'],
                'paciente_id' => (int)$row['paciente_id'],
                'paciente_nombre' => trim($row['paciente_apellido'] . ', ' . $row['paciente_nombre_prim']),
                'paciente_dni' => $row['paciente_dni'],
                'obra_social' => $row['obra_social'],
                'libreta' => $row['libreta']
            ];
        }
        
        json_out(['ok' => true, 'turnos' => $turnos]);
        
    } catch (Throwable $e) {
        json_out(['ok' => false, 'error' => $e->getMessage()], 500);
    }
}

// ========== PRÓXIMOS TURNOS ==========
if ($action === 'turnos_proximos') {
    try {
        $fecha = $_GET['fecha'] ?? null;
        
        if ($fecha) {
            // Fecha específica
            $whereClause = "DATE(t.Fecha) = ?";
            $params = [$medicoId, $fecha];
        } else {
            // Próximos 7 días
            $hoy = date('Y-m-d');
            $fin = date('Y-m-d', strtotime('+7 days'));
            $whereClause = "DATE(t.Fecha) BETWEEN ? AND ?";
            $params = [$medicoId, $hoy, $fin];
        }
        
        $st = $pdo->prepare("
            SELECT 
                t.Id_turno as id,
                t.Fecha as fecha,
                t.Atendido as atendido,
                t.Fecha_Atencion as fecha_atencion,
                p.Id_paciente as paciente_id,
                u.Nombre as paciente_nombre_prim,
                u.Apellido as paciente_apellido,
                u.dni as paciente_dni,
                os.Nombre as obra_social,
                p.Libreta_sanitaria as libreta
            FROM turno t
            JOIN paciente p ON p.Id_paciente = t.Id_paciente
            JOIN usuario u ON u.Id_usuario = p.Id_usuario
            LEFT JOIN obra_social os ON os.Id_obra_social = p.Id_obra_social
            WHERE t.Id_medico = ?
                AND $whereClause
                AND (t.Estado IS NULL OR t.Estado <> 'cancelado')
            ORDER BY t.Fecha ASC
        ");
        $st->execute($params);
        
        $turnos = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $turnos[] = [
                'id' => (int)$row['id'],
                'fecha' => $row['fecha'],
                'atendido' => (bool)$row['atendido'],
                'fecha_atencion' => $row['fecha_atencion'],
                'paciente_id' => (int)$row['paciente_id'],
                'paciente_nombre' => trim($row['paciente_apellido'] . ', ' . $row['paciente_nombre_prim']),
                'paciente_dni' => $row['paciente_dni'],
                'obra_social' => $row['obra_social'],
                'libreta' => $row['libreta']
            ];
        }
        
        json_out(['ok' => true, 'turnos' => $turnos]);
        
    } catch (Throwable $e) {
        json_out(['ok' => false, 'error' => $e->getMessage()], 500);
    }
}

// ========== GUARDAR DIAGNÓSTICO ==========
if ($action === 'guardar_diagnostico' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    ensure_csrf();
    
    try {
        $turnoId = (int)($_POST['turno_id'] ?? 0);
        $pacienteId = (int)($_POST['paciente_id'] ?? 0);
        $sintomas = trim($_POST['sintomas'] ?? '');
        $diagnostico = trim($_POST['diagnostico'] ?? '');
        $observaciones = trim($_POST['observaciones'] ?? '');
        $medicamentosJson = $_POST['medicamentos'] ?? '[]';
        $duracion = trim($_POST['duracion_tratamiento'] ?? '');
        
        if ($turnoId <= 0) throw new Exception('Turno inválido');
        if ($pacienteId <= 0) throw new Exception('Paciente inválido');
        if (empty($diagnostico)) throw new Exception('El diagnóstico es obligatorio');
        
        $medicamentos = json_decode($medicamentosJson, true);
        if (!is_array($medicamentos)) $medicamentos = [];
        
        // Verificar que el turno pertenece a este médico
        $st = $pdo->prepare("
            SELECT 1 FROM turno 
            WHERE Id_turno = ? AND Id_medico = ? 
            LIMIT 1
        ");
        $st->execute([$turnoId, $medicoId]);
        if (!$st->fetch()) {
            throw new Exception('No autorizado');
        }
        
        $pdo->beginTransaction();
        
        // Insertar diagnóstico
        $st = $pdo->prepare("
            INSERT INTO diagnostico 
            (Id_turno, Id_medico, Diagnostico, Observaciones, Sintomas, Fecha_Diagnostico)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $st->execute([$turnoId, $medicoId, $diagnostico, $observaciones, $sintomas]);
        $diagId = $pdo->lastInsertId();
        
        // Si hay medicamentos, crear receta
        if (!empty($medicamentos)) {
            $medicamentosTexto = [];
            foreach ($medicamentos as $med) {
                $texto = $med['medicamento'];
                if (!empty($med['indicacion'])) {
                    $texto .= ' - ' . $med['indicacion'];
                }
                $medicamentosTexto[] = $texto;
            }
            $medicamentosStr = implode("\n", $medicamentosTexto);
            
            // Calcular fecha de vencimiento (30 días por defecto)
            $vencimiento = date('Y-m-d', strtotime('+30 days'));
            
            $st = $pdo->prepare("
                INSERT INTO receta 
                (Id_diagnostico, Id_turno, Id_medico, Id_paciente, Medicamentos, Indicaciones, Duracion_Tratamiento, Fecha_Vencimiento)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $st->execute([
                $diagId, 
                $turnoId, 
                $medicoId, 
                $pacienteId, 
                $medicamentosStr,
                'Ver indicaciones en cada medicamento',
                $duracion,
                $vencimiento
            ]);
        }
        
        // Marcar turno como atendido
        $st = $pdo->prepare("
            UPDATE turno 
            SET Atendido = 1, Fecha_Atencion = NOW(), Id_medico_atencion = ?
            WHERE Id_turno = ?
        ");
        $st->execute([$medicoId, $turnoId]);
        
        // Agregar al historial clínico
        $contenido = "DIAGNÓSTICO: $diagnostico";
        if ($sintomas) $contenido .= "\n\nSÍNTOMAS: $sintomas";
        if ($observaciones) $contenido .= "\n\nOBSERVACIONES: $observaciones";
        if (!empty($medicamentos)) {
            $contenido .= "\n\nRECETA:\n" . $medicamentosStr;
        }
        
        $st = $pdo->prepare("
            INSERT INTO historial_clinico 
            (Id_paciente, Id_turno, Id_medico, Tipo_Registro, Contenido)
            VALUES (?, ?, ?, 'consulta', ?)
        ");
        $st->execute([$pacienteId, $turnoId, $medicoId, $contenido]);
        
        $pdo->commit();
        
        json_out(['ok' => true, 'mensaje' => 'Diagnóstico guardado y turno marcado como atendido']);
        
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        json_out(['ok' => false, 'error' => $e->getMessage()], 500);
    }
}

// ========== HISTORIAL ==========
if ($action === 'historial') {
    try {
        $desde = $_GET['desde'] ?? date('Y-m-d', strtotime('-30 days'));
        $hasta = $_GET['hasta'] ?? date('Y-m-d');
        
        $st = $pdo->prepare("
            SELECT 
                d.Diagnostico as diagnostico,
                d.Fecha_Diagnostico as fecha,
                u.Nombre as paciente_nombre_prim,
                u.Apellido as paciente_apellido,
                r.Medicamentos as medicamentos
            FROM diagnostico d
            JOIN turno t ON t.Id_turno = d.Id_turno
            JOIN paciente p ON p.Id_paciente = t.Id_paciente
            JOIN usuario u ON u.Id_usuario = p.Id_usuario
            LEFT JOIN receta r ON r.Id_diagnostico = d.Id_diagnostico
            WHERE d.Id_medico = ?
                AND DATE(d.Fecha_Diagnostico) BETWEEN ? AND ?
            ORDER BY d.Fecha_Diagnostico DESC
            LIMIT 50
        ");
        $st->execute([$medicoId, $desde, $hasta]);
        
        $historial = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $historial[] = [
                'fecha' => $row['fecha'],
                'paciente_nombre' => trim($row['paciente_apellido'] . ', ' . $row['paciente_nombre_prim']),
                'diagnostico' => $row['diagnostico'],
                'medicamentos' => $row['medicamentos']
            ];
        }
        
        json_out(['ok' => true, 'historial' => $historial]);
        
    } catch (Throwable $e) {
        json_out(['ok' => false, 'error' => $e->getMessage()], 500);
    }
}

json_out(['ok' => false, 'error' => 'Acción no soportada'], 400);