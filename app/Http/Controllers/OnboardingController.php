<?php
namespace App\Http\Controllers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
class OnboardingController extends Controller { public function complete(Request $request):RedirectResponse { if(!$request->user()->onboarding_completed_at){$request->user()->forceFill(['onboarding_completed_at'=>now()])->save();} return back()->with('status','Boas-vindas concluídas.'); } }
