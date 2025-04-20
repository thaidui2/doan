document.addEventListener("DOMContentLoaded", function() {
    // Handle edit staff
    const editButtons = document.querySelectorAll(".edit-staff-btn");
    
    editButtons.forEach(button => {
        button.addEventListener("click", function() {
            const staffId = this.getAttribute("data-staff-id");
            const staffName = this.getAttribute("data-staff-name");
            const staffUsername = this.getAttribute("data-staff-username");
            const staffEmail = this.getAttribute("data-staff-email");
            const staffPhone = this.getAttribute("data-staff-phone");
            const staffRole = this.getAttribute("data-staff-role");
            const staffStatus = this.getAttribute("data-staff-status");
            
            document.getElementById("staff_id").value = staffId;
            document.getElementById("name").value = staffName;
            document.getElementById("username").value = staffUsername;
            document.getElementById("email").value = staffEmail;
            document.getElementById("phone").value = staffPhone;
            document.getElementById("role").value = staffRole;
            document.getElementById("status").checked = staffStatus === "1";
            
            // Change modal title and button text
            document.querySelector("#staffModal .modal-title").textContent = "Chỉnh sửa nhân viên";
            document.querySelector("#staffModal button[type=submit]").textContent = "Cập nhật";
            
            // Hide password field for edit
            document.getElementById("password_container").classList.add("d-none");
            
            const staffModal = new bootstrap.Modal(document.getElementById("staffModal"));
            staffModal.show();
        });
    });
    
    // Handle add new staff button
    document.getElementById("add_staff_btn").addEventListener("click", function() {
        // Reset the form
        document.getElementById("staff_form").reset();
        document.getElementById("staff_id").value = "";
        
        // Change modal title and button text
        document.querySelector("#staffModal .modal-title").textContent = "Thêm nhân viên mới";
        document.querySelector("#staffModal button[type=submit]").textContent = "Thêm mới";
        
        // Show password field for new staff
        document.getElementById("password_container").classList.remove("d-none");
        
        const staffModal = new bootstrap.Modal(document.getElementById("staffModal"));
        staffModal.show();
    });
    
    // Handle reset password
    const resetPasswordButtons = document.querySelectorAll(".reset-password-btn");
    
    resetPasswordButtons.forEach(button => {
        button.addEventListener("click", function() {
            const staffId = this.getAttribute("data-staff-id");
            const staffName = this.getAttribute("data-staff-name");
            
            document.getElementById("reset_staff_id").value = staffId;
            document.getElementById("reset_staff_name").textContent = staffName;
            
            const resetModal = new bootstrap.Modal(document.getElementById("resetPasswordModal"));
            resetModal.show();
        });
    });
    
    // Auto dismiss alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll(".alert.alert-dismissible");
        alerts.forEach(function(alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
    
    // Filter auto-submission
    const autoSubmitFilters = document.querySelectorAll("#status_filter, #role_filter");
    autoSubmitFilters.forEach(filter => {
        filter.addEventListener("change", function() {
            this.closest("form").submit();
        });
    });
});
