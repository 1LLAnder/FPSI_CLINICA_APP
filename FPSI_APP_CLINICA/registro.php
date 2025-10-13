<?php
require 'config/db.php';

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $accion = $_POST['accion'] ?? '';

    // Recoger datos del formulario
    $nombre = $_POST['nombre'] ?? '';
    $dni = $_POST['dni'] ?? '';
    $contacto = $_POST['contacto'] ?? '';
    $fechaNacimiento = $_POST['fechaNacimiento'] ?? null;
    $alergias = $_POST['alergias'] ?? '';

    if ($accion === "registrar") {
        if ($nombre && $dni) {
            try {
                // Insertar paciente en la base de datos
                $stmt = $pdo->prepare("INSERT INTO pacientes (nombre_completo, dni, contacto, fecha_nacimiento, alergias) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$nombre, $dni, $contacto, $fechaNacimiento, $alergias]);

                $mensaje = "Paciente registrado con éxito.";
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $mensaje = "Error: Ya existe un paciente con ese DNI.";
                } else {
                    $mensaje = "Error al registrar paciente: " . $e->getMessage();
                }
            }
        } else {
            $mensaje = "Por favor, completa los campos obligatorios.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
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
            width: 400px;
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
            color: green;
            text-align: center;
        }
        .mensaje.error {
            color: red;
        }
    </style>
</head>
<body>

<div class="registro">
    <h2>Registro de Pacientes</h2>

    <?php if ($mensaje): ?>
        <div class="mensaje <?php echo strpos($mensaje, 'Error') === 0 ? 'error' : ''; ?>">
            <?php echo htmlspecialchars($mensaje); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="registro.php">
        <input type="text" name="nombre" placeholder="Nombre completo" required>
        <input type="text" name="dni" placeholder="DNI o identificación" required>
        <input type="text" name="contacto" placeholder="Teléfono o correo" required>
        <input type="date" name="fechaNacimiento" placeholder="Fecha de nacimiento" required>
        <input type="text" name="alergias" placeholder="Alergias o enfermedades">
        <div>
            <button type="submit" name="accion" value="registrar">Registrar</button>
            <button type="submit" name="accion" value="editar" class="btn-secondary">Editar</button>
            <button type="submit" name="accion" value="eliminar" class="btn-secondary">Eliminar</button>
            <button type="submit" name="accion" value="buscar" class="btn-secondary">Buscar</button>
