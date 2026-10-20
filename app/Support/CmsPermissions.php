<?php

namespace App\Support;

class CmsPermissions
{
    public static function modules(): array
    {
        return [
            'dashboard' => ['label' => 'Dashboard', 'actions' => ['view'], 'route' => 'admin.dashboard'],
            'products' => ['label' => 'Products', 'actions' => ['view', 'edit', 'delete', 'export'], 'route' => 'admin.products.index'],
            'zones' => ['label' => 'Zones', 'actions' => ['view', 'edit', 'delete'], 'route' => 'admin.zones.index'],
            'rfid-tags' => ['label' => 'RFID Tags', 'actions' => ['view', 'edit', 'delete', 'export'], 'route' => 'admin.rfid-tags.index'],
            'fit-and-go' => ['label' => 'AI Fit & Go', 'actions' => ['view', 'edit'], 'route' => 'admin.fit-and-go.index'],
            'tablets' => ['label' => 'Interactive Tablet', 'actions' => ['view', 'edit'], 'route' => 'admin.tablets.index'],
            'led-ambience' => ['label' => 'LED Ambience', 'actions' => ['view', 'edit'], 'route' => 'admin.led-ambience.index'],
            'table-expedition' => ['label' => 'Table Expedition', 'actions' => ['view', 'edit'], 'route' => 'admin.table-expedition.index'],
            'print-history' => ['label' => 'Histori Foto & Print', 'actions' => ['view', 'export'], 'route' => 'admin.print-history.index'],
            'integrations' => ['label' => 'Integrasi PIM & CARE', 'actions' => ['view', 'edit'], 'route' => 'admin.integrations.index'],
            'sync-logs' => ['label' => 'Sync Logs', 'actions' => ['view', 'export'], 'route' => 'admin.sync-logs.index'],
        ];
    }

    public static function keys(): array
    {
        $keys = [];
        foreach (self::modules() as $module => $definition) {
            foreach ($definition['actions'] as $action) {
                $keys[] = "$module.$action";
            }
        }

        return $keys;
    }

    public static function normalize(array $permissions): array
    {
        $permissions = array_values(array_intersect(self::keys(), $permissions));
        foreach ($permissions as $key) {
            $permissions[] = explode('.', $key)[0].'.view';
        }

        return array_values(array_unique($permissions));
    }

    public static function forRoute(?string $name, string $method): ?string
    {
        if ($name === 'admin.dashboard') {
            return 'dashboard.view';
        }
        $parts = explode('.', (string) $name);
        $module = $parts[1] ?? '';
        if (in_array($module, ['pim', 'care'], true)) {
            $module = 'integrations';
        }
        if (! isset(self::modules()[$module])) {
            return null;
        }
        $action = str_ends_with((string) $name, '.export') ? 'export'
            : ($method === 'DELETE' ? 'delete'
                : (! in_array($method, ['GET', 'HEAD'], true) || in_array(end($parts), ['create', 'edit'], true) ? 'edit' : 'view'));

        return "$module.$action";
    }
}
