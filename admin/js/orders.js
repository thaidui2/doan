document.addEventListener("DOMContentLoaded", function () {
  // Xử lý cập nhật trạng thái đơn hàng
  const updateStatusButtons = document.querySelectorAll(".update-status");
  updateStatusButtons.forEach((button) => {
    button.addEventListener("click", function (e) {
      e.preventDefault();

      const orderId = this.getAttribute("data-order-id");
      const statusId = this.getAttribute("data-status");
      const statusName = this.textContent.trim();

      if (
        confirm(
          `Bạn có chắc chắn muốn chuyển trạng thái đơn hàng #${orderId} thành "${statusName}"?`
        )
      ) {
        updateOrderStatus(orderId, statusId);
      }
    });
  });

  // Xử lý tìm kiếm đơn hàng
  const searchForm = document.getElementById("orderSearchForm");
  if (searchForm) {
    searchForm.addEventListener("submit", function (e) {
      // Loại bỏ các tham số trống trong form trước khi submit
      const inputs = searchForm.querySelectorAll("input, select");
      inputs.forEach((input) => {
        if (input.value === "" || input.value === "0") {
          input.disabled = true;
        }
      });
    });
  }

  // Xử lý xem chi tiết nhanh đơn hàng
  const quickViewButtons = document.querySelectorAll(".quick-view-order");
  if (quickViewButtons.length > 0) {
    quickViewButtons.forEach((button) => {
      button.addEventListener("click", function () {
        const orderId = this.getAttribute("data-order-id");
        openQuickViewModal(orderId);
      });
    });
  }

  // Xử lý xem lịch sử đơn hàng
  const viewHistoryButtons = document.querySelectorAll(".view-order-history");
  if (viewHistoryButtons.length > 0) {
    viewHistoryButtons.forEach((button) => {
      button.addEventListener("click", function () {
        const orderId = this.getAttribute("data-order-id");
        openHistoryModal(orderId);
      });
    });
  }

  // Hàm cập nhật trạng thái đơn hàng qua AJAX
  function updateOrderStatus(orderId, statusId) {
    // Hiển thị spinner
    const loadingOverlay = document.createElement("div");
    loadingOverlay.className = "loading-overlay";
    loadingOverlay.innerHTML = `
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Đang xử lý...</span>
            </div>
        `;
    document.body.appendChild(loadingOverlay);

    fetch("ajax/update-order.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: `order_id=${orderId}&status=${statusId}`,
    })
      .then((response) => response.json())
      .then((data) => {
        // Xóa loading overlay
        document.body.removeChild(loadingOverlay);

        if (data.success) {
          showToast("Cập nhật trạng thái thành công!", "success");

          // Reload trang sau 1 giây để hiển thị dữ liệu mới
          setTimeout(() => {
            window.location.reload();
          }, 1000);
        } else {
          showToast(`Lỗi: ${data.message}`, "danger");
        }
      })
      .catch((error) => {
        // Xóa loading overlay
        document.body.removeChild(loadingOverlay);

        console.error("Error:", error);
        showToast("Đã xảy ra lỗi khi cập nhật trạng thái đơn hàng!", "danger");
      });
  }

  // Hàm mở modal xem nhanh đơn hàng
  function openQuickViewModal(orderId) {
    const modalElement = document.getElementById("quickViewOrderModal");
    const modal = new bootstrap.Modal(modalElement);
    const modalContent = document.getElementById("quickViewOrderContent");

    // Reset content và hiển thị loading
    modalContent.innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Đang tải...</span>
                </div>
                <p class="mt-2">Đang tải thông tin đơn hàng...</p>
            </div>
        `;

    modal.show();

    // Lấy dữ liệu đơn hàng qua AJAX
    fetch(`ajax/get-order-data.php?id=${orderId}`)
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          renderOrderDetails(data, modalContent);
        } else {
          modalContent.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            ${
                              data.message || "Không thể tải thông tin đơn hàng"
                            }
                        </div>
                    `;
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        modalContent.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        Đã xảy ra lỗi khi tải thông tin đơn hàng
                    </div>
                `;
      });
  }

  // Hàm render chi tiết đơn hàng trong modal
  function renderOrderDetails(data, container) {
    const order = data.order;
    const items = data.items;
    const statusLabel = data.status_label;
    const statusBadge = data.status_badge;

    let itemsHtml = "";
    let totalAmount = 0;

    items.forEach((item, index) => {
      totalAmount += parseFloat(item.thanh_tien);
      itemsHtml += `
                <tr>
                    <td>${index + 1}</td>
                    <td>
                        <img src="${
                          item.image_url
                        }" class="img-thumbnail" width="50" height="50" alt="${
        item.tensanpham || "Sản phẩm"
      }">
                    </td>
                    <td>
                        <div class="fw-bold">${
                          item.tensanpham || "Sản phẩm không tồn tại"
                        }</div>
                        <div class="small">
                            ${
                              item.tenkichthuoc
                                ? `<span class="me-2">Size: ${item.tenkichthuoc}</span>`
                                : ""
                            }
                            ${
                              item.tenmau
                                ? `<span>Màu: ${item.tenmau}</span>`
                                : ""
                            }
                        </div>
                    </td>
                    <td>${formatCurrency(item.gia)}</td>
                    <td>${item.soluong}</td>
                    <td>${formatCurrency(item.thanh_tien)}</td>
                </tr>
            `;
    });

    const orderDate = new Date(order.ngaytao).toLocaleDateString("vi-VN", {
      day: "2-digit",
      month: "2-digit",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    });

    const updateDate = new Date(order.ngaycapnhat).toLocaleDateString("vi-VN", {
      day: "2-digit",
      month: "2-digit",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    });

    // Map payment method to readable text
    let paymentMethod = "Không xác định";
    switch (order.phuongthucthanhtoan) {
      case "cod":
        paymentMethod = "Tiền mặt khi nhận hàng (COD)";
        break;
      case "bank_transfer":
        paymentMethod = "Chuyển khoản ngân hàng";
        break;
      case "momo":
        paymentMethod = "Ví MoMo";
        break;
      case "vnpay":
        paymentMethod = "VNPay";
        break;
      default:
        paymentMethod = order.phuongthucthanhtoan;
    }

    // Build address
    const addressParts = [];
    if (order.diachi) addressParts.push(order.diachi);
    if (order.phuong_xa) addressParts.push(order.phuong_xa);
    if (order.quan_huyen) addressParts.push(order.quan_huyen);
    if (order.tinh_tp) addressParts.push(order.tinh_tp);
    const fullAddress = addressParts.join(", ");

    container.innerHTML = `
            <div class="row mb-4">
                <div class="col-md-6">
                    <h5 class="border-bottom pb-2 mb-3">Thông tin đơn hàng</h5>
                    <div class="row mb-2">
                        <div class="col-5 fw-bold">Mã đơn hàng:</div>
                        <div class="col-7">#${order.id_donhang}</div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-5 fw-bold">Trạng thái:</div>
                        <div class="col-7">
                            <span class="badge bg-${statusBadge}">${statusLabel}</span>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-5 fw-bold">Ngày đặt hàng:</div>
                        <div class="col-7">${orderDate}</div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-5 fw-bold">Cập nhật lần cuối:</div>
                        <div class="col-7">${updateDate}</div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-5 fw-bold">Phương thức thanh toán:</div>
                        <div class="col-7">${paymentMethod}</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <h5 class="border-bottom pb-2 mb-3">Thông tin khách hàng</h5>
                    <div class="row mb-2">
                        <div class="col-5 fw-bold">Tên khách hàng:</div>
                        <div class="col-7">${order.tennguoinhan}</div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-5 fw-bold">Số điện thoại:</div>
                        <div class="col-7">${order.sodienthoai}</div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-5 fw-bold">Email:</div>
                        <div class="col-7">${
                          order.email || '<em class="text-muted">Không có</em>'
                        }</div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-5 fw-bold">Địa chỉ:</div>
                        <div class="col-7">${fullAddress}</div>
                    </div>
                </div>
            </div>
            
            <h5 class="border-bottom pb-2 mb-3">Sản phẩm đặt mua</h5>
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>STT</th>
                            <th>Hình ảnh</th>
                            <th>Sản phẩm</th>
                            <th>Đơn giá</th>
                            <th>Số lượng</th>
                            <th>Thành tiền</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${itemsHtml}
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4"></td>
                            <td class="fw-bold">Tổng cộng:</td>
                            <td>${formatCurrency(totalAmount)}</td>
                        </tr>
                        <tr>
                            <td colspan="4"></td>
                            <td class="fw-bold">Phí vận chuyển:</td>
                            <td>${formatCurrency(order.phivanchuyen)}</td>
                        </tr>
                        <tr>
                            <td colspan="4"></td>
                            <td class="fw-bold">Tổng thanh toán:</td>
                            <td class="fw-bold text-danger">${formatCurrency(
                              order.tongtien
                            )}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            ${
              order.ghichu
                ? `
                <div class="mt-3">
                    <h5 class="border-bottom pb-2 mb-3">Ghi chú</h5>
                    <p class="fst-italic">${order.ghichu.replace(
                      /\n/g,
                      "<br>"
                    )}</p>
                </div>
            `
                : ""
            }
            
            <div class="mt-4 d-flex justify-content-end">
                <a href="order-detail.php?id=${
                  order.id_donhang
                }" class="btn btn-primary">
                    <i class="bi bi-eye"></i> Xem chi tiết
                </a>
                <a href="print-order.php?id=${
                  order.id_donhang
                }" target="_blank" class="btn btn-outline-secondary ms-2">
                    <i class="bi bi-printer"></i> In đơn hàng
                </a>
            </div>
        `;
  }

  // Hàm mở modal lịch sử đơn hàng
  function openHistoryModal(orderId) {
    // Implement this if you have order history feature
    console.log("View history for order:", orderId);
  }

  // Hàm hiển thị thông báo toast
  function showToast(message, type = "info") {
    const toastContainer = document.querySelector(".toast-container");
    const toastId = `toast-${Date.now()}`;

    const toastHtml = `
            <div id="${toastId}" class="toast align-items-center text-bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;

    toastContainer.insertAdjacentHTML("beforeend", toastHtml);

    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, {
      autohide: true,
      delay: 3000,
    });
    toast.show();

    toastElement.addEventListener("hidden.bs.toast", function () {
      toastElement.remove();
    });
  }

  // Hàm định dạng tiền tệ
  function formatCurrency(value) {
    return new Intl.NumberFormat("vi-VN", {
      style: "currency",
      currency: "VND",
      maximumFractionDigits: 0,
    }).format(value);
  }
});

// CSS cho overlay loading
document.head.insertAdjacentHTML(
  "beforeend",
  `
<style>
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}
</style>
`
);
