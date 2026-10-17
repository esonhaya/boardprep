#!/usr/bin/env sh
set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
REPOSITORY_ROOT=$(CDPATH= cd -- "$SCRIPT_DIR/../.." && pwd)

for command_name in php node git tar; do
    if ! command -v "$command_name" >/dev/null 2>&1; then
        printf 'Missing prerequisite: %s\n' "$command_name" >&2
        exit 1
    fi
done

php -r 'exit(PHP_VERSION_ID >= 80100 && extension_loaded("json") && extension_loaded("session") ? 0 : 1);' || {
    printf 'Acceptance Center requires PHP 8.1+ with json and session extensions.\n' >&2
    exit 1
}
node -e 'process.exit(Number(process.versions.node.split(".")[0]) >= 18 ? 0 : 1);' || {
    printf 'Acceptance Center requires Node.js 18 or newer.\n' >&2
    exit 1
}

BUILD_SHA=$(git -C "$REPOSITORY_ROOT" rev-parse --verify HEAD 2>/dev/null || true)
case "$BUILD_SHA" in
    [0-9a-f][0-9a-f]*) ;;
    *) printf 'Unable to identify the committed build to test.\n' >&2; exit 1 ;;
esac
if [ "${#BUILD_SHA}" -ne 40 ]; then
    printf 'Unable to identify the committed build to test.\n' >&2
    exit 1
fi

if [ -n "${BOARDPREP_ACCEPTANCE_STORAGE_PATH:-}" ]; then
    ACCEPTANCE_STORAGE_PATH=$BOARDPREP_ACCEPTANCE_STORAGE_PATH
elif [ -n "${PREFIX:-}" ]; then
    ACCEPTANCE_STORAGE_PATH=$PREFIX/share/boardprep/acceptance-center
elif [ -n "${XDG_STATE_HOME:-}" ]; then
    ACCEPTANCE_STORAGE_PATH=$XDG_STATE_HOME/boardprep/acceptance-center
else
    ACCEPTANCE_STORAGE_PATH=${HOME:?Set HOME or XDG_STATE_HOME}/.local/state/boardprep/acceptance-center
fi

printf 'Starting BoardPrep Human Acceptance Center.\n'
printf 'Committed build: %s\n' "$BUILD_SHA"
printf 'The preview serves this committed build only; uncommitted source changes are not included.\n'
printf 'Feedback stays in: %s\n' "$ACCEPTANCE_STORAGE_PATH"
printf 'Use Ctrl-C to stop the isolated preview; saved feedback remains.\n'

exec node "$REPOSITORY_ROOT/tools/acceptance/server.cjs" \
    --repository-root "$REPOSITORY_ROOT" \
    --build-sha "$BUILD_SHA" \
    --acceptance-storage "$ACCEPTANCE_STORAGE_PATH"
