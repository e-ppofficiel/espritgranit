#!/usr/bin/env python3
"""Réduit un HDRI équirectangulaire (.hdr Radiance) à une taille adaptée à model-viewer (1536×768 conseillé).

Usage : python3 convertir_hdri.py entree.hdr sortie.hdr [largeur=1536] [--decalage-deg D] [--max VALEUR]

  --decalage-deg D : fait tourner l'image de D degrés autour de l'axe vertical (D > 0 vers la droite),
                     pour choisir ce que reflète la face avant du monument.
  --max VALEUR     : plafonne la luminance des pixels (soleil très intense) pour éviter les taches éblouissantes.

Dépendance : numpy uniquement. La réduction se fait en lumière linéaire (moyenne par blocs), sans perdre d'énergie.
Les HDRI viennent de Poly Haven (licence CC0 : usage commercial autorisé, mention non obligatoire).
"""
import sys
import numpy as np


def lire_hdr(chemin):
    d = open(chemin, "rb").read()
    i = 0
    while True:  # en-tête : lignes jusqu'à la ligne vide, puis la ligne de résolution
        j = d.index(b"\n", i)
        ligne = d[i:j]
        i = j + 1
        if ligne.strip() == b"":
            break
    j = d.index(b"\n", i)
    res = d[i:j].split()
    i = j + 1
    h, w = int(res[1]), int(res[3])
    out = np.zeros((h, w, 4), np.uint8)
    p = i
    for y in range(h):
        if w >= 8 and w < 32768 and d[p] == 2 and d[p + 1] == 2 and (d[p + 2] & 0x80) == 0:  # RLE « nouveau format »
            p += 4
            for k in range(4):
                x = 0
                while x < w:
                    c = d[p]
                    p += 1
                    if c > 128:
                        n = c - 128
                        out[y, x:x + n, k] = d[p]
                        p += 1
                        x += n
                    else:
                        out[y, x:x + c, k] = np.frombuffer(d[p:p + c], np.uint8)
                        p += c
                        x += c
        else:  # non compressé
            out[y] = np.frombuffer(d[p:p + 4 * w], np.uint8).reshape(w, 4)
            p += 4 * w
    e = out[..., 3].astype(int) - 136
    return (out[..., :3] * np.where(out[..., 3] > 0, 2.0 ** e, 0)[..., None]).astype(np.float32)


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
    r = bytearray()
    i, n = 0, len(c)
    while i < n:
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


def ecrire_hdr(chemin, img):
    h, w, _ = img.shape
    px = rgbe(np.clip(img, 0, None))
    with open(chemin, "wb") as f:
        f.write(b"#?RADIANCE\nFORMAT=32-bit_rle_rgbe\n\n")
        f.write(f"-Y {h} +X {w}\n".encode())
        for y in range(h):
            f.write(bytes([2, 2, (w >> 8) & 255, w & 255]))
            for k in range(4):
                f.write(rle_canal(px[y, :, k]))


def reduire(img, largeur):
    h, w, _ = img.shape
    if largeur >= w:
        return img
    nh = largeur // 2
    # moyenne par blocs (fractions de pixels gérées par rééchantillonnage des bornes)
    bx = np.linspace(0, w, largeur + 1)
    by = np.linspace(0, h, nh + 1)
    cx = np.concatenate([np.zeros((h, 1, 3), np.float64), np.cumsum(img.astype(np.float64), axis=1)], axis=1)
    def interp_x(c, pos):
        i0 = np.floor(pos).astype(int).clip(0, w - 1)
        fr = (pos - i0)[None, :, None]
        return c[:, i0, :] * (1 - fr) + c[:, np.minimum(i0 + 1, w), :] * fr
    sx = interp_x(cx, bx)
    img_x = (sx[:, 1:, :] - sx[:, :-1, :]) / (w / largeur)
    cy = np.concatenate([np.zeros((1, largeur, 3), np.float64), np.cumsum(img_x, axis=0)], axis=0)
    i0 = np.floor(by).astype(int).clip(0, h - 1)
    fr = (by - i0)[:, None, None]
    sy = cy[i0, :, :] * (1 - fr) + cy[np.minimum(i0 + 1, h), :, :] * fr
    return ((sy[1:, :, :] - sy[:-1, :, :]) / (h / nh)).astype(np.float32)


if __name__ == "__main__":
    args = [a for a in sys.argv[1:]]
    opts = {}
    for cle in ("--decalage-deg", "--max"):
        if cle in args:
            k = args.index(cle)
            opts[cle] = float(args[k + 1])
            del args[k:k + 2]
    if len(args) < 2:
        sys.exit(__doc__)
    entree, sortie = args[0], args[1]
    largeur = int(args[2]) if len(args) > 2 else 1536
    img = reduire(lire_hdr(entree), largeur)
    if "--decalage-deg" in opts:
        img = np.roll(img, int(round(opts["--decalage-deg"] / 360.0 * img.shape[1])), axis=1)
    if "--max" in opts:
        img = np.minimum(img, opts["--max"])
    ecrire_hdr(sortie, img)
    lum = img.mean(axis=2)
    print(f"{sortie} : {img.shape[1]}×{img.shape[0]}, luminance moy={lum.mean():.2f} pic={lum.max():.1f}")
