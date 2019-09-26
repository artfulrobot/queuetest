# queuetest

This extension is a place to explore how safe the CiviCRM Queue Runner is in a
multiprocess environment.

The extension is licensed under [AGPL-3.0](LICENSE.txt).

## Requirements

* PHP v7.0+
* CiviCRM 5.15+

## Usage

From the directory where you store your extensions...

```bash
git clone https://github.com/artfulrobot/queuetest.git
cd queuetest/tests
./runTest 10 20 Sql
```

This will run a test with 10 simultaneous processes. The processes will all be
started in parallel and then will all wait until a time at least 10s ahead of
now, but on the 10s (i.e. 9:23:00 or 9:23:10 or 9:23:20...).

The output (which is saved in test-result.log) looks like this:

```
Queue test with 3 parallel processes and 5 tasks using Sql queue running at 2019-09-26T11:39:20+01:00
  #1      Booted
  #1      Creating a queue.
    #2    Booted
  #1      Created a queue with 5 tasks.
      #3  Booted
--------- All processes should have booted.
    #2    Started
      #3  Started
          (Task 1 running)
          (Task 2 running)
    #2    Ran Task 1. [continues] 3 left
  #1      Started
          (Task 3 running)
      #3  Ran Task 2. [continues] 2 left
    #2    Ran Task 3. [continues] 2 left
          (Task 4 running)
      #3  No claim. [error] 2 left
      #3  Completed.
    #2    No claim. [error] 2 left
    #2    Completed.
  #1      Ran Task 4. [continues] 1 left
          (Task 5 running)
  #1      Ran Task 5. 0 left
  #1      Completed.
Each task ran once.
```

- The `#N` numbers refer to the different processes.

- At the end you want to see "Each task ran once", otherwise you'll see
  errors like "ERROR: Task 23 ran 2 times"

There's an alternate SQL Queue class which you can test by passing `Sql2` as the
last argument.
