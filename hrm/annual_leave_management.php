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
require_once 'annual_leave_award_new.php';

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
    
    if ($action === 'start_new_financial_year') {
        $financial_year = sanitizeInput($_POST['financial_year'] ?? '');
        
        if (empty($financial_year)) {
            setFlashMessage("Please enter a valid financial year (e.g., 2024-2025)", 'danger');
        } else {
            try {
                $result = startNewFinancialYear($conn, $financial_year, $user['id']);
                
                if ($result['success']) {
                    $message = "New financial year {$financial_year} started successfully! {$result['awarded_count']} employees received 30 days of annual leave.";
                    setFlashMessage($message, 'success');
                } else {
                    setFlashMessage($result['message'], 'danger');
                }
            } catch (Exception $e) {
                setFlashMessage("Error starting new financial year: " . $e->getMessage(), 'danger');
            }
        }
        
        header("Location: annual_leave_management.php");
        exit();
    }
}

// Get available financial years
$availableYears = getAvailableFinancialYears($conn);

// Get current financial year info
$currentFinancialYear = getCurrentFinancialYear();

// Get selected financial year for viewing (default to current)
$selectedYear = $_GET['year'] ?? $currentFinancialYear;

// Get statistics for selected financial year
$stats = getFinancialYearStats($conn, $selectedYear);

// Get permanent employees count
$permanentEmployeesQuery = "SELECT COUNT(*) as count FROM employees WHERE employment_type = 'permanent' AND employee_status = 'active'";
$permanentResult = $conn->query($permanentEmployeesQuery);
$permanentCount = $permanentResult->fetch_assoc()['count'];

// Get detailed leave balances for selected year
$leaveBalances = getLeaveBalancesForYear($conn, $selectedYear);

// Get award history for selected year
$awardHistory = getAwardHistoryForYear($conn, $selectedYear);
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

                <!-- Financial Year Filter -->
                <div class="filter-section">
                    <h3>Financial Year Selection</h3>
                    <form method="GET" style="display: inline-block; margin-right: 20px;">
                        <select name="year" onchange="this.form.submit()" class="form-control" style="display: inline-block; width: auto;">
                            <?php foreach ($availableYears as $year): ?>
                                <option value="<?php echo $year; ?>" <?php echo ($year === $selectedYear) ? 'selected' : ''; ?>>
                                    <?php echo $year; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <span>Viewing data for: <strong><?php echo $selectedYear; ?></strong></span>
                </div>

                <!-- Start New Financial Year Section -->
                <div class="award-section">
                    <h3>Start New Financial Year</h3>
                    <p>Manually start a new financial year and award 30 days of annual leave to all permanently employed active employees.</p>
                    
                    <div class="alert alert-info">
                        <strong>Instructions:</strong> Enter the financial year in YYYY-YYYY format (e.g., 2024-2025). 
                        This will create leave balances for all permanent employees for that year.
                    </div>
                    
                    <form method="POST" onsubmit="return confirm('Are you sure you want to start this new financial year? This will award leave to all permanent employees.');">
                        <input type="hidden" name="action" value="start_new_financial_year">
                        
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label for="financial_year">Financial Year:</label>
                            <input type="text" id="financial_year" name="financial_year" 
                                   placeholder="e.g., 2024-2025" 
                                   pattern="^\d{4}-\d{4}$" 
                                   title="Format: YYYY-YYYY (e.g., 2024-2025)"
                                   class="form-control" 
                                   style="width: 200px; display: inline-block; margin-left: 10px;" 
                                   required>
                        </div>
                        
                        <button type="submit" class="award-button">
                            Start New Financial Year & Award Leave
                        </button>
                    </form>
                </div>

                <!-- Award History -->
                <div class="card">
                    <div class="card-header">
                        <h3>Award History for <?php echo $selectedYear; ?></h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($awardHistory)): ?>
                            <p>No award history found for <?php echo $selectedYear; ?>.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Employee ID</th>
                                            <th>Employee Name</th>
                                            <th>Days Awarded</th>
                                            <th>Award Type</th>
                                            <th>Date Awarded</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($awardHistory as $record): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($record['emp_id']); ?></td>
                                                <td><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></td>
                                                <td>
                                                    <span class="badge badge-success">
                                                        <?php echo $record['days_awarded']; ?> days
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $record['award_type'] === 'full' ? 'badge-success' : 'badge-warning'; ?>">
                                                        <?php echo ucfirst($record['award_type']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M d, Y H:i', strtotime($record['awarded_at'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Selected Year Leave Balances -->
                <div class="card" style="margin-top: 30px;">
                    <div class="card-header">
                        <h3>Leave Balances for <?php echo $selectedYear; ?></h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($leaveBalances)): ?>
                            <p>No leave balances found for <?php echo $selectedYear; ?>. Start a new financial year to award annual leave to employees.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Employee ID</th>
                                            <th>Name</th>
                                            <th>Department</th>
                                            <th>Section</th>
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
                                                <td><?php echo htmlspecialchars($balance['section_name'] ?? 'N/A'); ?></td>
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Financial year form validation and enhancement
            const financialYearForm = document.querySelector('form[action=""][method="POST"]');
            const financialYearInput = document.getElementById('financial_year');
            
            if (financialYearForm && financialYearInput) {
                // Auto-format financial year input
                financialYearInput.addEventListener('input', function() {
                    let value = this.value.replace(/[^0-9-]/g, ''); // Only allow numbers and hyphens
                    
                    // Auto-format to YYYY-YYYY pattern
                    if (value.length === 4 && !value.includes('-')) {
                        const currentYear = parseInt(value);
                        value = currentYear + '-' + (currentYear + 1);
                        this.value = value;
                    }
                });
                
                // Enhanced form validation
                financialYearForm.addEventListener('submit', function(e) {
                    const yearValue = financialYearInput.value.trim();
                    
                    // Validate format
                    const yearPattern = /^\d{4}-\d{4}$/;
                    if (!yearPattern.test(yearValue)) {
                        e.preventDefault();
                        alert('Please enter financial year in format YYYY-YYYY (e.g., 2024-2025)');
                        financialYearInput.focus();
                        return false;
                    }
                    
                    // Validate year sequence
                    const years = yearValue.split('-');
                    const startYear = parseInt(years[0]);
                    const endYear = parseInt(years[1]);
                    
                    if (endYear !== startYear + 1) {
                        e.preventDefault();
                        alert('End year must be exactly one year after start year (e.g., 2024-2025)');
                        financialYearInput.focus();
                        return false;
                    }
                    
                    // Validate reasonable year range
                    const currentYear = new Date().getFullYear();
                    if (startYear < currentYear - 5 || startYear > currentYear + 5) {
                        e.preventDefault();
                        alert('Please enter a reasonable financial year (within 5 years of current year)');
                        financialYearInput.focus();
                        return false;
                    }
                    
                    // Confirm action
                    const confirmMessage = `Are you sure you want to start financial year ${yearValue}?\n\nThis will:\n- Award 30 days of annual leave to all permanent employees\n- Create leave balances for the new financial year\n- This action cannot be undone`;
                    
                    if (!confirm(confirmMessage)) {
                        e.preventDefault();
                        return false;
                    }
                });
                
                // Suggest current financial year
                const currentDate = new Date();
                const currentMonth = currentDate.getMonth() + 1; // 0-based month
                const currentYear = currentDate.getFullYear();
                
                // If it's July or later, suggest current year as start year
                let suggestedStartYear = currentYear;
                if (currentMonth < 7) {
                    // If before July, suggest previous year as start year
                    suggestedStartYear = currentYear - 1;
                }
                
                const suggestedFinancialYear = suggestedStartYear + '-' + (suggestedStartYear + 1);
                
                // Add placeholder with suggestion
                financialYearInput.placeholder = `e.g., ${suggestedFinancialYear}`;
                
                // Add helper text
                const helpText = document.createElement('small');
                helpText.className = 'form-text text-muted';
                helpText.innerHTML = `Suggested: <strong>${suggestedFinancialYear}</strong> (based on current date)`;
                financialYearInput.parentNode.appendChild(helpText);
            }
            
            // Enhanced financial year dropdown functionality
            const yearDropdowns = document.querySelectorAll('select[name="year"]');
            yearDropdowns.forEach(function(dropdown) {
                dropdown.addEventListener('change', function() {
                    // Add loading indicator
                    const form = this.closest('form');
                    if (form) {
                        const loadingSpan = document.createElement('span');
                        loadingSpan.innerHTML = ' <em>Loading...</em>';
                        loadingSpan.className = 'loading-indicator';
                        this.parentNode.appendChild(loadingSpan);
                        
                        // Submit form after short delay to show loading
                        setTimeout(function() {
                            form.submit();
                        }, 100);
                    }
                });
            });
            
            // Add tooltips to statistical cards
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach(function(card) {
                const number = card.querySelector('.stat-number');
                const label = card.querySelector('.stat-label');
                
                if (number && label) {
                    const value = number.textContent.trim();
                    const labelText = label.textContent.trim();
                    
                    // Add hover effect and tooltip
                    card.style.cursor = 'help';
                    card.title = `${labelText}: ${value}`;
                    
                    card.addEventListener('mouseenter', function() {
                        this.style.transform = 'translateY(-2px)';
                        this.style.boxShadow = '0 4px 8px rgba(0,0,0,0.1)';
                        this.style.transition = 'all 0.2s ease';
                    });
                    
                    card.addEventListener('mouseleave', function() {
                        this.style.transform = 'translateY(0)';
                        this.style.boxShadow = '';
                    });
                }
            });
            
            // Enhanced table interactions
            const tables = document.querySelectorAll('.table');
            tables.forEach(function(table) {
                const rows = table.querySelectorAll('tbody tr');
                
                rows.forEach(function(row) {
                    // Add hover effect to table rows
                    row.addEventListener('mouseenter', function() {
                        this.style.backgroundColor = '#f8f9fa';
                        this.style.transition = 'background-color 0.2s ease';
                    });
                    
                    row.addEventListener('mouseleave', function() {
                        this.style.backgroundColor = '';
                    });
                });
            });
            
            // Add search functionality to tables
            const tableContainers = document.querySelectorAll('.table-container');
            tableContainers.forEach(function(container) {
                const table = container.querySelector('table');
                const header = container.querySelector('h3, h4');
                
                if (table && header && table.querySelectorAll('tbody tr').length > 5) {
                    // Add search input
                    const searchDiv = document.createElement('div');
                    searchDiv.className = 'table-search';
                    searchDiv.style.marginBottom = '15px';
                    
                    const searchInput = document.createElement('input');
                    searchInput.type = 'text';
                    searchInput.placeholder = 'Search in table...';
                    searchInput.className = 'form-control';
                    searchInput.style.maxWidth = '300px';
                    searchInput.style.display = 'inline-block';
                    
                    searchDiv.appendChild(searchInput);
                    header.parentNode.insertBefore(searchDiv, table);
                    
                    // Search functionality
                    searchInput.addEventListener('input', function() {
                        const searchTerm = this.value.toLowerCase();
                        const rows = table.querySelectorAll('tbody tr');
                        
                        rows.forEach(function(row) {
                            const text = row.textContent.toLowerCase();
                            if (text.includes(searchTerm)) {
                                row.style.display = '';
                            } else {
                                row.style.display = 'none';
                            }
                        });
                        
                        // Show/hide "no results" message
                        const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');
                        let noResultsRow = table.querySelector('.no-results-row');
                        
                        if (visibleRows.length === 0 && searchTerm) {
                            if (!noResultsRow) {
                                noResultsRow = document.createElement('tr');
                                noResultsRow.className = 'no-results-row';
                                noResultsRow.innerHTML = `<td colspan="${table.querySelectorAll('thead th').length}" class="text-center"><em>No results found for "${searchTerm}"</em></td>`;
                                table.querySelector('tbody').appendChild(noResultsRow);
                            }
                            noResultsRow.style.display = '';
                        } else if (noResultsRow) {
                            noResultsRow.style.display = 'none';
                        }
                    });
                }
            });
            
            // Auto-refresh functionality for real-time updates
            let refreshInterval;
            const refreshButton = document.createElement('button');
            refreshButton.type = 'button';
            refreshButton.className = 'btn btn-outline-secondary btn-sm';
            refreshButton.innerHTML = '🔄 Auto-refresh: OFF';
            refreshButton.style.position = 'fixed';
            refreshButton.style.bottom = '20px';
            refreshButton.style.right = '20px';
            refreshButton.style.zIndex = '1000';
            
            document.body.appendChild(refreshButton);
            
            let autoRefreshEnabled = false;
            refreshButton.addEventListener('click', function() {
                autoRefreshEnabled = !autoRefreshEnabled;
                
                if (autoRefreshEnabled) {
                    this.innerHTML = '🔄 Auto-refresh: ON';
                    this.className = 'btn btn-success btn-sm';
                    
                    refreshInterval = setInterval(function() {
                        // Only refresh if no forms are being filled
                        const activeElement = document.activeElement;
                        if (!activeElement || (activeElement.tagName !== 'INPUT' && activeElement.tagName !== 'TEXTAREA')) {
                            window.location.reload();
                        }
                    }, 30000); // Refresh every 30 seconds
                } else {
                    this.innerHTML = '🔄 Auto-refresh: OFF';
                    this.className = 'btn btn-outline-secondary btn-sm';
                    
                    if (refreshInterval) {
                        clearInterval(refreshInterval);
                    }
                }
            });
            
            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Ctrl+R or F5 - Manual refresh
                if ((e.ctrlKey && e.key === 'r') || e.key === 'F5') {
                    e.preventDefault();
                    window.location.reload();
                }
                
                // Ctrl+F - Focus search (if available)
                if (e.ctrlKey && e.key === 'f') {
                    const searchInput = document.querySelector('.table-search input');
                    if (searchInput) {
                        e.preventDefault();
                        searchInput.focus();
                        searchInput.select();
                    }
                }
                
                // Escape - Clear search
                if (e.key === 'Escape') {
                    const searchInputs = document.querySelectorAll('.table-search input');
                    searchInputs.forEach(function(input) {
                        if (input.value) {
                            input.value = '';
                            input.dispatchEvent(new Event('input'));
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>