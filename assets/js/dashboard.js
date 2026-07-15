/**
 * QR Attendance Dashboard — Application Logic
 * Handles routing, AJAX CRUD, sidebar, toasts, and page initialization.
 * Auth rules come from window.APP_AUTH (injected from conn/config.php).
 */

const AppAuth = window.APP_AUTH || {};

const FormValidation = {
    personName(value, label, required = true) {
        const v = (value || '').trim();
        if (!v) return required ? `${label} is required.` : null;
        if (v.length < 2) return `${label} must be at least 2 characters.`;
        if (v.length > 100) return `${label} must not exceed 100 characters.`;
        if (!/^[\p{L}\s.'\-]+$/u.test(v)) {
            return `${label} may only contain letters, spaces, periods, hyphens, and apostrophes.`;
        }
        return null;
    },

    courseSection(value) {
        const v = (value || '').trim();
        if (!v) return 'Course & section is required.';
        if (v.length < 2) return 'Course & section must be at least 2 characters.';
        if (v.length > 100) return 'Course & section must not exceed 100 characters.';
        if (!/^[A-Za-z0-9\s.\-\/]+$/.test(v)) {
            return 'Course & section may only contain letters, numbers, spaces, periods, hyphens, and slashes.';
        }
        return null;
    },

    phone(value, label = 'Parent phone', required = false) {
        const v = (value || '').trim();
        if (!v) return required ? `${label} is required.` : null;
        if (v.length < 7 || v.length > 20) return `${label} must be between 7 and 20 characters.`;
        if (!/^[0-9+\-\s().]+$/.test(v)) {
            return `${label} may only contain digits, spaces, and + - ( ).`;
        }
        const digits = v.replace(/\D+/g, '');
        if (digits.length < 7 || digits.length > 15) {
            return `${label} must contain between 7 and 15 digits.`;
        }
        return null;
    },

    username(value) {
        const rules = AppAuth.username || {};
        const min = Number(rules.min_length) || 3;
        const max = Number(rules.max_length) || 50;
        const patternSource = (rules.pattern || '/^[A-Za-z0-9._-]+$/').replace(/^\/|\/$/g, '');
        const pattern = new RegExp(patternSource);
        const patternMessage = rules.pattern_message
            || 'Username may only contain letters, numbers, periods, underscores, and hyphens.';

        const v = (value || '').trim();
        if (!v) return 'Username is required.';
        if (v.length < min) return `Username must be at least ${min} characters.`;
        if (v.length > max) return `Username must not exceed ${max} characters.`;
        if (!pattern.test(v)) return patternMessage;
        return null;
    },

    password(value, required = true) {
        const rules = AppAuth.password || {};
        const min = Number(rules.min_length) || 8;
        const max = Number(rules.max_length) || 72;
        const requireLetter = rules.require_letter !== false;
        const requireNumber = rules.require_number !== false;
        const requireSpecial = !!rules.require_special;

        const v = value || '';
        if (!v) return required ? 'Password is required.' : null;
        if (v.length < min) return `Password must be at least ${min} characters.`;
        if (v.length > max) return `Password must not exceed ${max} characters.`;

        const hasLetter = /[A-Za-z]/.test(v);
        const hasNumber = /[0-9]/.test(v);
        const hasSpecial = /[^A-Za-z0-9]/.test(v);

        if (requireLetter && requireNumber && (!hasLetter || !hasNumber)) {
            return 'Password must include at least one letter and one number.';
        }
        if (requireLetter && !hasLetter) return 'Password must include at least one letter.';
        if (requireNumber && !hasNumber) return 'Password must include at least one number.';
        if (requireSpecial && !hasSpecial) return 'Password must include at least one special character.';
        return null;
    },

    role(value) {
        const roles = AppAuth.roles || ['admin', 'staff'];
        const v = (value || '').trim();
        if (!v) return 'Role is required.';
        if (!roles.includes(v)) {
            const label = roles.map((r) => r.charAt(0).toUpperCase() + r.slice(1)).join(' or ');
            return `Role must be either ${label}.`;
        }
        return null;
    },

    qrCode(value) {
        const v = (value || '').trim();
        if (!v) return 'QR code is required. Please generate or scan a QR code.';
        if (v.length < 8 || v.length > 64) return 'QR code must be between 8 and 64 characters.';
        if (!/^[A-Za-z0-9]+$/.test(v)) return 'QR code is invalid. It must be alphanumeric.';
        return null;
    },

    loginUsername(value) {
        const rules = AppAuth.username || {};
        const min = Number(rules.min_length) || 3;
        const max = Number(rules.max_length) || 50;
        const v = (value || '').trim();
        if (!v) return 'Username is required.';
        if (v.length < min) return `Username must be at least ${min} characters.`;
        if (v.length > max) return `Username must not exceed ${max} characters.`;
        return null;
    },

    loginPassword(value) {
        const rules = AppAuth.password || {};
        const max = Number(rules.max_length) || 72;
        const v = value || '';
        if (!v) return 'Password is required.';
        if (v.length > max) return 'Password is too long.';
        return null;
    },

    clear(fieldIds) {
        fieldIds.forEach((id) => {
            const input = document.getElementById(id);
            if (input) input.classList.remove('is-invalid');

            const byData = document.querySelector(`[data-error-for="${id}"]`);
            if (byData) {
                byData.textContent = '';
                byData.classList.remove('show');
            }

            const byId = document.getElementById(`${id}-error`);
            if (byId) {
                byId.textContent = '';
                byId.classList.remove('show');
            }
        });
    },

    show(errors, fieldMap = {}) {
        const messages = [];
        Object.keys(errors).forEach((key) => {
            const message = errors[key];
            if (!message) return;
            messages.push(message);

            const fieldId = fieldMap[key] || key;
            const input = document.getElementById(fieldId);
            if (input) input.classList.add('is-invalid');

            const byData = document.querySelector(`[data-error-for="${fieldId}"]`);
            if (byData) {
                byData.textContent = message;
                byData.classList.add('show');
            }

            const byId = document.getElementById(`${fieldId}-error`);
            if (byId) {
                byId.textContent = message;
                byId.classList.add('show');
            }
        });
        return messages;
    },

    firstMessage(errors) {
        const keys = Object.keys(errors);
        return keys.length ? errors[keys[0]] : 'Please fix the highlighted fields.';
    }
};

const DashboardApp = {
    currentPage: null,
    scanner: null,

    // ─── Initialize ────────────────────────────────────────
    init() {
        // Setup global AJAX check for authentication
        $(document).ajaxSuccess((event, xhr, settings, data) => {
            if (data && data.success === false && data.error === 'unauthenticated') {
                window.location.reload();
            }
        });

        if (document.getElementById('loginForm')) {
            this.bindLogin();
            return;
        }
        this.bindSidebar();
        this.bindRouting();
        this.navigateToHash();
        window.addEventListener('hashchange', () => this.navigateToHash());
    },

    // ─── Hash Router ───────────────────────────────────────
    navigateToHash() {
        const raw = window.location.hash.replace('#', '') || 'dashboard';
        const [page, query = ''] = raw.split('?');
        this.loadPage(page, query);
    },

    loadPage(page, queryString = '') {
        const pageKey = queryString ? `${page}?${queryString}` : page;
        if (this.currentPage === pageKey) return;
        this.currentPage = pageKey;

        // Stop any active QR scanner
        if (this.scanner) {
            this.scanner.stop();
            this.scanner = null;
        }

        // Update sidebar active state
        document.querySelectorAll('.sidebar-link').forEach(link => {
            link.classList.toggle('active', link.dataset.page === page);
        });

        // Update page title
        const titles = {
            dashboard: 'Dashboard',
            attendance: 'Attendance',
            students: 'Students',
            users: 'Users',
            profile: 'My Profile'
        };
        const titleEl = document.getElementById('page-title');
        if (titleEl) titleEl.textContent = titles[page] || 'Dashboard';

        // Load page content via AJAX
        const contentArea = document.getElementById('main-content');
        contentArea.classList.add('content-loading');
        contentArea.classList.remove('content-loaded');

        const url = queryString
            ? `./pages/page-${page}.php?${queryString}`
            : `./pages/page-${page}.php`;

        $.ajax({
            url,
            method: 'GET',
            success: (html) => {
                // Clean up any previously moved modals and backdrops
                document.querySelectorAll('.ajax-modal').forEach(m => m.remove());
                document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
                document.body.classList.remove('modal-open');
                document.body.style.removeProperty('overflow');
                document.body.style.removeProperty('padding-right');

                contentArea.innerHTML = html;
                contentArea.classList.remove('content-loading');
                contentArea.classList.add('content-loaded');

                // Move modals to body so they escape stacking contexts
                contentArea.querySelectorAll('.modal').forEach(modal => {
                    modal.classList.add('ajax-modal');
                    document.body.appendChild(modal);
                });

                this.initPageScripts(page);
            },
            error: () => {
                contentArea.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon"><i class="bi bi-exclamation-triangle"></i></div>
                        <h5>Page Not Found</h5>
                        <p>The requested page could not be loaded.</p>
                    </div>`;
                contentArea.classList.remove('content-loading');
                contentArea.classList.add('content-loaded');
            }
        });

        // Close mobile sidebar
        if (window.innerWidth < 992) {
            document.getElementById('sidebar').classList.remove('show');
            document.getElementById('sidebar-backdrop').classList.remove('show');
        }
    },

    reloadCurrentPage() {
        const raw = window.location.hash.replace('#', '') || 'dashboard';
        const [page, query = ''] = raw.split('?');
        this.currentPage = null;
        this.loadPage(page, query);
    },

    // ─── Page-Specific Initialization ──────────────────────
    initPageScripts(page) {
        switch (page) {
            case 'dashboard':
                this.initDashboard();
                break;
            case 'attendance':
                this.initAttendance();
                break;
            case 'students':
                this.initStudents();
                break;
            case 'users':
                this.initUsers();
                break;
            case 'profile':
                // Profile forms are self-contained
                break;
        }
    },

    // ─── Dashboard Page ────────────────────────────────────
    initDashboard() {
        // Animated counters
        document.querySelectorAll('[data-counter]').forEach(el => {
            const target = parseInt(el.dataset.counter);
            this.animateCounter(el, 0, target, 1200);
        });

        // Init recent table
        if ($.fn.DataTable && document.getElementById('recentAttendanceTable')) {
            $('#recentAttendanceTable').DataTable({
                paging: false,
                searching: false,
                info: false,
                order: [[0, 'desc']],
                language: { emptyTable: 'No recent attendance records' }
            });
        }
    },

    animateCounter(el, start, end, duration) {
        if (start === end) { el.textContent = end; return; }
        const range = end - start;
        const increment = end > start ? 1 : -1;
        const stepTime = Math.abs(Math.floor(duration / range));
        let current = start;
        const timer = setInterval(() => {
            current += increment;
            el.textContent = current;
            if (current === end) clearInterval(timer);
        }, stepTime);
    },

    // ─── Attendance Page ───────────────────────────────────
    initAttendance() {
        if ($.fn.DataTable && document.getElementById('attendanceTable')) {
            if ($.fn.DataTable.isDataTable('#attendanceTable')) {
                $('#attendanceTable').DataTable().destroy();
            }
            $('#attendanceTable').DataTable({
                order: [[3, 'desc']],
                pageLength: 10,
                language: { emptyTable: 'No attendance records found for the selected filters.' }
            });
        }
        this.startQRScanner();
    },

    getAttendanceFilterQuery() {
        const dateFrom = (document.getElementById('filterDateFrom')?.value || '').trim();
        const dateTo = (document.getElementById('filterDateTo')?.value || '').trim();
        const course = (document.getElementById('filterCourse')?.value || '').trim();
        const search = (document.getElementById('filterSearch')?.value || '').trim();

        const params = new URLSearchParams();
        if (dateFrom) params.set('date_from', dateFrom);
        if (dateTo) params.set('date_to', dateTo);
        if (course) params.set('course', course);
        if (search) params.set('q', search);
        return params.toString();
    },

    applyAttendanceFilters(event) {
        if (event) event.preventDefault();

        const dateFrom = (document.getElementById('filterDateFrom')?.value || '').trim();
        const dateTo = (document.getElementById('filterDateTo')?.value || '').trim();

        if (dateFrom && dateTo && dateFrom > dateTo) {
            this.showToast('error', 'Validation', 'From date cannot be later than To date.');
            return false;
        }

        const query = this.getAttendanceFilterQuery();
        const nextHash = query ? `attendance?${query}` : 'attendance';
        const currentHash = window.location.hash.replace(/^#/, '');

        this.currentPage = null;
        if (currentHash === nextHash) {
            this.loadPage('attendance', query);
        } else {
            window.location.hash = nextHash;
        }
        return false;
    },

    clearAttendanceFilters() {
        const dateFrom = document.getElementById('filterDateFrom');
        const dateTo = document.getElementById('filterDateTo');
        const course = document.getElementById('filterCourse');
        const search = document.getElementById('filterSearch');

        if (dateFrom) dateFrom.value = '';
        if (dateTo) dateTo.value = '';
        if (course) course.value = '';
        if (search) search.value = '';

        this.currentPage = null;
        if (window.location.hash.replace(/^#/, '') === 'attendance') {
            this.loadPage('attendance', '');
        } else {
            window.location.hash = 'attendance';
        }
    },

    filterAttendanceToday() {
        const today = new Date();
        const iso = today.toISOString().slice(0, 10);
        const dateFrom = document.getElementById('filterDateFrom');
        const dateTo = document.getElementById('filterDateTo');
        if (dateFrom) dateFrom.value = iso;
        if (dateTo) dateTo.value = iso;
        this.applyAttendanceFilters();
    },

    startQRScanner() {
        const videoEl = document.getElementById('interactive');
        if (!videoEl) return;

        try {
            this.scanner = new Instascan.Scanner({ video: videoEl });

            this.scanner.addListener('scan', (content) => {
                document.getElementById('detected-qr-code').value = content;
                this.scanner.stop();
                document.querySelector('.scanner-con').style.display = 'none';
                document.querySelector('.qr-detected-container').style.display = '';
            });

            Instascan.Camera.getCameras()
                .then((cameras) => {
                    if (cameras.length > 0) {
                        this.scanner.start(cameras[0]);
                    } else {
                        this.showToast('error', 'Camera Error', 'No cameras found on this device.');
                    }
                })
                .catch((err) => {
                    console.error('Camera access error:', err);
                    this.showToast('error', 'Camera Error', 'Unable to access camera: ' + err);
                });
        } catch (e) {
            console.warn('Instascan not loaded:', e);
        }
    },

    submitAttendance() {
        const qrCode = document.getElementById('detected-qr-code').value;
        const errors = {};
        const qrErr = FormValidation.qrCode(qrCode);
        if (qrErr) errors.qr_code = qrErr;

        if (Object.keys(errors).length) {
            this.showToast('error', 'Validation', FormValidation.firstMessage(errors));
            return;
        }

        $.ajax({
            url: './endpoint/add-attendance.php',
            method: 'POST',
            data: { qr_code: qrCode },
            dataType: 'json',
            success: (res) => {
                if (res.success) {
                    this.showToast('success', 'Success', res.message);
                    this.reloadCurrentPage();
                } else {
                    this.showToast('error', 'Validation', res.message || 'Unable to submit attendance.');
                }
            },
            error: () => {
                this.showToast('error', 'Error', 'Failed to submit attendance.');
            }
        });
    },

    deleteAttendance(id) {
        if (!confirm('Are you sure you want to remove this attendance record?')) return;

        $.ajax({
            url: './endpoint/delete-attendance.php',
            method: 'POST',
            data: { attendance_id: id },
            dataType: 'json',
            success: (res) => {
                if (res.success) {
                    this.showToast('success', 'Deleted', res.message);
                    this.reloadCurrentPage();
                } else {
                    this.showToast('error', 'Error', res.message);
                }
            },
            error: () => {
                this.showToast('error', 'Error', 'Failed to delete record.');
            }
        });
    },

    // ─── Students Page ─────────────────────────────────────
    initStudents() {
        if ($.fn.DataTable && document.getElementById('studentTable')) {
            $('#studentTable').DataTable({
                order: [[0, 'desc']],
                language: { emptyTable: 'No students registered yet' }
            });
        }
    },

    addStudent() {
        const fields = ['studentName', 'studentCourse', 'parentName', 'parentPhone', 'generatedCode'];
        FormValidation.clear(fields);

        const name = document.getElementById('studentName').value.trim();
        const course = document.getElementById('studentCourse').value.trim();
        const parentName = document.getElementById('parentName').value.trim();
        const parentPhone = document.getElementById('parentPhone').value.trim();
        const code = document.getElementById('generatedCode').value.trim();

        const errors = {};
        const nameErr = FormValidation.personName(name, 'Student name');
        const courseErr = FormValidation.courseSection(course);
        const parentNameErr = FormValidation.personName(parentName, 'Parent name', false);
        const parentPhoneErr = FormValidation.phone(parentPhone, 'Parent phone', false);
        const codeErr = FormValidation.qrCode(code);

        if (nameErr) errors.studentName = nameErr;
        if (courseErr) errors.studentCourse = courseErr;
        if (parentNameErr) errors.parentName = parentNameErr;
        if (parentPhoneErr) errors.parentPhone = parentPhoneErr;
        if (codeErr) errors.generatedCode = codeErr;

        if (Object.keys(errors).length) {
            FormValidation.show(errors);
            this.showToast('error', 'Validation', FormValidation.firstMessage(errors));
            return;
        }

        $.ajax({
            url: './endpoint/add-student.php',
            method: 'POST',
            data: {
                student_name: name,
                course_section: course,
                parent_name: parentName,
                parent_phone: parentPhone,
                generated_code: code
            },
            dataType: 'json',
            success: (res) => {
                if (res.success) {
                    this.showToast('success', 'Success', res.message);
                    $('#addStudentModal').modal('hide');
                    this.currentPage = null;
                    this.loadPage('students');
                } else {
                    const map = {
                        student_name: 'studentName',
                        course_section: 'studentCourse',
                        parent_name: 'parentName',
                        parent_phone: 'parentPhone',
                        generated_code: 'generatedCode'
                    };
                    if (res.errors) FormValidation.show(res.errors, map);
                    this.showToast('error', 'Validation', res.message || 'Unable to add student.');
                }
            },
            error: () => {
                this.showToast('error', 'Error', 'Failed to add student.');
            }
        });
    },

    openUpdateModal(id) {
        FormValidation.clear(['updateStudentName', 'updateStudentCourse', 'updateParentName', 'updateParentPhone']);

        const name = document.getElementById('studentName-' + id).textContent;
        const course = document.getElementById('studentCourse-' + id).textContent;
        const parentName = document.getElementById('studentParentName-' + id).textContent;
        const parentPhone = document.getElementById('studentParentPhone-' + id).textContent;

        document.getElementById('updateStudentId').value = id;
        document.getElementById('updateStudentName').value = name;
        document.getElementById('updateStudentCourse').value = course;
        document.getElementById('updateParentName').value = parentName;
        document.getElementById('updateParentPhone').value = parentPhone;

        $('#updateStudentModal').modal('show');
    },

    updateStudent() {
        const fields = ['updateStudentName', 'updateStudentCourse', 'updateParentName', 'updateParentPhone'];
        FormValidation.clear(fields);

        const id = document.getElementById('updateStudentId').value;
        const name = document.getElementById('updateStudentName').value.trim();
        const course = document.getElementById('updateStudentCourse').value.trim();
        const parentName = document.getElementById('updateParentName').value.trim();
        const parentPhone = document.getElementById('updateParentPhone').value.trim();

        const errors = {};
        const nameErr = FormValidation.personName(name, 'Student name');
        const courseErr = FormValidation.courseSection(course);
        const parentNameErr = FormValidation.personName(parentName, 'Parent name', false);
        const parentPhoneErr = FormValidation.phone(parentPhone, 'Parent phone', false);

        if (nameErr) errors.updateStudentName = nameErr;
        if (courseErr) errors.updateStudentCourse = courseErr;
        if (parentNameErr) errors.updateParentName = parentNameErr;
        if (parentPhoneErr) errors.updateParentPhone = parentPhoneErr;

        if (Object.keys(errors).length) {
            FormValidation.show(errors);
            this.showToast('error', 'Validation', FormValidation.firstMessage(errors));
            return;
        }

        $.ajax({
            url: './endpoint/update-student.php',
            method: 'POST',
            data: {
                tbl_student_id: id,
                student_name: name,
                course_section: course,
                parent_name: parentName,
                parent_phone: parentPhone
            },
            dataType: 'json',
            success: (res) => {
                if (res.success) {
                    this.showToast('success', 'Updated', res.message);
                    $('#updateStudentModal').modal('hide');
                    this.currentPage = null;
                    this.loadPage('students');
                } else {
                    const map = {
                        student_name: 'updateStudentName',
                        course_section: 'updateStudentCourse',
                        parent_name: 'updateParentName',
                        parent_phone: 'updateParentPhone'
                    };
                    if (res.errors) FormValidation.show(res.errors, map);
                    this.showToast('error', 'Validation', res.message || 'Unable to update student.');
                }
            },
            error: () => {
                this.showToast('error', 'Error', 'Failed to update student.');
            }
        });
    },

    deleteStudent(id) {
        if (!confirm('Are you sure you want to delete this student?')) return;

        $.ajax({
            url: './endpoint/delete-student.php',
            method: 'POST',
            data: { student_id: id },
            dataType: 'json',
            success: (res) => {
                if (res.success) {
                    this.showToast('success', 'Deleted', res.message);
                    this.currentPage = null;
                    this.loadPage('students');
                } else {
                    this.showToast('error', 'Error', res.message);
                }
            },
            error: () => {
                this.showToast('error', 'Error', 'Failed to delete student.');
            }
        });
    },

    printStudentCard(id) {
        const card = document.getElementById('student-id-card-' + id);
        if (!card) {
            this.showToast('error', 'Print Error', 'Student ID card not found.');
            return;
        }

        const printWindow = window.open('', '_blank', 'width=920,height=700');
        if (!printWindow) {
            this.showToast('error', 'Print Error', 'Please allow pop-ups to print the student card.');
            return;
        }

        const styles = `
            @page { size: auto; margin: 0; }
            * { box-sizing: border-box; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            html, body {
                margin: 0;
                padding: 0;
                width: 100%;
                height: 100%;
                background: #fff;
                font-family: Inter, Arial, Helvetica, sans-serif;
            }
            body {
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
            }
            .student-id-card {
                width: 92mm;
                height: 58mm;
                display: grid;
                grid-template-columns: 14mm 1fr;
                background: #fff;
                border-radius: 2.8mm;
                overflow: hidden;
                border: 0.25mm solid #94a3b8;
                box-shadow: none;
                color: #0f172a;
                margin: 0 auto;
                flex-shrink: 0;
            }
            .id-card-rail {
                background: linear-gradient(165deg, #1e3a8a 0%, #0f172a 100%);
                color: #fff;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                align-items: center;
                padding: 3mm 1.4mm;
                text-align: center;
                position: relative;
            }
            .id-card-rail::after {
                content: '';
                position: absolute; inset: 0;
                background: repeating-linear-gradient(-35deg, rgba(255,255,255,0.05) 0, rgba(255,255,255,0.05) 0.3mm, transparent 0.3mm, transparent 1.8mm);
            }
            .id-card-rail-top, .id-card-rail-mid, .id-card-rail-bottom { position: relative; z-index: 1; }
            .id-card-rail-mark {
                width: 7mm; height: 7mm; border-radius: 1.6mm;
                background: rgba(255,255,255,0.12); border: 0.2mm solid rgba(255,255,255,0.2);
                display: flex; align-items: center; justify-content: center; font-size: 3.4mm; margin: 0 auto 1.4mm;
            }
            .id-card-rail-title { font-size: 2.1mm; font-weight: 800; letter-spacing: 0.04em; }
            .id-card-rail-mid {
                display: flex; flex-direction: column; gap: 1.2mm;
                font-size: 1.8mm; font-weight: 800; letter-spacing: 0.18em;
                writing-mode: vertical-rl; transform: rotate(180deg); color: rgba(255,255,255,0.85);
            }
            .id-card-rail-bottom {
                font-size: 1.5mm; font-weight: 700; letter-spacing: 0.12em; color: rgba(255,255,255,0.55);
                writing-mode: vertical-rl; transform: rotate(180deg);
            }
            .id-card-main { display: flex; flex-direction: column; min-width: 0; background: linear-gradient(180deg, #fff, #f8fafc); }
            .id-card-topbar {
                display: flex; align-items: center; justify-content: space-between; gap: 2mm;
                padding: 2.4mm 3mm 1.8mm; border-bottom: 0.2mm solid #e2e8f0;
            }
            .id-card-org { font-size: 1.9mm; font-weight: 800; letter-spacing: 0.1em; text-transform: uppercase; color: #1e3a8a; }
            .id-card-doc { margin-top: 0.4mm; font-size: 2.1mm; font-weight: 600; color: #64748b; }
            .id-card-chip {
                width: 7.5mm; height: 5.6mm; border-radius: 0.8mm;
                background: linear-gradient(135deg, #d4af37 0%, #f5e6a3 45%, #b8860b 100%);
                border: 0.15mm solid rgba(146,112,18,0.35);
                display: grid; grid-template-columns: 1fr 1fr; gap: 0.4mm; padding: 0.7mm;
            }
            .id-card-chip span { background: rgba(120,84,10,0.18); border-radius: 0.2mm; }
            .id-card-content {
                flex: 1; display: grid; grid-template-columns: 1fr 24mm; gap: 2.6mm;
                padding: 2.4mm 3mm; min-height: 0;
            }
            .id-card-identity { display: flex; gap: 2.2mm; min-width: 0; }
            .id-card-photo {
                flex-shrink: 0; padding: 0.6mm; border-radius: 1.4mm; background: #fff;
                border: 0.2mm solid #dbe3ef;
            }
            .id-card-avatar {
                width: 13mm; height: 16mm; border-radius: 1mm;
                background: linear-gradient(160deg, #1e3a8a, #1e293b); color: #fff;
                display: flex; align-items: center; justify-content: center;
                font-size: 4.2mm; font-weight: 800; letter-spacing: 0.05em;
            }
            .id-card-kicker { font-size: 1.6mm; font-weight: 800; letter-spacing: 0.12em; text-transform: uppercase; color: #94a3b8; margin-bottom: 0.5mm; }
            .id-card-name { margin: 0; font-size: 3.8mm; font-weight: 800; letter-spacing: -0.02em; line-height: 1.1; color: #0f172a; }
            .id-card-divider { width: 7mm; height: 0.6mm; border-radius: 99px; background: #1e3a8a; margin: 1.4mm 0 1.6mm; }
            .id-card-grid { margin: 0; display: grid; grid-template-columns: 1fr 1fr; gap: 1.2mm 2mm; }
            .id-card-grid dt { font-size: 1.5mm; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: #94a3b8; margin-bottom: 0.2mm; }
            .id-card-grid dd { margin: 0; font-size: 2.2mm; font-weight: 700; color: #334155; line-height: 1.2; }
            .id-card-scan {
                display: flex; flex-direction: column; align-items: center; justify-content: center;
                text-align: center; padding: 1.4mm; border-radius: 1.8mm; background: #fff; border: 0.2mm solid #dbe3ef;
            }
            .id-card-qr-wrap {
                width: 18.5mm; height: 18.5mm; padding: 0.8mm; border-radius: 1.2mm;
                border: 0.2mm solid #e2e8f0; display: flex; align-items: center; justify-content: center; background: #fff;
            }
            .id-card-qr-img { width: 16.8mm; height: 16.8mm; object-fit: contain; display: block; }
            .id-card-scan-label { margin-top: 1mm; font-size: 1.5mm; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; color: #1e3a8a; }
            .id-card-scan-code { margin-top: 0.3mm; font-size: 1.8mm; font-weight: 700; font-family: Consolas, monospace; color: #64748b; }
            .id-card-bottom {
                margin-top: auto; display: flex; justify-content: space-between; gap: 2mm; align-items: flex-end;
                padding: 1.6mm 3mm 2mm; border-top: 0.2mm solid #e2e8f0; background: #f1f5f9;
            }
            .id-card-bottom-left { display: flex; flex-direction: column; gap: 0.3mm; }
            .id-card-bottom-left strong { font-size: 1.8mm; font-weight: 800; color: #1e293b; }
            .id-card-bottom-left span { font-size: 1.6mm; font-weight: 500; color: #64748b; }
            .id-card-bottom-right { text-align: right; }
            .id-card-sig-label { display: block; font-size: 1.4mm; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #94a3b8; }
            .id-card-sig { display: inline-block; margin-top: 0.3mm; font-size: 2.1mm; font-weight: 700; font-style: italic; color: #1e3a8a; border-bottom: 0.2mm solid #cbd5e1; padding: 0 0.6mm 0.3mm; }
            @media print {
                html, body {
                    width: 100%;
                    height: 100%;
                    margin: 0;
                    padding: 0;
                }
                body {
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    min-height: 100vh;
                }
                .student-id-card {
                    margin: 0 auto;
                }
            }
        `;

        printWindow.document.open();
        printWindow.document.write(`<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Student ID Card</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
  <style>${styles}</style>
</head>
<body>
  ${card.outerHTML}
</body>
</html>`);
        printWindow.document.close();

        const triggerPrint = () => {
            printWindow.focus();
            printWindow.print();
        };

        const qrImg = printWindow.document.querySelector('.id-card-qr-img');
        if (qrImg && !qrImg.complete) {
            qrImg.onload = triggerPrint;
            qrImg.onerror = triggerPrint;
            setTimeout(triggerPrint, 1200);
        } else {
            setTimeout(triggerPrint, 250);
        }
    },

    // ─── QR Code Generation ────────────────────────────────
    generateQrCode() {
        FormValidation.clear(['studentName', 'studentCourse', 'parentName', 'parentPhone', 'generatedCode']);

        const nameEl = document.getElementById('studentName');
        const courseEl = document.getElementById('studentCourse');
        const parentName = document.getElementById('parentName').value.trim();
        const parentPhone = document.getElementById('parentPhone').value.trim();

        const errors = {};
        const nameErr = FormValidation.personName(nameEl.value, 'Student name');
        const courseErr = FormValidation.courseSection(courseEl.value);
        const parentNameErr = FormValidation.personName(parentName, 'Parent name', false);
        const parentPhoneErr = FormValidation.phone(parentPhone, 'Parent phone', false);

        if (nameErr) errors.studentName = nameErr;
        if (courseErr) errors.studentCourse = courseErr;
        if (parentNameErr) errors.parentName = parentNameErr;
        if (parentPhoneErr) errors.parentPhone = parentPhoneErr;

        if (Object.keys(errors).length) {
            FormValidation.show(errors);
            this.showToast('error', 'Validation', FormValidation.firstMessage(errors));
            return;
        }

        const code = this.generateRandomCode(10);
        document.getElementById('generatedCode').value = code;

        const qrImg = document.getElementById('qrImg');
        qrImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(code)}`;

        nameEl.readOnly = true;
        courseEl.readOnly = true;
        document.querySelector('.qr-con').style.display = '';
        document.querySelector('.qr-generator').style.display = 'none';
        document.querySelector('.modal-close').style.display = '';
    },

    generateRandomCode(length) {
        const chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        let result = '';
        for (let i = 0; i < length; i++) {
            result += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return result;
    },

    resetAddStudentModal() {
        FormValidation.clear(['studentName', 'studentCourse', 'parentName', 'parentPhone', 'generatedCode']);

        const nameEl = document.getElementById('studentName');
        const courseEl = document.getElementById('studentCourse');
        const parentNameEl = document.getElementById('parentName');
        const parentPhoneEl = document.getElementById('parentPhone');
        if (nameEl) {
            nameEl.value = '';
            nameEl.readOnly = false;
        }
        if (courseEl) {
            courseEl.value = '';
            courseEl.readOnly = false;
        }
        if (parentNameEl) parentNameEl.value = '';
        if (parentPhoneEl) parentPhoneEl.value = '';
        const codeEl = document.getElementById('generatedCode');
        if (codeEl) codeEl.value = '';
        const qrCon = document.querySelector('.qr-con');
        if (qrCon) qrCon.style.display = 'none';
        const genBtn = document.querySelector('.qr-generator');
        if (genBtn) genBtn.style.display = '';
        const closeBtn = document.querySelector('.modal-close');
        if (closeBtn) closeBtn.style.display = 'none';
    },

    // ─── Authentication Handlers ───────────────────────────
    showLoginPanel(panel) {
        const loginForm = document.getElementById('loginForm');
        const forgotForm = document.getElementById('forgotPasswordForm');
        const resetForm = document.getElementById('resetPasswordForm');
        if (loginForm) loginForm.style.display = panel === 'login' ? '' : 'none';
        if (forgotForm) forgotForm.style.display = panel === 'forgot' ? '' : 'none';
        if (resetForm) resetForm.style.display = panel === 'reset' ? '' : 'none';
    },

    bindLogin() {
        const loginForm = document.getElementById('loginForm');
        const forgotForm = document.getElementById('forgotPasswordForm');
        const resetForm = document.getElementById('resetPasswordForm');
        const forgotLink = document.getElementById('forgotPasswordLink');
        const backToLoginBtn = document.getElementById('backToLoginBtn');
        const backToForgotBtn = document.getElementById('backToForgotBtn');
        const errorDiv = $('#login-error');
        const successDiv = $('#login-success');

        const clearAlerts = () => {
            errorDiv.hide().text('');
            successDiv.hide().text('');
        };

        if (forgotLink) {
            forgotLink.addEventListener('click', (e) => {
                e.preventDefault();
                clearAlerts();
                const user = ($('#loginUsername').val() || '').trim();
                if (user) $('#forgotUsername').val(user);
                this.showLoginPanel('forgot');
            });
        }
        if (backToLoginBtn) {
            backToLoginBtn.addEventListener('click', () => {
                clearAlerts();
                this.showLoginPanel('login');
            });
        }
        if (backToForgotBtn) {
            backToForgotBtn.addEventListener('click', () => {
                clearAlerts();
                this.showLoginPanel('forgot');
            });
        }

        loginForm.addEventListener('submit', (e) => {
            e.preventDefault();
            FormValidation.clear(['loginUsername', 'loginPassword']);
            clearAlerts();

            const username = $('#loginUsername').val().trim();
            const password = $('#loginPassword').val();

            const errors = {};
            const userErr = FormValidation.loginUsername(username);
            const passErr = FormValidation.loginPassword(password);
            if (userErr) errors.loginUsername = userErr;
            if (passErr) errors.loginPassword = passErr;

            if (Object.keys(errors).length) {
                FormValidation.show(errors);
                errorDiv.text(FormValidation.firstMessage(errors)).fadeIn();
                return;
            }

            $.ajax({
                url: './endpoint/login.php',
                method: 'POST',
                data: { username, password },
                dataType: 'json',
                success: (res) => {
                    if (res.success) {
                        window.location.reload();
                    } else {
                        const map = { username: 'loginUsername', password: 'loginPassword' };
                        if (res.errors) FormValidation.show(res.errors, map);
                        errorDiv.text(res.message || 'Invalid username or password.').fadeIn();
                    }
                },
                error: () => {
                    errorDiv.text('An error occurred. Please try again.').fadeIn();
                }
            });
        });

        if (forgotForm) {
            forgotForm.addEventListener('submit', (e) => {
                e.preventDefault();
                FormValidation.clear(['forgotUsername']);
                clearAlerts();

                const username = ($('#forgotUsername').val() || '').trim();
                const userErr = FormValidation.loginUsername(username);
                if (userErr) {
                    FormValidation.show({ forgotUsername: userErr });
                    errorDiv.text(userErr).fadeIn();
                    return;
                }

                $.ajax({
                    url: './endpoint/forgot-password.php',
                    method: 'POST',
                    data: { username },
                    dataType: 'json',
                    success: (res) => {
                        if (res.success && res.sent) {
                            $('#resetUsername').val(username);
                            successDiv.text(res.message).fadeIn();
                            this.showLoginPanel('reset');
                        } else if (res.success) {
                            successDiv.text(res.message).fadeIn();
                        } else {
                            if (res.errors) {
                                FormValidation.show(res.errors, { username: 'forgotUsername' });
                            }
                            errorDiv.text(res.message || 'Unable to send reset code.').fadeIn();
                        }
                    },
                    error: () => {
                        errorDiv.text('Could not contact server. Please try again.').fadeIn();
                    }
                });
            });
        }

        if (resetForm) {
            resetForm.addEventListener('submit', (e) => {
                e.preventDefault();
                FormValidation.clear(['resetOtp', 'resetPassword', 'resetConfirmPassword']);
                clearAlerts();

                const username = ($('#resetUsername').val() || '').trim();
                const otp = ($('#resetOtp').val() || '').trim();
                const password = $('#resetPassword').val() || '';
                const confirmPassword = $('#resetConfirmPassword').val() || '';

                const errors = {};
                if (!otp) errors.resetOtp = 'Verification code is required.';
                const passErr = FormValidation.password(password, true);
                if (passErr) errors.resetPassword = passErr;
                if (!confirmPassword) {
                    errors.resetConfirmPassword = 'Please confirm your new password.';
                } else if (password !== confirmPassword) {
                    errors.resetConfirmPassword = 'Password and confirmation do not match.';
                }

                if (Object.keys(errors).length) {
                    FormValidation.show(errors);
                    errorDiv.text(FormValidation.firstMessage(errors)).fadeIn();
                    return;
                }

                $.ajax({
                    url: './endpoint/reset-password.php',
                    method: 'POST',
                    data: {
                        username,
                        otp,
                        password,
                        confirm_password: confirmPassword
                    },
                    dataType: 'json',
                    success: (res) => {
                        if (res.success) {
                            successDiv.text(res.message).fadeIn();
                            this.showLoginPanel('login');
                            $('#loginUsername').val(username);
                            $('#loginPassword').val('');
                        } else {
                            if (res.errors) {
                                FormValidation.show(res.errors, {
                                    otp: 'resetOtp',
                                    password: 'resetPassword',
                                    confirm_password: 'resetConfirmPassword'
                                });
                            }
                            errorDiv.text(res.message || 'Unable to reset password.').fadeIn();
                        }
                    },
                    error: () => {
                        errorDiv.text('Could not contact server. Please try again.').fadeIn();
                    }
                });
            });
        }
    },

    saveProfile(event) {
        if (event) event.preventDefault();
        const fields = ['profileName', 'profileUsername', 'profilePhone'];
        FormValidation.clear(fields);

        const name = document.getElementById('profileName').value.trim();
        const username = document.getElementById('profileUsername').value.trim();
        const phone = document.getElementById('profilePhone').value.trim();

        const errors = {};
        const nameErr = FormValidation.personName(name, 'Full name');
        const userErr = FormValidation.username(username);
        const phoneErr = FormValidation.phone(phone, 'Phone', false);
        if (nameErr) errors.profileName = nameErr;
        if (userErr) errors.profileUsername = userErr;
        if (phoneErr) errors.profilePhone = phoneErr;

        if (Object.keys(errors).length) {
            FormValidation.show(errors);
            this.showToast('error', 'Validation', FormValidation.firstMessage(errors));
            return false;
        }

        $.ajax({
            url: './endpoint/update-profile.php',
            method: 'POST',
            data: { name, username, phone },
            dataType: 'json',
            success: (res) => {
                if (res.success) {
                    this.showToast('success', 'Saved', res.message);
                    if (res.user) {
                        const nameEls = document.querySelectorAll('.sidebar-user-name, .navbar-user-name');
                        nameEls.forEach((el) => { el.textContent = res.user.name; });
                    }
                } else {
                    if (res.errors) {
                        FormValidation.show(res.errors, {
                            name: 'profileName',
                            username: 'profileUsername',
                            phone: 'profilePhone'
                        });
                    }
                    this.showToast('error', 'Validation', res.message || 'Unable to save profile.');
                }
            },
            error: () => this.showToast('error', 'Error', 'Failed to save profile.')
        });
        return false;
    },

    changePassword(event) {
        if (event) event.preventDefault();
        const fields = ['currentPassword', 'newPassword', 'confirmNewPassword'];
        FormValidation.clear(fields);

        const currentPassword = document.getElementById('currentPassword').value;
        const newPassword = document.getElementById('newPassword').value;
        const confirmPassword = document.getElementById('confirmNewPassword').value;

        const errors = {};
        if (!currentPassword) errors.currentPassword = 'Current password is required.';
        const passErr = FormValidation.password(newPassword, true);
        if (passErr) errors.newPassword = passErr;
        if (!confirmPassword) {
            errors.confirmNewPassword = 'Please confirm your new password.';
        } else if (newPassword !== confirmPassword) {
            errors.confirmNewPassword = 'New password and confirmation do not match.';
        }

        if (Object.keys(errors).length) {
            FormValidation.show(errors);
            this.showToast('error', 'Validation', FormValidation.firstMessage(errors));
            return false;
        }

        $.ajax({
            url: './endpoint/change-password.php',
            method: 'POST',
            data: {
                current_password: currentPassword,
                new_password: newPassword,
                confirm_password: confirmPassword
            },
            dataType: 'json',
            success: (res) => {
                if (res.success) {
                    this.showToast('success', 'Updated', res.message);
                    document.getElementById('currentPassword').value = '';
                    document.getElementById('newPassword').value = '';
                    document.getElementById('confirmNewPassword').value = '';
                } else {
                    if (res.errors) {
                        FormValidation.show(res.errors, {
                            current_password: 'currentPassword',
                            new_password: 'newPassword',
                            confirm_password: 'confirmNewPassword'
                        });
                    }
                    this.showToast('error', 'Validation', res.message || 'Unable to change password.');
                }
            },
            error: () => this.showToast('error', 'Error', 'Failed to change password.')
        });
        return false;
    },

    logout(e) {
        if (e) e.preventDefault();
        $.ajax({
            url: './endpoint/logout.php',
            method: 'POST',
            dataType: 'json',
            success: (res) => {
                if (res.success) {
                    window.location.reload();
                } else {
                    this.showToast('error', 'Logout Failed', res.message);
                }
            },
            error: () => {
                this.showToast('error', 'Logout Error', 'Could not contact server.');
            }
        });
    },

    // ─── User Management Page ──────────────────────────────
    initUsers() {
        if ($.fn.DataTable && document.getElementById('userTable')) {
            $('#userTable').DataTable({
                order: [[0, 'desc']],
                language: { emptyTable: 'No users created yet' }
            });
        }
    },

    resetAddUserModal() {
        FormValidation.clear(['newUserName', 'newUserUsername', 'newUserPassword', 'newUserRole']);
        const name = document.getElementById('newUserName');
        const user = document.getElementById('newUserUsername');
        const pass = document.getElementById('newUserPassword');
        const role = document.getElementById('newUserRole');
        if (name) name.value = '';
        if (user) user.value = '';
        if (pass) pass.value = '';
        if (role) role.value = 'staff';
    },

    addUser() {
        const fields = ['newUserName', 'newUserUsername', 'newUserPassword', 'newUserRole'];
        FormValidation.clear(fields);

        const name = document.getElementById('newUserName').value.trim();
        const username = document.getElementById('newUserUsername').value.trim();
        const password = document.getElementById('newUserPassword').value;
        const role = document.getElementById('newUserRole').value;

        const errors = {};
        const nameErr = FormValidation.personName(name, 'Full name');
        const userErr = FormValidation.username(username);
        const passErr = FormValidation.password(password, true);
        const roleErr = FormValidation.role(role);

        if (nameErr) errors.newUserName = nameErr;
        if (userErr) errors.newUserUsername = userErr;
        if (passErr) errors.newUserPassword = passErr;
        if (roleErr) errors.newUserRole = roleErr;

        if (Object.keys(errors).length) {
            FormValidation.show(errors);
            this.showToast('error', 'Validation', FormValidation.firstMessage(errors));
            return;
        }

        $.ajax({
            url: './endpoint/add-user.php',
            method: 'POST',
            data: { name, username, password, role },
            dataType: 'json',
            success: (res) => {
                if (res.success) {
                    this.showToast('success', 'Success', res.message);
                    $('#addUserModal').modal('hide');
                    this.currentPage = null;
                    this.loadPage('users');
                } else {
                    const map = {
                        name: 'newUserName',
                        username: 'newUserUsername',
                        password: 'newUserPassword',
                        role: 'newUserRole'
                    };
                    if (res.errors) FormValidation.show(res.errors, map);
                    this.showToast('error', 'Validation', res.message || 'Unable to add user.');
                }
            },
            error: () => {
                this.showToast('error', 'Error', 'Failed to add user.');
            }
        });
    },

    openUpdateUserModal(id, currentRole) {
        FormValidation.clear(['updateUserName', 'updateUserUsername', 'updateUserPassword', 'updateUserRole']);

        const name = document.getElementById('userName-' + id).textContent;
        const username = document.getElementById('userUsername-' + id).textContent;

        document.getElementById('updateUserId').value = id;
        document.getElementById('updateUserName').value = name;
        document.getElementById('updateUserUsername').value = username;
        document.getElementById('updateUserPassword').value = '';
        document.getElementById('updateUserRole').value = currentRole;

        $('#updateUserModal').modal('show');
    },

    updateUser() {
        const fields = ['updateUserName', 'updateUserUsername', 'updateUserPassword', 'updateUserRole'];
        FormValidation.clear(fields);

        const id = document.getElementById('updateUserId').value;
        const name = document.getElementById('updateUserName').value.trim();
        const username = document.getElementById('updateUserUsername').value.trim();
        const password = document.getElementById('updateUserPassword').value;
        const role = document.getElementById('updateUserRole').value;

        const errors = {};
        const nameErr = FormValidation.personName(name, 'Full name');
        const userErr = FormValidation.username(username);
        const passErr = FormValidation.password(password, false);
        const roleErr = FormValidation.role(role);

        if (nameErr) errors.updateUserName = nameErr;
        if (userErr) errors.updateUserUsername = userErr;
        if (passErr) errors.updateUserPassword = passErr;
        if (roleErr) errors.updateUserRole = roleErr;

        if (Object.keys(errors).length) {
            FormValidation.show(errors);
            this.showToast('error', 'Validation', FormValidation.firstMessage(errors));
            return;
        }

        $.ajax({
            url: './endpoint/update-user.php',
            method: 'POST',
            data: { tbl_user_id: id, name, username, password, role },
            dataType: 'json',
            success: (res) => {
                if (res.success) {
                    this.showToast('success', 'Updated', res.message);
                    $('#updateUserModal').modal('hide');
                    
                    // If updating self, refresh UI user details
                    const wasSelf = (document.querySelector('.sidebar-user-name').textContent.trim() === name);
                    this.currentPage = null;
                    this.loadPage('users');
                    
                    if (wasSelf) {
                        window.location.reload();
                    }
                } else {
                    const map = {
                        name: 'updateUserName',
                        username: 'updateUserUsername',
                        password: 'updateUserPassword',
                        role: 'updateUserRole'
                    };
                    if (res.errors) FormValidation.show(res.errors, map);
                    this.showToast('error', 'Validation', res.message || 'Unable to update user.');
                }
            },
            error: () => {
                this.showToast('error', 'Error', 'Failed to update user.');
            }
        });
    },

    deleteUser(id) {
        if (!confirm('Are you sure you want to delete this user?')) return;

        $.ajax({
            url: './endpoint/delete-user.php',
            method: 'POST',
            data: { tbl_user_id: id },
            dataType: 'json',
            success: (res) => {
                if (res.success) {
                    this.showToast('success', 'Deleted', res.message);
                    this.currentPage = null;
                    this.loadPage('users');
                } else {
                    this.showToast('error', 'Error', res.message);
                }
            },
            error: () => {
                this.showToast('error', 'Error', 'Failed to delete user.');
            }
        });
    },

    // ─── Sidebar ───────────────────────────────────────────
    bindSidebar() {
        const sidebar = document.getElementById('sidebar');
        const mainWrapper = document.getElementById('main-wrapper');
        const toggleBtn = document.getElementById('sidebar-toggle');
        const backdrop = document.getElementById('sidebar-backdrop');

        // Restore collapse state
        if (localStorage.getItem('sidebar-collapsed') === 'true' && window.innerWidth >= 992) {
            sidebar.classList.add('collapsed');
            mainWrapper.classList.add('sidebar-collapsed');
        }

        toggleBtn.addEventListener('click', () => {
            if (window.innerWidth < 992) {
                sidebar.classList.toggle('show');
                backdrop.classList.toggle('show');
            } else {
                sidebar.classList.toggle('collapsed');
                mainWrapper.classList.toggle('sidebar-collapsed');
                localStorage.setItem('sidebar-collapsed', sidebar.classList.contains('collapsed'));
            }
        });

        backdrop.addEventListener('click', () => {
            sidebar.classList.remove('show');
            backdrop.classList.remove('show');
        });
    },

    bindRouting() {
        document.querySelectorAll('.sidebar-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const page = link.dataset.page;
                window.location.hash = page;
            });
        });
    },

    // ─── Toast Notifications ───────────────────────────────
    showToast(type, title, message) {
        const container = document.getElementById('toast-container');
        const id = 'toast-' + Date.now();

        const icons = {
            success: 'bi-check-circle-fill',
            error: 'bi-x-circle-fill',
            warning: 'bi-exclamation-triangle-fill'
        };
        const colors = {
            success: 'var(--success)',
            error: 'var(--danger)',
            warning: 'var(--warning)'
        };

        const toastHTML = `
            <div id="${id}" class="toast toast-${type}" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="4000">
                <div class="toast-header">
                    <i class="bi ${icons[type]} me-2" style="color: ${colors[type]}; font-size: 1rem;"></i>
                    <strong class="me-auto" style="font-size: 0.82rem;">${title}</strong>
                    <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="toast"></button>
                </div>
                <div class="toast-body">${message}</div>
            </div>`;

        container.insertAdjacentHTML('beforeend', toastHTML);

        const toastEl = document.getElementById(id);
        const bsToast = new bootstrap.Toast(toastEl);
        bsToast.show();

        toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
    }
};

// ─── Boot ──────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => DashboardApp.init());
