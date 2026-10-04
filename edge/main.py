"""Edge K3 Cerdas: CCTV (RTSP) -> YOLOv8 -> deteksi APD & zona bahaya -> REST API Laravel."""
import argparse
import logging
import threading
import time
from collections import defaultdict

import cv2
import yaml

from k3edge.alarm import Alarm
from k3edge.api_client import ApiClient
from k3edge.detector import Detector
from k3edge.identifier import Identifier
from k3edge.ppe import NEGATIVE, violates
from k3edge.proximity import GroundMapper, people_in_danger
from k3edge.sessions import SessionTracker
from k3edge.types import DANGER_ZONE, PPE_VIOLATION

log = logging.getLogger("edge")
COLORS = {"ok": (60, 200, 60), "bad": (0, 0, 255)}


def annotate(frame, person, label):
    img = frame.copy()
    x1, y1, x2, y2 = (int(v) for v in person.xyxy)
    cv2.rectangle(img, (x1, y1), (x2, y2), COLORS["bad"], 3)
    cv2.putText(img, label, (x1, max(y1 - 10, 20)), cv2.FONT_HERSHEY_SIMPLEX, 0.8, COLORS["bad"], 2)
    cv2.putText(img, time.strftime("%Y-%m-%d %H:%M:%S"), (10, 30), cv2.FONT_HERSHEY_SIMPLEX, 0.8, (255, 255, 255), 2)
    return img


def run_camera(cam: dict, cfg: dict, api: ApiClient, stop: threading.Event, show: bool = False):
    rules = cfg["rules"]
    detector = Detector(cfg["model"])
    identifier = Identifier()
    sessions = SessionTracker(rules["min_violation_seconds"], rules["session_reset_seconds"])
    mapper = GroundMapper(**cam["homography"]) if cam.get("homography") else None
    alarm = Alarm()
    interval = 1.0 / rules.get("target_fps", 5)
    code = cam["code"]
    model_classes = set(cfg["model"]["classes"])

    cap = cv2.VideoCapture(cam["rtsp"])
    cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)
    last = 0.0
    while not stop.is_set():
        ok, frame = cap.read()
        if not ok:
            log.warning("[%s] stream terputus, menyambung ulang...", code)
            cap.release()
            time.sleep(5)
            cap = cv2.VideoCapture(cam["rtsp"])
            continue
        now = time.monotonic()
        if now - last < interval:
            continue
        last = now

        boxes = detector.detect(frame)
        by_cls = defaultdict(list)
        for b in boxes:
            by_cls[b.cls].append(b)
        persons = [p for p in by_cls["person"] if p.track_id is not None]

        violations = defaultdict(list)  # track_id -> [type_code]
        for p in persons:
            for kind in cam.get("checks", []):
                neg = NEGATIVE[kind]
                if violates(p, by_cls[kind], by_cls[neg], kind, neg in model_classes):
                    violations[p.track_id].append(PPE_VIOLATION[kind])
        if mapper:
            for p, dist in people_in_danger(persons, by_cls["heavy_machine"], mapper, rules["danger_radius_m"]):
                alarm.trigger(code, dist)  # peringatan langsung, tanpa menunggu sesi
                violations[p.track_id].append(DANGER_ZONE)

        for p in persons:
            if not violations[p.track_id]:
                continue
            worker_code = identifier.identify(frame, p)  # coba tiap frame, hasil di-cache per track
            for vtype in violations[p.track_id]:
                if sessions.observe((p.track_id, vtype), now):
                    pos = mapper.to_world(p.foot) if mapper else None
                    api.report(camera_code=code, type_code=vtype, frame=annotate(frame, p, vtype),
                               worker_code=worker_code, pos=pos)

        sessions.prune(now)
        identifier.prune(p.track_id for p in persons)

        if show:
            for p in persons:
                bad = bool(violations[p.track_id])
                x1, y1, x2, y2 = (int(v) for v in p.xyxy)
                cv2.rectangle(frame, (x1, y1), (x2, y2), COLORS["bad" if bad else "ok"], 2)
            cv2.imshow(code, frame)
            if cv2.waitKey(1) & 0xFF == ord("q"):
                stop.set()
    cap.release()


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--config", default="config.yaml")
    ap.add_argument("--test-video", help="Ganti sumber kamera pertama dengan file video/webcam (0)")
    ap.add_argument("--show", action="store_true", help="Tampilkan jendela preview (hanya 1 kamera)")
    args = ap.parse_args()

    logging.basicConfig(level=logging.INFO, format="%(asctime)s %(name)s %(levelname)s %(message)s")
    with open(args.config, encoding="utf-8") as f:
        cfg = yaml.safe_load(f)

    cameras = cfg["cameras"]
    if args.test_video:
        cam = dict(cameras[0])
        cam["rtsp"] = int(args.test_video) if args.test_video.isdigit() else args.test_video
        cameras = [cam]

    api = ApiClient(cfg["api"])
    stop = threading.Event()
    if args.show:
        run_camera(cameras[0], cfg, api, stop, show=True)
        return
    threads = [threading.Thread(target=run_camera, args=(c, cfg, api, stop), daemon=True, name=c["code"]) for c in cameras]
    for t in threads:
        t.start()
    log.info("Memantau %d kamera. Ctrl+C untuk berhenti.", len(threads))
    try:
        while any(t.is_alive() for t in threads):
            time.sleep(1)
    except KeyboardInterrupt:
        stop.set()


if __name__ == "__main__":
    main()
