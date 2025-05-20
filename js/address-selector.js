/**
 * Module xử lý địa chỉ Việt Nam
 */

// Create a reusable module for Vietnamese address selection
const VNAddressManager = {
  DEBUG: true,
  apiUrl: {
    provinces: "https://vn-public-apis.fpo.vn/provinces/getAll?limit=-1",
    districts: (provinceCode) =>
      `https://vn-public-apis.fpo.vn/districts/getByProvince?provinceCode=${provinceCode}&limit=-1`,
    wards: (districtCode) =>
      `https://vn-public-apis.fpo.vn/wards/getByDistrict?districtCode=${districtCode}&limit=-1`,
  },

  logDebug(message, data) {
    if (this.DEBUG) {
      console.log(
        `[${new Date().toLocaleTimeString()}] ${message}`,
        data || ""
      );
    }
  },

  showError(container, message) {
    const alertDiv = document.createElement("div");
    alertDiv.className = "alert alert-danger mt-2";
    alertDiv.role = "alert";
    alertDiv.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-2"></i>${message}`;

    // Remove existing error messages
    const oldAlert = container.querySelector(".alert-danger");
    if (oldAlert) oldAlert.remove();

    // Add new message
    container.appendChild(alertDiv);

    // Auto-remove after 5 seconds
    setTimeout(() => alertDiv.remove(), 5000);
  },

  async loadProvinces() {
    try {
      const response = await fetch(this.apiUrl.provinces);
      const data = await response.json();

      if (!data.data || !data.data.data) {
        throw new Error("Dữ liệu tỉnh/thành phố không hợp lệ");
      }

      const provinces = data.data.data;
      // Sort provinces by name
      return provinces.sort((a, b) => a.name.localeCompare(b.name, "vi"));
    } catch (error) {
      this.logDebug("Error loading provinces:", error);
      throw error;
    }
  },

  async loadDistricts(provinceCode) {
    try {
      const response = await fetch(this.apiUrl.districts(provinceCode));
      const data = await response.json();

      if (!data.data || !data.data.data) {
        throw new Error("Dữ liệu quận/huyện không hợp lệ");
      }

      const districts = data.data.data;
      // Sort districts by name
      return districts.sort((a, b) => a.name.localeCompare(b.name, "vi"));
    } catch (error) {
      this.logDebug("Error loading districts:", error);
      throw error;
    }
  },

  async loadWards(districtCode) {
    try {
      const response = await fetch(this.apiUrl.wards(districtCode));
      const data = await response.json();

      if (!data.data || !data.data.data) {
        throw new Error("Dữ liệu phường/xã không hợp lệ");
      }

      const wards = data.data.data;
      // Sort wards by name
      return wards.sort((a, b) => a.name.localeCompare(b.name, "vi"));
    } catch (error) {
      this.logDebug("Error loading wards:", error);
      throw error;
    }
  },

  populateSelect(
    selectElement,
    items,
    valueProperty,
    textProperty,
    selectedValue = null
  ) {
    // Clear existing options
    selectElement.innerHTML = "";

    // Add default option
    const defaultOption = document.createElement("option");
    defaultOption.value = "";
    defaultOption.textContent = selectElement.dataset.placeholder || "Chọn...";
    selectElement.appendChild(defaultOption);

    // Add options from items
    items.forEach((item) => {
      const option = document.createElement("option");
      option.value =
        valueProperty === textProperty
          ? item[valueProperty]
          : item[valueProperty];
      option.textContent = item[textProperty];
      if (selectedValue !== null && item[valueProperty] == selectedValue) {
        option.selected = true;
      }
      selectElement.appendChild(option);
    });

    // Enable the select element
    selectElement.disabled = false;
  },
};

// Original implementation using the shared module
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

  // Skip if elements don't exist
  if (!provinceSelect) return;
  // Lấy dữ liệu tỉnh/thành phố
  async function loadProvinces() {
    if (!provinceSelect) return;

    provinceSelect.innerHTML =
      '<option value="">Đang tải tỉnh/thành phố...</option>';
    districtSelect.innerHTML = '<option value="">Chọn quận/huyện</option>';
    wardSelect.innerHTML = '<option value="">Chọn phường/xã</option>';
    districtSelect.disabled = true;
    wardSelect.disabled = true;

    try {
      // Kiểm tra cache trước khi gọi API
      const cachedData = localStorage.getItem('vn_provinces');
      let provinces;

      if (cachedData) {
        try {
          provinces = JSON.parse(cachedData);
          console.log('Sử dụng dữ liệu tỉnh/thành phố từ cache');
        } catch (e) {
          console.log('Dữ liệu cache không hợp lệ, tải lại từ API');
          provinces = await VNAddressManager.loadProvinces();
          localStorage.setItem('vn_provinces', JSON.stringify(provinces));
        }
      } else {
        provinces = await VNAddressManager.loadProvinces();
        localStorage.setItem('vn_provinces', JSON.stringify(provinces));
      }

      VNAddressManager.populateSelect(
        provinceSelect,
        provinces,
        "code",
        "name"
      );
    } catch (error) {
      console.error("Lỗi khi tải tỉnh/thành phố:", error);
      provinceSelect.innerHTML = '<option value="">Lỗi tải dữ liệu</option>';
      VNAddressManager.showError(
        provinceSelect.parentNode,
        "Không thể tải danh sách tỉnh/thành phố. Vui lòng thử lại sau hoặc nhập thủ công."
      );
      addManualAddressField();
    }
  }

  // Lấy dữ liệu quận/huyện
  async function loadDistricts(provinceCode) {
    if (!districtSelect) return;
    if (!provinceCode) {
      districtSelect.innerHTML = '<option value="">Chọn quận/huyện</option>';
      districtSelect.disabled = true;
      wardSelect.innerHTML = '<option value="">Chọn phường/xã</option>';
      wardSelect.disabled = true;
      return;
    }

    districtSelect.innerHTML =
      '<option value="">Đang tải quận/huyện...</option>';
    wardSelect.innerHTML = '<option value="">Chọn phường/xã</option>';
    districtSelect.disabled = true;
    wardSelect.disabled = true;

    try {
      const districts = await VNAddressManager.loadDistricts(provinceCode);
      VNAddressManager.populateSelect(
        districtSelect,
        districts,
        "code",
        "name"
      );
    } catch (error) {
      console.error("Lỗi khi tải quận/huyện:", error);
      districtSelect.innerHTML = '<option value="">Lỗi tải dữ liệu</option>';
      VNAddressManager.showError(
        districtSelect.parentNode,
        "Không thể tải danh sách quận/huyện. Vui lòng thử lại hoặc nhập thủ công."
      );
    }
  }

  // Lấy dữ liệu phường/xã
  async function loadWards(districtCode) {
    if (!wardSelect) return;
    if (!districtCode) {
      wardSelect.innerHTML = '<option value="">Chọn phường/xã</option>';
      wardSelect.disabled = true;
      return;
    }

    wardSelect.innerHTML = '<option value="">Đang tải phường/xã...</option>';
    wardSelect.disabled = true;

    try {
      const wards = await VNAddressManager.loadWards(districtCode);
      VNAddressManager.populateSelect(wardSelect, wards, "code", "name");
    } catch (error) {
      console.error("Lỗi khi tải phường/xã:", error);
      wardSelect.innerHTML = '<option value="">Lỗi tải dữ liệu</option>';
      VNAddressManager.showError(
        wardSelect.parentNode,
        "Không thể tải danh sách phường/xã. Vui lòng thử lại hoặc nhập thủ công."
      );
    }
  }
  // Thêm trường nhập địa chỉ thủ công khi API không hoạt động
  function addManualAddressField() {
    if (document.getElementById("manual_full_address")) return;

    const container = document.createElement("div");
    container.className = "mb-3 mt-3 alert alert-warning";

    const heading = document.createElement("h6");
    heading.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-2"></i>Không thể tải dữ liệu địa chỉ';
    heading.className = "mb-2";

    const note = document.createElement("p");
    note.className = "small mb-3";
    note.textContent = "Hệ thống không thể kết nối đến máy chủ dữ liệu địa chỉ. Vui lòng nhập đầy đủ địa chỉ bên dưới hoặc thử lại sau.";

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

    // Thêm hidden field để truyền địa chỉ đầy đủ
    const hiddenField = document.createElement("input");
    hiddenField.type = "hidden";
    hiddenField.name = "manual_address_mode";
    hiddenField.value = "1";

    container.appendChild(heading);
    container.appendChild(note);
    container.appendChild(label);
    container.appendChild(input);
    container.appendChild(hiddenField);

    // Thêm sự kiện để cập nhật hidden fields với dữ liệu manual
    input.addEventListener('input', function () {
      const manualValue = input.value.trim();

      // Cập nhật hidden fields
      const provinceField = document.getElementById('province_name_hidden');
      const districtField = document.getElementById('district_name_hidden');
      const wardField = document.getElementById('ward_name_hidden');

      if (provinceField) provinceField.value = manualValue;
      if (districtField) districtField.value = manualValue;
      if (wardField) wardField.value = manualValue;

      if (fullAddressPreview && fullAddressText) {
        fullAddressText.textContent = manualValue;
        fullAddressPreview.classList.remove("d-none");
      }
    });

    // Thêm vào DOM
    if (wardSelect && wardSelect.parentNode) {
      wardSelect.parentNode.parentNode.appendChild(container);
    } else if (districtSelect && districtSelect.parentNode) {
      districtSelect.parentNode.parentNode.appendChild(container);
    } else if (provinceSelect && provinceSelect.parentNode) {
      provinceSelect.parentNode.parentNode.appendChild(container);
    }
  }
  // Cập nhật địa chỉ đầy đủ với màu sắc và định dạng rõ ràng hơn
  function updateFullAddressPreview() {
    if (!fullAddressPreview || !fullAddressText) return;

    const parts = [];

    if (addressInput && addressInput.value.trim()) {
      parts.push(`<span class="text-dark">${addressInput.value.trim()}</span>`);
    }

    if (selectedWard.name) {
      parts.push(`<span class="text-dark">${selectedWard.name}</span>`);
    }

    if (selectedDistrict.name) {
      parts.push(`<span class="text-dark">${selectedDistrict.name}</span>`);
    }

    if (selectedProvince.name) {
      parts.push(`<span class="text-primary">${selectedProvince.name}</span>`);
    }

    if (parts.length > 0) {
      fullAddressText.innerHTML = parts.join(", ");
      fullAddressPreview.classList.remove("d-none");

      // Thêm class để styling
      fullAddressPreview.classList.add("alert");
      fullAddressPreview.classList.add("alert-light");
      fullAddressPreview.classList.add("border");
    } else {
      fullAddressPreview.classList.add("d-none");
    }
  }  // Tạo hoặc cập nhật hidden fields cho tên các địa chỉ  
  function createOrUpdateHiddenField(name, value) {
    // Tìm theo ID trước (ưu tiên)
    let hiddenField = document.getElementById(`${name}_name_hidden`);

    // Nếu không tìm thấy theo ID, thử tìm theo tên 
    if (!hiddenField) {
      hiddenField = document.querySelector(`input[name="${name}_name"]`);
    }

    // Nếu vẫn không tìm thấy, tạo mới
    if (!hiddenField) {
      hiddenField = document.createElement('input');
      hiddenField.type = 'hidden';
      hiddenField.name = `${name}_name`;
      hiddenField.id = `${name}_name_hidden`;
      const form = provinceSelect.form || provinceSelect.closest('form');
      if (form) form.appendChild(hiddenField);
    }

    console.log(`Updating ${name}_name hidden field with value: "${value}" [type: ${typeof value}]`);

    // Đảm bảo giá trị không bao giờ là rỗng hoặc undefined
    hiddenField.value = value || '';

    // Debug - hiển thị giá trị sau khi đã set
    console.log(`${name}_name_hidden sau khi đặt giá trị: "${hiddenField.value}"`);
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

        // Lưu tên tỉnh/thành phố vào hidden field
        createOrUpdateHiddenField('province', selectedProvince.name);

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

        // Lưu tên quận/huyện vào hidden field
        createOrUpdateHiddenField('district', selectedDistrict.name);

        loadWards(this.value);
        updateFullAddressPreview();
      });
    } if (wardSelect) {
      wardSelect.addEventListener("change", function () {
        const selectedOption = this.options[this.selectedIndex];
        selectedWard.code = this.value;
        selectedWard.name =
          selectedOption.text !== "Chọn phường/xã" ? selectedOption.text : "";

        // Lưu tên phường/xã vào hidden field
        createOrUpdateHiddenField('ward', selectedWard.name);

        // Đảm bảo giá trị không bị để trống
        if (!selectedWard.name && selectedWard.code) {
          // Nếu không có tên nhưng có mã, sử dụng mã làm giá trị dự phòng
          createOrUpdateHiddenField('ward', selectedWard.code);
          console.log("Sử dụng mã phường/xã làm giá trị dự phòng: " + selectedWard.code);
        }

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

/**
 * Address selector for Vietnamese provinces, districts and wards
 * Unified implementation using the shared VNAddressManager
 */
// Function to initialize address selectors - reuses the VNAddressManager module
async function initializeAddressSelectors(options) {
  const provinceSelector = document.querySelector(options.provinceSelector);
  const districtSelector = document.querySelector(options.districtSelector);
  const wardSelector = document.querySelector(options.wardSelector);

  const selectedProvince = options.selectedProvince || "";
  const selectedDistrict = options.selectedDistrict || "";
  const selectedWard = options.selectedWard || "";

  if (!provinceSelector || !districtSelector || !wardSelector) {
    console.error("Address selectors not found");
    return;
  }

  // Setup placeholders
  provinceSelector.dataset.placeholder = "Chọn tỉnh/thành phố";
  districtSelector.dataset.placeholder = "Chọn quận/huyện";
  wardSelector.dataset.placeholder = "Chọn phường/xã";

  // Disable selectors initially
  districtSelector.disabled = true;
  wardSelector.disabled = true;

  try {
    // Load provinces
    const provinces = await VNAddressManager.loadProvinces();
    VNAddressManager.populateSelect(
      provinceSelector,
      provinces,
      "name",
      "name",
      selectedProvince
    );

    // Setup change event for province
    provinceSelector.addEventListener("change", async function () {
      const provinceName = this.value;
      districtSelector.innerHTML = '<option value="">Chọn quận/huyện</option>';
      wardSelector.innerHTML = '<option value="">Chọn phường/xã</option>';
      districtSelector.disabled = true;
      wardSelector.disabled = true;

      if (!provinceName) return;

      // Find province code by name
      const province = provinces.find((p) => p.name === provinceName);
      if (!province) return;

      try {
        // Load districts for selected province
        const districts = await VNAddressManager.loadDistricts(province.code);
        VNAddressManager.populateSelect(
          districtSelector,
          districts,
          "name",
          "name",
          selectedDistrict
        );

        // If we have a pre-selected district, trigger district change to load wards
        if (selectedDistrict && province.name === selectedProvince) {
          const district = districts.find((d) => d.name === selectedDistrict);
          if (district) {
            await loadWards(district.code, selectedWard);
          }
        }
      } catch (error) {
        console.error("Error loading districts:", error);
      }
    });

    // Setup district change event
    districtSelector.addEventListener("change", async function () {
      const districtName = this.value;
      wardSelector.innerHTML = '<option value="">Chọn phường/xã</option>';
      wardSelector.disabled = true;

      if (!districtName) return;

      // Find selected province
      const provinceName = provinceSelector.value;
      const province = provinces.find((p) => p.name === provinceName);
      if (!province) return;

      // Load districts to find district code
      const districts = await VNAddressManager.loadDistricts(province.code);
      const district = districts.find((d) => d.name === districtName);
      if (!district) return;

      await loadWards(district.code);
    });

    // Trigger province change if we have a pre-selected province
    if (selectedProvince) {
      provinceSelector.dispatchEvent(new Event("change"));
    }
  } catch (error) {
    console.error("Error initializing address selectors:", error);
  }

  // Function to load wards based on district code
  async function loadWards(districtCode, selectedValue = null) {
    try {
      const wards = await VNAddressManager.loadWards(districtCode);
      VNAddressManager.populateSelect(
        wardSelector,
        wards,
        "name",
        "name",
        selectedValue
      );
    } catch (error) {
      console.error("Error loading wards:", error);
      wardSelector.innerHTML = '<option value="">Lỗi tải dữ liệu</option>';
    }
  }
}

// Helper function to get the full selected address
function getSelectedAddressData() {
  const province = document.getElementById("tinh_tp");
  const district = document.getElementById("quan_huyen");
  const ward = document.getElementById("phuong_xa");
  const addressInput = document.getElementById("diachi");

  const provinceName = province ? province.value : "";
  const districtName = district ? district.value : "";
  const wardName = ward ? ward.value : "";
  const addressDetails = addressInput ? addressInput.value : "";

  // Construct full address
  let addressParts = [];
  if (addressDetails) addressParts.push(addressDetails);
  if (wardName) addressParts.push(wardName);
  if (districtName) addressParts.push(districtName);
  if (provinceName) addressParts.push(provinceName);

  const fullAddress = addressParts.join(", ");

  return {
    provinceName,
    districtName,
    wardName,
    addressDetails,
    fullAddress,
  };
}

// Make getSelectedAddressData available globally
window.getSelectedAddressData = getSelectedAddressData;
