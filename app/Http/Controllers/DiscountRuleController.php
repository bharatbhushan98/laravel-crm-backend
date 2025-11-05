<?php

namespace App\Http\Controllers;

use App\Models\DiscountRule;
use Illuminate\Http\Request;

class DiscountRuleController extends Controller
{
// app/Http/Controllers/DiscountRuleController.php

public function getRule()
{
    $rule = DiscountRule::first();

    if (!$rule) {
        return response()->json([
            'message' => 'No discount rule found. Please configure it first.'
        ], 404);
    }

    return response()->json([
        'rule' => $rule  // ✅ Yeh add karo!
    ]);
}

public function saveRule(Request $request)
{
    $validated = $request->validate([
        'min_order_amount' => 'required|numeric|min:0',
        'discount_type' => 'required|in:percentage,fixed',
        'discount_value' => 'required|numeric|min:0',
    ]);

    $rule = DiscountRule::firstOrNew([]);
    $rule->fill($validated);
    $rule->save();

    return response()->json([
        'message' => 'Discount rule saved successfully.',
        'rule' => $rule  // ✅ Yeh bhi return karo!
    ]);
}
}
