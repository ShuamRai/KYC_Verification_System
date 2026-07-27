<?php
session_start();
require '../config/db.php';
require '../includes/auth.php';

header('Content-Type: application/json');

$userId = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare(
        "SELECT id, document_type, status, uploaded_at, reviewed_at
         FROM kyc_documents
         WHERE user_id = ?
         ORDER BY uploaded_at DESC"
    );
    $stmt->execute([$userId]);
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    http_response_code(200);
    echo json_encode(['success' => true, 'documents' => $documents]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Something went wrong']);
}
?>