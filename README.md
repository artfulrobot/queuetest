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
Queue test with 3 parallel processes and 5 tasks using Sql2 queue, running at 2019-10-05T11:24:50+0000                                                                                       
  #1      Booted
      #3  Booted
    #2    Booted
  #1      Creating a queue.
  #1      Created a queue with 5 tasks.
--------- All processes should have booted.
    #2    Started
      #3  Started
          (Task 1 running)
    #2    No claim. [error] 5 left
    #2    Completed.
  #1      Started
  #1      No claim. [error] 5 left
  #1      Completed.
          (Task 1 completed 3s)
      #3  Ran Task 1. [continues] 4 left
          (Task 2 running)
          (Task 2 completed 0.67s)
      #3  Ran Task 2. [continues] 3 left
          (Task 3 running)
          (Task 3 completed 1.98s)
      #3  Ran Task 3. [continues] 2 left
          (Task 4 running)
          (Task 4 completed 1.57s)
      #3  Ran Task 4. [continues] 1 left
          (Task 5 running)
          (Task 5 completed 1.55s)
      #3  Ran Task 5. 0 left
      #3  Completed.
Tasks ran in order. No parallel processing occurred.
Each task ran once.
```

And another example:
```
Queue test with 3 parallel processes and 5 tasks using SqlParallel queue, running at 2019-10-05T11:26:00+0000                                                                                
      #3  Booted
    #2    Booted
  #1      Booted
  #1      Creating a queue.
  #1      Created a queue with 5 tasks.
--------- All processes should have booted.
      #3  Started
          (Task 1 running)
    #2    Started
          (Task 2 running)
          (Task 2 completed 0.92s)
    #2    Ran Task 2. [continues] 4 left
  #1      Started
          (Task 3 running)
          (Task 4 running)
          (Task 3 completed 1.63s)
          (Task 4 completed 1.65s)
    #2    Ran Task 3. [continues] 2 left
          (Task 5 running)
  #1      Ran Task 4. [continues] 2 left
  #1      No claim. [error] 2 left
  #1      Completed.
          (Task 5 completed 1.54s)
    #2    Ran Task 5. [continues] 1 left
    #2    No claim. [error] 1 left
    #2    Completed.
          (Task 1 completed 3s)
      #3  Ran Task 1. 0 left
      #3  Completed.
Tasks ran in order. Parallel processing occurred.
Each task ran once.
```

- The `#N` numbers refer to the different processes.

- At the end you want to see "Each task ran once", otherwise you'll see
  errors like "ERROR: Task 23 ran 2 times"

There's two alternate SQL Queue classes which you can test by passing
`Sql2` or `SqlParallel` as the last argument.
