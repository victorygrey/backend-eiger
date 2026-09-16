<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Zone;
use Illuminate\Support\Collection;

class ProductEnrichmentService
{
    protected static ?array $cachedJsonProducts = null;
    protected static ?Collection $cachedZones = null;

    /**
     * Load scraped products master catalog from products.json.
     */
    public static function getJsonProducts(): array
    {
        if (static::$cachedJsonProducts !== null) {
            return static::$cachedJsonProducts;
        }

        $paths = [
            database_path('data/products.json'),
            'd:/LAPTOP FAIZAL/_Project/scrap-eiger/data/products.json',
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                $decoded = json_decode(file_get_contents($path), true);
                if (is_array($decoded)) {
                    $map = [];
                    foreach ($decoded as $item) {
                        $sku = (string) ($item['product_code'] ?? $item['sku'] ?? '');
                        if ($sku) {
                            $map[$sku] = $item;
                        }
                    }
                    static::$cachedJsonProducts = $map;
                    return static::$cachedJsonProducts;
                }
            }
        }

        static::$cachedJsonProducts = [];
        return static::$cachedJsonProducts;
    }

    /**
     * Find scraped product item by SKU or product code.
     */
    public static function findJsonProduct(string $sku): ?array
    {
        $products = static::getJsonProducts();
        $code9 = substr($sku, 0, 9);

        if (isset($products[$sku])) {
            return $products[$sku];
        }
        if (isset($products[$code9])) {
            return $products[$code9];
        }

        return null;
    }

    /**
     * Extract material from description or infer based on product attributes.
     */
    public static function extractMaterial(?string $description, string $name = '', string $category = ''): string
    {
        $desc = $description ?? '';

        if (preg_match('/(?:kombinasi bahan|terbuat dari|menggunakan bahan)\s+(.+?)(?=\s+(?:yang|untuk|dengan)|[.,]|$)/i', $desc, $m)) {
            $candidate = trim(preg_replace('/\s+dan\s+/i', ' & ', $m[1]));
            if ($candidate !== '' && strlen($candidate) <= 60) {
                return ucwords(strtolower($candidate));
            }
        }

        // 1. Explicit pattern "Material : X" or "Bahan : X"
        if (preg_match('/(?:Material|Bahan)\s*:\s*([^.\r\n,]+)/i', $desc, $m)) {
            $candidate = trim($m[1]);
            if (strlen($candidate) <= 50 && !preg_match('/utama|dasar|baku|terbaik/i', $candidate)) {
                return $candidate;
            }
        }

        // 2. Pattern "dengan bahan [X] / berbahan [X]"
        if (preg_match('/(?:berbahan|dengan bahan)\s+([A-Za-z0-9\/\-\s]+?)(?:\s+(?:yang|untuk|berkualitas|waterproof|durable|tahan|ringan|\.|,|\r|\n))/i', $desc, $m)) {
            $candidate = trim($m[1]);
            if (strlen($candidate) <= 30 && !preg_match('/utama|dasar|baku|terbaik|nyaman/i', $candidate)) {
                return ucwords(strtolower($candidate));
            }
        }

        // 3. Known technical outdoor materials
        $knownMaterials = [
            'Cordura 1000D', 'Cordura 500D', 'Cordura',
            'Ripstop Nylon', 'Ripstop Polyester', 'Ripstop',
            'Poliester 600D', 'Polyester 600D', 'Polyester', 'Poliester',
            'Nylon Taslan', 'Nylon', 'Nilon',
            'Cotton Combed 30s', 'Cotton Combed', 'Cotton Twill', 'Cotton', 'Katun',
            'Canvas', 'Kanvas',
            'Softshell', 'Fleece', 'Gore-Tex', 'Waterproof Membrane',
            'Leather', 'Kulit Sintetis', 'Kulit Asli', 'Kulit',
            'EVA Sponge', 'EVA', 'Rubber', 'Karet',
            'Spandex', 'Mesh',
            'Stainless Steel 304', 'Stainless Steel', 'Aluminium', 'Aluminum', 'Titanium'
        ];

        foreach ($knownMaterials as $km) {
            if (stripos($desc, $km) !== false) {
                return $km;
            }
        }

        // 4. Infer fallback material from name & category if still not detected
        $combined = strtolower($name . ' ' . $category);
        if (preg_match('/t-shirt|kaos|polo|shirt|kemeja/i', $combined)) {
            return 'Cotton Combed 30s';
        }
        if (preg_match('/jaket|jacket|hoodie|parka|windproof/i', $combined)) {
            return 'Polyester Windproof';
        }
        if (preg_match('/celana|pants|cargo|shorts/i', $combined)) {
            return 'Ripstop Polyester';
        }
        if (preg_match('/tas|bag|backpack|ransel|carrier|hydropack|duffel|sling|pouch|waist/i', $combined)) {
            return 'Poliester 600D';
        }
        if (preg_match('/sepatu|shoe|boot|sandal/i', $combined)) {
            return 'EVA & Rubber Sole';
        }
        if (preg_match('/topi|cap|hat|beanie/i', $combined)) {
            return 'Cotton Twill';
        }
        if (preg_match('/tenda|tent|sleeping|matras|flysheet|hammock/i', $combined)) {
            return 'Ripstop Nylon 210T';
        }
        if (preg_match('/botol|bottle|tumbler|cookware|kompor|stove/i', $combined)) {
            return 'Stainless Steel 304';
        }
        if (preg_match('/jam|watch|headlamp|gadget/i', $combined)) {
            return 'Polycarbonate & Silicone';
        }

        return 'Polyester Synthetic';
    }

    /**
     * Resolve store Zone ID based on name, category, and gender.
     */
    public static function resolveZoneId(string $name, string $category = '', ?string $gender = null): ?int
    {
        // Zones are editable master data; always use the current mapping.
        $zones = Zone::all();
        if ($zones->isEmpty()) {
            return null;
        }

        $text = strtolower($name . ' ' . $category . ' ' . ($gender ?? ''));

        // Helper to find zone by partial name
        $findZone = function (array $keywords) use ($zones) {
            foreach ($keywords as $kw) {
                $match = $zones->first(fn ($z) => stripos($z->name, $kw) !== false);
                if ($match) return $match->id;
            }
            return null;
        };

        // 1. Sepatu & Alas Kaki
        if (preg_match('/sepatu|shoe|boot|sandal|alas kaki|footwear|socks|kaos kaki/i', $text)) {
            $id = $findZone(['Sepatu', 'Alas Kaki']);
            if ($id) return $id;
        }

        // 2. Tas & Aksesoris
        if (preg_match('/topi|cap|hat|beanie|buff|bandana|tas|bag|ransel|backpack|carrier|duffel|sling|waist|pouch|wallet|dompet|belt|ikat pinggang/i', $text)) {
            $id = $findZone(['Tas', 'Aksesoris']);
            if ($id) return $id;
        }

        // 3. Pakaian Wanita (WS prefix or Women)
        if (preg_match('/(^|\b)ws\b|wanita|women|dress|rok/i', $text)) {
            $id = $findZone(['Pakaian Wanita', 'Wanita']);
            if ($id) return $id;
        }

        // 4. Pakaian Pria & Apparel Umum
        if (preg_match('/pria|men|kaos|shirt|t-shirt|polo|kemeja|jaket|jacket|celana|pants|shorts|sweater|hoodie|vest|apparel|raincoat|poncho/i', $text)) {
            $id = $findZone(['Pakaian Pria', 'Pria']);
            if ($id) return $id;
        }

        // 5. Perlengkapan Camping
        if (preg_match('/tenda|tent|sleeping|matras|mattress|flysheet|hammock|cookware|kompor|gas stove|camping/i', $text)) {
            $id = $findZone(['Camping']);
            if ($id) return $id;
        }

        // 6. Peralatan Mendaki
        if (preg_match('/mendaki|climb|climbing|carabiner|trekking pole|harness|harnes|tali|rope/i', $text)) {
            $id = $findZone(['Mendaki']);
            if ($id) return $id;
        }

        // 7. Elektronik & Gadget
        if (preg_match('/elektronik|gadget|jam|watch|headlamp|senter|gps|kompas|radio|baterai/i', $text)) {
            $id = $findZone(['Elektronik', 'Gadget']);
            if ($id) return $id;
        }

        // 8. Perlengkapan Outdoor (Default Equipment)
        $outdoorId = $findZone(['Outdoor']);
        if ($outdoorId) return $outdoorId;

        return $zones->first()->id;
    }

    /**
     * Enrich a single product with missing description, material, category, and zone.
     * Returns true if product was updated.
     */
    public static function enrichProduct(Product $product, array $extraData = []): bool
    {
        $json = static::findJsonProduct($product->sku);
        $updates = [];

        // 1. Description
        if (empty($product->description)) {
            if (!empty($extraData['description'])) {
                $updates['description'] = trim(strip_tags($extraData['description']));
            } elseif (!empty($json['description'])) {
                $updates['description'] = trim($json['description']);
            } elseif (!empty($extraData['short_description'])) {
                $updates['description'] = trim($extraData['short_description']);
            } else {
                $updates['description'] = sprintf(
                    '%s merupakan produk perlengkapan outdoor berkualitas tinggi dari EIGER yang dirancang untuk memberikan kenyamanan, daya tahan, dan performa optimal saat beraktivitas.',
                    $product->name
                );
            }
        }

        $effectiveDesc = $updates['description'] ?? $product->description;

        // 2. Material
        if (empty($product->material)) {
            if (!empty($extraData['material'])) {
                $updates['material'] = trim($extraData['material']);
            } else {
                $cat = $extraData['category'] ?? $json['category'] ?? '';
                $updates['material'] = static::extractMaterial($effectiveDesc, $product->name, $cat);
            }
        }

        // 3. Zone ID
        if (empty($product->zone_id)) {
            $cat = $extraData['category'] ?? $json['category'] ?? '';
            $gender = $extraData['gender'] ?? $json['gender'] ?? null;
            $zoneId = static::resolveZoneId($product->name, $cat, $gender);
            if ($zoneId) {
                $updates['zone_id'] = $zoneId;
            }
        }

        // 4. Image fallback from json if empty
        if (empty($product->image) && !empty($json['images'][0]['url'])) {
            $updates['image'] = $json['images'][0]['url'];
        }

        if (!empty($updates)) {
            $product->update($updates);
            return true;
        }

        return false;
    }

    /**
     * Backfill all products in the database that are missing zone, material, or description.
     */
    public static function backfillAll(): array
    {
        $products = Product::whereNull('zone_id')
            ->orWhereNull('material')
            ->orWhere('material', '')
            ->orWhereNull('description')
            ->orWhere('description', '')
            ->get();

        $updatedCount = 0;
        foreach ($products as $product) {
            if (static::enrichProduct($product)) {
                $updatedCount++;
            }
        }

        return [
            'total_checked' => $products->count(),
            'updated'       => $updatedCount,
        ];
    }
}
