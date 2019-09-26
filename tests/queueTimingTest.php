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


function die_with_help() {
  global $argv;
  echo "Usage: $argv[0] <id> <processes> <tasks>\n";
  echo "\n";
  echo "- <id> is a number to uniquely identify this process. Note that the process called with '1' will create the queue.\n";
  echo "- <processes> total processes being run (just used for formatting).\n";
  echo "- <tasks> is a number of tasks to create.\n";
  echo "\n";
  echo "Typical invocation is via runTest\n";
  echo "\n";
  exit;
}


class QueueTimingTest
{
  public $process_id;
  public $start_time;
  public $tasks;
  public static $processes;

  public function __construct($process_id, $processes, $tasks) {
    $this->process_id = $process_id;
    static::$processes = $processes;
    $this->tasks = $tasks;

    // At least 30s ahead, on the :00 or :30
    $this->start_time = strtotime(date('H:i'));
    $now = time();
    while (($this->start_time - $now) < 10) {
      $this->start_time += 10;
    }

    $this->log("Booted");
    if ($process_id == '1') {
      $this->createQueue();
    }
  }
  public function log($message) {
    printf('%-' . (static::$processes*3) . 's %s',
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
    for ($i=1; $i<=$this->tasks; $i++) {
      $queue->createItem(new CRM_Queue_Task(
        ['QueueTimingTest', 'processTask'], [$i], "Task $i"
      ));
    }
    $this->log("Created a queue with $this->tasks tasks.");
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
      print str_repeat("---", static::$processes) . " All processes should have booted.\n";
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
    printf('%-' . (static::$processes*3) . 's %s', '', "(Task $i running)\n");
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

if ($argc < 4 || in_array($argv[1] ?? '', ['help', '--help', '-h'])) {
  die_with_help();
}


$queueTimingTest = new QueueTimingTest($argv[1], $argv[2], $argv[3]);
$queueTimingTest->onYourMarksSetGo();
