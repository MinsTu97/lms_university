<?php
require_once '../config/database.php';
requireLogin();

if (!hasRole('student')) {
    header('Location: ../auth/login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get enrolled courses
$query = "SELECT c.*, u.first_name, u.last_name, e.enrollment_date, e.final_grade 
          FROM courses c 
          JOIN enrollments e ON c.id = e.course_id 
          JOIN users u ON c.instructor_id = u.id 
          WHERE e.student_id = ? AND e.status = 'enrolled'";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$enrolled_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent assignments
$query = "SELECT a.*, c.title as course_title, c.course_code,
          CASE WHEN s.id IS NOT NULL THEN 'submitted' ELSE 'pending' END as status,
          s.grade, s.submitted_at
          FROM assignments a
          JOIN courses c ON a.course_id = c.id
          JOIN enrollments e ON c.id = e.course_id
          LEFT JOIN assignment_submissions s ON a.id = s.assignment_id AND s.student_id = ?
          WHERE e.student_id = ? AND e.status = 'enrolled'
          ORDER BY a.due_date ASC LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$recent_assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent quiz attempts
$query = "SELECT q.title, c.title as course_title, qa.score, qa.total_points, qa.completed_at
          FROM quiz_attempts qa
          JOIN quizzes q ON qa.quiz_id = q.id
          JOIN courses c ON q.course_id = c.id
          WHERE qa.student_id = ? AND qa.completed_at IS NOT NULL
          ORDER BY qa.completed_at DESC LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$recent_quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - University LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/dashboard.css" rel="stylesheet">
<style>
/* Medium gray button for Browse Courses */
.btn.btn-medium-gray {
    background: #6c757d !important;
    color: #fff !important;
    border: none;
    transition: background 0.15s;
}
.btn.btn-medium-gray:hover, .btn.btn-medium-gray:focus {
    background: #5a6268 !important;
    color: #fff !important;
}
/* Card header medium gray for main sections */
.card-header.bg-section-gray, .card-header.section-gray {
    background: #6c757d !important;
    color: #fff !important;
}
/* Stats cards: rectangle, all text white */
.stats-card {
    border-radius: 0.5rem;
    box-shadow: 0 6px 32px rgba(80, 120, 200, 0.13);
    border: none;
    color: #fff !important;
    background-clip: border-box;
    padding: 2rem 1.25rem 1.5rem 1.25rem;
    text-align: center;
    margin-bottom: 1.5rem;
    min-height: 160px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    transition: box-shadow 0.18s, transform 0.18s;
}
.stats-card .icon {
    width: 54px;
    height: 54px;
    border-radius: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1rem;
    font-size: 2rem;
    background: rgba(0,0,0,0.12);
}
.stats-card.bg-primary { background: #2563eb !important; }
.stats-card.bg-success { background: #22c55e !important; }
.stats-card.bg-info { background: #0ea5e9 !important; }
.stats-card.bg-warning { background: #facc15 !important; color: #fff !important; }
.stats-card.bg-warning * { color: #fff !important; }
.stats-card:hover {
    box-shadow: 0 12px 40px rgba(80, 120, 200, 0.18);
    transform: translateY(-2px) scale(1.02);
}
.stats-card .label {
    font-weight: 600;
    font-size: 1.1rem;
    letter-spacing: 0.5px;
    margin-bottom: 0.5rem;
}
.stats-card .value {
    font-size: 2.2rem;
    font-weight: 700;
    line-height: 1.1;
}
/* Navbar medium gray */
/* Navbar medium gray, text black */
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
@media (max-width: 991px) {
    .stats-card { min-height: 120px; padding: 1.25rem 0.75rem; }
}
</style>
</head>
<body>
    <?php include '../includes/student_navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/student_sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-calendar"></i> This week
                            </button>
                        </div>
                    </div>
                </div>



                <!-- Stats Cards -->
                <div class="row mb-4 g-4">
                    <div class="col-xl-3 col-md-6">
                        <div class="stats-card bg-primary">
                            <div class="icon"><i class="fas fa-book"></i></div>
                            <div class="label">Enrolled Courses</div>
                            <div class="value"><?php echo count($enrolled_courses); ?></div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="stats-card bg-success">
                            <div class="icon"><i class="fas fa-clipboard-check"></i></div>
                            <div class="label">Completed Assignments</div>
                            <div class="value"><?php echo count(array_filter($recent_assignments, function($a) { return $a['status'] == 'submitted'; })); ?></div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="stats-card bg-info">
                            <div class="icon"><i class="fas fa-question-circle"></i></div>
                            <div class="label">Quiz Attempts</div>
                            <div class="value"><?php echo count($recent_quizzes); ?></div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="stats-card bg-warning">
                            <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
                            <div class="label">Pending Tasks</div>
                            <div class="value"><?php echo count(array_filter($recent_assignments, function($a) { return $a['status'] == 'pending'; })); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="row">
                    <!-- Enrolled Courses -->
                    <div class="col-lg-8 mb-4">
                        <div class="card shadow">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold section-gray card-header bg-section-gray">
                                    <i class="fas fa-book"></i> My Courses
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($enrolled_courses)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-book fa-3x text-gray-300 mb-3"></i>
                                        <p class="text-muted">You are not enrolled in any courses yet.</p>
                                        <a href="courses.php" class="btn btn-medium-gray">Browse Courses</a>
                                    </div>
                                <?php else: ?>
                                    <div class="row">
                                        <?php foreach ($enrolled_courses as $course): ?>
                                            <div class="col-md-6 mb-3">
                                                <div class="card border-left-primary">
                                                    <div class="card-body">
                                                        <h6 class="card-title"><?php echo htmlspecialchars($course['title']); ?></h6>
                                                        <p class="card-text text-muted small">
                                                            <?php echo htmlspecialchars($course['course_code']); ?> - 
                                                            <?php echo htmlspecialchars($course['first_name'] . ' ' . $course['last_name']); ?>
                                                        </p>
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <small class="text-muted">
                                                                Enrolled: <?php echo formatDate($course['enrollment_date']); ?>
                                                            </small>
<a href="course_view.php?id=<?php echo $course['id']; ?>" class="btn btn-sm btn-medium-gray">
                                                                View Course
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="col-lg-4 mb-4">
                        <div class="card shadow">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold section-gray card-header bg-section-gray">
                                    <i class="fas fa-clock"></i> Recent Activity
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="timeline">
                                    <?php foreach ($recent_assignments as $assignment): ?>
                                        <div class="timeline-item">
                                            <div class="timeline-marker <?php echo $assignment['status'] == 'submitted' ? 'bg-success' : 'bg-warning'; ?>"></div>
                                            <div class="timeline-content">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($assignment['title']); ?></h6>
                                                <p class="text-muted small mb-1"><?php echo htmlspecialchars($assignment['course_title']); ?></p>
                                                <small class="text-muted">
                                                    Due: <?php echo formatDate($assignment['due_date']); ?>
                                                    <?php if ($assignment['status'] == 'submitted'): ?>
                                                        <span class="badge bg-success ms-2">Submitted</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning ms-2">Pending</span>
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Quiz Results -->
                <?php if (!empty($recent_quizzes)): ?>
                <div class="row">
                    <div class="col-12">
                        <div class="card shadow">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold section-gray card-header bg-section-gray">
                                    <i class="fas fa-chart-line"></i> Recent Quiz Results
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Quiz</th>
                                                <th>Course</th>
                                                <th>Score</th>
                                                <th>Percentage</th>
                                                <th>Completed</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_quizzes as $quiz): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($quiz['title']); ?></td>
                                                    <td><?php echo htmlspecialchars($quiz['course_title']); ?></td>
                                                    <td><?php echo $quiz['score']; ?>/<?php echo $quiz['total_points']; ?></td>
                                                    <td>
                                                        <?php 
                                                        $percentage = ($quiz['score'] / $quiz['total_points']) * 100;
                                                        $badge_class = $percentage >= 90 ? 'bg-success' : ($percentage >= 70 ? 'bg-warning' : 'bg-danger');
                                                        ?>
                                                        <span class="badge <?php echo $badge_class; ?>">
                                                            <?php echo number_format($percentage, 1); ?>%
                                                        </span>
                                                    </td>
                                                    <td><?php echo formatDate($quiz['completed_at']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>