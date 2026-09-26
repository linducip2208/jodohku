<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Services\AuditService;
use App\Services\BrandService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BrandController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Brand::class);
        $query = Brand::latest('id');
        if ($request->user()->role === UserRole::Client) {
            $query->where('id', $request->user()->brand_id);
        }
        $brands = $query->paginate(20);

        return $request->wantsJson()
            ? response()->json($brands)
            : view('admin.brands.index', ['brands' => $brands]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', Brand::class);

        return view('admin.brands.form', ['brand' => new Brand(['primary_color' => '#f43f5e', 'secondary_color' => '#8b5cf6'])]);
    }

    /** Self-service onboarding wizard (4 steps, live preview). */
    public function wizard(Request $request)
    {
        $this->authorize('create', Brand::class);

        return view('admin.brands.wizard');
    }

    /** Save text-only draft to session for landing preview (?preview_brand=draft). */
    public function wizardDraft(Request $request, BrandService $brands)
    {
        $this->authorize('create', Brand::class);
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:80'],
            'tagline' => ['nullable', 'string', 'max:200'],
            'primary_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'secondary_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);
        $request->session()->put('brand_wizard_draft', $data);

        return response()->json(['preview_url' => url('/?preview_brand=draft')]);
    }

    public function store(Request $request, BrandService $brands, AuditService $audit)
    {
        $this->authorize('create', Brand::class);
        $data = $this->validated($request);
        $brand = new Brand([
            'slug' => $this->slug($data['slug'] ?? $data['name']),
            'name' => trim($data['name']),
            'tagline' => $data['tagline'] ?? null,
            'primary_color' => $brands->sanitizeHex($data['primary_color'] ?? null) ?? '#f43f5e',
            'secondary_color' => $brands->sanitizeHex($data['secondary_color'] ?? null) ?? '#8b5cf6',
            'domain' => $data['domain'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'features' => $this->features($request),
        ]);
        $this->storeAssets($request, $brand);
        $brand->save();
        if ($brand->is_active && $request->boolean('is_default')) {
            Brand::where('id', '!=', $brand->id)->update(['is_default' => false]);
            $brand->update(['is_default' => true]);
        }
        $brands->forgetCache($brand);
        $audit->log('admin.brand.created', $request->user(), $brand);

        return $request->wantsJson()
            ? response()->json($brand->fresh(), 201)
            : redirect()->route('admin.brands.edit', $brand)->with('status', 'Brand dibuat ✅');
    }

    public function edit(Request $request, Brand $brand)
    {
        $this->authorize('view', $brand);

        return view('admin.brands.form', ['brand' => $brand]);
    }

    public function update(Request $request, Brand $brand, BrandService $brands, AuditService $audit)
    {
        $this->authorize('update', $brand);
        $data = $this->validated($request, $brand->id);
        $brand->fill([
            'name' => trim($data['name']),
            'tagline' => $data['tagline'] ?? null,
            'primary_color' => $brands->sanitizeHex($data['primary_color'] ?? null) ?? $brand->primary_color,
            'secondary_color' => $brands->sanitizeHex($data['secondary_color'] ?? null) ?? $brand->secondary_color,
            'domain' => $data['domain'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'features' => $this->features($request),
        ]);
        $this->storeAssets($request, $brand);
        if (! $brand->is_active) {
            $brand->is_default = false;
        } elseif ($request->boolean('is_default')) {
            Brand::where('id', '!=', $brand->id)->update(['is_default' => false]);
            $brand->is_default = true;
        }
        $brand->save();
        $brands->forgetCache($brand);
        $audit->log('admin.brand.updated', $request->user(), $brand);

        return $request->wantsJson()
            ? response()->json($brand->fresh())
            : back()->with('status', 'Brand disimpan ✅');
    }

    public function destroy(Request $request, Brand $brand, BrandService $brands, AuditService $audit)
    {
        $this->authorize('delete', $brand);
        Storage::disk('public')->delete([$brand->logo_path, $brand->favicon_path]);
        $audit->log('admin.brand.deleted', $request->user(), $brand);
        $brand->delete();
        $brands->forgetCache($brand);

        return $request->wantsJson()
            ? response()->json(['message' => 'Deleted.'])
            : redirect()->route('admin.brands')->with('status', 'Brand dihapus.');
    }

    /** Download brand package zip (pindah server). */
    public function export(Request $request, Brand $brand, BrandService $brands)
    {
        $this->authorize('view', $brand);
        $tmp = $brands->exportPackage($brand);

        return response()->download($tmp, 'brand-'.$brand->slug.'.zip')->deleteFileAfterSend();
    }

    /** Upload brand package zip. */
    public function import(Request $request, BrandService $brands, AuditService $audit)
    {
        $this->authorize('create', Brand::class);
        $request->validate(['package' => ['required', 'file', 'max:10240', 'mimetypes:application/zip,application/x-zip-compressed,multipart/x-zip']]);
        $file = $request->file('package');
        if (! $file->isValid()) {
            abort(422, 'Upload gagal.');
        }
        try {
            $brand = $brands->importPackage($file->getRealPath(), $request->boolean('activate'));
        } catch (\RuntimeException $e) {
            return $request->wantsJson()
                ? response()->json(['message' => $e->getMessage()], 422)
                : back()->withErrors(['package' => $e->getMessage()]);
        }
        $audit->log('admin.brand.imported', $request->user(), $brand);

        return $request->wantsJson()
            ? response()->json($brand, 201)
            : redirect()->route('admin.brands.edit', $brand)->with('status', 'Paket diimpor ✅');
    }

    /** @return array<string,mixed> */
    protected function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'slug' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9-]+$/', 'unique:brands,slug'.($ignoreId ? ','.$ignoreId : '')],
            'tagline' => ['nullable', 'string', 'max:200'],
            'primary_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'secondary_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'domain' => ['nullable', 'string', 'max:190', 'unique:brands,domain'.($ignoreId ? ','.$ignoreId : '')],
            'logo' => ['nullable', 'file', 'max:2048', 'mimetypes:image/png,image/jpeg,image/webp,image/svg+xml'],
            'favicon' => ['nullable', 'file', 'max:1024', 'mimetypes:image/png,image/jpeg,image/webp,image/svg+xml,image/x-icon,image/vnd.microsoft.icon'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ]);
    }

    /** @return array<string,bool> */
    protected function features(Request $request): array
    {
        $out = [];
        foreach (['taaruf', 'counselor', 'community', 'events', 'gifts', 'boost'] as $key) {
            if ($request->has('features.'.$key)) {
                $out[$key] = $request->boolean('features.'.$key);
            }
        }

        return $out;
    }

    protected function slug(string $raw): string
    {
        $slug = Str::slug($raw);
        $base = $slug !== '' ? $slug : 'brand';
        $i = 0;
        while (Brand::where('slug', $i > 0 ? $base.'-'.$i : $base)->exists()) {
            $i++;
        }

        return $i > 0 ? $base.'-'.$i : $base;
    }

    protected function storeAssets(Request $request, Brand $brand): void
    {
        foreach (['logo' => 'logo_path', 'favicon' => 'favicon_path'] as $input => $attr) {
            $file = $request->file($input);
            if (! $file || ! $file->isValid()) {
                continue;
            }
            if (@getimagesize($file->getRealPath()) === false && $file->getMimeType() !== 'image/svg+xml') {
                abort(422, 'File bukan gambar valid.');
            }
            if ($brand->$attr) {
                Storage::disk('public')->delete($brand->$attr);
            }
            $brand->$attr = $file->store('brands/'.$brand->slug, 'public');
        }
    }
}
