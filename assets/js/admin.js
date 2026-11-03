/**
 * Admin Dashboard Script - DAPUR TYAS by TECNO GARAGE
 */

document.addEventListener('DOMContentLoaded', function () {
    // Sidebar Mobile Toggle
    const btnToggle = document.getElementById('btnSidebarToggle');
    const sidebar = document.getElementById('adminSidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (btnToggle && sidebar && overlay) {
        btnToggle.addEventListener('click', function () {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('open');
        });

        overlay.addEventListener('click', function () {
            sidebar.classList.remove('open');
            overlay.classList.remove('open');
        });
    }

    // Live Clock Update
    const clockEl = document.getElementById('liveClock');
    if (clockEl) {
        function updateClock() {
            const now = new Date();
            const options = {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false
            };
            clockEl.textContent = now.toLocaleTimeString('id-ID', options) + ' WIB';
        }
        updateClock();
        setInterval(updateClock, 1000);
    }
});
