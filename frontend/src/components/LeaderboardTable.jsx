import { useEffect, useState } from 'react';

const sortIds = (ws) =>
  [...ws].sort((a, b) => b.current_score - a.current_score || a.name.localeCompare(b.name)).map((w) => w.id);

/** Baris berkedip merah dulu, lalu tabel diurutkan ulang setelah jeda (agar perubahan terlihat). */
export default function LeaderboardTable({ workers, flashIds, delay = 1200 }) {
  const [order, setOrder] = useState([]);

  useEffect(() => {
    if (order.length === 0) {
      setOrder(sortIds(workers));
      return undefined;
    }
    const t = setTimeout(() => setOrder(sortIds(workers)), delay);
    return () => clearTimeout(t);
  }, [workers]); // eslint-disable-line react-hooks/exhaustive-deps

  const byId = Object.fromEntries(workers.map((w) => [w.id, w]));
  const rows = [...order.filter((id) => byId[id]), ...workers.filter((w) => !order.includes(w.id)).map((w) => w.id)];

  return (
    <section className="panel">
      <h2>Peringkat Keselamatan</h2>
      <table className="board">
        <thead>
          <tr>
            <th>#</th>
            <th>Pekerja</th>
            <th>Departemen</th>
            <th>Skor</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((id, i) => {
            const w = byId[id];
            const level = w.current_score >= 100 ? 'perfect' : w.current_score < 50 ? 'critical' : w.current_score < 70 ? 'warn' : '';
            return (
              <tr key={id} className={flashIds.has(id) ? 'flash' : ''}>
                <td>{i + 1}</td>
                <td>
                  {w.name} <span className="muted">{w.worker_code}</span>
                </td>
                <td>{w.department}</td>
                <td>
                  <span className={`score ${level}`}>{w.current_score}</span>
                  {w.current_score >= 100 && ' 🏆'}
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </section>
  );
}
