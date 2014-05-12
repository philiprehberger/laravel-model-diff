<?php

declare(strict_types=1);

namespace PhilipRehberger\ModelDiff\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase;
use PhilipRehberger\ModelDiff\AttributeChange;
use PhilipRehberger\ModelDiff\Concerns\HasDiffLabels;
use PhilipRehberger\ModelDiff\DiffResult;
use PhilipRehberger\ModelDiff\Facades\ModelDiff as ModelDiffFacade;
use PhilipRehberger\ModelDiff\ModelDiff;
use PhilipRehberger\ModelDiff\ModelDiffServiceProvider;

// ---------------------------------------------------------------------------
// Enums used in tests
// ---------------------------------------------------------------------------

enum PostStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}

// ---------------------------------------------------------------------------
// Test models
// ---------------------------------------------------------------------------

class TestUser extends Model
{
    use HasDiffLabels;

    protected $table = 'test_users';

    protected $fillable = [
        'name',
        'email',
        'age',
        'is_active',
        'metadata',
        'published_at',
        'score',
        'status',
    ];

    protected $casts = [
        'age' => 'integer',
        'is_active' => 'boolean',
        'metadata' => 'array',
        'published_at' => 'datetime',
        'score' => 'float',
        'status' => PostStatus::class,
    ];

    protected array $diffLabels = [
        'name' => 'Full Name',
        'email' => 'Email Address',
        'is_active' => 'Active',
        'published_at' => 'Published At',
    ];
}

class TestUserNoLabels extends Model
{
    protected $table = 'test_users';

    protected $fillable = [
        'name',
        'email',
        'age',
        'is_active',
        'metadata',
        'published_at',
        'score',
        'status',
    ];

    protected $casts = [
        'age' => 'integer',
        'is_active' => 'boolean',
        'metadata' => 'array',
        'published_at' => 'datetime',
        'score' => 'float',
        'status' => PostStatus::class,
    ];
}

// ---------------------------------------------------------------------------
// The test case
// ---------------------------------------------------------------------------

class ModelDiffTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // Testbench wiring
    // -----------------------------------------------------------------------

    protected function getPackageProviders($app): array
    {
        return [ModelDiffServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('test_users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->integer('age')->nullable();
            $table->boolean('is_active')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->float('score')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function diff(): ModelDiff
    {
        return new ModelDiff;
    }

    /** Create and persist a TestUser with the given attributes. */
    private function makeUser(array $attributes = []): TestUser
    {
        return TestUser::create(array_merge([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'age' => 30,
            'is_active' => true,
        ], $attributes));
    }

    // -----------------------------------------------------------------------
    // Basic attribute comparison
    // -----------------------------------------------------------------------

    public function test_it_detects_basic_string_change(): void
    {
        $before = $this->makeUser(['name' => 'Alice']);
        $after = TestUser::find($before->id);
        $after->name = 'Bob';
        $after->save();

        $result = $this->diff()->compare($before, $after);

        $this->assertTrue($result->hasChanges());
        $this->assertContains('name', $result->changedAttributes());
    }

    public function test_it_returns_correct_old_and_new_values(): void
    {
        $before = $this->makeUser(['name' => 'Alice']);
        $after = TestUser::find($before->id);
        $after->name = 'Bob';
        $after->save();

        $result = $this->diff()->compare($before, $after);
        $changes = $result->getChanges();

        $nameChange = collect($changes)->firstWhere('attribute', 'name');

        $this->assertInstanceOf(AttributeChange::class, $nameChange);
        $this->assertSame('Alice', $nameChange->old);
        $this->assertSame('Bob', $nameChange->new);
    }

    // -----------------------------------------------------------------------
    // No changes
    // -----------------------------------------------------------------------

    public function test_it_returns_empty_result_when_nothing_changed(): void
    {
        $before = $this->makeUser();
        $after = TestUser::find($before->id);

        $result = $this->diff()->compare($before, $after);

        $this->assertFalse($result->hasChanges());
        $this->assertSame([], $result->changedAttributes());
        $this->assertSame([], $result->toArray());
    }

    // -----------------------------------------------------------------------
    // Ignored attributes
    // -----------------------------------------------------------------------

    public function test_it_ignores_default_attributes(): void
    {
        $before = $this->makeUser();

        // Fabricate an "after" with only timestamp difference
        $after = TestUser::find($before->id);
        $after->updated_at = now()->addHour();

        $result = $this->diff()->compare($before, $after);

        $this->assertNotContains('updated_at', $result->changedAttributes());
        $this->assertNotContains('created_at', $result->changedAttributes());
        $this->assertNotContains('id', $result->changedAttributes());
    }

    public function test_it_can_ignore_additional_attributes(): void
    {
        $before = $this->makeUser(['name' => 'Alice', 'email' => 'alice@example.com']);
        $after = TestUser::find($before->id);
        $after->name = 'Bob';
        $after->email = 'bob@example.com';
        $after->save();

        $result = $this->diff()->ignoring(['email'])->compare($before, $after);

        $this->assertContains('name', $result->changedAttributes());
        $this->assertNotContains('email', $result->changedAttributes());
    }

    // -----------------------------------------------------------------------
    // Boolean comparison
    // -----------------------------------------------------------------------

    public function test_it_detects_boolean_change(): void
    {
        $before = $this->makeUser(['is_active' => true]);
        $after = TestUser::find($before->id);
        $after->is_active = false;
        $after->save();

        $result = $this->diff()->compare($before, $after);

        $this->assertContains('is_active', $result->changedAttributes());
    }

    public function test_it_does_not_flag_equivalent_boolean_values(): void
    {
        $before = $this->makeUser(['is_active' => true]);
        $after = TestUser::find($before->id);

        // No change
        $result = $this->diff()->compare($before, $after);

        $this->assertNotContains('is_active', $result->changedAttributes());
    }

    // -----------------------------------------------------------------------
    // Date comparison
    // -----------------------------------------------------------------------

    public function test_it_detects_date_change(): void
    {
        $before = $this->makeUser(['published_at' => '2024-01-01 00:00:00']);
        $after = TestUser::find($before->id);
        $after->published_at = '2025-06-15 12:00:00';
        $after->save();

        $result = $this->diff()->compare($before, $after);

        $this->assertContains('published_at', $result->changedAttributes());
    }

    public function test_it_does_not_flag_same_date_value(): void
    {
        $before = $this->makeUser(['published_at' => '2024-01-01 00:00:00']);
        $after = TestUser::find($before->id);

        $result = $this->diff()->compare($before, $after);

        $this->assertNotContains('published_at', $result->changedAttributes());
    }

    public function test_dates_are_formatted_in_human_readable(): void
    {
        $before = $this->makeUser(['published_at' => '2024-01-15 09:00:00']);
        $after = TestUser::find($before->id);
        $after->published_at = '2025-06-20 14:30:00';
        $after->save();

        $result = $this->diff()->compare($before, $after);
        $readable = $result->toHumanReadable();

        // The label key is defined in $diffLabels
        $this->assertArrayHasKey('Published At', $readable);
    }

    // -----------------------------------------------------------------------
    // JSON / array comparison
    // -----------------------------------------------------------------------

    public function test_it_detects_array_change(): void
    {
        $before = $this->makeUser(['metadata' => ['role' => 'admin', 'level' => 1]]);
        $after = TestUser::find($before->id);
        $after->metadata = ['role' => 'editor', 'level' => 1];
        $after->save();

        $result = $this->diff()->compare($before, $after);

        $this->assertContains('metadata', $result->changedAttributes());
    }

    public function test_it_does_not_flag_semantically_equal_arrays(): void
    {
        $before = $this->makeUser(['metadata' => ['role' => 'admin', 'level' => 1]]);
        $after = TestUser::find($before->id);

        // Re-assign the same values — Eloquent may re-serialise but semantics are equal
        $after->metadata = ['role' => 'admin', 'level' => 1];

        $result = $this->diff()->compare($before, $after);

        $this->assertNotContains('metadata', $result->changedAttributes());
    }

    public function test_it_detects_nested_array_change(): void
    {
        $before = $this->makeUser(['metadata' => ['tags' => ['a', 'b']]]);
        $after = TestUser::find($before->id);
        $after->metadata = ['tags' => ['a', 'c']];
        $after->save();

        $result = $this->diff()->compare($before, $after);

        $this->assertContains('metadata', $result->changedAttributes());
    }

    // -----------------------------------------------------------------------
    // Enum comparison
    // -----------------------------------------------------------------------

    public function test_it_detects_enum_change(): void
    {
        $before = $this->makeUser(['status' => 'draft']);
        $after = TestUser::find($before->id);
        $after->status = PostStatus::Published;
        $after->save();

        $result = $this->diff()->compare($before, $after);

        $this->assertContains('status', $result->changedAttributes());
    }

    public function test_enum_values_stored_as_scalars_in_change(): void
    {
        $before = $this->makeUser(['status' => 'draft']);
        $after = TestUser::find($before->id);
        $after->status = PostStatus::Published;
        $after->save();

        $result = $this->diff()->compare($before, $after);
        $changes = $result->getChanges();

        $statusChange = collect($changes)->firstWhere('attribute', 'status');

        $this->assertNotNull($statusChange);
        $this->assertSame('draft', $statusChange->old);
        $this->assertSame('published', $statusChange->new);
    }

    // -----------------------------------------------------------------------
    // HasDiffLabels trait
    // -----------------------------------------------------------------------

    public function test_it_uses_diff_labels_when_trait_is_present(): void
    {
        $before = $this->makeUser(['name' => 'Alice']);
        $after = TestUser::find($before->id);
        $after->name = 'Bob';
        $after->save();

        $result = $this->diff()->compare($before, $after);
        $changes = $result->getChanges();

        $nameChange = collect($changes)->firstWhere('attribute', 'name');

        $this->assertSame('Full Name', $nameChange->label);
    }

    public function test_it_humanizes_unlabelled_attributes_when_trait_is_present(): void
    {
        $before = $this->makeUser(['age' => 30]);
        $after = TestUser::find($before->id);
        $after->age = 31;
        $after->save();

        $result = $this->diff()->compare($before, $after);
        $changes = $result->getChanges();

        // 'age' has no explicit label so it should be humanized → 'Age'
        $ageChange = collect($changes)->firstWhere('attribute', 'age');

        $this->assertSame('Age', $ageChange->label);
    }

    public function test_it_humanizes_attribute_names_without_trait(): void
    {
        $before = TestUserNoLabels::create(['name' => 'Alice', 'email' => 'alice@example.com', 'age' => 30, 'is_active' => true]);
        $after = TestUserNoLabels::find($before->id);
        $after->name = 'Bob';
        $after->save();

        $result = $this->diff()->compare($before, $after);
        $changes = $result->getChanges();

        $nameChange = collect($changes)->firstWhere('attribute', 'name');

        $this->assertSame('Name', $nameChange->label);
    }

    public function test_get_diff_label_returns_custom_label(): void
    {
        $model = new TestUser;

        $this->assertSame('Full Name', $model->getDiffLabel('name'));
    }

    public function test_get_diff_label_humanizes_unknown_attribute(): void
    {
        $model = new TestUser;

        $this->assertSame('Company Name', $model->getDiffLabel('company_name'));
    }

    // -----------------------------------------------------------------------
    // fromDirty
    // -----------------------------------------------------------------------

    public function test_from_dirty_detects_unsaved_changes(): void
    {
        $model = $this->makeUser(['name' => 'Alice']);
        $model->name = 'Charlie';

        // Do NOT save — we want dirty state
        $result = $this->diff()->fromDirty($model);

        $this->assertTrue($result->hasChanges());
        $this->assertContains('name', $result->changedAttributes());
    }

    public function test_from_dirty_returns_empty_when_model_is_clean(): void
    {
        $model = $this->makeUser(['name' => 'Alice']);

        $result = $this->diff()->fromDirty($model);

        $this->assertFalse($result->hasChanges());
    }

    public function test_from_dirty_ignores_default_attributes(): void
    {
        $model = $this->makeUser();
        $model->updated_at = now()->addMinute();

        $result = $this->diff()->fromDirty($model);

        $this->assertNotContains('updated_at', $result->changedAttributes());
    }

    public function test_from_dirty_tracks_multiple_changes(): void
    {
        $model = $this->makeUser(['name' => 'Alice', 'age' => 25]);
        $model->name = 'Dave';
        $model->age = 26;

        $result = $this->diff()->fromDirty($model);

        $this->assertContains('name', $result->changedAttributes());
        $this->assertContains('age', $result->changedAttributes());
    }

    // -----------------------------------------------------------------------
    // DiffResult helpers
    // -----------------------------------------------------------------------

    public function test_to_array_structure(): void
    {
        $before = $this->makeUser(['name' => 'Alice']);
        $after = TestUser::find($before->id);
        $after->name = 'Bob';
        $after->save();

        $array = $this->diff()->compare($before, $after)->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('attribute', $array[0]);
        $this->assertArrayHasKey('old', $array[0]);
        $this->assertArrayHasKey('new', $array[0]);
        $this->assertArrayHasKey('label', $array[0]);
    }

    public function test_to_human_readable_structure(): void
    {
        $before = $this->makeUser(['name' => 'Alice']);
        $after = TestUser::find($before->id);
        $after->name = 'Bob';
        $after->save();

        $readable = $this->diff()->compare($before, $after)->toHumanReadable();

        // Key is the label, not the attribute
        $this->assertArrayHasKey('Full Name', $readable);
        $this->assertArrayHasKey('old', $readable['Full Name']);
        $this->assertArrayHasKey('new', $readable['Full Name']);
    }

    // -----------------------------------------------------------------------
    // Facade
    // -----------------------------------------------------------------------

    public function test_facade_resolves_correctly(): void
    {
        $before = $this->makeUser(['name' => 'Alice']);
        $after = TestUser::find($before->id);
        $after->name = 'Facade User';
        $after->save();

        $result = ModelDiffFacade::compare($before, $after);

        $this->assertInstanceOf(DiffResult::class, $result);
        $this->assertTrue($result->hasChanges());
    }
}
