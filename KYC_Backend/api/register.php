<?php
require '../config/db.php';

header('Content-Type: application/json');

// Read incoming JSON data
$data = json_decode(file_get_contents('php://input'), true);

$fullName = trim($data['full_name'] ?? '');
$email    = filter_var(trim($data['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$password = $data['password'] ?? '';
$dob      = $data['dob'] ?? null;

// Validate input
if (!$fullName) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Full name is required']);
    exit;
}

if (!$email) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Valid email is required']);
    exit;
}

if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Password must be at least 8 characters']);
    exit;
}

// Hash the password (never store plaintext)
$passwordHash = password_hash($password, PASSWORD_BCRYPT);

try {
    $stmt = $pdo->prepare(
        "INSERT INTO users (full_name, email, password_hash, dob)
         VALUES (?, ?, ?, ?)
         RETURNING id, full_name, email, role, created_at"
    );
    $stmt->execute([$fullName, $email, $passwordHash, $dob]);

    $newUser = $stmt->fetch(PDO::FETCH_ASSOC);

    http_response_code(201);
    echo json_encode(['success' => true, 'user' => $newUser]);

} catch (PDOException $e) {
    // Postgres unique_violation error code
    if ($e->getCode() == 23505) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Email already registered']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Something went wrong']);
    }
}
?>