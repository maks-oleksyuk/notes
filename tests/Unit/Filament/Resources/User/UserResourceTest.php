<?php
declare(strict_types=1);

namespace Tests\Unit\Filament\Resources\User;

/*
Testing framework: PHPUnit (compatible with Pest). These tests focus on the diff-visible behavior
of App\Filament\Resources\User\UserResource: static properties, canCreate/canEdit, form() schema, and getPages().
*/

use App\Filament\Resources\User\Pages\ListUsers;
use App\Filament\Resources\User\Pages\ViewUser;
use App\Filament\Resources\User\UserResource;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class UserResourceTest extends TestCase
{
    public function test_model_property_is_user_class(): void
    {
        $ref = new ReflectionClass(UserResource::class);
        $prop = $ref->getProperty('model');
        $prop->setAccessible(true);
        self::assertSame(User::class, $prop->getValue());
    }

    public function test_navigation_icon_is_heroicon_users(): void
    {
        $ref = new ReflectionClass(UserResource::class);
        $prop = $ref->getProperty('navigationIcon');
        $prop->setAccessible(true);
        self::assertSame('heroicon-o-users', $prop->getValue());
    }

    public function test_can_create_returns_true(): void
    {
        self::assertTrue(UserResource::canCreate());
    }

    public function test_can_edit_returns_false_for_any_record(): void
    {
        $record = new class extends Model {};
        self::assertFalse(UserResource::canEdit($record));
        self::assertFalse(UserResource::canEdit(new User()));
    }

    public function test_form_defines_expected_schema_components(): void
    {
        $schema = $this->getMockBuilder(Schema::class)
            ->onlyMethods(['schema'])
            ->getMock();

        $schema
            ->expects($this->once())
            ->method('schema')
            ->with($this->callback(function (array $components): bool {
                // Ensure expected count and types/names
                $this->assertCount(4, $components, 'Expected exactly four form components.');

                $byName = [];
                foreach ($components as $component) {
                    $this->assertIsObject($component);
                    $this->assertTrue(
                        method_exists($component, 'getName'),
                        'Form component must have a getName() accessor.'
                    );
                    $byName[$component->getName()] = $component;
                }

                foreach (['name', 'email', 'created_at', 'email_verified_at'] as $expected) {
                    $this->assertArrayHasKey($expected, $byName, "Missing component: {$expected}");
                }

                $this->assertInstanceOf(TextInput::class, $byName['name']);
                $this->assertInstanceOf(TextInput::class, $byName['email']);
                $this->assertInstanceOf(DateTimePicker::class, $byName['created_at']);
                $this->assertInstanceOf(DateTimePicker::class, $byName['email_verified_at']);

                // Validate required flags for name and email when API available
                foreach (['name', 'email'] as $requiredField) {
                    $field = $byName[$requiredField];
                    $isRequired = null;

                    if (method_exists($field, 'isRequired')) {
                        $isRequired = $field->isRequired();
                    } elseif (method_exists($field, 'getRules')) {
                        $rules = $field->getRules();
                        if (is_string($rules)) {
                            $isRequired = str_contains($rules, 'required');
                        } elseif (is_array($rules)) {
                            $isRequired = in_array('required', $rules, true);
                        }
                    } elseif (method_exists($field, 'getValidationRules')) {
                        $rules = $field->getValidationRules();
                        if (is_array($rules)) {
                            $isRequired = in_array('required', $rules, true);
                        }
                    }

                    if ($isRequired !== null) {
                        $this->assertTrue($isRequired, "Field '{$requiredField}' should be required.");
                    }
                }

                return true;
            }))
            ->willReturnSelf();

        $result = UserResource::form($schema);
        self::assertSame($schema, $result, 'form() should return the provided Schema after configuration.');
    }

    public function test_get_pages_returns_expected_routes(): void
    {
        $pages = UserResource::getPages();

        self::assertIsArray($pages);
        self::assertArrayHasKey('index', $pages);
        self::assertArrayHasKey('view', $pages);

        // Compare to the exact route strings computed by the page classes.
        self::assertSame(ListUsers::route('/'), $pages['index']);
        self::assertSame(ViewUser::route('/{record}'), $pages['view']);

        // Basic sanity: routes are non-empty strings
        self::assertIsString($pages['index']);
        self::assertIsString($pages['view']);
        self::assertNotSame('', $pages['index']);
        self::assertNotSame('', $pages['view']);
    }
}