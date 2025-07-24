# Leave Calculation Error Fix

## Problem
User was getting "Error calculating days" when applying for leave through the AJAX endpoint.

## Root Causes Identified

### 1. **Function Return Type Mismatch**
- The `calculateLeaveDays()` function was returning just a number
- The AJAX handler expected an array with `days`, `note`, and `leave_type` keys

### 2. **Missing Database Column**
- The `leave_types` table might not have the `counts_weekends` column
- This would cause SQL errors when trying to select it

### 3. **Insufficient Error Handling**
- Limited error reporting made it difficult to diagnose issues
- No validation in calculation functions

## Fixes Applied

### 1. **Updated calculateLeaveDays Function**
```php
// Before: returned just a number
return calculateBusinessDays($startDate, $endDate, $conn, false);

// After: returns structured array
return [
    'days' => $days,
    'note' => $note,
    'leave_type' => $leaveTypeName
];
```

### 2. **Added Database Column Fallback**
```php
// Check if counts_weekends column exists
$columnCheck = $conn->query("SHOW COLUMNS FROM leave_types LIKE 'counts_weekends'");
$hasCountsWeekends = ($columnCheck && $columnCheck->num_rows > 0);

if ($hasCountsWeekends) {
    $countsWeekends = (bool)($leaveType['counts_weekends'] ?? 0);
} else {
    // Fallback: assume maternity leave counts weekends, others don't
    $countsWeekends = (stripos($leaveTypeName, 'maternity') !== false);
}
```

### 3. **Enhanced Error Handling**
- Added input validation to `calculateLeaveDays()`
- Added date format validation
- Added database error checking
- Added debug logging to AJAX handler
- Wrapped `calculateBusinessDays()` in try-catch

### 4. **Updated Function Calls**
```php
// Before
$days = calculateLeaveDays($startDate, $endDate, $leaveTypeId, $conn);

// After
$leaveCalculation = calculateLeaveDays($startDate, $endDate, $leaveTypeId, $conn);
$days = $leaveCalculation['days'];
```

## Testing Tools Added

### 1. **AJAX Calculation Test**
Access: `leave_management.php?test=ajax_calc`
- Tests the leave calculation with sample data
- Shows available leave types if test fails
- Displays detailed calculation results

### 2. **Database Structure Test**
Access: `leave_management.php?test=db_structure`
- Shows `leave_types` table structure
- Lists sample leave types with their settings
- Helps identify missing columns

### 3. **Enhanced Debug Logging**
- AJAX requests are now logged with parameters
- Error messages include more detail
- Function errors are logged to error log

## Expected Results

After these fixes:
✅ Leave calculation should work without "Error calculating days" message
✅ System handles missing `counts_weekends` column gracefully
✅ Better error messages help identify any remaining issues
✅ Maternity leave automatically counts weekends (fallback logic)
✅ Other leave types exclude weekends and holidays by default

## How to Test

1. **Basic Test**: Apply for leave normally through the web interface
2. **AJAX Test**: Visit `leave_management.php?test=ajax_calc`
3. **DB Test**: Visit `leave_management.php?test=db_structure`
4. **Check Logs**: Look at error logs for any remaining issues

## Next Steps

If you still get errors:
1. Check the test endpoints to see specific error messages
2. Verify your `leave_types` table has data
3. Check that the `holidays` table exists and has data
4. Ensure database connection is working properly