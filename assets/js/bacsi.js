// 1. NAVIGATION
function showSection(id, el) {
    document.querySelectorAll('.content-section').forEach(e => e.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    document.querySelectorAll('.menu-link').forEach(e => e.classList.remove('active'));
    el.classList.add('active');
}

// 2. TOGGLE USER MENU (LOGOUT)
function toggleUserMenu() {
    document.getElementById("userMenuDropdown").classList.toggle("show");
}

// 3. OPEN MEDICAL RECORD (XEM BỆNH ÁN)
function viewMedicalRecord(patientName, time) {
    document.getElementById('mrName').innerText = patientName;
    document.getElementById('mrTime').innerText = time;
    document.getElementById('medicalRecordModal').style.display = 'block';
}

// 4. GENERIC MODAL & UTILS
function closeModal(id) { 
    document.getElementById(id).style.display = 'none'; 
}

function showToast(msg) {
    let x = document.getElementById("toast");
    x.innerText = msg; 
    x.className = "show";
    setTimeout(() => x.className = x.className.replace("show", ""), 3000);
}

window.onclick = function(e) {
    if(e.target.classList.contains('modal')) e.target.style.display='none';
    
    if(!e.target.closest('.user-profile')) {
        let dropdowns = document.getElementsByClassName("user-dropdown");
        for (let i = 0; i < dropdowns.length; i++) {
            if (dropdowns[i].classList.contains('show')) {
                dropdowns[i].classList.remove('show');
            }
        }
    }
}

// 5. LOGIC XỬ LÝ LỊCH HẸN (CONFIRM/REJECT/RESCHEDULE)
function confirmRequest(rowId) {
    let row = document.getElementById(rowId);
    row.style.opacity = "0.5";
    setTimeout(() => { 
        row.remove(); 
        showToast("Đã duyệt lịch hẹn!"); 
    }, 400);
}

function rejectRequest(rowId) {
    if(confirm("Từ chối lịch này?")) {
        document.getElementById(rowId).remove();
        showToast("Đã từ chối lịch hẹn.");
    }
}

function openRescheduleModal(id, name) {
    document.getElementById('rescheduleApptId').value = id;
    document.getElementById('reschedulePatientName').innerText = name;
    document.getElementById('rescheduleModal').style.display = 'block';
}

function saveReschedule() {
    let id = document.getElementById('rescheduleApptId').value;
    let date = document.getElementById('newDate').value;
    let time = document.getElementById('newTime').value;
    if(!date) return alert("Chọn ngày!");
    
    let dateStr = `${time} - ${new Date(date).getDate()}/${new Date(date).getMonth()+1}/${new Date(date).getFullYear()}`;
    let cell = document.getElementById(`time-${id}`);
    if(cell) cell.innerHTML = `<strong style="color:var(--warning)">${dateStr}</strong><br><small>(Đã đổi)</small>`;
    
    closeModal('rescheduleModal');
    showToast("Đã đổi lịch & Gửi thông báo!");
}

document.addEventListener("DOMContentLoaded", function() {
    const logoutBtn = document.querySelector('.logout-btn');
    if(logoutBtn) {
        logoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if(confirm('Bạn có chắc chắn muốn đăng xuất?')) {
                window.location.href = '../controllers/logout.php';
            }
        });
    }
    
    const links = document.querySelectorAll('.user-dropdown a');
    links.forEach(link => {
        if(link.innerText.includes('Đăng xuất')) {
            link.href = '../controllers/logout.php';
        }
        if(link.innerText.includes('Cài đặt') || link.innerText.includes('Đổi mật khẩu')) {
             link.href = "#";
             link.onclick = function() { 
                 document.getElementById('changePassModal').style.display = 'block'; 
             }
        }
    });
});

const toggleBtn = document.getElementById('sidebarToggle');
const sidebar = document.querySelector('.sidebar');
const overlay = document.getElementById('sidebarOverlay');

if(toggleBtn) {
    toggleBtn.addEventListener('click', function() {
        sidebar.classList.add('show');
        overlay.style.display = 'block';
    });
}

function closeSidebar() {
    if(sidebar) sidebar.classList.remove('show');
    if(overlay) overlay.style.display = 'none';
}

document.querySelectorAll('.menu-link').forEach(link => {
    link.addEventListener('click', () => {
        if (window.innerWidth < 992) closeSidebar();
    });
});