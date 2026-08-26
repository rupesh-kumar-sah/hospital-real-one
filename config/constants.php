<?php
/**
 * Hospital Management System — Constants
 */

// Timezone
date_default_timezone_set('Asia/Kathmandu');

// Application
define('APP_NAME', 'MediCare HMS');
define('APP_VERSION', '1.0.0');
define('APP_TAGLINE', 'Hospital Management System');
define('APP_BASE_URL', 'http://localhost:9000');

// Roles
define('ROLE_ADMIN', 'admin');
define('ROLE_RECEPTIONIST', 'receptionist');
define('ROLE_DOCTOR', 'doctor');
define('ROLE_NURSE', 'nurse');
define('ROLE_PATIENT', 'patient');
define('ROLE_PHARMACIST', 'pharmacist');
define('ROLE_LAB_TECH', 'lab_technician');

// Role labels for display
define('ROLE_LABELS', [
    'admin' => 'Administrator',
    'receptionist' => 'Receptionist',
    'doctor' => 'Doctor',
    'nurse' => 'Nurse',
    'patient' => 'Patient',
    'pharmacist' => 'Pharmacist',
    'lab_technician' => 'Lab Technician'
]);

// Role dashboard paths
define('ROLE_DASHBOARDS', [
    'admin' => '/admin/dashboard.php',
    'receptionist' => '/receptionist/dashboard.php',
    'doctor' => '/doctor/dashboard.php',
    'nurse' => '/nurse/dashboard.php',
    'patient' => '/patient/dashboard.php',
    'pharmacist' => '/pharmacy/dashboard.php',
    'lab_technician' => '/lab/dashboard.php'
]);

// Role icons (Font Awesome)
define('ROLE_ICONS', [
    'admin' => 'fa-user-shield',
    'receptionist' => 'fa-headset',
    'doctor' => 'fa-user-doctor',
    'nurse' => 'fa-user-nurse',
    'patient' => 'fa-hospital-user',
    'pharmacist' => 'fa-pills',
    'lab_technician' => 'fa-flask'
]);

// Role accent colors
define('ROLE_COLORS', [
    'admin' => '#6366f1',
    'receptionist' => '#0ea5e9',
    'doctor' => '#10b981',
    'nurse' => '#f43f5e',
    'patient' => '#8b5cf6',
    'pharmacist' => '#f59e0b',
    'lab_technician' => '#06b6d4'
]);

// Appointment statuses
define('APPOINTMENT_STATUSES', [
    'pending_approval' => ['label' => 'Pending Approval', 'color' => '#8b5cf6', 'icon' => 'fa-hourglass-half'],
    'scheduled' => ['label' => 'Accepted & Confirmed', 'color' => '#3b82f6', 'icon' => 'fa-calendar-check'],
    'checked_in' => ['label' => 'Checked In & Billed', 'color' => '#f59e0b', 'icon' => 'fa-check-circle'],
    'in_progress' => ['label' => 'In Progress', 'color' => '#06b6d4', 'icon' => 'fa-stethoscope'],
    'completed' => ['label' => 'Completed', 'color' => '#10b981', 'icon' => 'fa-check-double'],
    'cancelled' => ['label' => 'Cancelled', 'color' => '#ef4444', 'icon' => 'fa-times-circle'],
    'no_show' => ['label' => 'No Show', 'color' => '#6b7280', 'icon' => 'fa-user-slash']
]);

// Bed statuses
define('BED_STATUSES', [
    'available' => ['label' => 'Available', 'color' => '#10b981'],
    'occupied' => ['label' => 'Occupied', 'color' => '#ef4444'],
    'reserved' => ['label' => 'Reserved', 'color' => '#f59e0b'],
    'maintenance' => ['label' => 'Maintenance', 'color' => '#6b7280']
]);

// Payment statuses
define('PAYMENT_STATUSES', [
    'unpaid' => ['label' => 'Unpaid', 'color' => '#ef4444'],
    'partial' => ['label' => 'Partial', 'color' => '#f59e0b'],
    'paid' => ['label' => 'Paid', 'color' => '#10b981'],
    'refunded' => ['label' => 'Refunded', 'color' => '#6b7280']
]);

// Pagination
define('ITEMS_PER_PAGE', 15);

// Date/Time formats
define('DATE_FORMAT', 'Y-m-d');
define('TIME_FORMAT', 'H:i');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');
define('DISPLAY_DATE_FORMAT', 'd M Y');
define('DISPLAY_TIME_FORMAT', 'h:i A');
define('DISPLAY_DATETIME_FORMAT', 'd M Y h:i A');

// UHID prefix
define('UHID_PREFIX', 'UHID-');
