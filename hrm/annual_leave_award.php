<?php
/**
 * Annual Leave Award System
 * Awards 30 days of annual leave to permanently employed employees at the beginning of each financial year (July)
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

/**
 * Get the current financial year
 * Financial year starts in July
 */
function getCurrentFinancialYear() {
    $currentMonth = date('n'); // 1-12
    $currentYear = date('Y');
    
    if ($currentMonth >= 7) {
        // July onwards - current financial year
        return $currentYear . '-' . ($currentYear + 1);
    } else {
        // January to June - previous financial year
        return ($currentYear - 1) . '-' . $currentYear;
    }
}

/**
 * Check if it's the beginning of the financial year (July)
 */
function isBeginningOfFinancialYear() {
    $currentMonth = date('n');
    $currentDay = date('j');
    
    // Check if it's July 1st or within the first week of July
    return ($currentMonth == 7 && $currentDay <= 7);
}

/**
 * Award annual leave to permanently employed employees
 */
function awardAnnualLeave($conn, $force = false) {
    $currentFinancialYear = getCurrentFinancialYear();
    $awardedCount = 0;
    $errors = [];
    
    try {
        // Get all permanently employed active employees
        $employeeQuery = "SELECT id, employee_id, first_name, last_name, hire_date 
                         FROM employees 
                         WHERE employment_type = 'permanent' 
                         AND employee_status = 'active'";
        
        $employeeResult = $conn->query($employeeQuery);
        
        if (!$employeeResult) {
            throw new Exception("Error fetching employees: " . $conn->error);
        }
        
        // Get Annual Leave type ID
        $leaveTypeQuery = "SELECT id FROM leave_types WHERE name = 'Annual Leave' LIMIT 1";
        $leaveTypeResult = $conn->query($leaveTypeQuery);
        
        if (!$leaveTypeResult || $leaveTypeResult->num_rows == 0) {
            throw new Exception("Annual Leave type not found in leave_types table");
        }
        
        $leaveType = $leaveTypeResult->fetch_assoc();
        $annualLeaveTypeId = $leaveType['id'];
        
        while ($employee = $employeeResult->fetch_assoc()) {
            try {
                // Check if employee already has leave balance for current financial year
                $existingBalanceQuery = "SELECT id FROM leave_balances 
                                       WHERE employee_id = ? 
                                       AND financial_year = ? 
                                       AND leave_type_id = ?";
                
                $stmt = $conn->prepare($existingBalanceQuery);
                $stmt->bind_param("isi", $employee['id'], $currentFinancialYear, $annualLeaveTypeId);
                $stmt->execute();
                $existingResult = $stmt->get_result();
                
                if ($existingResult->num_rows > 0 && !$force) {
                    // Employee already has leave balance for this financial year
                    continue;
                }
                
                // Calculate days to award (30 days for full year, pro-rated if hired during the year)
                $daysToAward = 30;
                $hireDate = new DateTime($employee['hire_date']);
                $financialYearStart = new DateTime(explode('-', $currentFinancialYear)[0] . '-07-01');
                
                // If employee was hired after the start of financial year, pro-rate the leave
                if ($hireDate > $financialYearStart) {
                    $financialYearEnd = new DateTime(explode('-', $currentFinancialYear)[1] . '-06-30');
                    $totalDaysInYear = $financialYearStart->diff($financialYearEnd)->days + 1;
                    $remainingDaysInYear = $hireDate->diff($financialYearEnd)->days + 1;
                    $daysToAward = round(($remainingDaysInYear / $totalDaysInYear) * 30);
                }
                
                if ($existingResult->num_rows > 0) {
                    // Update existing record
                    $updateQuery = "UPDATE leave_balances 
                                  SET annual_leave_entitled = ?, 
                                      annual_leave_balance = annual_leave_entitled - annual_leave_used,
                                      updated_at = CURRENT_TIMESTAMP
                                  WHERE employee_id = ? 
                                  AND financial_year = ? 
                                  AND leave_type_id = ?";
                    
                    $stmt = $conn->prepare($updateQuery);
                    $stmt->bind_param("iisi", $daysToAward, $employee['id'], $currentFinancialYear, $annualLeaveTypeId);
                } else {
                    // Insert new record
                    $insertQuery = "INSERT INTO leave_balances 
                                  (employee_id, financial_year, leave_type_id, annual_leave_entitled, 
                                   annual_leave_used, annual_leave_balance) 
                                  VALUES (?, ?, ?, ?, 0, ?)";
                    
                    $stmt = $conn->prepare($insertQuery);
                    $stmt->bind_param("isiii", $employee['id'], $currentFinancialYear, $annualLeaveTypeId, $daysToAward, $daysToAward);
                }
                
                if ($stmt->execute()) {
                    $awardedCount++;
                    echo "Awarded {$daysToAward} days to {$employee['first_name']} {$employee['last_name']} (ID: {$employee['employee_id']})\n";
                } else {
                    $errors[] = "Failed to award leave to {$employee['first_name']} {$employee['last_name']}: " . $stmt->error;
                }
                
            } catch (Exception $e) {
                $errors[] = "Error processing employee {$employee['first_name']} {$employee['last_name']}: " . $e->getMessage();
            }
        }
        
        // Log the award process
        $logQuery = "INSERT INTO leave_award_log (financial_year, employees_processed, employees_awarded, run_date, run_by) 
                    VALUES (?, ?, ?, NOW(), 'system')";
        
        // Create the log table if it doesn't exist
        $createLogTableQuery = "CREATE TABLE IF NOT EXISTS leave_award_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            financial_year VARCHAR(10) NOT NULL,
            employees_processed INT NOT NULL,
            employees_awarded INT NOT NULL,
            run_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            run_by VARCHAR(50) DEFAULT 'system',
            notes TEXT
        )";
        
        $conn->query($createLogTableQuery);
        
        $stmt = $conn->prepare($logQuery);
        $totalProcessed = $employeeResult->num_rows;
        $stmt->bind_param("sii", $currentFinancialYear, $totalProcessed, $awardedCount);
        $stmt->execute();
        
        return [
            'success' => true,
            'awarded_count' => $awardedCount,
            'total_processed' => $totalProcessed,
            'financial_year' => $currentFinancialYear,
            'errors' => $errors
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'awarded_count' => $awardedCount,
            'errors' => $errors
        ];
    }
}

/**
 * Check and award annual leave if it's the beginning of financial year
 */
function checkAndAwardAnnualLeave($conn, $force = false) {
    if (!$force && !isBeginningOfFinancialYear()) {
        return [
            'success' => false,
            'message' => 'Not the beginning of financial year. Annual leave is awarded in July.',
            'current_date' => date('Y-m-d'),
            'financial_year' => getCurrentFinancialYear()
        ];
    }
    
    echo "Starting annual leave award process for financial year: " . getCurrentFinancialYear() . "\n";
    echo "Date: " . date('Y-m-d H:i:s') . "\n\n";
    
    $result = awardAnnualLeave($conn, $force);
    
    if ($result['success']) {
        echo "\n=== ANNUAL LEAVE AWARD COMPLETED ===\n";
        echo "Financial Year: {$result['financial_year']}\n";
        echo "Total Employees Processed: {$result['total_processed']}\n";
        echo "Employees Awarded Leave: {$result['awarded_count']}\n";
        
        if (!empty($result['errors'])) {
            echo "\nErrors encountered:\n";
            foreach ($result['errors'] as $error) {
                echo "- $error\n";
            }
        }
    } else {
        echo "\n=== ANNUAL LEAVE AWARD FAILED ===\n";
        echo "Error: {$result['error']}\n";
        
        if (!empty($result['errors'])) {
            echo "\nAdditional errors:\n";
            foreach ($result['errors'] as $error) {
                echo "- $error\n";
            }
        }
    }
    
    return $result;
}

// If this script is run directly
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    $conn = getConnection();
    
    // Check for command line arguments
    $force = false;
    if (isset($argv) && in_array('--force', $argv)) {
        $force = true;
        echo "Force mode enabled - will award leave regardless of date\n\n";
    }
    
    $result = checkAndAwardAnnualLeave($conn, $force);
    
    $conn->close();
    
    // Exit with appropriate code
    exit($result['success'] ? 0 : 1);
}
?>