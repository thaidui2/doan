-- Bảng vai trò (roles)
CREATE TABLE IF NOT EXISTS `roles` (
  `id_role` int(11) NOT NULL AUTO_INCREMENT,
  `ten_role` varchar(100) NOT NULL,
  `mo_ta` text DEFAULT NULL,
  `ngay_tao` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Bảng quyền hạn (permissions)
CREATE TABLE IF NOT EXISTS `permissions` (
  `id_permission` int(11) NOT NULL AUTO_INCREMENT,
  `ten_permission` varchar(100) NOT NULL,
  `ma_permission` varchar(100) NOT NULL,
  `mo_ta` text DEFAULT NULL,
  `nhom_permission` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id_permission`),
  UNIQUE KEY `ma_permission` (`ma_permission`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Bảng liên kết giữa vai trò và quyền hạn (role_permissions)
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_role` int(11) NOT NULL,
  `id_permission` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_permission_unique` (`id_role`,`id_permission`),
  KEY `id_permission` (`id_permission`),
  CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`id_role`) REFERENCES `roles` (`id_role`) ON DELETE CASCADE,
  CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`id_permission`) REFERENCES `permissions` (`id_permission`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Bảng liên kết giữa admin và vai trò (admin_roles)
CREATE TABLE IF NOT EXISTS `admin_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_admin` int(11) NOT NULL,
  `id_role` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admin_role_unique` (`id_admin`,`id_role`),
  KEY `id_role` (`id_role`),
  CONSTRAINT `admin_roles_ibfk_1` FOREIGN KEY (`id_admin`) REFERENCES `admin` (`id_admin`) ON DELETE CASCADE,
  CONSTRAINT `admin_roles_ibfk_2` FOREIGN KEY (`id_role`) REFERENCES `roles` (`id_role`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dữ liệu mẫu cho roles (vai trò)
INSERT INTO `roles` (`ten_role`, `mo_ta`) VALUES
('Super Admin', 'Toàn quyền trên hệ thống'),
('Shop Manager', 'Quản lý sản phẩm, đơn hàng, khách hàng'),
('Content Manager', 'Quản lý nội dung, danh mục, bình luận'),
('Support Staff', 'Hỗ trợ khách hàng, xử lý đơn hàng');

-- Dữ liệu mẫu cho permissions (quyền hạn)
INSERT INTO `permissions` (`ten_permission`, `ma_permission`, `mo_ta`, `nhom_permission`) VALUES
-- Quyền quản lý sản phẩm
('Xem sản phẩm', 'product_view', 'Xem danh sách và chi tiết sản phẩm', 'products'),
('Thêm sản phẩm', 'product_add', 'Thêm sản phẩm mới', 'products'),
('Sửa sản phẩm', 'product_edit', 'Chỉnh sửa thông tin sản phẩm', 'products'),
('Xóa sản phẩm', 'product_delete', 'Xóa sản phẩm', 'products'),

-- Quyền quản lý danh mục
('Xem danh mục', 'category_view', 'Xem danh sách danh mục', 'categories'),
('Thêm danh mục', 'category_add', 'Thêm danh mục mới', 'categories'),
('Sửa danh mục', 'category_edit', 'Chỉnh sửa thông tin danh mục', 'categories'),
('Xóa danh mục', 'category_delete', 'Xóa danh mục', 'categories'),

-- Quyền quản lý đơn hàng
('Xem đơn hàng', 'order_view', 'Xem danh sách và chi tiết đơn hàng', 'orders'),
('Cập nhật trạng thái đơn hàng', 'order_update_status', 'Thay đổi trạng thái đơn hàng', 'orders'),
('Hủy đơn hàng', 'order_cancel', 'Hủy đơn hàng', 'orders'),
('Xóa đơn hàng', 'order_delete', 'Xóa đơn hàng khỏi hệ thống', 'orders'),

-- Quyền quản lý khách hàng
('Xem khách hàng', 'customer_view', 'Xem danh sách và thông tin khách hàng', 'customers'),
('Thêm khách hàng', 'customer_add', 'Thêm khách hàng mới', 'customers'),
('Sửa khách hàng', 'customer_edit', 'Chỉnh sửa thông tin khách hàng', 'customers'),
('Xóa khách hàng', 'customer_delete', 'Xóa khách hàng', 'customers'),
('Khóa/mở khóa khách hàng', 'customer_toggle_status', 'Thay đổi trạng thái khóa của khách hàng', 'customers'),

-- Quyền quản lý admin
('Xem admin', 'admin_view', 'Xem danh sách và thông tin admin', 'admins'),
('Thêm admin', 'admin_add', 'Thêm tài khoản admin mới', 'admins'),
('Sửa admin', 'admin_edit', 'Chỉnh sửa thông tin admin', 'admins'),
('Xóa admin', 'admin_delete', 'Xóa tài khoản admin', 'admins'),

-- Quyền quản lý vai trò và phân quyền
('Xem vai trò và quyền hạn', 'role_view', 'Xem danh sách vai trò và quyền hạn', 'permissions'),
('Thêm vai trò', 'role_add', 'Thêm vai trò mới', 'permissions'),
('Sửa vai trò', 'role_edit', 'Chỉnh sửa thông tin vai trò', 'permissions'),
('Xóa vai trò', 'role_delete', 'Xóa vai trò', 'permissions'),
('Phân quyền', 'permission_assign', 'Phân quyền cho vai trò', 'permissions'),

-- Quyền quản lý thống kê và báo cáo
('Xem thống kê', 'report_view', 'Xem các báo cáo thống kê', 'reports'),
('Xuất báo cáo', 'report_export', 'Xuất báo cáo', 'reports'),

-- Quyền quản lý hệ thống
('Quản lý cài đặt', 'setting_manage', 'Quản lý cài đặt hệ thống', 'settings'),
('Xem nhật ký hoạt động', 'log_view', 'Xem nhật ký hoạt động của hệ thống', 'settings');

-- Phân quyền cho vai trò Super Admin (toàn quyền)
INSERT INTO `role_permissions` (`id_role`, `id_permission`)
SELECT 1, id_permission FROM `permissions`;

-- Phân quyền cho vai trò Shop Manager
INSERT INTO `role_permissions` (`id_role`, `id_permission`)
SELECT 2, id_permission FROM `permissions` 
WHERE ma_permission IN ('product_view', 'product_add', 'product_edit', 'product_delete', 
                       'category_view', 'category_add', 'category_edit', 'category_delete',
                       'order_view', 'order_update_status', 'order_cancel',
                       'customer_view', 'customer_edit');

-- Phân quyền cho vai trò Content Manager
INSERT INTO `role_permissions` (`id_role`, `id_permission`)
SELECT 3, id_permission FROM `permissions` 
WHERE ma_permission IN ('product_view', 'product_add', 'product_edit',
                       'category_view', 'category_add', 'category_edit');

-- Phân quyền cho vai trò Support Staff
INSERT INTO `role_permissions` (`id_role`, `id_permission`)
SELECT 4, id_permission FROM `permissions` 
WHERE ma_permission IN ('product_view', 'order_view', 'order_update_status',
                       'customer_view');

-- Gán Super Admin cho người dùng admin hiện có
INSERT INTO `admin_roles` (`id_admin`, `id_role`)
SELECT id_admin, 1 FROM `admin` WHERE cap_bac = 3;
