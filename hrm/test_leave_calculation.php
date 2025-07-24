<?php
/**
 * Test script for leave days calculation
 * Tests that weekends and holidays are excluded except for maternity leave
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

// Helper functions (copied from leave_management.php)
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

echo "=== Leave Days Calculation Test ===\n\n";

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
        ],
        [
            'description' => 'Two weeks including weekends',
            'start_date' => '2024-07-22', // Monday
            'end_date' => '2024-08-02',   // Friday (2 weeks later)
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
    
    // Check for holidays in the system
    echo "Holidays in System:\n";
    $holidaysQuery = "SELECT name, date FROM holidays ORDER BY date";
    $result = $conn->query($holidaysQuery);
    if ($result && $result->num_rows > 0) {
        while ($holiday = $result->fetch_assoc()) {
            echo "- {$holiday['name']}: {$holiday['date']}\n";
        }
    } else {
        echo "No holidays configured in the system.\n";
    }
    
    $conn->close();
    echo "\n=== Test Completed ===\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>