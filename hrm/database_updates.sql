-- Annual Leave Award System Database Updates
-- Add this table to track annual leave awards

CREATE TABLE IF NOT EXISTS `annual_leave_award_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `financial_year` varchar(10) NOT NULL,
  `days_awarded` int(11) NOT NULL,
  `award_type` enum('full','prorated') DEFAULT 'full',
  `calculation_details` text DEFAULT NULL,
  `awarded_by` int(11) DEFAULT NULL,
  `award_method` enum('automatic','manual','cron') DEFAULT 'automatic',
  `notes` text DEFAULT NULL,
  `awarded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  KEY `financial_year` (`financial_year`),
  KEY `awarded_by` (`awarded_by`),
  UNIQUE KEY `unique_employee_year` (`employee_id`, `financial_year`),
  FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`awarded_by`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add index to leave_balances for better performance
ALTER TABLE `leave_balances` ADD INDEX `idx_employee_financial_year` (`employee_id`, `financial_year`);

-- Ensure employment_type column exists in employees table (if not already present)
ALTER TABLE `employees` ADD COLUMN IF NOT EXISTS `employment_type` enum('permanent','contract','temporary','probation') DEFAULT 'permanent';