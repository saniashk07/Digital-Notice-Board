// ========================================
// DIGITAL NOTICE BOARD - MASTER JAVASCRIPT
// ========================================

// ========== SCROLL TO SECTION FUNCTION ==========
function scrollToSection(sectionId) {
    // Update active tab
    document.querySelectorAll('.tab').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Find and activate the clicked tab
    if (event && event.target) {
        event.target.classList.add('active');
    }
    
    // Hide all panels
    document.querySelectorAll('.content-panel').forEach(panel => {
        panel.classList.remove('active');
    });
    
    // Show selected panel
    const panel = document.getElementById(sectionId + '-section');
    if (panel) {
        panel.classList.add('active');
        
        // Smooth scroll to the section
        setTimeout(() => {
            panel.scrollIntoView({ 
                behavior: 'smooth', 
                block: 'start',
                inline: 'nearest'
            });
        }, 100);
    }
}

// ========== TAB SWITCHING FUNCTION (Legacy) ==========
function showTab(tabName) {
    // Hide all panels
    document.querySelectorAll('.content-panel').forEach(panel => {
        panel.classList.remove('active');
    });
    
    // Show selected panel
    const panel = document.getElementById(tabName + '-panel');
    if (panel) {
        panel.classList.add('active');
    }
    
    // Update tab buttons
    document.querySelectorAll('.tab').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Find and activate the clicked tab
    if (event && event.target) {
        event.target.classList.add('active');
    }
}

// ========== ADMIN/FACULTY FIELD TOGGLE ==========
function toggleAdminField() {
    const roleSelect = document.getElementById('role');
    const adminField = document.getElementById('admin-field');
    
    if (roleSelect && adminField) {
        if (roleSelect.value === 'admin') {
            adminField.style.display = 'block';
        } else {
            adminField.style.display = 'none';
        }
    }
}

// ========== FORM VALIDATION ==========
function validateForm() {
    const password = document.getElementById('password');
    const confirm = document.getElementById('confirm');
    
    if (password && confirm) {
        if (password.value !== confirm.value) {
            alert('Passwords do not match!');
            return false;
        }
    }
    return true;
}

// ========== NOTICE MODAL ==========
function showNotice(notice) {
    const modal = document.getElementById('noticeModal');
    if (!modal) return;
    
    // Set modal title
    const titleEl = document.getElementById('modalTitle');
    if (titleEl) titleEl.innerHTML = notice.title;
    
    // Set modal meta info
    const metaEl = document.getElementById('modalMeta');
    if (metaEl) {
        let meta = `
            <span><i class="fas fa-tag"></i> ${notice.cat_name || 'Uncategorized'}</span>
            <span><i class="fas fa-user-tie"></i> ${notice.posted_by_name || 'Unknown'}</span>
            <span><i class="fas fa-building"></i> ${notice.faculty_dept || ''}</span>
            <span><i class="far fa-calendar-alt"></i> ${new Date(notice.created_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })}</span>
            ${notice.priority == 'urgent' ? '<span style="color: #e74c3c;"><i class="fas fa-exclamation-circle"></i> URGENT</span>' : ''}
        `;
        metaEl.innerHTML = meta;
    }
    
    // Set modal description
    const descEl = document.getElementById('modalDescription');
    if (descEl) {
        descEl.innerHTML = notice.description.replace(/\n/g, '<br>');
    }
    
    // Set attachment if exists
    const attachEl = document.getElementById('modalAttachment');
    if (attachEl) {
        if (notice.attachment) {
            attachEl.innerHTML = `<a href="uploads/${notice.attachment}" target="_blank" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: #2a5298; color: white; text-decoration: none; border-radius: 30px;"><i class="fas fa-paperclip"></i> View Attachment</a>`;
        } else {
            attachEl.innerHTML = '';
        }
    }
    
    // Show modal
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
}

// ========== CLOSE MODAL ==========
function closeModal() {
    const modal = document.getElementById('noticeModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

// ========== FILTER HISTORY BY YEAR ==========
function filterHistory(year) {
    // Update year buttons
    document.querySelectorAll('.year-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    if (event && event.target) {
        event.target.classList.add('active');
    }
    
    // Filter table rows
    const rows = document.querySelectorAll('#history-table tbody tr');
    rows.forEach(row => {
        if (year === 'all' || row.getAttribute('data-year') === year) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// ========== CONFIRM DELETE ==========
function confirmDelete(message) {
    return confirm(message || 'Are you sure you want to delete this?');
}

// ========== AUTO-HIDE ALERTS ==========
document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.animation = 'slideDown 0.5s reverse';
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.style.display = 'none';
                }
            }, 500);
        }, 5000);
    });
    
    // Close modal when clicking outside
    const modal = document.getElementById('noticeModal');
    if (modal) {
        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
        };
    }
    
    // Close modal with Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeModal();
        }
    });
    
    // Check URL hash for direct section access
    if (window.location.hash) {
        const sectionId = window.location.hash.substring(1);
        const section = document.getElementById(sectionId + '-section');
        if (section) {
            setTimeout(() => {
                section.scrollIntoView({ behavior: 'smooth' });
                section.classList.add('active');
                
                // Update active tab
                document.querySelectorAll('.tab').forEach(btn => {
                    btn.classList.remove('active');
                    if (btn.textContent.toLowerCase().includes(sectionId.replace('-', ' '))) {
                        btn.classList.add('active');
                    }
                });
            }, 200);
        }
    }
    
    // Add body class for background images
    const path = window.location.pathname;
    const filename = path.split('/').pop();
    
    if (filename === 'index.php' || filename === '') {
        document.body.classList.add('home-page');
    } else if (filename === 'login.php') {
        document.body.classList.add('login-page');
    } else if (filename === 'register.php') {
        document.body.classList.add('register-page');
    } else if (filename === 'admin.php') {
        document.body.classList.add('admin-page');
    } else if (filename === 'faculty.php') {
        document.body.classList.add('faculty-page');
    } else if (filename === 'student.php') {
        document.body.classList.add('student-page');
    }
});