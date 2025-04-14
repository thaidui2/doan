-- Step 1: Drop foreign keys that reference seller-related columns
ALTER TABLE `sanpham` DROP FOREIGN KEY IF EXISTS `sanpham_ibfk_2`;
ALTER TABLE `khuyen_mai` DROP FOREIGN KEY IF EXISTS `khuyen_mai_ibfk_1`;
ALTER TABLE `thongtin_shop` DROP FOREIGN KEY IF EXISTS `thongtin_shop_ibfk_1`;

-- Step 2: Drop the entire thongtin_shop table (seller information)
DROP TABLE IF EXISTS `thongtin_shop`;

-- Step 3: Update the users table structure - remove seller-related columns
-- First, update any existing seller users to regular users
UPDATE `users` SET `loai_user` = 0 WHERE `loai_user` = 1;

-- Now we can modify the structure
ALTER TABLE `users` 
  DROP COLUMN IF EXISTS `ten_shop`,
  DROP COLUMN IF EXISTS `mo_ta_shop`,
  DROP COLUMN IF EXISTS `logo_shop`,
  DROP COLUMN IF EXISTS `ngay_tro_thanh_nguoi_ban`,
  DROP COLUMN IF EXISTS `dia_chi_shop`,
  DROP COLUMN IF EXISTS `so_dien_thoai_shop`,
  DROP COLUMN IF EXISTS `email_shop`,
  DROP COLUMN IF EXISTS `ten_chu_tai_khoan`,
  DROP COLUMN IF EXISTS `so_tai_khoan`,
  DROP COLUMN IF EXISTS `ten_ngan_hang`,
  DROP COLUMN IF EXISTS `chi_nhanh`;

-- Keep the loai_user column but modify its comment to only reflect buyer status
ALTER TABLE `users` 
  CHANGE `loai_user` `loai_user` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0: Người dùng';

-- Step 4: Update product table - remove seller reference
ALTER TABLE `sanpham` 
  DROP COLUMN IF EXISTS `id_nguoiban`;

-- Step 5: Update khuyen_mai table
-- First, set a default admin ID for existing promotions (assuming admin ID 6 exists)
UPDATE `khuyen_mai` SET `id_nguoiban` = 6;

-- Then modify the table structure to remove references to specific sellers
ALTER TABLE `khuyen_mai`
  DROP INDEX IF EXISTS `ma_code_nguoiban`,
  ADD UNIQUE INDEX `ma_code_unique` (`ma_code`);

-- Update the comment for clarity
ALTER TABLE `khuyen_mai`
  CHANGE `id_nguoiban` `id_nguoiban` INT(11) NOT NULL COMMENT 'ID quản trị viên tạo mã';

-- Step 6: Set all products that had seller status 4 to active status 1
UPDATE `sanpham` SET `trangthai` = 1 WHERE `trangthai` = 4;

-- Step 7: Update order status for any orders marked as related to seller functionality
UPDATE `donhang` 
SET `trangthai` = 5, 
    `ghichu` = CONCAT(IFNULL(`ghichu`, ''), ' - Đã hủy do ngừng chức năng người bán') 
WHERE `ghichu` NOT LIKE '%Đã hủy do ngừng chức năng người bán%' 
  AND (`id_donhang` IN (SELECT `id_donhang` FROM `donhang_chitiet` WHERE `id_sanpham` IN (5, 6))
       OR `trangthai` IN (1, 2, 3));
