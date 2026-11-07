
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


<?php
require 'crud_bootstrap.php';
$id=valid_id($_GET['id']??$_POST['id']??null);$pid=profile_id($conn);$error='';$row=null;
if(!$id){http_response_code(400);exit('Invalid record ID');}

// TODO 2a: Fill SELECT SQL for exactly one record belonging to the profile.
$sql='SELECT skill_name, skill_level FROM skill WHERE id = ? AND profile_id = ?';
$stmt=mysqli_prepare($conn,$sql);
if($stmt){mysqli_stmt_bind_param($stmt,'ii',$id,$pid);mysqli_stmt_execute($stmt);mysqli_stmt_bind_result($stmt,$oldName,$oldLevel);if(mysqli_stmt_fetch($stmt))$row=['skill_name'=>$oldName,'skill_level'=>$oldLevel];mysqli_stmt_close($stmt);}


// TODO 2a: Fill SELECT SQL for exactly one record belonging to the profile.
$sql='/* TODO SELECT: specify columns and WHERE id/profile_id */ SELECT 1';
$sql = "SELECT * FROM skill WHERE id = ? AND profile_id = ?";


$stmt=mysqli_prepare($conn,$sql);
if($stmt){mysqli_stmt_bind_param($stmt,'ii',$id,$pid);{
}mysqli_stmt_execute($stmt);{
mysqli_stmt_bind_result($stmt,$oldId,$oldProfileId,$oldName,$oldLevel);}
if(mysqli_stmt_fetch($stmt)){
    $row=['skill_name'=>$oldName,'skill_level'=>$oldLevel];
    }
        mysqli_stmt_close($stmt);

}
 


if(!$row){http_response_code(404);exit('Record not found or TODO 2a unfinished.');}
$name=$row['skill_name'];$level=$row['skill_level'];
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();$name=trim((string)($_POST['skill_name']??''));$level=trim((string)($_POST['skill_level']??''));
 if($name===''||mb_strlen($name)>120||!in_array($level,['Beginner','Intermediate','Advanced'],true))$error='Please enter valid values.';
 else{

 // TODO 2b: Fill UPDATE SQL with placeholders AND safe WHERE clause.
 $sql='UPDATE skill SET skill_name = ?, skill_level = ? WHERE id = ? AND profile_id = ?';


 // TODO 2b: Fill UPDATE SQL with placeholders AND safe WHERE clause.
 $sql='/* TODO UPDATE: specify SET and WHERE id/profile_id */ SELECT 1';
 $sql = "UPDATE skill SET skill_name = ?, skill_level = ? WHERE id = ? AND profile_id = ?";



 $stmt=mysqli_prepare($conn,$sql);
 if(!$stmt)$error='SQL not ready: review TODO 2b.';
 else{mysqli_stmt_bind_param($stmt,'ssii',$name,$level,$id,$pid);if(mysqli_stmt_execute($stmt)){mysqli_stmt_close($stmt);header('Location: skills.php?success=updated');exit;} $error='Update failed.';mysqli_stmt_close($stmt);}
 }
}
page_start('UPDATE | Edit Skill');if($error)echo '<p class="err">'.h($error).'</p>';
echo '<div class="alert">Student tasks: complete SELECT (2a) and UPDATE (2b) in skill_edit.php.</div>';
?>
<form method="post"><?php csrf_field(); ?><input type="hidden" name="id" value="<?=h($id)?>"><label>Skill name</label><input name="skill_name" required maxlength="120" value="<?=h($name)?>"><label>Level</label><select name="skill_level"><?php foreach(['Beginner','Intermediate','Advanced'] as $choice)echo '<option '.($choice===$level?'selected':'').'>'.h($choice).'</option>';?></select><p><button type="submit">Update skill</button></p></form><?php page_end(); ?>
