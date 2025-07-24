<?php
/**
 * Test Script for Annual Leave Award System
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
require_once 'annual_leave_award.php';

echo "=== Annual Leave Award System Test ===\n\n";

try {
    $conn = getConnection();
    
    // Test 1: Check if we can get current financial year
    echo "Test 1: Current Financial Year\n";
    $currentFY = getCurrentFinancialYear();
    echo "Current Financial Year: $currentFY\n\n";
    
    // Test 2: Check if we can identify permanent employees
    echo "Test 2: Permanent Employees Check\n";
    $result = $conn->query("SELECT COUNT(*) as count FROM employees WHERE employment_type = 'permanent' AND employee_status = 'active'");
    if ($result) {
        $count = $result->fetch_assoc()['count'];
        echo "Found $count permanent employees\n\n";
    } else {
        echo "Error checking permanent employees: " . $conn->error . "\n\n";
    }
    
    // Test 3: Check leave_balances table structure
    echo "Test 3: Leave Balances Table Structure\n";
    $result = $conn->query("DESCRIBE leave_balances");
    if ($result) {
        echo "Leave balances table exists with columns:\n";
        while ($row = $result->fetch_assoc()) {
            echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
        }
        echo "\n";
    } else {
        echo "Error: leave_balances table not found\n\n";
    }
    
    // Test 4: Check if annual_leave_award_logs table exists
    echo "Test 4: Annual Leave Award Logs Table\n";
    $result = $conn->query("SHOW TABLES LIKE 'annual_leave_award_logs'");
    if ($result && $result->num_rows > 0) {
        echo "✓ annual_leave_award_logs table exists\n";
        
        // Show structure
        $result = $conn->query("DESCRIBE annual_leave_award_logs");
        if ($result) {
            echo "Table structure:\n";
            while ($row = $result->fetch_assoc()) {
                echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
            }
        }
    } else {
        echo "✗ annual_leave_award_logs table does not exist\n";
        echo "Please run the database updates first\n";
    }
    echo "\n";
    
    // Test 5: Dry run of award process
    echo "Test 5: Dry Run Award Process\n";
    echo "This would award leave to permanent employees for financial year: $currentFY\n";
    
    $result = $conn->query("
        SELECT e.id, e.employee_id, e.first_name, e.last_name, e.employment_type, e.hire_date
        FROM employees e 
        WHERE e.employment_type = 'permanent' 
        AND e.employee_status = 'active'
        LIMIT 5
    ");
    
    if ($result && $result->num_rows > 0) {
        echo "Sample employees who would receive awards:\n";
        while ($row = $result->fetch_assoc()) {
            echo "- {$row['employee_id']}: {$row['first_name']} {$row['last_name']} (hired: {$row['hire_date']})\n";
        }
    } else {
        echo "No permanent employees found\n";
    }
    
    $conn->close();
    echo "\n=== Test Completed ===\n";
    
} catch (Exception $e) {
    echo "Error during testing: " . $e->getMessage() . "\n";
}
?>