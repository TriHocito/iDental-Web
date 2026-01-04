// assets/js/admin.js

let currentTab = 'pending';

document.addEventListener("DOMContentLoaded", function() {
    // Sidebar Toggle
    const toggleBtn = document.querySelector('.toggle-sidebar-btn');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebarOverlay'); // Admin might not have overlay in HTML yet, but good to have logic

    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('show');
            // Admin CSS uses transform, so 'show' class is correct based on CSS
        });
    }

    // Bind Filter Button
    document.getElementById('filterBtn')?.addEventListener('click', function() {
        loadAppts(currentTab, null);
    });

    // Bind schedule form (AJAX load)
    const scheduleForm = document.getElementById('scheduleForm');
    if (scheduleForm) {
        scheduleForm.addEventListener('submit', loadScheduleAjax);
    }

    buildWeekPopup();

    // Đóng popup chọn tuần khi click ngoài
    document.addEventListener('click', (e) => {
        const pop = document.getElementById('weekPopup');
        if (!pop) return;
        if (pop.style.display === 'none') return;
        if (!pop.contains(e.target) && !e.target.closest('#weekPopup') && !e.target.closest('[data-week-toggle]')) {
            pop.style.display = 'none';
        }
    });
});

// --- Search Patient ---
async function triggerSearch() {
    const val = document.getElementById('globalSearchPat').value;
    if(!val) { alert("Vui lòng nhập thông tin tìm kiếm!"); return; }
    
    try {
        const res = await fetch(`../controllers/admin_actions.php?action=search_patient&keyword=${val}`);
        const data = await res.json();
        const dropdown = document.getElementById('searchResult');
        dropdown.innerHTML = '';
        
        if(data.length > 0) {
            dropdown.style.display = 'block';
            data.forEach(p => {
                dropdown.insertAdjacentHTML('beforeend', `
                    <div class="search-item" onclick="viewPatientHistory(${p.id_benhnhan}, '${p.ten_day_du}')">
                        <div><strong>${p.ten_day_du}</strong><br><small>${p.sdt}</small></div>
                        <i class="fas fa-history text-muted"></i>
                    </div>`);
            });
        } else { 
            dropdown.style.display = 'none';
            alert("Không tìm thấy bệnh nhân nào có SĐT hoặc Tên này!");
        }
    } catch(e) { console.error(e); }
}

// --- Load Appointments (Tab + Filter) ---
async function loadAppts(status, btn) {
    currentTab = status;
    
    // UI Tab Activation
    if(btn) {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
    }

    const tbody = document.getElementById('apptTableBody');
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center">Đang tải...</td></tr>';
    
    // Get filter values
    const phone = document.getElementById('filterPhone').value;
    const docId = document.getElementById('filterDoc').value;
    const dFrom = document.getElementById('filterDateFrom').value;
    const dTo = document.getElementById('filterDateTo').value;

    try {
        const url = `../controllers/admin_actions.php?action=filter_appointments&tab_status=${status}&phone=${phone}&doctor_id=${docId}&date_from=${dFrom}&date_to=${dTo}`;
        const res = await fetch(url);
        const data = await res.json();
        
        tbody.innerHTML = '';
        if(data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center">Không có dữ liệu.</td></tr>';
            return;
        }

        data.forEach(row => {
            let badge = 'pending';
            let statusText = 'Chờ duyệt';
            
            if(row.trang_thai == 'da_xac_nhan') { badge = 'confirmed'; statusText = 'Đã xác nhận'; }
            else if(row.trang_thai == 'hoan_thanh') { badge = 'success'; statusText = 'Hoàn thành'; }
            else if(row.trang_thai == 'huy') { badge = 'danger'; statusText = 'Đã hủy'; }
            else if(row.trang_thai == 'cho_xac_nhan') { badge = 'pending'; statusText = 'Chờ duyệt'; }
            
            let actions = '';
            if(row.trang_thai == 'cho_xac_nhan') {
                actions = `<a href="../controllers/admin_actions.php?action=approve_appointment&id=${row.id_lichhen}" class="btn-icon text-success"><i class="fas fa-check"></i></a>
                           <a href="../controllers/admin_actions.php?action=reject_appointment&id=${row.id_lichhen}" class="btn-icon text-danger"><i class="fas fa-times"></i></a>`;
            } else if(row.trang_thai == 'da_xac_nhan') {
                actions = `<a href="../controllers/admin_actions.php?action=reject_appointment&id=${row.id_lichhen}" class="btn-icon text-danger" title="Hủy lịch"><i class="fas fa-times"></i></a>`;
            }

            tbody.insertAdjacentHTML('beforeend', `
                <tr>
                    <td data-label="Ngày giờ">${new Date(row.ngay_gio_hen).toLocaleString('vi-VN')}</td>
                    <td data-label="Khách hàng"><strong>${row.ten_bn}</strong><br><small>${row.sdt}</small></td>
                    <td data-label="Dịch vụ">${row.ten_dich_vu}</td>
                    <td data-label="Bác sĩ">${row.ten_bs || '-'}</td>
                    <td data-label="Trạng thái"><span class="status-badge bg-${badge}">${statusText}</span></td>
                    <td data-label="Tác vụ">${actions}</td>
                </tr>
            `);
        });
    } catch(e) { console.error(e); }
}

// --- Navigation & Helpers ---
function showSection(id) {
    document.querySelectorAll('.content-section').forEach(el => el.classList.remove('active'));
    const target = document.getElementById(id);
    if(target) target.classList.add('active');
    
    document.querySelectorAll('.menu-link').forEach(el => el.classList.remove('active'));
    
    // Highlight menu item
    if(event && event.currentTarget && event.currentTarget.classList && event.currentTarget.classList.contains('menu-link')) {
        event.currentTarget.classList.add('active');
    } else {
        // Find link by onclick content
        const links = document.querySelectorAll('.menu-link');
        links.forEach(link => {
            if(link.getAttribute('onclick') && link.getAttribute('onclick').includes(`'${id}'`)) {
                link.classList.add('active');
            }
        });
    }
    
    if(window.innerWidth < 1024) document.querySelector('.sidebar').classList.remove('show');
}

function switchInnerTab(id, btn) {
    document.querySelectorAll('.inner-tab').forEach(el => el.style.display = 'none');
    document.getElementById(id).style.display = 'block';
    btn.parentElement.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}

function toggleSidebar() { document.querySelector('.sidebar').classList.toggle('show'); }

// --- History & Schedule Handlers ---
async function viewPatientHistory(id, name) {
    document.getElementById('searchResult').style.display = 'none';
    const modal = document.getElementById('patientHistoryModal');
    const tbody = document.getElementById('histBody');
    document.getElementById('histPatName').innerText = 'Lịch sử: ' + name;
    modal.style.display = 'block';
    tbody.innerHTML = '<tr><td>Đang tải...</td></tr>';
    try {
        const res = await fetch(`../controllers/admin_actions.php?action=get_patient_history&id=${id}`);
        const data = await res.json();
        tbody.innerHTML = '';
        if(data.length === 0) tbody.innerHTML = '<tr><td colspan="3">Chưa có lịch sử.</td></tr>';
        else data.forEach(r => tbody.insertAdjacentHTML('beforeend', `<tr><td>${new Date(r.ngay_gio_hen).toLocaleString('vi-VN')}</td><td>${r.ten_dich_vu}</td><td>${r.trang_thai}</td></tr>`));
    } catch(e) {}
}

async function handleScheduleRequest(id, action) {
    if(!confirm('Xác nhận thao tác?')) return;
    const fd = new FormData(); fd.append('action', action == 'approve' ? 'approve_schedule_request' : 'reject_schedule_request'); fd.append('request_id', id);
    try {
        const res = await fetch('../controllers/admin_actions.php', { method: 'POST', body: fd });
        const data = await res.json();
        if(data.status=='success') { alert(data.msg || 'Thành công'); location.reload(); } else alert('Lỗi: '+data.msg);
    } catch(e) { alert('Lỗi kết nối'); }
}

// Modal
function openModal(id) { document.getElementById(id).style.display = 'block'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }
window.onclick = function(e) { if(e.target.classList.contains('modal')) e.target.style.display = 'none'; }

// --- Schedule (AJAX) ---
const dayNames = { Mon: 'Thứ 2', Tue: 'Thứ 3', Wed: 'Thứ 4', Thu: 'Thứ 5', Fri: 'Thứ 6', Sat: 'Thứ 7', Sun: 'CN' };

// Week popup helpers
function getMonday(d) {
    const date = new Date(d);
    const day = date.getDay(); // 0=Sun
    const diff = (day === 0 ? -6 : 1) - day;
    date.setDate(date.getDate() + diff);
    return date;
}

function formatISODate(dateObj) {
    const off = dateObj.getTimezoneOffset();
    const local = new Date(dateObj.getTime() - off * 60000);
    return local.toISOString().split('T')[0];
}

function buildWeekPopup() {
    const body = document.getElementById('weekPopupBody');
    if (!body) return;
    body.innerHTML = '';
    const today = new Date();
    const currentMonday = getMonday(today);
    for (let i = -5; i <= 5; i++) {
        const monday = new Date(currentMonday.getTime() + i * 7 * 86400000);
        const sunday = new Date(monday.getTime() + 6 * 86400000);
        const label = `Tuần ${formatISODate(monday)} - ${formatISODate(sunday)}`;
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-outline';
        btn.style.display = 'block';
        btn.style.width = '100%';
        btn.style.textAlign = 'left';
        btn.textContent = label;
        btn.onclick = () => {
            document.getElementById('bulkFrom').value = formatISODate(monday);
            document.getElementById('bulkTo').value = formatISODate(sunday);
            const pop = document.getElementById('weekPopup');
            if (pop) pop.style.display = 'none';
        };
        body.appendChild(btn);
    }
}

function toggleWeekPopup(evt) {
    const pop = document.getElementById('weekPopup');
    if (!pop) return;
    // Anchor popup near button
    const rect = evt.target.getBoundingClientRect();
    pop.style.top = `${rect.bottom + window.scrollY + 6}px`;
    pop.style.left = `${rect.left + window.scrollX}px`;
    pop.style.display = (pop.style.display === 'none' || pop.style.display === '') ? 'block' : 'none';
}

function renderScheduleTable(dates, schedule) {
    const wrap = document.getElementById('scheduleTableWrap');
    if (!wrap) return;

    const headCells = dates.map(d => `<th>${d.display}<br><small>${dayNames[d.dow] || ''}</small></th>`).join('');

    const buildRow = (shift) => {
        return dates.map(d => {
            const items = schedule?.[shift]?.[d.date] || [];
            if (!items.length) return '<td></td>';
            const tags = items.map(it => {
                const style = it.is_off ? 'background:#ffebee; color:#c62828;' : (shift === 'Sang' ? 'background:#e3f2fd; color:#1565c0;' : 'background:#fff3e0; color:#ef6c00;');
                return `<div class="status-badge" style="display:block;margin-bottom:2px; ${style}">${it.name}</div>`;
            }).join('');
            return `<td>${tags}</td>`;
        }).join('');
    };

    wrap.innerHTML = `
        <table class="data-table" style="text-align:center;">
            <thead>
                <tr>
                    <th style="width:100px;">Ca</th>
                    ${headCells}
                </tr>
            </thead>
            <tbody>
                <tr><td><strong>SÁNG</strong></td>${buildRow('Sang')}</tr>
                <tr><td><strong>CHIỀU</strong></td>${buildRow('Chieu')}</tr>
            </tbody>
        </table>`;
}

function validateScheduleRange(from, to) {
    if (!from || !to) return 'Vui lòng chọn đủ ngày bắt đầu và kết thúc';
    const d1 = new Date(from); const d2 = new Date(to);
    if (d1 > d2) return 'Ngày bắt đầu không được lớn hơn ngày kết thúc';
    const diff = (d2 - d1) / (1000*60*60*24);
    if (diff !== 6) return 'Vui lòng chọn khoảng đúng 1 tuần (7 ngày)';
    return '';
}

async function loadScheduleAjax(evt) {
    if (evt) evt.preventDefault();
    const from = document.getElementById('schFrom')?.value;
    const to = document.getElementById('schTo')?.value;
    const err = validateScheduleRange(from, to);
    if (err) { alert(err); return false; }

    const wrap = document.getElementById('scheduleTableWrap');
    if (wrap) wrap.innerHTML = '<div style="padding:12px; text-align:center;">Đang tải lịch...</div>';

    try {
        const url = `../controllers/admin_actions.php?action=get_schedule_admin&from=${from}&to=${to}`;
        const res = await fetch(url);
        const data = await res.json();
        if (data.status !== 'success') throw new Error(data.message || 'Không lấy được dữ liệu');
        renderScheduleTable(data.dates, data.schedule);
    } catch (e) {
        console.error(e);
        if (wrap) wrap.innerHTML = '<div style="padding:12px; text-align:center; color:red;">Lỗi tải lịch!</div>';
        alert('Không tải được lịch làm việc: ' + e.message);
    }
    return false;
}

// --- Bulk schedule creation ---
async function handleBulkSchedule() {
    const doctorId = document.getElementById('bulkDoctor')?.value;
    const from = document.getElementById('bulkFrom')?.value;
    const to = document.getElementById('bulkTo')?.value;
    const days = Array.from(document.querySelectorAll('input[name="bulkDays"]:checked')).map(el => parseInt(el.value));
    const shifts = Array.from(document.querySelectorAll('input[name="bulkShifts"]:checked')).map(el => el.value);

    if (!doctorId || !from || !to) { alert('Vui lòng chọn bác sĩ và khoảng ngày'); return; }
    if (!days.length) { alert('Chọn ít nhất một thứ trong tuần'); return; }
    if (!shifts.length) { alert('Chọn ít nhất một ca'); return; }

    const payload = { id_bacsi: doctorId, fromDate: from, toDate: to, days, shifts };
    try {
        const res = await fetch('../controllers/admin_actions.php?action=add_schedule_bulk', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.status === 'success') {
            alert(data.message || 'Đã thêm lịch làm việc');
            closeModal('bulkScheduleModal');
            loadScheduleAjax(null);
        } else {
            alert(data.message || 'Không thể thêm lịch');
        }
    } catch (e) {
        console.error(e);
        alert('Không thể gửi yêu cầu: ' + e.message);
    }
}