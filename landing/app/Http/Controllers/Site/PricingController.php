<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\SitePage;
use Illuminate\View\View;

class PricingController extends Controller
{
    public function index(): View
    {
        $intro = SitePage::query()
            ->published()
            ->forLocale(app()->getLocale())
            ->where('slug', 'pricing')
            ->first();

        $plans = Plan::query()
            ->active()
            ->orderBy('sort_order')
            ->get();

        $locale = app()->getLocale();

        return view('site.pricing', [
            'intro' => $intro,
            'plans' => $plans,
            'seoCanonical' => landing_url_with_lang(route('site.pricing', absolute: true), $locale),
        ]);
    }
}
