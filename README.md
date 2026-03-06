# DataHawk Plugin for BASE3 Framework

The **DataHawk** plugin extends the BASE3 framework with a schema-driven query engine for reporting and data access. Queries are defined as structured JSON arrays, compiled to SQL, and executed through the BASE3 `IDatabase` abstraction.

---

## Features

### Schema-driven query system

* JSON query format compiled by `MysqlReportQueryCompiler`
* Supported element/expression types via `ElementCompiler`:

  * `fld` (field reference, optional `tablealias`)
  * `fn` (functions, incl. special handling for `GROUP_CONCAT`, plus `CAST`, `CONVERT`)
  * `windowfn` (window functions with `OVER`, optional `partition_by` / `order_by`)
  * `op` (operations like `=`, `AND`, `OR`, `IN`, `NOT IN`, `BETWEEN`, `IS NULL`, `IS NOT NULL`, …)
  * `case` (CASE WHEN … THEN … ELSE … END)
  * `subquery` (nested queries)
  * Scalars are allowed directly (`"text"`, `42`, `true`, `null`)
* Automatic join planning (schema join graph + `variant` hints like `required`/`optional`)
* Aliasing support for stable column names in results

### Sensitivity and data protection

* Tables/fields can be marked as `sensitive` in schema metadata
* Compilation marks sensitivity in compiled statements
* `QueryResult` includes per-column sensitivity flags (plus result-level `sensitive`)

### Schema management

* Schema is provided by an `IQuerySchemaProvider` (default: JSON-based provider)
* Table metadata includes joins, tags, categories, domains, default filters

### Query execution

* Execution via `IQueryService` (default: `DataHawk\Service\DefaultReportQueryService`)
* Uses BASE3 `IDatabase` for execution
* Integrity validation for select results (unless wildcard mode is enabled)

### Write queries: affected rows and insert id

`QueryResult` now also provides write metadata:

* `affectedRows` — affected rows for INSERT/UPDATE/DELETE (if known)
* `insertId` — last insert id for `type: "insert"` (backend-dependent)

### Transactions

DataHawk supports atomic multi-step operations using a **transaction query type**:

* `type: "transaction"` executes multiple subqueries within a DB transaction.
* On any failure, the transaction is rolled back.
* Errors are propagated as **exceptions**.

---

## QueryResult

`ResourceFoundation\Dto\QueryResult` provides:

* `columns`: column metadata (name/type/field/alias/table/sensitive)
* `rows`: result rows
* `debugSql`: optional debug SQL
* `sensitive`: whether the result contains sensitive data
* `affectedRows`: affected row count for write queries (nullable)
* `insertId`: insert id for insert queries (nullable)

---

## Query syntax

### Base structure

```json
{ "type": "...", ... }
```

### SELECT

```json
{
  "type": "select",
  "fields": [
    {
      "element": { "type": "fld", "table": "git_repository", "field": "name" },
      "alias": "repository_name"
    },
    {
      "element": {
        "type": "fn",
        "function": "GROUP_CONCAT",
        "params": [
          { "type": "fld", "table": "git_topic", "field": "topic" },
          ", "
        ]
      },
      "alias": "topics"
    }
  ],
  "from": "git_repository",
  "where": {
    "type": "op",
    "operator": "=",
    "params": [
      { "type": "fld", "table": "git_repository", "field": "id" },
      42
    ]
  },
  "order_by": [
    { "expression": { "type": "fld", "table": "git_repository", "field": "name" }, "direction": "ASC" }
  ],
  "limit": 50
}
```

### INSERT

The current insert compiler supports:

* `INSERT INTO ... VALUES (...)` via `values`
* optional explicit `columns`
* `INSERT INTO ... SELECT ...` via `from`
* optional `on_duplicate` (MySQL `ON DUPLICATE KEY UPDATE`)

**INSERT ... VALUES**

```json
{
  "type": "insert",
  "table": "git_repository",
  "values": [
    { "name": "TestRepo", "description": "demo" }
  ]
}
```

**INSERT ... VALUES with explicit columns and expressions**

```json
{
  "type": "insert",
  "table": "base3system_sysentry",
  "columns": ["type_id", "uuid"],
  "values": [
    {
      "type_id": 12,
      "uuid": { "type": "fn", "function": "UNHEX", "params": ["5c9fb997cd1f470dda5ed6da129b83d7"] }
    }
  ]
}
```

**INSERT ... SELECT**

```json
{
  "type": "insert",
  "table": "target_table",
  "columns": ["col_a", "col_b"],
  "from": {
    "type": "select",
    "fields": [
      { "element": { "type": "fld", "table": "source_table", "field": "a" }, "alias": "col_a" },
      { "element": { "type": "fld", "table": "source_table", "field": "b" }, "alias": "col_b" }
    ],
    "from": "source_table"
  }
}
```

**ON DUPLICATE KEY UPDATE**

```json
{
  "type": "insert",
  "table": "git_repository",
  "values": [
    { "id": 42, "name": "TestRepo" }
  ],
  "on_duplicate": {
    "name": "TestRepo"
  }
}
```

### DELETE

```json
{
  "type": "delete",
  "table": "git_repository",
  "where": {
    "type": "op",
    "operator": "LIKE",
    "params": [
      { "type": "fld", "table": "git_repository", "field": "name" },
      "%Archived%"
    ]
  },
  "limit": 1
}
```

### TRANSACTION

```json
{
  "type": "transaction",
  "queries": [
    { "type": "insert", "table": "a", "values": [ { "x": 1 } ] },
    { "type": "insert", "table": "b", "values": [ { "y": 2 } ] }
  ]
}
```

Notes:

* Subqueries are executed in order.
* On any error, the transaction is rolled back.
* Errors are propagated as exceptions.

---

## Exporters

| Format     | Class                     | Use Case                                  |
| ---------- | ------------------------- | ----------------------------------------- |
| CSV        | `CsvReportExporter`       | Data analysis, Excel import, simple tools |
| JSON       | `JsonReportExporter`      | APIs, integration, serialization          |
| HTML       | `HtmlReportExporter`      | Web preview, printing, PDF generation     |
| HTML-Mail  | `HtmlTableReportExporter` | Email delivery, HTML templates            |
| Excel-HTML | `ExcelHtmlReportExporter` | Excel download without external libraries |

---

## Roadmap / ideas

* Export filtering/masking based on sensitivity
* UI-driven schema visualizations (graph-based joins)
* Caching and pagination strategies
* Parameterized statements for write queries (reducing literal quoting)

---

## License

GPL 3.0. See BASE3 license for framework details.

