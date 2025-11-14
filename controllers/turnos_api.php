<?php
// controllers/turnos_api.php - VERSIÓN COMPLETAMENTE CORREGIDA
// ✅ CRÍTICO: NO debe haber NADA antes de este <?php

// Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/php-errors.log');

// Limpiar cualquier salida previa
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// Iniciar sesión
session_start();

// Incluir dependencias
require_once __DIR__ . '/../config/db.php';

// ========== FUNCIONES AUXILIARES ==========

// Función para respuesta JSON limpia
function json_out($data, $code = 200) { 
  if (ob_get_level()) {
      ob_end_clean();
  }
  
  http_response_code($code); 
  header('Content-Type: application/json; charset=utf-8');
  header('X-Content-Type-Options: nosniff');
  header('Access-Control-Allow-Origin: *');
  header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
  header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
  
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); 
  exit; 
}

// Función para verificar login
function require_login() { 
  if (empty($_SESSION['Id_usuario'])) {
    json_out(['ok' => false, 'error' => 'No autenticado'], 401); 
  }
  return (int)$_SESSION['Id_usuario']; 
}

// Función para validar CSRF
function ensure_csrf() { 
  $t = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''; 
  if (!$t || !hash_equals($_SESSION['csrf_token'] ?? '', $t)) {
    json_out(['ok' => false, 'error' => 'CSRF inválido'], 400); 
  }
}

// ✅ CORREGIDO: Función para obtener nombre del día
function get_day_name($ymd) {
  $dias = ['domingo', 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado'];
  $timestamp = strtotime($ymd);
  if ($timestamp === false) {
    error_log("Invalid date format in get_day_name: $ymd");
    return 'lunes'; // Default fallback
  }
  $w = (int)date('w', $timestamp);
  return $dias[$w];
}

// ✅ NUEVA: Función para validar fecha
function validate_date($dateStr) {
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
    return false;
  }
  
  $parts = explode('-', $dateStr);
  if (count($parts) !== 3) {
    return false;
  }
  
  $year = (int)$parts[0];
  $month = (int)$parts[1];
  $day = (int)$parts[2];
  
  return checkdate($month, $day, $year);
}

// Manejo de errores global
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno]: $errstr in $errfile on line $errline");
    json_out(['ok' => false, 'error' => 'Error interno del servidor'], 500);
});

set_exception_handler(function($e) {
    error_log("PHP Exception: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    json_out(['ok' => false, 'error' => 'Error interno del servidor'], 500);
});

// ========== CONEXIÓN A BASE DE DATOS ==========

try {
    $pdo = db();
    
    // Verificar conexión
    $pdo->query("SELECT 1");
    
} catch (Throwable $e) {
    error_log("Database connection error: " . $e->getMessage());
    json_out(['ok' => false, 'error' => 'Error de conexión a base de datos'], 500);
}

// Generar token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Obtener acción
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// Log de petición
error_log("Turnos API - Action: $action - User: " . ($_SESSION['Id_usuario'] ?? 'guest') . " - Method: " . $_SERVER['REQUEST_METHOD']);

// ========== ACCIONES PÚBLICAS (Sin requerir login) ==========

// ✅ Listar especialidades
if ($action === 'specialties') {
  try {
    $rows = $pdo->query("
      SELECT Id_Especialidad, Nombre 
      FROM especialidad 
      WHERE Activo = 1 
      ORDER BY Nombre
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Specialties found: " . count($rows));
    json_out(['ok' => true, 'items' => $rows]);
    
  } catch (Throwable $e) {
    error_log("Error en specialties: " . $e->getMessage());
    json_out(['ok' => false, 'error' => 'Error al cargar especialidades'], 500);
  }
}

// ✅ Listar médicos por especialidad
if ($action === 'doctors') {
  try {
    $esp = (int)($_GET['especialidad_id'] ?? 0);
    if ($esp <= 0) json_out(['ok' => false, 'error' => 'Especialidad inválida'], 400);
    
    $st = $pdo->prepare("
      SELECT m.Id_medico, u.Nombre, u.Apellido
      FROM medico m
      JOIN usuario u ON u.Id_usuario = m.Id_usuario
      WHERE m.Id_Especialidad = ? AND m.Activo = 1
      ORDER BY u.Apellido, u.Nombre
    ");
    $st->execute([$esp]);
    $items = $st->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Doctors found for specialty $esp: " . count($items));
    json_out(['ok' => true, 'items' => $items]);
    
  } catch (Throwable $e) {
    error_log("Error en doctors: " . $e->getMessage());
    json_out(['ok' => false, 'error' => 'Error al cargar médicos'], 500);
  }
}

// ✅ Información del médico con horarios
if ($action === 'medico_info') {
  try {
    $med = (int)($_GET['medico_id'] ?? 0);
    if ($med <= 0) json_out(['ok' => false, 'error' => 'Médico inválido'], 400);
    
    $st = $pdo->prepare("
      SELECT m.Id_medico, u.Nombre, u.Apellido, m.Duracion_Turno
      FROM medico m
      JOIN usuario u ON u.Id_usuario = m.Id_usuario
      WHERE m.Id_medico = ? AND m.Activo = 1
    ");
    $st->execute([$med]);
    $medico = $st->fetch(PDO::FETCH_ASSOC);
    
    if (!$medico) {
      error_log("Medico not found: $med");
      json_out(['ok' => false, 'error' => 'Médico no encontrado'], 404);
    }
    
    // Obtener horarios
    $st2 = $pdo->prepare("
      SELECT Dia_semana, Hora_inicio, Hora_fin 
      FROM horario_medico 
      WHERE Id_medico = ? 
      ORDER BY 
        FIELD(Dia_semana, 'lunes','martes','miercoles','jueves','viernes','sabado','domingo'),
        Hora_inicio
    ");
    $st2->execute([$med]);
    $horarios = $st2->fetchAll(PDO::FETCH_ASSOC);
    
    // Días únicos
    $dias_unicos = array_unique(array_map(fn($h) => $h['Dia_semana'], $horarios));
    
    error_log("Medico $med info loaded with " . count($horarios) . " schedules");
    
    json_out(['ok' => true, 'medico' => [
      'id' => (int)$medico['Id_medico'],
      'nombre' => $medico['Nombre'] . ' ' . $medico['Apellido'],
      'dias_disponibles' => implode(',', $dias_unicos),
      'horarios' => $horarios,
      'duracion_turno' => (int)($medico['Duracion_Turno'] ?? 30)
    ]]);
    
  } catch (Throwable $e) {
    error_log("Error en medico_info: " . $e->getMessage());
    json_out(['ok' => false, 'error' => 'Error al cargar información del médico'], 500);
  }
}

// ✅ CORREGIDO: Obtener slots disponibles
if ($action === 'slots') {
  try {
    $med = (int)($_GET['medico_id'] ?? 0);
    $date = $_GET['date'] ?? '';

    error_log("Slots request - Medico: $med, Date: $date");

    if ($med <= 0) json_out(['ok' => false, 'error' => 'Médico inválido'], 400);
    
    if (!$date || !validate_date($date)) {
      error_log("Invalid date format: $date");
      json_out(['ok' => false, 'error' => 'Fecha inválida'], 400);
    }

    $diaSemana = get_day_name($date);
    error_log("Day of week: $diaSemana");
    
    // Obtener horarios del médico para ese día
    $st = $pdo->prepare("
      SELECT Hora_inicio, Hora_fin 
      FROM horario_medico 
      WHERE Id_medico = ? AND Dia_semana = ?
      ORDER BY Hora_inicio
    ");
    $st->execute([$med, $diaSemana]);
    $horarios = $st->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Schedules found for day $diaSemana: " . count($horarios));
    
    if (empty($horarios)) {
      error_log("No schedules for doctor $med on day $diaSemana");
      json_out(['ok' => true, 'slots' => []]);
    }

    // Obtener turnos ocupados
    $st = $pdo->prepare("
      SELECT TIME(Fecha) AS hhmm 
      FROM turno 
      WHERE DATE(Fecha) = ? AND Id_medico = ? AND (Estado IS NULL OR Estado <> 'cancelado')
    ");
    $st->execute([$date, $med]);
    $busy = array_map(fn($r) => substr($r['hhmm'], 0, 5), $st->fetchAll(PDO::FETCH_ASSOC));

    error_log("Busy slots: " . count($busy) . " - " . implode(', ', $busy));

    // Generar slots
    $slots = [];
    foreach ($horarios as $bloque) {
      try {
        $inicio = new DateTime($date . ' ' . $bloque['Hora_inicio']);
        $fin = new DateTime($date . ' ' . $bloque['Hora_fin']);
        
        while ($inicio < $fin) {
          $slot = $inicio->format('H:i');
          if (!in_array($slot, $busy)) {
            $slots[] = $slot;
          }
          $inicio->modify('+30 minutes');
        }
      } catch (Exception $e) {
        error_log("Error generating slots: " . $e->getMessage());
      }
    }

    error_log("Available slots: " . count($slots) . " - " . implode(', ', $slots));
    json_out(['ok' => true, 'slots' => $slots]);
    
  } catch (Throwable $e) {
    error_log("Error en slots: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    json_out(['ok' => false, 'error' => 'Error al cargar horarios: ' . $e->getMessage()], 500);
  }
}

// ========== ACCIONES QUE REQUIEREN LOGIN ==========

// ✅ Mis turnos
if ($action === 'my_appointments') {
  $uid = require_login();
  
  try {
    $st = $pdo->prepare("SELECT Id_paciente FROM paciente WHERE Id_usuario = ? AND Activo = 1 LIMIT 1");
    $st->execute([$uid]); 
    $pacId = (int)($st->fetchColumn() ?: 0);
    
    if ($pacId <= 0) {
      error_log("User $uid is not a patient");
      json_out(['ok' => true, 'items' => []]);
    }
    
    $st = $pdo->prepare("
      SELECT t.Id_turno, t.Fecha, t.Estado, t.Id_medico,
             um.Nombre AS MNombre, um.Apellido AS MApellido, 
             e.Nombre AS Especialidad
      FROM turno t
      LEFT JOIN medico m ON m.Id_medico = t.Id_medico
      LEFT JOIN usuario um ON um.Id_usuario = m.Id_usuario
      LEFT JOIN especialidad e ON e.Id_Especialidad = m.Id_Especialidad
      WHERE t.Id_paciente = ? 
        AND (t.Estado IS NULL OR t.Estado != 'cancelado')
        AND t.Fecha >= NOW()
      ORDER BY t.Fecha ASC
    ");
    $st->execute([$pacId]);
    
    $items = []; 
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
      $items[] = [
        'Id_turno' => (int)$r['Id_turno'],
        'Id_medico' => (int)($r['Id_medico'] ?? 0),
        'fecha' => $r['Fecha'],
        'fecha_fmt' => date('d/m/Y H:i', strtotime($r['Fecha'])),
        'estado' => $r['Estado'] ?? 'reservado',
        'medico' => trim(($r['MApellido'] ?? '') . ', ' . ($r['MNombre'] ?? '')),
        'especialidad' => $r['Especialidad'] ?? '',
      ];
    }
    
    error_log("User $uid has " . count($items) . " appointments");
    json_out(['ok' => true, 'items' => $items]);
    
  } catch (Throwable $e) {
    error_log("Error en my_appointments: " . $e->getMessage());
    json_out(['ok' => false, 'error' => 'Error al cargar turnos'], 500);
  }
}

// REEMPLAZAR la acción 'book' en controllers/turnos_api.php

if ($action === 'book' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  ensure_csrf();
  $uid = require_login();
  
  try {
    $date = trim($_POST['date'] ?? '');
    $time = trim($_POST['time'] ?? '');
    $med = (int)($_POST['medico_id'] ?? 0);
    
    if (!$date || !validate_date($date)) {
      json_out(['ok' => false, 'error' => 'Fecha inválida'], 400);
    }
    if (!$time || !preg_match('/^\d{2}:\d{2}$/', $time)) {
      json_out(['ok' => false, 'error' => 'Hora inválida'], 400);
    }
    if ($med <= 0) json_out(['ok' => false, 'error' => 'Médico inválido'], 400);

    // Verificar médico activo
    $chkM = $pdo->prepare("SELECT 1 FROM medico WHERE Id_medico = ? AND Activo = 1");
    $chkM->execute([$med]); 
    if (!$chkM->fetch()) json_out(['ok' => false, 'error' => 'Médico no encontrado'], 404);

    // Obtener paciente
    $st = $pdo->prepare("SELECT Id_paciente FROM paciente WHERE Id_usuario = ? AND Activo = 1 LIMIT 1");
    $st->execute([$uid]); 
    $pacId = (int)($st->fetchColumn() ?: 0);
    if ($pacId <= 0) json_out(['ok' => false, 'error' => 'Usuario no registrado como paciente'], 400);

    // ✅ NUEVA VALIDACIÓN: Verificar si ya tiene turno activo con este médico
    $chkExisting = $pdo->prepare("
      SELECT COUNT(*) FROM turno 
      WHERE Id_paciente = ? 
      AND Id_medico = ? 
      AND (Estado IS NULL OR Estado = 'reservado')
      AND Fecha >= NOW()
    ");
    $chkExisting->execute([$pacId, $med]);
    
    if ($chkExisting->fetchColumn() > 0) {
      json_out([
        'ok' => false, 
        'error' => 'Ya tenés un turno activo con este médico. Cancelá o completá el turno anterior antes de reservar uno nuevo.'
      ], 409);
    }

    // Validar fecha/hora
    $dt = DateTime::createFromFormat('Y-m-d H:i', "$date $time");
    if (!$dt) json_out(['ok' => false, 'error' => 'Fecha/hora inválidas'], 400);

    $fechaHora = $dt->format('Y-m-d H:i:00');
    
    // Verificar horario del médico
    $diaSemana = get_day_name($date);
    $chkHorario = $pdo->prepare("
      SELECT 1 FROM horario_medico 
      WHERE Id_medico = ? AND Dia_semana = ? 
      AND TIME(?) >= Hora_inicio AND TIME(?) < Hora_fin
      LIMIT 1
    ");
    $chkHorario->execute([$med, $diaSemana, $time, $time]);
    if (!$chkHorario->fetch()) {
      json_out(['ok' => false, 'error' => 'Horario no disponible para este médico'], 400);
    }
    
    // Verificar disponibilidad del slot
    $chk = $pdo->prepare("
      SELECT 1 FROM turno 
      WHERE Fecha = ? AND Id_medico = ? AND (Estado IS NULL OR Estado <> 'cancelado') 
      LIMIT 1
    ");
    $chk->execute([$fechaHora, $med]);
    if ($chk->fetch()) json_out(['ok' => false, 'error' => 'Horario ocupado'], 409);

    // Insertar turno
    $ins = $pdo->prepare("
      INSERT INTO turno (Fecha, Estado, Id_paciente, Id_medico) 
      VALUES (?, 'reservado', ?, ?)
    ");
    $ins->execute([$fechaHora, $pacId, $med]);

    error_log("Turno created: User $uid, Medico $med, Date $fechaHora");
    json_out(['ok' => true, 'mensaje' => 'Turno reservado exitosamente']);
    
  } catch (Throwable $e) {
    error_log("Error en book: " . $e->getMessage());
    json_out(['ok' => false, 'error' => 'Error al reservar turno'], 500);
  }
}// REEMPLAZAR la acción 'book' en controllers/turnos_api.php

if ($action === 'book' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  ensure_csrf();
  $uid = require_login();
  
  try {
    $date = trim($_POST['date'] ?? '');
    $time = trim($_POST['time'] ?? '');
    $med = (int)($_POST['medico_id'] ?? 0);
    
    if (!$date || !validate_date($date)) {
      json_out(['ok' => false, 'error' => 'Fecha inválida'], 400);
    }
    if (!$time || !preg_match('/^\d{2}:\d{2}$/', $time)) {
      json_out(['ok' => false, 'error' => 'Hora inválida'], 400);
    }
    if ($med <= 0) json_out(['ok' => false, 'error' => 'Médico inválido'], 400);

    // Verificar médico activo
    $chkM = $pdo->prepare("SELECT 1 FROM medico WHERE Id_medico = ? AND Activo = 1");
    $chkM->execute([$med]); 
    if (!$chkM->fetch()) json_out(['ok' => false, 'error' => 'Médico no encontrado'], 404);

    // Obtener paciente
    $st = $pdo->prepare("SELECT Id_paciente FROM paciente WHERE Id_usuario = ? AND Activo = 1 LIMIT 1");
    $st->execute([$uid]); 
    $pacId = (int)($st->fetchColumn() ?: 0);
    if ($pacId <= 0) json_out(['ok' => false, 'error' => 'Usuario no registrado como paciente'], 400);

    // ✅ NUEVA VALIDACIÓN: Verificar si ya tiene turno activo con este médico
    $chkExisting = $pdo->prepare("
      SELECT COUNT(*) FROM turno 
      WHERE Id_paciente = ? 
      AND Id_medico = ? 
      AND (Estado IS NULL OR Estado = 'reservado')
      AND Fecha >= NOW()
    ");
    $chkExisting->execute([$pacId, $med]);
    
    if ($chkExisting->fetchColumn() > 0) {
      json_out([
        'ok' => false, 
        'error' => 'Ya tenés un turno activo con este médico. Cancelá o completá el turno anterior antes de reservar uno nuevo.'
      ], 409);
    }

    // Validar fecha/hora
    $dt = DateTime::createFromFormat('Y-m-d H:i', "$date $time");
    if (!$dt) json_out(['ok' => false, 'error' => 'Fecha/hora inválidas'], 400);

    $fechaHora = $dt->format('Y-m-d H:i:00');
    
    // Verificar horario del médico
    $diaSemana = get_day_name($date);
    $chkHorario = $pdo->prepare("
      SELECT 1 FROM horario_medico 
      WHERE Id_medico = ? AND Dia_semana = ? 
      AND TIME(?) >= Hora_inicio AND TIME(?) < Hora_fin
      LIMIT 1
    ");
    $chkHorario->execute([$med, $diaSemana, $time, $time]);
    if (!$chkHorario->fetch()) {
      json_out(['ok' => false, 'error' => 'Horario no disponible para este médico'], 400);
    }
    
    // Verificar disponibilidad del slot
    $chk = $pdo->prepare("
      SELECT 1 FROM turno 
      WHERE Fecha = ? AND Id_medico = ? AND (Estado IS NULL OR Estado <> 'cancelado') 
      LIMIT 1
    ");
    $chk->execute([$fechaHora, $med]);
    if ($chk->fetch()) json_out(['ok' => false, 'error' => 'Horario ocupado'], 409);

    // Insertar turno
    $ins = $pdo->prepare("
      INSERT INTO turno (Fecha, Estado, Id_paciente, Id_medico) 
      VALUES (?, 'reservado', ?, ?)
    ");
    $ins->execute([$fechaHora, $pacId, $med]);

    error_log("Turno created: User $uid, Medico $med, Date $fechaHora");
    json_out(['ok' => true, 'mensaje' => 'Turno reservado exitosamente']);
    
  } catch (Throwable $e) {
    error_log("Error en book: " . $e->getMessage());
    json_out(['ok' => false, 'error' => 'Error al reservar turno'], 500);
  }
}

// ✅ Cancelar turno
if ($action === 'cancel' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  ensure_csrf();
  $uid = require_login();
  
  try {
    $tid = (int)($_POST['turno_id'] ?? 0);
    if ($tid <= 0) json_out(['ok' => false, 'error' => 'Turno inválido'], 400);
    
    $st = $pdo->prepare("
      SELECT t.Id_turno
      FROM turno t 
      JOIN paciente p ON p.Id_paciente = t.Id_paciente
      WHERE t.Id_turno = ? AND p.Id_usuario = ? 
      LIMIT 1
    ");
    $st->execute([$tid, $uid]); 
    if (!$st->fetch()) json_out(['ok' => false, 'error' => 'No autorizado'], 403);
    
    $pdo->prepare("UPDATE turno SET Estado = 'cancelado' WHERE Id_turno = ?")->execute([$tid]);
    
    error_log("Turno $tid cancelled by user $uid");
    json_out(['ok' => true, 'mensaje' => 'Turno cancelado']);
    
  } catch (Throwable $e) {
    error_log("Error en cancel: " . $e->getMessage());
    json_out(['ok' => false, 'error' => 'Error al cancelar turno'], 500);
  }
}

// ✅ Reprogramar turno
if ($action === 'reschedule' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  ensure_csrf();
  $uid = require_login();
  
  try {
    $tid = (int)($_POST['turno_id'] ?? 0);
    $date = trim($_POST['date'] ?? '');
    $time = trim($_POST['time'] ?? '');
    $med = (int)($_POST['medico_id'] ?? 0);
    
    if ($tid <= 0) json_out(['ok' => false, 'error' => 'Turno inválido'], 400);
    if (!$date || !validate_date($date)) {
      json_out(['ok' => false, 'error' => 'Fecha inválida'], 400);
    }
    if (!$time || !preg_match('/^\d{2}:\d{2}$/', $time)) {
      json_out(['ok' => false, 'error' => 'Hora inválida'], 400);
    }
    if ($med <= 0) json_out(['ok' => false, 'error' => 'Médico inválido'], 400);
    
    $st = $pdo->prepare("
      SELECT t.Id_medico
      FROM turno t 
      JOIN paciente p ON p.Id_paciente = t.Id_paciente
      WHERE t.Id_turno = ? AND p.Id_usuario = ? 
      LIMIT 1
    ");
    $st->execute([$tid, $uid]); 
    if (!$st->fetch()) json_out(['ok' => false, 'error' => 'No autorizado'], 403);

    $dt = DateTime::createFromFormat('Y-m-d H:i', "$date $time");
    if (!$dt) json_out(['ok' => false, 'error' => 'Fecha/hora inválidas'], 400);

    $fechaHora = $dt->format('Y-m-d H:i:00');
    
    // Verificar horario del médico
    $diaSemana = get_day_name($date);
    $chkHorario = $pdo->prepare("
      SELECT 1 FROM horario_medico 
      WHERE Id_medico = ? AND Dia_semana = ? 
      AND TIME(?) >= Hora_inicio AND TIME(?) < Hora_fin
      LIMIT 1
    ");
    $chkHorario->execute([$med, $diaSemana, $time, $time]);
    if (!$chkHorario->fetch()) {
      json_out(['ok' => false, 'error' => 'Horario no disponible'], 400);
    }
    
    $chk = $pdo->prepare("
      SELECT 1 FROM turno 
      WHERE Fecha = ? AND Id_medico = ? AND (Estado IS NULL OR Estado <> 'cancelado') AND Id_turno <> ? 
      LIMIT 1
    ");
    $chk->execute([$fechaHora, $med, $tid]);
    if ($chk->fetch()) json_out(['ok' => false, 'error' => 'Horario ocupado'], 409);

    $pdo->prepare("
      UPDATE turno 
      SET Fecha = ?, Id_medico = ?, Estado = 'reservado' 
      WHERE Id_turno = ?
    ")->execute([$fechaHora, $med, $tid]);
    
    error_log("Turno $tid rescheduled by user $uid to $fechaHora");
    json_out(['ok' => true, 'mensaje' => 'Turno reprogramado exitosamente', 'fecha' => $fechaHora]);
    
  } catch (Throwable $e) {
    error_log("Error en reschedule: " . $e->getMessage());
    json_out(['ok' => false, 'error' => 'Error al reprogramar turno'], 500);
  }
}

// Acción no soportada
error_log("Unsupported action: $action");
json_out(['ok' => false, 'error' => 'Acción no soportada: ' . $action], 400);