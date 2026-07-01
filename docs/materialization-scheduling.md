# DataHawk Materialization Scheduling

`MaterializationRefreshJob` is intentionally lightweight. It implements `IJob` and has no worker timing policy.
The worker can call it frequently; each manifest decides independently whether it is due.

## Job configuration

Enable the job through the normal job configuration group:

```ini
[job]
datahawkmaterializationrefreshjob.active = 1
datahawkmaterializationrefreshjob.priority = 1
```

Optional filters:

```ini
[job]
datahawkmaterializationrefreshjob.manifest = "course_report_rows"
datahawkmaterializationrefreshjob.manifests = "course_learning_progress,last_course_access,course_report_rows"
datahawkmaterializationrefreshjob.mode = "refresh"
```

`mode` supports:

- `refresh`: use each manifest's `refresh.mode`.
- `full`: force full build for selected due manifests.
- `incremental`: run incremental mode for selected due manifests.

A one-shot forced run can be requested through state/config with:

```ini
[job]
datahawkmaterializationrefreshjob.force = 1
```

When `force` is read from `IStateStore`, the job clears the flag after one worker execution.

## Manifest schedules

Schedules live inside each manifest:

```json
"refresh": {
	"mode": "full",
	"schedule": {
		"policy": "interval",
		"seconds": 300
	}
}
```

Supported policies:

| Policy | Behavior |
| --- | --- |
| `interval` | Due when `last_success_at + seconds <= now`. |
| `daily_after` | Due once per day after `time`, for example `02:00`. |
| `always` | Due on every job run. Use only for very small materializations or during development. |
| `manual` | Never due automatically. Use manual/forced refresh. |

## Runtime state

The planner stores runtime state under:

```text
datahawk.materialization.<manifest_id>.*
```

Typical keys:

- `last_attempt_at`
- `last_success_at`
- `last_success_date`
- `last_status`
- `last_message`
- `last_row_count`

This state controls due checks. Report data itself stays in generated `base3_mat_*` tables.

## Suggested cadence

For the current ILIAS reporting stack:

| Manifest | Suggested schedule |
| --- | --- |
| `course_catalog` | `interval`, 21600 seconds |
| `course_membership` | `interval`, 600 seconds |
| `course_learning_progress` | `interval`, 300 seconds |
| `last_course_access` | `interval`, 300 seconds |
| `course_report_rows` | `interval`, 300 seconds, `dependencyRefresh=current` |

This keeps volatile data fresh while avoiding repeated rebuilds of stable tables.
