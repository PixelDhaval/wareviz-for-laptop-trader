<?php

use App\Enums\CodeDateFormat;
use App\Enums\CodeDateSource;
use App\Enums\CodeSegmentPosition;
use App\Filament\Pages\ManageSettings;
use App\Models\Currency;
use App\Models\Setting;
use App\Models\User;
use Livewire\Livewire;

test('the settings page loads with the current values', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $currency = Currency::factory()->create();
    Setting::factory()->create(['sale_local_currency_id' => $currency->id]);

    Livewire::test(ManageSettings::class)
        ->assertOk()
        ->assertFormSet(['sale_local_currency_id' => $currency->id]);
});

test('saving the settings page updates the singleton row', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $local = Currency::factory()->create();
    $export = Currency::factory()->create();
    $invoice = Currency::factory()->create();

    $repair = Currency::factory()->create();

    Livewire::test(ManageSettings::class)
        ->fillForm([
            'sale_local_currency_id' => $local->id,
            'sale_export_currency_id' => $export->id,
            'invoice_value_currency_id' => $invoice->id,
            'repair_cost_currency_id' => $repair->id,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $setting = Setting::current();

    expect($setting->sale_local_currency_id)->toBe($local->id)
        ->and($setting->sale_export_currency_id)->toBe($export->id)
        ->and($setting->invoice_value_currency_id)->toBe($invoice->id)
        ->and($setting->repair_cost_currency_id)->toBe($repair->id);
});

test('saving the settings page updates the code generation and fiscal year settings', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    Livewire::test(ManageSettings::class)
        ->fillForm([
            'fiscal_year_start_month' => 4,
            'laptop_code_prefix' => 'AST',
            'laptop_code_separator' => '-',
            'laptop_code_date_source' => CodeDateSource::CreatedAt->value,
            'laptop_code_date_format' => CodeDateFormat::YearMonthDay->value,
            'laptop_code_sequence_pad' => 4,
            'sale_code_prefix' => 'SL',
            'sale_code_date_format' => CodeDateFormat::FiscalYearLong->value,
            'sale_code_date_position' => CodeSegmentPosition::After->value,
            'sale_code_sequence_pad' => 5,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $setting = Setting::current();

    expect($setting->fiscal_year_start_month)->toBe(4)
        ->and($setting->laptop_code_prefix)->toBe('AST')
        ->and($setting->laptop_code_separator)->toBe('-')
        ->and($setting->laptop_code_date_source)->toBe(CodeDateSource::CreatedAt)
        ->and($setting->laptop_code_date_format)->toBe(CodeDateFormat::YearMonthDay)
        ->and($setting->laptop_code_sequence_pad)->toBe(4)
        ->and($setting->sale_code_prefix)->toBe('SL')
        ->and($setting->sale_code_date_format)->toBe(CodeDateFormat::FiscalYearLong)
        ->and($setting->sale_code_date_position)->toBe(CodeSegmentPosition::After)
        ->and($setting->sale_code_sequence_pad)->toBe(5);
});
