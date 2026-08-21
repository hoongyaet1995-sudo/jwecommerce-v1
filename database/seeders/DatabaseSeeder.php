<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Coupon;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (filled(env('DEMO_OWNER_EMAIL')) && filled(env('DEMO_OWNER_PASSWORD'))) {
            User::query()->updateOrCreate(
                ['email' => env('DEMO_OWNER_EMAIL')],
                ['name' => env('DEMO_OWNER_NAME', 'JW Studio Owner'), 'password' => Hash::make(env('DEMO_OWNER_PASSWORD'))],
            );
        }

        Product::query()->where('handle', 'aether-studio-anc-wireless')->delete();

        $categories = collect([
            ['slug' => 'audio', 'name' => 'Acoustic Studio', 'name_cn' => '声学音频', 'image_url' => '/images/catalog/aether-1.jpg', 'description' => 'Lossless Hi-Res & Planar Magnetic Systems'],
            ['slug' => 'workspace', 'name' => 'Ergonomic Desk', 'name_cn' => '桌面工学', 'image_url' => '/images/catalog/solis-1.jpg', 'description' => 'Precision Machined Walnut & Anodized Alloys'],
            ['slug' => 'carry', 'name' => 'Everyday Carry', 'name_cn' => '城市通勤', 'image_url' => '/images/catalog/nomad-1.jpg', 'description' => 'Cordura 1000D Weatherproof Packs & Organizers'],
            ['slug' => 'timepieces', 'name' => 'Mechanical Watches', 'name_cn' => '机械计时', 'image_url' => '/images/catalog/chronos-1.jpg', 'description' => 'Automatic Calibre & Sapphire Crystal Cases'],
            ['slug' => 'optics', 'name' => 'Eyewear & Optics', 'name_cn' => '光学镜镜', 'image_url' => '/images/catalog/helios-1.jpg', 'description' => 'Japanese Titanium & Zeiss Polarized Lenses'],
            ['slug' => 'living', 'name' => 'Nordic Living', 'name_cn' => '家居美学', 'image_url' => '/images/catalog/lumen-1.jpg', 'description' => 'Hand-blown Opal Glass & Minimalist Ceramics'],
        ])->mapWithKeys(fn (array $category) => [$category['slug'] => Category::query()->updateOrCreate(['slug' => $category['slug']], $category + ['is_active' => true])]);

        $collections = collect([
            ['slug' => 'atelier-2026', 'title' => 'The Monolith Series 2026', 'subtitle' => 'Machined aerospace aluminum meets tactile matte ceramics.', 'cover_image' => '/images/catalog/collection-monolith.jpg', 'badge' => 'Seasonal Capsule'],
            ['slug' => 'studio-noir', 'title' => 'Deep Obsidian Studio Edition', 'subtitle' => 'Curated dark aesthetics designed for high-focus deep work.', 'cover_image' => '/images/catalog/collection-noir.jpg', 'badge' => 'Limited Run'],
            ['slug' => 'sustainable-craft', 'title' => 'Earth & Grain Collection', 'subtitle' => 'Zero-waste certified sustainably harvested FSC oak and organic wool.', 'cover_image' => '/images/catalog/collection-earth-grain.jpg', 'badge' => 'Eco Certified'],
        ])->mapWithKeys(fn (array $collection) => [$collection['slug'] => Collection::query()->updateOrCreate(['slug' => $collection['slug']], $collection + ['is_active' => true])]);

        $catalog = [
            ['handle' => 'aether-studio-wireless-headphones', 'category' => 'audio', 'title' => 'Aether Studio ANC Wireless', 'subtitle' => 'Planar Magnetic Studio Monitor with Adaptive Noise Cancellation', 'brand' => 'AETHER ACOUSTICS', 'price' => 38900, 'compare' => 44900, 'description' => 'Engineered for audio purists and mastering engineers. The Aether Studio features 50mm planar magnetic drivers delivering ultra-low distortion across a 5Hz-50kHz frequency spectrum.', 'features' => ['Custom 50mm planar magnetic transducer drivers with neodymium magnets', 'Dual-chip Hybrid Active Noise Cancellation with transparency pass-through', 'Lossless 24-bit/96kHz wireless streaming via LDAC, aptX Adaptive and USB-C audio', '55 hours of battery life with ultra-fast 10-min USB-C quick charge for 6 hours', 'Solid CNC aircraft-grade aluminum chassis with tactile analog dial controls'], 'images' => ['https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=1200&auto=format&fit=crop&q=80', 'https://images.unsplash.com/photo-1583394838336-acd977736f90?w=1200&auto=format&fit=crop&q=80', 'https://images.unsplash.com/photo-1484704849700-f032a568e944?w=1200&auto=format&fit=crop&q=80', 'https://images.unsplash.com/photo-1546435770-a3e426bf472b?w=1200&auto=format&fit=crop&q=80'], 'variants' => [['SKU-AETH-ANC-OBS', 'Obsidian Black / Matte Titanium', 'Obsidian Black', '#18181b', 'Standard Over-Ear', 'Lambskin & Titanium', 38900, 44900, 28, 0], ['SKU-AETH-ANC-SLV', 'Lunar Silver / Natural Leather', 'Lunar Silver', '#e2e8f0', 'Standard Over-Ear', 'Italian Calfskin & Aluminum', 38900, 44900, 14, 1], ['SKU-AETH-ANC-SND', 'Desert Dune / Sandstone Ceramic', 'Desert Dune', '#d6c7b2', 'Standard Over-Ear', 'Organic Alcantara & Ceramic', 41900, 46900, 6, 2]], 'collections' => ['atelier-2026', 'studio-noir']],
            ['handle' => 'solis-machined-desk-shelf-system', 'category' => 'workspace', 'title' => 'Solis Modular Desk Shelf System', 'subtitle' => 'Solid American Walnut & CNC Milled Anodized Rail', 'brand' => 'NORDIC ATELIER', 'price' => 22000, 'compare' => 26000, 'description' => 'Transform your desk into an intentional workspace. Handcrafted from sustainably harvested FSC certified American Walnut and solid bead-blasted aluminum struts.', 'features' => ['Solid 20mm quarter-sawn American Walnut shelf finished in organic matte oil', 'Precision machined aerospace 6061 aluminum alloy feet with cork padding', 'Integrated magnetic rail for Solis accessory trays, headphone pegs, and phone stands', 'Tested to support heavy ultrawide and dual studio monitor setups up to 45kg'], 'images' => ['https://images.unsplash.com/photo-1527864550417-7fd91fc51a46?w=1200&auto=format&fit=crop&q=80', 'https://images.unsplash.com/photo-1593062096033-9a26b09da705?w=1200&auto=format&fit=crop&q=80', 'https://images.unsplash.com/photo-1518455027359-f3f8164ba6bd?w=1200&auto=format&fit=crop&q=80'], 'variants' => [['SKU-SOLIS-WAL-STD', 'American Walnut / Space Gray Legs (105cm)', 'Rich Walnut', '#5c4033', 'Standard (105 cm)', 'FSC Solid Walnut & 6061 Alloy', 22000, 26000, 35, 0], ['SKU-SOLIS-OAK-STD', 'Nordic White Oak / Silver Legs (105cm)', 'Nordic Oak', '#d8c29d', 'Standard (105 cm)', 'FSC White Oak & Silver Alloy', 22000, 26000, 22, 1], ['SKU-SOLIS-WAL-XL', 'American Walnut Dual-Tier XL (130cm)', 'Rich Walnut', '#5c4033', 'Studio XL (130 cm)', 'FSC Solid Walnut & 6061 Alloy', 27500, 32000, 12, 0]], 'collections' => ['atelier-2026', 'sustainable-craft']],
            ['handle' => 'chronos-automatic-field-watch-calibre-9', 'category' => 'timepieces', 'title' => 'Chronos Calibre 9 Automatic Field Watch', 'subtitle' => 'Swiss Sellita SW200-1 Automatic Movement & Grade 5 Titanium', 'brand' => 'HOROLOGY LAB', 'price' => 65000, 'compare' => 78000, 'description' => 'A tool watch distilled to pure utility and surgical legibility. The 39mm case is CNC carved from Grade 5 titanium, weighing merely 58g.', 'features' => ['Swiss Sellita SW200-1 automatic mechanical movement with hacking seconds', 'Ultralight Grade 5 Titanium case with micro-blasted matte scratch-resistant finish', 'Double-domed sapphire crystal with multi-layer inner anti-reflective coating', '200m water resistance with screw-down crown and Viton gaskets'], 'images' => ['https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=1200&auto=format&fit=crop&q=80', 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?w=1200&auto=format&fit=crop&q=80', 'https://images.unsplash.com/photo-1524805444758-089113d48a6d?w=1200&auto=format&fit=crop&q=80'], 'variants' => [['SKU-CHR-C9-BLK', 'Stealth Black Dial / Titanium Case', 'Stealth Black', '#1c1917', '39mm Case (20mm Lug)', 'Grade 5 Titanium & Sapphire', 65000, 78000, 9, 0], ['SKU-CHR-C9-WHT', 'Arctic Polar Dial / Titanium Case', 'Arctic Polar', '#f8fafc', '39mm Case (20mm Lug)', 'Grade 5 Titanium & Sapphire', 65000, 78000, 5, 1]], 'collections' => ['atelier-2026']],
            ['handle' => 'nomad-rolltop-weatherproof-backpack-28l', 'category' => 'carry', 'title' => 'Nomad 28L Weatherproof Rolltop Pack', 'subtitle' => 'X-Pac VX21 Technical Fabric & Magnetic Fidlock Buckles', 'brand' => 'VECTOR CARRY', 'price' => 18500, 'compare' => 21500, 'description' => 'Engineered for seamless inter-city commutes and unpredictable downpours. Constructed from multilayer laminated X-Pac VX21 waterproof textile with taped seams and YKK AquaGuard zips.', 'features' => ['Dimension-Polyant X-Pac VX21 composite fabric', 'German Fidlock V-buckle magnetic closures', 'Ergonomic high-density EVA backpanel with airflow channels', 'Suspended padded laptop sleeve fits up to 16 inch laptops'], 'images' => ['https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=1200&auto=format&fit=crop&q=80', 'https://images.unsplash.com/photo-1622560480605-d83c853bc5c3?w=1200&auto=format&fit=crop&q=80'], 'variants' => [['SKU-NOMAD-28-BLK', 'Tactical Black / X-Pac VX21', 'Tactical Black', '#09090b', '28 Liters (Expandable to 32L)', 'X-Pac VX21 & Cordura', 18500, 21500, 40, 0], ['SKU-NOMAD-28-OLV', 'Coyote Green / X-Pac VX21', 'Coyote Olive', '#4d533c', '28 Liters (Expandable to 32L)', 'X-Pac VX21 & Cordura', 18500, 21500, 18, 1]], 'collections' => ['studio-noir']],
            ['handle' => 'lumen-ambient-kinetic-desk-lamp', 'category' => 'living', 'title' => 'Lumen Kinetic Optical Lamp', 'subtitle' => 'Solid Spun Brass, Opal Glass & Infinite Magnetic Dimmer', 'brand' => 'NORDIC ATELIER', 'price' => 16500, 'compare' => 19500, 'description' => 'Lighting designed as functional sculpture with an ultra-high CRI 98+ full-spectrum LED array and mouth-blown opal glass.', 'features' => ['Full-spectrum SunLike LEDs for true-to-life colour', 'Warm dimming technology from 4000K to 1800K', 'Mouth-blown triple-layered diffuse opal glass globe', 'Braided woven cable with USB-C PD power delivery'], 'images' => ['https://images.unsplash.com/photo-1507473885765-e6ed057f782c?w=1200&auto=format&fit=crop&q=80', 'https://images.unsplash.com/photo-1513506003901-1e6a229e2d15?w=1200&auto=format&fit=crop&q=80'], 'variants' => [['SKU-LUMEN-BRS-01', 'Brushed Solid Brass / Opal Glass', 'Brushed Brass', '#c5a059', 'Height: 34cm, Base: 14cm', 'Solid Brass & Handblown Glass', 16500, 19500, 19, 0], ['SKU-LUMEN-BLK-01', 'Matte Cast Iron / Smoked Glass', 'Cast Iron Black', '#27272a', 'Height: 34cm, Base: 14cm', 'Cast Iron & Smoked Glass', 16500, 19500, 15, 1]], 'collections' => ['studio-noir']],
            ['handle' => 'titanium-precision-sunglasses-polar-zeiss', 'category' => 'optics', 'title' => 'Helios Precision Titanium Eyewear', 'subtitle' => 'Japanese Beta-Titanium Frames with Zeiss Tri-Pel Polarized Lenses', 'brand' => 'OPTIK CRAFT', 'price' => 24500, 'compare' => 29500, 'description' => 'Hand-crafted in Sabae, Japan. The Helios frame is laser cut from flexible Japanese Beta-Titanium and fitted with Carl Zeiss Vision polarized nylon lenses.', 'features' => ['Crafted from Japanese Beta-Titanium', 'Carl Zeiss Vision polarized nylon lenses', 'Screwless integrated barrel hinge mechanism', 'Medical-grade hypoallergenic silicone nosepads'], 'images' => ['https://images.unsplash.com/photo-1572635196237-14b3f281503f?w=1200&auto=format&fit=crop&q=80', 'https://images.unsplash.com/photo-1511499767150-a48a237f0083?w=1200&auto=format&fit=crop&q=80'], 'variants' => [['SKU-OPT-HELIOS-GUN', 'Gunmetal Titanium / Forest Green Polar Lens', 'Gunmetal Titanium', '#475569', '51-19-145 (Medium Universal)', 'Beta-Titanium & Zeiss Nylon', 24500, 29500, 14, 0], ['SKU-OPT-HELIOS-GLD', 'Champagne Gold / Classic Amber Polar Lens', 'Champagne Gold', '#eab308', '51-19-145 (Medium Universal)', 'Beta-Titanium & Zeiss Nylon', 24500, 29500, 8, 1]], 'collections' => ['sustainable-craft']],
        ];

        foreach ($catalog as $entry) {
            $product = Product::query()->updateOrCreate(['handle' => $entry['handle']], ['category_id' => $categories[$entry['category']]->id, 'title' => $entry['title'], 'subtitle' => $entry['subtitle'], 'brand' => $entry['brand'], 'description' => $entry['description'], 'base_price' => $entry['price'], 'compare_at_price' => $entry['compare'], 'features' => $entry['features'], 'status' => 'active', 'is_featured' => true]);
            $variantIds = [];
            foreach ($entry['variants'] as [$sku, $title, $colorName, $colorHex, $size, $material, $price, $compareAtPrice, $stock, $imageIndex]) {
                $variant = ProductVariant::query()->updateOrCreate(['sku' => $sku], ['product_id' => $product->id, 'title' => $title, 'color_name' => $colorName, 'color_hex' => $colorHex, 'size' => $size, 'material' => $material, 'price' => $price, 'compare_at_price' => $compareAtPrice, 'stock' => $stock]);
                $variantIds[$imageIndex] = $variant->id;
            }
            foreach ($entry['images'] as $position => $path) {
                $path = $this->localCatalogImage($path);
                ProductImage::query()->updateOrCreate(['product_id' => $product->id, 'position' => $position], ['product_variant_id' => $variantIds[$position] ?? null, 'path' => $path, 'alt' => $product->title]);
            }
            $product->collections()->sync(collect($entry['collections'])->map(fn (string $slug) => $collections[$slug]->id));
        }

        Coupon::query()->updateOrCreate(['code' => 'WELCOME10'], ['discount_type' => 'percentage', 'value' => 10, 'min_spend' => 5000, 'is_active' => true]);
        Coupon::query()->updateOrCreate(['code' => 'ATELIER50'], ['discount_type' => 'fixed_amount', 'value' => 5000, 'min_spend' => 25000, 'is_active' => true]);
        foreach ([
            'shipping_rate_cents' => 1200,
            'free_shipping_threshold_cents' => 15000,
            'tax_rate_percent' => 8.875,
        ] as $key => $value) {
            StoreSetting::query()->updateOrCreate(['key' => $key], ['value' => ['data' => $value]]);
        }
        $header = Menu::query()->updateOrCreate(['key' => 'header'], ['name' => 'Header mega navigation']);
        MenuItem::query()->where('menu_id', $header->id)->whereIn('label', ['Shop', 'Admin'])->delete();

        $put = function (array $attributes, ?MenuItem $parent = null) use ($header): MenuItem {
            return MenuItem::query()->updateOrCreate(
                ['menu_id' => $header->id, 'parent_id' => $parent?->id, 'label' => $attributes['label']],
                $attributes + ['menu_id' => $header->id, 'parent_id' => $parent?->id],
            );
        };

        $audio = $put(['label' => 'Electronics & Audio', 'label_cn' => '声学与数码', 'url' => '/shop', 'position' => 10, 'badge' => 'Popular', 'badge_type' => 'popular', 'featured_title' => 'Aether Studio ANC Wireless', 'featured_description' => '50mm planar magnetic drivers with 55h battery and lossless LDAC.', 'featured_price' => '$389.00', 'featured_image_url' => '/images/catalog/aether-1.jpg', 'featured_cta_label' => 'Explore Flagship Audio', 'featured_cta_url' => '/products/aether-studio-wireless-headphones']);
        $audioPremium = $put(['label' => 'Premium Audio', 'label_cn' => '大师级声学', 'url' => '/shop?category=audio', 'position' => 10, 'badge' => 'Top Rated', 'badge_type' => 'rated'], $audio);
        $put(['label' => 'Noise Cancelling Headphones', 'label_cn' => '主动降噪头戴耳机', 'url' => '/shop?category=audio', 'position' => 10, 'badge' => 'HOT', 'badge_type' => 'hot'], $audioPremium);
        $put(['label' => 'Hi-Fi Audio Systems', 'label_cn' => '无损解码耳放', 'url' => '/shop?category=audio', 'position' => 20], $audioPremium);
        $wearables = $put(['label' => 'Wearables & Smart', 'label_cn' => '智能穿戴与健康', 'url' => '/shop?category=timepieces', 'position' => 20], $audio);
        $put(['label' => 'Mechanical Watches', 'label_cn' => '专业机械计时', 'url' => '/shop?category=timepieces', 'position' => 10], $wearables);
        $workspace = $put(['label' => 'Desk & Workspace', 'label_cn' => '桌面工学与生产力', 'url' => '/shop?category=workspace', 'position' => 30], $audio);
        $put(['label' => 'Custom Mechanical Keyboards', 'label_cn' => '客制化机械键盘', 'url' => '/shop?category=workspace', 'position' => 10, 'badge' => 'HOT', 'badge_type' => 'hot'], $workspace);

        $lifestyle = $put(['label' => 'Apparel & Lifestyle', 'label_cn' => '通勤与美学生活', 'url' => '/shop', 'position' => 20, 'featured_title' => 'Vanguard 24L Tech Commuter', 'featured_description' => 'Ballistic Cordura 1000D with German Fidlock magnetic fasteners.', 'featured_price' => '$280.00', 'featured_image_url' => '/images/catalog/nomad-1.jpg', 'featured_cta_label' => 'Discover EDC Lineup', 'featured_cta_url' => '/shop?category=carry']);
        $carry = $put(['label' => 'Everyday Carry', 'label_cn' => '城市通勤装备', 'url' => '/shop?category=carry', 'position' => 10], $lifestyle);
        $put(['label' => 'Weatherproof Carry', 'label_cn' => '防泼水城市通勤', 'url' => '/shop?category=carry', 'position' => 10, 'badge' => 'HOT', 'badge_type' => 'hot'], $carry);
        $optics = $put(['label' => 'Eyewear & Optics', 'label_cn' => '光学镜框与太阳镜', 'url' => '/shop?category=optics', 'position' => 20], $lifestyle);
        $put(['label' => 'Japanese Titanium Frames', 'label_cn' => '日本手工纯钛镜架', 'url' => '/shop?category=optics', 'position' => 10, 'badge' => 'NEW', 'badge_type' => 'new'], $optics);
        $living = $put(['label' => 'Nordic Living', 'label_cn' => '北欧家居与氛围美学', 'url' => '/shop?category=living', 'position' => 30], $lifestyle);
        $put(['label' => 'Hand-blown Opal Glass Lamps', 'label_cn' => '手工吹制白玉玻璃台灯', 'url' => '/shop?category=living', 'position' => 10], $living);

        $collectionsMenu = $put(['label' => 'Collections', 'label_cn' => '胶囊系列', 'url' => '/shop', 'position' => 30, 'badge' => 'Capsules', 'badge_type' => 'accent', 'featured_title' => 'The Monolith Series 2026', 'featured_description' => 'Machined aerospace aluminum meets tactile matte ceramics.', 'featured_price' => 'From $180.00', 'featured_image_url' => '/images/catalog/collection-monolith.jpg', 'featured_cta_label' => 'Explore Capsule Drop', 'featured_cta_url' => '/collections/atelier-2026']);
        $signature = $put(['label' => 'Signature Capsules', 'label_cn' => '季度签名系列', 'url' => '/collections/atelier-2026', 'position' => 10, 'badge' => 'Limited Drop', 'badge_type' => 'hot'], $collectionsMenu);
        $put(['label' => 'The Monolith Series 2026', 'label_cn' => '2026 巨石极简限定系列', 'url' => '/collections/atelier-2026', 'position' => 10, 'badge' => 'Capsule', 'badge_type' => 'hot'], $signature);
        $put(['label' => 'Deep Obsidian Studio Edition', 'label_cn' => '深邃黑曜石高专注系列', 'url' => '/collections/studio-noir', 'position' => 20, 'badge' => 'Limited', 'badge_type' => 'default'], $signature);
        $material = $put(['label' => 'Material Collaborations', 'label_cn' => '材质工艺联名', 'url' => '/shop', 'position' => 20], $collectionsMenu);
        $put(['label' => 'FSC Solid American Black Walnut', 'label_cn' => '北美特选 FSC 认证黑胡桃木', 'url' => '/shop?category=workspace', 'position' => 10], $material);
        $curated = $put(['label' => 'Curated Workspaces', 'label_cn' => '场景化整套搭配', 'url' => '/shop?category=workspace', 'position' => 30], $collectionsMenu);
        $put(['label' => 'Executive Minimalist Setup', 'label_cn' => '高管极简深色桌面套组', 'url' => '/shop?category=workspace', 'position' => 10], $curated);

        $archive = $put(['label' => 'Archive Clearance', 'label_cn' => '限时特惠 / 清仓', 'url' => '/shop', 'position' => 40, 'badge' => '30% OFF', 'badge_type' => 'sale']);
        $vault = $put(['label' => 'Last Chance Vault', 'label_cn' => '清仓特惠专区', 'url' => '/shop', 'position' => 10, 'badge' => 'Up to 40% OFF', 'badge_type' => 'discount'], $archive);
        $put(['label' => 'Seasonal Archive Deals', 'label_cn' => '往期限定特惠', 'url' => '/shop', 'position' => 10, 'badge' => '30% OFF', 'badge_type' => 'discount'], $vault);
        $put(['label' => 'Curated Architecture', 'label_cn' => '设计工坊与叙事', 'url' => '/', 'position' => 50]);
    }

    private function localCatalogImage(string $path): string
    {
        foreach ([
            '1505740420928-5e560c06d30e' => 'aether-1.jpg',
            '1583394838336-acd977736f90' => 'aether-2.jpg',
            '1484704849700-f032a568e944' => 'aether-3.jpg',
            '1546435770-a3e426bf472b' => 'aether-4.jpg',
            '1527864550417-7fd91fc51a46' => 'solis-1.jpg',
            '1593062096033-9a26b09da705' => 'solis-2.jpg',
            '1518455027359-f3f8164ba6bd' => 'solis-3.jpg',
            '1523275335684-37898b6baf30' => 'chronos-1.jpg',
            '1522335789203-aabd1fc54bc9' => 'chronos-2.jpg',
            '1524805444758-089113d48a6d' => 'chronos-3.jpg',
            '1553062407-98eeb64c6a62' => 'nomad-1.jpg',
            '1622560480605-d83c853bc5c3' => 'nomad-2.jpg',
            '1507473885765-e6ed057f782c' => 'lumen-1.jpg',
            '1513506003901-1e6a229e2d15' => 'lumen-2.jpg',
            '1572635196237-14b3f281503f' => 'helios-1.jpg',
            '1511499767150-a48a237f0083' => 'helios-2.jpg',
        ] as $sourceId => $filename) {
            if (str_contains($path, $sourceId)) {
                return '/images/catalog/'.$filename;
            }
        }

        return $path;
    }
}
