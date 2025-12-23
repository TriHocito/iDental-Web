// assets/js/khachhang.js

// 1. NAVIGATION (Chuyển Tab)
function switchTab(tabId, el) {
    document.querySelectorAll('.content-section').forEach(e => e.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    
    document.querySelectorAll('.menu-link').forEach(e => e.classList.remove('active'));
    if(el) {
        el.classList.add('active');
    }
}

// 2. USER MENU & MODALS
function toggleUserMenu() {
    document.getElementById("userMenuDropdown").classList.toggle("show");
}

function openModal(id) {
    const modal = document.getElementById(id);
    if(modal) modal.style.display = 'block';
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if(modal) modal.style.display = 'none';
}

function openBookingModal() { openModal('bookingModal'); }
function closeBookingModal() { closeModal('bookingModal'); }

// 3. PROFILE UI LOGIC
function enableEditMode() {
    document.querySelectorAll('#profileForm .form-control').forEach(input => {
        if(!input.classList.contains('readonly')) {
            input.disabled = false;
        }
    });
    document.getElementById('editActions').style.display = 'block'; 
    document.getElementById('btnEditProfile').style.display = 'none'; 
}

function cancelEditMode() {
    document.querySelectorAll('#profileForm .form-control').forEach(input => input.disabled = true);
    document.getElementById('editActions').style.display = 'none'; 
    document.getElementById('btnEditProfile').style.display = 'inline-flex'; 
}

// 4. UTILS
function showToast(msg) {
    let x = document.getElementById("toast");
    if(x) {
        x.innerText = msg; 
        x.className = "show";
        setTimeout(() => x.className = x.className.replace("show", ""), 3000);
    }
}

// 5. GLOBAL EVENTS
window.onclick = function(e) {
    if (e.target.classList.contains('modal')) {
        e.target.style.display = 'none';
    }
    if(!e.target.closest('.user-profile')) {
        let dropdowns = document.getElementsByClassName("user-dropdown");
        for (let i = 0; i < dropdowns.length; i++) {
            if (dropdowns[i].classList.contains('show')) {
                dropdowns[i].classList.remove('show');
            }
        }
    }
}

// 6. DOM READY
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
    
    const docSelect = document.getElementById('docSelect');
    if(docSelect) {
        docSelect.addEventListener('change', async function() {
            const docId = this.value;
            const infoDiv = document.getElementById('docScheduleInfo');
            if (!docId) { 
                infoDiv.style.display = 'none'; 
                return; 
            }
            try {
                const response = await fetch(`../controllers/get_doctor_info.php?id=${docId}`);
                const data = await response.json();
                if (data.schedule_text) {
                    infoDiv.style.display = 'block'; 
                    infoDiv.innerText = "Lịch làm việc: " + data.schedule_text;
                    infoDiv.style.color = 'var(--primary)';
                } else { 
                    infoDiv.innerText = "Chưa có lịch cụ thể."; 
                }
            } catch (e) { console.error(e); }
        });
    }
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