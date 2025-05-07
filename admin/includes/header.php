<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Bug Shop Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php include 'includes/styles.php'; ?>
    <!-- Custom CSS -->
    <?php 
    
    // Hỗ trợ cách cũ thông qua $extra_css
    if(isset($extra_css)) echo $extra_css; 
    
    // Hỗ trợ cách mới thông qua $page_css (đơn lẻ hoặc mảng)
    if(isset($page_css)) {
        if(is_array($page_css)) {
            foreach($page_css as $css_file) {
                echo '<link rel="stylesheet" href="' . $css_file . '">';
            }
        } else {
            echo '<link rel="stylesheet" href="' . $page_css . '">';
        }
    }
    ?>
</head>
<body>

<div class="container-fluid">
    <div class="row">
