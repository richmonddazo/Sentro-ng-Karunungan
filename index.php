<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (!is_logged_in()) {
    redirect('login.php');
}

redirect(is_admin() ? 'admin/dashboard.php' : 'user/dashboard.php');
