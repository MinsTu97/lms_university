<nav class="navbar navbar-expand-lg navbar-dark bg-success">
    <div class="container-fluid">
        <a class="navbar-brand" href="../instructor/dashboard.php">
            <i class="fas fa-chalkboard-teacher"></i> University LMS - Instructor
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="../instructor/dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../instructor/dashboard.php#my-courses">
                        <i class="fas fa-book"></i> My Courses
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../instructor/dashboard.php#assignments">
                        <i class="fas fa-tasks"></i> Assignments
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../instructor/grading.php">
                        <i class="fas fa-clipboard-check"></i> Grading
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../instructor/analytics.php">
                        <i class="fas fa-chart-bar"></i> Analytics
                    </a>
                </li>
            </ul>
            
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user"></i> Prof. <?php echo $_SESSION['last_name']; ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="../profile.php"><i class="fas fa-user-edit"></i> Profile</a></li>
                        <li><a class="dropdown-item" href="../settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>