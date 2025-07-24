# Leave Days Calculation Update

## Overview

The leave management system has been updated to ensure that leave days calculation excludes weekends and holidays for most leave types, with the exception of maternity leave which includes all calendar days.

## Changes Made

### 1. **Enhanced Leave Calculation Logic**

#### New Functions Added (`leave_management.php`):
- **`calculateLeaveDays()`** - Main function that determines calculation method based on leave type
- **`calculateCalendarDays()`** - Calculates total calendar days (including weekends/holidays)
- **Updated `calculateBusinessDays()`** - Improved to properly exclude holidays and weekends

#### Calculation Rules:
- **Most Leave Types** (Annual, Sick, Paternity, Study, Short, Compassionate):
  - ✅ **Excludes weekends** (Saturday & Sunday)
  - ✅ **Excludes holidays** (from holidays table)
  - ✅ **Counts only business days**

- **Maternity Leave**:
  - ✅ **Includes weekends** (Saturday & Sunday)
  - ✅ **Includes holidays** 
  - ✅ **Counts all calendar days**

### 2. **AJAX-Powered Dynamic Calculation**

#### New File: `calculate_leave_days.php`
- Real-time leave days calculation endpoint
- Returns JSON response with calculated days and explanation
- Validates input dates and leave type
- Provides user-friendly error messages

#### Enhanced User Interface:
- **Dynamic calculation** as user selects dates and leave type
- **Visual indicators** showing calculation method
- **Informative notes** explaining whether weekends/holidays are included
- **Leave type descriptions** with calculation details

### 3. **Database Integration**

#### Leave Types Configuration:
The system uses the `counts_weekends` column in the `leave_types` table:

| Leave Type | counts_weekends | Calculation Method |
|------------|-----------------|-------------------|
| Annual Leave | 0 | Business days only |
| Sick Leave | 0 | Business days only |
| **Maternity Leave** | **1** | **All calendar days** |
| Paternity Leave | 0 | Business days only |
| Study Leave | 0 | Business days only |
| Short Leave | 0 | Business days only |
| Compassionate Leave | 0 | Business days only |

### 4. **User Experience Improvements**

#### Leave Application Form:
- **Smart calculation** updates automatically when dates or leave type change
- **Information panel** shows leave type description and calculation method
- **Clear indicators** whether weekends/holidays are included or excluded
- **Real-time validation** prevents invalid date ranges

#### Visual Feedback:
```
Example display:
"5 days (Excludes weekends and holidays)" - for Annual Leave
"7 days (Includes weekends and holidays)" - for Maternity Leave
```

## How It Works

### 1. **Leave Type Selection**
When a user selects a leave type, the system:
1. Checks the `counts_weekends` flag for that leave type
2. Shows appropriate information about calculation method
3. Updates the calculation display

### 2. **Date Selection**
When dates are selected:
1. AJAX call to `calculate_leave_days.php`
2. Server-side calculation based on leave type
3. Returns calculated days with explanation
4. Updates form display in real-time

### 3. **Calculation Examples**

#### Scenario: Friday to Monday (4 calendar days)
- **Annual Leave**: 2 days (excludes Sat/Sun weekend)
- **Maternity Leave**: 4 days (includes Sat/Sun weekend)

#### Scenario: Monday to Friday with Wednesday holiday
- **Annual Leave**: 4 days (excludes Wednesday holiday)
- **Maternity Leave**: 5 days (includes Wednesday holiday)

## Technical Implementation

### Backend Changes:
```php
// New function determines calculation method
function calculateLeaveDays($startDate, $endDate, $leaveTypeId, $conn) {
    $leaveType = getLeaveTypeSettings($leaveTypeId, $conn);
    
    if ($leaveType['counts_weekends']) {
        return calculateCalendarDays($startDate, $endDate);
    } else {
        return calculateBusinessDays($startDate, $endDate, $conn, false);
    }
}
```

### Frontend Changes:
```javascript
// Dynamic calculation with AJAX
fetch('calculate_leave_days.php', {
    method: 'POST',
    body: `start_date=${startDate}&end_date=${endDate}&leave_type_id=${leaveTypeId}`
})
.then(response => response.json())
.then(data => {
    displayCalculatedDays(data.days, data.note);
});
```

## Testing

### Test Script: `test_leave_calculation.php`
- Comprehensive testing of different scenarios
- Validates calculation for all leave types
- Tests weekend and holiday exclusion/inclusion
- Provides detailed output for verification

### Test Scenarios:
1. **Monday to Friday** - Tests basic business days
2. **Friday to Monday** - Tests weekend handling
3. **Two weeks with holidays** - Tests holiday exclusion
4. **Maternity leave periods** - Tests calendar day inclusion

## Benefits

### 1. **Accurate Leave Tracking**
- Employees don't lose leave days for weekends/holidays
- Maternity leave properly accounts for full period
- Consistent calculation across all applications

### 2. **Improved User Experience**
- Real-time calculation feedback
- Clear explanation of calculation method
- No surprises about leave day counts

### 3. **Compliance**
- Follows standard HR practices
- Maternity leave includes all days as typically required
- Configurable per leave type

### 4. **Administrative Benefits**
- Reduced queries about leave calculations
- Consistent application processing
- Clear audit trail of calculations

## Configuration

### Adding New Leave Types:
When adding new leave types, set the `counts_weekends` flag:
- `0` = Exclude weekends and holidays (most leave types)
- `1` = Include weekends and holidays (maternity, extended medical leave)

### Holiday Management:
- Holidays are managed in the `holidays` table
- System automatically excludes holidays for business day calculations
- Holidays are included for calendar day calculations (maternity leave)

## Future Enhancements

### Potential Additions:
1. **Half-day leave** calculation
2. **Public holiday configuration** by region
3. **Custom weekend definitions** (different countries)
4. **Leave type specific holiday rules**
5. **Automatic holiday calendar imports**

The system now provides accurate, user-friendly leave day calculations that align with standard HR practices while maintaining flexibility for different leave types.