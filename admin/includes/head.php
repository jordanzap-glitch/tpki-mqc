<!-- Favicon -->
    <link href="img/favicon.ico" rel="icon">

    <!-- Theme early init: PHP cookie (server-side) → localStorage → default dark -->
    <?php
        $tpkiTheme = 'dark';
        if (!empty($_COOKIE['tpki_theme']) && in_array($_COOKIE['tpki_theme'], ['dark','light'], true)) {
            $tpkiTheme = $_COOKIE['tpki_theme'];
        }
    ?>
    <script>(function(){
        var t='<?php echo $tpkiTheme; ?>';
        try{var ls=localStorage.getItem('theme');if(ls==='light'||ls==='dark')t=ls;}catch(e){}
        document.documentElement.setAttribute('data-theme',t);
    })();</script>

    <!-- Google Web Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Roboto:wght@500;700&display=swap" rel="stylesheet"> 
    
    <!-- Icon Font Stylesheet -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Libraries Stylesheet -->
    <link href="../lib/owlcarousel/assets/owl.carousel.min.css" rel="stylesheet">
    <link href="../lib/tempusdominus/css/tempusdominus-bootstrap-4.min.css" rel="stylesheet" />

    <!-- Customized Bootstrap Stylesheet -->
    <link href="../css/bootstrap.min.css?v=<?php echo filemtime(__DIR__.'/../../css/bootstrap.min.css'); ?>" rel="stylesheet">

    <!-- Template Stylesheet -->
    <link href="../css/style.css?v=<?php echo filemtime(__DIR__.'/../../css/style.css'); ?>" rel="stylesheet">