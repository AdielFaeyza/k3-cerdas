import math
from typing import Iterable, List, Optional, Sequence, Tuple

import cv2
import numpy as np

from .types import Box


class GroundMapper:
    """Mengubah koordinat piksel ke meter di bidang lantai (homography),
    sehingga radius bahaya akurat di dekat maupun jauh dari kamera."""

    def __init__(self, image_points: Sequence[Sequence[float]], world_points: Sequence[Sequence[float]]):
        self.H = cv2.getPerspectiveTransform(np.float32(image_points), np.float32(world_points))

    def to_world(self, pt: Tuple[float, float]) -> Tuple[float, float]:
        p = np.array([[pt]], dtype=np.float32)
        x, y = cv2.perspectiveTransform(p, self.H)[0][0]
        return float(x), float(y)


def nearest_machine_distance(person: Box, machines: Iterable[Box], mapper: GroundMapper) -> Optional[float]:
    px, py = mapper.to_world(person.foot)
    best: Optional[float] = None
    for m in machines:
        mx, my = mapper.to_world(m.foot)
        d = math.hypot(px - mx, py - my)
        best = d if best is None else min(best, d)
    return best


def people_in_danger(persons: Iterable[Box], machines: List[Box], mapper: GroundMapper, radius_m: float):
    if not machines:
        return []
    out = []
    for p in persons:
        d = nearest_machine_distance(p, machines, mapper)
        if d is not None and d < radius_m:
            out.append((p, d))
    return out
