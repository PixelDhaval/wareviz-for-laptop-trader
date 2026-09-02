<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Print barcodes</title>
    <style>
        @page {
            size: 50mm 25mm;
            margin: 0;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #e5e5e5;
            font-family: Arial, Helvetica, sans-serif;
        }

        .toolbar {
            padding: 12px;
            text-align: center;
        }

        .toolbar button {
            font-size: 14px;
            padding: 8px 16px;
            cursor: pointer;
        }

        .sticker {
            width: 50mm;
            height: 25mm;
            padding: 1.5mm 2mm;
            background: #fff;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            overflow: hidden;
            margin: 0 auto 8px;
        }

        .sticker__title {
            font-size: 7pt;
            font-weight: bold;
            line-height: 1.1;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
        }

        .sticker__spec {
            font-size: 5.5pt;
            line-height: 1.2;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
        }

        .sticker__barcode {
            width: 100%;
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 0;
        }

        .sticker__barcode svg {
            width: 100%;
            height: 100%;
            max-height: 10mm;
        }

        .sticker__code {
            font-size: 6pt;
            font-family: 'Courier New', monospace;
            letter-spacing: 0.5px;
        }

        @media print {
            body {
                background: #fff;
            }

            .toolbar {
                display: none;
            }

            .sticker {
                margin: 0;
                page-break-after: always;
            }

            .sticker:last-child {
                page-break-after: auto;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Print {{ $laptops->count() }} {{ \Illuminate\Support\Str::plural('sticker', $laptops->count()) }}</button>
    </div>

    @foreach ($laptops as $laptop)
        @include('laptops.barcode-sticker', ['laptop' => $laptop])
    @endforeach
</body>
</html>
