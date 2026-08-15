<?php
/**
 * Hospital Management System — Header Component
 * Included at the top of every authenticated page
 */

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/functions.php';

$currentUser = getCurrentUser();
$notifCount = getUnreadNotificationCount();
$notifications = getRecentNotifications(5);
$pageTitle = $pageTitle ?? 'Dashboard';
$breadcrumbs = $breadcrumbs ?? [];
$roleColor = ROLE_COLORS[$currentUser['role']] ?? '#6366f1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($pageTitle) ?> — <?= APP_NAME ?></title>
    <meta name="description" content="<?= APP_NAME ?> — <?= APP_TAGLINE ?>">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <!-- Main CSS -->
    <link rel="stylesheet" href="/assets/css/style.css">
    
    <style>
        :root { --role-color: <?= $roleColor ?>; }
    </style>
</head>
<body>
<div class="app-layout">
    
    <!-- Sidebar Overlay (mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
    
    <?php include __DIR__ . '/sidebar.php'; ?>
    
    <div class="main-content">
        <!-- Top Header -->
        <header class="top-header">
            <div class="header-left">
                <button class="mobile-menu-btn" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <?php if (!empty($breadcrumbs)): ?>
                    <nav class="breadcrumb">
                        <a href="<?= ROLE_DASHBOARDS[$currentUser['role']] ?>"><i class="fas fa-home"></i></a>
                        <?php foreach ($breadcrumbs as $crumb): ?>
                        <span class="separator">/</span>
                        <?php if (isset($crumb['url'])): ?>
                        <a href="<?= $crumb['url'] ?>"><?= $crumb['label'] ?></a>
                        <?php else: ?>
                        <span><?= $crumb['label'] ?></span>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </nav>
                    <?php endif; ?>
                    <h1><?= sanitize($pageTitle) ?></h1>
                </div>
            </div>
            
            <div class="header-right">
                <!-- Search -->
                <button class="header-btn" data-tooltip="Search" onclick="toggleSearch()">
                    <i class="fas fa-search"></i>
                </button>
                
                <!-- Notifications -->
                <div class="dropdown">
                    <button class="header-btn" onclick="toggleNotifications()">
                        <i class="fas fa-bell"></i>
                        <?php if ($notifCount > 0): ?>
                        <span class="notification-dot"></span>
                        <?php endif; ?>
                    </button>
                    
                    <div class="notification-panel" id="notificationPanel">
                        <div class="notification-panel-header">
                            <span>Notifications <?php if ($notifCount > 0): ?>(<?= $notifCount ?>)<?php endif; ?></span>
                            <?php if ($notifCount > 0): ?>
                            <a href="#" onclick="markAllRead()" class="text-sm text-primary">Mark all read</a>
                            <?php endif; ?>
                        </div>
                        <div class="notification-list">
                            <?php if (empty($notifications)): ?>
                            <div class="empty-state" style="padding: 24px;">
                                <i class="fas fa-bell-slash"></i>
                                <p>No notifications yet</p>
                            </div>
                            <?php else: ?>
                            <?php foreach ($notifications as $notif): ?>
                            <div class="notification-item <?= $notif['is_read'] ? '' : 'unread' ?>">
                                <div class="notif-icon" style="background: <?= $notif['type'] === 'error' ? 'var(--danger-light)' : ($notif['type'] === 'warning' ? 'var(--warning-light)' : ($notif['type'] === 'success' ? 'var(--success-light)' : 'var(--info-light)')) ?>; color: <?= $notif['type'] === 'error' ? 'var(--danger)' : ($notif['type'] === 'warning' ? 'var(--warning)' : ($notif['type'] === 'success' ? 'var(--success)' : 'var(--info)')) ?>;">
                                    <i class="fas <?= $notif['type'] === 'appointment' ? 'fa-calendar' : ($notif['type'] === 'lab' ? 'fa-flask' : ($notif['type'] === 'prescription' ? 'fa-pills' : ($notif['type'] === 'billing' ? 'fa-receipt' : 'fa-info-circle'))) ?>"></i>
                                </div>
                                <div class="notif-content">
                                    <h4><?= sanitize($notif['title']) ?></h4>
                                    <p><?= sanitize($notif['message']) ?></p>
                                    <span class="notif-time"><?= timeAgo($notif['created_at']) ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- User Menu -->
                <div class="dropdown">
                    <button class="header-btn" onclick="toggleUserMenu()" style="width: auto; padding: 0 8px; gap: 8px; display: flex; align-items: center;">
                        <div class="avatar avatar-sm" style="background: <?= $roleColor ?>;">
                            <?= strtoupper(substr($currentUser['full_name'], 0, 1)) ?>
                        </div>
                    </button>
                    
                    <div class="dropdown-menu" id="userMenu">
                        <div style="padding: 10px 12px; border-bottom: 1px solid var(--gray-200); margin-bottom: 4px;">
                            <div class="font-semibold" style="font-size: 0.875rem;"><?= sanitize($currentUser['full_name']) ?></div>
                            <div class="text-xs text-muted"><?= ROLE_LABELS[$currentUser['role']] ?? ucfirst($currentUser['role']) ?></div>
                        </div>
                        <a href="/<?= $currentUser['role'] === 'pharmacist' ? 'pharmacy' : ($currentUser['role'] === 'lab_technician' ? 'lab' : $currentUser['role']) ?>/profile.php" class="dropdown-item">
                            <i class="fas fa-user"></i> My Profile
                        </a>
                        <a href="/admin/settings.php" class="dropdown-item">
                            <i class="fas fa-cog"></i> Settings
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="/auth/logout.php" class="dropdown-item danger">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- Content Area -->
        <div class="content-area">
            <?php
            // Flash message
            $flash = getFlash();
            if ($flash):
            ?>
            <div class="alert alert-<?= $flash['type'] ?>" id="flashAlert">
                <i class="fas <?= $flash['type'] === 'success' ? 'fa-check-circle' : ($flash['type'] === 'error' ? 'fa-exclamation-circle' : ($flash['type'] === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle')) ?>"></i>
                <?= sanitize($flash['message']) ?>
                <button class="alert-close" onclick="this.parentElement.remove()">×</button>
            </div>
            <?php endif; ?>
