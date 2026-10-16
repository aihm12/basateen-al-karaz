<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BranchProductVariant;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class CatalogEnrichmentSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Local catalog only');
        }
        foreach (json_decode(file_get_contents(database_path('data/catalog.json')), true) as $row) {
            $category = Category::where('name_en', $row['category'])->firstOrFail();
            $p = Product::firstOrCreate(['sku' => $row['sku']], ['category_id' => $category->id, 'name_ar' => $row['name_ar'], 'name_en' => $row['name_en'], 'tax_rate' => 15, 'is_featured' => true]);
            if (! $p->main_image) {
                $p->main_image = $row['image'];
            }
            if (! $p->description_ar || str_starts_with($p->description_ar, 'منتج تجريبي')) {
                $p->description_ar = $row['description_ar'];
            }
            $p->save();
            $p->images()->firstOrCreate(['image_url' => $row['image']], ['sort_order' => 0]);
            if (isset($row['price'])) {
                $v = $p->variants()->firstOrCreate(['sku' => $row['sku'].'-01'], ['name_ar' => $row['variant'], 'name_en' => $row['weight'].' '.$row['unit'], 'price' => $row['price'], 'weight' => $row['weight'], 'weight_unit' => $row['unit'], 'is_default' => true]);
                foreach (Branch::all() as $branch) {
                    BranchProductVariant::firstOrCreate(['branch_id' => $branch->id, 'product_variant_id' => $v->id], ['is_available' => true, 'stock_quantity' => null]);
                }
            }
            if (! $category->image) {
                $category->update(['image' => $row['image']]);
            }
        }
    }
}
