/**
 * Quản lý chọn màu và hiển thị hình ảnh cho trang chi tiết sản phẩm
 */

class ProductColorSelector {
  constructor() {
    this.availableSizesByColor = {};
    this.colorImagesData = {};
    this.init();
  }

  init() {
    // Lấy dữ liệu từ JSON được nhúng trong trang
    try {
      this.availableSizesByColor = JSON.parse(
        document.getElementById("available-sizes-data").textContent
      );
      this.colorImagesData = JSON.parse(
        document.getElementById("color-images-data").textContent
      );
    } catch (e) {
      console.error("Error parsing color/size data:", e);
    }

    this.initColorSelection();
  }

  initColorSelection() {
    const colorOptions = document.querySelectorAll(".color-option");
    const self = this;

    colorOptions.forEach((option) => {
      const colorId = option.dataset.colorId;

      // Check if this color has available sizes
      if (
        !this.availableSizesByColor[colorId] ||
        this.availableSizesByColor[colorId].length === 0
      ) {
        option.classList.add("disabled");
        option.title = "Màu này hiện không có sẵn";
        return;
      }

      option.addEventListener("click", function () {
        if (this.classList.contains("disabled")) return;

        // Update UI
        colorOptions.forEach((opt) => opt.classList.remove("active"));
        this.classList.add("active");

        const colorName = this.dataset.colorName;
        const colorId = this.dataset.colorId;

        // Update selected color info
        document.querySelector(".selected-color-value").textContent = colorName;

        // Update available sizes
        self.updateAvailableSizes(colorId);

        // Update product image
        self.updateProductImage(colorId);

        // Trigger custom event for other components to listen to
        document.dispatchEvent(
          new CustomEvent("color-selected", {
            detail: {
              colorId: colorId,
              colorName: colorName,
            },
          })
        );
      });
    });
  }

  updateAvailableSizes(colorId) {
    const sizeButtons = document.querySelectorAll(".size-btn");
    if (!sizeButtons.length) return;

    // Reset selection
    this.resetSizeSelection();

    // If no size information is available for this color
    if (!this.availableSizesByColor[colorId]) {
      sizeButtons.forEach((btn) => {
        btn.disabled = true;
        btn.classList.add("disabled");
        btn.title = "Kích thước này không có sẵn cho màu đã chọn";
      });
      return;
    }

    // Enable/disable size buttons based on selected color
    sizeButtons.forEach((btn) => {
      const sizeId = parseInt(btn.dataset.sizeId);
      if (this.availableSizesByColor[colorId].includes(sizeId)) {
        btn.disabled = false;
        btn.classList.remove("disabled");
        btn.title = "";
      } else {
        btn.disabled = true;
        btn.classList.add("disabled");
        btn.title = "Kích thước này không có sẵn cho màu đã chọn";
      }
    });
  }

  resetSizeSelection() {
    const sizeButtons = document.querySelectorAll(".size-btn");
    sizeButtons.forEach((btn) => {
      btn.classList.remove("active", "disabled");
      btn.disabled = false;
    });
  }

  updateProductImage(colorId) {
    // Check if this color has an image
    if (this.colorImagesData[colorId]) {
      const mainImage = document.getElementById("main-product-image");
      if (!mainImage) return;

      // Find color thumbnail
      const colorThumbnail = document.querySelector(
        `.thumbnail-wrapper[data-color-id="${colorId}"]`
      );

      if (colorThumbnail) {
        // Update thumbnail active state
        document
          .querySelectorAll(".thumbnail-wrapper")
          .forEach((w) => w.classList.remove("active"));
        colorThumbnail.classList.add("active");

        // Get image src from thumbnail
        const imgSrc = colorThumbnail.querySelector("img").src;

        // Apply fade effect
        mainImage.style.opacity = "0.5";

        // Update main image with small delay for transition
        setTimeout(() => {
          mainImage.src = imgSrc;
          mainImage.style.opacity = "1";
        }, 200);
      }
    }
  }
}

// Thêm vào function updateImageForColor
function updateImageForColor(colorId) {
  console.log("Updating image for color ID:", colorId);
  console.log("Available color images:", colorImagesData);

  // Kiểm tra xem màu này có hình ảnh không
  if (colorImagesData[colorId]) {
    console.log("Found image for color:", colorImagesData[colorId]);
    // Còn lại như cũ
  } else {
    console.log("No image found for this color");
  }
}

// Initialize on DOM content loaded
document.addEventListener("DOMContentLoaded", function () {
  new ProductColorSelector();

  // Size button click handler
  const sizeButtons = document.querySelectorAll(".size-btn");
  sizeButtons.forEach((button) => {
    button.addEventListener("click", function () {
      if (this.disabled) return;

      sizeButtons.forEach((btn) => btn.classList.remove("active"));
      this.classList.add("active");
    });
  });

  // Thumbnail gallery
  const thumbnailWrappers = document.querySelectorAll(".thumbnail-wrapper");
  const mainImage = document.getElementById("main-product-image");

  thumbnailWrappers.forEach((wrapper) => {
    wrapper.addEventListener("click", function () {
      // Update active state
      thumbnailWrappers.forEach((w) => w.classList.remove("active"));
      this.classList.add("active");

      // Update main image
      const thumbnail = this.querySelector("img");
      if (thumbnail && mainImage) {
        mainImage.style.opacity = "0.5";
        setTimeout(() => {
          mainImage.src = thumbnail.src;
          mainImage.style.opacity = "1";
        }, 200);
      }

      // If color thumbnail, update color selection
      if (this.dataset.type === "color") {
        const colorId = this.dataset.colorId;
        if (colorId) {
          const colorOption = document.querySelector(
            `.color-option[data-color-id="${colorId}"]`
          );
          if (
            colorOption &&
            !colorOption.classList.contains("active") &&
            !colorOption.classList.contains("disabled")
          ) {
            colorOption.click();
          }
        }
      }
    });
  });
});
