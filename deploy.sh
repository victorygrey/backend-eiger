#!/bin/sh
# Update an existing TrueNAS Apps deployment from its GitHub checkout.
set -eu
umask 077
cd "$(dirname "$0")"
test -z "$(git status --porcelain)" || { echo 'Commit or preserve local changes before deploying.' >&2; exit 1; }
git pull --ff-only
tag="eiger-backend:$(git rev-parse --short=12 HEAD)"
docker build -t "$tag" .
config_file=$(mktemp)
trap 'rm -f "$config_file"' EXIT
midclt call app.config eiger-backend > "$config_file"
request=$(python3 - "$tag" "$config_file" <<'PY'
import json, sys
with open(sys.argv[2]) as f:
    config = json.load(f)
config['services']['app']['image'] = sys.argv[1]
print(json.dumps({'custom_compose_config': config}))
PY
)
job=$(midclt call app.update eiger-backend "$request")
printf 'TrueNAS update job: %s\n' "$job"
echo 'Check Apps job completion and /up. Database backup runs before migration; no seed/reset is performed.'
