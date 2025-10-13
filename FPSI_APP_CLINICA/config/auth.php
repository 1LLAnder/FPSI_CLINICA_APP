<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function require_login() {
  if (empty($_SESSION['user_id'])) {
    header("Location: /clinica/login.php");
    exit;
  }
}

function current_user() {
  return $_SESSION['user_name'] ?? null;
}
