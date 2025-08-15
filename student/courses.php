<?php
require_once '../config/database.php';
requireLogin();

if (!hasRole('student')) {
    header('Location: ../auth/login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];
$message = '';

// Handle enroll action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['course_id'])) {
    $course_id = (int)$_POST['course_id'];
    $stmt = $db->prepare("SELECT id FROM enrollments WHERE student_id = ? AND course_id = ?");
    $stmt->execute([$user_id, $course_id]);
    if ($stmt->fetch()) {
        $message = "You are already enrolled in this course.";
    } else {
        $stmt = $db->prepare("INSERT INTO enrollments (student_id, course_id, status, enrollment_date) VALUES (?, ?, 'enrolled', NOW())");
        $stmt->execute([$user_id, $course_id]);
        $message = "Enrolled successfully!";
    }
}

// Get available courses
$query = "SELECT c.*, u.first_name, u.last_name
          FROM courses c
          JOIN users u ON c.instructor_id = u.id
          WHERE c.id NOT IN (
              SELECT course_id FROM enrollments WHERE student_id = ?
          ) AND c.status = 'active'";
$stmt = $db->prepare($query);
$stmt->execute([$user_id]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Browse Courses</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/student_navbar.php'; ?>
    <div class="container mt-5">
        <h2>Available Courses</h2>
        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if (empty($courses)): ?>
            <div class="alert alert-warning">No available courses to enroll.</div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($courses as $course): ?>
                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($course['title']); ?></h5>
                                <p class="card-text">
                                    Code: <?php echo htmlspecialchars($course['course_code']); ?><br>
                                    Instructor: <?php echo htmlspecialchars($course['first_name'] . ' ' . $course['last_name']); ?><br>
                                    Semester: <?php echo htmlspecialchars($course['semester']); ?>, Year: <?php echo htmlspecialchars($course['year']); ?>
                                </p>
                                <form method="POST">
                                    <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                    <button type="submit" class="btn btn-primary">Enroll</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <a href="dashboard.php" class="btn btn-secondary mt-3">Back to Dashboard</a>
    </div>
</body>
</html>
