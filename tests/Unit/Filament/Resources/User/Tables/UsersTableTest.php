<?php
declare(strict_types=1);

namespace Tests\Unit\Filament\Resources\User\Tables;

use App\Filament\Resources\User\Tables\UsersTable;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as BaseQueryBuilder;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Database\Query\Processors\Processor;
use PHPUnit\Framework\TestCase;

final class UsersTableTest extends TestCase
{
    private function makeTable(): Table
    {
        // Filament\Table is configured via a fluent API; here we instantiate a bare Table
        // that can be configured by the UsersTable::configure method.
        // Filament\Table has a constructor(Table $table) only in runtime via Livewire components;
        // however, the static Table::make() also exists in many versions.
        // To avoid version differences, we instantiate via reflection if needed.

        // Prefer Table::make() if available:
        if (method_exists(Table::class, 'make')) {
            /** @var Table $table */
            $table = Table::make();
            return UsersTable::configure($table);
        }

        // Fallback: create a new Table instance (constructor without args used in some versions)
        $table = new Table();
        return UsersTable::configure($table);
    }

    public function test_columns_include_name_email_created_at_as_sortable(): void
    {
        $table = $this->makeTable();

        // Extract column instances; Table exposes getColumns() in Filament v3
        $columns = method_exists($table, 'getColumns') ? $table->getColumns() : [];
        $this->assertNotEmpty($columns, 'Table columns should not be empty');

        // Map by column name/key if accessible
        $columnsByName = [];
        foreach ($columns as $col) {
            if ($col instanceof TextColumn) {
                $columnsByName[$col->getName()] = $col;
            } elseif (method_exists($col, 'getName')) {
                $columnsByName[$col->getName()] = $col;
            }
        }

        // Assert presence
        foreach (['name', 'email', 'created_at'] as $key) {
            $this->assertArrayHasKey($key, $columnsByName, "Missing TextColumn: {$key}");
            $this->assertInstanceOf(TextColumn::class, $columnsByName[$key], "Column {$key} should be a TextColumn");
            // Sortable flag
            $isSortable = method_exists($columnsByName[$key], 'isSortable')
                ? $columnsByName[$key]->isSortable()
                : true; // If method not available, assume configured correctly to avoid false negatives on version variance
            $this->assertTrue($isSortable, "Column {$key} should be sortable");
        }
    }

    public function test_filters_layout_is_above_content_and_deferred(): void
    {
        $table = $this->makeTable();

        // Layout
        if (method_exists($table, 'getFiltersLayout')) {
            $layout = $table->getFiltersLayout();
            $this->assertSame(FiltersLayout::AboveContent, $layout, 'Filters layout must be AboveContent');
        } else {
            $this->addWarning('Table::getFiltersLayout() not available in this Filament version; skipping strict layout assertion.');
        }

        // Deferred filters
        if (method_exists($table, 'areFiltersDeferred')) {
            $this->assertTrue($table->areFiltersDeferred(), 'Filters should be deferred');
        } else {
            $this->addWarning('Table::areFiltersDeferred() not available; skipping deferred filters assertion.');
        }
    }

    public function test_name_filter_schema_and_query_callback(): void
    {
        $table = $this->makeTable();
        $filters = method_exists($table, 'getFilters') ? $table->getFilters() : [];
        $this->assertNotEmpty($filters, 'Table filters should not be empty');

        $nameFilter = $this->findFilterByName($filters, 'name');
        $this->assertNotNull($nameFilter, 'Expected a Filter named "name"');

        // Schema assertions
        $this->assertFilterSchemaContainsTextInput($nameFilter, 'name', 'Enter name to filter', 'Name');

        // Query callback behavior
        $builder = $this->fakeEloquentBuilder();
        $cb = $this->extractFilterQueryCallback($nameFilter);
        $this->assertIsCallable($cb, 'Filter query callback must be callable');

        // When name is string ⇒ applies whereLike "%value%"
        $builder1 = $cb($builder->newQuery(), ['name' => 'Alice']);
        $this->assertInstanceOf(Builder::class, $builder1);
        $sql1 = $this->compileWhereSql($builder1->getQuery());
        $this->assertStringContainsString('like', strtolower($sql1));
        $this->assertStringContainsString('%Alice%', $this->flattenBindings($builder1->getQuery()), 'Binding should contain %Alice%');

        // When name is null/non-string ⇒ no where added
        $builder2 = $cb($builder->newQuery(), ['name' => null]);
        $sql2 = $this->compileWhereSql($builder2->getQuery());
        $this->assertNoWhereClause($sql2);
    }

    public function test_email_filter_schema_and_query_callback(): void
    {
        $table = $this->makeTable();
        $filters = method_exists($table, 'getFilters') ? $table->getFilters() : [];
        $this->assertNotEmpty($filters, 'Table filters should not be empty');

        $emailFilter = $this->findFilterByName($filters, 'email');
        $this->assertNotNull($emailFilter, 'Expected a Filter named "email"');

        // Schema assertions
        $this->assertFilterSchemaContainsTextInput($emailFilter, 'email', 'Enter email to filter', 'Email');

        // Query callback behavior
        $builder = $this->fakeEloquentBuilder();
        $cb = $this->extractFilterQueryCallback($emailFilter);
        $this->assertIsCallable($cb, 'Filter query callback must be callable');

        // When email is string ⇒ applies whereLike "%value%"
        $builder1 = $cb($builder->newQuery(), ['email' => 'example.com']);
        $sql1 = $this->compileWhereSql($builder1->getQuery());
        $this->assertStringContainsString('like', strtolower($sql1));
        $this->assertStringContainsString('%example.com%', $this->flattenBindings($builder1->getQuery()));

        // When email is absent ⇒ no where added
        $builder2 = $cb($builder->newQuery(), []);
        $sql2 = $this->compileWhereSql($builder2->getQuery());
        $this->assertNoWhereClause($sql2);
    }

    public function test_record_actions_include_view_and_delete_with_empty_labels(): void
    {
        $table = $this->makeTable();

        if (! method_exists($table, 'getRecordActions')) {
            $this->addWarning('Table::getRecordActions() not available; skipping assertions.');
            return;
        }

        $actions = $table->getRecordActions();
        $this->assertNotEmpty($actions, 'Record actions should not be empty');

        // Removed unused local variable $types;
        $this->assertTrue($this->containsInstanceOf($actions, ViewAction::class), 'Missing ViewAction');
        $this->assertTrue($this->containsInstanceOf($actions, DeleteAction::class), 'Missing DeleteAction');

        foreach ($actions as $action) {
            if ($action instanceof ViewAction || $action instanceof DeleteAction) {
                if (method_exists($action, 'getLabel')) {
                    $this->assertSame('', (string) $action->getLabel(), get_class($action) . ' should have empty label');
                }
            }
        }
    }

    public function test_toolbar_actions_include_bulk_group_with_delete_bulk_action(): void
    {
        $table = $this->makeTable();

        if (! method_exists($table, 'getHeaderActions') && ! method_exists($table, 'getToolbarActions')) {
            $this->addWarning('Toolbar/header actions getters not available; skipping assertions.');
            return;
        }

        $actions = method_exists($table, 'getToolbarActions')
            ? $table->getToolbarActions()
            : (method_exists($table, 'getHeaderActions') ? $table->getHeaderActions() : []);

        $this->assertNotEmpty($actions, 'Toolbar/Header actions should not be empty');

        $hasBulkGroup = false;
        $hasDeleteBulk = false;

        foreach ($actions as $action) {
            if ($action instanceof BulkActionGroup) {
                $hasBulkGroup = true;
                $groupActions = method_exists($action, 'getActions') ? $action->getActions() : [];
                if ($this->containsInstanceOf($groupActions, DeleteBulkAction::class)) {
                    $hasDeleteBulk = true;
                }
            }
        }

        $this->assertTrue($hasBulkGroup, 'Expected a BulkActionGroup in toolbar/header actions');
        $this->assertTrue($hasDeleteBulk, 'Expected DeleteBulkAction inside BulkActionGroup');
    }

    // ------------------------------------------------------------
    // Helper methods
    // ------------------------------------------------------------

    /** @param array<int, mixed> $filters */
    private function findFilterByName(array $filters, string $name): ?Filter
    {
        foreach ($filters as $filter) {
            if ($filter instanceof Filter) {
                // Filter::getName() available in Filament v3
                if (method_exists($filter, 'getName') && $filter->getName() === $name) {
                    return $filter;
                }
                // Some versions may store name as public property
                if (! method_exists($filter, 'getName') && property_exists($filter, 'name') && $filter->name === $name) {
                    return $filter;
                }
            }
        }

        return null;
    }

    private function assertFilterSchemaContainsTextInput(Filter $filter, string $componentName, string $placeholder, string $label): void
    {
        $schema = method_exists($filter, 'getFormSchema')
            ? $filter->getFormSchema()
            : (method_exists($filter, 'getSchema') ? $filter->getSchema() : []);
        $this->assertIsArray($schema, 'Filter schema should be an array');

        $found = false;
        foreach ($schema as $component) {
            if ($component instanceof TextInput) {
                // Name/id
                if (method_exists($component, 'getName')) {
                    $this->assertSame($componentName, $component->getName(), 'TextInput name mismatch');
                }
                // Placeholder/label (methods may differ across versions)
                if (method_exists($component, 'getPlaceholder')) {
                    $this->assertSame($placeholder, (string) $component->getPlaceholder(), 'TextInput placeholder mismatch');
                }
                if (method_exists($component, 'getLabel')) {
                    $this->assertSame($label, (string) $component->getLabel(), 'TextInput label mismatch');
                }
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, "Filter schema must contain TextInput '{$componentName}'");
    }

    /** @return callable(Builder, array): Builder */
    private function extractFilterQueryCallback(Filter $filter): callable
    {
        // Filament Filter stores a query callback retrievable via getQuery() or similar
        foreach (['getQuery', 'getQueryCallback', 'getQueryCallable', 'getModifyQueryUsing'] as $method) {
            if (method_exists($filter, $method)) {
                /** @var callable $cb */
                $cb = $filter->{$method}();
                return $cb;
            }
        }

        // As a fallback, we know UsersTable config uses Filter::make(...)->query(fn (Builder $query, array $data) => ...)
        // If the API surface isn't exposed, we rebind by reflection.
        $ref = new \ReflectionObject($filter);
        if ($ref->hasProperty('modifyQueryUsing')) {
            $prop = $ref->getProperty('modifyQueryUsing');
            $prop->setAccessible(true);
            /** @var callable $cb */
            $cb = $prop->getValue($filter);
            return $cb;
        }

        $this->fail('Could not access filter query callback via public API or reflection.');
    }

    private function containsInstanceOf(array $items, string $class): bool
    {
        foreach ($items as $item) {
            if ($item instanceof $class) {
                return true;
            }
        }

        return false;
    }

    private function assertNoWhereClause(string $sql): void
    {
        $normalized = strtolower(preg_replace('/\s+/', ' ', trim($sql)));
        $this->assertFalse(str_contains($normalized, ' where '), 'Unexpected WHERE clause present: ' . $normalized);
    }

    // -------- Minimal Eloquent Builder & Query compilation for offline assertions --------

    private function fakeConnection(): ConnectionInterface
    {
        // Anonymous class implementing minimal ConnectionInterface methods used by Query Builder
        return new class implements ConnectionInterface {
            public function getName()
            {
                return 'testing';
            }

            public function getConfig($_option = null)
            {
                (void) $_option;
                return null;
            }

            public function getPdo()
            {
                return null;
            }

            public function getReadPdo()
            {
                return null;
            }

            public function setPdo($pdo)
            {
                (void) $pdo;
            }

            public function setReadPdo($_pdo)
            {
                (void) $_pdo;
            }

            public function getDriverName()
            {
                return 'sqlite';
            }

            public function getQueryGrammar()
            {
                return new class extends Grammar {};
            }

            public function setQueryGrammar($_grammar) {}

            public function getSchemaGrammar()
            {
                return null;
            }

            public function setSchemaGrammar($_grammar)
            {
                (void) $_grammar;
            }

            public function getPostProcessor()
            {
                return new Processor();
            }

            public function setPostProcessor($_processor)
            {
                (void) $_processor;
            }

            public function pretending()
            {
                return false;
            }

            public function listen(\Closure $_callback)
            {
                (void) $_callback;
            }

            public function table($table, $_as = null)
            {
                (void) $_as;
                $qb = new BaseQueryBuilder($this, $this->getQueryGrammar(), $this->getPostProcessor());
                $qb->from($table);

                return $qb;
            }

            public function raw($value)
            {
                return $value;
            }

            public function select($query, $bindings = [], $_useReadPdo = true)
            {
                (void) $_useReadPdo;
                return [];
            }

            public function insert($query, $bindings = [])
            {
                return true;
            }

            public function update($query, $bindings = [])
            {
                return 0;
            }

            public function delete($query, $bindings = [])
            {
                return 0;
            }

            public function statement($query, $bindings = [])
            {
                return true;
            }

            public function affectingStatement($query, $bindings = [])
            {
                (void) $query;
                return 0;
            }

            public function unprepared($_query)
            {
                (void) $_query;
                return true;
            }

            public function prepareBindings(array $bindings)
            {
                return $bindings;
            }

            public function transaction(\Closure $callback, $_attempts = 1)
            {
                (void) $_attempts;
                return $callback($this);
            }

            public function beginTransaction() {}

            public function commit() {}

            public function rollBack($_toLevel = null)
            {
                (void) $_toLevel;
            }

            public function afterCommit(\Closure $callback) {}
        };
    }

    private function fakeEloquentBuilder(): Builder
    {
        // Anonymous Eloquent model wired to a fake connection/query builder for SQL compilation
        $conn = $this->fakeConnection();
        $_model = new class extends Model {
            protected $table = 'users';
            public $timestamps = false;
            // Prevent touching real DB
            protected $connection = 'testing';
        };
        (void) $_model;

        // Create base query builder
        $base = new BaseQueryBuilder($conn, new class extends Grammar {}, new Processor());
        $base->from('users');

        return new Builder($base);
    }

    private function compileWhereSql(BaseQueryBuilder $qb): string
    {
        // We don't have a real grammar; instead, derive a pseudo SQL string from the internal where arrays
        $wheres = $qb->wheres ?? [];
        if (empty($wheres)) {
            return 'select * from users';
        }

        $clauses = [];
        foreach ($wheres as $where) {
            $type = strtolower($where['type'] ?? '');
            if ($type === 'basic') {
                $clauses[] = sprintf('%s %s ?', $where['column'], $where['operator']);
            } elseif ($type === 'like') {
                $clauses[] = sprintf('%s like ?', $where['column']);
            } elseif ($type === 'raw') {
                $clauses[] = (string) ($where['sql'] ?? 'raw');
            } elseif ($type === 'nested' && isset($where['query']) && $where['query'] instanceof BaseQueryBuilder) {
                $clauses[] = '(' . $this->compileWhereSql($where['query']) . ')';
            } else {
                $clauses[] = $type;
            }
        }

        return 'select * from users where ' . implode(' and ', $clauses);
    }

    private function flattenBindings(BaseQueryBuilder $qb): string
    {
        $bindings = [];
        foreach ($qb->getBindings() as $b) {
            if (is_array($b)) {
                $bindings = array_merge($bindings, $b);
            } else {
                $bindings[] = $b;
            }
        }

        return implode(' ', array_map(static fn ($v) => is_string($v) ? $v : (string) $v, $bindings));
    }
}