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

if (!isset($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}

$conn = new mysqli('localhost', 'root', '', 'billing_system');
if ($conn->connect_error) {
  die('Connection failed: ' . $conn->connect_error);
}

$user_id = (int) $_SESSION['user_id'];

$usernameColumnCheck = $conn->query("
  SELECT COLUMN_NAME
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'username'
");
$hasUsername = $usernameColumnCheck && $usernameColumnCheck->num_rows > 0;
if ($usernameColumnCheck instanceof mysqli_result) {
  $usernameColumnCheck->free();
}

$barangayColumnCheck = $conn->query("
  SELECT COLUMN_NAME
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'barangay'
");
$hasBarangay = $barangayColumnCheck && $barangayColumnCheck->num_rows > 0;
if ($barangayColumnCheck instanceof mysqli_result) {
  $barangayColumnCheck->free();
}

$userFields = 'full_name, email, address';
if ($hasUsername) {
  $userFields .= ', username';
}
if ($hasBarangay) {
  $userFields .= ', barangay';
}

$userStmt = $conn->prepare("SELECT {$userFields} FROM users WHERE id = ? AND role = 'user' AND is_active = 1 LIMIT 1");
if (!$userStmt) {
  die('Unable to load the account.');
}

$userStmt->bind_param('i', $user_id);
$userStmt->execute();
$userResult = $userStmt->get_result();
$currentUser = $userResult ? $userResult->fetch_assoc() : null;
$userStmt->close();

if (!$currentUser) {
  session_unset();
  session_destroy();
  header('Location: login.php');
  exit;
}

$user_name = trim($currentUser['full_name'] ?? ($_SESSION['user_name'] ?? 'User'));
$user_name = $user_name !== '' ? $user_name : 'User';
$user_email = trim($currentUser['email'] ?? '');
$user_address = trim($currentUser['address'] ?? '');
$user_barangay = trim($currentUser['barangay'] ?? '');
$user_username = $hasUsername
  ? trim($currentUser['username'] ?? '')
  : strtolower(preg_replace('/[^a-z0-9]+/i', '', strstr($user_email, '@', true) ?: $user_name));

if ($user_username === '') {
  $user_username = 'user';
}

$initialSource = ltrim($user_name);
if ($initialSource === '') {
  $initialSource = 'U';
}

$user_initial = function_exists('mb_substr')
  ? mb_strtoupper(mb_substr($initialSource, 0, 1, 'UTF-8'), 'UTF-8')
  : strtoupper(substr($initialSource, 0, 1));

$_SESSION['user_name'] = $user_name;
