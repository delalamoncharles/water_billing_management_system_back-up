<?php
$conn = new mysqli('localhost', 'root', '', 'water_billing_management_system');
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

$hashed = password_hash('admin123', PASSWORD_BCRYPT);
$stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $full_name, $email, $password, $role);

$full_name = 'Admin User';
$email = 'admin@gmail.com';
$password = $hashed;
$role = 'admin';

if ($stmt->execute()) {
  echo "Admin inserted.";
} else {
  echo "Error: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>