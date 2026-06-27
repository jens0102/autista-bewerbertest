<?php

require_once __DIR__ . '/../app/bootstrap.php';
require_once BASE_PATH . '/app/Services/MigrationService.php';
require_once BASE_PATH . '/app/Services/TestService.php';

function assert_same($expected, $actual, string $label): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $label . ' failed: expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

$multiple = ['type' => 'multiple', 'points' => 1, 'correct_answers' => '["A","B","C"]'];
assert_same(1.0, score_question($multiple, ['A', 'B', 'C']), 'multiple full');
assert_same(0.67, score_question($multiple, ['A', 'B']), 'multiple partial');
assert_same(0.33, score_question($multiple, ['A', 'B', 'D']), 'multiple penalty');

$ordering = ['type' => 'ordering', 'points' => 1, 'correct_answers' => '{"0":"A","1":"B","2":"C"}'];
assert_same(0.67, score_question($ordering, ['0' => 'A', '1' => 'C', '2' => 'C']), 'ordering partial');

$matching = ['type' => 'matching', 'points' => 1, 'correct_answers' => '{"L1":"A","L2":"B","L3":"C","L4":"D"}'];
assert_same(0.5, score_question($matching, ['L1' => 'A', 'L2' => 'X', 'L3' => 'C', 'L4' => 'Y']), 'matching partial');

echo "Self-test passed." . PHP_EOL;
