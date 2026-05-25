#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'USAGE'
Usage:
  ./scripts/digestpipe-poll.sh <domain[:port]> [interval_seconds]

Examples:
  ./scripts/digestpipe-poll.sh digestpipe-main-hzt8l1.laravel.cloud
  ./scripts/digestpipe-poll.sh digestpipe-main-hzt8l1.laravel.cloud 300
  ./scripts/digestpipe-poll.sh localhost:8080 60

URL scheme:
  localhost or localhost:<port> uses http://
  other domains use https://
USAGE
}

if [[ $# -lt 1 || $# -gt 2 ]]; then
  usage >&2
  exit 1
fi

target="${1%/}"
interval="${2:-300}"

if ! [[ "${interval}" =~ ^[1-9][0-9]*$ ]]; then
  echo "interval_seconds must be a positive integer." >&2
  exit 1
fi

if [[ "${target}" == "localhost" || "${target}" =~ ^localhost:[0-9]+$ ]]; then
  base_url="http://${target}"
else
  base_url="https://${target}"
fi

while true; do
  timestamp="$(date -u +"%Y-%m-%dT%H:%M:%SZ")"

  if curl -fsS "${base_url}/up" >/dev/null; then
    echo "${timestamp} OK ${base_url}/up"
  else
    status=$?
    echo "${timestamp} ERROR ${base_url}/up curl_exit=${status}" >&2
    exit "${status}"
  fi

  sleep "${interval}"
done
