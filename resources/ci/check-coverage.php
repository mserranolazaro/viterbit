<?php

declare(strict_types=1);

/*
 * Fails when line or method coverage drops below the minimum (make coverage, CI).
 *
 *     php resources/ci/check-coverage.php var/coverage/clover.xml 90
 */

[, $cloverPath, $minimum] = $argv + [null, 'var/coverage/clover.xml', '90'];

$clover = @simplexml_load_file((string) $cloverPath);
if ($clover === false) {
    fwrite(STDERR, sprintf("Cannot read the coverage report %s.\n", $cloverPath));
    exit(1);
}

$metrics = $clover->project->metrics;
$percent = static fn (string $covered, string $total): float => (int) $metrics[$total] === 0
    ? 100.0
    : 100 * (int) $metrics[$covered] / (int) $metrics[$total];

$results = [
    'Lines' => $percent('coveredstatements', 'statements'),
    'Methods' => $percent('coveredmethods', 'methods'),
];

$failed = false;
foreach ($results as $name => $value) {
    $ok = $value >= (float) $minimum;
    $failed = $failed || !$ok;
    printf("  %-8s %6.2f %%  %s (minimum %s %%)\n", $name, $value, $ok ? 'OK' : 'TOO LOW', $minimum);
}

exit($failed ? 1 : 0);
