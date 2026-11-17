#!/usr/bin/env python3
"""Génère un environnement HDR de studio (équirectangulaire, format Radiance .hdr) pour model-viewer.

Fond gris très sombre + softboxes rectangulaires : ce sont leurs reflets doux dans le granit poli qui
donnent l'aspect « pierre » (un granit poli sans reflets paraît plat). Aucune licence tierce : tout est généré ici.

Deux styles :
  studio     (défaut) éclairage doux, celui livré avec le rendu réaliste v1 ;
  contraste  fond très sombre, grandes sources dégradées et bandes verticales : reflets nets et brillants
             sur les faces polies (effet « pierre polie / miroir »).

Usage : python3 generer_studio_hdr.py [sortie.hdr] [largeur] [studio|contraste]
"""
import sys
import numpy as np

sortie = sys.argv[1] if len(sys.argv) > 1 else "studio.hdr"
W = int(sys.argv[2]) if len(sys.argv) > 2 else 1024
STYLE = sys.argv[3] if len(sys.argv) > 3 else "studio"
if STYLE not in ("studio", "contraste"):
    sys.exit("style inconnu : %s (studio ou contraste)" % STYLE)
H = W // 2

# direction de chaque pixel
u = (np.arange(W) + 0.5) / W
v = (np.arange(H) + 0.5) / H
az = (u - 0.5) * 2 * np.pi            # -π..π, 0 = devant
el = (0.5 - v) * np.pi                # +π/2 = zénith
AZ, EL = np.meshgrid(az, el)

# fond : léger dégradé ciel / sol
img = np.zeros((H, W, 3), np.float32)
if STYLE == "contraste":
    ciel = 0.025 + 0.035 * np.clip(EL / (np.pi / 2), 0, 1)
    sol = 0.02 + 0.015 * np.clip(-EL / (np.pi / 2), 0, 1)
else:
    ciel = 0.10 + 0.10 * np.clip(EL / (np.pi / 2), 0, 1)
    sol = 0.03 + 0.02 * np.clip(-EL / (np.pi / 2), 0, 1)
fond = np.where(EL >= 0, ciel, sol)
img += fond[..., None] * np.array([1.0, 1.0, 1.02], np.float32)


def diff_az(a, b):
    d = a - b
    return (d + np.pi) % (2 * np.pi) - np.pi


def softbox(az0, el0, larg, haut, puissance, teinte=(1.0, 1.0, 1.0), douceur=0.18, degrade=0.0):
    """Rectangle lumineux aux bords adoucis (angles en degrés).

    degrade : 0 = lumière uniforme ; 0.6 = le haut du rectangle est 60 % plus sombre que le bas
    (donne le dégradé caractéristique des reflets dans une pierre polie).
    """
    a0, e0 = np.radians(az0), np.radians(el0)
    hl, hh = np.radians(larg) / 2, np.radians(haut) / 2
    k = np.radians(douceur * min(larg, haut))
    dx = np.abs(diff_az(AZ, a0)) - hl
    dy = np.abs(EL - e0) - hh
    # distance signée approximative au rectangle, fondue sur k
    d = np.maximum(dx, dy)
    masque = np.clip(0.5 - d / max(k, 1e-6), 0, 1)
    masque = masque * masque * (3 - 2 * masque)
    if degrade:
        t = np.clip((EL - (e0 - hh)) / (2 * hh), 0, 1)
        masque = masque * (1 - degrade * t)
    return masque[..., None] * puissance * np.array(teinte, np.float32)


if STYLE == "contraste":
    # Convention model-viewer : az +90° = derrière la caméra de la vue de face, az -90° = derrière le monument.
    # Quatre grandes sources dégradées (une par côté) : les faces polies y reflètent un dégradé lumineux quel
    # que soit l'angle de vue ; des bandes verticales étroites entre elles dessinent des reflets nets qui
    # glissent sur la pierre quand on tourne autour ; plafonnier et filet d'horizon complètent.
    img += softbox(90, 22, 64, 40, 4.0, (1.0, 0.98, 0.94), degrade=0.6)
    img += softbox(-90, 22, 64, 40, 1.4, (0.95, 0.97, 1.0), degrade=0.75)
    img += softbox(0, 22, 50, 40, 2.0, degrade=0.7)
    img += softbox(180, 22, 50, 40, 2.0, degrade=0.7)
    img += softbox(45, 20, 6, 70, 10.0, (1.0, 0.97, 0.92), degrade=0.35)
    img += softbox(135, 20, 6, 70, 8.0, (0.92, 0.96, 1.0), degrade=0.35)
    img += softbox(-45, 20, 6, 70, 8.0, (0.95, 0.97, 1.0), degrade=0.35)
    img += softbox(-135, 20, 6, 70, 10.0, (1.0, 0.97, 0.93), degrade=0.35)
    img += softbox(0, 80, 120, 30, 1.2)
    img += softbox(0, 3, 340, 3, 0.5)
else:
    # lumière principale (gauche/avant), remplissage (droite), contre-jour de bord, plafonnier
    img += softbox(-50, 28, 55, 40, 7.0, (1.0, 0.97, 0.92))
    img += softbox(60, 20, 50, 35, 2.8, (0.92, 0.96, 1.0))
    img += softbox(180, 35, 90, 18, 4.5)
    img += softbox(0, 80, 120, 40, 4.0)
    img += softbox(0, 5, 160, 6, 0.9)            # bande basse douce : éclaire les faces avant

img = np.clip(img, 0, None)


def rgbe(a):
    m = a.max(axis=2)
    e = np.zeros_like(m)
    nz = m > 1e-32
    mant, ex = np.frexp(m[nz])
    e[nz] = ex
    f = np.zeros_like(m)
    f[nz] = mant * 256.0 / m[nz]
    out = np.zeros(a.shape[:2] + (4,), np.uint8)
    out[..., :3] = np.clip(a * f[..., None], 0, 255).astype(np.uint8)
    out[..., 3] = np.where(nz, np.clip(e + 128, 0, 255), 0).astype(np.uint8)
    return out


def rle_canal(c):
    """Encode une ligne (un canal) au format RLE de Radiance."""
    r = bytearray()
    i, n = 0, len(c)
    while i < n:
        # série de valeurs identiques ?
        j = i
        while j < n and j - i < 127 and c[j] == c[i]:
            j += 1
        if j - i >= 4:
            r += bytes([128 + (j - i), int(c[i])])
            i = j
        else:
            j = i
            while j < n and j - i < 128 and not (j + 3 < n and c[j] == c[j + 1] == c[j + 2] == c[j + 3]):
                j += 1
            if j == i:
                j = i + 1
            r += bytes([j - i]) + bytes(int(x) for x in c[i:j])
            i = j
    return bytes(r)


px = rgbe(img)
with open(sortie, "wb") as f:
    f.write(b"#?RADIANCE\nFORMAT=32-bit_rle_rgbe\n\n")
    f.write(f"-Y {H} +X {W}\n".encode())
    for y in range(H):
        f.write(bytes([2, 2, (W >> 8) & 255, W & 255]))
        for k in range(4):
            f.write(rle_canal(px[y, :, k]))
print(f"{sortie} : {W}×{H}, {len(open(sortie,'rb').read())//1024} Ko")
