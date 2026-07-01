# DataHawk Materialization UI

DataHawk provides small administration displays for materialization operations. The displays are intended for operational visibility first, not as a full manifest editor.

## Displays

The reporting administration section can expose these displays:

| Display | Purpose |
| --- | --- |
| `datahawkmaterializationoverviewdisplay` | High-level overview, due manifests, recent runs, quick actions. |
| `datahawkmaterializationmanifestdisplay` | Manifest list with schedule, dependency mode, current generation and manual refresh. |
| `datahawkmaterializationregistrydisplay` | Registry history from `base3_mat_registry`. |
| `datahawkmaterializationrundisplay` | Recent build runs from `base3_mat_run`. |
| `datahawkmaterializationtabledisplay` | Generated physical tables matching `base3_mat_%`. |

All displays use the same MVC template and expose a JSON endpoint through the normal display `out=json` mechanism.

## Actions

The UI currently supports three operational actions:

| Action | Effect |
| --- | --- |
| Reload | Reloads manifests, registry, runs and table state. |
| Refresh due | Uses `MaterializationRefreshPlanner` to build only currently due manifests. |
| Refresh all | Forces all enabled manifests through the planner. |
| Refresh on a manifest row | Refreshes only the selected manifest. |

Manual refresh updates the materialization scheduler state after a successful run so the always-run job does not immediately repeat the same manifest.

## Scope

The UI is intentionally not a manifest editor yet. Manifests remain JSON files because this keeps source schema, materialized schema and Vizion report configuration reviewable and versionable.

The next UI step can add a read-only manifest detail view that shows query JSON, columns and indexes. A later editor should validate manifest JSON before writing files.

## Grid implementation

Materialization tables in the UI use the shared ClientStack ModularGrid assets:

- `plugin/ClientStack/assets/modulargrid/styles/modulargrid.css`
- `plugin/ClientStack/assets/modulargrid/index.js`

The cards and action toolbar remain part of the DataHawk template. The row areas below them are rendered as ModularGrid instances, using the display JSON endpoint with `mode=grid` and a `gridView` value such as `manifests`, `registry`, `runs`, `tables`, `overview_due`, `overview_manifests` or `overview_runs`.

The first version intentionally keeps the grid feature set small:

- server-side paging over the already loaded materialization rows,
- search,
- header sorting,
- column visibility,
- reset,
- per-grid session storage,
- inline refresh buttons for manifest rows.

Filtering and row detail panels can be added later without changing the underlying display endpoints.
