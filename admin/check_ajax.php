<?php
session_start();

// Kiểm tra đăng nhập
if(!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die("Unauthorized access");
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kiểm tra AJAX - Bug Shop Admin</title>
    <link rel="stylesheet" href="../node_modules/bootstrap/dist/css/bootstrap.css">
</head>
<body>
    <div class="container py-5">
        <h1>Kiểm tra AJAX cho thêm màu</h1>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Test AJAX Call</h5>
                    </div>
                    <div class="card-body">
                        <form id="testForm" class="mb-4">
                            <div class="mb-3">
                                <label for="colorName" class="form-label">Tên màu</label>
                                <input type="text" class="form-control" id="colorName" value="Test Color">
                            </div>
                            <div class="mb-3">
                                <label for="colorCode" class="form-label">Mã màu</label>
                                <input type="text" class="form-control" id="colorCode" value="#ff0000">
                            </div>
                            <button type="button" id="testButton" class="btn btn-primary">Thử AJAX call</button>
                        </form>
                        
                        <div id="result" class="alert d-none"></div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Kiểm tra file AJAX</h5>
                    </div>
                    <div class="card-body">
                        <p>Đường dẫn đầy đủ đến file AJAX: <code><?php echo realpath('ajax/add_color.php'); ?></code></p>
                        <div class="d-grid gap-2">
                            <button id="checkFile" class="btn btn-secondary">Kiểm tra file tồn tại</button>
                            <button id="showCode" class="btn btn-info">Xem nội dung file</button>
                        </div>
                        
                        <div id="fileResult" class="mt-3"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('testButton').addEventListener('click', function() {
            const colorName = document.getElementById('colorName').value.trim();
            const colorCode = document.getElementById('colorCode').value.trim();
            const resultDiv = document.getElementById('result');
            
            resultDiv.className = 'alert';
            resultDiv.textContent = 'Đang gửi request...';
            resultDiv.classList.remove('d-none', 'alert-success', 'alert-danger');
            resultDiv.classList.add('alert-info');
            
            fetch('ajax/add_color.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `name=${encodeURIComponent(colorName + '_test')}&code=${encodeURIComponent(colorCode)}`
            })
            .then(response => response.json())
            .then(data => {
                resultDiv.textContent = JSON.stringify(data, null, 2);
                resultDiv.classList.remove('alert-info');
                resultDiv.classList.add(data.success ? 'alert-success' : 'alert-danger');
            })
            .catch(error => {
                resultDiv.textContent = 'Lỗi: ' + error.message;
                resultDiv.classList.remove('alert-info');
                resultDiv.classList.add('alert-danger');
            });
        });
        
        document.getElementById('checkFile').addEventListener('click', function() {
            const fileResult = document.getElementById('fileResult');
            fileResult.innerHTML = 'Đang kiểm tra...';
            
            fetch('ajax_check_file.php?file=ajax/add_color.php')
                .then(response => response.text())
                .then(data => {
                    fileResult.innerHTML = data;
                })
                .catch(error => {
                    fileResult.innerHTML = 'Lỗi: ' + error.message;
                });
        });
        
        document.getElementById('showCode').addEventListener('click', function() {
            const fileResult = document.getElementById('fileResult');
            fileResult.innerHTML = 'Đang tải nội dung...';
            
            fetch('ajax_show_file.php?file=ajax/add_color.php')
                .then(response => response.text())
                .then(data => {
                    fileResult.innerHTML = `<pre style="max-height: 400px; overflow-y: auto;">${data}</pre>`;
                })
                .catch(error => {
                    fileResult.innerHTML = 'Lỗi: ' + error.message;
                });
        });
    </script>
</body>
</html>
