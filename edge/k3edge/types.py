from dataclasses import dataclass
from typing import Optional, Tuple


@dataclass
class Box:
    cls: str
    conf: float
    xyxy: Tuple[float, float, float, float]
    track_id: Optional[int] = None

    @property
    def foot(self) -> Tuple[float, float]:
        """Titik tengah bawah kotak = posisi kaki di lantai."""
        x1, _, x2, y2 = self.xyxy
        return ((x1 + x2) / 2, y2)

    @property
    def center(self) -> Tuple[float, float]:
        x1, y1, x2, y2 = self.xyxy
        return ((x1 + x2) / 2, (y1 + y2) / 2)


# Jenis APD -> kode pelanggaran (harus sama dengan violation_types.code di backend)
PPE_VIOLATION = {
    "helmet": "NO_HELMET",
    "vest": "NO_VEST",
    "goggles": "NO_GOGGLES",
}
DANGER_ZONE = "DANGER_ZONE"
