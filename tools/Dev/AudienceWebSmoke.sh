#!/data/data/com.termux/files/usr/bin/bash
set -e

PORT="${1:-8099}"
BASE_URL="http://127.0.0.1:${PORT}"
DIR=".boardprep-web-smoke"

rm -rf "$DIR"
mkdir -p "$DIR"

php -S "127.0.0.1:${PORT}" -t public >"$DIR/server.log" 2>&1 &
SERVER_PID=$!

cleanup() {
    kill "$SERVER_PID" 2>/dev/null || true
    rm -rf "$DIR"
}
trap cleanup EXIT

sleep 1

echo "=== GET /quiz ==="

curl -sS \
    -o "$DIR/settings.html" \
    -c "$DIR/cookie" \
    -b "$DIR/cookie" \
    -w 'HTTP %{http_code} | redirects=%{num_redirects} | bytes=%{size_download}\n' \
    "$BASE_URL/quiz"

grep -q 'name="action"' "$DIR/settings.html"
grep -q 'value="start"' "$DIR/settings.html"
echo "[PASS] Settings exposes action=start."

echo "=== QUIZ START ==="

curl -sS \
    -o "$DIR/question.html" \
    -c "$DIR/cookie" \
    -b "$DIR/cookie" \
    -w 'HTTP %{http_code} | redirects=%{num_redirects} | bytes=%{size_download}\n' \
    "$BASE_URL/quiz?action=start&count=5&difficulty=mixed&mode=practice&subject=English"

if grep -q 'Question [0-9]' "$DIR/question.html"; then

    echo "[PASS] Quiz generation reached question view."

    grep -q 'action="/quiz?action=submit"' "$DIR/question.html"
    echo "[PASS] Submit action contract present."

    grep -q 'action="/quiz?action=next"' "$DIR/question.html"
    echo "[PASS] Next action contract present."

    ANSWER=$(
        grep -o 'name="answer"[^>]*value="[^"]*"' "$DIR/question.html" |
        head -1 |
        sed -E 's/.*value="([^"]*)".*/\1/'
    )

    if [ -n "$ANSWER" ]; then

        echo "=== SUBMIT ANSWER ==="

        curl -sS \
            -o "$DIR/submitted.html" \
            -c "$DIR/cookie" \
            -b "$DIR/cookie" \
            -X POST \
            -d "action=submit&answer=${ANSWER}" \
            -w 'HTTP %{http_code} | redirects=%{num_redirects} | bytes=%{size_download}\n' \
            "$BASE_URL/quiz?action=submit"

        if grep -qE 'Correct|Incorrect|Explanation|Next Question|Finish Quiz' "$DIR/submitted.html"; then
            echo "[PASS] Submission reached feedback/navigation."
        else
            echo "[FAIL] Submission did not reach expected quiz state."
            exit 1
        fi
    else
        echo "[INFO] No answer option detected."
    fi

else

    echo "[INFO] Quiz generation returned no question."

    if grep -qiE 'no question|no questions|not available' "$DIR/question.html"; then
        echo "[INFO] Explicit no-question state detected."
    else
        echo "[FAIL] Unrecognized quiz-start response."
        exit 1
    fi

fi

echo "=== SERVER ERROR SIGNAL ==="

if grep -qE 'Fatal error|Parse error|Uncaught|Warning|ERROR' "$DIR/server.log"; then
    grep -E 'Fatal error|Parse error|Uncaught|Warning|ERROR' "$DIR/server.log" | tail -20
    exit 1
fi

echo "[PASS] No PHP fatal/error signal."
echo "=== AUDIENCE WEB SMOKE PASSED ==="
