from typing import Dict, List

from .types import Box


class Detector:
    """Pembungkus YOLOv8 + ByteTrack. Satu instance per kamera (state tracker terpisah)."""

    def __init__(self, cfg: dict):
        from ultralytics import YOLO  # import di sini agar unit test tidak butuh ultralytics

        self.model = YOLO(cfg["path"])
        self.conf = cfg.get("confidence", 0.5)
        self.device = cfg.get("device", "cpu")
        # nama kelas model -> kelas logis; satu kelas logis boleh berisi beberapa kelas model
        self.alias: Dict[str, str] = {}
        for logical, names in cfg["classes"].items():
            for name in [names] if isinstance(names, str) else names:
                self.alias[name] = logical

    def detect(self, frame) -> List[Box]:
        r = self.model.track(frame, persist=True, conf=self.conf, device=self.device, verbose=False)[0]
        if r.boxes is None or len(r.boxes) == 0:
            return []
        ids = r.boxes.id.int().tolist() if r.boxes.id is not None else [None] * len(r.boxes)
        out = []
        for xyxy, c, conf, tid in zip(r.boxes.xyxy.tolist(), r.boxes.cls.int().tolist(), r.boxes.conf.tolist(), ids):
            logical = self.alias.get(r.names[c])
            if logical:
                out.append(Box(logical, float(conf), tuple(xyxy), tid))
        return out
