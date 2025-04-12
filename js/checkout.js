// Thêm đoạn mã debug để kiểm tra
document.addEventListener("DOMContentLoaded", function () {
  console.log(
    "Payment Methods:",
    document.querySelectorAll(".payment-method-item")
  );

  // Đảm bảo sự kiện click cho payment methods hoạt động đúng
  const paymentMethods = document.querySelectorAll(
    'input[name="payment_method"]'
  );
  paymentMethods.forEach((method) => {
    method.addEventListener("change", function () {
      document.querySelectorAll(".payment-method-item").forEach((item) => {
        item.classList.remove("active");
      });
      this.closest(".payment-method-item").classList.add("active");

      // Debug
      console.log("Selected payment method:", this.value);
    });
  });
});
