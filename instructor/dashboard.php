<?php
require_once '../config/database.php';
requireLogin();

if (!hasRole('instructor')) {
    header('Location: ../auth/login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Initialize pagination and search parameters
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$items_per_page = 5;
$offset = ($page - 1) * $items_per_page;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';

// Sanitize search input
$search = htmlspecialchars(strip_tags($search));

// Get instructor's courses with search and filter
$where_clause = "WHERE c.instructor_id = ?";
$params = [$_SESSION['user_id']];
if (!empty($search)) {
    $where_clause .= " AND c.title LIKE ?";
    $params[] = "%$search%";
}
if ($filter_status !== 'all') {
    $where_clause .= " AND c.status = ?";
    $params[] = $filter_status;
}

try {
    // Get total courses for pagination
    $query = "SELECT COUNT(*) as total FROM courses c $where_clause";
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $total_courses = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_courses / $items_per_page);

    // Get courses (LIMIT and OFFSET must not be bound as parameters in MySQL/MariaDB)
    $query = "SELECT c.*, COUNT(e.id) as enrolled_students 
              FROM courses c 
              LEFT JOIN enrollments e ON c.id = e.course_id AND e.status = 'enrolled'
              $where_clause 
              GROUP BY c.id 
              LIMIT $items_per_page OFFSET $offset";
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recent assignments
    $query = "SELECT a.*, c.title as course_title, COUNT(s.id) as submissions
              FROM assignments a
              JOIN courses c ON a.course_id = c.id
              LEFT JOIN assignment_submissions s ON a.id = s.assignment_id
              WHERE c.instructor_id = ?
              GROUP BY a.id
              ORDER BY a.created_at DESC LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $recent_assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get pending grading count
    $query = "SELECT COUNT(*) as pending_count
              FROM assignment_submissions s
              JOIN assignments a ON s.assignment_id = a.id
              JOIN courses c ON a.course_id = c.id
              WHERE c.instructor_id = ? AND s.grade IS NULL";
    $stmt = $db->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $pending_grading = $stmt->fetch(PDO::FETCH_ASSOC)['pending_count'];

    // Get total students
    $query = "SELECT COUNT(DISTINCT e.student_id) as total_students
              FROM enrollments e
              JOIN courses c ON e.course_id = c.id
              WHERE c.instructor_id = ? AND e.status = 'enrolled'";
    $stmt = $db->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $total_students = $stmt->fetch(PDO::FETCH_ASSOC)['total_students'];
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
    $courses = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instructor Dashboard - University LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/dashboard.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        /* Minimal, clean palette */
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
        body {
            background: #eaf1fb;
        }
        .card {
            border-radius: 0.75rem;
            box-shadow: 0 2px 12px rgba(80, 80, 120, 0.07);
            border: none;
            background: #fff;
        }
        .card-header {
            border-radius: 0.75rem 0.75rem 0 0;
            background: #f5f7fa !important;
            color: #222 !important;
            box-shadow: none;
        }
        .card-header h6, .card-header h4, .card-header .m-0 {
            color: #111 !important;
        }
        .card-body {
            background: #fff;
        }
        .btn-primary, .btn-success, .btn-info, .btn-warning {
            border: none;
            box-shadow: 0 2px 8px rgba(80, 80, 120, 0.07);
            font-weight: 500;
            transition: background 0.12s, box-shadow 0.12s;
        }
        .btn-primary {
            background: #2d5be3;
        }
        .btn-success {
            background: #1cc88a;
        }
        .btn-info {
            background: #36b9cc;
        }
        .btn-warning {
            background: #f6c23e;
            color: #fff;
        }
        .btn-primary:hover, .btn-success:hover, .btn-info:hover, .btn-warning:hover {
            filter: brightness(1.08);
            box-shadow: 0 4px 16px rgba(80, 80, 120, 0.13);
        }
        .badge.bg-info {
            background: #36b9cc;
            color: #fff;
        }
        .badge.bg-success {
            background: #1cc88a;
            color: #fff;
        }
        .badge.bg-danger {
            background: #e74a3b;
            color: #fff;
        }
        .badge.bg-warning {
            background: #f6c23e;
            color: #fff;
        }
        .badge.bg-secondary {
            background: #858796;
            color: #fff;
        }
        .table thead th {
            background: #f5f7fa;
            color: #2d5be3;
            font-weight: 600;
        }
        .table-bordered {
            border-radius: 0.5rem;
            overflow: hidden;
        }
        .search-container { margin-bottom: 20px; }
        .pagination { justify-content: center; }
        .error-message { color: #e74a3b; margin-bottom: 15px; font-weight: 500; }
        /* Responsive tweaks */
        @media (max-width: 768px) {
            .table-responsive { font-size: 0.9rem; }
            .btn-group .btn { font-size: 0.8rem; padding: 5px; }
        }
    </style>
</head>
<body>
    <?php include '../includes/instructor_navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/instructor_sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-chalkboard-teacher"></i> Instructor Dashboard
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="course_create.php" class="btn btn-sm btn-primary">
                                <i class="fas fa-plus"></i> New Course
                            </a>
                            <a href="assignment_create.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-tasks"></i> New Assignment
                            </a>
                            <a href="#" class="btn btn-sm btn-outline-success" id="export-courses">
                                <i class="fas fa-download"></i> Export Courses
                            </a>
                        </div>
                    </div>
                </div>

                <?php if (isset($error_message)): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>



                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Active Courses</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($courses); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-book fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Total Students</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_students; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-user-graduate fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Assignments Created</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($recent_assignments); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Pending Grading</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $pending_grading; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="row">
                    <!-- My Courses -->
                    <div class="col-lg-8 mb-4" id="my-courses">
                        <div class="card shadow">
                            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-book"></i> My Courses
                                </h6>
                                <a href="course_create.php" class="btn btn-sm btn-primary">
                                    <i class="fas fa-plus"></i> Add Course
                                </a>
                            </div>
                            <div class="card-body">
                                <!-- Search and Filter -->
                                <div class="search-container">
                                    <form method="GET" class="d-flex">
                                        <input type="text" name="search" class="form-control me-2" placeholder="Search courses..." value="<?php echo htmlspecialchars($search); ?>">
                                        <select name="status" class="form-select me-2" style="width: 150px;">
                                            <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Status</option>
                                            <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo $filter_status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                                    </form>
                                </div>

                                <?php if (empty($courses)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-book fa-3x text-gray-300 mb-3"></i>
                                        <p class="text-muted">No courses found.</p>
                                        <a href="course_create.php" class="btn btn-primary">Create Your First Course</a>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>Course</th>
                                                    <th>Code</th>
                                                    <th>Students</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($courses as $course): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($course['title']); ?></strong>
                                                            <br>
                                                            <small class="text-muted"><?php echo htmlspecialchars($course['semester'] . ' ' . $course['year']); ?></small>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                                                        <td>
                                                            <span class="badge bg-info"><?php echo $course['enrolled_students']; ?></span>
                                                        </td>
                                                        <td>
                                                            <span class="badge <?php echo $course['status'] == 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                                                <?php echo ucfirst($course['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group" role="group">
                                                                <a href="course_view.php?id=<?php echo $course['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                                    <i class="fas fa-eye"></i>
                                                                </a>
                                                                <a href="course_edit.php?id=<?php echo $course['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                                                    <i class="fas fa-edit"></i>
                                                                </a>
                                                                <a href="course_analytics.php?id=<?php echo $course['id']; ?>" class="btn btn-sm btn-outline-info">
                                                                    <i class="fas fa-chart-bar"></i>
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- Pagination -->
                                    <nav aria-label="Page navigation">
                                        <ul class="pagination">
                                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $filter_status; ?>">Previous</a>
                                            </li>
                                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $filter_status; ?>"><?php echo $i; ?></a>
                                                </li>
                                            <?php endfor; ?>
                                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $filter_status; ?>">Next</a>
                                            </li>
                                        </ul>
                                    </nav>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions & Stats -->
                    <div class="col-lg-4 mb-4" id="quick-actions">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-bolt"></i> Quick Actions
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="course_create.php" class="btn btn-primary">
                                        <i class="fas fa-plus"></i> Create New Course
                                    </a>
                                    <a href="assignment_create.php" class="btn btn-success">
                                        <i class="fas fa-tasks"></i> Create Assignment
                                    </a>
                                    <a href="quiz_create.php" class="btn btn-info">
                                        <i class="fas fa-question-circle"></i> Create Quiz
                                    </a>
                                    <a href="grading.php" class="btn btn-warning">
                                        <i class="fas fa-clipboard-check"></i> Grade Submissions
                                        <?php if ($pending_grading > 0): ?>
                                            <span class="badge bg-danger"><?php echo $pending_grading; ?></span>
                                        <?php endif; ?>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Course Enrollment Chart -->
                        <div class="card shadow">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-chart-pie"></i> Course Enrollment
                                </h6>
                            </div>
                            <div class="card-body">
                                <canvas id="enrollmentChart" width="100" height="100"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Assignments -->
                <?php if (!empty($recent_assignments)): ?>
                <div class="row" id="assignments">
                    <div class="col-12">
                        <div class="card shadow">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-clipboard-list"></i> Recent Assignments
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Assignment</th>
                                                <th>Course</th>
                                                <th>Due Date</th>
                                                <th>Submissions</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_assignments as $assignment): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($assignment['title']); ?></td>
                                                    <td><?php echo htmlspecialchars($assignment['course_title']); ?></td>
                                                    <td><?php echo formatDate($assignment['due_date']); ?></td>
                                                    <td>
                                                        <span class="badge bg-info"><?php echo $assignment['submissions']; ?></span>
                                                    </td>
                                                    <td>
                                                        <select class="assignment-status" data-id="<?php echo $assignment['id']; ?>">
                                                            <option value="active" <?php echo $assignment['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                                            <option value="inactive" <?php echo $assignment['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <a href="assignment_view.php?id=<?php echo $assignment['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                            <a href="assignment_grade.php?id=<?php echo $assignment['id']; ?>" class="btn btn-sm btn-outline-success">
                                                                <i class="fas fa-clipboard-check"></i>
                                                            </a>
                                                        </div>
                                                    </td>
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
    <script>
        // Course Enrollment Chart
        const ctx = document.getElementById('enrollmentChart').getContext('2d');
        const enrollmentChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: [
                    <?php foreach ($courses as $course): ?>
                        '<?php echo addslashes($course['title']); ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    data: [
                        <?php foreach ($courses as $course): ?>
                            <?php echo $course['enrolled_students']; ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: [
                        '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b',
                        '#858796', '#5a5c69', '#6f42c1', '#e83e8c', '#fd7e14'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Export courses to CSV
        document.getElementById('export-courses').addEventListener('click', function(e) {
            e.preventDefault();
            const courses = <?php echo json_encode($courses); ?>;
            let csvContent = "data:text/csv;charset=utf-8,";
            csvContent += "Title,Code,Semester,Year,Students,Status\n";
            courses.forEach(course => {
                csvContent += `"${course.title.replace(/"/g, '""')}","${course.course_code}","${course.semester}","${course.year}",${course.enrolled_students},${course.status}\n`;
            });
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "courses_export.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });

        // AJAX for assignment status update
        $('.assignment-status').on('change', function() {
            const assignmentId = $(this).data('id');
            const status = $(this).val();
            $.ajax({
                url: 'update_assignment_status.php',
                method: 'POST',
                data: { id: assignmentId, status: status },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Optionally show a success message
                        // alert('Assignment status updated successfully.');
                    } else {
                        alert(response.error || 'Failed to update status.');
                    }
                },
                error: function(xhr) {
                    let msg = 'An error occurred.';
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        msg = xhr.responseJSON.error;
                    }
                    alert(msg);
                }
            });
           }); 