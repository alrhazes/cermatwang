<?php

namespace App\Http\Controllers;

use App\Support\FinancialOnboarding;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();
        $needsOnboarding = $user->needsFinancialOnboarding();

        return Inertia::render('chat', [
            'needsFinancialOnboarding' => $needsOnboarding,
            'chatWelcome' => $needsOnboarding
                ? FinancialOnboarding::welcomeMessage()
                : 'Welcome back. I’m working from what you’ve already told me in this chat and anything saved on your profile—so we won’t start from zero. What’s most useful right now: tightening this month’s cashflow, a debt or card you want a plan for, or a big expense coming up?',
        ]);
    }
}
