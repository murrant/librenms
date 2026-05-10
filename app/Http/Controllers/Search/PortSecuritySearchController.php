<?php

namespace App\Http\Controllers\Search;

use App\Http\Controllers\Controller;
use App\Models\Port;

class PortSecuritySearchController extends Controller
{
    /**
     * Display the port security search page.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $this->authorize('viewAny', Port::class);

        return view('search.portsecurity', [
            'pagetitle' => __('Port Security'),
        ]);
    }
}
