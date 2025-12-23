// assets/js/admin.js

// ============================================================
// 1. NAVIGATION & MODAL SYSTEM
// ============================================================

function showSection(id) {
    document.querySelectorAll('.content-section').forEach(el => el.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    
    document.querySelectorAll('.menu-link').forEach(el => el.classList.remove('active'));
    event.currentTarget.classList.add('active');
    
    if(id === 'schedule') renderWeek();
}

function openModal(id) { document.getElementById(id).style.display = 'block'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }

window.onclick = function(e) { 
    if (e.target.classList.contains('modal')) e.target.style.display = 'none';
    if(!e.target.closest('.user-profile')) {
        let dropdowns = document.getElementsByClassName("user-dropdown");
        for (let i = 0; i < dropdowns.length; i++) {
            if (dropdowns[i].classList.contains('show')) dropdowns[i].classList.remove('show');
        }
    }
}

function toggleUserMenu() {
    document.getElementById("userMenuDropdown").classList.toggle("show");
}

// ============================================================
// 2. DATA HANDLERS & AJAX
// ============================================================

function openEditDoctorModal(data) {
    document.getElementById('editDocId').value = data.id_bacsi;
    document.getElementById('editDocName').value = data.ten_day_du;
    document.getElementById('editDocPhone').value = data.sdt;
    document.getElementById('editDocSpec').value = data.chuyen_khoa;
    openModal('editDoctorModal');
}

function openResetPassDocModal(id, name) {
    document.getElementById('resetDocId').value = id;
    document.getElementById('resetDocName').innerText = name;
    openModal('resetPassDoctorModal');
}

async function viewPatientHistory(id, name) {
    document.getElementById('histPatName').innerText = name;
    const tbody = document.getElementById('histBody');
    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center">Đang tải...</td></tr>';
    openModal('patientHistoryModal');

    try {
        const res = await fetch(`../controllers/admin_actions.php?action=get_patient_history&id=${id}`);
        const data = await res.json();
        tbody.innerHTML = '';
        
        if(data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center">Chưa có lịch sử khám.</td></tr>';
        } else {
            data.forEach(row => {
                let statusColor = row.trang_thai === 'hoan_thanh' ? 'green' : (row.trang_thai === 'huy' ? 'red' : 'orange');
                tbody.insertAdjacentHTML('beforeend', `
                    <tr>
                        <td>${new Date(row.ngay_gio_hen).toLocaleString('vi-VN')}</td>
                        <td>${row.ten_dich_vu}</td>
                        <td>${row.ten_bs || '-'}</td>
                        <td style="color:${statusColor}; font-weight:bold">${row.trang_thai}</td>
                    </tr>
                `);
            });
        }
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:red">Lỗi tải dữ liệu.</td></tr>';
    }
}

async function handleBulkSchedule() {
    const docId = document.getElementById('bulkDoc').value;
    const bedId = document.getElementById('bulkBed').value;
    const fromDate = document.getElementById('bulkFromDate').value;
    const toDate = document.getElementById('bulkToDate').value;
    
    if(!docId) return alert("Vui lòng chọn bác sĩ!");
    if(!bedId) return alert("Vui lòng chọn ghế/giường!");
    
    let days = [];
    document.querySelectorAll('input[name="bulkDay"]:checked').forEach(cb => days.push(cb.value));
    
    let shifts = [];
    if(document.getElementById('bulkMorning').checked) shifts.push('Sang');
    if(document.getElementById('bulkAfternoon').checked) shifts.push('Chieu');
    
    if(days.length === 0 || shifts.length === 0) return alert("Vui lòng chọn ít nhất 1 ngày và 1 ca làm!");

    const btn = document.querySelector('#shiftModal .btn-primary');
    const oldText = btn.innerText;
    btn.innerText = "Đang xử lý..."; btn.disabled = true;

    try {
        const payload = {
            id_bacsi: docId,
            id_giuongbenh: bedId,
            fromDate: fromDate,
            toDate: toDate,
            days: days,
            shifts: shifts
        };

        const response = await fetch('../controllers/admin_actions.php?action=add_schedule_bulk', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        
        const result = await response.json();
        
        if(result.status === 'success') {
            alert(result.message);
            location.reload(); 
        } else {
            alert("Có lỗi xảy ra: " + (result.message || JSON.stringify(result)));
        }
    } catch (e) {
        console.error(e);
        alert("Lỗi kết nối server!");
    } finally {
        btn.innerText = oldText; btn.disabled = false;
    }
}

// ============================================================

// ============================================================
let weekOffset = 0;
function getMonday(d) { d = new Date(d); var day = d.getDay(), diff = d.getDate() - day + (day == 0 ? -6 : 1); return new Date(d.setDate(diff)); }
function renderWeek() { }

document.addEventListener("DOMContentLoaded", function() {
    renderWeek();
});

const toggleBtn = document.getElementById('sidebarToggle');
const sidebar = document.querySelector('.sidebar');
const overlay = document.getElementById('sidebarOverlay');

if(toggleBtn) {
    toggleBtn.addEventListener('click', function() {
        sidebar.classList.add('show');
        if(overlay) overlay.style.display = 'block';
    });
}

function closeSidebar() {
    if(sidebar) sidebar.classList.remove('show');
    if(overlay) overlay.style.display = 'none';
}

document.querySelectorAll('.menu-link').forEach(link => {
    link.addEventListener('click', () => {
        if (window.innerWidth < 992) {
            closeSidebar();
        }
    });
});