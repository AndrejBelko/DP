<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
error_reporting(E_WARNING);
require_once('config.php');
session_start();

try {
    $db = new PDO("mysql:host=$hostname;dbname=$dbname", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo $e->getMessage();
}

$error_msg = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $sql = "SELECT id, username, email, password FROM users WHERE username = :username";

    $stmt = $db->prepare($sql);

    $stmt->bindParam(":username", $_POST["username"], PDO::PARAM_STR);

    $stmt->execute();

    if ($stmt->rowCount() == 1) {
        $row = $stmt->fetch();
        $hashed_password = $row["heslo"];

        if (password_verify($_POST["password"], $hashed_password)) {
            $row = $stmt->fetch();
            $_SESSION['loggedin'] = true;
            $_SESSION['username'] = $_POST["username"];
            $_SESSION['user_id'] = $row["id"];
            header("Location: ../index.php");
            exit;
        } else {
            $_SESSION['login_attempts']++;
            $_SESSION['last_login_attempt'] = time();
            $error_msg .= "Nesprávny login alebo heslo.";
        }
    } else {
        $error_msg .= "Nesprávny login alebo heslo.";
    }

    unset($stmt);
    unset($db);
}
?>
<!doctype html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-GLhlTQ8iRABdZLl6O3oVMWSktQOp6b7In1Zl3/Jr59b6EGGoI1aFkw7cmDA6j6gD" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.2/css/dataTables.bootstrap5.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.3/jquery.min.js"
            integrity="sha512-STof4xm1wgkfm7heWqFJVn58Hm3EtS31XFaagaa8VMReCXAkQnJZ+jEy8PCC/iT18dFy95WcExNHFTqLyp72eQ=="
            crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-w76AqPfDkMBDXo30jS1Sgez6pr3x5MlQ1ZAGC+nuZB+EYdgRZgiwxhTBTkF7CXvN"
            crossorigin="anonymous"></script>
    <!--    <link rel="stylesheet" href="css/utility.css">-->

    <title>Prihlásenie</title>
</head>
<body>

<section class="vh-100" style="background-color: darkslategray;">
    <div class="container">
        <div class="row d-flex justify-content-center align-items-center">
            <div class="col-lg-12 col-xl-11">
                <div class="card text-black" style="border-radius: 25px;">
                    <div class="card-body p-md-5">
                        <div class="row justify-content-center">
                            <div class="col-md-10 col-lg-6 col-xl-5 order-2 order-lg-1">

                                <p class="text-center h1 fw-bold mb-5 mx-1 mx-md-4 mt-4">Prihlásenie</p>

                                <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>"
                                      class="mx-1 mx-md-4">

                                    <div class="d-flex flex-row align-items-center mb-4">
                                        <i class="fas fa-user fa-lg me-3 fa-fw"></i>
                                        <div data-mdb-input-init class="form-outline flex-fill mb-0">
                                            <input type="text" name="username"
                                                   id="username"
                                                   class="form-control" required>
                                            <label class="form-label" for="username">Používateľské meno</label>
                                        </div>
                                    </div>

                                    <div class="d-flex flex-row align-items-center mb-4">
                                        <i class="fas fa-lock fa-lg me-3 fa-fw"></i>
                                        <div data-mdb-input-init class="form-outline flex-fill mb-0">
                                            <input type="password" name="password"
                                                   id="password" class="form-control"
                                                   required class="form-control"/>
                                            <label class="form-label" for="password">Heslo</label>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-center mx-4 mb-3 mb-lg-4">
                                        <button type="submit"
                                                style="background-color: darkslategray; border-color: darkslategray;"
                                                data-mdb-button-init data-mdb-ripple-init
                                                class="btn btn-primary btn-lg">Prihlásenie
                                        </button>
                                    </div>

                                    <div class="d-flex justify-content-center mx-4 mb-3 mb-lg-4">
                                        <a href="register.php">
                                            Registrácia
                                        </a>
                                    </div>

                                </form>

                            </div>
                            <div class="col-md-10 col-lg-6 col-xl-7 d-flex align-items-center order-1 order-lg-2">

                                <img src="https://mdbcdn.b-cdn.net/img/Photos/new-templates/bootstrap-registration/draw1.webp"
                                     class="img-fluid" alt="Sample image">

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Toast Container -->
    <div class="toast-container p-3 top-0 start-50 translate-middle-x">
        <div id="errorToast" class="toast bg-danger" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="true">
            <div class="toast-header">
                <strong class="me-auto">Error</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                <?php echo $errmsg; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS (with Popper.js) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <?php if (!empty($errmsg)) : ?>
        <script>
            // Show the toast if $errmsg is not empty
            const errorToast = new bootstrap.Toast(document.getElementById('errorToast'));
            errorToast.show();
        </script>
    <?php endif; ?>
</section>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>

</body>
</html>