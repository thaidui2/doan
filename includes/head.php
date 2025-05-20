<!DOCTYPE html>
<html lang="vi" class="font-loading">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base
        href="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/bug_shop/'; ?>">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - Bug Shop' : 'Bug Shop'; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Page specific CSS -->
    <?php
    if (isset($page_css) && is_array($page_css)) {
        foreach ($page_css as $css) {
            echo '<link rel="stylesheet" href="' . $css . '">';
        }
    }
    ?> <!-- Custom CSS (loaded last to override other styles) -->
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/promotions.css"> <!-- Bootstrap JS -->
    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Font Loader -->
    <script defer src="js/font-loader.js"></script>

    <!-- Custom head content -->
    <?php echo $head_custom ?? ''; ?>
</head>

<body>
</body>

</html>