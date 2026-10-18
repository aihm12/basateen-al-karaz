<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchProductVariant;
use App\Models\Category;
use App\Models\OptionGroup;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function index(Request $r): View
    {
        $r->validate(['q' => 'nullable|string|max:100']);

        return view('admin.products', ['products' => Product::with('category', 'variants')->when($r->q, fn ($q, $term) => $q->where(fn ($q) => $q->where('name_ar', 'like', '%'.$term.'%')->orWhere('sku', 'like', '%'.$term.'%')))->latest('id')->paginate(16)->withQueryString(), 'categories' => Category::orderBy('sort_order')->get()]);
    }

    public function edit(?Product $product = null): View
    {
        $product ??= new Product(['is_active' => false, 'tax_rate' => 0]);
        $product->load('variants', 'images', 'optionGroups');

        return view('admin.product', ['product' => $product, 'categories' => Category::orderBy('sort_order')->get(), 'groups' => OptionGroup::with('values')->get(), 'branches' => Branch::all(), 'stocks' => $product->exists ? BranchProductVariant::whereIn('product_variant_id', $product->variants->pluck('id'))->get()->keyBy(fn ($s) => $s->product_variant_id.'-'.$s->branch_id) : collect()]);
    }

    public function save(Request $r, ?Product $product = null): RedirectResponse
    {
        $product ??= new Product;
        $data = $r->validate([
            'name_ar' => 'required|string|max:255', 'name_en' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id', 'sku' => ['required', 'string', 'max:100', Rule::unique('products')->ignore($product->id)],
            'description_ar' => 'nullable|string|max:5000', 'description_en' => 'nullable|string|max:5000', 'tax_rate' => 'required|numeric|min:0|max:100',
            'is_active' => 'required|boolean', 'is_featured' => 'required|boolean', 'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120|dimensions:max_width=5000,max_height=5000',
            'option_groups' => 'nullable|array', 'option_groups.*' => 'integer|exists:option_groups,id',
        ]);
        if ($r->boolean('is_active') && (! $product->exists || ! $product->variants()->where('is_active', true)->exists())) {
            return back()->withErrors(['is_active' => 'أضف حجمًا فعالًا وسعره قبل إظهار المنتج للعملاء.'])->withInput();
        }
        unset($data['image'],$data['option_groups']);
        DB::transaction(function () use ($r, $product, $data) {
            $product->fill($data)->save();
            $product->optionGroups()->sync($r->input('option_groups', []));
            if ($r->hasFile('image')) {
                $image = $r->file('image');
                $name = Str::uuid().'.'.$image->extension();
                $diskName = config('filesystems.catalog_disk');
                if ($diskName) {
                    $disk = Storage::disk($diskName);
                    $stored = $disk->putFileAs('catalog', $image, $name);
                    if ($stored === false) {
                        throw ValidationException::withMessages(['image' => 'تعذّر حفظ الصورة. حاول مرة أخرى.']);
                    }
                    $path = $disk->url($stored);
                } else {
                    if (app()->isProduction()) {
                        throw ValidationException::withMessages(['image' => 'لم يتم إعداد التخزين الدائم للصور بعد.']);
                    }
                    $image->move(public_path('images/uploads'), $name);
                    $path = '/images/uploads/'.$name;
                }
                $product->update(['main_image' => $path]);
                $product->images()->create(['image_url' => $path, 'sort_order' => $product->images()->count()]);
            }
        });

        return redirect()->route('admin.products.edit', $product)->with('success', 'تم حفظ المنتج. التغييرات تظهر بعد تحديث تطبيق العميل.');
    }

    public function variant(Request $r, Product $product): RedirectResponse
    {
        $data = $r->validate(['id' => ['nullable', 'integer', Rule::exists('product_variants')->where('product_id', $product->id)],
            'name_ar' => 'required|string|max:255', 'name_en' => 'required|string|max:255', 'sku' => ['required', 'string', 'max:100', Rule::unique('product_variants')->ignore($r->input('id'))],
            'price' => 'required|numeric|min:0|max:999999.99', 'weight' => 'nullable|numeric|min:0|max:999999', 'weight_unit' => ['nullable', Rule::in(['g', 'kg', 'ml', 'l', 'piece'])],
            'is_active' => 'required|boolean']);
        $id = $data['id'] ?? null;
        unset($data['id']);
        $variant = $id ? $product->variants()->findOrFail($id) : new ProductVariant(['product_id' => $product->id]);
        $variant->fill($data)->save();

        return back()->with('success', 'تم حفظ الحجم والسعر. راجع مخزون الفروع أدناه.');
    }

    public function stock(Request $r, Product $product): RedirectResponse
    {
        $data = $r->validate(['variant_id' => ['required', Rule::exists('product_variants', 'id')->where('product_id', $product->id)], 'branch_id' => 'required|exists:branches,id', 'stock_quantity' => 'nullable|integer|min:0|max:1000000', 'is_available' => 'required|boolean', 'previous' => 'nullable|string|max:30']);
        DB::transaction(function () use ($data) {
            $row = BranchProductVariant::firstOrCreate(['branch_id' => $data['branch_id'], 'product_variant_id' => $data['variant_id']], ['is_available' => false]);
            $row = BranchProductVariant::whereKey($row->id)->lockForUpdate()->firstOrFail();
            $current = $row->stock_quantity === null ? '' : (string) $row->stock_quantity;
            if ($current !== ($data['previous'] ?? '')) {
                throw ValidationException::withMessages(['stock_quantity' => 'تغيّر المخزون منذ فتح الصفحة. حدّثها قبل الحفظ.']);
            }
            $row->update(['stock_quantity' => $data['stock_quantity'] ?? null, 'is_available' => $data['is_available']]);
        });

        return back()->with('success', 'تم تحديث مخزون الفرع.');
    }

    public function image(Request $r, Product $product, ProductImage $image): RedirectResponse
    {
        abort_unless($image->product_id === $product->id, 404);
        $r->validate(['action' => ['required', Rule::in(['primary', 'remove'])]]);
        if ($r->action === 'primary') {
            $product->update(['main_image' => $image->image_url]);
        } else {
            if ($product->main_image === $image->image_url) {
                $product->update(['main_image' => null]);
            }
            $image->delete();
        }

        return back()->with('success', 'تم تحديث صور المنتج.');
    }

    public function categories(): View
    {
        return view('admin.categories', ['categories' => Category::orderBy('sort_order')->get()]);
    }

    public function category(Request $r, ?Category $category = null): RedirectResponse
    {
        $data = $r->validate(['name_ar' => 'required|string|max:255', 'name_en' => 'required|string|max:255', 'sort_order' => 'required|integer|min:0|max:10000', 'is_active' => 'required|boolean']);
        ($category ?? new Category)->fill($data)->save();

        return back()->with('success', 'تم حفظ القسم.');
    }
}
