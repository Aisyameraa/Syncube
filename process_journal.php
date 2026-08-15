<?php
// process_journal.php snippet
// 1. Grab file targets if images are submitted
$image_destination = null;
if(!empty($_FILES['journal_image']['name'])) {
    $image_destination = 'uploads/' . time() . '_' . $_FILES['journal_image']['name'];
    move_uploaded_uploaded_file($_FILES['journal_image']['tmp_name'], $image_destination);
}

// 2. Map directly to columns seen in your phpMyAdmin table structure:
$sql = "INSERT INTO journalentries (user_id, title, content, mood, created_at, updated_at) 
        VALUES (:user_id, :title, :content, :mood, NOW(), NOW())";
// (Execute statement using PDO or MySQLi wrappers)