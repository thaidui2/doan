/**
 * Module xử lý địa chỉ Việt Nam
 */
document.addEventListener("DOMContentLoaded", function () {
  // Các phần tử DOM
  const provinceSelect = document.getElementById("province");
  const districtSelect = document.getElementById("district");
  const wardSelect = document.getElementById("ward");
  const fullAddressPreview = document.getElementById("full-address-preview");
  const fullAddressText = document.getElementById("full-address-text");
  const addressInput = document.getElementById("address");

  // Dữ liệu đã chọn
  let selectedProvince = { code: "", name: "" };
  let selectedDistrict = { code: "", name: "" };
  let selectedWard = { code: "", name: "" };

  // Biến debug
  const DEBUG = true;

  // Hàm log nâng cao
  function logDebug(message, data) {
    if (DEBUG) {
      console.log(
        `[${new Date().toLocaleTimeString()}] ${message}`,
        data || ""
      );
    }
  }

  // Lấy dữ liệu tỉnh/thành phố
  function loadProvinces() {
    if (!provinceSelect) return;

    provinceSelect.innerHTML =
      '<option value="">Đang tải tỉnh/thành phố...</option>';
    districtSelect.innerHTML = '<option value="">Chọn quận/huyện</option>';
    wardSelect.innerHTML = '<option value="">Chọn phường/xã</option>';
    districtSelect.disabled = true;
    wardSelect.disabled = true;

    // Sử dụng API thay thế ngay từ đầu vì API có vẻ ổn định hơn
    fetch("https://vn-public-apis.fpo.vn/provinces/getAll?limit=-1")
      .then((response) => {
        logDebug("Provinces API Response:", response.status);
        return response.json();
      })
      .then((data) => {
        logDebug("Provinces data structure:", Object.keys(data));

        if (!data.data || !data.data.data) {
          throw new Error("Dữ liệu tỉnh/thành phố không hợp lệ");
        }

        const provinces = data.data.data;
        logDebug(`Đã tải ${provinces.length} tỉnh/thành phố`);

        // Sắp xếp tỉnh/thành phố theo tên
        const sortedProvinces = provinces.sort((a, b) => {
          return a.name.localeCompare(b.name, "vi");
        });

        let options = '<option value="">Chọn tỉnh/thành phố</option>';
        sortedProvinces.forEach((province) => {
          options += `<option value="${province.code}">${province.name}</option>`;
        });

        provinceSelect.innerHTML = options;
      })
      .catch((error) => {
        console.error("Lỗi khi tải tỉnh/thành phố:", error);
        provinceSelect.innerHTML = '<option value="">Lỗi tải dữ liệu</option>';
        showError(
          "Không thể tải danh sách tỉnh/thành phố. Vui lòng thử lại sau hoặc nhập thủ công."
        );
        addManualAddressField();
      });
  }

  // Lấy dữ liệu quận/huyện
  function loadDistricts(provinceCode) {
    if (!districtSelect) return;
    if (!provinceCode) {
      districtSelect.innerHTML = '<option value="">Chọn quận/huyện</option>';
      districtSelect.disabled = true;
      wardSelect.innerHTML = '<option value="">Chọn phường/xã</option>';
      wardSelect.disabled = true;
      return;
    }

    logDebug(
      "Province Code được chọn:",
      provinceCode + " (type: " + typeof provinceCode + ")"
    );

    districtSelect.innerHTML =
      '<option value="">Đang tải quận/huyện...</option>';
    wardSelect.innerHTML = '<option value="">Chọn phường/xã</option>';
    districtSelect.disabled = true;
    wardSelect.disabled = true;

    // Sử dụng API thay thế ngay từ đầu
    fetch(
      `https://vn-public-apis.fpo.vn/districts/getByProvince?provinceCode=${provinceCode}&limit=-1`
    )
      .then((response) => {
        logDebug("Districts API Response:", response.status);
        return response.json();
      })
      .then((data) => {
        logDebug("Districts data structure:", Object.keys(data));

        if (!data.data || !data.data.data) {
          throw new Error("Dữ liệu quận/huyện không hợp lệ");
        }

        const districts = data.data.data;
        logDebug(
          `Đã tải ${districts.length} quận/huyện cho tỉnh ${provinceCode}`
        );

        // Sắp xếp quận/huyện theo tên
        const sortedDistricts = districts.sort((a, b) => {
          return a.name.localeCompare(b.name, "vi");
        });

        let options = '<option value="">Chọn quận/huyện</option>';
        sortedDistricts.forEach((district) => {
          options += `<option value="${district.code}">${district.name}</option>`;
        });

        districtSelect.innerHTML = options;
        districtSelect.disabled = false;
      })
      .catch((error) => {
        console.error("Lỗi khi tải quận/huyện:", error);
        districtSelect.innerHTML = '<option value="">Lỗi tải dữ liệu</option>';
        showError(
          "Không thể tải danh sách quận/huyện. Vui lòng thử lại hoặc nhập thủ công."
        );
      });
  }

  // Lấy dữ liệu phường/xã
  function loadWards(districtCode) {
    if (!wardSelect) return;
    if (!districtCode) {
      wardSelect.innerHTML = '<option value="">Chọn phường/xã</option>';
      wardSelect.disabled = true;
      return;
    }

    logDebug(
      "District Code được chọn:",
      districtCode + " (type: " + typeof districtCode + ")"
    );

    wardSelect.innerHTML = '<option value="">Đang tải phường/xã...</option>';
    wardSelect.disabled = true;

    // Sử dụng API thay thế ngay từ đầu
    fetch(
      `https://vn-public-apis.fpo.vn/wards/getByDistrict?districtCode=${districtCode}&limit=-1`
    )
      .then((response) => {
        logDebug("Wards API Response:", response.status);
        return response.json();
      })
      .then((data) => {
        logDebug("Wards data structure:", Object.keys(data));

        if (!data.data || !data.data.data) {
          throw new Error("Dữ liệu phường/xã không hợp lệ");
        }

        const wards = data.data.data;
        logDebug(
          `Đã tải ${wards.length} phường/xã cho quận/huyện ${districtCode}`
        );

        // Sắp xếp phường/xã theo tên
        const sortedWards = wards.sort((a, b) => {
          return a.name.localeCompare(b.name, "vi");
        });

        let options = '<option value="">Chọn phường/xã</option>';
        sortedWards.forEach((ward) => {
          options += `<option value="${ward.code}">${ward.name}</option>`;
        });

        wardSelect.innerHTML = options;
        wardSelect.disabled = false;
      })
      .catch((error) => {
        console.error("Lỗi khi tải phường/xã:", error);
        wardSelect.innerHTML = '<option value="">Lỗi tải dữ liệu</option>';
        showError(
          "Không thể tải danh sách phường/xã. Vui lòng thử lại hoặc nhập thủ công."
        );
      });
  }

  // Thêm trường nhập địa chỉ thủ công khi API không hoạt động
  function addManualAddressField() {
    if (document.getElementById("manual_full_address")) return;

    const container = document.createElement("div");
    container.className = "mb-3 mt-3";

    const label = document.createElement("label");
    label.className = "form-label";
    label.htmlFor = "manual_full_address";
    label.innerHTML = 'Địa chỉ đầy đủ <span class="text-danger">*</span>';

    const input = document.createElement("input");
    input.type = "text";
    input.className = "form-control";
    input.name = "manual_full_address";
    input.id = "manual_full_address";
    input.placeholder =
      "Nhập đầy đủ địa chỉ (Số nhà, Đường, Phường/Xã, Quận/Huyện, Tỉnh/Thành phố)";
    input.required = true;

    container.appendChild(label);
    container.appendChild(input);

    // Thêm vào DOM
    if (wardSelect && wardSelect.parentNode) {
      wardSelect.parentNode.parentNode.appendChild(container);
    } else if (districtSelect && districtSelect.parentNode) {
      districtSelect.parentNode.parentNode.appendChild(container);
    } else if (provinceSelect && provinceSelect.parentNode) {
      provinceSelect.parentNode.parentNode.appendChild(container);
    }
  }

  // Cập nhật địa chỉ đầy đủ
  function updateFullAddressPreview() {
    if (!fullAddressPreview || !fullAddressText) return;

    const parts = [];

    if (addressInput && addressInput.value.trim()) {
      parts.push(addressInput.value.trim());
    }

    if (selectedWard.name) {
      parts.push(selectedWard.name);
    }

    if (selectedDistrict.name) {
      parts.push(selectedDistrict.name);
    }

    if (selectedProvince.name) {
      parts.push(selectedProvince.name);
    }

    if (parts.length > 0) {
      fullAddressText.textContent = parts.join(", ");
      fullAddressPreview.classList.remove("d-none");
    } else {
      fullAddressPreview.classList.add("d-none");
    }
  }

  // Thiết lập các sự kiện
  function setupEventListeners() {
    if (provinceSelect) {
      provinceSelect.addEventListener("change", function () {
        const selectedOption = this.options[this.selectedIndex];
        selectedProvince.code = this.value;
        selectedProvince.name =
          selectedOption.text !== "Chọn tỉnh/thành phố"
            ? selectedOption.text
            : "";

        logDebug("Selected province:", {
          code: selectedProvince.code,
          name: selectedProvince.name,
        });

        loadDistricts(this.value);
        resetWard();
        updateFullAddressPreview();
      });
    }

    if (districtSelect) {
      districtSelect.addEventListener("change", function () {
        const selectedOption = this.options[this.selectedIndex];
        selectedDistrict.code = this.value;
        selectedDistrict.name =
          selectedOption.text !== "Chọn quận/huyện" ? selectedOption.text : "";

        logDebug("Selected district:", {
          code: selectedDistrict.code,
          name: selectedDistrict.name,
        });

        loadWards(this.value);
        updateFullAddressPreview();
      });
    }

    if (wardSelect) {
      wardSelect.addEventListener("change", function () {
        const selectedOption = this.options[this.selectedIndex];
        selectedWard.code = this.value;
        selectedWard.name =
          selectedOption.text !== "Chọn phường/xã" ? selectedOption.text : "";

        updateFullAddressPreview();
      });
    }

    if (addressInput) {
      addressInput.addEventListener("input", updateFullAddressPreview);
    }
  }

  // Reset quận/huyện
  function resetDistrict() {
    if (districtSelect) {
      districtSelect.innerHTML = '<option value="">Chọn quận/huyện</option>';
      districtSelect.disabled = true;
      selectedDistrict = { code: "", name: "" };
    }
  }

  // Reset phường/xã
  function resetWard() {
    if (wardSelect) {
      wardSelect.innerHTML = '<option value="">Chọn phường/xã</option>';
      wardSelect.disabled = true;
      selectedWard = { code: "", name: "" };
    }
  }

  // Hiển thị thông báo lỗi
  function showError(message) {
    const alertDiv = document.createElement("div");
    alertDiv.className = "alert alert-danger mt-2";
    alertDiv.role = "alert";
    alertDiv.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-2"></i>${message}`;

    // Xóa thông báo lỗi cũ
    const oldAlert = document.querySelector(".alert-danger");
    if (oldAlert) oldAlert.remove();

    // Thêm thông báo mới
    if (provinceSelect && provinceSelect.parentNode) {
      provinceSelect.parentNode.appendChild(alertDiv);
    }

    // Tự động xóa sau 5 giây
    setTimeout(() => alertDiv.remove(), 5000);
  }

  // Lấy dữ liệu địa chỉ đã chọn
  function getSelectedAddressData() {
    // Kiểm tra nếu người dùng đang nhập thủ công
    const manualAddressInput = document.getElementById("manual_full_address");
    const manualAddress = manualAddressInput
      ? manualAddressInput.value.trim()
      : "";

    // Xây dựng địa chỉ đầy đủ
    const addressParts = [];
    if (addressInput && addressInput.value.trim()) {
      addressParts.push(addressInput.value.trim());
    }
    if (selectedWard.name) {
      addressParts.push(selectedWard.name);
    }
    if (selectedDistrict.name) {
      addressParts.push(selectedDistrict.name);
    }
    if (selectedProvince.name) {
      addressParts.push(selectedProvince.name);
    }

    const fullAddress =
      addressParts.length > 0 ? addressParts.join(", ") : manualAddress;

    return {
      provinceCode: selectedProvince.code,
      provinceName: selectedProvince.name,
      districtCode: selectedDistrict.code,
      districtName: selectedDistrict.name,
      wardCode: selectedWard.code,
      wardName: selectedWard.name,
      address: addressInput ? addressInput.value.trim() : "",
      fullAddress: fullAddress,
    };
  }

  // Khởi tạo
  loadProvinces();
  setupEventListeners();

  // Đặt hàm getSelectedAddressData() trong window để có thể truy cập từ bên ngoài
  window.getSelectedAddressData = getSelectedAddressData;

  // Thêm vào cuối file
  function populateAddressData(provinceId, districtId, wardId) {
    if (provinceId) {
      document.getElementById("province").value = provinceId;
      // Trigger change event để load districts
      const event = new Event("change");
      document.getElementById("province").dispatchEvent(event);

      // Set timeout để đợi districts load xong
      setTimeout(() => {
        if (districtId) {
          document.getElementById("district").value = districtId;
          document.getElementById("district").dispatchEvent(event);

          // Set timeout để đợi wards load xong
          setTimeout(() => {
            if (wardId) {
              document.getElementById("ward").value = wardId;
              document.getElementById("ward").dispatchEvent(event);
            }
          }, 500);
        }
      }, 500);
    }
  }

  // Expose to global scope
  window.populateAddressData = populateAddressData;
});
