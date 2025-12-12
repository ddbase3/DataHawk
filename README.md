# DataHawk Plugin for BASE3 Framework

The **DataHawk** plugin extends the [BASE3](https://github.com/ddbase3/Base3Framework) framework with a comprehensive, modular reporting and query engine. It enables dynamic report generation, flexible data access, and UI-driven analysis — all while enforcing schema-based security and advanced metadata.

---

## Features

### ✨ Schema-Driven Query System

* JSON-based query format with support for:

  * `select`, `from`, `where`, `group_by`, `having`, `order_by`, `limit`, `offset`
  * complex expressions via `fn` (functions), `op` (operations), `subquery`
  * table joins resolved automatically via graph traversal
* Supports table aliases, optional/required join variants, and join path resolution
* Custom metadata per field: `description`, `nullable`, `tags`, `alias`, `sensitive`

### 🔒 Sensitivity and Data Protection

* Fields and tables can be marked as `sensitive`
* Query compilation detects sensitivity and marks `QueryStatement::$sensitive`
* `QueryResult` includes per-column sensitivity flags
* Future-proof for export filtering, masking, and audit policies

### ⚖️ Schema Management

* Schema provided by `IQuerySchemaProvider` implementation (default: `DefaultReportSchemaProvider`)
* Loaded from JSON files via configurable data directory
* Table metadata includes joins, tags, categories, domains, default filters

### 📈 Query Execution

* Queries compiled by `ReportQueryCompiler` into raw SQL
* Execution via `IDatabase` abstraction (supports multiQuery, scalarQuery, etc.)
* Field aliasing supported
* Sensitivity computed at compile time
* Result delivered as `QueryResult` with:

  * Columns (name, type, field, alias, table, sensitive)
  * Rows (array of values)
  * Debug SQL string

### ⚙️ Extensibility

* Plugin-based design under `DataHawk` namespace
* Designed to integrate with BASE3 UI features: column selector, filters, sorting
* Extendable with new schema providers, query compilers, exporters

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

## 📘 Query Syntax (CRUD in Structured JSON Form)

This system uses a declarative, JSON-based query language to describe database operations. All CRUD operations (`select`, `insert`, `update`, `delete`) follow a uniform, extensible format. This enables simple as well as complex queries with full control over fields, conditions, and data flow.

### 🔑 Base Structure

```json
{
  "type": "select" | "insert" | "update" | "delete",
  ...
}
```

---

### 🔍 `SELECT`

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
  "where": { ... },
  "group_by": [ ... ],
  "order_by": [ ... ]
}
```

**Fields:**

* `fields[]`: List of fields with `element` and `alias`.
* `from`: Starting table of the query.
* `where`, `group_by`, `order_by`: Optional, similar to SQL.

---

### ✏️ `UPDATE`

```json
{
  "type": "update",
  "table": "git_repository",
  "fields": [
    {
      "element": {
        "type": "fld",
        "table": "git_branch",
        "field": "message"
      },
      "alias": "last_commit_message"
    },
    {
      "element": "New description",
      "alias": "description"
    }
  ],
  "from": "git_branch",
  "where": {
    "type": "op",
    "operator": "=",
    "params": [
      { "type": "fld", "table": "git_repository", "field": "id" },
      42
    ]
  }
}
```

**Notes:**

* `fields[]`: Specifies which columns (`alias`) in the target table will be updated.
* `element` can be:

  * a field (`type: "fld"`),
  * a function expression (`type: "fn"`),
  * a scalar value (direct).
* `from`: Optional, e.g. for `UPDATE ... FROM` constructs.

---

### ➕ `INSERT`

```json
{
  "type": "insert",
  "table": "git_repository",
  "fields": [
    {
      "element": "TestRepo",
      "alias": "name"
    },
    {
      "element": {
        "type": "fn",
        "function": "NOW"
      },
      "alias": "created_at"
    }
  ]
}
```

**Notes:**

* Fields define the target columns (`alias`) and their corresponding values (`element`).
* Order is arbitrary.
* `element` may be static or dynamic.

---

### 🗑️ `DELETE`

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
  }
}
```

**Notes:**

* `table`: Target of the delete operation.
* `where`: Optional – without condition, all rows will be deleted (use with caution!).

---

### 🔬 `element` – Detailed Structure

```json
"element": {
  "type": "fld" | "fn" | "op",
  ...
}
```

**Types:**

* `"fld"` – Access to a field: `table`, `field`, optional `tablealias`.
* `"fn"` – Function call: `function`, `params[]`.
* `"op"` – Logical or arithmetic operation: `operator`, `params[]`.
* **Scalars** like `42`, `"Test"`, `true`, `null` are allowed directly.

---

### 🧠 Examples for `element`

| Type      | Example (JSON)                                          |
| --------- | ------------------------------------------------------- |
| Field     | `{ "type": "fld", "table": "users", "field": "email" }` |
| Function  | `{ "type": "fn", "function": "NOW" }`                   |
| Operation | `{ "type": "op", "operator": "+", "params": [1, 2] }`   |
| Scalar    | `"Hello"`                                               |

---

### 🔄 Advantages of the Structure

* 🔁 **Uniform**: All CRUD operations use the same `fields[]` structure.
* 🔄 **Flexible**: `element` can contain arbitrary expressions or values.
* 🔌 **Extensible**: Later extensions like `upsert`, `merge`, `bulk`, etc. are supported.
* 🔐 **Clear Separation**: Target (`alias`) and source (`element`) are clearly separated.

---

### 📎 Further Notes

* JOINs are generated automatically if table fields are logically connected.
* Alias conflicts and ambiguities are avoided through clear `table`/`alias` specification.
* Extensions like `limit`, `offset`, `having` can be easily added.

---

## Roadmap / Future Ideas

* Export filtering/masking based on sensitivity
* Integration with BASE3 permission system (role-based filtering)
* UI-driven schema visualizations (graph-based joins)
* Custom report definitions with user-specific layouts
* Caching, indexing, and pagination strategies
* Automatic column type inference + formatting options

---

## License

GPL 3.0. See BASE3 license for framework details.

