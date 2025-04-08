document.querySelectorAll(".add-to-cart").forEach((button) => {
  button.addEventListener("click", function (e) {
    e.preventDefault();
    const productId = this.getAttribute("data-id");
    const button = this; // Lưu tham chiếu đến nút

    // Thay đổi trạng thái nút
    button.innerHTML =
      '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Đang thêm...';
    button.disabled = true;

    // Sửa đường dẫn AJAX thành ajax/them_vao_gio.php
    fetch("ajax/them_vao_gio.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: "product_id=" + productId + "&quantity=1",
    })
      .then((response) => response.json())
      .then((data) => {
        // Hiển thị thông báo
        const toast = document.createElement("div");
        toast.className = `toast align-items-center ${
          data.success ? "text-white bg-success" : "text-white bg-danger"
        } position-fixed bottom-0 end-0 m-3`;
        toast.setAttribute("role", "alert");
        toast.setAttribute("aria-live", "assertive");
        toast.setAttribute("aria-atomic", "true");
        toast.innerHTML = `
                        <div class="d-flex">
                            <div class="toast-body">
                                ${data.message}
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                    `;
        document.body.appendChild(toast);

        // Kích hoạt toast
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();

        // Cập nhật số lượng giỏ hàng trong header
        if (data.success && data.cart_count) {
          const cartBadges = document.querySelectorAll(".cart-count");
          cartBadges.forEach((badge) => {
            badge.textContent = data.cart_count;
            badge.style.display = "inline-block";
          });
        }

        // Khôi phục nút
        button.innerHTML = '<i class="bi bi-cart-plus"></i> Thêm vào giỏ';
        button.disabled = false;
      })
      .catch((error) => {
        console.error("Error:", error);
        // Khôi phục nút trong trường hợp lỗi
        button.innerHTML = '<i class="bi bi-cart-plus"></i> Thêm vào giỏ';
        button.disabled = false;

        // Hiển thị thông báo lỗi
        const toast = document.createElement("div");
        toast.className =
          "toast align-items-center text-white bg-danger position-fixed bottom-0 end-0 m-3";
        toast.setAttribute("role", "alert");
        toast.setAttribute("aria-live", "assertive");
        toast.setAttribute("aria-atomic", "true");
        toast.innerHTML = `
                        <div class="d-flex">
                            <div class="toast-body">
                                Có lỗi xảy ra khi thêm sản phẩm vào giỏ hàng.
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                    `;
        document.body.appendChild(toast);

        // Kích hoạt toast
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
      });
  });
});

// Thêm hiệu ứng hover cho sản phẩm có rating cao
document.addEventListener("DOMContentLoaded", function () {
  const productCards = document.querySelectorAll(".product-card");

  productCards.forEach((card) => {
    const ratingElement = card.querySelector(".rating");
    if (ratingElement) {
      const starsFilled =
        ratingElement.querySelectorAll(".bi-star-fill").length;
      const starsHalf = ratingElement.querySelectorAll(".bi-star-half").length;
      const rating = starsFilled + starsHalf * 0.5;

      // Nếu rating cao (>= 4.5), thêm hiệu ứng đặc biệt
      if (rating >= 4.5) {
        card.classList.add("border-warning");
        const badge = document.createElement("span");
        badge.className =
          "position-absolute top-0 start-0 translate-middle badge rounded-pill bg-warning text-dark";
        badge.style.left = "15%";
        badge.style.top = "5%";
        badge.innerHTML = '<i class="bi bi-trophy"></i> Top rated';
        card.querySelector(".card").appendChild(badge);
      }
    }
  });
});

document.addEventListener("DOMContentLoaded", function () {
  const searchInput = document.querySelector(".search-input");
  const searchResults = document.createElement("div");
  searchResults.className = "search-results-dropdown";

  // Thêm khung kết quả vào DOM
  document.querySelector(".search-form").appendChild(searchResults);

  // Xử lý khi người dùng nhập từ khóa
  let timer;
  searchInput.addEventListener("input", function () {
    clearTimeout(timer);
    const keyword = this.value.trim();

    // Ẩn kết quả nếu không có từ khóa
    if (keyword.length < 2) {
      searchResults.style.display = "none";
      return;
    }

    // Đợi 300ms để tránh gọi API quá nhiều
    timer = setTimeout(() => {
      fetchSearchResults(keyword);
    }, 300);
  });

  // Đóng kết quả tìm kiếm khi click ra ngoài
  document.addEventListener("click", function (e) {
    if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
      searchResults.style.display = "none";
    }
  });

  // Hàm gọi AJAX để lấy kết quả tìm kiếm
  function fetchSearchResults(keyword) {
    fetch(`ajax/quick_search.php?keyword=${encodeURIComponent(keyword)}`)
      .then((response) => response.json())
      .then((data) => {
        displayResults(data, keyword);
      })
      .catch((error) => {
        console.error("Lỗi tìm kiếm:", error);
      });
  }

  // Hiển thị kết quả tìm kiếm
  function displayResults(data, keyword) {
    if (data.length === 0) {
      searchResults.innerHTML = `<div class="no-results">Không tìm thấy sản phẩm cho "${keyword}"</div>`;
      searchResults.style.display = "block";
      return;
    }

    let html = "";
    data.forEach((product) => {
      html += `
                <a href="product-detail.php?id=${
                  product.id_sanpham
                }" class="search-item">
                    <div class="search-item-image">
                        <img src="${
                          product.hinhanh
                            ? "uploads/products/" + product.hinhanh
                            : "images/no-image.png"
                        }" alt="${product.tensanpham}">
                    </div>
                    <div class="search-item-info">
                        <div class="search-item-title">${
                          product.tensanpham
                        }</div>
                        <div class="search-item-price">${new Intl.NumberFormat(
                          "vi-VN"
                        ).format(product.gia)} ₫</div>
                    </div>
                </a>
            `;
    });

    html += `<div class="view-all"><a href="sanpham.php?search=${encodeURIComponent(
      keyword
    )}">Xem tất cả kết quả</a></div>`;

    searchResults.innerHTML = html;
    searchResults.style.display = "block";
  }
});
