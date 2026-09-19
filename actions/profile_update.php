<?php 
//profile_update.php 

//Very common code
//Check the session, Start if not started
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

//Connect db_connect & auth.php files
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';

//Ensure log must
requireLogin();
$user_id = getCurrentUserId(); //Get the current user id via session [source: auth.php]

//Check if the action passed method is not post or the action is not == update profile 
if($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['action'] ?? '') !== 'update_profile'){
    header("Location: ../edit_profile.php");
    exit();
}

$name = trim($_POST['name'] ?? ''); //trim spaces from updated name
$bio = trim($_POST['bio'] ?? ''); //trim spaces from bio
$exp_level = $_POST['exp_level'] ?? null; //set the exp_level if its updated, keep null if not

//catch '' string from html form input & set it to null
if($exp_level === ''){
    $exp_level = null;
}

//if name is empty or its lenth is more than 100 char fallback to edit_profile
if(empty($name) || strlen($name) > 100){
    $_SESSION['error'] = 'Name is required and must not exceed 100 characters';
    header("Location: ../edit_profile.php");
    exit();
}


$allowed_exp_levels = ['Beginner', 'Intermediate', 'Expert']; //set a valid choice of exp_level
//check if the user forget to set exp_level or $exp_level is not allowed_exp_level[Done by in_array stmt]
if($exp_level !== null && !in_array($exp_level, $allowed_exp_levels, true)){
    $_SESSION['error'] = "Invalid experience level selected.";
    header("Location: ../edit_profile.php");
    exit();
}

try {
    //retrive the current profile_photo by PDO statement
    $stmt = $pdo->prepare("SELECT profile_photo FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $_SESSION['error'] = "User record not found.";
        header("Location: ../edit_profile.php");
        exit();
    }
    // check if the user had any current photo if not default pic
    $current_photo = $user['profile_photo'] ?? 'default.jpg';
} catch (PDOException $e) {
    //this common block handle any database error & throw massage
    error_log("Database Error (Fetch User): ".$e->getMessage());
    $_SESSION['error'] = "An unexpected database error occurred.";
    header("Location: ../edit_profile.php");
    exit();
}

$new_photo_filename = null; //a new photo file name variable created, set null for later use
 //__DIR__ find absolute file path where this script, Enable the code to run even if the sever changed
$upload_dir = __DIR__. '/../assets/images/profile/';

//##IMPORTANT
if(!is_dir($upload_dir)){ //if upload directory missing
// create a new directory. "0755" - security permission means only owner can read & write 
// for rest of server read only 
    mkdir($upload_dir, 0755, true);
}

//stmt Ensure that global files array has an item named prifle photo and
//if user doesn't add any photo "!== UPLOAD_ERR_NO_FILE" become true. So father code will not run
if(isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] !== UPLOAD_ERR_NO_FILE){
    $file = $_FILES['profile_photo'];

    //during a file upload PHP populate with a internal int code, 0 means success, anything else error
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = "An error occurred during file upload. Please try again.";
        header("Location: ../edit_profile.php");
        exit();
    }

    //validate that photo is <2MB (2*1024kb*1224byts)
    if ($file['size'] > 2097152) {
        $_SESSION['error'] = "Profile photo must be 2MB or smaller.";
        header("Location: ../edit_profile.php");
        exit();
    }

    //during upload PHP takes the image a store with temp name
    //getimagesize() verify that the uploaded pic is a legitmate image
    $image_info = getimagesize($file['tmp_name']);
    //if getimagesize() failed return false
    if ($image_info === false) { 
        $_SESSION['error'] = "The uploaded file is not a valid image.";
        header("Location: ../edit_profile.php");
        exit();
    }


    $allowed_mime_types = ['image/jpeg', 'image/png', 'image/webp'];
    //if user try to put any other file format than jpeg/png/wbmp throw error
    if (!in_array($image_info['mime'], $allowed_mime_types, true)) {
        $_SESSION['error'] = "Only JPG, PNG, and WEBP image formats are allowed.";
        header("Location: ../edit_profile.php");
        exit();
    }

    $extension = 'jpg';
    if($image_info['mime'] === 'image/png') $extension = 'png';
    if($image_info['mime'] === 'image/webp') $extension = 'webp';

    //Genarate a new file name-
    //attach the user_id and a randomised text by uniquid(..., true) then .fileExtension
    //Example: user_5_65a7b2c8e14f98.71029384.png 
    $new_photo_filename = uniqid('user_' . $user_id . '_', true) . '.' . $extension;
    $destination = $upload_dir . $new_photo_filename;

    //move_uploaded_file() failed
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        $_SESSION['error'] = "Failed to save the uploaded image to the server.";
        header("Location: ../edit_profile.php");
        exit();
    }
}
//ternary op, if new photo is valid set new photo to be final photo if not current photo remains
$final_photo = $new_photo_filename ? $new_photo_filename : $current_photo;

try {
    $updatestmt = $pdo->prepare("UPDATE users SET name = ?, bio = ?, exp_level = ?, profile_photo = ? WHERE user_id = ?");
    $updatestmt->execute([$name, $bio, $exp_level, $final_photo, $user_id]);

    //if the user add a new photo and the old photo is not the 'default photo' 
    if ($new_photo_filename && $current_photo !== 'default.jpg') {
        $old_photo_path = $upload_dir . $current_photo;
        if (file_exists($old_photo_path) && is_file($old_photo_path)) {
            unlink($old_photo_path); // safely unlink the photo to prevent abandoned photos to take place
        }
    }

    $_SESSION['name'] = $name;
    $_SESSION['success'] = "Profile updated successfully.";
} catch (PDOException $e) {
    error_log("Database error (Update Profile): ".$e->getMessage());
    
    //upload file clean up for failed uploads
    if ($new_photo_filename) {
        $failed_upload_path = $upload_dir . $new_photo_filename;
        if (file_exists($failed_upload_path) && is_file($failed_upload_path)) {
            unlink($failed_upload_path);
        }
    }
    $_SESSION['error'] = "An unexpected error occurred while updating the profile.";
}

header("Location: ../edit_profile.php");
exit();
