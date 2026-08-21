export interface RouteSpecItem {
  path: string;
  module: string;
  component: string;
  name: string;
  description: string;
  authRole: string;
  keyFeatures: string[];
}

export const ADMIN_ROUTES_SPEC: RouteSpecItem[] = [
  {
    path: '/admin/login',
    module: 'Authentication',
    component: 'AdminLoginPage.tsx',
    name: 'Admin Login',
    description: 'Administrator authentication portal with 2FA support and credential remember.',
    authRole: 'Public (Guest)',
    keyFeatures: ['Email & Password', '2FA Token Modal', 'Remember Device', 'Quick Demo Access']
  },
  {
    path: '/admin/dashboard',
    module: 'Overview',
    component: 'DashboardOverview.tsx',
    name: 'Dashboard Overview',
    description: 'Executive overview with revenue KPIs, live order feed, conversion charts and low stock alerts.',
    authRole: 'All Roles',
    keyFeatures: ['Sales & Order Trends (Recharts)', 'KPI Metric Delta', 'Realtime Activity Stream', 'Inventory Alerts']
  },
  {
    path: '/admin/menus/header',
    module: 'Navigation & CMS',
    component: 'HeaderMenuAdmin.tsx',
    name: 'Header Menu Admin (3-Level)',
    description: 'Tree-based navigation builder for Mega Menu: Main Menu (L1) -> Subcategory (L2) -> Leaf Link (L3).',
    authRole: 'Super Admin, Catalog Manager',
    keyFeatures: ['3-Tier Nested Tree', 'Drag & Reorder', 'MegaMenu vs Simple Dropdown', 'Live Storefront Nav Preview']
  },
  {
    path: '/admin/menus/footer',
    module: 'Navigation & CMS',
    component: 'FooterMenuAdmin.tsx',
    name: 'Footer Menu Admin (2-Level)',
    description: 'Footer columns structure editor: Section Header (L1) -> Column Links (L2).',
    authRole: 'Super Admin, Catalog Manager',
    keyFeatures: ['2-Tier Hierarchy', 'Social & Legal Link Binding', 'External Link Flags', 'Live Store Footer Preview']
  },
  {
    path: '/admin/catalog/categories',
    module: 'Catalog Management',
    component: 'CategoryAdmin.tsx',
    name: 'Categories Management',
    description: 'CRUD interface for product taxonomies, parent-child relations, icons, and SEO metadata.',
    authRole: 'Super Admin, Catalog Manager',
    keyFeatures: ['Parent Category Selector', 'Lucide Icon Picker', 'Live Slug Generator', 'Google SERP SEO Preview']
  },
  {
    path: '/admin/catalog/collections',
    module: 'Catalog Management',
    component: 'CollectionAdmin.tsx',
    name: 'Collections & Campaigns',
    description: 'Manage curated collections with manual item pickers or automated dynamic rule engines.',
    authRole: 'Super Admin, Catalog Manager',
    keyFeatures: ['Dynamic Rules Engine', 'Manual SKU Picker', 'Banner Image Uploader', 'Match Criteria (All/Any)']
  },
  {
    path: '/admin/catalog/products',
    module: 'Catalog Management',
    component: 'ProductAdmin.tsx',
    name: 'Products & Variant Matrix',
    description: 'Complete product catalog with Cartesian product variant generator for multi-attribute SKUs.',
    authRole: 'Super Admin, Catalog Manager',
    keyFeatures: ['Cartesian SKU Generator (Color x Size)', 'Batch Price/Stock Apply', 'Rich Media Grid', 'SEO Fields']
  },
  {
    path: '/admin/customers',
    module: 'Customer Management',
    component: 'CustomerAdmin.tsx',
    name: 'Customers Directory',
    description: 'Customer list with lifetime value, segmentation tags, and detailed purchase profile drawer.',
    authRole: 'Super Admin, Order Specialist',
    keyFeatures: ['Customer Lifetime Value (LTV)', 'Multi-address Book', 'Order History Drawer', 'VIP Tagging']
  },
  {
    path: '/admin/orders',
    module: 'Order Management',
    component: 'OrderAdmin.tsx',
    name: 'Orders & Fulfillment',
    description: 'Comprehensive order processing console with payment state, logistics tracking, and refund workflow.',
    authRole: 'Super Admin, Order Specialist',
    keyFeatures: ['Multi-State Tabs', 'Tracking Carrier Dispatch', 'Line Item Breakdown', 'Refund Modal Console']
  }
];

export const PHASE2_JSON_SCHEMAS = {
  headerMenuSchema: {
    $schema: "https://json-schema.org/draft/2020-12/schema",
    title: "HeaderMenu3LevelSchema",
    type: "array",
    description: "Header Menu Structure supporting 3-level nesting for modern Ecommerce Mega Menus",
    items: {
      type: "object",
      required: ["id", "title", "url", "linkType", "displayStyle", "isActive", "sortOrder", "columns"],
      properties: {
        id: { type: "string", example: "menu-l1-01" },
        title: { type: "string", example: "Electronics & Tech" },
        url: { type: "string", example: "/categories/electronics" },
        linkType: { type: "string", enum: ["category", "collection", "product", "page", "custom_url"] },
        targetId: { type: "string", example: "cat-electronics" },
        icon: { type: "string", example: "Laptop" },
        badge: { type: "string", example: "NEW" },
        isHighlighted: { type: "boolean", default: false },
        displayStyle: { type: "string", enum: ["dropdown", "megamenu", "simple_link"] },
        isActive: { type: "boolean", default: true },
        sortOrder: { type: "integer", default: 1 },
        columns: {
          type: "array",
          description: "Level 2: Sub-category Groups or Mega Menu Columns",
          items: {
            type: "object",
            required: ["id", "title", "url", "linkType", "isActive", "sortOrder", "items"],
            properties: {
              id: { type: "string", example: "menu-l2-101" },
              title: { type: "string", example: "Audio & Acoustics" },
              url: { type: "string", example: "/categories/audio" },
              linkType: { type: "string", enum: ["category", "collection", "product", "page", "custom_url"] },
              targetId: { type: "string", example: "cat-audio" },
              icon: { type: "string", example: "Headphones" },
              badge: { type: "string", example: "Popular" },
              isActive: { type: "boolean", default: true },
              sortOrder: { type: "integer", default: 1 },
              items: {
                type: "array",
                description: "Level 3: Leaf navigation items",
                items: {
                  type: "object",
                  required: ["id", "title", "url", "linkType", "isActive", "sortOrder"],
                  properties: {
                    id: { type: "string", example: "menu-l3-1001" },
                    title: { type: "string", example: "Noise Cancelling Headphones" },
                    url: { type: "string", example: "/categories/anc-headphones" },
                    linkType: { type: "string", enum: ["category", "collection", "product", "page", "custom_url"] },
                    targetId: { type: "string", example: "cat-anc-headphones" },
                    badge: { type: "string", example: "HOT" },
                    isExternal: { type: "boolean", default: false },
                    isActive: { type: "boolean", default: true },
                    sortOrder: { type: "integer", default: 1 }
                  }
                }
              }
            }
          }
        }
      }
    }
  },

  footerMenuSchema: {
    $schema: "https://json-schema.org/draft/2020-12/schema",
    title: "FooterMenu2LevelSchema",
    type: "array",
    description: "Footer Menu 2-level schema: Section Header -> Child Links",
    items: {
      type: "object",
      required: ["id", "sectionTitle", "isActive", "sortOrder", "links"],
      properties: {
        id: { type: "string", example: "footer-sec-01" },
        sectionTitle: { type: "string", example: "Customer Care" },
        isActive: { type: "boolean", default: true },
        sortOrder: { type: "integer", default: 1 },
        links: {
          type: "array",
          items: {
            type: "object",
            required: ["id", "title", "url", "linkType", "isActive", "sortOrder"],
            properties: {
              id: { type: "string", example: "footer-link-101" },
              title: { type: "string", example: "Track Your Order" },
              url: { type: "string", example: "/order-tracking" },
              linkType: { type: "string", enum: ["page", "custom_url", "category"] },
              badge: { type: "string", example: "24/7" },
              isExternal: { type: "boolean", default: false },
              isActive: { type: "boolean", default: true },
              sortOrder: { type: "integer", default: 1 }
            }
          }
        }
      }
    }
  },

  productVariantMatrixSchema: {
    $schema: "https://json-schema.org/draft/2020-12/schema",
    title: "ProductWithVariantMatrixSchema",
    type: "object",
    description: "Product model with multi-attribute Cartesian Variant Matrix",
    properties: {
      id: { type: "string", example: "prod-aurora-pro" },
      title: { type: "string", example: "Aurora Studio Pro Wireless Headphones" },
      slug: { type: "string", example: "aurora-studio-pro-wireless" },
      brand: { type: "string", example: "AcousticLab" },
      categoryId: { type: "string", example: "cat-audio" },
      basePrice: { type: "number", example: 299.00 },
      options: {
        type: "array",
        description: "Dimensions for Cartesian Product generation",
        items: {
          type: "object",
          required: ["id", "name", "values"],
          properties: {
            id: { type: "string", example: "opt-color" },
            name: { type: "string", example: "Color" },
            values: { type: "array", items: { type: "string" }, example: ["Midnight Black", "Arctic White", "Matte Gold"] }
          }
        }
      },
      variants: {
        type: "array",
        description: "Calculated Cartesian product SKUs with independent pricing and stock",
        items: {
          type: "object",
          required: ["id", "sku", "title", "options", "price", "inventoryQuantity", "enabled"],
          properties: {
            id: { type: "string", example: "var-aurora-blk-std" },
            sku: { type: "string", example: "AUR-BLK-STD" },
            title: { type: "string", example: "Midnight Black / Standard" },
            options: {
              type: "object",
              additionalProperties: { type: "string" },
              example: { "Color": "Midnight Black", "Edition": "Standard" }
            },
            price: { type: "number", example: 299.00 },
            compareAtPrice: { type: "number", example: 349.00 },
            costPrice: { type: "number", example: 120.00 },
            inventoryQuantity: { type: "integer", example: 85 },
            weightGrams: { type: "number", example: 320 },
            barcode: { type: "string", example: "6941234567890" },
            image: { type: "string", example: "https://images.unsplash.com/photo-505740420928-5e560c06d30e" },
            enabled: { type: "boolean", default: true }
          }
        }
      }
    }
  }
};

export const MASTER_CODE_PROMPT = `Build an enterprise-grade, clean Universal Ecommerce Admin Dashboard in React + Tailwind CSS + Lucide Icons + Recharts with the following architecture:
1. LAYOUT & UX: Left Sidebar with collapsable sections, top Header with global search, notifications drawer, dark/light theme switch, and user profile with 2FA status.
2. 3-LEVEL HEADER MENU ADMIN: Hierarchical tree editor supporting Main Menu (L1) -> Subcategory (L2) -> Leaf Item (L3), with drag/order controls, mega-menu vs simple dropdown settings, and live interactive storefront navbar simulator.
3. 2-LEVEL FOOTER MENU ADMIN: Section Header (L1) -> Link Item (L2) with external link badges and live footer preview.
4. CATALOG MANAGEMENT:
   - Category CRUD with parent category selector, Lucide icon picker, live slug auto-generation, and Google SERP SEO preview card.
   - Collection CRUD with dynamic automated rule builder (Field: price/tag, Operator: >, <, equals, value) and manual SKU picker.
   - Product CRUD with an interactive Cartesian Product Variant Matrix Generator (e.g. Color x Size x Material) calculating SKU rows with independent price, compare-at, inventory stock, image, barcode, and batch bulk updater.
5. CUSTOMER MANAGEMENT: Customer directory with LTV, spending badges, and slide-over Customer Detail Drawer with history timeline & address book.
6. ORDER MANAGEMENT: Multi-state tabbed data table (Pending, Processing, Shipped, Completed, Cancelled) with Order Detail Drawer showing line item breakdown, logistics carrier & tracking number updater, and interactive refund console.
7. ARCHITECTURE & SCHEMAS: Built-in visual route mapper, JSON schema viewer with one-click copy, and system architecture specifications.`;
