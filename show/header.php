<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="IE=edge" http-equiv="X-UA-Compatible">
    <meta content="width=device-width, initial-scale=1" name="viewport">
    <meta content="Bio inspired tracks comparison" name="description">
    <meta content="mComputing.eu, Maros Cavojsky" name="author">

    <title><?php echo $title; ?></title>

    <!-- Bootstrap core CSS -->
    <link href="css/bootstrap.min.css" rel="stylesheet">

    <!-- Custom styles for this template -->
    <link href="css/justified-nav.css" rel="stylesheet">

    <script src="js/ie-emulation-modes-warning.js"></script>

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" integrity="sha512-xodZBNTC5n17Xt2atTPuE1HxjVMSvLVW9ocqUKLsCC5CXdbqCmblAshOMAS6/keqq/sMZMZ19scR4PsZChSR7A==" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js" integrity="sha512-XQoYMqMTK8LvdxXYG3nZ448hOEQiglfqkJs1NOQV44cWnUrBc8PkAOcXy20w0vlaXaVUearIOBhiXZ5V3ynxwA==" crossorigin=""></script>
    <script src="js/jquery.min.js"></script>
    <script src="js/bootstrap.min.js"></script>

    <style>
        #map {
            height: 600px;
            width: 100%;
        }
        #map-swa {
            height: 600px;
            width: 100%;
        }

        .alg-form .form-group {
            padding-bottom: 20px;
        }
    </style>

</head>

<body>

<nav class="navbar navbar-default navbar-fixed-top hidden-lg hidden-md hidden-sm">
    <div class="container">
        <div class="navbar-header">
            <button aria-controls="navbar" aria-expanded="false" class="navbar-toggle collapsed" data-target="#navbar"
                    data-toggle="collapse" type="button">
                <span class="sr-only">Toggle navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            <a class="navbar-brand" href="index.html">COhave</a>
        </div>
        <div class="navbar-collapse collapse" id="navbar">
            <ul class="nav nav-justified">
                <li><a href="index.php">Track search</a></li>
                <li><a href="nwa.php">Needleman–Wunsch</a></li>
                <li><a href="swa.php">Smith–Waterman</a></li>
            </ul>
        </div><!--/.nav-collapse -->
    </div>
</nav>

<div class="container" id="up">

    <div class="masthead hidden-xs">
        <h3 class="text-muted">COhave research</h3>
        <nav>
            <ul class="nav nav-justified">
                <li><a href="index.php">Track search</a></li>
                <li><a href="nwa.php">Needleman–Wunsch</a></li>
                <li><a href="swa.php">Smith–Waterman</a></li>
            </ul>
        </nav>
    </div>
