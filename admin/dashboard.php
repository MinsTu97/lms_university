<?php
require_once '../config/database.php';
requireLogin();

if (!hasRole('admin')) {
    header('Location: ../auth/login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Handle search
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_results = [];
if ($search_query) {
    $query = "SELECT id, CONCAT(first_name, ' ', last_name) as name, email, role FROM users 
              WHERE CONCAT(first_name, last_name, email) LIKE :search 
              UNION 
              SELECT id, title as name, '' as email, 'course' as role FROM courses 
              WHERE title LIKE :search 
              LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->execute(['search' => "%$search_query%"]);
    $search_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle notification
$notification_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
    $message = trim($_POST['message']);
    $recipient = $_POST['recipient'];
    
    if ($message) {
        $query = "INSERT INTO notifications (message, recipient_type, created_by, created_at) 
                  VALUES (:message, :recipient, :admin_id, NOW())";
        $stmt = $db->prepare($query);
        $stmt->execute([
            'message' => $message,
            'recipient' => $recipient,
            'admin_id' => $_SESSION['user_id']
        ]);
        $notification_message = 'Notification sent successfully!';
    } else {
        $notification_message = 'Please enter the notification content!';
    }
}

// Handle course approval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_course'])) {
    $course_id = $_POST['course_id'];
    $query = "UPDATE courses SET status = 'active' WHERE id = :course_id";
    $stmt = $db->prepare($query);
    $stmt->execute(['course_id' => $course_id]);
    $notification_message = 'Course approved!';
    header('Location: dashboard.php?message=' . urlencode($notification_message));
    exit();
}

// Handle user deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $user_id = $_POST['user_id'];
    $query = "DELETE FROM users WHERE id = :user_id AND role != 'admin'";
    $stmt = $db->prepare($query);
    $stmt->execute(['user_id' => $user_id]);
    $notification_message = 'User deleted!';
    header('Location: dashboard.php?message=' . urlencode($notification_message));
    exit();
}

// Handle course deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_course'])) {
    $course_id = $_POST['course_id'];
    $query = "SELECT title FROM courses WHERE id = :course_id";
    $stmt = $db->prepare($query);
    $stmt->execute(['course_id' => $course_id]);
    $course_title = $stmt->fetch(PDO::FETCH_ASSOC)['title'];

    $query = "DELETE FROM courses WHERE id = :course_id";
    $stmt = $db->prepare($query);
    $stmt->execute(['course_id' => $course_id]);
    $notification_message = "Course '$course_title' has been deleted!";
    header('Location: dashboard.php?message=' . urlencode($notification_message));
    exit();
}

// Get system statistics
$stats = [];
$query = "SELECT role, COUNT(*) as count FROM users GROUP BY role";
$stmt = $db->prepare($query);
$stmt->execute();
$user_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($user_stats as $stat) {
    $stats[$stat['role']] = $stat['count'];
}

$query = "SELECT COUNT(*) as count FROM courses";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['courses'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$query = "SELECT COUNT(*) as count FROM enrollments WHERE status = 'enrolled'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['enrollments'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Pending courses
$query = "SELECT c.id, c.title, c.instructor_id, CONCAT(u.first_name, ' ', u.last_name) as instructor_name, c.created_at 
          FROM courses c 
          JOIN users u ON c.instructor_id = u.id 
          WHERE c.status = 'inactive' LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute();
$pending_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent activities
$query = "SELECT 'user_registered' as type, CONCAT(first_name, ' ', last_name) as description, created_at as date
          FROM users 
          WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
          UNION ALL
          SELECT 'course_created' as type, title as description, created_at as date
          FROM courses 
          WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
          UNION ALL
          SELECT 'enrollment' as type, CONCAT('Student enrolled in course') as description, enrollment_date as date
          FROM enrollments 
          WHERE enrollment_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
          ORDER BY date DESC LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute();
$recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Course enrollment data for chart
$query = "SELECT c.title, COUNT(e.id) as enrollments
          FROM courses c
          LEFT JOIN enrollments e ON c.id = e.course_id AND e.status = 'enrolled'
          GROUP BY c.id, c.title
          ORDER BY enrollments DESC
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute();
$course_enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Monthly user registrations
$query = "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count
          FROM users
          WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
          GROUP BY DATE_FORMAT(created_at, '%Y-%m')
          ORDER BY month";
$stmt = $db->prepare($query);
$stmt->execute();
$monthly_registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// User list for management
$user_filter = isset($_GET['user_filter']) ? $_GET['user_filter'] : 'all';
$user_query = "SELECT id, username, email, CONCAT(first_name, ' ', last_name) as name, role, created_at 
               FROM users";
if ($user_filter !== 'all') {
    $user_query .= " WHERE role = :role";
}
$user_query .= " ORDER BY created_at DESC LIMIT 10";
$stmt = $db->prepare($user_query);
if ($user_filter !== 'all') {
    $stmt->execute(['role' => $user_filter]);
} else {
    $stmt->execute();
}
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Course list for management
$query = "SELECT c.id, c.title, c.course_code, c.status, c.created_at, CONCAT(u.first_name, ' ', u.last_name) as instructor_name 
          FROM courses c 
          JOIN users u ON c.instructor_id = u.id 
          ORDER BY c.created_at DESC LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute();
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle export report
if (isset($_GET['export'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="lms_report.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Report Type', 'Value']);
    fputcsv($output, ['Total Students', $stats['student'] ?? 0]);
    fputcsv($output, ['Total Instructors', $stats['instructor'] ?? 0]);
    fputcsv($output, ['Total Courses', $stats['courses']]);
    fputcsv($output, ['Active Enrollments', $stats['enrollments']]);
    fputcsv($output, []);
    fputcsv($output, ['Course', 'Enrollments']);
    foreach ($course_enrollments as $course) {
        fputcsv($output, [$course['title'], $course['enrollments']]);
    }
    fclose($output);
    exit();
}

// Get notification message from URL
$notification_message = isset($_GET['message']) ? urldecode($_GET['message']) : $notification_message;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - University LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/dashboard.css" rel="stylesheet">
    <style>
        .bg-gradient-primary {
            background: linear-gradient(90deg, #4e73df 0%, #224abe 100%) !important;
        }
        .dashboard-card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 2px 16px rgba(80, 80, 120, 0.08);
        }
        .dashboard-card .card-header {
            border-radius: 1rem 1rem 0 0;
        }
        .dashboard-card .card-body {
            background: #f8f9fc;
        }
        .dashboard-stats .card {
            border-left-width: 0.5rem;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include '../includes/admin_navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/admin_sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-4">
                    <h1 class="h2 fw-bold text-primary"><i class="fas fa-tachometer-alt me-2"></i>Admin Dashboard</h1>
                    <div class="btn-toolbar gap-2">
                        <form class="d-flex me-2">
                            <input type="text" name="search" class="form-control me-2 shadow-sm" placeholder="Search users/courses..." value="<?php echo htmlspecialchars($search_query); ?>">
                            <button type="submit" class="btn btn-primary shadow-sm"><i class="fas fa-search"></i></button>
                        </form>
                        <a href="?export=1" class="btn btn-success shadow-sm">
                            <i class="fas fa-download"></i> Export Report
                        </a>
                    </div>
                </div>

                <!-- Notification Message -->
                <?php if ($notification_message): ?>
                    <div class="alert alert-info"><?php echo htmlspecialchars($notification_message); ?></div>
                <?php endif; ?>

                <!-- Search Results -->
                <?php if ($search_query && $search_results): ?>
                    <div class="card mb-4">
                        <div class="card-header">Search Results for "<?php echo htmlspecialchars($search_query); ?>"</div>
                        <div class="card-body">
                            <ul class="list-group">
                                <?php foreach ($search_results as $result): ?>
                                    <li class="list-group-item">
                                        <strong><?php echo htmlspecialchars($result['name']); ?></strong>
                                        <?php if ($result['email']): ?>
                                            (<?php echo htmlspecialchars($result['email']); ?>)
                                        <?php endif; ?>
                                        <span class="badge bg-<?php echo $result['role'] === 'course' ? 'info' : ($result['role'] === 'student' ? 'success' : 'primary'); ?>">
                                            <?php echo ucfirst($result['role']); ?>
                                        </span>
                                        <a href="<?php echo $result['role'] === 'course' ? 'courses.php?id=' : 'users.php?id='; ?><?php echo $result['id']; ?>" class="btn btn-sm btn-outline-primary float-end">View</a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>
                                <!-- Welcome Section -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card bg-gradient-primary text-black dashboard-card mb-4">
                            <div class="card-body">
                                <h4 class="fw-bold"><i class="fas fa-user-shield me-2"></i>Welcome, Administrator!</h4>
                                <p class="mb-0">System overview and management tools for University LMS.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="dashboard-stats row mb-4">
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card dashboard-card border-0 shadow h-100 py-2 bg-white">
                                <div class="card-body d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <div class="text-xs fw-bold text-primary text-uppercase mb-1">Total Students</div>
                                        <div class="h4 mb-0 fw-bold text-dark"><?php echo isset($stats['student']) ? $stats['student'] : 0; ?></div>
                                    </div>
                                    <div class="ms-3">
                                        <i class="fas fa-user-graduate fa-2x text-primary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card dashboard-card border-0 shadow h-100 py-2 bg-white">
                                <div class="card-body d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <div class="text-xs fw-bold text-success text-uppercase mb-1">Total Instructors</div>
                                        <div class="h4 mb-0 fw-bold text-dark"><?php echo isset($stats['instructor']) ? $stats['instructor'] : 0; ?></div>
                                    </div>
                                    <div class="ms-3">
                                        <i class="fas fa-chalkboard-teacher fa-2x text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card dashboard-card border-0 shadow h-100 py-2 bg-white">
                                <div class="card-body d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <div class="text-xs fw-bold text-info text-uppercase mb-1">Total Courses</div>
                                        <div class="h4 mb-0 fw-bold text-dark"><?php echo $stats['courses']; ?></div>
                                    </div>
                                    <div class="ms-3">
                                        <i class="fas fa-book fa-2x text-info"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card dashboard-card border-0 shadow h-100 py-2 bg-white">
                                <div class="card-body d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <div class="text-xs fw-bold text-warning text-uppercase mb-1">Active Enrollments</div>
                                        <div class="h4 mb-0 fw-bold text-dark"><?php echo $stats['enrollments']; ?></div>
                                    </div>
                                    <div class="ms-3">
                                        <i class="fas fa-users fa-2x text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->

                               <!-- Management Tools -->
                <div class="row mb-4">
                    <div class="col-lg-12">
                        <div class="card shadow">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-tools"></i> Management Tools
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <div class="card bg-primary text-white">
                                            <div class="card-body text-center">
                                                <i class="fas fa-users fa-2x mb-2"></i>
                                                <h6>User Management</h6>
                                                <a href="users.php" class="btn btn-light btn-sm">Manage</a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <div class="card bg-success text-white">
                                            <div class="card-body text-center">
                                                <i class="fas fa-book fa-2x mb-2"></i>
                                                <h6>Course Management</h6>
                                                <a href="courses.php" class="btn btn-light btn-sm">Manage</a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <div class="card bg-info text-white">
                                            <div class="card-body text-center">
                                                <i class="fas fa-chart-bar fa-2x mb-2"></i>
                                                <h6>Analytics</h6>
                                                <a href="analytics.php" class="btn btn-light btn-sm">View</a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <div class="card bg-warning text-white">
                                            <div class="card-body text-center">
                                                <i class="fas fa-cog fa-2x mb-2"></i>
                                                <h6>System Settings</h6>
                                                <a href="settings.php" class="btn btn-light btn-sm">Configure</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>


                <!-- Notification Form -->
                <div class="card mb-4">
                    <div class="card-header bg-gradient-primary text-white fw-bold"><i class="fas fa-bell me-2"></i>Send Notification</div>
                    <div class="card-body bg-light">
                        <form method="POST">
                            <div class="mb-3">
                                <label for="recipient" class="form-label">Recipient</label>
                                <select name="recipient" id="recipient" class="form-select">
                                    <option value="all">All Users</option>
                                    <option value="student">Students</option>
                                    <option value="instructor">Instructors</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="message" class="form-label">Content</label>
                                <textarea name="message" id="message" class="form-control" rows="4" required></textarea>
                            </div>
                            <button type="submit" name="send_notification" class="btn btn-primary">Send</button>
                        </form>
                    </div>
                </div>
                

                <!-- Pending Courses -->
                <?php if ($pending_courses): ?>
                    <div class="card mb-4">
                    <div class="card-header bg-warning text-dark fw-bold"><i class="fas fa-hourglass-half me-2"></i>Courses Pending Approval</div>
                    <div class="card-body bg-light">
                            <ul class="list-group">
                                <?php foreach ($pending_courses as $course): ?>
                                    <li class="list-group-item">
                                        <strong><?php echo htmlspecialchars($course['title']); ?></strong>
                                        <p class="text-muted small mb-1">Created by: <?php echo htmlspecialchars($course['instructor_name']); ?> on <?php echo formatDate($course['created_at']); ?></p>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                            <button type="submit" name="approve_course" class="btn btn-sm btn-success">Approve</button>
                                        </form>
                                        <a href="courses.php?id=<?php echo $course['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- User Management -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white fw-bold d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-users me-2"></i>User Management</span>
                        <div class="btn-group">
                            <a href="?user_filter=all" class="btn btn-sm btn-light <?php echo $user_filter === 'all' ? 'active' : ''; ?>">All</a>
                            <a href="?user_filter=student" class="btn btn-sm btn-light <?php echo $user_filter === 'student' ? 'active' : ''; ?>">Students</a>
                            <a href="?user_filter=instructor" class="btn btn-sm btn-light <?php echo $user_filter === 'instructor' ? 'active' : ''; ?>">Instructors</a>
                        </div>
                    </div>
                    <div class="card-body bg-light">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Created At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo ucfirst($user['role']); ?></td>
                                        <td><?php echo formatDate($user['created_at']); ?></td>
                                        <td>
                                            <a href="users.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                            <a href="users.php?edit=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-warning">Edit</a>
                                            <?php if ($user['role'] !== 'admin'): ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" name="delete_user" class="btn btn-sm btn-outline-danger">Delete</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Course Management -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white fw-bold"><i class="fas fa-book me-2"></i>Course Management</div>
                    <div class="card-body bg-light">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Course Code</th>
                                    <th>Instructor</th>
                                    <th>Status</th>
                                    <th>Created At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($courses as $course): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($course['title']); ?></td>
                                        <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                                        <td><?php echo htmlspecialchars($course['instructor_name']); ?></td>
                                        <td><?php echo ucfirst($course['status']); ?></td>
                                        <td><?php echo formatDate($course['created_at']); ?></td>
                                        <td>
                                            <a href="courses.php?id=<?php echo $course['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                            <a href="courses.php?edit=<?php echo $course['id']; ?>" class="btn btn-sm btn-outline-warning">Edit</a>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this course?');">
                                                <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                                <button type="submit" name="delete_course" class="btn btn-sm btn-outline-danger">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>


 
                <!-- Course Enrollments Chart -->
                <div class="row mb-4">
                    <div class="col-xl-12">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-chart-bar"></i> Course Enrollments
                                </h6>
                            </div>
                            <div class="card-body">
                                <canvas id="courseEnrollmentChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Monthly Registrations Chart -->
                <div class="row mb-4">
                    <div class="col-xl-12">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-chart-line"></i> Monthly Registrations
                                </h6>
                            </div>
                            <div class="card-body">
                                <canvas id="registrationChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="row mb-4">
                    <div class="col-lg-12">
                        <div class="card shadow">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-clock"></i> Recent Activity
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="timeline">
                                    <?php foreach ($recent_activities as $activity): ?>
                                        <div class="timeline-item">
                                            <div class="timeline-marker 
                                                <?php 
                                                switch($activity['type']) {
                                                    case 'user_registered': echo 'bg-success'; break;
                                                    case 'course_created': echo 'bg-primary'; break;
                                                    case 'enrollment': echo 'bg-info'; break;
                                                    default: echo 'bg-secondary';
                                                }
                                                ?>"></div>
                                            <div class="timeline-content">
                                                <h6 class="mb-1">
                                                    <?php 
                                                    switch($activity['type']) {
                                                    case 'user_registered': echo 'New User Registered'; break;
                                                    case 'course_created': echo 'Course Created'; break;
                                                    case 'enrollment': echo 'New Course Enrollment'; break;
                                                    default: echo 'Activity';
                                                    }
                                                    ?>
                                                </h6>
                                                <p class="text-muted small mb-1"><?php echo htmlspecialchars($activity['description']); ?></p>
                                                <small class="text-muted"><?php echo formatDate($activity['date']); ?></small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Course Enrollment Chart
        const ctx1 = document.getElementById('courseEnrollmentChart').getContext('2d');
        const courseEnrollmentChart = new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: [
                    <?php foreach ($course_enrollments as $course): ?>
                        '<?php echo addslashes($course['title']); ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Enrollments',
                    data: [
                        <?php foreach ($course_enrollments as $course): ?>
                            <?php echo $course['enrollments']; ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: '#4e73df',
                    borderColor: '#4e73df',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Monthly Registration Chart
        const ctx2 = document.getElementById('registrationChart').getContext('2d');
        const registrationChart = new Chart(ctx2, {
            type: 'line',
            data: {
                labels: [
                    <?php foreach ($monthly_registrations as $reg): ?>
                        '<?php echo $reg['month']; ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Registrations',
                    data: [
                        <?php foreach ($monthly_registrations as $reg): ?>
                            <?php echo $reg['count']; ?>,
                        <?php endforeach; ?>
                    ],
                    borderColor: '#1cc88a',
                    backgroundColor: 'rgba(28, 200, 138, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>