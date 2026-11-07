# Icônes de l'espace de gestion (.sources/gestion/icones/) : celles du site (.sources/favicon/, le 조 ivoire sur chocolat)
# aux couleurs inversées (chocolat sur ivoire), pour distinguer la gestion du site dans les onglets et sur l'écran d'accueil
# (demande d'Olivier du 07/11/2026). Les fichiers produits sont versés dans le dépôt : ce script ne sert qu'à les refaire
# si les icônes du site changent, avec Pillow :
#     python3 -I -B .sources/icones-gestion.py
# puis gen.py comme d'habitude. Échange exact des deux couleurs, pixel par pixel : les bords lissés restent lissés.
import os
from PIL import Image

ICI = os.path.dirname(os.path.abspath(__file__))
SRC, DST = os.path.join(ICI, "favicon"), os.path.join(ICI, "gestion", "icones")
C, I = (59, 29, 14), (243, 237, 228)            # chocolat #3B1D0E, ivoire #F3EDE4


def inverse(im):
    # Les icônes du site ne mêlent que ces deux couleurs : chaque pixel devient son symétrique entre elles, canal par canal
    return Image.merge("RGB", [Image.eval(bande, lambda v, k=k: max(0, min(255, I[k] + C[k] - v)))
                               for k, bande in enumerate(im.convert("RGB").split())])


os.makedirs(DST, exist_ok=True)
for nom in ("apple-touch-icon.png", "icone-192.png", "icone-512.png"):
    inverse(Image.open(os.path.join(SRC, nom))).save(os.path.join(DST, nom), optimize=True)

# ICO (16, 32 et 48 px) : le carreau aux coins arrondis porte de la transparence sur ses bords ;
# ces pixels prennent la nouvelle couleur du carreau (ivoire) en gardant leur transparence
ico = Image.open(os.path.join(SRC, "favicon.ico"))
images = []
for taille in sorted(ico.info["sizes"]):
    ico.size = taille
    ico.load()
    fr = ico.copy().convert("RGBA")
    alpha = fr.getchannel("A")
    out = Image.composite(inverse(fr), Image.new("RGB", fr.size, I), alpha.point(lambda a: 255 if a == 255 else 0))
    out.putalpha(alpha)
    images.append(out)
images[-1].save(os.path.join(DST, "favicon.ico"), format="ICO", sizes=[im.size for im in images], append_images=images[:-1])

# SVG : le fond et le trait échangent leurs couleurs
with open(os.path.join(SRC, "favicon.svg"), encoding="utf-8") as f:
    svg = f.read()
svg = svg.replace("#3B1D0E", "@@C@@").replace("#F3EDE4", "#3B1D0E").replace("@@C@@", "#F3EDE4")
with open(os.path.join(DST, "favicon.svg"), "w", encoding="utf-8") as f:
    f.write(svg)
print("icônes de la gestion refaites dans", os.path.relpath(DST))
