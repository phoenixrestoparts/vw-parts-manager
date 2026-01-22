# VW Parts Manufacturing Manager

## Installation

1. **Create the folder structure:**
   ```
   /wp-content/plugins/vw-parts-manager/
       ├── vw-parts-manager.php
       ├── assets/
       │   ├── css/
       │   │   └── admin.css
       │   └── js/
       │       └── admin.js
       └── includes/
           └── admin/
               ├── dashboard.php
               ├── suppliers.php
               ├── production.php
               └── import-export.php
   ```

2. **Upload all the files** to their respective locations

3. **Activate the plugin:**
   - Go to WordPress Admin > Plugins
   - Find "VW Parts Manufacturing Manager"
   - Click "Activate"

4. **You should now see "VW Parts" in your admin menu!**

## Getting Started

### Step 1: Add Suppliers
1. Go to **VW Parts > Suppliers**
2. Add your component suppliers with:
   - Name
   - Email (for PO emails)
   - Contact details

### Step 2: Add Tools
You can either:
- **Manually add tools:** VW Parts > Tools > Add New Tool
- **Import from CSV:** VW Parts > Import/Export > Import Tools

Each tool needs:
- Tool Name
- Tool Number
- Location
- Notes (optional)

### Step 3: Add Components
You can either:
- **Manually add components:** VW Parts > Components > Add New Component
- **Import from CSV:** VW Parts > Import/Export > Import Components

Each component needs:
- Component Name
- Component Number (e.g., com111-809-456)
- Supplier (select from your suppliers)
- Price
- Drawing file (DWG - optional)
- Notes (optional)

### Step 4: Set Up Product BOMs
1. Go to **Products** in WooCommerce
2. Edit any product
3. Scroll down to find two new sections:
   - **Bill of Materials** - Add components and quantities needed
   - **Required Tools** - Add tools needed to manufacture
   - **Product Supplier** - If it's a ready-made product, select supplier

For example, for product 111-809-456:
- Add component: com111-809-456 (quantity: 1)
- Add component: com111-809-456/b (quantity: 1)
- Add component: M8 Bolt (quantity: 1)
- Add tool: Laser Cutter
- Add tool: Hydraulic Press

### Step 5: Use Production Calculator
1. Go to **VW Parts > Production Calculator**
2. Select whether you're manufacturing (with BOM) or ordering ready-made products
3. Choose the product
4. Enter quantity (e.g., 50)
5. Click "Calculate Requirements"
6. You'll see:
   - All components needed with quantities
   - Total costs
   - Tools required and their locations
   - Email or Print buttons for POs

## CSV Import Format

### Tools CSV
```csv
Tool Name,Tool Number,Location,Notes
Laser Cutter,LC-001,Workshop A,Main laser
Hydraulic Press,HP-002,Workshop B,For forming
```

### Components CSV
```csv
Component Name,Component Number,Supplier Name,Price,Notes
Steel Blank A,com111-809-456,MetalWorks Ltd,12.50,Standard blank
Steel Blank B,com111-809-456/b,MetalWorks Ltd,15.00,Reinforced
M8 Bolt,BOLT-M8-50,Fasteners Inc,0.25,50mm length
```

**Important:** Supplier names must exactly match existing suppliers!

## Features

✅ Manage unlimited suppliers with contact details
✅ Track all your manufacturing tools with locations
✅ Manage components with pricing and drawings
✅ Set up Bill of Materials for products
✅ Assign tools required for each product
✅ Support for ready-made products from suppliers
✅ Production calculator with automatic quantity calculations
✅ Generate purchase orders grouped by supplier
✅ Email POs directly to suppliers (HTML format)
✅ Print/save POs as PDF
✅ CSV import/export for bulk operations
✅ Search tools by name/number/location

## Tips

- **Add suppliers first** before importing components
- **Test imports** with a small CSV file first
- **Back up regularly** using the export feature
- **Set up BOMs carefully** - the calculator relies on accurate data
- **Print to PDF** works in all modern browsers (Ctrl/Cmd + P)

## Troubleshooting

**"No components to order"** - Make sure your product has a BOM set up

**"Supplier not found"** - When importing, ensure supplier names match exactly

**CSV import fails** - Check your CSV format matches the examples

**Email not sending** - Some hosting doesn't allow wp_mail(). Use Print/PDF instead and attach manually

## Support

This is a custom plugin built specifically for your VW parts manufacturing workflow. If you need modifications or have questions, keep the conversation going!

---

Built for managing classic VW parts manufacturing with love ❤️