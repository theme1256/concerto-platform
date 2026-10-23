#!/usr/bin/env bash
set -euo pipefail

PLATFORM="linux/amd64"

docker buildx build --pull --platform "$PLATFORM" --load -t campsych/concerto-platform:test .
docker run --rm --network none --entrypoint sh campsych/concerto-platform:test \
  /usr/local/share/concerto/php/verify-runtime.sh
docker run --rm --network none --entrypoint php campsych/concerto-platform:test \
  /app/concerto/tests/php-runtime/check.php
