<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\Ticket;
use App\Models\Task;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Http\Controllers\RoadMap\DataController;
use App\Http\Controllers\Auth\OidcAuthController;

// -------------------------------------------------------------
// AUTENTICACIÓN Y FLUJO
// -------------------------------------------------------------
Route::get('/tickets/share/{ticket:code}', function (Ticket $ticket) {
    return redirect()->to(route('filament.onetask.resources.tickets.view', $ticket));
})->name('filament.onetask.resources.tickets.share');

Route::get('/validate-account/{user:creation_token}', function (User $user) {
    return view('validate-account', compact('user'));
})->name('validate-account')->middleware(['web', \Filament\Http\Middleware\SetUpPanel::class . ':onetask', DispatchServingFilamentEvent::class]);

Route::redirect('/login-redirect', '/login')->name('login');
Route::get('/verify-email-redirect', fn () => redirect()->to(\Filament\Facades\Filament::getPanel('onetask')->getEmailVerificationPromptUrl()))
    ->middleware('auth')->name('verification.notice');

Route::prefix('oidc')->name('oidc.')->group(function () {
    Route::get('redirect', [OidcAuthController::class, 'redirect'])->name('redirect');
    Route::get('callback', [OidcAuthController::class, 'callback'])->name('callback');
});

// -------------------------------------------------------------
// DATOS / JSON
// -------------------------------------------------------------
Route::get('road-map/data/{project}', [DataController::class, 'data'])
    ->middleware(['auth', \Filament\Http\Middleware\SetUpPanel::class . ':onetask', 'verified', \Jeffgreco13\FilamentBreezy\Middleware\MustTwoFactor::class])
    ->name('road-map.data');

// -------------------------------------------------------------
// FILAMENT / TAREAS
// -------------------------------------------------------------
Route::get('/tasks/{task}', function (Task $task) {
    return redirect()->route('filament.onetask.resources.tasks.view', ['record' => $task->id]);
})->middleware(['auth', \Filament\Http\Middleware\SetUpPanel::class . ':onetask', 'verified', \Jeffgreco13\FilamentBreezy\Middleware\MustTwoFactor::class, 'can:view,task'])->name('tasks.show');

// Downloads always belong to an authorized task; no user-supplied filesystem routes.
Route::get('/tasks/{task}/files/{field}', [\App\Http\Controllers\TaskFileController::class, 'show'])
    ->middleware(['auth', \Filament\Http\Middleware\SetUpPanel::class . ':onetask', 'verified', \Jeffgreco13\FilamentBreezy\Middleware\MustTwoFactor::class])->name('tasks.files');
