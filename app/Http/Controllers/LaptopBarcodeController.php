<?php

namespace App\Http\Controllers;

use App\Models\Laptop;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class LaptopBarcodeController extends Controller
{
    public function show(Laptop $laptop): View
    {
        return view('laptops.barcode-print', [
            'laptops' => Laptop::query()
                ->with(['brand', 'laptopModel', 'processor', 'generation'])
                ->whereKey($laptop->id)
                ->get(),
        ]);
    }

    public function bulk(Request $request): View
    {
        $ids = array_filter(explode(',', (string) $request->query('ids')));

        return view('laptops.barcode-print', [
            'laptops' => Laptop::query()
                ->with(['brand', 'laptopModel', 'processor', 'generation'])
                ->whereIn('id', $ids)
                ->get(),
        ]);
    }
}
