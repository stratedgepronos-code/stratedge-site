<?php
echo "VERSION: REBUILD 2978882 — " . date('Y-m-d H:i:s') . " — ";
echo file_exists(__DIR__ . '/packs-daily.php') ? 'packs-daily EXISTS' : 'MISSING';
echo " — ";
$content = @file_get_contents(__DIR__ . '/packs-daily.php');
if (strpos($content, 'pk-hero') !== false) echo 'NEW DESIGN ✅';
elseif (strpos($content, 'packs-hero') !== false) echo 'OLD DESIGN ❌';
else echo 'UNKNOWN';
