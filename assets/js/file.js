// 1. Hàm tải file HTML
async function loadComponent(id, file) {
    try {
        const response = await fetch(file);
        if (response.ok) {
            const text = await response.text();
            document.getElementById(id).innerHTML = text;
        } else {
            console.error(`Lỗi: Không tìm thấy file ${file}`);
        }
    } catch (e) { 
        console.error("Lỗi tải component:", e); 
    }
}

// 2. Logic Highlight Menu
function setActiveMenu() {
    let currentFile = window.location.pathname.split("/").pop().split("?")[0];
    if (currentFile === "" || currentFile === "DOANWEB") currentFile = "index.php";

    const navLinks = document.querySelectorAll('.nav-link');
    
    navLinks.forEach(link => {
        const hrefRaw = link.getAttribute('href');
        if (hrefRaw) {
            const linkFile = hrefRaw.split("/").pop().split("?")[0];
            if (linkFile === currentFile) {
                link.classList.add('active');
            } else {
                link.classList.remove('active');
            }
        }
    });
}

// 3. Logic Mobile Menu
function toggleMobileMenu() {
    const navMenu = document.getElementById('navMenu');
    const headerActions = document.getElementById('headerActions');
    const btn = document.querySelector('.mobile-menu-btn');

    if(navMenu && headerActions) {
        navMenu.classList.toggle('active');
        headerActions.classList.toggle('active');
        
        if (navMenu.classList.contains('active')) {
            document.addEventListener('click', closeMenuOutside);
        } else {
            document.removeEventListener('click', closeMenuOutside);
        }
    }
}

function closeMenuOutside(event) {
    const navMenu = document.getElementById('navMenu');
    const headerActions = document.getElementById('headerActions');
    const btn = document.querySelector('.mobile-menu-btn');

    if (!navMenu.contains(event.target) && 
        !headerActions.contains(event.target) && 
        !btn.contains(event.target)) {
        
        navMenu.classList.remove('active');
        headerActions.classList.remove('active');
        
        document.removeEventListener('click', closeMenuOutside);
    }
}

document.addEventListener("DOMContentLoaded", function() {
    setActiveMenu();
});