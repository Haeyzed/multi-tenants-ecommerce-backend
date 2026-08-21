---
paths:
  - 'app/Models/Tenant/{Product,ProductVariant,Warehouse,Inventory}*.php'
---

# Tenant

## Catalogue vs warehouse inventory
A Product/ProductVariant is tenant catalogue identity. Warehouse stock lives on Inventory via morph inventoryable (product or variant), unique per warehouse + inventoryable. Do not add warehouse_id to products or duplicate products per location. SKU/barcode belong on the variant, not the warehouse. Simple products with a SKU use an implicit variant as the stockable; checkout/POS/availability must resolve that via InventoryStockableResolver rather than Product vs Variant independently.
