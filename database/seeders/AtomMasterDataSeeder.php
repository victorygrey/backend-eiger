<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AtomMasterDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->seedCategories();
            $this->seedActivityGroups();
        });
    }

    private function seedCategories(): void
    {
        foreach ($this->rows('dm_product_category.csv') as $row) {
            DB::table('atom_product_categories')->updateOrInsert(['source_id' => (int) $row['id']], [
                'external_id' => $row['external_id'], 'name' => $row['name'], 'slug' => $row['slug'],
                'image' => $this->null($row['image']), 'image_secondary' => $this->null($row['image2']),
                'image_web' => $this->null($row['image_web']), 'channel_code' => $this->null($row['channel_code']),
                'sequence_number' => $this->integer($row['sequence_number']), 'is_featured' => $this->boolean($row['is_featured']),
                'is_active' => $this->boolean($row['is_active']), 'source_created_at' => $this->date($row['created_at']),
                'source_updated_at' => $this->date($row['updated_at']), 'source_deleted_at' => $this->date($row['deleted_at']),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $categoryIds = DB::table('atom_product_categories')->pluck('id', 'source_id');
        foreach ($this->rows('dm_product_sub_category.csv') as $row) {
            DB::table('atom_product_sub_categories')->updateOrInsert(['source_id' => (int) $row['id']], [
                'category_id' => $categoryIds[(int) $row['product_category_id']], 'external_id' => $row['external_id'],
                'name' => $row['name'], 'slug' => $row['slug'], 'image' => $this->null($row['image']),
                'image_secondary' => $this->null($row['image2']), 'image_web' => $this->null($row['image_web']),
                'sequence_number' => $this->integer($row['sequence_number']), 'is_featured' => $this->boolean($row['is_featured']),
                'is_active' => $this->boolean($row['is_active']), 'source_created_at' => $this->date($row['created_at']),
                'source_updated_at' => $this->date($row['updated_at']), 'source_deleted_at' => $this->date($row['deleted_at']),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function seedActivityGroups(): void
    {
        foreach ($this->rows('dm_product_activity_group.csv') as $row) {
            DB::table('atom_product_activity_groups')->updateOrInsert(['source_id' => (int) $row['id']], [
                'external_id' => $row['external_id'], 'name' => $row['name'], 'slug' => $row['slug'],
                'image' => $this->null($row['image']), 'image_web' => $this->null($row['image_web']),
                'sequence_number' => $this->integer($row['sequence_number']), 'is_active' => $this->boolean($row['is_active']),
                'source_created_at' => $this->date($row['created_at']), 'source_updated_at' => $this->date($row['updated_at']),
                'source_deleted_at' => $this->date($row['deleted_at']), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $groupIds = DB::table('atom_product_activity_groups')->pluck('id', 'source_id');
        foreach ($this->rows('dm_product_activity.csv') as $row) {
            DB::table('atom_product_activities')->updateOrInsert(['source_id' => (int) $row['id']], [
                'activity_group_id' => $groupIds[(int) $row['product_activity_group_id']],
                'external_id' => $row['external_id'], 'name' => $row['name'], 'slug' => $row['slug'],
                'image' => $this->null($row['image']), 'description' => $this->null($row['description']),
                'rating_description' => $this->null($row['rating_desc']), 'is_active' => $this->boolean($row['is_active']),
                'source_created_at' => $this->date($row['created_at']), 'source_updated_at' => $this->date($row['updated_at']),
                'source_deleted_at' => $this->date($row['deleted_at']), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    /** @return array<int, array<string, string>> */
    private function rows(string $filename): array
    {
        $handle = fopen(database_path('data/atom/'.$filename), 'rb');
        if (! $handle) throw new \RuntimeException('Master data ATOM tidak dapat dibaca: '.$filename);
        $headers = fgetcsv($handle);
        $rows = [];
        while (($values = fgetcsv($handle)) !== false) $rows[] = array_combine($headers, $values);
        fclose($handle);
        return $rows;
    }

    private function null(?string $value): ?string { return trim((string) $value) === '' ? null : $value; }
    private function integer(?string $value): ?int { return trim((string) $value) === '' ? null : (int) $value; }
    private function boolean(?string $value): bool { return filter_var($value, FILTER_VALIDATE_BOOL); }
    private function date(?string $value): ?string
    {
        return trim((string) $value) === '' ? null : CarbonImmutable::parse($value)->utc()->format('Y-m-d H:i:s');
    }
}
