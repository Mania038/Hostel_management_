# 🏠 UniNest — Hostel Management System

<div align="center">

![UniNest](https://img.shields.io/badge/UniNest-Hostel%20Management%20System-2563EB?style=for-the-badge&logo=house&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-Database-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![XAMPP](https://img.shields.io/badge/XAMPP-Local%20Server-FB7A24?style=for-the-badge&logo=xampp&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-Vanilla-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)

**A full-stack web application for managing university hostel operations**

*United International University — Software Engineering Lab*
*Team 8 — 2026*

</div>

---

## 📋 Table of Contents

- [About the Project](#about-the-project)
- [Features](#features)
- [Screenshots](#screenshots)
- [Tech Stack](#tech-stack)
- [Getting Started](#getting-started)
- [Database Setup](#database-setup)
- [Project Structure](#project-structure)
- [User Roles](#user-roles)
- [Login Credentials (Demo)](#login-credentials-demo)
- [Team Members](#team-members)

---

## 🎯 About the Project

**UniNest** is an integrated, web-based Hostel Management System (HMS) designed for university environments. It digitises and centralises all hostel-related operations so students and administrators can manage everything from a single platform — no paperwork, no manual registers.

### What it solves
- ❌ Paper-based room allocation → ✅ Online room booking & allocation
- ❌ Manual fee collection → ✅ Online payment gateway (bKash, Nagad, Card, Bank)
- ❌ Verbal complaints → ✅ Tracked complaint & maintenance system
- ❌ No income for struggling students → ✅ Task Exchange System (earn fee credits)
- ❌ Single admin for all blocks → ✅ Multi-level admin (Super Admin + Block Managers)

---

## ✨ Features

### 🌐 Public (No Login Required)
| Feature | Description |
|---------|-------------|
| 🏠 Landing Page | Hero section with live hostel stats (rooms, seats, availability) |
| 🛏️ Room Availability | Browse & filter rooms by block, type, and availability status |
| 📝 Registration | Student self-registration with full form validation |
| 🔐 Login | Tabbed login for Students and Admins on the same page |

### 👨‍🎓 Student Portal
| Feature | Description |
|---------|-------------|
| 📊 Dashboard | Application status timeline, assigned room, balance due, open complaints |
| 🛏️ Browse & Apply | Browse available rooms and submit a room application |
| 💳 Online Payment | Pay fees via bKash, Nagad, Debit/Credit Card, or Bank Transfer |
| 🔄 Task Exchange | Earn fee credits by completing admin-posted tasks |
| 🔧 Complaints | Submit and track maintenance/hostel complaints |

### 🛠️ Admin Panel
| Feature | Description |
|---------|-------------|
| 📈 System Dashboard | Real-time stats: occupancy, fees collected, pending apps, open tasks |
| 🏢 Room Management | Add, edit, filter rooms across all blocks |
| 👥 Student Records | View, search, add, and deactivate student accounts |
| 📋 Applications | Review, approve, reject, and allocate room applications |
| 🏠 Allocations | Manage active room assignments per student |
| 💰 Fee Tracking | Track all fee records with payment method and status |
| 🔄 Task Exchange | Post tasks, review applications, verify completions |
| 🔧 Complaints Mgmt | Respond to, escalate, and resolve student complaints |
| 📢 Notices | Publish announcements visible on student dashboards |
| 👔 Hostel Managers | Manage multi-level admin accounts per block |

---

## 📸 Screenshots

### Home Page
![Home Page](screenshots/home.png)

### Room Availability
![Room Availability](screenshots/rooms_pub.png)

### Student Login
![Student Login](screenshots/login_stu.png)

### Admin Login
![Admin Login](screenshots/login_adm.png)

### Student Registration
![Registration](screenshots/register.png)

### Student Dashboard
![Student Dashboard](screenshots/stu_dash.png)

### Online Payment
![Online Payment](screenshots/payment.png)

### Task Exchange (Student)
![Task Exchange Student](screenshots/task_ex_stu.png)

### Complaints & Requests
![Complaints](screenshots/complaints.png)

### Admin System Dashboard
![Admin Dashboard](screenshots/adm_dash.png)

### Room Management
![Room Management](screenshots/room_mgmt.png)

### Student Records
![Student Records](screenshots/students.png)

### Room Applications
![Applications](screenshots/apps.png)

### Room Allocations
![Allocations](screenshots/allocs.png)

### Fee Tracking
![Fee Tracking](screenshots/fees.png)

### Task Exchange (Admin)
![Task Exchange Admin](screenshots/task_adm.png)

### Complaints Management
![Complaints Admin](screenshots/complaints_a.png)

### Notices & Announcements
![Notices](screenshots/notices.png)

### Hostel Manager Accounts
![Hostel Managers](screenshots/managers.png)

### Database Schema
![Database](screenshots/db.png)

---

## 🛠️ Tech Stack

| Layer | Technology |
|-------|-----------|
| **Frontend** | HTML5, CSS3, Vanilla JavaScript |
| **Icons** | Font Awesome |
| **Fonts** | Google Fonts (Poppins) |
| **Backend** | PHP 8+ |
| **Database** | MySQL |
| **Local Server** | XAMPP (Apache + MySQL + PHP) |
| **DB Admin** | phpMyAdmin |
| **Security** | MySQLi Prepared Statements, Session Auth, bcrypt Passwords |
| **Version Control** | Git |

---

## 🚀 Getting Started

### Prerequisites

- [XAMPP](https://www.apachefriends.org/) (includes Apache, MySQL, PHP)
- A modern web browser (Chrome, Firefox, Edge)
- Git (optional)

### Installation

**1. Clone or download the project**
```bash
git clone https://github.com/yourusername/uninest.git
```
Or download the ZIP and extract it.

**2. Move to XAMPP's htdocs folder**
```bash
# On Windows
C:\xampp\htdocs\uninest\

# On macOS/Linux
/opt/lampp/htdocs/uninest/
```

**3. Start XAMPP**
- Open the XAMPP Control Panel
- Start **Apache**
- Start **MySQL**

**4. Set up the database**

See [Database Setup](#database-setup) below.

**5. Open the project in your browser**
```
http://localhost/uninest/
```

---

## 🗄️ Database Setup

### Step 1 — Create the database

Open **phpMyAdmin** → `http://localhost/phpmyadmin/`

Create a new database:
```sql
CREATE DATABASE hostel_db;
```

### Step 2 — Import base schema

In phpMyAdmin, select `hostel_db` → Import → choose:
```
/db/hostel_db.sql
```

### Step 3 — Run migration

After the base import, import the migration file:
```
/db/hostel_db_update.sql
```

This adds:
- `payment_transactions` table (online payment records)
- `tasks` table (admin-posted tasks)
- `task_applications` table (student task applications)
- Alters `admins` table: adds `manager_level` and `assigned_block` columns

### Step 4 — Database connection

Edit the DB connection settings in your config file:
```php
// db/config.php  (or wherever your connection is defined)
$host     = "localhost";
$dbname   = "hostel_db";
$username = "root";
$password = "";       // default XAMPP password is empty
```

---

## 📁 Project Structure

```
uninest/
├── index.php                        # Landing page
│
├── auth/
│   ├── login.php                    # Student & Admin login (tabbed)
│   ├── register.php                 # Student registration
│   └── logout.php                   # Session destroy
│
├── student/
│   ├── dashboard.php                # Student main dashboard
│   ├── rooms.php                    # Browse rooms & apply
│   ├── pay_online.php               # Online payment gateway
│   ├── tasks.php                    # Task Exchange (student view)
│   └── complaints.php               # Submit & track complaints
│
├── admin/
│   ├── dashboard.php                # Admin system dashboard
│   ├── rooms.php                    # Room management
│   ├── students.php                 # Student records
│   ├── applications.php             # Room applications
│   ├── allocations.php              # Room allocations
│   ├── payments.php                 # Fee tracking
│   ├── tasks.php                    # Task Exchange (admin)
│   ├── complaints.php               # Complaints management
│   ├── notices.php                  # Notices & announcements
│   └── managers.php                 # Hostel manager accounts
│
├── includes/
│   ├── student_sidebar.php          # Shared student sidebar (DRY)
│   └── admin_sidebar.php            # Shared admin sidebar (DRY)
│
├── assets/
│   ├── css/                         # Custom stylesheets
│   └── js/                          # JavaScript files
│
├── db/
│   ├── hostel_db.sql                # Base database schema
│   └── hostel_db_update.sql         # Migration (new tables & columns)
│
└── README.md
```

---

## 👥 User Roles

### 🔵 Super Admin
- Full access to all blocks, students, rooms, and settings
- Manages Hostel Manager accounts
- Can view all reports and analytics
- Posts tasks, approves payments, manages all complaints

### 🟢 Hostel Manager (Block Manager)
- Scoped to their **assigned block only**
- Can approve/reject room applications for their block
- Handles complaints from students in their block
- Posts tasks, tracks fees — all within their block scope

### 🟡 Student
- Self-registers and applies for a room
- Views their dashboard: assigned room, fees, complaints
- Pays fees online or earns credits through Task Exchange
- Submits and tracks maintenance/hostel complaints

---

## 🔐 Login Credentials (Demo)

### Student Login
| Field | Value |
|-------|-------|
| Student ID | `2024-CS-045` |
| Password | `Student@123` |

### Admin Login (Super Admin)
| Field | Value |
|-------|-------|
| Username | `admin` |
| Password | `Admin@123` |

> ⚠️ These are demo credentials. Change them before any production use.

---

## 🗃️ Database Schema Overview

| Table | Description |
|-------|-------------|
| `students` | Student profiles, room assignment, department, status |
| `admins` | Admin accounts with `manager_level` and `assigned_block` |
| `rooms` | Room details: block, floor, type, capacity, fee, amenities |
| `applications` | Student room applications with status lifecycle |
| `allocations` | Active room assignments per student per semester |
| `fees` | Fee records per student with due date and status |
| `complaints` | Student complaints with category, priority, and status |
| `notices` | Admin announcements shown on student dashboard |
| `payment_transactions` | Online payment records with method and status |
| `tasks` | Admin-posted tasks with credit amount and deadline |
| `task_applications` | Student task applications with full status lifecycle |

---

## 🔒 Security

- ✅ **Prepared Statements** — All SQL queries use MySQLi prepared statements (no raw SQL)
- ✅ **Session Authentication** — Role-based PHP sessions with full validation on every page
- ✅ **Password Hashing** — bcrypt via `password_hash()` / `password_verify()`
- ✅ **Role-Based Access Control** — Students can't access admin pages and vice versa
- ✅ **Block-Level Scoping** — Hostel Managers see only their assigned block's data
- ✅ **Input Validation** — Server-side validation on all form submissions

---

---

## 📄 License

This project was developed as a university lab assignment for **United International University**.
For academic use only.

---

<div align="center">
Made with ❤️ by <strong>Team Nexus</strong> — United International University
</div>
