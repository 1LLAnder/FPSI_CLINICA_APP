<?php
// confirmar_pago.php
require_once 'config/db.php';

$paciente_id = isset($_GET['paciente_id']) ? (int)$_GET['paciente_id'] : 0;
$doctor_id = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
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

$servicio = "Consulta médica con " . ($doctor ? $doctor['name'] : "Doctor");
$monto = 80.00;
$metodo_pago = "Tarjeta de crédito";

// Si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $tarjeta = substr($_POST['numero_tarjeta'], -4);
  $desea_comprobante = isset($_POST['comprobante']) ? 1 : 0;

  // Guardar en la BD
  $stmt = $pdo->prepare("INSERT INTO pagos (paciente_id, servicio, monto, metodo_pago, tarjeta_ultimos4, desea_comprobante) 
                         VALUES (?, ?, ?, ?, ?, ?)");
  $stmt->execute([$paciente_id, $servicio, $monto, $metodo_pago, $tarjeta, $desea_comprobante]);

  // Redirigir a pantalla de éxito
  header("Location: pago_exitoso.php");
  exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Confirmar Pago</title>
  <link rel="stylesheet" href="public/confirmar_pago.css">
</head>
<body>
  <form method="POST" class="card">
    <h2>Confirmar Pago</h2>
    <p>Verifica los datos antes de confirmar la transacción.</p>

    <p><strong>Paciente:</strong> <?= htmlspecialchars($paciente['nombre_completo'] ?? '') ?></p>
    <p><strong>Contacto:</strong> <?= htmlspecialchars($paciente['contacto'] ?? '') ?></p>
    <p><strong>Servicio:</strong> <?= htmlspecialchars($servicio) ?></p>
    <p><strong>Fecha de cita:</strong> <?= htmlspecialchars($date) ?></p>
    <p><strong>Monto total:</strong> S/ <?= number_format($monto, 2) ?></p>
    <p><strong>Método de pago:</strong> <?= $metodo_pago ?></p>

    <div class="form-group">
      <label for="numero_tarjeta">Número de tarjeta</label>
      <input type="text" name="numero_tarjeta" required maxlength="19" placeholder="XXXX XXXX XXXX XXXX">
    </div>

    <div class="form-group flex">
      <div style="flex: 1">
        <label for="fecha">Fecha</label>
        <input type="text" name="fecha" placeholder="MM/AA" required>
      </div>
      <div style="flex: 1">
        <label for="cvv">CVV</label>
        <input type="text" name="cvv" required maxlength="4">
      </div>
    </div>

    <div class="form-group">
      <label for="titular">Titular</label>
      <input type="text" name="titular" required>
    </div>

    <div class="form-group">
      <label><input type="checkbox" name="comprobante" checked> Deseo recibir comprobante electrónico en mi correo</label>
    </div>

    <div class="actions">
      <button type="submit" class="btn btn-primary">Confirmar Pago</button>
      <a href="index.php" class="btn btn-secondary">Cancelar</a>
    </div>
  </form>
</body>
</html>
