/**
 * Xử lý chức năng Yêu thích sản phẩm
 */
console.log("Wishlist.js loaded - đang khởi tạo"); // Debug line

document.addEventListener("DOMContentLoaded", function () {
  console.log("DOM Content Loaded - bắt đầu khởi tạo nút yêu thích"); // Debug line

  // Kiểm tra các nút wishlist
  const wishlistButtons = document.querySelectorAll(".wishlist-button");
  console.log("Tìm thấy", wishlistButtons.length, "nút yêu thích"); // Debug line

  // Log ra một số nút (nếu có) để kiểm tra chi tiết
  if (wishlistButtons.length > 0) {
    console.log("Chi tiết nút đầu tiên:", wishlistButtons[0].outerHTML);
  }

  // Khởi tạo các nút wishlist
  initWishlistButtons();

  // Kiểm tra các sản phẩm đã yêu thích
  checkWishlistItems();
});

// Thêm vào ngay sau khi document ready
document.addEventListener("click", function (e) {
  if (e.target.closest(".wishlist-button")) {
    console.log(
      "Click đã được bắt trên nút:",
      e.target.closest(".wishlist-button")
    );
    console.log(
      "Product ID:",
      e.target.closest(".wishlist-button").dataset.productId
    );
  }
});

/**
 * Khởi tạo các sự kiện click cho nút yêu thích
 */
function initWishlistButtons() {
  const wishlistButtons = document.querySelectorAll(".wishlist-button");
  console.log(
    "Đang khởi tạo sự kiện click cho",
    wishlistButtons.length,
    "nút yêu thích"
  );

  wishlistButtons.forEach((button) => {
    // Xóa event handler cũ nếu có để tránh duplicate
    button.removeEventListener("click", wishlistClickHandler);

    // Thêm event handler mới
    button.addEventListener("click", wishlistClickHandler);
  });
}

// Hàm xử lý click riêng biệt để dễ debug
function wishlistClickHandler(e) {
  console.log("Đã click nút yêu thích!");
  e.preventDefault();
  e.stopPropagation();

  // Sử dụng this thay vì e.currentTarget để đảm bảo tham chiếu đúng đến button
  const productId = this.getAttribute("data-product-id");
  console.log("Product ID:", productId);

  if (!productId) {
    console.error("Không tìm thấy data-product-id trên nút yêu thích");
    return;
  }

  toggleWishlistItem(productId, this);
}

/**
 * Toggle sản phẩm trong danh sách yêu thích
 */
function toggleWishlistItem(productId, buttonElement) {
  console.log("Thực hiện toggle wishlist cho sản phẩm ID:", productId);

  // Tạo form data
  const formData = new FormData();
  formData.append("product_id", productId);

  // Hiển thị trạng thái loading
  buttonElement.classList.add("loading");
  buttonElement.disabled = true;

  // Gửi request đến server với đường dẫn tuyệt đối
  fetch(window.location.origin + "/bug_shop/ajax/toggle_wishlist.php", {
    method: "POST",
    body: formData,
    credentials: "same-origin",
  })
    .then((response) => {
      console.log("Response status:", response.status);
      return response.json();
    })
    .then((data) => {
      // Reset trạng thái button
      buttonElement.classList.remove("loading");
      buttonElement.disabled = false;

      if (data.success) {
        // Cập nhật giao diện button
        updateWishlistButtonUI(buttonElement, data.is_in_wishlist);

        // Hiển thị thông báo
        showToast(data.message, data.is_in_wishlist ? "success" : "info");
      } else if (data.message === "login_required") {
        // Chuyển đến trang đăng nhập
        window.location.href =
          "dangnhap.php?redirect=" + encodeURIComponent(window.location.href);
      } else {
        // Hiển thị lỗi
        showToast(data.message, "error");
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      buttonElement.classList.remove("loading");
      buttonElement.disabled = false;
      showToast("Có lỗi xảy ra, vui lòng thử lại sau", "error");
    });
}

/**
 * Cập nhật UI của nút yêu thích
 */
function updateWishlistButtonUI(button, isInWishlist) {
  // Tìm icon trong button
  let icon = button.querySelector("i") || button;

  if (isInWishlist) {
    icon.classList.remove("bi-heart");
    icon.classList.add("bi-heart-fill");
    button.classList.add("active");
    button.setAttribute("title", "Đã yêu thích");
  } else {
    icon.classList.remove("bi-heart-fill");
    icon.classList.add("bi-heart");
    button.classList.remove("active");
    button.setAttribute("title", "Thêm vào yêu thích");
  }
}

/**
 * Kiểm tra các sản phẩm đã nằm trong danh sách yêu thích
 */
function checkWishlistItems() {
  const wishlistButtons = document.querySelectorAll(".wishlist-button");
  if (wishlistButtons.length === 0) return;

  console.log(
    "Checking wishlist items for " + wishlistButtons.length + " products"
  );

  // Lấy tất cả ID sản phẩm
  const productIds = Array.from(wishlistButtons).map(
    (button) => button.dataset.productId
  );

  // Tạo form data
  const formData = new FormData();
  formData.append("product_ids", JSON.stringify(productIds));

  console.log("Sending product IDs:", productIds);

  // Gửi request đến server
  fetch("ajax/check_wishlist.php", {
    method: "POST",
    body: formData,
    credentials: "same-origin",
  })
    .then((response) => {
      console.log("Response status:", response.status);
      return response.json();
    })
    .then((data) => {
      console.log("Check wishlist response:", data);
      if (data.success) {
        // Cập nhật UI các nút yêu thích
        wishlistButtons.forEach((button) => {
          const productId = parseInt(button.dataset.productId);
          const isInWishlist = data.in_wishlist.includes(productId);
          updateWishlistButtonUI(button, isInWishlist);
        });
      }
    })
    .catch((error) => {
      console.error("Error checking wishlist items:", error);
    });
}

/**
 * Hiển thị thông báo toast
 */
function showToast(message, type = "info") {
  // Kiểm tra xem có container toast chưa, nếu chưa thì tạo mới
  let toastContainer = document.querySelector(".toast-container");
  if (!toastContainer) {
    toastContainer = document.createElement("div");
    toastContainer.className =
      "toast-container position-fixed bottom-0 end-0 p-3";
    document.body.appendChild(toastContainer);
  }

  // Tạo ID duy nhất cho toast
  const toastId = "toast-" + Date.now();

  // Xác định class và icon dựa vào loại toast
  let bgClass, iconClass;
  switch (type) {
    case "success":
      bgClass = "bg-success text-white";
      iconClass = "bi-check-circle me-2";
      break;
    case "error":
      bgClass = "bg-danger text-white";
      iconClass = "bi-exclamation-circle me-2";
      break;
    case "warning":
      bgClass = "bg-warning";
      iconClass = "bi-exclamation-triangle me-2";
      break;
    default:
      bgClass = "bg-info text-white";
      iconClass = "bi-info-circle me-2";
  }

  // Tạo HTML cho toast
  const toastHtml = `
        <div id="${toastId}" class="toast ${bgClass}" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <i class="bi ${iconClass}"></i>
                <strong class="me-auto">Thông báo</strong>
                <small>Bây giờ</small>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                ${message}
            </div>
        </div>
    `;

  // Thêm toast vào container
  toastContainer.innerHTML += toastHtml;

  // Khởi tạo và hiển thị toast
  const toastElement = document.getElementById(toastId);
  const toast = new bootstrap.Toast(toastElement, {
    animation: true,
    autohide: true,
    delay: 3000,
  });
  toast.show();

  // Xóa toast sau khi đóng
  toastElement.addEventListener("hidden.bs.toast", function () {
    toastElement.remove();
  });
}
