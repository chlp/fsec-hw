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
telemetry-collector    | {"level":"Info","message":"Collect 2 more messages (7 events)"}
telemetry-collector    | {"level":"Info","message":"Collect 3 more messages (8 events)"}
telemetry-collector    | {"level":"Info","message":"Collect 0 more messages (0 events)"}
telemetry-collector    | {"level":"Info","message":"Supervisor next tick. Average count is 10, average size 3078 bytes."}
telemetry-collector    | {"level":"Info","message":"Collect 0 more messages (0 events)"}
telemetry-collector    | {"level":"Info","message":"Collect 0 more messages (0 events)"}
```

## Assignment details

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

### About `time_created`

This is a specific field. Need to know the details of how we can use it. Looks like we can't believe him, but we can use
it for future analysis. We passing it as is.

I have not found timezone inside time_created :(. If it were, we would be able to make assumptions about how fast
messages reach us (about realtime).

And yes, we have different ISO 8601 time format:

```
"time_processed": "2021-03-29T12:06:20+00:00"
"time_created":   "2021-03-29T12:06:09.576033"
```

### About `submission_id`

I decided to leave it, although it was not in the requirements. It looks like it doesn't take up much space, and may be
useful for analysis, but, of course, it is better to know the specifics to make a decision.

## Main functions

* `runs continuously until terminated` - Yes
* `reads "submissions" from submissions SQS queue` - Yes, but `submission` name set via environment
  variable `SUBMISSIONS_QUEUE_NAME`.
* `publishes "events" to events Kinesis stream` - Yes, but `events` name set via environment
  variable `EVENTS_DATA_STREAM_NAME`.

## Requirements

* `each event is published as an individual record to kinesis` - Yes, in the code, this can be found next to the
  lines `telemetryCollector.php`:

```php
            foreach ($telemetryMessageEvents as $event) {
                if (!$dataStreamService->putRecord($event->toDataStreamRecord(), $afterSendingCallback)) {
```

* `each event must have information of the event type ("new_process" or "network_connection")` - Yes, each event has
  field `type`
* `each event must have an unique identifier` - Yes, each event has field `event_id`, but if we receive one event
  several times, it would have different `event_id`. I didn't have time to think about how to make it permanent for one
  message. It seems to be possible to assemble it from the submission_id, the type, and its position in the array.
* `each event must have an identifier of the source device ("device_id")` - Yes, each event has field `device_id` with
  validated data
* `each event must have a timestamp when it was processed (backend side time in UTC)` - Yes, each event has
  field `time_processed`
* `submissions are validated and invalid or broken submissions are dropped` - Yes, and you can find all dropped data if
  turn on debug log `Logger.php`:

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
  - Yes, result application has an entity `DataStreamService` that can respond with callback after it puts data to the
  stream. We have a counter of events for each message `$eventsCountPerMessage`, and we delete a message when all events
  put to data stream.
* `must guarantee ordering of events in the context of a single submission` - It looks like it is. We keep the order of
  the elements in the array and pass it on. Although I am not completely sure that I understood this point completely
  correctly.
* `the number of messages read from SQS with a single request must be configurable` - Yes, environment
  variable `QUEUE_MAX_NUMBER_OF_MESSAGE_PER_REQUEST`.
* `the visibility timeout of read SQS messages must be configurable` - Yes, environment
  variable `QUEUE_VISIBILITY_TIMEOUT_SEC`. Inside code `QueueService.php` `QueueService::changeMessagesVisibility()`,
  but I did not test that visibility timeout parameter of messages is change in fact. And I haven't worked out this
  method well enough. What should I do if I can't change it? Can we find out the current value of this parameter, so as
  not to make unnecessary calls, if it is the same value already.

## Known issues and todo