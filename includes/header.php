<?php
// includes/header.php

// Set a default page title if one hasn't been defined by the including page
if (!isset($page_title)) {
    $page_title = 'SkillConnect';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></title>
    
    <!-- 
      Path is relative to the root pages (e.g., dashboard.php) 
      that will be including this header file.
    -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
