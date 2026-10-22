# Kiosk photo sessions

The kiosk server uploads the final approved, upscaled image when the visitor finishes. The CMS creates the canonical photo code and displays the result under **Histori Foto & Print**. This does not create a print job or establish purchase eligibility. No original webcam photo, intermediate result, or visitor email is retained by this API.

## Authentication and endpoints

Use the existing paired device bearer token, over HTTPS. An active kiosk without a device token cannot upload or retrieve photos, even when its catalog is public. A device can only access its own sessions. Never embed the token in frontend JavaScript or use the CMS administrator password as an API credential.

| Method | Path (under `/api/v1/fit-and-go`) | Purpose |
| --- | --- | --- |
| POST | `/kiosks/{deviceCode}/sessions` | Store a completed fitting |
| GET | `/kiosks/{deviceCode}/sessions/{code}` | Authenticated receipt lookup |
| GET | `/kiosks/{deviceCode}/sessions/{code}/image` | Authenticated original final image |
| GET | `/kiosks/{deviceCode}/sessions/capabilities` | Authenticated upload capabilities |

All requests require `Authorization: Bearer <device token>` and `Accept: application/json`. POST also requires `Idempotency-Key: <UUID>` and multipart fields:

- `image`: actual PNG, JPEG, or WebP, up to 64,000,000 bytes, 40 megapixels, 16,000 pixels per axis.
- `consent`: `accepted`.
- `gender`: `men` or `women`.
- `activity`: slug up to 100 characters.
- `background_id`: optional identifier up to 100 characters.
- `products`: JSON string containing one to five snapshots, one per category (`hat`, `upper`, `lower`, `shoes`, `bag`).

```json
[{"product_id":501,"product_sku":"910011123","color":"BLACK","category":"hat","catalog_item_id":50101}]
```

The curated kiosk's `catalog_item_id` identifies a color reference, not a CMS database product. Product/SKU references are snapshots; they do not require the current catalog to retain that product or color. IDs and categories are checked against the catalog by the kiosk server before upload.

Recovery of a previously completed local fitting may additionally send `capture_code` (exactly ten uppercase hexadecimal characters) to preserve its already-emailed code. Normal new fittings omit this field and receive a CMS-generated code. A different session cannot overwrite an existing code: collisions return 409, and retries must keep the same image, metadata and code. Check authenticated `/sessions/capabilities` for `data.preserve_capture_code: true` before importing; older deployments may ignore the extra field. Recovery must not resend email or upload expired local photos.

After both file storage and the database transaction succeed, POST returns HTTP 201:

```json
{"status":"success","data":{"id":123,"code":"8FD137AE02","device_code":"fit-and-go-lantai-1","products":[{"product_id":501,"product_sku":"910011123","color":"BLACK","category":"hat","catalog_item_id":50101}],"expires_at":"2026-10-22T16:59:59.000000Z"}}
```

The same UUID and image/metadata return the same receipt with HTTP 200. A conflicting payload returns 409. Device writes serialize before the duplicate check, including SQLite. UUIDs are bound to a completed result; retain them across network/email retries. Expired UUIDs return 410 and cannot recreate the photo. Other errors include 401 for invalid/inactive device credentials, 404 for another kiosk's or unknown code, 422 for invalid fields/files, and 429 for excessive uploads. Temporary storage errors return a non-success response and roll back the database and partial files; retry with the same UUID.

## Storage and retention

Private disk `fit-photos` is rooted at `storage/app/private/fit-photos`. `FIT_PHOTO_PATH` can override the private root for a separately mounted dataset. It must be writable by `www-data` and persisted across container updates. It is deliberately outside `public/storage` and the public PIM media dataset. The existing TrueNAS storage bind mount already persists this location; the updated entrypoint creates the directory and sets its permissions.

The full final file is retained byte-for-byte. A small JPEG thumbnail is generated for images up to 16 megapixels to bound decoder memory; larger images keep their final file and can be viewed in detail. Admin previews require an authenticated user with `print-history.view`. Image responses disable caching and refuse expired photos immediately.

The new opt-in JSON CMS interface is supported: history list/detail records include `photo_url`, `thumbnail_url`, and `photo_available`. These are authenticated admin image routes, not public file locations. Private filesystem paths, request hashes and idempotency fields are excluded from serialized history records. Expired records expose no image URLs, while their code/audit record remains available.

`FIT_PHOTO_TIMEZONE` defaults to `Asia/Jakarta`. Expiry is 23:59:59 on the store day at upload time, returned in UTC. `php artisan fit-photos:purge` removes expired final images/thumbnails and clears fitting metadata. Minimal code/device/idempotency and print audit records remain so expired codes and replayed requests stay expired. Customer emails are never stored here. Backup retention is separate from live file retention and must be configured at the storage layer.

Private fitting timestamps are stored/read as UTC even when `APP_TIMEZONE=Asia/Jakarta`; the purge query also compares UTC. An older deployment could interpret the UTC expiry as local time and purge photos seven hours early. Explicit code-preserving import can restore such a purged result only with its original kiosk, UUID and code and only while its original UTC expiry is still future. Recovery never extends the expiry or revives a genuinely expired photo. `/sessions/capabilities` advertises `utc_photo_timestamps: true` after this fix is deployed.

Docker Supervisor runs a dedicated purge loop every 60 seconds, independent of `RUN_SCHEDULER`, which may be disabled for unrelated integrations. Laravel's scheduler also registers the idempotent purge command for installations outside Docker. For a non-container installation, keep `schedule:work` running or configure the normal Laravel scheduler cron. Monitor retention-worker errors and available disk space.

## Release and kiosk configuration

1. Release this backend through the existing GitHub image/Watchtower process. The entrypoint backs up SQLite and runs migrations. For a non-container deployment, run `php artisan migrate --force`, clear/rebuild cached config/routes/views, and enable the scheduler.
2. Verify `GET /api/v1/fit-and-go/kiosks/fit-and-go-lantai-1/sessions/0000000000` without credentials returns JSON 401, not 404 (route unavailable).
3. On the kiosk server only, set:

```dotenv
SESSION_STORAGE=cms
CMS_API_URL=https://eiger-dev.automataserver.com/api
CMS_KIOSK_SLUG=fit-and-go-lantai-1
CMS_DEVICE_TOKEN=<existing paired Lantai 1 token>
```

4. Restart the kiosk server. Finish a test fitting without email. Confirm its code/photo in CMS history, and test the printer proxy using its separate `PRINTER_API_TOKEN`.

Do not re-activate an already paired live device merely to recover a forgotten token: `/activate` issues a replacement and invalidates the old one. Reuse the existing token, or coordinate re-pairing the affected device. Administrator web sign-in cannot recover a hashed device token and does not deploy local code.

The kiosk defaults to its existing `SESSION_STORAGE=local` until deployment and token configuration are complete. CMS mode fails closed on upload failure; it does not silently create a different local code. Email delivery is separate: SMTP failure leaves the CMS photo intact, and the visitor can retry email or finish without it using the same code. SMTP acceptance and CMS persistence are separate operations; email delivery does not provide an exactly-once guarantee.

## Validation

```bash
php artisan test --compact
```

`FitPhotoSessionTest` covers strict device access, invalid image/metadata/consent, unchanged retries, conflicting retries, scoped lookup, byte-for-byte print image downloads, end-of-day expiry, purge, authenticated Blade/JSON CMS previews, and partial-storage rollback.

See [the Print app handoff](print-app-handoff.md) for its current repository adapter, compatible photo codes, original-image retrieval, and the separate receipt/print-job integration to implement later.
