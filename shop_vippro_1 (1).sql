-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1
-- Thời gian đã tạo: Th5 20, 2025 lúc 04:35 PM
-- Phiên bản máy phục vụ: 10.4.32-MariaDB
-- Phiên bản PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `shop_vippro_1`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `danhgia`
--

CREATE TABLE `danhgia` (
  `id` int(11) NOT NULL,
  `id_sanpham` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `id_donhang` int(11) DEFAULT NULL,
  `diem` tinyint(4) NOT NULL DEFAULT 5,
  `noi_dung` text DEFAULT NULL,
  `hinh_anh` varchar(255) DEFAULT NULL,
  `khuyen_dung` tinyint(1) DEFAULT 0,
  `ngay_danhgia` timestamp NOT NULL DEFAULT current_timestamp(),
  `trang_thai` tinyint(1) DEFAULT 1 COMMENT '0: Ẩn, 1: Hiển thị'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `danhgia`
--

INSERT INTO `danhgia` (`id`, `id_sanpham`, `id_user`, `id_donhang`, `diem`, `noi_dung`, `hinh_anh`, `khuyen_dung`, `ngay_danhgia`, `trang_thai`) VALUES
(9, 27, 2, 27, 5, 'skibidi', NULL, 1, '2025-04-20 06:55:35', 1),
(10, 27, 2, 29, 5, 'ok', '1745132340_68049b34797ab.jpg', 1, '2025-04-20 06:59:00', 1),
(11, 25, 2, 30, 5, 'test', NULL, 1, '2025-04-20 08:42:59', 1);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `danhmuc`
--

CREATE TABLE `danhmuc` (
  `id` int(11) NOT NULL,
  `ten` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `mo_ta` text DEFAULT NULL,
  `hinhanh` varchar(255) DEFAULT NULL,
  `danhmuc_cha` int(11) DEFAULT NULL,
  `thu_tu` int(11) DEFAULT 0,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `trang_thai` tinyint(1) DEFAULT 1 COMMENT '0: Ẩn, 1: Hiển thị',
  `ngay_tao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `danhmuc`
--

INSERT INTO `danhmuc` (`id`, `ten`, `slug`, `mo_ta`, `hinhanh`, `danhmuc_cha`, `thu_tu`, `meta_title`, `meta_description`, `trang_thai`, `ngay_tao`) VALUES
(1, 'Giày Nam', 'giay-nam', 'Các mẫu giày dành cho nam', 'uploads/categories/1747728218_Geometric Bearded Man Logo.jpg', NULL, 0, '', '', 1, '2025-04-17 06:03:11'),
(2, 'Giày Nữ', 'giay-nu', 'Các mẫu giày dành cho nữ', 'uploads/categories/1747728199_Premium Vector _ Hair woman and face logo and symbols.jpg', NULL, 0, '', '', 1, '2025-04-17 06:03:11'),
(3, 'Giày Thể Thao', 'giay-the-thao', 'Các mẫu giày thể thao', 'uploads/categories/1747728182_Elevator Shoes IN 2023.jpg', NULL, 0, '', '', 1, '2025-04-17 06:03:11'),
(4, 'Giày Thời Trang', 'giay-thoi-trang', 'Các mẫu giày thời trang', 'uploads/categories/1747728165_danh_muc_thoi_trang.png', NULL, 0, '', '', 1, '2025-04-17 06:03:11'),
(5, 'Giày Trẻ Em', 'giay-tre-em', 'Các mẫu giày dành cho trẻ em', 'uploads/categories/1747728153_download (3).jpg', NULL, 0, '', '', 1, '2025-04-17 06:03:11'),
(7, 'dép', 'd', '', 'uploads/categories/1745130294_Sandal Icon - Free PNG & SVG 315091 - Noun Project.jpg', NULL, 2, '', '0', 1, '2025-04-20 06:24:54');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `donhang`
--

CREATE TABLE `donhang` (
  `id` int(11) NOT NULL,
  `ma_donhang` varchar(20) NOT NULL,
  `id_user` int(11) DEFAULT NULL,
  `ho_ten` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `sodienthoai` varchar(20) NOT NULL,
  `diachi` text NOT NULL,
  `tinh_tp` varchar(100) NOT NULL,
  `quan_huyen` varchar(100) NOT NULL,
  `phuong_xa` varchar(100) DEFAULT NULL,
  `tong_tien` decimal(12,2) NOT NULL DEFAULT 0.00,
  `phi_vanchuyen` decimal(10,2) DEFAULT 0.00,
  `giam_gia` decimal(10,2) DEFAULT 0.00,
  `thanh_tien` decimal(12,2) NOT NULL DEFAULT 0.00,
  `ma_giam_gia` varchar(50) DEFAULT NULL,
  `phuong_thuc_thanh_toan` varchar(50) DEFAULT 'cod',
  `ma_giao_dich` varchar(100) DEFAULT NULL,
  `trang_thai_thanh_toan` tinyint(1) DEFAULT 0,
  `trang_thai_don_hang` tinyint(1) DEFAULT 1 COMMENT '1: Chờ xác nhận, 2: Đã xác nhận, 3: Đang giao hàng, 4: Đã giao, 5: Đã hủy',
  `ghi_chu` text DEFAULT NULL,
  `ngay_dat` timestamp NOT NULL DEFAULT current_timestamp(),
  `ngay_capnhat` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `donhang`
--

INSERT INTO `donhang` (`id`, `ma_donhang`, `id_user`, `ho_ten`, `email`, `sodienthoai`, `diachi`, `tinh_tp`, `quan_huyen`, `phuong_xa`, `tong_tien`, `phi_vanchuyen`, `giam_gia`, `thanh_tien`, `ma_giam_gia`, `phuong_thuc_thanh_toan`, `ma_giao_dich`, `trang_thai_thanh_toan`, `trang_thai_don_hang`, `ghi_chu`, `ngay_dat`, `ngay_capnhat`) VALUES
(1, 'BUG250418501A4', 2, 'nguyen xuan thai', 'thai@gmail.com', '0123456789', 'đông tiến', 'Đà Nẵng', 'Liên Chiểu', '0', 700000.00, 30000.00, 0.00, 730000.00, '', '0', NULL, 0, 4, '', '2025-04-18 03:37:50', '2025-04-18 04:00:58'),
(2, 'BUG250418043D0', 2, 'nguyen xuan thai', 'thai@gmail.com', '0123456789', 'đông tiến', 'Đắk Lắk', 'Krông Pắc', '0', 770000.00, 30000.00, 770000.00, 30000.00, 'test1', '0', NULL, 0, 5, '\nĐơn hàng đã bị hủy bởi khách hàng với lý do: ok', '2025-04-18 08:06:54', '2025-04-18 10:09:50'),
(3, 'BUG250418591EB', 2, 'nguyen xuan thai', 'thai@gmail.com', '0123456789', 'đông tiến', 'Đà Nẵng', 'Liên Chiểu', '0', 770000.00, 30000.00, 770000.00, 30000.00, 'test1', 'vnpay', '14913820', 1, 2, '', '2025-04-18 08:12:09', '2025-04-18 08:13:25'),
(4, 'BUG250418GH732', 5, 'Nguyễn Văn A', 'nguyenvana@example.com', '0987654321', '15 Nguyễn Huệ', 'Hồ Chí Minh', 'Quận 1', NULL, 3200000.00, 0.00, 0.00, 3200000.00, NULL, 'cod', NULL, 0, 2, NULL, '2025-04-18 10:46:04', NULL),
(5, 'BUG250418TY901', 6, 'Trần Thị B', 'tranthib@example.com', '0912345678', '27 Lê Lợi', 'Hà Nội', 'Hoàn Kiếm', NULL, 1800000.00, 30000.00, 0.00, 1830000.00, NULL, 'vnpay', NULL, 1, 3, NULL, '2025-04-18 10:46:04', NULL),
(6, 'BUG250418QZ345', 7, 'Phạm Tuấn C', 'phamtuanc@example.com', '0956781234', '72 Trần Phú', 'Đà Nẵng', 'Hải Châu', NULL, 899000.00, 30000.00, 0.00, 929000.00, NULL, 'bank_transfer', NULL, 1, 4, NULL, '2025-04-18 10:46:04', NULL),
(11, 'BUG25041869772', NULL, 'nguyen van a', 'admin@gmail.com', '0123456782', 'ok', 'Bạc Liêu', 'Hoà Bình', '0', 3200000.00, 30000.00, 0.00, 3230000.00, '', '0', NULL, 0, 1, '', '2025-04-18 11:11:01', NULL),
(12, 'BUG250418ebe4d', NULL, 'nguyen van a', 'a@gmail.com', '0123456781', 'đông tiến', 'Đắk Lắk', 'Krông Pắc', '0', 1650000.00, 30000.00, 0.00, 1680000.00, '', '0', NULL, 0, 1, '', '2025-04-18 13:28:13', NULL),
(13, 'BUG250418e544f', 2, 'nguyen xuan thai', 'tobenha4@gmail.com', '0123456789', 'đông tiến', 'Cà Mau', 'Đầm Dơi', '0', 1980000.00, 30000.00, 0.00, 2010000.00, '', '0', NULL, 0, 1, '', '2025-04-18 15:06:13', NULL),
(14, 'BUG2504189fa0a', 2, 'nguyen xuan thai', 'tobenha4@gmail.com', '0123456789', 'đông tiến', 'Đắk Lắk', 'Krông Pắc', '0', 1650000.00, 30000.00, 0.00, 1680000.00, '', '0', NULL, 0, 1, '', '2025-04-18 15:12:34', NULL),
(15, 'BUG25041806F5C', 2, 'nguyen xuan thai', 'tobenha4@gmail.com', '0123456789', 'đông tiến', 'Bạc Liêu', 'Giá Rai', 'Phong Thạnh', 1980000.00, 0.00, 0.00, 1980000.00, '', '0', NULL, 0, 1, '', '2025-04-18 15:25:24', NULL),
(16, 'BUG250418C366C', 2, 'nguyen xuan thai', 'tobenha4@gmail.com', '0123456789', 'đông tiến', 'Cần Thơ', 'Phong Điền', 'Phong Điền', 1650000.00, 0.00, 0.00, 1650000.00, '', '0', NULL, 0, 1, '', '2025-04-18 15:29:01', NULL),
(17, 'BUG2504181C3A6', 2, 'nguyen xuan thai', 'tobenha4@gmail.com', '0123456789', 'đông tiến', 'Đà Nẵng', 'Ngũ Hành Sơn', 'Hoà Quý', 1650000.00, 0.00, 0.00, 1650000.00, '', '0', '14914525', 1, 2, '', '2025-04-18 15:29:15', '2025-04-18 15:30:12'),
(18, 'BUG250418E52FD', 2, 'nguyen xuan thai', 'tobenha4@gmail.com', '0123456789', 'đông tiến', 'Đà Nẵng', 'Ngũ Hành Sơn', 'Hoà Hải', 1750000.00, 0.00, 0.00, 1750000.00, '', '0', NULL, 0, 5, '\nĐơn hàng đã bị hủy bởi khách hàng với lý do: Đổi ý, không muốn mua nữa', '2025-04-18 15:33:14', '2025-04-18 15:47:14'),
(19, 'BUG250418FC036', 2, 'nguyen xuan thai', 'tobenha4@gmail.com', '0123456789', 'đông tiến', 'Điện Biên', 'Nậm Pồ', 'Pa Tần', 1200000.00, 0.00, 0.00, 1200000.00, '', '0', NULL, 0, 5, '\nĐơn hàng đã bị hủy bởi khách hàng với lý do: Muốn thay đổi phương thức thanh toán', '2025-04-18 15:36:38', '2025-04-18 15:47:11'),
(20, 'BUG2504189E168', 2, 'nguyen xuan thai', 'tobenha4@gmail.com', '0123456789', 'đông tiến', 'Đắk Lắk', 'Krông Pắc', 'Phước An', 1980000.00, 0.00, 0.00, 1980000.00, '', '0', NULL, 0, 5, '\nĐơn hàng đã bị hủy bởi khách hàng với lý do: Đổi ý, không muốn mua nữa', '2025-04-18 15:37:31', '2025-04-18 15:47:07'),
(21, 'BUG2504186220A', 2, 'nguyen xuan thai', 'tobenha4@gmail.com', '0123456789', 'đông tiến', 'Đắk Nông', 'Krông Nô', 'Quảng Phú', 1980000.00, 0.00, 0.00, 1980000.00, '', '0', NULL, 0, 5, '\nĐơn hàng đã bị hủy bởi khách hàng với lý do: test', '2025-04-18 15:39:48', '2025-04-18 15:47:04'),
(22, 'BUG250418105E0', 2, 'nguyen xuan thai', 'tobenha4@gmail.com', '0123456789', 'đông tiến', 'Bến Tre', 'Chợ Lách', 'Long Thới', 3500000.00, 0.00, 0.00, 3500000.00, '', '0', NULL, 0, 4, '', '2025-04-18 15:46:43', '2025-04-19 07:39:04'),
(23, 'BUG250419A11D8', 2, 'nguyen xuan thai', 'tobenha4@gmail.com', '0123456789', 'đông tiến', 'Đắk Lắk', 'Krông Năng', 'Krông Năng', 1650000.00, 0.00, 0.00, 1650000.00, '', '0', NULL, 0, 4, '', '2025-04-19 13:22:34', '2025-04-19 13:22:47'),
(24, 'BUG250419FBE99', 2, 'nguyen xuan thai', 'tobenha4@gmail.com', '0123456789', 'đông tiến', 'Đà Nẵng', 'Liên Chiểu', 'Hòa Hiệp Nam', 1200000.00, 0.00, 0.00, 1200000.00, '', '0', '14915628', 1, 4, '', '2025-04-19 13:30:19', '2025-04-19 13:31:19'),
(25, 'BUG250419693C7', 2, 'nguyen xuan thai', 'tobenha4@gmail.com', '0123456789', 'đông tiến', 'Đắk Lắk', 'Krông Pắc', 'KRông Búk', 1200000.00, 0.00, 0.00, 1200000.00, '', '0', NULL, 0, 5, '', '2025-04-19 13:52:08', '2025-04-19 14:05:21'),
(26, 'BUG250419C6DDB', 2, 'nguyen xuan thai', 'tobenha4@gmail.com', '0123456789', 'đông tiến', 'Đắk Nông', 'Krông Nô', 'Nâm Nung', 1200000.00, 0.00, 0.00, 1200000.00, '', '0', NULL, 0, 1, '', '2025-04-19 13:52:27', '2025-04-19 14:11:05'),
(27, 'BUG250419B61D9', 2, 'nguyen xuan thai', 'tobenha4@gmail.com', '0123456789', 'đông tiến', 'Cần Thơ', 'Phong Điền', 'Tân Thới', 1650000.00, 0.00, 0.00, 1650000.00, '', '0', NULL, 0, 4, '', '2025-04-19 14:23:01', '2025-04-19 14:23:13'),
(28, 'BUG250419626FF', 9, 'thaidui', 'thaidui@gmail.com', '0123456789', '69', 'Đắk Nông', 'Gia Nghĩa', 'Nghĩa Đức', 1650000.00, 0.00, 0.00, 1650000.00, '', '0', NULL, 0, 4, 'test\r\n', '2025-04-19 14:37:42', '2025-04-19 14:38:30'),
(29, 'BUG2504206F9DA', 2, 'nguyen xuan thai', 'tobenha4@gmail.com', '0123456789', 'đông tiến', 'Đắk Lắk', 'Krông Pắc', 'Hòa Tiến', 1650000.00, 0.00, 0.00, 1650000.00, '', '0', NULL, 0, 4, '', '2025-04-20 06:57:44', '2025-04-20 06:57:58'),
(30, 'BUG25042072F6F', 2, 'nguyen xuan thai', 'tobenha4@gmail.com', '0123456789', 'đông tiến', 'Bến Tre', 'Bình Đại', 'Đại Hòa Lộc', 1750000.00, 0.00, 1750000.00, 0.00, 'testadmin1', '0', NULL, 0, 4, '', '2025-04-20 08:38:04', '2025-04-20 08:42:08'),
(31, 'BUG25042074A43', 2, 'nguyen xuan thai', 'tobenha4@gmail.com', '0123456789', 'test', 'Bắc Ninh', 'Tiên Du', 'Lạc Vệ', 829000.00, 0.00, 0.00, 829000.00, '', '0', '14916628', 1, 4, '', '2025-04-20 08:46:37', '2025-04-23 10:00:29'),
(32, 'BUG2504204729D60', 2, 'nguyen xuan thai', 'tobenha4@gmail.com', '0123456789', 'đông tiến', 'Bắc Kạn', 'Chợ Đồn', '0', 829000.00, 30000.00, 0.00, 859000.00, '', 'vnpay', NULL, 0, 1, '', '2025-04-20 08:57:52', NULL),
(33, 'BUG250420534F98E', 2, 'nguyen xuan thai', 'tobenha4@gmail.com', '0123456789', 'đông tiến', 'Đắk Lắk', 'Krông Năng', '0', 829000.00, 30000.00, 0.00, 859000.00, '', 'vnpay', '14916646', 1, 2, '', '2025-04-20 08:58:54', '2025-04-20 08:59:30'),
(34, 'BUG250422033A390', 2, 'nguyen xuan thai', 'tobenha4@gmail.com', '0123456789', 'đông tiến', 'Đà Nẵng', 'Liên Chiểu', '0', 1650000.00, 30000.00, 0.00, 1680000.00, '', 'vnpay', NULL, 0, 1, '', '2025-04-22 08:20:33', NULL),
(35, 'BUG2504232474BE3', NULL, 'lâm', 'lam@gmail.com', '0982858305', '96', 'Cao Bằng', 'Nguyên Bình', '0', 2200000.00, 30000.00, 0.00, 2230000.00, '', 'vnpay', NULL, 0, 1, '', '2025-04-23 09:57:27', NULL),
(36, 'BUG2504233452CDF', NULL, 'lâm', 'lam@gmail.com', '0223456784', '99', 'Đà Nẵng', 'Liên Chiểu', '0', 2500000.00, 30000.00, 0.00, 2530000.00, '', 'vnpay', '14922639', 1, 2, '', '2025-04-23 09:59:05', '2025-04-23 09:59:36'),
(37, 'BUG250507050AC38', 2, 'nguyen xuan thai', 'tobenha4@gmail.com', '0123456789', 'đông tiến', 'Bạc Liêu', 'Giá Rai', '0', 1658000.00, 30000.00, 0.00, 1688000.00, '', 'cod', NULL, 0, 1, '', '2025-05-07 08:04:10', NULL),
(38, 'BUG250507352F26A', 2, 'nguyen xuan thai', 'tobenha4@gmail.com', '0123456789', 'đông tiến', 'Đà Nẵng', 'Sơn Trà', '0', 2200000.00, 30000.00, 0.00, 2230000.00, '', 'cod', NULL, 0, 1, '', '2025-05-07 08:09:12', NULL),
(39, 'BUG250507396F370', 2, 'nguyen xuan thai', 'tobenha4@gmail.com', '0123456789', 'đông tiến', 'Bạc Liêu', 'Hoà Bình', '0', 900000.00, 30000.00, 0.00, 930000.00, '', 'cod', NULL, 0, 1, '', '2025-05-07 08:09:56', NULL),
(40, 'BUG2505076452BB6', 2, 'nguyen xuan thai', 'tobenha4@gmail.com', '0123456789', 'đông tiến', 'Đắk Nông', 'Gia Nghĩa', '0', 2200000.00, 30000.00, 0.00, 2230000.00, '', 'cod', NULL, 0, 1, '', '2025-05-07 08:30:45', NULL),
(41, 'BUG25050753696EF', 2, 'nguyen xuan thai', 'tobenha4@gmail.com', '0123456789', 'đông tiến', 'Đắk Lắk', 'Krông Năng', '0', 900000.00, 30000.00, 0.00, 930000.00, '', 'cod', NULL, 0, 1, '', '2025-05-07 09:02:16', NULL),
(42, 'BUG2505079620609', 2, 'nguyen xuan thai', 'tobenha4@gmail.com', '0123456789', 'đông tiến', 'Cần Thơ', 'Thốt Nốt', '0', 2200000.00, 30000.00, 0.00, 2230000.00, '', 'cod', NULL, 0, 1, '', '2025-05-07 09:42:42', NULL),
(43, 'BUG250507044DAE3', NULL, 'chưa đăng nhập', 'chuadangnhap@gmail.com', '0475729543', 'làng xóm', 'Đắk Lắk', 'Krông Năng', '0', 2500000.00, 30000.00, 0.00, 2530000.00, '', 'vnpay', '14943891', 1, 2, 'chưa đăng nhập', '2025-05-07 09:44:04', '2025-05-07 09:45:09'),
(44, 'BUG25050790636D5', 2, 'nguyen xuan thai', 'tobenha4@gmail.com', '0123456789', 'đông tiến', 'Bà Rịa - Vũng Tàu', 'Long Điền', '0', 2500000.00, 30000.00, 0.00, 2530000.00, '', 'cod', NULL, 0, 1, '', '2025-05-07 09:58:26', NULL),
(45, 'BUG25050721890C1', 2, 'nguyen xuan thai', 'tobenha4@gmail.com', '0123456789', 'đông tiến', 'Đà Nẵng', 'Ngũ Hành Sơn', '0', 2500000.00, 30000.00, 0.00, 2530000.00, '', 'cod', NULL, 0, 1, '', '2025-05-07 10:03:38', NULL),
(46, 'BUG250507419655F', 2, 'nguyen xuan thai', 'tobenha4@gmail.com', '0123456789', 'đông tiến', 'Cần Thơ', 'Thốt Nốt', '0', 2500000.00, 30000.00, 0.00, 2530000.00, '', 'cod', NULL, 0, 1, '', '2025-05-07 10:06:59', NULL),
(47, 'BUG250507557462F', 2, 'nguyen xuan thai', 'tobenha4@gmail.com', '0123456789', 'đông tiến', 'Đắk Lắk', 'Lắk', '0', 7500000.00, 30000.00, 0.00, 7530000.00, '', 'cod', NULL, 0, 1, '', '2025-05-07 10:09:17', NULL),
(48, 'BUG250507857526B', 2, 'nguyen xuan thai', 'tobenha4@gmail.com', '0123456789', 'đông tiến', 'Đà Nẵng', 'Thanh Khê', '0', 5000000.00, 30000.00, 0.00, 5030000.00, '', 'cod', NULL, 0, 1, 'ok', '2025-05-07 10:14:17', NULL),
(49, 'BUG2505079948024', 2, 'nguyen xuan thai', 'tobenha4@gmail.com', '0123456789', 'đông tiến', 'Đắk Lắk', 'Lắk', '0', 7500000.00, 30000.00, 0.00, 7530000.00, '', 'cod', NULL, 0, 1, '', '2025-05-07 10:16:34', NULL),
(50, 'BUG2505070818060', 2, 'nguyen xuan thai', 'tobenha4@gmail.com', '0123456789', 'đông tiến', 'Đắk Nông', 'Krông Nô', '0', 2200000.00, 30000.00, 0.00, 2230000.00, '', 'cod', NULL, 0, 1, '', '2025-05-07 10:18:01', NULL),
(51, 'BUG250516639E751', 2, 'nguyen xuan thai', 'tobenha4@gmail.com', '0123456789', 'đông tiến', 'Cà Mau', 'Đầm Dơi', '0', 2200000.00, 30000.00, 0.00, 2230000.00, '', 'cod', NULL, 0, 4, '', '2025-05-16 10:00:39', '2025-05-16 10:15:59'),
(52, 'BUG250516377303E', 2, 'nguyen xuan thai', 'tobenha4@gmail.com', '0123456789', 'đông tiến', 'Bắc Ninh', 'Từ Sơn', '0', 2200000.00, 30000.00, 2200000.00, 30000.00, 'testadmin1', 'vnpay', '14961586', 1, 2, '', '2025-05-16 14:06:17', '2025-05-16 14:07:01'),
(53, 'BUG2505161080B1B', 9, 'thaidui', 'thaidui@gmail.com', '0123456789', 'xóm làng', 'Đắk Nông', 'Gia Nghĩa', '0', 2200000.00, 30000.00, 2200000.00, 30000.00, 'testadmin1', 'cod', NULL, 0, 1, '', '2025-05-16 16:15:08', NULL),
(54, 'BUG250516939FD7C', 9, 'thaidui', 'thaidui@gmail.com', '0123456789', 'làng xóm', 'Đắk Nông', 'Krông Nô', '0', 900000.00, 30000.00, 0.00, 930000.00, '', 'cod', NULL, 0, 4, '', '2025-05-16 16:28:59', '2025-05-16 16:30:19'),
(55, 'BUG250516738B7A7', 9, 'thaidui', 'thaidui@gmail.com', '0123456789', 'xóm làng', 'Cần Thơ', 'Thới Lai', '0', 1500000.00, 30000.00, 1500000.00, 30000.00, 'testadmin1', 'cod', NULL, 0, 1, '', '2025-05-16 16:58:58', NULL),
(56, 'BUG250516827CA07', 9, 'thaidui', 'thaidui@gmail.com', '0123456789', 'xóm làng', 'Cần Thơ', 'Thới Lai', '0', 1500000.00, 30000.00, 1500000.00, 30000.00, 'testadmin1', 'cod', NULL, 0, 1, '', '2025-05-16 17:00:27', NULL),
(57, 'BUG2505179877FF4', 11, 'thai', 'thai2@gmail.com', '0123456781', 'xóm làng', 'Đồng Tháp', 'Tháp Mười', '0', 2200000.00, 30000.00, 2200000.00, 30000.00, 'testadmin1', 'cod', NULL, 0, 5, '', '2025-05-17 07:13:07', '2025-05-17 09:56:29'),
(58, 'BUG250517385EAA1', 11, 'thai', 'thai2@gmail.com', '0123456781', 'xóm làng', 'Đồng Nai', 'Trảng Bom', '0', 2200000.00, 30000.00, 2200000.00, 30000.00, 'testadmin1', 'cod', NULL, 0, 5, '', '2025-05-17 07:19:45', '2025-05-17 09:53:17'),
(59, 'BUG250517833F835', 11, 'thai', 'thai2@gmail.com', '0123456781', 'xóm làng', 'Hưng Yên', 'Văn Giang', '0', 2200000.00, 30000.00, 0.00, 2230000.00, '', 'cod', NULL, 0, 4, '', '2025-05-17 09:57:13', '2025-05-17 09:57:37'),
(60, 'BUG2505179216ABF', NULL, 'không biết', 'idk@gmail.com', '0987654421', 'xóm làng', 'Đắk Lắk', 'Lắk', '0', 2200000.00, 30000.00, 2200000.00, 30000.00, 'testadmin1', 'vnpay', '14963031', 1, 2, '', '2025-05-17 15:32:01', '2025-05-17 15:35:48'),
(61, 'BUG2505183842782', 11, 'thai', 'thai2@gmail.com', '0123456781', 'xóm làng', 'Quảng Ngãi', 'Trà Bồng', '0', 900000.00, 30000.00, 900000.00, 30000.00, 'test2', 'cod', NULL, 0, 1, '', '2025-05-18 14:09:44', NULL),
(62, 'BUG250520510F406', NULL, 'test thai ', 'mail@gmmail.com', '0123456789', '@@', 'Cần Thơ', 'Thới Lai', '0', 2200000.00, 30000.00, 2200000.00, 30000.00, 'test2', 'vnpay', '14968035', 1, 2, 'test', '2025-05-20 08:08:30', '2025-05-20 08:09:57'),
(63, 'BUG2505202525C94', 2, 'nguyen xuan thai', 'tobenha4@gmail.com', '0123456789', 'đông tiến', 'Cao Bằng', 'Thạch An', '0', 1658000.00, 30000.00, 1658000.00, 30000.00, 'test2', 'cod', NULL, 0, 2, 'test', '2025-05-20 13:20:52', '2025-05-20 13:21:35'),
(64, 'BUG2505207732A61', 3, 'lò vi sóng', 'song@gmail.com', '0223456789', 'làng xóm', 'Lào Cai', 'Si Ma Cai', '0', 2700000.00, 30000.00, 2700000.00, 30000.00, 'test2', 'cod', NULL, 0, 1, 'test', '2025-05-20 14:02:53', NULL),
(65, 'BUG25052067164A1', 3, 'lò vi sóng', 'song@gmail.com', '0223456789', 'làng xóm', 'Nghệ An', 'Quỳ Hợp', '0', 900000.00, 30000.00, 900000.00, 30000.00, 'test2', 'cod', NULL, 0, 1, 'xóm phường', '2025-05-20 14:34:31', NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `donhang_chitiet`
--

CREATE TABLE `donhang_chitiet` (
  `id` int(11) NOT NULL,
  `id_donhang` int(11) NOT NULL,
  `id_sanpham` int(11) NOT NULL,
  `id_bienthe` int(11) DEFAULT NULL,
  `tensp` varchar(255) NOT NULL,
  `thuoc_tinh` varchar(255) DEFAULT NULL COMMENT 'Màu sắc, kích thước... khi mua',
  `gia` decimal(12,2) NOT NULL,
  `soluong` int(11) NOT NULL,
  `thanh_tien` decimal(12,2) NOT NULL,
  `da_danh_gia` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `donhang_chitiet`
--

INSERT INTO `donhang_chitiet` (`id`, `id_donhang`, `id_sanpham`, `id_bienthe`, `tensp`, `thuoc_tinh`, `gia`, `soluong`, `thanh_tien`, `da_danh_gia`) VALUES
(18, 18, 25, 86, 'Giày VANS OLD SKOOL LOGO CHECK BLACK', 'Size: 38, Màu: Đen', 1750000.00, 1, 1750000.00, 0),
(22, 22, 25, 90, 'Giày VANS OLD SKOOL LOGO CHECK BLACK', 'Size: 42, Màu: Đen', 1750000.00, 2, 3500000.00, 1),
(27, 27, 27, 102, 'Giày Nike Court Vision Mid Smoke Grey DN3577-002', 'Size: 41, Màu: Trắng', 1650000.00, 1, 1650000.00, 1),
(28, 28, 27, 102, 'Giày Nike Court Vision Mid Smoke Grey DN3577-002', 'Size: 41, Màu: Trắng', 1650000.00, 1, 1650000.00, 0),
(29, 29, 27, 111, 'Giày Nike Court Vision Mid Smoke Grey DN3577-002', 'Size: 45, Màu: Xanh dương', 1650000.00, 1, 1650000.00, 1),
(30, 30, 25, 86, 'Giày VANS OLD SKOOL LOGO CHECK BLACK', 'Size: 38, Màu: Đen', 1750000.00, 1, 1750000.00, 1),
(31, 31, 29, 115, 'Giày Thể Thao Biti\'s Hunter Core Nữ Màu Xám HSW009100XAM', 'Size: 43, Màu: Xám', 829000.00, 1, 829000.00, 0),
(32, 32, 29, 114, 'Giày Thể Thao Biti\'s Hunter Core Nữ Màu Xám HSW009100XAM', 'Size: 42, Màu: Trắng', 829000.00, 1, 829000.00, 0),
(33, 33, 29, 114, 'Giày Thể Thao Biti\'s Hunter Core Nữ Màu Xám HSW009100XAM', 'Size: 42, Màu: Trắng', 829000.00, 1, 829000.00, 0),
(34, 34, 27, 111, 'Giày Nike Court Vision Mid Smoke Grey DN3577-002', 'Size: 45.5, Màu: Xanh dương', 1650000.00, 1, 1650000.00, 0),
(35, 35, 31, 127, 'Giày Puma RS-X3', 'Size: 41, Màu: Xanh dương', 2200000.00, 1, 2200000.00, 0),
(36, 36, 30, 118, 'Giày Adidas Ultraboost 21', 'Size: 42, Màu: Trắng', 2500000.00, 1, 2500000.00, 0),
(37, 37, 29, 114, 'Giày Thể Thao Biti\'s Hunter Core Nữ Màu Xám HSW009100XAM', 'Size: 42, Màu: Trắng', 829000.00, 2, 1658000.00, 0),
(38, 38, 31, 127, 'Giày Puma RS-X3', 'Size: 41, Màu: Xanh dương', 2200000.00, 1, 2200000.00, 0),
(39, 39, 33, 137, 'Dép Nike Air Max', 'Size: 42, Màu: Xanh lá', 900000.00, 1, 900000.00, 0),
(40, 40, 31, 122, 'Giày Puma RS-X3', 'Size: 39, Màu: Đỏ', 2200000.00, 1, 2200000.00, 0),
(41, 41, 33, 137, 'Dép Nike Air Max', 'Size: 42, Màu: Xanh lá', 900000.00, 1, 900000.00, 0),
(42, 42, 31, 122, 'Giày Puma RS-X3', 'Size: 39, Màu: Đỏ', 2200000.00, 1, 2200000.00, 0),
(43, 43, 30, 118, 'Giày Adidas Ultraboost 21', 'Size: 42, Màu: Trắng', 2500000.00, 1, 2500000.00, 0),
(44, 44, 30, 118, 'Giày Adidas Ultraboost 21', 'Size: 42, Màu: Trắng', 2500000.00, 1, 2500000.00, 0),
(45, 45, 30, 118, 'Giày Adidas Ultraboost 21', 'Size: 42, Màu: Trắng', 2500000.00, 1, 2500000.00, 0),
(46, 46, 30, 117, 'Giày Adidas Ultraboost 21', 'Size: 41, Màu: Trắng', 2500000.00, 1, 2500000.00, 0),
(47, 47, 30, 118, 'Giày Adidas Ultraboost 21', 'Size: 42, Màu: Trắng', 2500000.00, 3, 7500000.00, 0),
(48, 48, 30, 117, 'Giày Adidas Ultraboost 21', 'Size: 41, Màu: Trắng', 2500000.00, 2, 5000000.00, 0),
(49, 49, 30, 118, 'Giày Adidas Ultraboost 21', 'Size: 42, Màu: Trắng', 2500000.00, 3, 7500000.00, 0),
(50, 50, 31, 127, 'Giày Puma RS-X3', 'Size: 41, Màu: Xanh dương', 2200000.00, 1, 2200000.00, 0),
(51, 51, 31, 122, 'Giày Puma RS-X3', 'Size: 39, Màu: Đỏ', 2200000.00, 1, 2200000.00, 0),
(52, 52, 31, 127, 'Giày Puma RS-X3', 'Size: 41, Màu: Xanh dương', 2200000.00, 1, 2200000.00, 0),
(53, 53, 31, 127, 'Giày Puma RS-X3', 'Size: 41, Màu: Xanh dương', 2200000.00, 1, 2200000.00, 0),
(54, 54, 33, 137, 'Dép Nike Air Max', 'Size: 42, Màu: Xanh lá', 900000.00, 1, 900000.00, 0),
(55, 55, 32, 130, 'Giày Converse Chuck Taylor All Star', 'Size: 40, Màu: Trắng', 1500000.00, 1, 1500000.00, 0),
(56, 56, 32, 130, 'Giày Converse Chuck Taylor All Star', 'Size: 40, Màu: Trắng', 1500000.00, 1, 1500000.00, 0),
(57, 57, 31, 127, 'Giày Puma RS-X3', 'Size: 41, Màu: Xanh dương', 2200000.00, 1, 2200000.00, 0),
(58, 58, 31, 127, 'Giày Puma RS-X3', 'Size: 41, Màu: Xanh dương', 2200000.00, 1, 2200000.00, 0),
(59, 59, 31, 127, 'Giày Puma RS-X3', 'Size: 41, Màu: Xanh dương', 2200000.00, 1, 2200000.00, 0),
(60, 60, 31, 127, 'Giày Puma RS-X3', 'Size: 41, Màu: Xanh dương', 2200000.00, 1, 2200000.00, 0),
(61, 61, 33, 137, 'Dép Nike Air Max', 'Size: 42, Màu: Xanh lá', 900000.00, 1, 900000.00, 0),
(62, 62, 31, 127, 'Giày Puma RS-X3', 'Size: 41, Màu: Xanh dương', 2200000.00, 1, 2200000.00, 0),
(63, 63, 29, 115, 'Giày Thể Thao Biti\'s Hunter Core Nữ Màu Xám HSW009100XAM', 'Size: 43, Màu: Xám', 829000.00, 2, 1658000.00, 0),
(64, 64, 31, 126, 'Giày Puma RS-X3', 'Size: 40, Màu: Xanh dương', 2200000.00, 1, 2200000.00, 0),
(65, 64, 34, 143, 'Urbas Love+ 23 - Slip On - Offwhite', 'Size: 40, Màu: Trắng', 500000.00, 1, 500000.00, 0),
(66, 65, 33, 137, 'Dép Nike Air Max', 'Size: 42, Màu: Xanh lá', 900000.00, 1, 900000.00, 0);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `donhang_lichsu`
--

CREATE TABLE `donhang_lichsu` (
  `id` int(11) NOT NULL,
  `id_donhang` int(11) NOT NULL,
  `hanh_dong` varchar(255) NOT NULL,
  `nguoi_thuchien` varchar(100) NOT NULL,
  `ghi_chu` text DEFAULT NULL,
  `ngay_thaydoi` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `donhang_lichsu`
--

INSERT INTO `donhang_lichsu` (`id`, `id_donhang`, `hanh_dong`, `nguoi_thuchien`, `ghi_chu`, `ngay_thaydoi`) VALUES
(1, 1, 'Cập nhật trạng thái', 'Super Administrator', 'Thay đổi trạng thái từ \"Đã xác nhận\" sang \"Đã xác nhận\"', '2025-04-18 04:00:39'),
(2, 1, 'Cập nhật trạng thái', 'Super Administrator', 'Thay đổi trạng thái từ \"Đã giao\" sang \"Đã giao\"', '2025-04-18 04:00:58'),
(3, 3, 'Thanh toán VNPAY thành công', 'Hệ thống', 'Thanh toán qua VNPAY với mã giao dịch: 14913820', '2025-04-18 08:13:25'),
(4, 2, 'Hủy đơn hàng', 'nguyen xuan thai', 'Đơn hàng đã bị hủy với lý do: ok', '2025-04-18 10:09:50'),
(5, 4, 'Đặt hàng', 'Nguyễn Văn A', 'Đơn hàng mới được tạo', '2025-04-18 10:46:04'),
(6, 4, 'Xác nhận', 'Super Administrator', 'Đơn hàng đã được xác nhận', '2025-04-18 10:46:04'),
(7, 5, 'Đặt hàng', 'Trần Thị B', 'Đơn hàng mới được tạo', '2025-04-18 10:46:04'),
(8, 5, 'Xác nhận', 'Nhân viên Kinh doanh', 'Đơn hàng đã được xác nhận', '2025-04-18 10:46:04'),
(9, 5, 'Đang giao', 'Super Administrator', 'Đơn hàng đã được giao cho đơn vị vận chuyển', '2025-04-18 10:46:04'),
(10, 6, 'Đặt hàng', 'Phạm Tuấn C', 'Đơn hàng mới được tạo', '2025-04-18 10:46:04'),
(11, 6, 'Xác nhận', 'Super Administrator', 'Đơn hàng đã được xác nhận', '2025-04-18 10:46:04'),
(12, 6, 'Đang giao', 'Super Administrator', 'Đơn hàng đã được giao cho đơn vị vận chuyển', '2025-04-18 10:46:04'),
(13, 6, 'Đã giao', 'Nhân viên Kinh doanh', 'Đơn hàng đã giao thành công', '2025-04-18 10:46:04'),
(14, 7, 'Cập nhật trạng thái', 'mng', 'Thay đổi trạng thái từ \"Đã hủy\" sang \"Đã hủy\"', '2025-04-18 10:49:15'),
(15, 10, 'Tạo đơn hàng', 'Khách vãng lai', 'Đơn hàng mới được tạo với phương thức thanh toán: vnpay', '2025-04-18 11:04:56'),
(16, 11, 'Tạo đơn hàng', 'Khách vãng lai', 'Đơn hàng mới được tạo với phương thức thanh toán: vnpay', '2025-04-18 11:11:01'),
(17, 12, 'Tạo đơn hàng', 'Khách vãng lai', 'Đơn hàng mới được tạo với phương thức thanh toán: vnpay', '2025-04-18 13:28:13'),
(18, 13, 'Tạo đơn hàng', 'Khách hàng', 'Đơn hàng mới được tạo với phương thức thanh toán: vnpay', '2025-04-18 15:06:13'),
(19, 14, 'Tạo đơn hàng', 'Khách hàng', 'Đơn hàng mới được tạo với phương thức thanh toán: vnpay', '2025-04-18 15:12:34'),
(20, 15, 'Tạo đơn hàng', 'Người dùng', 'Đơn hàng mới được tạo với phương thức thanh toán: vnpay', '2025-04-18 15:25:24'),
(21, 16, 'Tạo đơn hàng', 'Người dùng', 'Đơn hàng mới được tạo với phương thức thanh toán: cod', '2025-04-18 15:29:01'),
(22, 17, 'Tạo đơn hàng', 'Người dùng', 'Đơn hàng mới được tạo với phương thức thanh toán: vnpay', '2025-04-18 15:29:15'),
(23, 17, 'Thanh toán VNPAY thành công', 'Hệ thống', 'Thanh toán qua VNPAY với mã giao dịch: 14914525', '2025-04-18 15:30:12'),
(24, 18, 'Tạo đơn hàng', 'Người dùng', 'Đơn hàng mới được tạo với phương thức thanh toán: cod', '2025-04-18 15:33:14'),
(25, 19, 'Tạo đơn hàng', 'Người dùng', 'Đơn hàng mới được tạo với phương thức thanh toán: cod', '2025-04-18 15:36:38'),
(26, 20, 'Tạo đơn hàng', 'Người dùng', 'Đơn hàng mới được tạo với phương thức thanh toán: cod', '2025-04-18 15:37:31'),
(27, 21, 'Tạo đơn hàng', 'Người dùng', 'Đơn hàng mới được tạo với phương thức thanh toán: cod', '2025-04-18 15:39:48'),
(28, 22, 'Tạo đơn hàng', 'Người dùng', 'Đơn hàng mới được tạo với phương thức thanh toán: cod', '2025-04-18 15:46:43'),
(29, 21, 'Hủy đơn hàng', 'nguyen xuan thai', 'Đơn hàng đã bị hủy với lý do: test', '2025-04-18 15:47:04'),
(30, 20, 'Hủy đơn hàng', 'nguyen xuan thai', 'Đơn hàng đã bị hủy với lý do: Đổi ý, không muốn mua nữa', '2025-04-18 15:47:07'),
(31, 19, 'Hủy đơn hàng', 'nguyen xuan thai', 'Đơn hàng đã bị hủy với lý do: Muốn thay đổi phương thức thanh toán', '2025-04-18 15:47:11'),
(32, 18, 'Hủy đơn hàng', 'nguyen xuan thai', 'Đơn hàng đã bị hủy với lý do: Đổi ý, không muốn mua nữa', '2025-04-18 15:47:14'),
(33, 22, 'Cập nhật trạng thái', 'mng', 'Thay đổi trạng thái từ \"Đã xác nhận\" sang \"Đã xác nhận\"', '2025-04-18 15:51:53'),
(34, 22, 'Cập nhật trạng thái', 'mng', 'Thay đổi trạng thái từ \"Đã giao\" sang \"Đã giao\"', '2025-04-18 15:52:18'),
(35, 22, 'Cập nhật trạng thái', 'Super Administrator', 'Thay đổi trạng thái từ \"Đang giao hàng\" sang \"Đang giao hàng\"', '2025-04-19 07:39:00'),
(36, 22, 'Cập nhật trạng thái', 'Super Administrator', 'Thay đổi trạng thái từ \"Đã giao\" sang \"Đã giao\"', '2025-04-19 07:39:04'),
(37, 23, 'Tạo đơn hàng', 'Người dùng', 'Đơn hàng mới được tạo với phương thức thanh toán: cod', '2025-04-19 13:22:34'),
(38, 23, 'Cập nhật trạng thái', 'Super Administrator', 'Thay đổi trạng thái từ \"Đã giao\" sang \"Đã giao\"', '2025-04-19 13:22:47'),
(39, 24, 'Tạo đơn hàng', 'Người dùng', 'Đơn hàng mới được tạo với phương thức thanh toán: vnpay', '2025-04-19 13:30:19'),
(40, 24, 'Thanh toán VNPAY thành công', 'Hệ thống', 'Thanh toán qua VNPAY với mã giao dịch: 14915628', '2025-04-19 13:31:05'),
(41, 24, 'Cập nhật trạng thái', 'Super Administrator', 'Thay đổi trạng thái từ \"Đã giao\" sang \"Đã giao\"', '2025-04-19 13:31:19'),
(42, 24, 'Yêu cầu hoàn trả', 'nguyen xuan thai', 'Khách hàng yêu cầu hoàn trả sản phẩm với lý do: Sản phẩm bị hư hỏng', '2025-04-19 13:36:44'),
(43, 24, 'Cập nhật yêu cầu hoàn trả', 'Super Administrator', 'Cập nhật trạng thái hoàn trả sang \"Không xác định\". Ghi chú: ok skibidi', '2025-04-19 13:42:12'),
(44, 25, 'Tạo đơn hàng', 'Người dùng', 'Đơn hàng mới được tạo với phương thức thanh toán: vnpay', '2025-04-19 13:52:08'),
(45, 26, 'Tạo đơn hàng', 'Người dùng', 'Đơn hàng mới được tạo với phương thức thanh toán: cod', '2025-04-19 13:52:27'),
(46, 25, 'Hủy đơn hàng', 'Khách hàng', 'Đơn hàng đã bị hủy với lý do: Tôi muốn thay đổi địa chỉ giao hàng', '2025-04-19 14:05:21'),
(47, 26, 'Cập nhật trạng thái', 'Super Administrator', 'Thay đổi trạng thái từ \"Chờ xác nhận\" sang \"Đã xác nhận\"', '2025-04-19 14:10:57'),
(48, 26, 'Cập nhật trạng thái', 'Super Administrator', 'Thay đổi trạng thái từ \"Đã xác nhận\" sang \"Chờ xác nhận\"', '2025-04-19 14:11:05'),
(49, 27, 'Tạo đơn hàng', 'Người dùng', 'Đơn hàng mới được tạo với phương thức thanh toán: cod', '2025-04-19 14:23:01'),
(50, 27, 'Cập nhật trạng thái', 'Super Administrator', 'Thay đổi trạng thái từ \"Đã giao\" sang \"Đã giao\"', '2025-04-19 14:23:13'),
(51, 28, 'Tạo đơn hàng', 'Người dùng', 'Đơn hàng mới được tạo với phương thức thanh toán: cod', '2025-04-19 14:37:42'),
(52, 28, 'Cập nhật trạng thái', 'Super Administrator', 'Thay đổi trạng thái từ \"Đã giao\" sang \"Đã giao\"', '2025-04-19 14:37:58'),
(53, 28, 'Yêu cầu hoàn trả', 'thaidui', 'Khách hàng yêu cầu hoàn trả sản phẩm với lý do: Sản phẩm không đúng mô tả', '2025-04-19 14:38:22'),
(54, 28, 'Cập nhật trạng thái', 'Super Administrator', 'Thay đổi trạng thái từ \"Đã giao\" sang \"Đã giao\"', '2025-04-19 14:38:30'),
(55, 28, 'Cập nhật yêu cầu hoàn trả', 'Super Administrator', 'Cập nhật trạng thái hoàn trả sang \"Đã xác nhận\". Ghi chú: ok', '2025-04-19 14:38:44'),
(56, 28, 'Cập nhật yêu cầu hoàn trả', 'Super Administrator', 'Cập nhật trạng thái hoàn trả sang \"Đang xử lý\". Ghi chú: ok', '2025-04-19 14:38:54'),
(57, 28, 'Cập nhật yêu cầu hoàn trả', 'Super Administrator', 'Cập nhật trạng thái hoàn trả sang \"Hoàn thành\". Ghi chú: ok', '2025-04-19 14:39:04'),
(58, 29, 'Tạo đơn hàng', 'Người dùng', 'Đơn hàng mới được tạo với phương thức thanh toán: cod', '2025-04-20 06:57:44'),
(59, 29, 'Cập nhật trạng thái', 'Quản trị viên', 'Thay đổi trạng thái từ \"Chờ xác nhận\" sang \"Đã giao\"', '2025-04-20 06:57:58'),
(60, 24, 'Cập nhật yêu cầu hoàn trả', 'Super Administrator', 'Cập nhật trạng thái hoàn trả sang \"Đã xác nhận\". Ghi chú: ok skibidi', '2025-04-20 07:17:41'),
(61, 24, 'Cập nhật yêu cầu hoàn trả', 'Super Administrator', 'Cập nhật trạng thái hoàn trả sang \"Từ chối\". Ghi chú: ok skibidi', '2025-04-20 07:18:00'),
(62, 30, 'Tạo đơn hàng', 'Người dùng', 'Đơn hàng mới được tạo với phương thức thanh toán: cod', '2025-04-20 08:38:04'),
(63, 30, 'Cập nhật trạng thái', 'Quản trị viên', 'Thay đổi trạng thái từ \"Chờ xác nhận\" sang \"Chờ xác nhận\"', '2025-04-20 08:38:27'),
(64, 30, 'Cập nhật trạng thái', 'Quản trị viên', 'Thay đổi trạng thái từ \"Chờ xác nhận\" sang \"Đã xác nhận\"', '2025-04-20 08:38:40'),
(65, 30, 'Cập nhật trạng thái', 'Quản trị viên', 'Thay đổi trạng thái từ \"Đã xác nhận\" sang \"Đã xác nhận\"', '2025-04-20 08:40:30'),
(66, 30, 'Cập nhật trạng thái', 'Quản trị viên', 'Thay đổi trạng thái từ \"Đã xác nhận\" sang \"Đã xác nhận\"', '2025-04-20 08:41:46'),
(67, 30, 'Cập nhật trạng thái', 'Quản trị viên', 'Thay đổi trạng thái từ \"Đã xác nhận\" sang \"Đã giao\"', '2025-04-20 08:42:08'),
(68, 31, 'Tạo đơn hàng', 'Người dùng', 'Đơn hàng mới được tạo với phương thức thanh toán: vnpay', '2025-04-20 08:46:37'),
(69, 31, 'Thanh toán VNPAY thành công', 'Hệ thống', 'Thanh toán qua VNPAY với mã giao dịch: 14916628', '2025-04-20 08:47:18'),
(70, 33, 'Thanh toán VNPAY thành công', 'Hệ thống', 'Thanh toán qua VNPAY với mã giao dịch: 14916646', '2025-04-20 08:59:30'),
(71, 30, 'Yêu cầu hoàn trả', 'nguyen xuan thai', 'Khách hàng yêu cầu hoàn trả sản phẩm với lý do: Khác', '2025-04-20 09:18:31'),
(72, 30, 'Cập nhật yêu cầu hoàn trả', 'Super Administrator', 'Cập nhật trạng thái hoàn trả sang \"Từ chối\". Ghi chú: không thích', '2025-04-20 09:18:54'),
(73, 30, 'Yêu cầu hoàn trả', 'nguyen xuan thai', 'Khách hàng yêu cầu hoàn trả sản phẩm với lý do: Khác', '2025-04-20 09:18:57'),
(74, 30, 'Cập nhật yêu cầu hoàn trả', 'Super Administrator', 'Cập nhật trạng thái hoàn trả sang \"Từ chối\". Ghi chú: không thích', '2025-04-20 09:19:10'),
(75, 30, 'Cập nhật yêu cầu hoàn trả', 'Super Administrator', 'Cập nhật trạng thái hoàn trả sang \"Từ chối\". Ghi chú: không thích', '2025-04-20 09:19:48'),
(76, 35, 'Thanh toán VNPAY thất bại', 'Hệ thống', 'Thanh toán qua VNPAY thất bại. Mã lỗi: 01', '2025-04-23 09:58:18'),
(77, 36, 'Thanh toán VNPAY thành công', 'Hệ thống', 'Thanh toán qua VNPAY với mã giao dịch: 14922639', '2025-04-23 09:59:36'),
(78, 36, 'Thanh toán VNPAY thành công', 'Hệ thống', 'Thanh toán qua VNPAY với mã giao dịch: 14922639', '2025-04-23 09:59:56'),
(79, 31, 'Cập nhật trạng thái', 'Quản trị viên', 'Thay đổi trạng thái từ \"Đã xác nhận\" sang \"Đã giao\"', '2025-04-23 10:00:29'),
(80, 37, 'Tạo đơn hàng', 'Người dùng', 'Đơn hàng mới được tạo với phương thức thanh toán: cod', '2025-05-07 08:04:10'),
(81, 38, 'Tạo đơn hàng', 'Người dùng', 'Đơn hàng mới được tạo với phương thức thanh toán: cod', '2025-05-07 08:09:12'),
(82, 39, 'Tạo đơn hàng', 'Người dùng', 'Đơn hàng mới được tạo với phương thức thanh toán: cod', '2025-05-07 08:09:56'),
(83, 40, 'Tạo đơn hàng', 'Người dùng', 'Đơn hàng mới được tạo với phương thức thanh toán: cod', '2025-05-07 08:30:45'),
(84, 41, 'Tạo đơn hàng', 'Người dùng', 'Đơn hàng mới được tạo với phương thức thanh toán: cod', '2025-05-07 09:02:16'),
(85, 42, 'Tạo đơn hàng', 'Người dùng', 'Đơn hàng mới được tạo với phương thức thanh toán: cod', '2025-05-07 09:42:42'),
(86, 43, 'Thanh toán VNPAY thành công', 'Hệ thống', 'Thanh toán qua VNPAY với mã giao dịch: 14943891', '2025-05-07 09:45:09'),
(87, 44, 'Tạo đơn hàng', 'Người dùng', 'Đơn hàng mới được tạo với phương thức thanh toán: cod', '2025-05-07 09:58:26'),
(88, 45, 'Tạo đơn hàng', 'Người dùng', 'Đơn hàng mới được tạo với phương thức thanh toán: cod', '2025-05-07 10:03:38'),
(89, 46, 'Tạo đơn hàng', 'Người dùng', 'Đơn hàng mới được tạo với phương thức thanh toán: cod', '2025-05-07 10:06:59'),
(90, 47, 'Tạo đơn hàng', 'Người dùng', 'Đơn hàng mới được tạo với phương thức thanh toán: cod', '2025-05-07 10:09:17'),
(91, 48, 'Tạo đơn hàng', 'Người dùng', 'Đơn hàng mới được tạo với phương thức thanh toán: cod', '2025-05-07 10:14:17'),
(92, 49, 'Tạo đơn hàng', 'Người dùng', 'Đơn hàng mới được tạo với phương thức thanh toán: cod', '2025-05-07 10:16:34'),
(93, 50, 'Tạo đơn hàng', 'Người dùng', 'Đơn hàng mới được tạo với phương thức thanh toán: cod', '2025-05-07 10:18:01'),
(94, 51, 'Tạo đơn hàng', 'Người dùng', 'Đơn hàng mới được tạo với phương thức thanh toán: cod', '2025-05-16 10:00:39'),
(95, 51, 'Cập nhật trạng thái', 'Quản trị viên', 'Thay đổi trạng thái từ \"Chờ xác nhận\" sang \"Đã giao\"', '2025-05-16 10:15:59'),
(96, 52, 'Thanh toán VNPAY thành công', 'Hệ thống', 'Thanh toán qua VNPAY với mã giao dịch: 14961586', '2025-05-16 14:07:01'),
(97, 53, 'Tạo đơn hàng', 'Người dùng', 'Đơn hàng mới được tạo với phương thức thanh toán: cod', '2025-05-16 16:15:08'),
(98, 54, 'Tạo đơn hàng', 'Người dùng', 'Đơn hàng mới được tạo với phương thức thanh toán: cod', '2025-05-16 16:28:59'),
(99, 54, 'Cập nhật trạng thái', 'Quản trị viên', 'Thay đổi trạng thái từ \"Chờ xác nhận\" sang \"Đã giao\"', '2025-05-16 16:30:19'),
(100, 55, 'Tạo đơn hàng', 'Người dùng', 'Đơn hàng mới được tạo với phương thức thanh toán: cod', '2025-05-16 16:58:58'),
(101, 56, 'Tạo đơn hàng', 'Người dùng', 'Đơn hàng mới được tạo với phương thức thanh toán: cod', '2025-05-16 17:00:27'),
(102, 57, 'Tạo đơn hàng', 'Người dùng', 'Đơn hàng mới được tạo với phương thức thanh toán: cod', '2025-05-17 07:13:07'),
(103, 58, 'Tạo đơn hàng', 'Người dùng', 'Đơn hàng mới được tạo với phương thức thanh toán: cod', '2025-05-17 07:19:45'),
(104, 58, 'Hủy đơn hàng', 'Khách hàng', 'Đơn hàng đã bị hủy với lý do: Tôi muốn thay đổi phương thức thanh toán', '2025-05-17 09:53:17'),
(105, 57, 'Hủy đơn hàng', 'Khách hàng', 'Đơn hàng đã bị hủy với lý do: Tôi muốn thay đổi phương thức thanh toán', '2025-05-17 09:56:29'),
(106, 59, 'Tạo đơn hàng', 'Người dùng', 'Đơn hàng mới được tạo với phương thức thanh toán: cod', '2025-05-17 09:57:13'),
(107, 59, 'Cập nhật trạng thái', 'Quản trị viên', 'Thay đổi trạng thái từ \"Chờ xác nhận\" sang \"Đã giao\"', '2025-05-17 09:57:37'),
(108, 60, 'Thanh toán VNPAY thành công', 'Hệ thống', 'Thanh toán qua VNPAY với mã giao dịch: 14963031', '2025-05-17 15:35:48'),
(109, 61, 'Tạo đơn hàng', 'Người dùng', 'Đơn hàng mới được tạo với phương thức thanh toán: cod', '2025-05-18 14:09:44'),
(110, 62, 'Thanh toán VNPAY thành công', 'Hệ thống', 'Thanh toán qua VNPAY với mã giao dịch: 14968035', '2025-05-20 08:09:57'),
(111, 63, 'Tạo đơn hàng', 'Người dùng', 'Đơn hàng mới được tạo với phương thức thanh toán: cod', '2025-05-20 13:20:52'),
(112, 63, 'update_status', 'Quản trị viên', 'Thay đổi trạng thái từ \"Chờ xác nhận\" sang \"Đã xác nhận\". Ghi chú: ok', '2025-05-20 13:21:21'),
(113, 63, 'update_status', 'Quản trị viên', 'Thay đổi trạng thái từ \"Đã xác nhận\" sang \"Đã xác nhận\". Ghi chú: ok', '2025-05-20 13:21:35'),
(114, 64, 'Tạo đơn hàng', 'Người dùng', 'Đơn hàng mới được tạo với phương thức thanh toán: cod', '2025-05-20 14:02:53'),
(115, 65, 'Tạo đơn hàng', 'Người dùng', 'Đơn hàng mới được tạo với phương thức thanh toán: cod', '2025-05-20 14:34:31');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `giohang`
--

CREATE TABLE `giohang` (
  `id` int(11) NOT NULL,
  `id_user` int(11) DEFAULT NULL,
  `session_id` varchar(100) NOT NULL,
  `ngay_tao` timestamp NOT NULL DEFAULT current_timestamp(),
  `ngay_capnhat` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `giohang`
--

INSERT INTO `giohang` (`id`, `id_user`, `session_id`, `ngay_tao`, `ngay_capnhat`) VALUES
(1, NULL, 'pvlgmqsrka1ng58jj6rh5vko73', '2025-04-17 13:58:41', '2025-04-17 14:54:42'),
(2, 2, 'bs7h66gvn36j3pae4372k5funj', '2025-04-17 17:49:42', '2025-05-18 16:13:52'),
(3, NULL, 'h2ojr2dfo8ib1a8mdu68431dad', '2025-04-17 18:57:17', NULL),
(4, NULL, 'ppd115333pgtbkl5l93q1sjp8v', '2025-04-18 10:47:19', NULL),
(5, NULL, '9aqo88t8lg46hti1aev0oeor21', '2025-04-18 13:27:31', NULL),
(6, NULL, 'hnpend9lvbvthattg5d8ceudl4', '2025-04-19 05:12:48', NULL),
(7, NULL, 'lb3ufu77ae9b2u62t03im5ifpa', '2025-04-20 15:46:22', '2025-04-20 15:46:31'),
(8, NULL, '5k8h38iqgs526vos1fr3f7q0cg', '2025-04-23 09:58:20', NULL),
(9, NULL, 'un5088f98puj3866ub8ib8vboi', '2025-05-07 07:40:29', NULL),
(10, NULL, 'uubu5b2nekpvkr6hcd82km47sl', '2025-05-07 09:34:41', NULL),
(11, NULL, 'kbgk6ohck8skrdp2rqgcctlhqh', '2025-05-17 15:57:50', '2025-05-17 16:07:48'),
(12, NULL, 'mmlpmnoqemlgauobpn0pq378vu', '2025-05-18 10:20:00', NULL),
(13, NULL, 'o6tjcg7qcmtudikn8sntrgu54i', '2025-05-20 08:05:23', NULL),
(21, 3, '0lfloact2d44s2qn10ir8osutm', '2025-05-20 14:01:05', NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `giohang_chitiet`
--

CREATE TABLE `giohang_chitiet` (
  `id` int(11) NOT NULL,
  `id_giohang` int(11) NOT NULL,
  `id_bienthe` int(11) NOT NULL,
  `so_luong` int(11) NOT NULL DEFAULT 1,
  `gia` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `giohang_chitiet`
--

INSERT INTO `giohang_chitiet` (`id`, `id_giohang`, `id_bienthe`, `so_luong`, `gia`) VALUES
(26, 9, 122, 1, 2200000.00),
(29, 10, 122, 1, 2200000.00),
(34, 11, 127, 1, 2200000.00),
(36, 11, 137, 1, 900000.00),
(37, 11, 92, 1, 1750000.00),
(38, 11, 107, 3, 1650000.00),
(39, 12, 115, 1, 829000.00),
(41, 13, 127, 1, 2200000.00);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `hoantra`
--

CREATE TABLE `hoantra` (
  `id_hoantra` int(11) NOT NULL,
  `id_donhang` int(11) NOT NULL,
  `id_sanpham` int(11) NOT NULL,
  `id_nguoidung` int(11) NOT NULL,
  `lydo` varchar(255) NOT NULL,
  `mota_chitiet` text DEFAULT NULL,
  `phan_hoi` text DEFAULT NULL,
  `trangthai` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1: Chờ xác nhận, 2: Đã xác nhận, 3: Đang xử lý, 4: Hoàn thành, 5: Từ chối',
  `ngaytao` timestamp NOT NULL DEFAULT current_timestamp(),
  `ngaycapnhat` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `hoantra`
--

INSERT INTO `hoantra` (`id_hoantra`, `id_donhang`, `id_sanpham`, `id_nguoidung`, `lydo`, `mota_chitiet`, `phan_hoi`, `trangthai`, `ngaytao`, `ngaycapnhat`) VALUES
(1, 22, 25, 2, 'Sản phẩm bị lỗi', 'test', '', 2, '2025-04-19 08:39:47', '2025-04-19 10:48:41'),
(3, 28, 27, 9, 'Sản phẩm không đúng mô tả', 'test skibidi', 'ok', 4, '2025-04-19 14:38:22', '2025-04-19 14:39:04'),
(4, 30, 25, 2, 'Khác', 'không thích', 'không thích', 5, '2025-04-20 09:18:31', '2025-04-20 09:19:48'),
(5, 30, 25, 2, 'Khác', 'không thích', NULL, 1, '2025-04-20 09:18:57', NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `khuyen_mai`
--

CREATE TABLE `khuyen_mai` (
  `id` int(11) NOT NULL,
  `ten` varchar(255) NOT NULL,
  `ma_khuyenmai` varchar(50) NOT NULL,
  `loai_giamgia` tinyint(1) DEFAULT 0 COMMENT '0: Phần trăm, 1: Số tiền cụ thể',
  `gia_tri` decimal(10,2) NOT NULL,
  `dieu_kien_toithieu` decimal(12,2) DEFAULT 0.00,
  `so_luong` int(11) DEFAULT NULL,
  `da_su_dung` int(11) DEFAULT 0,
  `ngay_bat_dau` timestamp NULL DEFAULT NULL,
  `ngay_ket_thuc` timestamp NULL DEFAULT NULL,
  `trang_thai` tinyint(1) DEFAULT 1,
  `ngay_tao` timestamp NOT NULL DEFAULT current_timestamp(),
  `max_su_dung_per_user` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `khuyen_mai`
--

INSERT INTO `khuyen_mai` (`id`, `ten`, `ma_khuyenmai`, `loai_giamgia`, `gia_tri`, `dieu_kien_toithieu`, `so_luong`, `da_su_dung`, `ngay_bat_dau`, `ngay_ket_thuc`, `trang_thai`, `ngay_tao`, `max_su_dung_per_user`) VALUES
(1, 'test1', 'TEST1', 0, 100.00, 0.00, 99, 2, '2025-04-17 17:00:00', '2025-04-18 17:00:00', 1, '2025-04-20 07:25:38', NULL),
(2, 'Khuyến mãi mùa hè', 'SUMMER2025', 0, 15.00, 500000.00, 100, 0, '2025-04-30 17:00:00', '2025-06-30 16:59:59', 1, '2025-04-20 07:25:38', NULL),
(3, 'Giảm giá đặc biệt', 'SPECIAL200K', 1, 200000.00, 1000000.00, 50, 0, '0000-00-00 00:00:00', '2025-05-21 16:59:59', 1, '2025-04-20 07:25:38', NULL),
(4, 'Chào mừng thành viên mới', 'NEWUSER', 0, 10.00, 0.00, 200, 0, NULL, '2025-12-31 16:59:59', 1, '2025-04-20 07:25:38', 1),
(5, 'testadmin1', 'TESTADMIN1', 0, 100.00, 0.00, 99, 2, '0000-00-00 00:00:00', '2025-05-19 16:59:59', 1, '2025-04-20 07:26:58', NULL),
(6, 'test', 'TEST2', 0, 100.00, 0.00, 1234, 4, NULL, '2025-05-21 16:59:59', 1, '2025-05-17 07:22:52', NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `khuyen_mai_apdung`
--

CREATE TABLE `khuyen_mai_apdung` (
  `id` int(11) NOT NULL,
  `id_khuyenmai` int(11) NOT NULL,
  `loai_doi_tuong` tinyint(1) NOT NULL COMMENT '0: Tất cả, 1: Danh mục, 2: Sản phẩm',
  `id_doi_tuong` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `khuyen_mai_apdung`
--

INSERT INTO `khuyen_mai_apdung` (`id`, `id_khuyenmai`, `loai_doi_tuong`, `id_doi_tuong`) VALUES
(1, 1, 0, NULL),
(2, 2, 1, 3),
(18, 3, 2, NULL),
(10, 4, 0, NULL),
(20, 5, 0, NULL),
(24, 6, 0, NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `khuyen_mai_su_dung`
--

CREATE TABLE `khuyen_mai_su_dung` (
  `id` int(11) NOT NULL,
  `id_khuyenmai` int(11) NOT NULL,
  `id_user` int(11) DEFAULT NULL,
  `id_donhang` int(11) NOT NULL,
  `ma_khuyenmai` varchar(50) NOT NULL,
  `ngay_su_dung` datetime NOT NULL DEFAULT current_timestamp(),
  `giam_gia` decimal(15,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `khuyen_mai_su_dung`
--

INSERT INTO `khuyen_mai_su_dung` (`id`, `id_khuyenmai`, `id_user`, `id_donhang`, `ma_khuyenmai`, `ngay_su_dung`, `giam_gia`) VALUES
(1, 5, 9, 56, 'testadmin1', '2025-05-17 00:00:27', 1500000.00);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `mausac_hinhanh`
--

CREATE TABLE `mausac_hinhanh` (
  `id` int(11) NOT NULL,
  `id_sanpham` int(11) NOT NULL,
  `id_mausac` int(11) NOT NULL,
  `hinhanh` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `nhat_ky`
--

CREATE TABLE `nhat_ky` (
  `id` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `hanh_dong` varchar(50) NOT NULL,
  `doi_tuong_loai` varchar(50) NOT NULL,
  `doi_tuong_id` int(11) DEFAULT NULL,
  `chi_tiet` text DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `ngay_tao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `nhat_ky`
--

INSERT INTO `nhat_ky` (`id`, `id_user`, `hanh_dong`, `doi_tuong_loai`, `doi_tuong_id`, `chi_tiet`, `ip_address`, `ngay_tao`) VALUES
(1, 1, 'create', 'product', 1, 'Thêm sản phẩm mới: dép tổ ong', NULL, '2025-04-17 09:07:34'),
(2, 1, 'create', 'product', 8, 'Thêm sản phẩm mới: dép tổ ong', NULL, '2025-04-17 10:00:55'),
(3, 1, 'create', 'product', 10, 'Thêm sản phẩm mới: dép tổ ong', NULL, '2025-04-17 10:17:08'),
(4, 1, 'create', 'product', 12, 'Thêm sản phẩm mới: dép tổ ong', NULL, '2025-04-17 10:31:24'),
(5, 1, 'create', 'product', 15, 'Thêm sản phẩm mới: Dép Adidas Adilette', '::1', '2025-04-17 18:56:04'),
(6, 1, 'update', 'product', 14, 'Chỉnh sửa sản phẩm: Biti\'s Hunter Street x Vietmax 2020 - BST HaNoi Culture Patchwork Old Wall Yellow. (ID: 14)', '::1', '2025-04-18 02:44:37'),
(7, 1, 'update', 'product', 14, 'Chỉnh sửa sản phẩm: Biti\'s Hunter Street x Vietmax 2020 - BST HaNoi Culture Patchwork Old Wall Yellow. (ID: 14)', '::1', '2025-04-18 02:49:24'),
(8, 1, 'update', 'category', 6, 'Ẩn danh mục ID: 6', '::1', '2025-04-18 04:19:11'),
(9, 1, 'create', 'product', 17, 'Thêm sản phẩm mới: Giày Thể Thao Biti\'s Hunter Core Nam Màu Xanh Dương Lợt', '::1', '2025-04-18 05:56:04'),
(10, 1, 'hide', 'review', 1, 'Ẩn đánh giá ID: 1', '::1', '2025-04-18 06:23:08'),
(11, 1, 'create', 'promotion', 1, 'Thêm mã khuyến mãi mới: test1 (TEST1)', '::1', '2025-04-18 06:57:44'),
(12, 1, 'update', 'admin', 1, 'Chỉnh sửa tài khoản quản trị: Super Administrator (ID: 1)', '::1', '2025-04-18 07:35:19'),
(13, 1, 'login', 'admin', 1, 'Đăng nhập hệ thống quản trị', '::1', '2025-04-18 09:04:05'),
(14, 1, 'update', 'settings', 0, 'Cập nhật cài đặt hệ thống', '::1', '2025-04-18 09:10:26'),
(15, 1, 'update', 'settings', 0, 'Cập nhật cài đặt hệ thống', '::1', '2025-04-18 09:15:43'),
(16, 1, 'update', 'settings', 0, 'Cập nhật cài đặt hệ thống', '::1', '2025-04-18 09:18:32'),
(17, 4, 'login', 'admin', 4, 'Đăng nhập hệ thống quản trị', '::1', '2025-04-18 10:37:27'),
(18, 1, 'login', 'admin', 1, 'Đăng nhập hệ thống quản trị', '::1', '2025-04-18 12:26:24'),
(19, 1, 'update', 'product', 18, 'Chỉnh sửa sản phẩm: Giày Adidas Ultraboost 22 (ID: 18)', '::1', '2025-04-18 12:27:45'),
(20, 1, 'update', 'product', 19, 'Chỉnh sửa sản phẩm: Giày Converse Chuck Taylor All Star Classic (ID: 19)', '::1', '2025-04-18 12:28:21'),
(21, 1, 'update', 'product', 21, 'Chỉnh sửa sản phẩm: Giày Vans Old Skool (ID: 21)', '::1', '2025-04-18 12:31:32'),
(22, 1, 'create', 'product', 23, 'Thêm sản phẩm mới: Biti\'s Hunter Street x Vietmax 2020 - BST HaNoi Culture Patchwork Old Wall Yellow.', '::1', '2025-04-18 13:18:58'),
(23, 1, 'create', 'product', 24, 'Thêm sản phẩm mới: Giày VANS SKATE OLD SKOOL BLACK/WHITE', '::1', '2025-04-18 13:22:20'),
(24, 4, 'login', 'admin', 4, 'Đăng nhập hệ thống quản trị', '::1', '2025-04-18 13:22:34'),
(25, 4, 'create', 'product', 25, 'Thêm sản phẩm mới: Giày VANS OLD SKOOL LOGO CHECK BLACK', '::1', '2025-04-18 13:24:32'),
(26, 4, 'create', 'product', 26, 'Thêm sản phẩm mới: Giày Nike Court Vision Mid Smoke Grey DN3577-002', '::1', '2025-04-18 13:26:57'),
(27, 4, 'lock', 'customer', 2, 'Đã khóa tài khoản khách hàng: thai', '::1', '2025-04-18 15:54:15'),
(28, 1, 'login', 'admin', 1, 'Đăng nhập hệ thống quản trị', '::1', '2025-04-18 15:59:23'),
(29, 1, 'unlock', 'customer', 2, 'Đã mở khóa tài khoản khách hàng: thai', '::1', '2025-04-18 15:59:24'),
(30, 1, 'login', 'admin', 1, 'Đăng nhập hệ thống quản trị', '::1', '2025-04-19 04:54:18'),
(31, 4, 'login', 'admin', 4, 'Đăng nhập hệ thống quản trị', '::1', '2025-04-19 06:42:26'),
(32, 1, 'login', 'admin', 1, 'Đăng nhập hệ thống quản trị', '::1', '2025-04-19 06:42:40'),
(33, 1, 'update', 'admin', 4, 'Đã vô hiệu hóa tài khoản quản trị: mng (ID: 4)', '::1', '2025-04-19 07:40:12'),
(34, 1, 'login', 'admin', 1, 'Đăng nhập hệ thống quản trị', '::1', '2025-04-19 07:43:58'),
(35, 4, 'login', 'admin', 4, 'Đăng nhập hệ thống quản trị', '::1', '2025-04-19 07:44:29'),
(36, 1, 'login', 'admin', 1, 'Đăng nhập hệ thống quản trị', '::1', '2025-04-19 07:45:03'),
(37, 1, 'login', 'admin', 1, 'Đăng nhập hệ thống quản trị', '::1', '2025-04-19 09:57:46'),
(38, 1, 'login', 'admin', 1, 'Đăng nhập hệ thống quản trị', '::1', '2025-04-19 09:57:58'),
(39, 4, 'login', 'admin', 4, 'Đăng nhập hệ thống quản trị', '::1', '2025-04-19 10:06:14'),
(40, 1, 'login', 'admin', 1, 'Đăng nhập hệ thống quản trị', '::1', '2025-04-19 10:06:25'),
(41, 1, 'hide', 'review', 6, 'Ẩn đánh giá ID: 6', '::1', '2025-04-19 13:21:38'),
(42, 1, 'show', 'review', 6, 'Đã hiển thị đánh giá ID: 6', '::1', '2025-04-19 13:23:48'),
(43, 1, 'delete', 'review', 6, 'Đã xóa đánh giá ID: 6', '::1', '2025-04-19 13:23:53'),
(44, 1, 'delete', 'product', 24, 'Xóa sản phẩm: Giày VANS SKATE OLD SKOOL BLACK/WHITE (ID: 24)', '::1', '2025-04-19 13:28:53'),
(45, 1, 'update', 'product', 25, 'Chỉnh sửa sản phẩm: Giày VANS OLD SKOOL LOGO CHECK BLACK (ID: 25)', '::1', '2025-04-19 13:29:17'),
(46, 1, 'delete', 'product', 26, 'Xóa sản phẩm: Giày Nike Court Vision Mid Smoke Grey DN3577-002 (ID: 26)', '::1', '2025-04-19 13:29:48'),
(47, 1, 'update', 'return', 2, 'Cập nhật trạng thái hoàn trả #2 sang Không xác định', '::1', '2025-04-19 13:42:12'),
(48, 1, 'update', 'product', 23, 'Chỉnh sửa sản phẩm: Biti\'s Hunter Street x Vietmax 2020 - BST HaNoi Culture Patchwork Old Wall Yellow. (ID: 23)', '::1', '2025-04-19 13:49:26'),
(49, 1, 'create', 'product', 27, 'Thêm sản phẩm mới: Giày Nike Court Vision Mid Smoke Grey DN3577-002', '::1', '2025-04-19 14:22:33'),
(50, 1, 'reset_password', 'customer', 9, 'Đã đặt lại mật khẩu cho khách hàng: thaidui', '::1', '2025-04-19 14:32:40'),
(51, 1, 'update', 'return', 3, 'Cập nhật trạng thái hoàn trả #3 sang Đã xác nhận', '::1', '2025-04-19 14:38:44'),
(52, 1, 'update', 'return', 3, 'Cập nhật trạng thái hoàn trả #3 sang Đang xử lý', '::1', '2025-04-19 14:38:54'),
(53, 1, 'update', 'return', 3, 'Cập nhật trạng thái hoàn trả #3 sang Hoàn thành', '::1', '2025-04-19 14:39:04'),
(54, 1, 'login', 'admin', 1, 'Đăng nhập hệ thống quản trị', '::1', '2025-04-20 01:29:56'),
(55, 1, 'login', 'admin', 1, 'Đăng nhập hệ thống quản trị', '::1', '2025-04-20 01:30:26'),
(56, 1, 'login', 'admin', 1, 'Đăng nhập hệ thống quản trị', '::1', '2025-04-20 01:31:05'),
(57, 1, 'login', 'admin', 1, 'Đăng nhập hệ thống quản trị', '::1', '2025-04-20 02:54:03'),
(58, 1, 'create', 'product', 28, 'Thêm sản phẩm mới: dép tổ ong', '::1', '2025-04-20 03:34:20'),
(59, 1, 'login', 'admin', 1, 'Đăng nhập hệ thống quản trị', '::1', '2025-04-20 04:04:23'),
(60, 1, 'hide', 'product', 28, 'Ẩn sản phẩm ID: 28', '::1', '2025-04-20 04:13:53'),
(61, 1, 'show', 'product', 28, 'Hiển thị sản phẩm ID: 28', '::1', '2025-04-20 04:14:02'),
(62, 1, 'feature', 'product', 28, 'Đặt nổi bật cho sản phẩm ID: 28', '::1', '2025-04-20 04:14:04'),
(63, 1, 'unfeature', 'product', 28, 'Bỏ nổi bật cho sản phẩm ID: 28', '::1', '2025-04-20 04:14:09'),
(64, 1, 'update', 'product', 28, 'Chỉnh sửa sản phẩm: dép tổ ong (ID: 28)', '::1', '2025-04-20 05:32:44'),
(65, 1, 'update', 'product', 28, 'Chỉnh sửa sản phẩm: dép tổ ong (ID: 28)', '::1', '2025-04-20 05:33:17'),
(66, 1, 'update', 'product', 28, 'Chỉnh sửa sản phẩm: dép tổ ong (ID: 28)', '::1', '2025-04-20 05:34:56'),
(67, 1, 'logout', 'admin', 1, 'Đăng xuất khỏi hệ thống quản trị', '::1', '2025-04-20 06:02:35'),
(68, 1, 'login', 'admin', 1, 'Đăng nhập hệ thống quản trị', '::1', '2025-04-20 06:02:48'),
(69, 1, 'update', 'product', 28, 'Chỉnh sửa sản phẩm: dép tổ ong (ID: 28)', '::1', '2025-04-20 06:16:36'),
(70, 1, 'feature', 'product', 28, 'Đặt nổi bật cho sản phẩm ID: 28', '::1', '2025-04-20 06:17:27'),
(71, 1, 'unfeature', 'product', 28, 'Bỏ nổi bật cho sản phẩm ID: 28', '::1', '2025-04-20 06:17:37'),
(72, 1, 'feature', 'product', 23, 'Đặt nổi bật cho sản phẩm ID: 23', '::1', '2025-04-20 06:17:39'),
(73, 1, 'unfeature', 'product', 23, 'Bỏ nổi bật cho sản phẩm ID: 23', '::1', '2025-04-20 06:17:46'),
(74, 1, 'delete', 'product', 28, 'Xóa sản phẩm: dép tổ ong (ID: 28)', '::1', '2025-04-20 06:18:02'),
(75, 1, 'update', 'category', 6, 'Cập nhật danh mục: Dép (ID: 6)', '::1', '2025-04-20 06:24:13'),
(76, 1, 'delete', 'category', 6, 'Xóa danh mục: Dép (ID: 6)', '::1', '2025-04-20 06:24:33'),
(77, 1, 'create', 'category', 7, 'Thêm danh mục mới: dép', '::1', '2025-04-20 06:24:54'),
(78, 1, 'update', 'category', 7, 'Cập nhật danh mục: dép (ID: 7)', '::1', '2025-04-20 06:26:36'),
(79, 1, 'lock', 'customer', 9, 'Đã khóa tài khoản khách hàng: thaidui', '::1', '2025-04-20 06:36:05'),
(80, 1, 'unlock', 'customer', 9, 'Đã mở khóa tài khoản khách hàng: thaidui', '::1', '2025-04-20 06:36:09'),
(81, 1, 'reset_password', 'customer', 9, 'Đã đặt lại mật khẩu cho khách hàng: thaidui', '::1', '2025-04-20 06:36:17'),
(82, 1, 'hide', 'review', 9, 'Ẩn đánh giá ID: 9', '::1', '2025-04-20 06:56:31'),
(83, 1, 'show', 'review', 9, 'Đã hiển thị đánh giá ID: 9', '::1', '2025-04-20 06:56:51'),
(84, 1, 'update_status', 'order', 29, 'Cập nhật trạng thái đơn hàng #29 thành: Đã giao', '::1', '2025-04-20 06:57:58'),
(85, 1, 'hide', 'review', 10, 'Ẩn đánh giá ID: 10', '::1', '2025-04-20 07:13:47'),
(86, 1, 'show', 'review', 10, 'Đã hiển thị đánh giá ID: 10', '::1', '2025-04-20 07:13:53'),
(87, 1, 'update', 'return', 2, 'Cập nhật trạng thái hoàn trả #2 sang \"Đã xác nhận\". Ghi chú: ok skibidi', '::1', '2025-04-20 07:17:41'),
(88, 1, 'update', 'return', 2, 'Cập nhật trạng thái hoàn trả #2 sang \"Từ chối\". Ghi chú: ok skibidi', '::1', '2025-04-20 07:18:00'),
(89, 1, 'create', 'promotion', 5, 'Thêm mã khuyến mãi mới: testadmin1 (TESTADMIN1)', '::1', '2025-04-20 07:26:58'),
(90, 1, 'update', 'promotion', 5, 'Cập nhật khuyến mãi: testadmin1 (ID: 5)', '::1', '2025-04-20 07:41:41'),
(91, 1, 'update', 'promotion', 5, 'Cập nhật khuyến mãi: testadmin1 (ID: 5)', '::1', '2025-04-20 07:41:44'),
(92, 1, 'update', 'promotion', 5, 'Cập nhật khuyến mãi: testadmin1 (ID: 5)', '::1', '2025-04-20 07:42:02'),
(93, 1, 'logout', 'admin', 1, 'Đăng xuất khỏi hệ thống quản trị', '::1', '2025-04-20 08:19:12'),
(94, 1, 'login', 'admin', 1, 'Đăng nhập hệ thống quản trị', '::1', '2025-04-20 08:19:19'),
(95, 1, 'disable', 'admin', 8, 'Đã vô hiệu hóa tài khoản nhân viên ID: 8', '::1', '2025-04-20 08:29:27'),
(96, 1, 'reset_password', 'admin', 8, 'Đã đặt lại mật khẩu cho nhân viên ID: 8', '::1', '2025-04-20 08:29:40'),
(97, 1, 'logout', 'admin', 1, 'Đăng xuất khỏi hệ thống quản trị', '::1', '2025-04-20 08:29:50'),
(98, 1, 'login', 'admin', 1, 'Đăng nhập hệ thống quản trị', '::1', '2025-04-20 08:30:14'),
(99, 1, 'reset_password', 'admin', 8, 'Đã đặt lại mật khẩu cho nhân viên ID: 8', '::1', '2025-04-20 08:30:24'),
(100, 1, 'logout', 'admin', 1, 'Đăng xuất khỏi hệ thống quản trị', '::1', '2025-04-20 08:30:27'),
(101, 4, 'login', 'admin', 4, 'Đăng nhập hệ thống quản trị', '::1', '2025-04-20 08:30:44'),
(102, 4, 'logout', 'admin', 4, 'Đăng xuất khỏi hệ thống quản trị', '::1', '2025-04-20 08:30:59'),
(103, 1, 'login', 'admin', 1, 'Đăng nhập hệ thống quản trị', '::1', '2025-04-20 08:31:05'),
(104, 1, 'unlock', 'admin', 8, 'Đã kích hoạt tài khoản nhân viên ID: 8', '::1', '2025-04-20 08:31:09'),
(105, 1, 'logout', 'admin', 1, 'Đăng xuất khỏi hệ thống quản trị', '::1', '2025-04-20 08:31:13'),
(106, 1, 'login', 'admin', 1, 'Đăng nhập hệ thống quản trị', '::1', '2025-04-20 08:31:31'),
(107, 1, 'disable', 'admin', 4, 'Đã vô hiệu hóa tài khoản nhân viên ID: 4', '::1', '2025-04-20 08:31:44'),
(108, 1, 'logout', 'admin', 1, 'Đăng xuất khỏi hệ thống quản trị', '::1', '2025-04-20 08:31:47'),
(109, 1, 'login', 'admin', 1, 'Đăng nhập hệ thống quản trị', '::1', '2025-04-20 08:32:04'),
(110, 1, 'unlock', 'admin', 4, 'Đã kích hoạt tài khoản nhân viên ID: 4', '::1', '2025-04-20 08:32:06'),
(111, 1, 'logout', 'admin', 1, 'Đăng xuất khỏi hệ thống quản trị', '::1', '2025-04-20 08:32:19'),
(112, 8, 'login', 'admin', 8, 'Đăng nhập hệ thống quản trị', '::1', '2025-04-20 08:32:26'),
(113, 8, 'logout', 'admin', 8, 'Đăng xuất khỏi hệ thống quản trị', '::1', '2025-04-20 08:34:46'),
(114, 1, 'login', 'admin', 1, 'Đăng nhập hệ thống quản trị', '::1', '2025-04-20 08:34:55'),
(115, 1, 'update_status', 'order', 30, 'Cập nhật trạng thái đơn hàng #30 thành: Chờ xác nhận', '::1', '2025-04-20 08:38:27'),
(116, 1, 'update_status', 'order', 30, 'Cập nhật trạng thái đơn hàng #30 thành: Đã xác nhận', '::1', '2025-04-20 08:38:40'),
(117, 1, 'update_status', 'order', 30, 'Cập nhật trạng thái đơn hàng #30 thành: Đã xác nhận', '::1', '2025-04-20 08:40:30'),
(118, 1, 'update_status', 'order', 30, 'Cập nhật trạng thái đơn hàng #30 thành: Đã xác nhận', '::1', '2025-04-20 08:41:46'),
(119, 1, 'update_status', 'order', 30, 'Cập nhật trạng thái đơn hàng #30 thành: Đã giao', '::1', '2025-04-20 08:42:08'),
(120, 1, 'create', 'product', 29, 'Thêm sản phẩm mới: Giày Thể Thao Biti\'s Hunter Core Nữ Màu Xám HSW009100XAM', '::1', '2025-04-20 08:46:07'),
(121, 1, 'update', 'return', 4, 'Cập nhật trạng thái hoàn trả #4 sang \"Từ chối\". Ghi chú: không thích', '::1', '2025-04-20 09:18:54'),
(122, 1, 'update', 'return', 4, 'Cập nhật trạng thái hoàn trả #4 sang \"Từ chối\". Ghi chú: không thích', '::1', '2025-04-20 09:19:10'),
(123, 1, 'update', 'return', 4, 'Cập nhật trạng thái hoàn trả #4 sang \"Từ chối\". Ghi chú: không thích', '::1', '2025-04-20 09:19:48'),
(124, 1, 'update', 'size', 19, 'Cập nhật kích thước: size45.5', '::1', '2025-04-20 09:28:54'),
(125, 1, 'create', 'color', 22, 'Thêm màu sắc mới: xám-xanh', '::1', '2025-04-20 09:32:41'),
(126, 1, 'update', 'color', 16, 'Cập nhật màu sắc: Xanh lá', '::1', '2025-04-20 09:33:15'),
(127, 1, 'delete', 'brand', 3, 'Xóa thương hiệu ID: 3', '::1', '2025-04-20 09:36:39'),
(128, 1, 'create', 'brand', 7, 'Thêm thương hiệu mới: adidas', '::1', '2025-04-20 09:37:02'),
(129, 1, 'update', 'brand', 7, 'Cập nhật thương hiệu: adidas', '::1', '2025-04-20 09:37:09'),
(130, 1, 'create', 'admin', 10, 'Thêm nhân viên mới: mng1', '::1', '2025-04-20 09:51:49'),
(131, 1, 'logout', 'admin', 1, 'Đăng xuất khỏi hệ thống quản trị', '::1', '2025-04-20 09:51:57'),
(132, 10, 'login', 'admin', 10, 'Đăng nhập hệ thống quản trị', '::1', '2025-04-20 09:52:03'),
(133, 10, 'update', 'product', 30, 'Chỉnh sửa sản phẩm: Giày Adidas Ultraboost 21 (ID: 30)', '::1', '2025-04-20 10:04:08'),
(134, 10, 'update', 'product', 31, 'Chỉnh sửa sản phẩm: Giày Puma RS-X3 (ID: 31)', '::1', '2025-04-20 10:05:05'),
(135, 10, 'update', 'product', 32, 'Chỉnh sửa sản phẩm: Giày Converse Chuck Taylor All Star (ID: 32)', '::1', '2025-04-20 10:06:49'),
(136, 10, 'update', 'product', 33, 'Chỉnh sửa sản phẩm: Dép Nike Air Max (ID: 33)', '::1', '2025-04-20 10:08:45'),
(137, 10, 'update', 'product', 33, 'Chỉnh sửa sản phẩm: Dép Nike Air Max (ID: 33)', '::1', '2025-04-20 14:46:04'),
(138, 10, 'logout', 'admin', 10, 'Đăng xuất khỏi hệ thống quản trị', '::1', '2025-04-20 14:46:12'),
(139, 8, 'login', 'admin', 8, 'Đăng nhập hệ thống quản trị', '::1', '2025-04-20 14:46:19'),
(140, 8, 'logout', 'admin', 8, 'Đăng xuất khỏi hệ thống quản trị', '::1', '2025-04-20 14:47:54'),
(141, 1, 'login', 'admin', 1, 'Đăng nhập hệ thống quản trị', '::1', '2025-04-20 14:48:02'),
(142, 1, 'login', 'admin', 1, 'Đăng nhập hệ thống quản trị', '::1', '2025-04-21 09:10:29'),
(143, 1, 'login', 'admin', 1, 'Đăng nhập hệ thống quản trị', '::1', '2025-04-21 16:18:34'),
(144, 1, 'login', 'admin', 1, 'Đăng nhập hệ thống quản trị', '::1', '2025-04-22 05:34:45'),
(145, 1, 'delete', 'product', 23, 'Xóa sản phẩm: Biti\'s Hunter Street x Vietmax 2020 - BST HaNoi Culture Patchwork Old Wall Yellow. (ID: 23)', '::1', '2025-04-22 05:35:06'),
(146, 1, 'login', 'admin', 1, 'Đăng nhập hệ thống quản trị', '::1', '2025-04-23 08:28:59'),
(147, 1, 'update_status', 'order', 31, 'Cập nhật trạng thái đơn hàng #31 thành: Đã giao', '::1', '2025-04-23 10:00:29'),
(148, 1, 'login', 'admin', 1, 'Đăng nhập hệ thống quản trị', '::1', '2025-04-23 14:27:11'),
(149, 1, 'login', 'admin', 1, 'Đăng nhập hệ thống quản trị', '::1', '2025-04-25 04:36:12'),
(150, 1, 'login', 'admin', 1, 'Đăng nhập hệ thống quản trị', '::1', '2025-05-07 08:08:30'),
(151, 1, 'login', 'admin', 1, 'Đăng nhập hệ thống quản trị', '::1', '2025-05-07 09:30:15'),
(152, 1, 'update', 'product', 30, 'Chỉnh sửa sản phẩm: Giày Adidas Ultraboost 21 (ID: 30)', '::1', '2025-05-07 10:08:21'),
(153, 1, 'update', 'product', 30, 'Chỉnh sửa sản phẩm: Giày Adidas Ultraboost 21 (ID: 30)', '::1', '2025-05-07 10:08:34'),
(154, 1, 'update', 'product', 30, 'Chỉnh sửa sản phẩm: Giày Adidas Ultraboost 21 (ID: 30)', '::1', '2025-05-07 10:15:35'),
(155, 1, 'update', 'product', 33, 'Chỉnh sửa sản phẩm: Dép Nike Air Max (ID: 33)', '::1', '2025-05-07 10:17:17'),
(156, 1, 'update', 'product', 29, 'Chỉnh sửa sản phẩm: Giày Thể Thao Biti\'s Hunter Core Nữ Màu Xám HSW009100XAM (ID: 29)', '::1', '2025-05-07 10:17:33'),
(157, 1, 'update', 'product', 33, 'Chỉnh sửa sản phẩm: Dép Nike Air Max (ID: 33)', '::1', '2025-05-07 10:17:41'),
(158, 1, 'unfeature', 'product', 33, 'Bỏ nổi bật cho sản phẩm ID: 33', '::1', '2025-05-07 14:35:48'),
(159, 1, 'feature', 'product', 33, 'Đặt nổi bật cho sản phẩm ID: 33', '::1', '2025-05-07 14:35:50'),
(160, 1, 'export', 'order', 1, 'Xuất danh sách đơn hàng ra Excel', '::1', '2025-05-07 15:50:34'),
(161, 1, 'export', 'order', 1, 'Xuất danh sách đơn hàng ra Excel', '::1', '2025-05-07 15:54:27'),
(162, 1, 'export', 'order', 1, 'Xuất danh sách đơn hàng ra Excel', '::1', '2025-05-07 15:57:24'),
(163, 1, 'export', 'order', 1, 'Xuất danh sách đơn hàng ra Excel', '::1', '2025-05-07 15:59:58'),
(164, 1, 'export', 'order', 1, 'Xuất danh sách đơn hàng ra Excel', '::1', '2025-05-07 16:05:21'),
(165, 1, 'export', 'order', 1, 'Xuất danh sách đơn hàng ra Excel', '::1', '2025-05-07 16:21:10'),
(166, 1, 'export', 'order', 1, 'Xuất danh sách đơn hàng ra Excel', '::1', '2025-05-07 16:23:27'),
(167, 1, 'export', 'order', 1, 'Xuất danh sách đơn hàng ra Excel', '::1', '2025-05-07 16:25:59'),
(168, 1, 'update', 'admin', 10, 'Cập nhật thông tin nhân viên: mng1 (ID: 10)', '::1', '2025-05-07 16:27:10'),
(169, 1, 'login', 'admin', 1, 'Đăng nhập hệ thống quản trị', '::1', '2025-05-16 10:11:38'),
(170, 1, 'logout', 'admin', 1, 'Đăng xuất khỏi hệ thống quản trị', '::1', '2025-05-16 10:13:30'),
(171, 1, 'login', 'admin', 1, 'Đăng nhập hệ thống quản trị', '::1', '2025-05-16 10:13:59'),
(172, 1, 'update_status', 'order', 51, 'Cập nhật trạng thái đơn hàng #51 thành: Đã giao', '::1', '2025-05-16 10:15:59'),
(173, 1, 'export', 'order', 1, 'Xuất danh sách đơn hàng ra Excel', '::1', '2025-05-16 13:51:36'),
(174, 1, 'export', 'order', 1, 'Xuất danh sách đơn hàng ra Excel', '::1', '2025-05-16 13:51:54'),
(175, 1, 'update', 'promotion', 4, 'Cập nhật khuyến mãi: Chào mừng thành viên mới (ID: 4)', '::1', '2025-05-16 13:54:50'),
(176, 1, 'update', 'promotion', 4, 'Cập nhật khuyến mãi: Chào mừng thành viên mới (ID: 4)', '::1', '2025-05-16 13:54:54'),
(177, 1, 'update', 'promotion', 5, 'Cập nhật khuyến mãi: testadmin1 (ID: 5)', '::1', '2025-05-16 14:05:59'),
(178, 1, 'update', 'promotion', 5, 'Cập nhật khuyến mãi: testadmin1 (ID: 5)', '::1', '2025-05-16 14:07:08'),
(179, 1, 'reset_password', 'customer', 9, 'Đã đặt lại mật khẩu cho khách hàng: thaidui', '::1', '2025-05-16 16:12:39'),
(180, 1, 'update', 'promotion', 5, 'Cập nhật khuyến mãi: testadmin1 (ID: 5)', '::1', '2025-05-16 16:29:42'),
(181, 1, 'update', 'promotion', 5, 'Cập nhật khuyến mãi: testadmin1 (ID: 5)', '::1', '2025-05-16 16:29:49'),
(182, 1, 'update', 'promotion', 5, 'Cập nhật khuyến mãi: testadmin1 (ID: 5)', '::1', '2025-05-16 16:29:51'),
(183, 1, 'update_status', 'order', 54, 'Cập nhật trạng thái đơn hàng #54 thành: Đã giao', '::1', '2025-05-16 16:30:19'),
(184, 1, 'login', 'admin', 1, 'Đăng nhập hệ thống quản trị', '::1', '2025-05-17 06:58:44'),
(185, 1, 'update', 'promotion', 5, 'Cập nhật khuyến mãi: testadmin1 (ID: 5)', '::1', '2025-05-17 07:20:17'),
(186, 1, 'update', 'promotion', 3, 'Cập nhật khuyến mãi: Giảm giá đặc biệt (ID: 3)', '::1', '2025-05-17 07:20:34'),
(187, 1, 'update', 'promotion', 3, 'Cập nhật khuyến mãi: Giảm giá đặc biệt (ID: 3)', '::1', '2025-05-17 07:21:23'),
(188, 1, 'create', 'promotion', 6, 'Thêm mã khuyến mãi mới: test (TEST2)', '::1', '2025-05-17 07:22:52'),
(189, 1, 'update', 'promotion', 5, 'Cập nhật khuyến mãi: testadmin1 (ID: 5)', '::1', '2025-05-17 07:23:42'),
(190, 1, 'update_status', 'order', 59, 'Cập nhật trạng thái đơn hàng #59 thành: Đã giao', '::1', '2025-05-17 09:57:37'),
(191, 11, 'change_password', 'user', 11, 'Người dùng đã thay đổi mật khẩu', '::1', '2025-05-17 10:08:22'),
(192, 1, 'login', 'admin', 1, 'Đăng nhập hệ thống quản trị', '::1', '2025-05-17 15:29:36'),
(193, 1, 'lock', 'customer', 11, 'Đã khóa tài khoản khách hàng: thai', '::1', '2025-05-17 15:37:31'),
(194, 1, 'login', 'admin', 1, 'Đăng nhập hệ thống quản trị', '::1', '2025-05-17 15:38:05'),
(195, 1, 'unlock', 'customer', 11, 'Đã mở khóa tài khoản khách hàng: thai', '::1', '2025-05-17 15:38:12'),
(196, 1, 'lock', 'customer', 11, 'Đã khóa tài khoản khách hàng: thai', '::1', '2025-05-17 15:38:35'),
(197, 1, 'unlock', 'customer', 11, 'Đã mở khóa tài khoản khách hàng: thai', '::1', '2025-05-17 15:38:42'),
(198, 1, 'login', 'admin', 1, 'Đăng nhập hệ thống quản trị', '::1', '2025-05-18 03:31:18'),
(199, 1, 'update', 'category', 5, 'Cập nhật danh mục: Giày Trẻ Em (ID: 5)', '::1', '2025-05-18 03:35:16'),
(200, 1, 'update', 'category', 1, 'Cập nhật danh mục: Giày Nam (ID: 1)', '::1', '2025-05-18 03:38:18'),
(201, 1, 'update', 'category', 1, 'Cập nhật danh mục: Giày Nam (ID: 1)', '::1', '2025-05-18 03:40:47'),
(202, 1, 'update', 'category', 2, 'Cập nhật danh mục: Giày Nữ (ID: 2)', '::1', '2025-05-18 03:44:44'),
(203, 1, 'update', 'category', 3, 'Cập nhật danh mục: Giày Thể Thao (ID: 3)', '::1', '2025-05-18 06:03:50'),
(204, 1, 'login', 'admin', 1, 'Đăng nhập hệ thống quản trị', '::1', '2025-05-18 14:09:06'),
(205, 1, 'update', 'promotion', 6, 'Cập nhật khuyến mãi: test (ID: 6)', '::1', '2025-05-18 14:10:09'),
(206, 1, 'update', 'promotion', 6, 'Cập nhật khuyến mãi: test (ID: 6)', '::1', '2025-05-18 14:19:09'),
(207, 1, 'login', 'admin', 1, 'Đăng nhập hệ thống quản trị', '::1', '2025-05-20 08:01:28'),
(208, 1, 'update', 'category', 5, 'Cập nhật danh mục: Giày Trẻ Em (ID: 5)', '::1', '2025-05-20 08:02:33'),
(209, 1, 'update', 'category', 4, 'Cập nhật danh mục: Giày Thời Trang (ID: 4)', '::1', '2025-05-20 08:02:45'),
(210, 1, 'update', 'category', 3, 'Cập nhật danh mục: Giày Thể Thao (ID: 3)', '::1', '2025-05-20 08:03:02'),
(211, 1, 'update', 'category', 2, 'Cập nhật danh mục: Giày Nữ (ID: 2)', '::1', '2025-05-20 08:03:19'),
(212, 1, 'update', 'category', 1, 'Cập nhật danh mục: Giày Nam (ID: 1)', '::1', '2025-05-20 08:03:24'),
(213, 1, 'update', 'category', 1, 'Cập nhật danh mục: Giày Nam (ID: 1)', '::1', '2025-05-20 08:03:38'),
(214, 1, 'update', 'promotion', 6, 'Cập nhật khuyến mãi: test (ID: 6)', '::1', '2025-05-20 08:08:21'),
(215, 1, 'update', 'promotion', 6, 'Cập nhật khuyến mãi: test (ID: 6)', '::1', '2025-05-20 08:08:56'),
(216, 1, 'update_status', 'order', 63, 'Cập nhật trạng thái đơn hàng #63 thành: Đã xác nhận', '::1', '2025-05-20 13:21:21'),
(217, 1, 'update_status', 'order', 63, 'Cập nhật trạng thái đơn hàng #63 thành: Đã xác nhận', '::1', '2025-05-20 13:21:35'),
(218, 1, 'create', 'brand', 8, 'Thêm thương hiệu mới: ananas', '::1', '2025-05-20 13:45:05'),
(219, 1, 'delete', 'brand', 8, 'Xóa thương hiệu ID: 8', '::1', '2025-05-20 13:45:22'),
(220, 1, 'create', 'brand', 9, 'Thêm thương hiệu mới: ananas', '::1', '2025-05-20 13:45:37'),
(221, 1, 'create', 'product', 34, 'Thêm sản phẩm mới: Urbas Love+ 23 - Slip On - Offwhite', '::1', '2025-05-20 13:51:22'),
(222, 1, 'update', 'product', 34, 'Chỉnh sửa sản phẩm: Urbas Love+ 23 - Slip On - Offwhite (ID: 34)', '::1', '2025-05-20 13:51:44'),
(223, 1, 'feature', 'product', 34, 'Đặt nổi bật cho sản phẩm ID: 34', '::1', '2025-05-20 13:52:26'),
(224, 1, 'login', 'admin', 1, 'Đăng nhập hệ thống quản trị', '::1', '2025-05-20 14:00:58'),
(225, 1, 'update', 'product', 34, 'Chỉnh sửa sản phẩm: Urbas Love+ 23 - Slip On - Offwhite (ID: 34)', '::1', '2025-05-20 14:02:17');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `payment_logs`
--

CREATE TABLE `payment_logs` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `payment_method` varchar(20) NOT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `status` varchar(20) NOT NULL,
  `response_code` varchar(10) DEFAULT NULL,
  `payment_data` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `payment_logs`
--

INSERT INTO `payment_logs` (`id`, `order_id`, `user_id`, `payment_method`, `transaction_id`, `amount`, `status`, `response_code`, `payment_data`, `created_at`) VALUES
(1, 10, NULL, 'vnpay', NULL, 1530000.00, 'pending', NULL, '{\"vnp_Amount\":153000000,\"vnp_Command\":\"pay\",\"vnp_CreateDate\":\"20250418180456\",\"vnp_CurrCode\":\"VND\",\"vnp_IpAddr\":\"::1\",\"vnp_Locale\":\"vn\",\"vnp_OrderInfo\":\"Thanh toan don hang BUG2504183412f tai Bug Shop\",\"vnp_OrderType\":\"other\",\"vnp_ReturnUrl\":\"http:\\/\\/localhost\\/bug_shop\\/vnpay_return.php\",\"vnp_TmnCode\":\"YOUR_TMN_CODE\",\"vnp_TxnRef\":\"BUG2504183412f\",\"vnp_Version\":\"2.1.0\"}', '2025-04-18 11:04:56'),
(2, 11, NULL, 'vnpay', NULL, 3230000.00, 'pending', NULL, '{\"vnp_Amount\":323000000,\"vnp_Command\":\"pay\",\"vnp_CreateDate\":\"20250418181101\",\"vnp_CurrCode\":\"VND\",\"vnp_IpAddr\":\"::1\",\"vnp_Locale\":\"vn\",\"vnp_OrderInfo\":\"Thanh toan don hang BUG25041869772 tai Bug Shop\",\"vnp_OrderType\":\"billpayment\",\"vnp_ReturnUrl\":\"http:\\/\\/localhost\\/bug_shop\\/vnpay_return.php\",\"vnp_TmnCode\":\"CGMART01\",\"vnp_TxnRef\":\"BUG25041869772\",\"vnp_Version\":\"2.1.0\"}', '2025-04-18 11:11:01'),
(3, 12, NULL, 'vnpay', NULL, 1680000.00, 'pending', NULL, '{\"vnp_Amount\":168000000,\"vnp_Command\":\"pay\",\"vnp_CreateDate\":\"20250418202813\",\"vnp_CurrCode\":\"VND\",\"vnp_IpAddr\":\"::1\",\"vnp_Locale\":\"vn\",\"vnp_OrderInfo\":\"Thanh toan don hang BUG250418ebe4d tai Bug Shop\",\"vnp_OrderType\":\"billpayment\",\"vnp_ReturnUrl\":\"http:\\/\\/localhost\\/bug_shop\\/vnpay_return.php\",\"vnp_TmnCode\":\"CGMART01\",\"vnp_TxnRef\":\"BUG250418ebe4d\",\"vnp_Version\":\"2.1.0\"}', '2025-04-18 13:28:13'),
(4, 13, 2, 'vnpay', NULL, 2010000.00, 'pending', NULL, '{\"vnp_Amount\":201000000,\"vnp_Command\":\"pay\",\"vnp_CreateDate\":\"20250418220613\",\"vnp_CurrCode\":\"VND\",\"vnp_IpAddr\":\"::1\",\"vnp_Locale\":\"vn\",\"vnp_OrderInfo\":\"Thanh toan don hang BUG250418e544f tai Bug Shop\",\"vnp_OrderType\":\"billpayment\",\"vnp_ReturnUrl\":\"http:\\/\\/localhost\\/bug_shop\\/vnpay_return.php\",\"vnp_TmnCode\":\"CGMART01\",\"vnp_TxnRef\":\"BUG250418e544f\",\"vnp_Version\":\"2.1.0\"}', '2025-04-18 15:06:13'),
(5, 14, 2, 'vnpay', NULL, 1680000.00, 'pending', NULL, '{\"vnp_Amount\":168000000,\"vnp_Command\":\"pay\",\"vnp_CreateDate\":\"20250418221234\",\"vnp_CurrCode\":\"VND\",\"vnp_IpAddr\":\"::1\",\"vnp_Locale\":\"vn\",\"vnp_OrderInfo\":\"Thanh toan don hang BUG2504189fa0a tai Bug Shop\",\"vnp_OrderType\":\"billpayment\",\"vnp_ReturnUrl\":\"http:\\/\\/localhost\\/bug_shop\\/vnpay_return.php\",\"vnp_TmnCode\":\"CGMART01\",\"vnp_TxnRef\":\"BUG2504189fa0a\",\"vnp_Version\":\"2.1.0\"}', '2025-04-18 15:12:34'),
(6, 15, 2, 'vnpay', NULL, 1980000.00, 'pending', NULL, '{\"vnp_Version\":\"2.1.0\",\"vnp_TmnCode\":\"CGMART01\",\"vnp_Amount\":198000000,\"vnp_Command\":\"pay\",\"vnp_CreateDate\":\"20250418222524\",\"vnp_CurrCode\":\"VND\",\"vnp_IpAddr\":\"::1\",\"vnp_Locale\":\"vn\",\"vnp_OrderInfo\":\"Thanh toan don hang BUG25041806F5C tai Bug Shop\",\"vnp_OrderType\":\"billpayment\",\"vnp_ReturnUrl\":\"http:\\/\\/localhost\\/bug_shop\\/vnpay_return.php\",\"vnp_TxnRef\":\"BUG25041806F5C\"}', '2025-04-18 15:25:24'),
(7, 17, 2, 'vnpay', NULL, 1650000.00, 'pending', NULL, '{\"vnp_Version\":\"2.1.0\",\"vnp_TmnCode\":\"HQTPW4RO\",\"vnp_Amount\":165000000,\"vnp_Command\":\"pay\",\"vnp_CreateDate\":\"20250418222915\",\"vnp_CurrCode\":\"VND\",\"vnp_IpAddr\":\"::1\",\"vnp_Locale\":\"vn\",\"vnp_OrderInfo\":\"Thanh toan don hang BUG2504181C3A6 tai Bug Shop\",\"vnp_OrderType\":\"billpayment\",\"vnp_ReturnUrl\":\"http:\\/\\/localhost\\/bug_shop\\/vnpay_return.php\",\"vnp_TxnRef\":\"BUG2504181C3A6\"}', '2025-04-18 15:29:15'),
(8, 17, 2, 'vnpay', '14914525', 1650000.00, '0', '00', '{\"vnp_Amount\":\"165000000\",\"vnp_BankCode\":\"NCB\",\"vnp_BankTranNo\":\"VNP14914525\",\"vnp_CardType\":\"ATM\",\"vnp_OrderInfo\":\"Thanh toan don hang BUG2504181C3A6 tai Bug Shop\",\"vnp_PayDate\":\"20250418223022\",\"vnp_ResponseCode\":\"00\",\"vnp_TmnCode\":\"HQTPW4RO\",\"vnp_TransactionNo\":\"14914525\",\"vnp_TransactionStatus\":\"00\",\"vnp_TxnRef\":\"BUG2504181C3A6\",\"vnp_SecureHash\":\"4d4cd18ef8f83ea796d69a3b12eddd33ed57157a9f764c41f3aee8258da52279ab94e5790738bed41aac94b640c28fc06951e4d11d16458d74dcfd5cb9f7dc10\"}', '2025-04-18 15:30:12'),
(9, 24, 2, 'vnpay', NULL, 1200000.00, 'pending', NULL, '{\"vnp_Version\":\"2.1.0\",\"vnp_TmnCode\":\"HQTPW4RO\",\"vnp_Amount\":120000000,\"vnp_Command\":\"pay\",\"vnp_CreateDate\":\"20250419203019\",\"vnp_CurrCode\":\"VND\",\"vnp_IpAddr\":\"::1\",\"vnp_Locale\":\"vn\",\"vnp_OrderInfo\":\"Thanh toan don hang BUG250419FBE99 tai Bug Shop\",\"vnp_OrderType\":\"billpayment\",\"vnp_ReturnUrl\":\"http:\\/\\/localhost\\/bug_shop\\/vnpay_return.php\",\"vnp_TxnRef\":\"BUG250419FBE99\"}', '2025-04-19 13:30:19'),
(10, 24, 2, 'vnpay', '14915628', 1200000.00, '0', '00', '{\"vnp_Amount\":\"120000000\",\"vnp_BankCode\":\"NCB\",\"vnp_BankTranNo\":\"VNP14915628\",\"vnp_CardType\":\"ATM\",\"vnp_OrderInfo\":\"Thanh toan don hang BUG250419FBE99 tai Bug Shop\",\"vnp_PayDate\":\"20250419203115\",\"vnp_ResponseCode\":\"00\",\"vnp_TmnCode\":\"HQTPW4RO\",\"vnp_TransactionNo\":\"14915628\",\"vnp_TransactionStatus\":\"00\",\"vnp_TxnRef\":\"BUG250419FBE99\",\"vnp_SecureHash\":\"cb74e3d8ad4e0ee8db360ef0a748c26f68d947763928c3338f5bba6fe6591a18654db77f3058f1601fe0aa91bca84aa5c146f4558157d6b9d66a28138cc8ad3d\"}', '2025-04-19 13:31:05'),
(11, 25, 2, 'vnpay', NULL, 1200000.00, 'pending', NULL, '{\"vnp_Version\":\"2.1.0\",\"vnp_TmnCode\":\"HQTPW4RO\",\"vnp_Amount\":120000000,\"vnp_Command\":\"pay\",\"vnp_CreateDate\":\"20250419205208\",\"vnp_CurrCode\":\"VND\",\"vnp_IpAddr\":\"::1\",\"vnp_Locale\":\"vn\",\"vnp_OrderInfo\":\"Thanh toan don hang BUG250419693C7 tai Bug Shop\",\"vnp_OrderType\":\"billpayment\",\"vnp_ReturnUrl\":\"http:\\/\\/localhost\\/bug_shop\\/vnpay_return.php\",\"vnp_TxnRef\":\"BUG250419693C7\"}', '2025-04-19 13:52:08'),
(12, 31, 2, 'vnpay', NULL, 829000.00, 'pending', NULL, '{\"vnp_Version\":\"2.1.0\",\"vnp_TmnCode\":\"HQTPW4RO\",\"vnp_Amount\":82900000,\"vnp_Command\":\"pay\",\"vnp_CreateDate\":\"20250420154637\",\"vnp_CurrCode\":\"VND\",\"vnp_IpAddr\":\"::1\",\"vnp_Locale\":\"vn\",\"vnp_OrderInfo\":\"Thanh toan don hang BUG25042074A43 tai Bug Shop\",\"vnp_OrderType\":\"billpayment\",\"vnp_ReturnUrl\":\"http:\\/\\/localhost\\/bug_shop\\/vnpay_return.php\",\"vnp_TxnRef\":\"BUG25042074A43\"}', '2025-04-20 08:46:37'),
(13, 31, 2, 'vnpay', '14916628', 829000.00, '0', '00', '{\"vnp_Amount\":\"82900000\",\"vnp_BankCode\":\"NCB\",\"vnp_BankTranNo\":\"VNP14916628\",\"vnp_CardType\":\"ATM\",\"vnp_OrderInfo\":\"Thanh toan don hang BUG25042074A43 tai Bug Shop\",\"vnp_PayDate\":\"20250420154729\",\"vnp_ResponseCode\":\"00\",\"vnp_TmnCode\":\"HQTPW4RO\",\"vnp_TransactionNo\":\"14916628\",\"vnp_TransactionStatus\":\"00\",\"vnp_TxnRef\":\"BUG25042074A43\",\"vnp_SecureHash\":\"bb51dbbc21f23cc50d26fd829bf85a14cc13ade793942c7bcca189ee1a0e8ad59b9304218269b8e7552911394abd703abbb4679ca454afd3140a0f8f10035c7f\"}', '2025-04-20 08:47:18'),
(14, 32, 2, 'vnpay', NULL, 859000.00, 'pending', NULL, '{\"vnp_Version\":\"2.1.0\",\"vnp_TmnCode\":\"HQTPW4RO\",\"vnp_Amount\":85900000,\"vnp_Command\":\"pay\",\"vnp_CreateDate\":\"20250420155752\",\"vnp_CurrCode\":\"VND\",\"vnp_IpAddr\":\"::1\",\"vnp_Locale\":\"vn\",\"vnp_OrderInfo\":\"Thanh toan don hang BUG2504204729D60 tai Bug Shop\",\"vnp_OrderType\":\"billpayment\",\"vnp_ReturnUrl\":\"http:\\/\\/localhost\\/bug_shop\\/vnpay_return.php\",\"vnp_TxnRef\":\"BUG2504204729D60\"}', '2025-04-20 08:57:52'),
(15, 33, 2, 'vnpay', NULL, 859000.00, 'pending', NULL, '{\"vnp_Version\":\"2.1.0\",\"vnp_TmnCode\":\"HQTPW4RO\",\"vnp_Amount\":85900000,\"vnp_Command\":\"pay\",\"vnp_CreateDate\":\"20250420155854\",\"vnp_CurrCode\":\"VND\",\"vnp_IpAddr\":\"::1\",\"vnp_Locale\":\"vn\",\"vnp_OrderInfo\":\"Thanh toan don hang BUG250420534F98E tai Bug Shop\",\"vnp_OrderType\":\"billpayment\",\"vnp_ReturnUrl\":\"http:\\/\\/localhost\\/bug_shop\\/vnpay_return.php\",\"vnp_TxnRef\":\"BUG250420534F98E\"}', '2025-04-20 08:58:54'),
(16, 33, 2, 'vnpay', '14916646', 859000.00, '0', '00', '{\"vnp_Amount\":\"85900000\",\"vnp_BankCode\":\"NCB\",\"vnp_BankTranNo\":\"VNP14916646\",\"vnp_CardType\":\"ATM\",\"vnp_OrderInfo\":\"Thanh toan don hang BUG250420534F98E tai Bug Shop\",\"vnp_PayDate\":\"20250420155941\",\"vnp_ResponseCode\":\"00\",\"vnp_TmnCode\":\"HQTPW4RO\",\"vnp_TransactionNo\":\"14916646\",\"vnp_TransactionStatus\":\"00\",\"vnp_TxnRef\":\"BUG250420534F98E\",\"vnp_SecureHash\":\"381158b57bf40dd9290ade2a4606c3fa8ee0741ce02fc344e324a65c4f604a33cf8cbb070fe03beff321d0de5346a2c4101b3208e78604c0c34859f01aa346d4\"}', '2025-04-20 08:59:30'),
(17, 34, 2, 'vnpay', NULL, 1680000.00, 'pending', NULL, '{\"vnp_Version\":\"2.1.0\",\"vnp_TmnCode\":\"HQTPW4RO\",\"vnp_Amount\":168000000,\"vnp_Command\":\"pay\",\"vnp_CreateDate\":\"20250422152033\",\"vnp_CurrCode\":\"VND\",\"vnp_IpAddr\":\"::1\",\"vnp_Locale\":\"vn\",\"vnp_OrderInfo\":\"Thanh toan don hang BUG250422033A390 tai Bug Shop\",\"vnp_OrderType\":\"billpayment\",\"vnp_ReturnUrl\":\"http:\\/\\/localhost\\/bug_shop\\/vnpay_return.php\",\"vnp_TxnRef\":\"BUG250422033A390\"}', '2025-04-22 08:20:33'),
(18, 35, NULL, 'vnpay', NULL, 2230000.00, 'pending', NULL, '{\"vnp_Version\":\"2.1.0\",\"vnp_TmnCode\":\"HQTPW4RO\",\"vnp_Amount\":223000000,\"vnp_Command\":\"pay\",\"vnp_CreateDate\":\"20250423165727\",\"vnp_CurrCode\":\"VND\",\"vnp_IpAddr\":\"::1\",\"vnp_Locale\":\"vn\",\"vnp_OrderInfo\":\"Thanh toan don hang BUG2504232474BE3 tai Bug Shop\",\"vnp_OrderType\":\"billpayment\",\"vnp_ReturnUrl\":\"http:\\/\\/localhost\\/bug_shop\\/vnpay_return.php\",\"vnp_TxnRef\":\"BUG2504232474BE3\"}', '2025-04-23 09:57:27'),
(19, 35, NULL, 'vnpay', '14922636', 2230000.00, '0', '01', '{\"vnp_Amount\":\"223000000\",\"vnp_BankCode\":\"NCB\",\"vnp_CardType\":\"ATM\",\"vnp_OrderInfo\":\"Thanh toan don hang BUG2504232474BE3 tai Bug Shop\",\"vnp_PayDate\":\"20250423165823\",\"vnp_ResponseCode\":\"01\",\"vnp_TmnCode\":\"HQTPW4RO\",\"vnp_TransactionNo\":\"14922636\",\"vnp_TransactionStatus\":\"02\",\"vnp_TxnRef\":\"BUG2504232474BE3\",\"vnp_SecureHash\":\"c233184d205121936ea7b8624818cb2cc1df576061dd31b0d0361a42189c5521502fc9e0e86f5fe50f444ffe2e0bfdb368e3a02288bae3c0de4b027295ec6d2c\"}', '2025-04-23 09:58:18'),
(20, 36, NULL, 'vnpay', NULL, 2530000.00, 'pending', NULL, '{\"vnp_Version\":\"2.1.0\",\"vnp_TmnCode\":\"HQTPW4RO\",\"vnp_Amount\":253000000,\"vnp_Command\":\"pay\",\"vnp_CreateDate\":\"20250423165905\",\"vnp_CurrCode\":\"VND\",\"vnp_IpAddr\":\"::1\",\"vnp_Locale\":\"vn\",\"vnp_OrderInfo\":\"Thanh toan don hang BUG2504233452CDF tai Bug Shop\",\"vnp_OrderType\":\"billpayment\",\"vnp_ReturnUrl\":\"http:\\/\\/localhost\\/bug_shop\\/vnpay_return.php\",\"vnp_TxnRef\":\"BUG2504233452CDF\"}', '2025-04-23 09:59:05'),
(21, 36, NULL, 'vnpay', '14922639', 2530000.00, '0', '00', '{\"vnp_Amount\":\"253000000\",\"vnp_BankCode\":\"NCB\",\"vnp_BankTranNo\":\"VNP14922639\",\"vnp_CardType\":\"ATM\",\"vnp_OrderInfo\":\"Thanh toan don hang BUG2504233452CDF tai Bug Shop\",\"vnp_PayDate\":\"20250423165946\",\"vnp_ResponseCode\":\"00\",\"vnp_TmnCode\":\"HQTPW4RO\",\"vnp_TransactionNo\":\"14922639\",\"vnp_TransactionStatus\":\"00\",\"vnp_TxnRef\":\"BUG2504233452CDF\",\"vnp_SecureHash\":\"956dd0ff674480a5624131409695e3e27c7d2dd0aea31728a539aa3a7e6fd818d6958068ef8d32cd10de3064fba24b2f58063d4433a14c84a9d9c4ae8c9e749c\"}', '2025-04-23 09:59:36'),
(22, 36, NULL, 'vnpay', '14922639', 2530000.00, '0', '00', '{\"vnp_Amount\":\"253000000\",\"vnp_BankCode\":\"NCB\",\"vnp_BankTranNo\":\"VNP14922639\",\"vnp_CardType\":\"ATM\",\"vnp_OrderInfo\":\"Thanh toan don hang BUG2504233452CDF tai Bug Shop\",\"vnp_PayDate\":\"20250423165946\",\"vnp_ResponseCode\":\"00\",\"vnp_TmnCode\":\"HQTPW4RO\",\"vnp_TransactionNo\":\"14922639\",\"vnp_TransactionStatus\":\"00\",\"vnp_TxnRef\":\"BUG2504233452CDF\",\"vnp_SecureHash\":\"956dd0ff674480a5624131409695e3e27c7d2dd0aea31728a539aa3a7e6fd818d6958068ef8d32cd10de3064fba24b2f58063d4433a14c84a9d9c4ae8c9e749c\"}', '2025-04-23 09:59:56'),
(23, 43, NULL, 'vnpay', NULL, 2530000.00, 'pending', NULL, '{\"vnp_Version\":\"2.1.0\",\"vnp_TmnCode\":\"HQTPW4RO\",\"vnp_Amount\":253000000,\"vnp_Command\":\"pay\",\"vnp_CreateDate\":\"20250507164405\",\"vnp_CurrCode\":\"VND\",\"vnp_IpAddr\":\"::1\",\"vnp_Locale\":\"vn\",\"vnp_OrderInfo\":\"Thanh toan don hang BUG250507044DAE3 tai Bug Shop\",\"vnp_OrderType\":\"billpayment\",\"vnp_ReturnUrl\":\"http:\\/\\/localhost\\/bug_shop\\/vnpay_return.php\",\"vnp_TxnRef\":\"BUG250507044DAE3\"}', '2025-05-07 09:44:05'),
(24, 43, NULL, 'vnpay', '14943891', 2530000.00, '0', '00', '{\"vnp_Amount\":\"253000000\",\"vnp_BankCode\":\"NCB\",\"vnp_BankTranNo\":\"VNP14943891\",\"vnp_CardType\":\"ATM\",\"vnp_OrderInfo\":\"Thanh toan don hang BUG250507044DAE3 tai Bug Shop\",\"vnp_PayDate\":\"20250507164514\",\"vnp_ResponseCode\":\"00\",\"vnp_TmnCode\":\"HQTPW4RO\",\"vnp_TransactionNo\":\"14943891\",\"vnp_TransactionStatus\":\"00\",\"vnp_TxnRef\":\"BUG250507044DAE3\",\"vnp_SecureHash\":\"ffc15b70bbeb800c2ec19cac9c7351ce931cace14d880d1643098719ab0f2183333a367a933042db83394d1ffd268440f268a3ce74e829e53131e92379f5e981\"}', '2025-05-07 09:45:09'),
(25, 52, 2, 'vnpay', NULL, 30000.00, 'pending', NULL, '{\"vnp_Version\":\"2.1.0\",\"vnp_TmnCode\":\"HQTPW4RO\",\"vnp_Amount\":3000000,\"vnp_Command\":\"pay\",\"vnp_CreateDate\":\"20250516210617\",\"vnp_CurrCode\":\"VND\",\"vnp_IpAddr\":\"::1\",\"vnp_Locale\":\"vn\",\"vnp_OrderInfo\":\"Thanh toan don hang BUG250516377303E tai Bug Shop\",\"vnp_OrderType\":\"billpayment\",\"vnp_ReturnUrl\":\"http:\\/\\/localhost\\/bug_shop\\/vnpay_return.php\",\"vnp_TxnRef\":\"BUG250516377303E\"}', '2025-05-16 14:06:17'),
(26, 52, 2, 'vnpay', '14961586', 30000.00, '0', '00', '{\"vnp_Amount\":\"3000000\",\"vnp_BankCode\":\"NCB\",\"vnp_BankTranNo\":\"VNP14961586\",\"vnp_CardType\":\"ATM\",\"vnp_OrderInfo\":\"Thanh toan don hang BUG250516377303E tai Bug Shop\",\"vnp_PayDate\":\"20250516210724\",\"vnp_ResponseCode\":\"00\",\"vnp_TmnCode\":\"HQTPW4RO\",\"vnp_TransactionNo\":\"14961586\",\"vnp_TransactionStatus\":\"00\",\"vnp_TxnRef\":\"BUG250516377303E\",\"vnp_SecureHash\":\"be88ed037db65040d529d7e3ac9f5eaf4f0dae0d2064e2df38f0c7b769db6b457801bc7b36fc32fa57a2b506c5bafa6178ca6a9561694b99166c18e3f5e277d0\"}', '2025-05-16 14:07:01'),
(27, 60, NULL, 'vnpay', NULL, 30000.00, 'pending', NULL, '{\"vnp_Version\":\"2.1.0\",\"vnp_TmnCode\":\"HQTPW4RO\",\"vnp_Amount\":3000000,\"vnp_Command\":\"pay\",\"vnp_CreateDate\":\"20250517223201\",\"vnp_CurrCode\":\"VND\",\"vnp_IpAddr\":\"::1\",\"vnp_Locale\":\"vn\",\"vnp_OrderInfo\":\"Thanh toan don hang BUG2505179216ABF tai Bug Shop\",\"vnp_OrderType\":\"billpayment\",\"vnp_ReturnUrl\":\"http:\\/\\/localhost\\/bug_shop\\/vnpay_return.php\",\"vnp_TxnRef\":\"BUG2505179216ABF\"}', '2025-05-17 15:32:01'),
(28, 60, NULL, 'vnpay', '14963031', 30000.00, '0', '00', '{\"vnp_Amount\":\"3000000\",\"vnp_BankCode\":\"NCB\",\"vnp_BankTranNo\":\"VNP14963031\",\"vnp_CardType\":\"ATM\",\"vnp_OrderInfo\":\"Thanh toan don hang BUG2505179216ABF tai Bug Shop\",\"vnp_PayDate\":\"20250517223605\",\"vnp_ResponseCode\":\"00\",\"vnp_TmnCode\":\"HQTPW4RO\",\"vnp_TransactionNo\":\"14963031\",\"vnp_TransactionStatus\":\"00\",\"vnp_TxnRef\":\"BUG2505179216ABF\",\"vnp_SecureHash\":\"f8fe1ce2788bb86f5719e909b2c804ccb365413452b30d36081496947203cacb3ca7c0d624274df4f46e7f93996609f2c950aacd86c47459159860c5238be552\"}', '2025-05-17 15:35:48'),
(29, 62, NULL, 'vnpay', NULL, 30000.00, 'pending', NULL, '{\"vnp_Version\":\"2.1.0\",\"vnp_TmnCode\":\"HQTPW4RO\",\"vnp_Amount\":3000000,\"vnp_Command\":\"pay\",\"vnp_CreateDate\":\"20250520150830\",\"vnp_CurrCode\":\"VND\",\"vnp_IpAddr\":\"::1\",\"vnp_Locale\":\"vn\",\"vnp_OrderInfo\":\"Thanh toan don hang BUG250520510F406 tai Bug Shop\",\"vnp_OrderType\":\"billpayment\",\"vnp_ReturnUrl\":\"http:\\/\\/localhost\\/bug_shop\\/vnpay_return.php\",\"vnp_TxnRef\":\"BUG250520510F406\"}', '2025-05-20 08:08:30'),
(30, 62, NULL, 'vnpay', '14968035', 30000.00, '0', '00', '{\"vnp_Amount\":\"3000000\",\"vnp_BankCode\":\"NCB\",\"vnp_BankTranNo\":\"VNP14968035\",\"vnp_CardType\":\"ATM\",\"vnp_OrderInfo\":\"Thanh toan don hang BUG250520510F406 tai Bug Shop\",\"vnp_PayDate\":\"20250520151022\",\"vnp_ResponseCode\":\"00\",\"vnp_TmnCode\":\"HQTPW4RO\",\"vnp_TransactionNo\":\"14968035\",\"vnp_TransactionStatus\":\"00\",\"vnp_TxnRef\":\"BUG250520510F406\",\"vnp_SecureHash\":\"ae18ca0cfad46d160c74095d05f5088094d2b4f618cb16951925f4d05e46a378ad09004612a20267d8c3dc72c4067ea82891f56c8ad99cde33e580f2bf8f3960\"}', '2025-05-20 08:09:57');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `quyen_han`
--

CREATE TABLE `quyen_han` (
  `id` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `module` varchar(50) NOT NULL COMMENT 'sanpham, donhang, khuyenmai, danhmuc, nguoidung, baocao, caidat',
  `quyen` varchar(20) NOT NULL COMMENT 'view, add, edit, delete'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `sanpham`
--

CREATE TABLE `sanpham` (
  `id` int(11) NOT NULL,
  `tensanpham` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `id_danhmuc` int(11) NOT NULL,
  `gia` decimal(12,2) DEFAULT 0.00,
  `giagoc` decimal(12,2) DEFAULT 0.00,
  `hinhanh` varchar(255) DEFAULT NULL,
  `mota` text DEFAULT NULL,
  `mota_ngan` varchar(500) DEFAULT NULL,
  `thuoc_tinh_chung` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Lưu trữ các thuộc tính như chất liệu, xuất xứ...' CHECK (json_valid(`thuoc_tinh_chung`)),
  `so_luong` int(11) DEFAULT 0,
  `da_ban` int(11) DEFAULT 0,
  `noibat` tinyint(1) DEFAULT 0,
  `trangthai` tinyint(1) DEFAULT 1,
  `ngay_tao` timestamp NOT NULL DEFAULT current_timestamp(),
  `ngay_capnhat` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `thuonghieu` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `sanpham`
--

INSERT INTO `sanpham` (`id`, `tensanpham`, `slug`, `id_danhmuc`, `gia`, `giagoc`, `hinhanh`, `mota`, `mota_ngan`, `thuoc_tinh_chung`, `so_luong`, `da_ban`, `noibat`, `trangthai`, `ngay_tao`, `ngay_capnhat`, `thuonghieu`) VALUES
(25, 'Giày VANS OLD SKOOL LOGO CHECK BLACK', 'gi-y-vans-old-skool-logo-check-black', 1, 1750000.00, 1750000.00, 'uploads/products/1744982672_giay-vans-old-skool-logo-check-black-vn0005ufcji-1.webp', 'đẹp', NULL, NULL, 459, 0, 0, 1, '2025-04-18 13:24:32', '2025-04-19 13:29:17', 6),
(27, 'Giày Nike Court Vision Mid Smoke Grey DN3577-002', 'gi-y-nike-court-vision-mid-smoke-grey-dn3577-002', 1, 1650000.00, 1950000.00, 'uploads/products/1745072553_giay-nike-court-vision-mid-smoke-grey-dn3577-002-44-500x333.jpg', 'đẹp', NULL, NULL, 1185, 0, 0, 1, '2025-04-19 14:22:33', '2025-04-19 14:38:30', 1),
(29, 'Giày Thể Thao Biti\'s Hunter Core Nữ Màu Xám HSW009100XAM', 'giay-the-thao-biti-s-hunter-core-nu-mau-xam-hsw009100xam', 2, 829000.00, 829000.00, 'uploads/products/1745138767_hsw009100xam__9__2e78f77d4ddf440791f12cc6a96eb54d_master.webp', '<p>test</p>', 'đẹp', NULL, 592, 0, 0, 1, '2025-04-20 08:46:07', '2025-05-20 13:20:52', 2),
(30, 'Giày Adidas Ultraboost 21', 'giay-adidas-ultraboost-21', 3, 2500000.00, 3000000.00, 'uploads/products/1745143448_swift-run-x-shoes-blue-fy2137-05-standard-06d44d80-aeba-438a-bbe5-8a06b450d041.webp', 'Giày Adidas Ultraboost 21 với công nghệ Boost tiên tiến, mang lại cảm giác êm ái và hỗ trợ tối ưu khi chạy bộ.', 'Giày chạy bộ cao cấp từ Adidas', NULL, 597, 0, 1, 1, '2025-04-20 09:58:47', '2025-05-07 10:16:34', 7),
(31, 'Giày Puma RS-X3', 'giay-puma-rsx3', 3, 2200000.00, 2500000.00, 'uploads/products/1745143505_05_371570_1_187846925565492498b18d9f07574556_large.webp', 'Giày Puma RS-X3 mang phong cách năng động, phù hợp cho các hoạt động thể thao và thời trang hàng ngày.', 'Giày thể thao Puma RS-X3 với thiết kế hiện đại', NULL, 410, 0, 1, 1, '2025-04-20 10:00:26', '2025-05-20 14:02:53', 5),
(32, 'Giày Converse Chuck Taylor All Star', 'giay-converse-chuck-taylor-all-star', 4, 1500000.00, 1800000.00, 'uploads/products/1745143609_download (8).jpg', 'Giày Converse Chuck Taylor All Star với thiết kế cổ điển, phù hợp cho mọi lứa tuổi.', 'Giày Converse cổ điển, phong cách trẻ trung', NULL, 598, 0, 0, 1, '2025-04-20 10:00:26', '2025-05-16 17:00:27', 4),
(33, 'Dép Nike Air Max', 'dep-nike-air-max', 7, 900000.00, 1200000.00, 'uploads/products/1745160364_dc14600072e372836368eb4493b7b5-0d37fff9-697e-478f-951c-6105da6aa61b.webp', 'Dép Nike Air Max với đế êm ái, phù hợp cho các hoạt động ngoài trời.', 'Dép Nike Air Max thoải mái cho mùa hè', NULL, 295, 0, 1, 1, '2025-04-20 10:00:26', '2025-05-20 14:34:31', 1),
(34, 'Urbas Love+ 23 - Slip On - Offwhite', 'urbas-love-23-slip-on-offwhite', 4, 550000.00, 1000000.00, 'uploads/products/1747749082_Pro_ALP2023_1.jpg', '', 'hiệu nhà dứa', NULL, 199, 0, 1, 1, '2025-05-20 13:51:22', '2025-05-20 14:02:53', 9);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `sanpham_bien_the`
--

CREATE TABLE `sanpham_bien_the` (
  `id` int(11) NOT NULL,
  `id_sanpham` int(11) NOT NULL,
  `id_mau` int(11) NOT NULL,
  `id_size` int(11) NOT NULL,
  `ma_bien_the` varchar(50) DEFAULT NULL,
  `hinhanh` varchar(255) DEFAULT NULL,
  `gia_bien_the` decimal(12,2) DEFAULT NULL COMMENT 'Nếu NULL thì sử dụng giá của sản phẩm cha',
  `so_luong` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `sanpham_bien_the`
--

INSERT INTO `sanpham_bien_the` (`id`, `id_sanpham`, `id_mau`, `id_size`, `ma_bien_the`, `hinhanh`, `gia_bien_the`, `so_luong`) VALUES
(86, 25, 12, 4, NULL, NULL, NULL, 67),
(87, 25, 12, 5, NULL, NULL, NULL, 66),
(88, 25, 12, 6, NULL, NULL, NULL, 66),
(89, 25, 12, 7, NULL, NULL, NULL, 66),
(90, 25, 12, 8, NULL, NULL, NULL, 62),
(91, 25, 12, 9, NULL, NULL, NULL, 66),
(92, 25, 12, 10, NULL, NULL, NULL, 66),
(100, 27, 11, 6, NULL, NULL, NULL, 99),
(101, 27, 15, 6, NULL, NULL, NULL, 99),
(102, 27, 11, 7, NULL, NULL, NULL, 96),
(103, 27, 15, 7, NULL, NULL, NULL, 99),
(104, 27, 11, 8, NULL, NULL, NULL, 99),
(105, 27, 15, 8, NULL, NULL, NULL, 99),
(106, 27, 11, 9, NULL, NULL, NULL, 99),
(107, 27, 15, 9, NULL, NULL, NULL, 99),
(108, 27, 11, 10, NULL, NULL, NULL, 99),
(109, 27, 15, 10, NULL, NULL, NULL, 99),
(110, 27, 11, 19, NULL, NULL, NULL, 99),
(111, 27, 15, 19, NULL, NULL, NULL, 98),
(114, 29, 11, 8, NULL, NULL, NULL, 541),
(115, 29, 13, 9, NULL, NULL, NULL, 51),
(116, 30, 11, 6, NULL, NULL, NULL, 100),
(117, 30, 11, 7, NULL, NULL, NULL, 100),
(118, 30, 11, 8, NULL, NULL, NULL, 97),
(119, 30, 12, 6, NULL, NULL, NULL, 100),
(120, 30, 12, 7, NULL, NULL, NULL, 100),
(121, 30, 12, 8, NULL, NULL, NULL, 100),
(122, 31, 14, 5, NULL, NULL, NULL, 67),
(123, 31, 14, 6, NULL, NULL, NULL, 70),
(124, 31, 14, 7, NULL, NULL, NULL, 70),
(125, 31, 15, 5, NULL, NULL, NULL, 70),
(126, 31, 15, 6, NULL, NULL, NULL, 69),
(127, 31, 15, 7, NULL, NULL, NULL, 60),
(128, 32, 11, 4, NULL, NULL, NULL, 100),
(129, 32, 11, 5, NULL, NULL, NULL, 100),
(130, 32, 11, 6, NULL, NULL, NULL, 98),
(131, 32, 12, 4, NULL, NULL, NULL, 100),
(132, 32, 12, 5, NULL, NULL, NULL, 100),
(133, 32, 12, 6, NULL, NULL, NULL, 100),
(134, 33, 13, 7, NULL, NULL, NULL, 75),
(135, 33, 13, 8, NULL, NULL, NULL, 75),
(136, 33, 16, 7, NULL, NULL, NULL, 75),
(137, 33, 16, 8, NULL, NULL, NULL, 70),
(138, 34, 11, 2, NULL, NULL, NULL, 20),
(140, 34, 11, 3, NULL, NULL, NULL, 10),
(141, 34, 11, 4, NULL, NULL, NULL, 20),
(142, 34, 11, 5, NULL, NULL, NULL, 50),
(143, 34, 11, 6, NULL, NULL, NULL, 99);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `sanpham_hinhanh`
--

CREATE TABLE `sanpham_hinhanh` (
  `id` int(11) NOT NULL,
  `id_sanpham` int(11) NOT NULL,
  `id_bienthe` int(11) DEFAULT NULL,
  `hinhanh` varchar(255) NOT NULL,
  `la_anh_chinh` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `sanpham_hinhanh`
--

INSERT INTO `sanpham_hinhanh` (`id`, `id_sanpham`, `id_bienthe`, `hinhanh`, `la_anh_chinh`) VALUES
(19, 25, NULL, 'uploads/products/1744982672_giay-vans-old-skool-logo-check-black-vn0005ufcji-1.webp', 1),
(21, 27, NULL, 'uploads/products/1745072553_giay-nike-court-vision-mid-smoke-grey-dn3577-002-44-500x333.jpg', 1),
(28, 29, NULL, 'uploads/products/1745138767_hsw009100xam__9__2e78f77d4ddf440791f12cc6a96eb54d_master.webp', 1),
(33, 30, NULL, 'uploads/products/1745143448_swift-run-x-shoes-blue-fy2137-05-standard-06d44d80-aeba-438a-bbe5-8a06b450d041.webp', 1),
(34, 31, NULL, 'uploads/products/1745143505_05_371570_1_187846925565492498b18d9f07574556_large.webp', 1),
(35, 32, NULL, 'uploads/products/1745143609_download (8).jpg', 1),
(36, 32, NULL, 'uploads/products/1745143609_0_85_d6e9ffc23f734be2986e02de06117d68_master.webp', 0),
(38, 33, NULL, 'uploads/products/1745143725_0_DC1460-004-1.webp', 0),
(39, 33, NULL, 'uploads/products/1745160364_dc14600072e372836368eb4493b7b5-0d37fff9-697e-478f-951c-6105da6aa61b.webp', 1),
(40, 33, NULL, 'uploads/products/1745160364_0_DC1460-004-1.webp', 0),
(41, 34, NULL, 'uploads/products/1747749082_Pro_ALP2023_1.jpg', 1),
(42, 34, NULL, 'uploads/products/1747749082_0_đáy dứa.jpg', 0);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_group` varchar(50) NOT NULL DEFAULT 'general',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `setting_group`, `created_at`, `updated_at`) VALUES
(1, 'site_name', 'Bug Shop', 'general', '2025-04-17 07:59:57', '2025-04-17 07:59:57'),
(2, 'site_description', 'Cửa hàng giày dép chất lượng cao', 'general', '2025-04-17 07:59:57', '2025-04-17 07:59:57'),
(3, 'contact_email', '20210140@eaut.edu.vn', 'general', '2025-04-17 07:59:57', '2025-05-07 16:24:46'),
(4, 'contact_phone', '0123456789', 'general', '2025-04-17 07:59:57', '2025-04-17 07:59:57'),
(5, 'address', 'Đại học Công Nghệ Đông Á', 'general', '2025-04-17 07:59:57', '2025-05-07 16:24:20'),
(6, 'logo', '', 'general', '2025-04-17 07:59:57', '2025-04-17 07:59:57'),
(7, 'favicon', '', 'general', '2025-04-17 07:59:57', '2025-04-17 07:59:57'),
(8, 'smtp_host', '', 'email', '2025-04-17 07:59:57', '2025-04-17 07:59:57'),
(9, 'smtp_port', '587', 'email', '2025-04-17 07:59:57', '2025-04-17 07:59:57'),
(10, 'smtp_username', '', 'email', '2025-04-17 07:59:57', '2025-04-17 07:59:57'),
(11, 'smtp_password', '', 'email', '2025-04-17 07:59:57', '2025-04-17 07:59:57'),
(12, 'smtp_encryption', 'tls', 'email', '2025-04-17 07:59:57', '2025-04-17 07:59:57'),
(13, 'email_sender', 'no-reply@bugshop.com', 'email', '2025-04-17 07:59:57', '2025-04-17 07:59:57'),
(14, 'email_sender_name', 'Bug Shop', 'email', '2025-04-17 07:59:57', '2025-04-17 07:59:57'),
(15, 'default_shipping_fee', '30000', 'order', '2025-04-17 07:59:57', '2025-04-17 07:59:57'),
(16, 'free_shipping_threshold', '500000', 'order', '2025-04-17 07:59:57', '2025-04-17 07:59:57'),
(17, 'order_prefix', 'BUG-', 'order', '2025-04-17 07:59:57', '2025-04-17 07:59:57'),
(18, 'enable_cod', '1', 'order', '2025-04-17 07:59:57', '2025-04-17 07:59:57'),
(19, 'enable_bank_transfer', '1', 'order', '2025-04-17 07:59:57', '2025-04-17 07:59:57'),
(20, 'facebook_url', 'https://www.facebook.com/thai.dui57', 'social', '2025-04-17 07:59:57', '2025-05-17 15:14:55'),
(21, 'instagram_url', '', 'social', '2025-04-17 07:59:57', '2025-04-17 07:59:57'),
(22, 'twitter_url', '', 'social', '2025-04-17 07:59:57', '2025-04-17 07:59:57'),
(23, 'youtube_url', '', 'social', '2025-04-17 07:59:57', '2025-04-17 07:59:57');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `thuoc_tinh`
--

CREATE TABLE `thuoc_tinh` (
  `id` int(11) NOT NULL,
  `ten` varchar(100) NOT NULL,
  `loai` varchar(50) NOT NULL COMMENT 'size, color, material',
  `gia_tri` varchar(100) NOT NULL,
  `ma_mau` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `thuoc_tinh`
--

INSERT INTO `thuoc_tinh` (`id`, `ten`, `loai`, `gia_tri`, `ma_mau`) VALUES
(1, 'Size 35', 'size', '35', NULL),
(2, 'Size 36', 'size', '36', NULL),
(3, 'Size 37', 'size', '37', NULL),
(4, 'Size 38', 'size', '38', NULL),
(5, 'Size 39', 'size', '39', NULL),
(6, 'Size 40', 'size', '40', NULL),
(7, 'Size 41', 'size', '41', NULL),
(8, 'Size 42', 'size', '42', NULL),
(9, 'Size 43', 'size', '43', NULL),
(10, 'Size 44', 'size', '44', NULL),
(11, 'Trắng', 'color', 'Trắng', '#FFFFFF'),
(12, 'Đen', 'color', 'Đen', '#000000'),
(13, 'Xám', 'color', 'Xám', '#808080'),
(14, 'Đỏ', 'color', 'Đỏ', '#FF0000'),
(15, 'Xanh dương', 'color', 'Xanh dương', '#0000FF'),
(16, 'Xanh lá', 'color', 'Xanh lá', '#77ee77'),
(17, 'Vàng', 'color', 'Vàng', '#FFFF00'),
(18, 'Hồng', 'color', 'Hồng', '#FFC0CB'),
(19, 'size45.5', 'size', '45.5', NULL),
(20, 'size34', 'size', '34', NULL),
(22, 'xám-xanh', 'color', '#c4cae9', '#c4cae9');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `thuong_hieu`
--

CREATE TABLE `thuong_hieu` (
  `id` int(11) NOT NULL,
  `ten` varchar(255) NOT NULL,
  `mo_ta` text DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `thuong_hieu`
--

INSERT INTO `thuong_hieu` (`id`, `ten`, `mo_ta`, `logo`) VALUES
(1, 'nike', 'đẹp', 'brand_6801e51894ecd.jpg'),
(2, 'bitis', 'thương hiệu Việt', 'brand_6801e8d133414.png'),
(4, 'Converse', 'Thương hiệu giày classic với lịch sử lâu đời', 'brand_68027a2f47105.jpg'),
(5, 'Puma', 'Thương hiệu thời trang thể thao từ Đức', 'brand_68027a120bf39.jpg'),
(6, 'Vans', 'Thương hiệu giày skate nổi tiếng từ Mỹ', 'brand_680279f54832e.jpg'),
(7, 'adidas', 'đẹp', 'brand_6804c03ebebb9.png'),
(9, 'ananas', 'dứa', 'brand_682c8781d7286.png');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `taikhoan` varchar(100) NOT NULL,
  `matkhau` varchar(255) NOT NULL,
  `ten` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `sodienthoai` varchar(20) DEFAULT NULL,
  `diachi` text DEFAULT NULL,
  `tinh_tp` varchar(100) DEFAULT NULL,
  `quan_huyen` varchar(100) DEFAULT NULL,
  `phuong_xa` varchar(100) DEFAULT NULL,
  `loai_user` tinyint(1) DEFAULT 0 COMMENT '0: Khách hàng, 1: Quản lý, 2: Admin',
  `anh_dai_dien` varchar(255) DEFAULT NULL,
  `trang_thai` tinyint(1) DEFAULT 1 COMMENT '0: Khóa, 1: Hoạt động',
  `ngay_tao` timestamp NOT NULL DEFAULT current_timestamp(),
  `lan_dang_nhap_cuoi` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `users`
--

INSERT INTO `users` (`id`, `taikhoan`, `matkhau`, `ten`, `email`, `sodienthoai`, `diachi`, `tinh_tp`, `quan_huyen`, `phuong_xa`, `loai_user`, `anh_dai_dien`, `trang_thai`, `ngay_tao`, `lan_dang_nhap_cuoi`) VALUES
(1, 'admin', '$2y$10$OgfjPpPCdm5ifub40rOkBelmuuN7QanMzqYblp1wNDxcyQ0P8nr8G', 'Super Administrator', 'admin@bugshop.com', NULL, NULL, NULL, NULL, NULL, 2, NULL, 1, '2025-04-17 06:03:11', '2025-05-20 14:04:52'),
(2, 'thai', '$2y$10$A35ZWJyrY12PksyMgMoRZewmz5.Kiho4dZCpGRyc2NxlbZuXMPrZi', 'nguyen xuan thai', 'tobenha4@gmail.com', '0123456789', 'đông tiến', 'Hải Dương', 'Tứ Kỳ', 'Hưng Đạo', 0, NULL, 1, '2025-04-17 06:52:30', '2025-04-17 14:54:21'),
(3, 'lovisong', '$2y$10$C/1B7YYeDnyCstxtncvyIOpGGuUNkFMLLZWhedrimMBsL46Yhhb6e', 'lò vi sóng', 'song@gmail.com', '0223456789', 'làng xóm', NULL, NULL, NULL, 0, NULL, 1, '2025-04-17 17:09:40', NULL),
(4, 'thaimng', '$2y$10$9HStd.K629E/yrxBkc2JEOxmGmwT1ZcLXavW5wYIHn5FkVD4ByhOu', 'mng', 'mng@gmail.com', NULL, NULL, NULL, NULL, NULL, 1, NULL, 1, '2025-04-18 10:34:09', '2025-04-20 08:30:44'),
(5, 'nguyenvan', '$2y$10$OgfjPpPCdm5ifub40rOkBelmuuN7QanMzqYblp1wNDxcyQ0P8nr8G', 'Nguyễn Văn A', 'nguyenvana@example.com', '0987654321', '15 Nguyễn Huệ', 'Hồ Chí Minh', 'Quận 1', NULL, 0, NULL, 1, '2025-04-18 10:46:04', NULL),
(6, 'tranthib', '$2y$10$OgfjPpPCdm5ifub40rOkBelmuuN7QanMzqYblp1wNDxcyQ0P8nr8G', 'Trần Thị B', 'tranthib@example.com', '0912345678', '27 Lê Lợi', 'Hà Nội', 'Hoàn Kiếm', NULL, 0, NULL, 1, '2025-04-18 10:46:04', NULL),
(7, 'phamtuanc', '$2y$10$OgfjPpPCdm5ifub40rOkBelmuuN7QanMzqYblp1wNDxcyQ0P8nr8G', 'Phạm Tuấn C', 'phamtuanc@example.com', '0956781234', '72 Trần Phú', 'Đà Nẵng', 'Hải Châu', NULL, 0, NULL, 1, '2025-04-18 10:46:04', NULL),
(8, 'staff1', '$2y$10$TULD9RZwssYxaMSeFPeWLew8KVYtSC4hdMtso35mOk6O5eBmkHjKu', 'Nhân viên Kinh doanh', 'staff1@bugshop.com', '0901234567', NULL, NULL, NULL, NULL, 1, NULL, 1, '2025-04-18 10:46:04', '2025-04-20 14:46:28'),
(9, 'thaidui', '$2y$10$L8NleMv41wPEOaiVXwR7t.XbiBT.QQG0IvQdAKBAtsn/IIHzDNTy2', 'thaidui', 'thaidui@gmail.com', '0123456789', NULL, NULL, NULL, NULL, 0, NULL, 1, '2025-04-19 07:35:49', NULL),
(10, 'mng1', '$2y$10$4zXl9yRMVhEd5mloIwKjKOREm97SV4MjDsPqYiSruacStZqbKxVZe', 'mng1', 'mng1@gmail.com', '0123456785', NULL, NULL, NULL, NULL, 1, NULL, 1, '2025-04-20 09:51:49', '2025-04-20 09:52:09'),
(11, 'thai2', '$2y$10$/scLgoCWQSmH7oIjiU9I6uoAtUnrZVsKZ6KoLzBU9waINBftbJVnG', 'thai', 'thai2@gmail.com', '0123456781', 'xóm làng', NULL, NULL, NULL, 0, NULL, 1, '2025-05-17 03:10:48', NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `yeu_thich`
--

CREATE TABLE `yeu_thich` (
  `id` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `id_sanpham` int(11) NOT NULL,
  `ngay_tao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `yeu_thich`
--

INSERT INTO `yeu_thich` (`id`, `id_user`, `id_sanpham`, `ngay_tao`) VALUES
(136, 11, 31, '2025-05-17 08:32:11'),
(140, 3, 34, '2025-05-20 14:01:29'),
(141, 3, 31, '2025-05-20 14:01:30');

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `danhgia`
--
ALTER TABLE `danhgia`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_danhgia_sanpham_idx` (`id_sanpham`),
  ADD KEY `fk_danhgia_user_idx` (`id_user`),
  ADD KEY `fk_danhgia_donhang_idx` (`id_donhang`);

--
-- Chỉ mục cho bảng `danhmuc`
--
ALTER TABLE `danhmuc`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `fk_danhmuc_cha_idx` (`danhmuc_cha`);

--
-- Chỉ mục cho bảng `donhang`
--
ALTER TABLE `donhang`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ma_donhang` (`ma_donhang`),
  ADD KEY `fk_donhang_user_idx` (`id_user`);

--
-- Chỉ mục cho bảng `donhang_chitiet`
--
ALTER TABLE `donhang_chitiet`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_donhangct_donhang_idx` (`id_donhang`),
  ADD KEY `fk_donhangct_sanpham_idx` (`id_sanpham`),
  ADD KEY `fk_donhangct_bienthe_idx` (`id_bienthe`);

--
-- Chỉ mục cho bảng `donhang_lichsu`
--
ALTER TABLE `donhang_lichsu`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_donhang` (`id_donhang`);

--
-- Chỉ mục cho bảng `giohang`
--
ALTER TABLE `giohang`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_id` (`session_id`),
  ADD UNIQUE KEY `id_user` (`id_user`);

--
-- Chỉ mục cho bảng `giohang_chitiet`
--
ALTER TABLE `giohang_chitiet`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id_giohang` (`id_giohang`,`id_bienthe`),
  ADD KEY `fk_giohangct_giohang_idx` (`id_giohang`),
  ADD KEY `fk_giohangct_bienthe_idx` (`id_bienthe`);

--
-- Chỉ mục cho bảng `hoantra`
--
ALTER TABLE `hoantra`
  ADD PRIMARY KEY (`id_hoantra`),
  ADD KEY `id_donhang` (`id_donhang`),
  ADD KEY `id_sanpham` (`id_sanpham`),
  ADD KEY `id_nguoidung` (`id_nguoidung`);

--
-- Chỉ mục cho bảng `khuyen_mai`
--
ALTER TABLE `khuyen_mai`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ma_khuyenmai` (`ma_khuyenmai`);

--
-- Chỉ mục cho bảng `khuyen_mai_apdung`
--
ALTER TABLE `khuyen_mai_apdung`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id_khuyenmai` (`id_khuyenmai`,`loai_doi_tuong`,`id_doi_tuong`),
  ADD KEY `fk_khuyenmai_apdung_idx` (`id_khuyenmai`);

--
-- Chỉ mục cho bảng `khuyen_mai_su_dung`
--
ALTER TABLE `khuyen_mai_su_dung`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_kmsd_khuyenmai` (`id_khuyenmai`),
  ADD KEY `fk_kmsd_user` (`id_user`),
  ADD KEY `fk_kmsd_donhang` (`id_donhang`);

--
-- Chỉ mục cho bảng `mausac_hinhanh`
--
ALTER TABLE `mausac_hinhanh`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_sanpham` (`id_sanpham`),
  ADD KEY `id_mausac` (`id_mausac`);

--
-- Chỉ mục cho bảng `nhat_ky`
--
ALTER TABLE `nhat_ky`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_nhatky_user_idx` (`id_user`);

--
-- Chỉ mục cho bảng `payment_logs`
--
ALTER TABLE `payment_logs`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `quyen_han`
--
ALTER TABLE `quyen_han`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id_user` (`id_user`,`module`,`quyen`);

--
-- Chỉ mục cho bảng `sanpham`
--
ALTER TABLE `sanpham`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `fk_sanpham_danhmuc_idx` (`id_danhmuc`),
  ADD KEY `fk_sanpham_thuonghieu` (`thuonghieu`);

--
-- Chỉ mục cho bảng `sanpham_bien_the`
--
ALTER TABLE `sanpham_bien_the`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id_sanpham` (`id_sanpham`,`id_mau`,`id_size`),
  ADD KEY `fk_bienthe_sanpham_idx` (`id_sanpham`),
  ADD KEY `fk_bienthe_mau_idx` (`id_mau`),
  ADD KEY `fk_bienthe_size_idx` (`id_size`);

--
-- Chỉ mục cho bảng `sanpham_hinhanh`
--
ALTER TABLE `sanpham_hinhanh`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_hinhanh_sanpham_idx` (`id_sanpham`),
  ADD KEY `fk_hinhanh_bienthe_idx` (`id_bienthe`);

--
-- Chỉ mục cho bảng `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Chỉ mục cho bảng `thuoc_tinh`
--
ALTER TABLE `thuoc_tinh`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `loai` (`loai`,`gia_tri`);

--
-- Chỉ mục cho bảng `thuong_hieu`
--
ALTER TABLE `thuong_hieu`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `taikhoan` (`taikhoan`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Chỉ mục cho bảng `yeu_thich`
--
ALTER TABLE `yeu_thich`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id_user` (`id_user`,`id_sanpham`),
  ADD KEY `fk_yeuthich_user_idx` (`id_user`),
  ADD KEY `fk_yeuthich_sanpham_idx` (`id_sanpham`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `danhgia`
--
ALTER TABLE `danhgia`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT cho bảng `danhmuc`
--
ALTER TABLE `danhmuc`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT cho bảng `donhang`
--
ALTER TABLE `donhang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT cho bảng `donhang_chitiet`
--
ALTER TABLE `donhang_chitiet`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

--
-- AUTO_INCREMENT cho bảng `donhang_lichsu`
--
ALTER TABLE `donhang_lichsu`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=116;

--
-- AUTO_INCREMENT cho bảng `giohang`
--
ALTER TABLE `giohang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT cho bảng `giohang_chitiet`
--
ALTER TABLE `giohang_chitiet`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT cho bảng `hoantra`
--
ALTER TABLE `hoantra`
  MODIFY `id_hoantra` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT cho bảng `khuyen_mai`
--
ALTER TABLE `khuyen_mai`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT cho bảng `khuyen_mai_apdung`
--
ALTER TABLE `khuyen_mai_apdung`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT cho bảng `khuyen_mai_su_dung`
--
ALTER TABLE `khuyen_mai_su_dung`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT cho bảng `mausac_hinhanh`
--
ALTER TABLE `mausac_hinhanh`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `nhat_ky`
--
ALTER TABLE `nhat_ky`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=226;

--
-- AUTO_INCREMENT cho bảng `payment_logs`
--
ALTER TABLE `payment_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT cho bảng `quyen_han`
--
ALTER TABLE `quyen_han`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `sanpham`
--
ALTER TABLE `sanpham`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT cho bảng `sanpham_bien_the`
--
ALTER TABLE `sanpham_bien_the`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=144;

--
-- AUTO_INCREMENT cho bảng `sanpham_hinhanh`
--
ALTER TABLE `sanpham_hinhanh`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT cho bảng `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT cho bảng `thuoc_tinh`
--
ALTER TABLE `thuoc_tinh`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT cho bảng `thuong_hieu`
--
ALTER TABLE `thuong_hieu`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT cho bảng `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT cho bảng `yeu_thich`
--
ALTER TABLE `yeu_thich`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=142;

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `danhgia`
--
ALTER TABLE `danhgia`
  ADD CONSTRAINT `fk_danhgia_sanpham` FOREIGN KEY (`id_sanpham`) REFERENCES `sanpham` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_danhgia_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `danhmuc`
--
ALTER TABLE `danhmuc`
  ADD CONSTRAINT `fk_danhmuc_cha` FOREIGN KEY (`danhmuc_cha`) REFERENCES `danhmuc` (`id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `donhang`
--
ALTER TABLE `donhang`
  ADD CONSTRAINT `fk_donhang_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `donhang_chitiet`
--
ALTER TABLE `donhang_chitiet`
  ADD CONSTRAINT `fk_donhangct_bienthe` FOREIGN KEY (`id_bienthe`) REFERENCES `sanpham_bien_the` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_donhangct_donhang` FOREIGN KEY (`id_donhang`) REFERENCES `donhang` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_donhangct_sanpham` FOREIGN KEY (`id_sanpham`) REFERENCES `sanpham` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `giohang`
--
ALTER TABLE `giohang`
  ADD CONSTRAINT `fk_giohang_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `giohang_chitiet`
--
ALTER TABLE `giohang_chitiet`
  ADD CONSTRAINT `fk_giohangct_bienthe` FOREIGN KEY (`id_bienthe`) REFERENCES `sanpham_bien_the` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_giohangct_giohang` FOREIGN KEY (`id_giohang`) REFERENCES `giohang` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `hoantra`
--
ALTER TABLE `hoantra`
  ADD CONSTRAINT `hoantra_donhang_fk` FOREIGN KEY (`id_donhang`) REFERENCES `donhang` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `hoantra_nguoidung_fk` FOREIGN KEY (`id_nguoidung`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `hoantra_sanpham_fk` FOREIGN KEY (`id_sanpham`) REFERENCES `sanpham` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `khuyen_mai_apdung`
--
ALTER TABLE `khuyen_mai_apdung`
  ADD CONSTRAINT `fk_khuyenmai_apdung` FOREIGN KEY (`id_khuyenmai`) REFERENCES `khuyen_mai` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `khuyen_mai_su_dung`
--
ALTER TABLE `khuyen_mai_su_dung`
  ADD CONSTRAINT `fk_kmsd_donhang` FOREIGN KEY (`id_donhang`) REFERENCES `donhang` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_kmsd_khuyenmai` FOREIGN KEY (`id_khuyenmai`) REFERENCES `khuyen_mai` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_kmsd_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `nhat_ky`
--
ALTER TABLE `nhat_ky`
  ADD CONSTRAINT `fk_nhatky_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `quyen_han`
--
ALTER TABLE `quyen_han`
  ADD CONSTRAINT `fk_quyenhan_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `sanpham`
--
ALTER TABLE `sanpham`
  ADD CONSTRAINT `fk_sanpham_danhmuc` FOREIGN KEY (`id_danhmuc`) REFERENCES `danhmuc` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sanpham_thuonghieu` FOREIGN KEY (`thuonghieu`) REFERENCES `thuong_hieu` (`id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `sanpham_bien_the`
--
ALTER TABLE `sanpham_bien_the`
  ADD CONSTRAINT `fk_bienthe_mau` FOREIGN KEY (`id_mau`) REFERENCES `thuoc_tinh` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_bienthe_sanpham` FOREIGN KEY (`id_sanpham`) REFERENCES `sanpham` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_bienthe_size` FOREIGN KEY (`id_size`) REFERENCES `thuoc_tinh` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `sanpham_hinhanh`
--
ALTER TABLE `sanpham_hinhanh`
  ADD CONSTRAINT `fk_hinhanh_bienthe` FOREIGN KEY (`id_bienthe`) REFERENCES `sanpham_bien_the` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_hinhanh_sanpham` FOREIGN KEY (`id_sanpham`) REFERENCES `sanpham` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `yeu_thich`
--
ALTER TABLE `yeu_thich`
  ADD CONSTRAINT `fk_yeuthich_sanpham` FOREIGN KEY (`id_sanpham`) REFERENCES `sanpham` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_yeuthich_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
