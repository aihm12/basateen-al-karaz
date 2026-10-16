<?php

namespace Database\Seeders;

use App\Models\Banner;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\DeliverySlot;
use App\Models\DeliveryZone;
use App\Models\OptionGroup;
use App\Models\Product;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Demo seeding is restricted to local/testing');
        }
        $branch = Branch::firstOrCreate(['name' => 'بساتين الكرز - الفرع التجريبي'], ['address' => 'عنوان تجريبي - الرياض', 'opening_hours' => ['daily' => '09:00-23:00']]);
        $catalog = [['الفواكه', 'Fruits', 'مانجو', 'Mango', '24.95', '1 كجم', '1 kg', 1000, 'g'], ['الخضروات', 'Vegetables', 'طماطم', 'Tomatoes', '7.50', '1 كجم', '1 kg', 1000, 'g'], ['العصائر', 'Juices', 'عصير برتقال', 'Orange juice', '12.00', '500 مل', '500 ml', 500, 'ml'], ['تقاطيع الفواكه', 'Fruit cuts', 'بوكس فواكه', 'Fruit box', '35.00', 'علبة', 'Box', 500, 'g'], ['العسل', 'Honey', 'عسل سدر', 'Sidr honey', '85.00', '500 جم', '500 g', 500, 'g'], ['المكسرات', 'Nuts', 'مكسرات مشكلة', 'Mixed nuts', '28.00', '250 جم', '250 g', 250, 'g']];
        $products = [];
        foreach ($catalog as $i => [$ar,$en,$pa,$pe,$price,$va,$ve,$weight,$unit]) {
            $category = Category::firstOrCreate(['name_en' => $en], ['name_ar' => $ar, 'sort_order' => $i + 1]);
            $p = Product::firstOrCreate(['sku' => 'BK-DEMO-'.($i + 1)], ['category_id' => $category->id, 'name_ar' => $pa, 'name_en' => $pe, 'description_ar' => 'منتج تجريبي للاختبار؛ السعر والضريبة قابلان للتعديل قبل الإطلاق.', 'tax_rate' => 15, 'is_featured' => true]);
            $v = $p->variants()->firstOrCreate(['sku' => $p->sku.'-01'], ['name_ar' => $va, 'name_en' => $ve, 'price' => $price, 'weight' => $weight, 'weight_unit' => $unit, 'is_default' => true]);
            $v->branches()->syncWithoutDetaching([$branch->id => ['is_available' => true, 'stock_quantity' => null]]);
            $products[] = $p;
        }
        $v = $products[0]->variants()->firstOrCreate(['sku' => 'BK-DEMO-1-02'], ['name_ar' => '500 جم', 'name_en' => '500 g', 'price' => '12.50', 'weight' => 500, 'weight_unit' => 'g']);
        $v->branches()->syncWithoutDetaching([$branch->id => ['is_available' => true, 'stock_quantity' => null]]);
        foreach ([['درجة النضج', 'Ripeness', 0, ['جاهز للأكل', 'متوسط', 'غير ناضج'], true], ['السكر', 'Sugar', 2, ['بدون', 'خفيف', 'عادي'], true], ['الإضافات', 'Extras', 3, ['عسل', 'مكسرات', 'شوكولاتة'], false]] as [$ar,$en,$idx,$values,$required]) {
            $g = OptionGroup::firstOrCreate(['name_en' => $en], ['name_ar' => $ar, 'selection_type' => $required ? 'single' : 'multiple', 'min_selection' => $required ? 1 : 0, 'max_selection' => $required ? 1 : 3, 'required' => $required]);
            foreach ($values as $i => $value) {
                $g->values()->firstOrCreate(['name_ar' => $value], ['name_en' => $en.' '.($i + 1), 'additional_price' => $required ? 0 : ($i === 1 ? 7 : 5)]);
            }
            $products[$idx]->optionGroups()->syncWithoutDetaching([$g->id]);
        }
        DeliveryZone::firstOrCreate(['branch_id' => $branch->id, 'name' => 'منطقة تجريبية'], ['delivery_fee' => 10, 'minimum_order' => 50, 'free_delivery_above' => 150, 'districts' => ['حي تجريبي'], 'estimated_minutes' => 60]);
        for ($day = 0; $day < 7; $day++) {
            foreach (['10:00:00', '18:00:00', '21:00:00'] as $start) {
                DeliverySlot::firstOrCreate(['branch_id' => $branch->id, 'day_of_week' => $day, 'start_time' => $start], ['end_time' => sprintf('%02d:00:00', (int) $start + 1), 'max_orders' => 15]);
            }
        }
        Coupon::firstOrCreate(['code' => 'WELCOME20'], ['discount_type' => 'percentage', 'discount_value' => 20, 'maximum_discount' => 30, 'minimum_order' => 0, 'usage_limit' => 100, 'usage_per_customer' => 1, 'first_order_only' => true]);
        Banner::firstOrCreate(['title' => 'أهلًا بكم في بساتين الكرز'], ['action_type' => 'category', 'action_id' => (string) $products[0]->category_id]);
    }
}
