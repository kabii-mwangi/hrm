<?php
/**
 * AJAX endpoint for calculating leave days
 * Excludes weekends and holidays except for maternity leave
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

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

// Helper functions for leave calculation
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

function calculateCalendarDays($startDate, $endDate) {
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    
    $interval = $start->diff($end);
    return $interval->days + 1; // +1 to include both start and end dates
}

function calculateLeaveDays($startDate, $endDate, $leaveTypeId, $conn) {
    // Get leave type settings
    $leaveTypeQuery = "SELECT name, counts_weekends FROM leave_types WHERE id = ?";
    $stmt = $conn->prepare($leaveTypeQuery);
    $stmt->bind_param("i", $leaveTypeId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($leaveType = $result->fetch_assoc()) {
        $countsWeekends = (bool)$leaveType['counts_weekends'];
        $leaveTypeName = $leaveType['name'];
        
        // If leave type counts weekends (like maternity leave), include weekends and holidays
        if ($countsWeekends) {
            $days = calculateCalendarDays($startDate, $endDate);
            $note = "Includes weekends and holidays";
        } else {
            // For other leave types, exclude weekends and holidays
            $days = calculateBusinessDays($startDate, $endDate, $conn, false);
            $note = "Excludes weekends and holidays";
        }
        
        return [
            'days' => $days,
            'note' => $note,
            'leave_type' => $leaveTypeName
        ];
    }
    
    // Default to business days if leave type not found
    $days = calculateBusinessDays($startDate, $endDate, $conn, false);
    return [
        'days' => $days,
        'note' => "Excludes weekends and holidays",
        'leave_type' => 'Unknown'
    ];
}

// Handle POST request
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
?>