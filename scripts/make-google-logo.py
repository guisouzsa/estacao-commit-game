"""Gera a logo quadrada da Estação Commit para a tela de consentimento do Google (e outros avatares de marca).

Estilo das cartas e do tabuleiro: fundo violeta com grade, borda preta grossa, sombra deslocada verde-lima e o
mascote do foguete no centro. Saída em public/images/estacao-commit/brand/ (512 px e 120 px, PNG).

Uso: python scripts/make-google-logo.py   (precisa do Pillow)
"""
from pathlib import Path

from PIL import Image, ImageDraw

ROOT = Path(__file__).resolve().parent.parent
MASCOT = ROOT / 'public/images/estacao-commit/mascots/foguete-gato.png'
OUT = ROOT / 'public/images/estacao-commit/brand'

INK = (29, 29, 27, 255)
VIOLET = (70, 68, 152, 255)
LIME = (180, 218, 51, 255)
PINK = (244, 129, 198, 255)
PAPER = (243, 241, 236, 255)

S = 1024  # desenha grande e reduz (bordas suaves)


def build() -> Image.Image:
    img = Image.new('RGBA', (S, S), PAPER)
    d = ImageDraw.Draw(img)

    # Sombra deslocada (lima) e o "card" violeta com borda preta, como as cartas do jogo.
    pad, shadow, border = 70, 46, 34
    d.rectangle([pad + shadow, pad + shadow, S - pad + shadow - 40, S - pad + shadow - 40], fill=LIME)
    box = [pad, pad, S - pad - 40, S - pad - 40]
    d.rectangle(box, fill=VIOLET)

    # Grade do tabuleiro.
    step = 74
    for x in range(box[0], box[2], step):
        d.line([x, box[1], x, box[3]], fill=(255, 255, 255, 38), width=4)
    for y in range(box[1], box[3], step):
        d.line([box[0], y, box[2], y], fill=(255, 255, 255, 38), width=4)

    # Faixa diagonal lima/preta no canto, igual à capa padrão do perfil.
    d.polygon([(box[2] - 250, box[3]), (box[2], box[3] - 250), (box[2], box[3] - 200), (box[2] - 200, box[3])], fill=LIME)
    d.polygon([(box[2] - 200, box[3]), (box[2], box[3] - 200), (box[2], box[3])], fill=INK)

    d.rectangle(box, outline=INK, width=border)

    # Mascote centralizado.
    mascot = Image.open(MASCOT).convert('RGBA')
    inner = box[2] - box[0] - 2 * border
    size = int(inner * 0.78)
    mascot.thumbnail((size, size), Image.LANCZOS)
    cx = (box[0] + box[2]) // 2 - mascot.width // 2
    cy = (box[1] + box[3]) // 2 - mascot.height // 2 - 10
    img.alpha_composite(mascot, (cx, cy))

    # Estrelinhas rosa (detalhe das cartas).
    for (x, y, r) in [(box[0] + 90, box[1] + 110, 16), (box[0] + 150, box[1] + 70, 9), (box[2] - 120, box[1] + 120, 12)]:
        d.polygon([(x, y - r * 2), (x + r // 2, y - r // 2), (x + r * 2, y), (x + r // 2, y + r // 2),
                   (x, y + r * 2), (x - r // 2, y + r // 2), (x - r * 2, y), (x - r // 2, y - r // 2)], fill=PINK)

    return img


def main() -> None:
    OUT.mkdir(parents=True, exist_ok=True)
    logo = build().convert('RGB')
    for px in (512, 120):
        logo.resize((px, px), Image.LANCZOS).save(OUT / f'logo-{px}.png', optimize=True)
    print('ok', [str(p.relative_to(ROOT)) for p in sorted(OUT.glob('logo-*.png'))])


if __name__ == '__main__':
    main()
