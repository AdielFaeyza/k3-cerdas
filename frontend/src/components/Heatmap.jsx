import { useEffect, useRef, useState } from 'react';
import { api } from '../api';

const W_M = Number(import.meta.env.VITE_SITE_WIDTH_M ?? 60);
const H_M = Number(import.meta.env.VITE_SITE_HEIGHT_M ?? 40);
const CANVAS_W = 960;

/** Titik pelanggaran (koordinat lantai, meter) digambar di atas denah /public/denah.png */
export default function Heatmap() {
  const ref = useRef(null);
  const [points, setPoints] = useState([]);
  const [error, setError] = useState(null);

  useEffect(() => {
    api.get('/heatmap').then((r) => setPoints(r.data)).catch((e) => setError(e.message));
  }, []);

  useEffect(() => {
    const c = ref.current;
    const ctx = c.getContext('2d');
    ctx.clearRect(0, 0, c.width, c.height);
    const r = 36;
    points.forEach((p) => {
      const x = (p.x / W_M) * c.width;
      const y = c.height - (p.y / H_M) * c.height;
      const rgb = p.severity === 'high' ? '230,30,30' : '255,170,0';
      const g = ctx.createRadialGradient(x, y, 0, x, y, r);
      g.addColorStop(0, `rgba(${rgb},0.45)`);
      g.addColorStop(1, `rgba(${rgb},0)`);
      ctx.fillStyle = g;
      ctx.fillRect(x - r, y - r, r * 2, r * 2);
    });
  }, [points]);

  return (
    <section className="panel">
      <h2>Spatial Heatmap — bulan ini ({points.length} titik)</h2>
      {error && <p className="error">{error}</p>}
      <div className="heatmap">
        <canvas ref={ref} width={CANVAS_W} height={Math.round((H_M / W_M) * CANVAS_W)} />
      </div>
      <p className="muted">Merah = zona bahaya alat berat · Kuning = APD. Taruh denah pabrik di frontend/public/denah.png.</p>
    </section>
  );
}
