<?php
/**
 * @file
 *
 * Parse log file to check whether:
 * - tasks ran in order.
 * - parallel processing occurred.
 *
 */
$lines = explode("\n", file_get_contents('./test-result.log'));
$last_task_started=0;
$last_task_completed=0;
$ran_in_order=TRUE;
$ran_in_parallel=FALSE;
$task=NULL;
foreach ($lines as $line) {
  if (preg_match('/Task (\d+) (running|completed)/', $line, $matches)) {
    $task = $matches[1];
    if ($matches[2] === 'running') {
      $ran_in_order &= ($task > $last_task_started);
      $last_task_started = $task;
      $ran_in_parallel |= ($task != 1 + $last_task_completed);
    }
    else {
      $last_task_completed = $task;
    }
  }
}
if ($task!==NULL) {
  echo $ran_in_order ? "Tasks ran in order. " : "Tasks did NOT run in order. ";
  echo ($ran_in_parallel ? 'Parallel' : 'No parallel') . " processing occurred.\n";
}
