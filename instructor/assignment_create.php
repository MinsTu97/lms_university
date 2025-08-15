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


// Get instructor's courses for dropdown
$courses = [];
try {
    $stmt = $db->prepare("SELECT id, title FROM courses WHERE instructor_id = ? AND status = 'active'");
    $stmt->execute([$_SESSION['user_id']]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Handle delete
if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    if ($delete_id > 0) {
        $stmt = $db->prepare("DELETE FROM assignments WHERE id = :id AND created_by = :created_by");
        $stmt->execute(['id' => $delete_id, 'created_by' => $_SESSION['user_id']]);
        header('Location: assignment_create.php?msg=deleted');
        exit();
    }
}

// Handle edit fetch
$edit_assignment = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM assignments WHERE id = :id AND created_by = :created_by");
    $stmt->execute(['id' => $edit_id, 'created_by' => $_SESSION['user_id']]);
    $edit_assignment = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle add/edit assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $course_id = $_POST['course_id'] ?? '';
    $due_date = $_POST['due_date'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $edit_id = $_POST['edit_id'] ?? null;

    if ($title === '' || $course_id === '' || $due_date === '') {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            if ($edit_id) {
                // Update
                $query = "UPDATE assignments SET course_id = ?, title = ?, description = ?, due_date = ?, status = ? WHERE id = ? AND created_by = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    $course_id,
                    $title,
                    $description,
                    $due_date,
                    $status,
                    $edit_id,
                    $_SESSION['user_id']
                ]);
                header('Location: assignment_create.php?msg=updated');
                exit();
            } else {
                // Insert
                $query = "INSERT INTO assignments (course_id, title, description, due_date, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    $course_id,
                    $title,
                    $description,
                    $due_date,
                    $status,
                    $_SESSION['user_id']
                ]);
                header('Location: assignment_create.php?msg=added');
                exit();
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Fetch all assignments created by this instructor
$my_assignments = [];
try {
    $stmt = $db->prepare("SELECT a.*, c.title as course_title FROM assignments a JOIN courses c ON a.course_id = c.id WHERE a.created_by = ? ORDER BY a.due_date DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $my_assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Assignment</title>
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
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0">Create New Assignment</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <form method="POST">
                            <?php if ($edit_assignment): ?>
                                <input type="hidden" name="edit_id" value="<?php echo $edit_assignment['id']; ?>">
                            <?php endif; ?>
                            <div class="mb-3">
                                <label for="title" class="form-label">Assignment Title</label>
                                <input type="text" class="form-control" id="title" name="title" value="<?php echo $edit_assignment ? htmlspecialchars($edit_assignment['title']) : ''; ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="course_id" class="form-label">Course</label>
                                <select class="form-select" id="course_id" name="course_id" required>
                                    <option value="">Select Course</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo $course['id']; ?>" <?php if ($edit_assignment && $edit_assignment['course_id'] == $course['id']) echo 'selected'; ?>><?php echo htmlspecialchars($course['title']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="due_date" class="form-label">Due Date</label>
                                <input type="date" class="form-control" id="due_date" name="due_date" value="<?php echo $edit_assignment ? htmlspecialchars(substr($edit_assignment['due_date'],0,10)) : ''; ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?php echo $edit_assignment ? htmlspecialchars($edit_assignment['description']) : ''; ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="active" <?php if ($edit_assignment && $edit_assignment['status'] == 'active') echo 'selected'; ?>>Active</option>
                                    <option value="inactive" <?php if ($edit_assignment && $edit_assignment['status'] == 'inactive') echo 'selected'; ?>>Inactive</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-success"><?php echo $edit_assignment ? 'Update Assignment' : 'Create Assignment'; ?></button>
                            <?php if ($edit_assignment): ?>
                                <a href="assignment_create.php" class="btn btn-secondary">Cancel</a>
                            <?php else: ?>
                                <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                            <?php endif; ?>
                        </form>

                        <hr>
                        <h5>Your Assignments</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Course</th>
                                        <th>Due Date</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($my_assignments)): ?>
                                        <tr><td colspan="5" class="text-center text-muted">No assignments found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($my_assignments as $assignment): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($assignment['title']); ?></td>
                                                <td><?php echo htmlspecialchars($assignment['course_title']); ?></td>
                                                <td><?php echo htmlspecialchars($assignment['due_date']); ?></td>
                                                <td><span class="badge bg-<?php echo $assignment['status'] == 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($assignment['status']); ?></span></td>
                                                <td>
                                                    <a href="assignment_create.php?edit=<?php echo $assignment['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                                    <a href="assignment_create.php?delete=<?php echo $assignment['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this assignment?');">Delete</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
