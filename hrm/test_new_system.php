<?php
/**
 * Simple test for the new annual leave system
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
require_once 'annual_leave_award_new.php';

echo "=== Testing New Annual Leave System ===\n\n";

try {
    $conn = getConnection();
    echo "✓ Database connection successful\n";
    
    // Test getCurrentFinancialYear function
    $currentFY = getCurrentFinancialYear();
    echo "✓ Current Financial Year: $currentFY\n";
    
    // Test getAvailableFinancialYears function
    $availableYears = getAvailableFinancialYears($conn);
    echo "✓ Available Financial Years: " . implode(', ', $availableYears) . "\n";
    
    // Test getFinancialYearStats function
    $stats = getFinancialYearStats($conn, $currentFY);
    echo "✓ Stats for $currentFY:\n";
    echo "  - Employees with leave: " . ($stats['employees_with_leave'] ?? 0) . "\n";
    echo "  - Total entitled: " . ($stats['total_entitled'] ?? 0) . "\n";
    echo "  - Total used: " . ($stats['total_used'] ?? 0) . "\n";
    echo "  - Total balance: " . ($stats['total_balance'] ?? 0) . "\n";
    
    // Check if annual_leave_award_logs table exists
    $result = $conn->query("SHOW TABLES LIKE 'annual_leave_award_logs'");
    if ($result && $result->num_rows > 0) {
        echo "✓ annual_leave_award_logs table exists\n";
    } else {
        echo "✗ annual_leave_award_logs table does not exist - need to run database updates\n";
    }
    
    $conn->close();
    echo "\n=== Test completed successfully ===\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>