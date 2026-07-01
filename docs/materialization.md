# DataHawk Materialization

DataHawk materialization turns JSON-defined DataHawk queries into generated physical database tables.
Reports can then read from compact, indexed, report-ready tables instead of repeatedly executing expensive live joins.

## Runtime model

A materialization is defined by a JSON manifest:

1. The manifest defines a logical target table, columns, indexes, and a source query.
2. DataHawk builds a new physical generation table named `base3_mat_<logical_table>_<generation>`.
3. DataHawk fills the generation table with `INSERT ... SELECT`.
4. DataHawk publishes the new generation in `base3_mat_registry`.
5. Query compilation resolves the logical table name to the current physical table through `ITableNameResolver`.
6. Old generations are removed according to `keepGenerations`.

There is no table rename during publish. The active generation changes by registry pointer.

## Technical tables

DataHawk creates two internal tables lazily:

- `base3_mat_registry`: current and previous published generations.
- `base3_mat_run`: build/run history, status, messages, row counts, and metadata.

The generated materialized tables are regenerable artifacts. They are not versioned migrations.

## Logical vs physical names

Reports and DataHawk queries use logical names:

```json
{
	"schema": "ilias_materialized",
	"table": "course_report_rows"
}
```

The resolver maps them at SQL compile time:

```text
ilias_materialized.course_report_rows
	-> base3_mat_course_report_rows_20260701150140_5ff2
```

## Project responsibilities

DataHawk provides the generic materialization engine, registry, resolver, job, and displays.
Project plugins provide project-specific source schemas, materialization manifests, and Vizion report configs.

For example, `Base3IliasLab` provides:

- `local/DataHawk/source/*.json`
- `local/DataHawk/materialized/*.json`
- `local/Vizion/*.json`
- project wiring for schema providers and table resolver
