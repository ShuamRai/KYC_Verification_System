<?php
session_start();
require '../config/db.php';
require '../includes/auth.php';

header('Content-Type: application/json');

$userId = $_SESSION['user_id'];

// Validate document type was sent
$allowedTypes = ['citizenship', 'passport', 'license'];
$documentType = $_POST['document_type'] ?? '';

if (!in_array($documentType, $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid document type']);
    exit;
}

// Validate both files were actually sent
if (!isset($_FILES['document']) || !isset($_FILES['selfie'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Document and selfie files are required']);
    exit;
}

// Validate file types and sizes
$allowedMime = ['image/jpeg', 'image/png'];
$maxSize = 5 * 1024 * 1024; // 5MB

foreach (['document', 'selfie'] as $field) {
    $file = $_FILES[$field];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Upload error on $field"]);
        exit;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $actualMime = $finfo->file($file['tmp_name']);

    if (!in_array($actualMime, $allowedMime)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "$field must be JPEG or PNG (detected: $actualMime)"]);
        exit;
    }

    if ($file['size'] > $maxSize) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "$field exceeds 5MB limit"]);
        exit;
    }
}

// Build safe, unique file names
$docExt    = pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION);
$selfieExt = pathinfo($_FILES['selfie']['name'], PATHINFO_EXTENSION);

$docFileName    = uniqid('doc_') . '.' . $docExt;
$selfieFileName = uniqid('selfie_') . '.' . $selfieExt;

$docPath    = '../uploads/documents/' . $docFileName;
$selfiePath = '../uploads/selfies/' . $selfieFileName;

// Move uploaded files from PHP's temp location to permanent storage
if (!move_uploaded_file($_FILES['document']['tmp_name'], $docPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save document']);
    exit;
}

if (!move_uploaded_file($_FILES['selfie']['tmp_name'], $selfiePath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save selfie']);
    exit;
}

try {
    $stmt = $pdo->prepare(
        "INSERT INTO kyc_documents (user_id, document_type, document_path, selfie_path)
         VALUES (?, ?, ?, ?)
         RETURNING id, status, uploaded_at"
    );
    $stmt->execute([$userId, $documentType, $docPath, $selfiePath]);
    $newDoc = $stmt->fetch(PDO::FETCH_ASSOC);

    // Log the upload
    $logStmt = $pdo->prepare(
        "INSERT INTO audit_logs (user_id, action, details) VALUES (?, ?, ?)"
    );
    $logStmt->execute([$userId, 'document_uploaded', "Document ID {$newDoc['id']} uploaded"]);

    http_response_code(201);
    echo json_encode(['success' => true, 'document' => $newDoc]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Something went wrong']);
}