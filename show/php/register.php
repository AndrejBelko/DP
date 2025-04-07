<?php
require_once('config.php');
session_start();
$errmsg = "";

try {
    $db = new PDO("mysql:host=$hostname;dbname=$dbname", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo $e->getMessage();
}

function checkLength($field, $min, $max): bool
{
    $string = trim($field);
    $length = strlen($string);
    if ($length < $min || $length > $max) {
        return false;
    }
    return true;
}

function checkSpecialChar($password): bool
{
    return preg_match('/[!@#$%^&*()\-_=+{}\[\]:;"\'<>,.?\/\\|`~]/', $password) === 1;
}


function checkNumber($password): bool
{
    return preg_match('/[0-9]/', $password);
}

function userExist($db, $username): bool
{
    $exist = false;

    $sql = "SELECT id FROM users WHERE username = :username";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":username", $username, PDO::PARAM_STR);

    $stmt->execute();

    if ($stmt->rowCount() == 1) {
        $exist = true;
    }
    unset($stmt);
    return $exist;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = $_POST["username"];
    $password = $_POST['password'];
    $password_again = $_POST['password_again'];

    if (userExist($db, $username)) {
        $errmsg .= "Username already exists. \n";
    } else{
        if (!checkLength($password, 6, 32)) {
            $errmsg .= "Password must be 6 to 32 characters long. \n";
        } else{
            if (!checkNumber($password)) {
                $errmsg .= "Password does not contain number. \n";
            }

            if (!checkSpecialChar($password)) {
                $errmsg .= "Password does not contain special character. \n";
            }

            if ($password != $password_again) {
                $errmsg .= "Passwords are not equal. \n";
            }
        }
    }

    if (empty($errmsg)) {
        $sql = "INSERT INTO users (username, email, password, token) VALUES (:username, :email, :password, :token)";

        $email = "sample@mail.com";
        $token = "token123";
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $db->prepare($sql);

        $stmt->bindParam(":username", $username, PDO::PARAM_STR);
        $stmt->bindParam(":email", $email, PDO::PARAM_STR);
        $stmt->bindParam(":token", $token, PDO::PARAM_STR);
        $stmt->bindParam(":password", $hashed_password, PDO::PARAM_STR);

        $stmt->execute();

        header("Location: login.php");

    }
    unset($stmt);
    unset($pdo);
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

    <title>Registrácia</title>
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

                                <p class="text-center h1 fw-bold mb-5 mx-1 mx-md-4 mt-4">Register</p>

                                <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>"
                                      class="mx-1 mx-md-4">

                                    <div class="d-flex flex-row align-items-center mb-4">
                                        <i class="fas fa-user fa-lg me-3 fa-fw"></i>
                                        <div data-mdb-input-init class="form-outline flex-fill mb-0">
                                            <input type="text" name="username"
                                                   id="username"
                                                   class="form-control" required>
                                            <label class="form-label" for="username">Username</label>
                                        </div>
                                    </div>

                                    <div class="d-flex flex-row align-items-center mb-4">
                                        <i class="fas fa-lock fa-lg me-3 fa-fw"></i>
                                        <div data-mdb-input-init class="form-outline flex-fill mb-0">
                                            <input type="password" name="password"
                                                   id="password" class="form-control"
                                                   required/>
                                            <label class="form-label" for="password">Password</label>
                                        </div>
                                    </div>

                                    <div class="d-flex flex-row align-items-center mb-4">
                                        <i class="fas fa-key fa-lg me-3 fa-fw"></i>
                                        <div data-mdb-input-init class="form-outline flex-fill mb-0">
                                            <input type="password" name="password_again"
                                                   id="password_again" class="form-control"
                                                   required/>
                                            <label class="form-label" for="password_again">Repeat password</label>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-center mx-4 mb-3 mb-lg-4">
                                        <button type="submit" style="background-color: darkslategray; border-color: darkslategray;" data-mdb-button-init data-mdb-ripple-init
                                                class="btn btn-primary btn-lg">Register
                                        </button>
                                    </div>

                                    <div class="d-flex justify-content-center mx-4 mb-3 mb-lg-4">
                                        <a href="login.php">
                                            Login
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
                <?php echo nl2br($errmsg); ?>
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

<footer class="d-flex justify-content-center py-3 my-4 mt-4 border-top border-dark-subtle">
    <span class="text-muted">© 2023 Úvod do počítačovej bezpečnosti</span>
</footer>

<script src="https://cdn.datatables.net/1.13.2/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.2/js/dataTables.bootstrap5.min.js"></script>

</body>
</html>
