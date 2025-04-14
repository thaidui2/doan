-- Additional cleanup for seller-related columns and references

-- Update user_type display in customer-detail.php
-- Change loai_user badge display from "Người bán" to "Người dùng"

-- Update users table loai_user column comment to remove seller reference
ALTER TABLE `users` MODIFY `loai_user` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0: Người mua';

-- Remove remaining shop/seller related columns in users table that weren't previously dropped
ALTER TABLE `users` 
  DROP COLUMN IF EXISTS `ten_shop`,
  DROP COLUMN IF EXISTS `mo_ta_shop`, 
  DROP COLUMN IF EXISTS `logo_shop`,
  DROP COLUMN IF EXISTS `diem_danh_gia`,
  DROP COLUMN IF EXISTS `so_luong_danh_gia`,
  DROP COLUMN IF EXISTS `ngay_tro_thanh_nguoi_ban`,
  DROP COLUMN IF EXISTS `dia_chi_shop`,
  DROP COLUMN IF EXISTS `so_dien_thoai_shop`,
  DROP COLUMN IF EXISTS `email_shop`,
  DROP COLUMN IF EXISTS `ten_chu_tai_khoan`,
  DROP COLUMN IF EXISTS `so_tai_khoan`,
  DROP COLUMN IF EXISTS `ten_ngan_hang`,
  DROP COLUMN IF EXISTS `chi_nhanh`;
