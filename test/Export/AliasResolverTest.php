<?php declare(strict_types=1);

namespace DataHawk\Test\Compiler;

use PHPUnit\Framework\TestCase;
use DataHawk\Compiler\AliasResolver;

class AliasResolverTest extends TestCase {

        public function testInitialStateIsEmpty(): void {
                $resolver = new AliasResolver();

                $this->assertSame([], $resolver->getAliasUsage());
                $this->assertSame([], $resolver->getRegisteredAliases());
                $this->assertNull($resolver->getFirstUsedTable());
        }

        public function testResetClearsAllState(): void {
                $resolver = new AliasResolver();

                $resolver->registerAlias('u', 'users');

                $query = [
                        'fields' => [
                                ['type' => 'fld', 'table' => 'users', 'tablealias' => 'u'],
                        ],
                        'where' => [
                                'type' => 'fld',
                                'table' => 'orders',
                                'tablealias' => 'o',
                        ],
                ];

                $resolver->scan($query);

                $this->assertNotSame([], $resolver->getAliasUsage());
                $this->assertNotNull($resolver->getFirstUsedTable());

                $resolver->reset();

                $this->assertSame([], $resolver->getAliasUsage());
                $this->assertSame([], $resolver->getRegisteredAliases());
                $this->assertNull($resolver->getFirstUsedTable());
        }

        public function testScanCollectsAliasUsageAndFirstUsedTable(): void {
                $resolver = new AliasResolver();

                $query = [
                        'fields' => [
                                // erster Feldzugriff -> bestimmt firstTableUsed
                                ['type' => 'fld', 'table' => 'users', 'tablealias' => 'u'],
                                // ohne tablealias -> alias == table
                                ['type' => 'fld', 'table' => 'orders'],
                        ],
                        'group_by' => [
                                // zweites Alias für dieselbe Tabelle
                                ['type' => 'fld', 'table' => 'users', 'tablealias' => 'u2'],
                        ],
                        'order_by' => [
                                ['type' => 'fld', 'table' => 'products', 'tablealias' => 'p'],
                        ],
                        'where' => [
                                'type'  => 'op',
                                'op'    => '=',
                                'left'  => ['type' => 'fld', 'table' => 'users', 'tablealias' => 'u'],
                                'right' => ['type' => 'const', 'value' => 1],
                        ],
                        'having' => [
                                'type' => 'fld',
                                'table' => 'orders', // nutzt wieder "orders"
                        ],
                ];

                $resolver->scan($query);

                $usage = $resolver->getAliasUsage();

                // users
                $this->assertArrayHasKey('users', $usage);
                $this->assertArrayHasKey('u', $usage['users']);
                $this->assertArrayHasKey('u2', $usage['users']);

                // orders (ohne explizites tablealias -> alias == 'orders')
                $this->assertArrayHasKey('orders', $usage);
                $this->assertArrayHasKey('orders', $usage['orders']);

                // products
                $this->assertArrayHasKey('products', $usage);
                $this->assertArrayHasKey('p', $usage['products']);

                // erstes verwendetes Table ist das aus dem ersten Feldknoten
                $this->assertSame('users', $resolver->getFirstUsedTable());
        }

        public function testScanIgnoresNonFieldNodesAndNodesWithoutTable(): void {
                $resolver = new AliasResolver();

                $query = [
                        'fields' => [
                                ['type' => 'const', 'value' => 123],       // kein fld
                                ['type' => 'fld', 'tablealias' => 'x'],    // kein table
                        ],
                        'where' => [
                                'type' => 'op',
                                'op' => '=',
                                'left' => ['type' => 'const', 'value' => 'x'], // kein fld
                        ],
                ];

                $resolver->scan($query);

                $this->assertSame([], $resolver->getAliasUsage());
                $this->assertNull($resolver->getFirstUsedTable());
        }

        public function testScanHandlesEmptyOrMissingSections(): void {
                $resolver = new AliasResolver();

                $resolver->scan([
                        // keine relevanten Keys gesetzt
                ]);

                $this->assertSame([], $resolver->getAliasUsage());
                $this->assertNull($resolver->getFirstUsedTable());

                $resolver->scan([
                        'fields' => [],
                        'group_by' => [],
                        'order_by' => [],
                ]);

                $this->assertSame([], $resolver->getAliasUsage());
                $this->assertNull($resolver->getFirstUsedTable());
        }

        public function testRegisterAliasAndGetters(): void {
                $resolver = new AliasResolver();

                $resolver->registerAlias('u', 'users');
                $resolver->registerAlias('o', 'orders');

                // getTableForAlias
                $this->assertSame('users', $resolver->getTableForAlias('u'));
                $this->assertSame('orders', $resolver->getTableForAlias('o'));
                $this->assertNull($resolver->getTableForAlias('x'));

                // getAliasForTable (liefert das erste passende Alias)
                $this->assertSame('u', $resolver->getAliasForTable('users'));
                $this->assertSame('o', $resolver->getAliasForTable('orders'));
                $this->assertNull($resolver->getAliasForTable('products'));

                // getRegisteredAliases
                $this->assertSame(
                        ['u' => 'users', 'o' => 'orders'],
                        $resolver->getRegisteredAliases()
                );

                // registerAlias trägt auch in aliasUsage ein
                $usage = $resolver->getAliasUsage();
                $this->assertArrayHasKey('users', $usage);
                $this->assertArrayHasKey('u', $usage['users']);
                $this->assertArrayHasKey('orders', $usage);
                $this->assertArrayHasKey('o', $usage['orders']);
        }

        public function testScanDoesNotOverrideRegisteredAliasesButAddsUsage(): void {
                $resolver = new AliasResolver();

                $resolver->registerAlias('u', 'users');

                $query = [
                        'fields' => [
                                ['type' => 'fld', 'table' => 'users', 'tablealias' => 'u'],
                        ],
                ];

                $resolver->scan($query);

                // scan() ruft reset(), daher bleiben registrierte Aliase NICHT erhalten
                $this->assertSame([], $resolver->getRegisteredAliases());

                // aliasUsage wird jedoch basierend auf scan neu gefüllt
                $usage = $resolver->getAliasUsage();
                $this->assertArrayHasKey('users', $usage);
                $this->assertArrayHasKey('u', $usage['users']);
        }
}
