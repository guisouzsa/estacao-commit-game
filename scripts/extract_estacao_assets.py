"""Reproducible extraction of Estacao Commit cards from the original vector PDFs."""
from pathlib import Path
import base64
import html
import shutil
import pymupdf as fitz
import numpy as np

ROOT = Path(__file__).resolve().parents[1]
DOCS = ROOT / "documentacao"
PUBLIC = ROOT / "public" / "images" / "estacao-commit"
CORRECTED = PUBLIC / "corrected"
LEGACY = PUBLIC / "legacy"
AUDIT = PUBLIC / "audit"
MASCOTS = PUBLIC / "mascots"
for folder in (CORRECTED, LEGACY, AUDIT, MASCOTS):
    folder.mkdir(parents=True, exist_ok=True)

# Rectangles are the outermost vector card borders reported by PyMuPDF.
CARDS = {
    "git-add": (82.6, 91.4, 252.7, 319.2),
    "git-commit": (264.3, 91.4, 434.4, 319.2),
    "git-push": (442.4, 91.8, 612.4, 319.5),
    "bloco-1": (82.3, 334.8, 252.4, 562.6),
    "bloco-2": (267.6, 335.4, 437.7, 563.1),
    "bloco-3": (451.0, 335.3, 621.1, 563.1),
    "bloco-4": (634.3, 335.4, 804.4, 563.1),
    "git-clone": (83.4, 621.2, 253.5, 849.0),
    "git-checkout": (260.1, 621.0, 430.2, 848.7),
    "git-pull": (436.4, 621.3, 606.5, 849.0),
    "git-login": (614.3, 620.5, 784.4, 848.2),
    "limpar-credenciais": (83.3, 905.8, 253.4, 1133.5),
    "merge-conflict": (267.9, 904.6, 438.0, 1132.4),
}

source = fitz.open(DOCS / "cartas.pdf")
page = source[0]
for name, coords in CARDS.items():
    old = PUBLIC / f"{name}.png"
    if old.exists() and not (LEGACY / old.name).exists():
        shutil.copy2(old, LEGACY / old.name)
    rect = fitz.Rect(*coords)
    context = fitz.Rect(rect.x0 - 12, rect.y0 - 12, rect.x1 + 12, rect.y1 + 12) & page.rect
    clip = fitz.Rect(rect.x0 - 4, rect.y0 - 4, rect.x1 + 4, rect.y1 + 4) & page.rect
    page.get_pixmap(matrix=fitz.Matrix(3, 3), clip=clip, alpha=False).save(CORRECTED / f"{name}.png")
    page.get_pixmap(matrix=fitz.Matrix(1.3, 1.3), clip=context, alpha=False).save(AUDIT / f"source-{name}.png")

def data_uri(path: Path) -> str:
    return "data:image/png;base64," + base64.b64encode(path.read_bytes()).decode()

def contact_svg(path: Path, cards: list[tuple[str, Path]], title: str, columns: int = 4):
    cell_w, cell_h, gap, top = 250, 370, 24, 86
    rows = (len(cards) + columns - 1) // columns
    width = columns * cell_w + (columns + 1) * gap
    height = top + rows * cell_h + (rows + 1) * gap
    parts = [f'<svg xmlns="http://www.w3.org/2000/svg" width="{width}" height="{height}" viewBox="0 0 {width} {height}">',
             '<rect width="100%" height="100%" fill="#f7f4ec"/>',
             f'<text x="24" y="48" font-family="Arial,sans-serif" font-size="26" font-weight="700" fill="#121213">{html.escape(title)}</text>']
    for index, (name, image) in enumerate(cards):
        col, row = index % columns, index // columns
        x, y = gap + col * (cell_w + gap), top + row * (cell_h + gap)
        parts += [f'<rect x="{x}" y="{y}" width="{cell_w}" height="{cell_h}" fill="white" stroke="#121213" stroke-width="3"/>',
                  f'<image href="{data_uri(image)}" x="{x+18}" y="{y+18}" width="{cell_w-36}" height="{cell_h-72}" preserveAspectRatio="xMidYMid meet"/>',
                  f'<text x="{x+18}" y="{y+cell_h-20}" font-family="monospace" font-size="15" font-weight="700" fill="#121213">{html.escape(name)}</text>']
    parts.append('</svg>')
    path.write_text('\n'.join(parts), encoding='utf-8')

contact_svg(AUDIT / "contact-sheet-corrected.svg", [(n, CORRECTED / f"{n}.png") for n in CARDS], "Cartas corrigidas - bordas vetoriais preservadas")

comparisons = []
for name in ("git-add", "git-commit", "git-push", "git-login", "merge-conflict"):
    comparisons.extend([(f"{name} / origem", AUDIT / f"source-{name}.png"),
                        (f"{name} / anterior", LEGACY / f"{name}.png"),
                        (f"{name} / corrigido", CORRECTED / f"{name}.png")])
contact_svg(AUDIT / "contact-sheet-comparison.svg", comparisons, "Origem x recorte anterior x recorte corrigido", columns=3)

for sheet in ("contact-sheet-corrected", "contact-sheet-comparison"):
    svg = AUDIT / f"{sheet}.svg"
    rendered = fitz.open(svg)
    rendered[0].get_pixmap(matrix=fitz.Matrix(1, 1), alpha=False).save(AUDIT / f"{sheet}.png")

def transparent_crop(doc_name: str, rect_tuple, output: str, background: str, scale: float = 3):
    p = fitz.open(DOCS / doc_name)[0]
    pix = p.get_pixmap(matrix=fitz.Matrix(scale, scale), clip=fitz.Rect(*rect_tuple), alpha=False)
    rgb = np.frombuffer(pix.samples, dtype=np.uint8).reshape(pix.height, pix.width, 3).copy()
    if background == "light": candidate = np.all(rgb > 242, axis=2)
    else: candidate = np.max(rgb, axis=2) < 42
    visited = np.zeros(candidate.shape, dtype=bool)
    visited[0, :] = candidate[0, :]; visited[-1, :] = candidate[-1, :]
    visited[:, 0] |= candidate[:, 0]; visited[:, -1] |= candidate[:, -1]
    while True:
        grown = visited.copy()
        grown[1:, :] |= visited[:-1, :]; grown[:-1, :] |= visited[1:, :]
        grown[:, 1:] |= visited[:, :-1]; grown[:, :-1] |= visited[:, 1:]
        grown &= candidate
        if np.array_equal(grown, visited): break
        visited = grown
    alpha = np.where(visited, 0, 255).astype(np.uint8)
    rgba = np.dstack((rgb, alpha))
    out = fitz.Pixmap(fitz.csRGB, pix.width, pix.height, rgba.tobytes(), True)
    out.save(MASCOTS / output)

transparent_crop("cartas.pdf", (285, 145, 415, 280), "gato-commit.png", "light")
transparent_crop("cartas.pdf", (635, 660, 770, 795), "gato-login.png", "light")
transparent_crop("TabuleiroPrincipal.pdf", (530, 540, 1030, 1025), "foguete-gato.png", "dark", 2.2)
print(f"Extracted {len(CARDS)} cards, 3 mascot studies and 2 contact sheets.")
