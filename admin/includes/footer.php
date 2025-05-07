</div>
    </div>
</div>

<!-- Common Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

<?php 
// Hỗ trợ cách cũ thông qua $extra_js
if(isset($extra_js)) echo $extra_js; 

// Hỗ trợ cách mới thông qua $page_js (đơn lẻ hoặc mảng)
if(isset($page_js)) {
    if(is_array($page_js)) {
        foreach($page_js as $js_file) {
            echo '<script src="' . $js_file . '"></script>';
        }
    } else {
        echo '<script src="' . $page_js . '"></script>';
    }
}
?>

</body>
</html>
