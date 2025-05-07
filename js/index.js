document.addEventListener("DOMContentLoaded", function () {
  // Xử lý nút thêm vào giỏ hàng
  const addToCartButtons = document.querySelectorAll(
    ".product-card .btn-primary"
  );
  addToCartButtons.forEach((button) => {
    button.addEventListener("click", function () {
      const productName =
        this.closest(".product-card").querySelector(".card-title").textContent;
      alert(`Đã thêm sản phẩm "${productName}" vào giỏ hàng!`);
      // TODO: Thêm logic xử lý giỏ hàng thực tế ở đây
    });
  });

  // Hiệu ứng cho nút thêm vào giỏ hàng
  const addToCartEffectButtons = document.querySelectorAll(".add-to-cart");
  addToCartEffectButtons.forEach((button) => {
    button.addEventListener("mouseenter", function () {
      this.innerHTML = '<i class="bi bi-cart-check me-2"></i> Thêm ngay';
    });

    button.addEventListener("mouseleave", function () {
      this.innerHTML = '<i class="bi bi-cart-plus me-2"></i> Thêm vào giỏ';
    });
  });

  // Xử lý nút yêu thích
  

  // Hiệu ứng cho nút yêu thích
  const wishlistEffectButtons = document.querySelectorAll(
    ".product-action button:first-child"
  );
  wishlistEffectButtons.forEach((button) => {
    button.addEventListener("click", function () {
      const icon = this.querySelector("i");
      if (icon.classList.contains("bi-heart")) {
        icon.classList.remove("bi-heart");
        icon.classList.add("bi-heart-fill");
        this.classList.add("text-danger");

        // Hiệu ứng tim bay lên
        const heart = document.createElement("div");
        heart.classList.add("floating-heart");
        this.appendChild(heart);

        setTimeout(() => {
          heart.remove();
        }, 1000);
      } else {
        icon.classList.remove("bi-heart-fill");
        icon.classList.remove("text-danger");
        icon.classList.add("bi-heart");
        this.classList.remove("text-danger");
      }
    });
  });

  // Xử lý form đăng ký newsletter
  const newsletterForm = document.querySelector(".newsletter form");
  if (newsletterForm) {
    newsletterForm.addEventListener("submit", function (e) {
      e.preventDefault();
      const emailInput = this.querySelector('input[type="email"]');
      alert(`Cảm ơn bạn đã đăng ký với email: ${emailInput.value}`);
      emailInput.value = "";
    });
  }

  // Biến để theo dõi vị trí hiện tại của carousel
  let currentPosition = 0;
  const itemsPerPage = window.innerWidth < 768 ? 2 : 4; // Responsive items
  const productItems = document.querySelectorAll(".product-item");
  const totalItems = productItems.length;
  const maxPosition = Math.max(0, Math.ceil(totalItems / itemsPerPage) - 1);

  // Nếu không có đủ sản phẩm để tạo carousel, ẩn nút điều hướng
  if (totalItems <= itemsPerPage) {
    document.querySelector(".carousel-navigation").style.display = "none";
  }

  // Khởi tạo hiển thị sản phẩm
  updateProductVisibility();

  // Xử lý nút prev
  document.getElementById("prevProduct").addEventListener("click", function () {
    if (currentPosition > 0) {
      currentPosition--;
      updateProductVisibility();
    }
  });

  // Xử lý nút next
  document.getElementById("nextProduct").addEventListener("click", function () {
    if (currentPosition < maxPosition) {
      currentPosition++;
      updateProductVisibility();
    }
  });

  // Hàm cập nhật hiển thị sản phẩm
  function updateProductVisibility() {
    const startIndex = currentPosition * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;

    // Ẩn tất cả sản phẩm
    productItems.forEach((item, index) => {
      if (index >= startIndex && index < endIndex) {
        item.style.display = "block";
      } else {
        item.style.display = "none";
      }
    });

    // Cập nhật trạng thái nút
    document.getElementById("prevProduct").disabled = currentPosition === 0;
    document.getElementById("nextProduct").disabled =
      currentPosition === maxPosition;
  }

  // Xử lý nút thêm vào giỏ
  
  // Hàm thêm sản phẩm vào giỏ hàng
  function addToCart(productId, quantity) {
    fetch("add_to_cart.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: `product_id=${productId}&quantity=${quantity}`,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          // Hiển thị thông báo thành công
          const toast = document.createElement("div");
          toast.className = "toast-notification success show";
          toast.innerHTML = `
                    <div class="toast-icon"><i class="bi bi-check-circle-fill"></i></div>
                    <div class="toast-message">Sản phẩm đã được thêm vào giỏ hàng!</div>
                `;
          document.body.appendChild(toast);

          // Cập nhật số lượng trên icon giỏ hàng
          if (document.querySelector(".cart-count")) {
            document.querySelector(".cart-count").textContent = data.cart_count;
          }

          // Tự động xóa thông báo sau 3 giây
          setTimeout(() => {
            toast.classList.remove("show");
            setTimeout(() => {
              toast.remove();
            }, 300);
          }, 3000);
        } else {
          alert(
            data.message || "Có lỗi xảy ra khi thêm sản phẩm vào giỏ hàng."
          );
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        alert("Đã xảy ra lỗi khi thêm sản phẩm vào giỏ hàng.");
      });
  }

  // Xử lý nút xem nhanh sản phẩm
  const quickViewButtons = document.querySelectorAll(".quick-view");
  const quickViewModal = new bootstrap.Modal(
    document.getElementById("quickViewModal")
  );

  quickViewButtons.forEach((button) => {
    button.addEventListener("click", function () {
      const productId = this.getAttribute("data-id");
      document.getElementById("quickViewContent").innerHTML =
        '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Đang tải...</span></div></div>';
      quickViewModal.show();

      // Gọi AJAX để lấy thông tin chi tiết sản phẩm
      fetch(`get_product_quick_view.php?id=${productId}`)
        .then((response) => response.text())
        .then((html) => {
          document.getElementById("quickViewContent").innerHTML = html;

          // Khởi tạo thêm các chức năng trong modal nếu cần
          const modalAddToCartBtn = document.querySelector(
            "#quickViewContent .add-to-cart"
          );
          if (modalAddToCartBtn) {
            modalAddToCartBtn.addEventListener("click", function () {
              const id = this.getAttribute("data-product-id");
              const qty =
                document.querySelector("#quickViewContent .product-quantity")
                  .value || 1;
              addToCart(id, qty);
              quickViewModal.hide();
            });
          }
        })
        .catch((error) => {
          console.error("Error:", error);
          document.getElementById("quickViewContent").innerHTML =
            '<div class="alert alert-danger">Không thể tải thông tin sản phẩm.</div>';
        });
    });
  });

  // Xử lý lỗi hình ảnh sản phẩm
  const productImages = document.querySelectorAll(".product-img");

  productImages.forEach((img) => {
    img.onerror = function () {
      console.log("Lỗi tải hình: " + this.src);
      this.onerror = null; // Tránh vòng lặp vô hạn
      this.src = "images/no-image.jpg";
    };
  });
  
  // Wishlist functionality
  // Get all wishlist buttons
  const wishlistButtons = document.querySelectorAll('.wishlist-button');
    
  // Function to update button appearance
  function updateWishlistButton(button, isInWishlist) {
      const icon = button.querySelector('i.bi');
      
      if (isInWishlist) {
          icon.classList.remove('bi-heart');
          icon.classList.add('bi-heart-fill');
          button.classList.add('active');
          button.setAttribute('title', 'Xóa khỏi yêu thích');
      } else {
          icon.classList.remove('bi-heart-fill');
          icon.classList.add('bi-heart');
          button.classList.remove('active');
          button.setAttribute('title', 'Thêm vào yêu thích');
      }
  }
  
  // Check which products are in wishlist
  function checkWishlistStatus() {
      // Only check if user is logged in and there are products on page
      if (wishlistButtons.length > 0) {
          const productIds = Array.from(wishlistButtons).map(button => 
              button.getAttribute('data-product-id')
          );
          
          fetch('ajax/wishlist.php?check_products=' + JSON.stringify(productIds), {
              method: 'GET',
              credentials: 'same-origin'
          })
          .then(response => response.json())
          .then(data => {
              if (data.success && data.wishlist_items) {
                  // Update buttons for items in wishlist
                  wishlistButtons.forEach(button => {
                      const productId = button.getAttribute('data-product-id');
                      const isInWishlist = data.wishlist_items.includes(parseInt(productId));
                      updateWishlistButton(button, isInWishlist);
                  });
              }
          })
          .catch(error => {
              console.error('Error checking wishlist status:', error);
          });
      }
  }
  
  // Add click event listener to wishlist buttons
  wishlistButtons.forEach(button => {
      button.addEventListener('click', function(e) {
          e.preventDefault();
          e.stopPropagation(); // Prevent the event from bubbling up
          
          const productId = this.getAttribute('data-product-id');
          const formData = new FormData();
          formData.append('product_id', productId);
          
          fetch('ajax/wishlist.php', {
              method: 'POST',
              body: formData,
              credentials: 'same-origin'
          })
          .then(response => response.json())
          .then(data => {
              if (data.success) {
                  // Update button appearance
                  updateWishlistButton(button, data.status === 'added');
                  
                  // Show toast notification
                  showToast(data.message, data.status === 'added' ? 'success' : 'info');
              } else if (data.redirect) {
                  // Redirect to login page if needed
                  window.location.href = data.redirect + '?redirect=' + encodeURIComponent(window.location.href);
              } else {
                  showToast(data.message || 'Có lỗi xảy ra, vui lòng thử lại sau', 'error');
                  console.error('Wishlist error:', data);
              }
          })
          .catch(error => {
              console.error('Error updating wishlist:', error);
              showToast('Đã xảy ra lỗi khi cập nhật danh sách yêu thích', 'error');
          });
      });
  });
  
  // Function to show toast notifications
  function showToast(message, type = 'info') {
      // Check if toast container exists, if not create it
      let toastContainer = document.querySelector('.toast-container');
      if (!toastContainer) {
          toastContainer = document.createElement('div');
          toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
          document.body.appendChild(toastContainer);
      }
      
      // Create toast element
      const toastEl = document.createElement('div');
      toastEl.className = `toast align-items-center border-0 bg-${type === 'error' ? 'danger' : (type === 'success' ? 'success' : 'info')}`;
      toastEl.setAttribute('role', 'alert');
      toastEl.setAttribute('aria-live', 'assertive');
      toastEl.setAttribute('aria-atomic', 'true');
      
      // Toast content
      toastEl.innerHTML = `
          <div class="d-flex">
              <div class="toast-body text-white">
                  ${message}
              </div>
              <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
          </div>
      `;
      
      // Add toast to container
      toastContainer.appendChild(toastEl);
      
      // Initialize Bootstrap toast
      const toast = new bootstrap.Toast(toastEl, {
          delay: 3000
      });
      
      // Show toast
      toast.show();
      
      // Remove toast element after it's hidden
      toastEl.addEventListener('hidden.bs.toast', function() {
          toastEl.remove();
      });
  }
  
  // Check wishlist status when page loads
  checkWishlistStatus();

  // Khởi tạo carousel hero banner
  if (typeof bootstrap !== 'undefined') {
      var myCarousel = document.getElementById('heroCarousel');
      if (myCarousel) {
          var carousel = new bootstrap.Carousel(myCarousel, {
              interval: 5000,
              wrap: true
          });
          
          // Khởi động carousel
          carousel.cycle();
      }
  }
});

// Toast Notification Styles
