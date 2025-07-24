# Annual Leave Award System

## Overview

The Annual Leave Award System automatically awards 30 days of annual leave to permanently employed employees at the beginning of each financial year (July). The system is designed to handle pro-rated leave for employees hired during the financial year and prevents duplicate awards.

## Features

- **Automatic Award**: Awards 30 days of annual leave to permanent employees
- **Pro-rated Calculation**: New employees receive leave proportional to their remaining time in the financial year
- **Duplicate Prevention**: Prevents awarding leave multiple times for the same financial year
- **Manual Override**: HR managers can force award leave regardless of date
- **Comprehensive Logging**: Tracks all award processes with detailed logs
- **Email Notifications**: Sends notifications to HR managers after award completion
- **Web Interface**: User-friendly interface for HR managers to manage the process

## Financial Year

The system operates on a July-to-June financial year:
- **Financial Year Start**: July 1st
- **Financial Year End**: June 30th
- **Award Period**: First week of July (July 1-7)

## Files Structure

```
hrm/
├── annual_leave_award.php          # Core award logic and functions
├── annual_leave_management.php     # Web interface for HR managers
├── cron_annual_leave.php          # Cron job script for automation
├── logs/
│   └── annual_leave_cron.log      # Cron job execution logs
└── ANNUAL_LEAVE_SYSTEM.md         # This documentation
```

## Database Tables

### Modified Tables

#### `leave_balances`
- Stores annual leave entitlements and balances
- Uses `financial_year` field in format "YYYY-YYYY" (e.g., "2025-2026")
- Links to employees and leave types

#### `employees`
- Uses `employment_type` field to identify permanent employees
- Uses `employee_status` to filter active employees
- Uses `hire_date` for pro-rated calculations

### New Tables

#### `leave_award_log`
```sql
CREATE TABLE leave_award_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    financial_year VARCHAR(10) NOT NULL,
    employees_processed INT NOT NULL,
    employees_awarded INT NOT NULL,
    run_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    run_by VARCHAR(50) DEFAULT 'system',
    notes TEXT
);
```

## Usage

### 1. Web Interface (Recommended for HR Managers)

1. Navigate to `annual_leave_management.php`
2. View current statistics and employee leave balances
3. Click "Award Annual Leave" during July or "Force Award Annual Leave" anytime
4. Review the award results and any errors

### 2. Command Line (Manual Execution)

```bash
# Award leave during appropriate time period (July 1-7)
php annual_leave_award.php

# Force award leave regardless of date
php annual_leave_award.php --force
```

### 3. Automated Execution (Cron Job)

Set up a cron job to automatically award leave:

```bash
# Edit crontab
crontab -e

# Add one of these lines:

# Option 1: Run daily during July 1-7 at 6 AM
0 6 1-7 7 * /usr/bin/php /path/to/hrm/cron_annual_leave.php

# Option 2: Run only on July 1st at 6 AM
0 6 1 7 * /usr/bin/php /path/to/hrm/cron_annual_leave.php

# Option 3: Run weekly during July at 6 AM on Mondays
0 6 * 7 1 /usr/bin/php /path/to/hrm/cron_annual_leave.php
```

## Award Logic

### Eligibility Criteria

1. **Employment Type**: Must be 'permanent'
2. **Employee Status**: Must be 'active'
3. **Duplicate Check**: No existing leave balance for current financial year (unless forced)

### Leave Calculation

#### Full Year Employees
- Employees hired before July 1st of the financial year
- **Award**: 30 days

#### New Employees (Pro-rated)
- Employees hired after July 1st of the financial year
- **Calculation**: `(Remaining days in financial year / Total days in financial year) × 30`
- **Example**: Employee hired on October 1st gets approximately 22 days

### Example Calculations

```
Financial Year: 2025-2026 (July 1, 2025 - June 30, 2026)

Employee A: Hired June 1, 2025
- Award: 30 days (hired before financial year start)

Employee B: Hired October 1, 2025
- Remaining days: 273 days (Oct 1 - Jun 30)
- Total days in year: 365 days
- Award: (273/365) × 30 = 22 days

Employee C: Hired March 1, 2026
- Remaining days: 121 days (Mar 1 - Jun 30)
- Total days in year: 365 days
- Award: (121/365) × 30 = 10 days
```

## System Functions

### Core Functions (annual_leave_award.php)

#### `getCurrentFinancialYear()`
Returns the current financial year in format "YYYY-YYYY"

#### `isBeginningOfFinancialYear()`
Checks if current date is within the first week of July

#### `awardAnnualLeave($conn, $force = false)`
Main function that processes all permanent employees and awards leave

#### `checkAndAwardAnnualLeave($conn, $force = false)`
Wrapper function that checks timing and executes award process

## Monitoring and Logs

### Log Files
- **Location**: `logs/annual_leave_cron.log`
- **Content**: Detailed execution logs with timestamps
- **Levels**: INFO, WARNING, ERROR

### Award History
- Accessible via web interface
- Shows all historical award processes
- Includes statistics and execution details

### Notifications
- Email notifications sent to HR managers
- System notifications (if notifications table exists)
- Success and error reporting

## Error Handling

### Common Issues and Solutions

#### 1. "Annual Leave type not found"
**Solution**: Ensure 'Annual Leave' exists in `leave_types` table
```sql
INSERT INTO leave_types (name, description, max_days_per_year, is_active) 
VALUES ('Annual Leave', 'Regular annual vacation leave', 30, 1);
```

#### 2. "No permanent employees found"
**Solution**: Check that employees have `employment_type = 'permanent'` and `employee_status = 'active'`

#### 3. Database connection errors
**Solution**: Verify database credentials in `config.php`

#### 4. Permission denied for cron job
**Solution**: Ensure the PHP script has execute permissions
```bash
chmod +x cron_annual_leave.php
```

## Security Considerations

1. **Access Control**: Only HR managers can access the web interface
2. **SQL Injection Prevention**: All queries use prepared statements
3. **Input Validation**: All user inputs are sanitized
4. **Command Line Security**: Cron script validates execution environment

## Maintenance

### Annual Tasks
1. Review and update employee employment types
2. Verify leave type configurations
3. Check cron job execution logs
4. Validate award calculations for new hires

### Monthly Tasks
1. Review leave balance reports
2. Monitor system logs for errors
3. Verify employee status updates

## Troubleshooting

### Debug Mode
Enable detailed error reporting by adding to the top of PHP files:
```php
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

### Manual Testing
Test the system with a small subset of employees:
```php
// Modify the employee query in awardAnnualLeave() function
$employeeQuery = "SELECT id, employee_id, first_name, last_name, hire_date 
                 FROM employees 
                 WHERE employment_type = 'permanent' 
                 AND employee_status = 'active'
                 AND id IN (1, 2, 3)"; // Test with specific employee IDs
```

## Support

For technical support or feature requests, contact the HR system administrator or development team.

## Version History

- **v1.0**: Initial implementation with basic award functionality
- **v1.1**: Added pro-rated calculations for new employees
- **v1.2**: Added web interface and cron job automation
- **v1.3**: Enhanced logging and notification system