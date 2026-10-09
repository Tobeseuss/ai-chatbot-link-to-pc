# AGENT-CONTRIBUTION.md — How AI Agents Update & Publish This Project

> **Audience:** AI agents (LLM-driven or autonomous) that are connected to a user's PC through the
> ACLP bridge and want to (a) extend the plugin/agent when a needed capability is missing, and
> (b) publish a new version to this repository, exactly like the project owner would.
>
> **Persian note (برای کاربر):** این سند پروتکل رسمی است که با آن ایجنت‌های هوش مصنوعی می‌توانند خودشان ریپو را بروزرسانی و ریلیز منتشر کنند. مالک پروژه می‌تواند در تنظیمات پلاگین (AI-PC Link → تنظیمات → یکپارچگی گیت‌هاب) آدرس ریپو و توکن PAT را تغییر دهد.

Before you touch anything, read — in this order:

1. `project.md` (core principles 1–4, mandatory change checklist, semver rules)
2. `docs/DEVELOPER.md` (architecture, how to add an action)
3. `docs/AGENT-API.md` (current REST surface)
4. `CHANGELOG.md` + `worklog.md` + `brainstorm.md` (what happened so far and why)

---

## 1. Get the GitHub integration settings

From any authenticated context (API key required):

```
GET {site}/wp-json/aclp/v1/github-integration
X-ACLP-Key: <api key>
```

Response:

```json
{
  "ok": true,
  "repo_url": "https://github.com/Tobeseuss/ai-chatbot-link-to-pc",
  "pat": "github_pat_...",
  "pat_set": true,
  "pat_required_scopes": "Fine-grained PAT with full read/write ...",
  "workflow_doc": ".../docs/AGENT-CONTRIBUTION.md",
  "note": "PAT available. Follow workflow_doc step by step. ..."
}
```

- `pat_set: false` (empty `pat`) → **STOP and ask the user** to paste their GitHub PAT in
  *WordPress admin → AI-PC Link → تنظیمات → یکپارچگی گیت‌هاب*. Never invent a token.
- The PAT must have **full read/write** on everything GitHub allows (Contents, Metadata,
  Actions, and Administration where GitHub permits). Read-only-scoped parts are the only
  exception — GitHub simply does not grant write there.

Fallback (owner's dev environment): the PAT also lives in the **persistent dev workspace**
(never committed to the repo — GitHub Push Protection blocks tokens even base64-encoded).
Read `/home/z/my-project/.env` (`ACLPS_GITHUB_PAT=...`), `/home/z/my-project/scripts/github_pat.local`,
or decode `/home/z/my-project/ai-chatbot-link-to-pc/.ai/pat.b64` (gitignored). Full vault map:
`.ai/README.md`. If none are available, ask the user — never guess.

## 2. Sync with the repository — CHECK EXISTING COMMITS FIRST (mandatory)

The owner requires that **before any push** you inspect the remote for commits made by
other agents or the owner:

```bash
git clone https://$PAT@github.com/OWNER/ai-chatbot-link-to-pc.git   # or reuse a previous clone
cd ai-chatbot-link-to-pc
git config user.name  "ACLP Agent <your-name>"    # e.g. "ACLP Agent (Claude)"
git config user.email "agent@example.invalid"
git fetch origin
git log origin/main --oneline -20        # study what changed since your last sync
git log origin/main --author="Agent" --oneline -10   # commits from other agents
```

**Report to the user** any commits you did not make (who, what, when) and ask:
*"Should my changes be merged on top of these? / Should anything be reverted?"*
Then rebase/merge onto the latest `origin/main` before starting your edit.

## 3. Make your changes the right way

Follow the **mandatory checklist** in `project.md` §2 — summary:

1. Change the code (plugin PHP and/or `agent/aclp_agent.py`).
2. Bump the version **in all four places** (they must stay in sync):
   - `ai-chatbot-link-to-pc/ai-chatbot-link-to-pc.php` → header `Version:` and `ACLP_VERSION`
   - `agent/aclp_agent.py` → `__VERSION__`
   - `CHANGELOG.md` → new entry
3. Update `docs/AGENT-API.md` ( principle 4: this file MUST be updated with every change).
4. Update the "نحوه اعلام نحوه استفاده از پل به ایجنت هوش مصنوعی" prompt text in **both** places:
   - `ai-chatbot-link-to-pc/includes/class-aclp-utils.php` → `ACLP_Utils::agent_prompt()`
   - the copyable block at the top of `README.md`
5. Update `docs/USER-GUIDE.fa.md`, `docs/DEVELOPER.md`, `SECURITY.md` if behavior changed.
6. Update `project.md` (status section + any new ADR decisions) and `worklog.md`.
7. Append the user↔agent conversation summary to `brainstorm.md` (principle 1).
8. Bump the version badge + download links in `README.md`.
9. Sanity checks before commit:
   - Python: `python3 -m py_compile agent/aclp_agent.py`
   - PHP: `php -l` on every changed file (or a bracket-balance check if php-cli is absent)
   - Build: `python3 tools/build_release.py` — must end with both ZIPs and matching versions.

## 4. Commit, push, and release

### 4.1 Commit style

One or a few clean commits, conventional messages:

```
feat(agent): add privilege elevation helpers (sudo/su/UAC)
docs: sync AGENT-API.md + README prompt for v1.2.0
```

### 4.2 Who owns the PAT? (decide automatically, tell the user)

```bash
curl -s -H "Authorization: Bearer $PAT" https://api.github.com/user          # → login
curl -s -H "Authorization: Bearer $PAT" https://api.github.com/repos/OWNER/ai-chatbot-link-to-pc   # → owner.login + permissions
```

- **PAT belongs to the repo owner** (`login == owner.login`, or `permissions.push/admin == true`):
  1. Push straight to `main`: `git push origin main`
  2. Publish the Release (§4.3).
- **PAT belongs to another user (contributor agent):**
  1. If that account has write access to the repo → **commit your changes directly to
     `main`** of the main repo (this is what the owner wants: your commits stay visible in
     the main project and can be merged/reviewed later). Do **not** publish a Release —
     ask the owner to review, then the owner (or a later agent with the owner PAT) publishes.
  2. If write access is denied (HTTP 403 on push) → fork, push to your fork, open a PR
     against `main`, and tell the user the PR URL.

### 4.3 Release (only with owner-level PAT)

```bash
python3 tools/build_release.py            # → dist/ai-chatbot-link-to-pc-vX.Y.Z.zip + dist/aclp-agent-vX.Y.Z.zip
TAG="v$(python3 - <<'PY'
import re,pathlib
print(re.search(r'Version:\s*([0-9.]+)', pathlib.Path('ai-chatbot-link-to-pc/ai-chatbot-link-to-pc.php').read_text()).group(1))
PY
)"
git tag "$TAG" && git push origin "$TAG"

# Create the GitHub release with both ZIPs (REST API):
for f in dist/ai-chatbot-link-to-pc-v*.zip dist/aclp-agent-v*.zip; do
  curl -s -X POST \
    -H "Authorization: Bearer $PAT" \
    -H "Content-Type: application/octet-stream" \
    --data-binary @"$f" \
    "https://uploads.github.com/repos/OWNER/ai-chatbot-link-to-pc/releases/$(...release_id...)/assets?name=$(basename $f)"
done
```

Release notes: paste the new `CHANGELOG.md` section. Both ZIPs must always be attached —
plugin and agent ship as a matched-version pair (project.md §6).

## 5. After publishing — close the loop (principles 1–4)

- `worklog.md`: append a section (Task, what changed, files, versions).
- `brainstorm.md`: append the conversation that led to this change.
- Tell the user: the new version number, the Release URL, and that they should update the
  WordPress plugin (delete old → install new ZIP) and replace the agent folder on their PC.
- Remind the user which capabilities you added and where they are documented.

## 6. Hard rules (never violate)

1. Never push to `main` without `git fetch` + reviewing new remote commits first (§2) —
   and always report them to the user.
2. Never invent, guess, or hardcode the PAT or any API key. If missing → ask.
3. Never break version sync between plugin and agent (build script fails on purpose).
4. Never delete or overwrite other agents' commits — rebase, don't force-push.
5. Never publish a Release without updating `docs/AGENT-API.md`, `README.md` prompt block,
   `CHANGELOG.md`, `project.md`, `worklog.md`, and `brainstorm.md`.
