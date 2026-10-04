import os
import sys
import unittest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from k3edge.ppe import violates, wears
from k3edge.proximity import GroundMapper, people_in_danger
from k3edge.sessions import SessionTracker
from k3edge.types import Box


class TestSessions(unittest.TestCase):
    def test_one_report_per_continuous_violation(self):
        s = SessionTracker(min_seconds=1.5, reset_seconds=30)
        reports = [s.observe((1, "NO_HELMET"), t * 0.2) for t in range(3000)]  # 10 menit
        self.assertEqual(sum(reports), 1)

    def test_new_session_after_compliance(self):
        s = SessionTracker(min_seconds=1.0, reset_seconds=5)
        self.assertFalse(s.observe("k", 0))
        self.assertTrue(s.observe("k", 1.2))
        self.assertFalse(s.observe("k", 2))
        self.assertFalse(s.observe("k", 20))  # sesi baru, belum 1 detik
        self.assertTrue(s.observe("k", 21.5))


class TestPPE(unittest.TestCase):
    person = Box("person", 0.9, (100, 100, 200, 400), 1)

    def test_helmet_on_head(self):
        self.assertTrue(wears(self.person, [Box("helmet", 0.9, (130, 80, 170, 130))], "helmet"))

    def test_helmet_in_hand_is_violation(self):
        self.assertFalse(wears(self.person, [Box("helmet", 0.9, (180, 250, 220, 290))], "helmet"))


class TestNegativeClass(unittest.TestCase):
    person = Box("person", 0.9, (100, 100, 200, 400), 1)
    no_hat = [Box("no_helmet", 0.8, (130, 90, 170, 130))]

    def test_explicit_no_hardhat_is_violation(self):
        self.assertTrue(violates(self.person, [], self.no_hat, "helmet", True))

    def test_nothing_detected_is_not_violation_when_negative_class_exists(self):
        self.assertFalse(violates(self.person, [], [], "helmet", True))

    def test_fallback_without_negative_class(self):
        self.assertTrue(violates(self.person, [], [], "helmet", False))


class TestProximity(unittest.TestCase):
    def test_danger_radius_in_meters(self):
        m = GroundMapper([[0, 0], [100, 0], [100, 100], [0, 100]], [[0, 0], [10, 0], [10, 10], [0, 10]])
        machine = Box("heavy_machine", 0.9, (0, 0, 20, 50))      # kaki (10,50) -> (1,5) m
        near = Box("person", 0.9, (30, 20, 50, 50), 1)           # (40,50) -> (4,5) m: 3 m
        far = Box("person", 0.9, (80, 20, 100, 50), 2)           # (90,50) -> (9,5) m: 8 m
        hits = people_in_danger([near, far], [machine], m, 5.0)
        self.assertEqual([p.track_id for p, _ in hits], [1])
        self.assertAlmostEqual(hits[0][1], 3.0, places=3)


if __name__ == "__main__":
    unittest.main()
