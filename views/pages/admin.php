<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

function dbx(){ return db(); }
function json_out($d,$c=200){ http_response_code($c); header('Content-Type: application/json; charset=utf-8'); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function ensure_csrf(){
  $t = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
  if (!$t || !hash_equals($_SESSION['csrf_token'] ?? '', $t)) json_out(['ok'=>false,'error'=>'CSRF inválido'],400);
}
function must_staff(PDO $pdo){
  if (empty($_SESSION['Id_usuario'])) { header('Location: login.php'); exit; }
  $uid = (int)$_SESSION['Id_usuario'];

  $st = $pdo->prepare("SELECT Id_secretaria FROM secretaria WHERE Id_usuario=? LIMIT 1");
  $st->execute([$uid]); $secData = $st->fetch(PDO::FETCH_ASSOC);
  $isSec = (bool)$secData;

  $st = $pdo->prepare("SELECT Id_medico FROM medico WHERE Id_usuario=? LIMIT 1");
  $st->execute([$uid]); $me = $st->fetch(PDO::FETCH_ASSOC);
  $isMed = (bool)$me;

  if (!$isSec && !$isMed) { http_response_code(403); echo "Acceso restringido"; exit; }
  return [$uid,$isSec,$isMed, $me ? (int)$me['Id_medico'] : null, $secData ? (int)$secData['Id_secretaria'] : null];
}
function weekday_name_es($ymd){
  $w = (int)date('N', strtotime($ymd));
  $map = [1=>'lunes',2=>'martes',3=>'miercoles',4=>'jueves',5=>'viernes',6=>'sabado',7=>'domingo'];
  return $map[$w] ?? '';
}



if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];
$pdo = dbx();

// ======= API =======
if (isset($_GET['fetch']) || isset($_POST['action'])) {
  [$uid,$isSec,$isMed,$myMedId,$mySecId] = must_staff($pdo);

  // Init - Cargar todos los datos iniciales
  if (($_GET['fetch'] ?? '') === 'init') {
    $esps = $pdo->query("SELECT Id_Especialidad, Nombre FROM especialidad WHERE Activo=1 ORDER BY Nombre")->fetchAll(PDO::FETCH_ASSOC);
    
    $meds = $pdo->query("
      SELECT m.Id_medico, u.Apellido, u.Nombre, u.dni, u.email, e.Nombre AS Especialidad, m.Legajo, 
             m.Id_Especialidad
      FROM medico m
      JOIN usuario u ON u.Id_usuario=m.Id_usuario
      LEFT JOIN especialidad e ON e.Id_Especialidad=m.Id_Especialidad
      WHERE m.Activo=1
      ORDER BY u.Apellido,u.Nombre
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener horarios para cada médico
    foreach($meds as &$med) {
      $st = $pdo->prepare("
        SELECT Dia_semana, Hora_inicio, Hora_fin 
        FROM horario_medico 
        WHERE Id_medico=? 
        ORDER BY FIELD(Dia_semana, 'lunes','martes','miercoles','jueves','viernes','sabado','domingo'), Hora_inicio
      ");
      $st->execute([$med['Id_medico']]);
      $med['horarios'] = $st->fetchAll(PDO::FETCH_ASSOC);
      
      // Construir string de días disponibles
      $dias = array_unique(array_map(fn($h)=>$h['Dia_semana'], $med['horarios']));
      $med['Dias_Disponibles'] = implode(',', $dias);
    }
    
    $secs = $pdo->query("
      SELECT s.Id_secretaria, u.Apellido, u.Nombre, u.dni, u.email, u.Id_usuario
      FROM secretaria s
      JOIN usuario u ON u.Id_usuario=s.Id_usuario
      WHERE s.Activo=1
      ORDER BY u.Apellido,u.Nombre
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    $obras = $pdo->query("
      SELECT Id_obra_social, Nombre, Activo 
      FROM obra_social 
      ORDER BY Nombre
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    json_out(['ok'=>true,'especialidades'=>$esps,'medicos'=>$meds,'secretarias'=>$secs,'obras_sociales'=>$obras,'csrf'=>$csrf]);
  }

  // ========== OBRAS SOCIALES ==========
  
  if (($_POST['action'] ?? '') === 'create_obra_social') {
    ensure_csrf();
    try {
      $nombre = trim($_POST['nombre'] ?? '');
      if (!$nombre) throw new Exception('El nombre es obligatorio');
      
      $check = $pdo->prepare("SELECT COUNT(*) FROM obra_social WHERE Nombre=?");
      $check->execute([$nombre]);
      if ($check->fetchColumn() > 0) throw new Exception('Esta obra social ya existe');
      
      $stmt = $pdo->prepare("INSERT INTO obra_social (Nombre, Activo) VALUES (?, 1)");
      $stmt->execute([$nombre]);
      
      json_out(['ok'=>true,'msg'=>'Obra social creada']);
    } catch (Throwable $e) {
      json_out(['ok'=>false,'error'=>$e->getMessage()],500);
    }
  }

  if (($_POST['action'] ?? '') === 'toggle_obra_social') {
    ensure_csrf();
    try {
      $id = (int)($_POST['id_obra_social'] ?? 0);
      if ($id <= 0) throw new Exception('ID inválido');
      
      $stmt = $pdo->prepare("UPDATE obra_social SET Activo = NOT Activo WHERE Id_obra_social=?");
      $stmt->execute([$id]);
      
      json_out(['ok'=>true,'msg'=>'Estado actualizado']);
    } catch (Throwable $e) {
      json_out(['ok'=>false,'error'=>$e->getMessage()],500);
    }
  }

  if (($_POST['action'] ?? '') === 'delete_obra_social') {
    ensure_csrf();
    try {
      $id = (int)($_POST['id_obra_social'] ?? 0);
      if ($id <= 0) throw new Exception('ID inválido');
      
      $check = $pdo->prepare("SELECT COUNT(*) FROM paciente WHERE Id_obra_social=?");
      $check->execute([$id]);
      if ($check->fetchColumn() > 0) {
        throw new Exception('No se puede eliminar: hay pacientes asociados a esta obra social');
      }
      
      $stmt = $pdo->prepare("DELETE FROM obra_social WHERE Id_obra_social=?");
      $stmt->execute([$id]);
      
      json_out(['ok'=>true,'msg'=>'Obra social eliminada']);
    } catch (Throwable $e) {
      json_out(['ok'=>false,'error'=>$e->getMessage()],500);
    }
  }

  // ========== MÉDICOS ==========
  
  if (($_POST['action'] ?? '') === 'create_medico') {
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
      
      // Horarios: recibir array de horarios
      $horariosJson = $_POST['horarios'] ?? '[]';
      $horarios = json_decode($horariosJson, true);
      
      if (!$nombre || !$apellido || !$dni || !$email || !$legajo || !$idEsp) throw new Exception('Faltan campos');
      if (empty($horarios)) throw new Exception('Debe agregar al menos un horario');

      $pdo->beginTransaction();

      $stmt = $pdo->prepare("INSERT INTO usuario (Nombre, Apellido, dni, email, Contraseña, Rol) VALUES (?,?,?,?,?,'medico')");
      $stmt->execute([$nombre, $apellido, $dni, $email, $password]);
      $idUsuario = $pdo->lastInsertId();

      $stmt = $pdo->prepare("INSERT INTO medico (Legajo, Id_usuario, Id_Especialidad, Activo) VALUES (?,?,?,1)");
      $stmt->execute([$legajo, $idUsuario, $idEsp]);
      $idMedico = $pdo->lastInsertId();

      // Insertar horarios
      $stmtHorario = $pdo->prepare("
        INSERT INTO horario_medico (Id_medico, Dia_semana, Hora_inicio, Hora_fin) 
        VALUES (?, ?, ?, ?)
      ");
      foreach($horarios as $h) {
        $stmtHorario->execute([$idMedico, $h['dia'], $h['inicio'], $h['fin']]);
      }

      $pdo->commit();
      json_out(['ok'=>true,'msg'=>'Médico creado']);
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      json_out(['ok'=>false,'error'=>$e->getMessage()],500);
    }
  }

  if (($_POST['action'] ?? '') === 'update_medico') {
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

      $stmt = $pdo->prepare("UPDATE usuario SET Nombre=?, Apellido=?, email=? WHERE Id_usuario=?");
      $stmt->execute([$nombre, $apellido, $email, $idUsuario]);

      $stmt = $pdo->prepare("UPDATE medico SET Legajo=?, Id_Especialidad=? WHERE Id_medico=?");
      $stmt->execute([$legajo, $idEsp, $idMed]);

      // Eliminar horarios antiguos e insertar nuevos
      $pdo->prepare("DELETE FROM horario_medico WHERE Id_medico=?")->execute([$idMed]);
      
      if (!empty($horarios)) {
        $stmtHorario = $pdo->prepare("
          INSERT INTO horario_medico (Id_medico, Dia_semana, Hora_inicio, Hora_fin) 
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

  if (($_POST['action'] ?? '') === 'delete_medico') {
    ensure_csrf();
    try {
      $idMed = (int)($_POST['id_medico'] ?? 0);
      if ($idMed <= 0) throw new Exception('ID inválido');

      $stmt = $pdo->prepare("UPDATE medico SET Activo=0 WHERE Id_medico=?");
      $stmt->execute([$idMed]);

      json_out(['ok'=>true,'msg'=>'Médico eliminado']);
    } catch (Throwable $e) {
      json_out(['ok'=>false,'error'=>$e->getMessage()],500);
    }
  }

  // ========== SECRETARIAS ==========
  
  if (($_POST['action'] ?? '') === 'create_secretaria') {
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

      $stmt = $pdo->prepare("INSERT INTO usuario (Nombre, Apellido, dni, email, Contraseña, Rol) VALUES (?,?,?,?,?,'secretaria')");
      $stmt->execute([$nombre, $apellido, $dni, $email, $password]);
      $idUsuario = $pdo->lastInsertId();

      $stmt = $pdo->prepare("INSERT INTO secretaria (Id_usuario, Activo) VALUES (?,1)");
      $stmt->execute([$idUsuario]);

      json_out(['ok'=>true,'msg'=>'Secretaria creada']);
    } catch (Throwable $e) {
      json_out(['ok'=>false,'error'=>$e->getMessage()],500);
    }
  }

  if (($_POST['action'] ?? '') === 'update_secretaria') {
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

      $stmt = $pdo->prepare("UPDATE usuario SET Nombre=?, Apellido=?, email=? WHERE Id_usuario=?");
      $stmt->execute([$nombre, $apellido, $email, $idUsuario]);

      json_out(['ok'=>true,'msg'=>'Secretaria actualizada']);
    } catch (Throwable $e) {
      json_out(['ok'=>false,'error'=>$e->getMessage()],500);
    }
  }

  if (($_POST['action'] ?? '') === 'delete_secretaria') {
    ensure_csrf();
    try {
      $idSec = (int)($_POST['id_secretaria'] ?? 0);
      if ($idSec <= 0) throw new Exception('ID inválido');

      $stmt = $pdo->prepare("UPDATE secretaria SET Activo=0 WHERE Id_secretaria=?");
      $stmt->execute([$idSec]);

      json_out(['ok'=>true,'msg'=>'Secretaria eliminada']);
    } catch (Throwable $e) {
      json_out(['ok'=>false,'error'=>$e->getMessage()],500);
    }
  }

  // ========== TURNOS ==========
  
  if (($_GET['fetch'] ?? '') === 'doctors') {
    $espId = (int)($_GET['especialidad_id'] ?? 0);
    if ($espId <= 0) json_out(['ok'=>false,'error'=>'Especialidad inválida'],400);
    
    $stmt = $pdo->prepare("
      SELECT m.Id_medico, u.Apellido, u.Nombre
      FROM medico m
      JOIN usuario u ON u.Id_usuario=m.Id_usuario
      WHERE m.Id_Especialidad=? AND m.Activo=1
      ORDER BY u.Apellido, u.Nombre
    ");
    $stmt->execute([$espId]);
    json_out(['ok'=>true,'items'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
  }

  if (($_GET['fetch'] ?? '') === 'agenda') {
    $medId = (int)($_GET['medico_id'] ?? 0);
    if ($medId <= 0) json_out(['ok'=>false,'error'=>'Médico inválido'],400);

    $from = $_GET['from'] ?? '';
    $to = $_GET['to'] ?? '';

    $where = "t.Id_medico=?";
    $params = [$medId];

    if ($from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
      $where .= " AND DATE(t.Fecha)>=?";
      $params[] = $from;
    }
    if ($to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
      $where .= " AND DATE(t.Fecha)<=?";
      $params[] = $to;
    }

    $stmt = $pdo->prepare("
      SELECT t.Id_turno, t.Fecha, t.Estado, t.Id_medico,
             up.Apellido AS PApellido, up.Nombre AS PNombre
      FROM turno t
      LEFT JOIN paciente p ON p.Id_paciente=t.Id_paciente
      LEFT JOIN usuario up ON up.Id_usuario=p.Id_usuario
      WHERE $where
      ORDER BY t.Fecha DESC
    ");
    $stmt->execute($params);

    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
      $items[] = [
        'Id_turno' => (int)$r['Id_turno'],
        'Id_medico' => (int)($r['Id_medico'] ?? 0),
        'fecha' => $r['Fecha'],
        'fecha_fmt' => date('d/m/Y H:i', strtotime($r['Fecha'])),
        'estado' => strtolower($r['Estado'] ?? 'cancelado'),
        'paciente' => trim(($r['PApellido'] ?? '') . ', ' . ($r['PNombre'] ?? ''))
      ];
    }

    json_out(['ok'=>true,'items'=>$items]);
  }

  if (($_GET['fetch'] ?? '') === 'slots') {
    $medId = (int)($_GET['medico_id'] ?? 0);
    $date = $_GET['date'] ?? '';

    if ($medId <= 0) json_out(['ok'=>false,'error'=>'Médico inválido'],400);
    if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) json_out(['ok'=>false,'error'=>'Fecha inválida'],400);

    $diaSemana = weekday_name_es($date);

    $stmt = $pdo->prepare("
      SELECT Hora_inicio, Hora_fin 
      FROM horario_medico 
      WHERE Id_medico=? AND Dia_semana=?
      ORDER BY Hora_inicio
    ");
    $stmt->execute([$medId, $diaSemana]);
    $horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($horarios)) {
      json_out(['ok'=>true,'slots'=>[]]);
    }

    $stmt = $pdo->prepare("
      SELECT TIME(Fecha) AS hhmm 
      FROM turno 
      WHERE DATE(Fecha)=? AND Id_medico=? AND (Estado IS NULL OR Estado <> 'cancelado')
    ");
    $stmt->execute([$date, $medId]);
    $busy = array_map(fn($r) => substr($r['hhmm'], 0, 5), $stmt->fetchAll(PDO::FETCH_ASSOC));

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

    json_out(['ok'=>true,'slots'=>$slots]);
  }

  if (($_GET['fetch'] ?? '') === 'search_pacientes') {
    $q = trim($_GET['q'] ?? '');
    if (strlen($q) < 2) json_out(['ok'=>true,'items'=>[]]);

    $stmt = $pdo->prepare("
      SELECT p.Id_paciente, u.Nombre, u.Apellido, u.dni, u.email, os.Nombre AS Obra_social
      FROM paciente p
      JOIN usuario u ON u.Id_usuario=p.Id_usuario
      LEFT JOIN obra_social os ON os.Id_obra_social=p.Id_obra_social
      WHERE p.Activo=1 AND (
        u.dni LIKE ? OR
        u.Nombre LIKE ? OR
        u.Apellido LIKE ? OR
        u.email LIKE ?
      )
      LIMIT 10
    ");
    $like = "%$q%";
    $stmt->execute([$like, $like, $like, $like]);

    json_out(['ok'=>true,'items'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
  }

  if (($_POST['action'] ?? '') === 'create_turno') {
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

      $check = $pdo->prepare("
        SELECT 1 FROM turno 
        WHERE Fecha=? AND Id_medico=? AND (Estado IS NULL OR Estado <> 'cancelado')
        LIMIT 1
      ");
      $check->execute([$fechaHora, $medId]);
      if ($check->fetch()) throw new Exception('Ese horario ya está ocupado');

      $stmt = $pdo->prepare("
        INSERT INTO turno (Fecha, Estado, Id_paciente, Id_medico, Id_secretaria) 
        VALUES (?, 'reservado', ?, ?, ?)
      ");
      $stmt->execute([$fechaHora, $pacId, $medId, $mySecId]);

      json_out(['ok'=>true,'msg'=>'Turno creado exitosamente']);
    } catch (Throwable $e) {
      json_out(['ok'=>false,'error'=>$e->getMessage()],500);
    }
  }

  if (($_POST['action'] ?? '') === 'cancel_turno') {
    ensure_csrf();
    try {
      $turnoId = (int)($_POST['turno_id'] ?? 0);
      if ($turnoId <= 0) throw new Exception('Turno inválido');

      $stmt = $pdo->prepare("UPDATE turno SET Estado='cancelado' WHERE Id_turno=?");
      $stmt->execute([$turnoId]);

      json_out(['ok'=>true,'msg'=>'Turno cancelado']);
    } catch (Throwable $e) {
      json_out(['ok'=>false,'error'=>$e->getMessage()],500);
    }
  }

  if (($_POST['action'] ?? '') === 'delete_turno') {
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

  if (($_POST['action'] ?? '') === 'reschedule_turno') {
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
        WHERE Fecha=? AND Id_medico=? AND (Estado IS NULL OR Estado <> 'cancelado') AND Id_turno<>?
        LIMIT 1
      ");
      $check->execute([$fechaHora, $medId, $turnoId]);
      if ($check->fetch()) throw new Exception('Ese horario ya está ocupado');

      $stmt = $pdo->prepare("
        UPDATE turno 
        SET Fecha=?, Id_medico=?, Estado='reservado' 
        WHERE Id_turno=?
      ");
      $stmt->execute([$fechaHora, $medId, $turnoId]);

      json_out(['ok'=>true,'msg'=>'Turno reprogramado exitosamente']);
    } catch (Throwable $e) {
      json_out(['ok'=>false,'error'=>$e->getMessage()],500);
    }
  }

  json_out(['ok'=>false,'error'=>'Acción no soportada'],400);
}

// ======= HTML =======
[$uid,$isSec,$isMed,$myMedId,$mySecId] = must_staff($pdo);
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
  <link rel="stylesheet" href="admin.css">
  <style>
    .horario-item {
      background: #0f172a;
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 12px;
      margin-bottom: 8px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .horario-info {
      flex: 1;
      color: var(--text);
      font-size: 14px;
    }
    .horario-info strong {
      color: var(--primary);
      text-transform: capitalize;
    }
    .btn-remove-horario {
      padding: 6px 10px;
      background: var(--err);
      color: white;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-size: 13px;
      transition: all 0.2s;
    }
    .btn-remove-horario:hover {
      transform: scale(1.05);
      box-shadow: 0 2px 8px rgba(239,68,68,0.4);
    }
    .horarios-list {
      max-height: 300px;
      overflow-y: auto;
      margin-bottom: 12px;
    }
    .horarios-list::-webkit-scrollbar {
      width: 6px;
    }
    .horarios-list::-webkit-scrollbar-track {
      background: #0b1220;
      border-radius: 3px;
    }
    .horarios-list::-webkit-scrollbar-thumb {
      background: var(--border);
      border-radius: 3px;
    }
    .horarios-list::-webkit-scrollbar-thumb:hover {
      background: var(--muted);
    }
    
    /* Mejorar el input de fecha en modal crear turno */
    #turnoDate {
      cursor: pointer;
    }
    
    /* Animación para los mensajes */
    .msg {
      animation: fadeInMsg 0.3s ease-in;
    }
    
    @keyframes fadeInMsg {
      from {
        opacity: 0;
        transform: translateY(-5px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    /* Mejorar botones en row-actions */
    .row-actions .btn {
      white-space: nowrap;
    }
    
    /* Hacer que la tabla sea más legible */
    .mini td:nth-child(5) {
      min-width: 200px;
    }
  </style>
</head>
<body>
<header class="hdr">
  <div class="brand">🏥 Panel Administrativo</div>
  <div class="who">👤 <?= htmlspecialchars($apellido.', '.$nombre) ?> — <?= $rolTexto ?></div>
  <nav class="actions">
    <a class="btn ghost" href="admin.php">🏠 Inicio</a>
    <form class="inline" action="logout.php" method="post" style="display:inline;margin:0">
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
    <form id="createMedicoForm" class="grid grid-4">
      <div class="field"><label>Nombre *</label><input type="text" name="nombre" required></div>
      <div class="field"><label>Apellido *</label><input type="text" name="apellido" required></div>
      <div class="field"><label>DNI *</label><input type="text" name="dni" required></div>
      <div class="field"><label>Email *</label><input type="email" name="email" required></div>
      <div class="field"><label>Contraseña *</label><input type="password" name="password" required></div>
      <div class="field"><label>Legajo *</label><input type="text" name="legajo" required></div>
      <div class="field"><label>Especialidad *</label><select name="especialidad" id="espCreateSelect" required></select></div>
    </form>

    <h3 style="margin-top:20px">⏰ Horarios de Atención</h3>
    <div class="card card-sub" style="background:#0f172a;padding:16px">
      <div class="grid grid-4">
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
        <div class="actions-row">
          <button type="button" id="btnAgregarHorario" class="btn ghost">➕ Agregar</button>
        </div>
      </div>
      
      <div id="horariosListCreate" class="horarios-list"></div>
      
      <div class="actions-row" style="margin-top:16px">
        <button type="button" id="btnCrearMedico" class="btn primary">✅ Crear Médico</button>
        <span id="msgCreateMed" class="msg"></span>
      </div>
    </div>

    <h3 style="margin-top:24px">📋 Lista de Médicos</h3>
    <div class="table-wrap">
      <table class="mini">
        <thead><tr><th>Nombre</th><th>DNI</th><th>Especialidad</th><th>Legajo</th><th>Horarios</th><th>Acciones</th></tr></thead>
        <tbody id="tblMedicos"></tbody>
      </table>
    </div>
  </section>

  <!-- ===== SECRETARIAS ===== -->
  <section id="tab-secretarias" class="card hidden">
    <h2>Gestión de Secretarias</h2>
    
    <h3>➕ Crear Secretaria</h3>
    <form id="createSecretariaForm" class="grid grid-3">
      <div class="field"><label>Nombre *</label><input type="text" name="nombre" required></div>
      <div class="field"><label>Apellido *</label><input type="text" name="apellido" required></div>
      <div class="field"><label>DNI *</label><input type="text" name="dni" required></div>
      <div class="field"><label>Email *</label><input type="email" name="email" required></div>
      <div class="field"><label>Contraseña *</label><input type="password" name="password" required></div>
      <div class="actions-row">
        <button class="btn" type="submit">✅ Crear</button>
        <span id="msgCreateSec" class="msg"></span>
      </div>
    </form>

    <h3 style="margin-top:24px">📋 Lista de Secretarias</h3>
    <div class="table-wrap">
      <table class="mini">
        <thead><tr><th>Nombre</th><th>DNI</th><th>Email</th><th>Acciones</th></tr></thead>
        <tbody id="tblSecretarias"></tbody>
      </table>
    </div>
  </section>

  <!-- ===== OBRAS SOCIALES ===== -->
  <section id="tab-obras" class="card hidden">
    <h2>Gestión de Obras Sociales</h2>
    
    <h3>➕ Crear Obra Social</h3>
    <form id="createObraForm" class="grid grid-2">
      <div class="field">
        <label>Nombre *</label>
        <input type="text" name="nombre" required placeholder="Ej: OSPROTURA">
      </div>
      <div class="actions-row">
        <button class="btn" type="submit">✅ Crear</button>
        <span id="msgCreateObra" class="msg"></span>
      </div>
    </form>

    <h3 style="margin-top:24px">📋 Lista de Obras Sociales</h3>
    <div class="table-wrap">
      <table class="mini">
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
    
    <div class="grid grid-3" style="margin-bottom:16px">
      <div class="field"><label for="fEsp">Especialidad</label><select id="fEsp"><option value="">Cargando…</option></select></div>
      <div class="field"><label for="fMed">Médico</label><select id="fMed" disabled><option value="">Elegí especialidad…</option></select></div>
      <div class="actions-row">
        <button id="btnNewTurno" class="btn" disabled>➕ Crear Turno</button>
      </div>
    </div>

    <div class="grid grid-2" style="margin-bottom:12px">
      <div class="field"><label for="fFrom">Desde</label><input id="fFrom" type="date"></div>
      <div class="field"><label for="fTo">Hasta</label><input id="fTo" type="date"></div>
    </div>

    <div class="actions-row" style="margin-bottom:16px">
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
      <div id="noData" class="msg" style="padding:10px;display:none">Seleccioná un médico para ver sus turnos</div>
    </div>

    <!-- Reprogramar turno -->
    <div id="reprogSection" class="card" style="margin-top:16px;display:none;background:#0f172a">
      <h3>🔄 Reprogramar Turno</h3>
      <div class="grid grid-3">
        <div class="field"><label for="newDate">Nueva fecha</label><input id="newDate" type="date"></div>
        <div class="field"><label for="newTime">Nuevo horario</label><select id="newTime"><option value="">Elegí fecha…</option></select></div>
        <div class="actions-row">
          <button id="btnReprog" class="btn primary" disabled>✅ Confirmar</button>
          <button id="btnCancelReprog" class="btn ghost">❌ Cancelar</button>
        </div>
      </div>
    </div>
  </section>
</main>

<!-- Modal Crear Turno -->
<div id="modalCreateTurno" class="modal" style="display:none">
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
      <div class="grid grid-2">
        <div class="field"><label>Fecha</label><input type="date" id="turnoDate" required></div>
        <div class="field"><label>Horario</label><select id="turnoTime" required><option value="">Elegí fecha primero...</option></select></div>
      </div>
      <div class="actions-row" style="margin-top:12px">
        <button type="submit" class="btn primary">✅ Crear Turno</button>
        <button type="button" id="btnCloseModal" class="btn ghost">❌ Cancelar</button>
        <span id="msgModal" class="msg"></span>
      </div>
    </form>
  </div>
</div>

<!-- Modal Editar Médico -->
<div id="modalEditMedico" class="modal" style="display:none">
  <div class="modal-content">
    <h2>✏️ Editar Médico</h2>
    <form id="formEditMedico">
      <input type="hidden" id="editMedId">
      <div class="grid grid-3">
        <div class="field"><label>Nombre</label><input type="text" id="editMedNombre" required></div>
        <div class="field"><label>Apellido</label><input type="text" id="editMedApellido" required></div>
        <div class="field"><label>Email</label><input type="email" id="editMedEmail" required></div>
        <div class="field"><label>Legajo</label><input type="text" id="editMedLegajo" required></div>
        <div class="field"><label>Especialidad</label><select id="editMedEsp" required></select></div>
      </div>

      <h3 style="margin-top:20px">⏰ Horarios de Atención</h3>
      <div class="card card-sub" style="background:#0f172a;padding:16px">
        <div class="grid grid-4">
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
          <div class="actions-row">
            <button type="button" id="btnAgregarHorarioEdit" class="btn ghost">➕ Agregar</button>
          </div>
        </div>
        
        <div id="horariosListEdit" class="horarios-list"></div>
      </div>

      <div class="actions-row" style="margin-top:12px">
        <button type="submit" class="btn primary">💾 Guardar</button>
        <button type="button" id="btnCloseMedicoModal" class="btn ghost">❌ Cancelar</button>
        <span id="msgMedicoModal" class="msg"></span>
      </div>
    </form>
  </div>
</div>

<!-- Modal Editar Secretaria -->
<div id="modalEditSecretaria" class="modal" style="display:none">
  <div class="modal-content">
    <h2>✏️ Editar Secretaria</h2>
    <form id="formEditSecretaria">
      <input type="hidden" id="editSecId">
      <div class="grid grid-3">
        <div class="field"><label>Nombre</label><input type="text" id="editSecNombre" required></div>
        <div class="field"><label>Apellido</label><input type="text" id="editSecApellido" required></div>
        <div class="field"><label>Email</label><input type="email" id="editSecEmail" required></div>
      </div>
      <div class="actions-row" style="margin-top:12px">
        <button type="submit" class="btn primary">💾 Guardar</button>
        <button type="button" id="btnCloseSecretariaModal" class="btn ghost">❌ Cancelar</button>
        <span id="msgSecretariaModal" class="msg"></span>
      </div>
    </form>
  </div>
</div>

<script src="admin.js"></script>
<script>
// Sistema de gestión de horarios múltiples
(function(){
  const horariosCreate = [];
  const horariosEdit = [];
  
  // Función para convertir hora 24h a 12h con AM/PM
  function formatHour12(time24) {
    if (!time24) return '';
    const [hours, minutes] = time24.split(':');
    let h = parseInt(hours);
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    return `${h}:${minutes} ${ampm}`;
  }
  
  // Función para verificar si un horario ya existe
  function horarioExists(list, dia, inicio, fin) {
    return list.some(h => 
      h.dia === dia && 
      h.inicio === inicio && 
      h.fin === fin
    );
  }
  
  // Función para verificar si hay solapamiento de horarios
  function horarioOverlaps(list, dia, inicioNuevo, finNuevo) {
    return list.some(h => {
      if (h.dia !== dia) return false;
      
      const inicioExistente = h.inicio;
      const finExistente = h.fin;
      
      // Verificar solapamiento
      return (inicioNuevo < finExistente && finNuevo > inicioExistente);
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
    
    // Ordenar horarios por día y hora
    const diasOrden = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
    const sortedList = [...list].sort((a, b) => {
      const diaCompare = diasOrden.indexOf(a.dia) - diasOrden.indexOf(b.dia);
      if (diaCompare !== 0) return diaCompare;
      return a.inicio.localeCompare(b.inicio);
    });
    
    sortedList.forEach((h, idx) => {
      const realIdx = list.indexOf(h);
      const div = document.createElement('div');
      div.className = 'horario-item';
      div.style.animation = 'fadeIn 0.3s ease-out';
      
      const horaInicio = formatHour12(h.inicio.substring(0,5));
      const horaFin = formatHour12(h.fin.substring(0,5));
      
      div.innerHTML = `
        <div class="horario-info">
          <strong style="text-transform:capitalize;color:var(--primary);font-size:14px">${h.dia}</strong>
          <br>
          <span style="color:var(--text);font-size:13px">🕒 ${horaInicio} - ${horaFin}</span>
        </div>
        <button type="button" class="btn-remove-horario" data-idx="${realIdx}" title="Eliminar horario">🗑️ Eliminar</button>
      `;
      container.appendChild(div);
    });
    
    // Eventos de eliminar
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
  
  // Agregar horario - Crear
  document.getElementById('btnAgregarHorario')?.addEventListener('click', () => {
    const dia = document.getElementById('diaHorario').value;
    const inicio = document.getElementById('horaInicio').value;
    const fin = document.getElementById('horaFin').value;
    const msgEl = document.getElementById('msgCreateMed');
    
    if (!inicio || !fin) {
      if (msgEl) {
        msgEl.textContent = '⚠️ Completá las horas de inicio y fin';
        msgEl.className = 'msg err';
      }
      alert('⚠️ Completá las horas de inicio y fin');
      return;
    }
    
    if (inicio >= fin) {
      if (msgEl) {
        msgEl.textContent = '⚠️ La hora de inicio debe ser menor que la de fin';
        msgEl.className = 'msg err';
      }
      alert('⚠️ La hora de inicio debe ser menor que la de fin');
      return;
    }
    
    const inicioFull = inicio + ':00';
    const finFull = fin + ':00';
    
    // Verificar si ya existe el mismo horario
    if (horarioExists(horariosCreate, dia, inicioFull, finFull)) {
      if (msgEl) {
        msgEl.textContent = '⚠️ Este horario ya fue agregado';
        msgEl.className = 'msg err';
      }
      alert('⚠️ Este horario ya fue agregado para este día');
      return;
    }
    
    // Verificar solapamiento
    if (horarioOverlaps(horariosCreate, dia, inicioFull, finFull)) {
      if (msgEl) {
        msgEl.textContent = '⚠️ Este horario se solapa con uno existente';
        msgEl.className = 'msg err';
      }
      alert('⚠️ Este horario se solapa con otro horario existente para este día');
      return;
    }
    
    horariosCreate.push({dia, inicio: inicioFull, fin: finFull});
    renderHorarios(horariosCreate, 'horariosListCreate');
    
    const horaInicio = formatHour12(inicio);
    const horaFin = formatHour12(fin);
    
    if (msgEl) {
      msgEl.textContent = `✅ Horario agregado: ${dia.charAt(0).toUpperCase() + dia.slice(1)} ${horaInicio} - ${horaFin}`;
      msgEl.className = 'msg ok';
    }
    
    // Limpiar campos
    document.getElementById('horaInicio').value = '08:00';
    document.getElementById('horaFin').value = '12:00';
  });
  
  // Agregar horario - Editar
  document.getElementById('btnAgregarHorarioEdit')?.addEventListener('click', () => {
    const dia = document.getElementById('editDiaHorario').value;
    const inicio = document.getElementById('editHoraInicio').value;
    const fin = document.getElementById('editHoraFin').value;
    const msgEl = document.getElementById('msgMedicoModal');
    
    if (!inicio || !fin) {
      if (msgEl) {
        msgEl.textContent = '⚠️ Completá las horas de inicio y fin';
        msgEl.className = 'msg err';
      }
      alert('⚠️ Completá las horas de inicio y fin');
      return;
    }
    
    if (inicio >= fin) {
      if (msgEl) {
        msgEl.textContent = '⚠️ La hora de inicio debe ser menor que la de fin';
        msgEl.className = 'msg err';
      }
      alert('⚠️ La hora de inicio debe ser menor que la de fin');
      return;
    }
    
    const inicioFull = inicio + ':00';
    const finFull = fin + ':00';
    
    // Verificar si ya existe el mismo horario
    if (horarioExists(horariosEdit, dia, inicioFull, finFull)) {
      if (msgEl) {
        msgEl.textContent = '⚠️ Este horario ya fue agregado';
        msgEl.className = 'msg err';
      }
      alert('⚠️ Este horario ya fue agregado para este día');
      return;
    }
    
    // Verificar solapamiento
    if (horarioOverlaps(horariosEdit, dia, inicioFull, finFull)) {
      if (msgEl) {
        msgEl.textContent = '⚠️ Este horario se solapa con uno existente';
        msgEl.className = 'msg err';
      }
      alert('⚠️ Este horario se solapa con otro horario existente para este día');
      return;
    }
    
    horariosEdit.push({dia, inicio: inicioFull, fin: finFull});
    renderHorarios(horariosEdit, 'horariosListEdit');
    
    const horaInicio = formatHour12(inicio);
    const horaFin = formatHour12(fin);
    
    if (msgEl) {
      msgEl.textContent = `✅ Horario agregado: ${dia.charAt(0).toUpperCase() + dia.slice(1)} ${horaInicio} - ${horaFin}`;
      msgEl.className = 'msg ok';
    }
    
    // Limpiar campos
    document.getElementById('editHoraInicio').value = '08:00';
    document.getElementById('editHoraFin').value = '12:00';
  });
  
  // Crear médico con horarios
  document.getElementById('btnCrearMedico')?.addEventListener('click', async () => {
    const form = document.getElementById('createMedicoForm');
    const msgEl = document.getElementById('msgCreateMed');
    
    if (!form || !msgEl) return;
    
    if (!form.checkValidity()) {
      form.reportValidity();
      return;
    }
    
    if (horariosCreate.length === 0) {
      msgEl.textContent = '⚠️ Debe agregar al menos un horario de atención';
      msgEl.className = 'msg err';
      alert('⚠️ Debe agregar al menos un horario de atención para el médico');
      return;
    }
    
    msgEl.textContent = '⏳ Creando médico...';
    msgEl.className = 'msg';
    
    const fd = new FormData(form);
    fd.set('action', 'create_medico');
    fd.set('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
    fd.set('horarios', JSON.stringify(horariosCreate));
    
    try {
      const res = await fetch('admin.php', {method:'POST', body:fd, headers:{'Accept':'application/json'}});
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Error creando médico');
      
      msgEl.textContent = '✅ ' + (data.msg || 'Médico creado exitosamente');
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
  
  // Al abrir modal de edición, cargar horarios
  window.loadMedicoHorarios = function(horarios) {
    horariosEdit.length = 0;
    if (horarios && Array.isArray(horarios)) {
      horarios.forEach(h => {
        horariosEdit.push({
          dia: h.Dia_semana,
          inicio: h.Hora_inicio,
          fin: h.Hora_fin
        });
      });
    }
    renderHorarios(horariosEdit, 'horariosListEdit');
  };
  
  // Submit editar médico
  document.getElementById('formEditMedico')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const msgEl = document.getElementById('msgMedicoModal');
    
    if (!msgEl) return;
    
    if (horariosEdit.length === 0) {
      msgEl.textContent = '⚠️ Debe tener al menos un horario de atención';
      msgEl.className = 'msg err';
      alert('⚠️ Debe tener al menos un horario de atención');
      return;
    }
    
    msgEl.textContent = '⏳ Actualizando médico...';
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
      const res = await fetch('admin.php', {method:'POST', body:fd, headers:{'Accept':'application/json'}});
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Error actualizando');
      
      msgEl.textContent = '✅ ' + (data.msg || 'Médico actualizado exitosamente');
      msgEl.className = 'msg ok';
      
      setTimeout(() => window.location.reload(), 1500);
    } catch(e) {
      msgEl.textContent = '❌ ' + e.message;
      msgEl.className = 'msg err';
    }
  });
  
  // Inicializar
  renderHorarios(horariosCreate, 'horariosListCreate');
  renderHorarios(horariosEdit, 'horariosListEdit');
})();
</script>
</body>
</html>