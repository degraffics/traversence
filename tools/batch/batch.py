#!/usr/bin/env python3
"""Claude Batch API runner for sessions (Jason, 2026-11-28: "Use the batch api now").

Non-urgent analysis goes here instead of the weekly session allowance: half the API price, paid from the
API credit (agents' budget, Jason #1573/#1574), never from the site's own $40 (AiClient's ai_month_usd).

  python3 tools/batch/batch.py submit jobs.jsonl --max-usd 10     # estimate, refuse over the limit, send
  python3 tools/batch/batch.py status <batch_id>
  python3 tools/batch/batch.py fetch <batch_id> --out research/ai-results/<name>/   # answers + real cost

jobs.jsonl: one JSON per line: {"id": "...", "model": "claude-haiku-5-5", "max_tokens": 2000,
"system": "...", "prompt": "...", "files": ["repo/path", ...]}. Files are read from the repo and attached.
Rules: public data and repo files only, never members' or owners' private data (decisions/0164); the key comes
only from TRV_ANTHROPIC_API_KEY (Jason adds it in the environment's settings; never in the repo or chat).
"""
import json, os, sys, time, urllib.request, urllib.error, pathlib

API = "https://api.anthropic.com/v1/messages/batches"   # explicit: the session's own ANTHROPIC_BASE_URL is not ours
# $ per million tokens (input, output), Batch API = half the list price (decisions/0180's envelope note)
LIST = {"claude-opus-5-5": (4.00, 20.00), "claude-sonnet-5-5": (2.00, 10.00), "claude-haiku-5-5": (0.10, 0.50)}
PRICE = {m: (i / 2, o / 2) for m, (i, o) in LIST.items()}


def key():
    # TRV_ANTHROPIC_API_KEY first: the session runtime keeps ANTHROPIC_API_KEY for itself and strips it (2026-11-28)
    k = (os.environ.get("TRV_ANTHROPIC_API_KEY") or os.environ.get("ANTHROPIC_API_KEY") or "").strip()
    if not k:
        sys.exit("TRV_ANTHROPIC_API_KEY isn't set: Jason adds it in the cloud environment's settings, then a new or resumed session sees it.")
    return k


def call(method, url, body=None):
    req = urllib.request.Request(url, method=method, data=json.dumps(body).encode() if body is not None else None,
                                 headers={"x-api-key": key(), "anthropic-version": "2023-06-01", "content-type": "application/json"})
    try:
        with urllib.request.urlopen(req, timeout=120) as r:
            return r.read().decode()
    except urllib.error.HTTPError as e:
        sys.exit(f"{e.code}: {e.read().decode()[:500]}")


def build(path):
    reqs, est = [], 0.0
    for n, line in enumerate(pathlib.Path(path).read_text().splitlines(), 1):
        if not line.strip():
            continue
        j = json.loads(line)
        model = j.get("model", "claude-haiku-5-5")
        if model not in PRICE:
            sys.exit(f"line {n}: unknown model {model}")
        text = j["prompt"]
        for f in j.get("files", []):
            text += f"\n\n<file path=\"{f}\">\n{pathlib.Path(f).read_text(errors='replace')}\n</file>"
        params = {"model": model, "max_tokens": int(j.get("max_tokens", 2000)), "messages": [{"role": "user", "content": text}]}
        if j.get("system"):
            params["system"] = j["system"]
        reqs.append({"custom_id": j["id"], "params": params})
        tin = len(text + j.get("system", "")) / 3.5   # rough tokens, on the high side
        est += tin / 1e6 * PRICE[model][0] + params["max_tokens"] / 1e6 * PRICE[model][1]
    return reqs, est


def main(a):
    if len(a) < 2:
        sys.exit(__doc__)
    cmd = a[0]
    if cmd == "submit":
        lim = float(a[a.index("--max-usd") + 1]) if "--max-usd" in a else 5.0
        reqs, est = build(a[1])
        print(f"{len(reqs)} requests, at most about ${est:.2f} (limit ${lim:.2f})")
        if est > lim:
            sys.exit("Refused: the estimate is over the limit. Split the jobs or raise --max-usd with a reason.")
        r = json.loads(call("POST", API, {"requests": reqs}))
        print(f"batch {r['id']}: {r['processing_status']}")
    elif cmd == "status":
        r = json.loads(call("GET", f"{API}/{a[1]}"))
        print(r["processing_status"], json.dumps(r["request_counts"]))
    elif cmd == "fetch":
        r = json.loads(call("GET", f"{API}/{a[1]}"))
        if r["processing_status"] != "ended":
            sys.exit(f"not ended yet: {r['processing_status']}")
        out = pathlib.Path(a[a.index("--out") + 1] if "--out" in a else f"research/ai-results/{a[1]}")
        out.mkdir(parents=True, exist_ok=True)
        cost, raw = 0.0, call("GET", r["results_url"])
        for line in raw.splitlines():
            j = json.loads(line)
            res = j["result"]
            if res["type"] != "succeeded":
                (out / f"{j['custom_id']}.error.json").write_text(json.dumps(res, indent=1))
                continue
            m = res["message"]
            pi, po = PRICE.get(m["model"], PRICE["claude-opus-5-5"])
            cost += m["usage"]["input_tokens"] / 1e6 * pi + m["usage"]["output_tokens"] / 1e6 * po
            (out / f"{j['custom_id']}.md").write_text("".join(b.get("text", "") for b in m["content"]))
        (out / "cost.json").write_text(json.dumps({"batch": a[1], "usd": round(cost, 4), "at": time.strftime("%Y-%m-%dT%H:%MZ", time.gmtime())}))
        print(f"saved to {out}, cost ${cost:.4f}")
    else:
        sys.exit(__doc__)


if __name__ == "__main__":
    main(sys.argv[1:])
