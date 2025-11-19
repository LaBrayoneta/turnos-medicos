<?php
// views/pages/admin.php - VERSIÓN CORREGIDA CON COLUMNAS EN MINÚSCULAS
// ✅ CRÍTICO: NO debe haber NADA antes de este <?php

// ✅ Configuración de errores para debugging
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/paths.php';

function dbx(){ return db(); }

function json_out($d, $c=200){ 
  // Limpiar cualquier output previo
  if (ob_get_level()) {
    ob_end_clean();
  }
  
  http_response_code($c); 
  header('Content-Type: application/json; charset=utf-8');
  header('X-Content-Type-Options: nosniff');
  
  echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); 
  exit; 
}

function ensure_csrf(){
  $t = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
  if (!$t || !hash_equals($_SESSION['csrf_token'] ?? '', $t)) {
    json_out(['ok'=>false,'error'=>'CSRF inválido'],400);
  }
}

function must_staff(PDO $pdo){
  if (empty($_SESSION['Id_usuario'])) {
    return [false, false, false, null, null];
  }
  
  $uid = (int)$_SESSION['Id_usuario'];

  try {
    $st = $pdo->prepare("SELECT Id_secretaria FROM secretaria WHERE Id_usuario=? AND activo=1 LIMIT 1");
    $st->execute([$uid]); 
    $secData = $st->fetch(PDO::FETCH_ASSOC);
    $isSec = (bool)$secData;

    $st = $pdo->prepare("SELECT Id_medico FROM medico WHERE Id_usuario=? AND activo=1 LIMIT 1");
    $st->execute([$uid]); 
    $me = $st->fetch(PDO::FETCH_ASSOC);
    $isMed = (bool)$me;

    if (!$isSec && !$isMed) {
      return [false, false, false, null, null];
    }
    
    return [$uid, $isSec, $isMed, $me ? (int)$me['Id_medico'] : null, $secData ? (int)$secData['Id_secretaria'] : null];
  } catch (Throwable $e) {
    error_log("Error in must_staff: " . $e->getMessage());
    return [false, false, false, null, null];
  }
}

function weekday_name_es($ymd){
  $w = (int)date('N', strtotime($ymd));
  $map = [1=>'lunes',2=>'martes',3=>'miercoles',4=>'jueves',5=>'viernes',6=>'sabado',7=>'domingo'];
  return $map[$w] ?? '';
}

// ✅ Inicializar PDO con manejo de errores
try {
  $pdo = dbx();
} catch (Throwable $e) {
  error_log("Database connection error in admin.php: " . $e->getMessage());
  
  if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    json_out(['ok'=>false,'error'=>'Error de conexión a base de datos'], 500);
  }
  
  die("Error de conexión a base de datos. Verifica la configuración.");
}

// ✅ Generar CSRF token
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

// ======= MANEJO DE PETICIONES API (ANTES DEL HTML) =======
$isApiRequest = isset($_GET['fetch']) || isset($_POST['action']);

if ($isApiRequest) {
  [$uid,$isSec,$isMed,$myMedId,$mySecId] = must_staff($pdo);
  
  if (!$uid) {
    json_out(['ok'=>false,'error'=>'No autorizado'], 403);
  }

  $action = $_GET['fetch'] ?? $_POST['action'] ?? '';

  // ========== VERIFICAR TURNO DUPLICADO ==========
if ($action === 'check_turno_existente') {
  $pacId = (int)($_GET['paciente_id'] ?? 0);
  $medId = (int)($_GET['medico_id'] ?? 0);
  
  if ($pacId <= 0 || $medId <= 0) {
    json_out(['ok' => false, 'error' => 'Parámetros inválidos'], 400);
  }
  
  try {
    $stmt = $pdo->prepare("
      SELECT 
        t.Id_turno,
        t.fecha,
        t.estado,
        DATE_FORMAT(t.fecha, '%d/%m/%Y %H:%i') as fecha_fmt
      FROM turno t
      WHERE t.Id_paciente = ?
        AND t.Id_medico = ?
        AND (t.estado IS NULL OR t.estado IN ('reservado', 'pendiente_confirmacion'))
        AND t.fecha >= NOW()
      LIMIT 1
    ");
    $stmt->execute([$pacId, $medId]);
    $turno = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($turno) {
      json_out([
        'ok' => true,
        'tiene_turno' => true,
        'turno' => $turno
      ]);
    } else {
      json_out([
        'ok' => true,
        'tiene_turno' => false
      ]);
    }
  } catch (Throwable $e) {
    error_log('Error verificando turno: ' . $e->getMessage());
    json_out(['ok' => false, 'error' => $e->getMessage()], 500);
  }
}
  // ========== FETCH: INIT ==========
  if ($action === 'init') {
    try {
      // ✅ Especialidades
      $esps = $pdo->query("
        SELECT Id_Especialidad, nombre 
        FROM especialidad 
        WHERE activo=1 
        ORDER BY nombre
      ")->fetchAll(PDO::FETCH_ASSOC);
      
      // ✅ Médicos con horarios
      $meds = $pdo->query("
        SELECT m.Id_medico, u.apellido, u.nombre, u.dni, u.email, 
               e.nombre AS Especialidad, m.legajo, m.Id_Especialidad
        FROM medico m
        JOIN usuario u ON u.Id_usuario=m.Id_usuario
        LEFT JOIN especialidad e ON e.Id_Especialidad=m.Id_Especialidad
        WHERE m.activo=1
        ORDER BY u.apellido, u.nombre
      ")->fetchAll(PDO::FETCH_ASSOC);
      
      // Cargar horarios para cada médico
      $stHorarios = $pdo->prepare("
        SELECT dia_semana, hora_inicio, hora_fin 
        FROM horario_medico 
        WHERE Id_medico=? 
        ORDER BY FIELD(dia_semana, 'lunes','martes','miercoles','jueves','viernes','sabado','domingo'), hora_inicio
      ");
      
      foreach($meds as &$med) {
        $stHorarios->execute([$med['Id_medico']]);
        $horarios = $stHorarios->fetchAll(PDO::FETCH_ASSOC);
        $med['horarios'] = $horarios;
        
        // Formatear para JS
        foreach($med['horarios'] as &$h) {
          $h['Dia_semana'] = $h['dia_semana'];
          $h['Hora_inicio'] = $h['hora_inicio'];
          $h['Hora_fin'] = $h['hora_fin'];
        }
        unset($h);
        
        // Formatear datos del médico
        $med['Apellido'] = $med['apellido'];
        $med['Nombre'] = $med['nombre'];
        $med['Legajo'] = $med['legajo'];
        
        $dias = array_unique(array_map(fn($h)=>$h['dia_semana'], $horarios));
        $med['Dias_Disponibles'] = implode(',', $dias);
      }
      unset($med);
      
      // ✅ Secretarias
      $secs = $pdo->query("
        SELECT s.Id_secretaria, u.apellido, u.nombre, u.dni, u.email, u.Id_usuario
        FROM secretaria s
        JOIN usuario u ON u.Id_usuario=s.Id_usuario
        WHERE s.activo=1
        ORDER BY u.apellido, u.nombre
      ")->fetchAll(PDO::FETCH_ASSOC);
      
      // Formatear para JS
      foreach($secs as &$sec) {
        $sec['Apellido'] = $sec['apellido'];
        $sec['Nombre'] = $sec['nombre'];
      }
      unset($sec);
      
      // ✅ Obras sociales
      $obras = $pdo->query("
        SELECT Id_obra_social, nombre, activo 
        FROM obra_social 
        ORDER BY nombre
      ")->fetchAll(PDO::FETCH_ASSOC);
      
      // Formatear para JS
      foreach($obras as &$obra) {
        $obra['Nombre'] = $obra['nombre'];
        $obra['Activo'] = $obra['activo'];
      }
      unset($obra);
      
      json_out([
        'ok'=>true,
        'especialidades'=>$esps,
        'medicos'=>$meds,
        'secretarias'=>$secs,
        'obras_sociales'=>$obras,
        'csrf'=>$csrf
      ]);
      
    } catch (Throwable $e) {
      error_log('Error en fetch=init: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
      json_out(['ok'=>false,'error'=>'Error al cargar datos: ' . $e->getMessage()], 500);
    }
  }

  // ========== OBRAS SOCIALES ==========
  if ($action === 'create_obra_social') {
    ensure_csrf();
    try {
      $nombre = trim($_POST['nombre'] ?? '');
      if (!$nombre) throw new Exception('El nombre es obligatorio');
      
      $check = $pdo->prepare("SELECT COUNT(*) FROM obra_social WHERE nombre=?");
      $check->execute([$nombre]);
      if ($check->fetchColumn() > 0) throw new Exception('Esta obra social ya existe');
      
      $stmt = $pdo->prepare("INSERT INTO obra_social (nombre, activo) VALUES (?, 1)");
      $stmt->execute([$nombre]);
      
      json_out(['ok'=>true,'msg'=>'Obra social creada']);
    } catch (Throwable $e) {
      json_out(['ok'=>false,'error'=>$e->getMessage()],500);
    }
  }

  if ($action === 'toggle_obra_social') {
    ensure_csrf();
    try {
      $id = (int)($_POST['id_obra_social'] ?? 0);
      if ($id <= 0) throw new Exception('ID inválido');
      
      $stmt = $pdo->prepare("UPDATE obra_social SET activo = NOT activo WHERE Id_obra_social=?");
      $stmt->execute([$id]);
      
      json_out(['ok'=>true,'msg'=>'Estado actualizado']);
    } catch (Throwable $e) {
      json_out(['ok'=>false,'error'=>$e->getMessage()],500);
    }
  }

  if ($action === 'delete_obra_social') {
    ensure_csrf();
    try {
      $id = (int)($_POST['id_obra_social'] ?? 0);
      if ($id <= 0) throw new Exception('ID inválido');
      
      $check = $pdo->prepare("SELECT COUNT(*) FROM paciente WHERE Id_obra_social=?");
      $check->execute([$id]);
      if ($check->fetchColumn() > 0) {
        throw new Exception('No se puede eliminar: hay pacientes asociados');
      }
      
      $stmt = $pdo->prepare("DELETE FROM obra_social WHERE Id_obra_social=?");
      $stmt->execute([$id]);
      
      json_out(['ok'=>true,'msg'=>'Obra social eliminada']);
    } catch (Throwable $e) {
      json_out(['ok'=>false,'error'=>$e->getMessage()],500);
    }
  }

  // ========== MÉDICOS ==========
  if ($action === 'create_medico') {
    ensure_csrf();
    try {
      $nombre = trim($_POST['nombre'] ?? '');
      $apellido = trim($_POST['apellido'] ?? '');
      $dni = trim($_POST['dni'] ?? '');
      $email = trim($_POST['email'] ?? '');
      $passwordRaw = $_POST['password'] ?? '';
      if ($passwordRaw === '') throw new Exception('Contraseña vacía');
      $password = password_hash($passwordRaw, PASSWORD_BCRYPT);
      $legajo = trim($_POST['legajo'] ?? '');
      $idEsp = intval($_POST['especialidad'] ?? 0);
      
      $horariosJson = $_POST['horarios'] ?? '[]';
      $horarios = json_decode($horariosJson, true);
      
      if (!$nombre || !$apellido || !$dni || !$email || !$legajo || !$idEsp) {
        throw new Exception('Faltan campos obligatorios');
      }
      if (empty($horarios)) throw new Exception('Debe agregar al menos un horario');

      $pdo->beginTransaction();

      // ✅ TODAS LAS COLUMNAS EN MINÚSCULAS
      $stmt = $pdo->prepare("INSERT INTO usuario (nombre, apellido, dni, email, password, rol) VALUES (?,?,?,?,?,'medico')");
      $stmt->execute([$nombre, $apellido, $dni, $email, $password]);
      $idUsuario = $pdo->lastInsertId();

      $stmt = $pdo->prepare("INSERT INTO medico (legajo, Id_usuario, Id_Especialidad, activo) VALUES (?,?,?,1)");
      $stmt->execute([$legajo, $idUsuario, $idEsp]);
      $idMedico = $pdo->lastInsertId();

      $stmtHorario = $pdo->prepare("
        INSERT INTO horario_medico (Id_medico, dia_semana, hora_inicio, hora_fin) 
        VALUES (?, ?, ?, ?)
      ");
      foreach($horarios as $h) {
        $stmtHorario->execute([$idMedico, $h['dia'], $h['inicio'], $h['fin']]);
      }

      $pdo->commit();
      json_out(['ok'=>true,'msg'=>'Médico creado exitosamente']);
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      json_out(['ok'=>false,'error'=>$e->getMessage()],500);
    }
  }

  if ($action === 'update_medico') {
    ensure_csrf();
    try {
      $idMed = (int)($_POST['id_medico'] ?? 0);
      if ($idMed <= 0) throw new Exception('ID inválido');

      $nombre = trim($_POST['nombre'] ?? '');
      $apellido = trim($_POST['apellido'] ?? '');
      $email = trim($_POST['email'] ?? '');
      $legajo = trim($_POST['legajo'] ?? '');
      $idEsp = (int)($_POST['especialidad'] ?? 0);
      
      $horariosJson = $_POST['horarios'] ?? '[]';
      $horarios = json_decode($horariosJson, true);

      $stmt = $pdo->prepare("SELECT Id_usuario FROM medico WHERE Id_medico=?");
      $stmt->execute([$idMed]);
      $idUsuario = $stmt->fetchColumn();
      if (!$idUsuario) throw new Exception('Médico no encontrado');

      $pdo->beginTransaction();

      // ✅ COLUMNAS EN MINÚSCULAS
      $stmt = $pdo->prepare("UPDATE usuario SET nombre=?, apellido=?, email=? WHERE Id_usuario=?");
      $stmt->execute([$nombre, $apellido, $email, $idUsuario]);

      $stmt = $pdo->prepare("UPDATE medico SET legajo=?, Id_Especialidad=? WHERE Id_medico=?");
      $stmt->execute([$legajo, $idEsp, $idMed]);

      $pdo->prepare("DELETE FROM horario_medico WHERE Id_medico=?")->execute([$idMed]);
      
      if (!empty($horarios)) {
        $stmtHorario = $pdo->prepare("
          INSERT INTO horario_medico (Id_medico, dia_semana, hora_inicio, hora_fin) 
          VALUES (?, ?, ?, ?)
        ");
        foreach($horarios as $h) {
          $stmtHorario->execute([$idMed, $h['dia'], $h['inicio'], $h['fin']]);
        }
      }

      $pdo->commit();
      json_out(['ok'=>true,'msg'=>'Médico actualizado']);
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      json_out(['ok'=>false,'error'=>$e->getMessage()],500);
    }
  }

 // ========== ELIMINAR MÉDICO FÍSICAMENTE ==========
if ($action === 'delete_medico') {
    ensure_csrf();
    try {
        $idMed = (int)($_POST['id_medico'] ?? 0);
        if ($idMed <= 0) throw new Exception('ID inválido');

        $pdo->beginTransaction();

        // Verificar si tiene turnos, diagnósticos o recetas
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM turno WHERE Id_medico=?");
        $stmt->execute([$idMed]);
        $hasTurnos = $stmt->fetchColumn() > 0;
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM diagnostico WHERE Id_medico=?");
        $stmt->execute([$idMed]);
        $hasDiagnosticos = $stmt->fetchColumn() > 0;
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM receta WHERE Id_medico=?");
        $stmt->execute([$idMed]);
        $hasRecetas = $stmt->fetchColumn() > 0;
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM historial_clinico WHERE Id_medico=?");
        $stmt->execute([$idMed]);
        $hasHistorial = $stmt->fetchColumn() > 0;

        if ($hasTurnos || $hasDiagnosticos || $hasRecetas || $hasHistorial) {
            // Tiene registros asociados - SOLO desactivar
            $stmt = $pdo->prepare("UPDATE medico SET activo=0 WHERE Id_medico=?");
            $stmt->execute([$idMed]);
            $mensaje = 'Médico desactivado (tiene registros médicos asociados)';
        } else {
            // NO tiene registros - eliminar físicamente
            
            // 1. Obtener Id_usuario
            $stmt = $pdo->prepare("SELECT Id_usuario FROM medico WHERE Id_medico=?");
            $stmt->execute([$idMed]);
            $idUsuario = $stmt->fetchColumn();
            
            if (!$idUsuario) throw new Exception('Médico no encontrado');

            // 2. Eliminar horarios (CASCADE debe funcionar aquí)
            $stmt = $pdo->prepare("DELETE FROM horario_medico WHERE Id_medico=?");
            $stmt->execute([$idMed]);

            // 3. Eliminar médico
            $stmt = $pdo->prepare("DELETE FROM medico WHERE Id_medico=?");
            $stmt->execute([$idMed]);

            // 4. Eliminar usuario
            $stmt = $pdo->prepare("DELETE FROM usuario WHERE Id_usuario=?");
            $stmt->execute([$idUsuario]);

            $mensaje = 'Médico eliminado completamente';
        }

        $pdo->commit();
        json_out(['ok'=>true,'msg'=>$mensaje]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Error deleting medico: " . $e->getMessage());
        json_out(['ok'=>false,'error'=>'No se puede eliminar: ' . $e->getMessage()],500);
    }
}
  // ========== SECRETARIAS ==========
  if ($action === 'create_secretaria') {
    ensure_csrf();
    try {
      $nombre = trim($_POST['nombre'] ?? '');
      $apellido = trim($_POST['apellido'] ?? '');
      $dni = trim($_POST['dni'] ?? '');
      $email = trim($_POST['email'] ?? '');
      $passwordRaw = $_POST['password'] ?? '';
      if ($passwordRaw === '') throw new Exception('Contraseña vacía');
      $password = password_hash($passwordRaw, PASSWORD_BCRYPT);

      if (!$nombre || !$apellido || !$dni || !$email) throw new Exception('Faltan campos');

      // ✅ COLUMNAS EN MINÚSCULAS
      $stmt = $pdo->prepare("INSERT INTO usuario (nombre, apellido, dni, email, password, rol) VALUES (?,?,?,?,?,'secretaria')");
      $stmt->execute([$nombre, $apellido, $dni, $email, $password]);
      $idUsuario = $pdo->lastInsertId();

      $stmt = $pdo->prepare("INSERT INTO secretaria (Id_usuario, activo) VALUES (?,1)");
      $stmt->execute([$idUsuario]);

      json_out(['ok'=>true,'msg'=>'Secretaria creada']);
    } catch (Throwable $e) {
      json_out(['ok'=>false,'error'=>$e->getMessage()],500);
    }
  }

  if ($action === 'update_secretaria') {
    ensure_csrf();
    try {
      $idSec = (int)($_POST['id_secretaria'] ?? 0);
      if ($idSec <= 0) throw new Exception('ID inválido');

      $nombre = trim($_POST['nombre'] ?? '');
      $apellido = trim($_POST['apellido'] ?? '');
      $email = trim($_POST['email'] ?? '');

      $stmt = $pdo->prepare("SELECT Id_usuario FROM secretaria WHERE Id_secretaria=?");
      $stmt->execute([$idSec]);
      $idUsuario = $stmt->fetchColumn();
      if (!$idUsuario) throw new Exception('Secretaria no encontrada');

      // ✅ COLUMNAS EN MINÚSCULAS
      $stmt = $pdo->prepare("UPDATE usuario SET nombre=?, apellido=?, email=? WHERE Id_usuario=?");
      $stmt->execute([$nombre, $apellido, $email, $idUsuario]);

      json_out(['ok'=>true,'msg'=>'Secretaria actualizada']);
    } catch (Throwable $e) {
      json_out(['ok'=>false,'error'=>$e->getMessage()],500);
    }
  }

  if ($action === 'delete_secretaria') {
    ensure_csrf();
    try {
        $idSec = (int)($_POST['id_secretaria'] ?? 0);
        if ($idSec <= 0) throw new Exception('ID inválido');

        $pdo->beginTransaction();

        // Verificar si tiene turnos creados
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM turno WHERE Id_secretaria=?");
        $stmt->execute([$idSec]);
        $hasTurnos = $stmt->fetchColumn() > 0;

        if ($hasTurnos) {
            // Si creó turnos, solo desactivar
            $stmt = $pdo->prepare("UPDATE secretaria SET activo=0 WHERE Id_secretaria=?");
            $stmt->execute([$idSec]);
            $mensaje = 'Secretaria desactivada (tiene turnos asociados)';
        } else {
            // No tiene turnos, eliminar físicamente
            
            // 1. Obtener Id_usuario
            $stmt = $pdo->prepare("SELECT Id_usuario FROM secretaria WHERE Id_secretaria=?");
            $stmt->execute([$idSec]);
            $idUsuario = $stmt->fetchColumn();
            
            if (!$idUsuario) throw new Exception('Secretaria no encontrada');

            // 2. Eliminar secretaria
            $stmt = $pdo->prepare("DELETE FROM secretaria WHERE Id_secretaria=?");
            $stmt->execute([$idSec]);

            // 3. Eliminar usuario
            $stmt = $pdo->prepare("DELETE FROM usuario WHERE Id_usuario=?");
            $stmt->execute([$idUsuario]);

            $mensaje = 'Secretaria eliminada completamente';
        }

        $pdo->commit();
        json_out(['ok'=>true,'msg'=>$mensaje]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        json_out(['ok'=>false,'error'=>$e->getMessage()],500);
    }
}
  // ========== TURNOS ==========
  if ($action === 'doctors') {
    $espId = (int)($_GET['especialidad_id'] ?? 0);
    if ($espId <= 0) json_out(['ok'=>false,'error'=>'Especialidad inválida'],400);
    
    $stmt = $pdo->prepare("
      SELECT m.Id_medico, u.apellido, u.nombre
      FROM medico m
      JOIN usuario u ON u.Id_usuario=m.Id_usuario
      WHERE m.Id_Especialidad=? AND m.activo=1
      ORDER BY u.apellido, u.nombre
    ");
    $stmt->execute([$espId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear para JS
    foreach($items as &$item) {
      $item['Apellido'] = $item['apellido'];
      $item['Nombre'] = $item['nombre'];
    }
    unset($item);
    
    json_out(['ok'=>true,'items'=>$items]);
  }

  if ($action === 'agenda') {
    $medId = (int)($_GET['medico_id'] ?? 0);
    if ($medId <= 0) json_out(['ok'=>false,'error'=>'Médico inválido'],400);

    $from = $_GET['from'] ?? '';
    $to = $_GET['to'] ?? '';

    $where = "t.Id_medico=?";
    $params = [$medId];

    if ($from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
      $where .= " AND DATE(t.fecha)>=?";
      $params[] = $from;
    }
    if ($to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
      $where .= " AND DATE(t.fecha)<=?";
      $params[] = $to;
    }

    $stmt = $pdo->prepare("
      SELECT t.Id_turno, t.fecha, t.estado, t.Id_medico,
             up.apellido AS PApellido, up.nombre AS PNombre
      FROM turno t
      LEFT JOIN paciente p ON p.Id_paciente=t.Id_paciente
      LEFT JOIN usuario up ON up.Id_usuario=p.Id_usuario
      WHERE $where
      ORDER BY t.fecha DESC
    ");
    $stmt->execute($params);

    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
      $items[] = [
        'Id_turno' => (int)$r['Id_turno'],
        'Id_medico' => (int)($r['Id_medico'] ?? 0),
        'fecha' => $r['fecha'],
        'fecha_fmt' => date('d/m/Y H:i', strtotime($r['fecha'])),
        'estado' => strtolower($r['estado'] ?? 'cancelado'),
        'paciente' => trim(($r['PApellido'] ?? '') . ', ' . ($r['PNombre'] ?? ''))
      ];
    }

    json_out(['ok'=>true,'items'=>$items]);
  }

  if ($action === 'slots') {
    $medId = (int)($_GET['medico_id'] ?? 0);
    $date = $_GET['date'] ?? '';

    if ($medId <= 0) json_out(['ok'=>false,'error'=>'Médico inválido'],400);
    if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) json_out(['ok'=>false,'error'=>'Fecha inválida'],400);

    $diaSemana = weekday_name_es($date);

    $stmt = $pdo->prepare("
      SELECT hora_inicio, hora_fin 
      FROM horario_medico 
      WHERE Id_medico=? AND dia_semana=?
      ORDER BY hora_inicio
    ");
    $stmt->execute([$medId, $diaSemana]);
    $horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($horarios)) {
      json_out(['ok'=>true,'slots'=>[]]);
    }

    $stmt = $pdo->prepare("
      SELECT TIME(fecha) AS hhmm 
      FROM turno 
      WHERE DATE(fecha)=? AND Id_medico=? AND (estado IS NULL OR estado <> 'cancelado')
    ");
    $stmt->execute([$date, $medId]);
    $busy = array_map(fn($r) => substr($r['hhmm'], 0, 5), $stmt->fetchAll(PDO::FETCH_ASSOC));

    $slots = [];
    foreach($horarios as $bloque) {
      $inicio = new DateTime($date.' '.$bloque['hora_inicio']);
      $fin = new DateTime($date.' '.$bloque['hora_fin']);
      
      while ($inicio < $fin) {
        $slot = $inicio->format('H:i');
        if (!in_array($slot, $busy)) $slots[] = $slot;
        $inicio->modify('+30 minutes');
      }
    }

    json_out(['ok'=>true,'slots'=>$slots]);
  }

  if ($action === 'search_pacientes') {
    $q = trim($_GET['q'] ?? '');
    if (strlen($q) < 2) json_out(['ok'=>true,'items'=>[]]);

    $stmt = $pdo->prepare("
      SELECT p.Id_paciente, u.nombre, u.apellido, u.dni, u.email, os.nombre AS Obra_social
      FROM paciente p
      JOIN usuario u ON u.Id_usuario=p.Id_usuario
      LEFT JOIN obra_social os ON os.Id_obra_social=p.Id_obra_social
      WHERE p.activo=1 AND (
        u.dni LIKE ? OR
        u.nombre LIKE ? OR
        u.apellido LIKE ? OR
        u.email LIKE ?
      )
      LIMIT 10
    ");
    $like = "%$q%";
    $stmt->execute([$like, $like, $like, $like]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear para JS
    foreach($items as &$item) {
      $item['Nombre'] = $item['nombre'];
      $item['Apellido'] = $item['apellido'];
    }
    unset($item);

    json_out(['ok'=>true,'items'=>$items]);
  }

  if ($action === 'create_turno') {
    ensure_csrf();
    try {
      $medId = (int)($_POST['medico_id'] ?? 0);
      $pacId = (int)($_POST['paciente_id'] ?? 0);
      $date = trim($_POST['date'] ?? '');
      $time = trim($_POST['time'] ?? '');

      if ($medId <= 0) throw new Exception('Médico inválido');
      if ($pacId <= 0) throw new Exception('Paciente inválido');
      if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) throw new Exception('Fecha inválida');
      if (!$time || !preg_match('/^\d{2}:\d{2}$/', $time)) throw new Exception('Hora inválida');

      $fechaHora = "$date $time:00";

      // ✅ NUEVA VALIDACIÓN: Verificar si el paciente ya tiene turno activo con este médico
      $chkExisting = $pdo->prepare("
        SELECT COUNT(*) FROM turno 
        WHERE Id_paciente = ? 
        AND Id_medico = ? 
        AND (estado IS NULL OR estado = 'reservado')
        AND fecha >= NOW()
      ");
      $chkExisting->execute([$pacId, $medId]);
      
      if ($chkExisting->fetchColumn() > 0) {
        throw new Exception('Este paciente ya tiene un turno activo con este médico. Debe cancelar o completar el turno anterior.');
      }

      $check = $pdo->prepare("
        SELECT 1 FROM turno 
        WHERE fecha=? AND Id_medico=? AND (estado IS NULL OR estado <> 'cancelado')
        LIMIT 1
      ");
      $check->execute([$fechaHora, $medId]);
      if ($check->fetch()) throw new Exception('Ese horario ya está ocupado');

      $stmt = $pdo->prepare("
        INSERT INTO turno (fecha, estado, Id_paciente, Id_medico, Id_secretaria) 
        VALUES (?, 'reservado', ?, ?, ?)
      ");
      $stmt->execute([$fechaHora, $pacId, $medId, $mySecId]);

      json_out(['ok'=>true,'msg'=>'Turno creado exitosamente']);
    } catch (Throwable $e) {
      json_out(['ok'=>false,'error'=>$e->getMessage()],500);
    }
  }


  if ($action === 'cancel_turno') {
    ensure_csrf();
    try {
      $turnoId = (int)($_POST['turno_id'] ?? 0);
      if ($turnoId <= 0) throw new Exception('Turno inválido');

      $stmt = $pdo->prepare("UPDATE turno SET estado='cancelado' WHERE Id_turno=?");
      $stmt->execute([$turnoId]);

      json_out(['ok'=>true,'msg'=>'Turno cancelado']);
    } catch (Throwable $e) {
      json_out(['ok'=>false,'error'=>$e->getMessage()],500);
    }
  }

  if ($action === 'delete_turno') {
    ensure_csrf();
    try {
      $turnoId = (int)($_POST['turno_id'] ?? 0);
      if ($turnoId <= 0) throw new Exception('Turno inválido');

      $stmt = $pdo->prepare("DELETE FROM turno WHERE Id_turno=?");
      $stmt->execute([$turnoId]);

      json_out(['ok'=>true,'msg'=>'Turno eliminado']);
    } catch (Throwable $e) {
      json_out(['ok'=>false,'error'=>$e->getMessage()],500);
    }
  }

  if ($action === 'reschedule_turno') {
    ensure_csrf();
    try {
      $turnoId = (int)($_POST['turno_id'] ?? 0);
      $medId = (int)($_POST['medico_id'] ?? 0);
      $date = trim($_POST['date'] ?? '');
      $time = trim($_POST['time'] ?? '');

      if ($turnoId <= 0) throw new Exception('Turno inválido');
      if ($medId <= 0) throw new Exception('Médico inválido');
      if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) throw new Exception('Fecha inválida');
      if (!$time || !preg_match('/^\d{2}:\d{2}$/', $time)) throw new Exception('Hora inválida');

      $fechaHora = "$date $time:00";

      $check = $pdo->prepare("
        SELECT 1 FROM turno 
        WHERE fecha=? AND Id_medico=? AND (estado IS NULL OR estado <> 'cancelado') AND Id_turno<>?
        LIMIT 1
      ");
      $check->execute([$fechaHora, $medId, $turnoId]);
      if ($check->fetch()) throw new Exception('Ese horario ya está ocupado');

      $stmt = $pdo->prepare("
        UPDATE turno 
        SET fecha=?, Id_medico=?, estado='reservado' 
        WHERE Id_turno=?
      ");
      $stmt->execute([$fechaHora, $medId, $turnoId]);

      json_out(['ok'=>true,'msg'=>'Turno reprogramado exitosamente']);
    } catch (Throwable $e) {
      json_out(['ok'=>false,'error'=>$e->getMessage()],500);
    }
  }

  json_out(['ok'=>false,'error'=>'Acción no soportada: ' . $action],400);
}
// ========== CONFIRMAR TURNO ==========
if ($action === 'confirmar_turno') {
    ensure_csrf();
    require_once __DIR__ . '/../../config/email.php';
    
    try {
        $turnoId = (int)($_POST['turno_id'] ?? 0);
        if ($turnoId <= 0) throw new Exception('Turno inválido');
        
        // Obtener nombre del staff (ya tenemos $uid de must_staff)
        $stmt = $pdo->prepare("SELECT nombre, apellido FROM usuario WHERE Id_usuario = ?");
        $stmt->execute([$uid]);
        $staff = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$staff) throw new Exception('Usuario no encontrado');
        
        $staffNombre = trim(($staff['apellido'] ?? '') . ', ' . ($staff['nombre'] ?? ''));
        
        // Verificar que el turno existe y está pendiente
        $stmt = $pdo->prepare("SELECT estado FROM turno WHERE Id_turno = ?");
        $stmt->execute([$turnoId]);
        $turno = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$turno) {
            throw new Exception('Turno no encontrado');
        }
        
        if ($turno['estado'] === 'confirmado') {
            throw new Exception('Este turno ya está confirmado');
        }
        
        if ($turno['estado'] === 'rechazado') {
            throw new Exception('Este turno fue rechazado previamente');
        }
        
        // Iniciar transacción
        $pdo->beginTransaction();
        
        // Actualizar turno
        $idStaff = $isSec ? $mySecId : $uid;
        $stmt = $pdo->prepare("
            UPDATE turno 
            SET estado = 'confirmado',
                fecha_confirmacion = NOW(),
                Id_staff_confirma = ?,
                motivo_rechazo = NULL
            WHERE Id_turno = ?
        ");
        $stmt->execute([$idStaff, $turnoId]);
        
        // Enviar email de confirmación
        $resultadoEmail = notificarTurnoConfirmado($turnoId, $staffNombre, $pdo);
        
        $pdo->commit();
        
        if ($resultadoEmail['ok']) {
            json_out([
                'ok' => true, 
                'msg' => 'Turno confirmado y email enviado al paciente'
            ]);
        } else {
            json_out([
                'ok' => true, 
                'msg' => 'Turno confirmado pero hubo un error al enviar el email: ' . ($resultadoEmail['error'] ?? 'desconocido')
            ]);
        }
        
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Error confirmando turno: " . $e->getMessage());
        json_out(['ok' => false, 'error' => $e->getMessage()], 500);
    }
}

// ========== RECHAZAR TURNO ==========
if ($action === 'rechazar_turno') {
    ensure_csrf();
    require_once __DIR__ . '/../../config/email.php';
    
    try {
        $turnoId = (int)($_POST['turno_id'] ?? 0);
        $motivo = trim($_POST['motivo'] ?? '');
        
        if ($turnoId <= 0) throw new Exception('Turno inválido');
        if (empty($motivo)) throw new Exception('Debe especificar el motivo del rechazo');
        if (strlen($motivo) < 10) throw new Exception('El motivo debe tener al menos 10 caracteres');
        if (strlen($motivo) > 500) throw new Exception('El motivo es demasiado largo (máximo 500 caracteres)');
        
        // Obtener nombre del staff
        $stmt = $pdo->prepare("SELECT nombre, apellido FROM usuario WHERE Id_usuario = ?");
        $stmt->execute([$uid]);
        $staff = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$staff) throw new Exception('Usuario no encontrado');
        
        $staffNombre = trim(($staff['apellido'] ?? '') . ', ' . ($staff['nombre'] ?? ''));
        
        // Verificar que el turno existe
        $stmt = $pdo->prepare("SELECT estado FROM turno WHERE Id_turno = ?");
        $stmt->execute([$turnoId]);
        $turno = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$turno) {
            throw new Exception('Turno no encontrado');
        }
        
        if ($turno['estado'] === 'rechazado') {
            throw new Exception('Este turno ya fue rechazado');
        }
        
        // Iniciar transacción
        $pdo->beginTransaction();
        
        // Actualizar turno
        $idStaff = $isSec ? $mySecId : $uid;
        $stmt = $pdo->prepare("
            UPDATE turno 
            SET estado = 'rechazado',
                fecha_confirmacion = NOW(),
                Id_staff_confirma = ?,
                motivo_rechazo = ?
            WHERE Id_turno = ?
        ");
        $stmt->execute([$idStaff, $motivo, $turnoId]);
        
        // Enviar email de rechazo
        $resultadoEmail = notificarTurnoRechazado($turnoId, $motivo, $staffNombre, $pdo);
        
        $pdo->commit();
        
        if ($resultadoEmail['ok']) {
            json_out([
                'ok' => true, 
                'msg' => 'Turno rechazado y email enviado al paciente'
            ]);
        } else {
            json_out([
                'ok' => true, 
                'msg' => 'Turno rechazado pero hubo un error al enviar el email: ' . ($resultadoEmail['error'] ?? 'desconocido')
            ]);
        }
        
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Error rechazando turno: " . $e->getMessage());
        json_out(['ok' => false, 'error' => $e->getMessage()], 500);
    }
}

// ======= VALIDACIÓN DE ACCESO PARA HTML =======
[$uid,$isSec,$isMed,$myMedId,$mySecId] = must_staff($pdo);

if (!$uid) {
  header('Location: login.php');
  exit;
}

$nombre = $_SESSION['Nombre'] ?? '';
$apellido = $_SESSION['Apellido'] ?? '';
$rolTexto = $isSec ? 'Secretaría' : 'Médico';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Panel Administrativo - Clínica</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= htmlspecialchars($csrf) ?>">
  <link rel="stylesheet" href="../assets/css/admin.css">
  <link rel="stylesheet" href="../assets/css/theme_light.css">
</head>
<body>
<header class="hdr">
  <div class="brand">🏥 Panel Administrativo</div>
  <div class="who">👤 <?= htmlspecialchars($apellido.', '.$nombre) ?> — <?= $rolTexto ?></div>
  <nav class="actions">
    <?php if ($isMed): ?>
      <a class="btn primary" href="medico_panel.php" style="display:inline-flex;align-items:center;gap:6px">
        👨‍⚕️ Mi Panel Médico
      </a>
    <?php endif; ?>
    <a class="btn ghost" href="index.php">🏠 Inicio</a>
    <form class="inline" action="../../controllers/logout.php" method="post" style="display:inline;margin:0">
      <button class="btn ghost" type="submit">🚪 Salir</button>
    </form>
  </nav>
</header>

<main class="wrap">
  <div class="tabs">
    <button class="tab active" data-tab="medicos">👨‍⚕️ Médicos</button>
    <button class="tab" data-tab="secretarias">👩‍💼 Secretarias</button>
    <button class="tab" data-tab="obras">🏥 Obras Sociales</button>
    <button class="tab" data-tab="turnos">📅 Gestión de Turnos</button>
  </div>

  <!-- ===== MÉDICOS ===== -->
  <section id="tab-medicos" class="card">
    <h2>Gestión de Médicos</h2>
    
    <h3>➕ Crear Médico</h3>
    <form id="createMedicoForm" class="grid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px">
      <div class="field"><label>Nombre *</label><input type="text" name="nombre" required></div>
      <div class="field"><label>Apellido *</label><input type="text" name="apellido" required></div>
      <div class="field"><label>DNI *</label><input type="text" name="dni" required></div>
      <div class="field"><label>Email *</label><input type="email" name="email" required></div>
      <div class="field"><label>Contraseña *</label><input type="password" name="password" required></div>
      <div class="field"><label>Legajo *</label><input type="text" name="legajo" required></div>
      <div class="field"><label>Especialidad *</label><select name="especialidad" id="espCreateSelect" required></select></div>
    </form>

    <h3 style="margin-top:20px">⏰ Horarios de Atención</h3>
    <div class="card" style="background:#0f172a;padding:16px;margin-top:10px">
      <div class="grid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px">
        <div class="field">
          <label>Día</label>
          <select id="diaHorario">
            <option value="lunes">Lunes</option>
            <option value="martes">Martes</option>
            <option value="miercoles">Miércoles</option>
            <option value="jueves">Jueves</option>
            <option value="viernes">Viernes</option>
            <option value="sabado">Sábado</option>
            <option value="domingo">Domingo</option>
          </select>
        </div>
        <div class="field"><label>Hora inicio</label><input type="time" id="horaInicio" value="08:00"></div>
        <div class="field"><label>Hora fin</label><input type="time" id="horaFin" value="12:00"></div>
        <div style="display:flex;align-items:flex-end">
          <button type="button" id="btnAgregarHorario" class="btn ghost" style="width:100%">➕ Agregar</button>
        </div>
      </div>
      
      <div id="horariosListCreate" style="margin-top:16px;min-height:40px"></div>
      
      <div style="display:flex;align-items:center;gap:16px;margin-top:16px">
        <button type="button" id="btnCrearMedico" class="btn primary">✅ Crear Médico</button>
        <span id="msgCreateMed" class="msg"></span>
      </div>
    </div>

    <h3 style="margin-top:24px">📋 Lista de Médicos</h3>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Nombre</th><th>DNI</th><th>Especialidad</th><th>Legajo</th><th>Horarios</th><th>Acciones</th></tr></thead>
        <tbody id="tblMedicos"></tbody>
      </table>
    </div>
  </section>

  <!-- ===== SECRETARIAS ===== -->
  <section id="tab-secretarias" class="card hidden">
    <h2>Gestión de Secretarias</h2>
    
    <h3>➕ Crear Secretaria</h3>
    <form id="createSecretariaForm" class="grid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px">
      <div class="field"><label>Nombre *</label><input type="text" name="nombre" required></div>
      <div class="field"><label>Apellido *</label><input type="text" name="apellido" required></div>
      <div class="field"><label>DNI *</label><input type="text" name="dni" required></div>
      <div class="field"><label>Email *</label><input type="email" name="email" required></div>
      <div class="field"><label>Contraseña *</label><input type="password" name="password" required></div>
      <div style="display:flex;align-items:flex-end;gap:12px">
        <button class="btn primary" type="submit">✅ Crear</button>
        <span id="msgCreateSec" class="msg"></span>
      </div>
    </form>

    <h3 style="margin-top:24px">📋 Lista de Secretarias</h3>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Nombre</th><th>DNI</th><th>Email</th><th>Acciones</th></tr></thead>
        <tbody id="tblSecretarias"></tbody>
      </table>
    </div>
  </section>

  <!-- ===== OBRAS SOCIALES ===== -->
  <section id="tab-obras" class="card hidden">
    <h2>Gestión de Obras Sociales</h2>
    
    <h3>➕ Crear Obra Social</h3>
    <form id="createObraForm" class="grid" style="display:grid;grid-template-columns:1fr auto;gap:16px;align-items:flex-end">
      <div class="field">
        <label>Nombre *</label>
        <input type="text" name="nombre" required placeholder="Ej: OSPROTURA">
      </div>
      <div style="display:flex;align-items:center;gap:12px">
        <button class="btn primary" type="submit">✅ Crear</button>
        <span id="msgCreateObra" class="msg"></span>
      </div>
    </form>

    <h3 style="margin-top:24px">📋 Lista de Obras Sociales</h3>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody id="tblObras"></tbody>
      </table>
    </div>
  </section>

  <!-- ===== TURNOS ===== -->
  <section id="tab-turnos" class="card hidden">
    <h2>📅 Gestión de Turnos</h2>
    
    <div class="grid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:16px">
      <div class="field"><label for="fEsp">Especialidad</label><select id="fEsp"><option value="">Cargando…</option></select></div>
      <div class="field"><label for="fMed">Médico</label><select id="fMed" disabled><option value="">Elegí especialidad…</option></select></div>
      <div style="display:flex;align-items:flex-end">
        <button id="btnNewTurno" class="btn primary" disabled style="width:100%">➕ Crear Turno</button>
      </div>
    </div>

    <div class="grid" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:12px">
      <div class="field"><label for="fFrom">Desde</label><input id="fFrom" type="date"></div>
      <div class="field"><label for="fTo">Hasta</label><input id="fTo" type="date"></div>
    </div>

    <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
      <button id="btnRefresh" class="btn ghost">🔄 Actualizar</button>
      <button id="btnClearDates" class="btn ghost">❌ Quitar filtro</button>
      <span id="msgTurns" class="msg"></span>
    </div>

    <h3>📋 Turnos del Médico</h3>
    <div class="table-wrap">
      <table id="tblAgenda">
        <thead><tr><th>Fecha</th><th>Paciente</th><th>Estado</th><th>Acciones</th></tr></thead>
        <tbody></tbody>
      </table>
      <div id="noData" class="msg" style="padding:20px;text-align:center;display:none">Seleccioná un médico para ver sus turnos</div>
    </div>

    <div id="reprogSection" class="card" style="margin-top:16px;display:none;background:#0f172a">
      <h3>🔄 Reprogramar Turno</h3>
      <div class="grid" style="display:grid;grid-template-columns:1fr 1fr auto;gap:16px">
        <div class="field"><label for="newDate">Nueva fecha</label><input id="newDate" type="date"></div>
        <div class="field"><label for="newTime">Nuevo horario</label><select id="newTime"><option value="">Elegí fecha…</option></select></div>
        <div style="display:flex;align-items:flex-end;gap:8px">
          <button id="btnReprog" class="btn primary" disabled>✅ Confirmar</button>
          <button id="btnCancelReprog" class="btn ghost">❌ Cancelar</button>
        </div>
      </div>
    </div>
  </section>
</main>

<!-- Modal Crear Turno -->
<div id="modalCreateTurno" class="modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);z-index:1000;align-items:center;justify-content:center">
  <div class="modal-content" style="background:#111827;border:1px solid #1f2937;border-radius:16px;padding:32px;max-width:600px;width:90%">
    <h2>➕ Crear Nuevo Turno</h2>
    <form id="formCreateTurno">
      <input type="hidden" id="turnoMedicoId">
      <div class="field">
        <label>Buscar Paciente (DNI, nombre o email)</label>
        <input type="text" id="searchPaciente" placeholder="Escribí al menos 2 caracteres...">
        <div id="pacienteResults" style="margin-top:8px"></div>
      </div>
      <input type="hidden" id="selectedPacienteId" name="paciente_id">
      <div class="field">
        <label>Paciente seleccionado</label>
        <div id="selectedPacienteInfo" style="padding:8px;background:#0b1220;border-radius:8px;min-height:40px;color:var(--muted)">
          Ninguno
        </div>
      </div>
      <div class="grid" style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <div class="field"><label>Fecha</label><input type="date" id="turnoDate" required></div>
        <div class="field"><label>Horario</label><select id="turnoTime" required><option value="">Elegí fecha primero...</option></select></div>
      </div>
      <div style="display:flex;align-items:center;gap:12px;margin-top:16px">
        <button type="submit" class="btn primary">✅ Crear Turno</button>
        <button type="button" id="btnCloseModal" class="btn ghost">❌ Cancelar</button>
        <span id="msgModal" class="msg"></span>
      </div>
    </form>
  </div>
</div>

<!-- Modal Editar Médico -->
<div id="modalEditMedico" class="modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);z-index:1000;align-items:center;justify-content:center">
  <div class="modal-content" style="background:#111827;border:1px solid #1f2937;border-radius:16px;padding:32px;max-width:700px;width:90%;max-height:90vh;overflow-y:auto">
    <h2>✏️ Editar Médico</h2>
    <form id="formEditMedico">
      <input type="hidden" id="editMedId">
      <div class="grid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px">
        <div class="field"><label>Nombre</label><input type="text" id="editMedNombre" required></div>
        <div class="field"><label>Apellido</label><input type="text" id="editMedApellido" required></div>
        <div class="field"><label>Email</label><input type="email" id="editMedEmail" required></div>
        <div class="field"><label>Legajo</label><input type="text" id="editMedLegajo" required></div>
        <div class="field"><label>Especialidad</label><select id="editMedEsp" required></select></div>
      </div>

      <h3 style="margin-top:20px">⏰ Horarios de Atención</h3>
      <div class="card" style="background:#0f172a;padding:16px;margin-top:10px">
        <div class="grid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px">
          <div class="field">
            <label>Día</label>
            <select id="editDiaHorario">
              <option value="lunes">Lunes</option>
              <option value="martes">Martes</option>
              <option value="miercoles">Miércoles</option>
              <option value="jueves">Jueves</option>
              <option value="viernes">Viernes</option>
              <option value="sabado">Sábado</option>
              <option value="domingo">Domingo</option>
            </select>
          </div>
          <div class="field"><label>Hora inicio</label><input type="time" id="editHoraInicio" value="08:00"></div>
          <div class="field"><label>Hora fin</label><input type="time" id="editHoraFin" value="12:00"></div>
          <div style="display:flex;align-items:flex-end">
            <button type="button" id="btnAgregarHorarioEdit" class="btn ghost" style="width:100%">➕ Agregar</button>
          </div>
        </div>
        
        <div id="horariosListEdit" style="margin-top:16px;min-height:40px"></div>
      </div>

      <div style="display:flex;align-items:center;gap:12px;margin-top:16px">
        <button type="submit" class="btn primary">💾 Guardar</button>
        <button type="button" id="btnCloseMedicoModal" class="btn ghost">❌ Cancelar</button>
        <span id="msgMedicoModal" class="msg"></span>
      </div>
    </form>
  </div>
</div>

<!-- Modal Editar Secretaria -->
<div id="modalEditSecretaria" class="modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);z-index:1000;align-items:center;justify-content:center">
  <div class="modal-content" style="background:#111827;border:1px solid #1f2937;border-radius:16px;padding:32px;max-width:600px;width:90%">
    <h2>✏️ Editar Secretaria</h2>
    <form id="formEditSecretaria">
      <input type="hidden" id="editSecId">
      <div class="grid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px">
        <div class="field"><label>Nombre</label><input type="text" id="editSecNombre" required></div>
        <div class="field"><label>Apellido</label><input type="text" id="editSecApellido" required></div>
        <div class="field"><label>Email</label><input type="email" id="editSecEmail" required></div>
      </div>
      <div style="display:flex;align-items:center;gap:12px;margin-top:16px">
        <button type="submit" class="btn primary">💾 Guardar</button>
        <button type="button" id="btnCloseSecretariaModal" class="btn ghost">❌ Cancelar</button>
        <span id="msgSecretariaModal" class="msg"></span>
      </div>
    </form>
  </div>
</div>

<style>
.horario-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px;
  background: #1a2332;
  border: 1px solid var(--border);
  border-radius: 8px;
  margin-bottom: 8px;
}
.horario-info {
  flex: 1;
}
.btn-remove-horario {
  padding: 6px 12px;
  background: #ef4444;
  color: white;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 13px;
  font-weight: 600;
}
.btn-remove-horario:hover {
  background: #dc2626;
}
.paciente-item {
  padding: 10px;
  background: #0b1220;
  border: 1px solid var(--border);
  border-radius: 8px;
  margin-bottom: 8px;
  cursor: pointer;
  transition: all 0.2s;
}
.paciente-item:hover {
  background: #1a2332;
  border-color: var(--primary);
}
.paciente-item.selected {
  background: var(--primary);
  color: #001219;
  border-color: var(--primary);
}
.tab {
  transition: all 0.3s;
}
.tab:hover {
  background: rgba(34,211,238,0.1);
  border-color: var(--primary);
}
.tab.active {
  background: var(--primary);
  color: #001219;
  border-color: var(--primary);
}
</style>

<script>
// Sistema de gestión de horarios
(function(){
  const horariosCreate = [];
  const horariosEdit = [];
  
  function formatHour12(time24) {
    if (!time24) return '';
    const [hours, minutes] = time24.split(':');
    let h = parseInt(hours);
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    return `${h}:${minutes} ${ampm}`;
  }
  
  function horarioExists(list, dia, inicio, fin) {
    return list.some(h => h.dia === dia && h.inicio === inicio && h.fin === fin);
  }
  
  function horarioOverlaps(list, dia, inicioNuevo, finNuevo) {
    return list.some(h => {
      if (h.dia !== dia) return false;
      return (inicioNuevo < h.fin && finNuevo > h.inicio);
    });
  }
  
  function renderHorarios(list, containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    container.innerHTML = '';
    if (list.length === 0) {
      container.innerHTML = '<p style="color:var(--muted);font-size:13px;padding:8px">⚠️ No hay horarios agregados. Agregá al menos uno.</p>';
      return;
    }
    
    const diasOrden = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
    const sortedList = [...list].sort((a, b) => {
      const diaCompare = diasOrden.indexOf(a.dia) - diasOrden.indexOf(b.dia);
      if (diaCompare !== 0) return diaCompare;
      return a.inicio.localeCompare(b.inicio);
    });
    
    sortedList.forEach((h) => {
      const realIdx = list.indexOf(h);
      const div = document.createElement('div');
      div.className = 'horario-item';
      
      const horaInicio = formatHour12(h.inicio.substring(0,5));
      const horaFin = formatHour12(h.fin.substring(0,5));
      
      div.innerHTML = `
        <div class="horario-info">
          <strong style="text-transform:capitalize;color:var(--primary);font-size:14px">${h.dia}</strong>
          <br>
          <span style="color:var(--text);font-size:13px">🕒 ${horaInicio} - ${horaFin}</span>
        </div>
        <button type="button" class="btn-remove-horario" data-idx="${realIdx}">🗑️ Eliminar</button>
      `;
      container.appendChild(div);
    });
    
    container.querySelectorAll('.btn-remove-horario').forEach(btn => {
      btn.addEventListener('click', () => {
        const idx = parseInt(btn.dataset.idx);
        if (containerId === 'horariosListCreate') {
          horariosCreate.splice(idx, 1);
          renderHorarios(horariosCreate, 'horariosListCreate');
          const msgEl = document.getElementById('msgCreateMed');
          if (msgEl) {
            msgEl.textContent = '✅ Horario eliminado';
            msgEl.className = 'msg ok';
          }
        } else {
          horariosEdit.splice(idx, 1);
          renderHorarios(horariosEdit, 'horariosListEdit');
          const msgEl = document.getElementById('msgMedicoModal');
          if (msgEl) {
            msgEl.textContent = '✅ Horario eliminado';
            msgEl.className = 'msg ok';
          }
        }
      });
    });
  }
  
  document.getElementById('btnAgregarHorario')?.addEventListener('click', () => {
    const dia = document.getElementById('diaHorario').value;
    const inicio = document.getElementById('horaInicio').value;
    const fin = document.getElementById('horaFin').value;
    const msgEl = document.getElementById('msgCreateMed');
    
    if (!inicio || !fin) {
      alert('⚠️ Completá las horas de inicio y fin');
      return;
    }
    
    if (inicio >= fin) {
      alert('⚠️ La hora de inicio debe ser menor que la de fin');
      return;
    }
    
    const inicioFull = inicio + ':00';
    const finFull = fin + ':00';
    
    if (horarioExists(horariosCreate, dia, inicioFull, finFull)) {
      alert('⚠️ Este horario ya fue agregado');
      return;
    }
    
    if (horarioOverlaps(horariosCreate, dia, inicioFull, finFull)) {
      alert('⚠️ Este horario se solapa con uno existente');
      return;
    }
    
    horariosCreate.push({dia, inicio: inicioFull, fin: finFull});
    renderHorarios(horariosCreate, 'horariosListCreate');
    
    if (msgEl) {
      msgEl.textContent = `✅ Horario agregado`;
      msgEl.className = 'msg ok';
    }
  });
  
  document.getElementById('btnAgregarHorarioEdit')?.addEventListener('click', () => {
    const dia = document.getElementById('editDiaHorario').value;
    const inicio = document.getElementById('editHoraInicio').value;
    const fin = document.getElementById('editHoraFin').value;
    const msgEl = document.getElementById('msgMedicoModal');
    
    if (!inicio || !fin) {
      alert('⚠️ Completá las horas de inicio y fin');
      return;
    }
    
    if (inicio >= fin) {
      alert('⚠️ La hora de inicio debe ser menor que la de fin');
      return;
    }
    
    const inicioFull = inicio + ':00';
    const finFull = fin + ':00';
    
    if (horarioExists(horariosEdit, dia, inicioFull, finFull)) {
      alert('⚠️ Este horario ya fue agregado');
      return;
    }
    
    if (horarioOverlaps(horariosEdit, dia, inicioFull, finFull)) {
      alert('⚠️ Este horario se solapa con uno existente');
      return;
    }
    
    horariosEdit.push({dia, inicio: inicioFull, fin: finFull});
    renderHorarios(horariosEdit, 'horariosListEdit');
    
    if (msgEl) {
      msgEl.textContent = `✅ Horario agregado`;
      msgEl.className = 'msg ok';
    }
  });
  
  document.getElementById('btnCrearMedico')?.addEventListener('click', async () => {
    const form = document.getElementById('createMedicoForm');
    const msgEl = document.getElementById('msgCreateMed');
    
    if (!form.checkValidity()) {
      form.reportValidity();
      return;
    }
    
    if (horariosCreate.length === 0) {
      msgEl.textContent = '⚠️ Debe agregar al menos un horario';
      msgEl.className = 'msg err';
      alert('⚠️ Debe agregar al menos un horario de atención');
      return;
    }
    
    msgEl.textContent = '⏳ Creando médico...';
    msgEl.className = 'msg';
    
    const fd = new FormData(form);
    fd.set('action', 'create_medico');
    fd.set('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
    fd.set('horarios', JSON.stringify(horariosCreate));
    
    try {
      const res = await fetch('admin.php', {method:'POST', body:fd});
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Error');
      
      msgEl.textContent = '✅ ' + (data.msg || 'Médico creado');
      msgEl.className = 'msg ok';
      
      form.reset();
      horariosCreate.length = 0;
      renderHorarios(horariosCreate, 'horariosListCreate');
      
      setTimeout(() => window.location.reload(), 1500);
    } catch(e) {
      msgEl.textContent = '❌ ' + e.message;
      msgEl.className = 'msg err';
    }
  });
  
  window.loadMedicoHorarios = function(horarios) {
    horariosEdit.length = 0;
    if (horarios && Array.isArray(horarios)) {
      horarios.forEach(h => {
        horariosEdit.push({
          dia: h.Dia_semana || h.dia_semana,
          inicio: h.Hora_inicio || h.hora_inicio,
          fin: h.Hora_fin || h.hora_fin
        });
      });
    }
    renderHorarios(horariosEdit, 'horariosListEdit');
  };
  
  document.getElementById('formEditMedico')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const msgEl = document.getElementById('msgMedicoModal');
    
    if (horariosEdit.length === 0) {
      msgEl.textContent = '⚠️ Debe tener al menos un horario';
      msgEl.className = 'msg err';
      alert('⚠️ Debe tener al menos un horario de atención');
      return;
    }
    
    msgEl.textContent = '⏳ Actualizando...';
    msgEl.className = 'msg';
    
    const fd = new FormData();
    fd.append('action', 'update_medico');
    fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
    fd.append('id_medico', document.getElementById('editMedId').value);
    fd.append('nombre', document.getElementById('editMedNombre').value);
    fd.append('apellido', document.getElementById('editMedApellido').value);
    fd.append('email', document.getElementById('editMedEmail').value);
    fd.append('legajo', document.getElementById('editMedLegajo').value);
    fd.append('especialidad', document.getElementById('editMedEsp').value);
    fd.append('horarios', JSON.stringify(horariosEdit));
    
    try {
      const res = await fetch('admin.php', {method:'POST', body:fd});
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Error');
      
      msgEl.textContent = '✅ ' + (data.msg || 'Actualizado');
      msgEl.className = 'msg ok';
      
      setTimeout(() => window.location.reload(), 1500);
    } catch(e) {
      msgEl.textContent = '❌ ' + e.message;
      msgEl.className = 'msg err';
    }
  });
  
  renderHorarios(horariosCreate, 'horariosListCreate');
  renderHorarios(horariosEdit, 'horariosListEdit');
})();

console.log('✅ Admin panel inicializado correctamente');
</script>
<script src="../assets/js/turnos_utils.js"></script>
<script src="../assets/js/admin_validation.js"></script>
<script src="../assets/js/admin_fixes.js"></script> 
<script src="../assets/js/admin_turno_confirmation.js"></script>
<script src="../assets/js/admin.js"></script>
<script>
// Sistema de gestión de horarios - VERSIÓN CORREGIDA
(function(){
  'use strict';
  
  const horariosCreate = [];
  const horariosEdit = [];
  
  function formatHour12(time24) {
    if (!time24) return '';
    const [hours, minutes] = time24.split(':');
    let h = parseInt(hours);
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    return `${h}:${minutes} ${ampm}`;
  }
  
  function horarioExists(list, dia, inicio, fin) {
    return list.some(h => h.dia === dia && h.inicio === inicio && h.fin === fin);
  }
  
  function horarioOverlaps(list, dia, inicioNuevo, finNuevo) {
    return list.some(h => {
      if (h.dia !== dia) return false;
      return (inicioNuevo < h.fin && finNuevo > h.inicio);
    });
  }
  
  function renderHorarios(list, containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    container.innerHTML = '';
    if (list.length === 0) {
      container.innerHTML = '<p style="color:var(--muted);font-size:13px;padding:8px">⚠️ No hay horarios agregados. Agregá al menos uno.</p>';
      return;
    }
    
    const diasOrden = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
    const sortedList = [...list].sort((a, b) => {
      const diaCompare = diasOrden.indexOf(a.dia) - diasOrden.indexOf(b.dia);
      if (diaCompare !== 0) return diaCompare;
      return a.inicio.localeCompare(b.inicio);
    });
    
    sortedList.forEach((h) => {
      const realIdx = list.indexOf(h);
      const div = document.createElement('div');
      div.className = 'horario-item';
      
      const horaInicio = formatHour12(h.inicio.substring(0,5));
      const horaFin = formatHour12(h.fin.substring(0,5));
      
      div.innerHTML = `
        <div class="horario-info">
          <strong style="text-transform:capitalize;color:var(--primary);font-size:14px">${h.dia}</strong>
          <br>
          <span style="color:var(--text);font-size:13px">🕒 ${horaInicio} - ${horaFin}</span>
        </div>
        <button type="button" class="btn-remove-horario" data-idx="${realIdx}">🗑️ Eliminar</button>
      `;
      container.appendChild(div);
    });
    
    container.querySelectorAll('.btn-remove-horario').forEach(btn => {
      btn.addEventListener('click', () => {
        const idx = parseInt(btn.dataset.idx);
        if (containerId === 'horariosListCreate') {
          horariosCreate.splice(idx, 1);
          renderHorarios(horariosCreate, 'horariosListCreate');
          const msgEl = document.getElementById('msgCreateMed');
          if (msgEl) {
            msgEl.textContent = '✅ Horario eliminado';
            msgEl.className = 'msg ok';
          }
        } else {
          horariosEdit.splice(idx, 1);
          renderHorarios(horariosEdit, 'horariosListEdit');
          const msgEl = document.getElementById('msgMedicoModal');
          if (msgEl) {
            msgEl.textContent = '✅ Horario eliminado';
            msgEl.className = 'msg ok';
          }
        }
      });
    });
  }
  
  document.getElementById('btnAgregarHorario')?.addEventListener('click', () => {
    const dia = document.getElementById('diaHorario').value;
    const inicio = document.getElementById('horaInicio').value;
    const fin = document.getElementById('horaFin').value;
    const msgEl = document.getElementById('msgCreateMed');
    
    if (!inicio || !fin) {
      alert('⚠️ Completá las horas de inicio y fin');
      return;
    }
    
    if (inicio >= fin) {
      alert('⚠️ La hora de inicio debe ser menor que la de fin');
      return;
    }
    
    const inicioFull = inicio + ':00';
    const finFull = fin + ':00';
    
    if (horarioExists(horariosCreate, dia, inicioFull, finFull)) {
      alert('⚠️ Este horario ya fue agregado');
      return;
    }
    
    if (horarioOverlaps(horariosCreate, dia, inicioFull, finFull)) {
      alert('⚠️ Este horario se solapa con uno existente');
      return;
    }
    
    horariosCreate.push({dia, inicio: inicioFull, fin: finFull});
    renderHorarios(horariosCreate, 'horariosListCreate');
    
    if (msgEl) {
      msgEl.textContent = `✅ Horario agregado`;
      msgEl.className = 'msg ok';
    }
  });
  
  document.getElementById('btnAgregarHorarioEdit')?.addEventListener('click', () => {
    const dia = document.getElementById('editDiaHorario').value;
    const inicio = document.getElementById('editHoraInicio').value;
    const fin = document.getElementById('editHoraFin').value;
    const msgEl = document.getElementById('msgMedicoModal');
    
    if (!inicio || !fin) {
      alert('⚠️ Completá las horas de inicio y fin');
      return;
    }
    
    if (inicio >= fin) {
      alert('⚠️ La hora de inicio debe ser menor que la de fin');
      return;
    }
    
    const inicioFull = inicio + ':00';
    const finFull = fin + ':00';
    
    if (horarioExists(horariosEdit, dia, inicioFull, finFull)) {
      alert('⚠️ Este horario ya fue agregado');
      return;
    }
    
    if (horarioOverlaps(horariosEdit, dia, inicioFull, finFull)) {
      alert('⚠️ Este horario se solapa con uno existente');
      return;
    }
    
    horariosEdit.push({dia, inicio: inicioFull, fin: finFull});
    renderHorarios(horariosEdit, 'horariosListEdit');
    
    if (msgEl) {
      msgEl.textContent = `✅ Horario agregado`;
      msgEl.className = 'msg ok';
    }
  });
  
  document.getElementById('btnCrearMedico')?.addEventListener('click', async () => {
    const form = document.getElementById('createMedicoForm');
    const msgEl = document.getElementById('msgCreateMed');
    
    if (!form.checkValidity()) {
      form.reportValidity();
      return;
    }
    
    if (horariosCreate.length === 0) {
      msgEl.textContent = '⚠️ Debe agregar al menos un horario';
      msgEl.className = 'msg err';
      alert('⚠️ Debe agregar al menos un horario de atención');
      return;
    }
    
    msgEl.textContent = '⏳ Creando médico...';
    msgEl.className = 'msg';
    
    const fd = new FormData(form);
    fd.set('action', 'create_medico');
    fd.set('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
    fd.set('horarios', JSON.stringify(horariosCreate));
    
    try {
      const res = await fetch('admin.php', {method:'POST', body:fd});
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Error');
      
      msgEl.textContent = '✅ ' + (data.msg || 'Médico creado');
      msgEl.className = 'msg ok';
      
      form.reset();
      horariosCreate.length = 0;
      renderHorarios(horariosCreate, 'horariosListCreate');
      
      setTimeout(() => window.location.reload(), 1500);
    } catch(e) {
      msgEl.textContent = '❌ ' + e.message;
      msgEl.className = 'msg err';
    }
  });
  
  window.loadMedicoHorarios = function(horarios) {
    horariosEdit.length = 0;
    if (horarios && Array.isArray(horarios)) {
      horarios.forEach(h => {
        horariosEdit.push({
          dia: h.Dia_semana || h.dia_semana,
          inicio: h.Hora_inicio || h.hora_inicio,
          fin: h.Hora_fin || h.hora_fin
        });
      });
    }
    renderHorarios(horariosEdit, 'horariosListEdit');
  };
  
  document.getElementById('formEditMedico')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const msgEl = document.getElementById('msgMedicoModal');
    
    if (horariosEdit.length === 0) {
      msgEl.textContent = '⚠️ Debe tener al menos un horario';
      msgEl.className = 'msg err';
      alert('⚠️ Debe tener al menos un horario de atención');
      return;
    }
    
    msgEl.textContent = '⏳ Actualizando...';
    msgEl.className = 'msg';
    
    const fd = new FormData();
    fd.append('action', 'update_medico');
    fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
    fd.append('id_medico', document.getElementById('editMedId').value);
    fd.append('nombre', document.getElementById('editMedNombre').value);
    fd.append('apellido', document.getElementById('editMedApellido').value);
    fd.append('email', document.getElementById('editMedEmail').value);
    fd.append('legajo', document.getElementById('editMedLegajo').value);
    fd.append('especialidad', document.getElementById('editMedEsp').value);
    fd.append('horarios', JSON.stringify(horariosEdit));
    
    try {
      const res = await fetch('admin.php', {method:'POST', body:fd});
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Error');
      
      msgEl.textContent = '✅ ' + (data.msg || 'Actualizado');
      msgEl.className = 'msg ok';
      
      setTimeout(() => window.location.reload(), 1500);
    } catch(e) {
      msgEl.textContent = '❌ ' + e.message;
      msgEl.className = 'msg err';
    }
  });
  
  // Inicializar vistas
  renderHorarios(horariosCreate, 'horariosListCreate');
  renderHorarios(horariosEdit, 'horariosListEdit');
  
  console.log('✅ Sistema de horarios inicializado');
})();

console.log('✅ Admin panel cargado completamente');
</script>
</body>
</html>
