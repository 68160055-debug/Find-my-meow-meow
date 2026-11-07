<?php
require 'crud_bootstrap.php';
$id=valid_id($_GET['id']??$_POST['id']??null);$pid=profile_id($conn);$error='';
if(!$id){http_response_code(400);exit('Invalid record ID');}
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();

 // TODO 3: Fill DELETE SQL with id AND profile_id conditions.
 $sql='DELETE FROM skill WHERE id = ? AND profile_id = ?';


 // TODO 3: Fill DELETE SQL with id AND profile_id conditions.
 $sql='/* TODO DELETE: specify WHERE id/profile_id */ SELECT 1';
 $sql = "DELETE FROM skill WHERE id = ? AND profile_id = ?";

 
 $stmt=mysqli_prepare($conn,$sql);
 if(!$stmt)$error='SQL not ready: review TODO 3.';
 else{mysqli_stmt_bind_param($stmt,'ii',$id,$pid);if(mysqli_stmt_execute($stmt)){mysqli_stmt_close($stmt);header('Location: skills.php?success=deleted');exit;} $error='Delete failed.';mysqli_stmt_close($stmt);}
}
page_start('DELETE | Confirm Skill Removal');
if($error)echo '<p class="err">'.h($error).'</p>';
echo '<div class="alert">Student task: complete DELETE SQL in skill_delete.php. This action cannot be undone.</div>';
?>
<p>Delete skill record #<?=h($id)?>?</p><form method="post"><?php csrf_field(); ?><input type="hidden" name="id" value="<?=h($id)?>"><button type="submit" class="danger">Yes, delete this skill</button> <a href="skills.php">Cancel</a></form><?php page_end(); ?>
