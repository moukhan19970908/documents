<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\Chat;
use App\Models\Document;
use App\Models\Workflow;
use App\Models\TripRequest;
use App\Models\VacationRequest;
use App\Policies\ChatPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\WorkflowPolicy;
use App\Policies\TripRequestPolicy;
use App\Policies\VacationRequestPolicy;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Document::class, DocumentPolicy::class);
        Gate::policy(Workflow::class, WorkflowPolicy::class);
        Gate::policy(Chat::class, ChatPolicy::class);
        Gate::policy(TripRequest::class, TripRequestPolicy::class);
        Gate::policy(VacationRequest::class, VacationRequestPolicy::class);
        // URL::forceScheme('https');
    }
}
