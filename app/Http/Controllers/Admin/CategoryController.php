<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Traits\PreventsSourcedResourceDeletion;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class CategoryController extends Controller
{
    use PreventsSourcedResourceDeletion;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        abort_if(request()->user()->is('salesman'), 403, 'You don\'t have permission.');

        return $this->view([
            'categories' => Category::nested(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): void
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        abort_if(request()->user()->is('salesman'), 403, 'You don\'t have permission.');
        if ($request->has('categories')) {
            $data = $request->validate([
                'categories' => 'required|array',
            ]);

            collect($data['categories'])
                ->each(function ($data): void {
                    Category::find($data['id'])->update($data);
                });

            cache()->forget('categories:nested');

            return true;
        }
        $data = $request->validate([
            'parent_id' => 'nullable|integer',
            'name' => 'required|unique:categories',
            'slug' => 'required|unique:categories',
            'base_image' => 'nullable|integer',
        ]);

        $data['image_id'] = Arr::pull($data, 'base_image');

        Category::create($data);

        return back()->with('success', 'Category Has Been Created.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category): void
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Category $category): void
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Category $category)
    {
        abort_if(request()->user()->is('salesman'), 403, 'You don\'t have permission.');
        $data = $request->validate([
            'parent_id' => 'nullable|integer',
            'name' => 'required|unique:categories,name,'.$category->id,
            'slug' => 'required|unique:categories,slug,'.$category->id,
            'base_image' => 'nullable|integer',
        ]);

        $data['image_id'] = Arr::pull($data, 'base_image');

        $category->update($data);

        return back()->with('success', 'Category Has Been Updated.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(Category $category)
    {
        abort_if(request()->user()->is('salesman'), 403, 'You don\'t have permission.');

        if (($result = $this->preventSourcedResourceDeletion($category)) !== true) {
            return $result;
        }

        $category->delete();

        return back()->with('success', 'Category Has Been Deleted.');
    }
}
