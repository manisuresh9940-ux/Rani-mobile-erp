<?php
/**
 * Redirect to list with sell tab
 */
require_once __DIR__ . '/../../config/auth.php';
require_auth();
header('Location: ' . BASE_URL . '/modules/secondhand/list.php');
exit;
