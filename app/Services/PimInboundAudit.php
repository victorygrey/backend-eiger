<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class PimInboundAudit
{
    private readonly float $startedAt;

    private string $stage = 'request.received';

    /** @var array<string, mixed> */
    private array $stageContext = [];

    /** @var array<string, mixed> */
    private array $baseContext;

    public function __construct(Request $request, string $endpoint)
    {
        $this->startedAt = microtime(true);

        $requestId = trim((string) $request->header('X-Request-ID'));
        if (! preg_match('/\A[A-Za-z0-9._:-]{1,128}\z/D', $requestId)) {
            $requestId = (string) Str::uuid();
        }

        if ($endpoint === 'image') {
            $isWrapped = is_array($request->input('image'));
            $product = [];
            $image = $isWrapped ? $request->input('image', []) : $request->all();
            $payloadMode = $isWrapped ? 'wrapped' : 'official';
            $genericSku = data_get($image, 'generic.0.sku');
            $variantCount = is_array(data_get($image, 'variant')) ? count(data_get($image, 'variant')) : 0;
        } else {
            $isCombined = is_array($request->input('product'));
            $product = $isCombined ? $request->input('product', []) : $request->all();
            $image = $isCombined && is_array($request->input('image')) ? $request->input('image') : [];
            $payloadMode = $isCombined ? 'combined' : 'official';
            $genericSku = data_get($product, 'generic');
            $variantCount = is_array(data_get($product, 'variant')) ? count(data_get($product, 'variant')) : 0;
        }

        $this->baseContext = [
            'request_id' => $requestId,
            'endpoint' => $endpoint,
            'payload_mode' => $payloadMode,
            'generic_sku' => $this->safeSku($genericSku),
            'variant_count' => $variantCount,
            'product_media_file_count' => $this->productMediaCount($product),
            'image_asset_count' => $this->imageAssetCount($image),
            'content_length' => max(0, (int) $request->server('CONTENT_LENGTH', 0)),
        ];
    }

    public function requestId(): string
    {
        return $this->baseContext['request_id'];
    }

    public function received(): void
    {
        $this->write('info', 'PIM inbound request received');
    }

    /** @param array<string, mixed> $context */
    public function stage(string $stage, array $context = [], bool $emit = false): void
    {
        $this->stage = $stage;
        $this->stageContext = $this->sanitizeContext($context);

        if ($emit) {
            $this->write('info', 'PIM inbound stage completed');
        }
    }

    /** @param array<string, mixed> $context */
    public function success(int $status, array $context = []): void
    {
        $this->stage = 'request.completed';
        $this->stageContext = [];
        $this->write('info', 'PIM inbound request completed', [
            'http_status' => $status,
            ...$this->sanitizeContext($context),
        ]);
    }

    /** @param array<string, mixed> $context */
    public function failure(Throwable $exception, int $status, array $context = []): void
    {
        $details = [
            'http_status' => $status,
            'exception' => $exception::class,
            'exception_message' => $this->sanitizeMessage($exception->getMessage()),
            'exception_file' => basename($exception->getFile()),
            'exception_line' => $exception->getLine(),
        ];

        if ($exception instanceof ValidationException) {
            $details['validation_fields'] = array_keys($exception->errors());
        }

        $this->write('error', 'PIM inbound request failed', [
            ...$details,
            ...$this->sanitizeContext($context),
        ]);
    }

    /** @param array<string, mixed> $context */
    public function failureMessage(int $status, string $message, array $context = []): void
    {
        $this->write('error', 'PIM inbound request failed', [
            'http_status' => $status,
            'exception_message' => $this->sanitizeMessage($message),
            ...$this->sanitizeContext($context),
        ]);
    }

    /** @param array<string, mixed> $context */
    private function write(string $level, string $message, array $context = []): void
    {
        $record = [
            ...$this->baseContext,
            'stage' => $this->stage,
            'stage_context' => $this->stageContext,
            'duration_ms' => (int) round((microtime(true) - $this->startedAt) * 1000),
            ...$this->sanitizeContext($context),
        ];

        try {
            Log::channel('pim_inbound')->log($level, $message, $record);
        } catch (Throwable $loggingFailure) {
            error_log(json_encode([
                'message' => 'PIM inbound audit logger failed',
                'request_id' => $this->baseContext['request_id'],
                'error' => $this->sanitizeMessage($loggingFailure->getMessage()),
            ], JSON_UNESCAPED_SLASHES));
        }
    }

    private function safeSku(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $sku = trim((string) $value);

        return preg_match('/\A[A-Za-z0-9._-]{1,100}\z/D', $sku) ? $sku : null;
    }

    /** @param array<string, mixed> $product */
    private function productMediaCount(array $product): int
    {
        $count = 0;
        foreach ($product['media'] ?? [] as $group) {
            if (! is_array($group)) {
                continue;
            }
            $count += is_array($group['files'] ?? null) ? count($group['files']) : 0;
        }

        return $count;
    }

    /** @param array<string, mixed> $image */
    private function imageAssetCount(array $image): int
    {
        $count = 0;
        foreach (['generic', 'variant'] as $group) {
            foreach ($image[$group] ?? [] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $count += is_array($row['image'] ?? null) ? count($row['image']) : 0;
            }
        }

        return $count;
    }

    private function sanitizeMessage(string $message): string
    {
        $message = preg_replace('~https?://\S+~i', '[url-redacted]', $message) ?? $message;
        $message = preg_replace('/\bBearer\s+\S+/i', 'Bearer [redacted]', $message) ?? $message;
        $message = preg_replace('/\bpim_[A-Za-z0-9_-]+/', '[token-redacted]', $message) ?? $message;

        return Str::limit($message, 1000, '...');
    }

    /**
     * Keep audit context scalar and bounded so an exception can never dump a
     * complete payload, bearer token, or signed media URL into container logs.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function sanitizeContext(array $context): array
    {
        $safe = [];
        foreach ($context as $key => $value) {
            if (preg_match('/token|authorization|payload|url/i', (string) $key)) {
                continue;
            }
            if (is_string($value)) {
                $safe[$key] = $this->sanitizeMessage($value);
            } elseif (is_int($value) || is_float($value) || is_bool($value) || $value === null) {
                $safe[$key] = $value;
            } elseif (is_array($value)) {
                $safe[$key] = array_slice(array_values(array_filter($value, 'is_scalar')), 0, 50);
            }
        }

        return $safe;
    }
}
