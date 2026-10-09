<?php

namespace App\Http\Controllers;

use App\Actions\BuildPortfolioViewAction;
use App\Http\Requests\PortfolioIndexRequest;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class PortfolioController extends Controller
{
    public function __invoke(PortfolioIndexRequest $request, BuildPortfolioViewAction $buildPortfolio): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('portfolio/index', $buildPortfolio->handle($user, $request->validated()));
    }
}
