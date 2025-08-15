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
$success = false;
$edit_course = null;

// Handle delete
if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    if ($delete_id > 0) {
        $stmt = $db->prepare("DELETE FROM courses WHERE id = :id AND instructor_id = :instructor_id");
        $stmt->execute(['id' => $delete_id, 'instructor_id' => $_SESSION['user_id']]);
        header('Location: course_create.php?msg=deleted');
        exit();
    }
}

// Handle edit fetch
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM courses WHERE id = :id AND instructor_id = :instructor_id");
    $stmt->execute(['id' => $edit_id, 'instructor_id' => $_SESSION['user_id']]);
    $edit_course = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $course_code = trim($_POST['course_code'] ?? '');
    $semester = trim($_POST['semester'] ?? '');
    $year = trim($_POST['year'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $edit_id = $_POST['edit_id'] ?? null;

    if ($title === '' || $course_code === '' || $semester === '' || $year === '') {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            if ($edit_id) {
                // Update
                $query = "UPDATE courses SET title = ?, course_code = ?, semester = ?, year = ?, status = ? WHERE id = ? AND instructor_id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    $title,
                    $course_code,
                    $semester,
                    $year,
                    $status,
                    $edit_id,
                    $_SESSION['user_id']
                ]);
                $success = true;
                header('Location: course_create.php?msg=updated');
                exit();
            } else {
                // Create
                $query = "INSERT INTO courses (instructor_id, title, course_code, semester, year, status) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    $_SESSION['user_id'],
                    $title,
                    $course_code,
                    $semester,
                    $year,
                    $status
                ]);
                $success = true;
                header('Location: course_create.php?msg=created');
                exit();
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = 'Course code already exists!';
            } else {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Fetch instructor's courses
$stmt = $db->prepare("SELECT * FROM courses WHERE instructor_id = :instructor_id ORDER BY id DESC");
$stmt->execute(['instructor_id' => $_SESSION['user_id']]);
$my_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Course</title>
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
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Create New Course</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <form method="POST">
                            <?php if ($edit_course): ?>
                                <input type="hidden" name="edit_id" value="<?php echo $edit_course['id']; ?>">
                            <?php endif; ?>
                            <div class="mb-3">
                                <label for="title" class="form-label">Course Title</label>
                                <input type="text" class="form-control" id="title" name="title" required value="<?php echo htmlspecialchars($edit_course['title'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="course_code" class="form-label">Course Code</label>
                                <input type="text" class="form-control" id="course_code" name="course_code" required value="<?php echo htmlspecialchars($edit_course['course_code'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="semester" class="form-label">Semester</label>
                                <input type="text" class="form-control" id="semester" name="semester" required value="<?php echo htmlspecialchars($edit_course['semester'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="year" class="form-label">Year</label>
                                <input type="number" class="form-control" id="year" name="year" min="2000" max="2100" required value="<?php echo htmlspecialchars($edit_course['year'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="active" <?php echo (isset($edit_course['status']) && $edit_course['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo (isset($edit_course['status']) && $edit_course['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-success"><?php echo $edit_course ? 'Update Course' : 'Create Course'; ?></button>
                            <a href="course_create.php" class="btn btn-secondary">Cancel</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- My Courses List (separate section) -->
        <div class="row justify-content-center mt-5">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">My Courses</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Title</th>
                                        <th>Code</th>
                                        <th>Semester</th>
                                        <th>Year</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($my_courses as $c): ?>
                                        <tr>
                                            <td><?php echo $c['id']; ?></td>
                                            <td><?php echo htmlspecialchars($c['title']); ?></td>
                                            <td><?php echo htmlspecialchars($c['course_code']); ?></td>
                                            <td><?php echo htmlspecialchars($c['semester']); ?></td>
                                            <td><?php echo htmlspecialchars($c['year']); ?></td>
                                            <td><?php echo ucfirst($c['status']); ?></td>
                                            <td>
                                                <a href="course_create.php?edit=<?php echo $c['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                                                <a href="course_create.php?delete=<?php echo $c['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this course?');">Delete</a>
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
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
