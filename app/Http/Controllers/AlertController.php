<?php

namespace App\Http\Controllers;

use App\Http\Resources\AlertResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AlertController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        return Inertia::render('Alerts/Index', [
            'alerts' => AlertResource::collection(
                $user->alerts()->with('listing.branch')->orderByDesc('id')->paginate(15)
            ),
        ]);
    }
}
