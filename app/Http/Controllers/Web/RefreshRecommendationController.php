<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\RefreshRecommendation;
use App\Services\Refresh\RefreshExecutionService;
use Illuminate\Http\RedirectResponse;

class RefreshRecommendationController extends Controller
{
    public function accept(RefreshRecommendation $refreshRecommendation): RedirectResponse
    {
        $this->authorize('update', $refreshRecommendation->site);

        $refreshRecommendation->update([
            'status' => RefreshRecommendation::STATUS_ACCEPTED,
        ]);

        return back()->with('success', 'Refresh recommendation accepted.');
    }

    public function dismiss(RefreshRecommendation $refreshRecommendation): RedirectResponse
    {
        $this->authorize('update', $refreshRecommendation->site);

        $refreshRecommendation->update([
            'status' => RefreshRecommendation::STATUS_DISMISSED,
        ]);

        return back()->with('success', 'Refresh recommendation dismissed.');
    }

    public function execute(RefreshRecommendation $refreshRecommendation, RefreshExecutionService $refreshExecution): RedirectResponse
    {
        $this->authorize('update', $refreshRecommendation->site);

        $refreshExecution->execute($refreshRecommendation->load('article.site'));

        return back()->with('success', 'Refresh draft generated.');
    }

    public function apply(RefreshRecommendation $refreshRecommendation, RefreshExecutionService $refreshExecution): RedirectResponse
    {
        $this->authorize('update', $refreshRecommendation->site);

        $refreshExecution->applyDraftToReview($refreshRecommendation->load(['article.site', 'runs']));

        return back()->with('success', 'Refresh draft moved back to review.');
    }
}
