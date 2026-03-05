<?php

namespace App\Http\Controllers\admin;
use App\Models\App;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class AppController extends Controller
{
    public function index()
    {
        $apps = App::all();
        return view('admin.pages.apps.index', compact('apps'));
    }

    public function store(Request $request)
    {
        App::create([
            'name'      => $request->name,
            'slug'      => Str::slug($request->name),
            'api_end_point' => $request->api_end_point,
            'is_active' => (int)$request->is_active,
        ]);

        return redirect()->route('apps.section')->with('success', 'App created successfully!');
    }

    public function update(Request $request, $id)
    {
        $app = App::findOrFail($id);

        $app->update([
            'name'      => $request->name,
            'slug'      => \Illuminate\Support\Str::slug($request->name),
            'api_end_point' => $request->api_end_point,
            'is_active' => $request->is_active,
        ]);

        return redirect()->route('apps.section')->with('success', 'App updated successfully!');
    }

    public function destroy($id)
    {
         $app = App::findOrFail($id);
         $app->delete();
        return redirect()->route('apps.section')->with('success', 'App deleted successfully!');
    }
}
