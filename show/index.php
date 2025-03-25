<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
error_reporting(E_WARNING);
require_once('php/config.php');
session_start();
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

try {
    $db = new PDO("mysql:host=$hostname;dbname=$dbname", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo $e->getMessage();
}


$sql = "
SELECT 
    username
FROM users 
WHERE email is NULL;
";

$stmt = $db->prepare($sql);

if ($stmt->execute()) {
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $schemaNames = array_column($rows, 'username');

}

unset($stmt);
unset($db);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="shortcut icon" type="image/x-icon" href="favicon.png"/>
    <meta charset="utf-8">
    <meta content="IE=edge" http-equiv="X-UA-Compatible">
    <meta content="width=device-width, initial-scale=1" name="viewport">
    <meta content="Bio inspired tracks comparison" name="description">
    <meta content="mComputing.eu, Maros Cavojsky" name="author">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <?php
    function endsWith($haystack, $needle)
    {
        $length = strlen($needle);
        if (!$length) {
            return true;
        }
        return substr($haystack, -$length) === $needle;
    }

    $files = scandir("center");
    $datasets = array();
    foreach ($files as $key => $value) {
        if (endsWith($value, ".json")) {
            array_push($datasets, json_decode(file_get_contents("center/$value"), true));
        }
    }

    $queryDB = (isset($_GET['db']) && $_GET['db'] != "") ? $_GET['db'] : $_SESSION['username'];
    $DBinfo = array();

    foreach ($datasets as $key => $value) {
        if (isset($_SESSION["username"]) && $queryDB == $value['dbname']) {
            if (file_exists("center/$queryDB.json")) {
                $DBinfo = $value;
                break;
            }
        } else if (!isset($_SESSION["username"])) {
            if (isset($_GET['db'])) {
                $name = $_GET['db'];
                if ($name == $value['dbname']) {
                    $DBinfo = $value;
                    $queryDB = $value['dbname'];
                    break;
                }
            } else {
                if (in_array($value['dbname'], $schemaNames)) {
                    $DBinfo = $value;
                    $queryDB = $value['dbname'];
                    break;
                }
            }
        }
    }
    if (empty($DBinfo)) {
        $value = array(
            "center" => array(
                "lat" => 48.151965,
                "lon" => 17.072995
            ),
            "title" => " ",
            "dbname" => " ",
            "attribution" => ""
        );
        $DBinfo = $value;
        $queryDB = $value['dbname'];
    }
    ?>

    <title>COhaveSearch - <?php echo $DBinfo['title']; ?></title>

    <link href="css/toastr.css" rel="stylesheet"/>
    <script src="js/ie-emulation-modes-warning.js"></script>

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
    <link href='https://fonts.googleapis.com/css?family=Roboto' rel='stylesheet' type='text/css'>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css"
          integrity="sha512-xodZBNTC5n17Xt2atTPuE1HxjVMSvLVW9ocqUKLsCC5CXdbqCmblAshOMAS6/keqq/sMZMZ19scR4PsZChSR7A=="
          crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"
            integrity="sha512-XQoYMqMTK8LvdxXYG3nZ448hOEQiglfqkJs1NOQV44cWnUrBc8PkAOcXy20w0vlaXaVUearIOBhiXZ5V3ynxwA=="
            crossorigin=""></script>
    <script src="js/jquery.min.js"></script>
    <link rel="stylesheet" href="css/L.Control.Sidebar.css"/>
    <script src="js/L.Control.Sidebar.js"></script>
    <script src="https://canvasjs.com/assets/script/jquery-1.11.1.min.js"></script>
    <script src="https://canvasjs.com/assets/script/jquery.canvasjs.min.js"></script>
    <script src="js/toastr.js"></script>

    <link href="https://netdna.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet-easybutton@2/src/easy-button.css">
    <script src="https://cdn.jsdelivr.net/npm/leaflet-easybutton@2/src/easy-button.js"></script>
    <script src="https://npmcdn.com/leaflet-geometryutil"></script>
    <script src="js/geohash.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-GLhlTQ8iRABdZLl6O3oVMWSktQOp6b7In1Zl3/Jr59b6EGGoI1aFkw7cmDA6j6gD" crossorigin="anonymous">

    <link href="https://cdn.datatables.net/v/bs5/jq-3.7.0/dt-1.13.7/b-2.4.2/b-colvis-2.4.2/b-html5-2.4.2/b-print-2.4.2/date-1.5.1/r-2.5.0/sc-2.3.0/sb-1.6.0/sp-2.2.0/sl-1.7.0/datatables.min.css" rel="stylesheet">

    <script src="https://cdn.datatables.net/v/bs5/jq-3.7.0/dt-1.13.7/b-2.4.2/b-colvis-2.4.2/b-html5-2.4.2/b-print-2.4.2/date-1.5.1/r-2.5.0/sc-2.3.0/sb-1.6.0/sp-2.2.0/sl-1.7.0/datatables.min.js"></script>

    <link rel="stylesheet" href="css/index.css">
</head>

<body>

<header>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                    data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
                    aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <!-- Left side navigation links -->
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link active fs-5" aria-current="page" href="index.php">Map</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fs-5" href="php/profile.php">Profile</a>
                    </li>
                    <?php if (isset($_SESSION['username']) && $_SESSION['loggedin'] === true): ?>
                        <li class="nav-item">
                            <a class="nav-link fs-5" href="php/logout.php">Log out</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link fs-5" href="php/register.php">Register</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link fs-5" href="php/login.php">Login</a>
                        </li>
                    <?php endif; ?>
                </ul>

            </div>
        </div>
    </nav>
</header>


<div id='map'></div>

<!-- Sidebar (Offcanvas) -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="sidebar" aria-labelledby="sidebarLabel"
     data-bs-backdrop="false">
    <div class="offcanvas-header">
        <h4 class="offcanvas-title" id="sidebarLabel">Dataset: <?php echo $DBinfo['title']; ?></h4>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Zatvoriť" onclick="changeSideBar()"></button>
    </div>
    <div class="offcanvas-body">
        <h5><strong>Boxes drawn: <span id="psize">0</span></strong></h5>

        <!-- Form Section -->
        <form class="mt-3">
            <div class="row g-3 my-3">
                <div class="col-md-6">
                    <label for="gsStart" class="form-label">Start ≤</label>
                    <input type="number" id="gsStart" class="form-control" placeholder="1" value="3" min="1">
                </div>
                <div class="col-md-6">
                    <label for="gsEnd" class="form-label">End ≥</label>
                    <input type="number" id="gsEnd" class="form-control" placeholder="1" value="2">
                </div>
            </div>
            <div class="row g-3 my-3">
                <div class="col-md-6">
                    <label for="gsMatch" class="form-label">Matches ≥</label>
                    <input type="number" id="gsMatch" class="form-control">
                </div>
                <div class="col-md-6">
                    <label for="gaps" class="form-label">Gaps ≤</label>
                    <input type="number" id="gaps" class="form-control">
                </div>
            </div>
            <div class="text-center my-3">
                <button type="button" class="btn btn-success" onclick="findPaths()">Apply</button>
            </div>
        </form>

        <hr>

        <!-- Toggle Switch -->
        <div class="row">
            <div class="col-12 text-center my-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="toggleSwitch">
                    <label class="form-check-label" for="toggleSwitch">Mapmatch</label>
                </div>
            </div>
        </div>

        <!-- Results Section -->
        <div class="row">
            <div class="col-12">
                <div id="resultsbox" style="display: none;">
                    <p>
                        Found <span id="totalfound"></span> -
                        <button class="btn btn-secondary btn-sm" onclick="showAll()">Show all</button>
                    </p>
                    <div style="height: fit-content; overflow-y:auto;">
                        <div id="results"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="mt-auto py-3">
            <div class="text-center">
                <?php
                if (isset($_SESSION["username"])) {
                    foreach ($datasets as $key => $value) {
                        if ($value['dbname'] == $_SESSION['username'] || in_array($value['dbname'], $schemaNames)) {
                            echo "<a href='?db=" . $value['dbname'] . "'>" . $value['title'] . "</a>";
                            if ($key < count($datasets)) {
                                echo " | ";
                            }
                        }
                    }
                } else {
                    foreach ($schemaNames as $value) {
                        echo "<a href='?db=" . $value . "'>" . $value . "</a> |";
                    }
                }
                ?>
                <p class="small">
                    Algorithm &copy; <a href="https://mcomputing.eu" class="text-decoration-none">mcomputing.eu</a>
                </p>
                <a href="php/nwa.php" class="text-decoration-none">Needleman–Wunsch</a> |
                <a href="php/swa.php" class="text-decoration-none">Smith–Waterman</a>
            </div>
        </footer>
    </div>
</div>

<!--Right sidebar -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="sidebarRight" aria-labelledby="sidebarRightLabel"
     data-bs-backdrop="false" data-bs-keyboard="false">

    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="sidebarRightLabel">Chart Sidebar</h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>

    <div class="offcanvas-body">
        <div class="row">
            <div class="col-lg-12" id="chartContainer" style="height: 300px; width: 100%; margin-top: 5%;"></div>
        </div>
        <div class="row">
            <div class="col-lg-12" id="chart2Container" style="height: 300px; width: 100%; margin-top: 5%;"></div>
        </div>
        <div class="mt-4">
            <div class="col-lg-12">
                <h1 style="font-size: 18px;">Click on individual columns to show or hide selected values on the map</h1>
            </div>
        </div>
    </div>
</div>


<link rel="stylesheet" href="css/theme.blue.css"/>


<!-- Load Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Load CanvasJS -->
<script src="https://canvasjs.com/assets/script/canvasjs.min.js"></script>

<script>
    var queryDB = "<?php echo $queryDB; ?>";
    var dbname = "<?php echo $DBinfo['dbname']?>";
    var map = L.map('map').setView([<?php echo $DBinfo['center']['lat'] . ", " . $DBinfo['center']['lon'];?>], 13);
</script>
<script src="js/index.js"></script>
</body>
</html>