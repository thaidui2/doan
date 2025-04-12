document.addEventListener("DOMContentLoaded", function () {
  // Hiển thị modal thêm màu mới
  const addColorButton = document.getElementById("addColorButton");
  const addColorModal = new bootstrap.Modal(
    document.getElementById("addColorModal")
  );

  if (addColorButton) {
    addColorButton.addEventListener("click", function () {
      addColorModal.show();
    });
  }

  // Xử lý thêm màu mới
  const saveNewColorButton = document.getElementById("saveNewColor");
  if (saveNewColorButton) {
    saveNewColorButton.addEventListener("click", function () {
      const colorName = document.getElementById("newColorName").value.trim();
      const colorCode = document.getElementById("newColorCode").value;

      if (!colorName) {
        alert("Vui lòng nhập tên màu");
        return;
      }

      // Hiển thị thông báo đang xử lý
      saveNewColorButton.disabled = true;
      saveNewColorButton.innerHTML =
        '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Đang xử lý...';

      // Gửi AJAX request để lưu màu mới
      fetch("them-mau-moi.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body:
          "name=" +
          encodeURIComponent(colorName) +
          "&code=" +
          encodeURIComponent(colorCode),
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            // Thêm màu mới vào danh sách
            const colorsContainer = document.querySelector(
              ".border.p-3.rounded.mb-3 .row"
            );
            const newColorHTML = `
                        <div class="col-md-4 col-6 mb-2">
                            <div class="form-check d-flex align-items-center">
                                <input class="form-check-input" type="checkbox" name="colors[]" 
                                       value="${data.id}" 
                                       id="color_${data.id}" checked>
                                <label class="form-check-label d-flex align-items-center ms-2" for="color_${data.id}">
                                    <span class="color-preview me-2" 
                                          style="width: 18px; height: 18px; background-color: ${colorCode}; display: inline-block; border-radius: 3px; border: 1px solid #ddd;"></span>
                                    ${colorName}
                                </label>
                            </div>
                        </div>
                    `;
            colorsContainer.insertAdjacentHTML("beforeend", newColorHTML);

            // Đóng modal và reset form
            addColorModal.hide();
            document.getElementById("addColorForm").reset();

            // Cập nhật lại bảng tồn kho
            updateInventoryTable();

            // Thông báo thành công
            alert("Thêm màu mới thành công!");
          } else {
            alert("Lỗi: " + data.message);
          }
        })
        .catch((error) => {
          console.error("Error:", error);
          alert("Có lỗi xảy ra khi xử lý yêu cầu");
        })
        .finally(() => {
          // Khôi phục nút
          saveNewColorButton.disabled = false;
          saveNewColorButton.innerHTML = "Thêm màu";
        });
    });
  }

  // Cập nhật bảng tồn kho khi chọn/bỏ chọn màu sắc hoặc kích thước
  function updateInventoryTable() {
    const selectedSizes = Array.from(
      document.querySelectorAll('input[name="sizes[]"]:checked')
    ).map((el) => {
      return {
        id: el.value,
        name: el.nextElementSibling.textContent.trim(),
      };
    });

    const selectedColors = Array.from(
      document.querySelectorAll('input[name="colors[]"]:checked')
    ).map((el) => {
      const colorLabel = el.nextElementSibling;
      const colorPreview = colorLabel.querySelector(".color-preview");
      return {
        id: el.value,
        name: colorLabel.textContent.trim(),
        code: colorPreview.style.backgroundColor,
      };
    });

    const tableBody = document.getElementById("variantsTableBody");
    const variantsContainer = document.getElementById("variantsContainer");
    const noVariantsMessage = document.getElementById("noVariantsMessage");

    // Hiển thị thông báo nếu không có biến thể
    if (selectedSizes.length === 0 || selectedColors.length === 0) {
      if (variantsContainer) variantsContainer.style.display = "none";
      if (noVariantsMessage) noVariantsMessage.style.display = "block";
      return;
    }

    // Hiển thị bảng biến thể
    if (variantsContainer) variantsContainer.style.display = "";
    if (noVariantsMessage) noVariantsMessage.style.display = "none";

    if (!tableBody) return;

    // Xóa các hàng cũ
    tableBody.innerHTML = "";

    // Thêm hàng mới cho mỗi biến thể
    selectedSizes.forEach((size) => {
      selectedColors.forEach((color) => {
        const row = document.createElement("tr");

        // Lấy giá trị số lượng hiện tại (nếu có)
        let currentQuantity = 0;
        const existingInput = document.querySelector(
          `input[name="inventory[${size.id}][${color.id}]"]`
        );
        if (existingInput) {
          currentQuantity = existingInput.value;
        }

        row.innerHTML = `
                    <td>${size.name}</td>
                    <td>
                        <div class="d-flex align-items-center">
                            <span class="color-preview me-2" style="width: 18px; height: 18px; background-color: ${color.code}; display: inline-block; border-radius: 3px; border: 1px solid #ddd;"></span>
                            ${color.name}
                        </div>
                    </td>
                    <td>
                        <input type="number" class="form-control inventory-input" name="inventory[${size.id}][${color.id}]" value="${currentQuantity}" min="0" required>
                    </td>
                `;

        tableBody.appendChild(row);
      });
    });
  }

  // Gán sự kiện cho các checkbox kích thước và màu sắc
  document
    .querySelectorAll('input[name="sizes[]"], input[name="colors[]"]')
    .forEach((checkbox) => {
      checkbox.addEventListener("change", updateInventoryTable);
    });

  // Chạy hàm updateInventoryTable lần đầu để khởi tạo bảng
  updateInventoryTable();
});
