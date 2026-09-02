<?php

use App\Filament\Imports\LaptopImporter;
use App\Models\Brand;
use App\Models\Laptop;
use App\Models\LaptopModel;
use App\Models\Processor;
use App\Models\Shipment;
use App\Models\User;
use Filament\Actions\Imports\Jobs\ImportCsv;
use Filament\Actions\Imports\Models\Import;

test('the real packing list csv imports laptops into a shipment', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $shipment = Shipment::factory()->create(['code' => 'SHP-USED-001']);

    $csvPath = base_path('wareviz-used computer packing list.csv');
    $handle = fopen($csvPath, 'r');
    $header = fgetcsv($handle);
    $seen = [];
    foreach ($header as $i => $name) {
        if (isset($seen[$name])) {
            $header[$i] = $name.'_'.$i;
        }
        $seen[$name] = true;
    }
    $rows = [];
    while (($line = fgetcsv($handle)) !== false) {
        $rows[] = array_combine($header, $line);
    }
    fclose($handle);

    $import = Import::create([
        'file_name' => 'wareviz-used computer packing list.csv',
        'file_path' => $csvPath,
        'importer' => LaptopImporter::class,
        'total_rows' => count($rows),
        'user_id' => $user->id,
    ]);

    $columnMap = [
        'serial_no' => 'SN',
        'brand' => 'BRAND',
        'model' => 'MODEL',
        'processor' => 'CORE',
        'generation' => 'GEN',
        'ram_gb' => 'RAM',
        'storage_gb' => 'HARD',
        'is_battery_ok' => 'BATTERY',
        'is_lcd_ok' => 'LCD',
        'is_bezel_ok' => 'BAZZEL',
        'is_top_cover_ok' => 'TOP',
        'is_body_ok' => 'BODY',
        'is_back_cover_ok' => 'BACK COVER',
        'is_keyboard_ok' => 'KEYBOARD',
        'is_touchpad_ok' => 'CLICK',
        'builtin_ram_and_hard' => 'BUILT IN RAM & HARD',
        'issues' => 'ISSUES',
    ];

    ImportCsv::dispatchSync($import, $rows, $columnMap, ['shipment_id' => $shipment->id]);

    expect(Laptop::query()->count())->toBe(count($rows))
        ->and($shipment->laptops()->count())->toBe(count($rows))
        ->and(Brand::query()->pluck('name')->sort()->values()->all())
        ->toBe(['Accer', 'Dell', 'Dynabook', 'Hp', 'Lenovo', 'Toshiba'])
        ->and(Processor::query()->where('name', 'I5')->exists())->toBeTrue()
        ->and(LaptopModel::query()->where('name', '3540')->exists())->toBeTrue();

    $firstRow = Laptop::query()->where('serial_no', '547')->first();
    expect($firstRow)->not->toBeNull()
        ->and($firstRow->brand->name)->toBe('Dell')
        ->and($firstRow->laptopModel->name)->toBe('3540')
        ->and($firstRow->processor->name)->toBe('I5')
        ->and($firstRow->generation->name)->toBe('13TH')
        ->and($firstRow->ram_gb)->toBe(8)
        ->and($firstRow->storage_gb)->toBe(256)
        ->and($firstRow->has_builtin_ram)->toBeFalse()
        ->and($firstRow->is_battery_ok)->toBeTrue()
        ->and($firstRow->has_issues)->toBeFalse()
        ->and($firstRow->asset_code)->toStartWith('WV');

    $builtinRow = Laptop::query()->where('serial_no', '676')->first();
    expect($builtinRow)->not->toBeNull()
        ->and($builtinRow->has_builtin_ram)->toBeTrue()
        ->and($builtinRow->builtin_ram_gb)->toBe(4)
        ->and($builtinRow->builtin_storage_gb)->toBe(64);

    $issueRow = Laptop::query()->where('serial_no', '622')->first();
    expect($issueRow)->not->toBeNull()
        ->and($issueRow->has_issues)->toBeTrue()
        ->and($issueRow->issues)->toBe('GLASS BROKEN');
});
