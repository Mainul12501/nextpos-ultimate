<?php

namespace App\Http\Controllers;

use App\Models\RentalCategory;
use App\Models\RentalItem;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Rental categories (Apartment, Shop, Generator, Camera…). A category may
 * carry a default item type, which pre-selects the type on the item form.
 */
class RentalCategoryController extends Controller
{
    private const SORTABLE = ['id', 'name', 'type', 'created_at'];

    public function index(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', RentalCategory::class);

        $perPage = (int) ($request->limit ?? 10);
        $page = max(1, (int) $request->get('page', 1));
        $order = in_array($request->SortField, self::SORTABLE, true) ? $request->SortField : 'id';
        $dir = strtolower((string) $request->SortType) === 'asc' ? 'asc' : 'desc';

        $query = RentalCategory::whereNull('deleted_at')
            ->withCount(['items' => fn ($q) => $q->whereNull('deleted_at')]);

        if ($request->filled('search')) {
            $query->where('name', 'LIKE', '%' . $request->search . '%');
        }

        $totalRows = $query->count();
        if ($perPage === -1) {
            $perPage = max(1, $totalRows);
        }

        $categories = $query->orderBy($order, $dir)
            ->offset(($page * $perPage) - $perPage)
            ->limit($perPage)
            ->get(['id', 'name', 'type', 'description', 'created_at']);

        return response()->json(['categories' => $categories, 'totalRows' => $totalRows]);
    }

    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', RentalCategory::class);

        RentalCategory::create($this->validated($request));

        return response()->json(['success' => true]);
    }

    public function update(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', RentalCategory::class);

        RentalCategory::whereNull('deleted_at')->findOrFail($id)->update($this->validated($request));

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'delete', RentalCategory::class);

        $category = RentalCategory::whereNull('deleted_at')->findOrFail($id);
        $this->guardUnused([$category->id]);
        $category->delete();

        return response()->json(['success' => true]);
    }

    public function deleteBySelection(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'delete', RentalCategory::class);

        $ids = (array) $request->selectedIds;
        $this->guardUnused($ids);
        RentalCategory::whereIn('id', $ids)->delete();

        return response()->json(['success' => true]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:191',
            'type' => ['nullable', Rule::in(RentalItem::TYPES)],
            'description' => 'nullable|string|max:2000',
        ]);

        return [
            'name' => $data['name'],
            'type' => $data['type'] ?? null,
            'description' => $data['description'] ?? null,
        ];
    }

    /** A category still holding items can't be removed — the items would lose it silently. */
    private function guardUnused(array $ids): void
    {
        $used = RentalItem::whereNull('deleted_at')->whereIn('rental_category_id', $ids)->exists();

        if ($used) {
            abort(response()->json([
                'message' => 'This category still has rental items. Move or delete them first.',
            ], 422));
        }
    }
}
