<?php
require_once __DIR__ . '/includes/auth.inc.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;

