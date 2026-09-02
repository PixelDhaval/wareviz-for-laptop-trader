@php
    $specParts = array_filter([
        $laptop->processor?->name,
        $laptop->generation?->name,
    ]);
    $memoryParts = array_filter([
        $laptop->ram_gb ? "{$laptop->ram_gb}GB RAM" : null,
        $laptop->storage_gb ? "{$laptop->storage_gb}GB" : null,
    ]);
@endphp
<div class="sticker">
    <div class="sticker__title">{{ $laptop->brand?->name }} {{ $laptop->laptopModel?->name }}</div>
    <div class="sticker__spec">{{ implode(' | ', $specParts) }}</div>
    <div class="sticker__spec">{{ implode(' / ', $memoryParts) }}</div>
    <div class="sticker__barcode">{!! \App\Support\Barcode::svg($laptop->asset_code) !!}</div>
    <div class="sticker__code">{{ $laptop->asset_code }}</div>
</div>
