# build/build.ps1
# Reads build/.env, syncs package files to pfSense dev VM, builds, installs, and fetches built .pkg to dist/

$repoRoot = Resolve-Path "$PSScriptRoot/.."
Set-Location $repoRoot

$envFile = Join-Path $PSScriptRoot ".env"
if (-not (Test-Path $envFile)) {
    $envFile = Join-Path $repoRoot ".env"
}

if (-not (Test-Path $envFile)) {
    Write-Host "Error: .env file not found. Copy build/.env.example to build/.env and set your PFSENSE_IP." -ForegroundColor Red
    exit 1
}

Get-Content $envFile | ForEach-Object {
    if ($_ -match '^\s*([^#=]+)\s*=\s*(.*)\s*$') {
        [Environment]::SetEnvironmentVariable($matches[1].Trim(), $matches[2].Trim())
    }
}

$ip = $env:PFSENSE_IP
$user = if ($env:PFSENSE_USER) { $env:PFSENSE_USER } else { "root" }

if (-not $ip) {
    Write-Host "Error: PFSENSE_IP is not set in build/.env" -ForegroundColor Red
    exit 1
}

$distDir = Join-Path $repoRoot "dist"
if (-not (Test-Path $distDir)) {
    New-Item -ItemType Directory -Path $distDir | Out-Null
}

Write-Host "Syncing package source to pfSense (${user}@${ip})..." -ForegroundColor Cyan
scp -r net/pfSense-pkg-cloudflared ${user}@${ip}:/tmp/

Write-Host "Building and installing package on pfSense..." -ForegroundColor Cyan

# Create remote build script on pfSense
$shScript = @'
#!/bin/sh
set -e

if ! command -v cloudflared >/dev/null 2>&1; then
    echo "Installing cloudflared binary package from FreeBSD repository..."
    pkg add -f https://pkg.freebsd.org/FreeBSD:14:amd64/latest/All/cloudflared-2026.2.0_6.pkg 2>/dev/null || \
    pkg add -f https://pkg.freebsd.org/FreeBSD:15:amd64/latest/All/cloudflared-2026.2.0_6.pkg 2>/dev/null || true
fi

if [ ! -f /usr/ports/Mk/bsd.port.mk ]; then
    echo "Fetching FreeBSD ports Mk infrastructure..."
    mkdir -p /usr/ports/Mk
    fetch -q -o /tmp/mk.tar.gz https://codeload.github.com/pfsense/FreeBSD-ports/tar.gz/refs/heads/devel
    tar -xzf /tmp/mk.tar.gz -C /tmp/
    cp -r /tmp/FreeBSD-ports-*/Mk/* /usr/ports/Mk/
    rm -rf /tmp/mk.tar.gz /tmp/FreeBSD-ports-*
fi

mkdir -p /usr/ports/net/pfSense-pkg-cloudflared
cp -r /tmp/pfSense-pkg-cloudflared/* /usr/ports/net/pfSense-pkg-cloudflared/
cd /usr/ports/net/pfSense-pkg-cloudflared

rm -rf work /tmp/pfSense-pkg-cloudflared-*.pkg /tmp/pfSense-pkg-cloudflared-*.txz
make ALLOW_UNSUPPORTED_SYSTEM=yes NO_DEPENDS=yes stage create-manifest
sed -i '' 's/FreeBSD:[0-9]*:[^"]*/FreeBSD:*:*/g' work/.metadir.pfSense-pkg-cloudflared/+MANIFEST work/.metadir.pfSense-pkg-cloudflared/+COMPACT_MANIFEST 2>/dev/null || true

echo "Building package archive (.pkg)..."
pkg create -m work/.metadir.pfSense-pkg-cloudflared -p work/.PLIST.mktmp -r work/stage -o /tmp/

echo "Installing package in FreeBSD & pfSense package manager..."
pkg add -f /tmp/pfSense-pkg-cloudflared-*.pkg

echo "Syncing package configuration..."
/usr/local/bin/php -r 'require_once("config.inc"); require_once("pkg-utils.inc"); delete_package_xml("cloudflared"); install_package_xml("cloudflared"); write_config("Installed cloudflared package");'

echo "Installing runtime files to /usr/local..."
cp -r work/stage/usr/local/* /usr/local/
chmod 755 /usr/local/etc/rc.d/cloudflared

# Tag package with pfSense repository annotation so Package Manager GUI lists it
sqlite3 /var/db/pkg/local.sqlite "
INSERT OR IGNORE INTO annotation (annotation) VALUES ('repository');
INSERT OR IGNORE INTO annotation (annotation) VALUES ('pfSense');
INSERT OR REPLACE INTO pkg_annotation (package_id, tag_id, value_id)
VALUES (
    (SELECT id FROM packages WHERE name = 'pfSense-pkg-cloudflared'),
    (SELECT annotation_id FROM annotation WHERE annotation = 'repository'),
    (SELECT annotation_id FROM annotation WHERE annotation = 'pfSense')
);
"

echo "Syncing service daemon..."
/usr/local/bin/php -r 'require_once("/usr/local/pkg/cloudflared.inc"); cloudflared_resync_config();'

echo "Package successfully deployed and registered!"
'@

$shScript | ssh ${user}@${ip} "cat > /tmp/build_remote.sh && chmod +x /tmp/build_remote.sh && /tmp/build_remote.sh"

Write-Host "Copying built package to dist/..." -ForegroundColor Cyan
scp "${user}@${ip}:/tmp/pfSense-pkg-cloudflared-*.pkg" "$distDir/"

Write-Host "Build complete! Output saved to dist/." -ForegroundColor Green
