<?php
if (!ob_get_level()) {
  ob_start();
}

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

if (!isset($_SESSION['user_id'], $_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
  header('Location: login.php');
  exit;
}

$authConn = new mysqli('localhost', 'root', '', 'billing_system');
if ($authConn->connect_error) {
  die('Connection failed: ' . $authConn->connect_error);
}

$adminId = (int) $_SESSION['user_id'];
$adminStmt = $authConn->prepare("SELECT full_name FROM users WHERE id = ? AND role = 'admin' AND is_active = 1 LIMIT 1");
if (!$adminStmt) {
  die('Unable to verify the admin account.');
}

$adminStmt->bind_param('i', $adminId);
$adminStmt->execute();
$adminResult = $adminStmt->get_result();
$adminUser = $adminResult ? $adminResult->fetch_assoc() : null;
$adminStmt->close();
$authConn->close();

if (!$adminUser) {
  session_unset();
  session_destroy();
  header('Location: login.php');
  exit;
}

$admin_name = trim($adminUser['full_name'] ?? ($_SESSION['user_name'] ?? 'Administrator'));
$admin_name = $admin_name !== '' ? $admin_name : 'Administrator';
$_SESSION['user_name'] = $admin_name;
