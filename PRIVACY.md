# DataHawk Privacy and Data Processing

This document describes the technical data-processing behavior of the DataHawk component itself. It is not a legal privacy notice for a complete application or deployment.

DataHawk is a generic BASE3 query, schema, and materialization component. The categories of personal data it can process depend entirely on the database schemas, queries, materialization definitions, and callers configured by the surrounding application.

## 1. Component boundary

DataHawk provides four main technical capabilities that are relevant to privacy and data protection:

1. schema-driven query compilation,
2. database query execution,
3. reporting and schema metadata discovery,
4. materialization of query results into generated database tables.

DataHawk does not define an application-specific user model, legal purpose, data subject population, or retention policy for source data. Those decisions belong to the application that exposes DataHawk and to the owners of the underlying data sources.

## 2. Data that DataHawk can process

Depending on the caller and configured schemas, DataHawk can process:

- structured query definitions,
- literal values included in query filters or write operations,
- table and field metadata,
- join definitions,
- tags, categories, domains, labels, and descriptions,
- sensitivity metadata,
- database rows returned by SELECT queries,
- values inserted or updated through write queries,
- compiled SQL text,
- database error messages,
- materialization manifests and query definitions,
- copied rows stored in materialized tables,
- materialization generation metadata,
- run status, messages, timestamps, and row counts,
- materialization scheduler state.

Any of these structures can contain personal data if the configured application uses DataHawk for personal-data-bearing sources.

## 3. Ordinary query execution

`DefaultReportQueryService` compiles a structured query and executes it through the configured BASE3 `IDatabase` service.

For SELECT queries, the service returns a `QueryResult` containing the rows and column metadata. DataHawk does not persist those ordinary SELECT result rows itself.

For write queries, DataHawk can directly change the connected database. Supported write or schema-changing operations include:

- INSERT,
- UPDATE,
- DELETE,
- CREATE TABLE,
- ALTER TABLE,
- DROP TABLE,
- RENAME TABLE,
- TRUNCATE TABLE.

These operations run with the privileges of the configured database connection.

## 4. No user-specific authorization inside the query service

The DataHawk query service does not receive the current user and does not perform user-specific authorization checks before executing a query.

This is an important architectural boundary. DataHawk must not be treated as the authorization layer for application data. A caller that exposes DataHawk query functionality must ensure that the requested operation is allowed before it reaches `IQueryService`.

This is especially important for write and schema-changing query types.

## 5. Schema metadata is not a security policy

The query schema describes available tables, fields, joins, tags, categories, domains, default filters, and sensitivity metadata.

Schema inclusion does not grant access, and schema omission should not be used as the sole protection mechanism. Authorization belongs at the relevant service, application, or data-access boundary.

## 6. Sensitivity metadata

Tables and fields can be marked as `sensitive` in schema metadata. SELECT compilation propagates those flags into column metadata and `QueryResult` exposes a result-level sensitivity flag.

The current DataHawk implementation does not automatically:

- mask sensitive values,
- redact sensitive columns,
- deny access to sensitive fields,
- encrypt result values,
- suppress sensitive fields in exports,
- apply different retention because a field is sensitive.

The flags are metadata for consumers. Consumers are responsible for enforcing any display, export, logging, authorization, or handling rules associated with them.

## 7. Compiled SQL and diagnostic data

`QueryResult::debugSql` can contain the complete compiled SQL statement.

The current compilers render literal values into SQL strings. This means `debugSql` can contain values originating from filters, INSERT rows, UPDATE values, or other query input. Those values can include personal or confidential data.

On database failure, the query service can append the database error message to `debugSql`. Database errors can reveal table names, column names, values, constraints, or other operational details.

`debugSql` must therefore be treated as potentially sensitive diagnostic data. Applications should not expose or persist it broadly without a specific operational reason.

## 8. Transactions

A transaction query executes multiple DataHawk subqueries within one database transaction.

If a subquery fails, DataHawk rolls back the transaction and propagates the failure. The same data-protection considerations as for individual queries apply to every subquery in the transaction.

## 9. Query values and SQL construction

The current DataHawk compiler produces SQL strings rather than separately bound parameter arrays for its write-query path. Identifier quoting and literal escaping are performed by compiler code.

This design does not make arbitrary query definitions suitable for untrusted input. The surrounding application should treat query construction as a privileged operation and validate business-level intent before execution.

## 10. Raw SELECT materializations

Materialization manifests can use a raw SELECT source query.

Raw SELECT mode bypasses the normal structured expression model for the source SELECT and should only be used with trusted, controlled materialization definitions. It is intended for versioned technical reporting definitions, not arbitrary end-user SQL.

A raw SELECT can contain table names, conditions, literals, and other SQL content that may itself be confidential.

## 11. Materialized data

Materialization intentionally persists query results.

A full build creates a new physical table in the `base3_mat_*` namespace and fills it with data selected from source tables. A materialized table can therefore contain the same personal or confidential information as the source query, or a derived form of that information.

Materialized tables are not merely metadata or caches from a privacy perspective. They are additional persisted copies of data and must be included in:

- access-control design,
- backup scope,
- retention planning,
- deletion workflows,
- data-subject request analysis where applicable,
- database security and encryption planning.

## 12. Materialization generation retention

After a successful full build, DataHawk keeps a configurable number of physical generations.

The default is two generations. A positive `keepGenerations` value in the manifest can change that number.

Older generation tables outside the retention set are dropped after a successful build. A failed newly created generation is also dropped when cleanup succeeds.

Because generation cleanup runs as part of successful materialization processing, inactive or failing materializations can retain previously generated data for longer than expected. Operational retention should therefore be monitored rather than assumed.

## 13. Materialization registry

DataHawk lazily creates `base3_mat_registry`.

The table stores technical information about generated tables, including:

- schema name,
- logical table name,
- physical table name,
- generation identifier,
- schema hash,
- query hash,
- row count,
- publication status,
- current-generation flag,
- publication and creation timestamps,
- optional JSON metadata.

The registry does not store the materialized result rows themselves. However, its names and metadata can reveal information about internal reporting structures and should normally be treated as administrative data.

## 14. Materialization run history

DataHawk lazily creates `base3_mat_run`.

A run row can contain:

- manifest id,
- schema and logical table names,
- physical table and generation names,
- build mode,
- status,
- message,
- row count,
- start and finish timestamps,
- optional JSON metadata.

Failure messages are populated from exceptions and can therefore include database error details or compiled SQL. Run history must be treated as potentially sensitive diagnostic data even though it is primarily operational metadata.

## 15. Materialization history cleanup

`DatabaseMaterializationRegistry::cleanupHistory()` can:

- remove non-current registry rows whose physical table no longer exists,
- remove older completed run rows beyond a configured count per manifest.

The standard materialization refresh job enables cleanup by default when the job itself is active. Its defaults are:

- cleanup interval: 86,400 seconds,
- retained completed runs per manifest: 100.

These values can be overridden through job configuration or runtime state.

Rows still marked `running` are excluded from ordinary run-history cleanup. Starting a newer run for the same manifest marks older open runs as failed.

If the refresh job is disabled, its automatic maintenance path does not run.

## 16. Scheduler state

Materialization scheduling uses `IStateStore`.

Manifest state is written under keys beginning with:

```text
datahawk.materialization.
```

Typical values include:

- `last_attempt_at`,
- `last_success_at`,
- `last_success_date`,
- `last_status`,
- `last_message`,
- `last_row_count`.

Job control and maintenance state use keys beginning with:

```text
datahawk.job.materializationrefresh.
```

The DataHawk code writes these values without a TTL. Most values are overwritten as new runs occur rather than accumulated as a separate history. `last_message` can contain failure text and should be considered diagnostic data.

The selected `IStateStore` backend determines where this state is physically stored.

## 17. Local schema and manifest files

DataHawk contains reusable providers that can read schema definitions and materialization manifests from local JSON files.

These providers read files but do not write them. The files can nevertheless contain:

- internal table and field names,
- descriptions and labels,
- query definitions,
- literal query values,
- raw SQL,
- materialization scheduling information.

Access to those files should follow the same repository and deployment protections as other technical configuration.

The standard central query-schema composition currently discovers ResourceFoundation definition providers. File-backed providers remain available for explicit use.

## 18. Reporting and schema metadata endpoints

The `datahawkschema` output can expose schema metadata, domains, categories, and tags as JSON.

The output class itself does not perform an end-user authorization check. Schema metadata does not normally contain result rows, but it can reveal internal data structures and sensitivity classifications.

Deployments should therefore decide whether this endpoint is public, authenticated, administrative, or unavailable based on their own security model.

## 19. Materialization administration displays

DataHawk provides administration displays for materialization overview, manifests, registry entries, run history, and generated tables.

The JSON actions support manual materialization refresh. A refresh can create tables, populate them with source data, publish a new generation, create indexes, and remove old generations.

The DataHawk display classes shown here do not perform their own user or permission check. Their JSON requests also do not add a DataHawk-specific CSRF token.

These displays must be protected by the surrounding application's authentication, authorization, routing, and request-protection mechanisms. They should be treated as administrative functionality.

## 20. Browser-side state in the materialization UI

The materialization UI uses a shared grid component with per-grid session storage for presentation state such as grid preferences.

DataHawk itself does not use that browser storage as a persistence mechanism for report rows or materialized data. The exact browser-side keys and behavior belong to the grid implementation used by the host application.

## 21. External network processing

The normal DataHawk query service and materialization service operate against locally provided BASE3 services and do not select an external cloud provider on their own.

DataHawk also includes microservice adapter classes. `DataHawkMicroservice` and `DataHawkSchemaMicroservice` can delegate to an `IMicroserviceConnector`.

If a deployment wires a remote connector, the following data can cross the configured transport boundary depending on the invoked method:

- structured query definitions,
- query results,
- schema metadata,
- table metadata,
- domains, categories, and tags.

The connector implementation and deployment configuration determine endpoint, authentication, encryption, logging, retention, and geographical processing location. DataHawk itself does not define those values.

## 22. Logging

The current DataHawk source does not directly inject or use the BASE3 `ILogger` service.

Data can nevertheless enter logs outside DataHawk through:

- database driver logging,
- HTTP or reverse-proxy logs,
- worker output capture,
- exception handling,
- application error handling,
- microservice connector logs,
- administration diagnostics.

In particular, compiled SQL, query literals, database error messages, materialization failure messages, and manifest identifiers should be considered when configuring surrounding logging.

## 23. Backups and replicas

Materialized tables, `base3_mat_registry`, and `base3_mat_run` live in the configured database and can therefore be included in database backups, replicas, snapshots, and disaster-recovery copies.

Dropping an old materialization generation from the live database does not automatically delete copies already present in backups. Backup retention must be handled separately by the deployment.

The same applies to any persistent `IStateStore` backend used for scheduler state.

## 24. Data deletion and source changes

DataHawk does not automatically understand when a person, source record, or business object has been deleted for privacy reasons.

For ordinary live queries, later reads naturally reflect the current source database state.

For materialized data, an older generation can still contain data that has since changed or been removed in the source. The application should ensure that refresh frequency and generation retention are appropriate for its deletion and correction requirements.

Where immediate propagation is required, the relevant materializations must be rebuilt and old generations removed accordingly.

## 25. Data minimization

Data minimization for DataHawk is primarily controlled by query and materialization design.

Recommended practices include:

- select only fields required for the reporting purpose,
- avoid copying unnecessary personal data into materialized tables,
- use aggregate or derived values where raw identifiers are not required,
- mark sensitive schema fields consistently,
- avoid putting secrets or unnecessary personal values into query literals,
- avoid exposing `debugSql` to normal users,
- keep only the materialization generations and run history needed operationally,
- restrict schema and materialization administration endpoints.

## 26. Installation checklist

Before exposing DataHawk in a production environment, the deployment should document at least:

- which database DataHawk can access,
- which query types each caller may invoke,
- where authorization is enforced before `IQueryService`,
- which schemas contain personal or sensitive data,
- how sensitivity flags are used by consumers,
- whether `debugSql` is exposed or logged,
- whether microservice connectors are local or remote,
- which materializations copy personal data,
- materialization refresh frequencies,
- `keepGenerations` values,
- run-history retention and cleanup configuration,
- the physical `IStateStore` backend,
- access controls around schema and materialization displays,
- backup and replica retention for generated tables,
- procedures for rebuilding materializations after source deletion or correction.

## 27. Summary

DataHawk is a technical data-processing layer. It can read, transform, write, and materialize database data, including personal data when the surrounding application configures such sources.

Its central privacy-relevant boundaries are:

- the configured database connection,
- the caller's authorization before query execution,
- sensitivity metadata that is descriptive rather than automatically enforced,
- diagnostic SQL and database error information,
- generated materialization tables,
- operational registry, run-history, and scheduler state,
- optional microservice transport when configured.

A production deployment should therefore evaluate DataHawk as part of the complete data flow in which it is used, while keeping authorization and retention decisions at the application boundaries that own them.
