import { useCallback, useRef, useState } from 'react';

const RANK = { low: 0, medium: 1, high: 2 };

/** Pelanggaran yang datang < windowMs dari toast terakhir digabung (batching) agar tidak alert fatigue. */
export function useToastBatcher(windowMs = 2000, ttl = 6000) {
  const [toasts, setToasts] = useState([]);
  const last = useRef(null);
  const timers = useRef({});

  const dismiss = useCallback((id) => {
    clearTimeout(timers.current[id]);
    delete timers.current[id];
    if (last.current?.id === id) last.current = null;
    setToasts((t) => t.filter((x) => x.id !== id));
  }, []);

  const schedule = useCallback(
    (id) => {
      clearTimeout(timers.current[id]);
      timers.current[id] = setTimeout(() => dismiss(id), ttl);
    },
    [dismiss, ttl],
  );

  const push = useCallback(
    (v) => {
      const now = Date.now();
      if (last.current && now - last.current.at < windowMs) {
        const { id } = last.current;
        last.current.at = now;
        setToasts((t) =>
          t.map((x) =>
            x.id === id
              ? { ...x, items: [v, ...x.items], severity: RANK[v.severity] > RANK[x.severity] ? v.severity : x.severity }
              : x,
          ),
        );
        schedule(id);
        return;
      }
      const id = `${now}-${v.id}`;
      last.current = { id, at: now };
      setToasts((t) => [...t, { id, items: [v], severity: v.severity }]);
      schedule(id);
    },
    [windowMs, schedule],
  );

  return { toasts, push, dismiss };
}
