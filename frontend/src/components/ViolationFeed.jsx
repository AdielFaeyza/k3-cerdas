import { useState } from 'react';

const STATUS = {
  pending: 'Menunggu review',
  confirmed: 'Terkonfirmasi',
  rejected: 'Ditolak',
  unidentified: 'Tanpa ID',
};

export default function ViolationFeed({ items, onReview }) {
  const [lightbox, setLightbox] = useState(null);

  const assign = (v) => {
    const code = window.prompt('Kode pekerja (mis. ID-049):');
    if (code) onReview(v.id, 'assign', { worker_code: code.trim() });
  };
  const reject = (v) => {
    const note = window.prompt('Alasan penolakan (false positive / banding):');
    if (note !== null) onReview(v.id, 'reject', { note });
  };

  return (
    <section className="panel feed">
      <h2>Linimasa Pelanggaran</h2>
      {items.length === 0 && <p className="muted">Belum ada pelanggaran.</p>}
      {items.map((v) => (
        <article key={v.id} className={`card sev-${v.severity} ${v.status === 'rejected' ? 'is-rejected' : ''}`}>
          <img src={v.snapshot_url} alt="Bukti" onClick={() => setLightbox(v.snapshot_url)} />
          <div className="card-body">
            <div className="card-head">
              <strong>{v.type.name}</strong>
              <span className={`badge st-${v.status}`}>{STATUS[v.status]}</span>
            </div>
            <div>
              {v.worker ? `${v.worker.name} (${v.worker.worker_code})` : 'Pekerja tidak teridentifikasi'}
              {v.points_deducted > 0 && <span className="minus"> −{v.points_deducted}</span>}
            </div>
            <div className="muted">
              {v.camera.location} · {new Date(v.detected_at).toLocaleString('id-ID')}
            </div>
            <div className="actions">
              {v.status === 'pending' && <button onClick={() => onReview(v.id, 'confirm')}>Konfirmasi</button>}
              {v.status === 'unidentified' && <button onClick={() => assign(v)}>Tetapkan pekerja</button>}
              {v.status !== 'rejected' && (
                <button className="ghost" onClick={() => reject(v)}>
                  Tolak
                </button>
              )}
            </div>
          </div>
        </article>
      ))}
      {lightbox && (
        <div className="lightbox" onClick={() => setLightbox(null)}>
          <img src={lightbox} alt="Bukti pelanggaran" />
        </div>
      )}
    </section>
  );
}
