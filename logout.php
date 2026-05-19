<?php
// auth/logout.php
require_once __DIR__ . '/../config/db.php';
session_start_safe();
session_unset();
session_destroy();
flash('success', 'You have been logged out successfully.');
redirect(APP_URL . '/auth/login.php');
