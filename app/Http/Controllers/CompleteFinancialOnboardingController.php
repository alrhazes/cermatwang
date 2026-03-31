<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CompleteFinancialOnboardingController extends Controller
{
    /**
     * Mark financial onboarding as finished so the assistant switches to normal coaching mode.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $request->user()->forceFill([
            'onboarding_completed_at' => now(),
        ])->save();

        return redirect()->route('chat');
    }
}
