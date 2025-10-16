<?php
require_once 'config/db.php';

$paciente_id = (int)($_GET['paciente_id'] ?? 0);
$doctor_id = (int)($_GET['doctor_id'] ?? 0);
$date = $_GET['date'] ?? '';

if ($paciente_id <= 0 || $doctor_id <= 0 || !$date) {
  echo "<p>Datos incompletos. <a href='registro.php'>Volver</a></p>";
  exit;
}

// Obtener datos del paciente y doctor
$stmt = $pdo->prepare("SELECT nombre_completo, contacto FROM pacientes WHERE id=?");
$stmt->execute([$paciente_id]);
$paciente = $stmt->fetch();

$stmt = $pdo->prepare("SELECT name FROM doctors WHERE id=?");
$stmt->execute([$doctor_id]);
$doctor = $stmt->fetch();

$servicio = "Consulta médica con " . ($doctor['name'] ?? "Doctor");
$monto = 80.00;
$metodo_pago = "Tarjeta de crédito";
$error = "";

// ======= Validación del pago =======
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $numero_tarjeta = preg_replace('/\s+/', '', $_POST['numero_tarjeta'] ?? '');
  $fecha = trim($_POST['fecha'] ?? '');
  $cvv = trim($_POST['cvv'] ?? '');
  $titular = trim($_POST['titular'] ?? '');
  $desea_comprobante = isset($_POST['comprobante']) ? 1 : 0;

  // 🔹 Validar número de tarjeta (16 dígitos)
  if (!preg_match('/^\d{16}$/', $numero_tarjeta)) {
    $error = "❌ Número de tarjeta inválido. Debe contener exactamente 16 dígitos.";
  }
  // 🔹 Validar formato de fecha (MM/AA) y que no esté vencida
  elseif (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $fecha)) {
    $error = "❌ Fecha inválida. Usa el formato MM/AA.";
  } else {
    [$mes, $anio] = explode('/', $fecha);
    $anio = (int)("20" . $anio);
    $mes = (int)$mes;
    $hoy = new DateTime();
    $expira = DateTime::createFromFormat('Y-m', "$anio-$mes");
    $expira->modify('last day of this month');
    if ($expira < $hoy) {
      $error = "❌ La tarjeta está vencida.";
    }
  }
  // 🔹 Validar CVV (3 o 4 dígitos)
  if (!$error && !preg_match('/^\d{3,4}$/', $cvv)) {
    $error = "❌ CVV inválido. Debe contener 3 o 4 dígitos.";
  }
  // 🔹 Validar titular
  if (!$error && strlen($titular) < 3) {
    $error = "❌ Nombre del titular demasiado corto.";
  }

  // Si todo está correcto
  if (!$error) {
    $tarjeta = substr($numero_tarjeta, -4);
    $stmt = $pdo->prepare("INSERT INTO pagos (paciente_id, servicio, monto, metodo_pago, tarjeta_ultimos4, desea_comprobante) 
                           VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$paciente_id, $servicio, $monto, $metodo_pago, $tarjeta, $desea_comprobante]);

    // Confirmar cita pagada
    $pdo->prepare("UPDATE appointments SET status='confirmada' 
                   WHERE doctor_id=? AND paciente_id=? AND date=?")
        ->execute([$doctor_id, $paciente_id, $date]);

    header("Location: pago_exitoso.php");
    exit;
  }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Confirmar Pago</title>
  <link rel="stylesheet" href="public/confirmar_pago.css">
  <style>
    .error {color:#d00;background:#fee;padding:10px;border-radius:6px;margin-bottom:10px;}
  </style>
</head>
<body>
  <form method="POST" class="card">
    <h2>Confirmar Pago</h2>
    <p><strong>Paciente:</strong> <?= htmlspecialchars($paciente['nombre_completo'] ?? '') ?></p>
    <p><strong>Fecha de cita:</strong> <?= htmlspecialchars($date) ?></p>
    <p><strong>Servicio:</strong> <?= htmlspecialchars($servicio) ?></p>
    <p><strong>Monto total:</strong> S/ <?= number_format($monto, 2) ?></p>
    <p><strong>Método de pago:</strong> <?= $metodo_pago ?></p>

    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="form-group">
      <label>Número de tarjeta</label>
      <input type="text" name="numero_tarjeta" maxlength="19" placeholder="XXXX XXXX XXXX XXXX" required>
    </div>

    <div class="form-group flex">
      <div style="flex:1;">
        <label>Fecha (MM/AA)</label>
        <input type="text" name="fecha" placeholder="MM/AA" maxlength="5" required>
      </div>
      <div style="flex:1;">
        <label>CVV</label>
        <input type="text" name="cvv" maxlength="4" required>
      </div>
    </div>

    <div class="form-group">
      <label>Titular</label>
      <input type="text" name="titular" maxlength="60" required>
    </div>

    <div class="form-group">
      <label><input type="checkbox" name="comprobante" checked> Deseo recibir comprobante electrónico</label>
    </div>

    <div class="actions">
      <button type="submit" class="btn btn-primary">Confirmar Pago</button>
      <a href="cancelar_cita.php?paciente_id=<?= $paciente_id ?>&doctor_id=<?= $doctor_id ?>&date=<?= urlencode($date) ?>" class="btn btn-secondary">Cancelar</a>
    </div>
  </form>
</body>
</html>



