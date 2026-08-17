from PIL import Image
import base64
from io import BytesIO
from pathlib import Path

BACKGROUND = "#000000"
SIZE = 180

root = Path(__file__).resolve().parents[1]
source = root / "assets" / "favicon-source.png"

if not source.exists():
    raise SystemExit(f"Missing favicon source: {source}")

src = Image.open(source).convert("RGBA")
icon = src.resize((SIZE, SIZE), Image.LANCZOS)
icon.save(root / "apple-touch-icon.png", format="PNG", optimize=True)

buf = BytesIO()
icon.save(buf, format="PNG", optimize=True)
b64 = base64.b64encode(buf.getvalue()).decode("ascii")

svg = (
    '<svg xmlns="http://www.w3.org/2000/svg" '
    'xmlns:xlink="http://www.w3.org/1999/xlink" '
    'viewBox="0 0 180 180" role="img" aria-label="Calm Canine">\n'
    f'  <rect width="180" height="180" fill="{BACKGROUND}" />\n'
    f'  <image width="180" height="180" href="data:image/png;base64,{b64}" />\n'
    "</svg>\n"
)

(root / "favicon.svg").write_text(svg, encoding="utf-8")
(root / "assets" / "favicon.svg").write_text(svg, encoding="utf-8")
print(f"Built favicon from {source.name}")
print(f"  apple-touch-icon.png ({len(buf.getvalue())} bytes)")
print(f"  favicon.svg ({len(svg)} bytes)")
