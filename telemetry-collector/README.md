# F-Secure homework

This file contains a description of the completed homework.

## Environment

I saved the original task environment and added one new service:

* SQS queue `submissions`: incoming submissions can be read from this queue
* Kinesis stream `events`: outgoing events are published to this stream
* Result php application `telemetry-collector`:  that receives messages from SQS queue, parse and put events into Kinesis data stream.

## Setup

### Launching

To launch the services, use `docker-compose up -d`. This will launch localstack, sensor-fleet and telemetry-collector to run on the background. Previously lets look at environment variables.

### Environment variables

Inside `docker-compose.yml` you can find several environment variables for service `telemetry-collector`. Main of them:

* `QUEUE_WAIT_TIME_SEC` - use for long polling mechanism
* `QUEUE_RECEIPTS_TO_DELETE_INTERVAL_SEC` - time to fill the SQS queue items to delete before deleting
* `QUEUE_MAX_RECEIPTS_TO_DELETE_AT_ONCE` - max count of items to delete in a batch
* `DATA_STREAM_FLUSH_INTERVAL_SEC` - time to fill the Kinesis data stream buffer before sending
* `DATA_STREAM_MAX_BUFFER_SIZE` - max count of items to delete in a batch
* `QUEUE_VISIBILITY_TIMEOUT_SEC` - SQS visibility timeout must be more than `QUEUE_RECEIPTS_TO_DELETE_INTERVAL_SEC` and `DATA_STREAM_FLUSH_INTERVAL_SEC`

### Checking the work

To validate that telemetry-collector is working, run the following commands and expect similar responses as in the examples below:

```console
$ docker-compose logs telemetry-collector
telemetry-collector    | {"level":"Info","message":"Collect 2 more messages (7 events)"}
telemetry-collector    | {"level":"Info","message":"Collect 3 more messages (8 events)"}
telemetry-collector    | {"level":"Info","message":"Collect 0 more messages (0 events)"}
telemetry-collector    | {"level":"Info","message":"Supervisor next tick. Average count is 10, average size 3078 bytes."}
telemetry-collector    | {"level":"Info","message":"Collect 0 more messages (0 events)"}
telemetry-collector    | {"level":"Info","message":"Collect 0 more messages (0 events)"}
```

