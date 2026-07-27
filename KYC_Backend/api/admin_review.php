<?php
session_start();
require '../config/db.php';
require '../includes/auth.php';

header('Content-Type: application/json');

// Extra check: only admins allowed here
if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // List all pending/flagged documents for review
    try {
        $stmt = $pdo->prepare(
            "SELECT kd.id, kd.document_type, kd.status, kd.uploaded_at,
                    kd.ocr_extracted_data, kd.face_match_score,
                    u.full_name, u.email
             FROM kyc_documents kd
             JOIN users u ON kd.user_id = u.id
             WHERE kd.status IN ('pending', 'flagged')
             ORDER BY kd.uploaded_at ASC"
        );
        $stmt->execute();
        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        http_response_code(200);
        echo json_encode(['success' => true, 'documents' => $documents]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Something went wrong']);
    }

} elseif ($method === 'POST') {
    // Approve or reject a specific document
    $data = json_decode(file_get_contents('php://input'), true);

    $documentId = $data['document_id'] ?? null;
    $decision   = $data['decision'] ?? '';

    if (!$documentId || !in_array($decision, ['approved', 'rejected'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'document_id and a valid decision are required']);
        exit;
    }

    try {
        $stmt = $pdo->prepare(
            "UPDATE kyc_documents
             SET status = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP
             WHERE id = ?
             RETURNING id, status, reviewed_at"
        );
        $stmt->execute([$decision, $_SESSION['user_id'], $documentId]);
        $updated = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$updated) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Document not found']);
            exit;
        }

        // Log the admin action
        $logStmt = $pdo->prepare(
            "INSERT INTO audit_logs (user_id, action, details) VALUES (?, ?, ?)"
        );
        $logStmt->execute([
            $_SESSION['user_id'],
            'admin_review',
            "Document ID {$documentId} marked as {$decision}"
        ]);

        http_response_code(200);
        echo json_encode(['success' => true, 'document' => $updated]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Something went wrong']);
    }

} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}
?>