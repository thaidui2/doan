// Add animation classes when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Add animation to elements
    const elementsToAnimate = document.querySelectorAll('.contact-card, .social-icons, .contact-form .card');
    elementsToAnimate.forEach(element => {
        element.classList.add('animate-up');
    });
    
    // Handle quick contact form submission
    const quickContactBtn = document.getElementById('submitQuickContact');
    if (quickContactBtn) {
        quickContactBtn.addEventListener('click', function() {
            alert('Cảm ơn bạn đã gửi yêu cầu! Chúng tôi sẽ liên hệ lại với bạn sớm nhất có thể.');
            const modal = bootstrap.Modal.getInstance(document.getElementById('quickContactModal'));
            modal.hide();
        });
    }
});
