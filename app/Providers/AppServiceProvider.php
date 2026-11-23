<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Used as route middleware: ->middleware('can:admin')
        Gate::define('admin', fn (User $user) => $user->isAdmin());

        JsonResource::withoutWrapping();
    }
}
