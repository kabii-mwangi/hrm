# Hierarchical Leave Approval System - Implementation Summary

## Overview

The HR system now implements a comprehensive hierarchical leave approval workflow where leave applications flow through different approval levels based on the employee's role in the organization.

## Approval Workflow by Employee Type

### 1. **Officer/Employee** (2-Level Approval)
- **Step 1**: Section Head approval required
- **Step 2**: Department Head approval required
- **Final**: Leave approved only after both approvals

### 2. **Section Head** (1-Level Approval)
- **Step 1**: Department Head approval required
- **Final**: Leave approved after department head approval

### 3. **Department Head/Manager** (Executive Approval)
- **Step 1**: Managing Director OR HR Manager approval required
- **Final**: Leave approved after executive approval

### 4. **Managing Director** (Executive Approval)
- **Step 1**: HR Manager approval required (Managing Director cannot approve own leave)
- **Final**: Leave approved after HR Manager approval

### 5. **HR Manager** (Managing Director Approval)
- **Step 1**: Managing Director approval required
- **Final**: Leave approved after Managing Director approval
- **Note**: HR Manager cannot approve their own leave (conflict of interest)

## Key Features Implemented

### 1. **Smart Workflow Detection**
- System automatically determines approval workflow based on employee type
- Shows approval path to employee when submitting application
- Prevents unnecessary approval steps

### 2. **Hierarchical Approval Interface**
- **Section Head**: Can approve applications requiring section-level approval
- **Department Head**: Can approve applications requiring department-level approval
- **HR Manager**: Can force-approve any application or reject applications

### 3. **Visual Status Tracking**
- Real-time approval status display with colored badges
- Shows which approval level is pending/completed
- Clear indication of approval workflow progress

### 4. **Notification System**
- Automatic notifications sent to appropriate approvers
- Different notification types for section head vs department head approvals

## Database Structure

The system uses existing `leave_applications` table fields:
- `section_head_approval`: 'pending', 'approved', 'rejected', 'not_required'
- `dept_head_approval`: 'pending', 'approved', 'rejected', 'not_required'
- `section_head_approved_by`: User ID who approved at section level
- `dept_head_approved_by`: User ID who approved at department level
- Timestamps for each approval level

## Testing the System

### Test Scenario Setup:
1. **Officer Employee**: Jack Kamau (ID: 004) - requires 2-level approval
2. **Section Head**: Josephine Kangara (EMP009) - can approve section level
3. **Department Head**: Hezron Njoroge (EMP10) - can approve department level

### Test Steps:
1. **Access Test Interface**: 
   - Visit: `leave_management.php?test=hierarchical_approval`
   - View employee hierarchy and approval workflow

2. **Officer Applies for Leave**:
   - Login as officer (ID: 004)
   - Apply for leave (e.g., 2-3 days annual leave)
   - System shows: "Your application will go through: Section Head → Department Head"

3. **Section Head Approval**:
   - Login as section head (EMP009)
   - Go to Leave Management → Manage tab
   - See application with "Section: Pending" status
   - Click "Section Approve" button
   - Status changes to "Section: Approved, Dept: Pending"

4. **Department Head Approval**:
   - Login as department head (EMP10)
   - Go to Leave Management → Manage tab
   - See application with "Dept: Pending" status
   - Click "Dept Approve" button
   - Application status changes to "Approved"
   - Leave balance is updated automatically

## User Interface Features

### For Employees:
- Clear indication of approval workflow when applying
- Status tracking of their applications
- Visibility into which approval level is pending

### For Section Heads:
- "Section Approve" button for applications requiring section approval
- Only see applications from their section
- Clear indication of approval status

### For Department Heads:
- "Dept Approve" button for applications requiring department approval
- Only see applications from their department
- Can approve after section head approval (if required)

### For HR Managers:
- "HR Approve" button to force-approve any application
- "Reject" button to reject applications
- Full visibility into all applications and approval status

## Approval Logic

### Sequential Approval:
- Applications must follow the defined sequence
- Department head cannot approve before section head (for 2-level approvals)
- System checks all required approvals before final approval

### Status Updates:
- `status` remains 'pending' until all required approvals are complete
- Only changes to 'approved' when all workflow steps are satisfied
- Leave balance is updated only upon final approval

### Permission Checks:
- Section heads can only approve section-level applications
- Department heads can only approve department-level applications
- HR managers can override any approval level

## Benefits

### 1. **Proper Authorization**
- Ensures leave requests go through appropriate management levels
- Maintains organizational hierarchy and accountability

### 2. **Clear Workflow**
- Employees know exactly who needs to approve their leave
- Approvers see only applications requiring their level of approval

### 3. **Audit Trail**
- Complete record of who approved what and when
- Timestamps for each approval level
- Clear history of approval workflow

### 4. **Flexible System**
- Easy to modify approval workflows for different employee types
- Can add new approval levels or change existing ones
- Supports different organizational structures

## Usage Examples

### Example 1: Officer Leave Application
```
Employee: Jack Kamau (Officer)
Application: 3 days annual leave
Workflow: Section Head → Department Head

Step 1: Josephine Kangara (Section Head) approves
Step 2: Hezron Njoroge (Dept Head) approves
Result: Leave approved, balance updated
```

### Example 2: Section Head Leave Application
```
Employee: Josephine Kangara (Section Head)
Application: 5 days annual leave
Workflow: Department Head only

Step 1: Hezron Njoroge (Dept Head) approves
Result: Leave approved, balance updated
```

### Example 3: Department Head Leave Application
```
Employee: Hezron Njoroge (Department Head)
Application: 5 days annual leave
Workflow: Managing Director OR HR Manager approval required

Step 1: Either Managing Director OR HR Manager approves
Result: Leave approved, balance updated
```

### Example 4: Managing Director Leave Application
```
Employee: John Kamau (Managing Director)
Application: 7 days annual leave
Workflow: HR Manager approval required (cannot approve own leave)

Step 1: HR Manager approves
Result: Leave approved, balance updated
```

### Example 5: HR Manager Leave Application
```
Employee: HR Manager
Application: 4 days annual leave
Workflow: Managing Director approval required (cannot approve own leave)

Step 1: Managing Director approves
Result: Leave approved, balance updated
```

## Updated Approval Matrix

| Employee Type | Approval Workflow | Who Can Approve |
|---------------|-------------------|-----------------|
| Officer/Employee | Section Head → Department Head | Section Head, then Department Head |
| Section Head | Department Head | Department Head |
| **Department Head** | **Executive Approval** | **Managing Director OR HR Manager** |
| **Manager** | **Executive Approval** | **Managing Director OR HR Manager** |
| **Managing Director** | **HR Manager Only** | **HR Manager** |
| **HR Manager** | **Managing Director Only** | **Managing Director** |

## Key Benefits of Executive Approval System

### 1. **Flexible Executive Oversight**
- Department Heads and Managers can be approved by either Managing Director or HR Manager
- Provides redundancy in case one executive is unavailable
- Managing Director cannot approve own leave (conflict of interest prevention)

### 2. **Proper Segregation of Duties**
- HR Manager can approve management-level leave but not their own
- Managing Director can approve department/manager leave and HR Manager leave but not own
- Clear separation of approval authority with conflict of interest prevention

### 3. **Enhanced Availability**
- Two potential approvers for management leave reduces delays
- System continues to function even if one executive is on leave
- Better business continuity

The hierarchical approval system ensures proper organizational oversight while maintaining efficiency and clear communication throughout the approval process. The system now provides:

- **Executive Level Control**: Both Managing Directors and HR Managers can approve department head and manager leave
- **Conflict of Interest Prevention**: No employee can approve their own leave at any level
- **Clear Hierarchy**: Managing Director → HR Manager → Department Head → Section Head → Employee
- **Proper Checks and Balances**: Each level has appropriate oversight from higher authority

This creates a robust approval system with built-in redundancy for management-level approvals while ensuring proper segregation of duties at the executive level.