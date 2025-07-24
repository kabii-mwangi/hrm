# Annual Leave Award System - Setup Guide

## Overview

This guide will help you implement the Annual Leave Award System that automatically awards 30 days of annual leave to permanently employed employees at the beginning of each financial year (July).

## Prerequisites

- PHP 7.4 or higher
- MySQL/MariaDB database
- Existing HR Management System
- Web server (Apache/Nginx)

## Installation Steps

### 1. Database Setup

First, run the database updates to create the necessary table:

```bash
# Navigate to your HR system directory
cd /path/to/hrm

# Run database updates
php run_db_updates.php
```

Or manually execute the SQL in `database_updates.sql` in your database.

### 2. File Verification

Ensure these files are present in your HR system directory:

- `annual_leave_award.php` - Core award logic
- `annual_leave_management.php` - Management interface
- `cron_annual_leave.php` - Cron job script
- `database_updates.sql` - Database schema updates
- `run_db_updates.php` - Database update runner
- `test_annual_leave.php` - System testing script

### 3. Test the System

Run the test script to verify everything is working:

```bash
php test_annual_leave.php
```

### 4. Set Up Cron Job (Automated Awards)

To automatically award leave every July 1st, add this cron job:

```bash
# Edit crontab
crontab -e

# Add this line to run at 6 AM on July 1st every year
0 6 1 7 * /usr/bin/php /path/to/hrm/cron_annual_leave.php

# Alternative: Run daily during first week of July (safer)
0 6 1-7 7 * /usr/bin/php /path/to/hrm/cron_annual_leave.php
```

### 5. Manual Award Process

HR managers can also manually award leave through the web interface:

1. Log in as HR Manager or Super Admin
2. Navigate to "Annual Leave Awards" in the sidebar
3. Click "Award Annual Leave" button
4. Review the list of eligible employees
5. Click "Confirm Award" to process

## Configuration Options

### Financial Year Settings

The system assumes financial year starts in July. To change this, modify the `getCurrentFinancialYear()` function in `annual_leave_award.php`.

### Leave Days Amount

To change from 30 days, modify the `ANNUAL_LEAVE_DAYS` constant in `annual_leave_award.php`.

### Employment Types

The system awards leave to employees with `employment_type = 'permanent'`. To include other types, modify the query in the award functions.

## How It Works

### 1. Eligibility Criteria

- Employee must have `employment_type = 'permanent'`
- Employee must have `employee_status = 'active'`
- Employee must not have already received an award for the current financial year

### 2. Pro-rating Logic

- Employees hired after July 1st receive pro-rated leave
- Calculation: `(Remaining months / 12) * 30 days`
- Minimum award is 1 day

### 3. Award Process

1. System checks current financial year
2. Identifies eligible permanent employees
3. Calculates leave days (full or pro-rated)
4. Updates `leave_balances` table
5. Logs award in `annual_leave_award_logs` table
6. Sends email notifications (if configured)

## Database Tables

### leave_balances
Stores employee leave balances for each financial year.

### annual_leave_award_logs
Tracks all annual leave awards with details:
- Employee ID
- Financial year
- Days awarded
- Award type (full/prorated)
- Award method (automatic/manual/cron)
- Timestamp

## Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Check `config.php` database settings
   - Verify database server is running

2. **Permission Denied**
   - Ensure web server has read/write permissions
   - Check file ownership and permissions

3. **Cron Job Not Running**
   - Verify cron service is running
   - Check cron logs: `tail -f /var/log/cron`
   - Ensure PHP path is correct in cron command

4. **No Permanent Employees Found**
   - Check `employment_type` column in employees table
   - Verify employees have correct employment type set

### Testing Commands

```bash
# Test database connection
php -r "require_once 'config.php'; $conn = getConnection(); echo 'Connected successfully';"

# Test financial year calculation
php -r "require_once 'annual_leave_award.php'; echo getCurrentFinancialYear();"

# Run system test
php test_annual_leave.php

# Dry run award process
php annual_leave_award.php --dry-run

# Force award (for testing)
php annual_leave_award.php --force
```

## Security Considerations

1. **Access Control**: Only HR managers and super admins can access the award system
2. **Audit Trail**: All awards are logged with timestamps and user information
3. **Duplicate Prevention**: System prevents multiple awards for the same financial year
4. **Validation**: Input validation on all user inputs

## Monitoring and Maintenance

### Regular Checks

1. **Monthly**: Review award logs for accuracy
2. **Annually**: Verify all permanent employees received awards
3. **Before July**: Test cron job functionality

### Log Files

- Award logs are stored in the database
- Cron job logs are in system cron logs
- PHP errors logged to web server error logs

## Support

For issues or questions:
1. Check the troubleshooting section
2. Review log files for error messages
3. Run the test script to identify problems
4. Verify database connectivity and permissions

## Customization

The system can be customized for different:
- Financial year start dates
- Leave day amounts
- Employee eligibility criteria
- Notification methods
- Pro-rating calculations

Modify the relevant functions in `annual_leave_award.php` for customizations.