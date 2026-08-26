<?php
/**
 * Hospital Management System — Utility Functions
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

/**
 * Sanitize input
 */
function sanitize(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Format date for display
 */
function formatDate(?string $date): string {
    if (!$date) return 'N/A';
    try {
        if (strpos($date, ' ') !== false || strpos($date, 'T') !== false) {
            $dt = new DateTime($date, new DateTimeZone('UTC'));
            $dt->setTimezone(new DateTimeZone(date_default_timezone_get()));
            return $dt->format(DISPLAY_DATE_FORMAT);
        }
        return date(DISPLAY_DATE_FORMAT, strtotime($date));
    } catch (Exception $e) {
        return date(DISPLAY_DATE_FORMAT, strtotime($date));
    }
}

/**
 * Format time for display
 */
function formatTime(?string $time): string {
    if (!$time) return 'N/A';
    try {
        if (strpos($time, ' ') !== false || strpos($time, 'T') !== false) {
            $dt = new DateTime($time, new DateTimeZone('UTC'));
            $dt->setTimezone(new DateTimeZone(date_default_timezone_get()));
            return $dt->format(DISPLAY_TIME_FORMAT);
        }
        return date(DISPLAY_TIME_FORMAT, strtotime($time));
    } catch (Exception $e) {
        return date(DISPLAY_TIME_FORMAT, strtotime($time));
    }
}

/**
 * Format datetime for display
 */
function formatDateTime(?string $datetime): string {
    if (!$datetime) return 'N/A';
    try {
        $dt = new DateTime($datetime, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone(date_default_timezone_get()));
        return $dt->format(DISPLAY_DATETIME_FORMAT);
    } catch (Exception $e) {
        return date(DISPLAY_DATETIME_FORMAT, strtotime($datetime));
    }
}

/**
 * Generate a unique UHID
 */
function generateUHID(): string {
    $db = getDB();
    $stmt = $db->query("SELECT MAX(CAST(REPLACE(uhid, '" . UHID_PREFIX . "', '') AS INTEGER)) as max_id FROM patients");
    $result = $stmt->fetch();
    $nextId = ($result['max_id'] ?? 0) + 1;
    return UHID_PREFIX . str_pad($nextId, 5, '0', STR_PAD_LEFT);
}

/**
 * Generate invoice number
 */
function generateInvoiceNumber(): string {
    $db = getDB();
    $prefix = 'INV-' . date('Ym') . '-';
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM billing WHERE invoice_number LIKE ?");
    $stmt->execute([$prefix . '%']);
    $result = $stmt->fetch();
    $nextNum = ($result['count'] ?? 0) + 1;
    return $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
}

/**
 * Generate next token number for a doctor on a given date
 */
function generateToken(int $doctorId, string $date): int {
    $db = getDB();
    $stmt = $db->prepare("SELECT MAX(token_number) as max_token FROM appointments WHERE doctor_id = ? AND appointment_date = ?");
    $stmt->execute([$doctorId, $date]);
    $result = $stmt->fetch();
    return ($result['max_token'] ?? 0) + 1;
}

/**
 * Get unread notification count for current user
 */
function getUnreadNotificationCount(): int {
    if (!isLoggedIn()) return 0;
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([getUserId()]);
    $result = $stmt->fetch();
    return (int)($result['count'] ?? 0);
}

/**
 * Get recent notifications for current user
 */
function getRecentNotifications(int $limit = 5): array {
    if (!isLoggedIn()) return [];
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([getUserId(), $limit]);
    return $stmt->fetchAll();
}

/**
 * Get paginated results
 */
function paginate(string $query, array $params, int $page = 1, int $perPage = ITEMS_PER_PAGE): array {
    $db = getDB();
    
    // Count total
    $countQuery = "SELECT COUNT(*) as total FROM (" . $query . ") as subquery";
    $stmt = $db->prepare($countQuery);
    $stmt->execute($params);
    $total = (int)$stmt->fetch()['total'];
    
    // Get page results
    $offset = ($page - 1) * $perPage;
    $stmt = $db->prepare($query . " LIMIT ? OFFSET ?");
    $allParams = array_merge($params, [$perPage, $offset]);
    $stmt->execute($allParams);
    $results = $stmt->fetchAll();
    
    return [
        'data' => $results,
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => ceil($total / $perPage),
        'has_prev' => $page > 1,
        'has_next' => ($page * $perPage) < $total
    ];
}

/**
 * Render pagination HTML
 */
function renderPagination(array $pagination, string $baseUrl = ''): string {
    if ($pagination['total_pages'] <= 1) return '';
    
    $html = '<div class="pagination">';
    
    // Previous
    if ($pagination['has_prev']) {
        $html .= '<a href="' . $baseUrl . '?page=' . ($pagination['page'] - 1) . '" class="pagination-btn"><i class="fas fa-chevron-left"></i> Prev</a>';
    }
    
    // Page numbers
    $start = max(1, $pagination['page'] - 2);
    $end = min($pagination['total_pages'], $pagination['page'] + 2);
    
    if ($start > 1) {
        $html .= '<a href="' . $baseUrl . '?page=1" class="pagination-btn">1</a>';
        if ($start > 2) $html .= '<span class="pagination-dots">...</span>';
    }
    
    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $pagination['page'] ? ' active' : '';
        $html .= '<a href="' . $baseUrl . '?page=' . $i . '" class="pagination-btn' . $active . '">' . $i . '</a>';
    }
    
    if ($end < $pagination['total_pages']) {
        if ($end < $pagination['total_pages'] - 1) $html .= '<span class="pagination-dots">...</span>';
        $html .= '<a href="' . $baseUrl . '?page=' . $pagination['total_pages'] . '" class="pagination-btn">' . $pagination['total_pages'] . '</a>';
    }
    
    // Next
    if ($pagination['has_next']) {
        $html .= '<a href="' . $baseUrl . '?page=' . ($pagination['page'] + 1) . '" class="pagination-btn">Next <i class="fas fa-chevron-right"></i></a>';
    }
    
    $html .= '<span class="pagination-info">Showing ' . (($pagination['page']-1)*$pagination['per_page']+1) . '-' . min($pagination['page']*$pagination['per_page'], $pagination['total']) . ' of ' . $pagination['total'] . '</span>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Calculate age from date of birth
 */
function calculateAge(?string $dob): string {
    if (!$dob) return 'N/A';
    $birth = new DateTime($dob);
    $now = new DateTime();
    $diff = $now->diff($birth);
    return $diff->y . ' yrs';
}

/**
 * Get status badge HTML
 */
function statusBadge(string $status, array $statusConfig = []): string {
    $config = $statusConfig[$status] ?? ['label' => ucfirst($status), 'color' => '#6b7280'];
    return '<span class="badge" style="--badge-color: ' . $config['color'] . '">' . $config['label'] . '</span>';
}

/**
 * Get time ago string
 */
function timeAgo(string $datetime): string {
    $time = time() - strtotime($datetime);
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' min ago';
    if ($time < 86400) return floor($time/3600) . ' hr ago';
    if ($time < 604800) return floor($time/86400) . ' days ago';
    return formatDate($datetime);
}

/**
 * Format currency
 */
function formatCurrency(float $amount): string {
    return 'Rs. ' . number_format($amount, 2);
}

/**
 * Get doctor details by user_id
 */
function getDoctorByUserId(int $userId): ?array {
    $db = getDB();
    $stmt = $db->prepare("SELECT d.*, u.full_name, u.email, u.phone, u.status as user_status, dep.name as department_name 
                          FROM doctors d 
                          JOIN users u ON d.user_id = u.id 
                          LEFT JOIN departments dep ON d.department_id = dep.id 
                          WHERE d.user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: null;
}

/**
 * Get patient details by user_id
 */
function getPatientByUserId(int $userId): ?array {
    $db = getDB();
    $stmt = $db->prepare("SELECT p.*, u.full_name, u.email, u.phone, u.status as user_status 
                          FROM patients p 
                          JOIN users u ON p.user_id = u.id 
                          WHERE p.user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: null;
}

/**
 * Get nurse details by user_id
 */
function getNurseByUserId(int $userId): ?array {
    $db = getDB();
    $stmt = $db->prepare("SELECT n.*, u.full_name, u.email, u.phone, w.name as ward_name 
                          FROM nurses n 
                          JOIN users u ON n.user_id = u.id 
                          LEFT JOIN wards w ON n.ward_id = w.id 
                          WHERE n.user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: null;
}
