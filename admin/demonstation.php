<?php
// admin/demonstation.php
// SkillConnect - College Demonstration
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

// Verify authentication gateway session
if (empty($_SESSION['demo_authenticated'])) {
    header("Location: infoAuth.php");
    exit();
}
?>
