<?php
require 'config/db.php';

$mensaje = "";
$datos = ['nombre' => '', 'dni' => '', 'contacto' => '', 'fechaNacimiento' => '', 'alergias' => ''];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $accion = $_POST['accion'] ?? '';
    $dni = trim($_POST['dni'] ?? '');

    // Prellenar campos si vienen
    $datos['nombre'] = trim($_POST['nombre'] ?? '');
    $datos['dni'] = $dni;
    $datos['contacto'] = trim($_POST['contacto'] ?? '');
    $datos['fechaNacimiento'] = $_POST['fechaNacimiento'] ?? '';
    $datos['alergias'] = trim($_POST['alergias'] ?? '');

    if ($accion === "registrar") {
        if ($datos['nombre'] && $datos['dni']) {
            try {
                $stmt = $pdo->prepare("INSERT INTO pacientes (nombre_completo, dni, contacto, fecha_nacimiento, alergias)
                                       VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$datos['nombre'], $datos['dni'], $datos['contacto'], $datos['fechaNacimiento'], $datos['alergias']]);
                $mensaje = "✅ Paciente registrado con éxito.";
                $datos = ['nombre' => '', 'dni' => '', 'contacto' => '', 'fechaNacimiento' => '', 'alergias' => ''];
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $mensaje = "❌ Ya existe un paciente con ese DNI.";
                } else {
                    $mensaje = "❌ Error al registrar: " . $e->getMessage();
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
            $mensaje = "👁 Paciente encontrado. Puedes editar o eliminar.";
        } else {
            $mensaje = "❌ No se encontró un paciente con ese DNI.";
        }
    }

    if ($accion === "editar" && $dni) {
        $stmt = $pdo->prepare("UPDATE pacientes SET nombre_completo=?, contacto=?, fecha_nacimiento=?, alergias=? WHERE dni=?");
        $stmt->execute([$datos['nombre'], $datos['contacto'], $datos['fechaNacimiento'], $datos['alergias'], $dni]);
        $mensaje = "✏️ Paciente actualizado correctamente.";
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
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f4f8;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .registro {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            width: 420px;
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #22303c;
        }
        input[type="text"], input[type="date"] {
            width: 100%;
            padding: 12px 15px;
            margin: 8px 0;
            box-sizing: border-box;
            border-radius: 5px;
            border: 1px solid #ccc;
        }
        button {
            background-color: #1860ff;
            color: white;
            border: none;
            padding: 12px 20px;
            margin-top: 10px;
            cursor: pointer;
            border-radius: 6px;
            font-size: 16px;
        }
        button:hover {
            background-color: #0f45cc;
        }
        .btn-secondary {
            background-color: #e8ebf0;
            color: #22303c;
            margin-left: 5px;
        }
        .btn-secondary:hover {
            background-color: #d3d8df;
        }
        .mensaje {
            margin-bottom: 15px;
            font-weight: bold;
            text-align: center;
        }
        .mensaje.error {
            color: red;
        }
        .mensaje.success {
            color: green;
        }
    </style>
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
        <input type="text" name="nombre" placeholder="Nombre completo (máx. 120)" maxlength="120" value="<?= htmlspecialchars($datos['nombre']) ?>" required>
        <input type="text" name="dni" placeholder="DNI (máx. 8)" maxlength="8" value="<?= htmlspecialchars($datos['dni']) ?>" required>
        <input type="text" name="contacto" placeholder="Teléfono o correo (máx. 120)" maxlength="120" value="<?= htmlspecialchars($datos['contacto']) ?>">
        <input type="date" name="fechaNacimiento" placeholder="Fecha de nacimiento" value="<?= htmlspecialchars($datos['fechaNacimiento']) ?>">
        <input type="text" name="alergias" placeholder="Alergias o enfermedades" maxlength="255" value="<?= htmlspecialchars($datos['alergias']) ?>">

        <div style="margin-top: 12px;">
            <button type="submit" name="accion" value="registrar">Registrar</button>
            <button type="submit" name="accion" value="buscar" class="btn-secondary">Buscar</button>
            <button type="submit" name="accion" value="editar" class="btn-secondary">Editar</button>
            <button type="submit" name="accion" value="eliminar" class="btn-secondary" onclick="return confirm('¿Seguro que deseas eliminar este paciente?');">Eliminar</button>
        </div>
    </form>
</div>

</body>
</html>
