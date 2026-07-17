<?php

namespace App\Http\Controllers;

use App\Http\Requests\Directory\StoreContactRequest;
use App\Http\Requests\Directory\UpdateContactRequest;
use App\Models\ActivityLog;
use App\Models\Contact;
use App\Services\Directory\IntegrationConnectionTestService;
use App\Support\DirectoryCategories;
use App\Support\DirectoryIndexLayout;
use App\Support\DirectoryIntegrationCatalog;
use Illuminate\Http\Request;

class DirectoryController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Contact::class);

        $filters = [
            'search' => $request->query('search'),
            'type' => $request->query('type'),
            'category' => $request->query('category'),
            'status' => $request->query('status'),
            'claim_channel' => $request->query('claim_channel'),
            'organization' => $request->query('organization'),
            'city' => $request->query('city'),
            'county' => $request->query('county'),
            'sort' => $request->query('sort', 'name'),
            'direction' => $request->query('direction', 'asc'),
        ];

        $contactsQuery = Contact::query()
            ->withCount('clients')
            ->directorySearch($filters['search'])
            ->filterType($filters['type'])
            ->filterStatus($filters['status'])
            ->filterClaimChannel($filters['claim_channel'])
            ->filterOrganization($filters['organization'])
            ->filterCity($filters['city'])
            ->filterCounty($filters['county']);

        $categoryTypes = DirectoryCategories::typesFor($filters['category']);
        if ($categoryTypes !== [] && blank($filters['type'])) {
            $contactsQuery->whereIn('type', $categoryTypes);
        }

        $contacts = $contactsQuery
            ->directorySort($filters['sort'], $filters['direction'])
            ->paginate(15)
            ->withQueryString();

        $types = Contact::types();
        $categories = DirectoryCategories::all();
        $typeCounts = Contact::query()
            ->selectRaw('type, COUNT(*) as aggregate')
            ->groupBy('type')
            ->pluck('aggregate', 'type');
        $hasFilters = $this->hasActiveFilters($filters);
        $filterSummary = $this->filterSummary($filters);
        $activeCategory = collect($categories)->first(function ($category) use ($filters) {
            if ($filters['category'] === $category['key']) {
                return true;
            }

            return filled($filters['type']) && in_array($filters['type'], $category['types'], true);
        }) ?? DirectoryCategories::forKey($filters['category']);

        $indexLayout = DirectoryIndexLayout::forCategory($activeCategory);
        $categoryClientTotal = $activeCategory
            ? Contact::query()
                ->when($categoryTypes !== [], fn ($query) => $query->whereIn('type', $categoryTypes))
                ->withCount('clients')
                ->get()
                ->sum('clients_count')
            : Contact::query()->withCount('clients')->get()->sum('clients_count');

        session(['directory.filters' => $this->persistableFilters($filters)]);

        return view('pages.directory.index', compact(
            'contacts',
            'filters',
            'types',
            'categories',
            'typeCounts',
            'hasFilters',
            'filterSummary',
            'activeCategory',
            'indexLayout',
            'categoryClientTotal',
        ), [
            'title' => 'Directories',
        ]);
    }

    public function create()
    {
        $this->authorize('create', Contact::class);

        $types = Contact::types();

        return view('pages.directory.create', compact('types'), ['title' => 'Add Directory Contact']);
    }

    public function store(StoreContactRequest $request)
    {
        Contact::create($this->contactAttributes($request->validated()));

        return redirect()
            ->route('directory', session('directory.filters', []))
            ->with('success', 'Contact added to directory.');
    }

    public function show($id)
    {
        $contact = Contact::withoutGlobalScopes()
            ->withCount('clients')
            ->with([
                'clients' => fn ($query) => $query->limit(5),
                'connectionHealth',
                'parentContact',
                'childContacts' => fn ($query) => $query->limit(3),
            ])
            ->findOrFail($id);
        $this->authorize('view', $contact);

        $auditLogs = ActivityLog::query()
            ->where('subject_type', Contact::class)
            ->where('subject_id', $contact->id)
            ->with('user')
            ->latest()
            ->limit(5)
            ->get();

        $createdBy = ActivityLog::query()
            ->where('subject_type', Contact::class)
            ->where('subject_id', $contact->id)
            ->where('action', 'like', 'Created%')
            ->with('user')
            ->first()?->user;

        $category = DirectoryCategories::forType($contact->type);
        $showProfile = \App\Support\DirectoryShowLayout::forContact($contact);

        return view('pages.directory.show', compact('contact', 'auditLogs', 'createdBy', 'category', 'showProfile'), [
            'title' => $contact->name,
        ]);
    }

    public function edit($id)
    {
        $contact = Contact::withoutGlobalScopes()->findOrFail($id);
        $this->authorize('update', $contact);

        $types = Contact::types();

        $createdBy = ActivityLog::query()
            ->where('subject_type', Contact::class)
            ->where('subject_id', $contact->id)
            ->where('action', 'like', 'Created%')
            ->with('user')
            ->first()?->user;

        return view('pages.directory.edit', compact('contact', 'types', 'createdBy'), [
            'title' => 'Edit '.$contact->name,
        ]);
    }

    public function update(UpdateContactRequest $request, $id)
    {
        $contact = Contact::withoutGlobalScopes()->findOrFail($id);
        $contact->update($this->contactAttributes($request->validated()));

        return redirect()
            ->route('directory.show', $contact->id)
            ->with('success', 'Directory contact updated.');
    }

    public function destroy(Request $request, $id)
    {
        $contact = Contact::withoutGlobalScopes()->findOrFail($id);
        $this->authorize('delete', $contact);
        $contact->delete();

        $returnFilters = $request->input('return_filters')
            ? json_decode($request->input('return_filters'), true)
            : session('directory.filters', []);

        return redirect()
            ->route('directory', is_array($returnFilters) ? $returnFilters : [])
            ->with('success', 'Directory contact removed.');
    }

    public function testConnection($id, IntegrationConnectionTestService $connectionTest)
    {
        $contact = Contact::withoutGlobalScopes()->findOrFail($id);
        $this->authorize('update', $contact);

        if (! $contact->isIntegrationCard()) {
            return redirect()
                ->route('directory.show', $contact->id)
                ->with('error', 'This directory entry is not linked to an API integration.');
        }

        $result = $connectionTest->testContact($contact);

        return redirect()
            ->route('directory.show', $contact->id)
            ->with(
                $result['success'] ? 'success' : 'error',
                $result['message']
            );
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function contactAttributes(array $validated): array
    {
        $attributes = collect($validated)->only([
            'name',
            'type',
            'job_title',
            'phone',
            'fax',
            'email',
            'clinic_name',
            'provider_id',
            'claim_channel',
            'contracted_rate',
            'parent_contact_id',
            'address_line1',
            'address_line2',
            'city',
            'state',
            'county',
            'zip',
            'notes',
            'is_active',
            'integration_slug',
            'integration_credential_key',
            'data_flow',
            'app_area',
            'owning_agent',
        ])->all();

        if (filled($attributes['integration_slug'] ?? null)) {
            $defaults = DirectoryIntegrationCatalog::defaultsForSlug($attributes['integration_slug']);
            foreach ($defaults as $key => $value) {
                if (blank($attributes[$key] ?? null) && filled($value)) {
                    $attributes[$key] = $value;
                }
            }
        }

        if (! array_key_exists('is_active', $attributes)) {
            $attributes['is_active'] = true;
        }

        $locationId = session('selected_location_id');
        if ($locationId) {
            $attributes['location_id'] = $locationId;
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function hasActiveFilters(array $filters): bool
    {
        return collect($filters)->except(['sort', 'direction'])
            ->filter(fn ($value) => filled($value))
            ->isNotEmpty();
    }
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, string>
     */
    private function filterSummary(array $filters): array
    {
        $summary = [];

        if (filled($filters['search'])) {
            $summary['search'] = 'Search: '.$filters['search'];
        }
        if (filled($filters['type'])) {
            $summary['type'] = 'Type: '.$filters['type'];
        }
        if (filled($filters['status'])) {
            $summary['status'] = 'Status: '.ucfirst($filters['status']);
        }
        if (filled($filters['organization'])) {
            $summary['organization'] = 'Organization: '.$filters['organization'];
        }
        if (filled($filters['city'])) {
            $summary['city'] = 'City: '.$filters['city'];
        }
        if (filled($filters['county'])) {
            $summary['county'] = 'County: '.$filters['county'];
        }
        if (filled($filters['category'])) {
            $category = DirectoryCategories::forKey($filters['category']);
            $label = $category['label'] ?? $filters['category'];
            $summary['category'] = 'Category: '.$label;
        }
        if (filled($filters['claim_channel'])) {
            $summary['claim_channel'] = 'Channel: '.ucfirst(str_replace('_', ' ', $filters['claim_channel']));
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function persistableFilters(array $filters): array
    {
        return collect($filters)
            ->only(['search', 'type', 'category', 'status', 'claim_channel', 'organization', 'city', 'county'])
            ->filter(fn ($value) => filled($value))
            ->all();
    }
}
