<!-- turnos_api.php -->
<?php
// turnos_api.php — API mejorada con horarios flexibles por médico
error_reporting(0);
ini_set('display_errors', '0');
session_start();
require_once __DIR__ . '/../config/db.php';

function json_out($data, $code=200){ 
  http_response_code($code); 
  header('Content-Type: application/json; charset=utf-8'); 
  echo json_encode($data, JSON_UNESCAPED_UNICODE); 
  exit; 
}
function require_login(){ 
  if (empty($_SESSION['Id_usuario'])) json_out(['ok'=>false,'error'=>'No autenticado'],401); 
  return (int)$_SESSION['Id_usuario']; 
}
function ensure_csrf(){ 
  $t = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''; 
  if (!$t || !hash_equals($_SESSION['csrf_token'] ?? '', $t)) json_out(['ok'=>false,'error'=>'CSRF inválido'],400); 
}
function get_day_name($ymd){
  $dias = ['domingo','lunes','martes','miercoles','jueves','viernes','sabado'];
  $w = (int)date('w', strtotime($ymd));
  return $dias[$w];
}

$pdo = db();
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// Listar especialidades
if ($action === 'specialties') {
  $rows = $pdo->query("SELECT Id_Especialidad, Nombre FROM especialidad WHERE Activo=1 ORDER BY Nombre")->fetchAll(PDO::FETCH_ASSOC);
  json_out(['ok'=>true,'items'=>$rows]);
}

// Listar médicos por especialidad
if ($action === 'doctors') {
  $esp = (int)($_GET['especialidad_id'] ?? 0);
  if ($esp<=0) json_out(['ok'=>false,'error'=>'Especialidad inválida'],400);
  $st = $pdo->prepare("
    SELECT m.Id_medico, u.Nombre, u.Apellido
    FROM medico m
    JOIN usuario u ON u.Id_usuario = m.Id_usuario
    WHERE m.Id_Especialidad = ? AND m.Activo=1
    ORDER BY u.Apellido, u.Nombre
  ");
  $st->execute([$esp]);
  json_out(['ok'=>true,'items'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
}

// Información del médico con horarios detallados
if ($action === 'medico_info') {
  $med = (int)($_GET['medico_id'] ?? 0);
  if ($med<=0) json_out(['ok'=>false,'error'=>'Médico inválido'],400);
  
  $st = $pdo->prepare("
    SELECT m.Id_medico, u.Nombre, u.Apellido, m.Duracion_Turno
    FROM medico m
    JOIN usuario u ON u.Id_usuario = m.Id_usuario
    WHERE m.Id_medico = ? AND m.Activo=1
  ");
  $st->execute([$med]);
  $medico = $st->fetch(PDO::FETCH_ASSOC);
  if(!$medico) json_out(['ok'=>false,'error'=>'Médico no encontrado'],404);
  
  // Obtener horarios desde la tabla horario_medico
  $st2 = $pdo->prepare("
    SELECT Dia_semana, Hora_inicio, Hora_fin 
    FROM horario_medico 
    WHERE Id_medico=? 
    ORDER BY 
      FIELD(Dia_semana, 'lunes','martes','miercoles','jueves','viernes','sabado','domingo'),
      Hora_inicio
  ");
  $st2->execute([$med]);
  $horarios = $st2->fetchAll(PDO::FETCH_ASSOC);
  
  // Construir lista de días disponibles
  $dias_unicos = array_unique(array_map(fn($h)=>$h['Dia_semana'], $horarios));
  
  json_out(['ok'=>true,'medico'=>[
    'id'=>(int)$medico['Id_medico'],
    'nombre'=>$medico['Nombre'].' '.$medico['Apellido'],
    'dias_disponibles'=>implode(',', $dias_unicos),
    'horarios'=>$horarios,
    'duracion_turno'=>(int)($medico['Duracion_Turno']??30)
  ]]);
}

// Obtener slots disponibles para un día y médico
if ($action === 'slots') {
  $med = (int)($_GET['medico_id'] ?? 0);
  $date = $_GET['date'] ?? '';

  if ($med <= 0) json_out(['ok' => false, 'error' => 'Médico inválido'], 400);
  if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) json_out(['ok' => false, 'error' => 'Fecha inválida'], 400);

  $diaSemana = get_day_name($date);
  
  // Obtener todos los horarios del médico para ese día
  $st = $pdo->prepare("
    SELECT Hora_inicio, Hora_fin 
    FROM horario_medico 
    WHERE Id_medico=? AND Dia_semana=?
    ORDER BY Hora_inicio
  ");
  $st->execute([$med, $diaSemana]);
  $horarios = $st->fetchAll(PDO::FETCH_ASSOC);
  
  if (empty($horarios)) {
    json_out(['ok' => true, 'slots' => []]); // día no disponible
  }

  // Obtener turnos ocupados
  $st = $pdo->prepare("
    SELECT TIME(Fecha) AS hhmm 
    FROM turno 
    WHERE DATE(Fecha)=? AND Id_medico=? AND (Estado IS NULL OR Estado <> 'cancelado')
  ");
  $st->execute([$date, $med]);
  $busy = array_map(fn($r) => substr($r['hhmm'], 0, 5), $st->fetchAll(PDO::FETCH_ASSOC));

  // Generar slots para cada bloque horario
  $slots = [];
  foreach($horarios as $bloque) {
    $inicio = new DateTime($date.' '.$bloque['Hora_inicio']);
    $fin = new DateTime($date.' '.$bloque['Hora_fin']);
    
    while ($inicio < $fin) {
      $slot = $inicio->format('H:i');
      if (!in_array($slot, $busy)) $slots[] = $slot;
      $inicio->modify('+30 minutes');
    }
  }

  json_out(['ok' => true, 'slots' => $slots]);
}

// Mis turnos
if ($action === 'my_appointments') {
  $uid = require_login();
  $st = $pdo->prepare("SELECT Id_paciente FROM paciente WHERE Id_usuario=? LIMIT 1");
  $st->execute([$uid]); 
  $pacId = (int)($st->fetchColumn() ?: 0);
  if ($pacId<=0) json_out(['ok'=>true,'items'=>[]]);
  
  $st = $pdo->prepare("
    SELECT t.Id_turno, t.Fecha, t.Estado, t.Id_medico,
           um.Nombre AS MNombre, um.Apellido AS MApellido, 
           e.Nombre AS Especialidad
    FROM turno t
    LEFT JOIN medico m ON m.Id_medico = t.Id_medico
    LEFT JOIN usuario um ON um.Id_usuario = m.Id_usuario
    LEFT JOIN especialidad e ON e.Id_Especialidad = m.Id_Especialidad
    WHERE t.Id_paciente = ?
    ORDER BY t.Fecha DESC
  ");
  $st->execute([$pacId]);
  $items=[]; 
  foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r){
    $items[]=[
      'Id_turno'=>(int)$r['Id_turno'],
      'Id_medico'=>(int)($r['Id_medico'] ?? 0),
      'fecha'=>$r['Fecha'],
      'fecha_fmt'=>date('d/m/Y H:i', strtotime($r['Fecha'])),
      'estado'=>$r['Estado'] ?? '',
      'medico'=>trim(($r['MApellido'] ?? '').', '.($r['MNombre'] ?? '')),
      'especialidad'=>$r['Especialidad'] ?? '',
    ];
  }
  json_out(['ok'=>true,'items'=>$items]);
}

// Reservar turno
if ($action === 'book' && $_SERVER['REQUEST_METHOD']==='POST') {
  ensure_csrf();
  $uid = require_login();
  $date = trim($_POST['date'] ?? '');
  $time = trim($_POST['time'] ?? '');
  $med  = (int)($_POST['medico_id'] ?? 0);
  
  if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) json_out(['ok'=>false,'error'=>'Fecha inválida'],400);
  if (!$time || !preg_match('/^\d{2}:\d{2}$/',$time)) json_out(['ok'=>false,'error'=>'Hora inválida'],400);
  if ($med<=0) json_out(['ok'=>false,'error'=>'Médico inválido'],400);

  $chkM = $pdo->prepare("SELECT 1 FROM medico WHERE Id_medico=? AND Activo=1");
  $chkM->execute([$med]); 
  if (!$chkM->fetch()) json_out(['ok'=>false,'error'=>'Médico no encontrado'],404);

  $st = $pdo->prepare("SELECT Id_paciente FROM paciente WHERE Id_usuario=? LIMIT 1");
  $st->execute([$uid]); 
  $pacId = (int)($st->fetchColumn() ?: 0);
  if ($pacId<=0) json_out(['ok'=>false,'error'=>'El usuario no está registrado como paciente'],400);

  $dt = DateTime::createFromFormat('Y-m-d H:i', "$date $time");
  if (!$dt) json_out(['ok'=>false,'error'=>'Fecha/hora inválidas'],400);

  $fechaHora = $dt->format('Y-m-d H:i:00');
  
  // Verificar que el horario esté dentro de los bloques del médico
  $diaSemana = get_day_name($date);
  $chkHorario = $pdo->prepare("
    SELECT 1 FROM horario_medico 
    WHERE Id_medico=? AND Dia_semana=? 
    AND TIME(?)>=Hora_inicio AND TIME(?)<Hora_fin
    LIMIT 1
  ");
  $chkHorario->execute([$med, $diaSemana, $time, $time]);
  if (!$chkHorario->fetch()) json_out(['ok'=>false,'error'=>'El horario seleccionado no está disponible para este médico'],400);
  
  $chk = $pdo->prepare("
    SELECT 1 FROM turno 
    WHERE Fecha=? AND Id_medico=? AND (Estado IS NULL OR Estado <> 'cancelado') 
    LIMIT 1
  ");
  $chk->execute([$fechaHora, $med]);
  if ($chk->fetch()) json_out(['ok'=>false,'error'=>'Ese horario ya está ocupado'],409);

  $ins = $pdo->prepare("
    INSERT INTO turno (Fecha, Estado, Id_paciente, Id_medico) 
    VALUES (?, 'reservado', ?, ?)
  ");
  $ins->execute([$fechaHora, $pacId, $med]);

  json_out(['ok'=>true,'mensaje'=>'Turno reservado exitosamente']);
}

// Cancelar turno
if ($action === 'cancel' && $_SERVER['REQUEST_METHOD']==='POST') {
  ensure_csrf();
  $uid = require_login();
  $tid = (int)($_POST['turno_id'] ?? 0);
  if ($tid<=0) json_out(['ok'=>false,'error'=>'Turno inválido'],400);
  
  $st = $pdo->prepare("
    SELECT t.Id_turno
    FROM turno t 
    JOIN paciente p ON p.Id_paciente=t.Id_paciente
    WHERE t.Id_turno=? AND p.Id_usuario=? 
    LIMIT 1
  ");
  $st->execute([$tid,$uid]); 
  if (!$st->fetch()) json_out(['ok'=>false,'error'=>'No autorizado'],403);
  
  $pdo->prepare("UPDATE turno SET Estado='cancelado' WHERE Id_turno=?")->execute([$tid]);
  json_out(['ok'=>true,'mensaje'=>'Turno cancelado']);
}

// Reprogramar turno
if ($action === 'reschedule' && $_SERVER['REQUEST_METHOD']==='POST') {
  ensure_csrf();
  $uid = require_login();
  $tid  = (int)($_POST['turno_id'] ?? 0);
  $date = trim($_POST['date'] ?? '');
  $time = trim($_POST['time'] ?? '');
  $med  = (int)($_POST['medico_id'] ?? 0);
  
  if ($tid<=0) json_out(['ok'=>false,'error'=>'Turno inválido'],400);
  if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) json_out(['ok'=>false,'error'=>'Fecha inválida'],400);
  if (!$time || !preg_match('/^\d{2}:\d{2}$/',$time)) json_out(['ok'=>false,'error'=>'Hora inválida'],400);
  if ($med<=0) json_out(['ok'=>false,'error'=>'Médico inválido'],400);
  
  $st = $pdo->prepare("
    SELECT t.Id_medico
    FROM turno t 
    JOIN paciente p ON p.Id_paciente=t.Id_paciente
    WHERE t.Id_turno=? AND p.Id_usuario=? 
    LIMIT 1
  ");
  $st->execute([$tid,$uid]); 
  if (!$st->fetch()) json_out(['ok'=>false,'error'=>'No autorizado'],403);

  $dt = DateTime::createFromFormat('Y-m-d H:i', "$date $time");
  if (!$dt) json_out(['ok'=>false,'error'=>'Fecha/hora inválidas'],400);

  $fechaHora = $dt->format('Y-m-d H:i:00');
  
  // Verificar horario del médico
  $diaSemana = get_day_name($date);
  $chkHorario = $pdo->prepare("
    SELECT 1 FROM horario_medico 
    WHERE Id_medico=? AND Dia_semana=? 
    AND TIME(?)>=Hora_inicio AND TIME(?)<Hora_fin
    LIMIT 1
  ");
  $chkHorario->execute([$med, $diaSemana, $time, $time]);
  if (!$chkHorario->fetch()) json_out(['ok'=>false,'error'=>'El horario seleccionado no está disponible para este médico'],400);
  
  $chk = $pdo->prepare("
    SELECT 1 FROM turno 
    WHERE Fecha=? AND Id_medico=? AND (Estado IS NULL OR Estado <> 'cancelado') AND Id_turno<>? 
    LIMIT 1
  ");
  $chk->execute([$fechaHora, $med, $tid]);
  if ($chk->fetch()) json_out(['ok'=>false,'error'=>'Ese horario ya está ocupado'],409);

  $pdo->prepare("
    UPDATE turno 
    SET Fecha=?, Id_medico=?, Estado='reservado' 
    WHERE Id_turno=?
  ")->execute([$fechaHora, $med, $tid]);
  
  json_out(['ok'=>true,'mensaje'=>'Turno reprogramado exitosamente','fecha'=>$fechaHora]);
}

json_out(['ok'=>false,'error'=>'Acción no soportada'],400);