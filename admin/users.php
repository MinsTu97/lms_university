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
    $where = "WHERE first_name LIKE :search OR last_name LIKE :search OR email LIKE :search";
    $params['search'] = "%$search%";
}

$query_count = "SELECT COUNT(*) as total FROM users $where";
$stmt = $db->prepare($query_count);
$stmt->execute($params);
$total_users = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_users / $items_per_page);

$query = "SELECT * FROM users $where ORDER BY id DESC LIMIT $items_per_page OFFSET $offset";
$stmt = $db->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$user = null;
if (isset($_GET['id']) || isset($_GET['edit'])) {
    $id = $_GET['id'] ?? $_GET['edit'];
    $query = "SELECT * FROM users WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute(['id' => $id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    if ($delete_id > 0) {
        $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
        $stmt->execute(['id' => $delete_id]);
        header('Location: users.php?msg=deleted');
        exit();
    }
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'student';
    $id = $_POST['id'] ?? null;
    if ($first_name && $last_name && $username && $email && $role) {
        // Check for duplicate username
        if ($id) {
            $stmt = $db->prepare("SELECT id FROM users WHERE username = :username AND id != :id");
            $stmt->execute(['username' => $username, 'id' => $id]);
            if ($stmt->fetch()) {
                $msg = 'Username already exists!';
            } else {
                $stmt = $db->prepare("UPDATE users SET first_name = :first_name, last_name = :last_name, username = :username, email = :email, role = :role WHERE id = :id");
                $stmt->execute([
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'username' => $username,
                    'email' => $email,
                    'role' => $role,
                    'id' => $id
                ]);
                $msg = 'Update successful!';
                header('Location: users.php?edit=' . $id . '&msg=updated');
                exit();
            }
        } else {
            $stmt = $db->prepare("SELECT id FROM users WHERE username = :username");
            $stmt->execute(['username' => $username]);
            if ($stmt->fetch()) {
                $msg = 'Username already exists!';
            } else {
                $password = password_hash('123456', PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (first_name, last_name, username, email, role, password) VALUES (:first_name, :last_name, :username, :email, :role, :password)");
                $stmt->execute([
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'username' => $username,
                    'email' => $email,
                    'role' => $role,
                    'password' => $password
                ]);
                $msg = 'Created successfully!';
                header('Location: users.php?msg=created');
                exit();
            }
        }
    } else {
        $msg = 'Please fill in all required fields!';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/admin_navbar.php'; ?>
    <div class="container mt-4">
        <h2>User Management</h2>
        <?php if (isset($_GET['msg'])): ?>
            <div class="alert alert-success">Action successful!</div>
        <?php endif; ?>
        <?php if ($msg): ?>
            <div class="alert alert-warning"><?php echo htmlspecialchars($msg); ?></div>
        <?php endif; ?>

        <!-- Add/Edit Form -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title"><?php echo $user ? (isset($_GET['edit']) ? 'Edit User' : 'View User') : 'Add User'; ?></h5>
                <form method="post" action="users.php<?php echo $user ? '?edit=' . $user['id'] : ''; ?>">
                    <?php if ($user): ?>
                        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                    <?php endif; ?>
                    <div class="row mb-3">
                        <div class="col">
                            <input type="text" name="first_name" class="form-control" placeholder="First Name" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required <?php echo isset($_GET['id']) ? 'readonly' : ''; ?>>
                        </div>
                        <div class="col">
                            <input type="text" name="last_name" class="form-control" placeholder="Last Name" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required <?php echo isset($_GET['id']) ? 'readonly' : ''; ?>>
                        </div>
                    </div>
                    <div class="mb-3">
                        <input type="text" name="username" class="form-control" placeholder="Username" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" required <?php echo isset($_GET['id']) ? 'readonly' : ''; ?>>
                    </div>
                    <div class="mb-3">
                        <input type="email" name="email" class="form-control" placeholder="Email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required <?php echo isset($_GET['id']) ? 'readonly' : ''; ?>>
                    </div>
                    <div class="mb-3">
                        <select name="role" class="form-select" required <?php echo isset($_GET['id']) ? 'disabled' : ''; ?>>
                            <option value="admin" <?php echo (isset($user['role']) && $user['role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                            <option value="instructor" <?php echo (isset($user['role']) && $user['role'] == 'instructor') ? 'selected' : ''; ?>>Instructor</option>
                            <option value="student" <?php echo (!isset($user['role']) || $user['role'] == 'student') ? 'selected' : ''; ?>>Student</option>
                        </select>
                    </div>
                    <?php if (!isset($_GET['id'])): ?>
                        <button type="submit" class="btn btn-success"><?php echo $user ? 'Update' : 'Add New'; ?></button>
                    <?php endif; ?>
                    <?php if ($user && isset($_GET['edit'])): ?>
                        <button type="submit" class="btn btn-primary">Update</button>
                    <?php endif; ?>
                    <?php if ($user && isset($_GET['id'])): ?>
                        <a href="users.php?edit=<?php echo $user['id']; ?>" class="btn btn-warning">Edit</a>
                    <?php endif; ?>
                    <a href="users.php" class="btn btn-secondary">Back</a>
                </form>
            </div>
        </div>

        <!-- User List -->
        <div class="card">
            <div class="card-body">
                <form class="row mb-3" method="get">
                    <div class="col-md-4">
                        <input type="text" name="search" class="form-control" placeholder="Search by name or email..." value="<?php echo htmlspecialchars($search); ?>">
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
                                <th>First Name</th>
                                <th>Last Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td><?php echo $u['id']; ?></td>
                                    <td><?php echo htmlspecialchars($u['first_name']); ?></td>
                                    <td><?php echo htmlspecialchars($u['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($u['username']); ?></td>
                                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($u['role'])); ?></td>
                                    <td>
                                        <a href="users.php?id=<?php echo $u['id']; ?>" class="btn btn-sm btn-info">View</a>
                                        <a href="users.php?edit=<?php echo $u['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                                        <a href="users.php?delete=<?php echo $u['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this user?');">Delete</a>
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