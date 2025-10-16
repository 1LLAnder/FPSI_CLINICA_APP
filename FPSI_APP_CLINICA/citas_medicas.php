<?php
/******************************************************
 * citas_medicas.php — Gestión de citas médicas
 * Corrige bloqueo de fechas canceladas o pendientes.
 ******************************************************/
date_default_timezone_set('America/Lima');

/* ==== CONFIGURACIÓN BD ==== */
const DB_HOST = '127.0.0.1';
const DB_NAME = 'clinica';
const DB_USER = 'root';
const DB_PASS = '';

function pdo(): PDO {
  static $pdo = null;
  if ($pdo === null) {
    $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
  }
  return $pdo;
}

function json_response($data, int $status = 200): void {
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data);
  exit;
}

function valid_date(string $d): bool {
  return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && checkdate((int)substr($d,5,2),(int)substr($d,8,2),(int)substr($d,0,4));
}

/* ==== ENDPOINTS AJAX ==== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  try {
    /* ======= Verificar disponibilidad ======= */
    if ($action === 'check') {
      $doctor_id = (int)($_POST['doctor_id'] ?? 0);
      $date = trim($_POST['date'] ?? '');
      if ($doctor_id <= 0 || !valid_date($date)) json_response(['ok'=>false,'error'=>'Datos inválidos.'],400);

      // ✅ Solo se consideran confirmadas como ocupadas
      $stmt = pdo()->prepare('SELECT COUNT(*) n FROM appointments WHERE doctor_id=? AND date=? AND status="confirmada"');
      $stmt->execute([$doctor_id,$date]);
      $busy = (int)$stmt->fetch()['n'] > 0;

      json_response(['ok'=>true,'available'=>!$busy]);
    }

    /* ======= Reservar cita (pendiente) ======= */
    if ($action === 'book') {
      $doctor_id = (int)($_POST['doctor_id'] ?? 0);
      $date = trim($_POST['date'] ?? '');
      $paciente_id = (int)($_POST['paciente_id'] ?? 0);
      if ($doctor_id <= 0 || !valid_date($date) || $paciente_id <= 0)
        json_response(['ok'=>false,'error'=>'Datos inválidos.'],400);

      // Si existe una cancelada para esa fecha, se reusa
      $stmt = pdo()->prepare("SELECT id FROM appointments WHERE doctor_id=? AND date=? AND status='cancelada'");
      $stmt->execute([$doctor_id,$date]);
      $old = $stmt->fetch();

      if ($old) {
          $update = pdo()->prepare("UPDATE appointments 
                                    SET paciente_id=?, status='pendiente' 
                                    WHERE id=?");
          $update->execute([$paciente_id, $old['id']]);
      } else {
          $stmt = pdo()->prepare('INSERT INTO appointments (doctor_id, paciente_id, date, status) VALUES (?,?,?, "pendiente")');
          $stmt->execute([$doctor_id, $paciente_id, $date]);
      }

      json_response(['ok'=>true,'message'=>'Cita agendada correctamente.']);
    }

    /* ======= Días ocupados del mes ======= */
    if ($action === 'month_busy_days') {
      $doctor_id = (int)($_POST['doctor_id'] ?? 0);
      $y = (int)($_POST['y'] ?? 0);
      $m = (int)($_POST['m'] ?? 0);
      if ($doctor_id<=0 || $y<1900 || $m<1 || $m>12) json_response(['ok'=>false],400);
      $start = sprintf('%04d-%02d-01', $y, $m);
      $end = date('Y-m-t', strtotime($start));

      // ✅ Solo se marcan como ocupadas las confirmadas
      $stmt = pdo()->prepare('SELECT date FROM appointments WHERE doctor_id=? AND date BETWEEN ? AND ? AND status="confirmada"');
      $stmt->execute([$doctor_id,$start,$end]);
      $days = array_map(fn($r)=>substr($r['date'],8,2), $stmt->fetchAll());
      json_response(['ok'=>true,'days'=>$days]);
    }

    json_response(['ok'=>false,'error'=>'Acción no soportada.'],400);
  } catch (Throwable $t) {
    json_response(['ok'=>false,'error'=>$t->getMessage()],500);
  }
}

/* ==== Render HTML ==== */
$doctors = [];
try { $doctors = pdo()->query('SELECT id,name FROM doctors ORDER BY name')->fetchAll(); } catch (Throwable $t) {}
$today = new DateTimeImmutable('today');
$y = (int)($_GET['y'] ?? $today->format('Y'));
$m = (int)($_GET['m'] ?? $today->format('n'));
$firstDay = DateTimeImmutable::createFromFormat('Y-n-j', "$y-$m-1");
$monthName = [
  1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',
  7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'
][$m];
$startDow = (int)$firstDay->format('N');
$daysInMonth = (int)$firstDay->format('t');
$paciente_id = (int)($_GET['paciente_id'] ?? 0);
if ($paciente_id <= 0) { header("Location: registro.php"); exit; }
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Citas médicas</title>
<link rel="stylesheet" href="public/citas_medicas.css">
</head>
<body>
<div class="wrap">
  <div class="header">Citas médicas</div>
  <div class="card">
    <div class="toolbar">
      <div class="left">
        <button class="btn" id="prev">‹</button>
        <button class="btn" id="next">›</button>
        <div class="month" id="monthLbl"><?= $monthName.' '.$y ?></div>
        <button class="btn btn-ghost" id="todayBtn">Hoy</button>
      </div>
      <div class="sel-doctor">
        <span class="muted">Doctor:</span>
        <select id="doctor">
          <?php foreach($doctors as $d): ?>
            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="cal" id="cal">
      <div class="cal-head">
        <div>Lun</div><div>Mar</div><div>Mié</div><div>Jue</div><div>Vie</div><div>Sáb</div><div>Dom</div>
      </div>
      <?php
      $cellsBefore = $startDow - 1;
      $totalCells = $cellsBefore + $daysInMonth;
      $rows = ceil($totalCells / 7);
      $currentDay = 1;
      $todayStr = $today->format('Y-m-d');
      for ($r=0; $r<$rows; $r++) {
        echo '<div class="cal-row">';
        for ($c=0; $c<7; $c++) {
          $index = $r*7+$c;
          if ($index < $cellsBefore || $currentDay > $daysInMonth) {
            echo '<div class="cell muted"></div>';
            continue;
          }
          $dateStr = sprintf('%04d-%02d-%02d', $y, $m, $currentDay);
          $isPast = ($dateStr < $todayStr);
          echo '<div class="cell'.($isPast?' past':'').'" data-date="'.$dateStr.'">';
          echo '<div class="daynum">'.$currentDay.'</div>';
          echo '</div>';
          $currentDay++;
        }
        echo '</div>';
      }
      ?>
    </div>

    <div class="footer">
      <button class="cta" id="bookBtn">Agendar cita</button>
    </div>
    <div class="muted">Selecciona un día disponible para el doctor elegido.</div>
  </div>
</div>

<script>
const pacienteId = <?= $paciente_id ?>;
const y0 = <?= $y ?>, m0 = <?= $m ?>;
const cal = document.getElementById('cal');
const selDoctor = document.getElementById('doctor');
const btnBook = document.getElementById('bookBtn');
let selectedCell = null;

// Peticiones AJAX
async function post(action, payload){
  const body = new URLSearchParams({action, ...(payload||{})});
  const res = await fetch('', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body});
  const data = await res.json();
  return {status:res.status, data};
}

// Selección de día
cal.addEventListener('click', async (e)=>{
  const cell = e.target.closest('.cell');
  if(!cell || cell.classList.contains('muted') || cell.classList.contains('past')) return;
  const date = cell.dataset.date;
  if(!selDoctor.value){ alert('Selecciona un doctor.'); return; }

  const {data} = await post('check', {doctor_id: selDoctor.value, date});
  if(!data.ok){ alert(data.error||'Error'); return; }
  if(!data.available){ alert('Ese día ya está ocupado.'); return; }

  if(selectedCell) selectedCell.classList.remove('selected');
  selectedCell = cell;
  cell.classList.add('selected');
});

// Agendar cita
btnBook.onclick = async ()=>{
  if(!selDoctor.value) return alert('Selecciona un doctor.');
  if(!selectedCell) return alert('Selecciona un día.');
  const date = selectedCell.dataset.date;
  const res = await post('book', {doctor_id: selDoctor.value, date, paciente_id: pacienteId});
  if(res.data.ok){
    window.location.href = `confirmar_pago.php?paciente_id=${pacienteId}&doctor_id=${selDoctor.value}&date=${date}`;
  } else {
    alert(res.data.error || 'Error al agendar.');
  }
};
</script>
</body>
</html>
