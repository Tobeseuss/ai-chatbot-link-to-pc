# ACLP REST API Reference — for AI Agents / Chatbots

> **Persian note (برای کاربر):** این سند عمداً به انگلیسی نوشته شده است، چون مخاطب اصلی آن چت‌بات‌ها و ایجنت‌های هوش مصنوعی هستند که مستندات انگلیسی را قابل‌اعتمادتر پارس می‌کنند. راهنمای فارسی کاربر: `docs/USER-GUIDE.fa.md`. این فایل را در system prompt یا ابزار knowledge چت‌بات خود قرار دهید تا بداند چگونه با سیستم کاربر تعامل کند.

Base URL: `https://YOUR-SITE.com/wp-json/aclp/v1`
Plugin version: 1.4.0 · API namespace: `aclp/v1`

> **v1.4.0:** the onboarding text (`GET /agent-prompt`) is now a **neutral, professional API-integration
> guide** — jobs, endpoints, poll intervals — with no environment backstory. Use this framing in your
> own tool descriptions too (e.g. name the tool `aclp_submit_job`, not `pc_control`).
> **v1.3.1 fix (agent):** users often paste this full REST Base URL into the **agent's** first-run
> setup, which doubled the path and failed with HTTP 404 `rest_no_route`. The agent (v1.3.1+) now
> auto-extracts the site root AND repairs an already-saved wrong `config.json` on every start.
> When instructing a user to set up the agent, tell them to enter **only the site root**
> (e.g. `https://example.com`) — the agent adds the REST path itself.
> **v1.3.0 highlights:** **USER CHAT** — the user can now chat with you directly through the agent
> program (`python aclp_agent.py chat`): four new endpoints (`POST /chat/send`, `GET /chat/pending`,
> `POST /chat/reply`, `GET /chat/replies`) — see section 11 · **auth: `Authorization: Bearer` is now
> the RECOMMENDED header** (some web hosts strip the custom `X-ACLP-Key` header — Bearer always
> survives) · every file object now includes a signed `download_url` that needs **no headers at all**
> · the bundled agent is now **ALL-ENGLISH**, **crash-proof** (window stays open on errors) and has
> **ZERO dependencies** (pure Python standard library — nothing is pip-installed).
> **v1.2.0:** text-only chatbots can work with the connected environment through the agent's **relay mode**
> (`python aclp_agent.py relay <action> ...` — see section 10) · `GET /agent-prompt` accepts
> `?api_key=` to embed a real key · `GET /clients` now returns agent specs (version, python, IP,
> capabilities, controlling AI model) · agents can self-report their controlling AI via the
> `ai_model` register field.
> **v1.1.0:** automatic HTTP fallback when HTTPS fails · public `GET /agent-prompt` ·
> `GET /github-integration` (repo + PAT for developer-agents) · `privilege_status` / `privilege_run`
> and `shell` payload flag `"elevated": true`.

## 0. Protocol fallback — HTTP when HTTPS is broken

The site serves this API over **both** HTTPS and HTTP. If your HTTPS connection fails with an SSL
or connection error, retry the same request with the `http://` scheme — nothing else changes.
`GET /ping` returns `"allow_http_fallback": true` and a ready-made `"http_fallback_url"`.
The bundled Python agent does this automatically (disable in its config.json with
`"allow_http_fallback": false`).

---

## 1. Authentication

Every request requires your API key (created in WordPress admin → AI-PC Link → API Keys).

**RECOMMENDED (v1.3.0+):** use the standard Authorization header — some web hosts silently
strip unknown/custom headers, and Bearer always reaches WordPress:

```
Authorization: Bearer aclp_live_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

Also accepted (any one of):
```
X-ACLP-Key: aclp_live_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
?api_key=aclp_live_xxx   (query parameter)
```

Agent-facing endpoints additionally require the client UID header:

```
X-ACLP-Client-UID: <uuid-of-the-registered-machine>
```

Rate limit: default 240 requests/minute per key → HTTP 429 when exceeded.

## 2. Core concepts

- **Command**: one instruction for one machine. Lifecycle: `pending → sent → running → completed | failed`.
- **Client**: one machine running the ACLP agent. Identified by `client_uid`.
- **Payload**: JSON object with parameters for the action (`type`).
- **wait mode**: send `wait: true` on `POST /commands` and the HTTP response blocks (max 25s) until the result arrives — recommended for short actions.

## 3. Endpoints

### 3.1 `GET /ping` — connectivity + auth check
Response:
```json
{"ok":true,"version":"1.3.0","chat_supported":true,"server_time":"...","site":"...","site_url":"...",
 "http_fallback_url":"http://YOUR-SITE.com/wp-json/aclp/v1","allow_http_fallback":true,
 "docs_url":".../docs/AGENT-API.md"}
```
`chat_supported` is `true` when the plugin is v1.3.0+ (user chat endpoints exist).

### 3.2 `GET /agent-prompt` — public onboarding text for AI agents (no key needed)
Returns `{"ok":true,"version":"...","usage":"...","docs_url":"...","repo_url":"...","prompt":"..."}`.
The `prompt` field is a ready-made, professional instruction block covering **both modes**:
MODE A (chatbots that can execute code — call the REST API directly) and MODE B (TEXT-ONLY
chatbots — they print one `aclp_agent.py relay ...` command per step and the user pastes the
JSON result back). The API key placeholder is left empty on purpose so the agent asks the user;
pass `?api_key=aclp_live_...` to embed a real key into the returned text. Feed it to **any** LLM —
even weak/limited ones — so it can work with this job-processing service without reading this whole
document. The text is deliberately written as a neutral, professional API-integration guide
(v1.4.0). The WordPress admin dashboard shows the same text with an API-key selector,
auto-filled site URL and a copy button.

### 3.3 `GET /github-integration` — repo + PAT for developer-agents (key required)
```json
{"ok":true,"repo_url":"https://github.com/Tobeseuss/ai-chatbot-link-to-pc",
 "pat":"github_pat_...","pat_set":true,
 "pat_required_scopes":"Fine-grained PAT with full read/write ...",
 "workflow_doc":".../docs/AGENT-CONTRIBUTION.md",
 "note":"PAT available. Follow workflow_doc step by step. ..."}
```
Use this when the **user asks you to extend or update the project itself**: clone the repo,
follow the workflow doc (check remote commits first, bump versions, update docs, build ZIPs,
push — owner PAT → publish Release; non-owner PAT → commit to main or fork+PR).
If `pat_set` is `false` (empty `pat`), **ask the user** to paste their GitHub PAT in
*WordPress admin → AI-PC Link → تنظیمات → یکپارچگی گیت‌هاب*. Never guess tokens.

### 3.4 `GET /clients` — list environments bound to this key
```json
{"ok":true,"clients":[
  {"client_uid":"...","name":"my-laptop","os":"Windows 11","hostname":"LAPTOP",
   "ip":"1.2.3.4","online":true,"last_seen_at":"...","commands_total":42,"registered_at":"...",
   "agent_version":"1.2.0","python_version":"3.12.1","ai_model":"ChatGPT",
   "capabilities":["file_delete","file_download","file_list","file_mkdir","file_move","file_read",
     "file_write","http_request","install","kill_process","open_url","ping","privilege_run",
     "privilege_status","process_list","run_python","screenshot","shell","sysinfo","upload_file"]}
]}
```
Pick a `client_uid` from here when multiple environments are connected. `ai_model` is the name of the
AI/chatbot configured as the controller of that environment (register field `ai_model`);
it is empty if the operator did not set it.

### 3.5 `POST /commands` — submit a job to an environment
Request body:
```json
{
  "type": "shell",
  "payload": { "command": "dir C:\\" },
  "client_uid": "optional-uuid",
  "broadcast": false,
  "source": "my-chatbot",
  "wait": true,
  "timeout": 20
}
```
- `type` (required): one of the actions below.
- `payload` (required): action-specific object.
- `client_uid`: target machine. Rules: omitted + 1 client → that client; omitted + several clients but only 1 online → the online one; omitted + several online → HTTP 400 with a `clients` list so you can choose.
- `broadcast: true`: queue for **all online clients** of this key (ignore `wait`).
- `wait` + `timeout` (1–25s): block until result (recommended for short actions).
- `source`: free-form label (your bot's name) shown in the history AND on the per-key
  "AI models in contact" list in the WordPress admin — always send something meaningful here
  (e.g. `"source": "ChatGPT"`), it is how the owner sees which AI used the key.

Immediate response (no wait): `{"ok":true,"commands":[{"command_uid":"...","client_uid":"...","status":"pending"}]}`

Wait-mode response = full command object (same as `GET /commands/{uid}`).

### 3.6 `GET /commands/{command_uid}` — fetch status / result
```json
{
  "command_uid":"...","type":"shell","status":"completed",
  "payload":{...},
  "result":{"exit_code":0,"stdout":"...","stderr":""},
  "error":null,"duration_ms":1234,"source":"my-chatbot",
  "client":{"client_uid":"...","name":"my-laptop","hostname":"LAPTOP"},
  "files":[{"file_id":7,"filename":"out.png","size":48211,"url":"https://site/wp-json/aclp/v1/files/7","direction":"from_pc"}],
  "created_at":"...","sent_at":"...","completed_at":"..."
}
```

### 3.7 `GET /commands?limit=20` — recent commands of this key

### 3.8 `POST /files` (multipart) — upload a file to deliver **to the environment**
Form fields: `file` (binary), optional `command_uid`.
Response: `{"ok":true,"file_id":9,"filename":"setup.zip","size":1048576,"url":"https://site/wp-json/aclp/v1/files/9","download_url":"https://site/wp-json/aclp/v1/files/9?aclp_token=..."}`
Then send a `file_download` command referencing that `file_id`.

### 3.9 `GET /files/{file_id}` — download a file produced by the environment
Requires ownership by your key **or** a valid signed token. Returns binary stream with
`Content-Disposition: attachment`.

**Signed download links (v1.3.0):** every file object returned by the API now includes
`download_url` — a link with an HMAC token (`?aclp_token=...`) that works in any browser,
curl or wget **without any auth header**. Use it when you cannot set custom headers, or when
you want to hand a file to the user/another AI as a plain link. Links stay valid while the
file exists (retention policy governs deletion).

## 4. Action types & payload schemas

| type | payload | result (summary) |
|------|---------|------------------|
| `ping` | `{}` | `{"pong":true,"time":"...","agent_version":"1.1.0"}` |
| `sysinfo` | `{}` | OS, hostname, CPU, RAM, disk, python, agent version |
| `shell` | `{"command":"<any shell command>","timeout":300,"elevated":false}` | `{"exit_code":int,"stdout":str,"stderr":str}` — Windows: cmd (`shell=True`), Linux: `/bin/sh`. With `"elevated": true` the command is retried/run with elevated rights when the operator configured elevation (see `privilege_status`). |
| `run_python` | `{"code":"print('hi')","timeout":120}` | stdout/stderr of the temporary script |
| `process_list` | `{}` | `{"count":N,"processes":[{pid,name,user,memory_mb}...]}` (needs psutil for structured output; else raw tasklist/ps text in `.raw`) |
| `kill_process` | `{"pid":1234}` or `{"name":"chrome.exe"}` | `{"terminated":[pids]}` |
| `file_read` | `{"path":"C:\\a.txt","max_bytes":8388608}` | `{"content_base64":"...","size":N,"truncated":bool}` — decode base64 to get bytes |
| `file_write` | `{"path":"C:\\a.txt","content_base64":"...","append":false}` | `{"bytes_written":N,"size":N}` |
| `file_list` | `{"path":"/home","limit":1000}` | `{"entries":[{name,is_dir,size,modified}...]}` |
| `file_delete` | `{"path":"...","recursive":false}` | `{"deleted":"..."}` |
| `file_mkdir` | `{"path":"..."}` | `{"created":"..."}` |
| `file_move` | `{"src":"...","dst":"...","copy":false}` | moved/copied paths |
| `upload_file` | `{"path":"C:\\report.pdf"}` | result includes `files:[{file_id,url,...}]` — download via `GET /files/{file_id}` |
| `file_download` | `{"file_id":9,"save_path":"C:\\Users\\me\\Downloads\\setup.zip"}` | `{"saved":"...","size":N}` — pairs with `POST /files` |
| `open_url` | `{"url":"https://example.com"}` | opens the URL with the environment's default handler |
| `http_request` | `{"url":"https://api.site/v1","method":"GET","headers":{},"body":null,"max_bytes":2000000}` | `{"status":200,"headers":{...},"body":"<text>","truncated":bool}` — lets you make web requests from the environment's network |
| `screenshot` | `{}` | environment uploads PNG → result includes `files:[{file_id,url}]` (requires pyautogui in the environment) |
| `install` | `{"packages":["vlc"],"manager":"auto","timeout":1800}` | shell output of winget/choco (Windows) or apt/dnf/pacman (Linux) / pip. On permission errors it retries elevated **only** if the user configured elevation credentials or `auto_elevate`. |
| `privilege_status` | `{}` | `{"elevated":bool,"platform":"...","auto_elevate":bool,"credentials_configured":bool,"sudo_available":bool,"method":"..."}` — check BEFORE running admin/root commands; elevation is optional and everything else works without it |
| `privilege_run` | `{"command":"apt-get install -y htop","timeout":600}` | same shape as `shell` result, executed with admin/root rights: Linux `sudo -S`/`su -c` with user-configured password (or NOPASSWD sudo); Windows shows a UAC prompt the user must accept |

**Unknown `type`** → command completes as `failed` with error `Unknown action type: ...`. Check `GET /ping`'s `version` and this doc after updates.

## 5. Recommended agent workflow (pseudo-code)

```
1. GET /ping                        → verify key & server
2. GET /clients                     → choose client_uid (or remember it)
3. POST /commands {type, payload, client_uid, wait:true, timeout:25}
   - completed → use result/files
   - timeout   → remember command_uid; poll GET /commands/{uid} every 3–5s
4. For files in result.files → GET /files/{file_id} (binary)
5. To send a file: POST /files (multipart) → file_id →
   POST /commands {type:"file_download", payload:{file_id, save_path}}
```

## 6. curl examples

```bash
BASE="https://your-site.com/wp-json/aclp/v1"
KEY="aclp_live_xxxx"

# connectivity check
curl -H "X-ACLP-Key: $KEY" "$BASE/ping"

# run a shell command and wait for the result
curl -X POST "$BASE/commands" -H "X-ACLP-Key: $KEY" -H "Content-Type: application/json" \
  -d '{"type":"shell","payload":{"command":"python -V"},"wait":true,"timeout":20}'

# broadcast to all online machines
curl -X POST "$BASE/commands" -H "X-ACLP-Key: $KEY" -H "Content-Type: application/json" \
  -d '{"type":"sysinfo","broadcast":true}'

# download a file the environment produced (file_id from result.files)
curl -H "X-ACLP-Key: $KEY" -o report.pdf "$BASE/files/12"

# upload a file to the environment
curl -X POST "$BASE/files" -H "X-ACLP-Key: $KEY" -F "file=@./installer.zip"
# then:
curl -X POST "$BASE/commands" -H "X-ACLP-Key: $KEY" -H "Content-Type: application/json" \
  -d '{"type":"file_download","payload":{"file_id":9,"save_path":"C:/Users/me/Desktop/installer.zip"},"wait":true}'
```

## 7. OpenAI-style function/tool definition

Provide this tool to your chatbot so it can submit jobs through the bridge:

```json
{
  "type": "function",
  "function": {
    "name": "aclp_submit_job",
    "description": "Submit a job to the ACLP bridge service and return the processed result. Job types: shell (run a command-line task), privilege_status, privilege_run (elevated task), file_read/file_write/file_list/file_delete/file_mkdir/file_move, upload_file (from environment), file_download (to environment, needs file_id from POST /files upload), open_url, http_request, screenshot, sysinfo, process_list, kill_process, install, run_python, ping.",
    "parameters": {
      "type": "object",
      "properties": {
        "type": {"type": "string", "enum": ["shell","privilege_status","privilege_run","file_read","file_write","file_list","file_delete","file_mkdir","file_move","upload_file","file_download","open_url","http_request","screenshot","sysinfo","process_list","kill_process","install","run_python","ping"]},
        "payload": {"type": "object", "description": "Action parameters, e.g. {\"command\":\"ls -la\"} for shell; {\"path\":\"/tmp/x\"} for file ops"},
        "client_uid": {"type": "string", "description": "Target machine; omit if only one machine is connected"}
      },
      "required": ["type", "payload"]
    }
  }
}
```

Tool handler pseudo-code:

```python
def pc_control(type, payload, client_uid=None):
    r = requests.post(f"{BASE}/commands",
        headers={"X-ACLP-Key": KEY},
        json={"type": type, "payload": payload, "client_uid": client_uid,
              "wait": True, "timeout": 25}, timeout=30)
    cmd = r.json()
    if cmd.get("status") not in ("completed", "failed"):
        # still pending → poll
        while True:
            time.sleep(3)
            cmd = requests.get(f"{BASE}/commands/{cmd['command_uid']}",
                               headers={"X-ACLP-Key": KEY}).json()
            if cmd["status"] in ("completed", "failed"):
                break
    return cmd  # status, result, error, files[]
```

## 8. Error codes

| HTTP | code | meaning |
|------|------|---------|
| 401 | `aclp_missing_key` / `aclp_invalid_key` | key missing/wrong |
| 403 | `aclp_key_inactive` / `aclp_unknown_client` | key disabled; client not registered to this key |
| 429 | `aclp_rate_limited` | slow down |
| 400 | `aclp_invalid` / `aclp_select_client` / `aclp_no_file` | bad input; several machines online → pass `client_uid` |
| 409 | `aclp_no_clients` / `aclp_no_online_clients` / `aclp_max_clients` | no machine bound to key / none online / client cap reached |
| 404 | `aclp_not_found` | command/file not found for this key |
| 410 | `aclp_file_expired` | file deleted by retention policy |

Errors are returned as `{"code":"...","message":"<Persian human message>","data":{"status":<http>}}` (WordPress REST standard).

## 9. Limits & gotchas

- `wait` blocks max **25 seconds**; long jobs: omit `wait`, poll `GET /commands/{uid}`.
- Max upload size per file: default **256 MB** (configurable server-side; also bounded by PHP `post_max_size`).
- `shell` output is truncated to ~400 KB per stream by the agent.
- File results older than the retention period return HTTP 410 — re-run `upload_file` if needed.
- Timestamps are UTC (`Y-m-d H:i:s`).
- Every action is logged server-side (command, payload, result, files, duration) and visible to the owner in the WordPress admin — act accordingly.
- **Elevation is opt-in:** if `privilege_run` / `elevated:true` fails with an elevation error, tell the user how to configure it (Linux: `elevation_user`/`elevation_password` in config.json; Windows: accept the UAC prompt) — or just continue without it, since normal actions never require admin rights.
- **Developer-agents:** to update the project itself read `GET /github-integration` then `docs/AGENT-CONTRIBUTION.md` (check remote commits before pushing; report them to the user).

## 10. TEXT-ONLY chatbots — the agent's relay mode (MODE B)

If you **cannot execute code or HTTP requests** (pure text chatbot), you can STILL use the service:
the operator runs a small relay program in their terminal and pastes the JSON results back to you.

For every single step:
1. Print EXACTLY ONE terminal command in one fenced code block — one of:
   ```
   python aclp_agent.py relay shell <command words here>
   python aclp_agent.py relay <action>
   python aclp_agent.py relay <action> --json {"one":"line","json":"payload"}
   ```
   Examples:
   ```
   python aclp_agent.py relay shell dir
   python aclp_agent.py relay sysinfo
   python aclp_agent.py relay shell git status
   python aclp_agent.py relay file_list --json {"path": "C:/Users"}
   ```
   For `shell` write the command words right after it (no quoting needed — the agent joins them).
   For other actions with parameters use `--json` with ONE line of JSON. Actions without
   parameters take only the action name.
2. Tell the user: "Copy this into the terminal of the connected PC, run it, then paste the whole
   JSON output back to me."
3. The agent registers itself, queues the command through the normal API (full history on the
   server), executes it, and prints one JSON block. Read the `result` field (or `error`) and
   continue with the next single command. NEVER invent output — always wait for the user's paste.

The relay output is the same object as `GET /commands/{uid}`: `status`, `result`, `error`,
`duration_ms`, `files[]`. Relay respects the API key already configured in the agent's
`config.json`; if the agent is not configured yet it runs its interactive setup first.

### 10.1 Agent registration extras (for code-capable agents managing their own machine)
The bundled agent sends these fields in `POST /agent/register`; if you implement your own agent,
send them too:
- `ai_model` — the name of the AI/chatbot controlling this machine (shown in the WordPress admin
  per-key view: "هوش مصنوعی کنترل‌کننده"). Set it in the agent's config.json ("ai_model": "ChatGPT").
- `agent_version`, `os`, `os_version`, `hostname`, `python_version`, `capabilities` — machine specs
  visible to the owner in the admin panel.

## 11. USER CHAT — talk with the user through the agent program (v1.3.0)

The user can chat with you directly from the connected PC by running:

```
python aclp_agent.py chat
```

Their messages (and files) arrive through the bridge — you do NOT need the agent's machine
to answer; you only need HTTP access to the site. This is a real-time conversational channel
**in addition to** the command queue. The bundled agent polls for your replies every ~2s and
prints them in the terminal.

### 11.1 Get new user messages — `GET /chat/pending`
```
GET {BASE}/chat/pending?wait=25
```
- `wait` (0–25, default 0): long-poll seconds. The server holds the request until a new user
  message arrives or the timeout expires. Poll in a loop with `wait=25` for near-real-time.
- Auth: key only (any AI using the key sees the user's messages for that key).
- Each returned message is marked delivered — it will not be returned again.

Response:
```json
{"ok":true,"count":1,"messages":[
  {"id":12,"direction":"to_ai","text":"please check disk space",
   "files":[{"file_id":34,"url":"https://site/wp-json/aclp/v1/files/34",
             "download_url":"https://site/wp-json/aclp/v1/files/34?aclp_token=...",
             "filename":"report.csv","size":2048}],
   "source":"user:my-pc","status":"delivered",
   "client":{"client_uid":"...","name":"my-pc","hostname":"DESKTOP"},
   "created_at":"2026-10-09 06:00:00"}
]}
```

### 11.2 Reply to the user — `POST /chat/reply`
```json
{"text": "C: has 120 GB free. Anything else?",
 "client_uid": "optional-uid-of-one-machine",
 "source": "ChatGPT"}
```
- `client_uid` (optional): target one machine's chat window; omit → all machines of this key see it.
- `source` (optional): your model name — shown to the user and in the admin panel.
- Auth: key only. Response: `{"ok":true,"message_id":13}`.
- The user sees your reply within ~2 seconds in their terminal.

### 11.3 (Agent-side) fetch replies — `GET /chat/replies?since=<id>&limit=50&order=asc`
Used by the agent program (auth: key + `X-ACLP-Client-UID`). Implement it only if you write
your own agent. Returns `from_ai` messages addressed to this machine (or broadcast).

### 11.4 Files in chat
- **User → you:** the agent uploads the file to the site and sends the message with a
  `download_url` — download it with a plain GET, no headers needed. Use this even when your
  own front-end cannot receive file uploads: the link always works.
- **You → user:** attach files to a `chat/reply` the same way (upload via `POST /files`, then
  put `[{"file_id": N}]` in the reply's `files` array) — or simply paste a `download_url` in
  your text.

### 11.5 Chat etiquette
- Answer through `POST /chat/reply`; run actions through `POST /commands` — do not mix them.
- Messages the user sends here are plain conversation; if they ask for an action, switch to commands.
- Everything (messages, files, commands) is visible to the owner in WordPress admin → AI-PC Link → گفتگوها.
