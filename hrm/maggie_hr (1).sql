-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jul 22, 2025 at 04:04 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `maggie_hr`
--

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Admin', 'Manages employee relations and company policies', '2025-07-19 09:04:13', '2025-07-19 09:04:13'),
(2, 'Commercial', 'Handles sales, marketing, and customer relations', '2025-07-19 09:04:13', '2025-07-19 09:04:13'),
(3, 'Technical', 'Manages technical operations and development', '2025-07-19 09:04:13', '2025-07-19 09:04:13'),
(4, 'Corporate Affairs', 'Handles legal, compliance, and corporate governance', '2025-07-19 09:04:13', '2025-07-19 09:04:13'),
(5, 'Fort-Aqua', 'Water management and supply operations', '2025-07-19 09:04:13', '2025-07-19 09:04:13');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `employee_id` varchar(50) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `national_id` int(10) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `designation` varchar(50) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `date_of_birth` date NOT NULL,
  `address` text DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `section_id` int(11) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `salary` decimal(10,2) DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `employment_type` varchar(20) NOT NULL,
  `employee_type` varchar(20) NOT NULL,
  `profile_image_url` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `employee_status` enum('active','inactive','resigned','fired','retired') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `employee_id`, `first_name`, `last_name`, `national_id`, `email`, `designation`, `phone`, `date_of_birth`, `address`, `department_id`, `section_id`, `position`, `salary`, `hire_date`, `employment_type`, `employee_type`, `profile_image_url`, `created_at`, `updated_at`, `employee_status`) VALUES
(5, 'EMP009', 'Josephine', 'Kangara', 3987654, 'josephine@gmail.com', '0', '0768525478', '1971-12-12', 'Kiambu', 2, 4, NULL, NULL, '2025-07-03', 'permanent', 'section_head', NULL, '2025-07-22 13:20:00', '2025-07-22 14:01:38', 'active'),
(104, 'EMP001', 'duncan', 'karenju', 40135584, 'karenjuduncan750@gmail.com', '0', '0112554479', '2008-03-04', 'Kiambu', 1, 2, NULL, NULL, '2025-07-18', 'contract', 'section_head', NULL, '2025-07-22 09:20:38', '2025-07-22 09:20:38', 'active'),
(111, '003', 'joseph', 'kamau', 105021, 'joseph@gmail.com', 'Employee', 'undefined', '0000-00-00', '1050', 3, 7, NULL, NULL, '2025-07-02', 'permanent', 'manager', NULL, '2025-07-21 13:48:26', '2025-07-21 14:27:58', 'active'),
(112, '004', 'jack', 'kamau', 1050, 'jack@gmail.com', '0', 'undefined', '2025-07-02', '1050', 2, 5, NULL, NULL, '2025-07-01', 'permanent', 'officer', NULL, '2025-07-21 13:49:38', '2025-07-22 09:26:55', 'active'),
(113, '001', 'john', 'kamau', 1050, 'john@gmail.com', 'Employee', '0707699054', '0000-00-00', '1050', NULL, NULL, NULL, NULL, '2025-07-01', 'permanent', 'managing_director', NULL, '2025-07-21 13:39:55', '2025-07-21 14:28:28', 'active'),
(114, '002', 'mike', 'kamau', 1245, 'mike@gmail.com', 'Employee', 'undefined', '0000-00-00', '1050', 2, NULL, NULL, NULL, '2025-07-02', 'permanent', 'dept_head', NULL, '2025-07-21 13:43:36', '2025-07-21 14:26:46', 'active'),
(118, 'EMP008', 'Mwangi', 'Kabii', 3987654, 'mwangikabii@gmail.com', '0', '0790765431', '1999-03-11', 'Kiambu', 2, 4, NULL, NULL, '2025-07-04', 'permanent', 'officer', NULL, '2025-07-22 10:23:07', '2025-07-22 10:23:07', 'active'),
(121, 'EMP10', 'Hezron', 'Njoroge', 3987654, 'hezronnjoro@gmail.com', '0', '0786542982', '1987-03-11', 'Mukurweini', 2, NULL, NULL, NULL, '2025-01-01', 'permanent', 'dept_head', NULL, '2025-07-22 13:32:58', '2025-07-22 13:32:58', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `holidays`
--

CREATE TABLE `holidays` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `date` date NOT NULL,
  `description` text DEFAULT NULL,
  `is_recurring` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `holidays`
--

INSERT INTO `holidays` (`id`, `name`, `date`, `description`, `is_recurring`, `created_at`) VALUES
(1, 'Jamhuri day', '2025-12-12', 'To become a republic', 1, '2025-07-22 09:41:38');

-- --------------------------------------------------------

--
-- Table structure for table `leave_applications`
--

CREATE TABLE `leave_applications` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `days_requested` int(11) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `section_head_approval` enum('pending','approved','rejected') DEFAULT 'pending',
  `section_head_approved_by` varchar(50) DEFAULT NULL,
  `section_head_approved_at` timestamp NULL DEFAULT NULL,
  `dept_head_approval` enum('pending','approved','rejected') DEFAULT 'pending',
  `dept_head_approved_by` varchar(50) DEFAULT NULL,
  `dept_head_approved_at` timestamp NULL DEFAULT NULL,
  `hr_processed_by` varchar(50) DEFAULT NULL,
  `hr_processed_at` timestamp NULL DEFAULT NULL,
  `hr_comments` text DEFAULT NULL,
  `approver_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_applications`
--

INSERT INTO `leave_applications` (`id`, `employee_id`, `leave_type_id`, `start_date`, `end_date`, `days_requested`, `reason`, `status`, `applied_at`, `section_head_approval`, `section_head_approved_by`, `section_head_approved_at`, `dept_head_approval`, `dept_head_approved_by`, `dept_head_approved_at`, `hr_processed_by`, `hr_processed_at`, `hr_comments`, `approver_id`) VALUES
(1, 112, 6, '2025-07-22', '2025-07-28', 5, 'medical emergency', 'approved', '2025-07-22 09:38:14', 'pending', NULL, NULL, 'pending', NULL, NULL, 'admin-001', '2025-07-22 09:38:25', NULL, NULL),
(2, 118, 4, '2025-07-23', '2025-07-30', 6, 'sick', 'approved', '2025-07-22 10:27:34', 'pending', NULL, NULL, 'pending', NULL, NULL, '3', '2025-07-22 11:26:03', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `leave_balances`
--

CREATE TABLE `leave_balances` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `financial_year` varchar(10) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `annual_leave_entitled` int(11) DEFAULT 30,
  `annual_leave_used` int(11) DEFAULT 0,
  `annual_leave_balance` int(11) DEFAULT 30,
  `sick_leave_used` int(11) DEFAULT 0,
  `other_leave_used` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leave_history`
--

CREATE TABLE `leave_history` (
  `id` int(11) NOT NULL,
  `leave_application_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `performed_by` int(11) NOT NULL,
  `comments` text DEFAULT NULL,
  `performed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leave_types`
--

CREATE TABLE `leave_types` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `max_days_per_year` int(11) DEFAULT NULL,
  `counts_weekends` tinyint(1) DEFAULT 0,
  `deducted_from_annual` tinyint(1) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_types`
--

INSERT INTO `leave_types` (`id`, `name`, `description`, `max_days_per_year`, `counts_weekends`, `deducted_from_annual`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Annual Leave', 'Regular annual vacation leave', 30, 0, 1, 1, '2025-07-21 10:55:35', '2025-07-21 10:55:35'),
(2, 'Sick Leave', 'Medical leave for illness', NULL, 0, 0, 1, '2025-07-21 10:55:35', '2025-07-21 10:55:35'),
(3, 'Maternity Leave', 'Maternity leave for female employees', 90, 1, 0, 1, '2025-07-21 10:55:35', '2025-07-21 10:55:35'),
(4, 'Paternity Leave', 'Paternity leave for male employees', 14, 0, 0, 1, '2025-07-21 10:55:35', '2025-07-21 10:55:35'),
(5, 'Study Leave', 'Educational or training leave', NULL, 0, 1, 1, '2025-07-21 10:55:35', '2025-07-21 10:55:35'),
(6, 'Short Leave', 'Short duration leave (half day, few hours)', NULL, 0, 1, 1, '2025-07-21 10:55:35', '2025-07-21 10:55:35'),
(7, 'Compassionate Leave', 'Emergency or bereavement leave', NULL, 0, 0, 1, '2025-07-21 10:55:35', '2025-07-21 10:55:35');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` varchar(50) NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','error') DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `related_type` varchar(50) DEFAULT NULL,
  `related_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`id`, `name`, `description`, `department_id`, `created_at`, `updated_at`) VALUES
(1, 'Human Resources', 'Employee management and policies', 1, '2025-07-19 09:04:13', '2025-07-19 09:04:13'),
(2, 'Finance', 'Financial planning and accounting', 1, '2025-07-19 09:04:13', '2025-07-19 09:04:13'),
(3, 'Sales', 'Direct sales operations', 2, '2025-07-19 09:04:13', '2025-07-19 09:04:13'),
(4, 'Marketing', 'Brand promotion and advertising', 2, '2025-07-19 09:04:13', '2025-07-19 09:04:13'),
(5, 'Customer Service', 'Customer support and relations', 2, '2025-07-19 09:04:13', '2025-07-19 09:04:13'),
(6, 'Software Development', 'Application and system development', 3, '2025-07-19 09:04:13', '2025-07-19 09:04:13'),
(7, 'IT Support', 'Technical support and maintenance', 3, '2025-07-19 09:04:13', '2025-07-19 09:04:13'),
(8, 'Network Operations', 'Network infrastructure management', 3, '2025-07-19 09:04:13', '2025-07-19 09:04:13'),
(9, 'Legal Affairs', 'Legal compliance and contracts', 4, '2025-07-19 09:04:13', '2025-07-19 09:04:13'),
(10, 'Public Relations', 'Media and public communications', 4, '2025-07-19 09:04:13', '2025-07-19 09:04:13'),
(11, 'Water Supply', 'Water distribution and supply management', 5, '2025-07-19 09:04:13', '2025-07-19 09:04:13');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(50) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('bod_chairman','super_admin','hr_manager','dept_head','section_head','manager','employee') DEFAULT 'employee',
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `profile_image_url` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `employee_id` varchar(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `first_name`, `last_name`, `password`, `role`, `phone`, `address`, `profile_image_url`, `created_at`, `updated_at`, `employee_id`) VALUES
(1, 'admin@company.com', 'Admin', 'User', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin', NULL, NULL, NULL, '2025-07-19 09:04:12', '2025-07-22 10:16:46', NULL),
(2, 'depthead@company.com', 'Department', 'Head', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'dept_head', NULL, NULL, NULL, '2025-07-19 09:04:13', '2025-07-22 10:16:57', NULL),
(3, 'hr@company.com', 'HR', 'Manager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'hr_manager', NULL, NULL, NULL, '2025-07-19 09:04:12', '2025-07-22 12:59:13', '118'),
(4, 'mwangikabii@gmail.com', 'Mwangi', 'Kabii', '$2y$10$iHS2eQYzKy9u04alnbWBMOSXH1ff3LDCi9j4uJ3JWiQC.92XDN.dS', 'employee', '0790765431', 'Kiambu', NULL, '2025-07-22 10:23:07', '2025-07-22 12:59:13', '112'),
(5, 'josephine@gmail.com', 'Josephine', 'Kangara', '$2y$10$c9v.Xk94usNFLIw2zveKJeZ1bdhdHNw14480WuyCpFwH19Ap3lYQW', 'section_head', '0768525478', 'Kiambu', NULL, '2025-07-22 13:20:00', '2025-07-22 13:20:00', 'EMP009'),
(6, 'hezronnjoro@gmail.com', 'Hezron', 'Njoroge', '$2y$10$0VLFP04KxABJW3pO6yi2Pe4GSZ2LeKZDMXWMZnn.bYBDwcAPi6GrO', 'dept_head', '0786542982', 'Mukurweini', NULL, '2025-07-22 13:32:58', '2025-07-22 13:32:58', 'EMP10');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `section_id` (`section_id`);

--
-- Indexes for table `holidays`
--
ALTER TABLE `holidays`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `leave_applications`
--
ALTER TABLE `leave_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `leave_type_id` (`leave_type_id`);

--
-- Indexes for table `leave_balances`
--
ALTER TABLE `leave_balances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_year` (`employee_id`,`financial_year`),
  ADD KEY `fk_leave_type` (`leave_type_id`);

--
-- Indexes for table `leave_types`
--
ALTER TABLE `leave_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`user_id`) USING BTREE;

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=122;

--
-- AUTO_INCREMENT for table `holidays`
--
ALTER TABLE `holidays`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `leave_applications`
--
ALTER TABLE `leave_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `leave_balances`
--
ALTER TABLE `leave_balances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leave_types`
--
ALTER TABLE `leave_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(50) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `employees_ibfk_2` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `leave_applications`
--
ALTER TABLE `leave_applications`
  ADD CONSTRAINT `leave_applications_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `leave_applications_ibfk_2` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`);

--
-- Constraints for table `leave_balances`
--
ALTER TABLE `leave_balances`
  ADD CONSTRAINT `fk_leave_type` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `leave_balances_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sections`
--
ALTER TABLE `sections`
  ADD CONSTRAINT `sections_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
