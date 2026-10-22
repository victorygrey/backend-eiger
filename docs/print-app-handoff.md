# Saved fitting photos and the Windows Print app

Reviewed against `D:/SDK-I809/Print` (`server/repository.js`, `server/kiosk.js`, `server/printer.js` and `scripts/PhotoPrint.cs`). The Print app currently uses `StaticCodeRepository` and demo codes. This change stores fitting results in CMS history; it does not replace that repository or send anything to a physical printer.

## Photo contract

The Fit & Go server uploads only the approved final image to `POST /api/v1/fit-and-go/kiosks/{deviceCode}/sessions`. CMS returns its canonical **10-character uppercase hexadecimal code**, kiosk, product snapshots and `expires_at`. Print's existing `normalizeCode()` accepts this format. Repeating the same idempotency UUID/payload returns the same code. Fitting completion creates a `PhotoCapture`, never a `PrintJob`.

The final image is stored byte-for-byte on the private `fit-photos` disk. Admins can see it in **Histori Foto & Print**. Both the Blade and JSON interfaces work; JSON history provides `photo_url`, `thumbnail_url` and `photo_available`. These URLs require the admin session and `print-history.view`, and are not URLs for an unattended print client. Filesystem paths and upload hashes are excluded from JSON.

Server-side code lookup and download use the existing paired fitting-kiosk credential:

| Request | Result |
| --- | --- |
| `GET /api/v1/fit-and-go/kiosks/{deviceCode}/sessions/{code}` | `{status, data: {id, code, device_code, products, expires_at}}` |
| `GET /api/v1/fit-and-go/kiosks/{deviceCode}/sessions/{code}/image` | Original image bytes and actual `Content-Type`; no JSON/base64 |

Send `Authorization: Bearer <paired kiosk token>` over HTTPS from trusted server code. Credentials scope access to that kiosk; a photo code alone does not authorize downloading it. Unknown/other-kiosk codes return 404; expired photos return 410; invalid credentials return 401. Access stops at 23:59:59 store time, including before the purge worker deletes the file. Photo codes are independent of receipt/purchase codes.

The fitting app also provides `GET /api/photo-code/{code}` and `GET /api/photo-code/{code}?image=1` for a future Print adapter, authenticated with its separate server-side `PRINTER_API_TOKEN`. In `SESSION_STORAGE=cms` mode this proxies the CMS receipt/image for the configured fitting kiosk without exposing its device token to Print. It maps unknown/expired photos to 404. This is an alternative to direct CMS access, not an additional public photo URL.

## Later Print adapter

Keep `findReceipt(code)` responsible for the real receipt and purchase eligibility. The current Print app checks `receipt.total >= 500000` and `receipt.photoCodes`; CMS photo storage does not supply a receipt association or redemption policy. Connect that source separately when implementing live printing.

After receipt eligibility and photo authorization, `findPhoto(receipt, code)` should look up the photo, download its full original into a controlled path under Print's `.runtime/`, and return `{code, label, path}` for `WindowsPrinter`. Keep the expiry and download server-side. Use the final image endpoint, never the small history thumbnail or a screenshot. The current Windows/System.Drawing bridge supports PNG/JPEG; convert WebP to PNG server-side if WebP uploads are used. Current generated final results are PNG.

Print still owns its existing session expiry, serialized spooler submissions, duplicate protection and uncertain-job handling. One-time receipt redemption/reservation and reporting actual print jobs to CMS are separate future integrations; do not mark a saved photo as printed or successful at upload time. Physical printing remains triggered only by the visitor's PRINT action.

## Deployment

The CMS migration, private storage volume and retention worker must be deployed before enabling `SESSION_STORAGE=cms` in the fitting app. Preserve the existing Lantai 1 pairing. No live device needs re-pairing for this feature. This local implementation does not deploy the server or switch the currently running fitting app away from local session storage.
