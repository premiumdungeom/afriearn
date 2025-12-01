<?php
// logout.php
require_once 'config.php';

$supabase->signOut();
redirect('login.php');
?>