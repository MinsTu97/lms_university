<?php
require_once '../config/database.php';
requireLogin();

if (!hasRole('student')) {
    header('Location: ../auth/login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get all assignments for the student's enrolled courses
$query = "SELECT a.*, c.title as course_title, c.course_code,
          s.grade, s.submitted_at,
          CASE WHEN s.id IS NOT NULL THEN 'submitted' ELSE 'pending' END as status
          FROM assignments a
          JOIN courses c ON a.course_id = c.id
          JOIN enrollments e ON c.id = e.course_id
          LEFT JOIN assignment_submissions s ON a.id = s.assignment_id AND s.student_id = ?
          WHERE e.student_id = ? AND e.status = 'enrolled'
          ORDER BY a.due_date ASC";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignments - University LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/dashboard.css" rel="stylesheet">
    <style>
        .status-badge.submitted { background: #22c55e; color: #fff; }
        .status-badge.pending { background: #facc15; color: #fff; }
    </style>
</head>
<body>
<?php include '../includes/student_navbar.php'; ?>
<div class="container-fluid">
    <div class="row">
        <?php include '../includes/student_sidebar.php'; ?>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-tasks"></i> Assignments</h1>
            </div>
            <div class="card shadow mb-4">
                <div class="card-header bg-section-gray text-white">
                    <h6 class="m-0 font-weight-bold"><i class="fas fa-tasks"></i> All Assignments</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Course</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <th>Grade</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($assignments)): ?>
                                    <tr><td colspan="6" class="text-center text-muted">No assignments found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($assignments as $assignment): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($assignment['title']); ?></td>
                                            <td><?php echo htmlspecialchars($assignment['course_title']); ?></td>
                                            <td><?php echo htmlspecialchars($assignment['due_date']); ?></td>
                                            <td><span class="badge status-badge <?php echo $assignment['status']; ?>"><?php echo ucfirst($assignment['status']); ?></span></td>
                                            <td><?php echo $assignment['grade'] !== null ? $assignment['grade'] : '-'; ?></td>
                                            <td>
                                                <a href="assignment_view.php?id=<?php echo $assignment['id']; ?>" class="btn btn-sm btn-medium-gray">View</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
