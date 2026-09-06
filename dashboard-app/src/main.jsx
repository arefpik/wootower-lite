import { createRoot } from 'react-dom/client';
import App from './App.jsx';
import './index.css';

const container = document.getElementById('wootower-dashboard-root');

if (container) {
  createRoot(container).render(<App />);
}
