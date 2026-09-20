#!/usr/bin/env python3
import json
import sys
from pathlib import Path

from pypdf import PdfReader, PdfWriter, Transformation
from pypdf.errors import PdfReadError


def fail(code: str, message: str) -> None:
    print(f"{code}: {message}", file=sys.stderr)
    raise SystemExit(2)


def main() -> None:
    if len(sys.argv) != 2:
        fail("INVALID_REQUEST", "expected one request file")
    request_path = Path(sys.argv[1]).resolve()
    payload = json.loads(request_path.read_text(encoding="utf-8"))
    source = Path(payload["source_path"]).resolve()
    output = Path(payload["output_path"]).resolve()
    if request_path.parent not in source.parents or request_path.parent not in output.parents:
        fail("INVALID_PATH", "paths must stay inside the run directory")
    configuration = payload.get("configuration", {})
    if configuration.get("coordinateSpace") != "effective-crop-box-top-left-v1":
        fail("INVALID_COORDINATE_SPACE", "unsupported coordinate system")
    page_number = int(configuration.get("page", 0))
    rect = configuration.get("rectNormalized", {})
    values = [float(rect.get(key, -1)) for key in ("x", "y", "width", "height")]
    x, y, width, height = values
    if page_number < 1 or x < 0 or y < 0 or width <= 0 or height <= 0 or x + width > 1.000001 or y + height > 1.000001:
        fail("INVALID_RECTANGLE", "normalized rectangle is outside the page")

    try:
        reader = PdfReader(str(source), strict=False)
        if reader.is_encrypted:
            fail("INVALID_ENCRYPTED_PDF", "encrypted PDFs are not supported")
        if page_number > len(reader.pages):
            fail("INVALID_PAGE", "page does not exist")
        page = reader.pages[page_number - 1]
        if page.rotation:
            page.transfer_rotation_to_content()
        box = page.cropbox
        left, bottom, right, top = map(float, (box.left, box.bottom, box.right, box.top))
        page_width, page_height = right - left, top - bottom
        crop_left = left + x * page_width
        crop_right = crop_left + width * page_width
        crop_top = top - y * page_height
        crop_bottom = crop_top - height * page_height

        writer = PdfWriter()
        target = writer.add_blank_page(width=crop_right - crop_left, height=crop_top - crop_bottom)
        target.merge_transformed_page(page, Transformation().translate(tx=-crop_left, ty=-crop_bottom), expand=False)
        writer.write(str(output))
    except (PdfReadError, EOFError, KeyError, TypeError, ValueError):
        fail("INVALID_PDF", "source is not a readable PDF")
    print(json.dumps({
        "schema_version": 1,
        "width_points": crop_right - crop_left,
        "height_points": crop_top - crop_bottom,
        "source_rotation_normalized": True,
    }))


if __name__ == "__main__":
    main()
