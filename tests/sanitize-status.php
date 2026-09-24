<?php
// Keep only non-identifying charger fields needed for compatibility tests.
if ($argc !== 3) { fwrite(STDERR, "Usage: php tests/sanitize-status.php input output\n"); exit(1); }
$raw = file_get_contents($argv[1]);
$data = json_decode($raw, true);
if (!is_array($data)) { fwrite(STDERR, 'Invalid JSON: '.json_last_error_msg()."\n"); exit(1); }
$allowed = ['alw','car','psm','frc','amp','err','var','ama','nrg','fwv'];
$safe = array_intersect_key($data, array_flip($allowed));
file_put_contents($argv[2], json_encode($safe, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
echo 'Kept '.count($safe).' fields; omitted '.(count($data)-count($safe))." fields.\n";
echo json_encode($safe, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
