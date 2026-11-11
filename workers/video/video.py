"""Traversence video worker (decisions/0153 §8, 0154 §2). Runs on Railway every 5 minutes, does a short run and exits.

1. GET  {SITE}/api/video/jobs.php?limit=2   -> {"paused": bool, "jobs": [{id, src, outputs: [{name, height, put, type}]}]}
   `src` is a short-lived link to the original in the file store; each output's `put` is a short-lived link to upload
   that copy to. The worker never holds the store's keys.
2. For each job: download the original, read its size and length (ffprobe), and make each output with ffmpeg:
   an H.264/AAC MP4 no taller than `height` (and no wider than its 16:9 width), web-ready (faststart).
3. POST {SITE}/api/video/results.php {id, ok, w, h, secs, outputs: [{name, bytes, w, h}], error}

Settings (Railway variables): TRAVERSENCE_SITE (https://traversence.com), VIDEO_WORKER_TOKEN (the same value as the
site's .env VIDEO_WORKER_TOKEN), RUN_SECONDS (default 240), JOBS_PER_ASK (default 2). Standard library and ffmpeg only.
"""
import json
import os
import subprocess
import sys
import tempfile
import time
import urllib.error
import urllib.request

SITE = os.environ.get("TRAVERSENCE_SITE", "https://traversence.com").rstrip("/")
TOKEN = os.environ.get("VIDEO_WORKER_TOKEN", "")
RUN_SECONDS = int(os.environ.get("RUN_SECONDS", "240"))
PER_ASK = int(os.environ.get("JOBS_PER_ASK", "2"))
UA = "TraversenceVideoWorker/1.0"


def api(path, body=None, timeout=60):
    data = json.dumps(body).encode() if body is not None else None
    req = urllib.request.Request(SITE + path, data=data, method="POST" if data else "GET", headers={
        "Authorization": "Bearer " + TOKEN, "X-Video-Token": TOKEN, "User-Agent": UA,
        "Content-Type": "application/json", "Accept": "application/json"})
    try:
        with urllib.request.urlopen(req, timeout=timeout) as r:
            return json.loads(r.read().decode() or "{}")
    except urllib.error.HTTPError as e:
        print(f"{path}: HTTP {e.code} {e.read()[:300]!r}")
        return None
    except Exception as e:  # noqa: BLE001 - a dropped connection: try again next run
        print(f"{path}: {e}")
        return None


def download(url, to):
    req = urllib.request.Request(url, headers={"User-Agent": UA})
    with urllib.request.urlopen(req, timeout=300) as r, open(to, "wb") as f:
        while True:
            b = r.read(1 << 20)
            if not b:
                break
            f.write(b)


def upload(url, path, ctype):
    size = os.path.getsize(path)
    with open(path, "rb") as f:
        req = urllib.request.Request(url, data=f, method="PUT", headers={"Content-Type": ctype, "Content-Length": str(size), "User-Agent": UA})
        with urllib.request.urlopen(req, timeout=600) as r:
            r.read()
    return size


def probe(path):
    out = subprocess.run(["ffprobe", "-v", "error", "-select_streams", "v:0", "-show_entries", "stream=width,height:stream_tags=rotate:format=duration",
                          "-of", "json", path], capture_output=True, text=True, timeout=60)
    d = json.loads(out.stdout or "{}")
    s = (d.get("streams") or [{}])[0]
    w, h = int(s.get("width") or 0), int(s.get("height") or 0)
    if str((s.get("tags") or {}).get("rotate", "0")) in ("90", "270", "-90"):
        w, h = h, w
    return w, h, float((d.get("format") or {}).get("duration") or 0)


def make(src, dst, height):
    # fit inside a box `height` tall (portrait) or `height` on the short side (landscape): never upscale
    box = f"'min(iw,if(gt(iw,ih),{height}*16/9,{height}))':'min(ih,if(gt(iw,ih),{height},{height}*16/9))'"
    vf = f"scale={box}:force_original_aspect_ratio=decrease:force_divisible_by=2"
    cmd = ["ffmpeg", "-y", "-v", "error", "-i", src, "-vf", vf, "-c:v", "libx264", "-preset", "veryfast", "-crf", "24" if height >= 1080 else "26",
           "-pix_fmt", "yuv420p", "-c:a", "aac", "-b:a", "128k" if height >= 1080 else "96k", "-movflags", "+faststart", dst]
    r = subprocess.run(cmd, capture_output=True, text=True, timeout=1200)
    if r.returncode != 0:
        raise RuntimeError("ffmpeg: " + (r.stderr or "")[-400:])
    return probe(dst)


def run_job(job):
    with tempfile.TemporaryDirectory() as tmp:
        src = os.path.join(tmp, "src")
        download(job["src"], src)
        w, h, secs = probe(src)
        outs = []
        for o in job.get("outputs", []):
            dst = os.path.join(tmp, o["name"] + ".mp4")
            ow, oh, _ = make(src, dst, int(o["height"]))
            outs.append({"name": o["name"], "bytes": upload(o["put"], dst, o.get("type", "video/mp4")), "w": ow, "h": oh})
        return {"id": job["id"], "ok": True, "w": w, "h": h, "secs": round(secs, 1), "outputs": outs}


def main():
    if not TOKEN:
        print("VIDEO_WORKER_TOKEN is not set: nothing to do.")
        return 0
    t0 = time.time()
    done = 0
    while time.time() - t0 < RUN_SECONDS:
        d = api(f"/api/video/jobs.php?limit={PER_ASK}")
        if not d or d.get("paused") or not d.get("jobs"):
            break
        for job in d["jobs"]:
            try:
                res = run_job(job)
            except Exception as e:  # noqa: BLE001 - reported to the site, which retries a few times then gives up
                res = {"id": job["id"], "ok": False, "error": str(e)[:500]}
            print(json.dumps({k: v for k, v in res.items() if k != "outputs"}))
            api("/api/video/results.php", res)
            done += 1
    print(f"Done: {done} video(s) in {round(time.time() - t0)} s.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
