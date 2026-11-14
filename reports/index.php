<?php
/** Entry point for reports: sends each role to the report page it is allowed to use. */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_role(['admin', 'school']);
redirect($user['role'] === 'admin' ? 'admin/reports.php' : 'school/reports.php');
