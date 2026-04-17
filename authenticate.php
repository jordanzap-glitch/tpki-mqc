<?php
require __DIR__ . '/db/dbcon.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

if (empty($email) || empty($password)) {
    $_SESSION['error'] = 'Please enter both email and password.';
    header('Location: index.php');
    exit;
}

$sql = "SELECT u.id, u.Email_Address, u.Password, u.User_Type_ID FROM tbl_user u WHERE u.Email_Address = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $stored = isset($row['Password']) ? $row['Password'] : '';
        $authenticated = false;

        // First try password_verify for hashed passwords
        if (!empty($stored) && password_verify($password, $stored)) {
            $authenticated = true;
        }

        // Fallback: allow direct match if password is stored unhashed/plaintext
        if (!$authenticated && $password === $stored) {
            $authenticated = true;
        }

        if ($authenticated) {
            $_SESSION['userId'] = $row['id'];
            $_SESSION['email'] = $row['Email_Address'];
            $_SESSION['userTypeId'] = (int)$row['User_Type_ID'];

            if ($_SESSION['userTypeId'] === 1) {
                header('Location: admin/index.php');
                exit;
            } else {
                header('Location: index.php');
                exit;
            }
        }
    }
}

$_SESSION['error'] = 'Invalid email or password.';
header('Location: index.php');
exit;

?>
