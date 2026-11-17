/**
 * LaporFasilitas - Core Frontend Script
 */

document.addEventListener('DOMContentLoaded', () => {
  // Auto-dismiss alerts after 5 seconds
  const alerts = document.querySelectorAll('.alert-dismissible');
  alerts.forEach(alert => {
    setTimeout(() => {
      alert.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
      alert.style.opacity = '0';
      alert.style.transform = 'translateY(-10px)';
      setTimeout(() => alert.remove(), 500);
    }, 5000);
  });

  // Image preview helper for file inputs
  const photoInput = document.querySelector('input[type="file"][data-preview-target]');
  if (photoInput) {
    const targetId = photoInput.getAttribute('data-preview-target');
    const previewContainer = document.getElementById(targetId);
    
    photoInput.addEventListener('change', (e) => {
      const file = e.target.files[0];
      if (file && previewContainer) {
        const reader = new FileReader();
        reader.onload = (event) => {
          previewContainer.innerHTML = `
            <div style="position:relative; display:inline-block; margin-top:0.5rem;">
              <img src="${event.target.result}" alt="Preview" style="max-height: 220px; border-radius: 8px; border: 1px solid #cbd5e1; object-fit: cover;">
            </div>
          `;
        };
        reader.readAsDataURL(file);
      }
    });
  }
});
