<?php 
session_start();
require_once '../includes/db_connect.php';


if($_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_SESSION['user_id'])){
#if request_method is not post or direct acess, kick unauthenticated service
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';
$skill_type = $_POST['skill_type'] ?? '';

if(!in_array($skill_type, ['knows', 'wants_to_learn'])){
    $_SESSION['error'] = "Invalid skill type designation";
    header("Location: ../edit_profile.php");
    exit;
}

try {
    if($action === 'add'){
        strtoupper( $skill_name = trim($_POST['skill_name'] ?? ''));
        //take the input skill name
        if(empty($skill_name)){
            throw new Exception("Skill name cannot be empty");    
        }
        
        //these stmts check if skill aready exsist in the master skills table
        $stmt = $pdo->prepare("SELECT skill_id FROM skills WHERE skill_name = ?");
        $stmt->execute([$skill_name]);
        $skill = $stmt->fetch();

        if ($skill) {
           $skill_id = $skill['skill_id'];
        } else{
            //if skill not exist in master skill table run a prepare query
            $stmt = $pdo->prepare("INSERT INTO skills (skill_name) VALUES(?)");
            $stmt->execute([$skill_name]); //add the skill
            $skill_id = $pdo->lastInsertId(); //give the skill_id 
        }

        $stmt = $pdo->prepare("INSERT IGNORE INTO user_skills (user_id, skill_id, skill_type) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $skill_id, $skill_type]);
        #above two stmt prevent page from fetal error, 
        //if user input same skill twice the Query will simply ignore it.

        $_SESSION['msg'] ="Skill added successfully";
    } elseif ($action === 'remove') {
        $skill_id = (int) ($_POST['skill_id'] ?? 0); #take the skill id & convert into an int.
        $stmt = $pdo->prepare("DELETE FROM user_skills WHERE user_id = ? AND skill_id = ? AND skill_type = ?");
        $stmt->execute([$user_id, $skill_id, $skill_type]);
        //above 2 stmt run a delete query which delete the skill from user table
        //but keep it inside the master skill table
        $_SESSION['msg'] = "Skill removed.";
    }
} catch (PDOException $e) {
    error_log("Databse error in skill_process. ".$e->getMessage());
    $_SESSION['error'] = "System error while updating skills";
} catch(Exception $e){
    $_SESSION["error"] = $e->getMessage();
}

$redirect_url = $_SERVER['HTTP_REFERER'] ?? '../edit_profile.php';
header("Location: ".$redirect_url);
//above line takes user back where he started or
// if its missing or not shown then edit_profile.php
exit;
?>