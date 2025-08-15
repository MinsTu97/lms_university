<?php
// Disable error display to prevent HTML output
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Set JSON content type
header('Content-Type: application/json');

require_once '../config/database.php';
requireLogin();

if (!hasRole('instructor')) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$assignment_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$status = isset($_POST['status']) && in_array($_POST['status'], ['active', 'inactive']) ? $_POST['status'] : null;

if (!$assignment_id || !$status) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Verify assignment belongs to instructor
    $query = "SELECT a.id FROM assignments a JOIN courses c ON a.course_id = c.id WHERE a.id = ? AND c.instructor_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$assignment_id, $_SESSION['user_id']]);
    
    if ($stmt->rowCount() === 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit();
    }

    // Update assignment status
    $query = "UPDATE assignments SET status = ? WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$status, $assignment_id]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>