document.addEventListener("DOMContentLoaded", function () {
  const selectAllCheckbox = document.getElementById("select-all");
  const itemCheckboxes = document.querySelectorAll(
    ".item-checkbox:not([disabled])"
  );
  const checkoutSelectedBtn = document.getElementById("checkout-selected-btn");
  const deleteSelectedBtn = document.getElementById("delete-selected-btn");
  const selectedCountEl = document.getElementById("selected-count");
  const selectedCountDeleteEl = document.getElementById(
    "selected-count-delete"
  );
  const totalFullEl = document.getElementById("total-full");
  const totalSelectedEl = document.getElementById("total-selected");

  // Hàm cập nhật số lượng và giá trị đã chọn
  function updateSelection() {
    const selectedItems = document.querySelectorAll(".item-checkbox:checked");
    const selectedCount = selectedItems.length;

    // Cập nhật số lượng
    selectedCountEl.textContent = selectedCount;
    selectedCountDeleteEl.textContent = selectedCount;

    // Cập nhật trạng thái nút thanh toán đã chọn
    checkoutSelectedBtn.disabled = selectedCount === 0;
    deleteSelectedBtn.disabled = selectedCount === 0;

    // Tính tổng tiền các sản phẩm đã chọn
    let totalSelected = 0;
    selectedItems.forEach((item) => {
      const priceStr = item.getAttribute("data-price");
      const price = parseFloat(priceStr);

      if (!isNaN(price)) {
        totalSelected += price;
      } else {
        console.error("Giá trị không hợp lệ:", priceStr);
      }
    });

    // Cập nhật hiển thị tổng tiền
    if (selectedCount > 0) {
      // Luôn hiển thị tổng tiền đã chọn khi có sản phẩm được chọn
      totalFullEl.classList.add("d-none");
      totalSelectedEl.classList.remove("d-none");
      totalSelectedEl.textContent = formatCurrency(totalSelected) + "₫";
    } else {
      // Khi không có sản phẩm nào được chọn, hiển thị tổng tiền toàn bộ
      totalFullEl.classList.remove("d-none");
      totalSelectedEl.classList.add("d-none");
    }
  }

  // Định dạng số tiền thành chuỗi có dấu phân cách
  function formatCurrency(amount) {
    return new Intl.NumberFormat("vi-VN").format(amount);
  }

  // Xử lý chọn tất cả
  selectAllCheckbox.addEventListener("change", function () {
    itemCheckboxes.forEach((checkbox) => {
      checkbox.checked = this.checked;
    });
    updateSelection();
  });

  // Xử lý khi chọn từng sản phẩm
  itemCheckboxes.forEach((checkbox) => {
    checkbox.addEventListener("change", function () {
      // Kiểm tra nút "Chọn tất cả"
      const allChecked = Array.from(itemCheckboxes).every((cb) => cb.checked);
      selectAllCheckbox.checked = allChecked;

      updateSelection();
    });
  });

  // Xử lý khi submit form với các nút khác nhau
  document.querySelector("form").addEventListener("submit", function (e) {
    if (e.submitter && e.submitter.name === "checkout_selected") {
      const selectedItems = document.querySelectorAll(".item-checkbox:checked");

      if (selectedItems.length === 0) {
        e.preventDefault();
        alert("Vui lòng chọn ít nhất một sản phẩm để thanh toán.");
      }
    } else if (e.submitter && e.submitter.name === "checkout_all") {
      // Khi nhấn "Thanh toán tất cả", tự động chọn tất cả các sản phẩm
      itemCheckboxes.forEach((checkbox) => {
        checkbox.checked = true;
      });
      // Không cần gọi updateSelection() vì form sẽ được submit ngay
    }
  });

  // Xử lý nút xóa nhiều sản phẩm đã chọn
  deleteSelectedBtn.addEventListener("click", function () {
    const selectedItems = document.querySelectorAll(".item-checkbox:checked");

    if (selectedItems.length === 0) {
      alert("Vui lòng chọn ít nhất một sản phẩm để xóa.");
      return;
    }

    if (
      confirm(
        `Bạn có chắc chắn muốn xóa ${selectedItems.length} sản phẩm đã chọn?`
      )
    ) {
      // Tạo form tạm thời để gửi dữ liệu
      const form = document.createElement("form");
      form.method = "post";
      form.action = "giohang.php";

      // Thêm field ẩn cho action xóa
      const actionField = document.createElement("input");
      actionField.type = "hidden";
      actionField.name = "delete_selected";
      actionField.value = "1";
      form.appendChild(actionField);

      // Thêm các sản phẩm đã chọn
      selectedItems.forEach((item) => {
        const hiddenField = document.createElement("input");
        hiddenField.type = "hidden";
        hiddenField.name = "selected_items[]";
        hiddenField.value = item.value;
        form.appendChild(hiddenField);
      });

      // Thêm form vào document và submit
      document.body.appendChild(form);
      form.submit();
    }
  });

  // Hàm hiển thị toast notification
  function showToast(message, type = "success") {
    const toastContainer = document.getElementById("toast-container");

    // Nếu chưa có container, tạo mới
    if (!toastContainer) {
      const container = document.createElement("div");
      container.id = "toast-container";
      container.className = "toast-container position-fixed bottom-0 end-0 p-3";
      document.body.appendChild(container);
    }

    const toastId = "toast-" + Date.now();
    const toastEl = document.createElement("div");
    toastEl.className = `toast align-items-center text-bg-${type} border-0`;
    toastEl.id = toastId;
    toastEl.setAttribute("role", "alert");
    toastEl.setAttribute("aria-live", "assertive");
    toastEl.setAttribute("aria-atomic", "true");

    toastEl.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-${
                      type === "success" ? "check-circle" : "exclamation-circle"
                    }-fill me-2"></i>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        `;

    document.getElementById("toast-container").appendChild(toastEl);

    // Khởi tạo toast
    const toast = new bootstrap.Toast(toastEl, {
      animation: true,
      delay: 3000,
    });
    toast.show();

    // Xóa toast sau khi ẩn
    toastEl.addEventListener("hidden.bs.toast", () => {
      toastEl.remove();
    });
  }

  // Gắn sự kiện xóa cho các nút xóa sản phẩm
  document.querySelectorAll(".remove-item-btn").forEach((button) => {
    button.addEventListener("click", function (e) {
      e.preventDefault();
      const itemId = this.getAttribute("data-item-id");
      removeCartItem(itemId, this);
    });
  });

  // Xử lý nút "Xóa giỏ hàng"
  const clearCartBtn = document.getElementById("clear-cart-btn");
  if (clearCartBtn) {
    clearCartBtn.addEventListener("click", function (e) {
      e.preventDefault();
      if (confirm("Bạn có chắc chắn muốn xóa toàn bộ giỏ hàng?")) {
        clearCart();
      }
    });
  }

  // Hàm xóa toàn bộ giỏ hàng
  function clearCart() {
    fetch(`giohang_ajax.php?action=clear_cart`, {
      method: "GET",
    })
      .then((response) => response.json())
      .then((data) => {
        console.log("Clear cart response:", data);

        if (data.success) {
          showToast("Đã xóa toàn bộ giỏ hàng!", "success");
          // Tải lại trang sau khi xóa thành công
          setTimeout(() => {
            location.reload();
          }, 1000);
        } else {
          showToast(data.message || "Không thể xóa giỏ hàng", "danger");
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        showToast("Đã xảy ra lỗi khi xóa giỏ hàng", "danger");
      });
  }

  // Khởi tạo khi trang tải xong
  updateSelection();
});

// Xử lý xóa sản phẩm bằng Ajax
function removeCartItem(itemId, element) {
  if (!itemId) {
    console.error("Item ID is missing!");
    return false;
  }

  console.log("Removing item: " + itemId);

  if (confirm("Bạn có chắc chắn muốn xóa sản phẩm này?")) {
    // Hiệu ứng fade out cho hàng sản phẩm
    const row = element.closest("tr");
    row.style.transition = "opacity 0.5s";
    row.style.opacity = "0.5";

    console.log("Sending AJAX request to remove item ID: " + itemId);
    fetch(`giohang_ajax.php?action=remove&item_id=${itemId}`, {
      method: "GET",
    })
      .then((response) => {
        console.log("AJAX response status: " + response.status);
        return response.json();
      })
      .then((data) => {
        console.log("AJAX response data:", data);

        if (data.success) {
          // Hiển thị thông báo thành công
          showToast("Đã xóa sản phẩm khỏi giỏ hàng!", "success");

          // Xóa hàng khỏi bảng
          setTimeout(() => {
            row.remove();

            // Cập nhật tổng tiền hiển thị
            const totalFullEl = document.getElementById("total-full");
            if (totalFullEl) {
              totalFullEl.textContent = formatCurrency(data.cart_total) + "₫";
            }

            // Cập nhật số lượng sản phẩm trong giỏ hàng
            if (data.cart_count === 0) {
              // Nếu giỏ hàng trống, tải lại trang
              location.reload();
            } else {
              // Nếu vẫn còn sản phẩm, cập nhật số lượng
              const cartHeader = document.querySelector(".card-header h5");
              if (cartHeader) {
                cartHeader.textContent = `Sản phẩm trong giỏ (${data.cart_count} sản phẩm)`;
              }
            }
          }, 500);
        } else {
          // Hiển thị thông báo lỗi
          showToast(
            data.message || "Có lỗi xảy ra khi xóa sản phẩm.",
            "danger"
          );
          row.style.opacity = "1";
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        showToast("Đã xảy ra lỗi khi xóa sản phẩm.", "danger");
        row.style.opacity = "1";
      });
  }
  return false;
}

// Hàm hiển thị toast notification (cần để sử dụng trong phạm vi toàn cục)
function showToast(message, type = "success") {
  const toastContainer = document.getElementById("toast-container");

  // Nếu chưa có container, tạo mới
  if (!toastContainer) {
    const container = document.createElement("div");
    container.id = "toast-container";
    container.className = "toast-container position-fixed bottom-0 end-0 p-3";
    document.body.appendChild(container);
  }

  const toastId = "toast-" + Date.now();
  const toastEl = document.createElement("div");
  toastEl.className = `toast align-items-center text-bg-${type} border-0`;
  toastEl.id = toastId;
  toastEl.setAttribute("role", "alert");
  toastEl.setAttribute("aria-live", "assertive");
  toastEl.setAttribute("aria-atomic", "true");

  toastEl.innerHTML = `
      <div class="d-flex">
          <div class="toast-body">
              <i class="bi bi-${
                type === "success" ? "check-circle" : "exclamation-circle"
              }-fill me-2"></i>
              ${message}
          </div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
  `;

  document.getElementById("toast-container").appendChild(toastEl);

  // Khởi tạo toast
  const toast = new bootstrap.Toast(toastEl, {
    animation: true,
    delay: 3000,
  });
  toast.show();

  // Xóa toast sau khi ẩn
  toastEl.addEventListener("hidden.bs.toast", () => {
    toastEl.remove();
  });
}

// Định dạng số tiền thành chuỗi có dấu phân cách (để dùng trong phạm vi toàn cục)
function formatCurrency(amount) {
  return new Intl.NumberFormat("vi-VN").format(amount);
}
