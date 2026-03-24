<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of DataHawk for BASE3 Framework.
 *
 * DataHawk extends the BASE3 framework with a schema-driven query
 * engine for reporting and data access. Queries are defined as
 * structured JSON arrays, compiled into SQL, and executed through
 * the BASE3 IDatabase abstraction.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/datahawk
 * https://github.com/ddbase3/DataHawk
 **********************************************************************/

namespace DataHawk\Api;

use Base3\Api\IBase;
use ResourceFoundation\Dto\QueryResult;

interface IReportExporter extends IBase {

	/**
	 * Sets a query definition to be executed internally.
	 * The result will be stored for later export.
	 *
	 * @param array $queryJson Structured query array
	 * @return $this
	 */
	public function setExportQuery(array $queryJson): self;

	/**
	 * Sets the result to be exported directly.
	 * Useful if the query has already been executed externally.
	 *
	 * @param QueryResult $result
	 * @return $this
	 */
	public function setResult(QueryResult $result): self;

	/**
	 * Gets the internal result.
	 *
	 * @return ?QueryResult
	 */
	public function getResult(): ?QueryResult;

	/**
	 * Renders the export content as string.
	 *
	 * @return string
	 */
	public function toString(): string;

	/**
	 * Writes the export content to a file.
	 *
	 * @param string $filePath Absolute or relative file path
	 * @return $this
	 *
	 * @throws \RuntimeException If writing fails
	 */
	public function toFile(string $filePath): self;

	/**
	 * Returns the sql statement for debugging.
	 *
	 * @retrn string
	 */
	public function toSql(): string;

	/**
	 * Returns the MIME type of the exported content.
	 *
	 * @return string
	 */
	public function getMimeType(): string;

	/**
	 * Returns the recommended file extension (without dot).
	 *
	 * @return string
	 */
	public function getFileExtension(): string;
}

