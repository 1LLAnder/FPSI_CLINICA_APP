<?php
// confirmar_pago.php
require_once 'config/db.php';

// Simulamos datos recibidos (en práctica vienen desde una cita previa)
$paciente_id = 1; // ID de la base de datos
$paciente_nombre = "Juan Pérez";
$servicio = "Consulta médica general";
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

  echo "<p>✅ Pago registrado exitosamente.</p>";
  exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Confirmar Pago</title>
  <style>
    body { font-family: Arial, sans-serif; background: #f3f4f6; display: flex; align-items: center; justify-content: center; height: 100vh; }
    .card {
      background: white; border-radius: 16px; padding: 24px; box-shadow: 0 8px 24px rgba(0,0,0,0.1);
      max-width: 400px; width: 100%;
    }
    .card h2 { margin-top: 0; }
    .form-group { margin-bottom: 12px; }
    label { display: block; margin-bottom: 4px; font-weight: bold; }
    input[type="text"], input[type="number"] {
      width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 8px;
    }
    .flex { display: flex; gap: 8px; }
    .actions { margin-top: 16px; display: flex; justify-content: space-between; }
    .btn {
      padding: 10px 16px; border: none; border-radius: 8px; cursor: pointer; font-weight: bold;
    }
    .btn-primary { background: #3b82f6; color: white; }
    .btn-secondary { background: #e5e7eb; }
  </style>
</head>
<body>
  <form method="POST" class="card">
    <h2>Confirmar Pago</h2>
    <p>Verifica los datos antes de confirmar la transacción.</p>

    <p><strong>Paciente:</strong> <?= htmlspecialchars($paciente_nombre) ?></p>
    <p><strong>Servicio:</strong> <?= htmlspecialchars($servicio) ?></p>
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
