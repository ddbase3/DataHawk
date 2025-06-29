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
* Query compilation detects sensitivity and marks `SqlQuery::$sensitive`
* `QueryResult` includes per-column sensitivity flags
* Future-proof for export filtering, masking, and audit policies

### ⚖️ Schema Management

* Schema provided by `IReportSchemaProvider` implementation (default: `DefaultReportSchemaProvider`)
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

## Roadmap / Future Ideas

* Export filtering/masking based on sensitivity
* Integration with BASE3 permission system (role-based filtering)
* UI-driven schema visualizations (graph-based joins)
* Custom report definitions with user-specific layouts
* Caching, indexing, and pagination strategies
* Automatic column type inference + formatting options

---

## License

LGPL. See BASE3 license for framework details.

