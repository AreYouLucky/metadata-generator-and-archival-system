<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Only active admins may open the Horizon dashboard, in every environment.
     * The parent class also lets everyone in on `local`, which we do not want
     * because the dev stack is reachable from the local network.
     */
    protected function authorization(): void
    {
        $this->gate();

        Horizon::auth(fn (Request $request): bool => Gate::check('viewHorizon', [$request->user()]));
    }

    /**
     * Register the Horizon gate.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn (?User $user = null): bool => $user !== null && $user->is_active && $user->isAdmin());
    }
}
