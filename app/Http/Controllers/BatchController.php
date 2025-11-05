<?php
namespace App\Http\Controllers;

use App\Models\Batch;
use Illuminate\Http\Request;

class BatchController extends Controller {
    public function index() {
        return Batch::with('product')->get();
    }

    public function store(Request $request) {
        return Batch::create($request->all());
    }
}
