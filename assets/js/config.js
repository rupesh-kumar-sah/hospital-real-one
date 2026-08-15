/**
 * MediCare HMS — Frontend Dynamic Backend Config & Auto-Refresh Sync
 */
window.HMS_CONFIG = {
    // Render Backend API URL (Replace with your actual Render URL when deployed)
    RENDER_BACKEND_URL: window.location.hostname.includes('vercel.app')
        ? 'https://medicare-hms-backend.onrender.com'
        : window.location.origin,
    
    // Auto-refresh interval in milliseconds (3 seconds for fast live updates)
    AUTO_REFRESH_MS: 3000,
    
    // Check Backend & Laptop Database Connection Status
    async checkDatabaseHealth() {
        try {
            const res = await fetch(`${this.RENDER_BACKEND_URL}/api/health.php`);
            const data = await res.json();
            console.log('[HMS DB Connection Status]', data);
            return data;
        } catch (err) {
            console.error('[HMS DB Connection Error]', err);
            return { status: 'error', error: err.message };
        }
    }
};

// Auto-ping database connection health on page load
document.addEventListener('DOMContentLoaded', () => {
    window.HMS_CONFIG.checkDatabaseHealth();
});
