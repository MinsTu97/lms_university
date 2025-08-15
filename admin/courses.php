<?php
require_once '../config/database.php';
requireLogin();
if (!hasRole('admin')) {
    header('Location: ../auth/login.php');
    exit();
}
$database = new Database();
$db = $database->getConnection();

// Handle search and pagination
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

$where = '';
$params = [];
if ($search !== '') {
    $where = "WHERE c.title LIKE :search OR c.course_code LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search";
    $params['search'] = "%$search%";
}

// Count total courses
$query_count = "SELECT COUNT(*) as total FROM courses c JOIN users u ON c.instructor_id = u.id $where";
$stmt = $db->prepare($query_count);
$stmt->execute($params);
$total_courses = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_courses / $items_per_page);

// Get course list for current page
$query = "SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) as instructor_name FROM courses c JOIN users u ON c.instructor_id = u.id $where ORDER BY c.id DESC LIMIT $items_per_page OFFSET $offset";
$stmt = $db->prepare($query);
$stmt->execute($params);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle view/edit course
$course = null;
if (isset($_GET['id']) || isset($_GET['edit'])) {
    $id = $_GET['id'] ?? $_GET['edit'];
    $query = "SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) as instructor_name FROM courses c JOIN users u ON c.instructor_id = u.id WHERE c.id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute(['id' => $id]);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle course deletion
if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    if ($delete_id > 0) {
        $stmt = $db->prepare("DELETE FROM courses WHERE id = :id");
        $stmt->execute(['id' => $delete_id]);
        header('Location: courses.php?msg=deleted');
        exit();
    }
}

// Handle add/edit course and file upload
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $course_code = trim($_POST['course_code'] ?? '');
    $semester = trim($_POST['semester'] ?? '');
    $year = trim($_POST['year'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $instructor_id = $_POST['instructor_id'] ?? '';
    $id = $_POST['id'] ?? null;
    $file_name = null;
    // Handle file upload if present
    if (isset($_FILES['course_file']) && $_FILES['course_file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../assets/course_files/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $original_name = basename($_FILES['course_file']['name']);
        $ext = pathinfo($original_name, PATHINFO_EXTENSION);
        $file_name = uniqid('course_') . '.' . $ext;
        $target_path = $upload_dir . $file_name;
        if (move_uploaded_file($_FILES['course_file']['tmp_name'], $target_path)) {
            // OK
        } else {
            $msg = 'File upload error!';
        }
    }
    // Validation: required fields
    if ($title && $course_code && $semester && $year && $instructor_id) {
        // Check for duplicate course_code
        if ($id) {
            // Edit: check if another course has this code
            $stmt = $db->prepare("SELECT id FROM courses WHERE course_code = :course_code AND id != :id");
            $stmt->execute(['course_code' => $course_code, 'id' => $id]);
            if ($stmt->fetch()) {
                $msg = 'Course code already exists!';
            } else {
                $update_sql = "UPDATE courses SET title = :title, course_code = :course_code, semester = :semester, year = :year, status = :status, instructor_id = :instructor_id";
                $params_update = [
                    'title' => $title,
                    'course_code' => $course_code,
                    'semester' => $semester,
                    'year' => $year,
                    'status' => $status,
                    'instructor_id' => $instructor_id,
                    'id' => $id
                ];
                if ($file_name) {
                    $update_sql .= ", course_file = :course_file";
                    $params_update['course_file'] = $file_name;
                }
                $update_sql .= " WHERE id = :id";
                $stmt = $db->prepare($update_sql);
                $stmt->execute($params_update);
                $msg = 'Update successful!';
                header('Location: courses.php?edit=' . $id . '&msg=updated');
                exit();
            }
        } else {
            // Add new: check if course_code exists
            $stmt = $db->prepare("SELECT id FROM courses WHERE course_code = :course_code");
            $stmt->execute(['course_code' => $course_code]);
            if ($stmt->fetch()) {
                $msg = 'Course code already exists!';
            } else {
                $insert_sql = "INSERT INTO courses (title, course_code, semester, year, status, instructor_id";
                $insert_vals = ":title, :course_code, :semester, :year, :status, :instructor_id";
                $params_insert = [
                    'title' => $title,
                    'course_code' => $course_code,
                    'semester' => $semester,
                    'year' => $year,
                    'status' => $status,
                    'instructor_id' => $instructor_id
                ];
                if ($file_name) {
                    $insert_sql .= ", course_file";
                    $insert_vals .= ", :course_file";
                    $params_insert['course_file'] = $file_name;
                }
                $insert_sql .= ") VALUES ($insert_vals)";
                $stmt = $db->prepare($insert_sql);
                $stmt->execute($params_insert);
                $msg = 'Created successfully!';
                header('Location: courses.php?msg=created');
                exit();
            }
        }
    } else {
        $msg = 'Please enter all required information!';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Course Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/admin_navbar.php'; ?>
    <div class="container mt-4">
        <h2>Course Management</h2>
        <?php if (isset($_GET['msg'])): ?>
            <div class="alert alert-success">Operation successful!</div>
        <?php endif; ?>
        <?php if ($msg): ?>
            <div class="alert alert-warning"><?php echo htmlspecialchars($msg); ?></div>
        <?php endif; ?>

        <!-- Add/Edit Form -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title"><?php echo $course ? (isset($_GET['edit']) ? 'Edit Course' : 'View Course') : 'Add Course'; ?></h5>
                <form method="post" enctype="multipart/form-data" action="courses.php<?php echo $course ? '?edit=' . $course['id'] : ''; ?>">
                    <?php if ($course): ?>
                        <input type="hidden" name="id" value="<?php echo $course['id']; ?>">
                    <?php endif; ?>
                    <div class="row mb-3">
                        <div class="col">
                            <input type="text" name="title" class="form-control" placeholder="Course Title" value="<?php echo htmlspecialchars($course['title'] ?? ''); ?>" required <?php echo isset($_GET['id']) ? 'readonly' : ''; ?>>
                        </div>
                        <div class="col">
                            <input type="text" name="course_code" class="form-control" placeholder="Course Code" value="<?php echo htmlspecialchars($course['course_code'] ?? ''); ?>" required <?php echo isset($_GET['id']) ? 'readonly' : ''; ?>>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col">
                            <input type="text" name="semester" class="form-control" placeholder="Semester" value="<?php echo htmlspecialchars($course['semester'] ?? ''); ?>" required <?php echo isset($_GET['id']) ? 'readonly' : ''; ?>>
                        </div>
                        <div class="col">
                            <input type="number" name="year" class="form-control" placeholder="Year" value="<?php echo htmlspecialchars($course['year'] ?? ''); ?>" required <?php echo isset($_GET['id']) ? 'readonly' : ''; ?>>
                        </div>
                    </div>
                    <div class="mb-3">
                        <select name="status" class="form-select" required <?php echo isset($_GET['id']) ? 'disabled' : ''; ?> >
                            <option value="active" <?php echo (isset($course['status']) && $course['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo (isset($course['status']) && $course['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <?php
                        // Lấy danh sách giảng viên
                        $instructors = $db->query("SELECT id, first_name, last_name FROM users WHERE role = 'instructor'")->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                        <select name="instructor_id" class="form-select" required <?php echo isset($_GET['id']) ? 'disabled' : ''; ?> >
                            <option value="">Select Instructor</option>
                            <?php foreach ($instructors as $ins): ?>
                                <option value="<?php echo $ins['id']; ?>" <?php echo (isset($course['instructor_id']) && $course['instructor_id'] == $ins['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($ins['first_name'] . ' ' . $ins['last_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="course_file" class="form-label">Document/Syllabus (optional, pdf/doc/xls/ppt...)</label>
                        <input type="file" name="course_file" id="course_file" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar">
                        <?php if (!empty($course['course_file'])): ?>
                            <a href="../assets/course_files/<?php echo htmlspecialchars($course['course_file']); ?>" target="_blank">Download current file</a>
                        <?php endif; ?>
                    </div>
                    <?php if (!isset($_GET['id'])): ?>
                        <button type="submit" class="btn btn-success"><?php echo $course ? 'Update' : 'Create'; ?></button>
                    <?php endif; ?>
                    <?php if ($course && isset($_GET['edit'])): ?>
                        <button type="submit" class="btn btn-primary">Update</button>
                    <?php endif; ?>
                    <?php if ($course && isset($_GET['id'])): ?>
                        <a href="courses.php?edit=<?php echo $course['id']; ?>" class="btn btn-warning">Edit</a>
                    <?php endif; ?>
                    <a href="courses.php" class="btn btn-secondary">Back</a>
                </form>
            </div>
        </div>

        <!-- Course List -->
        <div class="card">
            <div class="card-body">
                <form class="row mb-3" method="get">
                    <div class="col-md-4">
                        <input type="text" name="search" class="form-control" placeholder="Search by title, code, or instructor..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary">Search</button>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Course Title</th>
                                <th>Code</th>
                                <th>Semester</th>
                                <th>Year</th>
                                <th>Status</th>
                                <th>Instructor</th>
                                <th>Document</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courses as $c): ?>
                                <tr>
                                    <td><?php echo $c['id']; ?></td>
                                    <td><?php echo htmlspecialchars($c['title']); ?></td>
                                    <td><?php echo htmlspecialchars($c['course_code']); ?></td>
                                    <td><?php echo htmlspecialchars($c['semester']); ?></td>
                                    <td><?php echo htmlspecialchars($c['year']); ?></td>
                                    <td><?php echo $c['status'] == 'active' ? '<span class=\'badge bg-success\'>Active</span>' : '<span class=\'badge bg-secondary\'>Inactive</span>'; ?></td>
                                    <td><?php echo htmlspecialchars($c['instructor_name']); ?></td>
                                    <td>
                                        <?php if (!empty($c['course_file'])): ?>
                                            <a href="../assets/course_files/<?php echo htmlspecialchars($c['course_file']); ?>" target="_blank">Download</a>
                                        <?php else: ?>
                                            <span class="text-muted">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="courses.php?id=<?php echo $c['id']; ?>" class="btn btn-sm btn-info">View</a>
                                        <a href="courses.php?edit=<?php echo $c['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                                        <a href="courses.php?delete=<?php echo $c['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this course?');">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <nav>
                    <ul class="pagination">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</body>
</html>