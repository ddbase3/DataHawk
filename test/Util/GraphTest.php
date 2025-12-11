<?php declare(strict_types=1);

namespace DataHawk\Test\Util;

use PHPUnit\Framework\TestCase;
use DataHawk\Util\Graph;

class GraphTest extends TestCase {

        public function testAddNodeAndHasNodeAndGetNodes(): void {
                $g = new Graph();

                $this->assertFalse($g->hasNode('A'));
                $g->addNode('A');

                $this->assertTrue($g->hasNode('A'));
                $this->assertSame(['A'], $g->getNodes());
        }

        public function testAddEdgeCreatesBothNodesAndEdgeIsAccessible(): void {
                $g = new Graph();

                $g->addEdge('A', 'B', 'rel');

                $this->assertTrue($g->hasNode('A'));
                $this->assertTrue($g->hasNode('B'));

                $edgesA = $g->getEdges('A');
                $this->assertCount(1, $edgesA);
                $this->assertSame('B', $edgesA[0]['to']);
                $this->assertSame('rel', $edgesA[0]['label']);
                $this->assertSame([], $edgesA[0]['meta']);

                $this->assertSame([], $g->getEdges('B'), 'B has no outgoing edges');
        }

        public function testGetAllEdgesFlattensAdjacency(): void {
                $g = new Graph();
                $g->addEdge('A', 'B', 'ab');
                $g->addEdge('A', 'C', 'ac', ['x' => 1]);
                $g->addEdge('B', 'C', 'bc');

                $all = $g->getAllEdges();

                $this->assertCount(3, $all);

                $this->assertSame(
                        [
                                ['from' => 'A', 'to' => 'B', 'label' => 'ab', 'meta' => []],
                                ['from' => 'A', 'to' => 'C', 'label' => 'ac', 'meta' => ['x' => 1]],
                                ['from' => 'B', 'to' => 'C', 'label' => 'bc', 'meta' => []],
                        ],
                        $all
                );
        }

        public function testFindAllPathsThrowsWhenStartNodeMissing(): void {
                $g = new Graph();
                $g->addNode('B');

                $this->expectException(\RuntimeException::class);
                $this->expectExceptionMessage("Start node 'A' does not exist.");
                $g->findAllPaths('A', 'B');
        }

        public function testFindAllPathsThrowsWhenEndNodeMissing(): void {
                $g = new Graph();
                $g->addNode('A');

                $this->expectException(\RuntimeException::class);
                $this->expectExceptionMessage("End node 'B' does not exist.");
                $g->findAllPaths('A', 'B');
        }

        public function testFindAllPathsReturnsEmptyPathWhenStartEqualsEnd(): void {
                $g = new Graph();
                $g->addNode('A');

                $paths = $g->findAllPaths('A', 'A');

                $this->assertSame([[]], $paths);
        }

        public function testFindAllPathsFindsMultiplePaths(): void {
                $g = new Graph();

                // A -> B -> D
                // A -> C -> D
                $g->addEdge('A', 'B', 'ab');
                $g->addEdge('B', 'D', 'bd');
                $g->addEdge('A', 'C', 'ac');
                $g->addEdge('C', 'D', 'cd');

                $paths = $g->findAllPaths('A', 'D');

                $this->assertCount(2, $paths);

                $expected1 = [
                        ['from' => 'A', 'to' => 'B', 'label' => 'ab', 'meta' => []],
                        ['from' => 'B', 'to' => 'D', 'label' => 'bd', 'meta' => []],
                ];
                $expected2 = [
                        ['from' => 'A', 'to' => 'C', 'label' => 'ac', 'meta' => []],
                        ['from' => 'C', 'to' => 'D', 'label' => 'cd', 'meta' => []],
                ];

                // Reihenfolge ist hier deterministisch (Insert-Reihenfolge), aber wir prüfen robust:
                $this->assertTrue(
                        $this->pathsContain($paths, $expected1),
                        'Expected path A->B->D not found'
                );
                $this->assertTrue(
                        $this->pathsContain($paths, $expected2),
                        'Expected path A->C->D not found'
                );
        }

        public function testFindAllPathsPreventsCycles(): void {
                $g = new Graph();

                // Cycle: A -> B -> A
                // Exit:  B -> D
                $g->addEdge('A', 'B', 'ab');
                $g->addEdge('B', 'A', 'ba');
                $g->addEdge('B', 'D', 'bd');

                $paths = $g->findAllPaths('A', 'D');

                $this->assertCount(1, $paths);
                $this->assertSame(
                        [
                                ['from' => 'A', 'to' => 'B', 'label' => 'ab', 'meta' => []],
                                ['from' => 'B', 'to' => 'D', 'label' => 'bd', 'meta' => []],
                        ],
                        $paths[0]
                );
        }

        public function testGetEdgesToFiltersOnlyMatchingDestination(): void {
                $g = new Graph();
                $g->addEdge('A', 'B', 'ab');
                $g->addEdge('A', 'B', 'ab2', ['default' => true]);
                $g->addEdge('A', 'C', 'ac');

                $edgesToB = $g->getEdgesTo('A', 'B');

                $this->assertCount(2, $edgesToB);
                $this->assertSame('B', $edgesToB[0]['to']);
                $this->assertSame('B', $edgesToB[1]['to']);
        }

        public function testGetDefaultEdgeReturnsNullIfNoEdges(): void {
                $g = new Graph();
                $g->addNode('A');
                $g->addNode('B');

                $this->assertNull($g->getDefaultEdge('A', 'B'));
        }

        public function testGetDefaultEdgePrefersMetaDefaultTrue(): void {
                $g = new Graph();
                $g->addEdge('A', 'B', 'first');
                $g->addEdge('A', 'B', 'second', ['default' => true]);
                $g->addEdge('A', 'B', 'third', ['default' => false]);

                $edge = $g->getDefaultEdge('A', 'B');

                $this->assertNotNull($edge);
                $this->assertSame('B', $edge['to']);
                $this->assertSame('second', $edge['label']);
                $this->assertSame(['default' => true], $edge['meta']);
        }

        public function testGetDefaultEdgeFallsBackToFirstEdgeWhenNoDefaultMarked(): void {
                $g = new Graph();
                $g->addEdge('A', 'B', 'first');
                $g->addEdge('A', 'B', 'second', ['default' => false]);

                $edge = $g->getDefaultEdge('A', 'B');

                $this->assertNotNull($edge);
                $this->assertSame('first', $edge['label']);
        }

        /**
         * Helper to check whether $paths contains a path identical to $expected.
         *
         * @param array $paths
         * @param array $expected
         * @return bool
         */
        private function pathsContain(array $paths, array $expected): bool {
                foreach ($paths as $p) {
                        if ($p === $expected) {
                                return true;
                        }
                }
                return false;
        }
}
