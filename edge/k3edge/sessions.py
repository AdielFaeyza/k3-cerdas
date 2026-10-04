from dataclasses import dataclass
from typing import Dict, Hashable


@dataclass
class _Session:
    start: float
    last_seen: float
    reported: bool = False


class SessionTracker:
    """Satu pelanggaran berkelanjutan = SATU laporan (bukan tiap 10 detik).

    - Laporan dikirim setelah pelanggaran konsisten >= min_seconds (anti false-positive).
    - Sesi berakhir jika pelanggaran tidak terlihat selama reset_seconds
      (pekerja sudah patuh / keluar frame). Pelanggaran berikutnya = sesi baru.
    """

    def __init__(self, min_seconds: float = 1.5, reset_seconds: float = 30.0):
        self.min_seconds = min_seconds
        self.reset_seconds = reset_seconds
        self.sessions: Dict[Hashable, _Session] = {}

    def observe(self, key: Hashable, now: float) -> bool:
        s = self.sessions.get(key)
        if s is None or now - s.last_seen > self.reset_seconds:
            s = _Session(start=now, last_seen=now)
            self.sessions[key] = s
        s.last_seen = now
        if not s.reported and now - s.start >= self.min_seconds:
            s.reported = True
            return True
        return False

    def prune(self, now: float) -> None:
        self.sessions = {k: s for k, s in self.sessions.items() if now - s.last_seen <= self.reset_seconds}
