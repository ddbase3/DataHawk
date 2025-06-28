# DataHawk

DataHawk is a BASE3 Framework plugin that allows embedding dynamic reports as page modules, enabling easy display of interactive tables and charts for data visualization and analysis.

## Exporter

| Format     | Class                     | Use Case                                   |
| ---------- | ------------------------- | ------------------------------------------ |
| CSV        | `CsvReportExporter`       | Data analysis, Excel import, simple tools  |
| JSON       | `JsonReportExporter`      | APIs, integration, serialization           |
| HTML       | `HtmlReportExporter`      | Web preview, printing, PDF generation      |
| HTML-Mail  | `HtmlTableReportExporter` | Email delivery, HTML templates             |
| Excel-HTML | `ExcelHtmlReportExporter` | Excel download without external libraries  |

