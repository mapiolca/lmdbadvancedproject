"""Check actual PDFs emitted by costpdf_test.php (requires pypdf/pdfplumber)."""
from pathlib import Path
from pypdf import PdfReader
import pdfplumber

cache = Path(__file__).resolve().parent / '.cache'
expected = {'positive': '(89%)', 'negative': '(-11%)', 'zero': '(0%)',
            'undefined': '(-)', 'large': '(76%)'}
for name, rate in expected.items():
    path = cache / f'margin-pdf-{name}.pdf'
    text = PdfReader(path).pages[0].extract_text()
    assert 'Marge brute' in text and rate in text, name
    with pdfplumber.open(path) as pdf:
        # Last tile starts at x = 10mm + 5*(277mm/6). Amount and rate
        # must remain inside the tile ending at y=53mm, above the detail.
        words = [w for w in pdf.pages[0].extract_words()
                 if w['x0'] > 240 * 72 / 25.4 and 44 * 72 / 25.4 < w['top'] < 54 * 72 / 25.4]
        assert words, name
        assert max(w['bottom'] for w in words) <= 53 * 72 / 25.4, name
        assert min(w['x0'] for w in words) >= 240 * 72 / 25.4, name
        assert max(w['x1'] for w in words) <= 287 * 72 / 25.4, name
assert 'Marge brute' in PdfReader(cache / 'cost-report.pdf').pages[0].extract_text()
assert all('Marge brute' not in page.extract_text()
           for page in PdfReader(cache / 'cost-report-no-margin.pdf').pages)
print('PDF margin contents, permission and tile bounds passed (5 cases).')
