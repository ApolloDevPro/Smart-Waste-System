-- WASTE MANAGEMENT SYSTEM DATABASE SCHEMA
-- Database: waste_management_system

CREATE DATABASE IF NOT EXISTS waste_management_system;
USE waste_management_system;

-- Users table to store user information
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    role ENUM('user', 'staff', 'admin') DEFAULT 'user',
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    date_registered TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Stores waste collection requests from users
CREATE TABLE IF NOT EXISTS waste_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    waste_type ENUM('Organic', 'Plastic', 'Paper', 'Metal', 'Glass', 'E-waste', 'Other') NOT NULL,
    quantity_kg DECIMAL(10,2),
    address TEXT,
    description TEXT,
    status ENUM('Pending', 'Approved', 'In Progress', 'Collected', 'Rejected', 'Canceled') DEFAULT 'Pending',
    request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    collection_date DATE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Stores information about waste collection trucks
CREATE TABLE IF NOT EXISTS trucks (
    truck_id INT AUTO_INCREMENT PRIMARY KEY,
    truck_number VARCHAR(50) UNIQUE NOT NULL,
    driver_name VARCHAR(100),
    capacity_kg DECIMAL(10,2),
    status ENUM('Available', 'Busy', 'Maintenance') DEFAULT 'Available',
    fuel_level DECIMAL(5,2) DEFAULT 100.00,
    last_maintenance DATE,
    next_maintenance DATE,
    maintenance_notes TEXT,
    total_distance DECIMAL(10,2) DEFAULT 0.00,
    current_location VARCHAR(255),
    vehicle_type VARCHAR(50),
    manufacture_year INT,
    registration_date DATE
);

-- Stores information about staff members
-- Stores information about staff members
CREATE TABLE IF NOT EXISTS staff (
    staff_id INT PRIMARY KEY, -- Link to users.user_id
    name VARCHAR(100),
    phone VARCHAR(20),
    position VARCHAR(50),
    truck_id INT,
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    FOREIGN KEY (staff_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (truck_id) REFERENCES trucks(truck_id) ON DELETE SET NULL
);

-- Tracks which staff/truck is assigned to each request
CREATE TABLE IF NOT EXISTS assignments (
    assignment_id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    truck_id INT NOT NULL,
    staff_id INT,
    assigned_by INT,
    assigned_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Assigned', 'Completed', 'Canceled') DEFAULT 'Assigned',
    FOREIGN KEY (request_id) REFERENCES waste_requests(request_id) ON DELETE CASCADE,
    FOREIGN KEY (truck_id) REFERENCES trucks(truck_id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_by) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Records payments for waste collection services
CREATE TABLE IF NOT EXISTS payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    request_id INT NOT NULL,
    amount DECIMAL(10,2),
    payment_method ENUM('Cash', 'Mobile Money', 'Card') DEFAULT 'Cash',
    payment_status ENUM('Pending', 'Paid', 'Failed') DEFAULT 'Pending',
    transaction_id VARCHAR(100),
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (request_id) REFERENCES waste_requests(request_id)
);

-- Stores recurring collection schedules for areas
CREATE TABLE IF NOT EXISTS schedules (
    schedule_id INT AUTO_INCREMENT PRIMARY KEY,
    area_name VARCHAR(100),
    waste_type ENUM('Organic', 'Plastic', 'Paper', 'Metal', 'Glass', 'E-waste', 'Other'),
    collection_day ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
    start_time TIME,
    end_time TIME
);

-- Stores feedback or complaints from users
CREATE TABLE IF NOT EXISTS feedback (
    feedback_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    subject VARCHAR(100),
    message TEXT,
    feedback_type ENUM('Complaint', 'Suggestion', 'Inquiry', 'Other') DEFAULT 'Other',
    status ENUM('New', 'Reviewed', 'Resolved') DEFAULT 'New',
    date_submitted TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Stores system-generated reports
CREATE TABLE IF NOT EXISTS reports (
    report_id INT AUTO_INCREMENT PRIMARY KEY,
    report_title VARCHAR(150),
    report_type ENUM('Daily', 'Weekly', 'Monthly', 'Custom'),
    generated_by INT,
    generated_on TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total_requests INT,
    total_collected DECIMAL(10,2),
    total_payments DECIMAL(10,2),
    notes TEXT,
    FOREIGN KEY (generated_by) REFERENCES users(user_id)
);

-- Stores information about areas served
CREATE TABLE IF NOT EXISTS areas (
    area_id INT AUTO_INCREMENT PRIMARY KEY,
    area_name VARCHAR(100) UNIQUE,
    region VARCHAR(100),
    description TEXT
);

-- Stores contact messages from users
CREATE TABLE IF NOT EXISTS contact_messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    subject VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    date_sent TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Stores notifications for users
CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    notification_type ENUM('request', 'payment', 'assignment', 'feedback', 'system', 'general') DEFAULT 'general',
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    related_id INT NULL COMMENT 'ID of related entity (request_id, payment_id, etc)',
    action_url VARCHAR(255) NULL COMMENT 'URL to navigate when notification is clicked',
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_created (created_at)
);

-- Stores email notification logs
CREATE TABLE IF NOT EXISTS email_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    recipient_email VARCHAR(100) NOT NULL,
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    error_message TEXT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Insert admin user
INSERT INTO users (email, password, role) 
VALUES ('apolloturyahebwa@gmail.com', '$2y$10$qZLIeQqvhgxxVOTgk047c.yLOILEtr5U/MMlSGhkIHfwRFxJoRxZO', 'admin');