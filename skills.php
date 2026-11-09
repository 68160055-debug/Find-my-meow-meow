<?php
require 'crud_bootstrap.php';
$pid=profile_id($conn); $rows=[];
$stmt=mysqli_prepare($conn,'SELECT id,skill_name,skill_level FROM skill WHERE profile_id = ? ORDER BY id DESC');
mysqli_stmt_bind_param($stmt,'i',$pid); mysqli_stmt_execute($stmt);mysqli_stmt_bind_result($stmt,$id,$name,$level);
while(mysqli_stmt_fetch($stmt))$rows[]=['id'=>$id,'skill_name'=>$name,'skill_level'=>$level];mysqli_stmt_close($stmt);
page_start('Manage Skills | CRUD Lab');
if(isset($_GET['success'])) echo '<p class="ok">Action completed. Check the rows below.</p>';
echo '<p class="muted">Existing resume skill records, all actions target the first demo profile.</p><table><tr><th>ID</th><th>Skill</th><th>Level</th><th>Actions</th></tr>';
foreach($rows as $r){echo '<tr><td>'.h($r['id']).'</td><td>'.h($r['skill_name']).'</td><td>'.h($r['skill_level']).'</td><td><a href="skill_edit.php?id='.urlencode((string)$r['id']).'">Edit</a> | <a href="skill_delete.php?id='.urlencode((string)$r['id']).'">Delete</a></td></tr>';}
echo '</table>';page_end();
