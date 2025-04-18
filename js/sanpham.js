document.querySelectorAll(".add-to-cart").forEach((button) => {
  button.addEventListener("click", function (e) {
    e.preventDefault();
    const productId = this.getAttribute("data-id");
    const button = this; // Lưu tham chiếu đến nút

    // Thay đổi trạng thái nút
    button.innerHTML =
      '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Đang thêm...';
    button.disabled = true;

    // Fix: Use JSON format instead of form data
    fetch("ajax/them_vao_gio.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        productId: parseInt(productId),
        quantity: 1,
        sizeId: null,
        colorId: null,
      }),
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
              ${
                data.message ||
                (data.success
                  ? "Đã thêm sản phẩm vào giỏ hàng!"
                  : "Có lỗi xảy ra khi thêm vào giỏ hàng")
              }
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
          </div>
        `;

        document.body.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();

        // Update cart count if needed
        if (data.success && data.cartCount) {
          const cartCountElement = document.getElementById("cartCount");
          if (cartCountElement) {
            cartCountElement.textContent = data.cartCount;
          }
        }

        // Restore button state
        button.innerHTML = '<i class="bi bi-cart-plus"></i> Thêm vào giỏ';
        button.disabled = false;
      })
      .catch((error) => {
        console.error("Error:", error);
        button.innerHTML = '<i class="bi bi-cart-plus"></i> Thêm vào giỏ';
        button.disabled = false;
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

document.addEventListener("DOMContentLoaded", function () {
  // Đồng bộ hóa các dropdown sắp xếp
  const sortSelect = document.getElementById("sort-select");
  const sortHidden = document.getElementById("sort-hidden");
  const sortMobile = document.getElementById("sort-mobile");

  // Xử lý sự kiện thay đổi trên desktop dropdown
  if (sortSelect) {
    sortSelect.addEventListener("change", function () {
      sortHidden.value = this.value;
      if (sortMobile) sortMobile.value = this.value;
      document.getElementById("filter-form").submit();
    });
  }

  // Xử lý sự kiện thay đổi trên mobile dropdown
  if (sortMobile) {
    sortMobile.addEventListener("change", function () {
      sortHidden.value = this.value;
      if (sortSelect) sortSelect.value = this.value;
    });
  }

  // Xử lý chuyển đổi kiểu hiển thị (Grid/List)
  const viewButtons = document.querySelectorAll(".view-btn");
  const productsContainer = document.getElementById("products-container");

  if (viewButtons.length > 0 && productsContainer) {
    viewButtons.forEach((button) => {
      button.addEventListener("click", function () {
        // Xóa class active từ tất cả các nút
        viewButtons.forEach((btn) => btn.classList.remove("active"));
        // Thêm class active vào nút được nhấp
        this.classList.add("active");

        // Thay đổi kiểu hiển thị
        const viewType = this.getAttribute("data-view");
        productsContainer.className = `row g-3 ${viewType}-view`;

        // Lưu kiểu hiển thị vào localStorage
        localStorage.setItem("product_view_type", viewType);
      });
    });

    // Khôi phục kiểu hiển thị từ localStorage
    const savedViewType = localStorage.getItem("product_view_type");
    if (savedViewType) {
      const targetButton = document.querySelector(
        `.view-btn[data-view="${savedViewType}"]`
      );
      if (targetButton) {
        targetButton.click();
      }
    }
  }

  // Xử lý nút hiện/ẩn bộ lọc trên mobile
  const filterToggleBtn = document.querySelector(".filter-toggle button");
  const filtersContainer = document.querySelector(".filters-container");

  if (filterToggleBtn && filtersContainer) {
    filterToggleBtn.addEventListener("click", function () {
      const isCollapsed = filtersContainer.classList.contains("show");

      if (isCollapsed) {
        filtersContainer.classList.remove("show");
        this.innerHTML = '<i class="bi bi-funnel me-2"></i> Hiển thị bộ lọc';
      } else {
        filtersContainer.classList.add("show");
        this.innerHTML = '<i class="bi bi-x-lg me-2"></i> Ẩn bộ lọc';
      }
    });
  }

  // Xử lý nút thêm vào wishlist
  const wishlistButtons = document.querySelectorAll(".add-to-wishlist");

  if (wishlistButtons) {
    wishlistButtons.forEach((button) => {
      button.addEventListener("click", function () {
        const productId = this.getAttribute("data-id");

        // Kiểm tra nếu người dùng đã đăng nhập (qua AJAX)
        fetch("ajax/check-login.php")
          .then((response) => response.json())
          .then((data) => {
            if (data.logged_in) {
              // Người dùng đã đăng nhập, thêm vào wishlist
              addToWishlist(productId, this);
            } else {
              // Người dùng chưa đăng nhập, chuyển hướng đến trang đăng nhập
              window.location.href =
                "dangnhap.php?redirect=" +
                encodeURIComponent(window.location.href);
            }
          })
          .catch((error) => console.error("Lỗi:", error));
      });
    });
  }

  // Hàm thêm sản phẩm vào wishlist
  function addToWishlist(productId, buttonElement) {
    fetch("ajax/add-to-wishlist.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: "id=" + productId,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          // Cập nhật UI
          buttonElement.classList.add("active");
          buttonElement.querySelector("i").className = "bi bi-heart-fill";

          // Hiển thị thông báo thành công
          showToast("Đã thêm sản phẩm vào danh sách yêu thích!", "success");
        } else {
          // Hiển thị thông báo lỗi
          showToast(data.message, "danger");
        }
      })
      .catch((error) => {
        console.error("Lỗi:", error);
        showToast("Đã xảy ra lỗi khi thêm sản phẩm vào wishlist", "danger");
      });
  }

  // Xử lý nút xem nhanh (Quick view)
  const quickViewButtons = document.querySelectorAll(".quick-view-btn");
  const quickViewModal = document.getElementById("quickViewModal");
  const quickViewContent = document.getElementById("quickViewContent");
  const quickViewSpinner = document.querySelector(
    "#quickViewModal .spinner-container"
  );

  if (quickViewButtons && quickViewModal && quickViewContent) {
    const modalInstance = new bootstrap.Modal(quickViewModal);

    quickViewButtons.forEach((button) => {
      button.addEventListener("click", function () {
        const productId = this.getAttribute("data-id");

        // Hiển thị modal và spinner
        modalInstance.show();
        quickViewSpinner.style.display = "flex";
        quickViewContent.style.display = "none";

        // Tải dữ liệu sản phẩm
        fetch("ajax/quick-view.php?id=" + productId)
          .then((response) => response.text())
          .then((html) => {
            // Cập nhật nội dung và ẩn spinner
            quickViewContent.innerHTML = html;
            quickViewSpinner.style.display = "none";
            quickViewContent.style.display = "block";
          })
          .catch((error) => {
            console.error("Lỗi:", error);
            quickViewContent.innerHTML =
              '<div class="alert alert-danger">Đã xảy ra lỗi khi tải thông tin sản phẩm</div>';
            quickViewSpinner.style.display = "none";
            quickViewContent.style.display = "block";
          });
      });
    });
  }

  // Hàm hiển thị toast notification
  function showToast(message, type = "info") {
    // Tạo container nếu chưa tồn tại
    let toastContainer = document.querySelector(".toast-container");
    if (!toastContainer) {
      toastContainer = document.createElement("div");
      toastContainer.className =
        "toast-container position-fixed bottom-0 end-0 p-3";
      document.body.appendChild(toastContainer);
    }

    // Tạo ID duy nhất cho toast
    const toastId = "toast-" + Date.now();

    // Tạo HTML cho toast
    const toastHtml = `
            <div id="${toastId}" class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;

    // Thêm toast vào container
    toastContainer.insertAdjacentHTML("beforeend", toastHtml);

    // Khởi tạo và hiển thị toast
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, {
      autohide: true,
      delay: 3000,
    });
    toast.show();

    // Xóa toast khi ẩn
    toastElement.addEventListener("hidden.bs.toast", function () {
      toastElement.remove();
    });
  }
});
