// Add animation classes when page loads
document.addEventListener('DOMContentLoaded', function () {
    // Add animation to elements
    const elementsToAnimate = document.querySelectorAll('.contact-card, .social-icons, .contact-form .card');
    elementsToAnimate.forEach(element => {
        element.classList.add('animate-up');
    });
});
