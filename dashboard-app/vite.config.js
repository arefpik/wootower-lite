import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// Built as a single self-contained IIFE bundle (React included) so it can be
// dropped into a wp-admin page with a plain wp_enqueue_script(), no import
// maps or module loading required.
export default defineConfig({
  plugins: [react()],
  // Library mode doesn't get Vite's usual automatic process.env.NODE_ENV
  // replacement, so React's own internal `process.env.NODE_ENV` checks were
  // left in the bundle as a literal reference to the Node.js `process`
  // global — which doesn't exist in a browser, throwing
  // "ReferenceError: process is not defined" the instant the script ran and
  // preventing the app from mounting at all.
  define: {
    'process.env.NODE_ENV': JSON.stringify('production'),
  },
  build: {
    outDir: 'build',
    emptyOutDir: true,
    lib: {
      entry: 'src/main.jsx',
      name: 'WooTowerDashboard',
      formats: ['iife'],
      fileName: () => 'wootower-dashboard.js',
    },
    rollupOptions: {
      output: {
        assetFileNames: 'wootower-dashboard.[ext]',
      },
    },
  },
});
