<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeFilamentCrud extends Command
{
    protected $signature = 'make:filament-crud {name} {fields*} {--faker}';
    protected $description = 'Generate Model, Migration, Filament Resource, and optional Faker data with relationships';

    public function handle()
    {
        $name   = Str::studly($this->argument('name'));
        $fields = $this->argument('fields');

        $modelPath    = app_path("Models/{$name}.php");
        $resourcePath = app_path("Filament/Resources/{$name}Resource.php");
        // Choose icon dynamically
        $iconMap = config('icons.map');

        $icon = $iconMap[$name] ?? 'heroicon-o-rectangle-stack';

        // ✅ Prevent overwrite
        if (File::exists($modelPath)) {
            $this->error("❌ Model {$name} already exists at: {$modelPath}");
            return Command::FAILURE;
        }
        if (File::exists($resourcePath)) {
            $this->error("❌ Filament Resource {$name} already exists at: {$resourcePath}");
            return Command::FAILURE;
        }

        $this->info("🚀 Generating Filament CRUD for: {$name}");

        /**
         * 1. Model + Migration
         */
        Artisan::call("make:model {$name} -m");

        $tableName     = Str::plural(Str::snake($name));
        $migrationFile = collect(File::files(database_path('migrations')))
            ->filter(fn($file) => Str::contains($file->getFilename(), "create_{$tableName}_table"))
            ->first()?->getPathname();

        if ($migrationFile) {
            $migrationFields = collect($fields)->map(function ($field) {
                [$fieldName, $type] = explode(':', $field);

                if ($type === 'belongsTo') {
                    // ✅ Add foreign key instead of plain field
                    return "\$table->foreignId('{$fieldName}')->constrained()->cascadeOnDelete();";
                }

                return "\$table->{$type}('{$fieldName}');";
            })->implode("\n            ");

            $migrationContent = File::get($migrationFile);
            $migrationContent = str_replace(
                "\$table->id();",
                "\$table->id();\n            {$migrationFields}",
                $migrationContent
            );
            File::put($migrationFile, $migrationContent);
        }

        /**
         * 2. Add $fillable + relationships in Model
         */
        if (File::exists($modelPath)) {
            $modelContent = File::get($modelPath);
            $fillable     = collect($fields)
                ->map(fn($f) => "'" . explode(':', $f)[0] . "'")
                ->implode(', ');

            $fillableProperty = "\n    protected \$fillable = [{$fillable}];\n";

            // Add relationships
            $relationshipMethods = collect($fields)->map(function ($field) {
                [$fieldName, $type] = explode(':', $field);

                if ($type === 'belongsTo') {
                    $related = Str::studly(str_replace('_id', '', $fieldName));
                    return <<<PHP

    public function {$related}()
    {
        return \$this->belongsTo(\\App\\Models\\{$related}::class);
    }

PHP;
                }
                return '';
            })->implode("\n");

            $modelContent = preg_replace('/}\s*$/', $fillableProperty . "\n" . $relationshipMethods . "}", $modelContent);
            File::put($modelPath, $modelContent);
        }

        /**
         * 3. Filament Resource
         */
        Artisan::call("make:filament-resource {$name} --generate");

        $resourcePath    = app_path("Filament/Resources/{$name}Resource.php");
        $resourceContent = File::get($resourcePath);

        $formFields = collect($fields)->map(function ($field) {
            [$fieldName, $type] = explode(':', $field);

            if ($type === 'belongsTo') {
                $related = Str::studly(str_replace('_id', '', $fieldName));
                return "Forms\\Components\\Select::make('{$fieldName}')"
                    . "->relationship('{$related}', 'name')->searchable()->required(),";
            }

            return match ($type) {
                'string'  => "Forms\\Components\\TextInput::make('{$fieldName}')->required(),",
                'text'    => "Forms\\Components\\Textarea::make('{$fieldName}')->required(),",
                'boolean' => "Forms\\Components\\Toggle::make('{$fieldName}'),",
                'integer' => "Forms\\Components\\TextInput::make('{$fieldName}')->numeric(),",
                default   => "Forms\\Components\\TextInput::make('{$fieldName}'),",
            };
        })->implode("\n                ");

        $tableColumns = collect($fields)->map(function ($field) {
            [$fieldName, $type] = explode(':', $field);

            if ($type === 'belongsTo') {
                $related = Str::studly(str_replace('_id', '', $fieldName));
                return "Tables\\Columns\\TextColumn::make('{$related}.name')->sortable()->searchable(),";
            }

            return "Tables\\Columns\\TextColumn::make('{$fieldName}')->sortable()->searchable(),";
        })->implode("\n                ");

        $resourceContent = preg_replace(
            '/return\s+\$form[\s\S]*?->\s*schema\s*\(\s*\[.*?\]\s*\);/s',
            "return \$form->schema([\n                {$formFields}\n            ]);",
            $resourceContent
        );

        $resourceContent = preg_replace(
            '/return\s+\$table[\s\S]*?->\s*columns\s*\(\s*\[.*?\]\s*\);/s',
            "return \$table->columns([\n                {$tableColumns}\n            ]);",
            $resourceContent
        );

        // Make regex multi-line and whitespace flexible
        $resourceContent = preg_replace(
            '/protected\s+static\s+\?string\s+\$navigationIcon\s*=\s*([\'"])[^\'"]*\1\s*;/m',
            "protected static ?string \$navigationIcon = '{$icon}';",
            $resourceContent
        );

        File::put($resourcePath, $resourceContent);

        /**
         * 4. Run Migration
         */
        Artisan::call("migrate");

        /**
         * 5. Faker (factory + seeder)
         */
        if ($this->option('faker')) {
            Artisan::call("make:factory {$name}Factory --model={$name}");
            $factoryPath = database_path("factories/{$name}Factory.php");

            if (File::exists($factoryPath)) {
                $factoryContent = File::get($factoryPath);
                $fakerFields    = collect($fields)->map(function ($field) {
                    [$fieldName, $type] = explode(':', $field);

                    if ($type === 'belongsTo') {
                        return "'{$fieldName}' => \\App\\Models\\" . Str::studly(str_replace('_id', '', $fieldName)) . "::factory(),";
                    }

                    return match ($type) {
                        'string'  => "'{$fieldName}' => \$this->faker->sentence(),",
                        'text'    => "'{$fieldName}' => \$this->faker->paragraph(),",
                        'boolean' => "'{$fieldName}' => \$this->faker->boolean(),",
                        'integer' => "'{$fieldName}' => \$this->faker->numberBetween(1, 100),",
                        default   => "'{$fieldName}' => \$this->faker->word(),",
                    };
                })->implode("\n            ");

                $factoryContent = preg_replace(
                    '/return\s+\[.*?\];/s',
                    "return [\n            {$fakerFields}\n        ];",
                    $factoryContent
                );
                File::put($factoryPath, $factoryContent);
            }

            Artisan::call("make:seeder {$name}Seeder");
            $seederPath = database_path("seeders/{$name}Seeder.php");

            if (File::exists($seederPath)) {
                $seederContent = File::get(base_path('public/masterstubs/seeder.stub'));
                $seederContent = str_replace(['{{ namespace }}', '{{ class }}', '{{ model }}'], [
                    'Database\\Seeders',
                    "{$name}Seeder",
                    $name,
                ], $seederContent);

                File::put($seederPath, $seederContent);
            }

            Artisan::call("db:seed", ['--class' => "{$name}Seeder"]);
        }

        $this->info("✅ Filament CRUD for {$name} generated with fields: " . implode(', ', $fields));
    }
}
