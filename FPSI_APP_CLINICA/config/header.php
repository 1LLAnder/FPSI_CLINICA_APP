<?php if (session_status() === PHP_SESSION_NONE) session_start(); ?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Clínica</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="/clinica/assets/styles.css">
</head>
<body>
  <nav class="topbar">
    <div class="brand">Clínica</div>
    <div class="navlinks">
      <?php if (!empty($_SESSION['user_id'])): ?>
        <a href="/clinica/">Inicio</a>
        <a href="/clinica/pacientes.php">Pacientes</a>
        <a href="/clinica/citas.php">Citas</a>
        <a href="/clinica/pagos.php">Pagos</a>
        <a href="/clinica/logout.php">Salir</a>
      <?php else: ?>
        <a href="/clinica/login.php">Ingresar</a>
        <a href="/clinica/registro.php">Registrarse</a>
      <?php endif; ?>
    </div>
  </nav>
  <main class="container">
