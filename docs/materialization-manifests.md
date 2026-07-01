# DataHawk Materialization Manifests

A materialization manifest is a JSON file that describes one generated reporting table.

## Minimal structure

```json
{
	"id": "course_report_rows",
	"enabled": true,
	"priority": 80,
	"sourceSchema": "ilias_materialized",
	"targetSchema": "ilias_materialized",
	"target": {
		"logicalTable": "course_report_rows",
		"physicalPrefix": "base3_mat_course_report_rows",
		"publishStrategy": "registry",
		"keepGenerations": 2
	},
	"refresh": {
		"mode": "full",
		"schedule": {
			"policy": "interval",
			"seconds": 300
		},
		"full": {
			"buildStrategy": "new_table"
		}
	},
	"dependsOn": [
		"course_membership",
		"course_learning_progress",
		"last_course_access"
	],
	"dependencyRefresh": "current",
	"query": {},
	"columns": [],
	"indexes": []
}
```

## Top-level fields

| Field | Purpose |
| --- | --- |
| `id` | Stable manifest identifier. Used by jobs, state keys, and run history. |
| `enabled` | Enables or disables automatic refresh. |
| `priority` | Ordering hint. Lower numbers are planned earlier. Dependency ordering still wins. |
| `sourceSchema` | Default schema used for the source query. |
| `targetSchema` | Logical schema exposed to DataHawk/Vizion. |
| `target.logicalTable` | Logical table name used by reports. |
| `target.physicalPrefix` | Prefix for generated physical tables. Must stay in the `base3_mat_*` namespace. |
| `target.keepGenerations` | Number of physical generations kept after a successful build. |
| `refresh.mode` | `full` or `incremental`. Incremental mode is reserved for later implementation. |
| `refresh.schedule` | Per-manifest timing policy. |
| `dependsOn` | Required logical materialization dependencies. |
| `dependencyRefresh` | Controls whether dependencies are rebuilt automatically. |
| `query` | DataHawk select query used as source for `INSERT ... SELECT`. |
| `columns` | Target table column definitions. |
| `indexes` | Target indexes created after data load. |

## Dependency refresh modes

`dependsOn` describes dependency order and availability. It does not always mean cascade rebuild.

| Value | Behavior |
| --- | --- |
| `current` | Use already-published dependencies. Do not auto-build them. |
| `missing` | Build dependencies only if no current generation exists. This is the default. |
| `due` | Build dependencies if they are missing or their own schedule is due. |
| `cascade` | Always build dependencies before this manifest. |

Use `current` for report-level tables that can be rebuilt from the currently published foundation tables.
Use `missing` for safe initial setup without unnecessary repeated dependency builds.

## Indexes

Indexes can be expressed as objects:

```json
{
	"name": "idx_course_report_rows_usr",
	"columns": ["usr_id"]
}
```

or, for simple non-unique indexes, as column arrays.

## Raw SELECT materializations

Most materializations should use structured DataHawk query JSON. For complex reporting shapes that are not expressible as a simple graph query, a manifest may define a raw SELECT query:

```json
{
	"query": {
		"type": "raw_select",
		"sql": "SELECT ..."
	}
}
```

The materialization service still creates the target table from `columns`, creates indexes from `indexes`, counts rows and publishes the generation through the registry. Only the source SELECT is raw SQL.

Raw SELECT SQL may reference currently published materialized tables with placeholders:

```sql
{{table:ilias_materialized.course_report_rows}}
```

At build time the placeholder is replaced by the current physical `base3_mat_*` table from the materialization registry. If no current generation is published, the build fails.

Use raw SELECTs sparingly. They are intended for report shapes such as recursive OrgUnit rollups where the source calculation is naturally SQL-specific.
