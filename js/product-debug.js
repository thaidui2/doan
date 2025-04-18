// Debug tool for product-detail page
document.addEventListener('DOMContentLoaded', function() {
    console.log('Product debug script loaded');
    
    // Create a debug button if in development environment
    const debugBtn = document.createElement('button');
    debugBtn.textContent = 'Debug Info';
    debugBtn.className = 'btn btn-sm btn-outline-secondary position-fixed';
    debugBtn.style.bottom = '10px';
    debugBtn.style.left = '10px';
    debugBtn.style.zIndex = '9999';
    
    debugBtn.addEventListener('click', function() {
        const debugInfo = {
            'Product ID': document.querySelector('[name="product_id"]')?.value || 'unknown',
            'Hidden Field Value': document.getElementById('current-product-id')?.value || 'not found',
            'Browser URL': window.location.href,
            'Query String': window.location.search,
            'ID From URL': new URLSearchParams(window.location.search).get('id')
        };
        
        console.table(debugInfo);
        
        // Create a popup to show the debug info
        const debugDiv = document.createElement('div');
        debugDiv.className = 'position-fixed top-0 start-0 bg-light p-3 border shadow';
        debugDiv.style.zIndex = '10000';
        debugDiv.style.maxWidth = '80%';
        debugDiv.style.maxHeight = '80%';
        debugDiv.style.overflow = 'auto';
        
        let debugHTML = '<h5>Debug Information</h5><table class="table table-sm">';
        for (const [key, value] of Object.entries(debugInfo)) {
            debugHTML += `<tr><td><strong>${key}</strong></td><td>${value}</td></tr>`;
        }
        debugHTML += '</table><button class="btn btn-sm btn-secondary" id="close-debug">Close</button>';
        
        debugDiv.innerHTML = debugHTML;
        document.body.appendChild(debugDiv);
        
        document.getElementById('close-debug').addEventListener('click', function() {
            debugDiv.remove();
        });
    });
    
    // Only add in development environment - check for localhost
    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
        document.body.appendChild(debugBtn);
    }
});
