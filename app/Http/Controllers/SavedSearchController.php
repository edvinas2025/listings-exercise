<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSavedSearchRequest;
use App\Http\Resources\SavedSearchResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SavedSearchController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        return Inertia::render('SavedSearches/Index', [
            'savedSearches' => SavedSearchResource::collection(
                $user->savedSearches()->orderByDesc('id')->paginate(15)
            ),
        ]);
    }

    public function store(StoreSavedSearchRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $criteria = array_replace([
            'max_price' => null,
            'min_bedrooms' => null,
            'property_type' => null,
            'region' => null,
        ], $request->validated());

        if ($user->savedSearches()->where($criteria)->exists()) {
            throw ValidationException::withMessages([
                'criteria' => 'You already have this saved search.',
            ]);
        }

        $user->savedSearches()->create($criteria);

        return to_route('listings.index', $request->validated())
            ->with('status', 'Search saved.');
    }

    public function delete(Request $request, int $savedSearch): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $user->savedSearches()->findOrFail($savedSearch)->delete();

        return back()->with('status', 'Saved search deleted.');
    }
}
