<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class MakeFilamentCrud extends Command
{
    protected $signature = 'make:filament-crud {name} {fields*} {--faker}';
    protected $description = 'Generate Model, Migration, Filament Resource, and optional Faker data with relationships';

    public function handle()
    {
        try {
            $name   = Str::studly($this->argument('name'));
            $fields = $this->argument('fields');

            $modelPath    = app_path("Models/{$name}.php");
            $resourcePath = app_path("Filament/Resources/{$name}Resource.php");

            $iconMap = config('icons.map');
            $icon = $iconMap[$name] ?? 'heroicon-o-rectangle-stack';

            // ✅ Prevent overwrite
            if (File::exists($modelPath)) {
                $this->error("❌ Model {$name} already exists at: {$modelPath}");
                Log::warning("Attempted to overwrite existing model: {$modelPath}");
                return Command::FAILURE;
            }
            if (File::exists($resourcePath)) {
                $this->error("❌ Filament Resource {$name} already exists at: {$resourcePath}");
                Log::warning("Attempted to overwrite existing resource: {$resourcePath}");
                return Command::FAILURE;
            }

            $this->info("🚀 Generating Filament CRUD for: {$name}");

            // ================================================
            // 1. Model + Migration
            // ================================================
            Artisan::call("make:model {$name} -m");
            $this->info("✅ Model & Migration created.");

            $tableName     = Str::plural(Str::snake($name));
            $migrationFile = collect(File::files(database_path('migrations')))
                ->filter(fn($file) => Str::contains($file->getFilename(), "create_{$tableName}_table"))
                ->first()?->getPathname();

            if (!$migrationFile) {
                throw new \Exception("Migration file for {$tableName} not found.");
            }

            $migrationFields = collect($fields)->map(function ($field) {
                [$fieldName, $type] = explode(':', $field);

                if ($type === 'belongsTo') {
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
            $this->info("✅ Migration fields added.");

            // ================================================
            // 2. Add Fillable + Relationships in Model
            // ================================================
            if (File::exists($modelPath)) {
                $modelContent = File::get($modelPath);
                $fillable     = collect($fields)
                    ->map(fn($f) => "'" . explode(':', $f)[0] . "'")
                    ->implode(', ');

                $fillableProperty = "\n    protected \$fillable = [{$fillable}];\n";

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
                $this->info("✅ Model updated with fillable + relationships.");
            }

            // ================================================
            // 3. Filament Resource
            // ================================================
            Artisan::call("make:filament-resource {$name} --generate");
            $this->info("✅ Filament resource created.");

            $resourceContent = File::get($resourcePath);

            // Generate form + table schema
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
            $resourceContent = preg_replace(
                '/protected\s+static\s+\?string\s+\$navigationIcon\s*=\s*([\'"])[^\'"]*\1\s*;/m',
                "protected static ?string \$navigationIcon = '{$icon}';",
                $resourceContent
            );

            File::put($resourcePath, $resourceContent);
            $this->info("✅ Filament resource updated with fields + icon.");

            // ================================================
            // 4. Run Migration
            // ================================================
            Artisan::call("migrate");
            $this->info("✅ Database migrated.");

            // ================================================
            // 5. Optional Faker
            // ================================================
            if ($this->option('faker')) {
                Artisan::call("make:factory {$name}Factory --model={$name}");
                Artisan::call("make:seeder {$name}Seeder");
                Artisan::call("db:seed", ['--class' => "{$name}Seeder"]);
                $this->info("✅ Factory & Seeder generated with Faker data.");
            }

            $this->info("🎉 Filament CRUD for {$name} generated successfully!");
            return Command::SUCCESS;

        } catch (Throwable $e) {
            $this->error("❌ Error: " . $e->getMessage());
            Log::error("MakeFilamentCrud failed", [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);
            return Command::FAILURE;
        }
    }
}
