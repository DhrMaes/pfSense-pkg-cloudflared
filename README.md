# pfSense-pkg-cloudflared

pfSense package for managing `cloudflared` (Cloudflare Tunnels) with a Web GUI.

## Installation

Download the `.pkg` from Releases / Artifacts, transfer it to pfSense (e.g. `/tmp`), and run:

```bash
pkg add pfSense-pkg-cloudflared-*.pkg
```

This single command automatically installs the binary dependency, registers the Web GUI, and enables the service.

## Configuration

1. Go to **Services > Cloudflare Tunnels** in pfSense.
2. Click **Add**, paste your Zero Trust Tunnel token, and save.
3. Monitor status and logs under **Status > Cloudflare Tunnels**.

## Dev Deployment (pfSense VM)

1. Copy `build/.env.example` to `build/.env` and set `PFSENSE_IP` and `PFSENSE_USER`.
2. Run build and deployment:

```powershell
.\build\build.ps1
```
