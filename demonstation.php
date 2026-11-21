<?php
// demonstation.php
require_once __DIR__ . '/includes/auth.php';
requireAdmin();

header("Location: admin/infoAuth.php");
exit();
