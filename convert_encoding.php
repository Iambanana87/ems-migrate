<?php
$c = file_get_contents('c:/xampp/htdocs/ems/audit_raw_out.txt');
$c = mb_convert_encoding($c, 'UTF-8', 'UTF-16LE');
file_put_contents('C:\Users\hoaih\.gemini\antigravity\brain\1c92025a-e06c-4320-bf3f-732056640691\adversarial_raw_data.md', $c);
