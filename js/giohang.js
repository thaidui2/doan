document.addEventListener("DOMContentLoaded", function () {
  // Handle select all checkbox
  const selectAllCheckbox = document.getElementById('select-all');
  const itemCheckboxes = document.querySelectorAll('.item-checkbox:not(:disabled)');
  const deleteSelectedBtn = document.getElementById('delete-selected-btn');
  const checkoutSelectedBtn = document.getElementById('checkout-selected-btn');
  const selectedCountDelete = document.getElementById('selected-count-delete');
  const selectedCount = document.getElementById('selected-count');
  const totalFullSpan = document.getElementById('total-full');
  const totalSelectedSpan = document.getElementById('total-selected');

  if (selectAllCheckbox) {
      selectAllCheckbox.addEventListener('change', function() {
          itemCheckboxes.forEach(checkbox => {
              checkbox.checked = this.checked;
          });
          updateSelectedCount();
          updateTotalPrice();
      });
  }

  // Handle individual checkboxes
  itemCheckboxes.forEach(checkbox => {
      checkbox.addEventListener('change', function() {
          updateSelectedCount();
          updateTotalPrice();
          
          // Check if all checkboxes are selected
          if (selectAllCheckbox) {
              selectAllCheckbox.checked = [...itemCheckboxes].every(cb => cb.checked);
          }
      });
  });

  // Update selected count
  function updateSelectedCount() {
      const checkedCount = document.querySelectorAll('.item-checkbox:checked').length;
      
      if (selectedCount) selectedCount.textContent = checkedCount;
      if (selectedCountDelete) selectedCountDelete.textContent = checkedCount;
      
      if (deleteSelectedBtn) {
          deleteSelectedBtn.disabled = checkedCount === 0;
      }
      
      if (checkoutSelectedBtn) {
          checkoutSelectedBtn.disabled = checkedCount === 0;
      }
  }

  // Update total price based on selection
  function updateTotalPrice() {
      const checkedBoxes = document.querySelectorAll('.item-checkbox:checked');
      
      if (checkedBoxes.length > 0) {
          let totalPrice = 0;
          checkedBoxes.forEach(box => {
              totalPrice += parseFloat(box.dataset.price || 0);
          });
          
          if (totalSelectedSpan) {
              totalSelectedSpan.textContent = formatCurrency(totalPrice);
              totalSelectedSpan.classList.remove('d-none');
          }
          
          if (totalFullSpan) {
              totalFullSpan.classList.add('d-none');
          }
      } else {
          if (totalSelectedSpan) {
              totalSelectedSpan.classList.add('d-none');
          }
          
          if (totalFullSpan) {
              totalFullSpan.classList.remove('d-none');
          }
      }
  }

  // Handle clear cart button
  const clearCartBtn = document.getElementById('clear-cart-btn');
  if (clearCartBtn) {
      clearCartBtn.addEventListener('click', function(e) {
          e.preventDefault();
          if (confirm('Bạn có chắc chắn muốn xóa toàn bộ giỏ hàng?')) {
              window.location.href = 'giohang.php?clear_cart=1';
          }
      });
  }

  // Handle remove item buttons
  const removeButtons = document.querySelectorAll('.remove-item-btn');
  removeButtons.forEach(button => {
      button.addEventListener('click', function() {
          const itemId = this.dataset.itemId;
          if (confirm('Bạn có chắc chắn muốn xóa sản phẩm này khỏi giỏ hàng?')) {
              window.location.href = 'giohang.php?remove_item=' + itemId;
          }
      });
  });
  
  // Format currency
  function formatCurrency(amount) {
      return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', minimumFractionDigits: 0 }).format(amount);
  }

  // Khởi tạo khi trang tải xong
  updateSelectedCount();
  updateTotalPrice();
});
