export default function LiveToastManager({ toasts, onDismiss }) {
  return (
    <div className="toasts">
      {toasts.map((t) => {
        const danger = t.severity === 'high';
        const first = t.items[0];
        return (
          <div key={t.id} className={`toast ${danger ? 'toast-red' : 'toast-yellow'}`} onClick={() => onDismiss(t.id)}>
            <strong>{danger ? '⚠ ZONA BAHAYA' : '⛑ Pelanggaran APD'}</strong>
            {t.items.length === 1 ? (
              <p>
                {first.type.name} — {first.worker?.name ?? 'Tidak teridentifikasi'} · {first.camera.location}
              </p>
            ) : (
              <>
                <p>{t.items.length} pelanggaran dalam waktu berdekatan</p>
                <ul>
                  {t.items.slice(0, 3).map((v) => (
                    <li key={v.id}>
                      {v.type.name} — {v.worker?.name ?? 'Tanpa ID'}
                    </li>
                  ))}
                </ul>
              </>
            )}
          </div>
        );
      })}
    </div>
  );
}
