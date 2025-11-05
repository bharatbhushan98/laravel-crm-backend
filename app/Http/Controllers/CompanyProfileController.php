<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use Illuminate\Http\Request;

class CompanyProfileController extends Controller
{
    // Show company profile (sirf ek record expect karte hain)
    public function index()
    {
        $company = CompanyProfile::first();
        return response()->json($company);
    }

    // Create company profile
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'gst_number'  => 'nullable|string|max:255',
            'address'     => 'nullable|string',
            'email'       => 'nullable|email|max:255',
            'phone'       => 'nullable|string|max:20',
            'bank_details'=> 'nullable|string',
            'logo'        => 'nullable|string',
        ]);

        // Sirf ek hi profile ho sakti hai
        if (CompanyProfile::exists()) {
            return response()->json(['error' => 'Company profile already exists.'], 400);
        }

        $company = CompanyProfile::create($data);

        return response()->json($company, 201);
    }

    // Update company profile
    public function update(Request $request, $id)
    {
        $company = CompanyProfile::findOrFail($id);

        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'gst_number'  => 'nullable|string|max:255',
            'address'     => 'nullable|string',
            'email'       => 'nullable|email|max:255',
            'phone'       => 'nullable|string|max:20',
            'bank_details'=> 'nullable|string',
            'logo'        => 'nullable|string',
        ]);

        $company->update($data);

        return response()->json($company);
    }

    // Delete company profile
    public function destroy($id)
    {
        $company = CompanyProfile::findOrFail($id);
        $company->delete();

        return response()->json(['message' => 'Company profile deleted successfully']);
    }
}