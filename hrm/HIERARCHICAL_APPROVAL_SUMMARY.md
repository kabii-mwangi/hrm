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

### 3. **Department Head/Manager** (HR Approval)
- **Step 1**: HR Manager approval required
- **Final**: Leave approved after HR approval

### 4. **Managing Director/HR Manager** (Auto-Approved)
- **No approval required**: Leave automatically approved

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

The hierarchical approval system ensures proper organizational oversight while maintaining efficiency and clear communication throughout the approval process.