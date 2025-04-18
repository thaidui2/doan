// Add basic debugging and helper functions for the product detail page
document.addEventListener('DOMContentLoaded', function() {
    console.log('Product detail page script loaded');
    
    // Debug information
    const productId = document.getElementById('current-product-id')?.value;
    console.log('Current product ID:', productId);
    
    // Log button click events for debugging
    const addToCartBtn = document.getElementById('addToCartBtn');
    if (addToCartBtn) {
        addToCartBtn.addEventListener('click', function() {
            console.log('Add to cart button clicked');
            console.log('Product ID when adding to cart:', document.getElementById('current-product-id').value);
        }, { capture: true });
    }
    
    const buyNowBtn = document.getElementById('buyNowBtn');
    if (buyNowBtn) {
        buyNowBtn.addEventListener('click', function() {
            console.log('Buy now button clicked');
            console.log('Product ID when buying now:', document.getElementById('current-product-id').value);
        }, { capture: true });
    }
});
