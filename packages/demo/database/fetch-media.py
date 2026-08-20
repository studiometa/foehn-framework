"""Download the demo's photographs from one Unsplash collection, with credits.

One collection — "Minimal BW", 2393384 — so the set stays homogeneous in treatment,
which is what a portfolio needs and what the Swiss layout depends on.

Unsplash's API terms require triggering `links.download_location` whenever a photo is
downloaded, so that is done here rather than skipped. The access key is read from the
environment and never written to the manifest.
"""

import json
import os
import pathlib
import urllib.parse
import urllib.request

KEY = os.environ["UNSPLASH_ACCESS_KEY"]
OUT = pathlib.Path(os.environ["MEDIA_OUT"])
WIDTH = 1800
QUALITY = 80

# id -> (project slug, sort order). Curated so each project reads as one body of work.
CURATION = {
    "corridors": ["LCJ9iOli-uE", "9cU_HC5CND8", "hxi_yRxODNc", "WOSvM6tPHBQ", "zw07kVDaHPw"],
    "silhouettes": ["kX9lb7LUDWc", "-x-Brii2QaM", "ZNVGL_Pcf74", "R9OueKOtGGU"],
    "winter": ["Du41jIaI5Ww", "4L-AyDJM-yM", "kLwAv4D8eCs", "UXLD2XHnWds"],
    "objects": ["VBPzRgd7gfc", "NuOGFo4PudE", "vpOJwXxxpJ4", "sDeGlMAwcH4", "34lqQKELTT4"],
    "osaka": ["_v2aoMh8xf0", "fTvlM_68oZM", "NECyhRpxGmU", "jZJn74JUwSE", "tFu9EMyR87E"],
    "editions": ["RWRnUMWtOWk", "EbuaKnSm8Zw", "HY3l4IeOc3E", "YIPSx8PFi9s", "MAgPyHRO0AA"],
    "_studio": ["VBWWscZtszY", "cmt3JdS5MC4"],  # portrait and about page
}


def get(url):
    request = urllib.request.Request(url, headers={"Authorization": f"Client-ID {KEY}"})
    with urllib.request.urlopen(request, timeout=60) as response:
        return json.load(response)


photos = {}
for page in (1, 2):
    for photo in get(f"https://api.unsplash.com/collections/2393384/photos?per_page=30&page={page}"):
        photos[photo["id"]] = photo

OUT.mkdir(parents=True, exist_ok=True)
manifest = []
missing = []

for project, ids in CURATION.items():
    for order, photo_id in enumerate(ids):
        photo = photos.get(photo_id)

        if photo is None:
            missing.append(photo_id)
            continue

        filename = f"{project.lstrip('_')}-{order + 1:02d}-{photo_id}.jpg".replace("_", "-")
        target = OUT / filename

        if not target.exists():
            source = f"{photo['urls']['raw']}&w={WIDTH}&q={QUALITY}&fm=jpg&fit=max"
            with urllib.request.urlopen(source, timeout=120) as response:
                target.write_bytes(response.read())

            # Required by the Unsplash API terms whenever a photo is downloaded.
            get(photo["links"]["download_location"] + f"&client_id={KEY}")

        manifest.append({
            "file": filename,
            "project": project,
            "order": order,
            "unsplash_id": photo_id,
            "page": photo["links"]["html"],
            "photographer": photo["user"]["name"],
            "photographer_url": photo["user"]["links"]["html"],
            "description": (photo.get("description") or photo.get("alt_description") or "").strip(),
            "width": photo["width"],
            "height": photo["height"],
            "orientation": "landscape" if photo["width"] >= photo["height"] else "portrait",
            "bytes": target.stat().st_size,
        })

(OUT / "credits.json").write_text(json.dumps(manifest, indent=2, ensure_ascii=False) + "\n")

total = sum(m["bytes"] for m in manifest)
print(f"{len(manifest)} photographs, {total / 1_048_576:.1f} MB")
print("missing:", missing or "none")
