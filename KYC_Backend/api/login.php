<?php
session_start();
require '../config/db.php';

header('Content-Type: application/json');

// Read incoming JSON data
$data = json_decode(file_get_contents('php://input'), true);

$email    = filter_var(trim($data['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$password = $data['password'] ?? '';

// Validate input
if (!$email || !$password) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Email and password are required']);
    exit;
}

try {
    $stmt = $pdo->prepare(
        "SELECT id, full_name, email, password_hash, role FROM users WHERE email = ?"
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid email or password']);
        exit;
    }

    // Login successful — start session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role']    = $user['role'];

    // Log this login event
    $logStmt = $pdo->prepare(
        "INSERT INTO audit_logs (user_id, action, details) VALUES (?, ?, ?)"
    );
    $logStmt->execute([$user['id'], 'user_login', 'User logged in']);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'user' => [
            'id'        => $user['id'],
            'full_name' => $user['full_name'],
            'email'     => $user['email'],
            'role'      => $user['role'],
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Something went wrong']);
}