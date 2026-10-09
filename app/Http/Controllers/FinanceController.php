<?php

namespace App\Http\Controllers;

use App\Enums\BudgetStatus;
use App\Enums\ReconciliationExceptionStatus;
use App\Enums\ReconciliationStatus;
use App\Models\Budget;
use App\Models\FinancialTransaction;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class FinanceController extends Controller
{
    public function __invoke(): Response
    {
        $this->authorize('viewAny', FinancialTransaction::class);
        $user = request()->user();
        $scope = fn (Builder $query): Builder => $query
            ->when(! $user->isGlobalAdministrator(), fn (Builder $query) => $query
                ->whereHas('activity', fn (Builder $activity) => $activity->where('unit_id', $user->unit_id)));
        $base = FinancialTransaction::query()->tap($scope);

        $transactions = (clone $base)
            ->with([
                'activity:id,unit_id,budget_id,code,title',
                'budgetLine:id,budget_version_id,account_code,description,subtotal',
                'reconciliation.exceptions.owner:id,name',
                'reconciliation.exceptions.requester:id,name',
                'reconciliation.exceptions.decisionMaker:id,name',
            ])
            ->latest('transaction_date')
            ->paginate(20)
            ->withQueryString();

        $budgets = Budget::query()
            ->when(! $user->isGlobalAdministrator(), fn (Builder $query) => $query->where('unit_id', $user->unit_id))
            ->where('status', BudgetStatus::Approved)
            ->with(['currentVersionRecord.lines', 'activities:id,budget_id,code'])
            ->get(['id', 'code', 'current_version']);

        return Inertia::render('finance/index', [
            'transactions' => $transactions,
            'budgetLines' => $budgets->flatMap(fn (Budget $budget) => $budget->currentVersionRecord?->lines->map(fn ($line) => [
                ...$line->only(['id', 'account_code', 'description', 'subtotal']),
                'budget_id' => $budget->id,
                'budget_code' => $budget->code,
            ]) ?? [])->values(),
            'metrics' => [
                'total' => (clone $base)->sum('amount'),
                'matched' => (clone $base)->whereIn('reconciliation_status', [ReconciliationStatus::Matched, ReconciliationStatus::Resolved])->count(),
                'exceptions' => (clone $base)->where('reconciliation_status', ReconciliationStatus::Exception)->count(),
                'openExceptions' => (clone $base)->whereHas('reconciliation.exceptions', fn (Builder $query) => $query->whereIn('status', ReconciliationExceptionStatus::BLOCKING_VALUES))->count(),
            ],
            'canReconcile' => $user->can('finance.reconcile'),
            'canResolveException' => $user->can('finance.resolve-exception'),
            'canApproveException' => $user->can('finance.approve-exception'),
        ]);
    }
}
