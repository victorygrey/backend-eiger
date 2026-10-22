<?php

namespace App\Http\Middleware;

use App\Http\Resources\ProductResource;
use App\Models\FitAndGoDevice;
use App\Models\Product;
use App\Models\Tablet;
use App\Services\FitAndGoProductClassifier;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class CmsJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if (! $request->expectsJson() || $request->header('X-CMS-Interface') !== 'nuxt' || ! $request->is('admin/*', 'login', 'logout')) {
            return $response;
        }

        if ($response instanceof RedirectResponse) {
            $errors = $request->session()->get('errors')?->getBag('default')->getMessages() ?? [];
            $error = $request->session()->get('error');
            return response()->json([
                'success' => ! $errors && ! $error,
                'message' => $error ?? ($errors ? 'Periksa kembali isian formulir.' : ($request->session()->get('success') ?? $request->session()->get('status') ?? 'Perubahan berhasil disimpan.')),
                'errors' => $errors,
                'redirect' => parse_url($response->getTargetUrl(), PHP_URL_PATH),
            ], $errors || $error ? 422 : 200)->header('Cache-Control', 'private, no-store');
        }

        $view = method_exists($response, 'getOriginalContent') ? $response->getOriginalContent() : null;
        if (! $view instanceof View || ! str_starts_with($view->name(), 'admin.')) {
            return $response;
        }
        $data = $view->getData();
        $ready = $data['configuredProductIds'] ?? [];
        $normalize = function (mixed $value, int $depth = 0) use (&$normalize, $request, $ready): mixed {
            if ($depth > 12) {
                return null;
            }
            if ($value instanceof LengthAwarePaginator) {
                return [
                    'data' => $normalize($value->getCollection(), $depth + 1),
                    'current_page' => $value->currentPage(), 'last_page' => $value->lastPage(),
                    'per_page' => $value->perPage(), 'total' => $value->total(),
                ];
            }
            if ($value instanceof Product) {
                $row = (new ProductResource($value))->resolve($request);
                unset($row['pim_payload'], $row['pim_image_payload']);
                return $normalize($row + [
                    'ai_fit_and_go_active' => (bool) $value->ai_fit_and_go_active,
                    'interactive_tablet_active' => (bool) $value->interactive_tablet_active,
                    'fit_group' => app(FitAndGoProductClassifier::class)->groupCode($value),
                    'presentation_configured' => in_array($value->id, $ready),
                ], $depth + 1);
            }
            if ($value instanceof Model) {
                $row = $value->attributesToArray();
                foreach ($value->getRelations() as $key => $relation) {
                    $row[\Illuminate\Support\Str::snake($key)] = $relation;
                }
                if (($value instanceof FitAndGoDevice || $value instanceof Tablet) && $request->routeIs('admin.fit-and-go.devices.edit', 'admin.tablets.edit')) {
                    $row['activation_code'] = $value->activation_code_encrypted;
                }
                return $normalize($row, $depth + 1);
            }
            if ($value instanceof JsonResource) {
                return $normalize($value->resolve($request), $depth + 1);
            }
            if ($value instanceof Collection) {
                return $normalize($value->all(), $depth + 1);
            }
            if ($value instanceof \DateTimeInterface) {
                return $value->format(DATE_ATOM);
            }
            if (is_array($value)) {
                $result = [];
                foreach ($value as $key => $item) {
                    if (is_string($key) && preg_match('/password|token|secret|activation_code_(hash|lookup|encrypted)/i', $key)) {
                        continue;
                    }
                    $result[$key] = $normalize($item, $depth + 1);
                }
                return $result;
            }
            if ($value instanceof \stdClass) {
                return $normalize((array) $value, $depth + 1);
            }
            return is_scalar($value) || $value === null ? $value : null;
        };

        return response()->json(['data' => $normalize($data), 'meta' => ['view' => $view->name(), 'api_version' => 1]])
            ->header('Cache-Control', 'private, no-store');
    }
}
