<?php
// Thiết lập tiêu đề trang
$page_title = "Thêm Sản Phẩm Mới";

// Include header
include('includes/header.php');

// Kiểm tra xem user có thông tin shop chưa - nếu chưa redirect tới trang hồ sơ
if (empty($seller['ten_shop'])) {
    $_SESSION['info_message'] = "Vui lòng cập nhật thông tin shop trước khi bắt đầu bán hàng";
    header("Location: ho-so.php");
    exit();
}

// Lấy danh sách danh mục sản phẩm
$categories_query = $conn->query("SELECT * FROM loaisanpham WHERE trangthai = 1 ORDER BY tenloai");
$categories = [];
while ($category = $categories_query->fetch_assoc()) {
    $categories[] = $category;
}

// Lấy danh sách thương hiệu
$brands_query = $conn->query("SELECT * FROM thuonghieu WHERE trangthai = 1 ORDER BY tenthuonghieu");
$brands = [];
while ($brand = $brands_query->fetch_assoc()) {
    $brands[] = $brand;
}

// Lấy danh sách kích thước
$sizes_query = $conn->query("SELECT * FROM kichthuoc ORDER BY tenkichthuoc");
$sizes = [];
while ($size = $sizes_query->fetch_assoc()) {
    $sizes[] = $size;
}

// Lấy danh sách màu sắc
$colors_query = $conn->query("SELECT * FROM mausac WHERE trangthai = 1 ORDER BY tenmau");
$colors = [];
while ($color = $colors_query->fetch_assoc()) {
    $colors[] = $color;
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Thêm Sản Phẩm Mới</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="danh-sach-san-pham.php" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Quay lại
        </a>
    </div>
</div>

<?php if (isset($_SESSION['error_message'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle me-2"></i> <?php echo $_SESSION['error_message']; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php unset($_SESSION['error_message']); ?>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="xu-ly-san-pham.php" method="post" enctype="multipart/form-data" id="addProductForm">
            <input type="hidden" name="action" value="add">
            
            <!-- Thông tin cơ bản -->
            <div class="mb-4">
                <h5 class="card-title">Thông tin cơ bản</h5>
                <div class="row g-3">
                    <div class="col-md-8">
                        <div class="mb-3">
                            <label for="tensanpham" class="form-label">Tên sản phẩm <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="tensanpham" name="tensanpham" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="id_loai" class="form-label">Danh mục <span class="text-danger">*</span></label>
                            <select class="form-select" id="id_loai" name="id_loai" required>
                                <option value="">-- Chọn danh mục --</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id_loai']; ?>">
                                    <?php echo htmlspecialchars($category['tenloai']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="id_thuonghieu" class="form-label">Thương hiệu</label>
                            <select class="form-select" id="id_thuonghieu" name="id_thuonghieu">
                                <option value="">-- Chọn thương hiệu --</option>
                                <?php foreach ($brands as $brand): ?>
                                <option value="<?php echo $brand['id_thuonghieu']; ?>">
                                    <?php echo htmlspecialchars($brand['tenthuonghieu']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="gia" class="form-label">Giá bán <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="gia" name="gia" min="1000" required>
                                        <span class="input-group-text">VNĐ</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="giagoc" class="form-label">Giá gốc</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="giagoc" name="giagoc" min="1000">
                                        <span class="input-group-text">VNĐ</span>
                                    </div>
                                    <div class="form-text">Để trống nếu không có giá gốc</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="hinhanh" class="form-label">Hình ảnh chính <span class="text-danger">*</span></label>
                            <input type="file" class="form-control" id="hinhanh" name="hinhanh" accept="image/*" required>
                            <div class="form-text">Kích thước khuyến nghị: 800x800px, tối đa 2MB</div>
                            
                            <div id="imagePreview" class="mt-2 text-center d-none">
                                <img src="" alt="Hình ảnh sản phẩm" class="img-thumbnail" style="max-height: 200px;">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="mota" class="form-label">Mô tả sản phẩm</label>
                    <textarea class="form-control" id="mota" name="mota" rows="5"></textarea>
                </div>
            </div>
            
            <!-- Biến thể sản phẩm -->
            <div class="mb-4">
                <h5 class="card-title">Biến thể sản phẩm</h5>
                <p class="text-muted">Quản lý các phiên bản sản phẩm dựa trên kích thước và màu sắc</p>
                
                <div class="row g-3">
                    <!-- Kích thước -->
                    <div class="col-md-6">
                        <label class="form-label">Kích thước <span class="text-danger">*</span></label>
                        <div class="border p-3 rounded mb-3">
                            <div class="row">
                                <?php foreach ($sizes as $size): ?>
                                <div class="col-md-3 col-4 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="sizes[]" 
                                               value="<?php echo $size['id_kichthuoc']; ?>" 
                                               id="size_<?php echo $size['id_kichthuoc']; ?>">
                                        <label class="form-check-label" for="size_<?php echo $size['id_kichthuoc']; ?>">
                                            <?php echo htmlspecialchars($size['tenkichthuoc']); ?>
                                        </label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Màu sắc -->
                    <div class="col-md-6">
                        <label class="form-label">Màu sắc <span class="text-danger">*</span></label>
                        <div class="border p-3 rounded mb-3">
                            <div class="row">
                                <?php foreach ($colors as $color): ?>
                                <div class="col-md-4 col-6 mb-2">
                                    <div class="form-check d-flex align-items-center">
                                        <input class="form-check-input" type="checkbox" name="colors[]" 
                                               value="<?php echo $color['id_mausac']; ?>" 
                                               id="color_<?php echo $color['id_mausac']; ?>">
                                        <label class="form-check-label d-flex align-items-center ms-2" for="color_<?php echo $color['id_mausac']; ?>">
                                            <span class="color-preview me-2" 
                                                  style="width: 18px; height: 18px; background-color: <?php echo $color['mamau']; ?>; display: inline-block; border-radius: 3px; border: 1px solid #ddd;"></span>
                                            <?php echo htmlspecialchars($color['tenmau']); ?>
                                        </label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="mt-3">
                                <button type="button" class="btn btn-sm btn-outline-primary" id="addColorButton">
                                    <i class="bi bi-plus-circle me-1"></i> Thêm màu mới
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quản lý số lượng -->
            <div class="mb-4" id="inventorySection">
                <h5 class="card-title">Quản lý tồn kho</h5>
                
                <div class="mb-3" id="noVariantsMessage">
                    <div class="alert alert-info py-2">
                        <i class="bi bi-info-circle me-2"></i>
                        Vui lòng chọn ít nhất một kích thước và một màu sắc để quản lý tồn kho.
                    </div>
                </div>
                
                <div id="variantsContainer" style="display:none;">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th width="25%">Kích thước</th>
                                <th width="40%">Màu sắc</th>
                                <th width="20%">Số lượng tồn</th>
                                <th width="15%">Hình ảnh</th>
                            </tr>
                        </thead>
                        <tbody id="variantsTableBody">
                            <!-- Các biến thể sẽ được thêm vào đây bằng JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Hình ảnh bổ sung -->
            <div class="mb-4">
                <h5 class="card-title">Hình ảnh bổ sung</h5>
                <div class="mb-3">
                    <label for="hinhanh_phu" class="form-label">Thêm hình ảnh (tối đa 5 hình)</label>
                    <input type="file" class="form-control" id="hinhanh_phu" name="hinhanh_phu[]" accept="image/*" multiple>
                    <div class="form-text">Chọn nhiều hình ảnh cùng lúc để mô tả chi tiết sản phẩm.</div>
                </div>
                
                <div id="additionalImagesPreview" class="mt-2 d-flex flex-wrap gap-2"></div>
            </div>
            
            <!-- Cài đặt bổ sung -->
            <div class="mb-4">
                <h5 class="card-title">Cài đặt bổ sung</h5>
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="noibat" name="noibat" value="1">
                            <label class="form-check-label" for="noibat">Đánh dấu là sản phẩm nổi bật</label>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="trangthai" class="form-label">Trạng thái</label>
                            <select class="form-select" id="trangthai" name="trangthai">
                                <option value="1" selected>Còn hàng</option>
                                <option value="0">Hết hàng</option>
                                <option value="2">Ngừng kinh doanh</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="d-flex justify-content-end">
                <button type="button" class="btn btn-outline-secondary me-2" onclick="window.location.href='danh-sach-san-pham.php'">
                    <i class="bi bi-x-circle me-1"></i> Hủy
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i> Thêm sản phẩm
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal thêm màu mới -->
<div class="modal fade" id="addColorModal" tabindex="-1" aria-labelledby="addColorModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addColorModalLabel">Thêm màu mới</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addColorForm">
                    <div class="mb-3">
                        <label for="newColorName" class="form-label">Tên màu</label>
                        <input type="text" class="form-control" id="newColorName" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="newColorCode" class="form-label">Mã màu</label>
                        <input type="color" class="form-control form-control-color w-100" id="newColorCode" name="code" value="#563d7c" required>
                        <div class="form-text">Chọn mã màu theo định dạng HEX</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" id="saveNewColor">Thêm màu</button>
            </div>
        </div>
    </div>
</div>

<?php
// JavaScript cho trang
$page_specific_js = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Preview ảnh chính khi chọn file
    const mainImageInput = document.getElementById("hinhanh");
    const imagePreview = document.getElementById("imagePreview");
    
    mainImageInput.addEventListener("change", function() {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                imagePreview.classList.remove("d-none");
                imagePreview.querySelector("img").src = e.target.result;
            };
            reader.readAsDataURL(this.files[0]);
        } else {
            imagePreview.classList.add("d-none");
        }
    });
    
    // Preview ảnh bổ sung khi chọn files
    const additionalImagesInput = document.getElementById("hinhanh_phu");
    const additionalImagesPreview = document.getElementById("additionalImagesPreview");
    
    additionalImagesInput.addEventListener("change", function() {
        additionalImagesPreview.innerHTML = "";
        
        if (this.files && this.files.length > 0) {
            const maxFiles = 5;
            const filesToPreview = Math.min(this.files.length, maxFiles);
            
            for (let i = 0; i < filesToPreview; i++) {
                const reader = new FileReader();
                const file = this.files[i];
                
                reader.onload = function(e) {
                    const imgContainer = document.createElement("div");
                    imgContainer.className = "position-relative";
                    
                    const img = document.createElement("img");
                    img.src = e.target.result;
                    img.className = "img-thumbnail";
                    img.style.width = "100px";
                    img.style.height = "100px";
                    img.style.objectFit = "cover";
                    
                    imgContainer.appendChild(img);
                    additionalImagesPreview.appendChild(imgContainer);
                };
                
                reader.readAsDataURL(file);
            }
            
            if (this.files.length > maxFiles) {
                const note = document.createElement("div");
                note.className = "small text-danger mt-2";
                note.textContent = `Chỉ 5 hình đầu tiên sẽ được tải lên. Bạn đã chọn ${this.files.length} hình.`;
                additionalImagesPreview.appendChild(note);
            }
        }
    });
    
    // Xử lý biến thể sản phẩm
    const sizeCheckboxes = document.querySelectorAll("input[name=\"sizes[]\"]");
    const colorCheckboxes = document.querySelectorAll("input[name=\"colors[]\"]");
    const variantsContainer = document.getElementById("variantsContainer");
    const variantsTableBody = document.getElementById("variantsTableBody");
    const noVariantsMessage = document.getElementById("noVariantsMessage");
    
    function updateVariants() {
        // Lấy danh sách kích thước và màu sắc đã chọn
        const selectedSizes = [];
        const selectedColors = [];
        
        sizeCheckboxes.forEach(checkbox => {
            if (checkbox.checked) {
                const sizeId = checkbox.value;
                const sizeName = checkbox.nextElementSibling.textContent.trim();
                selectedSizes.push({ id: sizeId, name: sizeName });
            }
        });
        
        colorCheckboxes.forEach(checkbox => {
            if (checkbox.checked) {
                const colorId = checkbox.value;
                const colorElement = checkbox.nextElementSibling;
                const colorName = colorElement.textContent.trim();
                const colorPreview = colorElement.querySelector(".color-preview");
                const colorCode = colorPreview ? window.getComputedStyle(colorPreview).backgroundColor : "#000000";
                
                selectedColors.push({ 
                    id: colorId, 
                    name: colorName,
                    code: colorCode
                });
            }
        });
        
        // Nếu có cả kích thước và màu sắc được chọn, hiển thị bảng biến thể
        if (selectedSizes.length > 0 && selectedColors.length > 0) {
            variantsContainer.style.display = "block";
            noVariantsMessage.style.display = "none";
            
            // Tạo lại bảng biến thể
            variantsTableBody.innerHTML = "";
            
            selectedSizes.forEach(size => {
                selectedColors.forEach(color => {
                    const row = document.createElement("tr");
                    
                    const sizeCell = document.createElement("td");
                    sizeCell.textContent = size.name;
                    
                    const colorCell = document.createElement("td");
                    const colorDiv = document.createElement("div");
                    colorDiv.className = "d-flex align-items-center";
                    
                    const colorPreview = document.createElement("span");
                    colorPreview.className = "color-preview me-2";
                    colorPreview.style.width = "18px";
                    colorPreview.style.height = "18px";
                    colorPreview.style.backgroundColor = color.code;
                    colorPreview.style.display = "inline-block";
                    colorPreview.style.borderRadius = "3px";
                    colorPreview.style.border = "1px solid #ddd";
                    
                    colorDiv.appendChild(colorPreview);
                    colorDiv.appendChild(document.createTextNode(color.name));
                    colorCell.appendChild(colorDiv);
                    
                    const qtyCell = document.createElement("td");
                    const qtyInput = document.createElement("input");
                    qtyInput.type = "number";
                    qtyInput.className = "form-control";
                    qtyInput.name = `inventory[${size.id}][${color.id}]`;
                    qtyInput.value = "0";
                    qtyInput.min = "0";
                    qtyInput.required = true;
                    
                    qtyCell.appendChild(qtyInput);
                    
                    const imgCell = document.createElement("td");
                    const imgInput = document.createElement("input");
                    imgInput.type = "file";
                    imgInput.className = "form-control form-control-sm";
                    imgInput.name = `variant_image[${size.id}][${color.id}]`;
                    imgInput.accept = "image/*";
                    
                    const imgHelp = document.createElement("small");
                    imgHelp.className = "form-text text-muted";
                    imgHelp.textContent = "Tùy chọn";
                    
                    imgCell.appendChild(imgInput);
                    imgCell.appendChild(imgHelp);
                    
                    row.appendChild(sizeCell);
                    row.appendChild(colorCell);
                    row.appendChild(qtyCell);
                    row.appendChild(imgCell);
                    
                    variantsTableBody.appendChild(row);
                });
            });
        } else {
            variantsContainer.style.display = "none";
            noVariantsMessage.style.display = "block";
        }
    }
    
    // Đăng ký sự kiện khi chọn kích thước hoặc màu sắc
    sizeCheckboxes.forEach(checkbox => {
        checkbox.addEventListener("change", updateVariants);
    });
    
    colorCheckboxes.forEach(checkbox => {
        checkbox.addEventListener("change", updateVariants);
    });
    
    // Xử lý modal thêm màu mới
    const addColorButton = document.getElementById("addColorButton");
    const addColorModal = new bootstrap.Modal(document.getElementById("addColorModal"));
    const saveNewColorButton = document.getElementById("saveNewColor");
    const addColorForm = document.getElementById("addColorForm");
    
    addColorButton.addEventListener("click", function() {
        addColorModal.show();
    });
    
    saveNewColorButton.addEventListener("click", function() {
        const formData = new FormData(addColorForm);
        
        // Xác thực dữ liệu form
        const colorName = formData.get("name").trim();
        const colorCode = formData.get("code").trim();
        
        if (!colorName) {
            alert("Vui lòng nhập tên màu");
            return;
        }
        
        // Gọi AJAX để thêm màu mới
        fetch("../admin/ajax/add_color.php", {
            method: "POST",
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Thêm màu mới vào danh sách
                const colorsContainer = document.querySelector(".border.p-3.rounded.mb-3 .row");
                
                const newColorDiv = document.createElement("div");
                newColorDiv.className = "col-md-4 col-6 mb-2";
                
                const newColorHtml = `
                    <div class="form-check d-flex align-items-center">
                        <input class="form-check-input" type="checkbox" name="colors[]" value="${data.id}" id="color_${data.id}" checked>
                        <label class="form-check-label d-flex align-items-center ms-2" for="color_${data.id}">
                            <span class="color-preview me-2" style="width: 18px; height: 18px; background-color: ${data.code}; display: inline-block; border-radius: 3px; border: 1px solid #ddd;"></span>
                            ${data.name}
                        </label>
                    </div>
                `;
                
                newColorDiv.innerHTML = newColorHtml;
                colorsContainer.appendChild(newColorDiv);
                
                // Thêm sự kiện cho checkbox mới
                const newCheckbox = document.getElementById(`color_${data.id}`);
                newCheckbox.addEventListener("change", updateVariants);
                
                // Cập nhật danh sách biến thể
                updateVariants();
                
                // Đóng modal và reset form
                addColorModal.hide();
                addColorForm.reset();
                
                // Hiển thị thông báo thành công
                alert("Đã thêm màu mới thành công!");
            } else {
                alert("Lỗi: " + data.message);
            }
        })
        .catch(error => {
            console.error("Error:", error);
            alert("Đã xảy ra lỗi khi thêm màu mới.");
        });
    });
    
    // Xác thực form trước khi submit
    const addProductForm = document.getElementById("addProductForm");
    addProductForm.addEventListener("submit", function(event) {
        const tensanpham = document.getElementById("tensanpham").value.trim();
        const id_loai = document.getElementById("id_loai").value;
        const gia = document.getElementById("gia").value;
        const hinhanh = document.getElementById("hinhanh").value;
        
        let isValid = true;
        let errorMessage = "";
        
        if (!tensanpham) {
            errorMessage += "- Vui lòng nhập tên sản phẩm\n";
            isValid = false;
        }
        
        if (!id_loai) {
            errorMessage += "- Vui lòng chọn danh mục sản phẩm\n";
            isValid = false;
        }
        
        if (!gia || gia <= 0) {
            errorMessage += "- Vui lòng nhập giá hợp lệ\n";
            isValid = false;
        }
        
        if (!hinhanh) {
            errorMessage += "- Vui lòng chọn hình ảnh chính cho sản phẩm\n";
            isValid = false;
        }
        
        // Kiểm tra xem có ít nhất một kích thước và một màu được chọn không
        let hasSizes = false;
        let hasColors = false;
        
        sizeCheckboxes.forEach(checkbox => {
            if (checkbox.checked) hasSizes = true;
        });
        
        colorCheckboxes.forEach(checkbox => {
            if (checkbox.checked) hasColors = true;
        });
        
        if (!hasSizes) {
            errorMessage += "- Vui lòng chọn ít nhất một kích thước\n";
            isValid = false;
        }
        
        if (!hasColors) {
            errorMessage += "- Vui lòng chọn ít nhất một màu sắc\n";
            isValid = false;
        }
        
        // Đảm bảo bảng biến thể hiển thị trước khi submit
        if (hasSizes && hasColors) {
            variantsContainer.style.display = "block";
            
            // Kiểm tra các input số lượng tồn kho trước khi submit
            const inventoryInputs = document.querySelectorAll("input[name^=\'inventory\']");
            console.log("Số lượng input inventory:", inventoryInputs.length);
            
            // Đảm bảo các input đều có giá trị hợp lệ
            inventoryInputs.forEach(input => {
                if (input.value === "" || isNaN(parseInt(input.value))) {
                    input.value = 0; // Gán giá trị mặc định là 0
                }
            });
        }
        
        // Nếu không hợp lệ, ngăn form submit
        if (!isValid) {
            event.preventDefault();
            alert("Vui lòng kiểm tra lại các thông tin sau:\n" + errorMessage);
        }
    });
});

</script>
';

// Include footer
include('includes/footer.php');
?>
