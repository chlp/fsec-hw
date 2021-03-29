# F-Secure homework

This file contains a description of the completed homework.

## Environment

I saved the original task environment and added one new service:

* SQS queue `submissions`: incoming submissions can be read from this queue
* Kinesis stream `events`: outgoing events are published to this stream
* Result php application `telemetry-collector`:  that receives messages from SQS queue, parse and put events into
  Kinesis data stream.

## Setup

### Launching

To launch the services, use `docker-compose up -d`. This will launch localstack, sensor-fleet and telemetry-collector to
run on the background. Previously lets look at environment variables.

### Environment variables

Inside `docker-compose.yml` you can find several environment variables for service `telemetry-collector`. Main of them:

* `QUEUE_WAIT_TIME_SEC` - use for long polling mechanism
* `QUEUE_RECEIPTS_TO_DELETE_INTERVAL_SEC` - time to fill the SQS queue items to delete before deleting
* `QUEUE_MAX_RECEIPTS_TO_DELETE_AT_ONCE` - max count of items to delete in a batch
* `DATA_STREAM_FLUSH_INTERVAL_SEC` - time to fill the Kinesis data stream buffer before sending
* `DATA_STREAM_MAX_BUFFER_SIZE` - max count of items to delete in a batch
* `QUEUE_VISIBILITY_TIMEOUT_SEC` - SQS visibility timeout must be more than `QUEUE_RECEIPTS_TO_DELETE_INTERVAL_SEC`
  and `DATA_STREAM_FLUSH_INTERVAL_SEC`

### Checking the work

To validate that telemetry-collector is working, run the following commands and expect similar responses as in the
examples below:

```console
$ docker-compose logs telemetry-collector
{"level":"Info","message":"Collect 2 more messages (15 events)"}
{"level":"Info","message":"Collect 2 more messages (20 events)"}
{"level":"Info","message":"Collect 2 more messages (18 events)"}
{"level":"Info","message":"Collect 0 more messages (0 events)"}
{"level":"Info","message":"Collect 0 more messages (0 events)"}
{"level":"Info","message":"Supervisor next tick. Average count is 9, average size 3916 bytes."}
{"level":"Info","message":"Collect 0 more messages (0 events)"}
{"level":"Info","message":"Collect 0 more messages (0 events)"}
{"level":"Info","message":"Collect 0 more messages (0 events)"}
```

## Assignment details

### Output event format

Events are created from the data from messages and put to Kinesis data stream in the following JSON format:

```yaml
{
  "event_id": "<uuid>",                             # event unique identifier generated inside telemetry-collector app
  "type": "<new_process/network_connection>",       # event type name: "new_process" or "network_connection" (string)
  "device_id": "<uuid>",                            # unique identifier of the device (string)
  "submission_id": "<uuid>",                        # unique identifier of the submission (string)
  "time_processed": "<ISO 8601>",                   # processed by backend time, UTC (string)
  "time_created": "<ISO 8601>",                     # passed from source, creation time of the submission, device local time (string)
  "type_specific_data": { }                         # see below
}
```

`type_specific_data` for `new_process`:

```yaml
{
  "cmdl": "<commandline>",                          # command line of the executed process (string)
  "user": "<username>"                              # username who started the process (string)
}
```

`type_specific_data` for `network_connection`:

```yaml
{
  "source_ip": "<ipv4>",                            # source ip of the network connection, e.g. "192.168.0.1" (string)
  "destination_ip": "<ipv4>",                       # destination ip of the network connection, e.g. "142.250.74.110" (string)
  "destination_port": <0-65535>                     # destination port of the network connection, e.g. 443 (integer)
}
```

#### About `time_created`

This is a specific field. Need to know the details of how we can use it. Looks like we can't believe him, but we can use
it for future analysis. We passing it as is.

I have not found timezone inside time_created :(. If it were, we would be able to make assumptions about how fast
messages reach us (about realtime).

And yes, we have different ISO 8601 time format:

```
"time_processed": "2021-03-29T12:06:20+00:00"
"time_created":   "2021-03-29T12:06:09.576033"
```

#### About `submission_id`

I decided to leave it, although it was not in the requirements. It looks like it doesn't take up much space, and may be
useful for analysis, but, of course, it is better to know the specifics to make a decision.

### Main functions

* `runs continuously until terminated` - Yes
* `reads "submissions" from submissions SQS queue` - Yes, but `submission` name set via environment
  variable `SUBMISSIONS_QUEUE_NAME`.
* `publishes "events" to events Kinesis stream` - Yes, but `events` name set via environment
  variable `EVENTS_DATA_STREAM_NAME`.

### Requirements

* `each event is published as an individual record to kinesis` - Yes, in the code, this can be found next to the
  lines `telemetryCollector.php`:

```php
            foreach ($telemetryMessageEvents as $event) {
                if (!$dataStreamService->putRecord($event->toDataStreamRecord(), $afterSendingCallback)) {
```

* `each event must have information of the event type ("new_process" or "network_connection")`
  Yes, each event has field `type`
* `each event must have an unique identifier`
  Yes, each event has field `event_id`, but if we receive one event several times, it would have different `event_id`. I
  didn't have time to think about how to make it permanent for one message. It seems to be possible to assemble it from
  the submission_id, the type, and its position in the array.
* `each event must have an identifier of the source device ("device_id")`
  Yes, each event has field `device_id` with validated data
* `each event must have a timestamp when it was processed (backend side time in UTC)`
  Yes, each event has field `time_processed`
* `submissions are validated and invalid or broken submissions are dropped`
  Yes, and you can find all dropped data if turn on debug log `Logger.php` (but after changes it is need to
  rebuild `docker-compose up -d --no-deps --build telemetry-collector`):

```php
    public static function debug(string $message): void
    {
        // todo: turn on from config
        if (false) {
            self::writeLog([
                'level' => self::DEBUG,
                'message' => substr($message, 0, self::MAX_MESSAGE_SIZE),
            ]);
        }
    }
```

* `must guarantee no data loss (for valid data), i.e. submissions must not be deleted before all events are succesfully published`
  Yes, result application has an entity `DataStreamService` that can respond with callback after it puts data to the
  stream. We have a counter of events for each message `$eventsCountPerMessage`, and we delete a message when all events
  put to data stream. If the application crashes, we can duplicate the record in the data stream, but not lose the
  message.
* `must guarantee ordering of events in the context of a single submission`
  It looks like it is. We keep the order of the elements in the array and pass it on. Although I am not completely sure
  that I understood this point completely correctly.
* `the number of messages read from SQS with a single request must be configurable`
  Yes, environment variable `QUEUE_MAX_NUMBER_OF_MESSAGE_PER_REQUEST`.
* `the visibility timeout of read SQS messages must be configurable`
  Yes, environment variable `QUEUE_VISIBILITY_TIMEOUT_SEC`. Inside
  code `QueueService.php` `QueueService::changeMessagesVisibility()`, but I did not test that visibility timeout
  parameter of messages is change in fact. And I haven't worked out this method well enough. What should I do if I can't
  change it? Can we find out the current value of this parameter, so as not to make unnecessary calls, if it is the same
  value already?

### Optional design questions

#### How does your application scale and guarantee near-realtime processing when the incoming traffic increases? Where are the possible bottlenecks and how to tackle those?

We can keep several instances of our application and then each of them can simultaneously request data from the queue
and put it in the stream. This does not contradict the requirements, because we work with one message from start to end
inside one application instance, but a later queue message may reach the data stream earlier.

I used the long polling mechanism for receiving messages from SQS queue. This allows you to receive events as soon as
they are received. We can use this same mechanism to understand how many application instances we need to keep:

* If there is at least one application that hangs on long polling mechanism for more than a second, then we have enough.
* If there are N (more than one) "waiting" instances, than we need to kill N-1 instances.
* If we don't have such "waiting" apps, then we need to start new, and continue until it turns out that there is one
  waiting application only one.

To avoid killing and creating apps too often, we can increase the difference from 1 to 2 (or 3, or ...).

It seems that to keep track of near-realtime we could use input `time_created`, but this is a big question.

We need to think about Kinesis limits:

```
One shard can ingest up to 1000 data records per second, or 1MB/sec. Add more shards to increase your ingestion capability.
```

My application have its own Supervisor `Supervisor.php`, that calculate average count of records and size and increase
and decrease shards count, but works only with single instance. It is need to use shared storage (like redis/cache) for
syncing OR put supervisor in a separate application that will monitor the overall shared metrics.

Ok. If we are talking about incoming traffic increasing, then I should highlight a few main system points:

* Queue
    * we need to observe the read limits and work out execution errors
    * the queue can fall, restart, reconfigure, we need to ensure the operation of the system as a whole, so that an
      error in this place does not lead to the fall of the entire system (the domino principle). Need to think about
      graceful degradation.
    * we need to regularly disable it, see how the system behaves and turn it back on. The rest of the system should
      recover itself
* Network between queue server and our application
    * we need to set correct timeouts (exponential backoff)
    * set alerts (the response time is less than the timeout, but higher than the norm) to see the problem before
      everything crashes
    * regularly remove nodes from the product load and conduct exercises with load testing to understand when everything
      will fall
* Our system. CPU, RAM
    * constantly monitor the main indicators and alert not only critical situations, but also unusual behavior for this
      time, day of the week
    * need to test our program on different situations
* Network between our application and Data stream server.
    * the same as "Network between queue server and our application"
* Data stream
    * the same as Queue
    * Shards. The total load on them and a separate one for each (increase and decrease count of shards).

We need to use here Fault Tolerance principles. It often happens that when the system responds with an error, we
instantly send the next command, and the system works even worse as a result. To prevent it we need to use Circuit
breaker and Exponential backoff (about timeouts).

#### What kind of metrics you would collect from the application to get visibility to its througput, performance and health?

* The number of requests (and the size of them) sent to the queue and to the data stream.
* CPU, RAM of our servers
* The size of our buffers (we send messages in batches). For example, if the data stream starts to slow down, we will be
  able to make a decision to increase the visibility timeout. And memory leaks.
* The count of warnings and errors. If we have more than the expected number (or percent) of errors, then we need to
  alarm.
* I have already written, but I will repeat myself. We need to see not only the critical moments, we need to remember
  the patterns of behavior and predict the result, so that we can prevent the problem before the critical indicators
  come
* Our application can provide special API method "healthcheck". This is necessary so that the system can monitor from
  the outside (so that the one who follows life does not die along with the system).
* We need to monitor the work of our ci cd mechanisms
* We can learn from those who put data at the input (as part of testing the system) of the queue and compare it with
  what we work out. The same thing with the output.

#### How would you deploy your application in a real world scenario? What kind of testing, deployment stages or quality gates you would build to ensure a safe production deployment?

##### Deploy

The most understandable scheme of deploy for me is my own bash script:

* Script runs regularly (for example, cron)
* This script receives updates from code repository from a separate branch
* Builds the program
* Runs the tests, warm up, cache update
* Sends part of the product load on the new version (it can be only one of servers, or special folder)
* Wait for ? seconds
* If errors > ? or warnings > ?, then send alert (and clean folder) and exit
* Send full of the product load on the new version
* Wait for ? seconds
* If errors > ? or warnings > ?, then send alert (and clean folder) and revert to previous version

The previous version and the new version remain on the servers. This is necessary so that script can quickly swap them.

The script can be called from Gitlab pipeline, or Jenkins or from somewhere else.

We can use Kubernetes to manage the number of replicas of new and old versions.

##### Deployment stages or quality gates

Servers or clusters:

* Test server per developer, or per team, or per feature, or per branch
* Devel or Stage. It is like test, but more stable. The whole system works fine in this cluster.
* Preprod. Server with a partial product load (we can use it for trying new versions)
* Product

New functionality or features can be sent into product through AB testing. We can put in the code who will be affected
by this or that functionality. For example:

```php
if ($clientId % 100 === 0) { // one percent of users will see new feature
    newFeature();
} else {
    oldFeature();
}
```

Mock data for test constantly updated with real data and problem cases. Save old data for backward compatibility
testing.

We can agree in advance on a flag in the data, thanks to which the data will be excluded from the analysis, in order to
check the full stack on the product.

Code culture, code manifest, code review allow you to avoid many problems.

Changes should be minimal. Large changes need to be rolled out in small pieces and not when ready, but as you write
them, as Goldratt teaches :). Merge requests easy to check, design issues are seen earlier, other branches see changes
earlier, other development participants ask questions earlier.

## Known issues and todo

* Large number of todos
* Gracefully app termination. After receiving the termination signal, the application should stop taking new messages
  from the queue, process the already loaded ones, and exit.
* Graceful degradation. Application need to continue to work without unnecessary parts.
* If the message SQS queue crashes or restarts, we will continue working, but what happens if the Kinesis data stream
  restarts?
* Where are tests?
* Application starts with several of errors, waiting for SQS Queue to start
* php/vendor folder. Need to build the vendor folder via composer, not drag it around.
* php not the best option for an implemented application (I want more than one thread or event loop), but it's the most
  comfortable language for me. Because SQS and Kinesis are both new technologies for me, plus there are only 2 days, I
  wanted to minimize uncomfortable tools.