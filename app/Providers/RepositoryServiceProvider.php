<?php

namespace App\Providers;

use App\Repositories\Eloquent\QuizRepository;
use App\Repositories\Eloquent\QuizSessionRepository;
use App\Repositories\Eloquent\QuizSlideVisitRepository;
use App\Repositories\Interfaces\QuizRepositoryInterface;
use App\Repositories\Interfaces\QuizSessionRepositoryInterface;
use App\Repositories\Interfaces\QuizSlideVisitRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(QuizRepositoryInterface::class, QuizRepository::class);
        $this->app->bind(QuizSessionRepositoryInterface::class, QuizSessionRepository::class);
        $this->app->bind(QuizSlideVisitRepositoryInterface::class, QuizSlideVisitRepository::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
