<?php

use App\Models\Grade;
use App\Models\Offering;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group(['middleware' => ['auth', 'role:admin']], function () {

    Route::get('grades', function (Request $request) {
        return Grade::all();
    })->name('api.grades.index');

    Route::get('offerings', function (Request $request) {
        $offerings = Offering::where('schoolyear_id', $request->schoolyear_id)->where('grade_id', $request->grade_id)->get();
        return $offerings;
    })->name('api.offerings.index');

    Route::post('offerings', function (Request $request) {

        $request->validate([
            'activated' => 'required',
            'grade_id' => 'required',
            'name' => 'string|required',
            'schoolyear_id' => 'required'
        ]);


        $offering = Offering::create([
            'activated' => $request->activated,
            'grade_id' => $request->grade_id,
            'name' => $request->name,
            'schoolyear_id' => $request->schoolyear_id
        ]);

        return $offering;
    })->name('api.offerings.store');

    Route::post('offerings/toggle', function (Request $request) {
        $request->validate([
            'offering_id' => 'required|exists:offerings,id'
        ]);

        $offering = Offering::find($request->offering_id);
        $offering->activated = !$offering->activated;
        $offering->save();

        return $offering;
    })->name('api.offerings.toggle');

    Route::delete('offerings/{offering}', function (Offering $offering): bool {

        $offering->delete();

        return true;
    })->name('api.offerings.destroy');
});
