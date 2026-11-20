<?php
// my_skills.php - Redirects to the integrated skills management tab in profile.php

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();

header("Location: profile.php?tab=skills");
exit;
