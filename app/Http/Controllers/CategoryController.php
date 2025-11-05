<?php
namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller {
    public function index() {
        // Category ke sath HSN code + GST bhi load karo
        return Category::with('hsnCode')->get();
    }
    public function store(Request $request) {
        return Category::create($request->all());
    }
}
