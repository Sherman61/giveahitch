# WhatsApp Website Bridge — Phase 1

This service proves a reliable WhatsApp connection using [Baileys](https://github.com/WhiskeySockets/Baileys). It does **not** connect to MySQL or a website, and it does not persist messages.

## Requirements

- Node.js 20 or later
- A WhatsApp account and its phone available to approve the linked device

## Setup

```bash
npm install
cp .env.example .env
npm start
```

Open `http://127.0.0.1:3000` to scan the QR code, see connection status, and view live normalized message logs. The dashboard holds logs only in memory; it does not expose session files or persist messages.

With the default empty `PAIRING_PHONE_NUMBER`, the dashboard presents a QR linking flow. Scan it from WhatsApp: **Settings → Linked Devices → Link a Device**. The first successful connection creates local state in `auth/`; later starts reuse it.

Alternatively, set `PAIRING_PHONE_NUMBER` in `.env` to your full international number using digits only, then run `npm start`. The dashboard displays a pairing code to enter from Linked Devices.

## Security

`auth/` contains the linked-device authentication state and is excluded from Git, as is `.env`. Anyone who obtains that authentication state may potentially gain access to the linked WhatsApp session. Keep the directory private, restrict server access, and never serve it from an HTTP static directory. No credentials belong in source control. The QR code is also sensitive: this dashboard binds to `127.0.0.1` by default. If you make it remote, put it behind HTTPS and strong authentication (for example, an authenticated reverse proxy).

To intentionally unlink this service, remove the linked device from WhatsApp. If the session becomes invalid, delete the local `auth/` directory and link again (only after confirming it is the intended session to remove).

## What Phase 1 logs

Only incoming, non-status/non-broadcast messages are handled. Each supported message is normalized before it is logged. Group logs include group JID/name, sender JID/display name, message body, message ID, and Unix timestamp. Private messages are logged in the same normalized shape. Supported body formats are normal and extended text plus image/video captions; unsupported formats are safely identified and skipped.

## Project layout

- `src/config.js` — environment configuration
- `src/whatsapp.js` — session lifecycle, connection/reconnection, group metadata cache
- `src/messageHandler.js` — reusable message filtering and parser
- `src/index.js` — process bootstrap and graceful shutdown

## Next phase

Add an explicit persistence adapter and website integration after this connection/logging stage has been verified.
