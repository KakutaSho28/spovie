#!/bin/bash
# =========================================================
# 本番/ローカル API スモークテスト
# 登録 → ログイン → YouTube動画登録 → アノテーション作成 → 共有リンク発行 → 共有JSON取得
# 使い方: bash scripts/smoke-test.sh https://<railway-domain>/api [https://<vercel-domain>]
# 第2引数（任意）を渡すと、CORS プリフライトとフロントのSPAルーティングも確認する。
# =========================================================
set -euo pipefail

API="${1:?usage: smoke-test.sh <api-base-url> [frontend-url]}"
FRONT="${2:-}"
API="${API%/}"
EMAIL="smoke-$(date +%s)-$RANDOM@example.com"
PASS="password123"

req() { curl -sS --max-time 30 -w '\n%{http_code}' "$@"; }
check() { # name expected_status body_and_status
  local name="$1" expected="$2" out="$3"
  local status="${out##*$'\n'}"
  if [ "$status" != "$expected" ]; then
    echo "✗ $name (expected $expected, got $status)"; echo "${out%$'\n'*}"; exit 1
  fi
  echo "✓ $name ($status)"
}
body() { printf '%s' "${1%$'\n'*}"; }
json() { python3 -c "import sys,json; print(json.load(sys.stdin)$1)"; }

out=$(req "$API/health");                                                   check "health" 200 "$out"
out=$(req -X POST "$API/auth/register" -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d "{\"name\":\"Smoke\",\"email\":\"$EMAIL\",\"password\":\"$PASS\",\"password_confirmation\":\"$PASS\"}"); check "register" 201 "$out"
out=$(req -X POST "$API/auth/login" -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d "{\"email\":\"$EMAIL\",\"password\":\"$PASS\"}");                       check "login" 200 "$out"
TOKEN=$(body "$out" | json "['data']['token']")
AUTH=(-H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' -H 'Accept: application/json')

out=$(req -X POST "$API/videos" "${AUTH[@]}" \
  -d '{"title":"Smoke video","youtube_url":"https://www.youtube.com/watch?v=dQw4w9WgXcQ"}'); check "create YouTube video" 201 "$out"
VIDEO_ID=$(body "$out" | json "['data']['id']")

out=$(req "$API/videos/$VIDEO_ID" "${AUTH[@]}");                            check "show video" 200 "$out"
out=$(req -X POST "$API/videos/$VIDEO_ID/annotations" "${AUTH[@]}" \
  -d '{"start_seconds":1,"end_seconds":5,"canvas_data":{"canvas_width":1280,"canvas_height":720,"objects":[]},"comment":"smoke"}'); check "create annotation" 201 "$out"
ANNO_ID=$(body "$out" | json "['data']['id']")

out=$(req -X POST "$API/annotations/$ANNO_ID/share" "${AUTH[@]}" -d '{}');  check "create share link" 201 "$out"
SHARE_TOKEN=$(body "$out" | json "['data']['token']")
SHARE_URL=$(body "$out" | json "['data']['share_url']")
echo "  share_url: $SHARE_URL"

out=$(req "$API/share/$SHARE_TOKEN" -H 'Accept: application/json');         check "open share link (no auth)" 200 "$out"
[ "$(body "$out" | json "['data']['video']['youtube_video_id']")" = "dQw4w9WgXcQ" ] && echo "✓ share JSON has the video"

out=$(req "$API/videos" -H 'Accept: application/json');                     check "protected route rejects anonymous" 401 "$out"

if [ -n "$FRONT" ]; then
  FRONT="${FRONT%/}"
  hdr=$(curl -sS -o /dev/null -D - -X OPTIONS "$API/videos" -H "Origin: $FRONT" \
    -H 'Access-Control-Request-Method: POST' -H 'Access-Control-Request-Headers: authorization,content-type' | tr -d '\r')
  echo "$hdr" | grep -qi "^access-control-allow-origin: $FRONT" && echo "✓ CORS allows $FRONT" || { echo "✗ CORS does not allow $FRONT"; echo "$hdr"; exit 1; }
  code=$(curl -sS -o /dev/null -w '%{http_code}' "$FRONT/share/$SHARE_TOKEN")
  [ "$code" = "200" ] && echo "✓ frontend serves /share/<token> (SPA rewrite)" || { echo "✗ frontend /share/<token> returned $code"; exit 1; }
fi

echo "All smoke checks passed."
