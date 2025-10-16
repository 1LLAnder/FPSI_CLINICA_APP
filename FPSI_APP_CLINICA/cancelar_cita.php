<?php
require_once 'config/db.php';

$paciente_id = (int)($_GET['paciente_id'] ?? 0);
$doctor_id = (int)($_GET['doctor_id'] ?? 0);
$date = $_GET['date'] ?? '';

if ($paciente_id && $doctor_id && $date) {
    $stmt = $pdo->prepare("UPDATE appointments 
                           SET status='cancelada' 
                           WHERE paciente_id=? AND doctor_id=? AND date=?");
    $stmt->execute([$paciente_id,$doctor_id,$date]);
}
header("Location: registro.php");
exit;
?>