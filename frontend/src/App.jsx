import { useCallback, useEffect, useState } from 'react';
import { api } from './api';
import { echo } from './echo';
import { useToastBatcher } from './useToastBatcher';
import LiveToastManager from './components/LiveToastManager';
import ViolationFeed from './components/ViolationFeed';
import LeaderboardTable from './components/LeaderboardTable';
import Heatmap from './components/Heatmap';

export default function App() {
  const [tab, setTab] = useState('live');
  const [feed, setFeed] = useState([]);
  const [workers, setWorkers] = useState([]);
  const [flashIds, setFlashIds] = useState(new Set());
  const [connected, setConnected] = useState(false);
  const [error, setError] = useState(null);
  const { toasts, push, dismiss } = useToastBatcher();

  const applyScore = useCallback((workerId, score) => {
    setWorkers((ws) => ws.map((w) => (w.id === workerId ? { ...w, current_score: score } : w)));
    setFlashIds((s) => new Set(s).add(workerId));
    setTimeout(
      () =>
        setFlashIds((s) => {
          const n = new Set(s);
          n.delete(workerId);
          return n;
        }),
      1500,
    );
  }, []);

  const upsert = (v) => setFeed((f) => [v, ...f.filter((x) => x.id !== v.id)].slice(0, 100));

  useEffect(() => {
    Promise.all([api.get('/violations'), api.get('/leaderboard')])
      .then(([v, l]) => {
        setFeed(v.data);
        setWorkers(l.data);
      })
      .catch((e) => setError(e.message));

    echo
      .private('k3.dashboard')
      .listen('.violation.detected', (v) => {
        upsert(v);
        push(v);
        if (v.worker) applyScore(v.worker.id, v.worker.current_score);
      })
      .listen('.violation.updated', (v) => setFeed((f) => f.map((x) => (x.id === v.id ? v : x))))
      .listen('.score.changed', (e) => applyScore(e.worker_id, e.current_score));

    const conn = echo.connector.pusher.connection;
    const onState = ({ current }) => setConnected(current === 'connected');
    conn.bind('state_change', onState);
    return () => {
      conn.unbind('state_change', onState);
      echo.leave('k3.dashboard');
    };
  }, [push, applyScore]);

  const review = async (id, action, extra = {}) => {
    try {
      const r = await api.post(`/violations/${id}/review`, { action, ...extra });
      setFeed((f) => f.map((x) => (x.id === id ? r.data : x)));
    } catch (e) {
      alert(`Gagal: ${e.message}`);
    }
  };

  return (
    <div className="app">
      <header>
        <h1>Dasbor K3 Cerdas</h1>
        <nav>
          <button className={tab === 'live' ? 'active' : ''} onClick={() => setTab('live')}>
            Real-Time
          </button>
          <button className={tab === 'heatmap' ? 'active' : ''} onClick={() => setTab('heatmap')}>
            Heatmap
          </button>
        </nav>
        <span className={`conn ${connected ? 'on' : 'off'}`}>{connected ? '● Live' : '○ Terputus'}</span>
      </header>
      {error && <p className="error">Gagal memuat data: {error}</p>}
      {tab === 'live' ? (
        <main className="grid">
          <ViolationFeed items={feed} onReview={review} />
          <LeaderboardTable workers={workers} flashIds={flashIds} />
        </main>
      ) : (
        <main>
          <Heatmap />
        </main>
      )}
      <LiveToastManager toasts={toasts} onDismiss={dismiss} />
    </div>
  );
}
