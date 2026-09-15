<?php
//Authentication Process[One of Important code of project]
session_start(); //session: allow persist data across page 
require_once '../includes/db_connect.php'; //import db_connect file

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    //if someone try to access via 'GET' rather post send them back;
    header("Location: ../index.php");
    exit;
}

$action = $_POST['action'] ?? '';
//take action from login form. Default: An empty string
try {
    if ($action == 'register') {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        # trim()function take data from login.php & remove accidental space
        $password = $_POST['password'];

        $is_learner = isset($_POST['is_learner']) ? 1 : 0;
        $is_mentor = isset($_POST['is_mentor']) ? 1 : 0;
        #if check assin 1 else 0

        if (!$is_learner && !$is_mentor) {
            $is_learner = 1;
            //if user doesn't check either auto register as learner
        }

        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        #password hasing throught php default password hash

        $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, is_learner, is_mentor) VALUES (?, ?, ?, ?, ?)");
        //THIS PREPARE AN INSERT QUERY TO INSERT REGISTER VALUES TO USERS TABLE

        try {
            $stmt->execute([$name, $email, $password_hash, $is_learner, $is_mentor]);
            $user_id = $pdo->lastInsertId();

            #auto login code after registration
            $_SESSION['user_id'] = $user_id;
            $_SESSION['name'] = $name;
            $_SESSION['is_learner'] = $is_learner;
            $_SESSION['is_mentor'] = $is_mentor;

            routeUser($is_learner, $is_mentor); #function code at line-90

        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                #23000 is a SQL containts violation(like duplicate email)
                $_SESSION['error'] = "An account already exsists with this email.";
                header("Location: ../login.php");
                # move back to login page for email re-submission
                exit;
            }
            throw $e;//Throw $e to catch by the main catch block in case
            //its not SQLSTATE 23000 error.
        }
    }
    ######LOGIN#####
    elseif ($action === 'login') {
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(); #if any user found then true otherwise false
        if ($user && password_verify($password, $user['password_hash'])) {
            #if the user found & password_hash match then a login session generated
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['is_learner'] = $user['is_learner'];
            $_SESSION['is_mentor'] = $user['is_mentor'];
            $_SESSION['is_admin'] = $user['is_admin'];

            routeUser($user['is_learner'], $user['is_mentor']);
        } else {
            $_SESSION['error'] = "Invalid email or password";
            header("Location: ../login.php");
            exit;
        }
    }
} catch (Exception $e) {
    error_log("Authentication error: " . $e->getMessage());
    $_SESSION['error'] = "A system error occured. Please try again.";
    header("Location: ../login.php");
    #this block catch any system or DB authentication error and go for re-login.
    exit;
}

//routeUser function
function  routeUser(bool $is_learner, bool $is_mentor)
{
    if ($is_learner) {
        header("Location: ../dashboard_learner.php");
    } elseif ($is_mentor) {
        header("Location: ../dashboard_mentor.php");
    } else {
        header("Location: ../profile.php");
    }
    exit;
}

?>