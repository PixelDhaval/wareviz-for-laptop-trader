---
paths:
  - 'app/Filament/Imports/**'
---

# Imports

## Packing-list serial numbers are not unique — never dedupe imports on them
The wareviz packing list CSV's SN column is a running tally per sheet, not a true serial number — it can repeat within the same file for distinct physical units (verified: 305 CSV rows, only 302 unique SNs). LaptopImporter::resolveRecord() must always `return new Laptop(...)` and never firstOrNew()/firstOrCreate() by shipment_id+serial_no, or legitimate duplicate units get silently merged into one record.
