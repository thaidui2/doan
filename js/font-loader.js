// Đảm bảo font Poppins được tải trước khi hiển thị nội dung
document.addEventListener('DOMContentLoaded', function () {
    // Tạo một promise sẽ resolve khi font đã tải
    const fontPromise = document.fonts.ready.then(function () {
        // Kiểm tra font Poppins đã được tải chưa
        if (document.fonts.check('1em Poppins')) {
            console.log('Poppins font đã được tải thành công');
            // Xóa class hiding-content nếu có
            document.documentElement.classList.remove('font-loading');
        } else {
            console.log('Font Poppins không khả dụng, sử dụng font dự phòng');
        }
    });

    // Set timeout để đảm bảo nội dung sẽ hiển thị ngay cả khi font không tải được
    setTimeout(function () {
        document.documentElement.classList.remove('font-loading');
    }, 1000);
});
