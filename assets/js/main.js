/**
 * Hospital Management System — Main JavaScript
 */

// =====================================================
// SIDEBAR TOGGLE (Mobile)
// =====================================================
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('mobile-open');
    overlay.classList.toggle('active');
}

// =====================================================
// DROPDOWNS
// =====================================================
function toggleNotifications() {
    const panel = document.getElementById('notificationPanel');
    const userMenu = document.getElementById('userMenu');
    if (userMenu) userMenu.classList.remove('active');
    panel.classList.toggle('active');
}

function toggleUserMenu() {
    const userMenu = document.getElementById('userMenu');
    const panel = document.getElementById('notificationPanel');
    const roleMenu = document.getElementById('roleMenu');
    if (panel) panel.classList.remove('active');
    if (roleMenu) roleMenu.classList.remove('active');
    userMenu.classList.toggle('active');
}

function toggleRoleMenu() {
    const roleMenu = document.getElementById('roleMenu');
    const userMenu = document.getElementById('userMenu');
    const panel = document.getElementById('notificationPanel');
    if (userMenu) userMenu.classList.remove('active');
    if (panel) panel.classList.remove('active');
    if (roleMenu) roleMenu.classList.toggle('active');
}

// Close dropdowns on outside click
document.addEventListener('click', (e) => {
    if (!e.target.closest('.dropdown')) {
        document.querySelectorAll('.dropdown-menu, .notification-panel').forEach(el => {
            el.classList.remove('active');
        });
    }
});

// =====================================================
// FLASH MESSAGE AUTO-DISMISS
// =====================================================
document.addEventListener('DOMContentLoaded', () => {
    const flash = document.getElementById('flashAlert');
    if (flash) {
        setTimeout(() => {
            flash.style.opacity = '0';
            flash.style.transform = 'translateY(-10px)';
            flash.style.transition = 'all 0.3s ease';
            setTimeout(() => flash.remove(), 300);
        }, 5000);
    }
});

// =====================================================
// TABS
// =====================================================
function switchTab(tabId) {
    // Update buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.dataset.tab === tabId) btn.classList.add('active');
    });
    
    // Update content
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
    });
    document.getElementById(tabId)?.classList.add('active');
}

// =====================================================
// MODALS
// =====================================================
function openModal(modalId) {
    document.getElementById(modalId)?.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
    document.getElementById(modalId)?.classList.remove('active');
    document.body.style.overflow = '';
}

// Close modal on overlay click
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('active');
        document.body.style.overflow = '';
    }
});

// Close modal on Escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(modal => {
            modal.classList.remove('active');
        });
        document.body.style.overflow = '';
    }
});

// =====================================================
// SEARCH
// =====================================================
function toggleSearch() {
    const searchBox = document.getElementById('globalSearch');
    if (searchBox) {
        searchBox.classList.toggle('d-none');
        if (!searchBox.classList.contains('d-none')) {
            searchBox.querySelector('input')?.focus();
        }
    }
}

// =====================================================
// FORM VALIDATION
// =====================================================
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return true;
    
    let isValid = true;
    
    // Check required fields
    form.querySelectorAll('[required]').forEach(field => {
        const errorEl = field.parentElement.querySelector('.form-error');
        
        if (!field.value.trim()) {
            field.classList.add('error');
            if (errorEl) errorEl.textContent = 'This field is required';
            isValid = false;
        } else {
            field.classList.remove('error');
            if (errorEl) errorEl.textContent = '';
        }
    });
    
    // Check email fields
    form.querySelectorAll('input[type="email"]').forEach(field => {
        if (field.value && !isValidEmail(field.value)) {
            field.classList.add('error');
            const errorEl = field.parentElement.querySelector('.form-error');
            if (errorEl) errorEl.textContent = 'Invalid email address';
            isValid = false;
        }
    });
    
    // Check phone fields
    form.querySelectorAll('input[type="tel"]').forEach(field => {
        if (field.value && !isValidPhone(field.value)) {
            field.classList.add('error');
            const errorEl = field.parentElement.querySelector('.form-error');
            if (errorEl) errorEl.textContent = 'Invalid phone number';
            isValid = false;
        }
    });
    
    return isValid;
}

function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function isValidPhone(phone) {
    return /^[0-9+\-\s()]{7,20}$/.test(phone);
}

// Remove error on input
document.addEventListener('input', (e) => {
    if (e.target.classList.contains('error')) {
        e.target.classList.remove('error');
        const errorEl = e.target.parentElement.querySelector('.form-error');
        if (errorEl) errorEl.textContent = '';
    }
});

// =====================================================
// CONFIRM DELETE
// =====================================================
function confirmDelete(message = 'Are you sure you want to delete this item?') {
    return confirm(message);
}

// =====================================================
// FORMAT NUMBERS
// =====================================================
function formatCurrency(amount) {
    return 'Rs. ' + parseFloat(amount).toLocaleString('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// =====================================================
// MARK NOTIFICATIONS READ
// =====================================================
async function markAllRead() {
    try {
        const response = await fetch('/api/notifications.php?action=mark_all_read');
        if (response.ok) {
            document.querySelectorAll('.notification-item.unread').forEach(item => {
                item.classList.remove('unread');
            });
            const dot = document.querySelector('.notification-dot');
            if (dot) dot.remove();
        }
    } catch (err) {
        console.error('Failed to mark notifications as read:', err);
    }
}

// =====================================================
// PRINT PAGE
// =====================================================
function printPage() {
    window.print();
}

// =====================================================
// DYNAMIC TABLE SEARCH
// =====================================================
function filterTable(inputId, tableId) {
    const filter = document.getElementById(inputId).value.toLowerCase();
    const rows = document.querySelectorAll(`#${tableId} tbody tr`);
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
}

// =====================================================
// ANIMATE NUMBERS
// =====================================================
function animateNumber(elementId, target, duration = 1000) {
    const el = document.getElementById(elementId);
    if (!el) return;
    
    const start = 0;
    const startTime = performance.now();
    
    function update(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3); // ease-out cubic
        const current = Math.floor(start + (target - start) * eased);
        el.textContent = current.toLocaleString();
        
        if (progress < 1) {
            requestAnimationFrame(update);
        }
    }
    
    requestAnimationFrame(update);
}

// =====================================================
// DATE/TIME HELPERS
// =====================================================
function formatDateForDisplay(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { day: '2-digit', month: 'short', year: 'numeric' });
}

function formatTimeForDisplay(timeStr) {
    const [hours, minutes] = timeStr.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour % 12 || 12;
    return `${displayHour}:${minutes} ${ampm}`;
}

// =====================================================
// LOADING STATE
// =====================================================
function showLoading(buttonId) {
    const btn = document.getElementById(buttonId);
    if (!btn) return;
    btn.disabled = true;
    btn.dataset.originalText = btn.innerHTML;
    btn.innerHTML = '<div class="spinner" style="width:18px;height:18px;border-width:2px;"></div> Processing...';
}

function hideLoading(buttonId) {
    const btn = document.getElementById(buttonId);
    if (!btn) return;
    btn.disabled = false;
    btn.innerHTML = btn.dataset.originalText || 'Submit';
}

// =====================================================
// DEBOUNCE
// =====================================================
function debounce(func, wait = 300) {
    let timeout;
    return function executedFunction(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}
