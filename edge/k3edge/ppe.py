from typing import Iterable

from .types import Box

# Rentang vertikal (proporsi tinggi tubuh) tempat APD seharusnya berada
REGION = {
    "helmet": (0.0, 0.25),
    "goggles": (0.05, 0.25),
    "vest": (0.2, 0.65),
}
MARGIN = 0.1  # helm sering sedikit di atas kotak "person"

# Kelas "negatif" eksplisit dari model (mis. NO-Hardhat)
NEGATIVE = {"helmet": "no_helmet", "vest": "no_vest", "goggles": "no_goggles"}


def _in_region(person: Box, items: Iterable[Box], kind: str) -> bool:
    x1, y1, x2, y2 = person.xyxy
    h = y2 - y1
    top = y1 + (REGION[kind][0] - MARGIN) * h
    bottom = y1 + REGION[kind][1] * h
    for it in items:
        cx, cy = it.center
        if x1 <= cx <= x2 and top <= cy <= bottom:
            return True
    return False


def wears(person: Box, items: Iterable[Box], kind: str) -> bool:
    """True jika ada APD `kind` yang titik tengahnya berada di area tubuh yang benar."""
    return _in_region(person, items, kind)


def violates(person: Box, positives: Iterable[Box], negatives: Iterable[Box], kind: str, model_has_negative: bool) -> bool:
    """Jika model punya kelas negatif (NO-Hardhat), pelanggaran hanya dihitung bila ADA bukti
    negatif di area tubuh tsb dan tidak ada APD positif -> lebih sedikit false positive.
    Jika tidak ada kelas negatif, pakai logika 'APD tidak terlihat'."""
    has_positive = _in_region(person, positives, kind)
    if model_has_negative:
        return _in_region(person, negatives, kind) and not has_positive
    return not has_positive
