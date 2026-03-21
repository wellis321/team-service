<?php
/**
 * Team Service — Logout
 */
require_once dirname(__DIR__) . '/config/config.php';

Auth::logout();
header('Location: ' . url('login.php'));
exit;
