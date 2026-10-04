import re
from typing import Dict, Iterable, Optional

import cv2

from .types import Box

CODE_RE = re.compile(r"^ID-\d{3,}$")


class Identifier:
    """Membaca QR Code pekerja dari area tubuh. Tag utama di ROMPI (punggung & dada),
    karena QR di helm hilang tepat saat pelanggaran 'tidak pakai helm'."""

    def __init__(self):
        self.qr = cv2.QRCodeDetector()
        self.cache: Dict[int, str] = {}  # track_id -> worker_code

    def identify(self, frame, person: Box) -> Optional[str]:
        tid = person.track_id
        if tid is not None and tid in self.cache:
            return self.cache[tid]
        x1, y1, x2, y2 = (int(v) for v in person.xyxy)
        crop = frame[max(y1, 0):y2, max(x1, 0):x2]
        if crop.size == 0:
            return None
        if crop.shape[1] < 200:  # perbesar agar QR kecil tetap terbaca
            crop = cv2.resize(crop, None, fx=2, fy=2, interpolation=cv2.INTER_CUBIC)
        data, _, _ = self.qr.detectAndDecode(crop)
        code = (data or "").strip()
        if CODE_RE.match(code):
            if tid is not None:
                self.cache[tid] = code
            return code
        return None

    def prune(self, active_ids: Iterable[int]) -> None:
        active = set(active_ids)
        self.cache = {k: v for k, v in self.cache.items() if k in active}
