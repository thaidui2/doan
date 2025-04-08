-- Bảng lưu thông tin mã khuyến mãi
CREATE TABLE `khuyen_mai` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ma_code` varchar(50) NOT NULL,
  `loai_giam_gia` tinyint(1) NOT NULL COMMENT '1: Phần trăm, 2: Số tiền cố định',
  `gia_tri` decimal(10,2) NOT NULL,
  `ngay_bat_dau` datetime NOT NULL,
  `ngay_ket_thuc` datetime NOT NULL,
  `so_luong` int(11) NOT NULL DEFAULT 1,
  `so_luong_da_dung` int(11) NOT NULL DEFAULT 0,
  `gia_tri_don_toi_thieu` decimal(10,2) NOT NULL DEFAULT 0,
  `gia_tri_giam_toi_da` decimal(10,2) NOT NULL DEFAULT 0,
  `mo_ta` text DEFAULT NULL,
  `id_nguoiban` int(11) NOT NULL,
  `ap_dung_sanpham` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0: Áp dụng tất cả sản phẩm, 1: Áp dụng sản phẩm cụ thể',
  `trang_thai` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0: Không kích hoạt, 1: Đang hoạt động, 2: Hết hạn',
  `ngay_tao` timestamp NOT NULL DEFAULT current_timestamp(),
  `ngay_capnhat` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `ma_code_nguoiban` (`ma_code`, `id_nguoiban`),
  KEY `id_nguoiban` (`id_nguoiban`),
  CONSTRAINT `khuyen_mai_ibfk_1` FOREIGN KEY (`id_nguoiban`) REFERENCES `users` (`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Bảng lưu các sản phẩm được áp dụng khuyến mãi (nếu ap_dung_sanpham = 1)
CREATE TABLE `khuyen_mai_sanpham` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_khuyen_mai` int(11) NOT NULL,
  `id_sanpham` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_khuyen_mai_sanpham` (`id_khuyen_mai`,`id_sanpham`),
  KEY `id_sanpham` (`id_sanpham`),
  CONSTRAINT `khuyen_mai_sanpham_ibfk_1` FOREIGN KEY (`id_khuyen_mai`) REFERENCES `khuyen_mai` (`id`) ON DELETE CASCADE,
  CONSTRAINT `khuyen_mai_sanpham_ibfk_2` FOREIGN KEY (`id_sanpham`) REFERENCES `sanpham` (`id_sanpham`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Bảng ghi lịch sử sử dụng mã khuyến mãi
CREATE TABLE `khuyen_mai_lichsu` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_khuyen_mai` int(11) NOT NULL,
  `id_donhang` int(11) NOT NULL,
  `id_nguoidung` int(11) NOT NULL,
  `gia_tri_giam` decimal(10,2) NOT NULL,
  `ngay_su_dung` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `id_khuyen_mai` (`id_khuyen_mai`),
  KEY `id_donhang` (`id_donhang`),
  KEY `id_nguoidung` (`id_nguoidung`),
  CONSTRAINT `khuyen_mai_lichsu_ibfk_1` FOREIGN KEY (`id_khuyen_mai`) REFERENCES `khuyen_mai` (`id`),
  CONSTRAINT `khuyen_mai_lichsu_ibfk_2` FOREIGN KEY (`id_donhang`) REFERENCES `donhang` (`id_donhang`),
  CONSTRAINT `khuyen_mai_lichsu_ibfk_3` FOREIGN KEY (`id_nguoidung`) REFERENCES `users` (`id_user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;