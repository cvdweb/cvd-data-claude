/**
 * app-php.js — Utilities cho PHP version
 * Đã loại bỏ: themeToggle, initTheme, openModal, closeModal, initTabs
 * (những hàm đó đã có trong layout-php.js)
 */

// ---- Status / Priority config (dùng trong JS nếu cần) ----
const statusConfig = {
  complete:   { label: 'Hoàn thành',    badge: 'badge-success', dot: '#22c55e' },
  pending:    { label: 'Chưa cập nhật', badge: 'badge-warning', dot: '#f59e0b' },
  processing: { label: 'Đang xử lý',   badge: 'badge-info',    dot: '#3b82f6' },
};

const priorityConfig = {
  high:   { label: 'Cao',         badge: 'badge-danger'  },
  medium: { label: 'Trung bình',  badge: 'badge-warning' },
  low:    { label: 'Thấp',        badge: 'badge-neutral' },
};

// ---- Format helpers ----
const formatDate = (dateStr) => {
  if (!dateStr) return '—';
  const d = new Date(dateStr + 'T00:00:00');
  return d.toLocaleDateString('vi-VN');
};

const formatFileSize = (bytes) => {
  if (!bytes) return '—';
  if (bytes < 1024)     return bytes + ' B';
  if (bytes < 1048576)  return (bytes/1024).toFixed(1) + ' KB';
  return (bytes/1048576).toFixed(1) + ' MB';
};

const timeAgo = (dateStr) => {
  if (!dateStr) return '—';
  const diff = Math.floor((Date.now() - new Date(dateStr)) / 1000);
  if (diff < 60)       return 'Vừa xong';
  if (diff < 3600)     return Math.floor(diff/60) + ' phút trước';
  if (diff < 86400)    return Math.floor(diff/3600) + ' giờ trước';
  if (diff < 604800)   return Math.floor(diff/86400) + ' ngày trước';
  return new Date(dateStr).toLocaleDateString('vi-VN');
};
