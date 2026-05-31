/* ============================================
   EDUVN - app.js
   Shared data, utilities, and global logic
   ============================================ */

// ---- Dark Mode ----
const themeToggle = () => {
  const current = document.documentElement.getAttribute('data-theme');
  const next = current === 'dark' ? 'light' : 'dark';
  document.documentElement.setAttribute('data-theme', next);
  localStorage.setItem('eduvn-theme', next);
  document.querySelectorAll('.theme-icon').forEach(el => {
    el.innerHTML = next === 'dark'
      ? '<i class="fas fa-sun"></i>'
      : '<i class="fas fa-moon"></i>';
  });
};

const initTheme = () => {
  const saved = localStorage.getItem('eduvn-theme') || 'light';
  document.documentElement.setAttribute('data-theme', saved);
  document.querySelectorAll('.theme-icon').forEach(el => {
    el.innerHTML = saved === 'dark'
      ? '<i class="fas fa-sun"></i>'
      : '<i class="fas fa-moon"></i>';
  });
};

// ---- Toast Notifications ----
const showToast = (type, title, msg, duration = 3500) => {
  const container = document.getElementById('toastContainer') || createToastContainer();
  const icons = { success: 'fa-check', warning: 'fa-exclamation', danger: 'fa-times', info: 'fa-info' };
  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  toast.innerHTML = `
    <div class="toast-icon"><i class="fas ${icons[type] || 'fa-info'}"></i></div>
    <div class="toast-content">
      <div class="toast-title">${title}</div>
      <div class="toast-msg">${msg}</div>
    </div>
    <button class="toast-close" onclick="this.closest('.toast').remove()">×</button>
  `;
  container.appendChild(toast);
  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateX(30px)';
    toast.style.transition = '0.3s ease';
    setTimeout(() => toast.remove(), 300);
  }, duration);
};

const createToastContainer = () => {
  const c = document.createElement('div');
  c.id = 'toastContainer';
  c.className = 'toast-container';
  document.body.appendChild(c);
  return c;
};

// ---- Tabs ----
const initTabs = (containerSelector) => {
  const containers = document.querySelectorAll(containerSelector || '.tabs-wrapper');
  containers.forEach(container => {
    const buttons = container.querySelectorAll('.tab-btn');
    const panels = container.querySelectorAll('.tab-content');
    buttons.forEach((btn, i) => {
      btn.addEventListener('click', () => {
        buttons.forEach(b => b.classList.remove('active'));
        panels.forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        if (panels[i]) panels[i].classList.add('active');
      });
    });
  });
};

// ---- Modal ----
const openModal = (id) => {
  const m = document.getElementById(id);
  if (m) { m.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
};
const closeModal = (id) => {
  const m = document.getElementById(id);
  if (m) { m.style.display = 'none'; document.body.style.overflow = ''; }
};

document.addEventListener('click', e => {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.style.display = 'none';
    document.body.style.overflow = '';
  }
  if (e.target.closest('[data-close-modal]')) {
    const id = e.target.closest('[data-close-modal]').dataset.closeModal;
    closeModal(id);
  }
  if (e.target.closest('[data-open-modal]')) {
    const id = e.target.closest('[data-open-modal]').dataset.openModal;
    openModal(id);
  }
});

// ============================================
// SAMPLE DATA
// ============================================
const DEPARTMENTS = [
  { id: 1, name: 'Tổ Toán', head: 'Nguyễn Văn Hùng', deputy: 'Trần Thị Mai', count: 8, color: '#1a6ef5', color2: '#0f52c1', icon: '📐', docs: 32 },
  { id: 2, name: 'Tổ Văn', head: 'Lê Thị Hoa', deputy: 'Phạm Văn Long', count: 7, color: '#8b5cf6', color2: '#6d28d9', icon: '📚', docs: 28 },
  { id: 3, name: 'Tổ Tiếng Anh', head: 'Nguyễn Thị Lan', deputy: 'Đỗ Văn Nam', count: 6, color: '#10b981', color2: '#059669', icon: '🌐', docs: 24 },
  { id: 4, name: 'Tổ Tin học', head: 'Trần Văn Đức', deputy: 'Nguyễn Thị Hà', count: 5, color: '#f59e0b', color2: '#d97706', icon: '💻', docs: 19 },
  { id: 5, name: 'Tổ KHTN', head: 'Lê Văn Minh', deputy: 'Bùi Thị Thu', count: 9, color: '#ef4444', color2: '#dc2626', icon: '🔬', docs: 41 },
  { id: 6, name: 'Tổ Lịch sử & Địa lý', head: 'Phạm Thị Ngọc', deputy: 'Hoàng Văn Tùng', count: 5, color: '#00c6ae', color2: '#0891b2', icon: '🗺️', docs: 17 },
];

const TEACHERS = [
  { id: 1, name: 'Nguyễn Thị Hương', subject: 'Toán', dept: 'Tổ Toán', deptId: 1, status: 'complete', age: 38, degree: 'Thạc sĩ', rank: 'GV hạng II', joined: '2010-08-01', phone: '0901234567', email: 'huong.nguyen@thcsthpt.edu.vn', avatar: 'https://i.pravatar.cc/150?img=47', address: '123 Lê Lợi, Phường 1, TP. Sóc Trăng', dob: '1986-05-12', gender: 'Nữ', hometown: 'Sóc Trăng', ethnicity: 'Kinh', party: true, partyYear: 2012 },
  { id: 2, name: 'Trần Văn Bình', subject: 'Vật lý', dept: 'Tổ KHTN', deptId: 5, status: 'complete', age: 42, degree: 'Thạc sĩ', rank: 'GV hạng I', joined: '2005-08-01', phone: '0912345678', email: 'binh.tran@thcsthpt.edu.vn', avatar: 'https://i.pravatar.cc/150?img=12', address: '45 Hai Bà Trưng, Phường 3, TP. Sóc Trăng', dob: '1982-11-20', gender: 'Nam', hometown: 'Cần Thơ', ethnicity: 'Kinh', party: true, partyYear: 2008 },
  { id: 3, name: 'Lê Thị Phương', subject: 'Ngữ văn', dept: 'Tổ Văn', deptId: 2, status: 'pending', age: 31, degree: 'Đại học', rank: 'GV hạng III', joined: '2018-08-01', phone: '0923456789', email: 'phuong.le@thcsthpt.edu.vn', avatar: 'https://i.pravatar.cc/150?img=23', address: '78 Nguyễn Huệ, Phường 2, TP. Sóc Trăng', dob: '1993-03-08', gender: 'Nữ', hometown: 'Hậu Giang', ethnicity: 'Kinh', party: false, partyYear: null },
  { id: 4, name: 'Phạm Đức Trung', subject: 'Tiếng Anh', dept: 'Tổ Tiếng Anh', deptId: 3, status: 'complete', age: 35, degree: 'Thạc sĩ', rank: 'GV hạng II', joined: '2014-08-01', phone: '0934567890', email: 'trung.pham@thcsthpt.edu.vn', avatar: 'https://i.pravatar.cc/150?img=33', address: '12 Trần Phú, Phường 4, TP. Sóc Trăng', dob: '1989-07-15', gender: 'Nam', hometown: 'Sóc Trăng', ethnicity: 'Kinh', party: true, partyYear: 2016 },
  { id: 5, name: 'Nguyễn Thị Kim Chi', subject: 'Hóa học', dept: 'Tổ KHTN', deptId: 5, status: 'complete', age: 40, degree: 'Thạc sĩ', rank: 'GV hạng II', joined: '2008-08-01', phone: '0945678901', email: 'chi.nguyen@thcsthpt.edu.vn', avatar: 'https://i.pravatar.cc/150?img=44', address: '56 Lê Thánh Tôn, Phường 6, TP. Sóc Trăng', dob: '1984-01-30', gender: 'Nữ', hometown: 'Bạc Liêu', ethnicity: 'Kinh', party: true, partyYear: 2011 },
  { id: 6, name: 'Hoàng Minh Khoa', subject: 'Tin học', dept: 'Tổ Tin học', deptId: 4, status: 'processing', age: 28, degree: 'Đại học', rank: 'GV hạng III', joined: '2021-08-01', phone: '0956789012', email: 'khoa.hoang@thcsthpt.edu.vn', avatar: 'https://i.pravatar.cc/150?img=15', address: '99 Nguyễn Trãi, Phường 5, TP. Sóc Trăng', dob: '1996-09-22', gender: 'Nam', hometown: 'An Giang', ethnicity: 'Kinh', party: false, partyYear: null },
  { id: 7, name: 'Bùi Thị Thu Hà', subject: 'Sinh học', dept: 'Tổ KHTN', deptId: 5, status: 'complete', age: 36, degree: 'Thạc sĩ', rank: 'GV hạng II', joined: '2013-08-01', phone: '0967890123', email: 'ha.bui@thcsthpt.edu.vn', avatar: 'https://i.pravatar.cc/150?img=25', address: '34 Phạm Hùng, Phường 7, TP. Sóc Trăng', dob: '1988-12-05', gender: 'Nữ', hometown: 'Sóc Trăng', ethnicity: 'Kinh', party: true, partyYear: 2015 },
  { id: 8, name: 'Võ Tấn Phát', subject: 'Toán', dept: 'Tổ Toán', deptId: 1, status: 'pending', age: 33, degree: 'Đại học', rank: 'GV hạng III', joined: '2016-08-01', phone: '0978901234', email: 'phat.vo@thcsthpt.edu.vn', avatar: 'https://i.pravatar.cc/150?img=8', address: '67 Đinh Tiên Hoàng, Phường 1, TP. Sóc Trăng', dob: '1991-04-18', gender: 'Nam', hometown: 'Trà Vinh', ethnicity: 'Kinh', party: false, partyYear: null },
  { id: 9, name: 'Đặng Thị Mỹ Linh', subject: 'Địa lý', dept: 'Tổ Lịch sử & Địa lý', deptId: 6, status: 'complete', age: 44, degree: 'Thạc sĩ', rank: 'GV hạng I', joined: '2003-08-01', phone: '0989012345', email: 'linh.dang@thcsthpt.edu.vn', avatar: 'https://i.pravatar.cc/150?img=38', address: '88 Phan Đình Phùng, Phường 2, TP. Sóc Trăng', dob: '1980-08-14', gender: 'Nữ', hometown: 'Sóc Trăng', ethnicity: 'Kinh', party: true, partyYear: 2006 },
  { id: 10, name: 'Lý Văn Khải', subject: 'Lịch sử', dept: 'Tổ Lịch sử & Địa lý', deptId: 6, status: 'processing', age: 39, degree: 'Đại học', rank: 'GV hạng II', joined: '2009-08-01', phone: '0901234568', email: 'khai.ly@thcsthpt.edu.vn', avatar: 'https://i.pravatar.cc/150?img=20', address: '100 Hùng Vương, Phường 3, TP. Sóc Trăng', dob: '1985-06-25', gender: 'Nam', hometown: 'Kiên Giang', ethnicity: 'Khmer', party: true, partyYear: 2013 },
  { id: 11, name: 'Trương Thị Lan Anh', subject: 'Ngữ văn', dept: 'Tổ Văn', deptId: 2, status: 'complete', age: 45, degree: 'Thạc sĩ', rank: 'GV hạng I', joined: '2002-08-01', phone: '0912345679', email: 'lananh.truong@thcsthpt.edu.vn', avatar: 'https://i.pravatar.cc/150?img=41', address: '22 Mạc Đĩnh Chi, Phường 4, TP. Sóc Trăng', dob: '1979-02-10', gender: 'Nữ', hometown: 'Sóc Trăng', ethnicity: 'Kinh', party: true, partyYear: 2005 },
  { id: 12, name: 'Phan Quốc Huy', subject: 'Tiếng Anh', dept: 'Tổ Tiếng Anh', deptId: 3, status: 'complete', age: 29, degree: 'Thạc sĩ', rank: 'GV hạng III', joined: '2020-08-01', phone: '0923456780', email: 'huy.phan@thcsthpt.edu.vn', avatar: 'https://i.pravatar.cc/150?img=52', address: '55 Lê Duẩn, Phường 5, TP. Sóc Trăng', dob: '1995-11-02', gender: 'Nam', hometown: 'Cần Thơ', ethnicity: 'Kinh', party: false, partyYear: null },
];

const REQUESTS = [
  { id: 1, title: 'Cập nhật chứng chỉ bồi dưỡng THPT 2024', dept: 'Tất cả tổ', deadline: '2025-01-15', status: 'pending', priority: 'high', desc: 'Yêu cầu tất cả giáo viên cập nhật chứng chỉ bồi dưỡng thường xuyên theo Thông tư 19/2019.', completed: 28, total: 40 },
  { id: 2, title: 'Bổ sung thông tin gia đình (vợ/chồng, con)', dept: 'Tất cả tổ', deadline: '2025-01-20', status: 'processing', priority: 'medium', desc: 'Cập nhật đầy đủ thông tin gia đình theo mẫu hồ sơ mới từ Sở GD&ĐT.', completed: 35, total: 40 },
  { id: 3, title: 'Nộp minh chứng giờ dạy giỏi cấp tỉnh', dept: 'Tổ Toán, Tổ Văn, Tổ Anh', deadline: '2024-12-30', status: 'complete', priority: 'high', desc: 'Minh chứng tham dự và đạt giải hội thi giáo viên dạy giỏi cấp tỉnh năm học 2024-2025.', completed: 8, total: 8 },
  { id: 4, title: 'Kế hoạch bài dạy học kỳ II', dept: 'Tất cả tổ', deadline: '2025-01-05', status: 'complete', priority: 'high', desc: 'Nộp kế hoạch bài dạy (KHBD) toàn bộ học kỳ II theo chương trình GDPT 2018.', completed: 40, total: 40 },
  { id: 5, title: 'Cập nhật trình độ ngoại ngữ (IELTS/TOEIC)', dept: 'Tất cả tổ', deadline: '2025-02-01', status: 'pending', priority: 'low', desc: 'Giáo viên bổ sung kết quả kiểm tra ngoại ngữ theo chuẩn Châu Âu hoặc tương đương.', completed: 12, total: 40 },
  { id: 6, title: 'Đăng ký đề tài nghiên cứu khoa học 2025', dept: 'Tổ KHTN, Tổ Toán', deadline: '2025-01-25', status: 'processing', priority: 'medium', desc: 'Đăng ký đề tài NCKHSP cấp cơ sở cho giáo viên có nguyện vọng.', completed: 7, total: 14 },
];

const ACTIVITIES = [
  { icon: 'fa-user-edit', color: '#1a6ef5', bg: 'var(--primary-light)', text: '<strong>Nguyễn Thị Hương</strong> đã cập nhật hồ sơ cá nhân', time: '5 phút trước' },
  { icon: 'fa-file-upload', color: '#10b981', bg: 'var(--accent-light)', text: '<strong>Tổ Toán</strong> đã nộp kế hoạch bài dạy học kỳ II', time: '12 phút trước' },
  { icon: 'fa-bell', color: '#f59e0b', bg: 'var(--warning-light)', text: '<strong>BGH</strong> đã tạo yêu cầu mới: Cập nhật chứng chỉ bồi dưỡng', time: '1 giờ trước' },
  { icon: 'fa-check-circle', color: '#22c55e', bg: 'var(--success-light)', text: '<strong>Trần Văn Bình</strong> đã hoàn thành hồ sơ minh chứng', time: '2 giờ trước' },
  { icon: 'fa-folder-plus', color: '#8b5cf6', bg: '#f3e8ff', text: '<strong>Tổ Văn</strong> đã thêm 3 tài liệu mới vào kho', time: '3 giờ trước' },
  { icon: 'fa-user-plus', color: '#1a6ef5', bg: 'var(--primary-light)', text: '<strong>Hoàng Minh Khoa</strong> đã tạo hồ sơ lần đầu', time: 'Hôm qua' },
];

const DOCS = [
  { type: 'folder', name: 'Phân phối chương trình', count: 12, updated: '20/01/2025', color: '#f59e0b', bg: '#fef3c7' },
  { type: 'folder', name: 'Kế hoạch dạy học', count: 38, updated: '18/01/2025', color: '#1a6ef5', bg: 'var(--primary-light)' },
  { type: 'folder', name: 'Đề kiểm tra', count: 56, updated: '17/01/2025', color: '#10b981', bg: 'var(--accent-light)' },
  { type: 'folder', name: 'Minh chứng thi đua', count: 24, updated: '15/01/2025', color: '#8b5cf6', bg: '#f3e8ff' },
  { type: 'folder', name: 'Văn bản BGD', count: 18, updated: '12/01/2025', color: '#ef4444', bg: 'var(--danger-light)' },
  { type: 'file', name: 'Kế hoạch giáo dục nhà trường 2024-2025.pdf', size: '2.4 MB', updated: '10/01/2025', color: '#ef4444', bg: '#fee2e2', ext: 'PDF' },
  { type: 'file', name: 'Danh sách GV đăng ký thi GVG 2025.xlsx', size: '156 KB', updated: '09/01/2025', color: '#10b981', bg: 'var(--accent-light)', ext: 'XLS' },
  { type: 'file', name: 'Thông báo lịch họp tháng 01-2025.docx', size: '48 KB', updated: '08/01/2025', color: '#1a6ef5', bg: 'var(--primary-light)', ext: 'DOC' },
];

// ---- Status helpers ----
const statusConfig = {
  complete:   { label: 'Hoàn thành',    badge: 'badge-success', dot: '#22c55e' },
  pending:    { label: 'Chưa cập nhật', badge: 'badge-warning', dot: '#f59e0b' },
  processing: { label: 'Đang xử lý',   badge: 'badge-info',    dot: '#3b82f6' },
};

const priorityConfig = {
  high:   { label: 'Cao',    badge: 'badge-danger' },
  medium: { label: 'Trung bình', badge: 'badge-warning' },
  low:    { label: 'Thấp',   badge: 'badge-neutral' },
};

// ---- Init on page load — chỉ theme, tabs. Sidebar & dropdown do layout.js xử lý ----
document.addEventListener('DOMContentLoaded', () => {
  initTheme();
  initTabs();
});
