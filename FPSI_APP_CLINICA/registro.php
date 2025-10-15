<?php
require 'config/db.php';

$mensaje = "";
$datos = ['nombre' => '', 'dni' => '', 'contacto' => '', 'fechaNacimiento' => '', 'alergias' => ''];
$paciente_id = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $accion = $_POST['accion'] ?? '';
    $dni = trim($_POST['dni'] ?? '');

    $datos['nombre'] = trim($_POST['nombre'] ?? '');
    $datos['dni'] = $dni;
    $datos['contacto'] = trim($_POST['contacto'] ?? '');
    $datos['fechaNacimiento'] = $_POST['fechaNacimiento'] ?? '';
    $datos['alergias'] = trim($_POST['alergias'] ?? '');

    $fechaValida = true;
    if (
        ($accion === "registrar" || $accion === "editar") &&
        $datos['fechaNacimiento']
    ) {
        $fnac = $datos['fechaNacimiento'];
        $hoy = date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fnac) ||
            !checkdate((int)substr($fnac,5,2), (int)substr($fnac,8,2), (int)substr($fnac,0,4))
        ) {
            $fechaValida = false;
            $mensaje = "❌ Fecha de nacimiento inválida.";
        } elseif ($fnac < '1900-01-01') {
            $fechaValida = false;
            $mensaje = "❌ Fecha de nacimiento demasiado antigua.";
        } elseif ($fnac > $hoy) {
            $fechaValida = false;
            $mensaje = "❌ La fecha de nacimiento no puede ser en el futuro.";
        } elseif ($fnac > '2025-12-31') {
            $fechaValida = false;
            $mensaje = "❌ La fecha de nacimiento no puede ser posterior a 2025.";
        }
    }

    if ($accion === "registrar") {
        if ($datos['nombre'] && $datos['dni']) {
            if ($fechaValida) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO pacientes (nombre_completo, dni, contacto, fecha_nacimiento, alergias)
                                       VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$datos['nombre'], $datos['dni'], $datos['contacto'], $datos['fechaNacimiento'], $datos['alergias']]);
                    $mensaje = "✅ Paciente registrado con éxito.";
                    $paciente_id = $pdo->lastInsertId();
                    $datos = ['nombre' => '', 'dni' => '', 'contacto' => '', 'fechaNacimiento' => '', 'alergias' => ''];
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) {
                        $stmt = $pdo->prepare("SELECT id FROM pacientes WHERE dni = ?");
                        $stmt->execute([$datos['dni']]);
                        $row = $stmt->fetch();
                        if ($row) $paciente_id = $row['id'];
                        $mensaje = "❌ Ya existe un paciente con ese DNI.";
                    } else {
                        $mensaje = "❌ Error al registrar: " . $e->getMessage();
                    }
                }
            }
        } else {
            $mensaje = "❌ Completa los campos obligatorios.";
        }
    }

    if ($accion === "buscar" && $dni) {
        $stmt = $pdo->prepare("SELECT * FROM pacientes WHERE dni = ?");
        $stmt->execute([$dni]);
        $paciente = $stmt->fetch();

        if ($paciente) {
            $datos['nombre'] = $paciente['nombre_completo'];
            $datos['contacto'] = $paciente['contacto'];
            $datos['fechaNacimiento'] = $paciente['fecha_nacimiento'];
            $datos['alergias'] = $paciente['alergias'];
            $paciente_id = $paciente['id'];
            $mensaje = "👁 Paciente encontrado. Puedes editar o eliminar.";
        } else {
            $mensaje = "❌ No se encontró un paciente con ese DNI.";
        }
    }

    if ($accion === "editar" && $dni) {
        if ($fechaValida) {
            $stmt = $pdo->prepare("UPDATE pacientes SET nombre_completo=?, contacto=?, fecha_nacimiento=?, alergias=? WHERE dni=?");
            $stmt->execute([$datos['nombre'], $datos['contacto'], $datos['fechaNacimiento'], $datos['alergias'], $dni]);
            $mensaje = "✏️ Paciente actualizado correctamente.";
            $stmt = $pdo->prepare("SELECT id FROM pacientes WHERE dni = ?");
            $stmt->execute([$dni]);
            $row = $stmt->fetch();
            if ($row) $paciente_id = $row['id'];
        }
    }

    if ($accion === "eliminar" && $dni) {
        $stmt = $pdo->prepare("DELETE FROM pacientes WHERE dni = ?");
        $stmt->execute([$dni]);

        if ($stmt->rowCount() > 0) {
            $mensaje = "🗑 Paciente eliminado exitosamente.";
            $datos = ['nombre' => '', 'dni' => '', 'contacto' => '', 'fechaNacimiento' => '', 'alergias' => ''];
        } else {
            $mensaje = "❌ No se encontró un paciente con ese DNI.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro de Pacientes</title>
    <link rel="stylesheet" href="public/registro.css">
</head>
<body>
<div class="registro">
    <h2>Gestión de Pacientes</h2>

    <?php if ($mensaje): ?>
        <div class="mensaje <?= strpos($mensaje, '❌') === 0 ? 'error' : 'success' ?>">
            <?= htmlspecialchars($mensaje) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="registro.php">
        <input type="text" name="nombre" placeholder="Nombre completo (máx. 60)" maxlength="60" value="<?= htmlspecialchars($datos['nombre']) ?>">
        <input type="text" name="dni" placeholder="DNI (máx. 8)" maxlength="8" value="<?= htmlspecialchars($datos['dni']) ?>" required>
        <input type="text" name="contacto" placeholder="Teléfono o correo (máx. 60)" maxlength="60" value="<?= htmlspecialchars($datos['contacto']) ?>">
        <label for="fechaNacimiento" style="font-size:14px;color:#22303c;">Fecha de nacimiento (DD/MM/AAAA)</label>
        <input type="date" id="fechaNacimiento" name="fechaNacimiento" value="<?= htmlspecialchars($datos['fechaNacimiento']) ?>">
        <input type="text" name="alergias" placeholder="Alergias o enfermedades" maxlength="120" value="<?= htmlspecialchars($datos['alergias']) ?>">

        <div style="margin-top: 12px;">
            <button type="submit" name="accion" value="registrar">Registrar</button>
            <button type="submit" name="accion" value="buscar" class="btn-secondary">Buscar</button>
            <button type="submit" name="accion" value="editar" class="btn-secondary">Editar</button>
            <button type="submit" name="accion" value="eliminar" class="btn-secondary" onclick="return confirm('¿Seguro que deseas eliminar este paciente?');">Eliminar</button>
        </div>
    </form>

    <?php if ($paciente_id): ?>
        <form method="get" action="citas_medicas.php" style="margin-top:18px;text-align:center;">
            <input type="hidden" name="paciente_id" value="<?= (int)$paciente_id ?>">
            <button type="submit" class="btn-primary" style="width:100%;background:#1860ff;color:#fff;padding:12px 0;border-radius:6px;font-size:16px;">Continuar para agendar cita</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
