<?php
require_once __DIR__ . '/../includes/bootstrap.php';
if (current_user()) { audit_log('logout', 'User logged out'); }
do_logout();
session_start();                       // fresh session just to carry the confirmation message
flash('success', 'You have been logged out.');
redirect('auth/login.php');
