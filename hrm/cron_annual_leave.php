#!/usr/bin/env php
<?php
/**
 * Cron Job Script for Annual Leave Award
 * 
 * This script should be scheduled to run daily during the first week of July
 * to automatically award 30 days of annual leave to permanently employed employees.
 * 
 * Cron schedule example (runs daily at 6 AM during July 1-7):
 * 0 6 1-7 7 * /usr/bin/php /path/to/hrm/cron_annual_leave.php
 * 
 * Or to run only on July 1st at 6 AM:
 * 0 6 1 7 * /usr/bin/php /path/to/hrm/cron_annual_leave.php
 */

// Ensure this script is only run from command line
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line.');
}

// Set error reporting for cron environment
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Change to the script directory
chdir(dirname(__FILE__));

require_once 'config.php';
require_once 'annual_leave_award.php';

/**
 * Log messages to both console and log file
 */
function logMessage($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
    
    // Output to console
    echo $logMessage;
    
    // Log to file
    $logFile = 'logs/annual_leave_cron.log';
    
    // Create logs directory if it doesn't exist
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

/**
 * Send notification email to HR managers
 */
function sendNotificationEmail($conn, $result) {
    try {
        // Get HR managers' email addresses
        $hrQuery = "SELECT u.email, u.first_name, u.last_name 
                   FROM users u 
                   WHERE u.role IN ('hr_manager', 'super_admin') 
                   AND u.email IS NOT NULL AND u.email != ''";
        
        $hrResult = $conn->query($hrQuery);
        
        if ($hrResult && $hrResult->num_rows > 0) {
            $subject = "Annual Leave Award Process Completed - " . $result['financial_year'];
            
            $message = "Dear HR Team,\n\n";
            $message .= "The annual leave award process has been completed for financial year {$result['financial_year']}.\n\n";
            $message .= "Summary:\n";
            $message .= "- Total Employees Processed: {$result['total_processed']}\n";
            $message .= "- Employees Awarded Leave: {$result['awarded_count']}\n";
            $message .= "- Date: " . date('Y-m-d H:i:s') . "\n\n";
            
            if (!empty($result['errors'])) {
                $message .= "Errors encountered:\n";
                foreach ($result['errors'] as $error) {
                    $message .= "- $error\n";
                }
                $message .= "\n";
            }
            
            $message .= "Please log into the HR system to review the award details.\n\n";
            $message .= "Best regards,\n";
            $message .= "HR Management System";
            
            $headers = "From: noreply@company.com\r\n";
            $headers .= "Reply-To: hr@company.com\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion();
            
            while ($hr = $hrResult->fetch_assoc()) {
                $personalizedMessage = str_replace(
                    "Dear HR Team,", 
                    "Dear {$hr['first_name']} {$hr['last_name']},", 
                    $message
                );
                
                if (mail($hr['email'], $subject, $personalizedMessage, $headers)) {
                    logMessage("Notification email sent to {$hr['email']}");
                } else {
                    logMessage("Failed to send notification email to {$hr['email']}", 'ERROR');
                }
            }
        }
    } catch (Exception $e) {
        logMessage("Error sending notification emails: " . $e->getMessage(), 'ERROR');
    }
}

/**
 * Create system notification
 */
function createSystemNotification($conn, $result) {
    try {
        // Check if notifications table exists
        $checkTable = "SHOW TABLES LIKE 'notifications'";
        $tableResult = $conn->query($checkTable);
        
        if ($tableResult && $tableResult->num_rows > 0) {
            $title = "Annual Leave Award Completed";
            $message = "Annual leave has been awarded to {$result['awarded_count']} employees for financial year {$result['financial_year']}.";
            $type = $result['success'] ? 'success' : 'error';
            
            $notificationQuery = "INSERT INTO notifications (title, message, type, created_at) VALUES (?, ?, ?, NOW())";
            $stmt = $conn->prepare($notificationQuery);
            $stmt->bind_param("sss", $title, $message, $type);
            
            if ($stmt->execute()) {
                logMessage("System notification created successfully");
            } else {
                logMessage("Failed to create system notification: " . $stmt->error, 'ERROR');
            }
        }
    } catch (Exception $e) {
        logMessage("Error creating system notification: " . $e->getMessage(), 'ERROR');
    }
}

// Main execution
try {
    logMessage("=== ANNUAL LEAVE AWARD CRON JOB STARTED ===");
    logMessage("Current date: " . date('Y-m-d'));
    logMessage("Current financial year: " . getCurrentFinancialYear());
    
    // Connect to database
    $conn = getConnection();
    if (!$conn) {
        throw new Exception("Failed to connect to database");
    }
    
    logMessage("Database connection established");
    
    // Check if it's the appropriate time to award annual leave
    if (!isBeginningOfFinancialYear()) {
        logMessage("Not the beginning of financial year. Skipping award process.");
        logMessage("Annual leave is awarded during the first week of July.");
        exit(0);
    }
    
    logMessage("Beginning of financial year detected. Starting award process...");
    
    // Award annual leave
    $result = awardAnnualLeave($conn, false);
    
    if ($result['success']) {
        logMessage("=== ANNUAL LEAVE AWARD COMPLETED SUCCESSFULLY ===");
        logMessage("Financial Year: {$result['financial_year']}");
        logMessage("Total Employees Processed: {$result['total_processed']}");
        logMessage("Employees Awarded Leave: {$result['awarded_count']}");
        
        // Send notifications
        sendNotificationEmail($conn, $result);
        createSystemNotification($conn, $result);
        
        if (!empty($result['errors'])) {
            logMessage("Errors encountered during processing:", 'WARNING');
            foreach ($result['errors'] as $error) {
                logMessage("- $error", 'WARNING');
            }
        }
        
        logMessage("Cron job completed successfully");
        exit(0);
        
    } else {
        logMessage("=== ANNUAL LEAVE AWARD FAILED ===", 'ERROR');
        logMessage("Error: " . ($result['error'] ?? 'Unknown error'), 'ERROR');
        
        if (!empty($result['errors'])) {
            logMessage("Additional errors:", 'ERROR');
            foreach ($result['errors'] as $error) {
                logMessage("- $error", 'ERROR');
            }
        }
        
        // Send error notification
        $result['success'] = false; // Ensure it's marked as failed
        sendNotificationEmail($conn, $result);
        createSystemNotification($conn, $result);
        
        logMessage("Cron job failed");
        exit(1);
    }
    
} catch (Exception $e) {
    logMessage("Fatal error in cron job: " . $e->getMessage(), 'ERROR');
    logMessage("Stack trace: " . $e->getTraceAsString(), 'ERROR');
    exit(1);
    
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
        logMessage("Database connection closed");
    }
    
    logMessage("=== ANNUAL LEAVE AWARD CRON JOB ENDED ===");
}
?>