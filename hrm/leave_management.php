<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Handle test request for hierarchical approval workflow
if (isset($_GET['test']) && $_GET['test'] === 'hierarchical_approval') {
    require_once 'config.php';
    
    echo "<h2>Hierarchical Leave Approval Test</h2>";
    echo "<pre>";
    
    try {
        $conn = getConnection();
        
        // Find an officer employee for testing
        $officerQuery = "SELECT e.*, d.name as department_name, s.name as section_name 
                        FROM employees e 
                        LEFT JOIN departments d ON e.department_id = d.id 
                        LEFT JOIN sections s ON e.section_id = s.id 
                        WHERE e.employee_type = 'officer' AND e.employee_status = 'active' 
                        LIMIT 1";
        $result = $conn->query($officerQuery);
        $officer = $result->fetch_assoc();
        
        if (!$officer) {
            echo "No officer found for testing. Creating test scenario...\n\n";
            echo "Please ensure you have:\n";
            echo "1. An employee with employee_type = 'officer'\n";
            echo "2. A section_head in the same department/section\n";
            echo "3. A dept_head in the same department\n";
            $conn->close();
            echo "</pre>";
            echo '<p><a href="leave_management.php">Back to Leave Management</a></p>';
            exit();
        }
        
        echo "=== TEST SCENARIO ===\n";
        echo "Officer: {$officer['first_name']} {$officer['last_name']} (ID: {$officer['employee_id']})\n";
        echo "Employee Type: {$officer['employee_type']}\n";
        echo "Department: {$officer['department_name']}\n";
        echo "Section: {$officer['section_name']}\n\n";
        
        // Get hierarchy information
        $hierarchy = getEmployeeHierarchy($officer['id'], $conn);
        
        echo "=== APPROVAL HIERARCHY ===\n";
        if ($hierarchy['section_head']) {
            echo "Section Head: {$hierarchy['section_head']['first_name']} {$hierarchy['section_head']['last_name']} (ID: {$hierarchy['section_head']['employee_id']})\n";
        } else {
            echo "Section Head: NOT FOUND\n";
        }
        
        if ($hierarchy['department_head']) {
            echo "Department Head: {$hierarchy['department_head']['first_name']} {$hierarchy['department_head']['last_name']} (ID: {$hierarchy['department_head']['employee_id']})\n";
        } else {
            echo "Department Head: NOT FOUND\n";
        }
        
        // Get approval workflow
        $workflow = getApprovalWorkflow($officer['employee_type']);
        echo "\nApproval Workflow: " . implode(' → ', array_map('ucwords', str_replace('_', ' ', $workflow))) . "\n\n";
        
        // Check recent leave applications by this officer
        echo "=== RECENT LEAVE APPLICATIONS ===\n";
        $recentQuery = "SELECT la.*, lt.name as leave_type_name 
                       FROM leave_applications la 
                       JOIN leave_types lt ON la.leave_type_id = lt.id 
                       WHERE la.employee_id = ? 
                       ORDER BY la.applied_at DESC 
                       LIMIT 5";
        $stmt = $conn->prepare($recentQuery);
        $stmt->bind_param("i", $officer['id']);
        $stmt->execute();
        $recentApplications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        if (empty($recentApplications)) {
            echo "No recent applications found.\n";
        } else {
            foreach ($recentApplications as $app) {
                echo "Application ID: {$app['id']}\n";
                echo "Leave Type: {$app['leave_type_name']}\n";
                echo "Dates: {$app['start_date']} to {$app['end_date']} ({$app['days_requested']} days)\n";
                echo "Status: {$app['status']}\n";
                echo "Section Head Approval: " . ($app['section_head_approval'] ?? 'not_set') . "\n";
                echo "Dept Head Approval: " . ($app['dept_head_approval'] ?? 'not_set') . "\n";
                echo "Applied: {$app['applied_at']}\n";
                echo "---\n";
            }
        }
        
        echo "\n=== TEST INSTRUCTIONS ===\n";
        echo "1. Log in as the officer: {$officer['employee_id']}\n";
        echo "2. Go to Leave Management → Apply Leave\n";
        echo "3. Apply for leave (e.g., 2-3 days)\n";
        echo "4. Check that the application shows workflow: Section Head → Department Head\n";
        echo "5. Log in as section head to approve first level\n";
        echo "6. Log in as department head to approve second level\n";
        echo "7. Verify that leave is only fully approved after both approvals\n\n";
        
        echo "=== MANAGEMENT LEVEL TEST ===\n";
        echo "1. Find a department head, manager, or managing director employee\n";
        echo "2. Log in as that management employee\n";
        echo "3. Apply for leave\n";
        echo "4. Check that workflow shows: Managing Director or HR Manager\n";
        echo "5. Log in as Managing Director OR HR Manager to approve\n";
        echo "6. Verify leave is approved after executive approval\n";
        echo "7. Both Managing Director and HR Manager can approve management leave\n\n";
        
        echo "=== CURRENT USER PERMISSIONS ===\n";
        echo "Current user role: " . ($_SESSION['user_role'] ?? 'not_set') . "\n";
        echo "Can approve as section head: " . (hasPermission('section_head') ? 'YES' : 'NO') . "\n";
        echo "Can approve as dept head: " . (hasPermission('dept_head') ? 'YES' : 'NO') . "\n";
        echo "Can approve as HR manager: " . (hasPermission('hr_manager') ? 'YES' : 'NO') . "\n";
        echo "Can approve as Managing Director: " . (hasPermission('managing_director') ? 'YES' : 'NO') . "\n";
        echo "Can do executive approval: " . ((hasPermission('hr_manager') || hasPermission('managing_director')) ? 'YES' : 'NO') . "\n";
        
        $conn->close();
        echo "\nTest completed successfully!";
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
    
    echo "</pre>";
    echo '<p><a href="leave_management.php">Back to Leave Management</a></p>';
    echo '<p><a href="leave_management.php?test=leave_calculation">Test Leave Calculation</a></p>';
    exit();
}

// Handle test request for leave days calculation
if (isset($_GET['test']) && $_GET['test'] === 'leave_calculation') {
    require_once 'config.php';
    
    echo "<h2>Leave Days Calculation Test</h2>";
    echo "<pre>";
    
    try {
        $conn = getConnection();
        
        // Get leave types
        $leaveTypesQuery = "SELECT id, name, counts_weekends FROM leave_types WHERE is_active = 1";
        $result = $conn->query($leaveTypesQuery);
        $leaveTypes = $result->fetch_all(MYSQLI_ASSOC);
        
        echo "Available Leave Types:\n";
        foreach ($leaveTypes as $type) {
            $weekendNote = $type['counts_weekends'] ? 'Includes weekends/holidays' : 'Excludes weekends/holidays';
            echo "- {$type['name']} (ID: {$type['id']}) - {$weekendNote}\n";
        }
        echo "\n";
        
        // Test scenarios
        $testCases = [
            [
                'description' => 'Monday to Friday (1 week)',
                'start_date' => '2024-07-22', // Monday
                'end_date' => '2024-07-26',   // Friday
            ],
            [
                'description' => 'Friday to Monday (includes weekend)',
                'start_date' => '2024-07-26', // Friday
                'end_date' => '2024-07-29',   // Monday
            ]
        ];
        
        foreach ($testCases as $testCase) {
            echo "Test Case: {$testCase['description']}\n";
            echo "Date Range: {$testCase['start_date']} to {$testCase['end_date']}\n";
            echo "Calendar Days: " . calculateCalendarDays($testCase['start_date'], $testCase['end_date']) . "\n";
            echo "Business Days: " . calculateBusinessDays($testCase['start_date'], $testCase['end_date'], $conn, false) . "\n";
            
            echo "\nCalculation by Leave Type:\n";
            foreach ($leaveTypes as $leaveType) {
                $result = calculateLeaveDays($testCase['start_date'], $testCase['end_date'], $leaveType['id'], $conn);
                echo "- {$result['leave_type']}: {$result['days']} days ({$result['note']})\n";
            }
            echo "\n" . str_repeat("-", 50) . "\n\n";
        }
        
        $conn->close();
        echo "Test completed successfully!";
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
    
    echo "</pre>";
    echo '<p><a href="leave_management.php">Back to Leave Management</a></p>';
    exit();
}

// Handle AJAX request for leave days calculation
if (isset($_GET['ajax']) && $_GET['ajax'] === 'calculate_days') {
    header('Content-Type: application/json');
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit();
    }
    
    require_once 'config.php';
    
    // Sanitize input function
    function sanitizeInput($data) {
        return htmlspecialchars(strip_tags(trim($data)));
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $startDate = sanitizeInput($_POST['start_date'] ?? '');
            $endDate = sanitizeInput($_POST['end_date'] ?? '');
            $leaveTypeId = (int)($_POST['leave_type_id'] ?? 0);
            
            // Validate inputs
            if (empty($startDate) || empty($endDate) || $leaveTypeId <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid input parameters']);
                exit();
            }
            
            // Validate date format and range
            $start = DateTime::createFromFormat('Y-m-d', $startDate);
            $end = DateTime::createFromFormat('Y-m-d', $endDate);
            
            if (!$start || !$end) {
                echo json_encode(['success' => false, 'error' => 'Invalid date format']);
                exit();
            }
            
            if ($end < $start) {
                echo json_encode(['success' => false, 'error' => 'End date must be after start date']);
                exit();
            }
            
            // Connect to database
            $conn = getConnection();
            
            // Calculate leave days
            $result = calculateLeaveDays($startDate, $endDate, $leaveTypeId, $conn);
            
            $conn->close();
            
            echo json_encode([
                'success' => true,
                'days' => $result['days'],
                'note' => $result['note'],
                'leave_type' => $result['leave_type'],
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    }
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config.php';
require_once 'annual_leave_award_new.php';
$conn = getConnection();

// Initialize $tab with default value BEFORE any output
$tab = isset($_GET['tab']) ? sanitizeInput($_GET['tab']) : 'apply';

// Get available financial years and selected year for filtering
$availableYears = getAvailableFinancialYears($conn);
$currentFinancialYear = getCurrentFinancialYear();
$selectedYear = $_GET['year'] ?? $currentFinancialYear;

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

function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        return ['message' => $message, 'type' => $type];
    }
    return false;
}

// Helper functions for leave management
function calculateBusinessDays($startDate, $endDate, $conn, $includeWeekends = false) {
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    $days = 0;

    // Get holidays from database
    $holidayQuery = "SELECT date FROM holidays WHERE date BETWEEN ? AND ?";
    $stmt = $conn->prepare($holidayQuery);
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();

    $holidays = [];
    while ($row = $result->fetch_assoc()) {
        $holidays[] = $row['date'];
    }

    $current = clone $start;
    while ($current <= $end) {
        $dayOfWeek = $current->format('N'); // 1 = Monday, 7 = Sunday
        $currentDate = $current->format('Y-m-d');

        // Skip weekends if not included
        if (!$includeWeekends && ($dayOfWeek == 6 || $dayOfWeek == 7)) {
            $current->add(new DateInterval('P1D'));
            continue;
        }

        // Skip holidays if not including weekends (for most leave types except maternity)
        if (!$includeWeekends && in_array($currentDate, $holidays)) {
            $current->add(new DateInterval('P1D'));
            continue;
        }

        $days++;
        $current->add(new DateInterval('P1D'));
    }

    return $days;
}

/**
 * Calculate leave days based on leave type settings
 * This function considers whether weekends and holidays should be counted based on leave type
 */
function calculateLeaveDays($startDate, $endDate, $leaveTypeId, $conn) {
    // Get leave type settings
    $leaveTypeQuery = "SELECT counts_weekends FROM leave_types WHERE id = ?";
    $stmt = $conn->prepare($leaveTypeQuery);
    $stmt->bind_param("i", $leaveTypeId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($leaveType = $result->fetch_assoc()) {
        $countsWeekends = (bool)$leaveType['counts_weekends'];
        
        // If leave type counts weekends (like maternity leave), include weekends and holidays
        if ($countsWeekends) {
            return calculateCalendarDays($startDate, $endDate);
        } else {
            // For other leave types, exclude weekends and holidays
            return calculateBusinessDays($startDate, $endDate, $conn, false);
        }
    }
    
    // Default to business days if leave type not found
    return calculateBusinessDays($startDate, $endDate, $conn, false);
}

/**
 * Calculate calendar days (including weekends and holidays)
 * Used for leave types like maternity leave
 */
function calculateCalendarDays($startDate, $endDate) {
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    
    $interval = $start->diff($end);
    return $interval->days + 1; // +1 to include both start and end dates
}

function getLeaveTypeBalance($employeeId, $leaveTypeId, $conn) {
    $query = "SELECT * FROM leave_balances WHERE employee_id = ? AND leave_type_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $employeeId, $leaveTypeId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        return $row;
    }

    return ['allocated' => 0, 'used' => 0, 'remaining' => 0];
}

function updateLeaveBalance($employeeId, $leaveTypeId, $days, $conn, $action = 'use') {
    $balance = getLeaveTypeBalance($employeeId, $leaveTypeId, $conn);

    if ($action == 'use') {
        $newUsed = $balance['used'] + $days;
        $newRemaining = $balance['allocated'] - $newUsed;
    } else {
        $newUsed = max(0, $balance['used'] - $days);
        $newRemaining = $balance['allocated'] - $newUsed;
    }
    
    $query = "UPDATE leave_balances SET used = ?, remaining = ? WHERE employee_id = ? AND leave_type_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiii", $newUsed, $newRemaining, $employeeId, $leaveTypeId);
    return $stmt->execute();
}

/**
 * Get the section head for a given department and section
 */
function getSectionHead($departmentId, $sectionId, $conn) {
    $query = "SELECT id, employee_id, first_name, last_name, email 
              FROM employees 
              WHERE department_id = ? AND section_id = ? AND employee_type = 'section_head' AND employee_status = 'active' 
              LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $departmentId, $sectionId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

/**
 * Get the department head for a given department
 */
function getDepartmentHead($departmentId, $conn) {
    $query = "SELECT id, employee_id, first_name, last_name, email 
              FROM employees 
              WHERE department_id = ? AND employee_type = 'dept_head' AND employee_status = 'active' 
              LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $departmentId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

/**
 * Get employee hierarchy information
 */
function getEmployeeHierarchy($employeeId, $conn) {
    $query = "SELECT e.*, d.name as department_name, s.name as section_name 
              FROM employees e 
              LEFT JOIN departments d ON e.department_id = d.id 
              LEFT JOIN sections s ON e.section_id = s.id 
              WHERE e.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $employeeId);
    $stmt->execute();
    $result = $stmt->get_result();
    $employee = $result->fetch_assoc();
    
    if ($employee) {
        // Get section head
        if ($employee['section_id']) {
            $employee['section_head'] = getSectionHead($employee['department_id'], $employee['section_id'], $conn);
        }
        
        // Get department head
        if ($employee['department_id']) {
            $employee['department_head'] = getDepartmentHead($employee['department_id'], $conn);
        }
    }
    
    return $employee;
}

/**
 * Determine approval workflow based on employee type
 */
function getApprovalWorkflow($employeeType) {
    switch ($employeeType) {
        case 'officer':
        case 'employee':
            return ['section_head', 'dept_head']; // Two-level approval
        case 'section_head':
            return ['dept_head']; // Only department head approval
        case 'dept_head':
        case 'manager':
        case 'managing_director':
            return ['executive_approval']; // Managing Director or HR Manager approval
        case 'hr_manager':
            return []; // No approval needed (auto-approved)
        default:
            return ['section_head', 'dept_head']; // Default two-level approval
    }
}

/**
 * Create notification for leave approval
 */
function createLeaveNotification($conn, $userId, $applicationId, $type) {
    $messages = [
        'section_head_approval' => 'New leave application requires your approval as Section Head',
        'dept_head_approval' => 'New leave application requires your approval as Department Head',
        'hr_manager_approval' => 'New leave application requires your approval as HR Manager',
        'executive_approval' => 'New management-level leave application requires executive approval'
    ];
    
    $message = $messages[$type] ?? 'New leave application requires your approval';
    
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, related_type, related_id) 
                           VALUES (?, 'Leave Approval Required', ?, 'warning', 'leave_application', ?)");
    $stmt->bind_param("isi", $userId, $message, $applicationId);
    $stmt->execute();
}



function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input ?? '')));
}

function formatDate($date) {
    if (!$date) return 'N/A';
    return date('M d, Y', strtotime($date));
}

function getStatusBadgeClass($status) {
    switch ($status) {
        case 'approved': return 'badge-success';
        case 'rejected': return 'badge-danger';
        case 'pending': return 'badge-warning';
        default: return 'badge-secondary';
    }
}

// Get user's employee record for auto-filling
$userEmployeeQuery = "SELECT e.* FROM employees e 
                      LEFT JOIN users u ON u.employee_id = e.employee_id 
                      WHERE u.id = ?";
$stmt = $conn->prepare($userEmployeeQuery);
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$userEmployee = $stmt->get_result()->fetch_assoc();

// Initialize variables
$success = '';
$error = '';
$employees = [];
$departments = [];
$sections = [];
$leaveTypes = [];
$leaveApplications = [];
$leaveBalances = [];
$pendingLeaves = [];
$approvedLeaves = [];
$rejectedLeaves = [];
$currentLeaves = [];
$allLeaves = [];
$holidays = [];
$employee = null;
$leaveBalance = null;
$leaveHistory = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'apply_leave':
            $employeeId = $userEmployee['id'] ?? 0;
            $leaveTypeId = (int)$_POST['leave_type_id'];
            $startDate = $_POST['start_date'];
            $endDate = $_POST['end_date'];
            $reason = sanitizeInput($_POST['reason']);
            $emergencyContact = sanitizeInput($_POST['emergency_contact']);
            $emergencyPhone = sanitizeInput($_POST['emergency_phone']);

            // Calculate days based on leave type (excludes weekends/holidays except for maternity leave)
            $days = calculateLeaveDays($startDate, $endDate, $leaveTypeId, $conn);

            // Check balance
            $balance = getLeaveTypeBalance($employeeId, $leaveTypeId, $conn);
            if ($days > $balance['remaining']) {
                $error = "Insufficient leave balance. You have {$balance['remaining']} days remaining.";
            } else {
                try {
                    // Get employee hierarchy information
                    $employeeInfo = getEmployeeHierarchy($employeeId, $conn);
                    $approvalWorkflow = getApprovalWorkflow($employeeInfo['employee_type']);
                    
                    // Determine initial approval status based on workflow
                    $sectionHeadApproval = in_array('section_head', $approvalWorkflow) ? 'pending' : 'not_required';
                    $deptHeadApproval = in_array('dept_head', $approvalWorkflow) ? 'pending' : 'not_required';
                    $hrApproval = in_array('hr_manager', $approvalWorkflow) || in_array('executive_approval', $approvalWorkflow) ? 'pending' : 'not_required';
                    $status = empty($approvalWorkflow) ? 'approved' : 'pending';
                    
                    $stmt = $conn->prepare("INSERT INTO leave_applications 
                        (employee_id, leave_type_id, start_date, end_date, days_requested, reason, 
                         emergency_contact, emergency_phone, status, section_head_approval, dept_head_approval, hr_approval, applied_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->bind_param("iissssssssss", $employeeId, $leaveTypeId, $startDate, $endDate, 
                                    $days, $reason, $emergencyContact, $emergencyPhone, $status, 
                                    $sectionHeadApproval, $deptHeadApproval, $hrApproval);

                    if ($stmt->execute()) {
                        $applicationId = $conn->insert_id;
                        
                        // Create notifications for approvers
                        if (in_array('section_head', $approvalWorkflow) && $employeeInfo['section_head']) {
                            createLeaveNotification($conn, $employeeInfo['section_head']['id'], $applicationId, 'section_head_approval');
                        }
                        if (in_array('dept_head', $approvalWorkflow) && $employeeInfo['department_head']) {
                            createLeaveNotification($conn, $employeeInfo['department_head']['id'], $applicationId, 'dept_head_approval');
                        }
                        if (in_array('hr_manager', $approvalWorkflow)) {
                            // Find HR managers to notify
                            $hrQuery = "SELECT id FROM employees WHERE employee_type = 'hr_manager' AND employee_status = 'active'";
                            $hrResult = $conn->query($hrQuery);
                            while ($hrManager = $hrResult->fetch_assoc()) {
                                createLeaveNotification($conn, $hrManager['id'], $applicationId, 'hr_manager_approval');
                            }
                        }
                        if (in_array('executive_approval', $approvalWorkflow)) {
                            // Find Managing Directors and HR Managers to notify
                            $execQuery = "SELECT id FROM employees WHERE employee_type IN ('managing_director', 'hr_manager') AND employee_status = 'active'";
                            $execResult = $conn->query($execQuery);
                            while ($executive = $execResult->fetch_assoc()) {
                                createLeaveNotification($conn, $executive['id'], $applicationId, 'executive_approval');
                            }
                        }
                        
                        $workflowText = '';
                        if (!empty($approvalWorkflow)) {
                            $displayWorkflow = array_map(function($step) {
                                return $step === 'executive_approval' ? 'Managing Director or HR Manager' : ucwords(str_replace('_', ' ', $step));
                            }, $approvalWorkflow);
                            $workflowText = " Your application will go through: " . implode(' → ', $displayWorkflow);
                        }
                        
                        $success = "Leave application submitted successfully!{$workflowText}";
                    } else {
                        $error = "Error submitting application.";
                    }
                } catch (Exception $e) {
                    $error = "Database error: " . $e->getMessage();
                }
            }
            break;

        case 'section_head_approve':
            if (hasPermission('section_head') || hasPermission('hr_manager')) {
                $applicationId = (int)$_POST['application_id'];
                $comments = sanitizeInput($_POST['comments'] ?? '');

                try {
                    $conn->begin_transaction();

                    // Get application details
                    $stmt = $conn->prepare("SELECT la.*, e.employee_type, e.department_id, e.section_id 
                                          FROM leave_applications la 
                                          JOIN employees e ON la.employee_id = e.id 
                                          WHERE la.id = ?");
                    $stmt->bind_param("i", $applicationId);
                    $stmt->execute();
                    $application = $stmt->get_result()->fetch_assoc();

                    if ($application) {
                        // Update section head approval
                        $stmt = $conn->prepare("UPDATE leave_applications 
                                              SET section_head_approval = 'approved', 
                                                  section_head_approved_by = ?, 
                                                  section_head_approved_at = NOW() 
                                              WHERE id = ?");
                        $stmt->bind_param("si", $user['id'], $applicationId);
                        $stmt->execute();

                        // Check if this completes the approval process
                        if ($application['dept_head_approval'] === 'approved' || $application['dept_head_approval'] === 'not_required') {
                            // Check if HR approval is needed
                            $hrApproval = $application['hr_approval'] ?? 'not_required';
                            if ($hrApproval === 'approved' || $hrApproval === 'not_required') {
                                // All approvals complete - approve the leave
                                $stmt = $conn->prepare("UPDATE leave_applications SET status = 'approved' WHERE id = ?");
                                $stmt->bind_param("i", $applicationId);
                                $stmt->execute();
                                
                                // Update leave balance
                                updateLeaveBalance($application['employee_id'], $application['leave_type_id'], 
                                                 $application['days_requested'], $conn, 'use');
                            }
                        }

                        $conn->commit();
                        $success = "Leave application approved at section level successfully!";
                    } else {
                        $conn->rollback();
                        $error = "Application not found.";
                    }
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Error approving leave: " . $e->getMessage();
                }
            }
            break;

        case 'dept_head_approve':
            if (hasPermission('dept_head') || hasPermission('hr_manager')) {
                $applicationId = (int)$_POST['application_id'];
                $comments = sanitizeInput($_POST['comments'] ?? '');

                try {
                    $conn->begin_transaction();

                    // Get application details
                    $stmt = $conn->prepare("SELECT la.*, e.employee_type 
                                          FROM leave_applications la 
                                          JOIN employees e ON la.employee_id = e.id 
                                          WHERE la.id = ?");
                    $stmt->bind_param("i", $applicationId);
                    $stmt->execute();
                    $application = $stmt->get_result()->fetch_assoc();

                    if ($application) {
                        // Update department head approval
                        $stmt = $conn->prepare("UPDATE leave_applications 
                                              SET dept_head_approval = 'approved', 
                                                  dept_head_approved_by = ?, 
                                                  dept_head_approved_at = NOW() 
                                              WHERE id = ?");
                        $stmt->bind_param("si", $user['id'], $applicationId);
                        $stmt->execute();

                        // Check if section head approval is also complete (or not required)
                        if ($application['section_head_approval'] === 'approved' || $application['section_head_approval'] === 'not_required') {
                            // Check if HR approval is needed
                            $hrApproval = $application['hr_approval'] ?? 'not_required';
                            if ($hrApproval === 'approved' || $hrApproval === 'not_required') {
                                // All approvals complete - approve the leave
                                $stmt = $conn->prepare("UPDATE leave_applications SET status = 'approved' WHERE id = ?");
                                $stmt->bind_param("i", $applicationId);
                                $stmt->execute();
                                
                                // Update leave balance
                                updateLeaveBalance($application['employee_id'], $application['leave_type_id'], 
                                                 $application['days_requested'], $conn, 'use');
                            }
                        }

                        $conn->commit();
                        $success = "Leave application approved at department level successfully!";
                    } else {
                        $conn->rollback();
                        $error = "Application not found.";
                    }
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Error approving leave: " . $e->getMessage();
                }
            }
            break;

        case 'executive_approve':
            if (hasPermission('hr_manager') || hasPermission('managing_director')) {
                $applicationId = (int)$_POST['application_id'];
                $comments = sanitizeInput($_POST['comments'] ?? '');

                try {
                    $conn->begin_transaction();

                    // Get application details
                    $stmt = $conn->prepare("SELECT la.*, e.employee_type 
                                          FROM leave_applications la 
                                          JOIN employees e ON la.employee_id = e.id 
                                          WHERE la.id = ?");
                    $stmt->bind_param("i", $applicationId);
                    $stmt->execute();
                    $application = $stmt->get_result()->fetch_assoc();

                    if ($application) {
                        // Update HR approval (used for executive approval tracking)
                        $stmt = $conn->prepare("UPDATE leave_applications 
                                              SET hr_approval = 'approved', 
                                                  hr_processed_by = ?, 
                                                  hr_processed_at = NOW(),
                                                  hr_comments = ?,
                                                  status = 'approved'
                                              WHERE id = ?");
                        $stmt->bind_param("ssi", $user['id'], $comments, $applicationId);
                        $stmt->execute();

                        // Update leave balance
                        updateLeaveBalance($application['employee_id'], $application['leave_type_id'], 
                                         $application['days_requested'], $conn, 'use');

                        $conn->commit();
                        $approverType = hasPermission('managing_director') ? 'Managing Director' : 'HR Manager';
                        $success = "Leave application approved by {$approverType} successfully!";
                    } else {
                        $conn->rollback();
                        $error = "Application not found.";
                    }
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Error approving leave: " . $e->getMessage();
                }
            }
            break;

        case 'hr_manager_approve':
            if (hasPermission('hr_manager')) {
                $applicationId = (int)$_POST['application_id'];
                $comments = sanitizeInput($_POST['comments'] ?? '');

                try {
                    $conn->begin_transaction();

                    // Get application details
                    $stmt = $conn->prepare("SELECT la.*, e.employee_type 
                                          FROM leave_applications la 
                                          JOIN employees e ON la.employee_id = e.id 
                                          WHERE la.id = ?");
                    $stmt->bind_param("i", $applicationId);
                    $stmt->execute();
                    $application = $stmt->get_result()->fetch_assoc();

                    if ($application) {
                        // Update HR approval
                        $stmt = $conn->prepare("UPDATE leave_applications 
                                              SET hr_approval = 'approved', 
                                                  hr_processed_by = ?, 
                                                  hr_processed_at = NOW(),
                                                  hr_comments = ?,
                                                  status = 'approved'
                                              WHERE id = ?");
                        $stmt->bind_param("ssi", $user['id'], $comments, $applicationId);
                        $stmt->execute();

                        // Update leave balance
                        updateLeaveBalance($application['employee_id'], $application['leave_type_id'], 
                                         $application['days_requested'], $conn, 'use');

                        $conn->commit();
                        $success = "Leave application approved by HR Manager successfully!";
                    } else {
                        $conn->rollback();
                        $error = "Application not found.";
                    }
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Error approving leave: " . $e->getMessage();
                }
            }
            break;

        case 'approve_leave':
            if (hasPermission('hr_manager')) {
                $applicationId = (int)$_POST['application_id'];
                $approverComments = sanitizeInput($_POST['approver_comments']);

                try {
                    $conn->begin_transaction();

                    // Get application details
                    $stmt = $conn->prepare("SELECT * FROM leave_applications WHERE id = ?");
                    $stmt->bind_param("i", $applicationId);
                    $stmt->execute();
                    $application = $stmt->get_result()->fetch_assoc();

                    // Update application status
                    $stmt = $conn->prepare("UPDATE leave_applications 
                                          SET status = 'approved', approver_id = ?, approver_comments = ?, 
                                              approved_date = NOW() WHERE id = ?");
                    $stmt->bind_param("isi", $user['id'], $approverComments, $applicationId);
                    $stmt->execute();

                    // Update leave balance
                    updateLeaveBalance($application['employee_id'], $application['leave_type_id'], 
                                     $application['days_requested'], $conn, 'use');

                    $conn->commit();
                    $success = "Leave application approved successfully!";
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Error approving leave: " . $e->getMessage();
                }
            }
            break;

        case 'reject_leave':
            if (hasPermission('hr_manager')) {
                $applicationId = (int)$_POST['application_id'];
                $approverComments = sanitizeInput($_POST['approver_comments']);

                try {
                    $stmt = $conn->prepare("UPDATE leave_applications 
                                          SET status = 'rejected', approver_id = ?, approver_comments = ?, 
                                              approved_date = NOW() WHERE id = ?");
                    $stmt->bind_param("isi", $user['id'], $approverComments, $applicationId);

                    if ($stmt->execute()) {
                        $success = "Leave application rejected.";
                    } else {
                        $error = "Error rejecting application.";
                    }
                } catch (Exception $e) {
                    $error = "Database error: " . $e->getMessage();
                }
            }
            break;

        case 'add_holiday':
            if (hasPermission('hr_manager')) {
                $name = sanitizeInput($_POST['name']);
                $date = $_POST['date'];
                $description = sanitizeInput($_POST['description']);
                $isRecurring = isset($_POST['is_recurring']) ? 1 : 0;

                try {
                    $stmt = $conn->prepare("INSERT INTO holidays (name, date, description, is_recurring) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("sssi", $name, $date, $description, $isRecurring);

                    if ($stmt->execute()) {
                        $success = "Holiday added successfully!";
                    } else {
                        $error = "Error adding holiday.";
                    }
                } catch (Exception $e) {
                    $error = "Database error: " . $e->getMessage();
                }
            }
            break;
    }
}

// Handle GET actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];

    if ($action === 'approve_leave' && isset($_GET['id']) && hasPermission('hr_manager')) {
        $leaveId = (int)$_GET['id'];
        try {
            $stmt = $conn->prepare("UPDATE leave_applications SET status = 'approved', approver_id = ?, approved_date = NOW() WHERE id = ?");
            $stmt->bind_param("si", $user['id'], $leaveId);

            if ($stmt->execute()) {
                $_SESSION['flash_message'] = "Leave application approved successfully!";
                $_SESSION['flash_type'] = "success";
            } else {
                $_SESSION['flash_message'] = "Error approving leave application.";
                $_SESSION['flash_type'] = "danger";
            }
        } catch (Exception $e) {
            $_SESSION['flash_message'] = "Database error: " . $e->getMessage();
            $_SESSION['flash_type'] = "danger";
        }

        header("Location: leave_management.php?tab=manage");
        exit();
    }

    if ($action === 'reject_leave' && isset($_GET['id']) && hasPermission('hr_manager')) {
        $leaveId = (int)$_GET['id'];
        try {
            $stmt = $conn->prepare("UPDATE leave_applications SET status = 'rejected', approver_id = ?, approved_date = NOW() WHERE id = ?");
            $stmt->bind_param("si", $user['id'], $leaveId);

            if ($stmt->execute()) {
                $_SESSION['flash_message'] = "Leave application rejected!";
                $_SESSION['flash_type'] = "warning";
            } else {
                $_SESSION['flash_message'] = "Error rejecting leave application.";
                $_SESSION['flash_type'] = "danger";
            }
        } catch (Exception $e) {
            $_SESSION['flash_message'] = "Database error: " . $e->getMessage();
            $_SESSION['flash_type'] = "danger";
        }

        header("Location: leave_management.php?tab=manage");
        exit();
    }
}

// Fetch data for dropdowns and displays
try {
    // Get departments
    $departmentsResult = $conn->query("SELECT * FROM departments ORDER BY name");
    $departments = $departmentsResult->fetch_all(MYSQLI_ASSOC);

    // Get sections
    $sectionsResult = $conn->query("SELECT s.*, d.name as department_name FROM sections s 
                                   LEFT JOIN departments d ON s.department_id = d.id ORDER BY s.name");
    $sections = $sectionsResult->fetch_all(MYSQLI_ASSOC);

    // Get leave types
    $leaveTypesResult = $conn->query("SELECT * FROM leave_types ORDER BY name");
    $leaveTypes = $leaveTypesResult->fetch_all(MYSQLI_ASSOC);

    // Get employees (for managers)
if (in_array($user['role'], ['hr_manager', 'dept_head', 'section_head'])) {
    $employeesQuery = "SELECT e.*, d.name as department_name, s.name as section_name 
                      FROM employees e 
                      LEFT JOIN departments d ON e.department_id = d.id 
                      LEFT JOIN sections s ON e.section_id = s.id";
    
    // Add role-specific filtering
    if ($user['role'] === 'dept_head') {
        $employeesQuery .= " WHERE e.department_id = " . (int)$userEmployee['department_id'];
    } 
    elseif ($user['role'] === 'section_head') {
        $employeesQuery .= " WHERE e.section_id = " . (int)$userEmployee['section_id'];
    }
    
    $employeesQuery .= " ORDER BY e.first_name, e.last_name";
    $employees = $conn->query($employeesQuery)->fetch_all(MYSQLI_ASSOC);
}

    // Get holidays
    $holidaysResult = $conn->query("SELECT * FROM holidays ORDER BY date DESC");
    $holidays = $holidaysResult->fetch_all(MYSQLI_ASSOC);

    // Prepare data based on tab
    if ($tab === 'manage' && hasPermission('hr_manager')) {
        // Get pending leaves filtered by financial year with hierarchical approval status
        $pendingQuery = "SELECT la.*, e.employee_id, e.first_name, e.last_name, e.employee_type,
                         lt.name as leave_type_name, d.name as department_name, s.name as section_name,
                         COALESCE(la.hr_approval, 'not_required') as hr_approval
                         FROM leave_applications la
                         JOIN employees e ON la.employee_id = e.id
                         JOIN leave_types lt ON la.leave_type_id = lt.id
                         LEFT JOIN departments d ON e.department_id = d.id
                         LEFT JOIN sections s ON e.section_id = s.id
                         LEFT JOIN leave_balances lb ON e.id = lb.employee_id AND lb.financial_year = ?
                         WHERE la.status = 'pending'
                         ORDER BY la.applied_at DESC";
        $stmt = $conn->prepare($pendingQuery);
        $stmt->bind_param("s", $selectedYear);
        $stmt->execute();
        $pendingLeaves = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Get approved leaves filtered by financial year
        $approvedQuery = "SELECT la.*, e.employee_id, e.first_name, e.last_name, lt.name as leave_type_name
                          FROM leave_applications la
                          JOIN employees e ON la.employee_id = e.id
                          JOIN leave_types lt ON la.leave_type_id = lt.id
                          LEFT JOIN leave_balances lb ON e.id = lb.employee_id AND lb.financial_year = ?
                          WHERE la.status = 'approved'
                          ORDER BY la.applied_at DESC
                          LIMIT 20";
        $stmt = $conn->prepare($approvedQuery);
        $stmt->bind_param("s", $selectedYear);
        $stmt->execute();
        $approvedLeaves = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Get rejected leaves filtered by financial year
        $rejectedQuery = "SELECT la.*, e.employee_id, e.first_name, e.last_name, lt.name as leave_type_name
                          FROM leave_applications la
                          JOIN employees e ON la.employee_id = e.id
                          JOIN leave_types lt ON la.leave_type_id = lt.id
                          LEFT JOIN leave_balances lb ON e.id = lb.employee_id AND lb.financial_year = ?
                          WHERE la.status = 'rejected'
                          ORDER BY la.applied_at DESC
                          LIMIT 20";
        $stmt = $conn->prepare($rejectedQuery);
        $stmt->bind_param("s", $selectedYear);
        $stmt->execute();
        $rejectedLeaves = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    if ($tab === 'history' && hasPermission('hr_manager')) {
        // Get current leaves filtered by financial year
        $currentQuery = "SELECT la.*, e.employee_id, e.first_name, e.last_name, lt.name as leave_type_name
                         FROM leave_applications la
                         JOIN employees e ON la.employee_id = e.id
                         JOIN leave_types lt ON la.leave_type_id = lt.id
                         LEFT JOIN leave_balances lb ON e.id = lb.employee_id AND lb.financial_year = ?
                         WHERE la.start_date <= CURDATE() AND la.end_date >= CURDATE() AND la.status = 'approved'
                         ORDER BY la.start_date";
        $stmt = $conn->prepare($currentQuery);
        $stmt->bind_param("s", $selectedYear);
        $stmt->execute();
        $currentLeaves = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Get all leaves filtered by financial year
        $allQuery = "SELECT la.*, e.employee_id, e.first_name, e.last_name, lt.name as leave_type_name
                     FROM leave_applications la
                     JOIN employees e ON la.employee_id = e.id
                     JOIN leave_types lt ON la.leave_type_id = lt.id
                     LEFT JOIN leave_balances lb ON e.id = lb.employee_id AND lb.financial_year = ?
                     ORDER BY la.applied_at DESC
                     LIMIT 50";
        $stmt = $conn->prepare($allQuery);
        $stmt->bind_param("s", $selectedYear);
        $stmt->execute();
        $allLeaves = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    if ($tab === 'profile') {
        // Get employee info
        if ($userEmployee) {
            $employee = $userEmployee;

            // Get leave balance for selected financial year
            $balanceQuery = "SELECT * FROM leave_balances WHERE employee_id = ? AND financial_year = ? ORDER BY leave_type_id";
            $stmt = $conn->prepare($balanceQuery);
            $stmt->bind_param("is", $employee['id'], $selectedYear);
            $stmt->execute();
            $balanceResult = $stmt->get_result();
            $leaveBalance = $balanceResult->fetch_assoc();

            // Get leave history
            $historyQuery = "SELECT la.*, lt.name as leave_type_name
                             FROM leave_applications la
                             JOIN leave_types lt ON la.leave_type_id = lt.id
                             WHERE la.employee_id = ?
                             ORDER BY la.applied_at DESC";
            $stmt = $conn->prepare($historyQuery);
            $stmt->bind_param("i", $employee['id']);
            $stmt->execute();
            $historyResult = $stmt->get_result();
            $leaveHistory = $historyResult->fetch_all(MYSQLI_ASSOC);
        }
    }

    // Get leave applications
    if (hasPermission('hr_manager')) {
        // Managers can see all applications
        $applicationsQuery = "SELECT la.*, e.employee_id, e.first_name, e.last_name, 
                             lt.name as leave_type_name, d.name as department_name, s.name as section_name,
                             u.first_name as approver_first_name, u.last_name as approver_last_name
                             FROM leave_applications la
                             JOIN employees e ON la.employee_id = e.id
                             JOIN leave_types lt ON la.leave_type_id = lt.id
                             LEFT JOIN departments d ON e.department_id = d.id
                             LEFT JOIN sections s ON e.section_id = s.id
                             LEFT JOIN users u ON la.approver_id = u.id
                             ORDER BY la.applied_at DESC";
        $applicationsResult = $conn->query($applicationsQuery);
        $leaveApplications = $applicationsResult->fetch_all(MYSQLI_ASSOC);
    } else {
        // Regular employees see only their applications
        if ($userEmployee) {
            $stmt = $conn->prepare("SELECT la.*, lt.name as leave_type_name,
                                   u.first_name as approver_first_name, u.last_name as approver_last_name
                                   FROM leave_applications la
                                   JOIN leave_types lt ON la.leave_type_id = lt.id
                                   LEFT JOIN users u ON la.approver_id = u.id
                                   WHERE la.employee_id = ?
                                   ORDER BY la.applied_at DESC");
            $stmt->bind_param("i", $userEmployee['id']);
            $stmt->execute();
            $leaveApplications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
    }

    // Get leave balances for current user
    if ($userEmployee) {
        $stmt = $conn->prepare("SELECT lb.*, lt.name as leave_type_name 
                               FROM leave_balances lb
                               JOIN leave_types lt ON lb.leave_type_id = lt.id
                               WHERE lb.employee_id = ?
                               ORDER BY lt.name");
        $stmt->bind_param("i", $userEmployee['id']);
        $stmt->execute();
        $leaveBalances = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

} catch (Exception $e) {
    $error = "Error fetching data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Management - HR Management System</title>
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
            <div class="nav">
                <ul>
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="employees.php">Employees</a></li>
                    <?php if (hasPermission('hr_manager')): ?>
                    <li><a href="departments.php">Departments</a></li>
                    <?php endif; ?>
                    <?php if (hasPermission('super_admin')): ?>
                    <li><a href="users.php">Users</a></li>
                    <?php endif; ?>
                    <li><a href="leave_management.php" class="active">Leave Management</a></li>
                    <?php if (hasPermission('hr_manager')): ?>
                    <li><a href="reports.php">Reports</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <h1>Leave Management System</h1>
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

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <div class="leave-tabs">
                    <a href="leave_management.php?tab=apply" class="leave-tab <?php echo $tab === 'apply' ? 'active' : ''; ?>">Apply Leave</a>
                    <?php if (hasPermission('hr_manager')): ?>
                    <a href="leave_management.php?tab=manage" class="leave-tab <?php echo $tab === 'manage' ? 'active' : ''; ?>">Manage Leave</a>
                    <a href="leave_management.php?tab=history" class="leave-tab <?php echo $tab === 'history' ? 'active' : ''; ?>">Leave History</a>
                    <a href="leave_management.php?tab=holidays" class="leave-tab <?php echo $tab === 'holidays' ? 'active' : ''; ?>">Holidays</a>
                    <?php endif; ?>
                    <a href="leave_management.php?tab=profile" class="leave-tab <?php echo $tab === 'profile' ? 'active' : ''; ?>">My Leave Profile</a>
                </div>

                <?php if ($tab === 'apply'): ?>
                <!-- Apply Leave Tab -->
                <div class="tab-content">
                    <h3>Apply for Leave</h3>

                    <?php if ($userEmployee): ?>
                    <form method="POST" action="">
                    
                        <div class="form-grid">
                        <div class="form-group">
    <label for="employee_id">Employee</label>
    <select id="employee_id" name="employee_id" class="form-control" required>
        <option value="">Select Employee</option>
        <?php 
        if ($userEmployee) {
            echo '<!-- DEBUG: Current User - Role: ' . $user['role'] . 
                 ' | Dept: ' . ($userEmployee['department_id'] ?? 'NULL') . 
                 ' | Section: ' . ($userEmployee['section_id'] ?? 'NULL') . ' -->';

            // Regular Employee: Only show their own name
            if (!in_array($user['role'], ['hr_manager', 'dept_head', 'section_head'])) {
                echo '<option value="' . $userEmployee['id'] . '" selected>' . 
                     htmlspecialchars(
                         $userEmployee['employee_id'] . ' - ' . 
                         $userEmployee['first_name'] . ' ' . 
                         $userEmployee['last_name'] . ' (' . 
                         ($userEmployee['designation'] ?? '') . ')'
                     ) . '</option>';
            } 
            // Managers: Show filtered lists
            elseif (isset($employees) && is_array($employees)) {
                foreach ($employees as $employee) {
                    $selected = ($employee['id'] == $userEmployee['id']) ? 'selected' : '';
                    echo '<option value="' . $employee['id'] . '" ' . $selected . '>' . 
                         htmlspecialchars(
                             $employee['employee_id'] . ' - ' . 
                             $employee['first_name'] . ' ' . 
                             $employee['last_name'] . ' (' . 
                             ($employee['designation'] ?? '') . ')'
                         ) . '</option>';
                }
            }
        } else {
            echo '<option value="">No employee record found</option>';
        }
        ?>
    </select>
</div>
                            <div class="form-group">
                                <label for="leave_type_id">Leave Type</label>
                                <select name="leave_type_id" id="leave_type_id" class="form-control" required>
                                    <option value="">Select Leave Type</option>
                                    <?php foreach ($leaveTypes as $type): ?>
                                    <option value="<?php echo $type['id']; ?>" 
                                            data-counts-weekends="<?php echo $type['counts_weekends']; ?>"
                                            data-description="<?php echo htmlspecialchars($type['description']); ?>">
                                        <?php echo htmlspecialchars($type['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div id="leave_type_info" class="alert alert-info" style="margin-top: 10px; display: none;">
                                    <small id="leave_type_description"></small>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="start_date">Start Date</label>
                                <input type="date" name="start_date" id="start_date" class="form-control" required>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="end_date">End Date</label>
                                <input type="date" name="end_date" id="end_date" class="form-control" required>
                            </div>

                            <div class="form-group">
                                <label for="calculated_days">Calculated Days</label>
                                <input type="text" id="calculated_days" class="form-control" readonly>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="reason">Reason for Leave</label>
                            <textarea name="reason" id="reason" class="form-control" rows="3" required></textarea>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="emergency_contact">Emergency Contact</label>
                                <input type="text" name="emergency_contact" id="emergency_contact" class="form-control" required>
                            </div>

                            <div class="form-group">
                                <label for="emergency_phone">Emergency Phone</label>
                                <input type="tel" name="emergency_phone" id="emergency_phone" class="form-control" required>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Submit Application</button>
                            <button type="reset" class="btn btn-secondary">Reset Form</button>
                        </div>
                    </form>
                    <?php else: ?>
                    <div class="alert alert-warning">
                        Your user account is not linked to an employee record. Please contact HR to resolve this issue.
                    </div>
                    <?php endif; ?>

                    <!-- My Leave Applications -->
                    <div class="table-container mt-4">
                        <h3>My Leave Applications</h3>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Leave Type</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Days</th>
                                    <th>Status</th>
                                    <th>Applied Date</th>
                                    <th>Approver</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($leaveApplications)): ?>
                                    <?php foreach ($leaveApplications as $application): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($application['leave_type_name']); ?></td>
                                        <td><?php echo formatDate($application['start_date']); ?></td>
                                        <td><?php echo formatDate($application['end_date']); ?></td>
                                        <td><?php echo $application['days_requested']; ?></td>
                                        <td>
                                            <span class="badge <?php echo getStatusBadgeClass($application['status']); ?>">
                                                <?php echo ucfirst($application['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo formatDate($application['applied_at']); ?></td>
                                        <td>
                                            <?php 
                                            if ($application['approver_first_name']) {
                                                echo htmlspecialchars($application['approver_first_name'] . ' ' . $application['approver_last_name']);
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">No leave applications found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php elseif ($tab === 'manage' && hasPermission('hr_manager')): ?>
                <!-- Manage Leave Tab -->
                <div class="tab-content">
                    <h3>Manage Leave Applications</h3>
                    
                    <!-- Financial Year Filter -->
                    <div class="filter-section" style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
                        <strong>Filter by Financial Year:</strong>
                        <form method="GET" style="display: inline-block; margin-left: 10px;">
                            <input type="hidden" name="tab" value="manage">
                            <select name="year" onchange="this.form.submit()" class="form-control" style="display: inline-block; width: 150px;">
                                <?php foreach ($availableYears as $year): ?>
                                    <option value="<?php echo $year; ?>" <?php echo ($year === $selectedYear) ? 'selected' : ''; ?>>
                                        <?php echo $year; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                        <span style="margin-left: 15px;">Viewing: <strong><?php echo $selectedYear; ?></strong></span>
                    </div>

                    <!-- Pending Leaves -->
                    <div class="table-container mb-4">
                        <h4>Pending Leave Applications</h4>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Leave Type</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Days</th>
                                    <th>Applied Date</th>
                                    <th>Department/Section</th>
                                    <th>Approval Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pendingLeaves)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center">No pending leave applications</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($pendingLeaves as $leave): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($leave['employee_id'] . ' - ' . $leave['first_name'] . ' ' . $leave['last_name']); ?>
                                            <br><small class="text-muted"><?php echo ucwords(str_replace('_', ' ', $leave['employee_type'])); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($leave['leave_type_name']); ?></td>
                                        <td><?php echo formatDate($leave['start_date']); ?></td>
                                        <td><?php echo formatDate($leave['end_date']); ?></td>
                                        <td><?php echo $leave['days_requested']; ?></td>
                                        <td><?php echo formatDate($leave['applied_at']); ?></td>
                                        <td><?php echo htmlspecialchars(($leave['department_name'] ?? 'N/A') . ' / ' . ($leave['section_name'] ?? 'N/A')); ?></td>
                                        <td>
                                            <?php 
                                            $sectionStatus = $leave['section_head_approval'] ?? 'not_required';
                                            $deptStatus = $leave['dept_head_approval'] ?? 'not_required';
                                            $hrStatus = $leave['hr_approval'] ?? 'not_required';
                                            
                                            if ($sectionStatus !== 'not_required') {
                                                $sectionBadge = $sectionStatus === 'approved' ? 'badge-success' : 
                                                              ($sectionStatus === 'rejected' ? 'badge-danger' : 'badge-warning');
                                                echo '<span class="badge ' . $sectionBadge . '">Section: ' . ucfirst($sectionStatus) . '</span><br>';
                                            }
                                            
                                            if ($deptStatus !== 'not_required') {
                                                $deptBadge = $deptStatus === 'approved' ? 'badge-success' : 
                                                           ($deptStatus === 'rejected' ? 'badge-danger' : 'badge-warning');
                                                echo '<span class="badge ' . $deptBadge . '">Dept: ' . ucfirst($deptStatus) . '</span><br>';
                                            }
                                            
                                            if ($hrStatus !== 'not_required') {
                                                $hrBadge = $hrStatus === 'approved' ? 'badge-success' : 
                                                          ($hrStatus === 'rejected' ? 'badge-danger' : 'badge-warning');
                                                echo '<span class="badge ' . $hrBadge . '">HR: ' . ucfirst($hrStatus) . '</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php if (hasPermission('section_head') && $leave['section_head_approval'] === 'pending'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="section_head_approve">
                                                    <input type="hidden" name="application_id" value="<?php echo $leave['id']; ?>">
                                                    <button type="submit" class="btn btn-success btn-sm" 
                                                            onclick="return confirm('Approve this leave application at section level?')">
                                                        Section Approve
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if (hasPermission('dept_head') && $leave['dept_head_approval'] === 'pending'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="dept_head_approve">
                                                    <input type="hidden" name="application_id" value="<?php echo $leave['id']; ?>">
                                                    <button type="submit" class="btn btn-primary btn-sm" 
                                                            onclick="return confirm('Approve this leave application at department level?')">
                                                        Dept Approve
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php 
                                            // Check if this requires executive approval (dept_head, manager, managing_director)
                                            $requiresExecutiveApproval = in_array($leave['employee_type'], ['dept_head', 'manager', 'managing_director']);
                                            ?>
                                            
                                            <?php if ((hasPermission('hr_manager') || hasPermission('managing_director')) && $requiresExecutiveApproval): ?>
                                                <?php if (($leave['hr_approval'] ?? 'not_required') === 'pending'): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="executive_approve">
                                                        <input type="hidden" name="application_id" value="<?php echo $leave['id']; ?>">
                                                        <button type="submit" class="btn btn-warning btn-sm" 
                                                                onclick="return confirm('Approve this management-level leave application?')">
                                                            Executive Approve
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            
                                            <?php if (hasPermission('hr_manager')): ?>
                                                <?php if (($leave['hr_approval'] ?? 'not_required') === 'pending' && !$requiresExecutiveApproval): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="hr_manager_approve">
                                                        <input type="hidden" name="application_id" value="<?php echo $leave['id']; ?>">
                                                        <button type="submit" class="btn btn-info btn-sm" 
                                                                onclick="return confirm('Approve this leave application as HR Manager?')">
                                                            HR Approve
                                                        </button>
                                                    </form>
                                                <?php elseif (($leave['hr_approval'] ?? 'not_required') !== 'pending'): ?>
                                                    <a href="leave_management.php?action=approve_leave&id=<?php echo $leave['id']; ?>&tab=manage" 
                                                       class="btn btn-success btn-sm" 
                                                       onclick="return confirm('Force approve this leave application?')">Force Approve</a>
                                                <?php endif; ?>
                                                <a href="leave_management.php?action=reject_leave&id=<?php echo $leave['id']; ?>&tab=manage" 
                                                   class="btn btn-danger btn-sm" 
                                                   onclick="return confirm('Reject this leave application?')">Reject</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Approved Leaves -->
                    <div class="table-container mb-4">
                        <h4>Recently Approved Leaves</h4>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Leave Type</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Days</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($approvedLeaves)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No approved leaves found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($approvedLeaves as $leave): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($leave['employee_id'] . ' - ' . $leave['first_name'] . ' ' . $leave['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($leave['leave_type_name']); ?></td>
                                        <td><?php echo formatDate($leave['start_date']); ?></td>
                                        <td><?php echo formatDate($leave['end_date']); ?></td>
                                        <td><?php echo $leave['days_requested']; ?></td>
                                        <td><span class="badge badge-success">Approved</span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Rejected Leaves -->
                    <div class="table-container">
                        <h4>Recently Rejected Leaves</h4>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Leave Type</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Days</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($rejectedLeaves)): ?>
                                    <tr>
                                        <td colspan="6"class="text-center">No rejected leaves found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($rejectedLeaves as $leave): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($leave['employee_id'] . ' - ' . $leave['first_name'] . ' ' . $leave['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($leave['leave_type_name']); ?></td>
                                        <td><?php echo formatDate($leave['start_date']); ?></td>
                                        <td><?php echo formatDate($leave['end_date']); ?></td>
                                        <td><?php echo $leave['days_requested']; ?></td>
                                        <td><span class="badge badge-danger">Rejected</span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php elseif ($tab === 'history' && hasPermission('hr_manager')): ?>
                <!-- Leave History Tab -->
                <div class="tab-content">
                    <h3>Leave History</h3>
                    
                    <!-- Financial Year Filter -->
                    <div class="filter-section" style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
                        <strong>Filter by Financial Year:</strong>
                        <form method="GET" style="display: inline-block; margin-left: 10px;">
                            <input type="hidden" name="tab" value="history">
                            <select name="year" onchange="this.form.submit()" class="form-control" style="display: inline-block; width: 150px;">
                                <?php foreach ($availableYears as $year): ?>
                                    <option value="<?php echo $year; ?>" <?php echo ($year === $selectedYear) ? 'selected' : ''; ?>>
                                        <?php echo $year; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                        <span style="margin-left: 15px;">Viewing: <strong><?php echo $selectedYear; ?></strong></span>
                    </div>

                    <!-- Employees Currently on Leave -->
                    <div class="table-container mb-4">
                        <h4>Employees Currently on Leave</h4>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Leave Type</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Days</th>
                                    <th>Remaining Days</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($currentLeaves)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No employees currently on leave</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($currentLeaves as $leave): ?>
                                    <?php
                                        $today = new DateTime();
                                        $endDate = new DateTime($leave['end_date']);
                                        $remainingDays = $today->diff($endDate)->days;
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($leave['employee_id'] . ' - ' . $leave['first_name'] . ' ' . $leave['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($leave['leave_type_name']); ?></td>
                                        <td><?php echo formatDate($leave['start_date']); ?></td>
                                        <td><?php echo formatDate($leave['end_date']); ?></td>
                                        <td><?php echo $leave['days_requested']; ?></td>
                                        <td><?php echo $remainingDays; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- All Leave History -->
                    <div class="table-container">
                        <h4>All Leave Applications (Recent 50)</h4>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Leave Type</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Days</th>
                                    <th>Applied Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allLeaves as $leave): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($leave['employee_id'] . ' - ' . $leave['first_name'] . ' ' . $leave['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($leave['leave_type_name']); ?></td>
                                    <td><?php echo formatDate($leave['start_date']); ?></td>
                                    <td><?php echo formatDate($leave['end_date']); ?></td>
                                    <td><?php echo $leave['days_requested']; ?></td>
                                    <td><?php echo formatDate($leave['applied_at']); ?></td>
                                    <td>
                                        <?php
                                        $statusClass = [
                                            'pending' => 'badge-warning',
                                            'approved' => 'badge-success',
                                            'rejected' => 'badge-danger',
                                            'cancelled' => 'badge-secondary'
                                        ];
                                        ?>
                                        <span class="badge <?php echo $statusClass[$leave['status']] ?? 'badge-light'; ?>">
                                            <?php echo ucfirst($leave['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php elseif ($tab === 'holidays' && hasPermission('hr_manager')): ?>
                <!-- Holidays Management Content -->
                <div class="tab-content">
                    <h3>Manage Holidays</h3>
                    <form method="POST" action="" class="mb-4">
                        <input type="hidden" name="action" value="add_holiday">
                        <h4>Add New Holiday</h4>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="name">Holiday Name</label>
                                <input type="text" id="name" name="name" class="form-control" required>
                            </div>

                            <div class="form-group">
                                <label for="date">Date</label>
                                <input type="date" id="date" name="date" class="form-control" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" class="form-control"></textarea>
                        </div>

                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="is_recurring"> This is a recurring holiday
                            </label>
                        </div>

                        <button type="submit" class="btn btn-primary">Add Holiday</button>
                    </form>

                    <div class="table-container">
                        <h4>Current Holidays</h4>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Date</th>
                                    <th>Description</th>
                                    <th>Recurring</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($holidays)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No holidays found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($holidays as $holiday): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($holiday['name']); ?></td>
                                        <td><?php echo formatDate($holiday['date']); ?></td>
                                        <td><?php echo htmlspecialchars($holiday['description'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="badge <?php echo $holiday['is_recurring'] ? 'badge-success' : 'badge-secondary'; ?>">
                                                <?php echo $holiday['is_recurring'] ? 'Yes' : 'No'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="leave_management.php?action=delete_holiday&id=<?php echo $holiday['id']; ?>&tab=holidays" 
                                               class="btn btn-danger btn-sm" 
                                               onclick="return confirm('Are you sure you want to delete this holiday?')">Delete</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php elseif ($tab === 'profile'): ?>
                <!-- My Leave Profile Tab -->
                <div class="tab-content">
                    <h3>My Leave Profile</h3>
                    
                    <!-- Financial Year Filter -->
                    <div class="filter-section" style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
                        <strong>Financial Year:</strong>
                        <form method="GET" style="display: inline-block; margin-left: 10px;">
                            <input type="hidden" name="tab" value="profile">
                            <select name="year" onchange="this.form.submit()" class="form-control" style="display: inline-block; width: 150px;">
                                <?php foreach ($availableYears as $year): ?>
                                    <option value="<?php echo $year; ?>" <?php echo ($year === $selectedYear) ? 'selected' : ''; ?>>
                                        <?php echo $year; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                        <span style="margin-left: 15px;">Viewing: <strong><?php echo $selectedYear; ?></strong></span>
                    </div>

                    <?php if ($employee): ?>
                    <!-- Employee Information -->
                    <div class="employee-info mb-4">
                        <div class="form-grid">
                            <div>
                                <h4>Employee Information</h4>
                                <p><strong>Employee ID:</strong> <?php echo htmlspecialchars($employee['employee_id']); ?></p>
                                <p><strong>Name:</strong> <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></p>
                                <p><strong>Employment Type:</strong> <?php echo htmlspecialchars($employee['employment_type']); ?></p>
                                <p><strong>Department:</strong> <?php echo htmlspecialchars($employee['department_id'] ?? 'N/A'); ?></p>
                            </div>
                            <div>
                                <h4>Leave Balance (Current Year)</h4>
                                <?php if ($leaveBalance): ?>
                                    <p><strong>Annual Leave Entitled:</strong> <?php echo $leaveBalance['allocated'] ?? 0; ?> days</p>
                                    <p><strong>Annual Leave Used:</strong> <?php echo $leaveBalance['used'] ?? 0; ?> days</p>
                                    <p><strong>Annual Leave Balance:</strong> <span class="badge badge-info"><?php echo $leaveBalance['remaining'] ?? 0; ?> days</span></p>
                                <?php else: ?>
                                    <p class="text-muted">Leave balance not available. Please contact HR.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Leave History -->
                    <div class="table-container">
                        <h4>My Leave History</h4>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Leave Type</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Days</th>
                                    <th>Applied Date</th>
                                    <th>Status</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($leaveHistory)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center">No leave applications found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($leaveHistory as $leave): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($leave['leave_type_name']); ?></td>
                                        <td><?php echo formatDate($leave['start_date']); ?></td>
                                        <td><?php echo formatDate($leave['end_date']); ?></td>
                                        <td><?php echo $leave['days_requested']; ?></td>
                                        <td><?php echo formatDate($leave['applied_at']); ?></td>
                                        <td>
                                            <?php
                                            $statusClass = [
                                                'pending' => 'badge-warning',
                                                'approved' => 'badge-success',
                                                'rejected' => 'badge-danger',
                                                'cancelled' => 'badge-secondary'
                                            ];
                                            ?>
                                            <span class="badge <?php echo $statusClass[$leave['status']] ?? 'badge-light'; ?>">
                                                <?php echo ucfirst($leave['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars(substr($leave['reason'], 0, 50) . (strlen($leave['reason']) > 50 ? '...' : '')); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Quick Actions -->
                    <div class="action-buttons mt-4">
                        <a href="leave_management.php?tab=apply" class="btn btn-primary">Apply for New Leave</a>
                    </div>

                    <?php else: ?>
                    <div class="alert alert-warning">
                        Employee record not found. Please contact HR to resolve this issue.
                    </div>
                    <?php endif; ?>
                </div>

                <?php else: ?>
                <div class="tab-content">
                    <p>Access denied or content under development.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Calculate leave days when dates change
        document.addEventListener('DOMContentLoaded', function() {
            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');
            const calculatedDays = document.getElementById('calculated_days');

            if (startDateInput && endDateInput && calculatedDays) {
                function calculateDays() {
                    const leaveTypeSelect = document.getElementById('leave_type_id');
                    
                    if (startDateInput.value && endDateInput.value && leaveTypeSelect.value) {
                        const start = new Date(startDateInput.value);
                        const end = new Date(endDateInput.value);

                        if (end >= start) {
                            // Make AJAX call to calculate days based on leave type
                            fetch('leave_management.php?ajax=calculate_days', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: `start_date=${startDateInput.value}&end_date=${endDateInput.value}&leave_type_id=${leaveTypeSelect.value}`
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    calculatedDays.value = data.days + ' days';
                                    if (data.note) {
                                        calculatedDays.value += ' (' + data.note + ')';
                                    }
                                } else {
                                    calculatedDays.value = 'Error calculating days';
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                calculatedDays.value = 'Error calculating days';
                            });
                        } else {
                            calculatedDays.value = 'Invalid date range';
                        }
                    } else {
                        calculatedDays.value = '';
                    }
                }

                startDateInput.addEventListener('change', calculateDays);
                endDateInput.addEventListener('change', calculateDays);
                
                // Also recalculate when leave type changes
                const leaveTypeSelect = document.getElementById('leave_type_id');
                const leaveTypeInfo = document.getElementById('leave_type_info');
                const leaveTypeDescription = document.getElementById('leave_type_description');
                
                if (leaveTypeSelect) {
                    leaveTypeSelect.addEventListener('change', function() {
                        calculateDays();
                        
                        // Show leave type information
                        const selectedOption = leaveTypeSelect.options[leaveTypeSelect.selectedIndex];
                        if (selectedOption.value) {
                            const countsWeekends = selectedOption.getAttribute('data-counts-weekends') === '1';
                            const description = selectedOption.getAttribute('data-description');
                            
                            let infoText = description;
                            if (countsWeekends) {
                                infoText += ' • <strong>Note:</strong> This leave type includes weekends and holidays in the calculation.';
                            } else {
                                infoText += ' • <strong>Note:</strong> This leave type excludes weekends and holidays from the calculation.';
                            }
                            
                            leaveTypeDescription.innerHTML = infoText;
                            leaveTypeInfo.style.display = 'block';
                        } else {
                            leaveTypeInfo.style.display = 'none';
                        }
                    });
                }
            }

            // Set minimum date to today for leave applications
            const today = new Date().toISOString().split('T')[0];
            if (startDateInput) {
                startDateInput.min = today;
            }
            if (endDateInput) {
                endDateInput.min = today;
            }
        });
    </script>

</body>
</html>
<?php
//Get the leave_management_handler
include 'leave_management_handler.php';

// Handle GET actions for department head approval/rejection
if (isset($_GET['action'])) {
    $action = $_GET['action'];

    if ($action === 'dept_head_approve' && isset($_GET['id']) && hasPermission('dept_head')) {
        $leaveId = (int)$_GET['id'];

        try {
            $conn->begin_transaction();

            // Get application details
            $stmt = $conn->prepare("SELECT * FROM leave_applications WHERE id = ?");
            $stmt->bind_param("i", $leaveId);
            $stmt->execute();
            $application = $stmt->get_result()->fetch_assoc();

            // Get current user's employee record
            $userEmpQuery = "SELECT id FROM employees WHERE employee_id = (SELECT employee_id FROM users WHERE id = ?)";
            $stmt = $conn->prepare($userEmpQuery);
            $stmt->bind_param("s", $user['id']);
            $stmt->execute();
            $userEmpRecord = $stmt->get_result()->fetch_assoc();

            if ($userEmpRecord && $application && $application['dept_head_emp_id'] == $userEmpRecord['id']) {
                // Update application status
                $stmt = $conn->prepare("UPDATE leave_applications SET dept_head_approved = 1, dept_head_approver_id = ? WHERE id = ?");
                $stmt->bind_param("ii", $userEmpRecord['id'], $leaveId);
                $stmt->execute();

                $conn->commit();
                $_SESSION['flash_message'] = "Leave application approved successfully!";
                $_SESSION['flash_type'] = "success";
            } else {
                $conn->rollback();
                $_SESSION['flash_message'] = "You are not authorized to approve this leave application.";
                $_SESSION['flash_type'] = "danger";
            }
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['flash_message'] = "Database error: " . $e->getMessage();
            $_SESSION['flash_type'] = "danger";
        }

        header("Location: leave_management.php?tab=manage");
        exit();
    }

    if ($action === 'dept_head_reject' && isset($_GET['id']) && hasPermission('dept_head')) {
        $leaveId = (int)$_GET['id'];

        try {
            $conn->begin_transaction();

            // Get application details
            $stmt = $conn->prepare("SELECT * FROM leave_applications WHERE id = ?");
            $stmt->bind_param("i", $leaveId);
            $stmt->execute();
            $application = $stmt->get_result()->fetch_assoc();

            // Get current user's employee record
           $userEmpQuery = "SELECT id FROM employees WHERE employee_id = (SELECT employee_id FROM users WHERE id = ?)";
            $stmt = $conn->prepare($userEmpQuery);
            $stmt->bind_param("s", $user['id']);
            $stmt->execute();
            $userEmployee = $stmt->get_result()->fetch_assoc();

            if ($userEmployee && $application && $application['dept_head_emp_id'] == $userEmployee['id']) {
                // Update application status
                $stmt = $conn->prepare("UPDATE leave_applications SET dept_head_approved = 0, dept_head_approver_id = ? WHERE id = ?");
                $stmt->bind_param("ii", $userEmployee['id'], $leaveId);
                $stmt->execute();

                $conn->commit();
                $_SESSION['flash_message'] = "Leave application rejected!";
                $_SESSION['flash_type'] = "warning";
            } else {
                $conn->rollback();
                $_SESSION['flash_message'] = "You are not authorized to reject this leave application.";
                $_SESSION['flash_type'] = "danger";
            }
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['flash_message'] = "Database error: " . $e->getMessage();
            $_SESSION['flash_type'] = "danger";
        }

        header("Location: leave_management.php?tab=manage");
        exit();
    }
}

?>