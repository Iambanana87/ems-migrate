<?php
$file = 'c:\xampp\htdocs\ems\laravel-api\.env';
$contents = file_get_contents($file);
// Remove null bytes and the garbage at the end
$contents = str_replace("\0", "", $contents);
$contents = preg_replace('/A P P _ T I M E Z O N E .*/', '', $contents);
$contents = trim($contents) . "\n\nAPP_TIMEZONE=Asia/Ho_Chi_Minh\n";
file_put_contents($file, $contents);
echo "Cleaned .env successfully.\n";
