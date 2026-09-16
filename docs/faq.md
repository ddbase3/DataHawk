# DataHawk Frequently Asked Questions

## What is DataHawk?

DataHawk is a schema-driven query and materialization component for the BASE3 Framework. It accepts structured query definitions, validates and compiles them to SQL, executes them through the BASE3 database abstraction, and returns structured `QueryResult` objects.

DataHawk also provides a materialization subsystem for turning expensive reporting queries into generated database tables that can be refreshed and published as logical reporting tables.

## Is DataHawk a reporting user interface?

No. DataHawk is primarily a query, schema, and materialization layer. It exposes query results and reporting metadata to consumers. It includes administration displays for materialization operations, but it does not define a general report designer or report renderer.

## Which shared contracts does DataHawk implement?

DataHawk uses ResourceFoundation contracts for its main public service boundaries. The central runtime services include:

- `IQuerySchemaProvider`
- `IScopedQuerySchemaProvider`
- `IQueryCompiler`
- `IQueryService`
- `IReportingScopeRegistry`
- `IMaterializationRegistry`
- `IMaterializationRunRepository`
- `IMaterializationManifestProvider`
- `IScopedMaterializationManifestProvider`
- `IMaterializationSchemaProvider`
- `IMaterializationService`
- `ITableNameResolver`

The plugin registers replaceable defaults with `NOOVERWRITE` where appropriate.

## What query format does DataHawk use?

Queries are represented as PHP arrays or equivalent JSON structures. A query has a `type` and type-specific fields.

The structured format is intended to make query construction explicit and machine-readable rather than requiring consumers to concatenate SQL strings themselves.

## Which query types are supported?

The current implementation supports:

- `select`
- `insert`
- `update`
- `delete`
- `create`
- `alter`
- `drop`
- `rename`
- `truncate`
- `transaction`

`transaction` is handled by `DefaultReportQueryService` and executes multiple subqueries within one database transaction.

## Does DataHawk support UNION queries?

Yes. SELECT queries can contain a UNION definition with multiple SELECT subqueries. The validator requires at least two SELECT queries in the UNION block.

## Which expression types can be used in SELECT queries?

The element compiler supports structured expressions including:

- field references with optional aliases
- function calls
- window functions
- operators such as comparisons, boolean expressions, `IN`, `BETWEEN`, and null checks
- `CASE` expressions
- subqueries
- scalar values such as strings, numbers, booleans, and null

The exact SQL generated depends on the expression type and query compiler.

## How are joins determined?

DataHawk builds a join graph from schema metadata and uses `JoinPlanner` to determine required joins for referenced fields and expressions. Join metadata can describe available paths and variants, including required or optional relationships.

This allows consumers to describe the data they need without manually writing every join clause.

## What is the query schema?

The query schema is metadata describing tables, fields, joins, tags, categories, domains, default filters, sensitivity flags, and related reporting information.

The schema does not contain the table rows themselves. It describes how DataHawk can address and relate data sources.

## How are query schemas contributed?

DataHawk discovers `IQuerySchemaDefinitionProvider` implementations and creates scoped query schema providers from them. It also incorporates materialization definition providers into the same technical schema namespace.

The central `IScopedQuerySchemaProvider` can enumerate scopes and resolve schema information for one scope.

## What is a reporting scope?

A reporting scope is a user-facing grouping of technical query schema, materialization, and report scopes. DataHawk discovers `IReportingScopeDefinitionProvider` implementations and exposes the resulting definitions through `IReportingScopeRegistry`.

Reporting scope identifiers must be unique and use stable lowercase technical names containing letters, digits, underscores, or hyphens.

## Can table names be qualified by scope?

Yes. `CompositeQuerySchemaProvider` accepts qualified table identifiers using forms such as `scope:table` and `scope.table`.

If an unqualified table name exists in multiple non-default scopes, resolution fails as ambiguous rather than silently choosing one.

## What happens if no default query schema exists?

`DataHawkQuerySchemaProviderRegistry` requires a query schema scope named `default`. Construction fails if the default scope is not available.

## Can schemas be loaded from JSON files?

Yes. `FileQuerySchemaProvider` can load table schema definitions from JSON files in a directory. The provider is a reusable implementation and reads the files on demand. It does not write or modify the schema files.

The normal central composition uses discoverable definition providers rather than requiring a project to manually assemble the complete provider.

## What is returned by a query?

`IQueryService::executeQuery()` returns `ResourceFoundation\Dto\QueryResult`.

For SELECT queries the result can contain:

- column metadata
- result rows
- compiled SQL in `debugSql`
- sensitivity information
- affected-row metadata where available

For write queries it can additionally contain:

- `affectedRows`
- `insertId` for INSERT operations where the database backend provides it

## Does DataHawk mark sensitive data?

Yes. Schema tables and fields can be marked as `sensitive`. SELECT compilation propagates this information into the compiled field metadata and the returned `QueryResult` contains per-column sensitivity flags and a result-level `sensitive` flag.

This is metadata. DataHawk does not automatically mask, redact, suppress, encrypt, or deny sensitive result values.

## Does the sensitivity flag enforce access control?

No. Sensitivity metadata is descriptive and can be used by consuming components to decide how to display, export, log, or otherwise handle a result.

Authorization must be enforced at the appropriate application or data-access boundary. DataHawk itself does not identify the current end user and does not perform user-specific permission checks in `IQueryService`.

## Is the schema itself an authorization boundary?

No. The schema defines discoverable query metadata and join relationships. It should not be treated as a substitute for authorization.

Consumers must only expose query operations that are appropriate for their trust boundary, especially because DataHawk supports write and schema-changing query types.

## Are write queries supported?

Yes. DataHawk can execute INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, RENAME, and TRUNCATE operations through the same query service.

These operations use the privileges of the configured `IDatabase` connection. DataHawk does not add an end-user authorization layer before executing them.

## Does DELETE require a WHERE condition?

Yes. The current DELETE validator requires a non-empty `where` expression. This prevents an unqualified full-table DELETE through that query type.

This does not make DELETE an authorization-safe operation. The caller must still be trusted and authorized.

## Does UPDATE require a WHERE condition?

No. UPDATE supports an optional WHERE expression. A caller can therefore construct an UPDATE that affects every row in the target table.

Access to write query functionality should be restricted accordingly.

## Are SQL statements parameterized?

The current compiler produces complete SQL strings. Values are rendered into those SQL strings by the compiler rather than being carried as separately bound parameters.

Consumers should therefore treat structured query input as privileged data and should not expose arbitrary query construction to untrusted callers. Parameterized write queries are listed as a future improvement in the project README.

## How are database errors returned?

For ordinary query execution, database failures are represented in an empty `QueryResult` whose `debugSql` can contain the compiled SQL followed by the database error message.

Transaction failures are detected and propagated as exceptions after rollback.

Because SQL text and database error messages can contain sensitive information, `debugSql` should be handled as diagnostic data rather than ordinary user-facing output.

## How do transactions work?

A transaction query contains a non-empty `queries` array. DataHawk:

1. opens a database transaction,
2. executes each subquery in order,
3. rolls back if any subquery fails,
4. commits only when all subqueries succeed.

The returned result is based on the final subquery, with total affected rows accumulated and the latest INSERT id retained where applicable.

## What is materialization?

Materialization converts a query definition into a generated physical database table. It is intended for expensive reporting shapes that should be calculated periodically rather than on every report request.

A full build creates a new generation table, fills it, creates configured indexes, counts the rows, and publishes it in the materialization registry.

## How are materialized tables named?

Generated physical tables use the `base3_mat_` namespace. The physical table name contains a stable prefix and a generated version suffix.

The exact generation suffix contains a timestamp and a short random component.

## How are logical materialized tables resolved?

Consumers address the logical table name. `MaterializationTableNameResolver` asks the materialization registry for the current published generation and substitutes its physical table name during query compilation.

If no current generation exists, the logical name is left unchanged.

## How is a new generation published?

The materialization registry does not rename tables. Publishing marks previous registry rows as non-current and inserts a new current generation row.

The active generation is therefore a registry pointer rather than a physical table rename.

## Which technical materialization tables does DataHawk create?

DataHawk lazily creates:

- `base3_mat_registry`, containing published generation metadata
- `base3_mat_run`, containing materialization run history

Generated report data is stored separately in `base3_mat_*` generation tables.

## What information is stored in the materialization registry?

Registry rows contain technical metadata such as:

- schema name
- logical table name
- physical table name
- generation identifier
- schema and query hashes
- row count
- status
- current-generation flag
- publication and creation timestamps
- optional JSON metadata

The generated data rows themselves are not stored in the registry table. They are stored in the physical materialization table.

## What information is stored in materialization run history?

`base3_mat_run` stores operational information such as:

- manifest identifier
- schema and logical table
- generated physical table and generation
- build mode
- status
- message
- row count
- start and finish timestamps
- optional JSON metadata

A failed run message can include an exception or database error message and should therefore be treated as diagnostic information.

## How many materialization generations are kept?

The default is two physical generations unless the manifest specifies another positive `keepGenerations` value.

After a successful full build, older physical generation tables outside the retention set are dropped.

## Is materialization run history cleaned up?

Yes, when the `MaterializationRefreshJob` is active and its cleanup is enabled. The database registry supports history maintenance that:

- removes non-current registry rows whose physical tables no longer exist
- keeps a configurable number of completed runs per manifest

The job defaults to a cleanup interval of 86,400 seconds and a retention of 100 completed run rows per manifest unless configuration or runtime state overrides those values.

Rows still marked as `running` are not deleted by the history cleanup.

## What happens to an older run still marked as running?

When a new run for the same manifest starts, existing open runs for that manifest, schema, and logical table are marked as failed with the message that they were superseded by a newer run.

## Which materialization build modes exist?

The service exposes `refresh`, `full`, and `incremental` behavior.

Full materialization is implemented. Incremental materialization currently returns a result stating that incremental materialization is not implemented yet.

`refresh` chooses the manifest's configured refresh mode.

## Can a materialization use raw SQL?

Yes. A materialization manifest can use `raw_select` or `raw-sql-select` as its source query type.

Raw SELECT mode is intended for trusted, versioned materialization definitions where a reporting shape cannot reasonably be represented by the structured query model. It is not intended for arbitrary end-user SQL.

Raw SELECT definitions can reference current materialization generations through `{{table:...}}` placeholders.

## What scheduling policies are supported for materializations?

The refresh planner supports:

- `interval`
- `daily_after`
- `always`
- `manual`

The planner uses runtime state to determine when a manifest last succeeded and whether it is due again.

## How are materialization dependencies handled?

A manifest can declare `dependsOn` entries. `dependencyRefresh` controls how dependencies are treated:

- `current`, use the currently published generation without automatically rebuilding it
- `missing`, build a dependency when no current generation exists
- `due`, build it if missing or due by its own schedule
- `cascade`, always rebuild it first

Dependency ordering is resolved before execution.

## Where is materialization scheduler state stored?

The planner stores operational state in `IStateStore` under keys beginning with `datahawk.materialization.`.

Typical values include:

- last attempt timestamp
- last success timestamp and date
- last status
- last message
- last row count

The worker also uses `datahawk.job.materializationrefresh.*` state for one-shot controls and maintenance information.

## Does materialization scheduler state expire automatically?

The DataHawk code shown here writes scheduler values without a TTL. They are updated in place as later runs occur. The active state store implementation determines persistence behavior.

## How is the materialization refresh job enabled?

`MaterializationRefreshJob` is a normal BASE3 job. It reads its active flag and priority from the `job` configuration group.

The job itself has no timing policy. It can be called frequently while each manifest independently decides whether it is due.

## What can be done through the materialization administration displays?

The materialization displays provide operational views for:

- overview and due manifests
- manifest definitions
- registry generations
- recent and historical runs
- generated physical tables

They also support manual refresh of one manifest, refresh of due manifests, and forced refresh of all enabled manifests within a reporting scope.

They are operational displays, not manifest editors.

## Do the materialization displays implement their own authorization or CSRF layer?

The DataHawk display classes shown in this component do not perform a user or permission check themselves and the JSON actions do not add a DataHawk-specific CSRF token.

They must therefore be mounted behind the host application's appropriate authentication, authorization, routing, and request-protection boundary. Manual refresh operations can create and drop materialization generation tables and should be treated as administrative actions.

## Does DataHawk expose schema information through an output endpoint?

Yes. The `datahawkschema` output can return table schema metadata or lists of domains, categories, and tags as JSON.

The output class itself does not perform a user-specific permission check. Deployments should expose it only where schema metadata is appropriate for the intended audience.

## Does DataHawk support microservice operation?

DataHawk includes `DataHawkMicroservice` and `DataHawkSchemaMicroservice` adapters. They can delegate query-service or schema-provider calls either to a local implementation or to an `IMicroserviceConnector`.

The connector determines whether a call stays in-process or crosses a network or process boundary.

## Does DataHawk store ordinary query results?

The normal query service returns results to its caller and does not persist SELECT results itself.

Materialization is the explicit exception: it intentionally copies selected source data into generated physical tables for later reporting use.

## Does DataHawk write application logs?

The current DataHawk source does not inject or call the BASE3 `ILogger` service directly. Errors can still surface through returned diagnostic fields, worker result strings, materialization run history, administration JSON responses, or surrounding infrastructure logs.

## Does DataHawk export files?

No. DataHawk returns `QueryResult` objects. Export formatting and file generation belong outside the DataHawk query layer.

## Where should I look for materialization-specific documentation?

The component contains additional documentation under `docs/`:

- `materialization.md`
- `materialization-manifests.md`
- `materialization-scheduling.md`
- `materialization-ui.md`

These documents describe the materialization runtime, manifest format, scheduler, and administration displays in more detail.
