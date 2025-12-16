MBARARA UNIVERSITY OF SCIENCE AND TECHNOLOGY
FACULTY OF COMPUTING AND INFORMATICS
BACHELOR DEGREE OF COMPUTER SCIENCE
COURSE UNIT: WEB PROGRAMING
GROUPNAME: GROUP NINE

WATMON DERICK             2024/BCS/010
EGWANG EMMANUEL           2024/BCS/003
WASUKIRA RONNIE           2024/BCS/169/PS 
MUSASIRE T KENNETH        2024/BCS/115/PS
TURYAHEBWA APOLLO         2024/BCS/164/PS
OGWAL HOSBON              2024/BCS/007
KAMUKAMA JORAM JOTHAM     2024/BCS/074/PS
KAMUGISHA JUNIOUR         2024/BCS/072/PS
OGUTI GAD JOEL            2024/BCS/230/PS

WASTE MANAGEMENT SYSTEM

 PROJECT OVERVIEW

The Waste Management System (WMS) is a database-driven, 
web-based application designed to streamline and automate 
waste collection, tracking, and reporting for municipal and 
private waste management services.

It enables users to request waste collection, allows staff 
to manage assigned tasks, and provides administrators with 
tools to monitor operations, manage resources, and generate 
performance reports.

The system ensures proper coordination between users, 
staff, and administrators — improving efficiency, 
accountability, and environmental sustainability.

OBJECTIVES OF THE SYSTEM


1. **Automate Waste Collection Operations**
   - To replace manual paper-based processes with a digital platform
     that enables users to submit, track, and manage waste collection
     requests efficiently.

2. **Enhance Service Efficiency**
   - To ensure timely collection and disposal of waste by enabling
     administrators to assign collection tasks to available staff
     and trucks based on area and capacity.

3. **Improve Communication and Transparency**
   - To provide clear communication channels between users, staff,
     and administrators, reducing service delays and misunderstandings.

4. **Centralize Data Management**
   - To store and manage all user, request, payment, and feedback data
     in one unified database for easy access, monitoring, and reporting.

5. **Enable Role-Based Access Control**
   - To implement secure authentication for different user roles 
     (Admin, Staff, User), ensuring appropriate access to system features.

6. **Monitor Performance and Generate Reports**
   - To generate daily, weekly, or monthly reports summarizing requests,
     waste collected, and total payments for better decision-making.

7. **Support Environmental Sustainability**
   - To promote proper waste handling, recycling awareness, and
     environmental cleanliness through efficient service delivery.

8. **Provide User Feedback and Accountability**
   - To allow users to share feedback or complaints, improving service
     quality and staff accountability.

9. **Ensure Data Security and Integrity**
   - To protect sensitive data (user info, payments) using password 
     hashing, secure database queries, and controlled access.

10. **Lay the Foundation for Future Expansion**
    - To build a scalable system that can integrate advanced features
      like real-time GPS tracking, SMS notifications, and mobile app
      connectivity.



DATABASE INFORMATION


Database Name: waste_management_system   
Database Engine: MySQL

Tables Overview:
1. users              → Manages user accounts (admin, staff, users)
2. waste_requests     → Tracks user waste collection requests
3. trucks             → Stores truck and driver information
4. staff              → Holds staff details and assignments
5. assignments        → Links requests to staff and trucks
6. payments           → Manages collection payments
7. schedules          → Defines collection days and times per area
8. feedback           → Records user feedback and complaints
9. reports            → Stores generated operational reports
10. areas             → Lists service regions and locations

Key Relationships:

- users : waste_requests (One-to-Many)
- users : payments (One-to-Many)
- waste_requests : assignments (One-to-One)
- trucks : assignments (One-to-Many)
- staff : assignments (One-to-Many)
- users : reports (One-to-Many)
- users : feedback (One-to-Many)

Default Records:

Two default users are inserted into the database:
- Admin: apolloturyahebwa@gmail.com
-password:qwert
(Passwords are pre-hashed for security.)

SYSTEM STRUCTURE

Frontend:   HTML, CSS, JavaScript  
Backend:    PHP  
Database:   MySQL (waste_management_system.sql)  
Server:     Apache (XAMPP )

 Project Directory Layout

waste_management_system/
│
├── db_connect.php             → Database connection file
├── index.php                  → Main login page
├── register.php               → User registration page
├── logout.php                 → Logout handler
│
├── assets/
│   ├── css/
│   │   └── style.css          → Styling for all pages
│   ├── js/
│   │   └── script.js          → Optional frontend logic
│   └── images/                → Images, icons, and assets
│
├── admin/
│   ├── dashboard.php          → Admin control panel
│   ├── manage_users.php       → Manage user accounts
│   ├── manage_requests.php    → Approve or reject requests
│   ├── manage_staff.php       → Add and manage staff
│   ├── manage_trucks.php      → Add and manage trucks
│   ├── generate_reports.php   → Generate system reports
│   └── view_feedback.php      → Review user feedback
│
├── staff/
│   ├── dashboard.php          → Staff dashboard
│   ├── assigned_requests.php  → View assigned requests
│   ├── update_status.php      → Update collection progress
│   └── view_schedule.php      → View collection schedule
│
├── user/
│   ├── dashboard.php          → User dashboard
│   ├── request_waste.php      → Submit new waste request
│   ├── view_requests.php      → Track request status
│   ├── make_payment.php       → Make payments
│   ├── give_feedback.php      → Submit feedback
│   └── view_schedule.php      → View collection days
│
├── includes/
│   ├── header.php             → Common header file
│   ├── footer.php             → Common footer file
│   └── auth_check.php         → Session role validation
│
├── sql/
│   └── waste_management_schema.sql  → Database schema
│
└── README.txt                 → Project documentation


SYSTEM MODULES

1. Authentication Module
   - Manages login, registration, and logout.
   - Validates users and roles with PHP sessions.

2. User Module
   - Allows users to create waste collection requests.
   - Displays request status, payment records, and feedback.

3. Staff Module
   - Displays assigned tasks and allows status updates.
   - Shows collection schedules and assigned areas.

4. Admin Module
   - Full management of users, staff, and trucks.
   - Assigns collection tasks.
   - Reviews feedback and generates reports.

5. Payment Module
   - Records payments for requests.
   - Supports Cash, Mobile Money, and Card methods.

6. Scheduling Module
   - Defines and displays collection schedules for each area.

7. Feedback Module
   - Stores user feedback and tracks resolution progress.

8. Reports Module
   - Generates daily, weekly, or monthly summaries.
   - Includes totals for requests, collections, and payments.

USER ROLES AND ACCESS CONTROL
 Admin:
   - Full access to manage all system data and settings.

 Staff:
   - Limited access to assigned tasks and schedules.

 User:
   - Can request collections, make payments, and send feedback.


SYSTEM WORKFLOW

1. User registers or logs in.
2. User submits a waste collection request.
3. Admin reviews and approves/rejects the request.
4. Admin assigns a truck and staff to the task.
5. Staff completes the collection and updates status.
6. User makes payment if applicable.
7. Admin generates operational reports.
8. Users submit feedback for service quality.

SECURITY AND DATA INTEGRITY

- Passwords hashed with PHP’s `password_hash()`.
- Uses prepared SQL statements to prevent injection.
- Role-based access control ensures data privacy.
- Cascading foreign keys maintain relational consistency.


SETUP INSTRUCTIONS

1. Install XAMPP/WAMP/LAMP and start Apache & MySQL.
2. Open phpMyAdmin → Import `waste_management_schema.sql`.
3. Configure database credentials in `db_connect.php`.
4. Run the system in your browser:
   http://localhost/waste_management_system/
5. Log in using the default admin or staff credentials.

FUTURE IMPROVEMENTS

- Add GPS tracking and live map integration.
- Implement online payment gateways.
- Enable email/SMS notifications.
- Add an analytics dashboard for waste trends.
- Support mobile-friendly responsive interface.



 AUTHORS: [Group9]

