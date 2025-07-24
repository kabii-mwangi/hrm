<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config.php';

// Get current user from session
$user = [
    'first_name' => isset($_SESSION['user_name']) ? explode(' ', $_SESSION['user_name'])[0] : 'User',
    'last_name' => isset($_SESSION['user_name']) ? (explode(' ', $_SESSION['user_name'])[1] ?? '') : '',
    'role' => $_SESSION['user_role'] ?? 'guest',
    'id' => $_SESSION['user_id']
];

// Permission check function
function hasPermission($requiredRole) {
    $userRole = $_SESSION['user_role'] ?? 'guest';
    
    // Permission hierarchy
    $roles = [
        'super_admin' => 3,
        'hr_manager' => 2,
        'dept_head' => 1,
        'employee' => 0
    ];
    
    $userLevel = $roles[$userRole] ?? 0;
    $requiredLevel = $roles[$requiredRole] ?? 0;
    
    return $userLevel >= $requiredLevel;
}

// Helper functions
function getEmployeeTypeBadge($type) {
    $badges = [
        'full_time' => 'badge-primary',
        'part_time' => 'badge-info',
        'contract' => 'badge-warning',
        'temporary' => 'badge-secondary',
        'officer' => 'badge-primary',
        'section_head' => 'badge-info',
        'manager' => 'badge-success'
    ];
    return $badges[$type] ?? 'badge-light';
}

function getEmployeeStatusBadge($status) {
    $badges = [
        'active' => 'badge-success',
        'on_leave' => 'badge-warning',
        'terminated' => 'badge-danger',
        'resigned' => 'badge-secondary'
    ];
    return $badges[$status] ?? 'badge-light';
}

function formatDate($date) {
    if (!$date) return 'N/A';
    return date('M d, Y', strtotime($date));
}

function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        return ['message' => $message, 'type' => $type];
    }
    return false;
}

// Get dashboard statistics
$conn = getConnection();

// Total employees - fixed column name
$result = $conn->query("SELECT COUNT(*) as count FROM employees WHERE employee_status = 'active'");
$totalEmployees = $result->fetch_assoc()['count'];

// Total departments
$result = $conn->query("SELECT COUNT(*) as count FROM departments");
$totalDepartments = $result->fetch_assoc()['count'];

// Total sections
$result = $conn->query("SELECT COUNT(*) as count FROM sections");
$totalSections = $result->fetch_assoc()['count'];

// Recent employees (last 30 days)
$result = $conn->query("SELECT COUNT(*) as count FROM employees WHERE hire_date >= (CURDATE() - INTERVAL 30 DAY)");
$recentHires = $result->fetch_assoc()['count'];

// Get recent employees for display
$result = $conn->query("
    SELECT e.*, 
           e.first_name,
           e.last_name,
           d.name as department_name, 
           s.name as section_name 
    FROM employees e 
    LEFT JOIN departments d ON e.department_id = d.id 
    LEFT JOIN sections s ON e.section_id = s.id 
    ORDER BY e.created_at DESC 
    LIMIT 5
");

$recentEmployees = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recentEmployees[] = $row;
    }
}

// Close connection
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - HR Management System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-brand">
                <h1>HR System</h1>
                <p>Management Portal</p>
            </div>
            <nav class="nav">
                <ul>
                    <li><a href="dashboard.php" class="active">Dashboard</a></li>
                    <li><a href="employees.php">Employees</a></li>
                    <?php if (hasPermission('hr_manager')): ?>
                    <li><a href="departments.php">Departments</a></li>
                    <?php endif; ?>
                    <?php if (hasPermission('super_admin')): ?>
                    <li><a href="users.php">Users</a></li>
                    <?php endif; ?>
                    <?php if (hasPermission('hr_manager')): ?>
                    <li><a href="reports.php">Reports</a></li>
                    <?php endif; ?>
                    <?php if (hasPermission('hr_manager')|| hasPermission('super_admin')||hasPermission('dept_head')||hasPermission('officer')): ?>
                    <li><a href="leave_management.php">Leave Management</a></li>
                    <?php endif; ?>
                    <?php if (hasPermission('hr_manager') || hasPermission('super_admin')): ?>
                    <li><a href="annual_leave_management.php">Annual Leave Awards</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <button class="sidebar-toggle">☰</button>
                <h1>HR Management Dashboard</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                    <span class="badge badge-info"><?php echo ucwords(str_replace('_', ' ', $user['role'])); ?></span>
                    <a href="logout.php" class="btn btn-secondary btn-sm">Logout</a>
                </div>
            </div>
            
            <div class="content">
                <?php $flash = getFlashMessage(); if ($flash): ?>
                    <div class="alert alert-<?php echo $flash['type']; ?>">
                        <?php echo htmlspecialchars($flash['message']); ?>
                    </div>
                <?php endif; ?>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3><?php echo $totalEmployees; ?></h3>
                        <p>Active Employees</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo $totalDepartments; ?></h3>
                        <p>Departments</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo $totalSections; ?></h3>
                        <p>Sections</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo $recentHires; ?></h3>
                        <p>New Hires (30 days)</p>
                    </div>
                </div>
                
                <div class="table-container">
                    <h3>Recent Employees</h3>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Employee ID</th>
                                <th>Name</th>
                                <th>Department</th>
                                <th>Section</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Hire Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentEmployees)): ?>
                                <tr>
                                    <td colspan="7" class="text-center">No employees found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentEmployees as $employee): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($employee['employee_id']); ?></td>
                                    <td><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($employee['department_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($employee['section_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge <?php echo getEmployeeTypeBadge($employee['employee_type'] ?? ''); ?>">
                                            <?php 
                                            $type = $employee['employee_type'] ?? '';
                                            echo $type ? ucwords(str_replace('_', ' ', $type)) : 'N/A'; 
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo getEmployeeStatusBadge($employee['employee_status'] ?? ''); ?>">
                                            <?php 
                                            $status = $employee['employee_status'] ?? '';
                                            echo $status ? ucwords($status) : 'N/A'; 
                                            ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatDate($employee['hire_date']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="action-buttons">
                    <a href="employees.php" class="btn btn-primary">View All Employees</a>
                    <?php if (hasPermission('hr_manager')): ?>
                        <a href="employees.php?action=add" class="btn btn-success">Add New Employee</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Enhance statistics cards with hover effects
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach(function(card) {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-3px)';
                    this.style.boxShadow = '0 6px 12px rgba(0,0,0,0.15)';
                    this.style.transition = 'all 0.3s ease';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = '';
                });
            });
            
            // Enhance table rows with hover effects
            const tableRows = document.querySelectorAll('.table tbody tr');
            tableRows.forEach(function(row) {
                row.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#f8f9fa';
                    this.style.transition = 'background-color 0.2s ease';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = '';
                });
            });
            
            // Add click-to-copy functionality for employee IDs
            const employeeIds = document.querySelectorAll('.table tbody tr td:first-child');
            employeeIds.forEach(function(cell) {
                cell.style.cursor = 'pointer';
                cell.title = 'Click to copy Employee ID';
                
                cell.addEventListener('click', function() {
                    const text = this.textContent.trim();
                    navigator.clipboard.writeText(text).then(function() {
                        // Show temporary feedback
                        const originalText = cell.textContent;
                        cell.textContent = '✓ Copied!';
                        cell.style.color = '#28a745';
                        
                        setTimeout(function() {
                            cell.textContent = originalText;
                            cell.style.color = '';
                        }, 1000);
                    }).catch(function() {
                        // Fallback for older browsers
                        const textArea = document.createElement('textarea');
                        textArea.value = text;
                        document.body.appendChild(textArea);
                        textArea.select();
                        document.execCommand('copy');
                        document.body.removeChild(textArea);
                        
                        const originalText = cell.textContent;
                        cell.textContent = '✓ Copied!';
                        cell.style.color = '#28a745';
                        
                        setTimeout(function() {
                            cell.textContent = originalText;
                            cell.style.color = '';
                        }, 1000);
                    });
                });
            });
            
            // Add real-time clock
            function updateClock() {
                const now = new Date();
                const timeString = now.toLocaleTimeString();
                const dateString = now.toLocaleDateString();
                
                // Find or create clock element
                let clockElement = document.getElementById('real-time-clock');
                if (!clockElement) {
                    clockElement = document.createElement('div');
                    clockElement.id = 'real-time-clock';
                    clockElement.style.position = 'fixed';
                    clockElement.style.top = '10px';
                    clockElement.style.right = '10px';
                    clockElement.style.background = 'rgba(255,255,255,0.9)';
                    clockElement.style.padding = '5px 10px';
                    clockElement.style.borderRadius = '5px';
                    clockElement.style.fontSize = '12px';
                    clockElement.style.color = '#666';
                    clockElement.style.zIndex = '1000';
                    clockElement.style.boxShadow = '0 2px 4px rgba(0,0,0,0.1)';
                    document.body.appendChild(clockElement);
                }
                
                clockElement.innerHTML = `${dateString}<br>${timeString}`;
            }
            
            // Update clock every second
            updateClock();
            setInterval(updateClock, 1000);
            
            // Add keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Alt+E - Go to employees
                if (e.altKey && e.key === 'e') {
                    e.preventDefault();
                    window.location.href = 'employees.php';
                }
                
                // Alt+L - Go to leave management
                if (e.altKey && e.key === 'l') {
                    e.preventDefault();
                    window.location.href = 'leave_management.php';
                }
                
                // Alt+A - Go to annual leave awards (if user has permission)
                if (e.altKey && e.key === 'a') {
                    const annualLeaveLink = document.querySelector('a[href="annual_leave_management.php"]');
                    if (annualLeaveLink) {
                        e.preventDefault();
                        window.location.href = 'annual_leave_management.php';
                    }
                }
                
                // F5 or Ctrl+R - Refresh
                if (e.key === 'F5' || (e.ctrlKey && e.key === 'r')) {
                    e.preventDefault();
                    window.location.reload();
                }
            });
            
            // Add welcome animation
            const header = document.querySelector('.header h1');
            if (header) {
                header.style.opacity = '0';
                header.style.transform = 'translateY(-20px)';
                header.style.transition = 'all 0.6s ease';
                
                setTimeout(function() {
                    header.style.opacity = '1';
                    header.style.transform = 'translateY(0)';
                }, 100);
            }
            
            // Animate stat cards on load
            const statCardsForAnimation = document.querySelectorAll('.stat-card');
            statCardsForAnimation.forEach(function(card, index) {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'all 0.6s ease';
                
                setTimeout(function() {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 200 + (index * 100));
            });
            
            // Add tooltips with keyboard shortcuts info
            const helpButton = document.createElement('button');
            helpButton.innerHTML = '?';
            helpButton.className = 'btn btn-outline-info btn-sm';
            helpButton.style.position = 'fixed';
            helpButton.style.bottom = '20px';
            helpButton.style.left = '20px';
            helpButton.style.borderRadius = '50%';
            helpButton.style.width = '35px';
            helpButton.style.height = '35px';
            helpButton.style.zIndex = '1000';
            helpButton.title = 'Keyboard Shortcuts';
            
            helpButton.addEventListener('click', function() {
                const shortcuts = [
                    'Alt + E: Go to Employees',
                    'Alt + L: Go to Leave Management',
                    'Alt + A: Go to Annual Leave Awards',
                    'F5 / Ctrl + R: Refresh page',
                    'Click Employee ID to copy'
                ];
                
                alert('Keyboard Shortcuts:\n\n' + shortcuts.join('\n'));
            });
            
            document.body.appendChild(helpButton);
        });
    </script>
</body>
</html>