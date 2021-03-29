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

"cmdl":"calculator.exe","user":"admin"} {"type":"network_connection","device_id":"18453134-f872-4336-a895-09451c2d70e7"
,"submission_id":"00e1456e-c080-48af-8a6e-1f7a6f037a59","time_processed":1617012950,"time_created":1617012949,"
source_ip":"192.168.0.1","destination_ip":"142.250.74.110","destination_port":8922}

```yaml
{
  "type": "<new_process/network_connection>",       # event type name: "new_process" or "network_connection" (string)
  "device_id": "<uuid>",                            # unique identifier of the device (string)
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

## Known issues and todo