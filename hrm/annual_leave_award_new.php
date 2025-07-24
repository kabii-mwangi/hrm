<?php
/**
 * Annual Leave Award System - Manual Financial Year Control
 * Awards 30 days of annual leave to permanently employed employees when starting a new financial year
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

/**
 * Get the current financial year based on date
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
 * Get all available financial years from the database
 */
function getAvailableFinancialYears($conn) {
    $query = "SELECT DISTINCT financial_year FROM leave_balances ORDER BY financial_year DESC";
    $result = $conn->query($query);
    
    $years = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $years[] = $row['financial_year'];
        }
    }
    
    return $years;
}

/**
 * Start a new financial year and award annual leave to all permanent employees
 */
function startNewFinancialYear($conn, $financial_year, $awarded_by_user_id) {
    $result = [
        'success' => false,
        'message' => '',
        'awarded_count' => 0,
        'financial_year' => $financial_year,
        'details' => []
    ];
    
    try {
        // Validate financial year format (e.g., 2024-2025)
        if (!preg_match('/^\d{4}-\d{4}$/', $financial_year)) {
            throw new Exception("Invalid financial year format. Use format: YYYY-YYYY (e.g., 2024-2025)");
        }
        
        // Check if this financial year already exists
        $checkQuery = "SELECT COUNT(*) as count FROM leave_balances WHERE financial_year = ?";
        $stmt = $conn->prepare($checkQuery);
        $stmt->bind_param("s", $financial_year);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc()['count'];
        
        if ($exists > 0) {
            throw new Exception("Financial year {$financial_year} has already been started. Leave balances already exist for this period.");
        }
        
        // Award leave to all permanent employees
        $awardResult = awardAnnualLeaveForYear($conn, $financial_year, $awarded_by_user_id);
        
        if ($awardResult['success']) {
            $result['success'] = true;
            $result['awarded_count'] = $awardResult['awarded_count'];
            $result['message'] = "Successfully started financial year {$financial_year} and awarded annual leave to {$awardResult['awarded_count']} permanent employees.";
            $result['details'] = $awardResult['details'];
        } else {
            throw new Exception($awardResult['message']);
        }
        
    } catch (Exception $e) {
        $result['message'] = $e->getMessage();
    }
    
    return $result;
}

/**
 * Award annual leave to permanently employed employees for a specific financial year
 */
function awardAnnualLeaveForYear($conn, $financial_year, $awarded_by_user_id) {
    $awardedCount = 0;
    $errors = [];
    $details = [];
    
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
        
        // Parse financial year to get start and end dates
        $years = explode('-', $financial_year);
        $financialYearStart = new DateTime($years[0] . '-07-01');
        $financialYearEnd = new DateTime($years[1] . '-06-30');
        
        while ($employee = $employeeResult->fetch_assoc()) {
            try {
                // Calculate days to award (30 days for full year, pro-rated if hired during the year)
                $daysToAward = 30;
                $awardType = 'full';
                $calculationDetails = "Full year award: 30 days";
                
                $hireDate = new DateTime($employee['hire_date']);
                
                // If employee was hired after the start of financial year, pro-rate the leave
                if ($hireDate > $financialYearStart) {
                    $totalDaysInYear = $financialYearStart->diff($financialYearEnd)->days + 1;
                    $remainingDaysInYear = $hireDate->diff($financialYearEnd)->days + 1;
                    $daysToAward = max(1, round(($remainingDaysInYear / $totalDaysInYear) * 30));
                    $awardType = 'prorated';
                    $calculationDetails = "Pro-rated for hire date {$employee['hire_date']}: {$remainingDaysInYear}/{$totalDaysInYear} days × 30 = {$daysToAward} days";
                }
                
                // Insert leave balance record
                $insertQuery = "INSERT INTO leave_balances 
                              (employee_id, financial_year, leave_type_id, annual_leave_entitled, 
                               annual_leave_used, annual_leave_balance) 
                              VALUES (?, ?, ?, ?, 0, ?)";
                
                $stmt = $conn->prepare($insertQuery);
                $stmt->bind_param("isiii", $employee['id'], $financial_year, $annualLeaveTypeId, $daysToAward, $daysToAward);
                
                if ($stmt->execute()) {
                    // Log the award
                    $logQuery = "INSERT INTO annual_leave_award_logs 
                               (employee_id, financial_year, days_awarded, award_type, calculation_details, 
                                awarded_by, award_method, notes) 
                               VALUES (?, ?, ?, ?, ?, ?, 'manual', 'Financial year started manually')";
                    
                    $logStmt = $conn->prepare($logQuery);
                    $logStmt->bind_param("isissi", $employee['id'], $financial_year, $daysToAward, 
                                       $awardType, $calculationDetails, $awarded_by_user_id);
                    $logStmt->execute();
                    
                    $awardedCount++;
                    $details[] = [
                        'employee_id' => $employee['employee_id'],
                        'name' => $employee['first_name'] . ' ' . $employee['last_name'],
                        'days_awarded' => $daysToAward,
                        'award_type' => $awardType,
                        'hire_date' => $employee['hire_date']
                    ];
                    
                    echo "Awarded {$daysToAward} days to {$employee['first_name']} {$employee['last_name']} (ID: {$employee['employee_id']})\n";
                } else {
                    $errors[] = "Failed to award leave to {$employee['first_name']} {$employee['last_name']}: " . $stmt->error;
                }
                
            } catch (Exception $e) {
                $errors[] = "Error processing employee {$employee['first_name']} {$employee['last_name']}: " . $e->getMessage();
            }
        }
        
        return [
            'success' => true,
            'awarded_count' => $awardedCount,
            'total_processed' => $employeeResult->num_rows,
            'financial_year' => $financial_year,
            'errors' => $errors,
            'details' => $details
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage(),
            'awarded_count' => $awardedCount,
            'errors' => $errors,
            'details' => $details
        ];
    }
}

/**
 * Get financial year statistics
 */
function getFinancialYearStats($conn, $financial_year = null) {
    if (!$financial_year) {
        $financial_year = getCurrentFinancialYear();
    }
    
    $statsQuery = "SELECT 
        COUNT(DISTINCT lb.employee_id) as employees_with_leave,
        SUM(lb.annual_leave_entitled) as total_entitled,
        SUM(lb.annual_leave_used) as total_used,
        SUM(lb.annual_leave_balance) as total_balance
    FROM leave_balances lb 
    JOIN leave_types lt ON lb.leave_type_id = lt.id 
    WHERE lb.financial_year = ? AND lt.name = 'Annual Leave'";
    
    $stmt = $conn->prepare($statsQuery);
    $stmt->bind_param("s", $financial_year);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    
    return $stats;
}

/**
 * Get leave balances for a specific financial year
 */
function getLeaveBalancesForYear($conn, $financial_year) {
    $balancesQuery = "SELECT 
        e.employee_id, e.first_name, e.last_name, e.hire_date,
        lb.annual_leave_entitled, lb.annual_leave_used, lb.annual_leave_balance,
        d.name as department_name,
        s.name as section_name
    FROM leave_balances lb
    JOIN employees e ON lb.employee_id = e.id
    JOIN leave_types lt ON lb.leave_type_id = lt.id
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN sections s ON e.section_id = s.id
    WHERE lb.financial_year = ? AND lt.name = 'Annual Leave'
    ORDER BY e.first_name, e.last_name";
    
    $stmt = $conn->prepare($balancesQuery);
    $stmt->bind_param("s", $financial_year);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get award history for a specific financial year
 */
function getAwardHistoryForYear($conn, $financial_year) {
    $historyQuery = "SELECT 
        all_logs.employee_id, all_logs.financial_year, all_logs.days_awarded, 
        all_logs.award_type, all_logs.calculation_details, all_logs.awarded_at,
        e.employee_id as emp_id, e.first_name, e.last_name
    FROM annual_leave_award_logs all_logs
    JOIN employees e ON all_logs.employee_id = e.id
    WHERE all_logs.financial_year = ?
    ORDER BY all_logs.awarded_at DESC";
    
    $stmt = $conn->prepare($historyQuery);
    $stmt->bind_param("s", $financial_year);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>