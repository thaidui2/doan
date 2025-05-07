<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - Bug Shop' : 'Bug Shop'; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo isset($path_prefix) ? $path_prefix : ''; ?>css/style.css">
    <?php if(isset($page_css)) {
        if(is_array($page_css)) {
            foreach($page_css as $css_file) {
                echo '<link rel="stylesheet" href="' . (isset($path_prefix) ? $path_prefix : '') . $css_file . '">';
            }
        } else {
            echo '<link rel="stylesheet" href="' . (isset($path_prefix) ? $path_prefix : '') . $page_css . '">';
        }
    } ?>
    
    <!-- Custom JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo isset($path_prefix) ? $path_prefix : ''; ?>js/chatbot.js"></script>
    <link rel="stylesheet" href="<?php echo isset($path_prefix) ? $path_prefix : ''; ?>css/chatbot.css">
    
    <!-- Wishlist JavaScript -->
    <script src="<?php echo isset($path_prefix) ? $path_prefix : ''; ?>js/wishlist.js" defer></script>
    
    <?php if(isset($page_js)) {
        if(is_array($page_js)) {
            foreach($page_js as $js_file) {
                echo '<script src="' . (isset($path_prefix) ? $path_prefix : '') . $js_file . '" defer></script>';
            }
        } else {
            echo '<script src="' . (isset($path_prefix) ? $path_prefix : '') . $page_js . '" defer></script>';
        }
    } ?>
    
    <!-- Custom head content -->
    <?php if(isset($head_custom)) echo $head_custom; ?>
</head>
<body>
</body>
</html>