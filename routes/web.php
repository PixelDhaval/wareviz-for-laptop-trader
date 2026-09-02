<?php

use App\Http\Controllers\LaptopBarcodeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->prefix('laptops')->name('laptops.')->group(function () {
    Route::get('barcodes/print', [LaptopBarcodeController::class, 'bulk'])->name('barcodes.print');
    Route::get('{laptop}/barcode', [LaptopBarcodeController::class, 'show'])->name('barcode');
});
