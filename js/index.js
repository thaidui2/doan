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
  
document.addEventListener('DOMContentLoaded', function() {
    // Khởi tạo carousel
    var heroCarousel = new bootstrap.Carousel(document.getElementById('heroCarousel'), {
        interval: 5000,
        wrap: true
    });
    
    // Code cho wishlist buttons - giữ nguyên
    // ...existing code...
});
});

// Toast Notification Styles
