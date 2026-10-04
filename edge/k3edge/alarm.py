import logging
import time

log = logging.getLogger("alarm")


class Alarm:
    """Peringatan lokal (sirene/lampu) untuk zona bahaya.

    PENTING: sistem visi komputer TIDAK boleh menjadi satu-satunya pemicu
    shutdown mesin. Shutdown wajib lewat interlock keselamatan bersertifikat;
    modul ini hanya memberi peringatan dini.
    """

    def __init__(self, cooldown: float = 3.0):
        self.cooldown = cooldown
        self._last = 0.0

    def trigger(self, camera_code: str, distance_m: float) -> None:
        now = time.monotonic()
        if now - self._last < self.cooldown:
            return
        self._last = now
        log.warning("ZONA BAHAYA di %s: pekerja %.1f m dari alat berat", camera_code, distance_m)
        # TODO: kirim sinyal ke sirene/lampu (GPIO, Modbus, MQTT) sesuai perangkat lapangan
