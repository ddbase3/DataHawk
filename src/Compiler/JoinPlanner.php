<?php declare(strict_types=1);

namespace DataHawk\Compiler;

use DataHawk\Exception\QueryValidationException;
use DataHawk\Util\Graph;

class JoinPlanner
{
    private Graph $joinGraph;

    public function __construct(
        private AliasResolver $aliasResolver,
        private ElementCompiler $elementCompiler,
        Graph $joinGraph
    ) {
        $this->joinGraph = $joinGraph;
    }

    /**
     * Analysiert alle Elemente und extrahiert benötigte Tabellen (mit Variant)
     */
    public function collectJoinDependencies(array $nodes): array
    {
        $tables = [];

        foreach ($nodes as $node) {
            if (!is_array($node)) continue;

            if (($node['type'] ?? null) === 'fld') {
                $table = $node['table'];
                $alias = $node['tablealias'] ?? $table;
                $this->aliasResolver->registerAlias($alias, $table);
                $tables[$table] = $node['variant'] ?? ($tables[$table] ?? null);
	    }

            if (isset($node['element'])) {
                $tables = array_merge($tables, $this->collectJoinDependencies([$node['element']]));
            }

            if (isset($node['params']) && is_array($node['params'])) {
                $tables = array_merge($tables, $this->collectJoinDependencies($node['params']));
            }

            if (isset($node['query']) && is_array($node['query'])) {
                $tables = array_merge($tables, $this->collectJoinDependencies([$node['query']]));
            }
        }

        return $tables;
    }

    /**
     * Baut JOIN-SQL anhand der gescannten aliasUsage und resolveden Pfade
     */
    public function compileJoins(string $from, array $joinRequests): string
    {
        $sql = '';
        $visited = [$from];
        $aliasUsage = $this->aliasResolver->getAliasUsage();

        foreach ($joinRequests as $target => $variant) {
            if ($target === $from) continue;

            $paths = $this->joinGraph->findAllPaths($from, $target);
            if (empty($paths)) {
                throw new QueryValidationException("No join path from '$from' to '$target'");
            }

            // Fallback: bevorzugt "default"-Pfad
            $path = null;
            foreach ($paths as $candidate) {
                if (!empty($candidate[0]['meta']['default'])) {
                    $path = $candidate;
                    break;
                }
            }
            if (!$path) $path = $paths[0];

            $lastStep = $path[array_key_last($path)];

            foreach ($path as $step) {
                $table = $step['to'];
                $aliases = array_keys($aliasUsage[$table] ?? [$table => true]);

                foreach ($aliases as $alias) {
                    $alreadyJoined = "{$table}::{$alias}";
                    static $joined = [];

                    if (isset($joined[$alreadyJoined])) continue;
                    $joined[$alreadyJoined] = true;

                    $this->aliasResolver->registerAlias($alias, $table);

                    $isDefault = $step['meta']['default'] ?? false;
                    $stepType = strtoupper($step['meta']['type'] ?? 'INNER');
                    $isLastStep = ($step === $lastStep);

                    $useLeft = ($isLastStep && $variant && strtoupper($variant) === 'OPTIONAL')
                            || ($isLastStep && !$variant && $isDefault && $stepType === 'LEFT');

                    $joinType = $useLeft ? 'LEFT JOIN' : 'INNER JOIN';

                    $onParts = [];
                    foreach ($step['meta']['on'] as $left => $right) {
                        $onParts[] = $this->quoteJoinField($left) . ' = ' . $this->quoteJoinField($right, $alias);
                    }

                    $sql .= " $joinType " . $this->elementCompiler->quoteIdentifier($table);
                    if ($alias !== $table) {
                        $sql .= " AS " . $this->elementCompiler->quoteIdentifier($alias);
                    }
                    $sql .= " ON " . implode(" AND ", $onParts);
                }
            }
        }

        return $sql;
    }

    private function quoteJoinField(string $str, ?string $aliasOverride = null): string
    {
        if (str_contains($str, '.')) {
            [$table, $field] = explode('.', $str, 2);
            $alias = $aliasOverride ?? $this->aliasResolver->getAliasForTable($table) ?? $table;
            return $this->elementCompiler->quoteIdentifier($alias) . '.' . $this->elementCompiler->quoteIdentifier($field);
        }
        return $this->elementCompiler->quoteIdentifier($aliasOverride ?? $str);
    }
}

