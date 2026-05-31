/**
 * layout-php.js
 * JS dùng chung cho các trang PHP
 * Thay thế layout.js (không cần inject HTML nữa vì PHP đã render)
 * Chỉ xử lý tương tác: sidebar, dropdown, theme, toast
 */

/* ============================================================
   SIDEBAR
   ============================================================ */
function openSidebar() {
    const sb = document.getElementById('sidebar');
    const ov = document.getElementById('sidebarOverlay');
    if (sb) sb.classList.add('open');
    if (ov) ov.classList.add('visible');
    document.body.style.overflow = 'hidden';
}

function closeSidebar() {
    const sb = document.getElementById('sidebar');
    const ov = document.getElementById('sidebarOverlay');
    if (sb) sb.classList.remove('open');
    if (ov) ov.classList.remove('visible');
    document.body.style.overflow = '';
}

function toggleSidebar() {
    const sb = document.getElementById('sidebar');
    sb?.classList.contains('open') ? closeSidebar() : openSidebar();
}

/* ============================================================
   DROPDOWN (Thông báo, User menu)
   ============================================================ */
function initDropdowns() {
    document.addEventListener('click', e => {
        const trigger = e.target.closest('[data-dropdown]');

        if (trigger) {
            const id     = trigger.dataset.dropdown;
            const target = document.getElementById(id);
            if (!target) return;

            const isOpen = target.style.display === 'block';

            // Đóng tất cả dropdown đang mở
            document.querySelectorAll('.dropdown-menu, .notif-panel')
                    .forEach(m => m.style.display = 'none');

            // Mở/đóng cái được click
            if (!isOpen) target.style.display = 'block';

            e.stopPropagation();
        } else if (!e.target.closest('.dropdown-menu') &&
                   !e.target.closest('.notif-panel')) {
            document.querySelectorAll('.dropdown-menu, .notif-panel')
                    .forEach(m => m.style.display = 'none');
        }
    });
}

/* ============================================================
   THEME (Dark / Light mode)
   ============================================================ */
const themeToggle = () => {
    const current = document.documentElement.getAttribute('data-theme');
    const next    = current === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('eduvn-theme', next);
    syncThemeIcons(next);
};

const syncThemeIcons = (theme) => {
    const isDark = theme === 'dark';

    // Icon trong header
    document.querySelectorAll('.theme-icon').forEach(el => {
        el.innerHTML = isDark
            ? '<i class="fas fa-sun"></i>'
            : '<i class="fas fa-moon"></i>';
    });

    // Icon trong sidebar nav
    const navIcon = document.querySelector('.theme-icon-nav');
    const label   = document.getElementById('themeLabel');
    if (navIcon) navIcon.className = `fas ${isDark ? 'fa-sun' : 'fa-moon'} theme-icon-nav`;
    if (label)   label.textContent = isDark ? 'Chế độ sáng' : 'Chế độ tối';
};

const initTheme = () => {
    const saved = localStorage.getItem('eduvn-theme') || 'light';
    document.documentElement.setAttribute('data-theme', saved);
    syncThemeIcons(saved);
};

/* ============================================================
   TOAST NOTIFICATIONS
   ============================================================ */
const showToast = (type, title, msg, duration = 3500) => {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const icons = {
        success: 'fa-check',
        warning: 'fa-exclamation',
        danger:  'fa-times',
        info:    'fa-info',
    };

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
        <div class="toast-icon">
            <i class="fas ${icons[type] || 'fa-info'}"></i>
        </div>
        <div class="toast-content">
            <div class="toast-title">${title}</div>
            <div class="toast-msg">${msg}</div>
        </div>
        <button class="toast-close" onclick="this.closest('.toast').remove()">×</button>`;

    container.appendChild(toast);

    // Tự ẩn sau duration ms
    setTimeout(() => {
        toast.style.cssText = 'opacity:0;transform:translateX(30px);transition:0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, duration);
};

/* ============================================================
   MODAL (dùng cho các trang có popup)
   ============================================================ */
const openModal = (id) => {
    const m = document.getElementById(id);
    if (m) {
        m.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
};

const closeModal = (id) => {
    const m = document.getElementById(id);
    if (m) {
        m.style.display = 'none';
        document.body.style.overflow = '';
    }
};

/* ============================================================
   GLOBAL SEARCH
   Nhấn Enter → chuyển sang trang tìm kiếm
   ============================================================ */
function initGlobalSearch() {
    const input = document.getElementById('globalSearch');
    if (!input) return;

    input.addEventListener('keydown', e => {
        if (e.key === 'Enter' && input.value.trim()) {
            window.location.href = `/teachers.php?q=${encodeURIComponent(input.value.trim())}`;
        }
    });
}

/* ============================================================
   TABS (cho các trang có tab như teacher-detail)
   ============================================================ */
function initTabs(containerSelector = '.tabs-wrapper') {
    document.querySelectorAll(containerSelector).forEach(container => {
        const buttons = container.querySelectorAll('.tab-btn');
        const panels  = container.querySelectorAll('.tab-content');

        buttons.forEach((btn, i) => {
            btn.addEventListener('click', () => {
                buttons.forEach(b => b.classList.remove('active'));
                panels.forEach(p => p.classList.remove('active'));
                btn.classList.add('active');
                if (panels[i]) panels[i].classList.add('active');
            });
        });
    });
}

/* ============================================================
   API HELPER (gọi PHP API từ JS khi cần)
   ============================================================ */
const API = {
    async get(endpoint, params = {}) {
        const url = new URL(endpoint, window.location.origin);
        Object.entries(params).forEach(([k, v]) => {
            if (v !== '' && v !== null && v !== undefined) {
                url.searchParams.set(k, v);
            }
        });

        const res = await fetch(url, {
            headers: {
                'Authorization': `Bearer ${window.API_TOKEN || ''}`,
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (res.status === 401) {
            window.location.href = '/index.php';
            return;
        }

        return res.json();
    },

    async post(endpoint, body = {}) {
        const res = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${window.API_TOKEN || ''}`,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body),
        });

        if (res.status === 401) {
            window.location.href = '/index.php';
            return;
        }

        return res.json();
    },

    async upload(endpoint, formData) {
        const res = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${window.API_TOKEN || ''}`,
                'X-Requested-With': 'XMLHttpRequest',
                // Không set Content-Type — để browser tự set với boundary
            },
            body: formData,
        });
        return res.json();
    },
};

/* ============================================================
   CONFIRM DELETE (dùng chung)
   ============================================================ */
function confirmDelete(message, callback) {
    if (confirm(message || 'Bạn có chắc muốn xóa không? Thao tác này không thể hoàn tác.')) {
        callback();
    }
}

/* ============================================================
   MODAL EVENTS (click overlay để đóng, Escape để đóng)
   ============================================================ */
function initModalEvents() {
    document.addEventListener('click', e => {
        // Click vào overlay
        if (e.target.classList.contains('modal-overlay')) {
            e.target.style.display = 'none';
            document.body.style.overflow = '';
        }
        // Click nút [data-close-modal]
        const closeBtn = e.target.closest('[data-close-modal]');
        if (closeBtn) closeModal(closeBtn.dataset.closeModal);

        // Click nút [data-open-modal]
        const openBtn = e.target.closest('[data-open-modal]');
        if (openBtn) openModal(openBtn.dataset.openModal);
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            // Đóng tất cả modal
            document.querySelectorAll('.modal-overlay')
                    .forEach(m => {
                        m.style.display = 'none';
                        document.body.style.overflow = '';
                    });
            closeSidebar();
        }
    });
}

/* ============================================================
   INIT — chạy khi DOM sẵn sàng
   ============================================================ */
document.addEventListener('DOMContentLoaded', () => {
    // Theme
    initTheme();

    // Theme buttons
    document.querySelectorAll('.btn-theme').forEach(btn => {
        btn.addEventListener('click', themeToggle);
    });

    // Sidebar toggle
    const toggleBtn = document.getElementById('sidebarToggleBtn');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', e => {
            e.stopPropagation();
            toggleSidebar();
        });
    }

    // Sidebar overlay
    const overlay = document.getElementById('sidebarOverlay');
    if (overlay) overlay.addEventListener('click', closeSidebar);

    // Đóng sidebar khi click nav item trên mobile
    document.querySelectorAll('.nav-item').forEach(item => {
        item.addEventListener('click', () => {
            if (window.innerWidth <= 768) closeSidebar();
        });
    });

    // Theme nav button trong sidebar
    const themeNavBtn = document.getElementById('themeNavBtn');
    if (themeNavBtn) {
        themeNavBtn.addEventListener('click', e => {
            e.preventDefault();
            themeToggle();
        });
    }

    // Dropdowns
    initDropdowns();

    // Modal events
    initModalEvents();

    // Global search
    initGlobalSearch();

    // Tabs
    initTabs();

    // Flash message auto hide
    setTimeout(() => {
        document.querySelectorAll('.toast:not([data-persistent])')
                .forEach(t => t.remove());
    }, 4000);
});
