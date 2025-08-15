<?php
require_once '../config/database.php';
requireLogin();

if (!hasRole('instructor')) {
    header('Location: ../auth/login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$error = '';
$success = '';

// Get assignments with submissions to grade
$assignments = [];
try {
    $stmt = $db->prepare("SELECT a.id, a.title, c.title as course_title FROM assignments a JOIN courses c ON a.course_id = c.id WHERE c.instructor_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Handle grading submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submission_id = $_POST['submission_id'] ?? '';
    $grade = $_POST['grade'] ?? '';
    if ($submission_id === '' || $grade === '') {
        $error = 'Please select a submission and enter a grade.';
    } else {
        try {
            $stmt = $db->prepare("UPDATE assignment_submissions SET grade = ? WHERE id = ?");
            $stmt->execute([$grade, $submission_id]);
            $success = 'Grade submitted successfully!';
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Get submissions to grade
$submissions = [];
try {
    $stmt = $db->prepare("SELECT s.id, s.student_id, s.assignment_id, s.grade, u.last_name, u.first_name, a.title as assignment_title FROM assignment_submissions s JOIN assignments a ON s.assignment_id = a.id JOIN users u ON s.student_id = u.id JOIN courses c ON a.course_id = c.id WHERE c.instructor_id = ? AND s.grade IS NULL");
    $stmt->execute([$_SESSION['user_id']]);
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Submissions</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    /* Match dashboard navbar: medium gray */
    .navbar, .navbar.navbar-expand-lg, .navbar.navbar-light, .navbar.navbar-dark {
        background: #6c757d !important;
        color: #111 !important;
    }
    .navbar .navbar-brand, .navbar .nav-link, .navbar .navbar-text, .navbar .dropdown-item {
        color: #111 !important;
    }
    .navbar .nav-link.active, .navbar .nav-link:focus, .navbar .nav-link:hover {
        color: #ffd600 !important;
    }
    </style>
</head>
<body>
    <?php include '../includes/instructor_navbar.php'; ?>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h4 class="mb-0">Grade Submissions</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php elseif ($success): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                        <?php endif; ?>
                        <?php if (empty($submissions)): ?>
                            <div class="alert alert-info">No submissions pending grading.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Student</th>
                                            <th>Assignment</th>
                                            <th>Grade</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($submissions as $sub): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($sub['last_name'] . ' ' . $sub['first_name']); ?></td>
                                                <td><?php echo htmlspecialchars($sub['assignment_title']); ?></td>
                                                <td>
                                                    <form method="POST" class="d-flex align-items-center">
                                                        <input type="hidden" name="submission_id" value="<?php echo $sub['id']; ?>">
                                                        <input type="number" name="grade" class="form-control me-2" min="0" max="100" required>
                                                        <button type="submit" class="btn btn-success btn-sm">Submit</button>
                                                    </form>
                                                </td>
                                                <td>
                                                    <?php echo $sub['grade'] !== null ? htmlspecialchars($sub['grade']) : 'Pending'; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
