<?php
/******************************************************
 * index.php — Citas médicas con calendario (UI estilizada)
 * PHP 7.4+ / MySQL
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
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return false;
  [$y,$m,$day] = explode('-', $d);
  return checkdate((int)$m,(int)$day,(int)$y);
}

/* ==== ENDPOINTS AJAX ==== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  try {
    if ($action === 'check') {
      $doctor_id = (int)($_POST['doctor_id'] ?? 0);
      $date = trim($_POST['date'] ?? '');
      if ($doctor_id <= 0 || !valid_date($date)) json_response(['ok'=>false,'error'=>'Datos inválidos.'],400);
      if ($date < date('Y-m-d')) json_response(['ok'=>true,'available'=>false],200);

      $stmt = pdo()->prepare('SELECT COUNT(*) n FROM appointments WHERE doctor_id=? AND date=?');
      $stmt->execute([$doctor_id,$date]);
      $busy = (int)$stmt->fetch()['n'] > 0;
      json_response(['ok'=>true,'available'=>!$busy]);
    }

    if ($action === 'book') {
      $doctor_id = (int)($_POST['doctor_id'] ?? 0);
      $date = trim($_POST['date'] ?? '');
      $paciente_id = (int)($_POST['paciente_id'] ?? 0);
      if ($doctor_id <= 0 || !valid_date($date) || $paciente_id <= 0) json_response(['ok'=>false,'error'=>'Datos inválidos.'],400);
      if ($date < date('Y-m-d')) json_response(['ok'=>false,'error'=>'No se permiten fechas pasadas.'],400);

      $stmt = pdo()->prepare('INSERT INTO appointments (doctor_id, paciente_id, date) VALUES (?,?,?)');
      try {
        $stmt->execute([$doctor_id, $paciente_id, $date]);
        json_response(['ok'=>true,'message'=>'Cita agendada correctamente.']);
      } catch (PDOException $e) {
        if ($e->getCode()==='23000') json_response(['ok'=>false,'error'=>'Ese día ya está ocupado para el doctor.'],409);
        throw $e;
      }
    }

    if ($action === 'month_busy_days') {
      // Devuelve días ocupados del mes para pintar el calendario (opcional)
      $doctor_id = (int)($_POST['doctor_id'] ?? 0);
      $y = (int)($_POST['y'] ?? 0);
      $m = (int)($_POST['m'] ?? 0);
      if ($doctor_id<=0 || $y<1900 || $m<1 || $m>12) json_response(['ok'=>false],400);
      $start = sprintf('%04d-%02d-01', $y, $m);
      $end = date('Y-m-t', strtotime($start));
      $stmt = pdo()->prepare('SELECT date FROM appointments WHERE doctor_id=? AND date BETWEEN ? AND ?');
      $stmt->execute([$doctor_id,$start,$end]);
      $days = array_map(fn($r)=>substr($r['date'],8,2), $stmt->fetchAll());
      json_response(['ok'=>true,'days'=>$days]);
    }

    json_response(['ok'=>false,'error'=>'Acción no soportada.'],400);
  } catch (Throwable $t) {
    json_response(['ok'=>false,'error'=>'Error del servidor.'],500);
  }
}

/* ==== RENDER SERVIDOR ==== */
// Doctores para el selector
try {
  $doctors = pdo()->query('SELECT id,name FROM doctors ORDER BY name')->fetchAll();
} catch (Throwable $t) { $doctors = []; }

// Mes visible (GET)
$today = new DateTimeImmutable('today');
$y = (int)($_GET['y'] ?? $today->format('Y'));
$m = (int)($_GET['m'] ?? $today->format('n'));
if ($m<1 || $m>12) { $m = (int)$today->format('n'); }
$firstDay = DateTimeImmutable::createFromFormat('Y-n-j', "$y-$m-1");
$monthName = [
  1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',
  7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'
][$m];

// Semana empieza en lunes
$startDow = (int)$firstDay->format('N'); // 1..7 (Lun..Dom)
$daysInMonth = (int)$firstDay->format('t');

function ymLink(int $y, int $m): string {
  return '?y='.$y.'&m='.$m;
}
$prev = (clone $firstDay)->modify('-1 month');
$next = (clone $firstDay)->modify('+1 month');

$paciente_id = isset($_GET['paciente_id']) ? (int)$_GET['paciente_id'] : 0;
if ($paciente_id <= 0) {
  // Redirigir si no hay paciente
  header("Location: registro.php");
  exit;
}

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Citas médicas</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="public/citas_medicas.css">
</head>
<body>
  <div class="wrap">
    <div class="header">Citas médicas</div>

    <div class="card">
      <div class="toolbar">
        <div class="left">
          <button class="btn" id="prev" aria-label="Mes anterior">‹</button>
          <button class="btn" id="next" aria-label="Mes siguiente">›</button>
          <div class="month" id="monthLbl"><?= $monthName.' '.$y ?></div>
          <button class="btn btn-ghost" id="todayBtn">Hoy</button>
        </div>
        <div class="sel-doctor">
          <span class="muted">Doctor:</span>
          <select id="doctor">
            <?php if ($doctors): foreach($doctors as $d): ?>
              <option value="<?= (int)$d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
            <?php endforeach; else: ?>
              <option value="">(Sin médicos)</option>
            <?php endif; ?>
          </select>
        </div>
      </div>

      <div class="cal" id="cal">
        <div class="cal-head">
          <div>Lun</div><div>Mar</div><div>Mié</div><div>Jue</div><div>Vie</div><div>Sáb</div><div>Dom</div>
        </div>
        <?php
          $cellsBefore = $startDow-1; // espacios antes del 1
          $totalCells = $cellsBefore + $daysInMonth;
          $rows = (int)ceil($totalCells/7);
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
    // Navegación mes
    const y0 = <?= (int)$y ?>, m0 = <?= (int)$m ?>;
    const pacienteId = <?= (int)$paciente_id ?>;
    const monthLbl = document.getElementById('monthLbl');
    const cal = document.getElementById('cal');
    const btnPrev = document.getElementById('prev');
    const btnNext = document.getElementById('next');
    const btnToday = document.getElementById('todayBtn');
    const btnBook = document.getElementById('bookBtn');
    const selDoctor = document.getElementById('doctor');
    let selectedCell = null;

    function gotoYM(y,m){
      // Mantener paciente_id en la URL
      window.location.search = `?y=${y}&m=${m}&paciente_id=${pacienteId}`;
    }

    btnPrev.onclick = () => {
      let y = y0, m = m0-1; if(m<1){m=12;y--;} gotoYM(y,m);
    };
    btnNext.onclick = () => {
      let y = y0, m = m0+1; if(m>12){m=1;y++;} gotoYM(y,m);
    };
    btnToday.onclick = () => { 
      // Mantener paciente_id al volver a hoy
      window.location.href='?paciente_id='+pacienteId; 
    };

    // Selección de día + ver disponibilidad inmediata
    cal.addEventListener('click', async (e)=>{
      const cell = e.target.closest('.cell');
      if(!cell || cell.classList.contains('muted') || cell.classList.contains('past')) return;

      const date = cell.dataset.date;
      if(!selDoctor.value){ alert('Selecciona un doctor.'); return; }

      // Check disponibilidad
      const {data} = await post('check', {doctor_id: selDoctor.value, date});
      if(!data.ok){ alert(data.error||'Error'); return; }

      if(!data.available){
        alert('Ese día ya está ocupado para el doctor. Por favor, elige otro día.');
        return;
      }

      // marcar selección
      if(selectedCell) selectedCell.classList.remove('selected');
      selectedCell = cell;
      selectedCell.classList.add('selected');
    });

    // Pintar puntos en días ocupados del mes para el doctor seleccionado
    async function paintBusy(){
      const y = <?= (int)$y ?>, m = <?= (int)$m ?>;
      if(!selDoctor.value) return;
      const {data} = await post('month_busy_days', {doctor_id: selDoctor.value, y, m});
      if(!data.ok) return;
      const busy = new Set(data.days);
      document.querySelectorAll('.cell[data-date]').forEach(cell=>{
        const d = (cell.dataset.date||'').slice(8,10);
        if (busy.has(d)) cell.classList.add('busy'); else cell.classList.remove('busy');
      });
    }
    selDoctor.addEventListener('change', paintBusy);
    paintBusy();

    // Agendar
    btnBook.onclick = async ()=>{
      if(!selDoctor.value) return alert('Selecciona un doctor.');
      if(!selectedCell) return alert('Primero elige un día disponible.');
      const date = selectedCell.dataset.date;

      const res = await post('book', {doctor_id: selDoctor.value, date, paciente_id: pacienteId});
      if(res.status===200 && res.data.ok){
        // Redirigir a pago
        window.location.href = `confirmar_pago.php?paciente_id=${pacienteId}&doctor_id=${selDoctor.value}&date=${date}`;
      } else if (res.status===409){
        alert('Ese día ya está ocupado para el doctor. Por favor, elige otro día.');
        if(selectedCell) selectedCell.classList.remove('selected'), selectedCell=null;
        paintBusy();
      } else {
        alert(res.data.error || 'No se pudo agendar. Intenta nuevamente.');
      }
    };

    // Helper POST
    async function post(action, payload){
      const body = new URLSearchParams({action, ...(payload||{})}).toString();
      const res = await fetch('', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'}, body});
      const data = await res.json().catch(()=>({ok:false,error:'Respuesta inválida'}));
      return {status:res.status, data};
    }
  </script>
</body>
</html>