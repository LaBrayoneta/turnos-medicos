<?php
// views/pages/admin.php -
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/paths.php';

function dbx(){ return db(); }

function json_out($d, $c=200){ 
  if (ob_get_level()) ob_end_clean();
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

try {
  $pdo = dbx();
} catch (Throwable $e) {
  error_log("Database connection error in admin.php: " . $e->getMessage());
  
  if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    json_out(['ok'=>false,'error'=>'Error de conexión a base de datos'], 500);
  }
  
  die("Error de conexión a base de datos. Verifica la configuración.");
}

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
          AND (t.estado IS NULL OR t.estado IN ('reservado', 'pendiente_confirmacion', 'confirmado'))
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
      // Especialidades
      $esps = $pdo->query("
        SELECT Id_Especialidad, nombre 
        FROM especialidad 
        WHERE activo=1 
        ORDER BY nombre
      ")->fetchAll(PDO::FETCH_ASSOC);
      
      // Médicos con horarios
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
        
        foreach($med['horarios'] as &$h) {
          $h['Dia_semana'] = $h['dia_semana'];
          $h['Hora_inicio'] = $h['hora_inicio'];
          $h['Hora_fin'] = $h['hora_fin'];
        }
        unset($h);
        
        $med['Apellido'] = $med['apellido'];
        $med['Nombre'] = $med['nombre'];
        $med['Legajo'] = $med['legajo'];
        
        $dias = array_unique(array_map(fn($h)=>$h['dia_semana'], $horarios));
        $med['Dias_Disponibles'] = implode(',', $dias);
      }
      unset($med);
      
      // Secretarias
      $secs = $pdo->query("
        SELECT s.Id_secretaria, u.apellido, u.nombre, u.dni, u.email, u.Id_usuario
        FROM secretaria s
        JOIN usuario u ON u.Id_usuario=s.Id_usuario
        WHERE s.activo=1
        ORDER BY u.apellido, u.nombre
      ")->fetchAll(PDO::FETCH_ASSOC);
      
      foreach($secs as &$sec) {
        $sec['Apellido'] = $sec['apellido'];
        $sec['Nombre'] = $sec['nombre'];
      }
      unset($sec);
      
      // Obras sociales
      $obras = $pdo->query("
        SELECT Id_obra_social, nombre, activo 
        FROM obra_social 
        ORDER BY nombre
      ")->fetchAll(PDO::FETCH_ASSOC);
      
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

  if ($action === 'delete_medico') {
    ensure_csrf();
    try {
      $idMed = (int)($_POST['id_medico'] ?? 0);
      if ($idMed <= 0) throw new Exception('ID inválido');

      $pdo->beginTransaction();

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
        $stmt = $pdo->prepare("UPDATE medico SET activo=0 WHERE Id_medico=?");
        $stmt->execute([$idMed]);
        $mensaje = 'Médico desactivado (tiene registros médicos asociados)';
      } else {
        $stmt = $pdo->prepare("SELECT Id_usuario FROM medico WHERE Id_medico=?");
        $stmt->execute([$idMed]);
        $idUsuario = $stmt->fetchColumn();
        
        if (!$idUsuario) throw new Exception('Médico no encontrado');

        $stmt = $pdo->prepare("DELETE FROM horario_medico WHERE Id_medico=?");
        $stmt->execute([$idMed]);

        $stmt = $pdo->prepare("DELETE FROM medico WHERE Id_medico=?");
        $stmt->execute([$idMed]);

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

      $stmt = $pdo->prepare("SELECT COUNT(*) FROM turno WHERE Id_secretaria=?");
      $stmt->execute([$idSec]);
      $hasTurnos = $stmt->fetchColumn() > 0;

      if ($hasTurnos) {
        $stmt = $pdo->prepare("UPDATE secretaria SET activo=0 WHERE Id_secretaria=?");
        $stmt->execute([$idSec]);
        $mensaje = 'Secretaria desactivada (tiene turnos asociados)';
      } else {
        $stmt = $pdo->prepare("SELECT Id_usuario FROM secretaria WHERE Id_secretaria=?");
        $stmt->execute([$idSec]);
        $idUsuario = $stmt->fetchColumn();
        
        if (!$idUsuario) throw new Exception('Secretaria no encontrada');

        $stmt = $pdo->prepare("DELETE FROM secretaria WHERE Id_secretaria=?");
        $stmt->execute([$idSec]);

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
    
    foreach($items as &$item) {
      $item['Apellido'] = $item['apellido'];
      $item['Nombre'] = $item['nombre'];
    }
    unset($item);
    
    json_out(['ok'=>true,'items'=>$items]);
  }

     //AGENDA 
  if ($action === 'agenda') {
    $medId = (int)($_GET['medico_id'] ?? 0);
    if ($medId <= 0) json_out(['ok'=>false,'error'=>'Médico inválido'],400);

    $from = $_GET['from'] ?? '';
    $to = $_GET['to'] ?? '';

    $where = "t.Id_medico=? AND t.estado NOT IN ('rechazado', 'cancelado')";
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
      SELECT t.Id_turno, t.fecha, t.estado, t.Id_medico, t.atendido,
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
        'estado' => strtolower($r['estado'] ?? 'pendiente'),
        'atendido' => (bool)$r['atendido'],
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

      // Verificar duplicados
      $chkExisting = $pdo->prepare("
        SELECT COUNT(*) FROM turno 
        WHERE Id_paciente = ? 
        AND Id_medico = ? 
        AND estado IN ('pendiente_confirmacion', 'confirmado')
        AND fecha >= NOW()
      ");
      $chkExisting->execute([$pacId, $medId]);
      
      if ($chkExisting->fetchColumn() > 0) {
        throw new Exception('Este paciente ya tiene un turno activo con este médico');
      }

      // Verificar horario
      $check = $pdo->prepare("
        SELECT 1 FROM turno 
        WHERE fecha=? AND Id_medico=? AND estado IN ('pendiente_confirmacion', 'confirmado')
        LIMIT 1
      ");
      $check->execute([$fechaHora, $medId]);
      if ($check->fetch()) throw new Exception('Ese horario ya está ocupado');

      // ✅ INSERTAR TURNO
      $stmt = $pdo->prepare("
        INSERT INTO turno (fecha, estado, Id_paciente, Id_medico, Id_secretaria, fecha_confirmacion, Id_staff_confirma) 
        VALUES (?, 'confirmado', ?, ?, ?, NOW(), ?)
      ");
      $stmt->execute([$fechaHora, $pacId, $medId, $mySecId, $uid]);

      $turnoId = $pdo->lastInsertId();
      
      // ✅ LOG DETALLADO para debugging
      error_log("✅ TURNO CREADO: ID=$turnoId, Médico=$medId, Paciente=$pacId, Fecha=$fechaHora, Estado=confirmado");

      json_out([
        'ok'=>true,
        'msg'=>'Turno creado y confirmado automáticamente',
        'turno_id' => $turnoId,
        'debug' => [
          'medico_id' => $medId,
          'paciente_id' => $pacId,
          'fecha_hora' => $fechaHora,
          'estado' => 'confirmado'
        ]
      ]);
    } catch (Throwable $e) {
      error_log("❌ Error create_turno: " . $e->getMessage());
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
        SET fecha=?, Id_medico=?, estado='confirmado' 
        WHERE Id_turno=?
      ");
      $stmt->execute([$fechaHora, $medId, $turnoId]);

      json_out(['ok'=>true,'msg'=>'Turno reprogramado exitosamente']);
    } catch (Throwable $e) {
      json_out(['ok'=>false,'error'=>$e->getMessage()],500);
    }
  }

  // ========== TURNOS PENDIENTES ==========
  if ($action === 'turnos_pendientes') {
    try {
      $where = "t.estado = 'pendiente_confirmacion'";
      $params = [];
      
      $espId = (int)($_GET['especialidad_id'] ?? 0);
      if ($espId > 0) {
        $where .= " AND m.Id_Especialidad = ?";
        $params[] = $espId;
      }
      
      $medId = (int)($_GET['medico_id'] ?? 0);
      if ($medId > 0) {
        $where .= " AND t.Id_medico = ?";
        $params[] = $medId;
      }
      
      $from = $_GET['from'] ?? '';
      if ($from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $where .= " AND DATE(t.fecha) >= ?";
        $params[] = $from;
      }
      
      $to = $_GET['to'] ?? '';
      if ($to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $where .= " AND DATE(t.fecha) <= ?";
        $params[] = $to;
      }

      $stmt = $pdo->prepare("
        SELECT 
          t.Id_turno,
          t.fecha,
          t.Id_medico,
          DATE_FORMAT(t.fecha, '%d/%m/%Y %H:%i') as fecha_fmt,
          CONCAT(up.apellido, ', ', up.nombre) AS paciente,
          up.dni AS paciente_dni,
          up.email AS paciente_email,
          CONCAT(um.apellido, ', ', um.nombre) AS medico,
          e.nombre AS especialidad,
          os.nombre AS obra_social
        FROM turno t
        JOIN paciente p ON p.Id_paciente = t.Id_paciente
        JOIN usuario up ON up.Id_usuario = p.Id_usuario
        JOIN medico m ON m.Id_medico = t.Id_medico
        JOIN usuario um ON um.Id_usuario = m.Id_usuario
        LEFT JOIN especialidad e ON e.Id_Especialidad = m.Id_Especialidad
        LEFT JOIN obra_social os ON os.Id_obra_social = p.Id_obra_social
        WHERE $where
        ORDER BY t.fecha ASC
      ");
      $stmt->execute($params);

      $items = [];
      foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $items[] = [
          'Id_turno' => (int)$r['Id_turno'],
          'Id_medico' => (int)$r['Id_medico'],
          'fecha' => $r['fecha'],
          'fecha_fmt' => $r['fecha_fmt'],
          'paciente' => $r['paciente'],
          'paciente_dni' => $r['paciente_dni'],
          'paciente_email' => $r['paciente_email'],
          'medico' => $r['medico'],
          'especialidad' => $r['especialidad'],
          'obra_social' => $r['obra_social'] ?? 'Sin obra social'
        ];
      }

      json_out(['ok'=>true,'items'=>$items]);
    } catch (Throwable $e) {
      error_log('Error en turnos_pendientes: ' . $e->getMessage());
      json_out(['ok'=>false,'error'=>$e->getMessage()], 500);
    }
  }

  // ========== CONFIRMAR TURNO ==========
  // BUSCAR ESTA SECCIÓN EN admin.php Y REEMPLAZARLA COMPLETA
  
  if ($action === 'confirmar_turno') {
    ensure_csrf();
    
    try {
      $turnoId = (int)($_POST['turno_id'] ?? 0);
      if ($turnoId <= 0) throw new Exception('Turno inválido');
      
      // Obtener datos del staff
      $stmt = $pdo->prepare("SELECT nombre, apellido FROM usuario WHERE Id_usuario = ?");
      $stmt->execute([$uid]);
      $staff = $stmt->fetch(PDO::FETCH_ASSOC);
      if (!$staff) throw new Exception('Usuario no encontrado');
      $staffNombre = trim(($staff['apellido'] ?? '') . ', ' . ($staff['nombre'] ?? ''));
      
      // Verificar estado actual
      $stmt = $pdo->prepare("SELECT estado FROM turno WHERE Id_turno = ?");
      $stmt->execute([$turnoId]);
      $turno = $stmt->fetch(PDO::FETCH_ASSOC);
      
      if (!$turno) throw new Exception('Turno no encontrado');
      if ($turno['estado'] === 'confirmado') throw new Exception('Ya está confirmado');
      if ($turno['estado'] === 'rechazado' || $turno['estado'] === 'cancelado') {
        throw new Exception('Turno rechazado/cancelado');
      }
      
      // Actualizar turno
      $pdo->beginTransaction();
      
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
      
      $pdo->commit();
      
      // Intentar enviar email (no bloquea)
      $emailMsg = '';
      try {
        $emailFile = __DIR__ . '/../../config/email.php';
        if (file_exists($emailFile)) {
          require_once $emailFile;
          if (function_exists('notificarTurnoConfirmado')) {
            $res = notificarTurnoConfirmado($turnoId, $staffNombre, $pdo);
            $emailMsg = $res['ok'] ? ' - Email enviado' : '';
          }
        }
      } catch (Throwable $e) {
        error_log("Email error: " . $e->getMessage());
      }
      
      json_out(['ok' => true, 'msg' => 'Turno confirmado' . $emailMsg]);
      
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      error_log("Error confirmar: " . $e->getMessage());
      json_out(['ok' => false, 'error' => $e->getMessage()], 500);
    }
  }

  // ========== RECHAZAR TURNO ==========
  if ($action === 'rechazar_turno') {
    ensure_csrf();
    
    try {
      $turnoId = (int)($_POST['turno_id'] ?? 0);
      $motivo = trim($_POST['motivo'] ?? '');
      
      if ($turnoId <= 0) throw new Exception('Turno inválido');
      if (empty($motivo)) throw new Exception('Motivo requerido');
      if (strlen($motivo) < 10) throw new Exception('Motivo muy corto (mín 10 caracteres)');
      
      // Obtener datos del staff
      $stmt = $pdo->prepare("SELECT nombre, apellido FROM usuario WHERE Id_usuario = ?");
      $stmt->execute([$uid]);
      $staff = $stmt->fetch(PDO::FETCH_ASSOC);
      if (!$staff) throw new Exception('Usuario no encontrado');
      $staffNombre = trim(($staff['apellido'] ?? '') . ', ' . ($staff['nombre'] ?? ''));
      
      // Verificar estado
      $stmt = $pdo->prepare("SELECT estado FROM turno WHERE Id_turno = ?");
      $stmt->execute([$turnoId]);
      $turno = $stmt->fetch(PDO::FETCH_ASSOC);
      
      if (!$turno) throw new Exception('Turno no encontrado');
      if ($turno['estado'] === 'rechazado') throw new Exception('Ya está rechazado');
      
      // Actualizar turno
      $pdo->beginTransaction();
      
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
      
      $pdo->commit();
      
      // Intentar enviar email (no bloquea)
      $emailMsg = '';
      try {
        $emailFile = __DIR__ . '/../../config/email.php';
        if (file_exists($emailFile)) {
          require_once $emailFile;
          if (function_exists('notificarTurnoRechazado')) {
            $res = notificarTurnoRechazado($turnoId, $motivo, $staffNombre, $pdo);
            $emailMsg = $res['ok'] ? ' - Email enviado' : '';
          }
        }
      } catch (Throwable $e) {
        error_log("Email error: " . $e->getMessage());
      }
      
      json_out(['ok' => true, 'msg' => 'Turno rechazado' . $emailMsg]);
      
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      error_log("Error rechazar: " . $e->getMessage());
      json_out(['ok' => false, 'error' => $e->getMessage()], 500);
    }
  }
  // Si llegamos aquí, la acción no fue reconocida
  json_out(['ok'=>false,'error'=>'Acción no soportada: ' . $action],400);
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
  
  <!-- ✅ CSS EXTERNOS -->
  <link rel="stylesheet" href="../assets/css/admin.css">
  <link rel="stylesheet" href="../assets/css/admin_additional.css">
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
    <button class="tab" data-tab="turnos-pendientes">⏳ Turnos Reservados</button>
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

  <!-- ===== TURNOS PENDIENTES ===== -->
  <section id="tab-turnos-pendientes" class="card hidden">
    <h2>⏳ Turnos Reservados - Pendientes de Confirmación</h2>
    
    <p style="color:var(--muted);margin-bottom:20px;padding:12px;background:rgba(251,146,60,0.1);border-left:3px solid var(--warn);border-radius:8px">
      ℹ️ <strong>Estos turnos fueron solicitados por pacientes y esperan confirmación o rechazo por parte del staff.</strong>
      <br>Una vez confirmados, se enviará un email automático al paciente con los detalles.
    </p>
    
    <div class="grid" style="display:grid;grid-template-columns:repeat(2,1fr);gap:16px;margin-bottom:16px">
      <div class="field">
        <label for="fEspPendientes">Filtrar por Especialidad</label>
        <select id="fEspPendientes">
          <option value="">Todas las especialidades</option>
        </select>
      </div>
      <div class="field">
        <label for="fMedPendientes">Filtrar por Médico</label>
        <select id="fMedPendientes" disabled>
          <option value="">Elegí especialidad primero</option>
        </select>
      </div>
    </div>

    <div class="grid" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:12px">
      <div class="field">
        <label for="fFromPendientes">Desde</label>
        <input id="fFromPendientes" type="date">
      </div>
      <div class="field">
        <label for="fToPendientes">Hasta</label>
        <input id="fToPendientes" type="date">
      </div>
    </div>

    <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px">
      <button id="btnRefreshPendientes" class="btn ghost">🔄 Actualizar</button>
      <button id="btnClearFiltersPendientes" class="btn ghost">❌ Limpiar filtros</button>
      <span id="msgPendientes" class="msg"></span>
    </div>

    <div class="table-wrap">
      <table id="tblTurnosPendientes">
        <thead>
          <tr>
            <th>Fecha y Hora</th>
            <th>Paciente</th>
            <th>DNI</th>
            <th>Médico</th>
            <th>Especialidad</th>
            <th>Obra Social</th>
            <th style="width:200px">Acciones</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
      <div id="noPendientes" class="msg" style="padding:40px;text-align:center;display:none">
        ✅ No hay turnos pendientes de confirmación
      </div>
    </div>
  </section>

  <!-- ===== GESTIÓN DE TURNOS ===== -->
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
      </div>
    </div>
  </section>
</main>

<!-- MODALES -->
<!-- Modal Crear Turno -->
<div id="modalCreateTurno" class="modal">
  <div class="modal-content">
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
<div id="modalEditMedico" class="modal">
  <div class="modal-content" style="max-width:700px;max-height:90vh;overflow-y:auto">
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
<div id="modalEditSecretaria" class="modal">
  <div class="modal-content">
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

<!-- ✅ JAVASCRIPT EXTERNOS -->
<script src="../assets/js/theme_toggle.js"></script>
<script src="../assets/js/turnos_utils.js"></script>
<script src="../assets/js/admin_validation.js"></script>
<script src="../assets/js/admin_fixes.js"></script> 
<script src="../assets/js/admin_turno_confirmation.js"></script>
<script src="../assets/js/admin.js"></script>
<script src="../assets/js/admin_horarios.js"></script>

<script>
console.log('✅ Admin panel cargado completamente');
</script>
</body>
</html>