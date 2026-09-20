import json
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path

from pypdf import PdfReader, PdfWriter
from pypdf.generic import ArrayObject, DecodedStreamObject, DictionaryObject, FloatObject, NameObject


PROCESSOR = Path(__file__).parents[1] / "pdf_crop_region.py"


class PdfCropRegionTest(unittest.TestCase):
    def run_processor(self, source: Path, page: int, rect: dict[str, float]) -> tuple[dict, PdfReader]:
        output = source.parent / "output.pdf"
        request = source.parent / "request.json"
        request.write_text(json.dumps({
            "schema_version": 1,
            "source_path": str(source),
            "output_path": str(output),
            "configuration": {
                "coordinateSpace": "effective-crop-box-top-left-v1",
                "page": page,
                "rectNormalized": rect,
            },
        }), encoding="utf-8")
        completed = subprocess.run([sys.executable, str(PROCESSOR), str(request)], check=True, capture_output=True, text=True)
        return json.loads(completed.stdout), PdfReader(str(output))

    def test_preserves_vector_text_and_respects_crop_box(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            source = Path(directory) / "source.pdf"
            writer = PdfWriter()
            page = writer.add_blank_page(width=240, height=150)
            page.cropbox = ArrayObject([FloatObject(10), FloatObject(20), FloatObject(210), FloatObject(120)])
            font = DictionaryObject({
                NameObject("/Type"): NameObject("/Font"),
                NameObject("/Subtype"): NameObject("/Type1"),
                NameObject("/BaseFont"): NameObject("/Helvetica"),
            })
            stream = DecodedStreamObject()
            stream.set_data(b"BT /F1 12 Tf 80 65 Td (Selectable) Tj ET 0 0 1 rg 70 55 60 25 re S")
            page[NameObject("/Resources")] = DictionaryObject({NameObject("/Font"): DictionaryObject({NameObject("/F1"): writer._add_object(font)})})
            page[NameObject("/Contents")] = writer._add_object(stream)
            writer.write(str(source))

            result, output = self.run_processor(source, 1, {"x": .25, "y": .2, "width": .5, "height": .5})

            self.assertEqual(100, result["width_points"])
            self.assertEqual(50, result["height_points"])
            self.assertEqual("Selectable", output.pages[0].extract_text().strip())
            self.assertIn(b" re\n", output.pages[0].get_contents().get_data())

    def test_normalizes_rotation_before_applying_normalized_rectangle(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            source = Path(directory) / "rotated.pdf"
            writer = PdfWriter()
            writer.add_blank_page(width=300, height=200).rotate(90)
            writer.write(str(source))

            result, output = self.run_processor(source, 1, {"x": 0, "y": 0, "width": .5, "height": .5})

            self.assertAlmostEqual(100, result["width_points"])
            self.assertAlmostEqual(150, result["height_points"])
            self.assertEqual(0, output.pages[0].rotation)

    def test_rejects_regions_outside_the_page(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            source = Path(directory) / "source.pdf"
            writer = PdfWriter()
            writer.add_blank_page(width=100, height=100)
            writer.write(str(source))
            request = Path(directory) / "request.json"
            request.write_text(json.dumps({
                "source_path": str(source), "output_path": str(Path(directory) / "output.pdf"),
                "configuration": {"coordinateSpace": "effective-crop-box-top-left-v1", "page": 1,
                                  "rectNormalized": {"x": .8, "y": 0, "width": .3, "height": .2}},
            }), encoding="utf-8")

            completed = subprocess.run([sys.executable, str(PROCESSOR), str(request)], capture_output=True, text=True)

            self.assertEqual(2, completed.returncode)
            self.assertIn("INVALID_RECTANGLE", completed.stderr)

    def test_selects_the_requested_page_and_preserves_landscape_dimensions(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            source = Path(directory) / "multipage.pdf"
            writer = PdfWriter()
            writer.add_blank_page(width=100, height=200)
            writer.add_blank_page(width=400, height=200)
            writer.write(str(source))

            result, output = self.run_processor(source, 2, {"x": .25, "y": .25, "width": .5, "height": .5})

            self.assertEqual(200, result["width_points"])
            self.assertEqual(100, result["height_points"])
            self.assertEqual(1, len(output.pages))

    def test_preserves_embedded_image_resources_without_rasterizing_the_page(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            source = Path(directory) / "image.pdf"
            writer = PdfWriter()
            page = writer.add_blank_page(width=200, height=200)
            image = DecodedStreamObject()
            image.set_data(bytes([255, 0, 0]))
            image.update({
                NameObject("/Type"): NameObject("/XObject"),
                NameObject("/Subtype"): NameObject("/Image"),
                NameObject("/Width"): FloatObject(1),
                NameObject("/Height"): FloatObject(1),
                NameObject("/ColorSpace"): NameObject("/DeviceRGB"),
                NameObject("/BitsPerComponent"): FloatObject(8),
            })
            stream = DecodedStreamObject()
            stream.set_data(b"q 100 0 0 100 40 40 cm /Im0 Do Q")
            page[NameObject("/Resources")] = DictionaryObject({NameObject("/XObject"): DictionaryObject({NameObject("/Im0"): writer._add_object(image)})})
            page[NameObject("/Contents")] = writer._add_object(stream)
            writer.write(str(source))

            _, output = self.run_processor(source, 1, {"x": 0, "y": 0, "width": .75, "height": .75})

            self.assertIn("/XObject", output.pages[0]["/Resources"])
            self.assertIn(b"/Im0 Do", output.pages[0].get_contents().get_data())

    def test_rejects_a_page_number_that_does_not_exist(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            source = Path(directory) / "single.pdf"
            writer = PdfWriter()
            writer.add_blank_page(width=100, height=100)
            writer.write(str(source))
            request = Path(directory) / "request.json"
            request.write_text(json.dumps({
                "source_path": str(source), "output_path": str(Path(directory) / "output.pdf"),
                "configuration": {"coordinateSpace": "effective-crop-box-top-left-v1", "page": 2,
                                  "rectNormalized": {"x": 0, "y": 0, "width": .5, "height": .5}},
            }), encoding="utf-8")

            completed = subprocess.run([sys.executable, str(PROCESSOR), str(request)], capture_output=True, text=True)

            self.assertEqual(2, completed.returncode)
            self.assertIn("INVALID_PAGE", completed.stderr)

    def test_rejects_a_malformed_pdf_without_a_traceback(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            source = Path(directory) / "broken.pdf"
            source.write_bytes(b"%PDF-1.4\nnot-a-pdf")
            request = Path(directory) / "request.json"
            request.write_text(json.dumps({
                "source_path": str(source), "output_path": str(Path(directory) / "output.pdf"),
                "configuration": {"coordinateSpace": "effective-crop-box-top-left-v1", "page": 1,
                                  "rectNormalized": {"x": 0, "y": 0, "width": .5, "height": .5}},
            }), encoding="utf-8")

            completed = subprocess.run([sys.executable, str(PROCESSOR), str(request)], capture_output=True, text=True)

            self.assertEqual(2, completed.returncode)
            self.assertIn("INVALID_PDF", completed.stderr)
            self.assertNotIn("Traceback", completed.stderr)


if __name__ == "__main__":
    unittest.main()
