#!/usr/bin/php
<?php
/**
 *
 * Call with cv scr queueTimingTest.php
 *
 */

if (php_sapi_name() !== 'cli') {
  exit;
}
/**
 * Call the "cv" command.
 *
 * @param string $cmd
 *   The rest of the command to send.
 * @param string $decode
 *   Ex: 'json' or 'phpcode'.
 * @return string
 *   Response output (if the command executed normally).
 * @throws \RuntimeException
 *   If the command terminates abnormally.
 */
function cv($cmd, $decode = 'json') {
  $cmd = 'cv ' . $cmd;
  $descriptorSpec = array(0 => array("pipe", "r"), 1 => array("pipe", "w"), 2 => STDERR);
  $env = getenv() + array('CV_OUTPUT' => 'json');
  $process = proc_open($cmd, $descriptorSpec, $pipes, __DIR__, $env);
  fclose($pipes[0]);
  $result = stream_get_contents($pipes[1]);
  fclose($pipes[1]);
  if (proc_close($process) !== 0) {
    throw new RuntimeException("Command failed ($cmd):\n$result");
  }
  switch ($decode) {
    case 'raw':
      return $result;

    case 'phpcode':
      // If the last output is /*PHPCODE*/, then we managed to complete execution.
      if (substr(trim($result), 0, 12) !== "/*BEGINPHP*/" || substr(trim($result), -10) !== "/*ENDPHP*/") {
        throw new \RuntimeException("Command failed ($cmd):\n$result");
      }
      return $result;

    case 'json':
      return json_decode($result, 1);

    default:
      throw new RuntimeException("Bad decoder format ($decode)");
  }
}

// $config = cv('vars:show');
// printf("We should navigate to the dsahboard: %s\n\n", cv('url civicrm/dashboard'));

function die_with_help() {
  global $argv;
  echo "Usage: $argv[0] <id>\n";
  echo "\n";
  echo "Where <id> is a number to uniquely identify this process. Note that the process called with '1' will create the queue.\n";
  echo "Typical invocation:\n";
  echo "\n";
  echo "    for id in \$(seq 5); do $argv[0] \$id & ; done | tee log ;wait\n";
  echo "\n";
  echo "\n";
  echo "Change 2 for a higher number for a more aggressive test\n";
  echo "\n";
  exit;
}


class QueueTimingTest
{
  public $process_id;
  public $start_time;

  public function __construct($process_id, $start_time) {
    $this->process_id = $process_id;
    $this->start_time = $start_time;
    $this->log("Booted");
    if ($process_id == '1') {
      $this->createQueue();
    }
  }
  public function log($message) {
    printf('%-30s %s',
      str_repeat(" ", 2*$this->process_id) . "#$this->process_id",
      "$message\n");
  }
  public function createQueue() {
    $this->log("Creating a queue.");
    $queue = CRM_Queue_Service::singleton()->create([
      'type' => 'Sql',
      'name' => 'queue-test',
      'reset' => true, // remove a previous queue.
    ]);
    for ($i=1; $i<11; $i++) {
      $queue->createItem(new CRM_Queue_Task(
        ['QueueTimingTest', 'processTask'], [$i], "Task $i"
      ));
    }
    $this->log("Created a queue with 10 tasks.");
  }
  public function onYourMarksSetGo() {
    $human = date('H:i:s', $this->start_time);
    if ($this->process_id == 1) {
      sleep(3); // Allow time for other processes to start.
      do {
        $wait = (int) ($this->start_time - microtime(TRUE));
        fwrite(STDERR, "\rStarts at $human in {$wait}s...    ");
        sleep(1);
      } while ($wait > 3);
      fwrite(STDERR, "\r                                   \r");
      print "------------------------------ All processes should have booted.\n";
    }
    $wait = $this->start_time - microtime(TRUE);
    sleep($wait);
    $this->log("Started");
    $this->runQueue();
  }
  public function runQueue() {
    $queue = CRM_Queue_Service::singleton()->create([
      'type' => 'Sql',
      'name' => 'queue-test',
      'reset' => false, // load existing queue.
    ]);

    $runner = new CRM_Queue_Runner([
      //'title' => '',
      'queue' => $queue,
      'onEnd' => [$this, 'onEnd'],
    ]);

    $last_task_title = NULL;
    while (TRUE) {
      $result = $runner->runNext(false);
      if ($result['last_task_title'] && $result['last_task_title'] !== $last_task_title) {
        $this->log("Ran $result[last_task_title]. "
          . ($result['is_error'] ? '[error] ' : '')
          . ($result['is_continue'] ? '[continues] ' : '')
          . "$result[numberOfItems] left");
        $last_task_title = $result['last_task_title'];
      }
      else {
        $this->log("No claim. "
          . ($result['is_error'] ? '[error] ' : '')
          . ($result['is_continue'] ? '[continues] ' : '')
          . "$result[numberOfItems] left");
      }
      if (!$result['is_continue']) {
        break;
      }
    }
    $this->log("Completed.");
  }
  public static function processTask($ctx, $i) {
    printf('%-30s %s', '', "(Task $i running)\n");
    return TRUE;
  }
  public function onEnd() {
    $this->log("Queue runner ended.");
  }
}


// CV boot.
eval(cv('php:boot', 'phpcode'));

// Disable output buffering.
ob_end_flush();
ob_implicit_flush();

if ($argc < 2 || in_array($argv[1] ?? '', ['help', '--help', '-h'])) {
  die_with_help();
}


// Parse command args.

if (!empty($argv[2])) {
  $start_time = strtotime($argv[1]);
  if ($start_time === FALSE || $start_time < time()+5) {
    echo "ERROR: start time is not valid.\n\n";
    die_with_help();
  }
}
else {
  // At least 30s ahead, on the :00 or :30
  $start_time = strtotime(date('H:i'));
  $now = time();
  while (($start_time - $now) < 10) {
    $start_time += 10;
  }
}

$queueTimingTest = new QueueTimingTest($argv[1], $start_time);
$queueTimingTest->onYourMarksSetGo();
