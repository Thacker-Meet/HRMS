# How to ru Project
open index.php file

# Dayflow – Human Resource Management System  
**Every workday, perfectly aligned.**

Dayflow is a web-based Human Resource Management System (HRMS) designed to digitize and streamline core HR operations within an organization. The system provides a secure, role-based platform for managing employees, attendance, leave, and payroll while ensuring efficiency, transparency, and accuracy.

---

## 📌 Purpose
The purpose of Dayflow HRMS is to replace manual and scattered HR processes with a centralized digital system. It helps organizations manage employee data, attendance, leave requests, and payroll visibility while supporting approval workflows for Admins and HR Officers.

---

## 📌 Scope of the System
Dayflow HRMS provides the following functionalities:

- Secure user authentication (Sign Up / Sign In)
- Role-based access control (Admin / HR vs Employee)
- Employee profile management
- Attendance tracking (daily and weekly)
- Leave and time-off management
- Payroll visibility
- Approval workflows for Admin and HR officers
- Reports and analytics for HR insights

---

## 👥 User Roles & Characteristics

### 🔹 Admin / HR Officer
- Manages employee records  
- Approves or rejects leave requests  
- Monitors attendance of all employees  
- Views and manages payroll data  
- Generates reports and analytics  

### 🔹 Employee
- Views personal profile and job details  
- Marks attendance (check-in / check-out)  
- Applies for leave and tracks its status  
- Views payroll information (read-only)  

---

## 🔐 Authentication & Authorization

### Sign Up
Users can register using:
- Employee ID  
- Email address  
- Password  
- Role (Employee / HR)  

Security features:
- Password validation rules  
- Email verification before account activation  

### Sign In
- Login using email and password  
- Error messages for incorrect credentials  
- Successful login redirects to respective dashboard  

---

## 📊 Dashboard Overview

### Employee Dashboard
- Quick access to:
  - Profile
  - Attendance
  - Leave requests
  - Payroll
  - Logout
- Displays recent activities or alerts

### Admin / HR Dashboard
- Overview of:
  - Employee list
  - Attendance records
  - Leave requests
- Ability to manage and switch between employees

---

## 🧾 Employee Profile Management

### View Profile
Employees can view:
- Personal details  
- Job-related information  
- Salary structure  
- Uploaded documents  
- Profile picture  

### Edit Profile
- Employees can update limited fields (address, phone number, profile picture)
- Admin/HR can edit all employee details

---

## ⏱ Attendance Management

### Attendance Tracking
- Daily and weekly attendance view
- Employee check-in and check-out feature
- Attendance status types:
  - Present
  - Absent
  - Half-day
  - Leave

### Attendance Access
- Employees can view only their own attendance
- Admin/HR can view attendance of all employees

---

## 🗓 Leave & Time-Off Management

### Apply for Leave (Employee)
Employees can:
- Select leave type (Paid, Sick, Unpaid)
- Choose date range
- Add remarks

Leave request statuses:
- Pending
- Approved
- Rejected

### Leave Approval (Admin / HR)
Admin/HR can:
- View all leave requests
- Approve or reject requests
- Add comments
- Changes reflect immediately in employee records

---

## 💰 Payroll / Salary Management

### Employee Payroll View
- Payroll information is **read-only** for employees

### Admin Payroll Control
Admin/HR can:
- View payroll of all employees
- Update salary structure
- Ensure payroll accuracy
- Send notifications and alerts

---

## 📈 Reports & Analytics
- Attendance reports
- Salary and payroll reports
- Leave summaries
- Downloadable records (future enhancement: salary slips)

---

## 🛠 Tech Stack
- **Frontend:** HTML, CSS, JavaScript  
- **Backend:** PHP  
- **Database:** MySQL  
- **Server:** Apache (XAMPP / WAMP)

---

## ⚙️ Project Setup
1. Clone the repository  
2. Import the SQL file from `database/dayflow_hrms.sql` into MySQL  
3. Configure database credentials in `config/db.php`  
4. Run the project using XAMPP / WAMP  
5. Access the application via:
