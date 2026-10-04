import logging
import queue
import threading
import time
import uuid
from datetime import datetime, timezone
from typing import Optional, Tuple

import cv2
import requests

log = logging.getLogger("api")


class ApiClient:
    """Mengirim pelanggaran ke Laravel secara asinkron (tidak memblokir inferensi).
    Gambar di-encode di RAM (cv2.imencode), tidak disimpan ke disk edge.
    event_uuid membuat retry aman (idempoten di backend)."""

    def __init__(self, cfg: dict):
        self.base = cfg["base_url"].rstrip("/")
        self.token = cfg["edge_token"]
        self.timeout = cfg.get("timeout", 5)
        self.q: "queue.Queue[Tuple[dict, bytes]]" = queue.Queue(maxsize=500)
        threading.Thread(target=self._worker, daemon=True).start()

    def report(self, *, camera_code: str, type_code: str, frame, worker_code: Optional[str] = None,
               pos: Optional[Tuple[float, float]] = None) -> None:
        ok, buf = cv2.imencode(".jpg", frame, [cv2.IMWRITE_JPEG_QUALITY, 80])
        if not ok:
            log.error("Gagal encode snapshot")
            return
        payload = {
            "event_uuid": str(uuid.uuid4()),
            "camera_code": camera_code,
            "type_code": type_code,
            "worker_code": worker_code or "",
            "pos_x": "" if pos is None else f"{pos[0]:.2f}",
            "pos_y": "" if pos is None else f"{pos[1]:.2f}",
            "detected_at": datetime.now(timezone.utc).isoformat(),
        }
        try:
            self.q.put_nowait((payload, buf.tobytes()))
        except queue.Full:
            log.error("Antrean penuh, pelanggaran dibuang: %s", payload)

    def _worker(self) -> None:
        while True:
            payload, img = self.q.get()
            for attempt in range(6):
                try:
                    r = requests.post(
                        f"{self.base}/violations",
                        data=payload,
                        files={"snapshot": ("snapshot.jpg", img, "image/jpeg")},
                        headers={"X-Edge-Token": self.token, "Accept": "application/json"},
                        timeout=self.timeout,
                    )
                    if r.status_code < 500:
                        if r.status_code >= 400:
                            log.error("Ditolak API (%s): %s", r.status_code, r.text[:300])
                        else:
                            log.info("Terkirim %s %s", payload["type_code"], payload["worker_code"] or "(tanpa ID)")
                        break
                    log.warning("API error %s, coba lagi", r.status_code)
                except requests.RequestException as e:
                    log.warning("Koneksi API gagal (%s), coba lagi", e)
                time.sleep(min(2 ** attempt, 30))
