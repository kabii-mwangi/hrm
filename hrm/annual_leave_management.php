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
require_once 'annual_leave_award.php';

$conn = getConnection();

// Get current user from session
$user = [
    'first_name' => isset($_SESSION['user_name']) ? explode(' ', $_SESSION['user_name'])[0] : 'User',
    'last_name' => isset($_SESSION['user_name']) ? (explode(' ', $_SESSION['user_name'])[1] ?? '') : '',
    'role' => $_SESSION['user_role'] ?? 'guest',
    'id' => $_SESSION['user_id']
];

// Permission checking function
function hasPermission($required_role) {
    global $user;
    $role_hierarchy = [
        'super_admin' => 5,
        'hr_manager' => 4,
        'dept_head' => 3,
        'section_head' => 2,
        'manager' => 1,
        'employee' => 0
    ];

    $user_level = $role_hierarchy[$user['role']] ?? 0;
    $required_level = $role_hierarchy[$required_role] ?? 0;

    return $user_level >= $required_level;
}

// Check if user has permission to access this page
if (!hasPermission('hr_manager')) {
    header("Location: dashboard.php");
    exit();
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

function setFlashMessage($message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'award_annual_leave') {
        $force = isset($_POST['force']) && $_POST['force'] === '1';
        
        // Capture output
        ob_start();
        $result = checkAndAwardAnnualLeave($conn, $force);
        $output = ob_get_clean();
        
        if ($result['success']) {
            $message = "Annual leave awarded successfully! {$result['awarded_count']} employees received leave for financial year {$result['financial_year']}.";
            setFlashMessage($message, 'success');
        } else {
            $message = "Failed to award annual leave: " . ($result['error'] ?? 'Unknown error');
            setFlashMessage($message, 'danger');
        }
        
        // Store detailed output in session for display
        $_SESSION['award_output'] = $output;
        
        header("Location: annual_leave_management.php");
        exit();
    }
}

// Get award history
$historyQuery = "SELECT * FROM leave_award_log ORDER BY run_date DESC LIMIT 20";
$historyResult = $conn->query($historyQuery);
$awardHistory = $historyResult ? $historyResult->fetch_all(MYSQLI_ASSOC) : [];

// Get current financial year info
$currentFinancialYear = getCurrentFinancialYear();
$isBeginningOfYear = isBeginningOfFinancialYear();

// Get statistics for current financial year
$statsQuery = "SELECT 
    COUNT(DISTINCT lb.employee_id) as employees_with_leave,
    SUM(lb.annual_leave_entitled) as total_entitled,
    SUM(lb.annual_leave_used) as total_used,
    SUM(lb.annual_leave_balance) as total_balance
FROM leave_balances lb 
JOIN leave_types lt ON lb.leave_type_id = lt.id 
WHERE lb.financial_year = ? AND lt.name = 'Annual Leave'";

$stmt = $conn->prepare($statsQuery);
$stmt->bind_param("s", $currentFinancialYear);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Get permanent employees count
$permanentEmployeesQuery = "SELECT COUNT(*) as count FROM employees WHERE employment_type = 'permanent' AND employee_status = 'active'";
$permanentResult = $conn->query($permanentEmployeesQuery);
$permanentCount = $permanentResult->fetch_assoc()['count'];

// Get detailed leave balances for current year
$balancesQuery = "SELECT 
    e.employee_id, e.first_name, e.last_name, e.hire_date,
    lb.annual_leave_entitled, lb.annual_leave_used, lb.annual_leave_balance,
    d.name as department_name
FROM leave_balances lb
JOIN employees e ON lb.employee_id = e.id
JOIN leave_types lt ON lb.leave_type_id = lt.id
LEFT JOIN departments d ON e.department_id = d.id
WHERE lb.financial_year = ? AND lt.name = 'Annual Leave' AND e.employment_type = 'permanent'
ORDER BY e.first_name, e.last_name";

$stmt = $conn->prepare($balancesQuery);
$stmt->bind_param("s", $currentFinancialYear);
$stmt->execute();
$leaveBalances = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Annual Leave Management - HR Management System</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #007bff;
            text-align: center;
        }
        
        .stat-card.success {
            border-left-color: #28a745;
        }
        
        .stat-card.warning {
            border-left-color: #ffc107;
        }
        
        .stat-card.info {
            border-left-color: #17a2b8;
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #333;
        }
        
        .stat-label {
            color: #666;
            margin-top: 5px;
        }
        
        .award-section {
            background: #fff;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .award-button {
            background: #28a745;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-right: 10px;
        }
        
        .award-button:hover {
            background: #218838;
        }
        
        .award-button.danger {
            background: #dc3545;
        }
        
        .award-button.danger:hover {
            background: #c82333;
        }
        
        .output-box {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-top: 15px;
            font-family: monospace;
            white-space: pre-wrap;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: bold;
        }
        
        .badge-success {
            background-color: #28a745;
            color: white;
        }
        
        .badge-warning {
            background-color: #ffc107;
            color: #212529;
        }
        
        .badge-info {
            background-color: #17a2b8;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-brand">
                <h1>HR System</h1>
                <p>Management Portal</p>
            </div>
            <div class="nav">
                <ul>
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="employees.php">Employees</a></li>
                    <li><a href="departments.php">Departments</a></li>
                    <li><a href="users.php">Users</a></li>
                    <li><a href="leave_management.php">Leave Management</a></li>
                    <li><a href="annual_leave_management.php" class="active">Annual Leave Awards</a></li>
                </ul>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <h1>Annual Leave Award Management</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                    <a href="logout.php" class="btn btn-secondary btn-sm">Logout</a>
                </div>
            </div>

            <div class="content">
                <?php $flash = getFlashMessage(); if ($flash): ?>
                    <div class="alert alert-<?php echo $flash['type']; ?>">
                        <?php echo htmlspecialchars($flash['message']); ?>
                    </div>
                <?php endif; ?>

                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card info">
                        <div class="stat-number"><?php echo $currentFinancialYear; ?></div>
                        <div class="stat-label">Current Financial Year</div>
                    </div>
                    
                    <div class="stat-card success">
                        <div class="stat-number"><?php echo $permanentCount; ?></div>
                        <div class="stat-label">Permanent Employees</div>
                    </div>
                    
                    <div class="stat-card warning">
                        <div class="stat-number"><?php echo $stats['employees_with_leave'] ?? 0; ?></div>
                        <div class="stat-label">Employees with Leave Balance</div>
                    </div>
                    
                    <div class="stat-card info">
                        <div class="stat-number"><?php echo $stats['total_entitled'] ?? 0; ?></div>
                        <div class="stat-label">Total Days Entitled</div>
                    </div>
                </div>

                <!-- Award Section -->
                <div class="award-section">
                    <h3>Award Annual Leave</h3>
                    <p>Awards 30 days of annual leave to all permanently employed active employees for the current financial year.</p>
                    
                    <?php if ($isBeginningOfYear): ?>
                        <div class="alert alert-success">
                            <strong>Beginning of Financial Year Detected!</strong> 
                            It's the perfect time to award annual leave to employees.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <strong>Note:</strong> Annual leave is typically awarded at the beginning of the financial year (July). 
                            You can force the award process using the "Force Award" button.
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" onsubmit="return confirm('Are you sure you want to award annual leave? This will process all permanent employees.');">
                        <input type="hidden" name="action" value="award_annual_leave">
                        
                        <?php if ($isBeginningOfYear): ?>
                            <button type="submit" class="award-button">Award Annual Leave</button>
                        <?php endif; ?>
                        
                        <button type="submit" name="force" value="1" class="award-button danger" 
                                onclick="return confirm('Force award will process leave regardless of the date. Continue?');">
                            Force Award Annual Leave
                        </button>
                    </form>
                    
                    <?php if (isset($_SESSION['award_output'])): ?>
                        <div class="output-box"><?php echo htmlspecialchars($_SESSION['award_output']); ?></div>
                        <?php unset($_SESSION['award_output']); ?>
                    <?php endif; ?>
                </div>

                <!-- Award History -->
                <div class="card">
                    <div class="card-header">
                        <h3>Award History</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($awardHistory)): ?>
                            <p>No award history found.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Financial Year</th>
                                            <th>Date</th>
                                            <th>Employees Processed</th>
                                            <th>Employees Awarded</th>
                                            <th>Run By</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($awardHistory as $record): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($record['financial_year']); ?></td>
                                                <td><?php echo date('M d, Y H:i', strtotime($record['run_date'])); ?></td>
                                                <td><?php echo $record['employees_processed']; ?></td>
                                                <td>
                                                    <span class="badge badge-success">
                                                        <?php echo $record['employees_awarded']; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($record['run_by']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Current Year Leave Balances -->
                <div class="card" style="margin-top: 30px;">
                    <div class="card-header">
                        <h3>Leave Balances for <?php echo $currentFinancialYear; ?></h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($leaveBalances)): ?>
                            <p>No leave balances found for the current financial year. Consider awarding annual leave to employees.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Employee ID</th>
                                            <th>Name</th>
                                            <th>Department</th>
                                            <th>Hire Date</th>
                                            <th>Entitled</th>
                                            <th>Used</th>
                                            <th>Balance</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($leaveBalances as $balance): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($balance['employee_id']); ?></td>
                                                <td><?php echo htmlspecialchars($balance['first_name'] . ' ' . $balance['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($balance['department_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($balance['hire_date'])); ?></td>
                                                <td><?php echo $balance['annual_leave_entitled']; ?></td>
                                                <td><?php echo $balance['annual_leave_used']; ?></td>
                                                <td><?php echo $balance['annual_leave_balance']; ?></td>
                                                <td>
                                                    <?php 
                                                    $percentage = $balance['annual_leave_entitled'] > 0 ? 
                                                        ($balance['annual_leave_used'] / $balance['annual_leave_entitled']) * 100 : 0;
                                                    
                                                    if ($percentage < 25) {
                                                        echo '<span class="badge badge-success">Excellent</span>';
                                                    } elseif ($percentage < 75) {
                                                        echo '<span class="badge badge-warning">Good</span>';
                                                    } else {
                                                        echo '<span class="badge badge-info">High Usage</span>';
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>