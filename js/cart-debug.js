/**
 * Helper script to debug cart issues
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log('Cart debug script loaded');
    
    // Create a debug button
    const debugBtn = document.createElement('button');
    debugBtn.textContent = 'Debug Cart';
    debugBtn.className = 'btn btn-sm btn-warning position-fixed';
    debugBtn.style.bottom = '10px';
    debugBtn.style.right = '10px';
    debugBtn.style.zIndex = '9999';
    debugBtn.style.opacity = '0.7';
    
    debugBtn.addEventListener('click', function() {
        // Get product ID from URL
        const urlParams = new URLSearchParams(window.location.search);
        const urlId = parseInt(urlParams.get('id'), 10);
        
        // Get product ID from hidden input
        const hiddenId = document.getElementById('current-product-id')?.value;
        
        // Display debug information
        alert(`
Debug Cart Information:
---------------------
URL: ${window.location.href}
Product ID from URL: ${urlId}
Product ID from hidden field: ${hiddenId}
Is a valid number: ${!isNaN(parseInt(hiddenId))}
---------------------
If these values don't match, there's your problem!
        `);
        
        // Log to console for more detailed inspection
        console.log('Debug Cart Information:', {
            url: window.location.href,
            urlId: urlId,
            hiddenId: hiddenId,
            hiddenIdParsed: parseInt(hiddenId),
            isValid: !isNaN(parseInt(hiddenId))
        });
    });
    
    // Add the button to the page only in development
    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
        document.body.appendChild(debugBtn);
    }
});
