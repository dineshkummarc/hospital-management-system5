// Confirmation for destructive actions (delete / cancel / deactivate / release bed).
document.addEventListener('click', function (e) {
  const el = e.target.closest('[data-confirm]');
  if (el) {
    if (!confirm(el.getAttribute('data-confirm'))) {
      e.preventDefault();
      e.stopPropagation();
    }
  }
});

// Generic "submit and show a loading state" for forms marked data-loading.
document.addEventListener('submit', function (e) {
  const form = e.target;
  if (form.matches('[data-loading]')) {
    const btn = form.querySelector('[type="submit"]');
    if (btn) {
      btn.disabled = true;
      btn.dataset.originalText = btn.innerHTML;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Please wait...';
    }
  }
});

// Time slot picker (used on patient/book_appointment.php).
function initSlotPicker(containerId, inputId) {
  const container = document.getElementById(containerId);
  const input = document.getElementById(inputId);
  if (!container || !input) return;
  container.addEventListener('click', function (e) {
    const btn = e.target.closest('.slot-btn');
    if (!btn || btn.disabled) return;
    container.querySelectorAll('.slot-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    input.value = btn.dataset.time;
  });
}
