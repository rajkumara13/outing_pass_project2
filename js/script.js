// script.js - Global JavaScript Utilities

document.addEventListener('DOMContentLoaded', () => {
    // 1. Auto-close Bootstrap Alerts after 4 seconds
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            if (bsAlert) {
                bsAlert.close();
            }
        }, 4000);
    });

    // 2. Custom Delete Confirmation Dialog
    const deleteButtons = document.querySelectorAll('.btn-confirm-delete');
    deleteButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            const confirmMsg = btn.getAttribute('data-confirm-message') || 'Are you sure you want to delete this item?';
            if (!confirm(confirmMsg)) {
                e.preventDefault();
            }
        });
    });

    // 3. Simple Form Validation Visual Feedback
    const forms = document.querySelectorAll('.needs-validation');
    forms.forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
});

// 4. Quick Print Helper
function printReport() {
    window.print();
}
